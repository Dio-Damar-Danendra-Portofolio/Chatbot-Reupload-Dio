import 'dotenv/config'; 
import express from 'express';
import cors from 'cors'; 
import { GoogleGenerativeAI } from '@google/generative-ai'; 
import mysql from 'mysql2/promise'; 
import { marked } from 'marked'; 
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const app = express();
const port = 3000;

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const uploadDir = path.join(__dirname, 'uploads', 'chat_files');

function ensureUploadDir() {
    if (!fs.existsSync(uploadDir)) {
        fs.mkdirSync(uploadDir, { recursive: true });
    }
}

function mimeToExtension(mimeType = '') {
    const mime = (mimeType || '').toLowerCase();
    if (mime.startsWith('image/')) {
        if (mime.includes('png')) return '.png';
        if (mime.includes('gif')) return '.gif';
        if (mime.includes('webp')) return '.webp';
        if (mime.includes('svg')) return '.svg';
        return '.jpg';
    }
    if (mime.startsWith('application/')) {
        if (mime.includes('pdf')) return '.pdf';
        if (mime.includes('zip') || mime.includes('compressed')) return '.zip';
        if (mime.includes('word') || mime.includes('doc')) return '.docx';
        if (mime.includes('excel') || mime.includes('xls')) return '.xlsx';
        if (mime.includes('powerpoint') || mime.includes('ppt')) return '.pptx';
        if (mime.includes('json')) return '.json';
        if (mime.includes('csv')) return '.csv';
        return '.bin';
    }
    if (mime.startsWith('video/')) {
        if (mime.includes('webm')) return '.webm';
        if (mime.includes('avi')) return '.avi';
        return '.mp4';
    }
    if (mime.startsWith('audio/')) {
        if (mime.includes('wav')) return '.wav';
        if (mime.includes('ogg')) return '.ogg';
        if (mime.includes('aac')) return '.aac';
        return '.mp3';
    }
    if (mime.startsWith('text/')) {
        if (mime.includes('html')) return '.html';
        if (mime.includes('css')) return '.css';
        if (mime.includes('javascript') || mime.includes('js')) return '.js';
        return '.txt';
    }
    return '.bin';
}

async function saveDataUriToFile(dataUri, mimeType) {
    if (!dataUri) return null;
    const base64Data = dataUri.split(',')[1];
    if (!base64Data) {
        throw new Error('Invalid Data URI');
    }
    ensureUploadDir();
    const buffer = Buffer.from(base64Data, 'base64');
    const extension = mimeToExtension(mimeType);
    const uniqueName = `file_${Date.now()}_${Math.random().toString(36).slice(2)}${extension}`;
    const absolutePath = path.join(uploadDir, uniqueName);
    await fs.promises.writeFile(absolutePath, buffer);
    const relativePath = path.join('uploads', 'chat_files', uniqueName).replace(/\\/g, '/');
    return { absolutePath, relativePath };
}

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
async function updateChatTitle(connection, chatId, firstMessageText) {
    try {
        const titleResponse = await titleModel.generateContent(firstMessageText);
        
        // --- PERBAIKAN UTAMA DI SINI ---
        // Gunakan metode akses yang aman, sama seperti pada chat utama
        const newTitleText = titleResponse?.response?.candidates?.[0]?.content?.parts?.[0]?.text;

        // Pastikan newTitleText ada sebelum di-trim
        if (newTitleText) {
            let newTitle = newTitleText.trim();
            
            // Bersihkan tanda kutip jika ada
            newTitle = newTitle.replace(/^['"“„]|['"”]$/g, '').substring(0, 250);

            // const connection = await pool.getConnection();
            await connection.execute(
                'UPDATE chats SET title = ? WHERE id = ?',
                [newTitle, chatId]
            );
            connection.release();
            return newTitle;
        } else {
            // Jika titleModel gagal (misalnya diblokir), lempar error agar ditangkap
            console.error("Gagal membuat judul: Respons titleModel kosong atau diblokir.");
            console.error("Debug Info titleModel:", JSON.stringify(titleResponse?.response, null, 2));
            throw new Error("Respons titleModel kosong.");
        }
    } catch (error) {
        // Blok catch ini akan menangani error dari 'trim()' (sebelumnya) 
        // atau error 'throw' yang baru (jika diblokir)
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
        let storedFilePath = null;

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
        if (fileDataUri && fileMimeType) {
            try {
                const savedFile = await saveDataUriToFile(fileDataUri, fileMimeType);
                storedFilePath = savedFile ? savedFile.relativePath : null;
            } catch (fileError) {
                await connection.rollback();
                connection.release();
                console.error('Gagal menyimpan lampiran:', fileError);
                return res.status(500).json({ error: 'Failed to store attachment on server.' });
            }
        }

        const [userResult] = await connection.execute(
            'INSERT INTO messages (chat_id, sender, message_text, file_path, file_mime_type) VALUES (?, ?, ?, ?, ?)',
            [chatId, 'user', userMessage, storedFilePath, fileMimeType || null] 
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
        const geminiResponseText = response?.response?.candidates?.[0]?.content?.parts?.[0]?.text;
        let geminiText = null;
        

        if (geminiResponseText) {            
            geminiText = marked.parse(geminiResponseText);
            // 5. SIMPAN PESAN GEMINI
            const [geminiResult] = await connection.execute(
                'INSERT INTO messages (chat_id, sender, message_text) VALUES (?, ?, ?)',
                [chatId, 'gemini', geminiText]
            );

            geminiMessageId = geminiResult.insertId;

            // 6. PEMBARUAN JUDUL (Animasi Mengetik)
            if (isNewChat === 'true') {
                 newTitle = await updateChatTitle(connection, chatId, userMessage);
            }

            await connection.commit();
            connection.release();

            // 7. RESPON KE KLIEN
            return res.json({ 
                text: geminiText, 
                messageId: geminiMessageId, 
                newTitle: newTitle, 
                chatId: chatId,
                userMessageId: userMessageId,
                userFilePath: storedFilePath,
                userFileMimeType: fileMimeType || null
            });
        } else {
            console.error("--- DEBUGGING GEMINI RESPONSE START ---");
             // Log Prompt Feedback: Memberikan alasan mengapa input diblokir
             console.error("Prompt Feedback:", JSON.stringify(response.response.promptFeedback, null, 2));
             // Log Candidates: Memberikan alasan mengapa output diblokir
             console.error("Candidates (Blocked Output Info):", JSON.stringify(response.response.candidates, null, 2));
             console.error("--- DEBUGGING GEMINI RESPONSE END ---");
             
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
