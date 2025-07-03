ALTER TABLE credit_account_requests 
ADD COLUMN approved_amount DECIMAL(10,2) DEFAULT NULL AFTER requested_limit; 