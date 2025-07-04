<?php
// Mulai session PHP
// session_start() harus dipanggil di setiap halaman yang menggunakan atau memanipulasi session.
session_start();

// Hapus semua variabel sesi.
// Ini menghapus semua data yang tersimpan di $_SESSION.
$_SESSION = array();

// Jika ingin menghapus cookie sesi, periksa apakah cookie sesi ada
// dan atur masa berlakunya ke waktu di masa lalu.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Terakhir, hancurkan sesi.
// Ini secara fisik menghapus file sesi dari server.
session_destroy();

// Arahkan pengguna kembali ke halaman login atau halaman beranda.
// Disarankan ke halaman login agar pengguna bisa langsung masuk kembali.
header("location: login.php"); // Atau bisa juga "index.php"
exit(); // Penting untuk menghentikan eksekusi skrip setelah redirect
?>