USE interview_rating;

-- 1) Add users.account_type only if it doesn't exist yet
SET @has_col = (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = 'interview_rating'
                  AND TABLE_NAME = 'users' AND COLUMN_NAME = 'account_type');
SET @sql = IF(@has_col = 0,
  "ALTER TABLE users ADD COLUMN account_type ENUM('region','field') NULL DEFAULT NULL",
  "SELECT 'account_type already exists'");
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2) Add the field positions to the SAME positions table
--    (copies SG and leadership level from the existing rows, so nothing is guessed)
INSERT IGNORE INTO positions (office_scope, title, salary_grade, leadership_level)
SELECT 'FIELD_OFFICES', title, salary_grade, leadership_level
FROM positions
WHERE office_scope = 'REGIONAL_OFFICE'
  AND title IN (
    'Administrative Aide IV','Administrative Aide VI',
    'Administrative Assistant I (Computer Operator I)','Administrative Assistant II (Bookkeeper)',
    'Administrative Assistant III (Computer Operator II)','Credit Officer I','Park Attendant II',
    'Forest Ranger','Engineering Aide','Cartographer I','Cartographer II',
    'Land Management Inspector','Forest Technician I','Forest Technician II','Park Maintenance Foreman',
    'Administrative Officer I (Cashier I)','Administrative Officer I (Records Officer I)',
    'Administrative Officer I (Supply Officer I)','Planning Officer I','Planning Officer II',
    'Information Systems Analyst II','Administrative Officer IV (Budget Officer II)',
    'Administrative Officer IV (HRMO II)','Forester I','Forester II',
    'Ecosystems Management Specialist I','Ecosystems Management Specialist II',
    'Land Management Officer I','Land Management Officer II','Special Investigator I',
    'Community Development Officer II','Development Management Officer I',
    'Development Management Officer II','Engineer II (Geodetic Engineer)',
    'Accountant III','Planning Officer III','Development Management Officer III',
    'Forester III','Land Management Officer III','Senior Ecosystems Management Specialist',
    'Supervising Ecosystems Management Specialist'
  );

-- 3) Backfill account_type for existing non-admin users (field_name set = field, region set = region)
UPDATE users SET account_type = 'field'
WHERE role <> 'admin' AND account_type IS NULL AND field_name IS NOT NULL AND field_name <> '';
UPDATE users SET account_type = 'region'
WHERE role <> 'admin' AND account_type IS NULL AND region IS NOT NULL AND region <> '';

-- 4) Check the result
SELECT office_scope, COUNT(*) AS total FROM positions GROUP BY office_scope;
SELECT username, role, region, field_name, account_type FROM users;