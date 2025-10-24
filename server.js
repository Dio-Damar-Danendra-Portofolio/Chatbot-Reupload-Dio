import 'dotenv/config'; 
import express from 'express';
import cors from 'cors'; 
import { GoogleGenerativeAI } from '@google/generative-ai'; 
import mysql from 'mysql2/promise'; 
import { marked } from 'marked'; 

const app = express();
const port = 3000;

// Middleware - Batas dinaikkan untuk transfer Data URI (gambar besar)
app.use(express.json({ limit: '500mb' })); 
app.use(cors());

// 1. Inisialisasi API Key dan Model
const API_KEY = process.env.GEMINI_API_KEY; 
if (!API_KEY) {
    throw new Error("GEMINI_API_KEY not found in .env file.");
}
const systemPrompt = "Anda adalah pembuat judul chat yang cerdas. Berdasarkan pesan pertama pengguna, buatlah judul yang singkat, deskriptif, dan menarik (maksimal 7 kata). Berikan HANYA judul tersebut tanpa teks atau tanda kutip tambahan.";
const ai = new GoogleGenerativeAI(API_KEY);

// Model untuk respons chat dan judul
const geminiModel = ai.getGenerativeModel({ model: "gemini-2.5-flash" }); 
const titleModel = ai.getGenerativeModel({ 
    model: "gemini-2.5-flash", 
    systemInstruction: systemPrompt
}); 

// 2. KONFIGURASI DAN KONEKSI DATABASE MYSQL (Menggunakan konfigurasi default untuk contoh)
const dbConfig = {
    host: 'localhost',
    user: 'root', 
    password: '', 
    database: 'chatbot', 
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
};
const pool = mysql.createPool(dbConfig);

// 3. FUNGSI PEMBANTU (Helper)
/** Mengkonversi Data URI (base64) menjadi Part untuk Gemini API (Multimodal) */
function dataUriToGenerativePart(dataUri, mimeType) {
    // Pastikan Data URI valid sebelum di-split
    const base64Data = dataUri.split(',')[1];
    if (!base64Data) {
         throw new Error("Invalid Data URI provided.");
    }
    return {
        inlineData: {
            data: base64Data,
            mimeType
        },
    };
}

/** Mengambil riwayat pesan dari DB */
async function getHistory(chatId) {
    if (chatId === 'null') return [];
    const connection = await pool.getConnection();
    // Hanya ambil riwayat teks untuk konteks
    const [rows] = await connection.execute(
        'SELECT sender, message_text FROM messages WHERE chat_id = ? ORDER BY created_at ASC',
        [chatId]
    );
    connection.release();

    return rows.map(row => ({
        role: row.sender === 'user' ? 'user' : 'model',
        parts: [{ text: row.message_text }]
    }));
}

/** Membuat judul baru menggunakan model khusus (Pembaruan Judul) */
async function updateChatTitle(chatId, firstMessageText) {
    try {
        const titleResponse = await titleModel.generateContent(firstMessageText);
        let newTitle = titleResponse.text.trim();
        
        // Bersihkan tanda kutip jika ada
        newTitle = newTitle.replace(/^['"“„]|['"”]$/g, '').substring(0, 250);

        const connection = await pool.getConnection();
        await connection.execute(
            'UPDATE chats SET title = ? WHERE id = ?',
            [newTitle, chatId]
        );
        connection.release();
        return newTitle;
    } catch (error) {
        console.error("Gagal membuat atau memperbarui judul:", error);
        return "Chat Gagal Diberi Nama";
    }
}


// 4. ENDPOINT UTAMA CHAT (/chat)
app.post('/chat', async (req, res) => {
    let connection;
    try {
        const { 
            chatId: userChatId, 
            message: userMessage, 
            fileDataUri, 
            fileMimeType, 
            isNewChat,
            userId // Asumsi userId dikirim dari sesi PHP (index.php)
        } = req.body;
        
        // Cek userId (kritis untuk keamanan dan pembuatan chat)
        if (!userId) {
             return res.status(401).json({ error: "Unauthorized: Missing user ID." });
        }
        
        let chatId = userChatId;
        let geminiMessageId = null;
        let userMessageId = null;
        let newTitle = null;

        connection = await pool.getConnection();
        await connection.beginTransaction();

        // 1. BUAT CHAT BARU JIKA PERLU
        if (chatId === 'null' || isNewChat === 'true') {
            const [chatResult] = await connection.execute(
                'INSERT INTO chats (user_id) VALUES (?)',
                [userId]
            );
            chatId = chatResult.insertId;
        }

        // 2. SIMPAN PESAN PENGGUNA (TERMASUK LAMPIRAN)
        // file_path menyimpan Data URI untuk dirender di klien dan dikirim ke Gemini
        const [userResult] = await connection.execute(
            'INSERT INTO messages (chat_id, sender, message_text, file_path, file_mime_type) VALUES (?, ?, ?, ?, ?)',
            [chatId, 'user', userMessage, fileDataUri || null, fileMimeType || null] 
        );
        userMessageId = userResult.insertId;
        
        // 3. SIAPKAN PROMPT MULTIMODAL DAN RIWAYAT
        const history = await getHistory(chatId);
        
        const userParts = [];
        // Tambahkan lampiran jika ada (Multimodal)
        if (fileDataUri && fileMimeType) {
            userParts.push(dataUriToGenerativePart(fileDataUri, fileMimeType));
        }
        userParts.push({ text: userMessage });

        // Kombinasikan riwayat dan pesan baru
        const contents = [...history, { role: 'user', parts: userParts }];

        // 4. PANGGIL API GEMINI
        const response = await geminiModel.generateContent({ contents: contents });
        const geminiText = marked.parse(response.text);

        if (response.text) {
            // 5. SIMPAN PESAN GEMINI
            const [geminiResult] = await connection.execute(
                'INSERT INTO messages (chat_id, sender, message_text) VALUES (?, ?, ?)',
                [chatId, 'gemini', geminiText]
            );
            geminiMessageId = geminiResult.insertId;

            // 6. PEMBARUAN JUDUL (Animasi Mengetik)
            if (isNewChat === 'true') {
                 newTitle = await updateChatTitle(chatId, userMessage);
            }

            await connection.commit();
            connection.release();

            // 7. RESPON KE KLIEN
            return res.json({ 
                text: geminiText, 
                messageId: geminiMessageId, 
                newTitle: newTitle, 
                chatId: chatId,
                userMessageId: userMessageId 
            });
        } else {
            throw new Error("Respons Gemini kosong atau diblokir.");
        }

    } catch (error) {
        if (connection) {
            await connection.rollback();
            connection.release();
        }
        console.error("Error utama saat memproses chat:", error);
        return res.status(500).json({ 
            error: "Gagal memproses chat. Silakan cek konsol server Anda untuk rincian."
        });
    }
});

app.listen(port, () => {
    console.log(`Server Node.js berjalan di http://localhost:${port}`);
});