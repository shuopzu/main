<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/AuthSchema.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/Email.php';

ensure_auth_schema($conn);
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');

$error = '';
$success = flash_get('success') ?? '';
$csrf = csrf_token();
$token = strtolower(trim((string)($_GET['token'] ?? $_POST['token'] ?? '')));
$hasToken = preg_match('/^[a-f0-9]{64}$/', $token) === 1;
$resetLinkIsValid = false;

if ($hasToken && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    $tokenHash = hash('sha256', $token);
    $stmt = $conn->prepare("SELECT 1 FROM password_reset_requests pr
        JOIN users u ON u.id = pr.user_id
        WHERE pr.token_hash = ? AND pr.status = 'approved'
            AND pr.expires_at > NOW() AND u.email IS NOT NULL
        LIMIT 1");
    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $resetLinkIsValid = $stmt->get_result()->num_rows === 1;
    $stmt->close();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Reload the page and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'request') {
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Enter a valid email address.';
            } else {
                $userId = null;
                $token = null;
                $tokenHash = null;
                $recipient = null;
                $username = null;
                $transactionStarted = false;
                try {
                    $conn->begin_transaction();
                    $transactionStarted = true;
                    $userStmt = $conn->prepare('SELECT id, username, email FROM users WHERE email = ? LIMIT 1 FOR UPDATE');
                    $userStmt->bind_param('s', $email);
                    $userStmt->execute();
                    $user = $userStmt->get_result()->fetch_assoc();
                    $userStmt->close();

                    if ($user) {
                        $userId = (int)$user['id'];
                        $requestStmt = $conn->prepare('SELECT id, status, requested_at FROM password_reset_requests WHERE user_id = ? LIMIT 1 FOR UPDATE');
                        $requestStmt->bind_param('i', $userId);
                        $requestStmt->execute();
                        $existingRequest = $requestStmt->get_result()->fetch_assoc();
                        $requestStmt->close();

                        $recentRequest = $existingRequest
                            && $existingRequest['status'] === 'approved'
                            && strtotime((string)$existingRequest['requested_at']) > time() - 60;
                        if (!$recentRequest) {
                            $token = bin2hex(random_bytes(32));
                            $tokenHash = hash('sha256', $token);
                            $recipient = (string)$user['email'];
                            $username = (string)$user['username'];
                            if ($existingRequest) {
                                $requestId = (int)$existingRequest['id'];
                                $updateStmt = $conn->prepare("UPDATE password_reset_requests
                                    SET status = 'approved', token_hash = ?, requested_at = NOW(),
                                        approved_at = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR),
                                        completed_at = NULL, reviewed_by = NULL
                                    WHERE id = ?");
                                $updateStmt->bind_param('si', $tokenHash, $requestId);
                                $updateStmt->execute();
                                $updateStmt->close();
                            } else {
                                $insertStmt = $conn->prepare("INSERT INTO password_reset_requests
                                    (user_id, status, token_hash, requested_at, approved_at, expires_at)
                                    VALUES (?, 'approved', ?, NOW(), NOW(), DATE_ADD(NOW(), INTERVAL 1 HOUR))");
                                $insertStmt->bind_param('is', $userId, $tokenHash);
                                $insertStmt->execute();
                                $insertStmt->close();
                            }
                        }
                    }
                    $conn->commit();
                    $transactionStarted = false;

                    if ($token !== null && $tokenHash !== null && $userId !== null
                        && !send_password_reset_email((string)$recipient, (string)$username, $token)) {
                        $invalidateStmt = $conn->prepare("UPDATE password_reset_requests
                            SET status = 'expired', token_hash = NULL
                            WHERE user_id = ? AND token_hash = ?");
                        $invalidateStmt->bind_param('is', $userId, $tokenHash);
                        $invalidateStmt->execute();
                        $invalidateStmt->close();
                    }

                    flash_set('success', 'If an account exists for that email address, a password reset link will be sent. The link expires in one hour.');
                    header('Location: password_reset.php');
                    exit;
                } catch (Throwable $exception) {
                    if ($transactionStarted) {
                        try { $conn->rollback(); } catch (Throwable $rollbackException) {
                            error_log('password_reset request rollback failed: ' . $rollbackException->getMessage());
                        }
                    }
                    error_log('password_reset request failed: ' . $exception->getMessage());
                    $error = 'The request could not be processed. Please try again later.';
                }
            }
        } elseif ($action === 'reset') {
            $token = strtolower(trim((string)($_POST['token'] ?? '')));
            $password = (string)($_POST['password'] ?? '');
            $confirmPassword = (string)($_POST['confirm_password'] ?? '');

            if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
                $error = 'This password reset link is invalid or expired. Request a new link.';
            } elseif (strlen($password) < 8) {
                $error = 'Password must be at least 8 characters.';
            } elseif (strlen($password) > 72) {
                $error = 'Password must be at most 72 characters.';
            } elseif (!hash_equals($password, $confirmPassword)) {
                $error = 'The two passwords do not match.';
            } else {
                $transactionStarted = false;
                try {
                    $conn->begin_transaction();
                    $transactionStarted = true;
                    $submittedHash = hash('sha256', $token);
                    $stmt = $conn->prepare("SELECT pr.id, pr.user_id, pr.token_hash
                        FROM password_reset_requests pr
                        JOIN users u ON u.id = pr.user_id
                        WHERE pr.token_hash = ? AND pr.status = 'approved'
                            AND pr.expires_at > NOW() AND u.email IS NOT NULL
                        LIMIT 1 FOR UPDATE");
                    $stmt->bind_param('s', $submittedHash);
                    $stmt->execute();
                    $request = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if (!$request || !hash_equals((string)$request['token_hash'], $submittedHash)) {
                        $conn->rollback();
                        $transactionStarted = false;
                        $error = 'This password reset link is invalid, expired, or already used. Request a new link.';
                    } else {
                        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                        if ($passwordHash === false) {
                            throw new RuntimeException('Password hashing failed.');
                        }
                        $userId = (int)$request['user_id'];
                        $updateUser = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                        $updateUser->bind_param('si', $passwordHash, $userId);
                        $updateUser->execute();
                        $updateUser->close();

                        $deleteTokens = $conn->prepare('DELETE FROM remember_tokens WHERE user_id = ?');
                        $deleteTokens->bind_param('i', $userId);
                        $deleteTokens->execute();
                        $deleteTokens->close();

                        $requestId = (int)$request['id'];
                        $consume = $conn->prepare("UPDATE password_reset_requests
                            SET status = 'used', token_hash = NULL, completed_at = NOW()
                            WHERE id = ? AND status = 'approved' AND token_hash = ?");
                        $consume->bind_param('is', $requestId, $submittedHash);
                        $consume->execute();
                        if ($consume->affected_rows !== 1) {
                            $consume->close();
                            throw new RuntimeException('The password reset link was already consumed.');
                        }
                        $consume->close();

                        $conn->commit();
                        $transactionStarted = false;
                        flash_set('success', 'Your password has been changed. You can now sign in.');
                        header('Location: login.php');
                        exit;
                    }
                } catch (Throwable $exception) {
                    if ($transactionStarted) {
                        try { $conn->rollback(); } catch (Throwable $rollbackException) {
                            error_log('password_reset completion rollback failed: ' . $rollbackException->getMessage());
                        }
                    }
                    error_log('password_reset completion failed: ' . $exception->getMessage());
                    $error = 'The password could not be changed. Request a new reset link.';
                }
            }
        } else {
            $error = 'Unknown password reset action.';
        }
    }
}

if (!$resetLinkIsValid && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && preg_match('/^[a-f0-9]{64}$/', $token)) {
    $tokenHash = hash('sha256', $token);
    $stmt = $conn->prepare("SELECT 1 FROM password_reset_requests pr
        JOIN users u ON u.id = pr.user_id
        WHERE pr.token_hash = ? AND pr.status = 'approved'
            AND pr.expires_at > NOW() AND u.email IS NOT NULL
        LIMIT 1");
    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $resetLinkIsValid = $stmt->get_result()->num_rows === 1;
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Password Reset</title>
    <link rel="stylesheet" href="ui-enhancements.css?v=<?php echo filemtime(__DIR__ . '/ui-enhancements.css'); ?>">
    <script src="ui-enhancements.js?v=<?php echo filemtime(__DIR__ . '/ui-enhancements.js'); ?>" defer></script>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 28px 16px; background: #f4f6f9; color: #202124; font-family: Arial, Helvetica, sans-serif; }
        main { max-width: 620px; margin: 0 auto; }
        .card { padding: 22px; margin: 18px 0; background: #fff; border: 1px solid #dce1e7; border-radius: 9px; }
        label { display: block; margin: 12px 0 5px; font-weight: 700; font-size: 13px; }
        input { width: 100%; padding: 10px 12px; border: 1px solid #cbd2d9; border-radius: 5px; font: inherit; }
        button { margin-top: 16px; min-height: 44px; padding: 10px 15px; border: 0; border-radius: 5px; background: #107c41; color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
        p { color: #555; line-height: 1.5; }
        .notice { padding: 11px 13px; margin: 14px 0; border-radius: 5px; }
        .error { background: #fdecea; color: #7a1f16; }
        .success { background: #e8f5e9; color: #14532d; }
        a { color: #107c41; font-weight: 700; }
    </style>
</head>
<body>
<main>
    <h1>Password reset</h1>
    <?php if ($error !== ''): ?><div class="notice error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php if ($success !== ''): ?><div class="notice success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

    <?php if ($resetLinkIsValid): ?>
        <section class="card">
            <h2>Choose a new password</h2>
            <p>This link expires one hour after it was requested and can only be used once.</p>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="reset">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                <label for="new_password">New password</label>
                <input id="new_password" type="password" name="password" minlength="8" maxlength="72" required autocomplete="new-password">
                <label for="confirm_password">Confirm new password</label>
                <input id="confirm_password" type="password" name="confirm_password" minlength="8" maxlength="72" required autocomplete="new-password">
                <button type="submit">Change password</button>
            </form>
        </section>
    <?php else: ?>
        <?php if ($token !== ''): ?>
            <div class="notice error">This reset link is invalid, expired, or already used. Request a new link below.</div>
        <?php endif; ?>
        <section class="card">
            <h2>Request a reset link</h2>
            <p>Enter the email address on your account. If it is registered, we will email you a one-time link to choose a new password.</p>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="request">
                <label for="email">Email address</label>
                <input id="email" type="email" name="email" maxlength="254" required autocomplete="email">
                <button type="submit">Email reset link</button>
            </form>
        </section>
    <?php endif; ?>
    <p><a href="login.php">Return to sign in</a></p>
</main>
</body>
</html>
