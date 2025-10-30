<?php // language.php
function get_texts($lang) {
    $texts = [
        'id' => [
            'new_chat_title' => 'Chat Baru...',
            'send-button' => 'Kirim',
            'file_upload' => 'Unggah Berkas',
            'remove_file' => 'Hapus Berkas',
            'edit_message' => 'Sunting',
            'edit_message_mode' => 'Mode Sunting Pesan',
            'copy_message' => 'Salin',
            'image_upload' => 'Unggahan Gambar',
            'save_btn' => 'Simpan',
            'cancel_btn' => 'Batal',
            'delete_chat' => 'Hapus Chat',
            'delete_chat_confirm' => 'Anda yakin ingin menghapus chat ini?',
            'type_message' => 'Ketik pesan Anda...',
            'new_chat_menu' => 'Mulai Chat Baru',
            'welcome_msg' => 'Selamat Datang',
            'start_chat_prompt' => 'Mulai chat baru dengan mengetik pesan di bawah.',
            'profile_menu' => 'Profil',
            'logout_menu' => 'Keluar',
            'gemini_error' => 'Gagal mendapatkan respons dari Chatbot.'
        ],
        'en' => [
            'new_chat_title' => 'New Chat...',
            'send-button' => 'Send',
            'file_upload' => 'Upload File',
            'remove_file' => 'Remove File',
            'edit_message' => 'Edit',
            'edit_message_mode' => 'Edit Message Mode',
            'copy_message' => 'Copy',
            'image_upload' => 'Image Upload',
            'save_btn' => 'Save',
            'cancel_btn' => 'Cancel',
            'delete_chat' => 'Delete Chat',
            'delete_chat_confirm' => 'Are you sure you want to delete this chat?',
            'type_message' => 'Type your message...',
            'new_chat_menu' => 'Start New Chat',
            'welcome_msg' => 'Welcome',
            'start_chat_prompt' => 'Start a new chat by typing your message below.',
            'profile_menu' => 'Profile',
            'logout_menu' => 'Logout',
            'gemini_error' => 'Failed to get response from Chatbot.'
        ],
    ];
    return $texts[$lang] ?? $texts['id'];
}
?>