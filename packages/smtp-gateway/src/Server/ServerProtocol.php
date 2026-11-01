<?php

declare(strict_types=1);

/**
 * Orchestrates the full server-side SMTP protocol pipeline.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\Server;

use PHPdot\Mail\Gateway\Auth\SaslDecoder;
use PHPdot\Mail\Gateway\DataType\DTO\AuthCredentials;
use PHPdot\Mail\Gateway\DataType\DTO\ParsedCommand;
use PHPdot\Mail\Gateway\DataType\Enum\AuthMechanism;
use PHPdot\Mail\Gateway\DataType\Enum\ReplyCode;
use PHPdot\Mail\Gateway\DataType\Enum\SmtpCommand;
use PHPdot\Mail\Gateway\Exception\AuthenticationException;
use PHPdot\Mail\Gateway\Exception\InvalidSaslSyntaxException;
use PHPdot\Mail\Gateway\Exception\ProtocolException;
use PHPdot\Mail\Gateway\Protocol\CommandParser;
use PHPdot\Mail\Gateway\Protocol\LineParser;
use PHPdot\Mail\Gateway\Protocol\ReplyBuilder;
use PHPdot\Mail\Gateway\Protocol\StateMachine;
use PHPdot\Mail\Gateway\Server\Event\AuthEvent;
use PHPdot\Mail\Gateway\Server\Event\Event;
use PHPdot\Mail\Gateway\Server\Event\MailFromEvent;
use PHPdot\Mail\Gateway\Server\Event\MessageEvent;
use PHPdot\Mail\Gateway\Server\Event\RcptToEvent;

/**
 * Orchestrates the full server-side SMTP protocol pipeline.
 *
 * Handles:
 * - EHLO/HELO negotiation with capability advertising
 * - STARTTLS upgrade
 * - AUTH (PLAIN, LOGIN, XOAUTH2) with multi-step LOGIN flow
 * - MAIL FROM / RCPT TO / DATA transaction
 * - DATA dot-unstuffing and message assembly
 * - PIPELINING (multiple commands in single TCP write)
 * - RSET transaction reset
 * - Bad command counting and forced disconnect
 */
final class ServerProtocol
{
    private readonly LineParser $lineParser;
    private readonly CommandParser $commandParser;
    private readonly ReplyBuilder $replyBuilder;
    private readonly EventEmitter $emitter;
    private readonly StateMachine $stateMachine;
    private readonly SaslDecoder $saslDecoder;

    private int $maxMessageSize;
    private int $maxRecipients;
    private int $maxBadCommands;

    private string|null $sender = null;
    /** @var list<string> */
    private array $recipients = [];
    private bool $inData = false;
    private string $dataBuffer = '';
    private int $dataSize = 0;
    private int $badCommandCount = 0;
    private string|null $authUser = null;
    private bool $tlsActive = false;

    /** @var list<AuthMechanism> Mechanisms offered in EHLO and accepted by AUTH. */
    private readonly array $authMechanisms;

    private bool $awaitingLoginUsername = false;
    private bool $awaitingLoginPassword = false;
    private string $loginUsername = '';

    /** @var list<string> Capabilities to advertise */
    private array $capabilities = [];

    private Event|null $lastEmittedEvent = null;

    /**
     * @param list<AuthMechanism>|null $authMechanisms Mechanisms to offer; null offers all the engine implements
     */
    public function __construct(
        private readonly string $hostname = 'localhost',
        int $maxMessageSize = 26214400,
        int $maxRecipients = 100,
        int $maxBadCommands = 20,
        bool $tlsRequired = true,
        bool $authRequired = true,
        StateMachine|null $stateMachine = null,
        bool $implicitTls = false,
        array|null $authMechanisms = null,
    ) {
        $this->authMechanisms = $authMechanisms ?? AuthMechanism::cases();
        $this->lineParser = new LineParser();
        $this->commandParser = new CommandParser();
        $this->replyBuilder = new ReplyBuilder($hostname);
        $this->emitter = new EventEmitter();
        $this->saslDecoder = new SaslDecoder();
        $this->stateMachine = $stateMachine ?? new StateMachine(
            tlsRequired: $tlsRequired,
            authRequired: $authRequired,
        );
        $this->maxMessageSize = $maxMessageSize;
        $this->maxRecipients = $maxRecipients;
        $this->maxBadCommands = $maxBadCommands;

        if ($implicitTls) {
            $this->tlsActive = true;
        }
    }

    /**
     * Register an event handler.
     *
     * @template T of Event
     *
     * @param class-string<T> $eventClass
     * @param callable(T): void $handler
     */
    public function on(string $eventClass, callable $handler): void
    {
        $this->emitter->on($eventClass, $handler);
    }

    /**
     * Generate the server greeting. Send this to the client on connect.
     */
    public function greeting(string $text = ''): string
    {
        return $this->replyBuilder->greeting($text);
    }

    /**
     * Feed incoming client bytes. Returns response bytes to send back.
     *
     * @return list<string>
     */
    public function onData(string $bytes): array
    {
        $responses = [];
        $data = $bytes;

        while ($data !== '') {
            if ($this->inData) {
                $lines = $this->lineParser->feedData($data);
                $data = '';

                foreach ($lines as $i => $line) {
                    $result = $this->processDataLine($line);
                    if ($result !== null) {
                        $responses[] = $result;
                    }

                    if (!$this->inData) {
                        $remaining = array_slice($lines, $i + 1);
                        if ($remaining !== []) {
                            $data = implode("\r\n", $remaining) . "\r\n";
                            $pending = $this->lineParser->pending();
                            if ($pending !== '') {
                                $data .= $pending;
                                $this->lineParser->reset();
                            }
                        }
                        break;
                    }
                }
            } else {
                $lines = $this->lineParser->feed($data);
                $data = '';

                foreach ($lines as $i => $line) {
                    $responses[] = $this->processCommandLine($line);

                    if ($this->inData) {
                        $remaining = array_slice($lines, $i + 1);
                        if ($remaining !== []) {
                            $data = implode("\r\n", $remaining) . "\r\n";
                            $pending = $this->lineParser->pending();
                            if ($pending !== '') {
                                $data .= $pending;
                                $this->lineParser->reset();
                            }
                        }
                        break;
                    }
                }
            }
        }

        return $responses;
    }

    /**
     * Whether a TLS upgrade is needed after the last response.
     *
     * The caller must upgrade the socket to TLS after sending the 220 response,
     * then call tlsUpgraded() to update state.
     */
    public function needsTlsUpgrade(): bool
    {
        return $this->tlsActive === false
            && $this->stateMachine->state() === \PHPdot\Mail\Gateway\DataType\Enum\SessionState::Secured;
    }

    /**
     * Notify the protocol that TLS upgrade completed.
     */
    public function tlsUpgraded(): void
    {
        $this->tlsActive = true;
        $this->lineParser->reset();
    }

    public function isInData(): bool
    {
        return $this->inData;
    }

    public function state(): \PHPdot\Mail\Gateway\DataType\Enum\SessionState
    {
        return $this->stateMachine->state();
    }

    public function isAuthenticated(): bool
    {
        return $this->authUser !== null;
    }

    public function authUser(): string|null
    {
        return $this->authUser;
    }

    public function lastEvent(): Event|null
    {
        return $this->lastEmittedEvent;
    }

    /**
     * @phpstan-impure Modifies session state: inData, sender, recipients, authUser, etc.
     */
    private function processCommandLine(string $line): string
    {

        if ($this->awaitingLoginUsername) {
            return $this->processLoginUsername($line);
        }
        if ($this->awaitingLoginPassword) {
            return $this->processLoginPassword($line);
        }

        try {
            $command = $this->commandParser->parse($line);
        } catch (ProtocolException) {
            $this->badCommandCount++;
            if ($this->badCommandCount >= $this->maxBadCommands) {
                return $this->replyBuilder->serviceUnavailable('Too many unrecognized commands');
            }
            return $this->replyBuilder->syntaxError();
        }

        if (!$this->stateMachine->isAllowed($command->verb)) {
            return $this->handleDisallowedCommand($command);
        }

        $this->badCommandCount = 0;

        return match ($command->verb) {
            SmtpCommand::Ehlo => $this->handleEhlo($command),
            SmtpCommand::Helo => $this->handleHelo($command),
            SmtpCommand::StartTls => $this->handleStartTls(),
            SmtpCommand::Auth => $this->handleAuth($command),
            SmtpCommand::MailFrom => $this->handleMailFrom($command),
            SmtpCommand::RcptTo => $this->handleRcptTo($command),
            SmtpCommand::Data => $this->handleData(),
            SmtpCommand::Rset => $this->handleRset(),
            SmtpCommand::Noop => $this->replyBuilder->ok(),
            SmtpCommand::Quit => $this->handleQuit(),
        };
    }

    private function handleDisallowedCommand(ParsedCommand $command): string
    {

        if ($command->verb === SmtpCommand::Auth && $this->isAuthenticated()) {
            return $this->replyBuilder->badSequence('Already authenticated');
        }

        if ($command->verb === SmtpCommand::StartTls && $this->tlsActive) {
            return $this->replyBuilder->badSequence('TLS already active');
        }

        if ($command->verb === SmtpCommand::MailFrom && !$this->isAuthenticated()) {
            return $this->replyBuilder->authRequired();
        }

        if ($command->verb === SmtpCommand::Auth && !$this->tlsActive) {
            return $this->replyBuilder->reply(
                ReplyCode::AuthRequired,
                'Must issue STARTTLS first',
            );
        }

        return $this->replyBuilder->badSequence();
    }

    private function handleEhlo(ParsedCommand $command): string
    {
        $hostname = $command->argument;
        if ($hostname === '') {
            return $this->replyBuilder->paramError('EHLO requires a hostname');
        }

        $this->resetTransactionState();

        $extensions = $this->buildCapabilities();

        if ($this->tlsActive) {
            $this->stateMachine->regreeted();
        } else {
            $this->stateMachine->greeted();
        }

        return $this->replyBuilder->ehlo($extensions);
    }

    private function handleHelo(ParsedCommand $command): string
    {
        $hostname = $command->argument;
        if ($hostname === '') {
            return $this->replyBuilder->paramError('HELO requires a hostname');
        }

        $this->resetTransactionState();

        if ($this->tlsActive) {
            $this->stateMachine->regreeted();
        } else {
            $this->stateMachine->greeted();
        }

        return $this->replyBuilder->ok($this->hostname . ' Hello ' . $hostname);
    }

    private function handleStartTls(): string
    {
        if ($this->tlsActive) {
            return $this->replyBuilder->badSequence('TLS already active');
        }

        $this->stateMachine->secured();

        return $this->replyBuilder->readyForTls();
    }

    private function handleAuth(ParsedCommand $command): string
    {

        $parts = explode(' ', $command->argument, 2);
        $mechanismStr = strtoupper(trim($parts[0]));
        $initialResponse = isset($parts[1]) ? trim($parts[1]) : null;

        $mechanism = AuthMechanism::fromString($mechanismStr);

        if ($mechanism === null || !in_array($mechanism, $this->authMechanisms, true)) {
            return $this->replyBuilder->reply(ReplyCode::ParamNotImplemented, 'Unsupported authentication mechanism');
        }

        return match ($mechanism) {
            AuthMechanism::Plain => $this->handleAuthPlain($initialResponse),
            AuthMechanism::Login => $this->handleAuthLogin($initialResponse),
            AuthMechanism::XOAuth2 => $this->handleAuthXOAuth2($initialResponse),
        };
    }

    private function handleAuthPlain(string|null $initialResponse): string
    {
        if ($initialResponse === null || $initialResponse === '') {

            return $this->replyBuilder->authChallenge('');
        }

        if ($initialResponse === '*') {
            return $this->replyBuilder->reply(ReplyCode::SyntaxParamError, 'Authentication cancelled');
        }

        try {
            $credentials = $this->saslDecoder->decodePlain($initialResponse);
            return $this->authenticateUser($credentials);
        } catch (InvalidSaslSyntaxException $e) {
            return $this->replyBuilder->reply(ReplyCode::SyntaxParamError, $e->getMessage());
        } catch (AuthenticationException $e) {
            return $this->replyBuilder->authFailed($e->getMessage());
        }
    }

    private function handleAuthLogin(string|null $initialResponse): string
    {
        if ($initialResponse !== null && $initialResponse !== '') {

            $this->loginUsername = $initialResponse;
            $this->awaitingLoginPassword = true;
            return $this->replyBuilder->authChallenge(base64_encode('Password:'));
        }

        $this->awaitingLoginUsername = true;
        return $this->replyBuilder->authChallenge(base64_encode('Username:'));
    }

    private function processLoginUsername(string $line): string
    {
        $this->awaitingLoginUsername = false;

        if (trim($line) === '*') {
            return $this->replyBuilder->reply(ReplyCode::SyntaxParamError, 'Authentication cancelled');
        }

        $this->loginUsername = trim($line);
        $this->awaitingLoginPassword = true;
        return $this->replyBuilder->authChallenge(base64_encode('Password:'));
    }

    private function processLoginPassword(string $line): string
    {
        $this->awaitingLoginPassword = false;

        if (trim($line) === '*') {
            $this->loginUsername = '';
            return $this->replyBuilder->reply(ReplyCode::SyntaxParamError, 'Authentication cancelled');
        }

        try {
            $credentials = $this->saslDecoder->decodeLogin($this->loginUsername, trim($line));
            $this->loginUsername = '';
            return $this->authenticateUser($credentials);
        } catch (InvalidSaslSyntaxException $e) {
            $this->loginUsername = '';
            return $this->replyBuilder->reply(ReplyCode::SyntaxParamError, $e->getMessage());
        } catch (AuthenticationException $e) {
            $this->loginUsername = '';
            return $this->replyBuilder->authFailed($e->getMessage());
        }
    }

    private function handleAuthXOAuth2(string|null $initialResponse): string
    {
        if ($initialResponse === null || $initialResponse === '') {
            return $this->replyBuilder->authChallenge('');
        }

        if ($initialResponse === '*') {
            return $this->replyBuilder->reply(ReplyCode::SyntaxParamError, 'Authentication cancelled');
        }

        try {
            $credentials = $this->saslDecoder->decodeXOAuth2($initialResponse);
            return $this->authenticateUser($credentials);
        } catch (InvalidSaslSyntaxException $e) {
            return $this->replyBuilder->reply(ReplyCode::SyntaxParamError, $e->getMessage());
        } catch (AuthenticationException $e) {
            return $this->replyBuilder->authFailed($e->getMessage());
        }
    }

    private function authenticateUser(AuthCredentials $credentials): string
    {
        $event = new AuthEvent($credentials);
        $this->lastEmittedEvent = $event;
        $this->emitter->emit($event);

        if (!$event->isHandled()) {

            return $this->replyBuilder->authFailed('Authentication not configured');
        }

        if ($event->isAccepted()) {
            $this->authUser = $credentials->username;
            $this->stateMachine->authenticated();
            return $this->replyBuilder->authSuccess();
        }

        return $this->replyBuilder->authFailed($event->getRejectText());
    }

    private function handleMailFrom(ParsedCommand $command): string
    {

        $argument = $command->argument;

        if (stripos($argument, 'FROM:') !== 0) {
            return $this->replyBuilder->paramError('MAIL requires FROM:<address>');
        }

        $address = CommandParser::extractAddress($argument);
        $declaredSize = CommandParser::extractSize($argument);
        $bodyType = CommandParser::extractBodyType($argument);

        if ($declaredSize !== null && $declaredSize > $this->maxMessageSize) {
            return $this->replyBuilder->messageTooLarge($this->maxMessageSize);
        }

        $event = new MailFromEvent($address, $declaredSize, $bodyType);
        $this->lastEmittedEvent = $event;
        $this->emitter->emit($event);

        if ($event->isHandled() && !$event->isAccepted()) {
            return $this->replyBuilder->reply($event->getRejectCode(), $event->getRejectText());
        }

        $this->sender = $address;
        $this->recipients = [];
        $this->stateMachine->transaction();

        return $this->replyBuilder->ok('OK');
    }

    private function handleRcptTo(ParsedCommand $command): string
    {
        $argument = $command->argument;

        if (stripos($argument, 'TO:') !== 0) {
            return $this->replyBuilder->paramError('RCPT requires TO:<address>');
        }

        $address = CommandParser::extractAddress($argument);

        if ($address === '') {
            return $this->replyBuilder->paramError('Empty recipient address');
        }

        if (count($this->recipients) >= $this->maxRecipients) {
            return $this->replyBuilder->reply(
                ReplyCode::InsufficientStorage,
                'Too many recipients (max ' . $this->maxRecipients . ')',
            );
        }

        $event = new RcptToEvent($address);
        $this->lastEmittedEvent = $event;
        $this->emitter->emit($event);

        if ($event->isHandled() && !$event->isAccepted()) {
            return $this->replyBuilder->reply($event->getRejectCode(), $event->getRejectText());
        }

        $this->recipients[] = $address;

        return $this->replyBuilder->ok('OK');
    }

    private function handleData(): string
    {
        if ($this->recipients === []) {
            return $this->replyBuilder->badSequence('No valid recipients');
        }

        $this->inData = true;
        $this->dataBuffer = '';
        $this->dataSize = 0;
        $this->stateMachine->data();

        return $this->replyBuilder->startData();
    }

    /**
     * Process a line in DATA mode.
     *
     * RFC 5321 Section 4.5.2: Transparency — dot-stuffing.
     * A line consisting of only "." terminates the message.
     * Lines starting with "." have the leading dot removed.
     *
     * @phpstan-impure Modifies inData, dataBuffer, dataSize, and triggers state transitions.
     */
    private function processDataLine(string $line): string|null
    {

        if ($line === '.') {
            return $this->completeData();
        }

        if (str_starts_with($line, '.')) {
            $line = substr($line, 1);
        }

        $lineLen = strlen($line) + 2;
        if ($this->maxMessageSize > 0 && ($this->dataSize + $lineLen) > $this->maxMessageSize) {

            $this->inData = false;
            $this->dataBuffer = '';
            $this->dataSize = 0;
            $this->stateMachine->dataComplete();
            $this->resetTransactionState();
            return $this->replyBuilder->messageTooLarge($this->maxMessageSize);
        }

        $this->dataBuffer .= $line . "\r\n";
        $this->dataSize += $lineLen;

        return null;
    }

    private function completeData(): string
    {
        $this->inData = false;
        $message = $this->dataBuffer;
        $size = $this->dataSize;
        $this->dataBuffer = '';
        $this->dataSize = 0;

        $messageId = $this->generateMessageId();

        $event = new MessageEvent(
            id: $messageId,
            sender: $this->sender ?? '',
            recipients: $this->recipients,
            message: $message,
            size: $size,
            authUser: $this->authUser ?? '',
            clientIp: '',
        );
        $this->lastEmittedEvent = $event;
        $this->emitter->emit($event);

        $this->stateMachine->dataComplete();
        $this->resetTransactionState();

        if ($event->isHandled() && !$event->isAccepted()) {
            return $this->replyBuilder->reply($event->getRejectCode(), $event->getRejectText());
        }

        return $this->replyBuilder->queued($messageId);
    }

    private function handleRset(): string
    {
        $this->resetTransactionState();
        $this->stateMachine->reset();
        return $this->replyBuilder->ok();
    }

    private function handleQuit(): string
    {
        $this->stateMachine->closing();
        return $this->replyBuilder->bye();
    }

    /**
     * @return list<string>
     */
    private function buildCapabilities(): array
    {
        $caps = [];

        if (!$this->tlsActive) {
            $caps[] = 'STARTTLS';
        }

        if ($this->tlsActive || !$this->stateMachine->isTlsRequired()) {
            if (!$this->isAuthenticated()) {
                $caps[] = 'AUTH ' . implode(' ', array_map(
                    static fn(AuthMechanism $m): string => $m->value,
                    $this->authMechanisms,
                ));
            }
        }

        if ($this->maxMessageSize > 0) {
            $caps[] = 'SIZE ' . $this->maxMessageSize;
        }

        $caps[] = '8BITMIME';
        $caps[] = 'PIPELINING';
        $caps[] = 'ENHANCEDSTATUSCODES';

        foreach ($this->capabilities as $cap) {
            $caps[] = $cap;
        }

        return $caps;
    }

    /**
     * Add a custom capability to advertise in EHLO.
     */
    public function addCapability(string $capability): void
    {
        $this->capabilities[] = $capability;
    }

    private function resetTransactionState(): void
    {
        $this->sender = null;
        $this->recipients = [];
        $this->dataBuffer = '';
        $this->dataSize = 0;
        $this->inData = false;
    }

    private function generateMessageId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
