import 'dotenv/config'; 
import express from 'express';
import cors from 'cors'; 
import { GoogleGenerativeAI } from '@google/generative-ai'; 
import mysql from 'mysql2/promise'; 

const app = express();
const port = 3000;

// Middleware 1: Parsing JSON (Ditingkatkan untuk batasan payload yang lebih besar untuk file)
// Batas 50mb untuk mengizinkan unggahan file yang lebih besar
app.use(express.json({ limit: '50mb' })); 

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

// Fungsi bantuan untuk menyimpan pesan ke database (DIPERBARUI untuk mendukung file)
async function saveMessage(chatId, sender, text, fileData = null) {
    if (!dbConnection) return console.error("Database tidak terhubung. Pesan tidak disimpan.");
    
    // Simpan data file (base64) jika ada. Kolom 'file_data' perlu ditambahkan di tabel 'messages'.
    const fileBase64 = fileData ? JSON.stringify(fileData) : null;

    try {
        const query = "INSERT INTO messages (chat_id, sender, message_text, file_data) VALUES (?, ?, ?, ?)";
        await dbConnection.execute(query, [chatId, sender, text, fileBase64]);
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

// --- FUNGSI MENGAMBIL RIWAYAT PESAN UNTUK KONTEKS (DIPERBARUI) ---
async function getChatHistory(chatId) {
    if (!dbConnection) {
        console.error("Database tidak terhubung. Riwayat chat tidak dapat dimuat.");
        return [];
    }

    try {
        // Ambil file_data juga
        const [rows] = await dbConnection.execute(
            "SELECT sender, message_text, file_data FROM messages WHERE chat_id = ? ORDER BY created_at ASC",
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

// =========================================================================
// ENDPOINT CHAT UTAMA (DIPERBARUI UNTUK MULTIMODALITAS & RETRY) - PERTAHANKAN INI
// =========================================================================
app.post('/chat', async (req, res) => {
    try {
        const { message: userMessage, chatId, fileData } = req.body; 

        if (!userMessage && !fileData) { 
            return res.status(400).json({ error: "Message or fileData is required" });
        }
        if (!chatId) {
            return res.status(400).json({ error: "chatId is required" });
        }

        // 1. Ambil riwayat pesan untuk chat ID ini (Konteks)
        const history = await getChatHistory(chatId);
        // SOLUSI: Riwayat pesan digunakan untuk membuat sesi chat yang berkesinambungan
        const chatSession = geminiModel.startChat({ history: history });
        
        // Buat array 'parts' untuk pesan baru (bisa berisi file dan teks)
        let newParts = [];
        
        // Tambahkan file ke parts jika ada
        if (fileData) {
            newParts.push(fileToGenerativePart(fileData));
            console.log(`Mengirim file: ${fileData.mimeType.split('/')[0]} (tipe: ${fileData.mimeType})`);
        }
        
        // Tambahkan pesan teks (meskipun kosong, Gemini akan fokus pada file)
        newParts.push({ text: userMessage || '' });

        // Simpan pesan USER ke database sebelum memanggil Gemini (termasuk data file)
        await saveMessage(chatId, 'user', userMessage || '', fileData);

        // 3. Kirim pesan terbaru ke sesi yang sudah dimuat historinya (dengan retry logic)
        const MAX_RETRIES = 3;
        const RETRY_DELAY_MS = 2000;
        let result;
        
        for (let attempt = 0; attempt < MAX_RETRIES; attempt++) {
            try {
                console.log(`Percobaan API ke-${attempt + 1}...`);
                result = await chatSession.sendMessage(newParts); // Perbaikan: Ganti ke object parts jika perlu, tapi 'newParts' langsung sudah benar
                
                // Jika berhasil, keluar dari loop
                break; 
                
            } catch (error) {
                // **PERBAIKAN ERROR 2 (503):** Implementasi Retry Logic
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
        
        // Kirim 500 jika ada error yang tidak tertangani (selain 503 yang sudah dicoba ulang)
        return res.status(500).json({ error: errorMessage });
    }
});

// =========================================================================
// ENDPOINT UNTUK MEMBUAT JUDUL CHAT (TETAP SAMA)
// =========================================================================
app.post('/chat/title', async (req, res) => {
    try {
        // ... (Kode untuk membuat judul tetap sama)
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
    console.log(`GEMINI_API_KEY yang digunakan: ${API_KEY ? API_KEY.substring(0, 10) + '...' : 'TIDAK ADA'}`);
});