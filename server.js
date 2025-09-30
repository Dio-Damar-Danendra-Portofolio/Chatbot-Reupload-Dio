import 'dotenv/config'; 
import express from 'express';
import cors from 'cors'; 
import { GoogleGenerativeAI } from '@google/generative-ai';

const app = express();
const port = 3000;

// Middleware 1: Parsing JSON (WAJIB di awal)
app.use(express.json());

// 1. Inisialisasi Model dan API Key
const API_KEY = process.env.GEMINI_API_KEY; 

if (!API_KEY) {
    // Pesan error jika environment variable tidak ditemukan
    throw new Error("GEMINI_API_KEY not found in .env file. Please check your configuration.");
}

// --- VALIDASI KRITIS API KEY ---
// Kunci AIzaSy TIDAK VALID untuk Gemini API. Pemeriksaan ini akan memaksa server berhenti.
// if (API_KEY.startsWith("AIzaSy")) {
//     throw new Error("FATAL ERROR: API Key yang Anda gunakan dimulai dengan 'AIzaSy'. Ini adalah kunci API umum Google Cloud dan TIDAK VALID untuk Gemini. Silakan buat kunci baru dari Google AI Studio atau Google Cloud Console SETELAH mengaktifkan Gemini API.");
// }
// --- AKHIR VALIDASI KRITIS ---

const ai = new GoogleGenerativeAI(API_KEY);
const model = ai.getGenerativeModel({ model: "gemini-2.5-flash" });

// 2. Konfigurasi CORS
const allowedOrigins = [
    'http://localhost', 
    'http://127.0.0.1:5500', 
    'http://localhost:5500' 
];
app.use(cors({ origin: allowedOrigins })); 

// --- ROUTE PENGUJIAN BARU ---
// Gunakan browser untuk mengakses http://localhost:3000/test
app.get('/test', (req, res) => {
    // Ini akan menguji apakah AI berhasil diinisialisasi
    if (ai && model) {
        res.status(200).json({ status: "OK", message: "Server berjalan dan model Gemini berhasil diinisialisasi (dengan asumsi API Key valid)." });
    } else {
        res.status(500).json({ status: "ERROR", message: "Gagal inisialisasi model." });
    }
});

// 4. Endpoint untuk menangani permintaan chat (POST /chat)
app.post('/chat', async (req, res) => {
    try {
        const userMessage = req.body.message;
        
        if (!userMessage) {
            return res.status(400).json({ error: "Message is required" });
        }

        const result = await model.generateContent(userMessage);
        const response = result.response;

        // Cek 1: Jika response.text tersedia, gunakan
        if (response && response.text) {
            return res.json({ text: response.text });
        } 
        
        // Cek 2: Cek apakah respons diblokir
        if (response && response.promptFeedback && response.promptFeedback.blockReason) {
            const blockReason = response.promptFeedback.blockReason;
            const userFeedback = `⚠️ Maaf, balasan diblokir karena alasan keamanan (${blockReason}). Silakan ajukan pertanyaan yang berbeda.`;
            
            return res.json({ text: userFeedback });
        }

        // Cek 3 (BLOK KRITIS UNTUK DEBUGGING): Tangani undefined/kosong
        console.error("DEBUG: Model returned no readable text content.");
        console.error("DEBUG: Full Gemini Result Object (Periksa ini untuk error API Key):", JSON.stringify(result, null, 2));

        // Mengembalikan status 200, tetapi dengan pesan error debug yang terstruktur.
        const debugMessage = "⚠️ DEBUG ERROR (SERVER): Model gagal memberikan output teks. Kemungkinan API Key tidak valid. Cek log server untuk objek respons penuh.";
        return res.status(200).json({ 
            text: debugMessage 
        });

    } catch (error) {
        // Catch untuk ERROR Jaringan/API key non-valid/rate limit (status 500).
        console.error("Gemini API Error (Catch Block):", error);
        
        let errorMessage = "Failed to generate content: " + error.message;
        if (error.message.includes("API key not valid")) {
             // Pesan error spesifik ini muncul jika kunci memiliki pola yang benar tetapi tidak aktif/salah.
             errorMessage = "API Key tidak valid. Silakan periksa kunci Anda di file .env.";
        }
        
        return res.status(500).json({ error: errorMessage });
    }
});

app.listen(port, () => {
    console.log(`Server Node.js berjalan di http://localhost:${port}`);
    console.log(`GEMINI_API_KEY yang digunakan: ${API_KEY.substring(0, 10)}...`);
    // Lanjutkan dengan pengujian di browser Anda
});
