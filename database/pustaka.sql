-- Pustaka Digital SMK - skema dan data awal
-- Cara import:  mysql -h <endpoint-rds> -u admin -p < pustaka.sql

CREATE DATABASE IF NOT EXISTS pustaka CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pustaka;

DROP TABLE IF EXISTS loans;
DROP TABLE IF EXISTS books;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS sessions;

CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('member','admin') NOT NULL DEFAULT 'member',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE books (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(200) NOT NULL,
  author      VARCHAR(150) NOT NULL,
  category    VARCHAR(80)  NOT NULL,
  year        SMALLINT UNSIGNED NOT NULL,
  stock       INT UNSIGNED NOT NULL DEFAULT 0,
  cover_file  VARCHAR(255) NULL,
  description TEXT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_books_title (title),
  INDEX idx_books_category (category)
) ENGINE=InnoDB;

CREATE TABLE loans (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  book_id     INT UNSIGNED NOT NULL,
  borrowed_at DATETIME NOT NULL,
  due_date    DATE NOT NULL,
  returned_at DATETIME NULL,
  CONSTRAINT fk_loans_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_loans_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
  INDEX idx_loans_user (user_id, returned_at)
) ENGINE=InnoDB;

-- Session PHP disimpan di database agar login berlaku di semua server di belakang ALB
CREATE TABLE sessions (
  id         VARCHAR(128) NOT NULL PRIMARY KEY,
  data       MEDIUMTEXT NOT NULL,
  updated_at INT UNSIGNED NOT NULL,
  INDEX idx_sessions_updated (updated_at)
) ENGINE=InnoDB;

-- Akun awal: admin@pustaka.test / admin123  dan  siswa@pustaka.test / siswa123
INSERT INTO users (name, email, password_hash, role) VALUES
('Administrator', 'admin@pustaka.test', '$2b$12$g9tlAxqPqppUKII6WOvQB.FEmIVhrNObhChXBYLQe9A2/1txBCLJi', 'admin'),
('Siswa Contoh',  'siswa@pustaka.test', '$2b$12$l0e0fmRqjmz3G3g6Ee7WweNX8f5/42WOXznhiNAQmEX3l.RyTb9q2', 'member');

INSERT INTO books (title, author, category, year, stock, cover_file, description) VALUES
('Dasar-Dasar Pemrograman Web', 'Tim Penulis SMK', 'Pemrograman', 2022, 5, 'cover-01.png', 'Pengenalan HTML, CSS, JavaScript, dan PHP untuk pemula.'),
('Jaringan Komputer untuk SMK', 'Tim Penulis SMK', 'Jaringan', 2021, 4, 'cover-02.png', 'Konsep jaringan, pengalamatan IP, dan konfigurasi dasar.'),
('Cloud Computing untuk Pemula', 'Tim Penulis SMK', 'Cloud', 2024, 6, 'cover-03.png', 'Mengenal layanan komputasi awan: compute, storage, dan database.'),
('Basis Data MySQL', 'Tim Penulis SMK', 'Basis Data', 2020, 3, 'cover-04.png', 'Perancangan tabel, query SQL, dan relasi antar tabel.'),
('Pemrograman Python Dasar', 'Tim Penulis SMK', 'Pemrograman', 2023, 5, 'cover-05.png', 'Variabel, percabangan, perulangan, dan fungsi dengan Python.'),
('Keamanan Siber Dasar', 'Tim Penulis SMK', 'Keamanan', 2023, 2, 'cover-06.png', 'Prinsip CIA, kata sandi yang aman, dan praktik keamanan harian.'),
('Administrasi Sistem Linux', 'Tim Penulis SMK', 'Sistem Operasi', 2022, 4, 'cover-07.png', 'Pengelolaan user, paket, layanan, dan izin file di Linux.'),
('Matematika Kelas X', 'Tim Penulis SMK', 'Matematika', 2021, 8, 'cover-08.png', 'Materi matematika dasar untuk kelas X.'),
('Bahasa Indonesia Kelas XI', 'Tim Penulis SMK', 'Bahasa', 2021, 7, 'cover-09.png', 'Teks laporan, eksposisi, dan surat lamaran kerja.'),
('Sejarah Indonesia', 'Tim Penulis SMK', 'Sejarah', 2020, 0, 'cover-10.png', 'Ringkasan peristiwa penting sejarah Indonesia.');
