-- Add school column to users table if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS school VARCHAR(255) DEFAULT NULL;

-- Create workers table if it doesn't exist
CREATE TABLE IF NOT EXISTS workers (
    id BIGINT(20) NOT NULL AUTO_INCREMENT,
    user_id BIGINT(20) NOT NULL,
    position VARCHAR(50) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
); 