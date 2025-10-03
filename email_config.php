<?php
// Email configuration for PHPMailer
// This configuration supports multiple email providers

// Mail driver: 'smtp' or 'sendgrid'
if (!defined('MAIL_DRIVER')) define('MAIL_DRIVER', 'smtp');

// Primary SMTP Configuration (update if your webhost allows SMTP)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '1official.mindmatters@gmail.com'); // Gmail account
define('SMTP_PASSWORD', 'rxza cfjc vzkj wirf'); // Gmail app password
define('SMTP_SECURE', 'tls');
// Allow self-signed if your host terminates TLS weirdly
if (!defined('SMTP_ALLOW_SELF_SIGNED')) define('SMTP_ALLOW_SELF_SIGNED', true);

// Alternative: Mailtrap (for testing) - Uncomment to use
// define('SMTP_HOST', 'sandbox.smtp.mailtrap.io');
// define('SMTP_PORT', 2525);
// define('SMTP_USERNAME', 'your-mailtrap-username');
// define('SMTP_PASSWORD', 'your-mailtrap-password');
// define('SMTP_SECURE', 'tls');

// Alternative: SendGrid Configuration - Uncomment to use
// HTTPS API fallback (recommended on shared hosts that block SMTP ports)
if (!defined('SENDGRID_API_KEY')) define('SENDGRID_API_KEY', ''); // set in production
if (!defined('SENDGRID_FROM_EMAIL')) define('SENDGRID_FROM_EMAIL', 'no-reply@mindmatters.free.nf');
if (!defined('SENDGRID_FROM_NAME')) define('SENDGRID_FROM_NAME', 'Mind Matters');

// Alternative: Outlook/Hotmail SMTP - Uncomment to use
// define('SMTP_HOST', 'smtp-mail.outlook.com');
// define('SMTP_PORT', 587);
// define('SMTP_USERNAME', 'your-outlook-email@outlook.com');
// define('SMTP_PASSWORD', 'your-outlook-password');
// define('SMTP_SECURE', 'tls');

// From email address
define('FROM_EMAIL', '1official.mindmatters@gmail.com');
define('FROM_NAME', 'Mind Matters');

// Site URL for verification links (auto-detect environment)
if (isset($_SERVER['HTTP_HOST'])) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    
    // Auto-detect environment
    if ($host === 'localhost' || $host === '127.0.0.1' || strpos($host, 'localhost:') === 0) {
        // Local development
        define('SITE_URL', 'http://localhost/mind-matters');
    } else {
        // Production - use actual domain
        define('SITE_URL', $protocol . '://' . $host);
    }
} else {
    // Fallback for CLI/cron jobs
    define('SITE_URL', 'https://mindmatters.free.nf');
}

// Additional configuration
define('EMAIL_VERIFICATION_EXPIRY_HOURS', 24);
define('MAX_VERIFICATION_ATTEMPTS', 3);
?>
