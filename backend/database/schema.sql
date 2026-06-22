-- ============================================
-- UniEvent Database Schema
-- For MySQL 5.7+ / MariaDB 10.3+ / TiDB Cloud
-- Run this ONCE to set up the database tables.
-- Seed data is inserted by `php database/seed.php`
-- (so passwords can be hashed with PHP's password_hash()).
--
-- ─── TiDB Cloud (free tier) note ───────────────────────────────────────────
-- TiDB Cloud's free serverless tier typically gives you a single pre-created
-- database (e.g. `sys` or `test`). In that case COMMENT OUT the next two
-- lines (`CREATE DATABASE` + `USE`) and import the tables directly into
-- that database. Then set DB_NAME=<that database> in your backend `.env`.
--
-- For a normal MySQL server (XAMPP/MAMP/VPS), leave the lines below enabled.
-- ============================================

CREATE DATABASE IF NOT EXISTS unievent_db;
USE unievent_db;

-- ============================================
-- 1. USERS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('student', 'organizer', 'admin') NOT NULL DEFAULT 'student',
  avatar VARCHAR(10) DEFAULT '',
  avatar_color VARCHAR(30) DEFAULT 'bg-indigo-500',
  phone VARCHAR(20) DEFAULT '',
  bio TEXT DEFAULT '',
  student_id VARCHAR(30) DEFAULT '',
  department VARCHAR(100) DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 2. EVENTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS events (
  event_id INT AUTO_INCREMENT PRIMARY KEY,
  organizer_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT NOT NULL,
  category ENUM('Technology', 'Career', 'Academic', 'Workshop', 'Seminar', 'Sports', 'Cultural', 'Community Service', 'Arts', 'Entertainment') NOT NULL,
  event_date DATE NOT NULL,
  start_time VARCHAR(20) DEFAULT '9:00 AM',
  end_time VARCHAR(20) DEFAULT '5:00 PM',
  venue VARCHAR(200) NOT NULL,
  capacity INT NOT NULL DEFAULT 100,
  available_seats INT NOT NULL DEFAULT 100,
  price VARCHAR(50) DEFAULT 'Free',
  image_url VARCHAR(500) DEFAULT '',
  status ENUM('open', 'closed', 'cancelled') DEFAULT 'open',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (organizer_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 3. BOOKINGS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS bookings (
  booking_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  event_id INT NOT NULL,
  ticket_quantity INT NOT NULL DEFAULT 1,
  booking_status ENUM('confirmed', 'pending_payment', 'cancelled', 'payment_failed') DEFAULT 'confirmed',
  amount DECIMAL(10,2) DEFAULT 0.00,
  booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 4. PAYMENTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS payments (
  payment_id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL,
  user_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  payment_method VARCHAR(50) DEFAULT 'card',
  payment_status ENUM('successful', 'failed', 'refunded') DEFAULT 'successful',
  payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 5. FORUM POSTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS forum_posts (
  post_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  event_id INT DEFAULT NULL,
  title VARCHAR(250) NOT NULL,
  content TEXT NOT NULL,
  author VARCHAR(100) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 6. COMMENTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS comments (
  comment_id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  user_id INT NOT NULL,
  comment_text TEXT NOT NULL,
  author VARCHAR(100) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES forum_posts(post_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 7. FEEDBACK TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS feedback (
  feedback_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  event_id INT NOT NULL,
  rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
  review TEXT NOT NULL,
  author VARCHAR(100) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 8. NOTIFICATIONS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS notifications (
  notification_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  message TEXT NOT NULL,
  notification_type ENUM('info', 'success', 'warning') DEFAULT 'info',
  is_read BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_notifications_user_read_created (user_id, is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 9. CALENDAR EVENTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS calendar_events (
  calendar_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  event_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  calendar_date DATE NOT NULL,
  start_time VARCHAR(20) DEFAULT '',
  end_time VARCHAR(20) DEFAULT '',
  venue VARCHAR(200) DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
  UNIQUE KEY uq_calendar_user_event (user_id, event_id),
  INDEX idx_calendar_user_date (user_id, calendar_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 10. EVENT AGENDA ITEMS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS event_agenda_items (
  agenda_id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT DEFAULT '',
  start_time VARCHAR(20) NOT NULL,
  end_time VARCHAR(20) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
  INDEX idx_agenda_event_time (event_id, start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
