// server.js (Versi Optimal Setelah Koreksi)
import 'dotenv/config'; 
import express from 'express';
import cors from 'cors'; 
import { GoogleGenerativeAI } from '@google/generative-ai';

const app = express();
const port = 3000;

app.use(express.json());

// 1. Inisialisasi Model dan API Key
const API_KEY = process.env.GEMINI_API_KEY; // [cite: 1]
// ... (Logic pengecekan API Key)
const ai = new GoogleGenerativeAI(API_KEY);
const model = ai.getGenerativeModel({ model: "gemini-2.5-flash" }); //

// 2. MIDDLEWARE WAJIB DI ATAS: Parsing JSON
 // <--- Dipindahkan ke atas

// 3. Konfigurasi CORS
const allowedOrigins = [
    'http://localhost', 
    'http://127.0.0.1:5500', 
    'http://localhost:5500' 
];
app.use(cors({ origin: allowedOrigins })); 

// 4. Endpoint untuk menangani permintaan chat (POST /chat)
// server.js (Corrected Logic for Response Handling)

// ... (existing imports and setup code)

// 4. Endpoint untuk menangani permintaan chat (POST /chat)
// server.js (Logika Akses Respons yang Ditingkatkan)

// server.js (Fokus pada blok catch-all/undefined)

app.post('/chat', async (req, res) => {
    try {
        const userMessage = req.body.message;
        
        // ... (Logika pengecekan API dan pemanggilan model)
        const result = await model.generateContent(userMessage);
        const response = result.response;

        // Cek 1: Jika response.text tersedia, gunakan
        if (response && response.text) {
            return res.json({ text: response.text });
        } 
        
        // Cek 2: Cek apakah respons diblokir
        if (response && response.promptFeedback && response.promptFeedback.blockReason) {
            // ... (Logika jika diblokir)
            // ...
        }

        // --- BLOK KRITIS UNTUK DEBUGGING ---
        // Jika response.text tetap undefined, cetak seluruh objek result!
        console.error("DEBUG: Model returned no readable text content.");
        console.error("DEBUG: Full Gemini Result Object:", JSON.stringify(result, null, 2));

        return res.status(500).json({ 
            // Mengirimkan pesan ini ke frontend (index.php)
            error: "Model returned no text content. Check the server logs for the full Gemini Result." 
        });

    } catch (error) {
        console.error("Gemini API Error (Catch Block):", error);
        return res.status(500).json({ error: "Failed to generate content: " + error.message });
    }
});

// ... (existing app.listen code)

app.listen(port, () => {
    console.log(`Server is running at http://localhost:${port}`);
});