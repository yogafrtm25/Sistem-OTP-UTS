USE sistem_otp;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(15) NOT NULL,
    locked_until DATETIME NULL

);

CREATE TABLE otp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(255),
    otp_code VARCHAR(6) NOT NULL,
    expires_at DATETIME NOT NULL,
    failed_attempts INT DEFAULT 0,
    locked_until DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

