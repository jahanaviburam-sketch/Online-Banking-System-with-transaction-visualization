<?php
$sessionDirectory = __DIR__ . '/sessions';

if (!is_dir($sessionDirectory)) {
    mkdir($sessionDirectory, 0777, true);
}

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

session_save_path($sessionDirectory);
session_start();
?>
