import 'dotenv/config'; 
import express from 'express';
import cors from 'cors'; 
import { GoogleGenerativeAI } from '@google/generative-ai'; 
import mysql from 'mysql2/promise'; 

const app = express();
const port = 3000;

// Middleware 1: Parsing JSON (Ditingkatkan untuk batasan payload yang lebih besar untuk file)
// Batas 500mb untuk mengizinkan unggahan file yang lebih besar
app.use(express.json({ limit: '500mb' })); 

// 1. Inisialisasi API Key dari .env
const API_KEY = process.env.GEMINI_API_KEY; 

if (!API_KEY) {
    throw new Error("GEMINI_API_KEY not found in .env file. Please check your configuration.");
}

// Inisialisasi GoogleGenerativeAI
const ai = new GoogleGenerativeAI(API_KEY);
// **PERUBAHAN:** Menggunakan gemini-2.5-flash untuk stabilitas dan kecepatan multimodalitas
const geminiModel = ai.getGenerativeModel({ model: "gemini-2.5-flash" }); 
const titleModel = ai.getGenerativeModel({ model: "gemini-2.5-flash" }); 

// 3. KONFIGURASI DAN KONEKSI DATABASE MYSQL
const dbConfig = {
    host: 'localhost',
    user: 'root', 
    password: '', 
    database: 'chatbot', 
    waitForConnections: true,
    connectionLimit: 1000,
    queueLimit: 0,

};

let dbPool;

async function connectDB() {
    try {
        // Gunakan createPool()
        dbPool = mysql.createPool(dbConfig);
        // Tes koneksi pool
        await dbPool.query("SELECT 1");
        console.log("Koneksi database MySQL (Pool) berhasil.");
    } catch (error) {
        console.error("Gagal menghubungkan ke database:", error);
        process.exit(1);
    }   
}

connectDB();

// Fungsi bantuan untuk menyimpan pesan ke database (DIPERBARUI untuk mendukung file)
async function saveMessage(chatId, sender, text, fileData = null) {
    // Cek dengan dbPool
    if (!dbPool) return console.error("Database Pool tidak terhubung. Pesan tidak disimpan.");
    
    // Simpan data file (base64) jika ada...
    const fileBase64 = fileData ? JSON.stringify(fileData) : null;

    try {
        const query = "INSERT INTO messages (chat_id, sender, message_text, file_data) VALUES (?, ?, ?, ?)";
        // Gunakan dbPool
        await dbPool.execute(query, [chatId, sender, text, fileBase64]);
    } catch (error) {
        console.error("Gagal menyimpan pesan ke database:", error);
    }
}

// Fungsi bantuan untuk memperbarui judul chat (TETAP SAMA)
async function updateChatTitle(chatId, newTitle) {
// Cek dengan dbPool
    if (!dbPool) return console.error("Database Pool tidak terhubung. Judul tidak disimpan.");
    
    try {
        const query = "UPDATE chats SET title = ? WHERE id = ?";
        // Gunakan dbPool
        await dbPool.execute(query, [newTitle, chatId]);
        console.log(`Judul chat ${chatId} berhasil diperbarui menjadi: ${newTitle}`);
    } catch (error) {
        console.error("Gagal memperbarui judul chat:", error);
    }
}

// --- FUNGSI MENGAMBIL RIWAYAT PESAN UNTUK KONTEKS (DIPERBARUI) ---
async function getChatHistory(chatId) {
    if (!dbPool) {
        console.error("Database Pool tidak terhubung. Riwayat chat tidak dapat dimuat.");
        return [];
    }

    try {
        // Ambil file_data juga, dan JUGA ID PESAN (PENTING untuk fitur Edit)
        const [rows] = await dbPool.execute(
            "SELECT id, sender, message_text, file_data FROM messages WHERE chat_id = ? ORDER BY created_at ASC",
            [chatId]
        );

        const history = rows.map(row => {
            let parts = [];
            
            // 1. Tambahkan data file jika ada (file_data disimpan sebagai string JSON)
            if (row.file_data) {
                try {
                    const file = JSON.parse(row.file_data);
                    if (file.mimeType && file.data) {
                        parts.push({
                            inlineData: {
                                data: file.data, // Base64
                                mimeType: file.mimeType 
                            }
                        });
                    }
                } catch (e) {
                    // Abaikan jika file_data tidak valid, tetapi logged
                    console.error("Gagal parse file_data dari riwayat:", e);
                }
            }
            
            // 2. Tambahkan pesan teks (selalu ada)
            // Cek jika ada file, teks mungkin kosong (hanya unggah file)
            if (row.message_text && row.message_text.trim() !== '') {
                 parts.push({ text: row.message_text });
            } else if (parts.length === 0) {
                 // Jika tidak ada teks dan tidak ada file, ini pesan yang tidak valid untuk history
                 return null;
            }

            return {
                role: row.sender === 'user' ? 'user' : 'model', 
                parts: parts
            };
        }).filter(item => item !== null); // Hapus entri null

        return history;
    } catch (error) {
        console.error("Gagal mengambil riwayat chat dari database:", error);
        return [];
    }
}

// ------------------------------------------------------------------
// FUNGSI BANTUAN UNTUK MULTIMODALITAS
// Mengonversi objek file dari frontend ke format 'Part' Gemini
function fileToGenerativePart(file) {
    return {
        inlineData: {
            data: file.data, // Base64
            mimeType: file.mimeType
        },
    };
}
// ------------------------------------------------------------------

// 2. Konfigurasi CORS (TETAP SAMA)
const allowedOrigins = [
    'http://localhost', 
    'http://127.0.0.1', 
    'http://127.0.0.1:5500' 
];
app.use(cors({ origin: allowedOrigins }));

async function deleteOldGeminiResponse(userMessageId) {
    if (!dbPool) return console.error("Database Pool tidak terhubung.");
    
    try {
        // Asumsi: Gemini response adalah pesan berikutnya setelah userMessageId
        // Dapatkan ID pesan Gemini yang terkait
        const [rows] = await dbPool.execute(
            `
            SELECT m.id 
            FROM messages m
            JOIN messages user_m ON m.chat_id = user_m.chat_id 
            AND m.created_at > user_m.created_at
            WHERE user_m.id = ? AND m.sender = 'gemini'
            ORDER BY m.id ASC LIMIT 1
            `,
            [userMessageId]
        );
        
        if (rows.length > 0) {
            const geminiIdToDelete = rows[0].id;
            const query = "DELETE FROM messages WHERE id = ?";
            await dbPool.execute(query, [geminiIdToDelete]);
            console.log(`Pesan Gemini ID ${geminiIdToDelete} yang lama dihapus.`);
            return true;
        }
        return false;
    } catch (error) {
        console.error("Gagal menghapus pesan Gemini lama:", error);
        return false;
    }
}

// =========================================================================
// ENDPOINT CHAT UTAMA (DIPERBARUI UNTUK MULTIMODALITAS & RETRY)
// =========================================================================
app.post('/chat', async (req, res) => {
    try {
        const { message: userMessage, chatId, fileData, messageIdToUpdate } = req.body; // MENAMBAH messageIdToUpdate

        if (!messageIdToUpdate) {
            await deleteOldGeminiResponse(messageIdToUpdate);
        } else {
            if (!userMessage && !fileData) { 
                return res.status(400).json({ error: "Message or fileData is required" });
            }
            if (!chatId) {
                return res.status(400).json({ error: "chatId is required" });
            }
            await saveMessage(chatId, 'user', userMessage || '', fileData);
        }

        const history = await getChatHistory(chatId);

        if (history.length === 0) {
            return res.status(400).json({ error: "Chat history is empty or invalid." });
        }        
        
        const newParts = history[history.length - 1].parts;
        
        const chatHistoryForSession = history.slice(0, -1); 

        // SOLUSI: Riwayat pesan digunakan untuk membuat sesi chat yang berkesinambungan
        // Gunakan riwayat yang sudah dipotong untuk sesi
        const chatSession = geminiModel.startChat({ history: chatHistoryForSession });

        // Tambahkan file ke parts jika ada
        if (fileData) {
            newParts.push(fileToGenerativePart(fileData));
            console.log(`Mengirim file: ${fileData.mimeType.split('/')[0]} (tipe: ${fileData.mimeType})`);
        }
        
        const MAX_RETRIES = 3;
        const RETRY_DELAY_MS = 2000;
        let result;
        
        for (let attempt = 0; attempt < MAX_RETRIES; attempt++) {
            try {
                console.log(`Percobaan API ke-${attempt + 1}...`);
                result = await chatSession.sendMessage({parts: newParts});                 
                break; 
                
            } catch (error) {
                if (error.message.includes('503 Service Unavailable') && attempt < MAX_RETRIES - 1) {
                    console.warn(`[503] Model Overloaded. Mencoba lagi dalam ${RETRY_DELAY_MS}ms...`);
                    await new Promise(resolve => setTimeout(resolve, RETRY_DELAY_MS));
                    continue; 
                }
                
                // Untuk error lain (500, 400, dll.) atau percobaan terakhir
                throw error; 
            }
        }
        
        const response = result.response;
        let geminiText = '';

        // Cek 1: Berhasil mendapatkan teks
        if (response && response.candidates && response.candidates.length > 0 && response.candidates[0].content && response.candidates[0].content.parts) {
            geminiText = response.candidates[0].content.parts[0].text;
            
            // Simpan respons GEMINI ke database (tidak ada file)
            await saveMessage(chatId, 'gemini', geminiText); // Simpan balasan baru

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
        
        // Kirim 500 jika ada error yang tidak tertangani (selain 503 yang sudah dicoba ulang)
        return res.status(500).json({ error: errorMessage });
    }
});

// =========================================================================
// ENDPOINT UNTUK MEMBUAT JUDUL CHAT (TETAP SAMA)
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

app.listen(port, () => {
    console.log(`Server Node.js berjalan di http://localhost:${port}`);
    console.log(`Model yang Digunakan untuk Chat: gemini-2.5-flash (Chat) & gemini-2.5-flash (Title)`);
    console.log(`GEMINI_API_KEY yang digunakan: ${API_KEY ? API_