-- Add default meet link field for doctors
ALTER TABLE users ADD COLUMN IF NOT EXISTS default_meet_link VARCHAR(500) NULL AFTER verification_expires;

-- Add index for better performance
CREATE INDEX IF NOT EXISTS idx_default_meet_link ON users(default_meet_link);

