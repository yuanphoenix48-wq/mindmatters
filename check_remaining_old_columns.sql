USE mind_matters_db;

-- Check for any remaining tables that might still have old column names
-- This will help identify what else needs to be updated

SELECT 
    TABLE_NAME as 'Table Name',
    COLUMN_NAME as 'Column Name',
    DATA_TYPE as 'Data Type',
    IS_NULLABLE as 'Nullable'
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'mind_matters_db' 
AND (
    COLUMN_NAME LIKE '%student%' 
    OR COLUMN_NAME LIKE '%doctor%'
)
ORDER BY TABLE_NAME, COLUMN_NAME;

-- Also check for any foreign key constraints that might reference old column names
SELECT 
    CONSTRAINT_NAME as 'Constraint Name',
    TABLE_NAME as 'Table Name',
    COLUMN_NAME as 'Column Name',
    REFERENCED_TABLE_NAME as 'Referenced Table',
    REFERENCED_COLUMN_NAME as 'Referenced Column'
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = 'mind_matters_db' 
AND (
    COLUMN_NAME LIKE '%student%' 
    OR COLUMN_NAME LIKE '%doctor%'
    OR REFERENCED_COLUMN_NAME LIKE '%student%'
    OR REFERENCED_COLUMN_NAME LIKE '%doctor%'
);

-- Check if sessions table has the correct new columns
SELECT 
    COLUMN_NAME as 'Column Name',
    DATA_TYPE as 'Data Type',
    IS_NULLABLE as 'Nullable'
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'mind_matters_db' 
AND TABLE_NAME = 'sessions'
AND COLUMN_NAME IN ('client_id', 'therapist_id', 'student_id', 'doctor_id')
ORDER BY COLUMN_NAME;



















































