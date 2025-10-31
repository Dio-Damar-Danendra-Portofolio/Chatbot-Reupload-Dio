<?php
// toggle_lang.php - switches session language and redirects back.
session_start();

require_once __DIR__ . '/language.php';

$supportedLanguages = get_supported_languages();
$currentLang = $_SESSION['lang'] ?? 'id';

$redirect = $_GET['redirect'] ?? 'index.php';

// Allow only same-site relative redirects to prevent open redirects.
if (preg_match('/^(?:https?:)?\\/\\//i', $redirect) || str_contains($redirect, "\r") || str_contains($redirect, "\n")) {
    $redirect = 'index.php';
}

$requestedLang = $_GET['lang'] ?? null;
if ($requestedLang && in_array($requestedLang, $supportedLanguages, true)) {
    $_SESSION['lang'] = $requestedLang;
} else {
    $currentIndex = array_search($currentLang, $supportedLanguages, true);
    if ($currentIndex === false) {
        $currentIndex = 0;
    }
    $nextIndex = ($currentIndex + 1) % count($supportedLanguages);
    $_SESSION['lang'] = $supportedLanguages[$nextIndex];
}

header('Location: ' . $redirect);
exit;
