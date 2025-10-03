<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>Gemini Chatbot Demo</title>
    <style>
        /* CSS Dasar */
        body { 
            font-family: Arial, sans-serif; 
            background-color: #f0ff; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            margin: 0; 
            flex-direction: column; 
            color: #333; 
            padding: 10px; 
            box-sizing: border-box; 
        }
        .container { 
            background-color: #fff; 
            padding: 2rem; 
            border-radius: 10px; 
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); 
            width: 80%; 
            max-width: 600px; 
            box-sizing: border-box; 
        }
        h1 { 
            color: #007bff; 
            text-align: center; 
            margin-top: 0; 
        }
        #chat-window { 
            border: 1px solid #ced4da; 
            padding: 1rem; 
            margin-bottom: 1rem; 
            border-radius: 5px; 
            height: 300px; 
            overflow-y: auto; 
            background-color: #e9ecef; 
        }
        .message { 
            margin-bottom: 0.5rem; 
            padding: 0.75rem; 
            border-radius: 15px; 
            max-width: 85%; 
            line-height: 1.4;
        }
        .user { 
            background-color: #007bff; 
            color: white; 
            margin-left: auto; 
            text-align: right; 
            border-bottom-right-radius: 2px; 
        }
        .gemini { 
            background-color: #d1ecf1; 
            color: #0c5460; 
            text-align: left; 
            white-space: pre-wrap;
            margin-right: auto;
            border-bottom-left-radius: 2px; 
        }
        #input-container { 
            display: flex; 
        }
        #prompt-input { 
            flex-grow: 1; 
            padding: 0.75rem; 
            border: 1px solid #ced4da; 
            border-radius: 5px 0 0 5px; 
            font-size: 1rem;
        }
        #send-button { 
            padding: 0.75rem 1rem; 
            background-color: #28a745; 
            color: white; 
            border: none; 
            border-radius: 0 5px 5px 0; 
            cursor: pointer; 
            transition: background-color 0.2s; 
        }
        #send-button:hover:not(:disabled) {
            background-color: #218838;
        }
        #send-button:disabled { 
            background-color: #95d2a8; 
            cursor: not-allowed;
        }

        /* --- Media Queries untuk Responsif --- */
        @media (max-width: 768px) {
            body {
                height: auto; 
                min-height: 100vh;
                padding: 5px;
            }
            .container {
                width: 100%; 
                padding: 1rem;
                margin: 20px 0; 
            }
            h1 {
                font-size: 1.5rem; 
            }
            #chat-window {
                height: 40vh; 
                padding: 0.75rem;
            }
            .message {
                font-size: 0.9rem; 
            }
            #prompt-input {
                padding: 0.6rem;
                font-size: 0.9rem;
            }
            #send-button {
                padding: 0.6rem 0.75rem;
            }
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 0.75rem;
            }
            #chat-window {
                height: 60vh; 
            }
            .message {
                max-width: 90%; 
            }
            #input-container {
                flex-direction: row; 
            }
            #send-button {
                padding: 0.6rem 0.5rem; 
            }
            #prompt-input::placeholder {
                font-size: 0.85rem;
            }
        }
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
                    if (data && data.text) {
                        addMessage(data.text, 'gemini');
                    } else {
                        console.error("DEBUG FRONTEND: Respons 200 OK, properti 'text' hilang. Data mentah:", data);
                        addMessage(`🚨 DEBUG ERROR: Server mengembalikan 200 OK tanpa teks balasan. Cek konsol.`, 'gemini');
                    }
                } else {
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