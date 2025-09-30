-- MySQL schema for the chatbot application-- Run this script in your MySQL client to create the necessary database and tables.-- Create the database
CREATE DATABASE IF NOT EXISTS chatbot 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE chatbot;-- users table to store user information
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY, 
    username VARCHAR(100) NOT NULL UNIQUE, 
    email VARCHAR(255) NOT NULL UNIQUE, 
    password VARCHAR(255) NOT NULL, 
    phone_number VARCHAR(255) NOT NULL, 
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;-- chats table to store each conversation history