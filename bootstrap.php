<?php

require_once __DIR__ . '/config.php';

$appDevMode = defined('APP_DEV_MODE') && APP_DEV_MODE === true;
error_reporting(E_ALL);
ini_set('display_errors', $appDevMode ? '1' : '0');
ini_set('display_startup_errors', $appDevMode ? '1' : '0');
ini_set('log_errors', '1');

$configuredLogDir = getenv('APP_ERROR_LOG_DIR');
if ($configuredLogDir !== false && $configuredLogDir !== '') {
    $appLogDir = $configuredLogDir;
} elseif (!empty($_SERVER['DOCUMENT_ROOT'])) {
    $appLogDir = dirname(rtrim((string)$_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR))
        . DIRECTORY_SEPARATOR . 'interview_rating_logs';
} else {
    $appLogDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'interview_rating_logs';
}

if (!is_dir($appLogDir) && !@mkdir($appLogDir, 0700, true) && !is_dir($appLogDir)) {
    $appLogDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'interview_rating_logs';
    if (!is_dir($appLogDir) && !@mkdir($appLogDir, 0700, true) && !is_dir($appLogDir)) {
        error_log('Unable to create the private application log directory.');
    }
}

ini_set('error_log', $appLogDir . DIRECTORY_SEPARATOR . 'php-errors.log');

set_exception_handler(static function (Throwable $exception) use ($appDevMode): void {
    $reference = substr(hash('sha256', microtime(true) . ':' . getmypid() . ':' . spl_object_id($exception)), 0, 12);
    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'unknown'));
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'unknown'));
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    error_log("Unhandled application exception [{$reference}] {$method} {$script} user_id={$userId}: " . (string)$exception);
    http_response_code(500);

    if ($exception instanceof mysqli_sql_exception && in_array((int)$exception->getCode(), [1054, 1146], true)) {
        http_response_code(503);
        $message = 'The database schema is incomplete or out of date. Run setup.php from the command line, then reload this page.';
    } elseif ($exception instanceof mysqli_sql_exception) {
        $message = 'The database rejected this request (error code ' . (int)$exception->getCode() . ').';
    } else {
        $message = 'The request could not be completed. Check the private application error log.';
    }
    if ($appDevMode) {
        $message .= ' Details: ' . $exception->getMessage();
    }
    $message .= ' Reference: ' . $reference . '.';

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    $wantsJson = str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
        || in_array(basename((string)($_SERVER['SCRIPT_NAME'] ?? '')), [
            'positions_api.php', 'sheets_api.php', 'template_api.php',
        ], true);
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => $message]);
        exit;
    }

    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Unable to load this page</title><style>'
        . '*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;'
        . 'background:#f3f6f4;color:#17231b;font:16px/1.6 Arial,Helvetica,sans-serif}'
        . 'main{width:min(100%,520px);padding:32px;border:1px solid #dce5de;border-radius:16px;background:#fff;'
        . 'box-shadow:0 12px 36px rgba(22,54,36,.1)}.icon{width:48px;height:48px;display:grid;place-items:center;'
        . 'margin-bottom:18px;border-radius:50%;background:#fff3e8;color:#a64b12;font-size:25px;font-weight:700}'
        . 'h1{margin:0 0 10px;font-size:23px}p{margin:0;color:#526158}.reference{margin-top:16px;font-size:13px}'
        . '.actions{margin-top:24px}a{display:inline-block;padding:11px 16px;border-radius:8px;background:#107c41;'
        . 'color:#fff;font-weight:700;text-decoration:none}a:focus-visible{outline:3px solid #8cc9a5;outline-offset:3px}'
        . '.hint{margin-top:16px;font-size:13px}</style></head><body><main>'
        . '<div class="icon" aria-hidden="true">!</div><h1>Unable to load this page</h1>'
        . '<p>' . $safeMessage . '</p><p class="hint">Please try again. If the problem continues, share the reference ID with your administrator.</p>'
        . '<div class="actions"><a href="sheet_picker.php">Return to position picker</a></div>'
        . '</main></body></html>';
    exit;
});

require_once __DIR__ . '/db.php';
ini_set('display_errors', $appDevMode ? '1' : '0');
ini_set('display_startup_errors', $appDevMode ? '1' : '0');
ini_set('log_errors', '1');

function app_require_tables(mysqli $conn, array $tables): void {
    $tables = array_values(array_unique($tables));
    if (!$tables) return;

    foreach ($tables as $table) {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            throw new InvalidArgumentException('Invalid required table name.');
        }
    }
    $quotedTables = array_map(
        static fn(string $table): string => "'" . $conn->real_escape_string($table) . "'",
        $tables
    );
    $result = $conn->query(
        'SELECT TABLE_NAME FROM information_schema.TABLES '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (' . implode(',', $quotedTables) . ')'
    );
    $found = [];
    while ($row = $result->fetch_assoc()) {
        $found[] = $row['TABLE_NAME'];
    }

    $missing = array_values(array_diff($tables, $found));
    if (!$missing) return;

    error_log('Required database tables are missing: ' . implode(', ', $missing));
    http_response_code(503);
    $message = 'Application setup is incomplete. Missing database table(s): '
        . implode(', ', $missing) . '. Run setup.php from the command line, then reload this page.';
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    $wantsJson = str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
        || in_array(basename((string)($_SERVER['SCRIPT_NAME'] ?? '')), [
            'positions_api.php', 'sheets_api.php', 'template_api.php',
        ], true);
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => $message]);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo $message;
    }
    exit;
}

if (!defined('APP_SKIP_SCHEMA_CHECK') || APP_SKIP_SCHEMA_CHECK !== true) {
    app_require_tables($conn, [
        'users', 'remember_tokens', 'login_attempts', 'submission_attempts',
        'candidates', 'candidate_scores', 'sheet_settings',
        'position_rater_order', 'removed_tabs', 'sheet_documents',
        'competency_templates', 'sheets', 'sheet_cells', 'evidence_options',
        'positions', 'positions_seed_state_v2', 'password_reset_requests', 'form_drafts',
    ]);
}
