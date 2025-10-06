import 'dotenv/config'; 
import express from 'express';
import cors from 'cors'; 
import { GoogleGenerativeAI } from '@google/generative-ai'; 
import mysql from 'mysql2/promise'; 

const app = express();
const port = 3000;

// Middleware 1: Parsing JSON
app.use(express.json());

// 1. Inisialisasi API Key dari .env
const API_KEY = process.env.GEMINI_API_KEY; 

if (!API_KEY) {
    throw new Error("GEMINI_API_KEY not found in .env file. Please check your configuration.");
}

// Inisialisasi GoogleGenerativeAI
const ai = new GoogleGenerativeAI(API_KEY);
const geminiModel = ai.getGenerativeModel({ model: "gemini-2.5-pro" }); 
const titleModel = ai.getGenerativeModel({ model: "gemini-2.5-flash" }); // Model baru untuk judul

// 3. KONFIGURASI DAN KONEKSI DATABASE MYSQL
const dbConfig = {
    host: 'localhost',
    user: 'root', // Ganti dengan username MySQL Anda
    password: '', // Ganti dengan password MySQL Anda
    database: 'chatbot'
};

let dbConnection;

async function connectDB() {
    try {
        dbConnection = await mysql.createConnection(dbConfig);
        console.log("Koneksi database MySQL berhasil.");
    } catch (error) {
        console.error("Gagal menghubungkan ke database:", error);
    }
}

connectDB();

// Fungsi bantuan untuk menyimpan pesan ke database (TETAP SAMA)
async function saveMessage(chatId, sender, text) {
    if (!dbConnection) return console.error("Database tidak terhubung. Pesan tidak disimpan.");
    
    try {
        const query = "INSERT INTO messages (chat_id, sender, message_text) VALUES (?, ?, ?)";
        await dbConnection.execute(query, [chatId, sender, text]);
    } catch (error) {
        console.error("Gagal menyimpan pesan ke database:", error);
    }
}

// Fungsi bantuan untuk memperbarui judul chat (TETAP SAMA)
async function updateChatTitle(chatId, newTitle) {
    if (!dbConnection) return console.error("Database tidak terhubung. Judul tidak disimpan.");
    
    try {
        const query = "UPDATE chats SET title = ? WHERE id = ?";
        await dbConnection.execute(query, [newTitle, chatId]);
        console.log(`Judul chat ${chatId} berhasil diperbarui menjadi: ${newTitle}`);
    } catch (error) {
        console.error("Gagal memperbarui judul chat:", error);
    }
}

// --- FUNGSI MENGAMBIL RIWAYAT PESAN UNTUK KONTEKS ---
async function getChatHistory(chatId) {
    if (!dbConnection) {
        console.error("Database tidak terhubung. Riwayat chat tidak dapat dimuat.");
        return [];
    }

    try {
        const [rows] = await dbConnection.execute(
            "SELECT sender, message_text FROM messages WHERE chat_id = ? ORDER BY created_at ASC",
            [chatId]
        );

        // Map data DB ke format riwayat Gemini
        const history = rows.map(row => ({
            role: row.sender === 'user' ? 'user' : 'model', 
            parts: [{ text: row.message_text }]
        }));

        return history;
    } catch (error) {
        console.error("Gagal mengambil riwayat chat dari database:", error);
        return [];
    }
}
// ------------------------------------------------------------------

// 2. Konfigurasi CORS (TETAP SAMA)
const allowedOrigins = [
    'http://localhost', 
    'http://127.0.0.1', 
    'http://127.0.0.1:5500' // Sesuaikan dengan port server PHP Anda
];
app.use(cors({ origin: allowedOrigins }));

// =========================================================================
// ENDPOINT UNTUK MEMBUAT JUDUL CHAT 
// =========================================================================
app.post('/chat/title', async (req, res) => {
    try {
        const { chatId, userMessage, geminiResponse } = req.body;

        if (!chatId || !userMessage || !geminiResponse) {
            return res.status(400).json({ error: "chatId, userMessage, and geminiResponse are required" });
        }

        // Kirim userMessage saja ke model (lebih efisien)
        const prompt = `Buatlah judul yang sangat singkat (maksimal 5 kata) dan deskriptif untuk percakapan chat ini, berdasarkan pesan pertama: "${userMessage}". Balas hanya dengan judulnya saja.`;

        const result = await titleModel.generateContent({ contents: [{ role: "user", parts: [{ text: prompt }] }]});
        
        let newTitle;
        if (result.text) {
            newTitle = result.text.trim().replace(/^['"\s]+|['"\s]+$/g, ''); 
        } else {
            // FALLBACK jika model gagal merespons dengan teks. 
            console.warn(`Gemini-2.5-Flash gagal menghasilkan judul untuk chatId ${chatId}. Menggunakan fallback.`);
            newTitle = userMessage.substring(0, 30) + '...';
        }

        // Pemeriksaan tambahan untuk relevansi judul
        if (newTitle.length < 5 || newTitle.toLowerCase().includes('judul') || newTitle.toLowerCase().includes('berdasarkan pesan pertama')) {
             newTitle = userMessage.substring(0, 30) + '...';
        }
        
        await updateChatTitle(chatId, newTitle);

        return res.json({ success: true, title: newTitle });

    } catch (error) {
        console.error("Error generating or updating title:", error);
        return res.status(500).json({ error: "Failed to generate title." });
    }
});

// =========================================================================
// ENDPOINT CHAT UTAMA 
// =========================================================================
app.post('/chat', async (req, res) => {
    try {
        const { message: userMessage, chatId } = req.body; 

        if (!userMessage || !chatId) { 
            return res.status(400).json({ error: "Message and chatId are required" });
        }
        
        // 1. Ambil riwayat pesan untuk chat ID ini (Konteks)
        const history = await getChatHistory(chatId);

        // 2. Buat sesi chat baru dengan riwayat yang dimuat
        const chatSession = geminiModel.startChat({ history: history });
        
        // Simpan pesan USER ke database sebelum memanggil Gemini
        await saveMessage(chatId, 'user', userMessage);

        // 3. Kirim pesan terbaru ke sesi yang sudah dimuat historinya
        const result = await chatSession.sendMessage(userMessage); 
        const response = result.response;
        let geminiText = '';

        // Cek 1: Berhasil mendapatkan teks
        if (response && response.candidates && response.candidates.length > 0 && response.candidates[0].content && response.candidates[0].content.parts) {
            geminiText = response.candidates[0].content.parts[0].text;
            
            // Simpan respons GEMINI ke database
            await saveMessage(chatId, 'gemini', geminiText);

            return res.json({ text: geminiText });
        } 
        
        // Cek 2 & 3: Tangani blokir atau respons kosong
        let finalMessage = "⚠️ DEBUG ERROR (SERVER): Model gagal memberikan output teks.";
        if (response && response.promptFeedback && response.promptFeedback.blockReason) {
            const blockReason = response.promptFeedback.blockReason;
            finalMessage = `⚠️ Maaf, balasan diblokir karena alasan keamanan (${blockReason}). Silakan ajukan pertanyaan yang berbeda.`;
        }
        
        console.error("DEBUG: Model returned no readable text content.");
        await saveMessage(chatId, 'gemini', finalMessage);

        return res.status(200).json({ 
            text: finalMessage 
        });

    } catch (error) {
        console.error("Gemini API Error (Catch Block):", error);
        
        let errorMessage = "Failed to generate content: " + error.message;
        
        if (error.message.includes("API key not valid")) {
            errorMessage = "API Key Anda terdeteksi, tetapi tidak valid untuk Gemini API. Harap buat kunci baru dari Google AI Studio.";
        }
        
        return res.status(500).json({ error: errorMessage });
    }
});

app.listen(port, () => {
    console.log(`Server Node.js berjalan di http://localhost:${port}`);
    console.log(`Model yang Digunakan untuk Chat: gemini-2.5-pro (Chat) & gemini-2.5-flash (Title)`);
    console.log(`GEMINI_API_KEY yang digunakan: ${API_KEY ? API_KEY.substring(0, 10) + '...' : 'TIDAK ADA'}`);
});