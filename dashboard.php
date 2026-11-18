<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/AuthSchema.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/Raters.php';
require_once __DIR__ . '/SheetsSchema.php';
require_once __DIR__ . '/AccessGroups.php';

require_role(['admin', 'rater']);
ensure_auth_schema($conn);
ensure_schema($conn);
ensure_competency_template_schema($conn);

function dashboard_h(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$role = (string)current_role();
$username = (string)($_SESSION['username'] ?? '');
$stats = [];
$profile = null;
$profileType = null;

if ($role === 'admin') {
    $result = $conn->query("SELECT
        (SELECT COUNT(*) FROM candidates) AS candidate_count,
        (SELECT COUNT(*) FROM users WHERE role = 'rater') AS rater_count,
        (SELECT COUNT(*) FROM competency_templates WHERE is_shared = 1) AS shared_template_count");
    $stats = $result->fetch_assoc() ?: [];
} else {
    $profile = find_user_access_profile($conn, (int)current_user_id());
    $profileType = account_type_for_profile($profile);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard</title>
    <link rel="stylesheet" href="ui-enhancements.css?v=<?php echo filemtime(__DIR__ . '/ui-enhancements.css'); ?>">
    <script src="ui-enhancements.js?v=<?php echo filemtime(__DIR__ . '/ui-enhancements.js'); ?>" defer></script>
    <style>
        * { box-sizing: border-box; }
        :root { --dash-green: #107c41; --dash-ink: #1c2420; --dash-muted: #68746d; --dash-line: #e0e7e2; }
        body { margin: 0; min-height: 100vh; padding: clamp(16px, 2.5vh, 30px) clamp(16px, 3vw, 44px); background: #f2f5f2; color: var(--dash-ink); font-family: "Public Sans", "Segoe UI", Arial, sans-serif; }
        main { display: flex; width: min(100%, 1560px); min-height: calc(100vh - clamp(32px, 5vh, 60px)); flex-direction: column; gap: 20px; margin: 0 auto; }
        .dashboard-admin, .dashboard-rater { --dash-surface: #fff; --dash-soft: #f5f8f6; }
        .dashboard-hero { position: relative; display: flex; min-height: 208px; align-items: center; justify-content: space-between; gap: 28px; overflow: hidden; padding: clamp(26px, 4vw, 48px); border-radius: 24px; background: linear-gradient(118deg, #0b5c30 0%, #107c41 58%, #239556 100%); color: #fff; box-shadow: 0 18px 44px rgba(17, 91, 48, .18); }
        .dashboard-hero::before, .dashboard-hero::after { position: absolute; border: 1px solid rgba(255,255,255,.11); border-radius: 50%; content: ""; pointer-events: none; }
        .dashboard-hero::before { width: 410px; height: 410px; top: -245px; right: 12%; }
        .dashboard-hero::after { width: 300px; height: 300px; right: -70px; bottom: -218px; }
        .hero-copy, .hero-actions { position: relative; z-index: 1; }
        .kicker { display: inline-flex; align-items: center; gap: 8px; margin: 0 0 14px; color: #c5eed3; font-size: 11px; font-weight: 750; letter-spacing: .14em; text-transform: uppercase; }
        .kicker::before { width: 7px; height: 7px; border-radius: 50%; background: #92e4ad; box-shadow: 0 0 0 4px rgba(146,228,173,.14); content: ""; }
        h1 { margin: 0 0 9px; color: #fff; font-size: clamp(30px, 4.5vw, 48px); font-weight: 700; letter-spacing: -.045em; line-height: 1.08; }
        .intro { max-width: 580px; margin: 0; color: rgba(255,255,255,.8); font-size: 14px; line-height: 1.6; }
        .hero-actions { display: flex; flex: 0 0 auto; flex-wrap: wrap; align-items: center; gap: 10px; }
        .hero-actions a { display: inline-flex; min-height: 46px; align-items: center; justify-content: center; gap: 9px; padding: 0 17px; border: 1px solid rgba(255,255,255,.32); border-radius: 10px; background: rgba(255,255,255,.1); color: #fff; font-size: 13px; font-weight: 700; text-decoration: none; backdrop-filter: blur(8px); transition: background .16s ease, transform .16s ease; }
        .hero-actions a:hover { transform: translateY(-1px); background: rgba(255,255,255,.2); }
        .hero-actions .hero-primary { border-color: rgba(255,255,255,.52); background: rgba(0,0,0,.16); color: #fff; }
        .hero-actions .hero-primary:hover { background: rgba(0,0,0,.25); }
        .hero-actions svg { width: 17px; height: 17px; }
        .stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
        .stat-card { position: relative; display: grid; min-height: 132px; grid-template-columns: 48px minmax(0, 1fr); align-content: center; align-items: center; gap: 5px 16px; padding: 20px 23px; overflow: hidden; border: 1px solid #e3eae5; border-radius: 16px; background: var(--dash-surface); box-shadow: 0 6px 20px rgba(26,52,35,.035); }
        .stat-card::after { position: absolute; width: 94px; height: 94px; top: -52px; right: -44px; border-radius: 50%; background: #f0f7f2; content: ""; }
        .stat-icon { z-index: 1; display: grid; width: 46px; height: 46px; grid-row: span 2; place-items: center; border-radius: 13px; background: #eaf5ed; color: var(--dash-green); }
        .stat-icon svg { width: 21px; height: 21px; }
        .stat-label { z-index: 1; align-self: end; color: var(--dash-muted); font-size: 12px; font-weight: 650; }
        .stat-value { z-index: 1; align-self: start; color: var(--dash-green); font-size: clamp(24px, 2.4vw, 32px); font-weight: 750; letter-spacing: -.04em; line-height: 1.2; overflow-wrap: anywhere; }
        .section-card { display: flex; flex: 1; flex-direction: column; min-height: 350px; padding: clamp(21px, 2.5vw, 32px); border: 1px solid #e3eae5; border-radius: 20px; background: var(--dash-surface); box-shadow: 0 8px 28px rgba(26,52,35,.045); }
        .section-heading { display: flex; align-items: end; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .section-heading h2 { margin: 0 0 5px; font-size: 20px; letter-spacing: -.025em; }
        .section-intro { margin: 0; color: var(--dash-muted); font-size: 13px; line-height: 1.5; }
        .section-count { display: inline-flex; flex: 0 0 auto; align-items: center; padding: 6px 10px; border: 1px solid #e1ebe4; border-radius: 99px; background: #f7faf8; color: #53665a; font-size: 11px; font-weight: 700; }
        .actions { display: grid; flex: 1; grid-template-columns: repeat(3, minmax(0, 1fr)); grid-auto-rows: minmax(150px, 1fr); gap: 14px; }
        .dashboard-rater .actions { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .dashboard-admin .action-card:last-child:nth-child(3n + 2) { grid-column: span 2; }
        .action-card { position: relative; display: flex; min-height: 150px; flex-direction: column; align-items: flex-start; justify-content: space-between; gap: 18px; padding: clamp(18px, 2vw, 25px); overflow: hidden; border: 1px solid #e5ebe7; border-radius: 15px; background: var(--dash-surface); color: inherit; text-decoration: none; transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease, background .18s ease; }
        .action-card:hover { transform: translateY(-3px); border-color: #b6d6c1; background: #fbfefc; box-shadow: 0 12px 25px rgba(27,75,43,.09); }
        .action-card:focus-visible { outline: 3px solid rgba(16,124,65,.28); outline-offset: 3px; }
        .action-top { display: flex; width: 100%; align-items: flex-start; justify-content: space-between; gap: 14px; }
        .action-icon { display: grid; width: 42px; height: 42px; flex: 0 0 42px; place-items: center; border: 1px solid #e1eee5; border-radius: 12px; background: #f0f7f2; color: var(--dash-green); }
        .action-icon svg { width: 20px; height: 20px; }
        .action-arrow { display: grid; width: 30px; height: 30px; place-items: center; border: 1px solid #e5ebe7; border-radius: 50%; color: #637168; font-size: 17px; line-height: 1; transition: color .18s ease, background .18s ease, border-color .18s ease, transform .18s ease; }
        .action-card:hover .action-arrow { transform: translateX(2px); border-color: #c9e2d1; background: #eaf5ed; color: var(--dash-green); }
        .action-copy strong { display: block; margin-bottom: 5px; color: var(--dash-ink); font-size: 15px; font-weight: 700; }
        .action-copy span { display: block; color: var(--dash-muted); font-size: 12px; line-height: 1.55; }
        .notice { padding: 15px 17px; border: 1px solid #f0dca0; border-radius: 12px; background: #fff8e6; color: #705600; font-size: 13px; line-height: 1.5; }
        html[data-theme="dark"] .dashboard-hero { background: linear-gradient(118deg, #123f2a, #145d35 60%, #1b7040); }
        html[data-theme="dark"] .stat-card, html[data-theme="dark"] .section-card, html[data-theme="dark"] .action-card { border-color: #39475a; background: #1b2533; }
        html[data-theme="dark"] .stat-card::after { background: #25372d; }
        html[data-theme="dark"] .stat-icon, html[data-theme="dark"] .action-icon { border-color: #31503c; background: #203326; }
        html[data-theme="dark"] .section-count { border-color: #39475a; background: #202c3b; color: #c7d2df; }
        html[data-theme="dark"] .action-card:hover { border-color: #527a5d; background: #202c3b; }
        html[data-theme="dark"] .action-arrow { border-color: #46566b; color: #bdc9d6; }
        html[data-theme="dark"] .action-card:hover .action-arrow { border-color: #31503c; background: #203326; color: #b8e4c4; }
        @media (max-width: 760px) {
            body { padding: 20px 15px; }
            main { min-height: calc(100vh - 40px); gap: 14px; }
            .dashboard-hero { min-height: 190px; align-items: flex-start; flex-direction: column; justify-content: center; gap: 22px; padding: 27px; border-radius: 20px; }
            .hero-actions { width: 100%; }
            .stats { gap: 10px; }
            .stat-card { min-height: 112px; grid-template-columns: 40px minmax(0, 1fr); gap: 5px 11px; padding: 15px; }
            .stat-icon { width: 38px; height: 38px; border-radius: 11px; }
            .stat-icon svg { width: 18px; height: 18px; }
            .stat-value { font-size: 24px; }
            .section-card { min-height: auto; padding: 20px; }
            .actions, .dashboard-rater .actions { grid-template-columns: repeat(2, minmax(0, 1fr)); grid-auto-rows: minmax(138px, auto); }
        }
        @media (max-width: 480px) {
            body { padding: 12px; }
            main { min-height: auto; gap: 12px; }
            .dashboard-hero { min-height: 0; gap: 20px; padding: 24px 20px; border-radius: 17px; }
            h1 { font-size: 31px; }
            .intro { font-size: 13px; }
            .hero-actions a { flex: 1 1 auto; min-height: 43px; padding: 0 12px; }
            .stats { grid-template-columns: 1fr; gap: 8px; }
            .stat-card { min-height: 78px; grid-template-columns: 38px minmax(0, 1fr); }
            .stat-label { align-self: end; }
            .stat-value { font-size: 22px; }
            .section-card { padding: 17px; border-radius: 16px; }
            .section-heading { align-items: flex-start; flex-direction: column; gap: 8px; margin-bottom: 15px; }
            .actions, .dashboard-rater .actions { grid-template-columns: 1fr; grid-auto-rows: auto; gap: 9px; }
            .dashboard-admin .action-card:last-child:nth-child(3n + 2) { grid-column: auto; }
            .action-card { min-height: 112px; flex-direction: row; align-items: center; justify-content: flex-start; gap: 13px; padding: 15px; }
            .action-top { width: auto; flex: 0 0 auto; align-items: center; gap: 0; }
            .action-arrow { display: none; }
            .action-copy strong { font-size: 14px; }
            .action-copy span { font-size: 12px; }
        }
    </style>
</head>
<body>
<main class="dashboard-<?php echo dashboard_h($role); ?>">
    <header class="dashboard-hero">
        <div class="hero-copy">
            <p class="kicker"><?php echo $role === 'admin' ? 'Administration' : 'Rater workspace'; ?></p>
            <h1>Welcome, <?php echo dashboard_h($username); ?></h1>
            <p class="intro"><?php echo $role === 'admin'
                ? 'Your overview and shortcuts for managing evaluations.'
                : 'Pick up where you left off or start a new evaluation.'; ?></p>
        </div>
        <nav class="hero-actions" aria-label="Account navigation">
            <?php if ($role === 'admin'): ?>
                <a class="hero-primary" href="results_2.php">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 19V5m0 14h17M8 15l4-4 3 3 5-6"/></svg>
                    Open results
                </a>
            <?php else: ?>
                <a class="hero-primary" href="sheet_picker.php">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 4.75A1.75 1.75 0 0 1 6.75 3H19v17H6.75A1.75 1.75 0 0 1 5 18.25z"/><path d="M5 17.5c0-1.1.9-2 2-2h12M9 7h6m-6 4h6"/></svg>
                    Choose a position
                </a>
            <?php endif; ?>
            <a href="logout.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M10 17l5-5-5-5m5 5H3"/><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/></svg>
                Sign out
            </a>
        </nav>
    </header>

    <?php if ($role === 'admin'): ?>
        <section class="stats" aria-label="System overview">
            <article class="stat-card">
                <span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span>
                <span class="stat-label">Interview records</span>
                <strong class="stat-value"><?php echo number_format((int)($stats['candidate_count'] ?? 0)); ?></strong>
            </article>
            <article class="stat-card">
                <span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3"/><path d="M3.5 19a5.5 5.5 0 0 1 11 0m2-13a3 3 0 0 1 0 6m1 2a4.5 4.5 0 0 1 3.5 4.4"/></svg></span>
                <span class="stat-label">Rater accounts</span>
                <strong class="stat-value"><?php echo number_format((int)($stats['rater_count'] ?? 0)); ?></strong>
            </article>
            <article class="stat-card">
                <span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 4.75A1.75 1.75 0 0 1 7.75 3H20v17H7.75A1.75 1.75 0 0 1 6 18.25z"/><path d="M6 17.5c0-1.1.9-2 2-2h12M10 7h6m-6 4h6"/></svg></span>
                <span class="stat-label">Shared templates</span>
                <strong class="stat-value"><?php echo number_format((int)($stats['shared_template_count'] ?? 0)); ?></strong>
            </article>
        </section>
        <section class="section-card">
            <div class="section-heading">
                <div>
                    <h2>Workspace</h2>
                    <p class="section-intro">Your tools for running the evaluation process.</p>
                </div>
                <span class="section-count">5 tools</span>
            </div>
            <div class="actions">
                <a class="action-card" href="results_2.php">
                    <span class="action-top"><span class="action-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V5m0 14h17M8 15l4-4 3 3 5-6"/></svg></span><span class="action-arrow" aria-hidden="true">↗</span></span>
                    <span class="action-copy"><strong>Results &amp; scores</strong><span>Review interview records and scoring progress.</span></span>
                </a>
                <a class="action-card" href="template_manager.php">
                    <span class="action-top"><span class="action-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 4h10a2 2 0 0 1 2 2v15H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><path d="M8 8h7M8 12h8M8 16h5M9 4V2h6v2"/></svg></span><span class="action-arrow" aria-hidden="true">↗</span></span>
                    <span class="action-copy"><strong>Competency templates</strong>Create, update, and share scoring forms.</span>
                </a>
                <a class="action-card" href="manage_users.php">
                    <span class="action-top"><span class="action-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0m2-12h5m-2.5-2.5v5"/></svg></span><span class="action-arrow" aria-hidden="true">↗</span></span>
                    <span class="action-copy"><strong>User accounts</strong>Update roles, emails, and access profiles.</span>
                </a>
                <a class="action-card" href="bulk_users.php">
                    <span class="action-top"><span class="action-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 16V4m-4 4 4-4 4 4"/><path d="M5 14v5a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-5"/></svg></span><span class="action-arrow" aria-hidden="true">↗</span></span>
                    <span class="action-copy"><strong>Bulk users</strong>Import or update multiple accounts at once.</span>
                </a>
                <a class="action-card" href="positions_import.php">
                    <span class="action-top"><span class="action-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s7-5.3 7-11a7 7 0 1 0-14 0c0 5.7 7 11 7 11z"/><circle cx="12" cy="10" r="2.3"/></svg></span><span class="action-arrow" aria-hidden="true">↗</span></span>
                    <span class="action-copy"><strong>Position catalog</strong>Import and maintain positions available to raters.</span>
                </a>
            </div>
        </section>
    <?php else: ?>
        <section class="stats" aria-label="Your access profile">
            <article class="stat-card">
                <span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7.5 12 3l8 4.5-8 4.5z"/><path d="m4 12 8 4.5 8-4.5M4 16.5l8 4.5 8-4.5"/></svg></span>
                <span class="stat-label">Account type</span>
                <strong class="stat-value"><?php echo dashboard_h($profileType !== null ? ACCESS_ACCOUNT_TYPES[$profileType] : 'Not set'); ?></strong>
            </article>
            <article class="stat-card">
                <span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V5m0 14h17M8 15l4-4 3 3 5-6"/></svg></span>
                <span class="stat-label">Salary grade</span>
                <strong class="stat-value"><?php echo $profile && $profile['salary_grade'] !== null ? 'SG ' . (int)$profile['salary_grade'] : 'Not set'; ?></strong>
            </article>
            <article class="stat-card">
                <span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s7-5.3 7-11a7 7 0 1 0-14 0c0 5.7 7 11 7 11z"/><circle cx="12" cy="10" r="2.3"/></svg></span>
                <span class="stat-label">Region</span>
                <strong class="stat-value"><?php echo dashboard_h(ACCESS_REGIONS[(string)($profile['region'] ?? '')] ?? 'Not set'); ?></strong>
            </article>
        </section>
        <?php if (!$profile): ?>
            <p class="notice">Your account profile is incomplete. Ask an administrator to review your access settings before starting an evaluation.</p>
        <?php endif; ?>
        <section class="section-card">
            <div class="section-heading">
                <div>
                    <h2>Your workspace</h2>
                    <p class="section-intro">Start an evaluation or jump back to your recent work.</p>
                </div>
                <span class="section-count"><?php echo dashboard_h($profileType !== null ? ACCESS_ACCOUNT_TYPES[$profileType] . ' rater' : 'Profile setup'); ?></span>
            </div>
            <div class="actions">
                <a class="action-card" href="sheet_picker.php">
                    <span class="action-top"><span class="action-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4.75A1.75 1.75 0 0 1 6.75 3H19v17H6.75A1.75 1.75 0 0 1 5 18.25z"/><path d="M5 17.5c0-1.1.9-2 2-2h12M9 7h6m-6 4h6"/></svg></span><span class="action-arrow" aria-hidden="true">↗</span></span>
                    <span class="action-copy"><strong>Start an evaluation</strong><span>Choose an available position and open its scoring form.</span></span>
                </a>
                <a class="action-card" href="sheet_picker.php#recentFormsSection">
                    <span class="action-top"><span class="action-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span><span class="action-arrow" aria-hidden="true">↗</span></span>
                    <span class="action-copy"><strong>Recent forms</strong><span>Continue or review a saved evaluation.</span></span>
                </a>
            </div>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
