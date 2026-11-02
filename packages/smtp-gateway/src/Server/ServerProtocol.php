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
use PHPdot\Mail\Gateway\DataType\Enum\SessionState;
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
    /** Longest command line, CRLF excluded (RFC 5321 Section 4.5.3.1.4). */
    private const int MAX_COMMAND_LINE = 510;

    /** Longest AUTH line or SASL response, CRLF excluded (RFC 4954 Section 4). */
    private const int MAX_AUTH_LINE = 12288;

    /** Longest DATA line kept when no message size limit is set. */
    private const int MAX_DATA_LINE = 28_311_552;

    /** @var list<string> Reply codes counted toward the bad-command limit. */
    private const array BAD_REPLY_CODES = ['500', '501', '503', '504', '530', '535'];

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
    private bool $dataTooLarge = false;
    private string $dataBuffer = '';
    private int $dataSize = 0;
    private int $badCommandCount = 0;
    private string|null $authUser = null;
    private bool $tlsActive = false;
    private bool $tlsPending = false;

    /** @var list<AuthMechanism> Mechanisms offered in EHLO and accepted by AUTH. */
    private readonly array $authMechanisms;

    private AuthMechanism|null $awaitingSasl = null;
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
        private readonly string $clientIp = '',
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
        if ($this->tlsPending || $this->stateMachine->state() === SessionState::Closing) {
            return [];
        }

        $this->lineParser->append($bytes);
        $responses = [];

        while (!$this->tlsPending && $this->stateMachine->state() !== SessionState::Closing) {
            $line = $this->lineParser->next($this->inData ? $this->maxDataLine() : self::MAX_AUTH_LINE);

            if ($line === null) {
                break;
            }

            if ($this->inData) {
                $reply = $line === false ? $this->dropOversizeData() : $this->processDataLine($line);
                if ($reply !== null) {
                    $responses[] = $reply;
                }
                continue;
            }

            $reply = $line === false
                ? $this->replyBuilder->syntaxError('Line too long')
                : $this->processCommandLine($line);
            $responses[] = $this->countBad($reply);
        }

        if ($this->tlsPending) {
            $this->lineParser->reset();
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
        return $this->tlsPending;
    }

    /**
     * Notify the protocol that TLS upgrade completed.
     */
    public function tlsUpgraded(): void
    {
        $this->tlsActive = true;
        $this->tlsPending = false;
        $this->lineParser->reset();
        $this->clearSasl();
        $this->resetTransactionState();
    }

    public function isInData(): bool
    {
        return $this->inData;
    }

    public function state(): SessionState
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
        if (strpbrk($line, "\r\n") !== false) {
            $this->clearSasl();
            return $this->replyBuilder->syntaxError('Bare CR or LF not allowed');
        }

        if ($this->awaitingSasl !== null) {
            return $this->processSaslResponse($line);
        }

        if (strlen($line) > self::MAX_COMMAND_LINE && strncasecmp($line, 'AUTH ', 5) !== 0) {
            return $this->replyBuilder->syntaxError('Line too long');
        }

        try {
            $command = $this->commandParser->parse($line);
        } catch (ProtocolException) {
            return $this->replyBuilder->syntaxError();
        }

        if (!$this->stateMachine->isAllowed($command->verb)) {
            return $this->handleDisallowedCommand($command);
        }

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

    /**
     * Count a failing reply toward the bad-command limit, closing the session with 421 once it is reached.
     */
    private function countBad(string $reply): string
    {
        if (!in_array(substr($reply, 0, 3), self::BAD_REPLY_CODES, true)) {
            return $reply;
        }

        $this->badCommandCount++;

        if ($this->maxBadCommands > 0 && $this->badCommandCount >= $this->maxBadCommands) {
            $this->stateMachine->closing();
            return $this->replyBuilder->serviceUnavailable('Too many bad commands, closing');
        }

        return $reply;
    }

    private function handleDisallowedCommand(ParsedCommand $command): string
    {
        if ($command->verb === SmtpCommand::MailFrom && $this->stateMachine->state() === SessionState::Transaction) {
            return $this->replyBuilder->badSequence('Sender already specified');
        }

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

        $this->regreet();

        return $this->replyBuilder->ehlo($this->buildCapabilities());
    }

    private function handleHelo(ParsedCommand $command): string
    {
        $hostname = $command->argument;
        if ($hostname === '') {
            return $this->replyBuilder->paramError('HELO requires a hostname');
        }

        $this->regreet();

        return $this->replyBuilder->ok($this->hostname . ' Hello ' . $hostname);
    }

    /**
     * EHLO or HELO resets the transaction; a signed-in session stays signed in, as RFC 4954 Section 4 leaves it.
     */
    private function regreet(): void
    {
        $this->resetTransactionState();

        if ($this->isAuthenticated()) {
            $this->stateMachine->authenticated();
        } elseif ($this->tlsActive) {
            $this->stateMachine->regreeted();
        } else {
            $this->stateMachine->greeted();
        }
    }

    private function handleStartTls(): string
    {
        if ($this->tlsActive) {
            return $this->replyBuilder->badSequence('TLS already active');
        }

        $this->stateMachine->secured();
        $this->tlsPending = true;

        return $this->replyBuilder->readyForTls();
    }

    private function handleAuth(ParsedCommand $command): string
    {

        $parts = explode(' ', $command->argument, 2);
        $mechanismStr = strtoupper(trim($parts[0]));
        $initialResponse = isset($parts[1]) ? trim($parts[1]) : null;

        if ($initialResponse === '=') {
            $initialResponse = '';
        }

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
        if ($initialResponse === null) {
            $this->awaitingSasl = AuthMechanism::Plain;
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
        $this->awaitingSasl = AuthMechanism::Login;

        if ($initialResponse !== null && $initialResponse !== '') {
            $this->loginUsername = $initialResponse;
            $this->awaitingLoginPassword = true;
            return $this->replyBuilder->authChallenge(base64_encode('Password:'));
        }

        return $this->replyBuilder->authChallenge(base64_encode('Username:'));
    }

    /**
     * Answer the client's reply to a 334 challenge for the mechanism it started.
     */
    private function processSaslResponse(string $line): string
    {
        $mechanism = $this->awaitingSasl;
        $response = trim($line);

        if ($response === '*') {
            $this->clearSasl();
            return $this->replyBuilder->reply(ReplyCode::SyntaxParamError, 'Authentication cancelled');
        }

        if ($mechanism === AuthMechanism::Login && !$this->awaitingLoginPassword) {
            $this->loginUsername = $response;
            $this->awaitingLoginPassword = true;
            return $this->replyBuilder->authChallenge(base64_encode('Password:'));
        }

        $this->awaitingSasl = null;

        return match ($mechanism) {
            AuthMechanism::Plain => $this->handleAuthPlain($response),
            AuthMechanism::XOAuth2 => $this->handleAuthXOAuth2($response),
            default => $this->processLoginPassword($response),
        };
    }

    private function clearSasl(): void
    {
        $this->awaitingSasl = null;
        $this->awaitingLoginPassword = false;
        $this->loginUsername = '';
    }

    private function processLoginPassword(string $line): string
    {
        $this->awaitingLoginPassword = false;

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
        if ($initialResponse === null) {
            $this->awaitingSasl = AuthMechanism::XOAuth2;
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

        $path = CommandParser::parsePath($command->argument, 'FROM');

        if ($path === null) {
            return $this->replyBuilder->paramError('MAIL requires FROM:<address>');
        }

        [$address, $parameters] = $path;
        $declaredSize = CommandParser::extractSize($parameters);
        $bodyType = CommandParser::extractBodyType($parameters);

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
        $path = CommandParser::parsePath($command->argument, 'TO');

        if ($path === null) {
            return $this->replyBuilder->paramError('RCPT requires TO:<address>');
        }

        $address = $path[0];

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
        $this->dataTooLarge = false;
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

        if ($this->dataTooLarge) {
            return null;
        }

        if (str_starts_with($line, '.')) {
            $line = substr($line, 1);
        }

        if (strpbrk($line, "\r\n") !== false) {
            $line = strtr($line, ["\r" => "\r\n", "\n" => "\r\n"]);
        }

        $lineLen = strlen($line) + 2;
        if ($this->maxMessageSize > 0 && ($this->dataSize + $lineLen) > $this->maxMessageSize) {
            return $this->dropOversizeData();
        }

        $this->dataBuffer .= $line . "\r\n";
        $this->dataSize += $lineLen;

        return null;
    }

    /**
     * Past the size limit the rest of the message is read and dropped up to its final dot, then refused (RFC 1870).
     */
    private function dropOversizeData(): null
    {
        $this->dataTooLarge = true;
        $this->dataBuffer = '';
        $this->dataSize = 0;

        return null;
    }

    private function maxDataLine(): int
    {
        return $this->maxMessageSize > 0 ? $this->maxMessageSize : self::MAX_DATA_LINE;
    }

    private function completeData(): string
    {
        if ($this->dataTooLarge) {
            $this->stateMachine->dataComplete();
            $this->resetTransactionState();
            return $this->replyBuilder->messageTooLarge($this->maxMessageSize);
        }

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
            clientIp: $this->clientIp,
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
        $this->dataTooLarge = false;
    }

    private function generateMessageId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
