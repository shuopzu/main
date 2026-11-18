<?php
require_once __DIR__ . '/bootstrap.php';
// ============================================================
// manage_users.php — COMPLETE FIXED FILE
//
// FIX (Bug E): admin-count check for demote/delete runs inside a
//              transaction with SELECT … FOR UPDATE on admin rows.
// FIX (Bug 6): every $conn->rollback() is guarded so strict-mode
//              mysqli cannot double-throw after commit(). Also the
//              outer catch now rolls back the transaction (it did
//              not before).
// FIX (Bug 2): repaired stray HTML in the hint paragraph.
// ============================================================

require_once 'db.php';
require_once __DIR__ . '/AuthSchema.php';
require_once 'auth.php';
require_once __DIR__ . '/AccessGroups.php';
require_once __DIR__ . '/Raters.php';

require_role('admin');
ensure_auth_schema($conn);

const MANAGE_ROLES = ['admin', 'rater', 'user'];
const MIN_PASSWORD_LENGTH = 8;
const MAX_PASSWORD_LENGTH = 72;
$salaryGrades = array_values(array_unique(array_merge(
    account_type_salary_grades($conn, 'region'),
    account_type_salary_grades($conn, 'field')
)));
sort($salaryGrades);
$profileGradesByType = [
    'region' => account_type_salary_grades($conn, 'region'),
    'field' => account_type_salary_grades($conn, 'field'),
];
$profileSalaryGrades = range(0, 26);

function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function flash(string $type, string $msg): void {
    flash_set($type, $msg);
}

function normalize_manage_region(string $region): ?string {
    $region = trim($region);
    if ($region === '') return ACCESS_DEFAULT_REGION;
    foreach (ACCESS_REGIONS as $key => $label) {
        if ($region === $key || $region === $label) return $key;
    }
    return null;
}

function redirect_back(): void {
    header('Location: manage_users.php');
    exit;
}

function admin_count(mysqli $conn): int {
    $res = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'");
    return (int)($res->fetch_assoc()['c'] ?? 0);
}

function revoke_user_tokens(mysqli $conn, int $userId): void {
    $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
}

function password_problem(string $pw): ?string {
    $len = strlen($pw);
    if ($len < MIN_PASSWORD_LENGTH) return 'Password must be at least ' . MIN_PASSWORD_LENGTH . ' characters.';
    if ($len > MAX_PASSWORD_LENGTH) return 'Password must be at most ' . MAX_PASSWORD_LENGTH . ' characters.';
    return null;
}

$myId = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        flash('error', 'Your session expired. Please try again.');
        redirect_back();
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'link_candidate') {
            $candidateId = (int)($_POST['candidate_id'] ?? 0);
            $userId = (int)($_POST['user_id'] ?? 0);
            if ($candidateId < 1 || $userId < 1) {
                flash('error', 'Choose a candidate record and a Standard User account.');
            } else {
                $stmt = $conn->prepare("SELECT id, username FROM users WHERE id = ? AND role = 'user' LIMIT 1");
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $targetUser = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$targetUser) {
                    flash('error', 'The selected account is not a Standard User.');
                } else {
                    $conn->begin_transaction();
                    try {
                        $stmt = $conn->prepare('SELECT user_id FROM candidates WHERE id = ? LIMIT 1 FOR UPDATE');
                        $stmt->bind_param('i', $candidateId);
                        $stmt->execute();
                        $candidate = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        if (!$candidate) {
                            $conn->rollback();
                            flash('error', 'Candidate record not found.');
                        } elseif ($candidate['user_id'] !== null && (int)$candidate['user_id'] !== $userId) {
                            $conn->rollback();
                            flash('error', 'This candidate is already linked to another account. Unlink it first.');
                        } else {
                            $stmt = $conn->prepare('UPDATE candidates SET user_id = ? WHERE id = ?');
                            $stmt->bind_param('ii', $userId, $candidateId);
                            $stmt->execute();
                            $stmt->close();
                            $conn->commit();
                            flash('success', 'Candidate record linked to ' . $targetUser['username'] . '.');
                        }
                    } catch (Throwable $e) {
                        try { $conn->rollback(); } catch (Throwable $ignored) {}
                        throw $e;
                    }
                }
            }

        } elseif ($action === 'unlink_candidate') {
            $candidateId = (int)($_POST['candidate_id'] ?? 0);
            if ($candidateId < 1) {
                flash('error', 'Invalid candidate record.');
            } else {
                $stmt = $conn->prepare('UPDATE candidates SET user_id = NULL WHERE id = ?');
                $stmt->bind_param('i', $candidateId);
                $stmt->execute();
                $changed = $stmt->affected_rows;
                $stmt->close();
                flash($changed > 0 ? 'success' : 'error', $changed > 0 ? 'Candidate record unlinked.' : 'Candidate link not found.');
            }

        } elseif ($action === 'create') {
            $username = trim($_POST['username'] ?? '');
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $password = $_POST['password'] ?? '';
            $role     = $_POST['role'] ?? '';
            $accountType = strtolower(trim((string)($_POST['account_type'] ?? '')));
            $region = normalize_manage_region((string)($_POST['region'] ?? ''));
            $salaryGradeValue = (string)($_POST['salary_grade'] ?? '');
            $raterPosition = trim((string)($_POST['rater_position'] ?? ''));

            if (!preg_match('/^[\p{L}0-9 .\-]{3,100}$/u', $username)) {
                flash('error', 'Username must be 3-100 characters and can only contain letters (including ñ), numbers, spaces, dots, and hyphens.');
            } elseif (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('error', 'Enter a valid email address for the account.');
            } elseif (!in_array($role, MANAGE_ROLES, true)) {
                flash('error', 'Please choose a valid role.');
            } elseif (!array_key_exists($accountType, ACCESS_ACCOUNT_TYPES)
                || $region === null
                || !access_position_salary_grade_is_valid($conn, $salaryGradeValue, account_type_salary_grades($conn, $accountType))) {
                flash('error', 'Please choose a valid account type, region, and salary grade.');
            } elseif (($raterPosition !== '' && !in_array($raterPosition, rater_list(), true))
                || ($role === 'rater' && $raterPosition === '')) {
                flash('error', 'Choose a valid rater position for Rater accounts.');
            } elseif (($problem = password_problem($password)) !== null) {
                flash('error', $problem);
            } elseif (find_user_by_username($conn, $username) !== null) {
                flash('error', "The username '$username' is already taken.");
            } elseif (find_user_by_email($conn, $email) !== null) {
                flash('error', 'That email address is already associated with an account.');
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $salaryGrade = (int)$salaryGradeValue;
                $storedRaterPosition = $role === 'rater' ? $raterPosition : null;
                $stmt = $conn->prepare("INSERT INTO users (email, username, password_hash, role, region, account_type, salary_grade, rater_position) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('ssssssis', $email, $username, $hash, $role, $region, $accountType, $salaryGrade, $storedRaterPosition);
                $stmt->execute();
                $stmt->close();
                flash('success', "Account '$username' created as $role.");
            }

        } elseif ($action === 'update_user_settings') {
            $id = (int)($_POST['id'] ?? 0);
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $role = trim((string)($_POST['role'] ?? ''));
            $accountType = strtolower(trim((string)($_POST['account_type'] ?? '')));
            $region = normalize_manage_region((string)($_POST['region'] ?? ''));
            $salaryGradeValue = (string)($_POST['salary_grade'] ?? '');
            $raterPosition = trim((string)($_POST['rater_position'] ?? ''));

            $target = null;
            if ($id > 0) {
                $targetStmt = $conn->prepare('SELECT id, username, email, role, account_type, region, salary_grade, rater_position FROM users WHERE id = ? LIMIT 1');
                $targetStmt->bind_param('i', $id);
                $targetStmt->execute();
                $target = $targetStmt->get_result()->fetch_assoc();
                $targetStmt->close();
            }

            if (!$target) {
                flash('error', 'User not found.');
            } elseif ($email !== '' && (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
                flash('error', 'Enter a valid email address, or leave it blank to clear it.');
            } elseif (!in_array($role, MANAGE_ROLES, true)) {
                flash('error', 'Please choose a valid role.');
            } elseif ($id === $myId && $role !== $target['role']) {
                flash('error', "You can't change your own role.");
            } elseif (!array_key_exists($accountType, ACCESS_ACCOUNT_TYPES) || $region === null) {
                flash('error', 'Please choose a valid account type and region.');
            } elseif (!access_position_salary_grade_is_valid(
                $conn,
                $salaryGradeValue,
                account_type_salary_grades($conn, $accountType)
            )) {
                flash('error', 'That salary grade has no positions for this account type. Choose a grade with available positions.');
            } elseif (($raterPosition !== '' && !in_array($raterPosition, rater_list(), true))
                || ($role === 'rater' && $raterPosition === '')) {
                flash('error', 'Choose a valid rater position for Rater accounts.');
            } else {
                $emailValue = $email === '' ? null : $email;
                $emailChanged = strtolower((string)($target['email'] ?? '')) !== (string)($emailValue ?? '');
                $roleChanged = $role !== $target['role'];
                $emailInUse = false;
                if ($emailValue !== null) {
                    $emailStmt = $conn->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
                    $emailStmt->bind_param('si', $emailValue, $id);
                    $emailStmt->execute();
                    $emailInUse = $emailStmt->get_result()->num_rows > 0;
                    $emailStmt->close();
                }

                if ($emailInUse) {
                    flash('error', 'That email address is already associated with another account.');
                } else {
                    $salaryGrade = (int)$salaryGradeValue;
                    $storedRaterPosition = $raterPosition !== '' ? $raterPosition : null;
                    $conn->begin_transaction();
                    try {
                        if ($target['role'] === 'admin' && $role !== 'admin') {
                            $conn->query("SELECT id FROM users WHERE role = 'admin' FOR UPDATE");
                            if (admin_count($conn) <= 1) {
                                $conn->rollback();
                                flash('error', 'You must keep at least one admin account.');
                                redirect_back();
                            }
                        }

                        $stmt = $conn->prepare('UPDATE users SET email = ?, role = ?, region = ?, account_type = ?, salary_grade = ?, rater_position = ? WHERE id = ?');
                        $stmt->bind_param('ssssisi', $emailValue, $role, $region, $accountType, $salaryGrade, $storedRaterPosition, $id);
                        $stmt->execute();
                        $stmt->close();

                        if ($emailChanged) {
                            $invalidateStmt = $conn->prepare("UPDATE password_reset_requests
                                SET status = 'expired', token_hash = NULL
                                WHERE user_id = ? AND status = 'approved'");
                            $invalidateStmt->bind_param('i', $id);
                            $invalidateStmt->execute();
                            $invalidateStmt->close();
                        }
                        if ($roleChanged) revoke_user_tokens($conn, $id);
                        $conn->commit();
                        flash('success', "Account settings saved for '" . $target['username'] . "'.");
                    } catch (Throwable $exception) {
                        try { $conn->rollback(); } catch (Throwable $rollbackException) {
                            error_log('manage_users settings update rollback failed: ' . $rollbackException->getMessage());
                        }
                        throw $exception;
                    }
                }
            }

        } elseif ($action === 'update_email') {
            $id = (int)($_POST['id'] ?? 0);
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $target = $id > 0 ? find_user_by_id($conn, $id) : null;

            if (!$target) {
                flash('error', 'User not found.');
            } elseif ($email !== '' && (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
                flash('error', 'Enter a valid email address, or leave it blank to clear it.');
            } else {
                $emailInUse = false;
                if ($email !== '') {
                    $emailStmt = $conn->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
                    $emailStmt->bind_param('si', $email, $id);
                    $emailStmt->execute();
                    $emailInUse = $emailStmt->get_result()->num_rows > 0;
                    $emailStmt->close();
                }
                if ($emailInUse) {
                    flash('error', 'That email address is already associated with another account.');
                } else {
                    $emailValue = $email === '' ? null : $email;
                    $conn->begin_transaction();
                    try {
                        $stmt = $conn->prepare('UPDATE users SET email = ? WHERE id = ?');
                        $stmt->bind_param('si', $emailValue, $id);
                        $stmt->execute();
                        $stmt->close();
                        $invalidateStmt = $conn->prepare("UPDATE password_reset_requests
                            SET status = 'expired', token_hash = NULL
                            WHERE user_id = ? AND status = 'approved'");
                        $invalidateStmt->bind_param('i', $id);
                        $invalidateStmt->execute();
                        $invalidateStmt->close();
                        $conn->commit();
                        flash('success', $emailValue === null
                            ? "Email address removed for '" . $target['username'] . "'."
                            : "Email address updated for '" . $target['username'] . "'.");
                    } catch (Throwable $exception) {
                        try { $conn->rollback(); } catch (Throwable $rollbackException) {
                            error_log('manage_users email update rollback failed: ' . $rollbackException->getMessage());
                        }
                        throw $exception;
                    }
                }
            }

        } elseif ($action === 'update_access_profile') {
            $id = (int)($_POST['id'] ?? 0);
            $accountType = strtolower(trim((string)($_POST['account_type'] ?? '')));
            $region = normalize_manage_region((string)($_POST['region'] ?? ''));
            $salaryGradeValue = (string)($_POST['salary_grade'] ?? '');
            $target = $id > 0 ? find_user_access_profile($conn, $id) : null;
            $raterPosition = trim((string)($_POST['rater_position'] ?? ($target['rater_position'] ?? '')));

            if (!$target) {
                flash('error', 'User not found.');
            } elseif (!array_key_exists($accountType, ACCESS_ACCOUNT_TYPES)
                || $region === null) {
                flash('error', 'Please choose a valid account type and region.');
            } elseif (!access_position_salary_grade_is_valid(
                $conn,
                $salaryGradeValue,
                account_type_salary_grades($conn, $accountType)
            )) {
                flash('error', 'That salary grade has no positions for this account type. Choose a grade with available positions.');
            } elseif (($raterPosition !== '' && !in_array($raterPosition, rater_list(), true))
                || ($target['role'] === 'rater' && $raterPosition === '')) {
                flash('error', 'Choose a valid rater position for Rater accounts.');
            } else {
                $salaryGrade = (int)$salaryGradeValue;
                $storedRaterPosition = $raterPosition !== '' ? $raterPosition : null;
                $stmt = $conn->prepare("UPDATE users SET region = ?, account_type = ?, salary_grade = ?, rater_position = ? WHERE id = ?");
                $stmt->bind_param('ssisi', $region, $accountType, $salaryGrade, $storedRaterPosition, $id);
                $stmt->execute();
                $stmt->close();
                flash('success', "Access profile updated for '" . $target['username'] . "' as "
                    . ACCESS_ACCOUNT_TYPES[$accountType] . " (SG {$salaryGrade}).");
            }

        } elseif ($action === 'change_role') {
            $id   = (int)($_POST['id'] ?? 0);
            $role = $_POST['role'] ?? '';
            $target = $id > 0 ? find_user_access_profile($conn, $id) : null;

            if (!$target) {
                flash('error', 'User not found.');
            } elseif (!in_array($role, MANAGE_ROLES, true)) {
                flash('error', 'Please choose a valid role.');
            } elseif ($id === $myId) {
                flash('error', "You can't change your own role.");
            } elseif ($role === 'rater' && !in_array((string)($target['rater_position'] ?? ''), rater_list(), true)) {
                flash('error', 'Assign a rater position in the access profile before changing this account to Rater.');
            } else {
                $conn->begin_transaction();
                try {
                    $conn->query("SELECT id FROM users WHERE role = 'admin' FOR UPDATE");
                    $adminTotal = admin_count($conn);
                    if ($target['role'] === 'admin' && $role !== 'admin' && $adminTotal <= 1) {
                        try { $conn->rollback(); } catch (Throwable $ignored) {}
                        flash('error', 'You must keep at least one admin account.');
                    } else {
                        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
                        $stmt->bind_param('si', $role, $id);
                        $stmt->execute();
                        $stmt->close();
                        revoke_user_tokens($conn, $id);
                        $conn->commit();
                        flash('success', "'" . $target['username'] . "' is now $role. It applies at their next login.");
                    }
                } catch (Throwable $e) {
                    try { $conn->rollback(); } catch (Throwable $ignored) {}
                    throw $e;
                }
            }

        } elseif ($action === 'reset_password') {
            $id       = (int)($_POST['id'] ?? 0);
            $password = $_POST['password'] ?? '';
            $target   = $id > 0 ? find_user_by_id($conn, $id) : null;

            if (!$target) {
                flash('error', 'User not found.');
            } elseif (($problem = password_problem($password)) !== null) {
                flash('error', $problem);
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $stmt->bind_param('si', $hash, $id);
                    $stmt->execute();
                    $stmt->close();
                    revoke_user_tokens($conn, $id);
                    $invalidateStmt = $conn->prepare("UPDATE password_reset_requests
                        SET status = 'expired', token_hash = NULL
                        WHERE user_id = ? AND status = 'approved'");
                    $invalidateStmt->bind_param('i', $id);
                    $invalidateStmt->execute();
                    $invalidateStmt->close();
                    $conn->commit();
                    flash('success', "Password updated for '" . $target['username'] . "'.");
                } catch (Throwable $exception) {
                    try { $conn->rollback(); } catch (Throwable $rollbackException) {
                        error_log('manage_users password reset rollback failed: ' . $rollbackException->getMessage());
                    }
                    throw $exception;
                }
            }

        } elseif ($action === 'delete') {
            $id     = (int)($_POST['id'] ?? 0);
            $target = $id > 0 ? find_user_by_id($conn, $id) : null;

            if (!$target) {
                flash('error', 'User not found.');
            } elseif ($id === $myId) {
                flash('error', "You can't delete your own account.");
            } else {
                $conn->begin_transaction();
                try {
                    $conn->query("SELECT id FROM users WHERE role = 'admin' FOR UPDATE");
                    $adminTotal = admin_count($conn);
                    if ($target['role'] === 'admin' && $adminTotal <= 1) {
                        try { $conn->rollback(); } catch (Throwable $ignored) {}
                        flash('error', 'You must keep at least one admin account.');
                    } else {
                        revoke_user_tokens($conn, $id);
                        $stmt = $conn->prepare('UPDATE candidates SET user_id = NULL WHERE user_id = ?');
                        $stmt->bind_param('i', $id);
                        $stmt->execute();
                        $stmt->close();
                        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->bind_param('i', $id);
                        $stmt->execute();
                        $stmt->close();
                        $conn->commit();
                        flash('success', "Account '" . $target['username'] . "' deleted.");
                    }
                } catch (Throwable $e) {
                    try { $conn->rollback(); } catch (Throwable $ignored) {}
                    throw $e;
                }
            }

        } else {
            flash('error', 'Unknown action.');
        }
    } catch (Throwable $e) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        error_log('manage_users error: ' . $e->getMessage());
        flash('error', DEBUG ? $e->getMessage() : 'Something went wrong. Please try again.');
    }

    redirect_back();
}

$flash = null;
if (($m = flash_get('success')) !== null)   $flash = ['type' => 'success', 'msg' => $m];
elseif (($m = flash_get('error')) !== null) $flash = ['type' => 'error',   'msg' => $m];
$users = [];
$res = $conn->query("SELECT id, username, email, role, region, field_name, account_type, salary_grade, rater_position, created_at FROM users ORDER BY FIELD(role,'admin','rater','user'), username");
while ($row = $res->fetch_assoc()) {
    $users[] = $row;
}
$candidateRecords = [];
$res = $conn->query("SELECT c.id, c.candidate_name, c.position_title, c.user_id, u.username
    FROM candidates c LEFT JOIN users u ON u.id = c.user_id ORDER BY c.id DESC");
while ($row = $res->fetch_assoc()) {
    $candidateRecords[] = $row;
}
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="mockup.css?v=<?php echo filemtime(__DIR__ . '/mockup.css'); ?>">
    <link rel="stylesheet" href="ui-enhancements.css?v=<?php echo filemtime(__DIR__ . '/ui-enhancements.css'); ?>">
    <script src="ui-enhancements.js?v=<?php echo filemtime(__DIR__ . '/ui-enhancements.js'); ?>" defer></script>
    <style>
        .alert { padding: 10px 14px; border: 1px solid transparent; border-radius: 8px; margin-bottom: 18px; font-size: 13px; }
        .alert-success { background: #e8f5e9; border-color: #a9d08e; color: #1e5e2a; }
        .alert-error { background: #fdecea; border-color: #f5c2c0; color: #7a1f16; }
        .inline { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
        .inline input, .inline select { width: auto; min-width: 120px; padding: 7px 8px; font-size: 13px; }
        #manageUsersPage { width: min(100%, 1400px); max-width: 1400px; }
        #manageUsersPage select {
            appearance: none;
            -webkit-appearance: none;
            width: 100%;
            min-width: 0;
            min-height: 42px;
            padding: 9px 40px 9px 12px;
            border: 1px solid #d5ded8;
            border-radius: 8px;
            background-color: #fff;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath d='m3 6 5 5 5-5' fill='none' stroke='%23536159' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 13px center;
            background-size: 13px;
            color: #202a35;
            font: inherit;
            font-size: 13px;
            line-height: 1.35;
            transition: border-color .16s ease, box-shadow .16s ease, background-color .16s ease;
        }
        #manageUsersPage select:hover:not(:disabled) { border-color: #aab9af; background-color: #fcfdfc; }
        #manageUsersPage select:focus { border-color: #107c41; outline: 0; box-shadow: 0 0 0 3px rgba(16, 124, 65, .12); }
        #manageUsersPage select:disabled { color: #89938d; background-color: #f1f4f2; cursor: not-allowed; }
        #manageUsersPage select option { padding: 8px 10px; background: #fff; color: #202a35; }
        #manageUsersPage .manage-native-select {
            position: absolute;
            width: 1px !important;
            height: 1px;
            padding: 0;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            clip-path: inset(50%);
        }
        #manageUsersPage .manage-select { position: relative; width: 100%; min-width: 0; }
        #manageUsersPage .role-field .manage-select { width: 145px; }
        #manageUsersPage .manage-select-trigger {
            display: flex;
            width: 100%;
            min-height: 42px;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 9px 12px;
            overflow: hidden;
            border: 1px solid #d5ded8;
            border-radius: 8px;
            background: #fff;
            color: #202a35;
            font: inherit;
            font-size: 13px;
            line-height: 1.35;
            text-align: left;
            cursor: pointer;
            transition: border-color .16s ease, box-shadow .16s ease, background-color .16s ease;
        }
        #manageUsersPage .manage-select-trigger::after {
            width: 13px;
            height: 13px;
            flex: 0 0 13px;
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath d='m3 6 5 5 5-5' fill='none' stroke='%23536159' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") center / contain no-repeat;
            content: "";
            transition: transform .16s ease;
        }
        #manageUsersPage .manage-select-trigger[aria-expanded="true"]::after { transform: rotate(180deg); }
        #manageUsersPage .manage-select-trigger:hover:not(:disabled) { border-color: #aab9af; background: #fcfdfc; }
        #manageUsersPage .manage-select-trigger:focus-visible { border-color: #107c41; outline: 0; box-shadow: 0 0 0 3px rgba(16, 124, 65, .12); }
        #manageUsersPage .manage-select-trigger:disabled { border-color: #e0e5e2; background: #f1f4f2; color: #89938d; cursor: not-allowed; }
        #manageUsersPage .manage-select-trigger[aria-invalid="true"] { border-color: #b3261e; box-shadow: 0 0 0 3px rgba(179, 38, 30, .1); }
        #manageUsersPage .manage-select-error { display: block; margin-top: 5px; color: #a3261e; font-size: 11px; }
        #manageUsersPage .manage-select-error[hidden] { display: none; }
        .manage-select-menu {
            position: fixed;
            z-index: 3000;
            display: grid;
            max-height: min(300px, calc(100vh - 16px));
            padding: 5px;
            overflow-y: auto;
            overscroll-behavior: contain;
            border: 1px solid #d7e0da;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 12px 32px rgba(28, 42, 33, .18);
        }
        .manage-select-option {
            display: flex;
            min-height: 38px;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 8px 10px;
            border: 0;
            border-radius: 6px;
            background: #fff;
            color: #26332b;
            font: inherit;
            font-size: 13px;
            text-align: left;
            cursor: pointer;
        }
        .manage-select-option:hover,
        .manage-select-option[aria-selected="true"],
        .manage-select-option[data-active="true"] { background: #edf6ef; color: #14532d; }
        .manage-select-option[aria-selected="true"] { font-weight: 700; }
        .manage-select-option[aria-selected="true"]::after { color: #107c41; content: "✓"; font-weight: 700; }
        .manage-select-menu .manage-select-option:hover { transform: none; }
        @media (max-width: 760px) { #manageUsersPage .role-field .manage-select { width: 145px; } }
        #manageUsersPage .topbar { align-items: center; flex-wrap: wrap; }
        #manageUsersPage .topbar h1 { margin: 0; }
        #manageUsersPage .nav { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        #manageUsersPage .create-account-form,
        #manageUsersPage .link-candidate-form { display: grid; align-items: end; gap: 12px; }
        #manageUsersPage .create-account-form { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        #manageUsersPage .link-candidate-form { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        #manageUsersPage .add-form > .f { min-width: 0; margin: 0; }
        #manageUsersPage .add-form > button { justify-self: start; }
        #manageUsersPage .create-account-form > button { grid-column: 1 / -1; }
        #manageUsersPage .table-scroll { border: 1px solid var(--ln); border-radius: 8px; }
        #manageUsersPage .accounts-scroll { overflow: auto; border: 0; }
        #manageUsersPage .account-view-switcher { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 14px; padding: 5px; border: 1px solid #e0e7e2; border-radius: 10px; background: #f4f7f5; }
        #manageUsersPage .account-view-button { display: inline-flex; min-height: 38px; align-items: center; padding: 7px 12px; border: 1px solid transparent; border-radius: 7px; background: transparent; color: #536159; font: inherit; font-weight: 650; cursor: pointer; }
        #manageUsersPage .account-view-button:hover { background: #e9f0eb; }
        #manageUsersPage .account-view-button[aria-pressed="true"] { border-color: #d7e4da; background: #fff; color: #14532d; box-shadow: 0 1px 3px rgba(28, 42, 33, .08); }
        #manageUsersPage #accountsEmptyMessage { margin: 0; padding: 24px; border: 1px dashed #d5ded8; border-radius: 10px; color: #6b756f; text-align: center; }
        #manageUsersPage #accountsTable { min-width: 1660px; table-layout: fixed; border-collapse: separate; border-spacing: 0 9px; }
        #manageUsersPage #accountsTable thead th { position: sticky; top: 0; z-index: 2; padding: 11px 12px; background: #edf2ee; color: #536159; border-bottom: 1px solid #dfe6e1; }
        #manageUsersPage #accountsTable tbody td { padding: 14px 12px; background: #fff; border-top: 1px solid #e3e9e5; border-bottom: 1px solid #e3e9e5; }
        #manageUsersPage #accountsTable tbody td:first-child { border-left: 1px solid #e3e9e5; border-radius: 10px 0 0 10px; }
        #manageUsersPage #accountsTable tbody td:last-child { border-right: 1px solid #e3e9e5; border-radius: 0 10px 10px 0; }
        #manageUsersPage #accountsTable tbody tr:hover td { background: #f7faf8; border-color: #cbd9cf; }
        #manageUsersPage #accountsTable th:nth-child(1),
        #manageUsersPage #accountsTable td:nth-child(1) { width: 150px; }
        #manageUsersPage #accountsTable th:nth-child(2),
        #manageUsersPage #accountsTable td:nth-child(2) { width: 230px; }
        #manageUsersPage #accountsTable th:nth-child(3),
        #manageUsersPage #accountsTable td:nth-child(3) { width: 200px; }
        #manageUsersPage #accountsTable th:nth-child(4),
        #manageUsersPage #accountsTable td:nth-child(4) { width: 620px; }
        #manageUsersPage #accountsTable th:nth-child(5),
        #manageUsersPage #accountsTable td:nth-child(5) { width: 235px; }
        #manageUsersPage #accountsTable th:nth-child(6),
        #manageUsersPage #accountsTable td:nth-child(6) { width: 150px; }
        #manageUsersPage #accountsTable th:nth-child(7),
        #manageUsersPage #accountsTable td:nth-child(7) { width: 75px; }
        #manageUsersPage #accountsTable tbody td { vertical-align: middle; }
        #manageUsersPage #accountsTable .badge { display: table; max-width: 100%; margin: 6px 0 0; white-space: normal; line-height: 1.4; }
        #manageUsersPage .access-profile-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: end; gap: 10px 12px; }
        #manageUsersPage .access-profile-fields > button { grid-column: 1 / -1; justify-self: end; min-width: 110px; }
        #manageUsersPage .access-profile-fields .profile-field,
        #manageUsersPage .role-field,
        #manageUsersPage .password-form .password-field,
        #manageUsersPage .email-field { display: flex; min-width: 0; flex-direction: column; gap: 3px; }
        #manageUsersPage .profile-field > span,
        #manageUsersPage .role-field > span,
        #manageUsersPage .password-form .password-field > span,
        #manageUsersPage .email-field > span { color: #52635a; font-size: 10px; font-weight: 700; line-height: 1.2; }
        #manageUsersPage .access-profile-fields .profile-field[hidden] { display: none; }
        #manageUsersPage .access-profile-fields select,
        #manageUsersPage .role-field select,
        #manageUsersPage .password-form input { min-height: 40px; }
        #manageUsersPage .access-profile-fields select,
        #manageUsersPage .role-field select,
        #manageUsersPage .password-form input { border-color: #d7e0da; border-radius: 7px; }
        #manageUsersPage #accountsTable .btn.sm { min-height: 36px; border-radius: 7px; }
        #manageUsersPage .password-form { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: end; gap: 8px; }
        #manageUsersPage .password-form input { width: 100%; min-width: 0; }
        #manageUsersPage .password-form button { white-space: nowrap; }
        #manageUsersPage .email-field input { width: 100%; min-width: 0; }
        @media (max-width: 760px) {
            #manageUsersPage .topbar { align-items: flex-start; }
            #manageUsersPage .nav { width: 100%; }
            #manageUsersPage .create-account-form,
            #manageUsersPage .link-candidate-form { grid-template-columns: minmax(0, 1fr); }
            #manageUsersPage .create-account-form > button { grid-column: auto; }
        }
        @media print { .no-print { display: none; } }
        #manageUsersPage { min-height: calc(100vh - 24px); padding: clamp(16px, 2.5vh, 30px) clamp(14px, 2.5vw, 32px); }
        #manageUsersPage .topbar { min-height: 170px; padding: clamp(24px, 3vw, 36px); border: 0; border-radius: 20px; background: linear-gradient(118deg, #0b5c30 0%, #107c41 58%, #239556 100%); box-shadow: 0 16px 38px rgba(17, 91, 48, .16); color: #fff; }
        #manageUsersPage .page-heading { max-width: 620px; }
        #manageUsersPage .topbar h1 { margin: 0 0 8px; color: #fff; font-size: clamp(29px, 4vw, 40px); letter-spacing: -.04em; }
        #manageUsersPage .page-intro { margin: 0; color: rgba(255,255,255,.82); font-size: 13px; line-height: 1.55; }
        #manageUsersPage .topbar .nav { gap: 8px; }
        #manageUsersPage .topbar .nav .btn:not(.d) { min-height: 40px; padding: 8px 13px; border: 1px solid rgba(255,255,255,.3); border-radius: 9px; background: rgba(255,255,255,.1); color: #fff; font-weight: 650; }
        #manageUsersPage .topbar .nav .btn:not(.d):hover { background: rgba(255,255,255,.2); }
        #manageUsersPage .topbar .nav .btn.d { min-height: 40px; padding: 8px 13px; border-radius: 9px; }
        #manageUsersPage .cd { padding: clamp(18px, 2.4vw, 28px); border-radius: 16px; box-shadow: 0 7px 22px rgba(26,52,35,.04); }
        #manageUsersPage .cd h2 { margin-bottom: 14px; font-size: 18px; letter-spacing: -.02em; }
        #manageUsersPage .hint { line-height: 1.55; }
        #manageUsersPage .account-view-switcher { border-radius: 11px; }
        #manageUsersPage .table-scroll { border-radius: 12px; }
        #manageUsersPage #accountsTable { border-spacing: 0 10px; }
        #manageUsersPage #accountsTable tbody td { padding-top: 16px; padding-bottom: 16px; }
        @media (max-width: 760px) {
            #manageUsersPage { min-height: calc(100vh - 16px); padding: 14px 12px; }
            #manageUsersPage .topbar { min-height: 0; gap: 18px; padding: 24px; border-radius: 17px; }
            #manageUsersPage .topbar .nav { gap: 7px; }
            #manageUsersPage .topbar .nav .btn { flex: 1 1 auto; text-align: center; }
            #manageUsersPage .cd { padding: 18px; }
        }
        @media (max-width: 480px) {
            #manageUsersPage { padding: 10px; }
            #manageUsersPage .topbar { padding: 21px 18px; border-radius: 15px; }
            #manageUsersPage .page-intro { font-size: 12px; }
            #manageUsersPage .topbar .nav .btn { flex: 1 1 calc(50% - 7px); }
            #manageUsersPage .cd { padding: 15px; border-radius: 13px; }
        }
    </style>
</head>
<body>
<div class="wrap pg" id="manageUsersPage">
    <div class="topbar ph">
        <div class="page-heading">
            <h1>Manage Users</h1>
            <p class="page-intro">Create accounts, set access profiles, and manage account recovery from one place.</p>
        </div>
        <div class="nav ag no-print">
            <a class="btn" href="dashboard.php">Dashboard</a>
            <a class="btn" href="results_2.php">&larr; Results</a>
            <a class="btn" href="bulk_users.php">Bulk Users</a>
            <a class="btn d" href="logout.php">Log out (<?php echo h($_SESSION['username'] ?? ''); ?>)</a>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'error'; ?>">
            <?php echo h($flash['msg']); ?>
        </div>
    <?php endif; ?>

    <div class="cd">
        <h2>Add a new account</h2>
        <form method="POST" action="manage_users.php" class="add-form r2 create-account-form" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
            <input type="hidden" name="action" value="create">
            <div class="f fl">
                <label for="new_username">Username</label>
                <input type="text" id="new_username" name="username" required pattern="[\p{L}0-9 .\-]{3,100}" title="3 to 100 characters: letters (including ñ), numbers, spaces, dots, and hyphens." autocomplete="off">
            </div>
            <div class="f fl">
                <label for="new_email">Email address</label>
                <input type="email" id="new_email" name="email" required maxlength="254" autocomplete="email">
            </div>
            <div class="f fl">
                <label for="new_password">Password</label>
                <input type="password" id="new_password" name="password" required
                       minlength="<?php echo MIN_PASSWORD_LENGTH; ?>" maxlength="<?php echo MAX_PASSWORD_LENGTH; ?>"
                       autocomplete="new-password">
            </div>
            <div class="f fl">
                <label for="new_role">Role</label>
                <select id="new_role" name="role" required>
                    <option value="rater">Rater</option>
                    <option value="user">Standard User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="f fl">
                <label for="new_account_type">Account type</label>
                <select id="new_account_type" name="account_type" required>
                    <option value="">-- Select type --</option>
                    <?php foreach (ACCESS_ACCOUNT_TYPES as $value => $label): ?>
                        <option value="<?php echo h($value); ?>"><?php echo h($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="f fl">
                <label for="new_region">Region</label>
                <select id="new_region" name="region" required>
                    <option value="">-- Select region --</option>
                    <?php foreach (ACCESS_REGIONS as $value => $label): ?>
                        <option value="<?php echo h($value); ?>"><?php echo h($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="f fl">
                <label for="new_salary_grade">Salary grade</label>
                <select id="new_salary_grade" name="salary_grade" required <?php echo $salaryGrades ? '' : 'disabled'; ?>>
                    <option value="">-- Select salary grade --</option>
                    <?php foreach ($salaryGrades as $grade): ?>
                        <option value="<?php echo $grade; ?>"><?php echo $grade; ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$salaryGrades): ?><span class="muted mu">No Region or Field salary grades exist. Seed or import positions first.</span><?php endif; ?>
            </div>
            <div class="f fl" id="newRaterPositionField">
                <label for="new_rater_position">Rater position</label>
                <select id="new_rater_position" name="rater_position">
                    <option value="">-- Not assigned --</option>
                    <?php foreach (rater_list() as $raterPosition): ?>
                        <option value="<?php echo h($raterPosition); ?>"><?php echo h($raterPosition); ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="muted mu">Required when role is Rater.</span>
            </div>
            <button type="submit" class="btn p">Create account</button>
        </form>

        <p class="hint mu">
            Passwords: <?php echo MIN_PASSWORD_LENGTH; ?>-<?php echo MAX_PASSWORD_LENGTH; ?> characters.
            Add the account email so it can be used to sign in and receive password reset links.
        </p>
    </div>

    <div class="cd">
        <h2>Link candidate records to Standard User accounts</h2>
        <p class="hint mu">Standard Users can only see candidate records explicitly linked to their account. Existing records are not matched automatically by name.</p>
        <form method="POST" action="manage_users.php" class="add-form r2 link-candidate-form" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
            <input type="hidden" name="action" value="link_candidate">
            <div class="f fl">
                <label for="candidate_link_user">Standard User account</label>
                <select id="candidate_link_user" name="user_id" required>
                    <option value="">-- Select account --</option>
                    <?php foreach ($users as $user): if ($user['role'] !== 'user') continue; ?>
                        <option value="<?php echo (int)$user['id']; ?>"><?php echo h($user['username']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="f fl">
                <label for="candidate_link_record">Candidate record</label>
                <select id="candidate_link_record" name="candidate_id" required>
                    <option value="">-- Select candidate --</option>
                    <?php foreach ($candidateRecords as $candidate): ?>
                        <?php
                        $candidateLabel = '#' . $candidate['id'] . ' · ' . ($candidate['candidate_name'] ?: 'Unnamed candidate')
                            . ' · ' . ($candidate['position_title'] ?: 'No position');
                        if (!empty($candidate['username'])) $candidateLabel .= ' · Linked to ' . $candidate['username'];
                        ?>
                        <option value="<?php echo (int)$candidate['id']; ?>"><?php echo h($candidateLabel); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn p">Link record</button>
        </form>
        <?php if (!$candidateRecords): ?>
            <p class="hint mu">No candidate records are available to link yet.</p>
        <?php endif; ?>
        <div class="table-scroll tbl">
            <table>
                <thead><tr><th>Candidate record</th><th>Linked account</th><th class="no-print"></th></tr></thead>
                <tbody>
                <?php foreach ($candidateRecords as $candidate): if ($candidate['user_id'] === null) continue; ?>
                    <tr>
                        <td><?php echo h(($candidate['candidate_name'] ?: 'Unnamed candidate') . ' · ' . ($candidate['position_title'] ?: 'No position')); ?></td>
                        <td><?php echo h($candidate['username'] ?? 'Deleted account'); ?></td>
                        <td class="no-print">
                            <form method="POST" action="manage_users.php">
                                <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
                                <input type="hidden" name="action" value="unlink_candidate">
                                <input type="hidden" name="candidate_id" value="<?php echo (int)$candidate['id']; ?>">
                                <button type="submit" class="btn sm d">Unlink</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="cd">
        <h2>Accounts (<?php echo count($users); ?>)</h2>
        <div class="account-view-switcher" role="group" aria-label="Account views">
            <button type="button" class="account-view-button" data-account-view="all" aria-pressed="true">All accounts</button>
            <button type="button" class="account-view-button" data-account-view="region" aria-pressed="false">Region raters</button>
            <button type="button" class="account-view-button" data-account-view="field" aria-pressed="false">Field raters</button>
        </div>
        <div class="table-scroll tbl accounts-scroll">
            <table id="accountsTable">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email / login</th>
                        <th>Role / rater type</th>
                        <th>Access profile</th>
                        <th>Reset password</th>
                        <th>Created</th>
                        <th class="no-print"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u):
                    $isMe = ((int)$u['id'] === $myId);
                    $accountType = strtolower((string)($u['account_type'] ?? ''));
                    $accountTypeLabel = ACCESS_ACCOUNT_TYPES[$accountType] ?? 'Not set';
                    $userGrade = $u['salary_grade'] === null ? null : (int)$u['salary_grade'];
                    $typeGrades = $profileGradesByType[$accountType] ?? [];
                    $profileMismatch = $u['role'] === 'rater'
                        && $userGrade !== null
                        && !in_array($userGrade, $typeGrades, true);
                    $eligibleAccountTypes = $profileMismatch
                        ? array_keys(array_filter(
                            $profileGradesByType,
                            static fn(array $candidateGrades): bool => in_array($userGrade, $candidateGrades, true)
                        ))
                        : [];
                    $suggestedAccountType = count($eligibleAccountTypes) === 1
                        ? $eligibleAccountTypes[0]
                        : null;
                ?>
                    <tr data-account-role="<?php echo h($u['role']); ?>" data-account-type="<?php echo h($accountType); ?>">
                        <td>
                            <?php echo h($u['username']); ?>
                            <?php if ($isMe): ?><span class="badge bd you">you</span><?php endif; ?>
                        </td>
                        <td>
                            <?php $settingsFormId = 'user-settings-' . (int)$u['id']; ?>
                            <form id="<?php echo h($settingsFormId); ?>" method="POST" action="manage_users.php">
                                <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
                                <input type="hidden" name="action" value="update_user_settings">
                                <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                            </form>
                            <label class="email-field">
                                    <span>Email for login and recovery</span>
                                    <input type="email" name="email" form="<?php echo h($settingsFormId); ?>" value="<?php echo h($u['email'] ?? ''); ?>"
                                           maxlength="254" placeholder="name@example.com" autocomplete="email">
                            </label>
                        </td>
                        <td>
                            <?php if ($isMe): ?>
                                <?php echo h($u['role']); ?><?php if ($u['role'] === 'rater'): ?> · <?php echo h($accountTypeLabel); ?> account<?php endif; ?>
                                <span class="muted mu">(can't change own role)</span>
                                <input type="hidden" name="role" form="<?php echo h($settingsFormId); ?>" value="<?php echo h($u['role']); ?>">
                            <?php else: ?>
                                <label class="role-field">
                                    <span>Role</span>
                                    <select name="role" form="<?php echo h($settingsFormId); ?>" aria-label="Role for <?php echo h($u['username']); ?>">
                                        <?php foreach (MANAGE_ROLES as $r): ?>
                                            <option value="<?php echo h($r); ?>" <?php echo $u['role'] === $r ? 'selected' : ''; ?>>
                                                <?php echo $r === 'user' ? 'Standard User' : ucfirst($r); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="access-profile-fields">
                                <label class="profile-field">
                                    <span>Account type</span>
                                    <select name="account_type" form="<?php echo h($settingsFormId); ?>" required aria-label="Account type for <?php echo h($u['username']); ?>">
                                        <option value="">-- Select type --</option>
                                        <?php foreach (ACCESS_ACCOUNT_TYPES as $value => $label): ?>
                                            <option value="<?php echo h($value); ?>" <?php echo $u['account_type'] === $value ? 'selected' : ''; ?>><?php echo h($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label class="profile-field">
                                    <span>Region</span>
                                    <select name="region" form="<?php echo h($settingsFormId); ?>" required aria-label="Region for <?php echo h($u['username']); ?>">
                                        <option value="">-- Select region --</option>
                                        <?php foreach (ACCESS_REGIONS as $value => $label): ?>
                                            <option value="<?php echo h($value); ?>" <?php echo normalize_manage_region((string)$u['region']) === $value ? 'selected' : ''; ?>><?php echo h($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label class="profile-field">
                                    <span>Salary grade (SG)</span>
                                    <select name="salary_grade" form="<?php echo h($settingsFormId); ?>" required aria-label="Salary grade for <?php echo h($u['username']); ?>">
                                        <option value="">-- Select grade --</option>
                                        <?php foreach ($profileSalaryGrades as $grade): ?>
                                            <option value="<?php echo $grade; ?>" <?php echo (string)$u['salary_grade'] === (string)$grade ? 'selected' : ''; ?>><?php echo $grade; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label class="profile-field">
                                    <span>Rater position</span>
                                    <select name="rater_position" form="<?php echo h($settingsFormId); ?>" aria-label="Rater position for <?php echo h($u['username']); ?>">
                                        <option value="">-- Not assigned --</option>
                                        <?php foreach (rater_list() as $raterPosition): ?>
                                            <option value="<?php echo h($raterPosition); ?>" <?php echo ($u['rater_position'] ?? '') === $raterPosition ? 'selected' : ''; ?>><?php echo h($raterPosition); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <button type="submit" form="<?php echo h($settingsFormId); ?>" class="btn sm">Save changes</button>
                            </div>
                            <?php if ($profileMismatch): ?>
                                <div class="alert alert-error">
                                    This rater is saved as <?php echo h($accountTypeLabel); ?> at SG <?php echo $userGrade; ?>, which is not configured for that account type.
                                    <?php if ($suggestedAccountType !== null): ?>
                                        SG <?php echo $userGrade; ?> is available for <?php echo h(ACCESS_ACCOUNT_TYPES[$suggestedAccountType]); ?>. Choose that account type and click Save changes.
                                    <?php else: ?>
                                        Change the account type or salary grade to a supported combination and click Save changes.
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" action="manage_users.php" class="inline password-form" autocomplete="off">
                                <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
                                <input type="hidden" name="action" value="reset_password">
                                <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                <label class="password-field">
                                    <span>New password</span>
                                    <input type="password" name="password" placeholder="Enter new password" required
                                           minlength="<?php echo MIN_PASSWORD_LENGTH; ?>" maxlength="<?php echo MAX_PASSWORD_LENGTH; ?>"
                                           autocomplete="new-password">
                                </label>
                                <button type="submit" class="btn sm">Reset</button>
                            </form>
                        </td>
                        <td class="muted mu"><?php echo h($u['created_at']); ?></td>
                        <td class="no-print">
                            <?php if (!$isMe): ?>
                                <form method="POST" action="manage_users.php"
                                      onsubmit="return confirm('Delete account <?php echo h(addslashes($u['username'])); ?>? This cannot be undone.');">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                    <button type="submit" class="btn sm d">Delete</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                    <tr><td colspan="7" class="muted mu">No accounts yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p id="accountsEmptyMessage" role="status" hidden></p>
    </div>
</div>
<script>
    const newRoleSelect = document.getElementById('new_role');
    const newRaterPositionField = document.getElementById('newRaterPositionField');
    const newRaterPositionSelect = document.getElementById('new_rater_position');
    function updateNewRaterPositionRequirement() {
        const isRater = newRoleSelect.value === 'rater';
        newRaterPositionField.hidden = !isRater;
        newRaterPositionSelect.required = isRater;
        newRaterPositionSelect.disabled = !isRater;
        if (!isRater) newRaterPositionSelect.value = '';
    }
    newRoleSelect.addEventListener('change', updateNewRaterPositionRequirement);
    updateNewRaterPositionRequirement();

    const manageSelects = new WeakMap();
    const manageSelectForms = new WeakSet();
    let manageSelectId = 0;
    let typeAheadText = '';
    let typeAheadTimer = 0;

    function syncManageSelect(select, syncRequired = false) {
        const state = manageSelects.get(select);
        if (!state) return;
        if (syncRequired) {
            select.dataset.manageRequired = select.required ? 'true' : 'false';
            select.required = false;
        }
        const selectedOption = select.options[select.selectedIndex];
        state.trigger.textContent = selectedOption ? selectedOption.textContent.trim() : '';
        state.trigger.disabled = select.disabled;
        state.trigger.setAttribute('aria-disabled', String(select.disabled));
        state.trigger.setAttribute('aria-required', String(select.dataset.manageRequired === 'true' && !select.disabled));
        if (select.disabled) closeManageSelect(state);
    }

    function syncManageMenuPosition(state) {
        if (!state.menu || !state.menu.isConnected) return;
        const rect = state.trigger.getBoundingClientRect();
        const width = Math.min(Math.max(rect.width, 220), window.innerWidth - 16);
        state.menu.style.width = width + 'px';
        const left = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8));
        const below = window.innerHeight - rect.bottom - 8;
        const above = rect.top - 8;
        const menuHeight = Math.min(state.menu.scrollHeight, 300, window.innerHeight - 16);
        const placeAbove = below < Math.min(menuHeight, 220) && above > below;
        state.menu.style.left = left + 'px';
        state.menu.style.top = (placeAbove
            ? Math.max(8, rect.top - menuHeight - 6)
            : Math.min(window.innerHeight - menuHeight - 8, rect.bottom + 6)) + 'px';
    }

    function setManageActiveOption(state, index) {
        const options = state.menu?.querySelectorAll('[role="option"]') ?? [];
        if (!options.length) return;
        const enabledIndexes = Array.from(options)
            .map((option, optionIndex) => option.getAttribute('aria-disabled') === 'true' ? -1 : optionIndex)
            .filter(optionIndex => optionIndex >= 0);
        if (!enabledIndexes.length) return;
        const wrappedPosition = ((index % enabledIndexes.length) + enabledIndexes.length) % enabledIndexes.length;
        const activeIndex = enabledIndexes[wrappedPosition];
        state.activeIndex = activeIndex;
        options.forEach((option, optionIndex) => option.dataset.active = String(optionIndex === activeIndex));
        state.trigger.setAttribute('aria-activedescendant', options[activeIndex].id);
        options[activeIndex].scrollIntoView({ block: 'nearest' });
    }

    function closeManageSelect(state, restoreFocus = false) {
        if (!state.menu) return;
        state.menu.remove();
        state.menu = null;
        state.trigger.setAttribute('aria-expanded', 'false');
        state.trigger.removeAttribute('aria-activedescendant');
        document.removeEventListener('pointerdown', state.outsidePointerDown, true);
        document.removeEventListener('scroll', state.reposition, true);
        window.removeEventListener('resize', state.reposition);
        if (restoreFocus) state.trigger.focus();
    }

    function chooseManageOption(state, index) {
        const option = state.select.options[index];
        if (!option || option.disabled) return;
        state.select.value = option.value;
        syncManageSelect(state.select);
        state.error.hidden = true;
        state.trigger.removeAttribute('aria-invalid');
        state.select.dispatchEvent(new Event('input', { bubbles: true }));
        state.select.dispatchEvent(new Event('change', { bubbles: true }));
        closeManageSelect(state, true);
    }

    function openManageSelect(state, initialDirection = 0) {
        if (state.select.disabled || state.menu) return;
        const menu = document.createElement('div');
        menu.className = 'manage-select-menu';
        menu.id = state.menuId;
        menu.setAttribute('role', 'listbox');
        menu.setAttribute('aria-label', state.label);
        Array.from(state.select.options).forEach((option, index) => {
            const item = document.createElement('div');
            item.id = state.menuId + '-option-' + index;
            item.className = 'manage-select-option';
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', String(option.selected));
            item.setAttribute('aria-disabled', String(option.disabled));
            item.textContent = option.textContent.trim();
            item.addEventListener('pointermove', () => {
                if (!option.disabled) setManageActiveOption(state, index);
            });
            item.addEventListener('click', () => chooseManageOption(state, index));
            menu.appendChild(item);
        });
        state.menu = menu;
        document.body.appendChild(menu);
        state.trigger.setAttribute('aria-expanded', 'true');
        const selectedIndex = Math.max(0, state.select.selectedIndex);
        setManageActiveOption(state, selectedIndex + initialDirection);
        syncManageMenuPosition(state);
        state.reposition = () => syncManageMenuPosition(state);
        state.outsidePointerDown = event => {
            if (!state.menu?.contains(event.target) && !state.trigger.contains(event.target)) {
                closeManageSelect(state);
            }
        };
        document.addEventListener('pointerdown', state.outsidePointerDown, true);
        document.addEventListener('scroll', state.reposition, true);
        window.addEventListener('resize', state.reposition);
    }

    function enhanceManageSelect(select) {
        const wrapper = document.createElement('div');
        wrapper.className = 'manage-select';
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);

        const labelElement = document.querySelector('label[for="' + CSS.escape(select.id) + '"]');
        const label = select.getAttribute('aria-label')
            || labelElement?.textContent.trim()
            || select.closest('label')?.querySelector('span')?.textContent.trim()
            || 'Choose an option';
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'manage-select-trigger';
        trigger.setAttribute('role', 'combobox');
        trigger.setAttribute('aria-label', label);
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-required', String(select.required));
        trigger.setAttribute('aria-controls', 'manage-select-menu-' + (++manageSelectId));
        trigger.setAttribute('aria-describedby', 'manage-select-error-' + manageSelectId);
        const error = document.createElement('span');
        error.className = 'manage-select-error';
        error.id = 'manage-select-error-' + manageSelectId;
        error.setAttribute('role', 'alert');
        error.textContent = 'Choose an option to continue.';
        error.hidden = true;
        wrapper.append(trigger, error);

        const state = {
            select,
            wrapper,
            trigger,
            error,
            label,
            menuId: trigger.getAttribute('aria-controls'),
            menu: null,
            activeIndex: 0,
        };
        manageSelects.set(select, state);
        select.dataset.manageRequired = String(select.required);
        select.required = false;
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');
        select.classList.add('manage-native-select');
        syncManageSelect(select);

        select.addEventListener('change', () => syncManageSelect(select));
        trigger.addEventListener('click', () => {
            if (state.menu) closeManageSelect(state);
            else openManageSelect(state);
        });
        trigger.addEventListener('keydown', event => {
            const isOpen = !!state.menu;
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                if (!isOpen) openManageSelect(state, event.key === 'ArrowDown' ? 1 : -1);
                else setManageActiveOption(state, state.activeIndex + (event.key === 'ArrowDown' ? 1 : -1));
            } else if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                if (!isOpen) openManageSelect(state);
                else chooseManageOption(state, state.activeIndex);
            } else if (event.key === 'Escape' && isOpen) {
                event.preventDefault();
                closeManageSelect(state, true);
            } else if (event.key === 'Tab' && isOpen) {
                closeManageSelect(state);
            } else if (event.key === 'Home' && isOpen) {
                event.preventDefault();
                setManageActiveOption(state, 0);
            } else if (event.key === 'End' && isOpen) {
                event.preventDefault();
                setManageActiveOption(state, state.select.options.length - 1);
            } else if (event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
                typeAheadText += event.key.toLocaleLowerCase();
                window.clearTimeout(typeAheadTimer);
                typeAheadTimer = window.setTimeout(() => { typeAheadText = ''; }, 700);
                const start = isOpen ? state.activeIndex + 1 : 0;
                const options = Array.from(state.select.options);
                const match = [...options.slice(start), ...options.slice(0, start)]
                    .findIndex(option => option.textContent.trim().toLocaleLowerCase().startsWith(typeAheadText));
                if (match >= 0) {
                    const actualIndex = (start + match) % options.length;
                    if (isOpen) setManageActiveOption(state, actualIndex);
                    else chooseManageOption(state, actualIndex);
                }
            }
        });

        const form = select.form;
        if (form && !manageSelectForms.has(form)) {
            manageSelectForms.add(form);
            form.addEventListener('submit', event => {
                const missingSelect = Array.from(form.elements)
                    .find(field => field instanceof HTMLSelectElement
                        && field.classList.contains('manage-native-select')
                        && field.dataset.manageRequired === 'true'
                        && !field.disabled
                        && field.value === '');
                if (!missingSelect) return;
                event.preventDefault();
                const missingState = manageSelects.get(missingSelect);
                missingState.error.hidden = false;
                missingState.trigger.setAttribute('aria-invalid', 'true');
                missingState.trigger.focus();
            });
        }
    }

    document.querySelectorAll('#manageUsersPage select').forEach(enhanceManageSelect);
    document.querySelectorAll('#accountsTable select[name="role"]').forEach(roleSelect => {
        const formId = roleSelect.getAttribute('form');
        const raterPositionSelect = Array.from(document.querySelectorAll('#accountsTable select[name="rater_position"]'))
            .find(select => select.getAttribute('form') === formId);
        if (!raterPositionSelect) return;

        const syncRaterPositionRequirement = () => {
            const required = roleSelect.value === 'rater';
            raterPositionSelect.dataset.manageRequired = String(required);
            const state = manageSelects.get(raterPositionSelect);
            if (state) {
                state.trigger.setAttribute('aria-required', String(required));
                state.error.hidden = true;
                state.trigger.removeAttribute('aria-invalid');
            }
        };
        roleSelect.addEventListener('change', syncRaterPositionRequirement);
        syncRaterPositionRequirement();
    });
    document.addEventListener('change', event => {
        if (event.target === newRoleSelect) syncManageSelect(newRaterPositionSelect, true);
    });

    const accountViewButtons = Array.from(document.querySelectorAll('[data-account-view]'));
    const accountRows = Array.from(document.querySelectorAll('#accountsTable tbody tr[data-account-role]'));
    const emptyAccountsRow = document.querySelector('#accountsTable tbody tr:not([data-account-role])');
    const accountsEmptyMessage = document.getElementById('accountsEmptyMessage');
    accountViewButtons.forEach(button => {
        button.addEventListener('click', () => {
            const view = button.dataset.accountView;
            accountViewButtons.forEach(tab => tab.setAttribute('aria-pressed', String(tab === button)));
            let visibleCount = 0;
            accountRows.forEach(row => {
                const matches = view === 'all'
                    || (row.dataset.accountRole === 'rater' && row.dataset.accountType === view);
                row.hidden = !matches;
                if (matches) visibleCount++;
            });
            if (emptyAccountsRow) emptyAccountsRow.hidden = view !== 'all' || accountRows.length > 0;
            accountsEmptyMessage.hidden = visibleCount > 0 || (view === 'all' && accountRows.length === 0);
            accountsEmptyMessage.textContent = view === 'region'
                ? 'No Region raters yet.'
                : (view === 'field' ? 'No Field raters yet.' : 'No accounts found.');
        });
    });
</script>
</body>
</html>
