
<?php
// Event-Pro Shared Security Functions

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate a CSRF token for the current session.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Add a hidden security token to HTML forms.
function csrf_field(): string
{
    $token = htmlspecialchars(
        $_SESSION['csrf_token'],
        ENT_QUOTES,
        'UTF-8'
    );

    return '<input type="hidden" name="csrf_token" value="' .
        $token . '">';
}

// Check that a submitted form belongs to this session.
function require_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submitted) ||
        !hash_equals($_SESSION['csrf_token'], $submitted)
    ) {
        http_response_code(403);
        exit('Invalid security token. Refresh the page and try again.');
    }
}

// Escape text before displaying it in HTML.
function escape_html($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}
