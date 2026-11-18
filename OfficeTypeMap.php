<?php

const ACCOUNT_TYPE_OFFICE_TYPES = [
    'region' => ['RO'],
    'field' => ['FO'],
];

const OFFICE_TYPE_SHEET_MAP = [
    'competency_Basic_FO' => ['office_type' => 'FO'],
    'competency_Intermediate_FO' => ['office_type' => 'FO'],
    'competency_advance_FO' => ['office_type' => 'FO'],
    'competency_RO' => ['office_type' => 'RO'],
    'competency_RO_Basic' => ['office_type' => 'RO'],
    'advance_RO' => ['office_type' => 'RO'],
];

const FIELD_POSITION_SHEETS = [
    'competency_Basic_FO' => [
        'min_salary_grade' => 6,
        'max_salary_grade' => 9,
        'positions' => [
            'Administrative Aide IV (Clerk II/Driver, Management)',
            'Administrative Aide VI (PCAS 3 only, Management)',
            'Administrative Assistant I (Computer Operator I)',
            'Administrative Assistant II (Bookkeeper)',
            'Administrative Assistant III (Computer Operator II)',
            'Administrative Aide IV (Technical, PCO 5 & 6 only)',
            'Administrative Aide VI (Technical)',
            'Credit Officer I',
            'Park Attendant II',
            'Forest Ranger',
            'Engineering Aide',
            'Cartographer I',
            'Cartographer II',
            'Land Management Inspector',
            'Forest Technician I',
            'Forest Technician II',
            'Park Maintenance Foreman',
        ],
    ],
    'competency_Intermediate_FO' => [
        'min_salary_grade' => 9,
        'max_salary_grade' => 11,
        'positions' => [
            'Credit Officer I',
            'Administrative Officer I (Cashier I)',
            'Administrative Officer I (Records Officer I)',
            'Administrative Officer I (Supply Officer I)',
            'Planning Officer I',
            'Planning Officer II',
            'Information Systems Analyst II',
            'Administrative Officer IV (Budget Officer II)',
            'Administrative Officer IV (HRMO II)',
            'Forester I',
            'Forester II',
            'Ecosystems Management Specialist I',
            'Ecosystems Management Specialist II',
            'Land Management Officer I',
            'Land Management Officer II',
            'Special Investigator I',
            'Community Development Officer I',
            'Community Development Officer II',
            'Development Management Officer I',
            'Development Management Officer II',
            'Engineer II (Geodetic Engineer)',
        ],
    ],
    'competency_advance_FO' => [
        'min_salary_grade' => 18,
        'max_salary_grade' => 22,
        'positions' => [
            'Accountant III',
            'Planning Officer III',
            'Development Management Officer III',
            'Forester III',
            'Land Management Officer III',
            'Senior Ecosystems Management Specialist',
            'Supervising Ecosystems Management Specialist',
        ],
    ],
];

function account_type_for_profile(?array $profile): ?string {
    $accountType = strtolower(trim((string)($profile['account_type'] ?? '')));
    return array_key_exists($accountType, ACCOUNT_TYPE_OFFICE_TYPES) ? $accountType : null;
}

function office_types_for_account_type(?string $accountType): array {
    return ACCOUNT_TYPE_OFFICE_TYPES[strtolower(trim((string)$accountType))] ?? [];
}

function office_type_for_sheet(string $sheetName): string {
    foreach (OFFICE_TYPE_SHEET_MAP as $configuredSheet => $mapping) {
        if (strcasecmp($configuredSheet, $sheetName) === 0) return $mapping['office_type'];
    }
    if (stripos($sheetName, 'RO') !== false) return 'RO';
    if (preg_match('/_FO$/i', $sheetName) || stripos($sheetName, 'field') === 0) return 'FO';
    return 'RO';
}

function office_type_for_template_layout($layout, string $title = ''): string {
    if (is_array($layout)) {
        $sourceSheet = trim((string)($layout['source_sheet'] ?? ''));
        if ($sourceSheet !== '') return office_type_for_sheet($sourceSheet);
        $explicitType = strtoupper(trim((string)($layout['office_type'] ?? '')));
        if (in_array($explicitType, ['RO', 'FO'], true)) return $explicitType;
    }
    return office_type_for_sheet($title);
}

function field_positions_by_sheet(): array {
    $positions = [];
    foreach (FIELD_POSITION_SHEETS as $sheetName => $sheet) {
        foreach ($sheet['positions'] as $title) {
            $positions[] = [
                'sheet_name' => $sheetName,
                'title' => $title,
                'min_salary_grade' => $sheet['min_salary_grade'],
                'max_salary_grade' => $sheet['max_salary_grade'],
                'office_type' => 'FO',
            ];
        }
    }
    return $positions;
}

function field_position_for_sheet_title(string $sheetName, string $title): ?array {
    foreach (field_positions_by_sheet() as $position) {
        if ($position['sheet_name'] === $sheetName && $position['title'] === $title) return $position;
    }
    return null;
}

function account_type_salary_grades(mysqli $conn, ?string $accountType): array {
    $allowedTypes = office_types_for_account_type($accountType);
    $grades = [];
    if (in_array('RO', $allowedTypes, true)) {
        $grades = access_position_salary_grades($conn);
    }
    if (in_array('FO', $allowedTypes, true)) {
        foreach (FIELD_POSITION_SHEETS as $sheet) {
            for ($grade = $sheet['min_salary_grade']; $grade <= $sheet['max_salary_grade']; $grade++) {
                $grades[] = $grade;
            }
        }
    }
    $grades = array_values(array_unique(array_map('intval', $grades)));
    sort($grades);
    return $grades;
}
