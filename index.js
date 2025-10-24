// 1. Impor GoogleGenerativeAI menggunakan URL yang mendukung ES Module (esm.run)
        import { GoogleGenerativeAI } from 'https://esm.run/@google/generative-ai';

        const outputElement = document.getElementById("output");
        const API_KEY = "AIzaSyBbjNFDaXzJzHlfc1K5RZPNeqtFrJroAkk"; 
        
        async function main() {
            try {
                // 2. Sekarang GoogleGenerativeAI sudah terdefinisi
                const genAI = new GoogleGenerativeAI(API_KEY);
                
                // Gunakan model yang valid: gemini-1.5-flash
                const model = genAI.getGenerativeModel({ model: "gemini-2.5-flash" });

                const prompt = "Apakah perbedaan kambing dan domba?";

                const result = await model.generateContent(prompt);
                const response = result.response;
                const text = response.text();

                outputElement.innerText = text;

            } catch (error) {
                console.error("Terjadi kesalahan:", error);
                // Menampilkan error.message agar lebih jelas
                outputElement.innerText = "Error: " + error.message; 
            }
        }

        main();