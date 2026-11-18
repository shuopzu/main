<?php

function send_password_reset_email(string $recipient, string $username, string $token): bool {
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)
        || !filter_var(MAIL_FROM_EMAIL, FILTER_VALIDATE_EMAIL)) {
        error_log('Password reset email not sent: recipient or MAIL_FROM_EMAIL is invalid.');
        return false;
    }

    $baseUrl = APP_BASE_URL;
    $parts = parse_url($baseUrl);
    if ($baseUrl === '' || !is_array($parts)
        || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)
        || empty($parts['host'])
        || isset($parts['user'])
        || isset($parts['pass'])
        || isset($parts['query'])
        || isset($parts['fragment'])
        || (APP_ENV !== 'local' && strtolower((string)$parts['scheme']) !== 'https')) {
        error_log('Password reset email not sent: APP_BASE_URL must be a valid absolute URL and use HTTPS outside local development.');
        return false;
    }

    $smtpHost = trim((string)(getenv('SMTP_HOST') ?: 'smtp.gmail.com'));
    $smtpUsername = trim((string)(getenv('SMTP_USERNAME') ?: ''));
    $smtpPassword = trim((string)(getenv('SMTP_PASSWORD') ?: ''));
    $smtpSecure = strtolower(trim((string)(getenv('SMTP_SECURE') ?: 'tls')));
    $smtpPortValue = getenv('SMTP_PORT');
    $smtpPort = $smtpPortValue === false || trim($smtpPortValue) === ''
        ? ($smtpSecure === 'ssl' ? 465 : 587)
        : filter_var($smtpPortValue, FILTER_VALIDATE_INT);

    if ($smtpHost === '' || $smtpUsername === '' || $smtpPassword === ''
        || !in_array($smtpSecure, ['tls', 'ssl'], true)
        || $smtpPort === false || $smtpPort < 1 || $smtpPort > 65535) {
        error_log('Password reset email not sent: SMTP_HOST, SMTP_USERNAME, and SMTP_PASSWORD must be configured with a valid SMTP_SECURE and SMTP_PORT.');
        return false;
    }

    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        error_log('Password reset email not sent: Composer dependencies are missing; run composer install.');
        return false;
    }
    require_once $autoload;

    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        error_log('Password reset email not sent: PHPMailer is unavailable; run composer install.');
        return false;
    }

    $resetUrl = rtrim($baseUrl, '/') . '/password_reset.php?token=' . rawurlencode($token);
    try {
        $message = new \PHPMailer\PHPMailer\PHPMailer(true);
        $message->isSMTP();
        $message->Host = $smtpHost;
        $message->SMTPAuth = true;
        $message->Username = $smtpUsername;
        $message->Password = $smtpPassword;
        $message->SMTPSecure = $smtpSecure === 'ssl'
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $message->Port = $smtpPort;
        $message->Timeout = 15;
        $message->CharSet = \PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
        $message->setFrom(MAIL_FROM_EMAIL, 'DENR Scoring App');
        $message->addAddress($recipient);
        $message->Subject = 'Reset your DENR scoring account password';
        $message->Body = "Hello {$username},\n\n"
            . "We received a request to reset the password for your account. "
            . "Use the link below within one hour to choose a new password:\n\n"
            . $resetUrl . "\n\n"
            . "If you did not request this, you can ignore this email.\n";
        return $message->send();
    } catch (\PHPMailer\PHPMailer\Exception $exception) {
        error_log('Password reset email delivery failed: ' . $exception->getMessage());
        return false;
    }
}
