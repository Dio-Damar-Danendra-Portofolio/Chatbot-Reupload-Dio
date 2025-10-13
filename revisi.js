// server.js (Kode Konsolidasi)
import 'dotenv/config'; 
import express from 'express';
import cors from 'cors'; 
import { GoogleGenerativeAI } from '@google/generative-ai'; 
import mysql from 'mysql2/promise'; 

const app = express();
const port = 3000;

// Middleware untuk JSON parsing dengan batas besar (untuk file)
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
    connectionLimit: 10, // Mengurangi dari 1000 ke 10 yang lebih standar
    queueLimit: 0,
};

let dbPool; // Menggunakan Pool untuk stabilitas

async function connectDB() {
    try {
        dbPool = mysql.createPool(dbConfig); // Gunakan createPool
        await dbPool.query("SELECT 1"); // Tes koneksi
        console.log("Koneksi database MySQL (Pool) berhasil.");
    } catch (error) {
        console.error("Gagal menghubungkan ke database:", error);
        process.exit(1);
    }
}
connectDB();

// -------------------------------------------------------------
// FUNGSI UTILITY
// -------------------------------------------------------------

async function saveMessage(chatId, sender, text, fileData = null) {
    if (!dbPool) return console.error("Database Pool tidak terhubung. Pesan tidak disimpan.");
    
    const fileBase64 = fileData ? JSON.stringify(fileData) : null;
    try {
        const query = "INSERT INTO messages (chat_id, sender, message_text, file_data) VALUES (?, ?, ?, ?)";
        await dbPool.execute(query, [chatId, sender, text, fileBase64]); 
    } catch (error) {
        console.error("Gagal menyimpan pesan ke database:", error);
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
    }
}

// FUNGSI KRITIS UNTUK KONTEKS: Mengambil riwayat pesan dan mengubah formatnya
async function getChatHistory(chatId) {
    if (!dbPool) {
        console.error("Database Pool tidak terhubung. Riwayat chat tidak dapat dimuat.");
        return [];
    }
    
    try {
        // Ambil semua pesan, urutkan berdasarkan ID (asumsi created_at dekat)
        const [rows] = await dbPool.execute(
            "SELECT id, sender, message_text, file_data FROM messages WHERE chat_id = ? ORDER BY id ASC",
            [chatId]
        );

        const history = [];
        for (const row of rows) {
            let parts = [];
            
            // Tambahkan teks pesan
            parts.push({ text: row.message_text });

            // Jika ada data file, parse dan tambahkan
            if (row.file_data) {
                try {
                    const fileData = JSON.parse(row.file_data);
                    // Asumsi fileData adalah objek { mimeType, base64Data }
                    parts.push({
                        inlineData: {
                            data: fileData.base64Data,
                            mimeType: fileData.mimeType,
                        }
                    });
                } catch (e) {
                    console.error("Gagal parse file data:", e);
                }
            }

            history.push({
                role: row.sender === 'user' ? 'user' : 'model',
                parts: parts,
                // Tambahkan ID pesan untuk keperluan debugging/re-generation (optional)
                message_id: row.id 
            });
        }
        return history;
    } catch (error) {
        console.error("Gagal mengambil riwayat chat dari database:", error);
        return [];
    }
}

// -------------------------------------------------------------
// ENDPOINT CHAT UTAMA
// -------------------------------------------------------------
app.post('/chat', async (req, res) => {
    try {
        // messageIdToUpdate dikirim dari JS setelah update_message.php sukses
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
            await saveMessage(chatId, 'user', userMessage || '', fileData);
        }
        
        // 1. Ambil riwayat pesan untuk chat ID ini (Konteks terbaru, termasuk pesan yang diedit/baru)
        const history = await getChatHistory(chatId);
        
        if (history.length === 0) {
            // Ini seharusnya tidak terjadi jika pesan user baru saja disimpan/di-update
            return res.status(400).json({ error: "Chat history is empty or invalid." });
        }
        
        // Dapatkan 'parts' dari pesan user terakhir di history (Prompt yang diedit/baru)
        const newParts = history[history.length - 1].parts; 
        
        // Riwayat untuk sesi adalah SEMUA pesan kecuali pesan user terakhir
        const chatHistoryForSession = history.slice(0, -1); 

        // 2. Buat sesi chat dengan riwayat sebelumnya
        const chatSession = geminiModel.startChat({ history: chatHistoryForSession });

        // 3. Kirim pesan terbaru ke sesi
        const MAX_RETRIES = 3;
        let result;
        
        for (let attempt = 0; attempt < MAX_RETRIES; attempt++) {
            try {
                console.log(`Percobaan API ke-${attempt + 1}...`);
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
             let finalMessage = "⚠️ DEBUG ERROR (SERVER): Model gagal memberikan output teks.";
                if (response && response.promptFeedback && response.promptFeedback.blockReason) {
                    const blockReason = response.promptFeedback.blockReason;
                    finalMessage = `⚠️ Maaf, balasan diblokir karena alasan keamanan (${blockReason}). Silakan ajukan pertanyaan yang berbeda.`;
                }
                console.error("DEBUG: Model returned no readable text content.");
                await saveMessage(chatId, 'gemini', finalMessage);
                return res.status(500).json({ error: "Gemini response was empty or blocked." });
            }

    } catch (error) {
        console.error("Error utama saat memproses chat:", error);
        return res.status(500).json({ 
            error: "Gagal memproses chat.",
            details: error.message
        });
    }
});

async function generateChatTitle(chatId, userMessage, geminiResponse) {
            console.log("Membuat judul chat...");
            try {
                const response = await fetch(TITLE_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ chatId, userMessage, geminiResponse })
                });
                
                const data = await response.json();

                if (data.success) {
                    console.log("Judul berhasil diperbarui:", data.title);
                    
                    const chatListItem = document.querySelector(`.chat-list-item[data-chat-id="${chatId}"]`);
                    if (chatListItem) {
                        const titleTextElement = chatListItem.querySelector('.chat-list-text');
                        const now = new Date();
                        const formattedTime = now.toLocaleTimeString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }).replace(' at ', ', '); 
                        titleTextElement.innerHTML = `${data.title} <small style="color: #bbb; display: block;">${formattedTime}</small>`;
                    } 
                    
                } else {
                    console.error("Gagal membuat judul:", data.error);
                }
            } catch (error) {
                console.error("Network error saat membuat judul:", error);
            }
        }
        
app.listen(port, () => {
    console.log(`Server Node.js berjalan di http://localhost:${port}`);
});