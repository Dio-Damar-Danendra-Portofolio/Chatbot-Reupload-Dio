import 'dotenv/config'; 
import express from 'express';
import cors from 'cors'; 
import { GoogleGenerativeAI } from '@google/generative-ai'; 

const app = express();
const port = 3000;

// Middleware 1: Parsing JSON (WAJIB di awal)
app.use(express.json());

// 1. Inisialisasi API Key dari .env
const API_KEY = process.env.GEMINI_API_KEY; 

if (!API_KEY) {
    throw new Error("GEMINI_API_KEY not found in .env file. Please check your configuration.");
}

// Inisialisasi GoogleGenerativeAI menggunakan API Key (Mendukung AIzaSy...)
const ai = new GoogleGenerativeAI(API_KEY);

// PERBAIKAN KRITIS: Mengganti nama model yang tidak didukung (gemini-1.5-pro-001) 
// dengan nama model publik yang benar.
const model = ai.getGenerativeModel({ model: "gemini-2.5-pro" }); 

// Bagian ini adalah contoh bagaimana Anda bisa menggunakan ListModels 
// untuk menemukan nama model yang benar. Anda bisa menghapusnya setelah digunakan.
/*
async function logAvailableModels() {
    try {
        console.log("--- Daftar Model yang Tersedia ---");
        const { models } = await ai.listModels();
        // Hanya tampilkan model yang dapat digunakan untuk generateContent
        const runnableModels = models.filter(m => m.supportedGenerationMethods.includes("generateContent"));
        runnableModels.forEach(m => console.log(`Nama Model: ${m.name}`));
        console.log("---------------------------------");
    } catch (e) {
        console.error("Gagal mendapatkan daftar model:", e.message);
    }
}
// Panggil ListModels sekali saat server dimulai
logAvailableModels();
*/

// 2. Konfigurasi CORS
const allowedOrigins = [
    'http://localhost', 
    'http://127.0.0.1:5500', 
    'http://localhost:5500' 
];
app.use(cors({ origin: allowedOrigins })); 

app.get('/test', (req, res) => {
    if (ai && model) {
        res.status(200).json({ status: "OK", message: "Server berjalan dan model Gemini berhasil diinisialisasi menggunakan API Key." });
    } else {
        res.status(500).json({ status: "ERROR", message: "Gagal inisialisasi model." });
    }
});

app.post('/chat', async (req, res) => {
    try {
        const userMessage = req.body.message;
        
        if (!userMessage) {
            return res.status(400).json({ error: "Message is required" });
        }

        const result = await model.generateContent(userMessage);
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

        // Cek 3 (BLOK KRITIS UNTUK DEBUGGING): Tangani undefined/kosong
        console.error("DEBUG: Model returned no readable text content.");
        console.error("DEBUG: Full Gemini Result Object (Periksa ini untuk error API Key):", JSON.stringify(result, null, 2));

        // Mengembalikan status 200, tetapi dengan pesan error debug yang terstruktur.
        const debugMessage = "⚠️ DEBUG ERROR (SERVER): Model gagal memberikan output teks. Kemungkinan ada masalah konfigurasi atau respons API tidak terduga.";
        return res.status(200).json({ 
            text: debugMessage 
        });

    } catch (error) {
        // Blok Catch: Tangani error Jaringan/API key non-valid/rate limit (status 500).
        console.error("Gemini API Error (Catch Block):", error);
        
        let errorMessage = "Failed to generate content: " + error.message;
        
        // Pesan ini mungkin muncul jika kunci AIzaSy... TIDAK valid untuk Gemini API.
        if (error.message.includes("API key not valid")) {
             errorMessage = "API Key Anda terdeteksi, tetapi tidak valid untuk Gemini API. Harap buat kunci baru dari Google AI Studio.";
        }
        
        return res.status(500).json({ error: errorMessage });
    }
});

app.listen(port, () => {
    console.log(`Server Node.js berjalan di http://localhost:${port}`);
    console.log(`Nama Model yang Digunakan: ${model.model}`);
    console.log(`GEMINI_API_KEY yang digunakan: ${API_KEY.substring(0, 10)}...`);
});
