<?php
// Mulai session PHP untuk bisa mengambil pesan error jika ada
session_start();

// Ambil pesan error dari session jika ada, yang mungkin diatur oleh proses_login.php
$error_message = "";
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']); // Hapus pesan setelah diambil
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akses Ditolak - Sistem Pakar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* CSS khusus untuk halaman akses ditolak */
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa; /* Latar belakang terang */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            text-align: center;
            color: #343a40;
        }
        .unauthorized-container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 90%;
        }
        .unauthorized-container .icon {
            font-size: 5rem;
            color: #dc3545; /* Warna merah untuk peringatan */
            margin-bottom: 20px;
        }
        .unauthorized-container h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #dc3545;
            margin-bottom: 15px;
        }
        .unauthorized-container p {
            font-size: 1.1rem;
            margin-bottom: 25px;
        }
        .unauthorized-container .btn {
            padding: 10px 25px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 50px;
            margin: 0 10px;
            transition: all 0.3s ease;
        }
        .unauthorized-container .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        .unauthorized-container .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
            transform: translateY(-2px);
        }
        .unauthorized-container .btn-outline-secondary {
            color: #6c757d;
            border-color: #6c757d;
        }
        .unauthorized-container .btn-outline-secondary:hover {
            background-color: #6c757d;
            color: white;
            transform: translateY(-2px);
        }
        .alert-custom {
            margin-top: 20px;
            font-size: 0.9rem;
            color: #856404;
            background-color: #fff3cd;
            border-color: #ffeeba;
            padding: 10px 15px;
            border-radius: 8px;
        }
    </style>
</head>
<body>

    <div class="unauthorized-container">
        <div class="icon">
            <i class="fas fa-ban"></i>
        </div>
        <h1>Akses Ditolak!</h1>
        <p>Anda tidak memiliki izin untuk mengakses halaman ini.</p>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-warning alert-custom" role="alert">
                <strong>Pesan Sistem:</strong> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="mt-4">
            <a href="javascript:history.back()" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i> Kembali
            </a>
            <a href="login.php" class="btn btn-primary">
                <i class="fas fa-sign-in-alt me-2"></i> Login Ulang
            </a>
            <a href="index.php" class="btn btn-info mt-2 mt-md-0">
                <i class="fas fa-home me-2"></i> Ke Beranda
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>