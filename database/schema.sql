-- =====================================================================
-- Boarding House Rental System — database schema (MySQL 8 / MariaDB 10.4+)
--
-- HOW TO RUN (MySQL Workbench):
--   1. Connect to your local MySQL server.
--   2. File > Open SQL Script... > choose this file.
--   3. Click the lightning bolt (Execute) button.
--   4. Then do the same with database/seed.sql to add sample data.
--
-- WARNING: this DROPS and recreates the `bhsystem` database, deleting all
-- existing data in it. Only run it for a fresh setup.
-- =====================================================================

DROP DATABASE IF EXISTS bhsystem;
CREATE DATABASE bhsystem CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bhsystem;

-- ---------------------------------------------------------------------
-- Accounts
-- role:            tenant | landlord | admin | super_admin
-- approval_status: landlords start as 'pending' until an admin approves
-- ---------------------------------------------------------------------
CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(30) NOT NULL DEFAULT 'tenant',
  first_name VARCHAR(80) NULL,
  middle_name VARCHAR(80) NULL,
  last_name VARCHAR(80) NULL,
  gender VARCHAR(20) NULL,
  birth_date DATE NULL,
  phone VARCHAR(20) NULL,
  address VARCHAR(255) NULL,
  approval_status ENUM('approved', 'pending', 'rejected') NOT NULL DEFAULT 'approved',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  failed_login_attempts INT NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_role (role),
  INDEX idx_users_approval (approval_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Extra business details collected when a landlord registers
CREATE TABLE landlord_profiles (
  user_id INT UNSIGNED PRIMARY KEY,
  property_name VARCHAR(150) NOT NULL,
  business_address VARCHAR(255) NOT NULL,
  barangay VARCHAR(100) NOT NULL,
  municipality VARCHAR(100) NOT NULL,
  province VARCHAR(100) NOT NULL,
  CONSTRAINT fk_landlord_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Identity / business documents uploaded at registration (files live in storage/documents)
CREATE TABLE user_documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  doc_type VARCHAR(40) NOT NULL,
  file_name VARCHAR(100) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_documents_user (user_id),
  CONSTRAINT fk_documents_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Listings: a landlord owns boarding houses; each house has rooms
-- ---------------------------------------------------------------------
CREATE TABLE boarding_houses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  landlord_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  address VARCHAR(255) NOT NULL,
  barangay VARCHAR(100) NOT NULL,
  city VARCHAR(100) NOT NULL,
  province VARCHAR(100) NOT NULL,
  nearby_school VARCHAR(150) NULL,
  distance_to_school VARCHAR(30) NULL,
  latitude DECIMAL(9,6) NULL,
  longitude DECIMAL(9,6) NULL,
  gender_policy ENUM('any', 'male', 'female') NOT NULL DEFAULT 'any',
  house_rules TEXT NULL,
  contact_phone VARCHAR(20) NULL,
  status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_houses_landlord (landlord_id),
  INDEX idx_houses_city (city),
  CONSTRAINT fk_houses_landlord FOREIGN KEY (landlord_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- room_type: shared | solo | dormitory | family
-- status:    available | full | hidden
CREATE TABLE rooms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  boarding_house_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  room_type VARCHAR(20) NOT NULL DEFAULT 'shared',
  monthly_rent DECIMAL(10,2) NOT NULL,
  deposit DECIMAL(10,2) NOT NULL DEFAULT 0,
  advance_payment DECIMAL(10,2) NOT NULL DEFAULT 0,
  capacity INT UNSIGNED NOT NULL DEFAULT 1,
  available_slots INT UNSIGNED NOT NULL DEFAULT 1,
  size_sqm DECIMAL(6,2) NULL,
  amenities TEXT NULL COMMENT 'Comma-separated list, e.g. WiFi,Aircon,Study Table',
  description TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'available',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_rooms_house (boarding_house_id),
  INDEX idx_rooms_type (room_type),
  INDEX idx_rooms_rent (monthly_rent),
  CONSTRAINT fk_rooms_house FOREIGN KEY (boarding_house_id) REFERENCES boarding_houses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- path is either a local file under uploads/rooms/ or a full https:// URL
CREATE TABLE room_photos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id INT UNSIGNED NOT NULL,
  path VARCHAR(500) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_photos_room (room_id),
  CONSTRAINT fk_photos_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Tenant activity
-- booking status: pending | approved | rejected | cancelled
-- ---------------------------------------------------------------------
CREATE TABLE bookings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id INT UNSIGNED NOT NULL,
  tenant_id INT UNSIGNED NOT NULL,
  move_in_date DATE NOT NULL,
  message TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  landlord_note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_bookings_room (room_id),
  INDEX idx_bookings_tenant (tenant_id),
  INDEX idx_bookings_status (status),
  CONSTRAINT fk_bookings_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
  CONSTRAINT fk_bookings_tenant FOREIGN KEY (tenant_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE favorites (
  user_id INT UNSIGNED NOT NULL,
  room_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, room_id),
  CONSTRAINT fk_favorites_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_favorites_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One review per tenant per room, allowed after an approved booking
CREATE TABLE reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id INT UNSIGNED NOT NULL,
  tenant_id INT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  comment TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_review_tenant_room (tenant_id, room_id),
  INDEX idx_reviews_room (room_id),
  CONSTRAINT fk_reviews_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
  CONSTRAINT fk_reviews_tenant FOREIGN KEY (tenant_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Security
-- ---------------------------------------------------------------------
CREATE TABLE audit_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  ip_address VARCHAR(45) NOT NULL,
  action VARCHAR(50) NOT NULL,
  details TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_user (user_id),
  INDEX idx_audit_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE password_resets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_reset_token (token),
  CONSTRAINT fk_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
