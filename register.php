<?php
// Mulai session PHP di awal skrip untuk bisa mengakses pesan dari session
session_start();

// Inisialisasi variabel untuk pesan feedback
$message_type = "";
$message_content = "";

// Cek jika ada pesan sukses atau error dari proses_register.php
if (isset($_SESSION['success_message']) && !empty($_SESSION['success_message'])) {
    $message_type = "success";
    $message_content = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Hapus pesan setelah ditampilkan
} elseif (isset($_SESSION['error_message']) && !empty($_SESSION['error_message'])) {
    $message_type = "danger";
    $message_content = $_SESSION['error_message'];
    unset($_SESSION['error_message']); // Hapus pesan setelah ditampilkan
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - Sistem Pakar Diagnosa Penyakit Kulit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Custom CSS khusus untuk halaman daftar/registrasi */
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #e9ecef; /* Warna latar belakang abu-abu muda */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh; /* Pastikan halaman mengisi tinggi viewport */
            margin: 0;
            padding: 20px; /* Tambahkan padding agar tidak terlalu mepet di mobile */
        }
        .register-container {
            max-width: 500px; /* Lebar maksimum container */
            width: 100%; /* Lebar responsif */
            padding: 35px; /* Padding sedikit lebih besar */
            background-color: #ffffff;
            border-radius: 12px; /* Lebih rounded */
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.15); /* Shadow lebih dalam */
        }
        .register-container h2 {
            margin-bottom: 30px;
            font-weight: 700; /* Lebih tebal */
            color: #343a40;
            text-align: center;
            font-size: 2rem; /* Ukuran judul lebih besar */
        }
        .register-container .form-label {
            font-weight: 500;
            color: #495057;
        }
        .form-control {
            border-radius: 8px; /* Input field lebih rounded */
            padding: 10px 15px;
        }
        .btn-register {
            width: 100%;
            padding: 12px; /* Tombol lebih besar */
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 50px; /* Tombol sangat rounded */
            transition: all 0.3s ease;
        }
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 123, 255, 0.3);
        }
        .text-center a {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        .text-center a:hover {
            color: #0056b3;
            text-decoration: underline;
        }
        .alert {
            margin-bottom: 20px; /* Spasi bawah alert */
            border-radius: 8px;
            font-size: 0.95rem;
        }
    </style>
</head>
<body>

    <div class="register-container">
        <h2><i class="fas fa-user-plus me-2"></i>Daftar Akun Baru</h2>
        
        <?php if ($message_content): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message_content; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form action="proses_register.php" method="POST">
            <div class="mb-3">
                <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="mb-4">
                <label for="konfirmasi_password" class="form-label">Konfirmasi Password</label>
                <input type="password" class="form-control" id="konfirmasi_password" name="konfirmasi_password" required>
            </div>
            <div class="d-grid gap-2 mb-3">
                <button type="submit" class="btn btn-primary btn-register">Daftar Sekarang</button>
            </div>
            <p class="text-center text-muted">Sudah punya akun? <a href="login.php">Login di sini</a></p>
            <p class="text-center"><a href="index.php">Kembali ke Beranda</a></p>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="js/script.js"></script>
</body>
</html>