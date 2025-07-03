-- Add payment deadline tracking columns to credit_accounts table
ALTER TABLE credit_accounts
ADD COLUMN last_payment_date DATE DEFAULT NULL,
ADD COLUMN payment_due_date DATE DEFAULT NULL,
ADD COLUMN auto_block_date DATE DEFAULT NULL,
ADD COLUMN is_auto_blocked BOOLEAN DEFAULT FALSE;

-- Add index for performance
CREATE INDEX idx_payment_due_date ON credit_accounts(payment_due_date);
CREATE INDEX idx_auto_block_date ON credit_accounts(auto_block_date); 