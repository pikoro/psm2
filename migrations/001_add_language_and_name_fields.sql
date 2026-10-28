-- Migration: Add language, first_name, last_name columns to users table
-- Date: 2026-10-28

ALTER TABLE users ADD COLUMN IF NOT EXISTS first_name VARCHAR(100) DEFAULT '' AFTER bio;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_name VARCHAR(100) DEFAULT '' AFTER first_name;
ALTER TABLE users ADD COLUMN IF NOT EXISTS language VARCHAR(10) DEFAULT 'en' AFTER paypal_email;

-- Add index for language lookups
CREATE INDEX IF NOT EXISTS idx_users_language ON users(language);
