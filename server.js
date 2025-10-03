import 'dotenv/config'; 
import express from 'express';
import cors from 'cors'; 
import { GoogleGenerativeAI } from '@google/generative-ai'; 

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

// Gunakan variabel untuk model yang akan kita gunakan
const geminiModel = ai.getGenerativeModel({ model: "gemini-2.5-pro" }); 

// INISIALISASI OBJEK CHAT GLOBAL (Simpan riwayat obrolan)
let chat = null;

// Fungsi untuk memulai sesi obrolan baru
async function startChatSession() {
    try {
        // Objek 'chat' sekarang akan mempertahankan riwayat obrolan
        chat = geminiModel.startChat({
            history: [], // Mulai dengan riwayat kosong
        });
        console.log("Sesi Chat Gemini berhasil dimulai.");
    } catch (e) {
        console.error("Gagal memulai sesi chat:", e.message);
        // Pertimbangkan untuk keluar dari aplikasi jika inisialisasi gagal
        // process.exit(1); 
    }
}

// Panggil startChatSession sekali saat server dimulai
startChatSession(); 

// 2. Konfigurasi CORS
const allowedOrigins = [
    'http://localhost', 
    'http://127.0.0.1:5500', 
    'http://localhost:5500' 
];
app.use(cors({ origin: allowedOrigins })); 

app.get('/test', (req, res) => {
    if (ai && chat) {
        res.status(200).json({ status: "OK", message: "Server berjalan dan model Gemini berhasil diinisialisasi untuk mode chat." });
    } else {
        res.status(500).json({ status: "ERROR", message: "Gagal inisialisasi model chat." });
    }
});

app.post('/chat', async (req, res) => {
    try {
        const userMessage = req.body.message;
        
        if (!userMessage) {
            return res.status(400).json({ error: "Message is required" });
        }
        
        if (!chat) {
             return res.status(503).json({ error: "Chat service is not available (failed to initialize)." });
        }

        // PERUBAHAN KRITIS: Menggunakan chat.sendMessage() untuk mempertahankan memori
        const result = await chat.sendMessage(userMessage); 

        const response = result.response;

        // Cek 1: Berhasil mendapatkan teks
        if (response && response.candidates && response.candidates.length > 0 && response.candidates[0].content && response.candidates[0].content.parts) {
            const text = response.candidates[0].content.parts[0].text;
            return res.json({ text });
        } 
        
        // Cek 2: Diblokir karena alasan keamanan
        if (response && response.promptFeedback && response.promptFeedback.blockReason) {
            const blockReason = response.promptFeedback.blockReason;
            const userFeedback = `⚠️ Maaf, balasan diblokir karena alasan keamanan (${blockReason}). Silakan ajukan pertanyaan yang berbeda.`;
            
            return res.json({ text: userFeedback });
        }

        // Cek 3: Tangani undefined/kosong
        console.error("DEBUG: Model returned no readable text content.");
        console.error("DEBUG: Full Gemini Result Object:", JSON.stringify(result, null, 2));

        const debugMessage = "⚠️ DEBUG ERROR (SERVER): Model gagal memberikan output teks. Kemungkinan ada masalah konfigurasi atau respons API tidak terduga.";
        return res.status(200).json({ 
            text: debugMessage 
        });

    } catch (error) {
        // Blok Catch: Tangani error Jaringan/API key non-valid/rate limit (status 500).
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
    console.log(`Model yang Digunakan untuk Chat: gemini-2.5-pro`);
    console.log(`GEMINI_API_KEY yang digunakan: ${API_KEY ? API_KEY.substring(0, 10) + '...' : 'TIDAK ADA'}`);
});