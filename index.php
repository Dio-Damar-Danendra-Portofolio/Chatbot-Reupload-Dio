<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Gemini Chatbot Demo</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f0ff; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; flex-direction: column; color: #333; }
        .container { background-color: #fff; padding: 2rem; border-radius: 10px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); width: 80%; max-width: 600px; }
        h1 { color: #007bff; text-align: center; }
        #chat-window { border: 1px solid #ced4da; padding: 1rem; margin-bottom: 1rem; border-radius: 5px; height: 300px; overflow-y: auto; background-color: #e9ecef; }
        .message { margin-bottom: 0.5rem; padding: 0.5rem; border-radius: 8px; max-width: 80%; }
        .user { background-color: #007bff; color: white; margin-left: auto; text-align: right; }
        .gemini { 
            background-color: #d1ecf1; 
            color: #0c5460; 
            text-align: left; 
            white-space: pre-wrap; /* Mempertahankan format pesan error */
        }
        #input-container { display: flex; }
        #prompt-input { flex-grow: 1; padding: 0.75rem; border: 1px solid #ced4da; border-radius: 5px 0 0 5px; }
        #send-button { padding: 0.75rem 1rem; background-color: #28a745; color: white; border: none; border-radius: 0 5px 5px 0; cursor: pointer; }
        #send-button:disabled { background-color: #95d2a8; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Gemini Chatbot</h1>
        <div id="chat-window"></div>
        <div id="input-container">
            <input type="text" id="prompt-input" placeholder="Ketik pesan Anda di sini...">
            <button id="send-button">Kirim</button>
        </div>
    </div>

    <script>
        const chatWindow = document.getElementById("chat-window");
        const promptInput = document.getElementById("prompt-input");
        const sendButton = document.getElementById("send-button");
        const SERVER_URL = 'http://localhost:3000/chat';

        function addMessage(text, sender) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${sender}`;
            messageDiv.innerText = text;
            chatWindow.appendChild(messageDiv);
            chatWindow.scrollTop = chatWindow.scrollHeight;
        }

        async function sendMessage() {
            const prompt = promptInput.value.trim();
            if (!prompt) return;

            addMessage(prompt, 'user');
            promptInput.value = '';
            sendButton.disabled = true;

            try {
                const response = await fetch(SERVER_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message: prompt })
                });

                const data = await response.json();

                if (response.ok) {
                    // Status 200: Respons Sukses atau Diblokir (Cek 1 atau Cek 2)
                    if (data && data.text) {
                        addMessage(data.text, 'gemini');
                    } else {
                        // Kasus 200 OK dengan data kosong (INI BUG LAMA ANDA)
                        console.error("DEBUG FRONTEND: Respons 200 OK, properti 'text' hilang. Data mentah:", data);
                        addMessage(`🚨 DEBUG ERROR: Server mengembalikan 200 OK tanpa teks balasan. Cek konsol.`, 'gemini');
                    }
                } else {
                    // Status Non-200 (400, 500 dari blok Cek 3 atau Catch)
                    const errorMessage = data.error || 'Terjadi kesalahan tidak dikenal di server.';
                    addMessage(`❌ ERROR SERVER (${response.status}): ${errorMessage}`, 'gemini');
                }

            } catch (error) {
                console.error("Network or Fetch Error:", error);
                addMessage("Kesalahan jaringan. Pastikan server (Node.js) berjalan di port 3000.", 'gemini');
            } finally {
                sendButton.disabled = false;
                promptInput.focus();
            }
        }

        sendButton.addEventListener('click', sendMessage);
        promptInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });
    </script>
</body>
</html>
