<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/AuthSchema.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/PositionsSchema.php';
require_once __DIR__ . '/Raters.php';
require_once __DIR__ . '/AccessGroups.php';



function field_office_competency_levels(): array {
    return [
        'BASIC' => ['min_grade' => 6, 'max_grade' => 9],
        'INTERMEDIATE' => ['min_grade' => 9, 'max_grade' => 11],
        'ADVANCE' => ['min_grade' => 18, 'max_grade' => 22],
    ];
}

function field_office_competency_library(): array {
    return [
        'PCFM1' => ['GENERAL ACCOUNTING', '', '', 'Reviews schedules, plans, work procedures and organizes processes in gathering financial information needed for the completion of the reports'],
        'PCFM2' => ['BUDGET PREPARATION', "Assists in budget preparation activities to determine the DENR's financial requirement", 'Analyzes and interprets DENR\'s financial transactions', ''],
        'PCFM3' => ['BUDGET ADMINISTRATION AND CONTROL', 'Assists in the preparation of budget allocation of departments/divisions', 'Performs budget administration and control activities', ''],
        'PCFM4' => ['CASH MANAGEMENT', 'Performs cash management activities', 'Performs cash management activities', ''],
        'PCAS1' => ['PROCUREMENT MANAGEMENT', 'Has basic skills / knowledge/ understanding on RA 9184 (Procurement Act) and e-procurement (PhilGEPS); and requires assistance to apply technical skills; and displays limited knowledge of technologies.', 'Has Intermediate Skill/ Working Knowledge on RA 9184 and e procurement; and applies limited technical skills and demonstrates limited knowledge of emerging technology (e.g. IT, processes, methodologies, etc.) on procurement management related activities.', ''],
        'PCAS2' => ['PROPERTY MANAGEMENT (PROPERTY INVENTORY AND DISPOSAL MANAGEMENT)', 'Assists in the conduct of property management activities', 'Conducts property warehousing, storage and inventory of the property, plant and equipment of the office', ''],
        'PCAS3' => ['RECORDS MANAGEMENT', 'Assists in records management activities', 'Implements records management activities', ''],
        'PCAS4' => ['CLERICAL/SECRETARIAL/EXECUTIVE ASSISTANCE SKILLS', 'Assists in discharging complete staff work', 'Implements complete staff work and enhances personal security measures', ''],
        'PCAS5' => ['INFRASTRUCTURE MAINTENANCE SYSTEM ADMINISTRATION', 'Performs basic maintenance activities or guides outsourced services for the maintenance and repair of buildings, equipment and other facilities', '', ''],
        'PCAS6' => ['VEHICLE REPAIR AND MAINTENANCE', 'Performs minor repairs and maintenance work on motor vehicles.', '', ''],
        'PCAS8' => ['CUSTOMER ASSISTANCE AND REQUEST HANDLING', 'Receives requests, complaints, observations and suggestions, and informs concerned personnel and offices', 'Monitors the work progress of the requests, complaints, observations and suggestions', ''],
        'PCP1' => ['PLANNING AND PROGRAMMING', '', 'Consolidates and reviews the PENRO Work and Financial Plans (WFPs)', 'Organizes the preparation of the PENRO operational plans'],
        'PCP2' => ['MONITORING AND EVALUATION', '', 'Conducts monitoring and evaluation of all DENR-PENRO programs and projects', 'Leads the preparation of monitoring and evaluation reports of all DENR-PENRO programs and projects'],
        'PCP3' => ['STATISTICAL COORDINATION AND DATA RESEARCH', '', 'Conducts statistical coordination and data research activities', 'Leads the conduct of statistical coordination and data research activities'],
        'PCIS1' => ['WEB DEVELOPMENT', '', 'Conducts activities in web development, design, installation and maintenance', ''],
        'PCIS2' => ['NETWORK SYSTEMS MANAGEMENT', '', 'Conducts activities in installing, maintaining, and modifying operating systems, database management systems, utilities, and related software', ''],
        'PCIS3' => ['INFORMATION AND COMMUNICATION TECHNOLOGIES (ICT) RESOURCE MANAGEMENT', '', 'Monitors and maintains the condition of HW, SW, NW and peripherals', ''],
        'PCHR1' => ['RECRUITMENT, SELECTION AND PLACEMENT', '', 'Implements recruitment, selection and placement processes', ''],
        'PCHR2' => ['COMPENSATION, BENEFITS AND WELFARE ADMINISTRATION', '', 'Implements Compensation, Benefits and Welfare administration policies', ''],
        'PCHR3' => ['PROCESSING OF PERSONNEL ACTIONS', '', 'Implements applicable DENR, CSC, DBM and CESB laws, rules and regulations in the processing of personnel actions', ''],
        'PCHR4' => ['GRIEVANCE HANDLING', '', 'Implements Grievance Handling Guidelines in resolving Administrative cases', ''],
        'PCHR5' => ['PERFORMANCE MANAGEMENT', '', 'Implements performance, incentives and awards policies', ''],
        'PCHR6' => ['LEARNING NEEDS ASSESSMENT (LNA)', '', 'Implements LNA activities', ''],
        'PCHR7' => ['CAREER DEVELOPMENT', '', 'Conducts career development activities', ''],
        'PCO1' => ['CONCEPT AND APPLICATION OF INTEGRATED ECOSYSTEMS MANAGEMENT (IEM)', 'Assists in the application of IEM and other related approaches to Natural Resources Management (NRM)', 'Applies IEM and other related approaches to NRM', 'Leads the application of IEM and other related approaches to NRM in basin, watersheds, sub-watershed, protected areas, ancestral domains, mineral reservations, islands, others'],
        'PCO2' => ['IDENTIFICATION OF INTERVENTIONS AND INTEGRATING STRATEGIES ACROSS SECTORS (FORESTRY, COASTAL, AGRICULTURE, URBAN, AIR PACE) AND ZONING FOR STRATEGIC MANAGEMENT', 'Assists in the identification of interventions and integrating strategies across sectors', 'Identifies interventions and integrating strategies across sectors', 'Leads the identification of interventions and integration strategies across sectors'],
        'PCO3' => ['CHARACTERIZATION OF ECOSYSTEMS AND USE OF PLANNING TOOLS AND PROCEDURES', 'Assists in the characterization of ecosystems and use of planning tools and procedures', 'Undertakes characterization of ecosystems and use of planning tools and procedures', 'Leads the characterization of ecosystem and use of planning tools and procedures'],
        'PCO4' => ['RESOURCE MANAGEMENT AND RESTORATION/REHABILITATION OF DEGRADED ECOSYSTEMS', 'Assists in identifying and implementing appropriate resource management and restoration strategies of various ecosystems', 'Identifies and implements appropriate resource management and restoration strategies of various ecosystems', 'Leads the implementation of appropriate resource management and restoration strategies of various ecosystems'],
        'PCO5' => ['PREPARATION OF NATURAL RESOURCES MANAGEMENT (NRM)-RELATED PLANS (FLUP, CRMP, ISWMP, PAMP, IRBM, IWRM)', 'Assists in the preparation of NRM-related plans (FLUP, CRMP, ISWMP, PAMP, IRBM, IWRM) and integration to LGU plans', 'Facilitates/ provides technical assistance in the formulation and implementation of NRM- related plans (FLUP, CRMP, ISWMP, PAMP, IRBM, IWRM) and integration to LGU plans', 'Leads the preparation of NRM-related plans (FLUP, CRMP, ISWMP, PAMP, IRBM, IWRM) and integration to LGU plans'],
        'PCO6' => ['ENVIRONMENT AND NATURAL RESOURCE (ENR) ACCOUNTING', 'Assists in undertaking ENR accounting activities', 'Undertakes ENR accounting activities', 'Implements ENR accounting activities'],
        'PCO7' => ['STRATEGIES AND SCHEMES FOR FINANCING ENVIRONMENTAL PROJECTS', 'Assists in planning, initiating and operationalizing strategies and schemes for financing environmental projects', 'Plans, initiates and operationalizes strategies and schemes for financing environmental projects', 'Leads the planning, initiating and operationalizing strategies and schemes for financing environmental projects'],
        'PCO8' => ['RESULTS-BASED MONITORING AND EVALUATION SYSTEM (RBME) AND ENVIRONMENTAL AUDIT FOR DIFFERENT ENRM SITES', 'Assists in RBME and Environmental Audit of activities for different ENRM sites', 'Implements RBME activities and environmental audit for different ENRM sites.', 'Leads the implementation of RBME activities and environmental audit for different ENRM sites'],
        'PCO9' => ['ENVIRONMENTAL GOVERNANCE', 'Assists in environmental governance activities that include transparency, accountability, participation, collaboration and functionality', 'Undertakes environmental governance activities that include transparency, accountability, participation, collaboration and functionality', 'Leads environmental governance activities that include transparency, accountability, participation, collaboration and functionality'],
        'PCO10' => ['CLIMATE CHANGE AND ENVIRONMENTAL MANAGEMENT', 'Assists in applying mitigating measures and adaptive solutions to climate change', 'Applies mitigating measures and adaptive solutions to climate change', 'Leads the application of mitigating measures and adaptive solutions to climate change'],
        'PCO11' => ['INFORMATION, EDUCATION AND COMMUNICATION, SOCIAL MARKETING AND EXTENSION SUPPORT', 'Assists in social marketing and extension support activities to improve ENRM', 'Undertakes social marketing and extension support activities to improve ENRM', 'Leads in social marketing and extension support activities to improve ENRM'],
        'PCO12' => ['SOCIAL NEGOTIATION', 'Assists in social negotiation activities', 'Conducts social negotiation activities', 'Leads conduct of social negotiation activities'],
        'PCO13' => ['ENR LAW ENFORCEMENT', 'Assists in ENR law enforcement activities', 'Implements ENR law enforcement activities', 'Leads the implementation of ENR law enforcement activities'],
        'PCO14' => ['LAND DISPOSITION AND MANAGEMENT', 'Assist in the disposition of A&D lands and management of other government lands.', 'Processes and evaluates public land applications', 'Leads in the disposition of A&D lands and management of other government lands'],
        'PCO15' => ['FOREST, WATER AND WILDLIFE RESOURCES REGULATION', 'Assists in the forest, water and wildlife resource utilization and regulation activities', 'Processes and evaluates forest, water and wildlife resource utilization and regulation activities', 'Evaluates and reviews the forest, water and wildlife resource utilization and regulation activities'],
        'PCO16' => ['TENURE AND RIGHTS ASSESSMENT', 'Assists in the evaluation on the effectiveness of tenure holders based on organizational, stewardship, protection and management capabilities', 'Evaluates effectiveness of tenure holders based on organizational, stewardship, protection and management capabilities', 'Leads the evaluation of effectiveness of tenure holders based on organizational, stewardship, protection and management capabilities'],
        'PCO17' => ['TENURIAL INSTRUMENTS AND PERMITS FOR IMPROVED RESOURCE MANAGEMENT', 'Assist in the preparation of tenurial instruments and permits for improved resource management', 'Prepares tenurial instruments and permits for improved resource management.', 'Reviews and recommends the approval of the issuance of tenurial instruments and permits for improved resource management.'],
        'PCO18' => ['GEOGRAPHIC INFORMATION SYSTEM (GIS)', 'Knows basic knowledge in GIS and other related software', 'Implements GIS application and development for resource mapping', ''],
        'PCO19' => ['SURVEYING', 'Assists in land surveying activities', 'Conducts land surveying activities', ''],
        'PCO20' => ['SURVEY VERIFICATION', 'Assists in survey verification activities', 'Conducts survey verification activities', ''],
        'PCO21' => ['MAPPING', 'Assists in land mapping activities', 'Conducts land mapping activities', ''],
        'PCO22' => ['LAND MANAGEMENT INFORMATION SYSTEM ADMINISTRATION', 'Has knowledge in LAMS and other related software', 'Implements LAMS application and development for resource mapping', ''],
        'PCO23' => ['LAND RECORDS MANAGEMENT', 'Assists in land records management activities', 'Maintains an updated and orderly filing system of land records', ''],
    ];
}

function field_office_competency_position_codes(): array {
    $positionCodes = [
        'BASIC' => [
            'ADMINISTRATIVE AIDE IV CLERK II DRIVER' => ['PCAS1','PCAS2','PCAS3','PCAS4','PCAS5','PCAS6'],
            'ADMINISTRATIVE AIDE IV CLERK II DRIVER MANAGEMENT' => ['PCAS1','PCAS2','PCAS3','PCAS4','PCAS5','PCAS6'],
            'ADMINISTRATIVE AIDE VI' => ['PCAS3'],
            'ADMINISTRATIVE AIDE VI PCAS 3 ONLY MANAGEMENT' => ['PCAS3'],
            'ADMINISTRATIVE ASSISTANT I COMPUTER OPERATOR I' => ['PCAS4','PCAS8'],
            'ADMINISTRATIVE ASSISTANT I' => ['PCAS4','PCAS8'],
            'ADMINISTRATIVE ASSISTANT II BOOKKEEPER' => ['PCFM2','PCFM3'],
            'ADMINISTRATIVE ASSISTANT II' => ['PCFM2','PCFM3'],
            'ADMINISTRATIVE ASSISTANT III COMPUTER OPERATOR II' => ['PCAS4','PCAS3','PCAS8'],
            'ADMINISTRATIVE ASSISTANT III' => ['PCAS4','PCAS3','PCAS8'],
            'ADMINISTRATIVE AIDE IV TECHNICAL' => ['PCO5','PCO6'],
            'ADMINISTRATIVE AIDE IV TECHNICAL PCO 5 6 ONLY' => ['PCO5','PCO6'],
            'ADMINISTRATIVE AIDE VI TECHNICAL' => ['PCO1','PCO4','PCO5','PCO6','PCO9','PCO10','PCO14','PCO15','PCO16','PCO17'],
            'CREDIT OFFICER I' => ['PCFM4','PCAS3','PCAS8'],
            'PARK ATTENDANT II' => ['PCO1','PCO2','PCO3','PCO4','PCO9','PCO10','PCO13'],
            'FOREST RANGER' => ['PCO1','PCO2','PCO3','PCO4','PCO9','PCO10','PCO13'],
            'PARK ATTENDANT II FOREST RANGER' => ['PCO1','PCO2','PCO3','PCO4','PCO9','PCO10','PCO13'],
            'ENGINEERING AIDE' => ['PCO14','PCO15','PCO16','PCO17','PCO18','PCO19','PCO20','PCO21','PCO22','PCO23'],
            'CARTOGRAPHER I' => ['PCO1','PCO4','PCO5','PCO9','PCO10','PCO14','PCO15','PCO16','PCO17'],
            'CARTOGRAPHER II' => ['PCO1','PCO4','PCO5','PCO9','PCO10','PCO14','PCO15','PCO16','PCO17'],
            'LAND MANAGEMENT INSPECTOR' => ['PCO1','PCO4','PCO5','PCO9','PCO10','PCO14','PCO15','PCO16','PCO17'],
            'CARTOGRAPHER I II LAND MANAGEMENT INSPECTOR' => ['PCO1','PCO4','PCO5','PCO9','PCO10','PCO14','PCO15','PCO16','PCO17'],
            'FOREST TECHNICIAN I' => ['PCO1','PCO2','PCO3','PCO4','PCO5','PCO6','PCO7','PCO8','PCO9','PCO10','PCO11','PCO12','PCO13','PCO14','PCO15','PCO16','PCO17'],
            'FOREST TECHNICIAN II' => ['PCO1','PCO2','PCO3','PCO4','PCO5','PCO6','PCO7','PCO8','PCO9','PCO10','PCO11','PCO12','PCO13','PCO14','PCO15','PCO16','PCO17'],
            'PARK MAINTENANCE FOREMAN' => ['PCO1','PCO2','PCO3','PCO4','PCO5','PCO6','PCO7','PCO8','PCO9','PCO10','PCO11','PCO12','PCO13','PCO14','PCO15','PCO16','PCO17'],
            'FOREST TECHNICIAN I II PARK MAINTENANCE FOREMAN' => ['PCO1','PCO2','PCO3','PCO4','PCO5','PCO6','PCO7','PCO8','PCO9','PCO10','PCO11','PCO12','PCO13','PCO14','PCO15','PCO16','PCO17'],
        ],
        'INTERMEDIATE' => [
            'CREDIT OFFICER I' => ['PCFM4'],
            'ADMINISTRATIVE OFFICER I CASHIER I' => ['PCFM4','PCAS4'],
            'ADMINISTRATIVE OFFICER I RECORDS OFFICER I' => ['PCAS1','PCAS2','PCAS3','PCAS4','PCAS8'],
            'ADMINISTRATIVE OFFICER I SUPPLY OFFICER I' => ['PCAS1','PCAS2','PCAS3','PCAS4','PCAS8'],
            'PLANNING OFFICER I' => ['PCP1','PCP2','PCP3'],
            'PLANNING OFFICER II' => ['PCP1','PCP2','PCP3'],
            'PLANNING OFFICER I II' => ['PCP1','PCP2','PCP3'],
            'INFORMATION SYSTEMS ANALYST II' => ['PCIS1','PCIS2','PCIS3'],
            'ADMINISTRATIVE OFFICER IV BUDGET OFFICER II' => ['PCFM2','PCFM3'],
            'ADMINISTRATIVE OFFICER IV HRMO II' => ['PCHR1','PCHR2','PCHR3','PCHR4','PCHR5','PCHR6','PCHR7'],
            'FORESTER I' => ['PCO1','PCO2','PCO3','PCO4','PCO5','PCO6','PCO7','PCO8','PCO9','PCO10','PCO11','PCO12','PCO13','PCO14','PCO15','PCO16','PCO17'],
            'FORESTER II' => ['PCO1','PCO2','PCO3','PCO4','PCO5','PCO6','PCO7','PCO8','PCO9','PCO10','PCO11','PCO12','PCO13','PCO14','PCO15','PCO16','PCO17'],
            'ECOSYSTEMS MANAGEMENT SPECIALIST I' => ['PCO1','PCO2','PCO3','PCO4','PCO5','PCO6','PCO7','PCO8','PCO9','PCO10','PCO11','PCO12','PCO13','PCO14','PCO15','PCO16','PCO17'],
            'ECOSYSTEMS MANAGEMENT SPECIALIST II' => ['PCO1','PCO2','PCO3','PCO4','PCO5','PCO6','PCO7','PCO8','PCO9','PCO10','PCO11','PCO12','PCO13','PCO14','PCO15','PCO16','PCO17'],
            'LAND MANAGEMENT OFFICER I' => ['PCO1','PCO2','PCO3','PCO4','PCO5','PCO6','PCO7','PCO8','PCO9','PCO10','PCO11','PCO12','PCO13','PCO14','PCO15','PCO16','PCO17'],
            'LAND MANAGEMENT OFFICER II' => ['PCO1','PCO2','PCO3','PCO4','PCO5','PCO6','PCO7','PCO8','PCO9','PCO10','PCO11','PCO12','PCO13','PCO14','PCO15','PCO16','PCO17'],
            'SPECIAL INVESTIGATOR I' => ['PCO1','PCO2','PCO3','PCO4','PCO5','PCO6','PCO7','PCO8','PCO9','PCO10','PCO11','PCO12','PCO13','PCO14','PCO15','PCO16','PCO17'],
            'COMMUNITY DEVELOPMENT OFFICER I' => ['PCO1','PCO2','PCO3','PCO4','PCO5','PCO6','PCO7','PCO8','PCO9','PCO10','PCO11','PCO12','PCO13','PCO14','PCO15','PCO16','PCO17'],
            'COMMUNITY DEVELOPMENT OFFICER II' => ['PCO1','PCO2','PCO3','PCO4','PCO5','PCO6','PCO7','PCO8','PCO9','PCO10','PCO11','PCO12','PCO13','PCO14','PCO15','PCO16','PCO17'],
            'DEVELOPMENT MANAGEMENT OFFICER I' => ['PCO1','PCO2','PCO3','PCO4','PCO5','PCO6','PCO7','PCO8','PCO9','PCO10','PCO11','PCO12','PCO13','PCO14','PCO15','PCO16','PCO17'],
            'DEVELOPMENT MANAGEMENT OFFICER II' => ['PCO1','PCO2','PCO3','PCO4','PCO5','PCO6','PCO7','PCO8','PCO9','PCO10','PCO11','PCO12','PCO13','PCO14','PCO15','PCO16','PCO17'],
            'ENGINEER II GEODETIC ENGINEER' => ['PCO14','PCO15','PCO16','PCO17','PCO18','PCO19','PCO20','PCO21','PCO22','PCO23'],
        ],
        'ADVANCE' => [
            'ACCOUNTANT III' => ['PCFM1'],
            'PLANNING OFFICER III' => ['PCP1','PCP2','PCP3'],
            'DEVELOPMENT MANAGEMENT OFFICER III' => ['PCO1','PCO2','PCO3','PCO4','PCO5','PCO6','PCO7','PCO8','PCO9','PCO10','PCO11','PCO12','PCO13','PCO14','PCO15','PCO16','PCO17'],
            'FORESTER III' => ['PCO1','PCO2','PCO3','PCO4','PCO5','PCO6','PCO7','PCO8','PCO9','PCO10','PCO11','PCO12','PCO13','PCO14','PCO15','PCO16','PCO17'],
            'LAND MANAGEMENT OFFICER III' => ['PCO1','PCO2','PCO3','PCO4','PCO5','PCO6','PCO7','PCO8','PCO9','PCO10','PCO11','PCO12','PCO13','PCO14','PCO15','PCO16','PCO17'],
            'SENIOR ECOSYSTEMS MANAGEMENT SPECIALIST' => ['PCO1','PCO2','PCO3','PCO4','PCO5','PCO6','PCO7','PCO8','PCO9','PCO10','PCO11','PCO12','PCO13','PCO14','PCO15','PCO16','PCO17'],
            'SUPERVISING ECOSYSTEMS MANAGEMENT SPECIALIST' => ['PCO1','PCO2','PCO3','PCO4','PCO5','PCO6','PCO7','PCO8','PCO9','PCO10','PCO11','PCO12','PCO13','PCO14','PCO15','PCO16','PCO17'],
        ],
    ];
    return $positionCodes;
}

function field_office_competency_normalize_title(string $title, bool $removeParentheticals = false): string {
    if ($removeParentheticals) {
        $title = (string)preg_replace('/\([^)]*\)/', ' ', $title);
    }
    return strtoupper(trim((string)preg_replace('/[^A-Z0-9]+/i', ' ', $title)));
}

function field_office_competency_resolve(string $positionTitle, int $salaryGrade, ?string $levelOverride = null): ?array {
    $levels = field_office_competency_levels();
    $codesByLevel = field_office_competency_position_codes();
    $level = $levelOverride === null ? null : strtoupper(trim($levelOverride));
    if ($level !== null) {
        if (!isset($levels[$level])
            || $salaryGrade < $levels[$level]['min_grade']
            || $salaryGrade > $levels[$level]['max_grade']) {
            return null;
        }
    } else {
        foreach (['INTERMEDIATE', 'BASIC', 'ADVANCE'] as $candidateLevel) {
            if ($salaryGrade >= $levels[$candidateLevel]['min_grade']
                && $salaryGrade <= $levels[$candidateLevel]['max_grade']) {
                $level = $candidateLevel;
                break;
            }
        }
    }
    if ($level === null) return null;

    $title = field_office_competency_normalize_title($positionTitle);
    $maps = [$level => $codesByLevel[$level] ?? []];
    if ($level === 'INTERMEDIATE' && in_array($salaryGrade, [10, 11], true)) {
        foreach ([
            'FORESTER I II' => 'FORESTER I',
            'ECOSYSTEMS MANAGEMENT SPECIALIST I II' => 'ECOSYSTEMS MANAGEMENT SPECIALIST I',
            'LAND MANAGEMENT OFFICER I II' => 'LAND MANAGEMENT OFFICER I',
            'COMMUNITY DEVELOPMENT OFFICER I II' => 'COMMUNITY DEVELOPMENT OFFICER I',
            'DEVELOPMENT MANAGEMENT OFFICER I II' => 'DEVELOPMENT MANAGEMENT OFFICER I',
        ] as $combinedTitle => $mappedTitle) {
            $maps['INTERMEDIATE'][$combinedTitle] = $codesByLevel['INTERMEDIATE'][$mappedTitle];
        }
    }

    $matchingLevels = [];
    foreach ($maps as $level => $positions) {
        if (isset($positions[$title])) $matchingLevels[$level] = $positions[$title];
    }
    if (!$matchingLevels) {
        $baseTitle = field_office_competency_normalize_title($positionTitle, true);
        $baseMatches = [];
        foreach ($maps as $level => $positions) {
            foreach ($positions as $mappedTitle => $codes) {
                if (field_office_competency_normalize_title($mappedTitle, true) === $baseTitle) {
                    $baseMatches[$level][$mappedTitle] = $codes;
                }
            }
        }
        foreach ($baseMatches as $level => $matches) {
            if (count($matches) === 1) $matchingLevels[$level] = reset($matches);
        }
    }
    if (!isset($matchingLevels[$level])) return null;
    $library = field_office_competency_library();
    $core = [
        'CC1 - DISCIPLINE' => [
            'BASIC' => 'Practices ethical and professional behaviors',
            'INTERMEDIATE' => 'Upholds the values of DENR in every action and decision',
            'ADVANCE' => 'Serves as a good role model on DENR values and ethics to staff/peers',
        ],
        'CC2 - EXCELLENCE' => [
            'BASIC' => "Complies with DENR's standards of service delivery",
            'INTERMEDIATE' => "Delivers and adds value to customers' expectations and requirements",
            'ADVANCE' => 'Anticipates, identifies and manages stakeholders\' standards and requirements towards excellent customer service through improving sense of responsibility and competence',
        ],
        'CC3 - NOBILITY' => [
            'BASIC' => 'Demonstrates virtue, goodness, honor, justness and decency at all times',
            'INTERMEDIATE' => 'Demonstrates virtue, goodness, honor, justness and decency even in difficult situations',
            'ADVANCE' => 'Influences others to observe virtue, goodness, honor, justness and decency in all situations',
        ],
        'CC4 - RESPONSIBILITY' => [
            'BASIC' => 'Observes the principle of transparency and accountability in the workplace with supervision and guidance',
            'INTERMEDIATE' => 'Practices the principle of transparency and accountability in the workplace',
            'ADVANCE' => 'Leads in the observance of the principle of transparency and accountability in the workplace',
        ],
        'CC5 - CARING FOR THE ENVIRONMENT AND NATURAL RESOURCES' => [
            'BASIC' => 'Has knowledge of concepts and application of preservation and conservation of the environment',
            'INTERMEDIATE' => 'Applies knowledge and understanding of the environment to further the aims of sustainability',
            'ADVANCE' => 'Serves as a good role model in conserving and preserving the environment to peers and staff',
        ],
    ];
    $organizational = [
        'OC1 - WRITING EFFECTIVELY' => [
            'BASIC' => 'Refers to and/or uses existing communication materials or templates to produce own written work',
            'INTERMEDIATE' => 'Edits existing or customizes available communication materials to produce an appropriate written work',
            'ADVANCE' => 'Produces written work from scratch with some guidance while complying to agreed or prescribed standards of communicating with the bureaucracy',
        ],
        'OC2 - SPEAKING EFFECTIVELY' => [
            'BASIC' => 'Effectively delivers messages that simply focus on data, facts or information and requires minimal preparation or can be supported by available communication materials. Focus of communication is to relay information and/or data',
            'INTERMEDIATE' => 'Effectively delivers messages that require some planning for the method used and the possible reception to the message (audience may be a controlled group, i.e., team/s, divisions). Focus of communication is to relay information and to build motivation',
            'ADVANCE' => 'Effectively delivers messages that require careful planning for the method used and the possible impact of the message (audience may be a large group, i.e., office, organization). Focus of communication is to relay information and to build motivation',
        ],
        'OC3 - TECHNOLOGY LITERACY AND MANAGING INFORMATION' => [
            'BASIC' => 'Applies basic technology (office software and equipment) in the performance of the assigned tasks. Collects, organizes and maintains data',
            'INTERMEDIATE' => 'Demonstrates the use of available equipment and software/s and application/s that can be utilized in the performance of the assigned tasks. Works with data to generate relevant information',
            'ADVANCE' => 'Analyzes appropriateness of office software and equipment in the performance of assigned tasks. Develops information assets to achieve organizational goals',
        ],
        'OC4 - PROJECT MANAGEMENT' => [
            'BASIC' => 'Assist in project management activities',
            'INTERMEDIATE' => 'Conducts project management activities',
            'ADVANCE' => 'Leads in project management activities',
        ],
        'OC5 - COMPLETED STAFF WORK (CSW)' => [
            'BASIC' => 'Practices basic knowledge of CSW to generate complete, accurate, relevant and timely output',
            'INTERMEDIATE' => 'Demonstrates advanced knowledge in CSW by applying appropriate tools and techniques',
            'ADVANCE' => 'Leads the practice of CSW in his/her office/unit',
        ],
    ];

    $makeRows = static function (array $items, string $level, string $prefix): array {
        $rows = [];
        $index = 1;
        foreach ($items as $title => $descriptions) {
            $rows[] = [
                'id' => strtolower($prefix . $index),
                'title' => $title,
                'desc' => $descriptions[$level],
            ];
            $index++;
        }
        return $rows;
    };
    $functionalRows = [];
    foreach ($matchingLevels[$level] as $code) {
        $competency = $library[$code] ?? null;
        $description = $competency === null ? '' : $competency[
            $level === 'BASIC' ? 1 : ($level === 'INTERMEDIATE' ? 2 : 3)
        ];
        if ($competency === null || $description === '') continue;
        $functionalRows[] = [
            'id' => strtolower($code),
            'title' => $code . ' - ' . $competency[0],
            'desc' => $description,
        ];
    }

    $displayLevel = $level === 'ADVANCE' ? 'Advance' : ucfirst(strtolower($level));
    return [
        'level' => $displayLevel,
        'core' => $makeRows($core, $level, 'cc'),
        'organizational' => $makeRows($organizational, $level, 'oc'),
        'functional' => $functionalRows,
    ];
}

function template_seed_apply_field_office_competencies(array $layout, array $position): array {
    $salaryGrade = (int)($position['salary_grade'] ?? 0);
    if (strtoupper((string)($position['office_scope'] ?? '')) !== 'FIELD_OFFICE') return $layout;
    $competencies = field_office_competency_resolve(
        (string)($position['title'] ?? ''),
        $salaryGrade,
        isset($position['competency_level']) ? (string)$position['competency_level'] : null
    );
    if ($competencies === null) return $layout;

    $layout['sections'] = [
        'core' => [
            'standard' => true,
            'label' => 'Core Competencies',
            'level' => $competencies['level'],
            'rows' => $competencies['core'],
        ],
        'org' => [
            'standard' => true,
            'label' => 'Organizational Competencies',
            'level' => $competencies['level'],
            'rows' => $competencies['organizational'],
        ],
        'func' => [
            'custom' => true,
            'label' => 'Functional Competencies',
            'level' => $competencies['level'],
            'rows' => $competencies['functional'],
        ],
    ];
    return $layout;
}

function template_seed_ro_advance_data(): array {
    static $data = null;
    if ($data !== null) return $data;

    $codeRange = static fn(string $prefix, int $first, int $last): array =>
        array_map(static fn(int $number): string => $prefix . $number, range($first, $last));
    $functional = [
        'RSCI1' => ['MEDIA RELATIONS MANAGEMENT', 'Leads in the conceptualization, design, and preparation of publications, editorial and writing materials, media relations materials and audio-visual media materials.'],
        'RSCI2' => ['DEVELOPMENT COMMUNICATION MANAGEMENT', 'Leads in the conduct of development communication activities'],
        'RSCI3' => ['EVENT MANAGEMENT', 'Leads in the conduct of event management activities.'],
        'RSCI4' => ['VISUAL COMMUNICATION (GRAPHIC DESIGN AND LAYOUT)', 'Leads the delivery visual communication works'],
        'RSCI5' => ['VIDEO PRODUCTION', 'Leads the delivery of video production works'],
        'RSCI6' => ['PHOTOJOURNALISM', 'Leads the delivery of photography works'],
        'RSCI7' => ['LIBRARY MANAGEMENT', 'Supervises the implementation of library management activities and programs'],
        'RP1' => ['PLANNING AND PROGRAMMING', "Handles the implementation of programming/reprogramming guidelines for DENR's short-, medium-, and long term plans preparation"],
        'RP2' => ['MONITORING AND EVALUATION', 'Leads the preparation of monitoring and evaluation reports of all DENR-RO programs, projects, and activities'],
        'RP3' => ['STATISTICAL ANALYSIS, DATA MANAGEMENT AND PRODUCTION OF KNOWLEDGE PRODUCTS', 'Leads the conduct of statistical analysis, data management and production of knowledge products'],
        'RIS1' => ['STATISTICAL ANALYSIS, CONVERSION OF STATISTICAL DATA TO SPATIAL DATA AND CONVERSION TO KNOWLEDGE PRODUCTS', 'Leads the analysis of spatial data, integration of statistical and spatial data and conversion of the same to knowledge product'],
        'RIS2' => ['SOFTWARE DEVELOPMENT', 'Leads and provides instructions to programmers and reviews results of program development'],
        'RIS3' => ['NETWORK INFRASTRUCTURE MANAGEMENT', 'Supervises the conduct of computer hardware/networking installation, troubleshooting and maintenance works'],
        'RIS4' => ['SYSTEMS ANALYSIS AND DESIGN', 'Leads the conduct of and reviews/ verifies results of system planning, analysis, design, implementation and maintenance'],
        'RIS5' => ['WEB DEVELOPMENT', 'Leads the conduct of web development, design, installation and maintenance activities'],
        'RIS6' => ['INFORMATION AND COMMUNICATION TECHNOLOGIES (ICT) RESOURCE MANAGEMENT', 'Leads in planning, design, installation and maintenance of ICT resources; and leads support to ICT research and development'],
        'RFM1' => ['GENERAL ACCOUNTING', 'Reviews schedules, plans, work procedures and organizes processes in gathering financial information needed for the completion of the reports'],
        'RFM2' => ['BUDGET PREPARATION', "Leads in the preparation/ consolidation of the budget of all the offices/divisions in DENR in accordance with existing DBM and agency rules and regulations"],
        'RFM3' => ['BUDGET ADMINISTRATION AND CONTROL', 'Leads in the conduct of budget administration and control activities'],
        'RFM4' => ['ORGANIZATION AND MANAGEMENT SYSTEMS IMPROVEMENT', 'Leads the conduct of organization and management systems improvement activities'],
        'RFM5' => ['CASH MANAGEMENT', 'Leads in the discharge of cash management activities'],
        'RAS1' => ['PROCUREMENT MANAGEMENT', 'Leads in the conduct of procurement activities'],
        'RAS2' => ['PROPERTY MANAGEMENT (PROPERTY INVENTORY AND DISPOSAL MANAGEMENT)', 'Conducts property inventory management and property disposal management activities'],
        'RAS3' => ['RECORDS MANAGEMENT', 'Leads the implementation of records management activities'],
        'RAS4' => ['COMPUTERIZED RECORDS MANAGEMENT', 'Leads the implementation of computerized records management activities'],
        'RAS5' => ['COURIER, POSTAL AND MESSENGERIAL SERVICES', 'Leads the conduct of courier, postal and messengerial services'],
        'RAS6' => ['CLERICAL/SECRETARIAL/EXECUTIVE ASSISTANCE SKILLS', 'Undertakes complex secretarial and executive assistant functions'],
        'RAS7' => ['BUILDING MAINTENANCE SYSTEM ADMINISTRATION', 'Monitors implementation of maintenance activities for buildings and other facilities'],
        'RAS8' => ['REPAIR AND FABRICATION', 'Organizes the simple and complex repair of buildings, equipment, electrical works, plumbing and airconditioning and fabrication of equipment, furniture and fixtures'],
        'RHR1' => ['RECRUITMENT, SELECTION AND PLACEMENT', 'Leads the recruitment, selection and placement processes in the DENR'],
        'RHR2' => ['COMPENSATION, BENEFITS AND WELFARE ADMINISTRATION', 'Leads the implementation of compensation, benefits and welfare administration'],
        'RHR3' => ['PROCESSING OF PERSONNEL ACTIONS', 'Leads in the processing of personnel actions'],
        'RHR4' => ['GRIEVANCE HANDLING', 'Leads the successful implementation of grievance handling guidelines and the administrative disciplinary process'],
        'RHR5' => ['PERFORMANCE MANAGEMENT', 'Leads the implementation of performance, incentives and awards policies'],
        'RHR6' => ['LEARNING NEEDS ASSESSMENT (LNA)', 'Supervises the conduct of LNA activities'],
        'RHR7' => ['PREPARATION OF LEARNING DESIGN', 'Leads the preparation of learning design'],
        'RHR8' => ['LEARNING EVENT MANAGEMENT', 'Leads in the conduct of learning program management activities'],
        'RHR9' => ['LEARNING EVENT FACILITATION', 'Facilitates symposium, conferences, focus group discussions and structured learning activities'],
        'RHR10' => ['NETWORK AND LINKAGING WITH HR PARTNERS', 'Leads in the conduct of networking and linkaging activities'],
        'RHR11' => ['MONITORING AND EVALUATION (M&E) OF LEARNING AND DEVELOPMENT (L&D) PROGRAMS', 'Leads the conduct of learning and development M&E activities'],
        'RHR12' => ['SCHOLARSHIP ADMINISTRATION', 'Leads the implementation of scholarship administration activities'],
        'RHR13' => ['HR PLANNING', 'Leads the conduct of HR planning activities'],
        'RHR14' => ['CAREER DEVELOPMENT', 'Leads the conduct of career development activities'],
        'RLA1' => ['SKILLS IN LEGAL RESEARCH', 'Sees through/leads the delivery of legal research activities'],
        'RLA2' => ['MANAGEMENT AND DISPOSITION OF ENR CASES AND OTHER LEGAL CONCERNS', 'Sees through/leads the management and disposition of ENR cases and other legal concerns'],
        'RLA3' => ['LITIGATION', 'Leads the handling of cases before the regular courts and quasi-judicial bodies'],
        'RLA4' => ['LEGAL COUNSELING AND ALTERNATE DISPUTE RESOLUTION (ADR)', 'Leads the legal counseling and ADR'],
        'RLA5' => ['INVESTIGATION AND DISPOSITION OF ADMINISTRATIVE COMPLAINTS', 'Leads the conduct of investigation and disposition of administrative complaints'],
        'RO1' => ['CONCEPT AND APPLICATION OF INTEGRATED ECOSYSTEMS MANAGEMENT (IEM)', 'Leads the application of IEM and other related approaches to NRM in basin, watersheds, sub-watershed, protected areas, ancestral domains, mineral reservations, islands, others'],
        'RO2' => ['IDENTIFICATION OF INTERVENTIONS AND INTEGRATING STRATEGIES ACROSS SECTORS (FORESTRY, COASTAL, AGRICULTURE, URBAN, AIR SPACE) AND ZONING FOR STRATEGIC MANAGEMENT', 'Leads the identification of interventions and integrating strategies across sectors'],
        'RO3' => ['CHARACTERIZATION OF ECOSYSTEM AND USE OF PLANNING TOOLS AND PROCEDURES', 'Leads the characterization of ecosystem and use of planning tools and procedures'],
        'RO4' => ['RESOURCE MANAGEMENT AND RESTORATION/ REHABILITATION OF DEGRADED ECOSYSTEMS', 'Leads the identification of appropriate resource management and restoration strategies of various ecosystems'],
        'RO5' => ['PREPARATION OF NATURAL RESOURCES MANAGEMENT (NRM) - RELATED PLANS (FLUP, CRMP, ISWMP, PAMP, IRBM, IWRM)', 'Leads the preparation of NRM- related Plans (FLUP, CRMP, ISWMP, PAMP, IRBM, IWRM) and integration to LGU Plans'],
        'RO6' => ['ENVIRONMENT AND NATURAL RESOURCE ACCOUNTING (ENRA)', 'Leads the implementation of Environment and Natural Resource Accounting activities'],
        'RO7' => ['STRATEGIES AND SCHEMES FOR FINANCING ENVIRONMENTAL PROJECTS', 'Leads the planning, initiating and operationalizing strategies and schemes for financing environmental projects'],
        'RO8' => ['RESULTS-BASED MONITORING AND EVALUATION SYSTEM (RBME) AND ENVIRONMENTAL AUDIT FOR DIFFERENT ENRM SITES', 'Leads the implementation of RBME activities and environmental audit for different ENRM sites'],
        'RO9' => ['ENVIRONMENTAL GOVERNANCE', 'Leads environmental governance activities that include transparency, accountability, participation, collaboration and functionality'],
        'RO10' => ['CLIMATE CHANGE AND ENVIRONMENTAL MANAGEMENT', 'Leads the application of mitigating measures and adaptive solutions to climate change'],
        'RO11' => ['INFORMATION, EDUCATION AND COMMUNICATION, SOCIAL MARKETING AND EXTENSION SUPPORT', 'Leads in social marketing and extension support activities to improve ENRM'],
        'RO12' => ['IMPACT ASSESSMENT ACROSS ECOSYSTEMS', 'Supervises the conduct of impact assessment across ecosystems in evaluating land and resource uses and EIA/IEE applications'],
        'RO13' => ['SOCIAL NEGOTIATION', 'Leads conduct of social negotiation activities'],
        'RO14' => ['ENR LAW ENFORCEMENT', 'Leads the implementation of ENR law enforcement activities'],
        'RO15' => ['GEOGRAPHIC INFORMATION SYSTEM (GIS)', 'Leads implementation of GIS techniques for resource mapping'],
        'RO16' => ['SURVEYING', 'Supervises land surveying activities'],
        'RO17' => ['SURVEY VERIFICATION', 'Supervises survey verification activities'],
        'RO18' => ['MAPPING', 'Leads land mapping activities'],
        'RO19' => ['LAND MANAGEMENT INFORMATION SYSTEM ADMINISTRATION', 'Leads implementation of LAMS resource mapping'],
        'RO20' => ['LAND RECORDS MANAGEMENT', 'Leads in land records management activities'],
        'RO21' => ['LAND DISPOSITION AND MANAGEMENT', 'Leads in the disposition of A&D land and management of other government lands'],
        'RO22' => ['FOREST, WATER & WILDLIFE RESOURCES REGULATION', 'Evaluates and reviews the forest, water and wildlife resource utilization and regulation activities'],
        'RO23' => ['TENURE AND RIGHTS ASSESSMENT', 'Leads the evaluation of effectiveness of tenure holders based on organizational, stewardship, protection and management capabilities'],
        'RO24' => ['TENURIAL INSTRUMENTS AND PERMITS FOR IMPROVED RESOURCE MANAGEMENT', 'Reviews and recommends the approval of the issuance of tenurial instruments and permits for improved resource management'],
    ];
    $foAdvance = field_office_competency_resolve('Accountant III', 18);
    if ($foAdvance === null) throw new RuntimeException('Unable to load shared Field Office Advance competencies.');
    $data = [
        'functional' => $functional,
        'core' => $foAdvance['core'],
        'org_a' => $foAdvance['organizational'],
        'org_b' => array_map(
            static fn(int $index, string $title, string $description): array => [
                'id' => 'oc' . $index,
                'title' => 'OC' . $index . ' - ' . $title,
                'desc' => $description,
            ],
            [1, 2, 3, 4, 5],
            ['WRITING EFFECTIVELY', 'SPEAKING EFFECTIVELY', 'TECHNOLOGY LITERACY AND MANAGING INFORMATION', 'PROJECT MANAGEMENT', 'COMPLETED STAFF WORK (CSW)'],
            [
                $foAdvance['organizational'][0]['desc'],
                'Effectively delivers messages that simply focus on data, facts or information and requires minimal preparation or can be supported by available communication materials. Focus of communication is to relay information and/or data',
                'Applies basic technology (office software and equipment) in the performance of the assigned tasks. Collects, organizes and maintains data',
                'Assist in project management activities',
                'Practices basic knowledge of CSW to generate complete, accurate, relevant and timely output',
            ]
        ),
        'leadership' => array_map(
            static fn(int $index, string $title, string $description): array => [
                'id' => 'lc' . $index,
                'title' => $title,
                'desc' => $description,
            ],
            [1, 2, 3, 4, 5],
            LEADERSHIP_ROW_TITLES,
            [
                "Develops office/ service's strategies and plans based on the DENR's mission/vision",
                'Influences and guides team in understanding, accepting and supporting any change efforts/ programs of the DENR',
                'Applies the basic principle of motivating and coaching people in the workplace',
                'Explains performance management principles',
                'Maintains existing local partnership and networks and capitalizes on these to deliver or enhance work outcomes',
            ]
        ),
        'positions' => [
            ['Senior Communications Development Officer', 'Office of the Regional Director', 'A', $codeRange('RSCI', 1, 7)],
            ['Planning Officer IV', 'Planning & Management Division', 'A', ['RP1', 'RP2', 'RIS1']],
            ['Planning Officer III', 'Planning & Management Division', 'A', ['RP1', 'RP2', 'RIS1']],
            ['Project Evaluation Officer III', 'Planning & Management Division', 'A', ['RP1', 'RP2', 'RIS1']],
            ['Information Systems Analyst III', 'Planning & Management Division', 'A', ['RP3', 'RIS2', 'RIS3', 'RIS4', 'RIS5', 'RIS6']],
            ['Supervising Administrative Officer', 'Finance Division', 'A', $codeRange('RFM', 1, 5)],
            ['Accountant III', 'Finance Division', 'A', $codeRange('RFM', 1, 5)],
            ['Administrative Officer V (Budget Officer III)', 'Finance Division', 'A', $codeRange('RFM', 1, 5), ['RFM1' => "Records, classifies and summarizes DENR's financial transactions"]],
            ['Supervising Administrative Officer', 'Administrative Division', 'B', array_merge($codeRange('RAS', 1, 8), $codeRange('RFM', 1, 5), $codeRange('RHR', 1, 14))],
            ['Administrative Officer V (HRMO III)', 'Personnel Section', 'B', $codeRange('RHR', 1, 5)],
            ['Administrative Officer V', 'Human Resource Development Section', 'B', $codeRange('RHR', 6, 14)],
            ['Administrative Officer V (Cashier III)', 'Cashiering Section', 'B', $codeRange('RFM', 1, 5)],
            ['Administrative Officer V', 'General Services Section', 'B', $codeRange('RAS', 3, 8)],
            ['Attorney IV', 'Legal Division', 'B', $codeRange('RLA', 1, 5)],
            ['Attorney III', 'Legal Division', 'B', $codeRange('RLA', 1, 5)],
            ['Attorney II', 'Legal Division', 'B', $codeRange('RLA', 1, 5)],
            ['Development Management Officer IV', 'Conservation and Development Division', 'B', $codeRange('RO', 1, 13)],
            ['Development Management Officer III', 'Conservation and Development Division', 'B', $codeRange('RO', 1, 13)],
            ['Engineer IV (Geodetic Engineer)', 'Surveys and Mapping Division', 'B', $codeRange('RO', 15, 20)],
            ['Engineer III (Geodetic Engineer)', 'Surveys and Mapping Division', 'B', $codeRange('RO', 15, 20)],
            ['Forester III', 'Surveys and Mapping Division', 'B', $codeRange('RO', 15, 20)],
            ['Development Management Officer IV', 'Licenses, Patents & Deeds Division', 'B', $codeRange('RO', 21, 24)],
            ['Development Management Officer III', 'Licenses, Patents & Deeds Division', 'B', $codeRange('RO', 21, 24)],
            ['Supervising Ecosystems Management Specialist', 'Enforcement Division', 'B', ['RO9', 'RO14']],
            ['Senior Ecosystems Management Specialist', 'Enforcement Division', 'B', ['RO9', 'RO14']],
        ],
    ];
    return $data;
}

function template_seed_ro_advance_variants(array $position): array {
    $salaryGrade = (int)($position['salary_grade'] ?? 0);
    if ($salaryGrade < 18 || $salaryGrade > 23
        || strtoupper((string)($position['office_scope'] ?? 'REGIONAL_OFFICE')) !== 'REGIONAL_OFFICE') {
        return [];
    }

    $normalizedTitle = field_office_competency_normalize_title((string)($position['title'] ?? ''));
    $matches = array_values(array_filter(
        template_seed_ro_advance_data()['positions'],
        static fn(array $entry): bool => field_office_competency_normalize_title($entry[0]) === $normalizedTitle
    ));
    if (!$matches) return [];

    $variants = [];
    foreach ($matches as $entry) {
        [$title, $division, $orgVariant, $codes] = $entry;
        $variant = $position;
        $variant['ro_advance'] = [
            'division' => $division,
            'organizational_variant' => $orgVariant,
            'codes' => $codes,
            'overrides' => $entry[4] ?? [],
        ];
        $variant['template_suffix'] = count($matches) > 1 ? ' [RO: ' . $division . ']' : '';
        $variants[] = $variant;
    }
    return $variants;
}

function template_seed_apply_ro_advance_competencies(array $layout, array $position): array {
    $variant = $position['ro_advance'] ?? null;
    $salaryGrade = (int)($position['salary_grade'] ?? 0);
    if (!is_array($variant) || $salaryGrade < 18 || $salaryGrade > 23
        || strtoupper((string)($position['office_scope'] ?? 'REGIONAL_OFFICE')) !== 'REGIONAL_OFFICE') {
        return $layout;
    }
    $data = template_seed_ro_advance_data();
    $functionalRows = [];
    foreach ($variant['codes'] as $code) {
        $competency = $data['functional'][$code] ?? null;
        if ($competency === null) {
            throw new RuntimeException('Missing RO Advance functional competency: ' . $code);
        }
        $functionalRows[] = [
            'id' => strtolower($code),
            'title' => $code . ' - ' . $competency[0],
            'desc' => $variant['overrides'][$code] ?? $competency[1],
        ];
    }
    $layout['sections'] = is_array($layout['sections'] ?? null) ? $layout['sections'] : [];
    foreach ([
        'core' => [
            'standard' => true,
            'label' => 'Core Competencies',
            'level' => 'Advance',
            'rows' => $data['core'],
        ],
        'org' => [
            'standard' => true,
            'label' => 'Organizational Competencies',
            'level' => 'Advance',
            'rows' => $variant['organizational_variant'] === 'A' ? $data['org_a'] : $data['org_b'],
        ],
        'lead' => [
            'standard' => true,
            'label' => 'Leadership Competencies',
            'level' => 'Advanced',
            'rows' => $data['leadership'],
        ],
        'func' => [
            'custom' => true,
            'label' => 'Functional Competencies',
            'level' => 'Advance',
            'rows' => $functionalRows,
        ],
    ] as $key => $sectionData) {
        $section = is_array($layout['sections'][$key] ?? null) ? $layout['sections'][$key] : [];
        $section = array_replace($section, $sectionData);
        unset($section[!empty($sectionData['custom']) ? 'standard' : 'custom']);
        $layout['sections'][$key] = $section;
    }
    return $layout;
}

function template_seed_generation_positions(array $positions): array {
    $generated = [];
    foreach ($positions as $position) {
        $variants = template_seed_ro_advance_variants($position);
        foreach ($variants ?: [$position] as $variant) $generated[] = $variant;
    }
    return $generated;
}

function template_seed_apply_basic_competencies(array $layout, array $position): array {
    $salaryGrade = (int)($position['salary_grade'] ?? 0);
    if (!in_array($salaryGrade, [6, 7, 8], true)
        || strtoupper((string)($position['office_scope'] ?? '')) !== 'FIELD_OFFICE') {
        return $layout;
    }
    $competencies = field_office_competency_resolve(
        (string)($position['title'] ?? ''),
        $salaryGrade
    );
    if ($competencies === null) return $layout;

    $layout['sections'] = is_array($layout['sections'] ?? null) ? $layout['sections'] : [];
    foreach ([
        'core' => ['Core Competencies', $competencies['core'], 'standard'],
        'org' => ['Organizational Competencies', $competencies['organizational'], 'standard'],
        'func' => ['Functional Competencies', $competencies['functional'], 'custom'],
    ] as $key => [$label, $rows, $sectionType]) {
        $section = is_array($layout['sections'][$key] ?? null) ? $layout['sections'][$key] : [];
        $section['label'] = $label;
        $section['level'] = 'Basic';
        $section['rows'] = $rows;
        unset($section[$sectionType === 'standard' ? 'custom' : 'standard']);
        $section[$sectionType] = true;
        $layout['sections'][$key] = $section;
    }
    return $layout;
}

function template_seed_apply_intermediate_competencies(array $layout, array $position): array {
    $salaryGrade = (int)($position['salary_grade'] ?? 0);
    if (!in_array($salaryGrade, [10, 11], true)
        || strtoupper((string)($position['office_scope'] ?? '')) !== 'FIELD_OFFICE') {
        return $layout;
    }
    $competencies = field_office_competency_resolve(
        (string)($position['title'] ?? ''),
        $salaryGrade
    );
    if ($competencies === null) return $layout;

    $layout['sections'] = is_array($layout['sections'] ?? null) ? $layout['sections'] : [];
    foreach ([
        'core' => ['Core Competencies', $competencies['core'], 'standard'],
        'org' => ['Organizational Competencies', $competencies['organizational'], 'standard'],
        'func' => ['Functional Competencies', $competencies['functional'], 'custom'],
    ] as $key => [$label, $rows, $sectionType]) {
        $section = is_array($layout['sections'][$key] ?? null) ? $layout['sections'][$key] : [];
        $section['label'] = $label;
        $section['level'] = 'Intermediate';
        $section['rows'] = $rows;
        unset($section[$sectionType === 'standard' ? 'custom' : 'standard']);
        $section[$sectionType] = true;
        $layout['sections'][$key] = $section;
    }
    return $layout;
}

function template_seed_apply_advance_competencies(array $layout, array $position): array {
    $salaryGrade = (int)($position['salary_grade'] ?? 0);
    if ($salaryGrade < 18 || $salaryGrade > 22
        || strtoupper((string)($position['office_scope'] ?? '')) !== 'FIELD_OFFICE') {
        return $layout;
    }
    $competencies = field_office_competency_resolve(
        (string)($position['title'] ?? ''),
        $salaryGrade
    );
    if ($competencies === null) return $layout;

    $layout['sections'] = is_array($layout['sections'] ?? null) ? $layout['sections'] : [];
    foreach ([
        'core' => ['Core Competencies', $competencies['core'], 'standard'],
        'org' => ['Organizational Competencies', $competencies['organizational'], 'standard'],
        'func' => ['Functional Competencies', $competencies['functional'], 'custom'],
    ] as $key => [$label, $rows, $sectionType]) {
        $section = is_array($layout['sections'][$key] ?? null) ? $layout['sections'][$key] : [];
        $section['label'] = $label;
        $section['level'] = 'Advance';
        $section['rows'] = $rows;
        unset($section[$sectionType === 'standard' ? 'custom' : 'standard']);
        $section[$sectionType] = true;
        $layout['sections'][$key] = $section;
    }
    return $layout;
}
require_admin(true);

require_once __DIR__ . '/sheets_schema.php';

$message = '';
$messageType = '';
if (($seedMessage = flash_get('success')) !== null) {
    $message = $seedMessage;
    $messageType = 'success';
} elseif (($seedMessage = flash_get('error')) !== null) {
    $message = $seedMessage;
    $messageType = 'error';
}
$generationSummary = null;
$functionalSummary = null;
$functionalErrors = [];
$positions = [];
$fieldOfficePositions = [];
$generatedTemplates = [];
$templateSeedSourceSheetLabels = [
    'competency_Basic_FO' => 'Basic',
    'competency_Intermediate_FO' => 'Intermediate',
    'competency_advance_FO' => 'Advanced',
];
$csrfToken = csrf_token();

function template_seed_field_office_positions(): array {
    $positions = [];
    foreach (field_positions_by_sheet() as $mapping) {
        for ($grade = (int)$mapping['min_salary_grade']; $grade <= (int)$mapping['max_salary_grade']; $grade++) {
            $positions[] = [
                'title' => $mapping['title'],
                'salary_grade' => $grade,
                'leadership_level' => null,
                'office_scope' => 'FIELD_OFFICE',
                'sheet_name' => $mapping['sheet_name'],
                'competency_level' => $mapping['competency_level'],
            ];
        }
    }
    return $positions;
}

function template_seed_selected_field_sheets($submitted): array {
    if (!is_array($submitted)) {
        throw new InvalidArgumentException('Choose valid Field Office sheets.');
    }
    $availableSheets = array_keys(FIELD_POSITION_SHEETS);
    $selectedSheets = [];
    foreach ($submitted as $sheetName) {
        if (!is_string($sheetName) || !in_array($sheetName, $availableSheets, true)) {
            throw new InvalidArgumentException('Choose valid Field Office sheets.');
        }
        if (!in_array($sheetName, $selectedSheets, true)) $selectedSheets[] = $sheetName;
    }
    return $selectedSheets;
}

function template_seed_find_field_office_template(mysqli $conn, int $ownerId, string $title, string $sheetName): ?array {
    $legacyTitle = $title . ' [Field Office]';
    $stmt = $conn->prepare('SELECT id, title, layout_json FROM competency_templates
        WHERE owner_id = ? AND title IN (?, ?) ORDER BY id ASC');
    $stmt->bind_param('iss', $ownerId, $title, $legacyTitle);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $legacyTemplate = null;
    foreach ($rows as $row) {
        $layout = json_decode((string)$row['layout_json'], true);
        if (($layout['source_sheet'] ?? '') === $sheetName) return $row;
        if ($row['title'] === $legacyTitle && empty($layout['source_sheet'])) $legacyTemplate = $row;
    }
    return $legacyTemplate;
}

function template_seed_positions(mysqli $conn): array {
    $tableName = 'positions';
    $check = $conn->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    $check->bind_param('s', $tableName);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();
    if (!$exists) {
        throw new RuntimeException('Required positions table is missing. Create and seed it before generating templates.');
    }

    [$seedRows, $errors] = parse_positions_csv(POSITIONS_SEED_CSV);
    if ($errors) throw new RuntimeException('Built-in position seed data is invalid.');
    return array_map(static fn(array $position): array => [
        'title' => $position['title'],
        'salary_grade' => $position['salary_grade'],
        'leadership_level' => $position['leadership_level'],
    ], $seedRows);
}

function template_seed_generated_templates(mysqli $conn): array {
    $stmt = $conn->prepare("SELECT id, title, min_salary_grade, layout_json
        FROM competency_templates
        WHERE title LIKE 'SG % - %' AND min_salary_grade = max_salary_grade
        ORDER BY min_salary_grade DESC, title ASC");
    $stmt->execute();
    $templates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($templates as &$template) {
        $layout = json_decode((string)($template['layout_json'] ?? ''), true);
        $template['office_type'] = template_seed_template_office_type($template);
        $template['source_sheet'] = trim((string)($layout['source_sheet'] ?? ''));
    }
    unset($template);
    return $templates;
}

function template_seed_template_office_type(array $template): string {
    $layout = json_decode((string)($template['layout_json'] ?? ''), true);
    $officeType = office_type_for_template_layout(
        is_array($layout) ? $layout : null,
        (string)($template['title'] ?? '')
    );
    if ($officeType === 'RO' && stripos((string)($template['title'] ?? ''), '[Field Office]') !== false) {
        return 'FO';
    }
    return $officeType;
}

function template_seed_region_template_titles(array $position): array {
    $variants = template_seed_ro_advance_variants($position);
    if (!$variants) $variants = [$position];
    return array_map(
        static fn(array $variant): string => 'SG ' . (int)$variant['salary_grade']
            . ' - ' . (string)$variant['title']
            . (string)($variant['template_suffix'] ?? ''),
        $variants
    );
}

function template_seed_copy_rows(array $section): array {
    $rows = [];
    foreach (($section['rows'] ?? []) as $row) {
        if (!is_array($row)) continue;
        $rows[] = [
            'id' => (string)($row['id'] ?? ''),
            'title' => (string)($row['title'] ?? ''),
            'desc' => (string)($row['desc'] ?? ''),
        ];
    }
    return $rows;
}

function template_seed_basic_position_codes(): array {
    return [
        'ADMINISTRATIVE ASSISTANT I' => ['RP1','RP2','RP3','RP4','RIS1','RIS2','RIS3','RIS4','RIS5','RIS6','RAS3','RAS4','RAS5','RAS6','RAS7','RAS8'],
        'ADMINISTRATIVE ASSISTANT I COMPUTER OPERATOR I' => ['RFM1','RFM2','RFM3','RFM4','RFM5','RAS1','RAS2','RLS1','RLS2','RLS3','RLS4'],
        'ADMINISTRATIVE ASSISTANT II' => ['RAS1','RAS2','RAS3','RAS4','RAS5','RAS6','RAS7','RAS8','RFM1','RFM2','RFM3','RFM4','RFM5','RHR6','RHR7','RHR8','RHR9','RHR10','RHR11','RHR12','RHR13','RHR14'],
        'ADMINISTRATIVE ASSISTANT II BUDGETING ASST' => ['RFM1','RFM2','RFM3','RFM4','RFM5'],
        'ADMINISTRATIVE ASSISTANT III' => ['RAS5','RAS6','RFM1','RFM2','RFM3','RFM4','RFM5','RHR6','RHR7','RHR8','RHR9','RHR10','RHR11','RHR12','RHR13','RHR14'],
        'ADMINISTRATIVE ASSISTANT III COMPUTER OPERATOR II' => ['RP1','RP2','RP3','RP4'],
        'ADMINISTRATIVE ASSISTANT III SENIOR BOOKKEEPER' => ['RFM1','RFM2','RFM3','RFM4','RFM5'],
        'CREDIT OFFICER I' => ['RFM1','RFM2','RFM3','RFM4','RFM5'],
        'ADMINISTRATIVE AIDE VI' => ['RLS1','RLS2','RLS3','RLS4','RO1','RO2','RO3','RO5','RO9','RO10','RO15','RO16','RO17','RO18','RO19','RO20','RO21','RO22','RO23','RO24','RO14'],
        'ZOOLOGY TECHNICIAN' => ['RO1','RO3','RO4','RO5','RO9','RO10'],
        'DRAFTSMAN I' => ['RO1','RO4','RO5','RO9','RO10'],
        'DRAFTSMAN II' => ['RO21','RO22','RO23','RO24'],
        'CARTOGRAPHER I' => ['RO15','RO16','RO17','RO18','RO19','RO20','RO21','RO22','RO23','RO24'],
        'ENGINEERING AIDE' => ['RO15','RO16','RO17','RO18','RO19','RO20'],
        'MATHEMATICIAN AIDE I' => ['RO15','RO16','RO17','RO18','RO19','RO20'],
        'MATHEMATICIAN AIDE II' => ['RO15','RO16','RO17','RO18','RO19','RO20'],
        'TRACER' => ['RO15','RO16','RO17','RO18','RO19','RO20','RO21','RO22','RO23','RO24'],
    ];
}

function template_seed_basic_position_codes_for_title(string $positionTitle): ?array {
    $normalizedTitle = strtoupper(trim((string)preg_replace('/[^A-Z0-9]+/i', ' ', $positionTitle)));
    $baseTitle = strtoupper(trim((string)preg_replace('/[^A-Z0-9]+/i', ' ', preg_replace('/\([^)]*\)/', '', $positionTitle))));
    $positionCodes = template_seed_basic_position_codes();
    return $positionCodes[$normalizedTitle] ?? $positionCodes[$baseTitle] ?? null;
}

function template_seed_basic_organizational_rows(): array {
    return [
        ['id' => 'oc1', 'title' => 'OC1 - WRITING EFFECTIVELY', 'desc' => 'Refers to and/or uses existing communication materials or templates to produce own written work'],
        ['id' => 'oc2', 'title' => 'OC2 - SPEAKING EFFECTIVELY', 'desc' => 'Effectively delivers messages that simply focus on data, facts or information and requires minimal preparation or can be supported by available communication materials. Focus of communication is to relay information and/or data'],
        ['id' => 'oc3', 'title' => 'OC3 - TECHNOLOGY LITERACY AND MANAGING INFORMATION', 'desc' => 'Applies basic technology (office software and equipment) in the performance of the assigned tasks. Collects, organizes and maintains data'],
        ['id' => 'oc4', 'title' => 'OC4 - PROJECT MANAGEMENT', 'desc' => 'Assist in project management activities'],
        ['id' => 'oc5', 'title' => 'OC5 - COMPLETED STAFF WORK (CSW)', 'desc' => 'Practices basic knowledge of CSW to generate complete, accurate, relevant and timely output'],
    ];
}

function template_seed_basic_core_rows(): array {
    return [
        ['id' => 'cc1', 'title' => 'CC1 - DISCIPLINE', 'desc' => 'Practices ethical and professional behaviors'],
        ['id' => 'cc2', 'title' => 'CC2 - EXCELLENCE', 'desc' => "Complies with DENR's standards of service delivery"],
        ['id' => 'cc3', 'title' => 'CC3 - NOBILITY', 'desc' => 'Demonstrates virtue, goodness, honor, justness and decency at all times'],
        ['id' => 'cc4', 'title' => 'CC4 - RESPONSIBILITY', 'desc' => 'Observes the principle of transparency and accountability in the workplace with supervision and guidance'],
        ['id' => 'cc5', 'title' => 'CC5 - CARING FOR THE ENVIRONMENT AND NATURAL RESOURCES', 'desc' => 'Has knowledge of concepts and application of preservation and conservation of the environment'],
    ];
}

function template_seed_apply_basic_rows(array $layout, array $position): array {
    if ((int)($position['salary_grade'] ?? 0) > BASIC_MAX_SG || !is_array($layout['sections'] ?? null)) return $layout;
    if (is_array($layout['sections']['core'] ?? null)) {
        $layout['sections']['core']['rows'] = template_seed_basic_core_rows();
    }
    if (is_array($layout['sections']['org'] ?? null)) {
        $layout['sections']['org']['rows'] = template_seed_basic_organizational_rows();
    }
    $positionTitle = (string)($position['title'] ?? '');
    if (template_seed_basic_position_codes_for_title($positionTitle) !== null
        && is_array($layout['sections']['func'] ?? null)) {
        $layout['sections']['func']['rows'] = template_seed_functional_rows($positionTitle);
    }
    return $layout;
}

function template_seed_functional_rows(string $positionTitle): array {
    $functionalSets = [
        'ras' => [
            ['RAS1', 'PROCUREMENT MANAGEMENT', 'Assists in the conduct of procurement activities'],
            ['RAS2', 'PROPERTY MANAGEMENT (PROPERTY INVENTORY AND DISPOSAL MANAGEMENT)', 'Assists in the conduct of property management activities'],
            ['RAS3', 'RECORDS MANAGEMENT', 'Assists in records management activities'],
            ['RAS4', 'COMPUTERIZED RECORDS MANAGEMENT', 'Assists in computerized records management activities'],
            ['RAS5', 'COURIER, POSTAL AND MESSENGERIAL SERVICES', 'Performs routine courier, postal and messengerial services'],
            ['RAS6', 'CLERICAL/SECRETARIAL/EXECUTIVE ASSISTANCE SKILLS', 'Undertakes routine clerical and secretarial functions'],
            ['RAS7', 'BUILDING MAINTENANCE SYSTEM ADMINISTRATION', 'Performs basic maintenance activities of buildings and other facilities'],
            ['RAS8', 'REPAIR AND FABRICATION', 'Performs minor repairs of equipment, electrical works, plumbing and airconditioning and simple fabrication of equipment, furniture and fixtures'],
        ],
        'rfm' => [
            ['RFM1', 'GENERAL ACCOUNTING', "Records, classifies and summarizes DENR's financial transactions"],
            ['RFM2', 'BUDGET PREPARATION', "Assists in budget preparation activities to determine the DENR's financial requirement"],
            ['RFM3', 'BUDGET ADMINISTRATION AND CONTROL', 'Assists in the preparation of budget allocation of departments/divisions'],
            ['RFM4', 'ORGANIZATION AND MANAGEMENT SYSTEMS IMPROVEMENT', 'Assists in the conduct of organization and management systems improvement activities'],
            ['RFM5', 'CASH MANAGEMENT', 'Assists in the discharge of cash management activities'],
        ],
        'planning' => [
            ['RP1', 'PLANNING AND PROGRAMMING', 'Assists in the conduct of planning and programming activities'],
            ['RP2', 'MONITORING AND EVALUATION', 'Assists in the monitoring and evaluation of DENR-RO programs and projects'],
            ['RP3', 'STATISTICAL ANALYSIS, DATA MANAGEMENT AND PRODUCTION OF KNOWLEDGE PRODUCTS', 'Assists in the conduct of statistical analysis, data management and production of knowledge products'],
            ['RP4', 'ORGANIZATION AND MANAGEMENT SYSTEMS IMPROVEMENT', 'Assists in the conduct of organization and management systems improvement activities'],
        ],
        'information_systems' => [
            ['RIS1', 'STATISTICAL ANALYSIS, CONVERSION OF STATISTICAL DATA TO SPATIAL DATA AND CONVERSION TO KNOWLEDGE PRODUCTS', 'Assists in the conduct of data management, conversion to spatial data, spatial analysis, and production of knowledge product'],
            ['RIS2', 'SOFTWARE DEVELOPMENT', 'Develops simple software programs'],
            ['RIS3', 'NETWORK INFRASTRUCTURE MANAGEMENT', 'Undertakes basic network infrastructure troubleshooting and maintenance'],
            ['RIS4', 'SYSTEMS ANALYSIS AND DESIGN', 'Provides support to/ assists in system analysis, design, planning, implementation, and maintenance activities'],
            ['RIS5', 'WEB DEVELOPMENT', 'Provides support to/ assists in web development, design, installation and maintenance activities'],
            ['RIS6', 'INFORMATION AND COMMUNICATION TECHNOLOGIES (ICT) RESOURCE MANAGEMENT', 'Performs basic computer HW, SW and NW configuration and maintenance'],
        ],
        'hr' => [
            ['RHR6', 'LEARNING NEEDS ASSESSMENT (LNA)', 'Assists in the conduct of LNA activities'],
            ['RHR7', 'PREPARATION OF LEARNING DESIGN', 'Assists in the preparation of learning design'],
            ['RHR8', 'LEARNING EVENT MANAGEMENT', 'Assists in the conduct of learning program management activities'],
            ['RHR9', 'LEARNING EVENT FACILITATION', 'Assists in facilitating learning events'],
            ['RHR10', 'NETWORK AND LINKAGING WITH HR PARTNERS', 'Communicates and coordinates with stakeholders/partners'],
            ['RHR11', 'MONITORING AND EVALUATION (M&E) OF LEARNING AND DEVELOPMENT (L&D) PROGRAMS', 'Assists conduct of learning and development M&E activities'],
            ['RHR12', 'SCHOLARSHIP ADMINISTRATION', 'Assists in the implementation of scholarship administration activities'],
            ['RHR13', 'HR PLANNING', 'Assists in HR planning activities'],
            ['RHR14', 'CAREER DEVELOPMENT', 'Assists in career development activities'],
        ],
        'legal' => [
            ['RLS1', 'LEGAL NOTE TAKING', 'Acts as support staff in the conduct of legal proceedings'],
            ['RLS2', 'LEGAL RECORDS MANAGEMENT', 'Assists in records management activities'],
            ['RLS3', 'COMPUTERIZED RECORDS MANAGEMENT', 'Assists in computerized records management activities'],
            ['RLS4', 'CLERICAL/SECRETARIAL/EXECUTIVE ASSISTANCE SKILLS', 'Assists in discharging complete staff work'],
        ],
        'conservation' => [
            ['RO1', 'CONCEPT AND APPLICATION OF INTEGRATED ECOSYSTEMS MANAGEMENT (IEM)', 'Assists in the application of IEM and other related approaches to NRM'],
            ['RO2', 'IDENTIFICATION OF INTERVENTIONS AND INTEGRATING STRATEGIES ACROSS SECTORS (FORESTRY, COASTAL, AGRICULTURE, URBAN, AIR SPACE) AND ZONING FOR STRATEGIC MANAGEMENT', 'Assists in the identification of interventions and integrating strategies across sectors'],
            ['RO3', 'CHARACTERIZATION OF ECOSYSTEM AND USE OF PLANNING TOOLS AND PROCEDURES', 'Assists in the characterization of ecosystem and use of planning tools and procedures'],
            ['RO4', 'RESOURCE MANAGEMENT AND RESTORATION/ REHABILITATION OF DEGRADED ECOSYSTEMS', 'Assists in identifying appropriate resource management and restoration strategies of various ecosystems'],
            ['RO5', 'PREPARATION OF NATURAL RESOURCES MANAGEMENT (NRM) - RELATED PLANS (FLUP, CRMP, ISWMP, PAMP, IRBM, IWRM)', 'Assists in the preparation of NRM- related Plans (FLUP, CRMP, ISWMP, PAMP, IRBM, IWRM) and integration to LGU Plans'],
            ['RO9', 'ENVIRONMENTAL GOVERNANCE', 'Assists in environmental governance activities that include transparency, accountability, participation, collaboration and functionality'],
            ['RO10', 'CLIMATE CHANGE AND ENVIRONMENTAL MANAGEMENT', 'Assists in applying mitigating measures and adaptive solutions to climate change'],
        ],
        'survey' => [
            ['RO15', 'GEOGRAPHIC INFORMATION SYSTEM (GIS)', 'Knows basic knowledge in GIS and other related software'],
            ['RO16', 'SURVEYING', 'Assists in land surveying activities'],
            ['RO17', 'SURVEY VERIFICATION', 'Assists in survey verification activities'],
            ['RO18', 'MAPPING', 'Assists in land mapping activities'],
            ['RO19', 'LAND MANAGEMENT INFORMATION SYSTEM ADMINISTRATION', 'Has knowledge in LAMS and other related software'],
            ['RO20', 'LAND RECORDS MANAGEMENT', 'Assists in land records management activities'],
        ],
        'land_management' => [
            ['RO21', 'LAND DISPOSITION AND MANAGEMENT', 'Assists in the disposition of A&D land and management of other government lands'],
            ['RO22', 'FOREST, WATER & WILDLIFE RESOURCES REGULATION', 'Assists in the forest, water and wildlife resource utilization and regulation activities'],
            ['RO23', 'TENURE AND RIGHTS ASSESSMENT', 'Assists in the evaluation of effectiveness of tenure holders based on organizational, stewardship, protection and management capabilities'],
            ['RO24', 'TENURIAL INSTRUMENTS AND PERMITS FOR IMPROVED RESOURCE MANAGEMENT', 'Assists in the preparation of tenurial instruments and permits for improved resource management'],
        ],
        'enforcement' => [
            ['RO14', 'ENR LAW ENFORCEMENT', 'Assists in ENR law enforcement activities'],
        ],
    ];

    $positionSets = [
        'ADMINISTRATIVE ASSISTANT I' => ['planning', 'information_systems', 'rfm', 'hr', 'ras', 'legal', 'survey', 'land_management'],
        'ADMINISTRATIVE ASSISTANT II' => ['ras', 'rfm', 'hr'],
        'ADMINISTRATIVE ASSISTANT III' => ['ras', 'planning', 'information_systems', 'rfm'],
        'ADMINISTRATIVE ASSISTANT I COMPUTER OPERATOR I' => ['rfm', 'legal'],
        'ADMINISTRATIVE ASSISTANT II BUDGETING ASST' => ['rfm'],
        'ADMINISTRATIVE ASSISTANT II SENIOR BOOKKEEPER' => ['rfm'],
        'ADMINISTRATIVE ASSISTANT II BOOKKEEPER' => ['rfm'],
        'ADMINISTRATIVE ASSISTANT III COMPUTER OPERATOR II' => ['planning', 'information_systems'],
        'ADMINISTRATIVE ASSISTANT III SENIOR BOOKKEEPER' => ['rfm'],
        'ADMINISTRATIVE ASSISTANT II COMPUTER OPERATOR I' => ['ras', 'rfm'],
        'ADMINISTRATIVE ASSISTANT I COMPUTER OPERATOR III' => ['planning', 'information_systems'],
        'ADMINISTRATIVE AIDE VI' => ['ras', 'legal', 'conservation', 'survey', 'land_management', 'enforcement'],
        'CREDIT OFFICER I' => ['rfm'],
        'ZOOLOGY TECHNICIAN' => ['conservation'],
        'DRAFTSMAN I' => ['conservation'],
        'DRAFTSMAN II' => ['land_management'],
        'CARTOGRAPHER I' => ['survey', 'land_management'],
        'ENGINEERING AIDE' => ['survey'],
        'MATHEMATICIAN AIDE I' => ['survey'],
        'MATHEMATICIAN AIDE II' => ['survey'],
        'TRACER' => ['survey', 'land_management'],
    ];

    $normalizedTitle = strtoupper(trim((string)preg_replace('/[^A-Z0-9]+/i', ' ', $positionTitle)));
    $baseTitle = strtoupper(trim((string)preg_replace('/[^A-Z0-9]+/i', ' ', preg_replace('/\([^)]*\)/', '', $positionTitle))));
    $basicCodes = template_seed_basic_position_codes_for_title($positionTitle);
    if ($basicCodes !== null) {
        $rowsByCode = [];
        foreach ($functionalSets as $functionalSet) {
            foreach ($functionalSet as [$code, $title, $description]) {
                $rowsByCode[$code] = [$title, $description];
            }
        }
        if ($normalizedTitle === 'CREDIT OFFICER I' || $baseTitle === 'CREDIT OFFICER I') {
            $rowsByCode['RFM5'][1] = 'Performs cash management activities';
        }
        $rows = [];
        foreach ($basicCodes as $code) {
            if (!isset($rowsByCode[$code])) continue;
            [$title, $description] = $rowsByCode[$code];
            $rows[] = ['id' => 'f' . (count($rows) + 1), 'title' => $code . ' - ' . $title, 'desc' => $description];
        }
        return $rows;
    }

    $setNames = $positionSets[$normalizedTitle] ?? null;
    if ($setNames === null) {
        $setNames = $positionSets[$baseTitle] ?? null;
    }
    if ($setNames === null) return [];

    $rows = [];
    $seen = [];
    foreach ($setNames as $setName) {
        foreach ($functionalSets[$setName] as [$code, $title, $description]) {
            if (isset($seen[$code])) continue;
            $seen[$code] = true;
            $rows[] = [
                'id' => 'f' . (count($rows) + 1),
                'title' => $code . ' - ' . $title,
                'desc' => $description,
            ];
        }
    }
    return $rows;
}

function template_seed_leadership_section(array $position): ?array {
    $salaryGrade = (int)($position['salary_grade'] ?? 0);
    $leadershipLevel = strtoupper(trim((string)($position['leadership_level'] ?? '')));
    if ($salaryGrade <= BASIC_MAX_SG || !in_array($leadershipLevel, ['B', 'I'], true)) {
        return null;
    }

    return [
        'standard' => true,
        'label' => 'Leadership Competencies',
        'level' => $leadershipLevel === 'B' ? 'Basic' : 'Advanced',
        'rows' => leadership_rows_for_level($leadershipLevel),
    ];
}

function template_seed_apply_leadership_section(array $layout, array $position): array {
    if (!is_array($layout['sections'] ?? null)) return $layout;

    $section = template_seed_leadership_section($position);
    $existing = $layout['sections']['lead'] ?? null;
    if ($section !== null) {
        $existingRows = is_array($existing['rows'] ?? null) ? $existing['rows'] : [];
        $standardIds = ['lc1', 'lc2', 'lc3', 'lc4', 'lc5'];
        $isGeneratedLeadership = !is_array($existing)
            || !empty($existing['standard'])
            || (empty($existing['custom']) && array_column($existingRows, 'id') === $standardIds);
        if ($isGeneratedLeadership) $layout['sections']['lead'] = $section;
    } elseif (is_array($existing) && !empty($existing['standard'])) {
        unset($layout['sections']['lead']);
    }

    return $layout;
}

function template_seed_layout(array $position, array $defaultLayout, ?array $existingLayout = null): array {
    $sg = (int)$position['salary_grade'];
    $isBasicGrade = $sg <= BASIC_MAX_SG;
    $level = template_level_for_salary_grade($sg);
    $coreRows = template_seed_copy_rows($defaultLayout['sections']['core']);
    $orgRows = template_seed_copy_rows($defaultLayout['sections']['org']);
    if ($isBasicGrade) {
        $coreRows = template_seed_basic_core_rows();
        $orgRows = template_seed_basic_organizational_rows();
    }

    $sections = [
        'core' => [
            'standard' => true,
            'label' => 'Core Competencies',
            'level' => $level,
            'rows' => $coreRows,
        ],
        'org' => [
            'standard' => true,
            'label' => 'Organizational Competencies',
            'level' => $level,
            'rows' => $orgRows,
        ],
    ];

    $leadershipSection = template_seed_leadership_section($position);
    if ($leadershipSection !== null) $sections['lead'] = $leadershipSection;

    $functionalRows = template_seed_copy_rows($defaultLayout['sections']['func']);
    if ($isBasicGrade) {
        $title = (string)$position['title'];
        $isExplicitPosition = template_seed_basic_position_codes_for_title($title) !== null;
        $existingRows = $existingLayout['sections']['func']['rows'] ?? null;
        if ($isExplicitPosition) {
            $functionalRows = template_seed_functional_rows($title);
        } elseif (is_array($existingRows) && count($existingRows) > 0) {
            $functionalRows = template_seed_copy_rows(['rows' => $existingRows]);
        } else {
            $mappedRows = template_seed_functional_rows($title);
            if ($mappedRows) $functionalRows = $mappedRows;
        }
    }

    $sections['func'] = [
        'custom' => true,
        'label' => 'Functional Competencies',
        'level' => $level,
        'rows' => $functionalRows,
    ];

    $layout = ['sections' => $sections];
    if (strtoupper((string)($position['office_scope'] ?? '')) === 'FIELD_OFFICE'
        && field_office_competency_resolve(
            (string)($position['title'] ?? ''),
            $sg,
            isset($position['competency_level']) ? (string)$position['competency_level'] : null
        ) !== null
        && is_array($existingLayout)) {
        $layout = $existingLayout;
    }
    $isFieldOffice = strtoupper((string)($position['office_scope'] ?? '')) === 'FIELD_OFFICE';
    $layout['office_type'] = $isFieldOffice ? 'FO' : 'RO';
    if ($isFieldOffice) {
        $layout['source_sheet'] = (string)($position['sheet_name'] ?? '');
    }

    if (is_array($position['ro_advance'] ?? null)) {
        $layout = template_seed_apply_ro_advance_competencies(
            is_array($existingLayout) ? $existingLayout : $layout,
            $position
        );
        $layout['office_type'] = 'RO';
        return $layout;
    }

    $layout = template_seed_apply_advance_competencies(
        template_seed_apply_intermediate_competencies(
            template_seed_apply_basic_competencies($layout, $position),
            $position
        ),
        $position
    );
    if ($isFieldOffice) {
        $layout = template_seed_apply_field_office_competencies($layout, $position);
    }
    return $layout;
}

function template_seed_parse_functional_rows(string $text): array {
    $rows = [];
    $errors = [];
    foreach (preg_split('/\r\n|\n|\r/', $text) as $index => $line) {
        $lineNumber = $index + 1;
        if (trim($line) === '') continue;
        if (str_contains($line, "\t")) {
            $fields = explode("\t", $line, 2);
        } else {
            $fields = str_getcsv($line);
            if (count($fields) > 2) {
                $fields = [$fields[0], implode(',', array_slice($fields, 1))];
            }
        }
        if (count($fields) !== 2) {
            $errors[] = ['line' => $lineNumber, 'message' => 'Expected a title and description separated by a tab or comma.'];
            continue;
        }
        $title = trim((string)$fields[0]);
        $description = trim((string)$fields[1]);
        if ($title === '' || $description === '' || mb_strlen($title) > 255 || mb_strlen($description) > 2000) {
            $errors[] = ['line' => $lineNumber, 'message' => 'Title and description are required (maximum 255 and 2,000 characters).'];
            continue;
        }
        $rows[] = ['title' => $title, 'desc' => $description];
    }
    return [$rows, $errors];
}

try {
    $positions = template_seed_positions($conn);
    $fieldOfficePositions = template_seed_field_office_positions();
    $defaultLayout = default_sheets_layout();

    $generatedTemplates = template_seed_generated_templates($conn);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!verify_csrf(isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : null)) {
            http_response_code(403);
            $message = 'Your session expired. Please reload the page and try again.';
            $messageType = 'error';
        } else {
            $action = (string)($_POST['action'] ?? '');
            if ($action === 'seed_positions') {
                if ($positions) {
                    http_response_code(409);
                    $message = 'Positions already exist; position seeding was not run.';
                    $messageType = 'error';
                } else {
                    $resetStmt = $conn->prepare('UPDATE positions_seed_state_v2 SET seed_version = 0 WHERE id = 1');
                    $resetStmt->execute();
                    $resetStmt->close();
                    ensure_positions_schema($conn);
                    $positions = template_seed_positions($conn);
                    if (!$positions) {
                        throw new RuntimeException('Position seeding completed but no Regional Office positions were found.');
                    }
                    flash_set('success', count($positions) . ' Regional Office positions are now available.');
                    header('Location: template_seed.php');
                    exit;
                }
            } elseif ($action === 'seed_field_positions') {
                ensure_positions_schema($conn);
                $positionSeedSummary = seed_field_office_positions($conn, field_positions_by_sheet());
                $message = "Field Office positions generated: {$positionSeedSummary['created']} created, {$positionSeedSummary['updated']} updated, {$positionSeedSummary['removed']} removed.";
                $messageType = 'success';
            } elseif ($action === 'rebuild_all') {
                if (!isset($_POST['replace_existing_sheets']) || $_POST['replace_existing_sheets'] !== '1') {
                    http_response_code(422);
                    $message = 'Confirm that existing generated sheet layouts may be replaced.';
                    $messageType = 'error';
                } else {
                    $ownerId = (int)current_user_id();
                    $findStmt = $conn->prepare('SELECT id, layout_json FROM competency_templates WHERE owner_id = ? AND title = ? ORDER BY id ASC LIMIT 1 FOR UPDATE');
                    $insertStmt = $conn->prepare('INSERT INTO competency_templates (owner_id, title, layout_json, min_salary_grade, max_salary_grade, region, field_name, is_shared) VALUES (?, ?, ?, ?, ?, ?, NULL, 1)');
                    $updateStmt = $conn->prepare('UPDATE competency_templates SET title = ?, layout_json = ?, min_salary_grade = ?, max_salary_grade = ?, region = ?, field_name = NULL, is_shared = 1 WHERE id = ?');
                    $renameStmt = $conn->prepare('UPDATE competency_templates SET title = ?, layout_json = ?, region = ?, field_name = NULL, is_shared = 1 WHERE id = ?');
                    $created = 0;
                    $rebuilt = 0;
                    $regionCreated = 0;
                    $regionRebuilt = 0;
                    $fieldOfficeCreated = 0;
                    $fieldOfficeRebuilt = 0;
                    $fieldOfficeSkipped = 0;
                    $conn->begin_transaction();
                    try {
                        foreach (template_seed_generation_positions(array_merge($positions, $fieldOfficePositions)) as $position) {
                            $sg = (int)$position['salary_grade'];
                            $isFieldOffice = strtoupper((string)($position['office_scope'] ?? '')) === 'FIELD_OFFICE';
                            $sourceSheet = $isFieldOffice ? (string)$position['sheet_name'] : '';
                            $title = 'SG ' . $sg . ' - ' . (string)$position['title']
                                . ($isFieldOffice ? '' : (string)($position['template_suffix'] ?? ''));
                            if ($isFieldOffice
                                && field_office_competency_resolve(
                                    (string)$position['title'],
                                    $sg,
                                    (string)$position['competency_level']
                                ) === null) {
                                $fieldOfficeSkipped++;
                                continue;
                            }
                            $existingTemplate = null;
                            $legacyTitle = '';
                            if ($isFieldOffice) {
                                $existingTemplate = template_seed_find_field_office_template($conn, $ownerId, $title, $sourceSheet);
                            } else {
                                $findStmt->bind_param('is', $ownerId, $title);
                                $findStmt->execute();
                                $existingTemplate = $findStmt->get_result()->fetch_assoc();
                                if (!$existingTemplate && !empty($position['ro_advance']['division'])) {
                                    $legacyTitle = 'SG ' . $sg . ' - ' . (string)$position['title'];
                                    $findStmt->bind_param('is', $ownerId, $legacyTitle);
                                    $findStmt->execute();
                                    $existingTemplate = $findStmt->get_result()->fetch_assoc();
                                }
                            }
                            $existingLayout = $existingTemplate
                                ? json_decode((string)$existingTemplate['layout_json'], true)
                                : null;
                            $layout = json_encode(
                                template_seed_layout(
                                    $position,
                                    $defaultLayout,
                                    is_array($existingLayout) ? $existingLayout : null
                                ),
                                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                            );
                            if ($layout === false) throw new RuntimeException('Unable to encode a generated sheet layout.');
                            $defaultRegion = ACCESS_DEFAULT_REGION;
                            if ($existingTemplate) {
                                $templateId = (int)$existingTemplate['id'];
                                if ($legacyTitle !== '') {
                                    $renameStmt->bind_param('sssi', $title, $layout, $defaultRegion, $templateId);
                                    $renameStmt->execute();
                                } else {
                                    $updateStmt->bind_param('ssiisi', $title, $layout, $sg, $sg, $defaultRegion, $templateId);
                                    $updateStmt->execute();
                                }
                                $rebuilt++;
                                if ($isFieldOffice) $fieldOfficeRebuilt++;
                                else $regionRebuilt++;
                            } else {
                                $insertStmt->bind_param('issiis', $ownerId, $title, $layout, $sg, $sg, $defaultRegion);
                                $insertStmt->execute();
                                $created++;
                                if ($isFieldOffice) $fieldOfficeCreated++;
                                else $regionCreated++;
                            }
                        }
                        $conn->commit();
                    } catch (Throwable $e) {
                        $conn->rollback();
                        throw $e;
                    } finally {
                        $findStmt->close();
                        $insertStmt->close();
                        $updateStmt->close();
                        $renameStmt->close();
                    }
                    $generationSummary = [
                        'created' => $created,
                        'rebuilt' => $rebuilt,
                        'region_created' => $regionCreated,
                        'region_rebuilt' => $regionRebuilt,
                        'field_office_created' => $fieldOfficeCreated,
                        'field_office_rebuilt' => $fieldOfficeRebuilt,
                        'field_office_skipped' => $fieldOfficeSkipped,
                    ];
                    $message = "Rebuild complete. Region: {$regionCreated} created, {$regionRebuilt} rebuilt. Field: {$fieldOfficeCreated} created, {$fieldOfficeRebuilt} rebuilt"
                        . ($fieldOfficeSkipped > 0 ? ", {$fieldOfficeSkipped} unmapped positions skipped." : '.')
                        . ' Rebuilt sheets are shared with raters.';
                    $messageType = 'success';
                    $generatedTemplates = template_seed_generated_templates($conn);
                }
            } elseif ($action === 'generate_field_office') {
                $selectedFieldSheets = template_seed_selected_field_sheets($_POST['field_sheets'] ?? []);
                if (!$selectedFieldSheets) {
                    throw new InvalidArgumentException('Select at least one Field Office sheet to generate.');
                }
                $fieldOfficePositions = array_values(array_filter(
                    template_seed_field_office_positions(),
                    static fn(array $position): bool => in_array($position['sheet_name'], $selectedFieldSheets, true)
                ));
                $shared = isset($_POST['share_field_office']) && $_POST['share_field_office'] === '1';
                $ownerId = (int)current_user_id();
                $insertStmt = $conn->prepare('INSERT INTO competency_templates (owner_id, title, layout_json, min_salary_grade, max_salary_grade, region, field_name, is_shared) VALUES (?, ?, ?, ?, ?, ?, NULL, ?)');
                $updateStmt = $conn->prepare('UPDATE competency_templates
                    SET title = ?, layout_json = ?, min_salary_grade = ?, max_salary_grade = ?,
                        region = ?, field_name = NULL, is_shared = ?
                    WHERE id = ? AND owner_id = ?');
                $shareAllStmt = $conn->prepare("UPDATE competency_templates
                    SET is_shared = 1, region = ?, field_name = NULL
                    WHERE title = ? AND min_salary_grade = ? AND max_salary_grade = ?
                        AND JSON_UNQUOTE(JSON_EXTRACT(layout_json, '$.source_sheet')) = ?");
                $created = 0;
                $updated = 0;
                $skipped = 0;
                $conn->begin_transaction();
                try {
                    foreach ($fieldOfficePositions as $position) {
                        $grade = (int)$position['salary_grade'];
                        $competencyLevel = (string)$position['competency_level'];
                        if (field_office_competency_resolve((string)$position['title'], $grade, $competencyLevel) === null) {
                            $skipped++;
                            continue;
                        }
                        $title = 'SG ' . $grade . ' - ' . (string)$position['title'];
                        if ($shared) {
                            $defaultRegion = ACCESS_DEFAULT_REGION;
                            $sourceSheet = (string)$position['sheet_name'];
                            $shareAllStmt->bind_param('ssiis', $defaultRegion, $title, $grade, $grade, $sourceSheet);
                            $shareAllStmt->execute();
                        }
                        $existing = template_seed_find_field_office_template(
                            $conn,
                            $ownerId,
                            $title,
                            (string)$position['sheet_name']
                        );
                        $existingLayout = isset($existing['layout_json'])
                            ? json_decode((string)$existing['layout_json'], true)
                            : null;
                        $layout = json_encode(
                            template_seed_layout(
                                $position,
                                $defaultLayout,
                                is_array($existingLayout) ? $existingLayout : null
                            ),
                            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                        );
                        if ($layout === false) throw new RuntimeException('Unable to encode a Field Office competency sheet.');
                        $defaultRegion = ACCESS_DEFAULT_REGION;
                        if ($existing) {
                            $templateId = (int)$existing['id'];
                            $updateStmt->bind_param('ssiisiii', $title, $layout, $grade, $grade, $defaultRegion, $shared, $templateId, $ownerId);
                            $updateStmt->execute();
                            $updated++;
                        } else {
                            $insertStmt->bind_param('issiisi', $ownerId, $title, $layout, $grade, $grade, $defaultRegion, $shared);
                            $insertStmt->execute();
                            $created++;
                        }
                    }
                    $conn->commit();
                    $generationSummary = ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'shared' => $shared ? $created + $updated : 0];
                    $message = "Field Office generation complete: {$created} created, {$updated} updated, {$skipped} not mapped.";
                    $messageType = 'success';
                } catch (Throwable $e) {
                    $conn->rollback();
                    throw $e;
                } finally {
                    $insertStmt->close();
                    $updateStmt->close();
                    $shareAllStmt->close();
                }
                $generatedTemplates = template_seed_generated_templates($conn);
            } elseif ($action === 'generate') {
                $shared = isset($_POST['share_generated']) && $_POST['share_generated'] === '1';
                $generateRegion = isset($_POST['generate_region']) && $_POST['generate_region'] === '1';
                $selectedFieldSheets = template_seed_selected_field_sheets($_POST['field_sheets'] ?? []);
                if (!$generateRegion && !$selectedFieldSheets) {
                    throw new InvalidArgumentException('Select Regional Office or at least one Field Office sheet to generate.');
                }
                $fieldOfficePositions = array_values(array_filter(
                    template_seed_field_office_positions(),
                    static fn(array $position): bool => in_array($position['sheet_name'], $selectedFieldSheets, true)
                ));
                $generationPositions = array_merge($generateRegion ? $positions : [], $fieldOfficePositions);
                $ownerId = (int)current_user_id();
                $insertStmt = $conn->prepare('INSERT INTO competency_templates (owner_id, title, layout_json, min_salary_grade, max_salary_grade, region, field_name, is_shared) VALUES (?, ?, ?, ?, ?, ?, NULL, ?)');
                $existsStmt = $conn->prepare('SELECT id, layout_json FROM competency_templates WHERE owner_id = ? AND title = ? LIMIT 1');
                $updateStmt = $conn->prepare('UPDATE competency_templates SET layout_json = ?, region = ?, field_name = NULL WHERE id = ? AND owner_id = ?');
                $renameStmt = $conn->prepare('UPDATE competency_templates SET title = ?, layout_json = ?, region = ?, field_name = NULL WHERE id = ? AND owner_id = ?');
                $updateFieldStmt = $conn->prepare('UPDATE competency_templates
                    SET title = ?, layout_json = ?, min_salary_grade = ?, max_salary_grade = ?,
                        region = ?, field_name = NULL,
                        is_shared = CASE WHEN ? = 1 THEN 1 ELSE is_shared END
                    WHERE id = ? AND owner_id = ?');
                $shareAllRegionalStmt = $conn->prepare("UPDATE competency_templates
                    SET is_shared = 1, region = ?, field_name = NULL
                    WHERE title = ? AND min_salary_grade = ? AND max_salary_grade = ?
                        AND COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(layout_json, '$.office_type')), ''), 'RO') = 'RO'
                        AND COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(layout_json, '$.source_sheet')), ''), '') = ''");
                $shareAllFieldStmt = $conn->prepare("UPDATE competency_templates
                    SET is_shared = 1, region = ?, field_name = NULL
                    WHERE title = ? AND min_salary_grade = ? AND max_salary_grade = ?
                        AND JSON_UNQUOTE(JSON_EXTRACT(layout_json, '$.source_sheet')) = ?");
                $shareStmt = $conn->prepare('UPDATE competency_templates SET is_shared = 1 WHERE id = ? AND owner_id = ?');
                $created = 0;
                $updated = 0;
                $skipped = 0;
                $sharedCount = 0;
                $fieldOfficeCreated = 0;
                $fieldOfficeUpdated = 0;
                $fieldOfficeSkipped = 0;
                $conn->begin_transaction();
                try {
                    foreach (template_seed_generation_positions($generationPositions) as $position) {
                        $sg = (int)$position['salary_grade'];
                        $isFieldOffice = strtoupper((string)($position['office_scope'] ?? '')) === 'FIELD_OFFICE';
                        if ($isFieldOffice && field_office_competency_resolve(
                            (string)$position['title'],
                            $sg,
                            (string)$position['competency_level']
                        ) === null) {
                            $fieldOfficeSkipped++;
                            continue;
                        }
                        $title = 'SG ' . $sg . ' - ' . (string)$position['title']
                            . ($isFieldOffice ? '' : (string)($position['template_suffix'] ?? ''));
                        $defaultRegion = ACCESS_DEFAULT_REGION;
                        if ($shared) {
                            if ($isFieldOffice) {
                                $sourceSheet = (string)$position['sheet_name'];
                                $shareAllFieldStmt->bind_param('ssiis', $defaultRegion, $title, $sg, $sg, $sourceSheet);
                                $shareAllFieldStmt->execute();
                            } else {
                                $shareAllRegionalStmt->bind_param('ssii', $defaultRegion, $title, $sg, $sg);
                                $shareAllRegionalStmt->execute();
                            }
                        }
                        $legacyTitle = '';
                        if ($isFieldOffice) {
                            $existingTemplate = template_seed_find_field_office_template(
                                $conn,
                                $ownerId,
                                $title,
                                (string)$position['sheet_name']
                            );
                            if ($existingTemplate && (string)$existingTemplate['title'] !== $title) {
                                $legacyTitle = (string)$existingTemplate['title'];
                            }
                        } else {
                            $existsStmt->bind_param('is', $ownerId, $title);
                            $existsStmt->execute();
                            $existingTemplate = $existsStmt->get_result()->fetch_assoc();
                            if (!$existingTemplate && !empty($position['ro_advance']['division'])) {
                                $legacyTitle = 'SG ' . $sg . ' - ' . (string)$position['title'];
                                $existsStmt->bind_param('is', $ownerId, $legacyTitle);
                                $existsStmt->execute();
                                $existingTemplate = $existsStmt->get_result()->fetch_assoc();
                            }
                        }

                        if ($shared && $existingTemplate) {
                            $templateId = (int)$existingTemplate['id'];
                            $shareStmt->bind_param('ii', $templateId, $ownerId);
                            $shareStmt->execute();
                            $sharedCount++;
                        }

                        if ($isFieldOffice) {
                            $existingLayout = $existingTemplate
                                ? json_decode((string)$existingTemplate['layout_json'], true)
                                : null;
                            $layoutJson = json_encode(
                                template_seed_layout($position, $defaultLayout, is_array($existingLayout) ? $existingLayout : null),
                                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                            );
                            if ($layoutJson === false) {
                                throw new RuntimeException('Unable to encode a Field Office competency sheet.');
                            }
                            if ($existingTemplate) {
                                $templateId = (int)$existingTemplate['id'];
                                $updateFieldStmt->bind_param('ssiiisii', $title, $layoutJson, $sg, $sg, $defaultRegion, $shared, $templateId, $ownerId);
                                $updateFieldStmt->execute();
                                $fieldOfficeUpdated++;
                            } else {
                                $insertStmt->bind_param('issiisi', $ownerId, $title, $layoutJson, $sg, $sg, $defaultRegion, $shared);
                                $insertStmt->execute();
                                $fieldOfficeCreated++;
                            }
                            if ($shared && !$existingTemplate) $sharedCount++;
                            continue;
                        }
                        if (!empty($position['ro_advance']['division'])) {
                            $existingLayout = $existingTemplate
                                ? json_decode((string)$existingTemplate['layout_json'], true)
                                : null;
                            $layoutJson = json_encode(
                                template_seed_layout($position, $defaultLayout, is_array($existingLayout) ? $existingLayout : null),
                                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                            );
                            if ($layoutJson === false) throw new RuntimeException('Unable to encode an RO Advance competency sheet.');
                            $defaultRegion = ACCESS_DEFAULT_REGION;
                            if ($existingTemplate) {
                                $templateId = (int)$existingTemplate['id'];
                                if ($legacyTitle !== '') {
                                    $renameStmt->bind_param('sssii', $title, $layoutJson, $defaultRegion, $templateId, $ownerId);
                                    $renameStmt->execute();
                                } else {
                                    $updateStmt->bind_param('ssii', $layoutJson, $defaultRegion, $templateId, $ownerId);
                                    $updateStmt->execute();
                                }
                                $updated++;
                            } else {
                                $insertStmt->bind_param('issiisi', $ownerId, $title, $layoutJson, $sg, $sg, $defaultRegion, $shared);
                                $insertStmt->execute();
                                $created++;
                                if ($shared) $sharedCount++;
                            }
                            continue;
                        }
                        if ($existingTemplate) {
                            $existingLayout = json_decode((string)$existingTemplate['layout_json'], true);
                            if (is_array($existingLayout)
                                && in_array($sg, [10, 11], true)
                                && ($updatedLayout = template_seed_apply_intermediate_competencies($existingLayout, $position)) !== $existingLayout) {
                                $updatedLayoutJson = json_encode($updatedLayout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                if ($updatedLayoutJson === false) {
                                    throw new RuntimeException('Unable to encode the updated Intermediate competencies.');
                                }
                                $templateId = (int)$existingTemplate['id'];
                                $updateStmt->bind_param('ssii', $updatedLayoutJson, $defaultRegion, $templateId, $ownerId);
                                $updateStmt->execute();
                                $updated++;
                                continue;
                            }
                            if (is_array($existingLayout) && (int)($position['salary_grade'] ?? 0) > BASIC_MAX_SG) {
                                $updatedLayout = template_seed_apply_leadership_section($existingLayout, $position);
                                if ($updatedLayout !== $existingLayout) {
                                    $updatedLayoutJson = json_encode($updatedLayout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                    if ($updatedLayoutJson === false) {
                                        throw new RuntimeException('Unable to encode the updated Leadership competencies.');
                                    }
                                    $templateId = (int)$existingTemplate['id'];
                                    $updateStmt->bind_param('ssii', $updatedLayoutJson, $defaultRegion, $templateId, $ownerId);
                                    $updateStmt->execute();
                                    $updated++;
                                    continue;
                                }
                            }
                            if ($sg <= BASIC_MAX_SG && is_array($existingLayout)) {
                                $existingLayout = template_seed_apply_basic_rows($existingLayout, $position);
                                $existingFunctionalRows = $existingLayout['sections']['func']['rows'] ?? null;
                                $isBlankPlaceholder = is_array($existingFunctionalRows)
                                    && count($existingFunctionalRows) === 1
                                    && (string)($existingFunctionalRows[0]['id'] ?? '') === 'f1'
                                    && trim((string)($existingFunctionalRows[0]['title'] ?? '')) === ''
                                    && trim((string)($existingFunctionalRows[0]['desc'] ?? '')) === '';
                                if ($isBlankPlaceholder && is_array($existingLayout['sections']['func'] ?? null)) {
                                    $functionalRows = template_seed_functional_rows((string)$position['title']);
                                    if (!$functionalRows) $functionalRows = template_seed_copy_rows($defaultLayout['sections']['func']);
                                    $existingLayout['sections']['func']['rows'] = $functionalRows;
                                    $existingLayout['sections']['func']['custom'] = true;
                                    unset($existingLayout['sections']['func']['standard']);
                                }
                                $updatedLayoutJson = json_encode($existingLayout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                if ($updatedLayoutJson === false) {
                                    throw new RuntimeException('Unable to encode the updated Basic competencies.');
                                }
                                $templateId = (int)$existingTemplate['id'];
                                $updateStmt->bind_param('ssii', $updatedLayoutJson, $defaultRegion, $templateId, $ownerId);
                                $updateStmt->execute();
                                $updated++;
                                continue;
                            }

                            $functionalRows = template_seed_copy_rows($defaultLayout['sections']['func']);
                            $existingFunctionalRows = $existingLayout['sections']['func']['rows'] ?? null;
                            $isBlankPlaceholder = is_array($existingFunctionalRows)
                                && count($existingFunctionalRows) === 1
                                && (string)($existingFunctionalRows[0]['id'] ?? '') === 'f1'
                                && trim((string)($existingFunctionalRows[0]['title'] ?? '')) === ''
                                && trim((string)($existingFunctionalRows[0]['desc'] ?? '')) === '';
                            if ($functionalRows && $isBlankPlaceholder && is_array($existingLayout['sections']['func'] ?? null)) {
                                $existingLayout['sections']['func']['rows'] = $functionalRows;
                                $existingLayout['sections']['func']['custom'] = true;
                                $existingLayout['sections']['func']['level'] = template_level_for_salary_grade($sg);
                                unset($existingLayout['sections']['func']['standard']);
                                $updatedLayoutJson = json_encode($existingLayout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                if ($updatedLayoutJson === false) {
                                    throw new RuntimeException('Unable to encode the updated functional competencies.');
                                }
                                $templateId = (int)$existingTemplate['id'];
                                $updateStmt->bind_param('ssii', $updatedLayoutJson, $defaultRegion, $templateId, $ownerId);
                                $updateStmt->execute();
                                $updated++;
                                continue;
                            }
                            $skipped++;
                            continue;
                        }

                        $defaultRegion = ACCESS_DEFAULT_REGION;
                        $layout = json_encode(
                            template_seed_layout($position, $defaultLayout),
                            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                        );
                        $insertStmt->bind_param('issiisi', $ownerId, $title, $layout, $sg, $sg, $defaultRegion, $shared);
                        $insertStmt->execute();
                        $created++;
                        if ($shared) $sharedCount++;
                    }
                    $conn->commit();
                    $generationSummary = [
                        'created' => $created,
                        'updated' => $updated,
                        'skipped' => $skipped,
                        'shared' => $sharedCount,
                        'field_office_created' => $fieldOfficeCreated,
                        'field_office_updated' => $fieldOfficeUpdated,
                        'field_office_skipped' => $fieldOfficeSkipped,
                    ];
                    $message = "Generation complete: {$created} Regional Office sheets created, {$updated} updated, {$skipped} skipped; {$fieldOfficeCreated} Field Office sheets created, {$fieldOfficeUpdated} updated, {$fieldOfficeSkipped} unmapped; {$sharedCount} shared with raters.";
                    $messageType = 'success';
                } catch (Throwable $e) {
                    $conn->rollback();
                    throw $e;
                } finally {
                    $insertStmt->close();
                    $existsStmt->close();
                    $updateStmt->close();
                    $renameStmt->close();
                    $updateFieldStmt->close();
                    $shareStmt->close();
                    $shareAllRegionalStmt->close();
                    $shareAllFieldStmt->close();
                }
                $generatedTemplates = template_seed_generated_templates($conn);
            } elseif ($action === 'import_functional') {
                $templateId = filter_var($_POST['template_id'] ?? null, FILTER_VALIDATE_INT);
                $append = ($_POST['import_mode'] ?? 'replace') === 'append';
                [$rowsToImport, $functionalErrors] = template_seed_parse_functional_rows((string)($_POST['functional_csv'] ?? ''));
                if ($templateId === false || $templateId < 1) {
                    http_response_code(422);
                    $message = 'Select a generated template.';
                    $messageType = 'error';
                } elseif (!$rowsToImport) {
                    http_response_code(422);
                    $message = 'No valid functional competency rows were provided.';
                    $messageType = 'error';
                } else {
                    $conn->begin_transaction();
                    try {
                        $templateStmt = $conn->prepare("SELECT layout_json FROM competency_templates
                            WHERE id = ? AND title LIKE 'SG % - %' AND min_salary_grade = max_salary_grade
                            LIMIT 1 FOR UPDATE");
                        $templateStmt->bind_param('i', $templateId);
                        $templateStmt->execute();
                        $templateRow = $templateStmt->get_result()->fetch_assoc();
                        $templateStmt->close();
                        if (!$templateRow) {
                            $conn->rollback();
                            http_response_code(404);
                            $message = 'Generated template not found.';
                            $messageType = 'error';
                        } else {
                            $layout = json_decode((string)$templateRow['layout_json'], true);
                            if (!is_array($layout) || !is_array($layout['sections'] ?? null)) {
                                throw new RuntimeException('Template layout data is invalid.');
                            }
                            if (!isset($layout['sections']['func']) || !is_array($layout['sections']['func'])) {
                                $layout['sections']['func'] = [
                                    'label' => 'Functional Competencies',
                                    'level' => 'Advanced',
                                    'rows' => [],
                                ];
                            }
                            $functionalSection = &$layout['sections']['func'];
                            $existingRows = is_array($functionalSection['rows'] ?? null) ? $functionalSection['rows'] : [];
                            $nextNumber = 1;
                            if ($append) {
                                foreach ($existingRows as $existingRow) {
                                    if (preg_match('/^f([1-9][0-9]*)$/', (string)($existingRow['id'] ?? ''), $matches)) {
                                        $nextNumber = max($nextNumber, (int)$matches[1] + 1);
                                    }
                                }
                            } else {
                                $existingRows = [];
                            }
                            foreach ($rowsToImport as $row) {
                                $existingRows[] = [
                                    'id' => 'f' . $nextNumber++,
                                    'title' => $row['title'],
                                    'desc' => $row['desc'],
                                ];
                            }
                            $functionalSection['rows'] = $existingRows;
                            unset($functionalSection);
                            $layoutJson = json_encode($layout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            $updateStmt = $conn->prepare('UPDATE competency_templates SET layout_json = ? WHERE id = ?');
                            $updateStmt->bind_param('si', $layoutJson, $templateId);
                            $updateStmt->execute();
                            $updateStmt->close();
                            $conn->commit();
                            $functionalSummary = count($rowsToImport);
                            $message = "Functional competencies saved: {$functionalSummary} row(s) imported.";
                            $messageType = 'success';
                        }
                    } catch (Throwable $e) {
                        if ($conn->errno || $conn->thread_id) {
                            try { $conn->rollback(); } catch (Throwable $ignored) {}
                        }
                        throw $e;
                    }
                }
            } else {
                http_response_code(400);
                $message = 'Unknown action.';
                $messageType = 'error';
            }
        }
    }
} catch (Throwable $e) {
    error_log('Template seed operation failed: ' . $e->getMessage());
    if ($messageType === '') {
        if ($e instanceof InvalidArgumentException) {
            http_response_code(422);
            $message = $e->getMessage();
        } else {
            http_response_code(500);
            $message = 'Unable to process templates. Check the server error log.';
        }
        $messageType = 'error';
    }
}

if (!$generatedTemplates) {
    try {
        $generatedTemplates = template_seed_generated_templates($conn);
    } catch (Throwable $e) {
        error_log('Template seed generated-template list failed: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Generate Position Sheets</title>
    <link rel="stylesheet" href="ui-enhancements.css?v=<?php echo filemtime(__DIR__ . '/ui-enhancements.css'); ?>">
    <script src="ui-enhancements.js?v=<?php echo filemtime(__DIR__ . '/ui-enhancements.js'); ?>" defer></script>
    <style>
        :root { --ink:#202a35; --muted:#687584; --line:#d5dce2; --paper:#fff; --canvas:#edf1f3; --green:#107c41; }
        * { box-sizing:border-box; }
        body { margin:0; padding:28px 16px; background:var(--canvas); color:var(--ink); font:14px "Segoe UI",Arial,sans-serif; }
        main { max-width:1100px; margin:0 auto; }
        section { margin-bottom:22px; padding:22px; background:var(--paper); border:1px solid var(--line); border-radius:8px; }
        h1,h2 { margin-top:0; }
        p { color:var(--muted); line-height:1.5; }
        label { display:block; margin:12px 0 6px; font-weight:600; }
        select,textarea,button { font:inherit; }
        select,textarea { width:100%; padding:9px; border:1px solid #b8c2cb; border-radius:4px; }
        textarea { min-height:190px; resize:vertical; font-family:ui-monospace,Consolas,monospace; }
        button,.button { display:inline-block; padding:9px 14px; border:1px solid var(--green); border-radius:4px; background:var(--green); color:#fff; text-decoration:none; cursor:pointer; }
        button.secondary,.button.secondary { background:#fff; color:var(--green); }
        table { width:100%; margin-top:14px; border-collapse:collapse; }
        th,td { padding:8px; border:1px solid var(--line); text-align:left; }
        th { background:#f4f7f8; }
        .notice { padding:11px 13px; border-radius:4px; background:#eef4ff; border:1px solid #c7d8f5; }
        .notice.error { color:#7a1f16; background:#fdecea; border-color:#f5c2c0; }
        .notice.success { color:#14532d; background:#e8f5e9; border-color:#b7dfbd; }
        .notice[role="status"],.notice[role="alert"] { margin:16px 0; }
        .notice-next-step { margin:8px 0 0; font-size:13px; }
        .notice-next-step a { color:inherit; font-weight:700; }
        .actions { display:flex; flex-wrap:wrap; align-items:center; gap:12px; margin-top:14px; }
        .inline { display:inline-flex; align-items:center; gap:7px; margin:0; font-weight:400; }
        .inline input { margin:0; }
        .sheet-options { display:grid; gap:8px; margin:14px 0; padding:12px; border:1px solid var(--line); border-radius:4px; }
        .sheet-options legend { padding:0 6px; font-weight:700; }
        .radio-group { display:flex; gap:18px; margin:10px 0; }
        .radio-group label { margin:0; font-weight:400; }
        .page-nav { position:absolute; top:24px; left:24px; }
        .back-link { display:inline-block; padding:9px 12px; border:1px solid var(--line); border-radius:4px; background:#fff; color:var(--ink); text-decoration:none; }
        .back-link:hover { border-color:var(--green); color:var(--green); }
        .confirm-dialog { width:min(calc(100% - 32px),480px); padding:0; border:0; border-radius:12px; color:var(--ink); box-shadow:0 18px 56px rgba(15,23,42,.28); }
        .confirm-dialog::backdrop { background:rgba(15,23,42,.5); }
        .confirm-dialog-content { padding:24px; }
        .confirm-dialog h2 { margin:0 0 10px; font-size:20px; }
        .confirm-dialog p { margin:0; }
        .confirm-dialog-actions { display:flex; justify-content:flex-end; flex-wrap:wrap; gap:10px; margin-top:24px; }
        .confirm-dialog-actions .secondary { border-color:var(--line); color:var(--ink); }
        button:focus-visible,.button:focus-visible,a:focus-visible { outline:3px solid rgba(16,124,65,.3); outline-offset:2px; }
        @media (max-width:1200px) { .page-nav { position:static; max-width:1100px; margin:0 auto 10px; padding:0 16px; } }
    </style>
</head>
<body>
<nav class="page-nav" aria-label="Page navigation"><a class="back-link" href="template_manager.php">Back to sheets</a></nav>
<main>
    <section>
        <h1>Generate Regional Office Position Sheets</h1>
        <p>Generate one scoring sheet per Regional Office position for <?php echo htmlspecialchars(ACCESS_REGIONS[ACCESS_DEFAULT_REGION], ENT_QUOTES, 'UTF-8'); ?>. Select the sharing option so raters can open the forms from the position picker. Existing competency content is preserved.</p>
        <?php if ($message !== ''): ?>
            <div class="notice <?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?>" role="<?php echo $messageType === 'error' ? 'alert' : 'status'; ?>" aria-live="<?php echo $messageType === 'error' ? 'assertive' : 'polite'; ?>"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if (count($positions) === 0): ?>
            <div class="notice error">No Regional Office positions were found. Seed the built-in positions or ask an administrator to import position data.</div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="seed_positions">
                <div class="actions"><button type="submit">Seed positions now</button></div>
            </form>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="generate">
                <fieldset class="sheet-options">
                    <legend>Select office sheets to generate</legend>
                    <label class="inline"><input type="checkbox" name="generate_region" value="1" checked> Region position sheets (RO)</label>
                    <?php foreach (FIELD_POSITION_SHEETS as $sheetName => $sheetMapping): ?>
                        <label class="inline"><input type="checkbox" name="field_sheets[]" value="<?php echo htmlspecialchars($sheetName, ENT_QUOTES, 'UTF-8'); ?>" checked>
                            Field: <?php echo htmlspecialchars($sheetName, ENT_QUOTES, 'UTF-8'); ?> (SG <?php echo (int)$sheetMapping['min_salary_grade']; ?>–<?php echo (int)$sheetMapping['max_salary_grade']; ?>)
                        </label>
                    <?php endforeach; ?>
                </fieldset>
                <label class="inline"><input type="checkbox" name="share_generated" value="1" checked> Make selected sheets available to raters (also shares existing generated sheets)</label>
                <div class="actions"><button type="submit">Generate selected sheets</button></div>
            </form>
            <form method="post" id="rebuildAllForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="rebuild_all">
                <label class="inline"><input type="checkbox" name="replace_existing_sheets" value="1" required> Replace existing generated layouts and create mapped Field Office sheets</label>
                <div class="actions"><button type="submit" id="rebuildAllButton">Rebuild all Regional and Field Office sheets</button></div>
            </form>
            <dialog class="confirm-dialog" id="rebuildConfirmDialog" aria-labelledby="rebuildConfirmTitle" aria-describedby="rebuildConfirmDescription">
                <div class="confirm-dialog-content">
                    <h2 id="rebuildConfirmTitle">Replace generated sheet layouts?</h2>
                    <p id="rebuildConfirmDescription">This rebuilds all Regional Office sheets and mapped Field Office sheets. Existing generated layouts will be replaced and the rebuilt sheets will be shared with raters. This cannot be undone.</p>
                    <div class="confirm-dialog-actions">
                        <button type="button" class="secondary" id="cancelRebuildButton">Cancel</button>
                        <button type="button" id="confirmRebuildButton">Rebuild sheets</button>
                    </div>
                </div>
            </dialog>
            <?php if ($generationSummary !== null): ?>
                <?php if (isset($generationSummary['rebuilt'])): ?>
                    <p>Rebuild summary: Region — <?php echo (int)$generationSummary['region_created']; ?> created, <?php echo (int)$generationSummary['region_rebuilt']; ?> rebuilt. Field — <?php echo (int)$generationSummary['field_office_created']; ?> created, <?php echo (int)$generationSummary['field_office_rebuilt']; ?> rebuilt, <?php echo (int)$generationSummary['field_office_skipped']; ?> unmapped positions skipped.</p>
                    <p class="notice-next-step">Rebuilding updates the generated templates; it does not open a scoring form. <a href="position_picker.php">Open the position picker to verify the sheets</a>.</p>
                <?php else: ?>
                    <p>Summary: <?php echo (int)$generationSummary['created']; ?> created; <?php echo (int)$generationSummary['updated']; ?> existing sheets updated; <?php echo (int)$generationSummary['skipped']; ?> skipped; <?php echo (int)$generationSummary['shared']; ?> made available to raters.</p>
                    <?php if (isset($generationSummary['field_office_created'])): ?>
                        <p>Field Office: <?php echo (int)$generationSummary['field_office_created']; ?> created; <?php echo (int)$generationSummary['field_office_updated']; ?> updated; <?php echo (int)$generationSummary['field_office_skipped']; ?> unmapped positions skipped.</p>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
            <?php
            $regionSalaryGradeCounts = [];
            foreach ($positions as $position) {
                $grade = (int)$position['salary_grade'];
                $regionSalaryGradeCounts[$grade] = ($regionSalaryGradeCounts[$grade] ?? 0) + 1;
            }
            $regionSalaryGradeSummary = [];
            foreach ($regionSalaryGradeCounts as $grade => $count) {
                $regionSalaryGradeSummary[] = 'SG ' . (int)$grade . ': ' . (int)$count;
            }
            $existingTitles = [];
            foreach ($generatedTemplates as $generatedTemplate) {
                if (template_seed_template_office_type($generatedTemplate) === 'RO') {
                    $existingTitles[(string)$generatedTemplate['title']] = true;
                }
            }
            $expectedRegionTitles = [];
            foreach ($positions as $position) {
                foreach (template_seed_region_template_titles($position) as $title) {
                    $expectedRegionTitles[$title] = true;
                }
            }
            $generatedRegionCount = count(array_intersect_key($expectedRegionTitles, $existingTitles));
            ?>
            <h2>Regional position summary</h2>
            <p><?php echo count($positions); ?> positions across <?php echo count($regionSalaryGradeCounts); ?> salary grades.</p>
            <p><?php echo $generatedRegionCount; ?> of <?php echo count($expectedRegionTitles); ?> Region sheets generated.</p>
            <?php if ($regionSalaryGradeCounts): ?>
                <p><?php echo htmlspecialchars(implode(' · ', $regionSalaryGradeSummary), ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <table>
                <thead><tr><th>SG</th><th>Position</th><th>Leadership</th><th>Action</th></tr></thead>
                <tbody>
                <?php
                foreach ($positions as $position):
                    $positionTitle = (string)$position['title'];
                    $sg = (int)$position['salary_grade'];
                    $positionTemplateTitles = template_seed_region_template_titles($position);
                    $allPositionSheetsExist = !array_diff($positionTemplateTitles, array_keys($existingTitles));
                    $level = strtoupper(trim((string)($position['leadership_level'] ?? '')));
                    $leadershipLabel = $sg <= BASIC_MAX_SG ? '—' : ($level === 'B' ? 'Basic' : ($level === 'I' ? 'Advanced' : '—'));
                ?>
                    <tr>
                        <td><?php echo $sg; ?></td>
                        <td><?php echo htmlspecialchars($positionTitle, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($leadershipLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo $allPositionSheetsExist ? 'skip' : 'create'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section>
        <h2>Generate Field Office Competency Sheets</h2>
        <p>Templates are generated for <?php echo htmlspecialchars(ACCESS_REGIONS[ACCESS_DEFAULT_REGION], ENT_QUOTES, 'UTF-8'); ?> from the configured Field Office sheet, position, salary-grade range, and competency-level mappings.</p>
        <?php if (!$fieldOfficePositions): ?>
            <div class="notice">No Field Office sheet-position mappings are configured.</div>
        <?php else: ?>
            <form method="post" class="actions">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="seed_field_positions">
                <button type="submit">Generate Field Office positions</button>
                <span>Seeds the configured Field position list into the positions database, separated by source sheet.</span>
            </form>
            <table>
                <thead><tr><th>Source sheet</th><th>SG</th><th>Position</th><th>Competency level</th><th>Functional rows</th></tr></thead>
                <tbody>
                <?php foreach ($fieldOfficePositions as $fieldOfficePosition):
                    $resolved = field_office_competency_resolve(
                        (string)$fieldOfficePosition['title'],
                        (int)$fieldOfficePosition['salary_grade'],
                        (string)$fieldOfficePosition['competency_level']
                    );
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string)$fieldOfficePosition['sheet_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo (int)$fieldOfficePosition['salary_grade']; ?></td>
                        <td><?php echo htmlspecialchars((string)$fieldOfficePosition['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo $resolved ? htmlspecialchars($resolved['level'], ENT_QUOTES, 'UTF-8') : 'No mapping'; ?></td>
                        <td><?php echo $resolved ? count($resolved['functional']) : '—'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="generate_field_office">
                <fieldset class="sheet-options">
                    <legend>Select Field Office sheets to generate</legend>
                    <?php foreach (FIELD_POSITION_SHEETS as $sheetName => $sheetMapping): ?>
                        <label class="inline"><input type="checkbox" name="field_sheets[]" value="<?php echo htmlspecialchars($sheetName, ENT_QUOTES, 'UTF-8'); ?>" checked>
                            <?php echo htmlspecialchars($sheetName, ENT_QUOTES, 'UTF-8'); ?> (SG <?php echo (int)$sheetMapping['min_salary_grade']; ?>–<?php echo (int)$sheetMapping['max_salary_grade']; ?>)
                        </label>
                    <?php endforeach; ?>
                </fieldset>
                <label class="inline"><input type="checkbox" name="share_field_office" value="1" checked> Make generated Field Office sheets available to raters</label>
                <div class="actions"><button type="submit">Generate selected Field Office sheets</button></div>
            </form>
        <?php endif; ?>
    </section>

    <section>
        <h2>Import Functional Competencies</h2>
        <p>Paste two columns from Excel: title and description. Use a tab or comma between the columns. Blank lines are ignored.</p>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="import_functional">
            <label for="template_id">Generated template</label>
            <select id="template_id" name="template_id" required>
                <option value="">Select a template</option>
                <?php foreach (['RO' => 'Region', 'FO' => 'Field'] as $officeType => $officeLabel):
                    $officeTemplates = array_filter(
                        $generatedTemplates,
                        static fn(array $template): bool => ($template['office_type'] ?? '') === $officeType
                    );
                    if (!$officeTemplates) continue;
                ?>
                    <optgroup label="<?php echo $officeLabel; ?> forms">
                        <?php foreach ($officeTemplates as $generatedTemplate):
                            $templateTitle = preg_replace('/^SG \d+ - /', '', (string)$generatedTemplate['title']);
                            $optionLabel = 'SG ' . (int)$generatedTemplate['min_salary_grade'] . ' — ' . $templateTitle;
                            if ($officeType === 'FO' && !empty($templateSeedSourceSheetLabels[$generatedTemplate['source_sheet'] ?? ''])) {
                                $optionLabel .= ' (' . $templateSeedSourceSheetLabels[$generatedTemplate['source_sheet']] . ')';
                            }
                        ?>
                            <option value="<?php echo (int)$generatedTemplate['id']; ?>" <?php echo (isset($_POST['template_id']) && (int)$_POST['template_id'] === (int)$generatedTemplate['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
            <div class="radio-group">
                <label><input type="radio" name="import_mode" value="replace" checked> Replace functional section</label>
                <label><input type="radio" name="import_mode" value="append"> Append</label>
            </div>
            <label for="functional_csv">Functional competencies</label>
            <textarea id="functional_csv" name="functional_csv" placeholder="Title&#9;Description"></textarea>
            <div class="actions"><button type="submit">Import functional competencies</button></div>
        </form>
        <?php if ($functionalSummary !== null): ?>
            <p>Summary: <?php echo (int)$functionalSummary; ?> functional competency row(s) added.</p>
        <?php endif; ?>
        <?php if ($functionalErrors): ?>
            <div class="notice error">
                <strong>Rejected lines:</strong>
                <ul>
                <?php foreach ($functionalErrors as $functionalError): ?>
                    <li>Line <?php echo (int)$functionalError['line']; ?>: <?php echo htmlspecialchars($functionalError['message'], ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </section>
</main>
<script>
    (function () {
        const form = document.getElementById('rebuildAllForm');
        const dialog = document.getElementById('rebuildConfirmDialog');
        const cancelButton = document.getElementById('cancelRebuildButton');
        const confirmButton = document.getElementById('confirmRebuildButton');
        let confirmed = false;

        if (!form || !dialog || typeof dialog.showModal !== 'function') return;

        form.addEventListener('submit', event => {
            if (confirmed) return;
            event.preventDefault();
            dialog.showModal();
        });
        cancelButton.addEventListener('click', () => dialog.close());
        confirmButton.addEventListener('click', () => {
            confirmed = true;
            dialog.close();
            form.requestSubmit(document.getElementById('rebuildAllButton'));
        });
    })();
</script>
</body>
</html>
