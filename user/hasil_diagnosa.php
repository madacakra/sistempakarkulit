<?php
// Mulai session PHP
session_start();

// Proteksi halaman: Cek apakah user sudah login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $_SESSION['error_message'] = "Anda harus login untuk mengakses halaman ini.";
    header("location: login.php");
    exit();
}

// Cek apakah ada hasil diagnosa di session
if (!isset($_SESSION['diagnosa_result']) || !isset($_SESSION['selected_gejala_names'])) {
    $_SESSION['error_message'] = "Tidak ada hasil diagnosa untuk ditampilkan. Silakan lakukan diagnosa terlebih dahulu.";
    header("location: user/diagnosa.php");
    exit();
}

// Ambil hasil diagnosa dari session
$final_diagnosis = $_SESSION['diagnosa_result'];
$selected_gejala_names = $_SESSION['selected_gejala_names'];

// Hapus hasil diagnosa dari session setelah ditampilkan (opsional, bisa juga dipertahankan untuk riwayat sesi)
unset($_SESSION['diagnosa_result']);
unset($_SESSION['selected_gejala_names']);

// Fungsi untuk menginterpretasikan nilai Certainty Factor (dari dokumen Anda, Tabel 2.1)
function interpretCF($cf_value) {
    if ($cf_value == 0) {
        return "Tidak Pasti";
    } elseif ($cf_value > 0 && $cf_value <= 0.2) {
        return "Kurang Pasti";
    } elseif ($cf_value > 0.2 && $cf_value <= 0.4) {
        return "Mungkin";
    } elseif ($cf_value > 0.4 && $cf_value <= 0.6) {
        return "Kemungkinan Besar";
    } elseif ($cf_value > 0.6 && $cf_value <= 0.8) {
        return "Hampir Pasti";
    } elseif ($cf_value > 0.8 && $cf_value <= 1.0) {
        return "Pasti";
    } else {
        return "Tidak Diketahui"; // Untuk nilai di luar rentang 0-1
    }
}

// Konversi CF ke persentase untuk tampilan
$cf_percentage = round($final_diagnosis['cf_total'] * 100, 2);
$cf_interpretation = interpretCF($final_diagnosis['cf_total']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Diagnosa - Sistem Pakar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* CSS khusus untuk halaman hasil diagnosa */
        body {
            background-color: #e9f2fa; /* Latar belakang lebih terang */
            padding-top: 70px;
        }
        .result-container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        .result-title {
            color: #007bff;
            font-weight: 700;
            margin-bottom: 25px;
        }
        .result-box {
            background-color: #f0f8ff;
            border: 1px solid #cce5ff;
            border-left: 5px solid #007bff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .result-box h4 {
            color: #007bff;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .result-box p {
            margin-bottom: 8px;
        }
        .progress-bar {
            background-color: #28a745; /* Hijau untuk progress bar */
        }
        .gejala-list {
            list-style-type: none;
            padding-left: 0;
            columns: 2; /* Tampilkan dalam 2 kolom */
            -webkit-columns: 2;
            -moz-columns: 2;
        }
        .gejala-list li {
            margin-bottom: 5px;
            font-size: 0.95rem;
        }
        .action-buttons .btn {
            padding: 10px 25px;
            font-size: 1.05rem;
            font-weight: 600;
            border-radius: 50px;
            margin: 0 10px;
        }
        .action-buttons .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        .action-buttons .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .action-buttons .btn-outline-secondary {
            color: #6c757d;
            border-color: #6c757d;
        }
        .action-buttons .btn-outline-secondary:hover {
            background-color: #6c757d;
            color: white;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <i class="fas fa-stethoscope me-2"></i> Sistem Pakar Kulit
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Beranda</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="user/diagnosa.php">Diagnosa Ulang</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle btn btn-outline-light text-white ms-lg-3" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['nama_lengkap']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="<?php echo $_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : '#'; ?>">Dashboard <?php echo htmlspecialchars(ucfirst($_SESSION['role'])); ?></a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="result-container">
            <h2 class="result-title text-center mb-5"><i class="fas fa-vial me-3"></i>Hasil Diagnosa Penyakit Kulit</h2>

            <?php if (!empty($final_diagnosis)): ?>
                <div class="result-box">
                    <h4><i class="fas fa-tag me-2"></i>Penyakit Terdiagnosa: <span class="text-primary"><?php echo htmlspecialchars($final_diagnosis['nama_penyakit']); ?></span></h4>
                    <p class="mb-2"><strong>Tingkat Kepastian (Certainty Factor):</strong></p>
                    <div class="progress mb-3" role="progressbar" aria-label="Certainty Factor" aria-valuenow="<?php echo $cf_percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar" style="width: <?php echo $cf_percentage; ?>%"><?php echo $cf_percentage; ?>%</div>
                    </div>
                    <p class="mb-3">Interpretasi: <span class="fw-bold"><?php echo htmlspecialchars($cf_interpretation); ?></span></p>

                    <h4><i class="fas fa-file-alt me-2"></i>Deskripsi Penyakit:</h4>
                    <p><?php echo htmlspecialchars($final_diagnosis['deskripsi_penyakit']); ?></p>

                    <h4><i class="fas fa-medkit me-2"></i>Solusi/Penanganan:</h4>
                    <p><?php echo htmlspecialchars($final_diagnosis['solusi_penyakit']); ?></p>
                </div>

                <div class="result-box">
                    <h4><i class="fas fa-list-check me-2"></i>Gejala yang Anda Pilih:</h4>
                    <?php if (!empty($selected_gejala_names)): ?>
                        <ul class="gejala-list">
                            <?php foreach ($selected_gejala_names as $gejala_name): ?>
                                <li><i class="fas fa-check-circle text-success me-2"></i><?php echo htmlspecialchars($gejala_name); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted">Tidak ada gejala yang tercatat.</p>
                    <?php endif; ?>
                </div>

                <div class="text-center mt-5 action-buttons">
                    <a href="user/diagnosa.php" class="btn btn-primary">
                        <i class="fas fa-redo-alt me-2"></i> Diagnosa Ulang
                    </a>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-home me-2"></i> Kembali ke Beranda
                    </a>
                </div>

            <?php else: ?>
                <div class="alert alert-warning text-center" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> Maaf, tidak ada hasil diagnosa yang valid ditemukan.
                    <p class="mt-2">Silakan kembali ke halaman diagnosa dan coba lagi.</p>
                    <a href="user/diagnosa.php" class="btn btn-warning mt-3"><i class="fas fa-arrow-left me-2"></i> Kembali ke Diagnosa</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer id="kontak" class="footer mt-auto py-4 bg-dark text-white">
        <div class="container text-center">
            <p class="mb-1">&copy; 2025 Sistem Pakar Diagnosa Penyakit Kulit. Hak Cipta Dilindungi.</p>
            <p class="mb-0">Institut Teknologi dan Bisnis Ahmad Dahlan, Jakarta</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>