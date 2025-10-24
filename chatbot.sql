-- chatbot.sql

-- Create the database
CREATE DATABASE IF NOT EXISTS chatbot 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE chatbot;

-- 1. users table: Ditambahkan kolom profile_picture
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY, 
    username VARCHAR(100) NOT NULL UNIQUE, 
    email VARCHAR(255) NOT NULL UNIQUE, 
    password VARCHAR(255) NOT NULL,
    phone_number VARCHAR(255) NOT NULL, 
    profile_picture VARCHAR(255) DEFAULT NULL, -- Ditambahkan untuk gambar profil
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. chats table
CREATE TABLE IF NOT EXISTS chats (
    id INT AUTO_INCREMENT PRIMARY KEY, 
    user_id INT NOT NULL,
    title VARCHAR(255) DEFAULT 'Chat Baru...',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. messages table: Dimodifikasi untuk dukungan lampiran/berkas (Multimodal)
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id INT NOT NULL,
    sender ENUM('user', 'gemini') NOT NULL,
    message_text TEXT NOT NULL,
    file_path VARCHAR(255) DEFAULT NULL, -- Kolom untuk jalur/Data URI berkas yang diunggah
    file_mime_type VARCHAR(50) DEFAULT NULL, -- Kolom untuk tipe MIME berkas
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;