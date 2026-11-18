<?php

require_once __DIR__ . '/bootstrap.php';
require_once 'auth.php';
require_once __DIR__ . '/config.php';
require_once 'db.php';
require_once __DIR__ . '/AuthSchema.php';
require_once __DIR__ . '/AccessGroups.php';
require_once __DIR__ . '/Raters.php';

ensure_auth_schema($conn);

if (is_logged_in()) {
    header('Location: ' . landing_page_for((string)current_role()));
    exit;
}

const REG_MIN_PASSWORD  = 8;
const REG_MAX_PASSWORD  = 72;
const REG_ALLOWED_ROLES = ['rater', 'user'];

$regionSalaryGrades = account_type_salary_grades($conn, 'region');
$fieldSalaryGrades = account_type_salary_grades($conn, 'field');
$signupSalaryGrades = array_values(array_unique(array_merge($regionSalaryGrades, $fieldSalaryGrades)));
sort($signupSalaryGrades);
$availableRaterPositions = rater_list();
$error = '';
$old   = ['username' => '', 'email' => '', 'role' => 'rater', 'account_type' => '', 'salary_grade' => '', 'rater_position' => ''];
$lockSecondsLeft = is_locked_out($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username   = preg_replace('/\s+/', ' ', trim((string)($_POST['username'] ?? '')));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password   = (string)($_POST['password'] ?? '');
    $confirm    = (string)($_POST['password_confirm'] ?? '');
    $postedRole = strtolower(trim((string)($_POST['role'] ?? '')));
    $accountType = strtolower(trim((string)($_POST['account_type'] ?? '')));
    $salaryGradeValue = trim((string)($_POST['salary_grade'] ?? ''));
    $raterPosition = trim((string)($_POST['rater_position'] ?? ''));

    $old['username'] = $username;
    $old['email'] = $email;
    $old['role'] = in_array($postedRole, REG_ALLOWED_ROLES, true) ? $postedRole : 'rater';
    $old['account_type'] = $accountType;
    $old['salary_grade'] = $salaryGradeValue;
    $old['rater_position'] = $raterPosition;

    if (!empty($_POST[HONEYPOT_FIELD])) {
        flash_set('success', 'Account created successfully! Please sign in.');
        header('Location: login.php');
        exit;
    }

    if ($lockSecondsLeft !== null) {
        $error = 'Too many attempts. Try again in ' . max(1, ceil($lockSecondsLeft / 60)) . ' minute(s).';
    } elseif (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } elseif ($postedRole === 'admin') {
        error_log('register.php: blocked attempt to self-register as admin from ' . client_ip());
        register_failed_attempt($conn);
        $error = 'Admin accounts cannot be created through registration.';
    } elseif (!in_array($postedRole, REG_ALLOWED_ROLES, true)) {
        $error = 'Please choose a valid account role.';
    } elseif (!array_key_exists($accountType, ACCESS_ACCOUNT_TYPES)) {
        $error = 'Please choose Region or Field.';
    } elseif (!access_position_salary_grade_is_valid(
        $conn,
        $salaryGradeValue,
        account_type_salary_grades($conn, $accountType)
    )) {
        $error = 'Please choose a salary grade available for your account type.';
    } elseif ($postedRole === 'rater' && !in_array($raterPosition, $availableRaterPositions, true)) {
        $error = 'Please choose your rater position.';
    } elseif (isset($_SESSION['last_register']) && (time() - $_SESSION['last_register']) < 5) {
        $error = 'Please wait a few seconds before trying again.';
    } elseif (!preg_match('/^[\p{L}0-9 .\-]{3,100}$/u', $username)) {
        $error = 'Name must be 3-100 characters: letters (including ñ), numbers, spaces, dots, and hyphens only.';
    } elseif (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (strlen($password) < REG_MIN_PASSWORD) {
        $error = 'Password must be at least ' . REG_MIN_PASSWORD . ' characters.';
    } elseif (strlen($password) > REG_MAX_PASSWORD) {
        $error = 'Password must be at most ' . REG_MAX_PASSWORD . ' characters.';
    } elseif (!hash_equals($password, $confirm)) {
        $error = 'The two passwords do not match.';
    } elseif (find_user_by_username($conn, $username) !== null) {
        $error = 'That name is already taken. Please choose another.';
    } elseif (find_user_by_email($conn, $email) !== null) {
        $error = 'That email address is already associated with an account.';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $role = $postedRole;

            $_SESSION['last_register'] = time();

            $region = ACCESS_DEFAULT_REGION;
            $salaryGrade = (int)$salaryGradeValue;
            $storedRaterPosition = $role === 'rater' ? $raterPosition : null;
            $stmt = $conn->prepare("INSERT INTO users (email, username, password_hash, role, region, field_name, account_type, salary_grade, rater_position) VALUES (?, ?, ?, ?, ?, NULL, ?, ?, ?)");
            $stmt->bind_param('ssssssis', $email, $username, $hash, $role, $region, $accountType, $salaryGrade, $storedRaterPosition);
            $stmt->execute();
            $stmt->close();

            unset($_SESSION['csrf_token']);
            flash_set('success', 'Account created successfully! Please sign in.');
            header('Location: login.php');
            exit;
        } catch (mysqli_sql_exception $e) {
            if ((int)$e->getCode() === 1062) {
                $error = 'That name or email address is already associated with an account.';
            } else {
                error_log('register error: ' . $e->getMessage());
                $error = DEBUG ? $e->getMessage() : 'Could not create the account. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="mockup.css?v=<?php echo filemtime(__DIR__ . '/mockup.css'); ?>">
    <link rel="stylesheet" href="ui-enhancements.css?v=<?php echo filemtime(__DIR__ . '/ui-enhancements.css'); ?>">
    <script src="ui-enhancements.js?v=<?php echo filemtime(__DIR__ . '/ui-enhancements.js'); ?>" defer></script>
    <style>
        #register {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 30px 18px;
            background: radial-gradient(ellipse at top left, #e6f2e9 0, transparent 48%), linear-gradient(135deg, #f5f7f6, #e9efeb);
        }
        #register .lc {
            width: min(100%, 520px);
            padding: 34px 38px 28px;
            border: 1px solid rgba(16, 124, 65, .12);
            border-top: 4px solid var(--br);
            border-radius: 18px;
            box-shadow: 0 18px 48px rgba(27, 55, 39, .12);
        }
        #register .lc h2 { font-size: 26px; letter-spacing: -.03em; }
        #register .lc .sub { margin-bottom: 26px; }
        #register form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 22px; }
        #register form > .fl { min-width: 0; margin-bottom: 18px; }
        #register form > .fl:first-of-type,
        #register form > .sb { grid-column: 1 / -1; }
        #register form > .sb { min-height: 48px; margin-top: 2px; border-radius: 9px; }
        #register .fl input[type="text"],
        #register .fl input[type="email"],
        #register .fl input[type="password"],
        #register .fl input[type="number"],
        #register .fl select {
            width: 100%;
            min-height: 46px;
            padding: 11px 12px;
            border: 1px solid #d5d9e0;
            border-radius: 8px;
            background: #fff;
            font: inherit;
            font-size: 14px;
        }
        #register .fl input[type="text"]:focus,
        #register .fl input[type="email"]:focus,
        #register .fl input[type="password"]:focus,
        #register .fl input[type="number"]:focus,
        #register .fl select:focus {
            outline: none;
            border-color: var(--br);
            box-shadow: 0 0 0 3px rgba(16, 124, 65, .12);
        }
        #register .manage-native-select {
            position: absolute;
            width: 1px !important;
            height: 1px;
            padding: 0;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            clip-path: inset(50%);
        }
        #register .manage-select { position: relative; width: 100%; min-width: 0; }
        #register .manage-select-trigger {
            display: flex;
            width: 100%;
            min-height: 46px;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 11px 12px;
            overflow: hidden;
            border: 1px solid #d5d9e0;
            border-radius: 8px;
            background: #fff;
            color: #202a35;
            font: inherit;
            font-size: 14px;
            line-height: 1.35;
            text-align: left;
            cursor: pointer;
            transition: border-color .16s ease, box-shadow .16s ease, background-color .16s ease;
        }
        #register .manage-select-trigger::after {
            width: 13px;
            height: 13px;
            flex: 0 0 13px;
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath d='m3 6 5 5 5-5' fill='none' stroke='%23536159' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") center / contain no-repeat;
            content: "";
            transition: transform .16s ease;
        }
        #register .manage-select-trigger[aria-expanded="true"]::after { transform: rotate(180deg); }
        #register .manage-select-trigger:hover:not(:disabled) { border-color: #aab9af; background: #fcfdfc; }
        #register .manage-select-trigger:focus-visible { border-color: #107c41; outline: 0; box-shadow: 0 0 0 3px rgba(16, 124, 65, .12); }
        #register .manage-select-trigger:disabled { border-color: #e0e5e2; background: #f1f4f2; color: #89938d; cursor: not-allowed; }
        #register .manage-select-trigger[aria-invalid="true"] { border-color: #b3261e; box-shadow: 0 0 0 3px rgba(179, 38, 30, .1); }
        #register .manage-select-error { display: block; margin-top: 5px; color: #a3261e; font-size: 12px; line-height: 1.4; }
        #register .manage-select-error[hidden] { display: none; }
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
            min-height: 40px;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 8px 10px;
            border: 0;
            border-radius: 6px;
            background: #fff;
            color: #26332b;
            font: inherit;
            font-size: 14px;
            text-align: left;
            cursor: pointer;
        }
        .manage-select-option:hover,
        .manage-select-option[aria-selected="true"],
        .manage-select-option[data-active="true"] { background: #edf6ef; color: #14532d; }
        .manage-select-option[aria-selected="true"] { font-weight: 700; }
        .manage-select-option[aria-selected="true"]::after { color: #107c41; content: "✓"; font-weight: 700; }
        .manage-select-option[aria-disabled="true"] { color: #8b948e; cursor: not-allowed; }
        .manage-select-menu .manage-select-option:hover { transform: none; }
        html[data-theme="dark"] .manage-select-option { border-color: #46566b; background: #111b28; color: #e5eaf0; }
        html[data-theme="dark"] .manage-select-option:hover,
        html[data-theme="dark"] .manage-select-option[aria-selected="true"],
        html[data-theme="dark"] .manage-select-option[data-active="true"] { background: #203326; color: #d3f0db; }
        #register .hint { line-height: 1.5; }
        #register .sw { margin-top: 20px; }
        #register .field-warning { margin-top: 5px; color: #a3261e; font-size: 12px; line-height: 1.4; }
        #register .field-warning[hidden] { display: none; }
        .alert { padding: 10px 14px; border: 1px solid transparent; border-radius: 8px; margin-bottom: 18px; font-size: 13px; }
        .alert-error { background: #fdecea; border-color: #f5c2c0; color: #7a1f16; }
        .alert-warn { background: #fff6e0; border-color: #f0dca0; color: #7a5a00; }
        .hint { margin-top: 5px; }
        @media (max-width: 600px) {
            #register { align-items: flex-start; padding: 18px 12px; }
            #register .lc { width: min(80vw, 480px); padding: 28px 38px 24px; }
        }
        @media (max-width: 440px) {
            #register .lc { width: 100%; }
            #register .lc { padding: 28px 22px 24px; }
            #register form { grid-template-columns: minmax(0, 1fr); gap: 0; }
            #register form > .fl, #register form > .sb { grid-column: 1; }
        }
    </style>
</head>
<body>
    <section id="register"><div class="lc">
        <h2>Sign Up</h2>
        <p class="sub">DENR Interview Scoring Sheet</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($lockSecondsLeft !== null): ?>
            <div class="alert alert-warn">
                Too many attempts. Try again in <?php echo max(1, ceil($lockSecondsLeft / 60)); ?> minute(s).
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <input type="text" name="<?php echo HONEYPOT_FIELD; ?>" tabindex="-1" autocomplete="off" aria-hidden="true"
                   style="position:absolute; left:-9999px; width:1px; height:1px;">

            <div class="fl">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" required maxlength="254"
                       value="<?php echo htmlspecialchars($old['email'], ENT_QUOTES, 'UTF-8'); ?>"
                       placeholder="you@example.com" autocomplete="email"
                       <?php echo $lockSecondsLeft !== null ? 'disabled' : ''; ?>>
                <div class="hint mu">This will be your login and password recovery email.</div>
            </div>

            <div class="fl">
                <label for="username">Full Name / Username</label>
                <input type="text" id="username" name="username" required autofocus
                       minlength="3" maxlength="100" pattern="[\p{L}0-9 .\-]{3,100}"
                       title="3 to 100 characters: letters (including ñ), numbers, spaces, dots, and hyphens."
                       value="<?php echo htmlspecialchars($old['username']); ?>"
                       placeholder="e.g. Juan Dela Cruz" autocomplete="username"
                       <?php echo $lockSecondsLeft !== null ? 'disabled' : ''; ?>>
                <div class="field-warning" id="usernameWarning" hidden>
                    Enter 3-100 characters using letters, numbers, spaces, dots, or dashes.
                </div>
            </div>

            <div class="fl">
                <label for="password">Password</label>
                <div class="pw">
                    <input type="password" id="password" name="password" required
                           minlength="<?php echo REG_MIN_PASSWORD; ?>" maxlength="<?php echo REG_MAX_PASSWORD; ?>"
                           placeholder="At least <?php echo REG_MIN_PASSWORD; ?> characters" autocomplete="new-password"
                           <?php echo $lockSecondsLeft !== null ? 'disabled' : ''; ?>>
                </div>
            </div>

            <div class="fl">
                <label for="password_confirm">Confirm Password</label>
                <div class="pw">
                    <input type="password" id="password_confirm" name="password_confirm" required
                           minlength="<?php echo REG_MIN_PASSWORD; ?>" maxlength="<?php echo REG_MAX_PASSWORD; ?>"
                           placeholder="Type the password again" autocomplete="new-password"
                           <?php echo $lockSecondsLeft !== null ? 'disabled' : ''; ?>>
                </div>
            </div>

            <div class="fl">
                <label for="account_type">Account type</label>
                <select id="account_type" name="account_type" required <?php echo $lockSecondsLeft !== null ? 'disabled' : ''; ?>>
                    <option value="">Region or Field</option>
                    <option value="region" <?php echo $old['account_type'] === 'region' ? 'selected' : ''; ?>>Region</option>
                    <option value="field" <?php echo $old['account_type'] === 'field' ? 'selected' : ''; ?>>Field</option>
                </select>
            </div>

            <div class="fl">
                <label for="role">Role</label>
                <select id="role" name="role" required <?php echo $lockSecondsLeft !== null ? 'disabled' : ''; ?>>
                    <option value="rater" <?php echo $old['role'] === 'rater' ? 'selected' : ''; ?>>Rater</option>
                    <option value="user" <?php echo $old['role'] === 'user' ? 'selected' : ''; ?>>Standard User</option>
                </select>
            </div>

            <div class="fl" id="raterPositionField">
                <label for="rater_position">Rater position</label>
                <select id="rater_position" name="rater_position" required <?php echo $lockSecondsLeft !== null ? 'disabled' : ''; ?>>
                    <option value="">Select your rater position</option>
                    <?php foreach ($availableRaterPositions as $position): ?>
                        <option value="<?php echo htmlspecialchars($position, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $old['rater_position'] === $position ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($position, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="field-warning" id="raterPositionWarning" hidden>
                    Choose your rater position to continue.
                </div>
            </div>

            <div class="fl">
                <label for="region_display">Region</label>
                <input type="text" id="region_display" value="<?php echo htmlspecialchars(ACCESS_REGIONS[ACCESS_DEFAULT_REGION], ENT_QUOTES, 'UTF-8'); ?>" readonly aria-readonly="true">
            </div>

            <div class="fl">
                <label for="salary_grade">Salary Grade</label>
                <select id="salary_grade" name="salary_grade" required <?php echo ($lockSecondsLeft !== null || !$signupSalaryGrades) ? 'disabled' : ''; ?>>
                    <option value="">Salary grade</option>
                    <?php foreach ($signupSalaryGrades as $grade): ?>
                        <option value="<?php echo $grade; ?>" <?php echo $old['salary_grade'] === (string)$grade ? 'selected' : ''; ?>>
                            SG <?php echo $grade; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$signupSalaryGrades): ?>
                    <div class="hint mu">No salary grades are available yet. Ask an administrator to seed or import positions.</div>
                <?php endif; ?>
            </div>

            <button type="submit" class="sb" <?php echo $lockSecondsLeft !== null ? 'disabled' : ''; ?>>
                Sign Up
            </button>
        </form>

        <div class="sw">Already have an account? <a href="login.php">Sign In</a></div>
    </div></section>
<script>
    const accountTypeSelect = document.getElementById('account_type');
    const salaryGradeSelect = document.getElementById('salary_grade');
    const roleSelect = document.getElementById('role');
    const raterPositionField = document.getElementById('raterPositionField');
    const raterPositionSelect = document.getElementById('rater_position');
    const registrationForm = document.querySelector('#register form');
    const registrationWarnings = new Map([
        [document.getElementById('username'), document.getElementById('usernameWarning')],
        [raterPositionSelect, document.getElementById('raterPositionWarning')],
    ]);
    const touchedRegistrationFields = new WeakSet();

    function updateRegistrationWarning(field, reveal = false) {
        const warning = registrationWarnings.get(field);
        if (!warning) return;
        if (reveal) touchedRegistrationFields.add(field);
        const required = field.required || field.dataset.registrationRequired === 'true';
        const invalid = !field.validity.valid
            || (field.dataset.registrationRequired === 'true' && field.value === '');
        warning.hidden = field.disabled
            || !required
            || (field.id !== 'username' && !touchedRegistrationFields.has(field))
            || !invalid;
    }

    registrationWarnings.forEach((warning, field) => {
        field.addEventListener('blur', () => updateRegistrationWarning(field, true));
        field.addEventListener('input', () => updateRegistrationWarning(field));
        field.addEventListener('change', () => updateRegistrationWarning(field));
    });
    registrationForm.addEventListener('invalid', event => {
        if (registrationWarnings.has(event.target)) {
            updateRegistrationWarning(event.target, true);
        }
    }, true);
    updateRegistrationWarning(document.getElementById('username'));

    const registrationSelects = new WeakMap();
    const registrationSelectForms = new WeakSet();
    let registrationSelectId = 0;
    let registrationTypeAheadText = '';
    let registrationTypeAheadTimer = 0;

    function syncRegistrationSelect(select, syncRequired = false) {
        const state = registrationSelects.get(select);
        if (!state) return;
        if (syncRequired) {
            select.dataset.registrationRequired = String(select.required);
            select.required = false;
        }
        const selectedOption = select.options[select.selectedIndex];
        state.trigger.textContent = selectedOption ? selectedOption.textContent.trim() : '';
        state.trigger.disabled = select.disabled;
        state.trigger.setAttribute('aria-disabled', String(select.disabled));
        state.trigger.setAttribute('aria-required', String(select.dataset.registrationRequired === 'true' && !select.disabled));
        if (select.disabled) closeRegistrationSelect(state);
    }

    function positionRegistrationMenu(state) {
        if (!state.menu?.isConnected) return;
        const rect = state.trigger.getBoundingClientRect();
        const width = Math.min(Math.max(rect.width, 220), window.innerWidth - 16);
        state.menu.style.width = width + 'px';
        state.menu.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8)) + 'px';
        const below = window.innerHeight - rect.bottom - 8;
        const above = rect.top - 8;
        const menuHeight = Math.min(state.menu.scrollHeight, 300, window.innerHeight - 16);
        const placeAbove = below < Math.min(menuHeight, 220) && above > below;
        state.menu.style.top = (placeAbove
            ? Math.max(8, rect.top - menuHeight - 6)
            : Math.min(window.innerHeight - menuHeight - 8, rect.bottom + 6)) + 'px';
    }

    function setRegistrationActiveOption(state, index) {
        const options = Array.from(state.menu?.querySelectorAll('[role="option"]') ?? []);
        const enabledIndexes = options
            .map((option, optionIndex) => option.getAttribute('aria-disabled') === 'true' ? -1 : optionIndex)
            .filter(optionIndex => optionIndex >= 0);
        if (!enabledIndexes.length) return;
        const activePosition = ((index % enabledIndexes.length) + enabledIndexes.length) % enabledIndexes.length;
        const activeIndex = enabledIndexes[activePosition];
        state.activeIndex = activeIndex;
        options.forEach((option, optionIndex) => option.dataset.active = String(optionIndex === activeIndex));
        state.trigger.setAttribute('aria-activedescendant', options[activeIndex].id);
        options[activeIndex].scrollIntoView({ block: 'nearest' });
    }

    function closeRegistrationSelect(state, restoreFocus = false) {
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

    function chooseRegistrationOption(state, index) {
        const option = state.select.options[index];
        if (!option || option.disabled || option.hidden) return;
        state.select.value = option.value;
        syncRegistrationSelect(state.select);
        state.error.hidden = true;
        state.trigger.removeAttribute('aria-invalid');
        state.select.dispatchEvent(new Event('input', { bubbles: true }));
        state.select.dispatchEvent(new Event('change', { bubbles: true }));
        closeRegistrationSelect(state, true);
    }

    function openRegistrationSelect(state, initialDirection = 0) {
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
            item.setAttribute('aria-disabled', String(option.disabled || option.hidden));
            item.textContent = option.textContent.trim();
            item.hidden = option.hidden;
            item.addEventListener('pointermove', () => {
                if (!option.disabled && !option.hidden) setRegistrationActiveOption(state, index);
            });
            item.addEventListener('click', () => chooseRegistrationOption(state, index));
            menu.appendChild(item);
        });
        state.menu = menu;
        document.body.appendChild(menu);
        state.trigger.setAttribute('aria-expanded', 'true');
        setRegistrationActiveOption(state, Math.max(0, state.select.selectedIndex + initialDirection));
        positionRegistrationMenu(state);
        state.reposition = () => positionRegistrationMenu(state);
        state.outsidePointerDown = event => {
            if (!state.menu?.contains(event.target) && !state.trigger.contains(event.target)) {
                closeRegistrationSelect(state);
            }
        };
        document.addEventListener('pointerdown', state.outsidePointerDown, true);
        document.addEventListener('scroll', state.reposition, true);
        window.addEventListener('resize', state.reposition);
    }

    function enhanceRegistrationSelect(select) {
        const wrapper = document.createElement('div');
        wrapper.className = 'manage-select';
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);

        const label = document.querySelector('label[for="' + CSS.escape(select.id) + '"]')?.textContent.trim()
            || 'Choose an option';
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'manage-select-trigger';
        trigger.setAttribute('role', 'combobox');
        trigger.setAttribute('aria-label', label);
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-controls', 'registration-select-menu-' + (++registrationSelectId));
        trigger.setAttribute('aria-describedby', 'registration-select-error-' + registrationSelectId);
        const error = document.createElement('span');
        error.className = 'manage-select-error';
        error.id = 'registration-select-error-' + registrationSelectId;
        error.setAttribute('role', 'alert');
        error.textContent = 'Choose an option to continue.';
        error.hidden = true;
        wrapper.append(trigger, error);

        const state = {
            select,
            trigger,
            error,
            label,
            menuId: trigger.getAttribute('aria-controls'),
            menu: null,
            activeIndex: 0,
        };
        registrationSelects.set(select, state);
        select.dataset.registrationRequired = String(select.required);
        select.required = false;
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');
        select.classList.add('manage-native-select');
        syncRegistrationSelect(select);
        select.addEventListener('change', () => syncRegistrationSelect(select));
        trigger.addEventListener('click', () => {
            if (state.menu) closeRegistrationSelect(state);
            else openRegistrationSelect(state);
        });
        trigger.addEventListener('keydown', event => {
            const isOpen = !!state.menu;
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                if (!isOpen) openRegistrationSelect(state, event.key === 'ArrowDown' ? 1 : -1);
                else setRegistrationActiveOption(state, state.activeIndex + (event.key === 'ArrowDown' ? 1 : -1));
            } else if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                if (!isOpen) openRegistrationSelect(state);
                else chooseRegistrationOption(state, state.activeIndex);
            } else if (event.key === 'Escape' && isOpen) {
                event.preventDefault();
                closeRegistrationSelect(state, true);
            } else if (event.key === 'Tab' && isOpen) {
                closeRegistrationSelect(state);
            } else if (event.key === 'Home' && isOpen) {
                event.preventDefault();
                setRegistrationActiveOption(state, 0);
            } else if (event.key === 'End' && isOpen) {
                event.preventDefault();
                setRegistrationActiveOption(state, state.select.options.length - 1);
            } else if (event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
                registrationTypeAheadText += event.key.toLocaleLowerCase();
                window.clearTimeout(registrationTypeAheadTimer);
                registrationTypeAheadTimer = window.setTimeout(() => { registrationTypeAheadText = ''; }, 700);
                const options = Array.from(state.select.options);
                const start = isOpen ? state.activeIndex + 1 : 0;
                const match = [...options.slice(start), ...options.slice(0, start)]
                    .findIndex(option => !option.disabled && !option.hidden
                        && option.textContent.trim().toLocaleLowerCase().startsWith(registrationTypeAheadText));
                if (match >= 0) {
                    const actualIndex = (start + match) % options.length;
                    if (isOpen) setRegistrationActiveOption(state, actualIndex);
                    else chooseRegistrationOption(state, actualIndex);
                }
            }
        });

        const form = select.form;
        if (form && !registrationSelectForms.has(form)) {
            registrationSelectForms.add(form);
            form.addEventListener('submit', event => {
                const missingSelect = Array.from(form.elements).find(field =>
                    field instanceof HTMLSelectElement
                    && field.classList.contains('manage-native-select')
                    && field.dataset.registrationRequired === 'true'
                    && !field.disabled
                    && field.value === ''
                );
                if (!missingSelect) return;
                event.preventDefault();
                const missingState = registrationSelects.get(missingSelect);
                missingState.error.hidden = false;
                missingState.trigger.setAttribute('aria-invalid', 'true');
                updateRegistrationWarning(missingSelect, true);
                missingState.trigger.focus();
            });
        }
    }

    document.querySelectorAll('#register select').forEach(enhanceRegistrationSelect);

    const salaryGradesByType = {
        region: <?php echo json_encode($regionSalaryGrades); ?>,
        field: <?php echo json_encode($fieldSalaryGrades); ?>
    };
    const registrationLocked = <?php echo json_encode($lockSecondsLeft !== null); ?>;
    function updateSalaryGrades() {
        const allowedGrades = salaryGradesByType[accountTypeSelect.value] || [];
        salaryGradeSelect.disabled = registrationLocked || allowedGrades.length === 0;
        Array.from(salaryGradeSelect.options).forEach(option => {
            if (option.value) option.hidden = !allowedGrades.includes(Number(option.value));
        });
        if (!allowedGrades.includes(Number(salaryGradeSelect.value))) {
            salaryGradeSelect.value = '';
        }
        syncRegistrationSelect(salaryGradeSelect);
    }
    accountTypeSelect.addEventListener('change', updateSalaryGrades);
    function updateRaterPosition() {
        const isRater = roleSelect.value === 'rater';
        raterPositionField.hidden = !isRater;
        raterPositionSelect.required = isRater;
        raterPositionSelect.disabled = registrationLocked || !isRater;
        if (!isRater) raterPositionSelect.value = '';
        syncRegistrationSelect(raterPositionSelect, true);
        updateRegistrationWarning(raterPositionSelect);
    }
    roleSelect.addEventListener('change', updateRaterPosition);
    updateSalaryGrades();
    updateRaterPosition();
</script>
</body>
</html>
