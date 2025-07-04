<?php
// Mulai session PHP
session_start();

// Proteksi halaman: Cek apakah user sudah login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $_SESSION['error_message'] = "Anda harus login untuk mengakses halaman ini.";
    header("location: ../login.php"); // Redirect ke halaman login di root
    exit();
}

// Sertakan file koneksi database
require_once '../config/database.php'; // Sesuaikan path

// Inisialisasi variabel untuk pesan feedback
$message_type = "";
$message_content = "";

if (isset($_SESSION['success_message'])) {
    $message_type = "success";
    $message_content = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
} elseif (isset($_SESSION['error_message'])) {
    $message_type = "danger";
    $message_content = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// --- Logika Update Profil ---
$current_user_id = $_SESSION['id'];
$user_data = null; // Untuk menyimpan data user yang sedang login

// Ambil data user yang sedang login
$sql_select_user = "SELECT id, nama_lengkap, email, username, role, created_at FROM users WHERE id = ?";
if ($stmt_select = $conn->prepare($sql_select_user)) {
    $stmt_select->bind_param("i", $current_user_id);
    $stmt_select->execute();
    $result_select = $stmt_select->get_result();
    if ($result_select->num_rows == 1) {
        $user_data = $result_select->fetch_assoc();
    } else {
        // Jika data user tidak ditemukan (aneh, harusnya selalu ada jika sudah login)
        $_SESSION['error_message'] = "Data pengguna tidak ditemukan.";
        header("location: ../logout.php"); // Atau redirect ke halaman error
        exit();
    }
    $stmt_select->close();
} else {
    $_SESSION['error_message'] = "Terjadi kesalahan saat mengambil data profil.";
    error_log("Error preparing select user profile: " . $conn->error);
}


// Handle POST requests untuk update profil atau password
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        // --- Update Profil (Nama, Email, Username) ---
        if ($action == 'update_profile') {
            $nama_lengkap = trim($_POST['nama_lengkap']);
            $email = trim($_POST['email']);
            $username = !empty($_POST['username']) ? trim($_POST['username']) : null;

            // Validasi
            if (empty($nama_lengkap) || empty($email)) {
                $_SESSION['error_message'] = "Nama lengkap dan email tidak boleh kosong.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error_message'] = "Format email tidak valid.";
            } else {
                // Cek duplikasi email (kecuali untuk user itu sendiri)
                $sql_check_email = "SELECT id FROM users WHERE email = ? AND id != ?";
                $stmt_check = $conn->prepare($sql_check_email);
                $stmt_check->bind_param("si", $email, $current_user_id);
                $stmt_check->execute();
                $stmt_check->store_result();
                if ($stmt_check->num_rows > 0) {
                    $_SESSION['error_message'] = "Email sudah terdaftar untuk pengguna lain.";
                } else {
                    // Update ke database
                    $sql_update = "UPDATE users SET nama_lengkap = ?, email = ?, username = ? WHERE id = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->bind_param("sssi", $nama_lengkap, $email, $username, $current_user_id);
                    
                    if ($stmt_update->execute()) {
                        $_SESSION['success_message'] = "Profil berhasil diperbarui.";
                        // Update session info juga
                        $_SESSION['nama_lengkap'] = $nama_lengkap;
                        $_SESSION['email'] = $email;
                    } else {
                        $_SESSION['error_message'] = "Gagal memperbarui profil: " . $stmt_update->error;
                    }
                    $stmt_update->close();
                }
                $stmt_check->close();
            }
        }
        // --- Update Password ---
        elseif ($action == 'change_password') {
            $current_password = trim($_POST['current_password']);
            $new_password = trim($_POST['new_password']);
            $confirm_new_password = trim($_POST['confirm_new_password']);

            // Validasi password
            if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
                $_SESSION['error_message'] = "Semua field password harus diisi.";
            } elseif (strlen($new_password) < 6) {
                $_SESSION['error_message'] = "Password baru minimal 6 karakter.";
            } elseif ($new_password !== $confirm_new_password) {
                $_SESSION['error_message'] = "Password baru dan konfirmasi password tidak cocok.";
            } else {
                // Verifikasi password lama
                if (!password_verify($current_password, $user_data['password'])) { // Menggunakan $user_data dari SELECT awal
                    $_SESSION['error_message'] = "Password lama salah.";
                } else {
                    // Hash password baru
                    $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);

                    // Update password di database
                    $sql_update_pass = "UPDATE users SET password = ? WHERE id = ?";
                    $stmt_update_pass = $conn->prepare($sql_update_pass);
                    $stmt_update_pass->bind_param("si", $hashed_new_password, $current_user_id);
                    
                    if ($stmt_update_pass->execute()) {
                        $_SESSION['success_message'] = "Password berhasil diubah.";
                    } else {
                        $_SESSION['error_message'] = "Gagal mengubah password: " . $stmt_update_pass->error;
                    }
                    $stmt_update_pass->close();
                }
            }
        }
    }
    // Redirect setelah POST untuk mencegah resubmission dan memperbarui pesan
    header("location: profile.php");
    exit();
}

$conn->close(); // Tutup koneksi setelah semua operasi database selesai
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - Sistem Pakar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* CSS khusus untuk halaman profil */
        body {
            background-color: #f0f2f5;
            padding-top: 70px; /* Space for fixed navbar */
        }
        .profile-container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 0 25px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }
        .profile-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .profile-header .icon {
            font-size: 4rem;
            color: #007bff;
            margin-bottom: 15px;
        }
        .profile-header h2 {
            font-weight: 700;
            color: #343a40;
        }
        .card-profile-section {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        .card-profile-section .card-header {
            font-weight: 600;
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            color: #495057;
        }
        .form-label {
            font-weight: 500;
        }
        .form-control {
            border-radius: 8px;
            padding: 10px 15px;
        }
        .btn-update {
            border-radius: 50px;
            padding: 10px 25px;
            font-weight: 600;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="../index.php">
                <i class="fas fa-stethoscope me-2"></i> Sistem Pakar Kulit
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">Beranda</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../user/diagnosa.php">Diagnosa</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle btn btn-outline-light text-white ms-lg-3" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['nama_lengkap']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item active" href="profile.php">Profil Saya</a></li>
                            <li><a class="dropdown-item" href="diagnoses_history.php">Riwayat Diagnosa</a></li>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../admin/dashboard.php">Dashboard Admin</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="profile-container">
            <div class="profile-header">
                <div class="icon"><i class="fas fa-user-circle"></i></div>
                <h2>Profil Saya</h2>
                <p class="text-muted">Kelola informasi akun Anda.</p>
            </div>

            <?php if ($message_content): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message_content; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card card-profile-section">
                <div class="card-header">
                    <i class="fas fa-address-card me-2"></i> Informasi Akun
                </div>
                <div class="card-body">
                    <form action="profile.php" method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="mb-3">
                            <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" value="<?php echo htmlspecialchars($user_data['nama_lengkap']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username (Opsional)</label>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user_data['username'] ?? ''); ?>">
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-update">Simpan Perubahan Profil</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card card-profile-section">
                <div class="card-header">
                    <i class="fas fa-key me-2"></i> Ganti Password
                </div>
                <div class="card-body">
                    <form action="profile.php" method="POST">
                        <input type="hidden" name="action" value="change_password">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Password Lama</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">Password Baru</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_new_password" class="form-label">Konfirmasi Password Baru</label>
                            <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" required>
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-warning btn-update text-white">Ubah Password</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>