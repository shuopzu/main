-- Add email-based login and password recovery to an existing installation.
-- Select the application's database before running this file.
-- Safe to run repeatedly; existing users and data are preserved.

SET @database_name = DATABASE();

SET @has_users_email = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @database_name
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'email'
);

SET @add_users_email_sql = IF(
    @has_users_email = 0,
    'ALTER TABLE `users` ADD COLUMN `email` VARCHAR(254) NULL DEFAULT NULL',
    'SELECT ''users.email already exists'' AS status'
);

PREPARE add_users_email_stmt FROM @add_users_email_sql;
EXECUTE add_users_email_stmt;
DEALLOCATE PREPARE add_users_email_stmt;

SET @has_unique_email_index = (
    SELECT COUNT(DISTINCT INDEX_NAME)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @database_name
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'email'
      AND NON_UNIQUE = 0
);

SET @add_unique_email_index_sql = IF(
    @has_unique_email_index = 0,
    'CREATE UNIQUE INDEX `uq_users_email` ON `users` (`email`)',
    'SELECT ''a unique users.email index already exists'' AS status'
);

PREPARE add_unique_email_index_stmt FROM @add_unique_email_index_sql;
EXECUTE add_unique_email_index_stmt;
DEALLOCATE PREPARE add_unique_email_index_stmt;

SHOW COLUMNS FROM `users` LIKE 'email';
SHOW INDEX FROM `users` WHERE `Column_name` = 'email';
