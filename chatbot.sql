-- MySQL schema for the chatbot application
-- Pastikan Anda sudah menjalankan ini di MySQL client Anda.

-- Create the database
CREATE DATABASE IF NOT EXISTS chatbot 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE chatbot;

-- 1. users table to store user information (Asumsi sudah ada dari kode login/register)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY, 
    username VARCHAR(100) NOT NULL UNIQUE, 
    email VARCHAR(255) NOT NULL UNIQUE, 
    password VARCHAR(255) NOT NULL,
    phone_number VARCHAR(255) NOT NULL, 
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. chats table to store each conversation history
CREATE TABLE IF NOT EXISTS chats (
    id INT AUTO_INCREMENT PRIMARY KEY, 
    user_id INT NOT NULL,
    title VARCHAR(255) DEFAULT 'Chat Baru...', -- Diubah dari 'New Chat' agar sesuai dengan save_new_chat.php
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. messages table to store individual messages
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id INT NOT NULL,
    sender ENUM('user', 'gemini') NOT NULL,
    message_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE -- KRITIS: Penghapusan chat akan menghapus semua pesan terkait
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;