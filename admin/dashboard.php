<?php
// Mulai session PHP
session_start();

// Proteksi halaman: Cek apakah user sudah login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $_SESSION['error_message'] = "Anda harus login untuk mengakses halaman ini.";
    header("location: ../login.php"); // Redirect ke halaman login di root
    exit();
}

// Proteksi role: Cek apakah user adalah admin
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = "Anda tidak memiliki hak akses ke halaman ini.";
    header("location: ../unauthorized.php"); // Atau ke index.php, atau halaman lain
    exit();
}

// Sertakan file koneksi database
require_once '../config/database.php'; // Sesuaikan path jika perlu

// Ambil statistik dasar (opsional)
$total_users = 0;
$total_diseases = 0;
$total_symptoms = 0;
$total_rules = 0;
$total_diagnoses = 0;

try {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM users");
    $stmt->execute();
    $result = $stmt->get_result();
    $total_users = $result->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM diseases");
    $stmt->execute();
    $result = $stmt->get_result();
    $total_diseases = $result->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM symptoms");
    $stmt->execute();
    $result = $stmt->get_result();
    $total_symptoms = $result->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM rules");
    $stmt->execute();
    $result = $stmt->get_result();
    $total_rules = $result->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM diagnoses");
    $stmt->execute();
    $result = $stmt->get_result();
    $total_diagnoses = $result->fetch_assoc()['total'];
    $stmt->close();

} catch (Exception $e) {
    error_log("Error fetching dashboard stats: " . $e->getMessage());
    // Anda bisa set pesan error ke session jika ingin menampilkannya
} finally {
    $conn->close(); // Tutup koneksi setelah selesai menggunakan database
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Sistem Pakar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* CSS khusus untuk dashboard admin */
        body {
            background-color: #f4f7f6; /* Latar belakang abu-abu yang nyaman */
            padding-top: 70px;
        }
        .sidebar {
            height: 100vh;
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            background-color: #343a40; /* Dark sidebar */
            color: white;
            padding-top: 60px; /* Space for fixed navbar */
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 15px 20px;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background-color: #007bff; /* Primary color for active/hover */
            border-left: 5px solid #ffc107; /* Highlight active link */
        }
        .main-content {
            margin-left: 250px; /* Offset for sidebar */
            padding: 20px;
        }
        .card-stats {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
            cursor: pointer;
        }
        .card-stats:hover {
            transform: translateY(-5px);
        }
        .card-stats .icon-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.8rem;
            color: white;
            margin-right: 15px;
        }
        .bg-primary-light { background-color: #e6f0ff !important; color: #007bff !important; }
        .bg-success-light { background-color: #e6ffe6 !important; color: #28a745 !important; }
        .bg-info-light { background-color: #e0f7fa !important; color: #17a2b8 !important; }
        .bg-warning-light { background-color: #fff9e6 !important; color: #ffc107 !important; }
        .bg-danger-light { background-color: #ffe6e6 !important; color: #dc3545 !important; }

        .icon-circle.bg-primary { background-color: #007bff !important; }
        .icon-circle.bg-success { background-color: #28a745 !important; }
        .icon-circle.bg-info { background-color: #17a2b8 !important; }
        .icon-circle.bg-warning { background-color: #ffc107 !important; }
        .icon-circle.bg-danger { background-color: #dc3545 !important; }
        
        .card-stats .card-title {
            font-weight: 600;
            color: #495057;
        }
        .card-stats .card-text {
            font-size: 2rem;
            font-weight: 700;
            color: #343a40;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                padding-top: 0;
            }
            .main-content {
                margin-left: 0;
                padding-top: 20px;
            }
            body {
                padding-top: 0;
            }
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="../index.php">
                <i class="fas fa-tools me-2"></i> Admin Panel
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="adminNavbar">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownAdmin" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-shield me-1"></i> Halo, Admin <?php echo htmlspecialchars($_SESSION['nama_lengkap']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdownAdmin">
                            <li><a class="dropdown-item" href="#">Profil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="sidebar d-flex flex-column p-3">
        <h5 class="text-white text-center mt-3 mb-4"><i class="fas fa-cogs me-2"></i> Menu Admin</h5>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="manage_users.php">
                    <i class="fas fa-users-cog me-2"></i> Manajemen Pengguna
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="manage_diseases.php">
                    <i class="fas fa-viruses me-2"></i> Manajemen Penyakit
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="manage_symptoms.php">
                    <i class="fas fa-head-side-cough me-2"></i> Manajemen Gejala
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="manage_rules.php">
                    <i class="fas fa-list-ol me-2"></i> Manajemen Aturan
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="view_diagnoses_history.php">
                    <i class="fas fa-history me-2"></i> Riwayat Diagnosa
                </a>
            </li>
            <li class="nav-item mt-auto">
                <a class="nav-link text-danger" href="../logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                </a>
            </li>
        </ul>
    </div>

    <div class="main-content">
        <h1 class="mb-4 text-primary"><i class="fas fa-tachometer-alt me-3"></i>Dashboard Admin</h1>
        <p class="lead">Selamat datang, Admin **<?php echo htmlspecialchars($_SESSION['nama_lengkap']); ?>**. Anda dapat mengelola seluruh aspek sistem di sini.</p>

        <div class="row mt-5">
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card card-stats p-3 bg-primary-light">
                    <div class="d-flex align-items-center">
                        <div class="icon-circle bg-primary me-3">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <h5 class="card-title text-primary">Total Pengguna</h5>
                            <p class="card-text text-primary"><?php echo $total_users; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card card-stats p-3 bg-success-light">
                    <div class="d-flex align-items-center">
                        <div class="icon-circle bg-success me-3">
                            <i class="fas fa-viruses"></i>
                        </div>
                        <div>
                            <h5 class="card-title text-success">Total Penyakit</h5>
                            <p class="card-text text-success"><?php echo $total_diseases; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card card-stats p-3 bg-info-light">
                    <div class="d-flex align-items-center">
                        <div class="icon-circle bg-info me-3">
                            <i class="fas fa-head-side-cough"></i>
                        </div>
                        <div>
                            <h5 class="card-title text-info">Total Gejala</h5>
                            <p class="card-text text-info"><?php echo $total_symptoms; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card card-stats p-3 bg-warning-light">
                    <div class="d-flex align-items-center">
                        <div class="icon-circle bg-warning me-3">
                            <i class="fas fa-book"></i>
                        </div>
                        <div>
                            <h5 class="card-title text-warning">Total Aturan</h5>
                            <p class="card-text text-warning"><?php echo $total_rules; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card card-stats p-3 bg-danger-light">
                    <div class="d-flex align-items-center">
                        <div class="icon-circle bg-danger me-3">
                            <i class="fas fa-history"></i>
                        </div>
                        <div>
                            <h5 class="card-title text-danger">Riwayat Diagnosa</h5>
                            <p class="card-text text-danger"><?php echo $total_diagnoses; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-5 shadow-sm">
            <div class="card-header bg-primary text-white fw-bold">
                <i class="fas fa-chart-pie me-2"></i> Ringkasan Sistem
            </div>
            <div class="card-body">
                <p>Dashboard ini menyediakan gambaran umum tentang data yang tersimpan dalam sistem pakar.</p>
                <p>Anda dapat menggunakan menu di samping untuk navigasi dan mengelola setiap entitas.</p>
                <p class="text-muted small">Terakhir diperbarui: <?php echo date('d M Y, H:i'); ?></p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>