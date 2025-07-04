<?php
// Mulai session PHP di awal skrip
session_start();

// Sertakan file koneksi database
require_once 'config/database.php'; // Pastikan path ini benar

// Inisialisasi variabel untuk pesan error
$_SESSION['error_message'] = "";

// Cek apakah form login telah disubmit menggunakan metode POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil data dari form dan bersihkan (sanitize)
    // trim() digunakan untuk menghapus spasi kosong (atau karakter lain) dari awal dan akhir string.
    $username_email = trim($_POST["username_email"]);
    $password = trim($_POST["password"]);

    // Validasi input
    if (empty($username_email)) {
        $_SESSION['error_message'] = "Username atau Email tidak boleh kosong.";
        header("location: login.php");
        exit(); // Hentikan eksekusi skrip setelah redirect
    }

    if (empty($password)) {
        $_SESSION['error_message'] = "Password tidak boleh kosong.";
        header("location: login.php");
        exit();
    }

    // Siapkan query SQL untuk mencari user berdasarkan email atau username
    // Menggunakan prepared statements untuk mencegah SQL Injection, ini adalah praktik terbaik.
    // Kolom 'role' juga diambil untuk menentukan pengarahan setelah login.
    $sql = "SELECT id, nama_lengkap, email, role, password FROM users WHERE email = ? OR username = ?";

    // Persiapkan statement SQL
    if ($stmt = $conn->prepare($sql)) {
        // Bind parameter ke statement yang disiapkan.
        // "ss" menunjukkan bahwa ada dua parameter yang keduanya adalah string.
        $stmt->bind_param("ss", $param_username_email, $param_username_email);

        // Set parameter nilai (nilai yang sama untuk email dan username)
        $param_username_email = $username_email;

        // Coba jalankan statement
        if ($stmt->execute()) {
            // Simpan hasil query untuk bisa mengambil jumlah baris
            $stmt->store_result();

            // Cek jika user ditemukan (harus ada tepat 1 baris)
            if ($stmt->num_rows == 1) {
                // Bind hasil kolom dari query ke variabel PHP
                // Pastikan urutan variabel sesuai dengan urutan kolom di SELECT query
                $stmt->bind_result($id, $nama_lengkap, $email, $role, $hashed_password);

                // Ambil (fetch) hasil baris
                if ($stmt->fetch()) {
                    // Verifikasi password yang dimasukkan dengan hashed password di database.
                    // password_verify() adalah fungsi yang aman untuk membandingkan password hash.
                    if (password_verify($password, $hashed_password)) {
                        // Password benar, user berhasil login.
                        // Mulai session baru dan simpan data user ke variabel session.

                        // session_regenerate_id(true) sangat direkomendasikan untuk keamanan
                        // (mencegah Session Fixation Attacks)
                        session_regenerate_id(true);

                        $_SESSION['loggedin'] = true; // Tandai bahwa user sudah login
                        $_SESSION['id'] = $id;
                        $_SESSION['nama_lengkap'] = $nama_lengkap;
                        $_SESSION['email'] = $email;
                        $_SESSION['role'] = $role; // Simpan role pengguna

                        // Redirect user ke halaman yang sesuai berdasarkan role mereka
                        if ($role === 'admin') {
                            // Contoh: Admin diarahkan ke dashboard khusus admin
                            header("location: admin/dashboard.php");
                        } else { // Jika role bukan admin, asumsikan user biasa
                            // Contoh: User biasa diarahkan ke halaman diagnosa
                            header("location: user/diagnosa.php");
                        }
                        exit(); // Penting: Hentikan eksekusi skrip setelah redirect
                    } else {
                        // Password salah
                        $_SESSION['error_message'] = "Password yang Anda masukkan salah.";
                        header("location: login.php");
                        exit();
                    }
                }
            } else {
                // Username atau Email tidak ditemukan di database
                $_SESSION['error_message'] = "Username atau Email tidak ditemukan.";
                header("location: login.php");
                exit();
            }
        } else {
            // Terjadi kesalahan saat menjalankan query (misalnya, masalah database)
            $_SESSION['error_message'] = "Terjadi kesalahan saat otentikasi. Silakan coba lagi nanti.";
            // Log error detail untuk debugging (error_log tidak ditampilkan ke user)
            error_log("Login execute error: " . $stmt->error);
            header("location: login.php");
            exit();
        }

        // Tutup statement yang sudah disiapkan
        $stmt->close();
    } else {
        // Terjadi kesalahan saat menyiapkan statement (misalnya, query SQL salah)
        $_SESSION['error_message'] = "Terjadi kesalahan internal. Silakan coba lagi nanti.";
        // Log error detail untuk debugging
        error_log("Login prepare error: " . $conn->error);
        header("location: login.php");
        exit();
    }

    // Tutup koneksi database
    $conn->close();

} else {
    // Jika halaman ini diakses langsung tanpa submit form (bukan POST request),
    // arahkan kembali ke halaman login.
    header("location: login.php");
    exit();
}
?>