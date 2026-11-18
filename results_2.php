<?php
require_once __DIR__ . '/bootstrap.php';
require_once 'db.php';
require_once __DIR__ . '/AuthSchema.php';
require_once 'auth.php';

require_admin();
require_once __DIR__ . '/Raters.php';
require_once __DIR__ . '/sheets_schema.php';
ensure_auth_schema($conn);
ensure_schema($conn);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$storedOrders = [];
try {
    $storedOrders = all_position_rater_orders($conn);
} catch (Exception $e) {
    error_log('results_2 rater order load error: ' . $e->getMessage());
}

$canonicalStoredOrders = [];
foreach ($storedOrders as $off => $positions) {
    foreach ($positions as $pos => $raters) {
        $canonicalPos = canonical_position_title($pos);
        if (!isset($canonicalStoredOrders[$off][$canonicalPos])) {
            $canonicalStoredOrders[$off][$canonicalPos] = [];
        }
        foreach ($raters as $r) {
            if (!in_array($r, $canonicalStoredOrders[$off][$canonicalPos], true)) {
                $canonicalStoredOrders[$off][$canonicalPos][] = $r;
            }
        }
    }
}
$storedOrders = $canonicalStoredOrders;

$scoreMap = [];
$scoreAccountTypes = [];
$sr = $conn->query("SELECT cs.candidate_id, cs.rater, cs.psycho, cs.potential, u.account_type
    FROM candidate_scores cs
    LEFT JOIN users u ON u.id = cs.rated_by");
if ($sr) {
    while ($sc = $sr->fetch_assoc()) {
        $candidateId = (int)$sc['candidate_id'];
        $scoreMap[$candidateId][$sc['rater']] = [
            'p' => $sc['psycho'] !== null ? (float)$sc['psycho'] : null,
            't' => $sc['potential'] !== null ? (float)$sc['potential'] : null,
        ];
        $accountType = strtolower(trim((string)($sc['account_type'] ?? '')));
        if (!isset($scoreAccountTypes[$candidateId])) $scoreAccountTypes[$candidateId] = ['types' => [], 'unknown' => false];
        if ($accountType === 'region') {
            $scoreAccountTypes[$candidateId]['types']['RO'] = true;
        } elseif ($accountType === 'field') {
            $scoreAccountTypes[$candidateId]['types']['FO'] = true;
        } else {
            $scoreAccountTypes[$candidateId]['unknown'] = true;
        }
    }
}

$result = $conn->query("SELECT * FROM candidates ORDER BY office_name ASC, interview_date ASC, id ASC");
$groupedCandidates = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $office = !empty($row['office_name']) ? strtoupper(trim($row['office_name'])) : 'REGIONAL OFFICE';
        $position = canonical_position_title((string)($row['position_title'] ?? 'LMO I'));
        if (!isset($groupedCandidates[$office])) $groupedCandidates[$office] = [];
        $id = (int)$row['id'];
        $legacyP = []; $legacyT = [];
        foreach (['core_avg', 'org_avg', 'psycho_extra1', 'psycho_extra2', 'psycho_extra3', 'psycho_extra4'] as $col) {
            $legacyP[] = isset($row[$col]) && $row[$col] !== null ? (float)$row[$col] : null;
        }
        foreach (['lead_avg', 'func_avg', 'potential_extra1', 'potential_extra2', 'potential_extra3', 'potential_extra4'] as $col) {
            $legacyT[] = isset($row[$col]) && $row[$col] !== null ? (float)$row[$col] : null;
        }
        $customCompetencies = json_decode((string)($row['custom_competencies_json'] ?? '[]'), true);
        if (!is_array($customCompetencies)) $customCompetencies = [];
        $storedOfficeType = strtoupper(trim((string)($row['office_type'] ?? '')));
        $scoreTypeData = $scoreAccountTypes[$id] ?? ['types' => [], 'unknown' => true];
        $resolvedOfficeType = in_array($storedOfficeType, ['RO', 'FO'], true)
            ? $storedOfficeType
            : (count($scoreTypeData['types']) === 1 && !$scoreTypeData['unknown']
                ? (string)array_key_first($scoreTypeData['types'])
                : '');
        $groupedCandidates[$office][] = [
            'id' => $id,
            'name' => $row['candidate_name'],
            'position' => $position,
            'office' => $office,
            'salaryGrade' => isset($row['salary_grade']) && $row['salary_grade'] !== null ? (int)$row['salary_grade'] : null,
            'accountType' => $resolvedOfficeType,
            'date' => !empty($row['interview_date']) ? $row['interview_date'] : date('F d, Y'),
            'scores' => isset($scoreMap[$id]) ? $scoreMap[$id] : [],
            'customCompetencies' => $customCompetencies,
            '_legacy' => ['p' => $legacyP, 't' => $legacyT],
        ];
    }
}

$defaultOffices = ['REGIONAL OFFICE', 'PENRO ILOILO', 'PENRO CAPIZ', 'PENRO AKLAN', 'PENRO ANTIQUE', 'PENRO GUIMARAS', 'PENRO NEGROS OCCIDENTAL'];
$orderedOffices = [];
foreach ($defaultOffices as $defOffice) $orderedOffices[$defOffice] = $groupedCandidates[$defOffice] ?? [];
foreach ($groupedCandidates as $office => $list) {
    if (!isset($orderedOffices[$office])) $orderedOffices[$office] = $list;
}
$groupedCandidates = $orderedOffices;
foreach (array_keys($storedOrders) as $storedOffice) {
    if (!isset($groupedCandidates[$storedOffice])) $groupedCandidates[$storedOffice] = [];
}
try {
    foreach (removed_tab_list($conn) as $removedOffice) {
        if (isset($groupedCandidates[$removedOffice]) && count($groupedCandidates[$removedOffice]) === 0) unset($groupedCandidates[$removedOffice]);
    }
} catch (Exception $e) {
    error_log('results_2 removed tabs load error: ' . $e->getMessage());
}

$headerLabels = [
    'hash' => 'NO.', 'name' => 'NAME OF APPLICANT / CANDIDATE', 'psychoGroup' => 'PSYCHO-SOCIAL ATTRIBUTES', 'potentialGroup' => 'POTENTIAL ATTRIBUTES',
    'core' => 'CORE', 'org' => 'ORG', 'psychoExtra1' => 'R1', 'psychoExtra2' => 'R2', 'psychoExtra3' => 'R3', 'psychoExtra4' => 'R4',
    'psychoAve' => 'AVE.', 'func' => 'FUNC', 'lead' => 'LEAD',
    'potentialExtra1' => 'R1', 'potentialExtra2' => 'R2', 'potentialExtra3' => 'R3', 'potentialExtra4' => 'R4', 'potentialAve' => 'AVE.'
];
try {
    $settingsResult = $conn->query("SELECT * FROM sheet_settings WHERE id = 1 LIMIT 1");
    $settingsRow = $settingsResult ? $settingsResult->fetch_assoc() : null;
    if ($settingsRow) {
        $headerLabels = [
            'hash' => $settingsRow['hdr_hash'] ?? 'NO.',
            'name' => $settingsRow['hdr_name'] ?? 'NAME OF APPLICANT / CANDIDATE',
            'psychoGroup' => $settingsRow['hdr_psycho_group'] ?? 'PSYCHO-SOCIAL ATTRIBUTES',
            'potentialGroup' => $settingsRow['hdr_potential_group'] ?? 'POTENTIAL ATTRIBUTES',
            'core' => $settingsRow['hdr_core'] ?? 'CORE',
            'org' => $settingsRow['hdr_org'] ?? 'ORG',
            'psychoExtra1' => $settingsRow['hdr_psycho_extra1'] ?? 'R1',
            'psychoExtra2' => $settingsRow['hdr_psycho_extra2'] ?? 'R2',
            'psychoExtra3' => $settingsRow['hdr_psycho_extra3'] ?? 'R3',
            'psychoExtra4' => $settingsRow['hdr_psycho_extra4'] ?? 'R4',
            'psychoAve' => $settingsRow['hdr_psycho_ave'] ?? 'AVE.',
            'func' => $settingsRow['hdr_func'] ?? 'FUNC',
            'lead' => $settingsRow['hdr_lead'] ?? 'LEAD',
            'potentialExtra1' => $settingsRow['hdr_potential_extra1'] ?? 'R1',
            'potentialExtra2' => $settingsRow['hdr_potential_extra2'] ?? 'R2',
            'potentialExtra3' => $settingsRow['hdr_potential_extra3'] ?? 'R3',
            'potentialExtra4' => $settingsRow['hdr_potential_extra4'] ?? 'R4',
            'potentialAve' => $settingsRow['hdr_potential_ave'] ?? 'AVE.',
        ];
    }
} catch (Exception $e) {}

function normalize_rater(string $label): ?string {
    $trimmed = trim($label);
    if ($trimmed === '') return null;
    foreach (rater_list() as $rater) {
        if (preg_replace('/[\s\-_]+/', '', strtoupper($rater)) === preg_replace('/[\s\-_]+/', '', strtoupper($trimmed))) return $rater;
    }
    return preg_replace('/\s+/', ' ', strtoupper($trimmed));
}

$legacyPLabels = [$headerLabels['core'], $headerLabels['org'], $headerLabels['psychoExtra1'], $headerLabels['psychoExtra2'], $headerLabels['psychoExtra3'], $headerLabels['psychoExtra4']];
$legacyTLabels = [$headerLabels['func'], $headerLabels['lead'], $headerLabels['potentialExtra1'], $headerLabels['potentialExtra2'], $headerLabels['potentialExtra3'], $headerLabels['potentialExtra4']];
foreach ($groupedCandidates as $officeKey => $list) {
    foreach ($list as $i => $candidate) {
        if (empty($candidate['scores'])) {
            $scores = [];
            for ($index = 0; $index < 6; $index++) {
                $psychoRater = normalize_rater((string)$legacyPLabels[$index]);
                $potentialRater = normalize_rater((string)$legacyTLabels[$index]);
                if ($psychoRater !== null && $candidate['_legacy']['p'][$index] !== null) $scores[$psychoRater]['p'] = $candidate['_legacy']['p'][$index];
                if ($potentialRater !== null && $candidate['_legacy']['t'][$index] !== null) $scores[$potentialRater]['t'] = $candidate['_legacy']['t'][$index];
            }
            foreach ($scores as $rater => $score) {
                $scores[$rater] = ['p' => $score['p'] ?? null, 't' => $score['t'] ?? null];
            }
            $groupedCandidates[$officeKey][$i]['scores'] = $scores;
        }
        unset($groupedCandidates[$officeKey][$i]['_legacy']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="chrome" content="nofill">
    <title>Master Interview Summary Rating Sheet</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="mockup.css?v=<?php echo filemtime(__DIR__ . '/mockup.css'); ?>">
    <link rel="stylesheet" href="ui-enhancements.css?v=<?php echo filemtime(__DIR__ . '/ui-enhancements.css'); ?>">
    <script src="ui-enhancements.js?v=<?php echo filemtime(__DIR__ . '/ui-enhancements.js'); ?>" defer></script>
    <style>
        .results-alert-backdrop { position: fixed; z-index: 10000; inset: 0; display: grid; place-items: center; padding: 20px; background: rgba(15, 23, 42, .48); }
        .results-alert-dialog { width: min(100%, 440px); padding: 24px; border: 1px solid #d6ddd8; border-radius: 14px; background: #fff; color: #202a35; box-shadow: 0 18px 56px rgba(15, 23, 42, .28); }
        .results-alert-dialog h2 { margin: 0 0 10px; font-size: 19px; }
        .results-alert-dialog p { margin: 0; color: #526158; line-height: 1.55; white-space: pre-line; overflow-wrap: anywhere; }
        .results-alert-actions { display: flex; justify-content: flex-end; margin-top: 24px; }
        .results-alert-actions button { min-width: 84px; min-height: 40px; padding: 8px 16px; border: 1px solid #107c41; border-radius: 7px; background: #107c41; color: #fff; font: inherit; font-weight: 650; cursor: pointer; }
        .results-confirm-actions { display: flex; justify-content: flex-end; flex-wrap: wrap; gap: 10px; margin-top: 24px; }
        .results-confirm-actions button { min-width: 84px; min-height: 42px; padding: 8px 16px; border: 1px solid #d5ddd7; border-radius: 7px; background: #fff; color: #344139; font: inherit; font-weight: 650; cursor: pointer; }
        .results-confirm-actions .confirm-delete { border-color: #b3261e; background: #b3261e; color: #fff; }
        .results-confirm-actions .prompt-submit { border-color: #107c41; background: #107c41; color: #fff; }
        .results-prompt-input { display: block; width: 100%; min-width: 0; min-height: 44px; margin-top: 14px; padding: 9px 11px; border: 1px solid #b8c2cb; border-radius: 7px; background: #fff; color: #202a35; font: inherit; }
        .results-prompt-input:focus { outline: 3px solid rgba(16, 124, 65, .2); border-color: #107c41; }
        .account-shortcuts { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 8px; padding: 10px 12px; background: #f5f8f6; border: 1px solid #dce5df; border-radius: 8px; }
        .account-shortcuts button { min-height: 38px; padding: 7px 14px; border: 1px solid #b9c9bf; border-radius: 7px; background: #fff; color: #174b30; font: inherit; font-weight: 650; cursor: pointer; }
        .account-shortcuts button:hover:not(:disabled), .account-shortcuts button:focus-visible { background: #e8f3ec; border-color: #26734a; }
        .account-shortcuts button.active { background: #17653b; border-color: #17653b; color: #fff; }
        .account-shortcuts button:disabled { opacity: .55; cursor: not-allowed; }
        .results-save-status { align-self: center; color: #52635a; font-size: 12px; }
        .results-save-status[data-state="dirty"], .results-save-status[data-state="error"] { color: #9a3412; font-weight: 650; }
        .results-save-status[data-state="saved"] { color: #17653b; font-weight: 650; }
        .account-scope { margin-top: 5px; font-size: 12px; font-weight: 700; color: #285d3e; letter-spacing: .04em; }
        .account-scope-label { display: inline-block; margin-left: 8px; padding: 2px 6px; border-radius: 4px; background: #e8f3ec; color: #285d3e; font-size: 10px; font-weight: 700; vertical-align: middle; }
        .results-navigation { position: sticky; top: env(safe-area-inset-top, 0px); z-index: 20; padding-top: 6px; background: #f3f5f4; }
        .account-result-section { display: none; }
        #results[data-account-view="RO"] .account-result-section[data-account-type="RO"],
        #results[data-account-view="FO"] .account-result-section[data-account-type="FO"],
        #results[data-account-view="RO"] .account-result-section[data-account-type="UNCLASSIFIED"],
        #results[data-account-view="FO"] .account-result-section[data-account-type="UNCLASSIFIED"] { display: block; }
        .account-empty-state { margin: 14px 0; padding: 16px; border: 1px dashed #cbd8cf; border-radius: 8px; color: #53645a; text-align: center; }
        @media (max-width: 520px) { .results-confirm-actions { flex-direction: column-reverse; } .results-confirm-actions button { width: 100%; } }
        #tables-wrapper > .table-container { display: none; }
        #tables-wrapper > .table-container.active { display: block; }
        #tabs .ind { z-index: 5; transition: left .42s cubic-bezier(.22, .61, .36, 1), width .42s cubic-bezier(.22, .61, .36, 1); }
        .excel-table th, .excel-table td { padding: 8px 6px; }
        .excel-table tbody tr:nth-child(even) td:not(.g):not(.y) { background-color: #f8faf9; }
        .excel-table tbody tr:hover td:not(.g):not(.y) { background-color: #eef6f0; }
        ::view-transition-old(active-sheet) { animation: ui-sheet-out-left .42s cubic-bezier(.22, .61, .36, 1) both; }
        ::view-transition-new(active-sheet) { animation: ui-sheet-in-right .42s cubic-bezier(.22, .61, .36, 1) both; }
        html[data-ui-transition="sheet-reverse"]::view-transition-old(active-sheet) { animation: ui-sheet-out-right .42s cubic-bezier(.22, .61, .36, 1) both; }
        html[data-ui-transition="sheet-reverse"]::view-transition-new(active-sheet) { animation: ui-sheet-in-left .42s cubic-bezier(.22, .61, .36, 1) both; }
        .table-container.sheet.sl { animation: ui-sheet-in-right .42s cubic-bezier(.22, .61, .36, 1) both; }
        .table-container.sheet.sr { animation: ui-sheet-in-left .42s cubic-bezier(.22, .61, .36, 1) both; }
        .table-container.sheet.sl .blk, .table-container.sheet.sr .blk { animation: none; }
        .tab-close-btn { display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; margin-left: 6px; border-radius: 50%; background: rgba(179, 38, 30, .12); color: #b3261e; font-size: 13px; cursor: pointer; }
        .tab-close-btn:hover { background: #b3261e; color: #fff; }
        .pos-delete-btn { float: right; width: 20px; height: 20px; padding: 0; border: 1px solid var(--ln); border-radius: 50%; background: #fff; cursor: pointer; }
        .row-number { display: inline; }
        .delete-icon { display: none; color: #b3261e; font-weight: 700; }
        .row-num-cell:hover .row-number { display: none; }
        .row-num-cell:hover .delete-icon { display: inline; }
        .mobile-sheet-scroll { display: none; }
        @media (max-width: 600px) {
            #results { width: 100%; min-width: 0; padding: 8px 6px; }
            .tabsbar { align-items: center; gap: 4px; padding: 6px 6px 0; border-radius: 8px 8px 0 0; }
            #tabs { min-width: 0; }
            .tab { padding: 9px 10px; font-size: 12px; white-space: nowrap; }
            .tab-close-btn { width: 16px; height: 16px; margin-left: 4px; }
            .tabsbar .add { min-width: 82px; height: 34px; padding: 0 9px; margin: 0 0 2px; }
            .srch { min-width: 0; padding: 6px 0; }
            .srch input { min-width: 0; }
            #tables-wrapper, .table-container.sheet { width: 100%; min-width: 0; max-width: 100%; }
            .table-container.sheet { overflow: hidden; padding: 14px 8px; border-radius: 0 0 8px 8px; }
            .dh { margin: 0 2px 14px; overflow-wrap: anywhere; }
            .dh .a, .dh .c { font-size: 10px; }
            .dh .b { font-size: 12px; }
            .dh .d { font-size: 13px; }
            .blk, .table-scroll.sc { width: 100%; min-width: 0; max-width: 100%; }
            .table-scroll.sc { overflow-x: auto; overflow-y: hidden; -webkit-overflow-scrolling: touch; scrollbar-width: thin; touch-action: pan-x pan-y; }
            .mobile-sheet-scroll { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin: 0 0 7px; color: #52635a; font-size: 11px; }
            .mobile-sheet-scroll button { flex: 0 0 40px; width: 40px; height: 40px; border: 1px solid #cbd5ce; border-radius: 9px; background: #fff; color: #145d35; font-size: 21px; line-height: 1; cursor: pointer; touch-action: manipulation; }
            .mobile-sheet-scroll button:disabled { color: #a7b0aa; background: #f0f3f1; cursor: default; }
            .mobile-sheet-scroll span { flex: 1; text-align: center; }
            .table-block.no-horizontal-overflow .mobile-sheet-scroll { display: none; }
            .table-scroll.sc::-webkit-scrollbar { height: 7px; }
            .table-scroll.sc::-webkit-scrollbar-thumb { background: #84938a; border-radius: 8px; }
            .table-scroll.sc:focus-visible { outline: 3px solid rgba(16,124,65,.35); outline-offset: 2px; }
            .excel-table th, .excel-table td { padding: 6px 4px; }
            .excel-table .nm { min-width: 170px; }
            .row-num-cell:hover .row-number { display: inline; }
            .row-num-cell:hover .delete-icon { display: none; }
            .sg { gap: 22px; }
            .ab { gap: 8px; padding: 9px; }
            .ag { gap: 6px; }
            .ag .btn { min-width: 0; padding: 8px 9px; font-size: 12px; }
        }
        @media print {
            @page { size: landscape; margin: 0.4in; }
            body { background: #fff; padding: 0; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            #results, #results * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .no-print, .excel-tabs-container, .action-bar, .rater-add, .pos-delete-btn, .tab-close-btn, .rater-remove { display: none !important; }
            .table-container { display: block !important; border: none; box-shadow: none; padding: 0; page-break-after: always; }
            .table-block { page-break-inside: avoid; }
            .table-scroll { overflow: visible !important; }
            .excel-table { min-width: 0 !important; }
            .excel-table th, .excel-table td { background-color: #fff !important; }
            .excel-table thead th:not(.g):not(.y) { background-color: #f4f7f5 !important; }
            .excel-table .dt { background-color: #f4f7f5 !important; }
            .excel-table .pc { background-color: #fbd3e5 !important; }
            .excel-table .g { background-color: #e3f1d9 !important; }
            .excel-table thead th.g { background-color: #cfe7bf !important; }
            .excel-table .y { background-color: #fff2c9 !important; }
            .excel-table thead th.y { background-color: #ffe79a !important; }
            .row-num-cell:hover .row-number { display: inline !important; }
            .row-num-cell:hover .delete-icon { display: none !important; }
        }
        @media screen {
            body { min-height: 100vh; background: #f2f5f2; }
            #results { width: min(100%, 1720px); min-height: 100vh; margin: 0 auto; padding: clamp(14px, 2.4vw, 32px); }
            .results-hero { display: flex; min-height: 172px; align-items: center; padding: clamp(24px, 3.5vw, 40px); margin-bottom: 16px; border-radius: 20px; background: linear-gradient(118deg, #0b5c30 0%, #107c41 58%, #239556 100%); box-shadow: 0 16px 38px rgba(17, 91, 48, .16); color: #fff; }
            .results-hero .eyebrow { margin: 0 0 10px; color: #c5eed3; font-size: 11px; font-weight: 750; letter-spacing: .14em; text-transform: uppercase; }
            .results-hero h1 { margin: 0 0 8px; color: #fff; font-size: clamp(29px, 4vw, 42px); letter-spacing: -.04em; line-height: 1.1; }
            .results-hero p:last-child { max-width: 670px; margin: 0; color: rgba(255,255,255,.82); font-size: 13px; line-height: 1.6; }
            .results-navigation { top: 0; padding: 8px; border: 1px solid #e0e7e2; border-radius: 14px; background: rgba(248, 250, 248, .96); box-shadow: 0 8px 24px rgba(26,52,35,.07); backdrop-filter: blur(12px); }
            .account-shortcuts { gap: 7px; padding: 0 0 9px; margin: 0 0 8px; border: 0; border-bottom: 1px solid #e2e9e4; border-radius: 0; background: transparent; }
            .account-shortcuts button { min-height: 40px; padding: 7px 17px; border-color: #d7e2da; border-radius: 9px; color: #405248; }
            .account-shortcuts button.active { border-color: #107c41; background: #107c41; color: #fff; box-shadow: 0 3px 9px rgba(16,124,65,.16); }
            .tabsbar { gap: 10px; padding: 5px 6px 0; border: 0; border-radius: 0; background: transparent; }
            #tabs { min-height: 42px; align-items: stretch; gap: 4px; }
            .tab { padding: 10px 15px; border-radius: 9px 9px 0 0; }
            .tab[aria-selected="true"] { box-shadow: inset 0 -3px #107c41; }
            .tab-close-btn { display: none; }
            .tab[aria-selected="true"] .tab-close-btn { display: inline-flex; }
            .tabsbar .add { display: inline-flex; width: auto; min-width: 88px; height: 36px; align-items: center; justify-content: center; padding: 0 12px; margin: 0 0 3px; border: 1px solid #cfe1d4; border-radius: 9px; background: #fff; color: #107c41; font-size: 12px; font-weight: 700; white-space: nowrap; transition: background-color .16s ease, border-color .16s ease, transform .16s ease; }
            .tabsbar .add:hover { transform: translateY(-1px); border-color: #9dc9aa; background: #f0f8f2; }
            .tabsbar .add:focus-visible { outline: 3px solid rgba(16,124,65,.2); outline-offset: 2px; }
            .srch { align-items: center; padding: 0 0 5px; }
            .srch input { min-height: 40px; width: clamp(180px, 22vw, 290px); border-radius: 9px; }
            .srch .btn { min-height: 40px; padding: 8px 13px; border-radius: 9px; }
            .table-container.sheet { padding: clamp(16px, 2.2vw, 28px); border: 1px solid #e0e7e2; border-radius: 0 0 16px 16px; box-shadow: 0 8px 26px rgba(26,52,35,.05); }
            .dh { margin: 2px 0 22px; }
            .dh .d { font-size: 15px; letter-spacing: .015em; }
            .blk { margin-bottom: 30px; }
            .sc { border-color: #dbe4dd; border-radius: 11px; box-shadow: 0 4px 15px rgba(26,52,35,.035); }
            .excel-table th, .excel-table td { padding: 9px 7px; }
            .excel-table thead th { padding-top: 11px; padding-bottom: 11px; }
            .ab { gap: 10px 14px; padding: 14px; margin-top: 16px; border-color: #dfe7e1; border-radius: 13px; box-shadow: 0 7px 22px rgba(26,52,35,.045); }
            .ag { gap: 8px; }
            .ag .btn { min-height: 40px; padding: 8px 13px; border-radius: 9px; }
            .ag+.ag { padding-left: 14px; border-color: #e2e9e4; }
            .results-save-status { min-width: 110px; }
            html[data-theme="dark"] .results-hero { background: linear-gradient(118deg, #123f2a, #145d35 60%, #1b7040); }
            html[data-theme="dark"] .results-navigation { border-color: #39475a; }
            html[data-theme="dark"] .account-shortcuts { border-color: #39475a; background: transparent; }
            html[data-theme="dark"] .account-shortcuts button { border-color: #46566b; background: #263447; color: #e5eaf0; }
            html[data-theme="dark"] .account-shortcuts button.active { border-color: #25834b; background: #17653b; color: #fff; }
            html[data-theme="dark"] .tabsbar .add { border-color: #46566b; background: #263447; color: #b8e4c4; }
            html[data-theme="dark"] .tabsbar .add:hover { border-color: #527a5d; background: #203326; }
            html[data-theme="dark"] .sc { border-color: #39475a; }
            @media (max-width: 760px) {
                #results { min-height: 100vh; padding: 16px 12px; }
                .results-hero { min-height: 0; padding: 25px; border-radius: 17px; }
                .results-navigation { padding: 7px; }
                .tabsbar { gap: 6px; }
                .srch { gap: 7px; }
                .table-container.sheet { padding: 16px 12px; }
                .ab { padding: 12px; }
                .ag+.ag { padding: 8px 0 0; border: 0; border-top: 1px solid #e2e9e4; }
                .results-save-status { min-width: 0; }
            }
            @media (max-width: 600px) {
                #results { padding: 12px 9px; }
                .results-hero { padding: 22px 18px; margin-bottom: 11px; border-radius: 15px; }
                .results-hero p:last-child { font-size: 12px; }
                .results-navigation { padding: 6px; border-radius: 11px; }
                .account-shortcuts { gap: 6px; padding-bottom: 7px; margin-bottom: 6px; }
                .account-shortcuts button { flex: 1; min-height: 40px; padding: 7px 10px; }
                .tabsbar { gap: 4px; }
                .tab { padding: 9px; }
                .tabsbar .add { min-width: 82px; height: 34px; padding: 0 9px; margin: 0 0 2px; }
                .table-container.sheet { padding: 13px 8px; }
                .ab { border-radius: 11px; }
            }
        }
    </style>
</head>
<body>

<section id="results">
<form id="resultsForm" autocomplete="off" onsubmit="return false;">
    <header class="results-hero no-print">
        <div>
            <p class="eyebrow">Administration workspace</p>
            <h1>Interview Results</h1>
            <p>Review scoring sheets, update records, and save changes to the evaluation results.</p>
        </div>
    </header>
    <div class="results-navigation no-print">
        <div class="account-shortcuts" aria-label="Results by account type">
            <button type="button" id="regionResultsButton" onclick="switchAccountType('RO')">Region</button>
            <button type="button" id="fieldResultsButton" onclick="switchAccountType('FO')">Field</button>
        </div>
        <div class="excel-tabs-container tabsbar">
            <div id="tabs"></div>
            <button type="button" class="add" onclick="promptNewTab()" aria-label="Add office tab">+ Add Tab</button>
            <div class="tab-search srch">
                <input type="text" id="candidateSearch" placeholder="Search applicant name..." autocomplete="off">
                <button type="button" class="btn" onclick="searchCandidate()">🔍 Find</button>
            </div>
        </div>
    </div>

    <div id="tables-wrapper"></div>

    <div class="action-bar ab no-print">
        <div class="action-group ag">
            <button type="button" id="saveResultsButton" class="btn p btn-primary" onclick="saveWholeTable(event)">💾 Save All Changes</button>
            <span id="resultsSaveStatus" class="results-save-status" role="status" aria-live="polite"></span>
            <button type="button" class="btn" id="importExcelButton">Import Excel</button>
            <input type="file" id="excelImportFile" accept=".xlsx,.xls,.csv" hidden>
            <button type="button" class="btn" id="exportExcelButton">Export Excel</button>
            <button type="button" class="btn" onclick="window.print()">🖨️ Print / Save as PDF</button>
        </div>
        <div class="action-group ag">
            <a href="dashboard.php" class="btn">Dashboard</a>
            <a href="template_manager.php" class="btn">Competency Templates</a>
        </div>
        <div class="action-group ag r action-group-admin">
            <a href="manage_users.php" class="btn">👥 Manage Users</a>
        </div>
        <div class="action-group ag action-group-logout">
            <a href="logout.php" class="btn d">🚪 Logout</a>
        </div>
    </div>
</form>
</section>

<script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js"></script>
<script src="results_excel_export.js?v=<?php echo (int)filemtime(__DIR__ . '/results_excel_export.js'); ?>"></script>
<script>
    <?php
    $excelImportModule = __DIR__ . '/results_excel_import.js';
    if (is_readable($excelImportModule)) {
        readfile($excelImportModule);
    } else {
        echo 'window.ResultsExcelImportLoadError = "The results_excel_import.js file is missing or unreadable on the server.";';
    }
    ?>
</script>
<script>
    if (typeof window.showSiteAlert !== 'function') {
        const resultsAlertQueue = [];
        let resultsAlertOpen = false;
        window.showSiteAlert = function (message) {
            return new Promise(resolve => {
                resultsAlertQueue.push({ message: String(message ?? ''), resolve });
                showNextResultsAlert();
            });
        };
        function showNextResultsAlert() {
            if (resultsAlertOpen || !resultsAlertQueue.length) return;
            resultsAlertOpen = true;
            const request = resultsAlertQueue.shift();
            const previousFocus = document.activeElement;
            const backdrop = document.createElement('div');
            backdrop.className = 'results-alert-backdrop';
            const dialog = document.createElement('section');
            dialog.className = 'results-alert-dialog';
            dialog.setAttribute('role', 'alertdialog');
            dialog.setAttribute('aria-modal', 'true');
            dialog.setAttribute('aria-labelledby', 'resultsAlertTitle');
            dialog.setAttribute('aria-describedby', 'resultsAlertMessage');
            const title = document.createElement('h2');
            title.id = 'resultsAlertTitle';
            title.textContent = 'Notice';
            const body = document.createElement('p');
            body.id = 'resultsAlertMessage';
            body.textContent = request.message;
            const actions = document.createElement('div');
            actions.className = 'results-alert-actions';
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = 'OK';
            actions.appendChild(button);
            dialog.append(title, body, actions);
            backdrop.appendChild(dialog);
            document.body.appendChild(backdrop);
            const close = () => {
                if (!resultsAlertOpen) return;
                backdrop.remove();
                resultsAlertOpen = false;
                request.resolve();
                if (previousFocus instanceof HTMLElement && previousFocus.isConnected) previousFocus.focus();
                showNextResultsAlert();
            };
            button.addEventListener('click', close);
            backdrop.addEventListener('keydown', event => {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    close();
                }
            });
            button.focus();
        }
    }
    if (typeof window.showSiteConfirm !== 'function') {
        window.showSiteConfirm = function (message, options = {}) {
            return new Promise(resolve => {
                const previousFocus = document.activeElement;
                const backdrop = document.createElement('div');
                backdrop.className = 'results-alert-backdrop';
                const dialog = document.createElement('section');
                dialog.className = 'results-alert-dialog';
                dialog.setAttribute('role', 'alertdialog');
                dialog.setAttribute('aria-modal', 'true');
                const title = document.createElement('h2');
                title.textContent = options.title || 'Please confirm';
                const body = document.createElement('p');
                body.textContent = String(message ?? '');
                const actions = document.createElement('div');
                actions.className = 'results-confirm-actions';
                const cancelButton = document.createElement('button');
                cancelButton.type = 'button';
                cancelButton.textContent = options.cancelText || 'Cancel';
                const confirmButton = document.createElement('button');
                confirmButton.type = 'button';
                confirmButton.className = 'confirm-delete';
                confirmButton.textContent = options.confirmText || 'Delete';
                actions.append(cancelButton, confirmButton);
                dialog.append(title, body, actions);
                backdrop.appendChild(dialog);
                document.body.appendChild(backdrop);

                let finished = false;
                const close = confirmed => {
                    if (finished) return;
                    finished = true;
                    backdrop.remove();
                    resolve(confirmed);
                    if (previousFocus instanceof HTMLElement && previousFocus.isConnected) previousFocus.focus();
                };
                cancelButton.addEventListener('click', () => close(false));
                confirmButton.addEventListener('click', () => close(true));
                backdrop.addEventListener('keydown', event => {
                    if (event.key === 'Escape') {
                        event.preventDefault();
                        close(false);
                    } else if (event.key === 'Tab') {
                        event.preventDefault();
                        const nextButton = document.activeElement === cancelButton
                            ? confirmButton
                            : cancelButton;
                        nextButton.focus();
                    }
                });
                cancelButton.focus();
            });
        };
    }
    if (typeof window.showSitePrompt !== 'function') {
        window.showSitePrompt = function (message, options = {}) {
            return new Promise(resolve => {
                const previousFocus = document.activeElement;
                const backdrop = document.createElement('div');
                backdrop.className = 'results-alert-backdrop';
                const dialog = document.createElement('section');
                dialog.className = 'results-alert-dialog';
                dialog.setAttribute('role', 'dialog');
                dialog.setAttribute('aria-modal', 'true');
                const title = document.createElement('h2');
                title.textContent = options.title || 'Enter a name';
                const body = document.createElement('p');
                body.textContent = String(message ?? '');
                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'results-prompt-input';
                input.maxLength = Number.isInteger(options.maxLength) ? options.maxLength : 100;
                input.setAttribute('aria-label', options.inputLabel || 'Name');
                input.value = options.defaultValue || '';
                const actions = document.createElement('div');
                actions.className = 'results-confirm-actions';
                const cancelButton = document.createElement('button');
                cancelButton.type = 'button';
                cancelButton.textContent = 'Cancel';
                const submitButton = document.createElement('button');
                submitButton.type = 'button';
                submitButton.className = 'prompt-submit';
                submitButton.textContent = options.confirmText || 'Add';
                actions.append(cancelButton, submitButton);
                dialog.append(title, body, input, actions);
                backdrop.appendChild(dialog);
                document.body.appendChild(backdrop);

                let finished = false;
                const close = value => {
                    if (finished) return;
                    finished = true;
                    backdrop.remove();
                    resolve(value);
                    if (previousFocus instanceof HTMLElement && previousFocus.isConnected) previousFocus.focus();
                };
                cancelButton.addEventListener('click', () => close(null));
                submitButton.addEventListener('click', () => close(input.value));
                input.addEventListener('keydown', event => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        close(input.value);
                    }
                });
                backdrop.addEventListener('keydown', event => {
                    if (event.key === 'Escape') {
                        event.preventDefault();
                        close(null);
                    } else if (event.key === 'Tab') {
                        const focusable = [input, cancelButton, submitButton];
                        const index = focusable.indexOf(document.activeElement);
                        const nextIndex = (index + (event.shiftKey ? -1 : 1) + focusable.length) % focusable.length;
                        event.preventDefault();
                        focusable[nextIndex].focus();
                    }
                });
                input.focus();
            });
        };
    }

    const groupedData = <?php echo json_encode($groupedCandidates, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    let headerLabels = <?php echo json_encode($headerLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    let activeTabOffice = 'REGIONAL OFFICE';
    const allCandidates = Object.values(groupedData).flat();
    let activeAccountType = allCandidates.some(candidate => candidate.accountType === 'RO') ? 'RO'
        : allCandidates.some(candidate => candidate.accountType === 'FO') ? 'FO' : 'RO';
    let sheetTransition = '';
    const RATERS = <?php echo json_encode(rater_list(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const MIN_SLOTS = 7;
    const CSRF_TOKEN = <?php echo json_encode(csrf_token()); ?>;
    const hiddenTabs = new Set();
    const pendingRenames = [];
    let skipNextSync = false;
    let resultsDirty = false;
    let resultsChangeVersion = 0;
    let resultsSaveBusy = false;
    const addedRaters = {};
    const storedOrders = <?php echo json_encode($storedOrders, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    window.__positionsToDelete = window.__positionsToDelete || [];

    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
    }

    function markResultsDirty() {
        resultsDirty = true;
        resultsChangeVersion++;
        const status = document.getElementById('resultsSaveStatus');
        if (status) {
            status.dataset.state = 'dirty';
            status.textContent = 'Unsaved changes — select Save All Changes.';
        }
    }

    function calculateAverage(...scores) {
        const validScores = scores.filter(score => score !== null && !isNaN(score) && score !== '' && score !== undefined);
        if (!validScores.length) return '#DIV/0!';
        return (validScores.reduce((total, score) => total + parseFloat(score), 0) / validScores.length).toFixed(2);
    }

    function formatVal(value) {
        if (value === null || value === undefined || isNaN(value) || value === '') return '';
        return Number(value).toFixed(2);
    }

    function updateHeaderLabel(element, key) { headerLabels[key] = element.innerText.replace(/[\r\n\t]/g, '').trim(); renderAll(); }
    function blockKey(office, position, date) { return office + '|' + position + '|' + date; }

    function raterColumns(candidates, key, office, position) {
        const stored = ((storedOrders[office] || {})[position]) || [];
        const ordered = [];
        stored.concat(addedRaters[key] || []).forEach(rater => { if (!ordered.includes(rater)) ordered.push(rater); });
        const scored = new Set();
        candidates.forEach(candidate => Object.keys(candidate.scores || {}).forEach(rater => scored.add(rater)));
        const rest = Array.from(scored).filter(rater => !ordered.includes(rater));
        return ordered.concat(RATERS.filter(rater => rest.includes(rater)), rest.filter(rater => !RATERS.includes(rater)));
    }

    function buildRowHtml(candidate = null, rowNumber = 1, cols = [], slots = MIN_SLOTS, accountType = '', salaryGrade = null) {
        const id = candidate ? candidate.id : '';
        const name = candidate ? escapeHtml(candidate.name) : '';
        const rowAccountType = candidate ? candidate.accountType : accountType;
        const rowSalaryGrade = candidate ? candidate.salaryGrade : salaryGrade;
        let psychoCells = '';
        let potentialCells = '';
        const psychoValues = [];
        const potentialValues = [];
        for (let index = 0; index < slots; index++) {
            const rater = cols[index];
            if (rater) {
                const score = (candidate && candidate.scores && candidate.scores[rater]) || {};
                psychoValues.push(score.p);
                potentialValues.push(score.t);
                psychoCells += `<td contenteditable="true" data-kind="p" data-rater="${escapeHtml(rater)}">${formatVal(score.p)}</td>`;
                potentialCells += `<td contenteditable="true" data-kind="t" data-rater="${escapeHtml(rater)}">${formatVal(score.t)}</td>`;
            } else {
                psychoCells += '<td></td>';
                potentialCells += '<td></td>';
            }
        }
        const psychoAverage = candidate ? calculateAverage(...psychoValues) : '#DIV/0!';
        const potentialAverage = candidate ? calculateAverage(...potentialValues) : '#DIV/0!';
        return `
            <tr class="in" style="--i:${rowNumber - 1}" data-id="${id}" data-account-type="${escapeHtml(rowAccountType || '')}" data-salary-grade="${rowSalaryGrade ?? ''}">
                <td class="row-num-cell n" onclick="handleRowDelete(this)"><span class="row-number">${rowNumber}</span><span class="delete-icon" title="Delete Row">×</span></td>
                <td class="text-left nm" contenteditable="true" data-field="name">${name}</td>
                ${psychoCells}
                <td class="col-green-ave g psycho-ave">${psychoAverage}</td>
                ${potentialCells}
                <td class="col-yellow-ave y potential-ave">${potentialAverage}</td>
            </tr>
        `;
    }

    function buildTableHeaderHtml(currentDate, branch, position, cols, slots, accountType = '', salaryGrade = null) {
        const raterHeaders = () => {
            let html = '';
            for (let index = 0; index < slots; index++) {
                if (cols[index]) html += `<th style="width: 62px;">${escapeHtml(cols[index])}<span class="rater-remove rx" data-rater="${escapeHtml(cols[index])}" title="Remove this column" onclick="removeRater(this)">×</span></th>`;
                else html += '<th style="width: 62px;"></th>';
            }
            return html;
        };
        const totalCols = 2 + (slots + 1) * 2;
        const escapedOffice = escapeHtml(branch).replace(/'/g, "\\'").replace(/"/g, '&quot;');
        const escapedPosition = escapeHtml(position).replace(/'/g, "\\'").replace(/"/g, '&quot;');
        return `
            <tr>
                <td colspan="3" class="text-left meta-row dt" contenteditable="true" data-field="interview_date" style="border-right: none;"><b>DATE OF INTERVIEW:</b> ${escapeHtml(currentDate)}</td>
                <td colspan="${totalCols - 3}" class="position-cell pc" style="border-left: none;">POSITION TITLE: ${escapeHtml(position)}<button type="button" class="pos-delete-btn no-print" title="Delete this entire position table" onclick="deletePositionGroup(this, &quot;${escapedOffice}&quot;, &quot;${escapedPosition}&quot;, &quot;${accountType}&quot;, &quot;${salaryGrade ?? ''}&quot;)">×</button></td>
            </tr>
            <tr>
                <th class="n" style="width: 30px;">${escapeHtml(headerLabels.hash)}</th>
                <th class="nm" style="width: 230px;" contenteditable="true" onblur="updateHeaderLabel(this,'name')">${escapeHtml(headerLabels.name)}</th>
                ${raterHeaders()}
                <th class="col-green-ave g" style="width: 55px; border-left: 2px solid #000 !important;" contenteditable="true" onblur="updateHeaderLabel(this,'psychoAve')">${escapeHtml(headerLabels.psychoAve)}</th>
                ${raterHeaders()}
                <th class="col-yellow-ave y" style="width: 55px; border-left: 2px solid #000 !important;" contenteditable="true" onblur="updateHeaderLabel(this,'potentialAve')">${escapeHtml(headerLabels.potentialAve)}</th>
            </tr>
        `;
    }
    function buildTableBlockHtml(candidates, currentDate, branch, posKey, accountType = '', salaryGrade = null) {
        const cols = raterColumns(candidates, blockKey(branch, posKey, currentDate), branch, posKey);
        const slots = Math.max(MIN_SLOTS, cols.length + 1);
        const free = RATERS.filter(r => !cols.includes(r));
        let rowsHtml = '';
        const minRows = Math.max(5, candidates.length);
        for (let i = 0; i < minRows; i++) rowsHtml += buildRowHtml(candidates[i] || null, i + 1, cols, slots, accountType, salaryGrade);
        const positionAttr = posKey ? ` data-position="${escapeHtml(posKey)}"` : '';
        const accountTypeAttr = accountType ? ` data-account-type="${escapeHtml(accountType)}"` : '';
        const salaryGradeAttr = salaryGrade !== null ? ` data-salary-grade="${salaryGrade}"` : '';

        return `
            <div class="table-block blk"${positionAttr}${accountTypeAttr}${salaryGradeAttr}>
                <div class="mobile-sheet-scroll">
                    <button type="button" data-sheet-scroll="-1" aria-label="Scroll scoring sheet left" disabled>‹</button>
                    <span>Swipe the sheet or use arrows to view all columns</span>
                    <button type="button" data-sheet-scroll="1" aria-label="Scroll scoring sheet right">›</button>
                </div>
                <div class="table-scroll sc" tabindex="0" role="region" aria-label="Scrollable scoring sheet. Swipe horizontally to see all columns.">
                    <table class="excel-table">
                        <thead>
                            ${buildTableHeaderHtml(currentDate, branch, posKey, cols, slots, accountType, salaryGrade)}
                        </thead>
                        <tbody class="scores-body" data-cols="${escapeHtml(JSON.stringify(cols))}" data-slots="${slots}">
                            ${rowsHtml}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    function updateSheetScrollControls(block) {
        const scroller = block.querySelector('.table-scroll');
        if (!scroller) return;
        const maxScroll = Math.max(0, scroller.scrollWidth - scroller.clientWidth);
        block.classList.toggle('no-horizontal-overflow', maxScroll < 2);
        const leftButton = block.querySelector('[data-sheet-scroll="-1"]');
        const rightButton = block.querySelector('[data-sheet-scroll="1"]');
        if (leftButton) leftButton.disabled = scroller.scrollLeft <= 1;
        if (rightButton) rightButton.disabled = scroller.scrollLeft >= maxScroll - 1;
    }

    document.addEventListener('click', event => {
        const button = event.target.closest('[data-sheet-scroll]');
        if (!button) return;
        const block = button.closest('.table-block');
        const scroller = block?.querySelector('.table-scroll');
        if (!scroller) return;
        const direction = Number(button.dataset.sheetScroll);
        scroller.scrollBy({
            left: direction * Math.max(220, scroller.clientWidth * 0.72),
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
        });
    });

    document.getElementById('tables-wrapper').addEventListener('scroll', event => {
        if (event.target.matches('.table-scroll')) {
            updateSheetScrollControls(event.target.closest('.table-block'));
        }
    }, true);

    window.addEventListener('resize', () => {
        document.querySelectorAll('#tables-wrapper .table-block').forEach(updateSheetScrollControls);
    });

    function buildSignatoryBlock() {
        return `
            <div class="signatory-section sg">
            <div class="sig-box"><i class="sig-line"></i><b class="sig-title">HRMPSB SECRETARIAT</b><small class="sig-sub">Prepared By</small></div>
            <div class="sig-box"><i class="sig-line"></i><b class="sig-title">HRMPSB COMMITTEE MEMBER</b><small class="sig-sub">Attested By</small></div>
            <div class="sig-box"><i class="sig-line"></i><b class="sig-title">CHAIRPERSON, HRMPSB</b><small class="sig-sub">Approved By</small></div>
            </div>
        `;
    }

    function checkAndAutoAddRow(tbody) {
        const rows = tbody.querySelectorAll('tr');
        if (rows.length === 0) return;
        const lastRow = rows[rows.length - 1];
        const nameCell = lastRow.querySelector('[data-field="name"]');
        if (nameCell && nameCell.innerText.trim() !== "") {
            const cols = JSON.parse(tbody.dataset.cols || '[]');
            const slots = parseInt(tbody.dataset.slots, 10) || MIN_SLOTS;
            const block = tbody.closest('.table-block');
            const accountType = block?.getAttribute('data-account-type') || '';
            const rawSalaryGrade = block?.getAttribute('data-salary-grade') || '';
            const salaryGrade = /^\d+$/.test(rawSalaryGrade) ? Number(rawSalaryGrade) : null;
            tbody.insertAdjacentHTML('beforeend', buildRowHtml(null, rows.length + 1, cols, slots, accountType, salaryGrade));
        }
    }

    function readDate(block) {
        const el = block.querySelector('[data-field="interview_date"]');
        const t = el ? el.innerText.replace('DATE OF INTERVIEW:', '').replace(/[\r\n\t]/g, ' ').trim() : '';
        return t || "<?php echo date('F d, Y'); ?>";
    }

    function collectRows() {
        const out = [];
        document.querySelectorAll('.table-container').forEach(container => {
            const office = container.getAttribute('data-office') || 'REGIONAL OFFICE';
            container.querySelectorAll('.table-block').forEach(block => {
                const position = block.getAttribute('data-position') || 'UNASSIGNED';
                const date = readDate(block);
                block.querySelectorAll('.scores-body tr').forEach(row => {
                    const id = parseInt(row.getAttribute('data-id') || '0', 10) || 0;
                    const name = (row.querySelector('[data-field="name"]')?.innerText || '').replace(/[\r\n\t]/g, '').trim();
                    if (!(id > 0 || name !== '')) return;
                    const accountType = row.getAttribute('data-account-type') || '';
                    const rawSalaryGrade = row.getAttribute('data-salary-grade') || '';
                    const salaryGrade = /^\d+$/.test(rawSalaryGrade) ? Number(rawSalaryGrade) : null;
                    const scores = {};
                    row.querySelectorAll('td[data-rater]').forEach(td => {
                        const r = td.getAttribute('data-rater');
                        const v = td.innerText.trim();
                        if (!scores[r]) scores[r] = { p: null, t: null };
                        if (v !== '' && !isNaN(parseFloat(v))) scores[r][td.getAttribute('data-kind')] = parseFloat(v);
                    });
                    out.push({ row, office, position, date, id, name, accountType, salaryGrade, scores });
                });
            });
        });
        return out;
    }

    function syncFromDom() {
        const containers = document.querySelectorAll('.table-container');
        if (!containers.length) return;
        const fresh = {};
        collectRows().forEach(r => {
            if (!(r.office in groupedData)) return;
            (fresh[r.office] = fresh[r.office] || []).push({ id: r.id, name: r.name, position: r.position, office: r.office, date: r.date, accountType: r.accountType, salaryGrade: r.salaryGrade, scores: r.scores });
        });
        containers.forEach(c => {
            const o = c.getAttribute('data-office');
            if (o in groupedData) groupedData[o] = fresh[o] || [];
        });
    }

    async function removeRater(el) {
        const r = el.getAttribute('data-rater');
        const block = el.closest('.table-block');
        const office = block.closest('.table-container').getAttribute('data-office');
        const position = block.getAttribute('data-position') || 'UNASSIGNED';
        const accountType = block.getAttribute('data-account-type') || '';
        const rawSalaryGrade = block.getAttribute('data-salary-grade') || '';
        const salaryGrade = /^\d+$/.test(rawSalaryGrade) ? Number(rawSalaryGrade) : null;
        if (!await window.showSiteConfirm('Remove the "' + r + '" column for position "' + position + '"?\n\nIts scores will be deleted when you click Save All Changes.', { confirmText: 'Remove' })) return;
        syncFromDom();
        (groupedData[office] || []).forEach(c => {
            const pos = (c.position && c.position.trim() !== '') ? c.position.trim() : 'UNASSIGNED';
            if (pos === position && (c.accountType || '') === accountType
                && (c.salaryGrade == null ? null : Number(c.salaryGrade)) === salaryGrade && c.scores) delete c.scores[r];
        });
        const otherScopeHasScore = (groupedData[office] || []).some(c => {
            const pos = (c.position && c.position.trim() !== '') ? c.position.trim() : 'UNASSIGNED';
            return pos === position && ((c.accountType || '') !== accountType
                || (c.salaryGrade == null ? null : Number(c.salaryGrade)) !== salaryGrade) && c.scores && c.scores[r];
        });
        if (!otherScopeHasScore && storedOrders[office] && storedOrders[office][position]) {
            storedOrders[office][position] = storedOrders[office][position].filter(x => x !== r);
        }
        Object.keys(addedRaters).forEach(k => {
            if (k.indexOf(office + '|' + position + '|') === 0) addedRaters[k] = addedRaters[k].filter(x => x !== r);
        });
        markResultsDirty();
        skipNextSync = true;
        renderAll();
    }

    function updateAccountShortcuts() {
        const accountTypes = [
            { type: 'RO', button: document.getElementById('regionResultsButton'), label: 'Region' },
            { type: 'FO', button: document.getElementById('fieldResultsButton'), label: 'Field' },
        ];
        accountTypes.forEach(({ type, button, label }) => {
            if (!button) return;
            button.textContent = label;
            button.classList.toggle('active', activeAccountType === type);
            button.setAttribute('aria-pressed', activeAccountType === type ? 'true' : 'false');
        });
    }

    function switchAccountType(accountType) {
        if (accountType !== 'RO' && accountType !== 'FO') return;
        activeAccountType = accountType;
        const matchingOffice = Object.entries(groupedData).find(([, candidates]) =>
            candidates.some(candidate => candidate.accountType === activeAccountType));
        if (matchingOffice) activeTabOffice = matchingOffice[0];
        renderAll();
    }

    async function deletePositionGroup(btnEl, office, position, accountType = '', rawSalaryGrade = '') {
        syncFromDom();
        const list = groupedData[office] || [];
        const matchesPosition = c => (c.position && c.position.trim() !== '') ? c.position.trim() === position : position === 'UNASSIGNED';
        const salaryGrade = /^\d+$/.test(rawSalaryGrade) ? Number(rawSalaryGrade) : null;
        const matchesSalaryGrade = c => (c.salaryGrade == null ? null : Number(c.salaryGrade)) === salaryGrade;
        const matching = list.filter(c => matchesPosition(c) && (c.accountType || '') === accountType && matchesSalaryGrade(c));
        const savedCount = matching.filter(c => c.id > 0).length;
        const scopeLabel = accountType === 'FO' ? 'Field' : accountType === 'RO' ? 'Region' : 'Unclassified';
        const gradeLabel = salaryGrade === null ? '' : ` at SG ${salaryGrade}`;
        const msg = savedCount > 0 ? `Delete the entire "${position}"${gradeLabel} ${scopeLabel} table for ${office}?\n\nThis will remove ${savedCount} saved applicant(s) and all of their scores.` : `Delete the entire "${position}"${gradeLabel} ${scopeLabel} table for ${office}?`;
        if (!await window.showSiteConfirm(msg)) return;
        markResultsDirty();
        groupedData[office] = list.filter(c => !matchesPosition(c) || (c.accountType || '') !== accountType || !matchesSalaryGrade(c));
        const hasOtherScopeRows = groupedData[office].some(c => matchesPosition(c));
        if (!hasOtherScopeRows && storedOrders[office] && storedOrders[office][position]) delete storedOrders[office][position];
        if (!hasOtherScopeRows) Object.keys(addedRaters).forEach(k => { if (k.indexOf(office + '|' + position + '|') === 0) delete addedRaters[k]; });
        window.__positionsToDelete.push({ office: office, position: position, accountType: accountType, salaryGrade: salaryGrade });
        skipNextSync = true;
        renderAll();
        if (savedCount > 0) window.showSiteAlert('Removed from view.\n\nClick "Save All Changes" to permanently delete from the database.');
    }

    function renderAll() {
        const transitionClass = sheetTransition;
        const update = () => renderAllNow(
            typeof document.startViewTransition === 'function' ? '' : transitionClass
        );
        if (typeof window.runUITransition === 'function') {
            const transition = transitionClass === 'sr'
                ? 'sheet-reverse'
                : transitionClass === 'sl' ? 'sheet-forward' : 'content';
            return window.runUITransition(update, transition);
        }
        return update();
    }

    function renderAllNow(transitionClass = '') {
        if (!skipNextSync) syncFromDom();
        skipNextSync = false;
        const tabsBar = document.getElementById('tabs');
        const wrapper = document.getElementById('tables-wrapper');
        document.getElementById('results').dataset.accountView = activeAccountType || 'UNCLASSIFIED';
        const previousIndicator = tabsBar.querySelector('.ind');
        const indicatorLeft = previousIndicator ? previousIndicator.style.left : '0px';
        const indicatorWidth = previousIndicator ? previousIndicator.style.width : '0px';
        tabsBar.innerHTML = '';
        const indicator = document.createElement('span');
        indicator.className = 'ind';
        indicator.setAttribute('aria-hidden', 'true');
        indicator.style.left = indicatorLeft;
        indicator.style.width = indicatorWidth;
        tabsBar.appendChild(indicator);
        wrapper.innerHTML = '';
        const branchKeys = Object.keys(groupedData);
        if (branchKeys.length > 0 && !branchKeys.includes(activeTabOffice)) activeTabOffice = branchKeys[0];
        updateAccountShortcuts();

        branchKeys.forEach(branch => {
            const candidates = groupedData[branch] || [];
            const tab = document.createElement('div');
            tab.className = `tab ${branch === activeTabOffice ? 'active' : ''}`;
            tab.setAttribute('aria-selected', branch === activeTabOffice ? 'true' : 'false');
            tab.onclick = () => switchTab(branch);
            const tabLabel = document.createElement('span');
            tabLabel.innerText = branch;
            tab.appendChild(tabLabel);
            const closeBtn = document.createElement('span');
            closeBtn.className = 'tab-close-btn';
            closeBtn.innerText = '×';
            closeBtn.title = 'Delete this tab';
            closeBtn.onclick = (e) => { e.stopPropagation(); e.preventDefault(); deleteTab(branch); };
            tab.appendChild(closeBtn);
            tabsBar.appendChild(tab);

            const container = document.createElement('div');
            container.className = `table-container sheet ${branch === activeTabOffice ? `active ${transitionClass}` : ''}`;
            container.setAttribute('data-office', branch);

            const headerScope = activeAccountType === 'FO' ? 'FIELD ACCOUNT RESULTS'
                : activeAccountType === 'RO' ? 'REGION ACCOUNT RESULTS' : 'UNCLASSIFIED RESULTS';

            let docHeaderHtml = `
                <div class="doc-header dh">
                    <div class="gov-title a">Republic of the Philippines</div>
                    <div class="agency-title b">DEPARTMENT OF ENVIRONMENT AND NATURAL RESOURCES</div>
                    <div class="office-sub c">${escapeHtml(branch)}</div>
                    <div class="account-scope">${headerScope}</div>
                    <div class="doc-name d">MASTER INTERVIEW SUMMARY RATING SHEET</div>
                </div>
            `;

            const positionGroups = {};
            candidates.forEach(cand => {
                const posKey = (cand.position && cand.position.trim() !== '') ? cand.position.trim() : 'UNASSIGNED';
                const accountType = cand.accountType === 'RO' || cand.accountType === 'FO' ? cand.accountType : '';
                const salaryGrade = cand.salaryGrade == null ? null : Number(cand.salaryGrade);
                const groupKey = JSON.stringify([accountType, salaryGrade, posKey]);
                if (!positionGroups[groupKey]) positionGroups[groupKey] = { position: posKey, accountType, salaryGrade, candidates: [] };
                positionGroups[groupKey].candidates.push(cand);
            });
            if (Object.keys(positionGroups).length === 0) {
                const groupKey = JSON.stringify([activeAccountType, null, 'UNASSIGNED']);
                positionGroups[groupKey] = { position: 'UNASSIGNED', accountType: activeAccountType, salaryGrade: null, candidates: [] };
            }

            const sectionsByType = {};
            Object.values(positionGroups).forEach(positionGroup => {
                const { position: posKey, accountType, salaryGrade, candidates: candsForPos } = positionGroup;
                const dateGroups = {};
                candsForPos.forEach(cand => {
                    const dateKey = (cand.date && cand.date.trim() !== '') ? cand.date.trim() : "<?php echo date('F d, Y'); ?>";
                    if (!dateGroups[dateKey]) dateGroups[dateKey] = [];
                    dateGroups[dateKey].push(cand);
                });
                if (Object.keys(dateGroups).length === 0) dateGroups["<?php echo date('F d, Y'); ?>"] = [];
                Object.keys(dateGroups).forEach(dateKey => {
                    const sectionType = accountType || 'UNCLASSIFIED';
                    if (!sectionsByType[sectionType]) sectionsByType[sectionType] = '';
                    sectionsByType[sectionType] += buildTableBlockHtml(dateGroups[dateKey], dateKey, branch, posKey, accountType, salaryGrade);
                });
            });

            const activeSectionType = activeAccountType || 'UNCLASSIFIED';
            if (!sectionsByType[activeSectionType] && !sectionsByType.UNCLASSIFIED) {
                const emptyText = activeAccountType === 'FO' ? 'No Field-account results in this office yet.'
                    : 'No Region-account results in this office yet.';
                sectionsByType[activeSectionType] = `<div class="account-empty-state">${emptyText}</div>`;
            }
            let stackHtml = docHeaderHtml;
            Object.entries(sectionsByType).forEach(([sectionType, sectionHtml]) => {
                const sectionLabel = sectionType === 'UNCLASSIFIED'
                    ? '<h3 class="account-scope">Account type not recorded</h3>'
                    : '';
                stackHtml += `<div class="account-result-section" data-account-type="${sectionType}">${sectionLabel}${sectionHtml}</div>`;
            });
            stackHtml += buildSignatoryBlock();
            container.innerHTML = stackHtml;
            wrapper.appendChild(container);
            container.querySelectorAll('.table-block').forEach(updateSheetScrollControls);
            container.querySelectorAll('.scores-body').forEach(tbody => checkAndAutoAddRow(tbody));
        });

        const selectedTab = tabsBar.querySelector('.tab[aria-selected="true"]');
        if (selectedTab) {
            indicator.style.left = `${selectedTab.offsetLeft}px`;
            indicator.style.width = `${selectedTab.offsetWidth}px`;
        }
        sheetTransition = '';
    }

    function switchTab(branch) {
        const branches = Object.keys(groupedData);
        const currentIndex = branches.indexOf(activeTabOffice);
        const nextIndex = branches.indexOf(branch);
        sheetTransition = nextIndex > currentIndex ? 'sl' : nextIndex < currentIndex ? 'sr' : '';
        activeTabOffice = branch;
        renderAll();
    }
    function searchCandidate() {
        const query = document.getElementById('candidateSearch').value.trim().toLowerCase();
        if (query === '') return;
        for (const branch of Object.keys(groupedData)) {
            const match = (groupedData[branch] || []).find(c => c.name && c.name.toLowerCase().includes(query));
            if (match) {
                activeTabOffice = branch;
                renderAll();
                setTimeout(() => {
                    const rows = document.querySelectorAll('.scores-body tr');
                    for (const row of rows) {
                        const nameCell = row.querySelector('[data-field="name"]');
                        if (nameCell && nameCell.innerText.trim().toLowerCase().includes(query)) {
                            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            row.style.backgroundColor = '#fff3b0';
                            setTimeout(() => { row.style.backgroundColor = ''; }, 2500);
                            break;
                        }
                    }
                }, 50);
                return;
            }
        }
        window.showSiteAlert('Candidate not found.');
    }

    async function deleteTab(branch) {
        syncFromDom();
        const saved = (groupedData[branch] || []).filter(c => c.id > 0).length;
        const msg = saved > 0 ? `Delete the "${branch}" tab?\n\nThis permanently deletes ${saved} saved applicant(s) and all of their scores from the database.` : `Delete the "${branch}" tab?`;
        if (!await window.showSiteConfirm(msg)) return;
        fetch('delete_tab.php', { method: 'POST', credentials: 'same-origin', body: new URLSearchParams({ office: branch, csrf_token: CSRF_TOKEN }) })
        .then(res => res.text().then(text => { try { return JSON.parse(text); } catch (e) { throw new Error('Unexpected server reply: ' + text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 300)); } }))
        .then(data => {
            if (data.status !== 'success') return window.showSiteAlert('Delete failed - the tab was NOT deleted.\n' + (data.message || 'Unknown error'));
            delete groupedData[branch];
            delete storedOrders[branch];
            Object.keys(addedRaters).forEach(k => { if (k.indexOf(branch + '|') === 0) delete addedRaters[k]; });
            const remaining = Object.keys(groupedData);
            activeTabOffice = remaining.length > 0 ? remaining[0] : '';
            renderAll();
        }).catch(err => window.showSiteAlert('Delete failed - the tab was NOT deleted.\n' + err.message));
    }

    async function promptNewTab() {
        const name = await window.showSitePrompt('Enter new PENRO or Branch Name:', {
            title: 'Add office / branch',
            inputLabel: 'PENRO or branch name',
            confirmText: 'Add',
            maxLength: 100,
        });
        if (name && name.trim() !== "") {
            const cleanName = name.trim().toUpperCase();
            if (!groupedData[cleanName]) { groupedData[cleanName] = []; activeTabOffice = cleanName; markResultsDirty(); renderAll(); }
            else switchTab(cleanName);
        }
    }

    function renameBranchTab(el) {
        const container = el.closest('.table-container');
        const oldBranch = container.getAttribute('data-office');
        const newBranch = el.innerText.replace('OFFICE/BRANCH:', '').replace(/[\r\n\t]/g, '').trim().toUpperCase();
        if (!newBranch || newBranch === oldBranch) { el.innerText = 'OFFICE/BRANCH: ' + oldBranch; return; }
        if (groupedData[newBranch]) { window.showSiteAlert('A tab named "' + newBranch + '" already exists.'); el.innerText = 'OFFICE/BRANCH: ' + oldBranch; return; }
        syncFromDom();
        markResultsDirty();
        pendingRenames.push({ from: oldBranch, to: newBranch });
        groupedData[newBranch] = groupedData[oldBranch] || [];
        hiddenTabs.add(oldBranch);
        delete groupedData[oldBranch];
        if (activeTabOffice === oldBranch) activeTabOffice = newBranch;
        skipNextSync = true;
        renderAll();
    }

    async function handleRowDelete(cellEl) {
        const row = cellEl.closest('tr');
        const tbody = row.closest('tbody');
        const id = row.getAttribute('data-id');
        if (await window.showSiteConfirm('Delete this applicant row?')) {
            if (id && parseInt(id) > 0) {
                fetch('delete_candidate.php', { method: 'POST', credentials: 'same-origin', body: new URLSearchParams({ id: id, csrf_token: CSRF_TOKEN }) })
                .then(res => res.text().then(text => { try { return JSON.parse(text); } catch (e) { throw new Error('Unexpected server reply: ' + text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 300)); } }))
                .then(data => {
                    if (data.status === 'success') { row.remove(); reindexTableRows(tbody); }
                    else window.showSiteAlert('Delete failed - the row was NOT deleted.\n' + (data.message || 'Unknown error'));
                }).catch(err => window.showSiteAlert('Delete failed - the row was NOT deleted.\n' + err.message));
            } else { row.remove(); reindexTableRows(tbody); }
        }
    }

    function reindexTableRows(tbody) {
        Array.from(tbody.children).forEach((tr, index) => {
            const numSpan = tr.querySelector('.row-number');
            if (numSpan) numSpan.innerText = index + 1;
        });
        checkAndAutoAddRow(tbody);
    }

    function recalculateRow(row) {
        const pv = Array.from(row.querySelectorAll('td[data-kind="p"]')).map(td => td.innerText.trim());
        const tv = Array.from(row.querySelectorAll('td[data-kind="t"]')).map(td => td.innerText.trim());
        const psychoAveEl = row.querySelector('.psycho-ave');
        const potentialAveEl = row.querySelector('.potential-ave');
        if (psychoAveEl) psychoAveEl.innerText = calculateAverage(...pv);
        if (potentialAveEl) potentialAveEl.innerText = calculateAverage(...tv);
    }

    function saveWholeTable(e) {
        if (e) e.preventDefault();
        if (resultsSaveBusy) return;
        resultsSaveBusy = true;
        const submittedVersion = resultsChangeVersion;
        const submittedPositionsToDelete = window.__positionsToDelete.slice();
        const submittedRenames = pendingRenames.slice();
        const saveButton = document.getElementById('saveResultsButton');
        const saveStatus = document.getElementById('resultsSaveStatus');
        if (saveButton) saveButton.disabled = true;
        if (saveStatus) {
            saveStatus.dataset.state = 'saving';
            saveStatus.textContent = 'Saving changes…';
        }
        const candidatesToSave = collectRows().map((r, i) => {
            r.row.setAttribute('data-temp-index', i);
            return { tempIndex: i, id: r.id, name: r.name, office: r.office, position: r.position, interviewDate: r.date, accountType: r.accountType, salaryGrade: r.salaryGrade, scores: r.scores };
        });

        const columnsToSave = [];
        document.querySelectorAll('.table-container').forEach(container => {
            const office = container.getAttribute('data-office') || 'REGIONAL OFFICE';
            container.querySelectorAll('.table-block').forEach(block => {
                const position = block.getAttribute('data-position') || 'UNASSIGNED';
                const tbody = block.querySelector('.scores-body');
                let raters = null;
                try { raters = JSON.parse((tbody && tbody.dataset.cols) || '[]'); } catch (err) { raters = null; }
                if (Array.isArray(raters)) columnsToSave.push({ office: office, position: position, raters: raters });
            });
        });

        const tabsToSave = Array.from(document.querySelectorAll('.table-container')).map(c => c.getAttribute('data-office')).filter(Boolean);

        fetch('save_score.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_token: CSRF_TOKEN, candidates: candidatesToSave, columns: columnsToSave, tabs: tabsToSave, hiddenTabs: Array.from(hiddenTabs), renames: submittedRenames, headers: headerLabels, positionsToDelete: submittedPositionsToDelete })
        })
        .then(res => res.text().then(text => { try { return JSON.parse(text); } catch (e) { throw new Error('Server returned non-JSON. First 300 chars: ' + text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 300)); } }))
        .then(data => {
            if (data.status === 'success') {
                if (data.savedIds) {
                    data.savedIds.forEach(item => {
                        const targetRow = document.querySelector(`tr[data-temp-index="${item.tempIndex}"]`);
                        if (targetRow) targetRow.setAttribute('data-id', item.id);
                    });
                }
                window.__positionsToDelete = window.__positionsToDelete.filter(item => !submittedPositionsToDelete.includes(item));
                for (const rename of submittedRenames) {
                    const index = pendingRenames.indexOf(rename);
                    if (index !== -1) pendingRenames.splice(index, 1);
                }
                if (resultsChangeVersion === submittedVersion) {
                    resultsDirty = false;
                    if (saveStatus) {
                        saveStatus.dataset.state = 'saved';
                        saveStatus.textContent = 'All changes saved.';
                    }
                } else if (saveStatus) {
                    saveStatus.dataset.state = 'dirty';
                    saveStatus.textContent = 'Saved, but newer changes still need to be saved.';
                }
                let msg = 'All interview rating sheets saved successfully!';
                if (typeof data.savedColumns === 'number') msg += '\n' + data.savedColumns + ' rater column(s) stored.';
                if (typeof data.deletedPositions === 'number' && data.deletedPositions > 0) msg += '\n' + data.deletedPositions + ' position table(s) permanently deleted.';
                if (typeof data.renamedOffices === 'number' && data.renamedOffices > 0) msg += '\n' + data.renamedOffices + ' office(s) renamed.';
                window.showSiteAlert(msg);
            } else {
                if (saveStatus) {
                    saveStatus.dataset.state = 'error';
                    saveStatus.textContent = 'Save failed. Your changes are still here; try again.';
                }
                window.showSiteAlert('Error saving: ' + (data.message || 'Unknown error'));
            }
        }).catch(err => {
            if (saveStatus) {
                saveStatus.dataset.state = 'error';
                saveStatus.textContent = 'Save failed. Your changes are still here; try again.';
            }
            window.showSiteAlert('Save request failed: ' + err.message);
        }).finally(() => {
            resultsSaveBusy = false;
            if (saveButton) saveButton.disabled = false;
        });
    }

    function exportExcel() {
        window.ResultsExcelExport.exportExcel({
            XLSX: window.XLSX,
            groupedData,
            headerLabels,
            raterColumns,
            blockKey,
            minSlots: MIN_SLOTS,
            todayDate: "<?php echo date('F d, Y'); ?>",
            syncFromDom
        });
    }

    async function importExcel(file) {
        syncFromDom();
        if (window.ResultsExcelImportLoadError) throw new Error(window.ResultsExcelImportLoadError);
        if (!window.ResultsExcelImport || typeof window.ResultsExcelImport.readWorkbook !== 'function') {
            throw new Error('The import module did not initialize. Refresh the page and check the results_excel_import.js file.');
        }
        const { candidates, invalidScores } = await window.ResultsExcelImport.readWorkbook(
            file,
            groupedData,
            "<?php echo date('F d, Y'); ?>",
            activeTabOffice
        );
        const existingById = new Map(Object.values(groupedData).flat()
            .filter(candidate => Number(candidate.id) > 0)
            .map(candidate => [Number(candidate.id), candidate]));
        candidates.forEach(candidate => {
            const previous = existingById.get(Number(candidate.id));
            if (previous) {
                candidate.accountType = previous.accountType || '';
                candidate.salaryGrade = previous.salaryGrade ?? null;
            }
            if (candidate.id > 0) {
                Object.keys(groupedData).forEach(branch => {
                    groupedData[branch] = groupedData[branch].filter(existing => Number(existing.id) !== candidate.id);
                });
            }
            if (!groupedData[candidate.office]) groupedData[candidate.office] = [];
            groupedData[candidate.office].push(candidate);
        });

        skipNextSync = true;
        activeTabOffice = Object.keys(groupedData).includes(activeTabOffice) ? activeTabOffice : Object.keys(groupedData)[0];
        renderAll();
        markResultsDirty();
        const scoreNotice = invalidScores.length ? ' Invalid score values were skipped on row(s): ' + [...new Set(invalidScores)].join(', ') + '.' : '';
        window.showSiteAlert(candidates.length + ' candidate row(s) imported. Review the results, then select Save All Changes to store them.' + scoreNotice);
    }

    document.addEventListener('DOMContentLoaded', function() {
        renderAll();
        document.getElementById('exportExcelButton').addEventListener('click', exportExcel);
        const importFile = document.getElementById('excelImportFile');
        document.getElementById('importExcelButton').addEventListener('click', () => importFile.click());
        importFile.addEventListener('change', async () => {
            const file = importFile.files[0];
            if (!file) return;
            try {
                await importExcel(file);
            } catch (error) {
                window.showSiteAlert('Import failed: ' + error.message);
            } finally {
                importFile.value = '';
            }
        });
        document.getElementById('candidateSearch')?.addEventListener('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); searchCandidate(); } });
        document.addEventListener('input', function(e) {
            if (e.target.closest('#tables-wrapper') && e.target.isContentEditable) markResultsDirty();
            if (e.target.isContentEditable) {
                const row = e.target.closest('tr');
                if (row && !(e.target.classList.contains('psycho-ave') || e.target.classList.contains('potential-ave'))) recalculateRow(row);
                const tbody = e.target.closest('.scores-body');
                if (tbody) checkAndAutoAddRow(tbody);
            }
        });
        document.getElementById('resultsForm').addEventListener('change', event => {
            if (event.target.closest('#tables-wrapper')) markResultsDirty();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.isContentEditable) {
                e.preventDefault();
                e.target.blur();
                const row = e.target.closest('tr');
                if (row) {
                    const cellIndex = Array.from(row.children).indexOf(e.target);
                    const nextRow = row.nextElementSibling;
                    if (nextRow && nextRow.children[cellIndex]) nextRow.children[cellIndex].focus();
                }
            }
        });
    });
    window.addEventListener('beforeunload', event => {
        if (!resultsDirty) return;
        event.preventDefault();
        event.returnValue = '';
    });
</script>
</body>
</html>