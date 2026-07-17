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
    <title><?= $errorContext->statusCode ?> - <?= $escape($title) ?></title>
    <style>
        :root { --bg: #0f172a; --text: #e2e8f0; --dim: #94a3b8; --accent: #38bdf8; --font: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        @media (prefers-color-scheme: light) { :root { --bg: #f8fafc; --text: #1e293b; --dim: #64748b; } }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font); background: var(--bg); color: var(--text); min-height: 100vh; display: flex; align-items: center; justify-content: center; text-align: center; padding: 2rem; }
        .container { max-width: 480px; }
        .code { font-size: 6rem; font-weight: 800; line-height: 1; color: var(--dim); opacity: 0.3; }
        h1 { font-size: 1.5rem; margin: 1rem 0 0.5rem; }
        p { color: var(--dim); font-size: 1rem; line-height: 1.6; }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .home { display: inline-block; margin-top: 1.5rem; padding: 0.6rem 1.5rem; border: 1px solid var(--dim); border-radius: 6px; color: var(--text); font-size: 0.9rem; }
        .home:hover { border-color: var(--accent); color: var(--accent); text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="code"><?= $errorContext->statusCode ?></div>
        <h1><?= $escape($title) ?></h1>
        <p><?= $escape($message) ?></p>
        <a href="/" class="home">Go Home</a>
    </div>
</body>
</html>
