<?php
// Mulai session PHP di awal skrip
session_start();

// Sertakan file koneksi database
require_once 'config/database.php'; // Pastikan path ini benar

// Inisialisasi variabel untuk pesan feedback (error/sukses)
$_SESSION['error_message'] = "";
$_SESSION['success_message'] = "";

// Cek apakah form registrasi telah disubmit menggunakan metode POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Ambil data dari form registrasi dan bersihkan (trim untuk menghapus spasi awal/akhir)
    $nama_lengkap = trim($_POST["nama_lengkap"]);
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    $konfirmasi_password = trim($_POST["konfirmasi_password"]);
    // Jika Anda menambahkan input username di register.php:
    // $username = trim($_POST["username"]);

    // Validasi input
    if (empty($nama_lengkap)) {
        $_SESSION['error_message'] = "Nama Lengkap tidak boleh kosong.";
        header("location: register.php");
        exit();
    }

    if (empty($email)) {
        $_SESSION['error_message'] = "Email tidak boleh kosong.";
        header("location: register.php");
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = "Format email tidak valid.";
        header("location: register.php");
        exit();
    }

    // Jika Anda mengaktifkan username, tambahkan validasi untuknya:
    // if (!empty($username)) {
    //     // Cek apakah username sudah terdaftar
    //     $sql_check_username = "SELECT id FROM users WHERE username = ?";
    //     if ($stmt_check_username = $conn->prepare($sql_check_username)) {
    //         $stmt_check_username->bind_param("s", $param_username_check);
    //         $param_username_check = $username;
    //         $stmt_check_username->execute();
    //         $stmt_check_username->store_result();
    //         if ($stmt_check_username->num_rows > 0) {
    //             $_SESSION['error_message'] = "Username sudah terdaftar. Silakan gunakan username lain.";
    //             header("location: register.php");
    //             exit();
    //         }
    //         $stmt_check_username->close();
    //     } else {
    //         $_SESSION['error_message'] = "Terjadi kesalahan internal saat cek username. Silakan coba lagi nanti.";
    //         error_log("Register username check prepare error: " . $conn->error);
    //         header("location: register.php");
    //         exit();
    //     }
    // }

    if (empty($password)) {
        $_SESSION['error_message'] = "Password tidak boleh kosong.";
        header("location: register.php");
        exit();
    }

    if (strlen($password) < 6) { // Contoh: minimal 6 karakter untuk password
        $_SESSION['error_message'] = "Password minimal 6 karakter.";
        header("location: register.php");
        exit();
    }

    if (empty($konfirmasi_password)) {
        $_SESSION['error_message'] = "Konfirmasi Password tidak boleh kosong.";
        header("location: register.php");
        exit();
    }

    if ($password !== $konfirmasi_password) {
        $_SESSION['error_message'] = "Password dan Konfirmasi Password tidak cocok.";
        header("location: register.php");
        exit();
    }

    // Cek apakah email sudah terdaftar di database
    $sql_check_email = "SELECT id FROM users WHERE email = ?";
    if ($stmt_check = $conn->prepare($sql_check_email)) {
        $stmt_check->bind_param("s", $param_email_check);
        $param_email_check = $email;
        $stmt_check->execute();
        $stmt_check->store_result();
        if ($stmt_check->num_rows > 0) {
            $_SESSION['error_message'] = "Email sudah terdaftar. Silakan gunakan email lain atau login.";
            header("location: register.php");
            exit();
        }
        $stmt_check->close();
    } else {
        $_SESSION['error_message'] = "Terjadi kesalahan internal saat cek email. Silakan coba lagi nanti.";
        error_log("Register email check prepare error: " . $conn->error);
        header("location: register.php");
        exit();
    }

    // Jika semua validasi lolos, hash password dan simpan data user
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $role = 'user'; // Role default untuk setiap pendaftar baru adalah 'user'

    // Siapkan query INSERT untuk menyimpan data user baru
    // Sesuaikan query dan bind_param jika Anda menyertakan kolom 'username'
    $sql_insert = "INSERT INTO users (nama_lengkap, email, password, role) VALUES (?, ?, ?, ?)";
    // Jika ada username: "INSERT INTO users (nama_lengkap, email, username, password, role) VALUES (?, ?, ?, ?, ?)";

    if ($stmt_insert = $conn->prepare($sql_insert)) {
        // Bind parameter. "ssss" untuk 4 string: nama_lengkap, email, hashed_password, role
        // Jika ada username: "sssss" dan tambahkan $username sebagai parameter kelima
        $stmt_insert->bind_param("ssss", $param_nama_lengkap, $param_email, $param_password_hash, $param_role);

        // Set parameter nilai
        $param_nama_lengkap = $nama_lengkap;
        $param_email = $email;
        // Jika ada username: $param_username = $username;
        $param_password_hash = $hashed_password;
        $param_role = $role;

        // Coba jalankan statement INSERT
        if ($stmt_insert->execute()) {
            // Registrasi berhasil
            $_SESSION['success_message'] = "Akun Anda berhasil dibuat! Silakan login.";
            header("location: login.php"); // Arahkan ke halaman login
            exit();
        } else {
            // Error saat insert data ke database
            $_SESSION['error_message'] = "Terjadi kesalahan saat pendaftaran. Silakan coba lagi nanti.";
            error_log("Register insert error: " . $stmt_insert->error); // Log detail error
            header("location: register.php");
            exit();
        }

        // Tutup statement
        $stmt_insert->close();
    } else {
        // Kesalahan saat menyiapkan statement INSERT
        $_SESSION['error_message'] = "Terjadi kesalahan internal saat pendaftaran. Silakan coba lagi nanti.";
        error_log("Register prepare insert error: " . $conn->error); // Log detail error
        header("location: register.php");
        exit();
    }

    // Tutup koneksi database
    $conn->close();

} else {
    // Jika halaman ini diakses langsung tanpa submit form (bukan POST request),
    // arahkan kembali ke halaman registrasi.
    header("location: register.php");
    exit();
}
?>