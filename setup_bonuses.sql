-- Initial bonus programs setup
-- Run this on the server to create sample bonuses

-- Welcome Bonus (Registration)
INSERT INTO bonus_programs (name, type, amount, description, trigger_value, is_enabled, max_claims_per_user)
VALUES ('Welcome Bonus', 'registration', 50.00, 'New player bonus! Get 50 PHP when you register. Valid for 7 days.', NULL, 1, 1)
ON DUPLICATE KEY UPDATE name=name;

-- First Deposit Bonus
INSERT INTO bonus_programs (name, type, amount, description, trigger_value, is_enabled, max_claims_per_user)
VALUES ('First Deposit Bonus', 'deposit', 100.00, 'Deposit 100 PHP or more and get 100 PHP bonus!', 100.00, 1, 1)
ON DUPLICATE KEY UPDATE name=name;

-- Loyalty Bonus
INSERT INTO bonus_programs (name, type, amount, description, trigger_value, is_enabled, max_claims_per_user)
VALUES ('Daily Loyalty Bonus', 'custom', 25.00, 'Daily bonus for our loyal players! Claim once per day.', NULL, 1, 999)
ON DUPLICATE KEY UPDATE name=name;
