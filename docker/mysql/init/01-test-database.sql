-- Runs once, on first start of an empty MySQL volume.
-- The PHPUnit suite uses its own database so tests never touch demo data.
CREATE DATABASE IF NOT EXISTS ride_hailing_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON ride_hailing_test.* TO 'sawari'@'%';
FLUSH PRIVILEGES;
