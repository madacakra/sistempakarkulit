<?php

/**
 * File: config/database.php
 * Deskripsi: Mengelola koneksi ke database MySQL.
 * Digunakan oleh skrip PHP lainnya untuk berinteraksi dengan database.
 */

// Konfigurasi koneksi database
// Ganti nilai-nilai ini sesuai dengan pengaturan database Anda
define('DB_SERVER', 'localhost'); // Alamat server database (biasanya 'localhost' untuk pengembangan lokal)
define('DB_USERNAME', 'root');    // Nama pengguna database Anda
define('DB_PASSWORD', '');        // Kata sandi database Anda (kosongkan jika tidak ada)
define('DB_NAME', 'sistem_pakar_kulit'); // Nama database yang akan digunakan

// Membuat koneksi ke database menggunakan MySQLi (objek oriented)
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Memeriksa koneksi database
if ($conn->connect_error) {
    // Jika koneksi gagal, hentikan eksekusi skrip dan tampilkan pesan error
    die("Koneksi database gagal: " . $conn->connect_error);
}

// Opsional: Atur charset koneksi ke utf8mb4 untuk mendukung berbagai karakter, termasuk emoji
$conn->set_charset("utf8mb4");

// Anda bisa menambahkan baris ini untuk debugging jika diperlukan.
// Contoh: echo "Koneksi database berhasil!";

?>