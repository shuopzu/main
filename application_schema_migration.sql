-- Consolidated application schema/data migration.
-- Select the application database before running this file.
-- Safe to run repeatedly; existing application data is preserved.
--
-- This combines the schema/data work from check.sql, schema.sql,
-- password_reset_requests.sql, and email_login_migration.sql.
-- FreeDB scripts are intentionally excluded. sum.sql is database-user
-- provisioning with a placeholder password, so it must be handled separately.

SET @database_name = DATABASE();

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','rater','user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @has_users_email = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @database_name
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'email'
);
SET @sql = IF(
    @has_users_email = 0,
    'ALTER TABLE `users` ADD COLUMN `email` VARCHAR(254) NULL DEFAULT NULL',
    'SELECT ''users.email already exists'' AS status'
);
PREPARE migration_stmt FROM @sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @has_unique_email_index = (
    SELECT COUNT(*)
    FROM (
        SELECT INDEX_NAME
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @database_name
          AND TABLE_NAME = 'users'
          AND NON_UNIQUE = 0
        GROUP BY INDEX_NAME
        HAVING COUNT(*) = 1 AND SUM(COLUMN_NAME = 'email') = 1
    ) AS email_indexes
);
SET @sql = IF(
    @has_unique_email_index = 0,
    'CREATE UNIQUE INDEX `uq_users_email` ON `users` (`email`)',
    'SELECT ''a unique users.email index already exists'' AS status'
);
PREPARE migration_stmt FROM @sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @has_users_region = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @database_name AND TABLE_NAME = 'users' AND COLUMN_NAME = 'region'
);
SET @sql = IF(
    @has_users_region = 0,
    'ALTER TABLE `users` ADD COLUMN `region` VARCHAR(100) NULL DEFAULT NULL',
    'SELECT ''users.region already exists'' AS status'
);
PREPARE migration_stmt FROM @sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @has_users_field_name = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @database_name AND TABLE_NAME = 'users' AND COLUMN_NAME = 'field_name'
);
SET @sql = IF(
    @has_users_field_name = 0,
    'ALTER TABLE `users` ADD COLUMN `field_name` VARCHAR(100) NULL DEFAULT NULL',
    'SELECT ''users.field_name already exists'' AS status'
);
PREPARE migration_stmt FROM @sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @has_users_account_type = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @database_name AND TABLE_NAME = 'users' AND COLUMN_NAME = 'account_type'
);
SET @sql = IF(
    @has_users_account_type = 0,
    'ALTER TABLE `users` ADD COLUMN `account_type` ENUM(''region'',''field'') NULL DEFAULT NULL',
    'SELECT ''users.account_type already exists'' AS status'
);
PREPARE migration_stmt FROM @sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @has_users_salary_grade = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @database_name AND TABLE_NAME = 'users' AND COLUMN_NAME = 'salary_grade'
);
SET @sql = IF(
    @has_users_salary_grade = 0,
    'ALTER TABLE `users` ADD COLUMN `salary_grade` TINYINT UNSIGNED NULL DEFAULT NULL',
    'SELECT ''users.salary_grade already exists'' AS status'
);
PREPARE migration_stmt FROM @sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @has_users_rater_position = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @database_name AND TABLE_NAME = 'users' AND COLUMN_NAME = 'rater_position'
);
SET @sql = IF(
    @has_users_rater_position = 0,
    'ALTER TABLE `users` ADD COLUMN `rater_position` VARCHAR(100) NULL DEFAULT NULL',
    'SELECT ''users.rater_position already exists'' AS status'
);
PREPARE migration_stmt FROM @sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

CREATE TABLE IF NOT EXISTS positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    office_scope VARCHAR(40) NOT NULL DEFAULT 'REGIONAL_OFFICE',
    source_sheet VARCHAR(100) NOT NULL DEFAULT '',
    title VARCHAR(255) NOT NULL,
    salary_grade TINYINT UNSIGNED NOT NULL,
    leadership_level CHAR(1) NULL,
    UNIQUE KEY uq_scope_sheet_title (office_scope, source_sheet, title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @has_positions_source_sheet = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @database_name AND TABLE_NAME = 'positions' AND COLUMN_NAME = 'source_sheet'
);
SET @sql = IF(
    @has_positions_source_sheet = 0,
    'ALTER TABLE `positions` ADD COLUMN `source_sheet` VARCHAR(100) NOT NULL DEFAULT '''' AFTER `office_scope`',
    'SELECT ''positions.source_sheet already exists'' AS status'
);
PREPARE migration_stmt FROM @sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @has_old_positions_index = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @database_name
      AND TABLE_NAME = 'positions'
      AND INDEX_NAME = 'uq_scope_title'
);
SET @sql = IF(
    @has_old_positions_index > 0,
    'ALTER TABLE `positions` DROP INDEX `uq_scope_title`',
    'SELECT ''legacy positions index is absent'' AS status'
);
PREPARE migration_stmt FROM @sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @has_positions_index = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @database_name
      AND TABLE_NAME = 'positions'
      AND INDEX_NAME = 'uq_scope_sheet_title'
);
SET @sql = IF(
    @has_positions_index = 0,
    'ALTER TABLE `positions` ADD UNIQUE KEY `uq_scope_sheet_title` (`office_scope`, `source_sheet`, `title`)',
    'SELECT ''positions.uq_scope_sheet_title already exists'' AS status'
);
PREPARE migration_stmt FROM @sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

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

UPDATE users
SET account_type = 'field'
WHERE role <> 'admin'
  AND account_type IS NULL
  AND field_name IS NOT NULL
  AND field_name <> '';

UPDATE users
SET account_type = 'region'
WHERE role <> 'admin'
  AND account_type IS NULL
  AND region IS NOT NULL
  AND region <> '';

CREATE TABLE IF NOT EXISTS form_drafts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    status ENUM('draft','saved') NOT NULL DEFAULT 'draft',
    template_id BIGINT UNSIGNED NULL,
    salary_grade TINYINT UNSIGNED NULL,
    position_title VARCHAR(255),
    office_name VARCHAR(255),
    candidate_name VARCHAR(255),
    form_json LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_user_status_updated (user_id, status, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_reset_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    status ENUM('pending','approved','rejected','used','expired') NOT NULL DEFAULT 'pending',
    token_hash CHAR(64) NULL,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME NULL,
    expires_at DATETIME NULL,
    completed_at DATETIME NULL,
    reviewed_by INT NULL,
    INDEX idx_reset_status_requested (status, requested_at),
    CONSTRAINT fk_password_reset_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SELECT 'users' AS table_name, COLUMN_NAME AS column_name
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @database_name AND TABLE_NAME = 'users'
  AND COLUMN_NAME IN ('email','region','field_name','account_type','salary_grade','rater_position')
UNION ALL
SELECT 'positions', COLUMN_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @database_name AND TABLE_NAME = 'positions'
  AND COLUMN_NAME IN ('office_scope','source_sheet','title','salary_grade','leadership_level');

SELECT office_scope, COUNT(*) AS total
FROM positions
GROUP BY office_scope;
