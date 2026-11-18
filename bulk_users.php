<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/AuthSchema.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/AccessGroups.php';
require_once __DIR__ . '/Raters.php';

require_admin();
ensure_auth_schema($conn);

const BULK_USER_ROLES = ['rater', 'user'];
const BULK_USER_CSV_COLUMNS = ['username', 'role', 'rater_position', 'account_type', 'salary_grade', 'region', 'field_name'];
const BULK_USER_MAX_ROWS = 500;
const BULK_USER_MAX_CSV_BYTES = 1048576;

function bulk_users_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function bulk_users_read_csv(string $csv): array {
    if (strlen($csv) > BULK_USER_MAX_CSV_BYTES) {
        throw new InvalidArgumentException('CSV must be 1 MB or smaller.');
    }
    $stream = fopen('php://temp', 'r+');
    if ($stream === false) {
        throw new RuntimeException('Unable to read the provided CSV.');
    }
    fwrite($stream, $csv);
    rewind($stream);
    $headers = fgetcsv($stream, null, ',', '"', '');
    if (!is_array($headers) || !$headers) {
        fclose($stream);
        throw new InvalidArgumentException('Provide a CSV with a header row.');
    }
    $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$headers[0]);
    $headers = array_map(static fn($header): string => strtolower(trim((string)$header)), $headers);
    $columnIndexes = [];
    foreach (BULK_USER_CSV_COLUMNS as $column) {
        $index = array_search($column, $headers, true);
        if ($index === false) {
            fclose($stream);
            throw new InvalidArgumentException('CSV headers must include: username, role, rater_position, account_type, salary_grade, region, field_name.');
        }
        $columnIndexes[$column] = $index;
    }
    $emailColumnIndex = array_search('email', $headers, true);

    $rows = [];
    $lineNumber = 1;
    while (($values = fgetcsv($stream, null, ',', '"', '')) !== false) {
        $lineNumber++;
        if ($values === [null] || (count($values) === 1 && trim((string)$values[0]) === '')) {
            continue;
        }
        if (count($rows) >= BULK_USER_MAX_ROWS) {
            fclose($stream);
            throw new InvalidArgumentException('CSV cannot contain more than ' . BULK_USER_MAX_ROWS . ' user rows.');
        }
        $row = ['line' => $lineNumber];
        foreach (BULK_USER_CSV_COLUMNS as $column) {
            $row[$column] = trim((string)($values[$columnIndexes[$column]] ?? ''));
        }
        $row['email'] = $emailColumnIndex === false
            ? ''
            : strtolower(trim((string)($values[$emailColumnIndex] ?? '')));
        $rows[] = $row;
    }
    fclose($stream);
    if (!$rows) {
        throw new InvalidArgumentException('The CSV contains no user rows.');
    }
    return $rows;
}

function bulk_users_validate_row(mysqli $conn, array $row, array $salaryGradesByType): array {
    $errors = [];
    $username = preg_replace('/\s+/', ' ', trim((string)($row['username'] ?? '')));
    $email = strtolower(trim((string)($row['email'] ?? '')));
    $role = strtolower(trim((string)($row['role'] ?? '')));
    $accountType = strtolower(trim((string)($row['account_type'] ?? '')));
    $region = trim((string)($row['region'] ?? ''));
    $fieldName = trim((string)($row['field_name'] ?? ''));
    $salaryGrade = trim((string)($row['salary_grade'] ?? ''));
    $raterPosition = trim((string)($row['rater_position'] ?? ''));

    if (!preg_match('/^[\p{L}0-9 .\-]{3,100}$/u', $username)) {
        $errors[] = 'Username must be 3-100 characters using letters (including ñ), numbers, spaces, dots, and hyphens.';
    }
    if ($email !== '' && (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
        $errors[] = 'Email must be a valid address when supplied.';
    } elseif ($email !== '' && find_user_by_email($conn, $email) !== null) {
        $errors[] = 'Email address already exists.';
    }
    if (!in_array($role, BULK_USER_ROLES, true)) {
        $errors[] = 'Role must be rater or user, matching public registration.';
    }
    if (($role === 'rater' && !in_array($raterPosition, rater_list(), true))
        || ($raterPosition !== '' && !in_array($raterPosition, rater_list(), true))) {
        $errors[] = 'Rater accounts must have a valid rater_position; Standard User accounts may leave it blank.';
    }
    if (!array_key_exists($accountType, ACCESS_ACCOUNT_TYPES)) {
        $errors[] = 'Account type must be region or field.';
    }
    if ($region !== 'Region VI') {
        $errors[] = 'Registration is currently limited to Region VI.';
    }
    if ($fieldName === '' || $fieldName === 'Other' || !access_field_is_valid($fieldName)) {
        $errors[] = 'Enter a valid field name; use a specific field name instead of Other.';
    }
    if (!access_position_salary_grade_is_valid($conn, $salaryGrade, $salaryGradesByType[$accountType] ?? [])) {
        $errors[] = 'Salary grade must be available for the selected account type.';
    }

    if (preg_match('/^[\p{L}0-9 .\-]{3,100}$/u', $username)) {
        if (find_user_by_username($conn, $username) !== null) {
            $errors[] = 'Username already exists.';
        }
    }

    return [
        'line' => (int)($row['line'] ?? 0),
        'username' => $username,
        'email' => $email,
        'role' => $role,
        'rater_position' => $raterPosition,
        'account_type' => $accountType,
        'salary_grade' => $salaryGrade,
        'region' => $region,
        'field_name' => $fieldName,
        'errors' => array_values(array_unique($errors)),
    ];
}

function bulk_users_mark_duplicates(array $rows): array {
    $indexesByUsername = [];
    $indexesByEmail = [];
    foreach ($rows as $index => $row) {
        if (preg_match('/^[\p{L}0-9 .\-]{3,100}$/u', $row['username'])) {
            $key = mb_strtolower($row['username'], 'UTF-8');
            $indexesByUsername[$key][] = $index;
        }
        if ($row['email'] !== '') {
            $indexesByEmail[mb_strtolower($row['email'], 'UTF-8')][] = $index;
        }
    }
    foreach ($indexesByUsername as $indexes) {
        if (count($indexes) < 2) continue;
        foreach ($indexes as $index) {
            $rows[$index]['errors'][] = 'Duplicate username within this CSV.';
            $rows[$index]['errors'] = array_values(array_unique($rows[$index]['errors']));
        }
        foreach ($indexesByEmail as $indexes) {
            if (count($indexes) < 2) continue;
            foreach ($indexes as $index) {
                $rows[$index]['errors'][] = 'Duplicate email within this CSV.';
                $rows[$index]['errors'] = array_values(array_unique($rows[$index]['errors']));
            }
        }
    }
    return $rows;
}

function bulk_users_generate_password(): string {
    return bin2hex(random_bytes(12));
}

$salaryGradesByType = [
    'region' => account_type_salary_grades($conn, 'region'),
    'field' => account_type_salary_grades($conn, 'field'),
];
$previewRows = [];
$createdResults = [];
$pageError = '';
$pageMessage = '';
$csrfToken = csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $pageError = 'Your session expired. Reload the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'preview') {
        try {
            $csv = (string)($_POST['csv_text'] ?? '');
            if (isset($_FILES['csv_file']) && (int)($_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if ((int)$_FILES['csv_file']['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string)$_FILES['csv_file']['tmp_name'])) {
                    throw new InvalidArgumentException('The CSV upload could not be read.');
                }
                if ((int)$_FILES['csv_file']['size'] > BULK_USER_MAX_CSV_BYTES) {
                    throw new InvalidArgumentException('CSV must be 1 MB or smaller.');
                }
                $uploadedCsv = file_get_contents((string)$_FILES['csv_file']['tmp_name']);
                if ($uploadedCsv === false) {
                    throw new RuntimeException('Unable to read the uploaded CSV.');
                }
                $csv = $uploadedCsv;
            }

            $inputRows = bulk_users_read_csv($csv);
            foreach ($inputRows as $inputRow) {
                $previewRows[] = bulk_users_validate_row($conn, $inputRow, $salaryGradesByType);
            }
            $previewRows = bulk_users_mark_duplicates($previewRows);
            $batchId = bin2hex(random_bytes(24));
            $_SESSION['bulk_users_preview'] = [
                'id' => $batchId,
                'created_at' => time(),
                'rows' => $inputRows,
            ];
        } catch (InvalidArgumentException $exception) {
            $pageError = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log('bulk_users preview failed: ' . $exception->getMessage());
            $pageError = 'The CSV preview could not be prepared. Check the server error log.';
        }
    } elseif (($_POST['action'] ?? '') === 'confirm') {
        $staged = $_SESSION['bulk_users_preview'] ?? null;
        $batchId = (string)($_POST['batch_id'] ?? '');
        if (!is_array($staged)
            || !isset($staged['id'], $staged['created_at'], $staged['rows'])
            || !is_string($staged['id'])
            || !hash_equals($staged['id'], $batchId)
            || time() - (int)$staged['created_at'] > 900
            || !is_array($staged['rows'])) {
            unset($_SESSION['bulk_users_preview']);
            http_response_code(409);
            $pageError = 'This preview expired or was already used. Upload the CSV again.';
        } else {
            $inputRows = $staged['rows'];
            unset($_SESSION['bulk_users_preview']);
            foreach ($inputRows as $inputRow) {
                $previewRows[] = bulk_users_validate_row($conn, $inputRow, $salaryGradesByType);
            }
            $previewRows = bulk_users_mark_duplicates($previewRows);
            $validRows = array_values(array_filter($previewRows, static fn(array $row): bool => !$row['errors']));
            if (!$validRows) {
                $pageError = 'No valid rows remain. Review the errors and upload a corrected CSV.';
            } else {
                $transactionStarted = false;
                try {
                    $insertStmt = $conn->prepare('INSERT INTO users (email, username, password_hash, role, region, field_name, account_type, salary_grade, rater_position)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $lookupStmt = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
                    $conn->begin_transaction();
                    $transactionStarted = true;
                    foreach ($validRows as $row) {
                        $username = $row['username'];
                        $email = $row['email'] !== '' ? $row['email'] : null;
                        $lookupStmt->bind_param('s', $username);
                        $lookupStmt->execute();
                        if ($lookupStmt->get_result()->num_rows > 0) {
                            $createdResults[] = ['username' => $username, 'email' => $email ?? '', 'password' => '', 'status' => 'Skipped: username now exists.'];
                            continue;
                        }
                        $password = bulk_users_generate_password();
                        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                        if ($passwordHash === false) {
                            unset($password);
                            throw new RuntimeException('Password hashing failed.');
                        }
                        $role = $row['role'];
                        $accountType = $row['account_type'];
                        $region = $row['region'];
                        $fieldName = $row['field_name'];
                        $salaryGrade = (int)$row['salary_grade'];
                        $raterPosition = $role === 'rater' ? $row['rater_position'] : null;
                        $insertStmt->bind_param('sssssssis', $email, $username, $passwordHash, $role, $region, $fieldName, $accountType, $salaryGrade, $raterPosition);
                        try {
                            $insertStmt->execute();
                        } catch (mysqli_sql_exception $exception) {
                            unset($password, $passwordHash);
                            if ((int)$exception->getCode() === 1062) {
                                $createdResults[] = ['username' => $username, 'email' => $email ?? '', 'password' => '', 'status' => 'Skipped: username or email now exists.'];
                                continue;
                            }
                            throw $exception;
                        }
                        $createdResults[] = ['username' => $username, 'email' => $email ?? '', 'password' => $password, 'status' => 'Created'];
                        unset($password, $passwordHash);
                    }
                    $conn->commit();
                    $transactionStarted = false;
                    $insertStmt->close();
                    $lookupStmt->close();
                    $createdCount = count(array_filter(
                        $createdResults,
                        static fn(array $result): bool => $result['status'] === 'Created'
                    ));
                    $pageMessage = $createdCount > 0
                        ? "{$createdCount} account(s) were created directly and are ready to use; they do not need to sign up separately. Save or download each generated password now because passwords are not retained after this response."
                        : 'No new accounts were created. Check each row status below.';
                } catch (Throwable $exception) {
                    if ($transactionStarted) {
                        try { $conn->rollback(); } catch (Throwable $rollbackException) {
                            error_log('bulk_users rollback failed: ' . $rollbackException->getMessage());
                        }
                    }
                    if (isset($insertStmt) && $insertStmt instanceof mysqli_stmt) $insertStmt->close();
                    if (isset($lookupStmt) && $lookupStmt instanceof mysqli_stmt) $lookupStmt->close();
                    $createdResults = [];
                    error_log('bulk_users create failed: ' . $exception->getMessage());
                    $pageError = 'Bulk account creation failed and was rolled back. Check the server error log.';
                }
            }
        }
    } else {
        http_response_code(400);
        $pageError = 'Unknown action.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bulk User Accounts</title>
    <link rel="stylesheet" href="ui-enhancements.css?v=<?php echo filemtime(__DIR__ . '/ui-enhancements.css'); ?>">
    <script src="ui-enhancements.js?v=<?php echo filemtime(__DIR__ . '/ui-enhancements.js'); ?>" defer></script>
    <style>
        * { box-sizing: border-box; }
        body { min-height: 100vh; margin: 0; padding: clamp(16px, 2.5vh, 30px) clamp(14px, 3vw, 42px); background: #f2f5f2; color: #1c2420; font-family: "Public Sans", "Segoe UI", Arial, sans-serif; }
        main { display: flex; width: min(100%, 1560px); min-height: calc(100vh - clamp(32px, 5vh, 60px)); flex-direction: column; gap: 16px; margin: 0 auto; }
        header, .actions { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
        header { min-height: 170px; padding: clamp(24px, 3.5vw, 40px); border-radius: 20px; background: linear-gradient(118deg, #0b5c30 0%, #107c41 58%, #239556 100%); box-shadow: 0 16px 38px rgba(17, 91, 48, .16); color: #fff; }
        header > div { max-width: 680px; }
        h1 { margin: 0 0 8px; color: #fff; font-size: clamp(28px, 4vw, 40px); letter-spacing: -.04em; }
        header p { margin: 0; color: rgba(255,255,255,.82); font-size: 13px; line-height: 1.55; }
        header nav { display: flex; flex-wrap: wrap; gap: 8px; }
        header nav a { display: inline-flex; min-height: 40px; align-items: center; padding: 0 13px; border: 1px solid rgba(255,255,255,.3); border-radius: 9px; color: #fff; text-decoration: none; }
        header nav a:hover { background: rgba(255,255,255,.15); }
        a { color: #107c41; font-weight: 700; }
        .card { padding: clamp(18px, 2.5vw, 28px); margin: 0; border: 1px solid #e0e7e2; border-radius: 16px; background: #fff; box-shadow: 0 7px 22px rgba(26,52,35,.04); }
        .card h2 { margin: 0 0 10px; font-size: 19px; letter-spacing: -.025em; }
        p { color: #59665e; line-height: 1.55; }
        .card > p:first-of-type { margin-top: 0; }
        textarea { width: 100%; min-height: 210px; padding: 13px; border: 1px solid #d5ded8; border-radius: 10px; background: #fbfcfb; font: 13px/1.55 ui-monospace, SFMono-Regular, Consolas, monospace; }
        textarea:focus { outline: 3px solid rgba(16,124,65,.14); border-color: #107c41; }
        input[type="file"] { display: block; max-width: 100%; margin: 8px 0 16px; padding: 10px; border: 1px solid #d5ded8; border-radius: 9px; background: #fbfcfb; }
        button { min-height: 42px; padding: 9px 15px; border: 1px solid #107c41; border-radius: 9px; background: #107c41; color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
        button:hover:not(:disabled) { background: #0b5c30; }
        button:focus-visible, a:focus-visible { outline: 3px solid rgba(16,124,65,.28); outline-offset: 2px; }
        .notice { padding: 14px 16px; margin: 0; border: 1px solid transparent; border-radius: 12px; }
        .notice.error { border-color: #f0c4c0; background: #fdecea; color: #7a1f16; }
        .notice.success { border-color: #b6d8bc; background: #e8f5e9; color: #14532d; }
        .table-scroll { overflow: auto; border: 1px solid #e1e8e3; border-radius: 11px; }
        .bulk-confirm { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; padding: 16px; margin: 16px 0; border: 1px solid #b6d8bc; border-radius: 11px; background: #f2faf4; }
        .bulk-confirm p { margin: 0; color: #14532d; }
        .bulk-confirm button { min-height: 44px; }
        .bulk-confirm button:disabled { border-color: #7b8580; background: #7b8580; cursor: not-allowed; }
        table { width: 100%; min-width: 860px; border-collapse: collapse; background: #fff; }
        th, td { padding: 11px 12px; border-bottom: 1px solid #e8ede9; text-align: left; vertical-align: top; }
        th { background: #f4f7f5; color: #536159; font-size: 12px; white-space: nowrap; }
        tbody tr:last-child td { border-bottom: 0; }
        tbody tr:hover td { background: #f8fbf9; }
        .errors { color: #a12622; }
        .valid { color: #14532d; font-weight: 650; }
        code { overflow-wrap: anywhere; }
        @media (max-width: 700px) {
            body { padding: 16px 12px; }
            main { min-height: calc(100vh - 32px); gap: 12px; }
            header { min-height: 0; align-items: flex-start; flex-direction: column; gap: 18px; padding: 25px; border-radius: 17px; }
            header nav { width: 100%; }
            header nav a { flex: 1 1 auto; justify-content: center; }
            .card { padding: 19px; }
            textarea { min-height: 180px; }
        }
        @media (max-width: 480px) {
            body { padding: 10px; }
            main { min-height: 0; gap: 10px; }
            header { padding: 22px 18px; border-radius: 15px; }
            .card { padding: 15px; border-radius: 13px; }
            .bulk-confirm { align-items: stretch; flex-direction: column; padding: 13px; }
            .bulk-confirm button { width: 100%; }
        }
    </style>
</head>
<body>
<main>
    <header>
        <div>
            <h1>Bulk User Accounts</h1>
            <p>Import accounts from a CSV, review every row, then create only the entries that pass validation.</p>
        </div>
        <nav aria-label="Page navigation"><a href="manage_users.php">Manage Users</a><a href="dashboard.php">Dashboard</a><a href="results_2.php">Results</a></nav>
    </header>
    <?php if ($pageError !== ''): ?><div class="notice error"><?php echo bulk_users_h($pageError); ?></div><?php endif; ?>
    <?php if ($pageMessage !== ''): ?><div class="notice success"><?php echo bulk_users_h($pageMessage); ?></div><?php endif; ?>

    <?php if ($createdResults): ?>
        <section class="card">
            <h2>One-time account results</h2>
            <p>Copy or download generated passwords now. They are shown only in this response and are not saved in the database or session.</p>
            <?php if (count(array_filter($createdResults, static fn(array $result): bool => $result['status'] === 'Created')) > 0): ?>
                <p><a href="manage_users.php">Open Manage Users to verify the new accounts</a></p>
            <?php endif; ?>
            <button type="button" id="downloadResults">Download CSV</button>
            <div class="table-scroll">
                <table id="createdUsers">
                    <thead><tr><th>Username</th><th>Email</th><th>Generated password</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($createdResults as $result): ?>
                        <tr><td><?php echo bulk_users_h($result['username']); ?></td><td><?php echo bulk_users_h($result['email']); ?></td><td><code><?php echo bulk_users_h($result['password']); ?></code></td><td><?php echo bulk_users_h($result['status']); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <script>
            document.getElementById('downloadResults').addEventListener('click', function () {
                const rows = Array.from(document.querySelectorAll('#createdUsers tr')).map(row =>
                    Array.from(row.cells).map(cell => {
                        const value = cell.textContent.trim();
                        return /^[=+\-@\t\r]/.test(value) ? "'" + value : value;
                    })
                );
                const csv = rows.map(row => row.map(value => '"' + value.replace(/"/g, '""') + '"').join(',')).join('\r\n');
                const link = document.createElement('a');
                const objectUrl = URL.createObjectURL(new Blob(['\uFEFF' + csv], {type: 'text/csv;charset=utf-8'}));
                link.href = objectUrl;
                link.download = 'created-users.csv';
                link.click();
                setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
            });
        </script>
    <?php endif; ?>

    <?php if ($previewRows): ?>
        <section class="card">
            <h2>Step 1: Review rows</h2>
            <p>Rows with errors will not be created. Duplicate usernames are flagged. Confirm creates only rows that pass validation at confirmation time.</p>
            <?php
            $validPreviewCount = count(array_filter($previewRows, static fn(array $row): bool => !$row['errors']));
            $staged = $_SESSION['bulk_users_preview'] ?? null;
            ?>
            <?php if (is_array($staged) && isset($staged['id'])): ?>
                <form method="post" class="bulk-confirm" id="confirmBulkUsersForm">
                    <input type="hidden" name="csrf_token" value="<?php echo bulk_users_h($csrfToken); ?>">
                    <input type="hidden" name="action" value="confirm">
                    <input type="hidden" name="batch_id" value="<?php echo bulk_users_h($staged['id']); ?>">
                    <p><strong><?php echo $validPreviewCount; ?></strong> valid account(s) will be created. You can review the rows below before confirming.</p>
                    <button type="submit" <?php echo $validPreviewCount === 0 ? 'disabled' : ''; ?>>Confirm and create valid accounts</button>
                </form>
                <script>
                    document.getElementById('confirmBulkUsersForm').addEventListener('submit', function (event) {
                        if (!window.confirm('Create <?php echo $validPreviewCount; ?> valid user account(s) from this CSV?')) {
                            event.preventDefault();
                        }
                    });
                </script>
            <?php endif; ?>
            <div class="table-scroll">
                <table>
                    <thead><tr><th>CSV row</th><th>Username</th><th>Email</th><th>Role</th><th>Rater position</th><th>Account type</th><th>Salary grade</th><th>Region</th><th>Field</th><th>Validation</th></tr></thead>
                    <tbody>
                    <?php foreach ($previewRows as $row): ?>
                        <tr>
                            <td><?php echo (int)$row['line']; ?></td>
                            <td><?php echo bulk_users_h($row['username']); ?></td>
                            <td><?php echo bulk_users_h($row['email']); ?></td>
                            <td><?php echo bulk_users_h($row['role']); ?></td>
                            <td><?php echo bulk_users_h($row['rater_position']); ?></td>
                            <td><?php echo bulk_users_h($row['account_type']); ?></td>
                            <td><?php echo bulk_users_h($row['salary_grade']); ?></td>
                            <td><?php echo bulk_users_h($row['region']); ?></td>
                            <td><?php echo bulk_users_h($row['field_name']); ?></td>
                            <td class="<?php echo $row['errors'] ? 'errors' : 'valid'; ?>">
                                <?php echo $row['errors'] ? bulk_users_h(implode(' ', $row['errors'])) : 'Valid'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <section class="card">
        <h2>Upload or paste a CSV</h2>
        <p>Required columns: <code>username,role,rater_position,account_type,salary_grade,region,field_name</code>. Add an optional <code>email</code> column to enable email login and password recovery for those accounts. Rater accounts must include a valid rater position; Standard User accounts may leave it blank. Account type must be region or field; region must be Region VI; salary grades must be valid for the account type. Maximum 500 rows and 1 MB. Review the rows before confirming account creation.</p>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo bulk_users_h($csrfToken); ?>">
            <input type="hidden" name="action" value="preview">
            <label for="csv_file">CSV file</label>
            <input type="file" id="csv_file" name="csv_file" accept=".csv,text/csv">
            <label for="csv_text">Or paste CSV text</label>
            <textarea id="csv_text" name="csv_text" placeholder="username,role,rater_position,account_type,salary_grade,region,field_name,email&#10;Jane Doe,rater,END-USER,region,18,Region VI,Forestry,jane@example.com"></textarea>
            <button type="submit">Create users</button>
        </form>
    </section>
</main>
</body>
</html>
