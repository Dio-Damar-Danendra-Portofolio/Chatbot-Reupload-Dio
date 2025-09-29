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
        res.status(200).json({ 
            status: "OK", 
            message: "Server running, AI Model Initialized. (Key status unknown until POST /chat is called)" 
        });
    } else {
        res.status(500).json({ 
            status: "Error", 
            message: "AI Model Initialization Failed." 
        });
    }
});
// ----------------------------

// 4. Endpoint untuk menangani permintaan chat (POST /chat)
app.post('/chat', async (req, res) => {
    try {
        const userMessage = req.body.message;
        
        if (!userMessage) {
            return res.status(400).json({ error: "Message is required" });
        }
        
        // Panggilan API Gemini
        const result = await model.generateContent(userMessage);
        
        // Cek 1: Coba ambil teks langsung. Gunakan optional chaining untuk keamanan.
        const responseText = result.response?.text;

        if (responseText) {
            return res.json({ text: responseText });
        } 
        
        // Cek 2: Cek apakah respons diblokir
        const blockReason = result.response?.promptFeedback?.blockReason;

        if (blockReason) {
            console.warn(`WARN: Response blocked. Reason: ${blockReason}`);
            const userFeedback = `❌ Maaf, pertanyaan Anda diblokir oleh filter keamanan AI (Reason: ${blockReason}). Coba ajukan pertanyaan yang berbeda.`;
            
            return res.json({ text: userFeedback });
        }

        // Cek 3 (BLOK KRITIS UNTUK DEBUGGING): Tangani undefined/kosong
        // Jika sampai sini, teks kosong dan tidak ada blokir.
        console.error("DEBUG: Model returned no readable text content.");
        console.error("DEBUG: Full Gemini Result Object (Periksa ini untuk error API Key):", JSON.stringify(result, null, 2));

        // --- Mengirim Status 200 agar Frontend menerima properti 'text' ---
        const debugMessage = "⚠️ DEBUG ERROR (SERVER): Model gagal memberikan output teks. Kemungkinan API Key tidak valid. Cek log server untuk objek respons penuh.";
        
        // Mengembalikan status 200, tetapi dengan pesan error debug yang terstruktur.
        return res.status(200).json({ 
            text: debugMessage 
        });

    } catch (error) {
        // Catch untuk ERROR Jaringan/API key non-valid/rate limit (status 500).
        console.error("Gemini API Error (Catch Block):", error);
        
        let errorMessage = "Failed to generate content: " + error.message;
        if (error.message.includes("API key not valid")) {
             errorMessage = "API Key tidak valid. Silakan periksa kunci Anda di file .env.";
        }
        
        return res.status(500).json({ error: errorMessage });
    }
});

app.listen(port, () => {
    console.log(`Server is running at http://localhost:${port}`);
});
