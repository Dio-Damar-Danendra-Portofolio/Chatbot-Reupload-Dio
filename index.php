<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dio's Chatbot</title>
    <!-- Bootstrap CSS, Icons, and JS bundles -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- External Libraries (Not strictly needed for Chatbot UI, but kept from original) -->
    <script src="https://cdn.jsdelivr.net/npm/read-excel-file@5.5.5/umd/read-excel-file.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    
    <style>
        body {
            font-family: 'Inter', Arial, sans-serif; /* Menggunakan font Inter yang lebih modern */
            background-color: #f8f9fa; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            margin: 0; 
            flex-direction: column; 
            color: #333; 
        }
        .container { 
            background-color: #fff; 
            padding: 2rem; 
            border-radius: 12px; 
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15); 
            width: 90%; 
            max-width: 650px; 
        }
        
        /* New style for header bar to hold title and button */
        #header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e9ecef;
        }

        h1 { 
            color: #0d6efd; /* Warna biru Bootstrap primary */
            text-align: left; 
            font-weight: 700;
            font-size: 1.8rem;
            margin: 0; /* Hapus margin default */
        }
        
        #chat-window { 
            border: 1px solid #ced4da; 
            padding: 1rem; 
            margin-bottom: 1rem; 
            border-radius: 8px; 
            height: 350px; 
            overflow-y: auto; 
            background-color: #f8f9fa; /* Lebih terang */
        }
        .message { 
            margin-bottom: 0.75rem; 
            padding: 0.7rem; 
            border-radius: 10px; 
            max-width: 85%;
            font-size: 0.95rem;
            line-height: 1.4;
        }
        .user { 
            background-color: #0d6efd; 
            color: white; 
            margin-left: auto; 
            text-align: right; 
            border-bottom-right-radius: 0; /* Gaya bubble */
        }
        .gemini { 
            background-color: #e0f7fa; 
            color: #004d40; 
            text-align: left; 
            white-space: pre-wrap;
            border: 1px solid #b2ebf2;
            border-bottom-left-radius: 0; /* Gaya bubble */
        }
        #input-container { 
            display: flex; 
            gap: 5px; 
        }
        #prompt-input { 
            flex-grow: 1; 
            padding: 0.75rem 1rem; 
            border: 1px solid #ced4da; 
            border-radius: 8px; 
        }
        #send-button { 
            padding: 0.75rem 1rem; 
            background-color: #198754; 
            color: white; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-weight: 600;
            transition: background-color 0.2s;
        }
        #send-button:hover:not(:disabled) { 
            background-color: #157347; 
        }
        #send-button:disabled { 
            background-color: #95d2a8; 
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- New Header Bar for Title and Auth Button -->
        <div id="header-bar">
            <h1>Dio's Chatbot</h1>
            <div id="auth-status">
                <?php 
                // Asumsi: $_SESSION['ID'] berisi ID pengguna jika sudah login
                if(isset($_SESSION['ID'])) { 
                ?>
                    <!-- Tampilkan tombol Logout jika pengguna sudah login -->
                    <a href="logout.php" class="btn btn-danger btn-sm rounded-pill shadow-sm">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                <?php 
                } else { 
                ?>
                    <!-- Tampilkan tombol Login jika pengguna belum login -->
                    <a href="login.php" class="btn btn-primary btn-sm rounded-pill shadow-sm">
                        <i class="bi bi-box-arrow-in-right"></i> Login
                    </a>
                <?php 
                } 
                ?>
            </div>
        </div>

        <div id="chat-window"></div>
        <div id="input-container">
            <input type="text" id="prompt-input" placeholder="Ketik pesan Anda di sini...">
            <button id="send-button">Kirim</button>
        </div>
    </div>

    <script>
        // FIREBASE INITIATION BLOCK (Keep this empty if not using Firebase in this version)
        // Jika Anda menjalankan ini di luar Canvas/Firebase, blok ini diabaikan.

        // START CHAT LOGIC
        const chatWindow = document.getElementById("chat-window");
        const promptInput = document.getElementById("prompt-input");
        const sendButton = document.getElementById("send-button");
        // Ganti dengan URL server Node.js/Express Anda yang benar
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
