// server.js (Kode Konsolidasi dengan Perbaikan Penanganan Error)
import 'dotenv/config'; 
import express from 'express';
import cors from 'cors'; 
import { GoogleGenerativeAI } from '@google/generative-ai'; 
import mysql from 'mysql2/promise'; 

const app = express();
const port = 3000;

// Middleware 
app.use(express.json({ limit: '500mb' })); 
app.use(cors());

// 1. Inisialisasi API Key dan Model
const API_KEY = process.env.GEMINI_API_KEY; 
if (!API_KEY) {
    throw new Error("GEMINI_API_KEY not found in .env file. Please check your configuration.");
}
const ai = new GoogleGenerativeAI(API_KEY);
const geminiModel = ai.getGenerativeModel({ model: "gemini-2.5-flash" }); 
const titleModel = ai.getGenerativeModel({ model: "gemini-2.5-flash" }); 

// 2. KONFIGURASI DAN KONEKSI DATABASE MYSQL (MENGGUNAKAN POOL)
const dbConfig = {
    host: 'localhost',
    user: 'root', 
    password: '', 
    database: 'chatbot', 
    waitForConnections: true,
    connectionLimit: 10, // Menggunakan nilai yang lebih wajar
    queueLimit: 0,
};

let dbPool; 

async function connectDB() {
    try {
        dbPool = mysql.createPool(dbConfig); 
        await dbPool.query("SELECT 1"); 
        console.log("Koneksi database MySQL (Pool) berhasil.");
    } catch (error) {
        console.error("Gagal menghubungkan ke database:", error);
        process.exit(1);
    }
}
connectDB();

// -------------------------------------------------------------
// FUNGSI UTILITY (MODIFIKASI: MELEMPAR ULANG ERROR)
// -------------------------------------------------------------

async function saveMessage(chatId, sender, text, fileData = null) {
    if (!dbPool) {
        const error = new Error("Database Pool tidak terhubung. Pesan tidak disimpan.");
        console.error(error);
        throw error; // Lempar ulang error
    }
    
    // Pastikan data yang masuk adalah string kosong jika null (untuk kompatibilitas TEXT)
    const fileBase64 = fileData ? JSON.stringify(fileData) : null;
    
    try {
        const query = "INSERT INTO messages (chat_id, sender, message_text, file_data) VALUES (?, ?, ?, ?)";
        await dbPool.execute(query, [chatId, sender, text, fileBase64]); 
    } catch (error) {
        console.error("Gagal menyimpan pesan ke database:", error);
        throw error; // KRITIS: Lempar ulang error agar ditangkap oleh endpoint utama
    }
}

async function updateChatTitle(chatId, newTitle) {
    if (!dbPool) return console.error("Database Pool tidak terhubung. Judul tidak disimpan.");
    
    try {
        const query = "UPDATE chats SET title = ? WHERE id = ?";
        await dbPool.execute(query, [newTitle, chatId]);
        console.log(`Judul chat ${chatId} berhasil diperbarui menjadi: ${newTitle}`);
    } catch (error) {
        console.error("Gagal memperbarui judul chat:", error);
        // Tidak perlu throw di sini karena kegagalan title tidak membatalkan chat
    }
}

async function getChatHistory(chatId) {
    if (!dbPool) {
        const error = new Error("Database Pool tidak terhubung. Riwayat chat tidak dapat dimuat.");
        console.error(error);
        throw error; // Lempar ulang error
    }
    
    try {
        const [rows] = await dbPool.execute(
            "SELECT id, sender, message_text, file_data FROM messages WHERE chat_id = ? ORDER BY id ASC",
            [chatId]
        );

        const history = [];
        for (const row of rows) {
            let parts = [];
            
            parts.push({ text: row.message_text });

            if (row.file_data) {
                try {
                    // Pastikan file_data di-parse. Jika ini error, berarti data di DB rusak.
                    const fileData = JSON.parse(row.file_data); 
                    parts.push({
                        inlineData: {
                            data: fileData.base64Data,
                            mimeType: fileData.mimeType,
                        }
                    });
                } catch (e) {
                    console.error(`Gagal parse file data pesan ID ${row.id}:`, e);
                    // Jika parsing file gagal, pesan tetap dikirim tanpa file
                }
            }

            history.push({
                role: row.sender === 'user' ? 'user' : 'model',
                parts: parts,
                message_id: row.id 
            });
        }
        return history;
    } catch (error) {
        console.error("Gagal mengambil riwayat chat dari database:", error);
        throw error; // KRITIS: Lempar ulang error agar ditangkap oleh endpoint utama
    }
}


// -------------------------------------------------------------
// ENDPOINT CHAT UTAMA
// -------------------------------------------------------------
app.post('/chat', async (req, res) => {
    try {
        const { message: userMessage, chatId, fileData, messageIdToUpdate } = req.body; 

        // -------------------------------------------------------------
        // LOGIKA PESAN BARU (JIKA BUKAN EDIT/UPDATE)
        // -------------------------------------------------------------
        if (!messageIdToUpdate) { 
            if (!userMessage && !fileData) { 
                return res.status(400).json({ error: "Message or fileData is required" });
            }
            if (!chatId) {
                return res.status(400).json({ error: "chatId is required" });
            }
            
            // Simpan pesan USER baru ke database
            // Jika ini gagal (e.g., chatID tidak valid), error akan di-throw dan ditangkap di bawah.
            await saveMessage(chatId, 'user', userMessage || '', fileData);
        }
        
        // 1. Ambil riwayat pesan untuk chat ID ini 
        // Jika ini gagal, error akan di-throw dan ditangkap di bawah.
        const history = await getChatHistory(chatId);
        
        if (history.length === 0) {
            // Ini seharusnya hanya terjadi jika DB error atau chatId sangat baru dan tidak valid
            return res.status(400).json({ error: "Chat history is empty or invalid." });
        }
        
        // Dapatkan 'parts' dari pesan user terakhir di history
        const newParts = history[history.length - 1].parts; 
        
        // Riwayat untuk sesi adalah SEMUA pesan kecuali pesan user terakhir
        const chatHistoryForSession = history.slice(0, -1); 

        // 2. Buat sesi chat dengan riwayat sebelumnya
        const chatSession = geminiModel.startChat({ history: chatHistoryForSession });

        // 3. Kirim pesan terbaru ke sesi (dengan retry)
        const MAX_RETRIES = 3;
        let result;
        
        for (let attempt = 0; attempt < MAX_RETRIES; attempt++) {
            try {
                console.log(`Percobaan API ke-${attempt + 1} untuk chatId ${chatId}...`);
                result = await chatSession.sendMessage({ parts: newParts }); 
                break; 
            } catch (error) {
                console.error(`Gagal menghubungi Gemini (Percobaan ${attempt + 1}):`, error.message);
                if (attempt === MAX_RETRIES - 1) throw error; 
                await new Promise(resolve => setTimeout(resolve, 2000));
            }
        }

        // 4. Proses dan Simpan Respons Gemini yang baru
        const response = result.response;
        let geminiText = '';

        if (response && response.candidates && response.candidates.length > 0 && response.candidates[0].content && response.candidates[0].content.parts) {
            geminiText = response.candidates[0].content.parts[0].text;
            
            // Simpan respons GEMINI baru ke database
            await saveMessage(chatId, 'gemini', geminiText);

            return res.json({ text: geminiText });
        } else {
             // Tangani blokir atau respons kosong
             return res.status(500).json({ error: "Gemini response was empty or blocked." });
        }

    } catch (error) {
        // BLOK INI SEKARANG AKAN MENANGKAP SEMUA ERROR DARI saveMessage, getChatHistory, dan Gemini API
        console.error("Error utama saat memproses chat:", error);
        
        // Logika tambahan untuk menentukan jenis error database
        const details = error.code && error.sqlMessage 
            ? `SQL Error ${error.code}: ${error.sqlMessage}` 
            : error.message;

        return res.status(500).json({ 
            error: "Gagal memproses chat. Silakan cek konsol server Anda untuk rincian.",
            details: details
        });
    }
});

app.listen(port, () => {
    console.log(`Server Node.js berjalan di http://localhost:${port}`);
});