-- Attendance System database schema
CREATE DATABASE IF NOT EXISTS attendance_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE attendance_system;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher') NOT NULL DEFAULT 'teacher',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    subject_code VARCHAR(50) NOT NULL,
    subject_name VARCHAR(150) NOT NULL,
    section VARCHAR(50) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Migration for databases created before is_active/is_deleted existed on subjects.
-- Deleting a subject only ever sets is_deleted = 1 (see teacher/actions/delete_subject.php) -
-- the row and its attendance history are never actually removed, so the ON DELETE CASCADE
-- above is a safety net, not something the app triggers in normal use.
SET @dbname = DATABASE();
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'subjects' AND COLUMN_NAME = 'is_active') > 0,
    'SELECT 1',
    'ALTER TABLE subjects ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'subjects' AND COLUMN_NAME = 'is_deleted') > 0,
    'SELECT 1',
    'ALTER TABLE subjects ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'subjects' AND COLUMN_NAME = 'sort_order') > 0,
    'SELECT 1',
    'ALTER TABLE subjects ADD COLUMN sort_order INT NOT NULL DEFAULT 0'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Give any pre-existing rows (sort_order still at its default of 0) a stable manual
-- order matching what they used to see (active first, newest first), so nothing
-- visibly reshuffles the first time a teacher opens the dashboard after this update.
-- New subjects and drag-reordering (see teacher/actions/reorder_subjects.php) always
-- write sort_order >= 1, so this only ever touches rows that have never been ordered.
UPDATE subjects s
JOIN (
    SELECT id, ROW_NUMBER() OVER (PARTITION BY teacher_id ORDER BY is_active DESC, created_at DESC) AS rn
    FROM subjects
) ranked ON ranked.id = s.id
SET s.sort_order = ranked.rn
WHERE s.sort_order = 0;

CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    student_number VARCHAR(50) NOT NULL,
    surname VARCHAR(100) NOT NULL,
    scanned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
);

-- Default admin account: username "admin", password "admin123"
-- Change this password after first login.
INSERT INTO users (full_name, username, email, password, role)
VALUES ('System Admin', 'admin', 'admin@attendance.local', '$2y$10$PxNWPshfbrJCe6vVwk9MNuTzbPLzF8gODVw794Se72C/Io.X0RLjq', 'admin')
ON DUPLICATE KEY UPDATE username = username;
