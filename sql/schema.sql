CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL,
  mobile VARCHAR(20) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','supervisor','member','owner','customer') NOT NULL DEFAULT 'customer',
  market_id INT NULL,
  token VARCHAR(64) NULL,
  status ENUM('active','inactive','blocked','removed') NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_users_token ON users(token);