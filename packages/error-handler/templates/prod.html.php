<?php
/**
 * @var \PHPdot\ErrorHandler\Context\ErrorContext $errorContext
 */

$titles = [
    400 => 'Bad Request',
    401 => 'Unauthorized',
    403 => 'Forbidden',
    404 => 'Page Not Found',
    405 => 'Method Not Allowed',
    422 => 'Unprocessable Entity',
    429 => 'Too Many Requests',
    500 => 'Server Error',
    502 => 'Bad Gateway',
    503 => 'Service Unavailable',
];
$title = $titles[$errorContext->statusCode] ?? 'Error';

$messages = [
    400 => 'The request could not be processed.',
    401 => 'You need to sign in to access this page.',
    403 => 'You don\'t have permission to access this page.',
    404 => 'The page you\'re looking for doesn\'t exist.',
    405 => 'This action is not supported.',
    422 => 'The submitted data is invalid.',
    429 => 'You\'re making too many requests. Please slow down.',
    500 => 'Something went wrong on our end. We\'ve been notified and are working on it.',
    503 => 'We\'re temporarily down for maintenance. Please check back soon.',
];
$message = $messages[$errorContext->statusCode] ?? 'An unexpected error occurred.';
$escape = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $errorContext->statusCode ?> &middot; <?= $escape($title) ?></title>
    <style>
        :root {
            --bg: #1f2a3d;
            --surface: #26334a;
            --line: #35455f;
            --text: #eef2f8;
            --dim: #9fb0c7;
            --accent: #7aa7d9;
            --font: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            --mono: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body {
            font-family: var(--font);
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1.5rem;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }
        .panel { width: 100%; max-width: 30rem; }
        .status {
            display: inline-block;
            font: 600 0.6875rem/1 var(--mono);
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--dim);
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 0.375rem 0.75rem;
            margin-bottom: 1.5rem;
        }
        h1 { font-size: 1.375rem; font-weight: 600; letter-spacing: -0.01em; margin-bottom: 0.5rem; }
        .message { color: var(--dim); font-size: 0.9375rem; }
        .reference {
            margin-top: 1.75rem;
            padding: 0.875rem 1rem;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 8px;
        }
        .reference-label {
            display: block;
            font-size: 0.6875rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--dim);
            margin-bottom: 0.375rem;
        }
        .reference code {
            font: 400 0.8125rem/1.5 var(--mono);
            color: var(--text);
            user-select: all;
            word-break: break-all;
        }
        .actions { margin-top: 1.75rem; }
        .home {
            display: inline-block;
            padding: 0.5625rem 1.125rem;
            border: 1px solid var(--line);
            border-radius: 6px;
            color: var(--text);
            font-size: 0.875rem;
            text-decoration: none;
            transition: border-color 0.15s ease, color 0.15s ease;
        }
        .home:hover, .home:focus-visible { border-color: var(--accent); color: var(--accent); }
        @media (max-width: 30rem) {
            h1 { font-size: 1.1875rem; }
        }
    </style>
</head>
<body>
    <main class="panel">
        <span class="status">Error <?= $errorContext->statusCode ?></span>
        <h1><?= $escape($title) ?></h1>
        <p class="message"><?= $escape($message) ?></p>
<?php if ($errorContext->traceId !== null): ?>
        <div class="reference">
            <span class="reference-label">Reference</span>
            <code><?= $escape($errorContext->traceId) ?></code>
        </div>
<?php endif; ?>
        <div class="actions">
            <a href="/" class="home">Return home</a>
        </div>
    </main>
</body>
</html>
