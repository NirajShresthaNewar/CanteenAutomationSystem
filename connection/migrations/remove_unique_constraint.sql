-- Remove the unique constraint from credit_account_requests
ALTER TABLE credit_account_requests
DROP INDEX user_vendor; 