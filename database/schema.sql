-- Product Management System - database schema for Aiven MySQL
-- Run this in the Aiven console / any MySQL client against your database (default: defaultdb).

CREATE TABLE IF NOT EXISTS products (
  id           INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  product_name VARCHAR(100)   NOT NULL,
  description  TEXT,
  price        DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  quantity     INT            NOT NULL DEFAULT 0,
  created_at   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Used by the LavaLust API library for login
CREATE TABLE IF NOT EXISTS users (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username   VARCHAR(50)  NOT NULL,
  email      VARCHAR(100) NOT NULL,
  password   VARCHAR(255) NOT NULL,
  role       ENUM('admin','moderator','user') NOT NULL DEFAULT 'user',
  is_active  TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL,
  UNIQUE KEY username_unique (username),
  KEY email_idx (email),
  KEY role_idx (role)
);

-- Used by the LavaLust API library to store hashed refresh tokens
CREATE TABLE IF NOT EXISTS refresh_tokens (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  token      TEXT NOT NULL,
  expires_at DATETIME NOT NULL,
  jti        TEXT NOT NULL,
  KEY user_id_idx (user_id)
);

-- Demo login: username "admin" / password "Admin@123"  (bcrypt hash below)
INSERT INTO users (username, email, password, role)
VALUES ('admin', 'admin@example.com', '$2y$10$LtHI3/9AQljnHS6v2ZH9jO.55msikxVqpu8ZFCzD8NzlsXV52jQD.', 'admin');

-- Sample products
INSERT INTO products (product_name, description, price, quantity) VALUES
  ('Wireless Mouse', '2.4GHz ergonomic wireless mouse', 599.00, 25),
  ('Mechanical Keyboard', 'Blue-switch keyboard with backlight', 1899.50, 10),
  ('USB-C Hub', '6-in-1 hub with HDMI and card reader', 1250.00, 15);
