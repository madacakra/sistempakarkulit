<?php
// Mulai session PHP
session_start();

// Proteksi halaman: Cek apakah user sudah login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $_SESSION['error_message'] = "Anda harus login untuk mengakses halaman ini.";
    header("location: ../login.php");
    exit();
}

// Proteksi role: Cek apakah user adalah admin
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = "Anda tidak memiliki hak akses ke halaman ini.";
    header("location: ../unauthorized.php");
    exit();
}

// Sertakan file koneksi database
require_once '../config/database.php';

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

// --- Logika CRUD ---

// Mode default: Tampilkan daftar pengguna
$mode = 'view';
$user_to_edit = null; // Untuk menyimpan data user yang akan diedit

// Handle request POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        // --- Tambah Pengguna ---
        if ($action == 'add_user') {
            $nama_lengkap = trim($_POST['nama_lengkap']);
            $email = trim($_POST['email']);
            $username = !empty($_POST['username']) ? trim($_POST['username']) : null; // Username bisa null
            $password = trim($_POST['password']);
            $role = trim($_POST['role']);

            // Validasi sederhana
            if (empty($nama_lengkap) || empty($email) || empty($password) || empty($role)) {
                $_SESSION['error_message'] = "Nama lengkap, email, password, dan role tidak boleh kosong.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error_message'] = "Format email tidak valid.";
            } elseif (strlen($password) < 6) {
                $_SESSION['error_message'] = "Password minimal 6 karakter.";
            } else {
                // Cek duplikasi email
                $sql_check_email = "SELECT id FROM users WHERE email = ?";
                $stmt_check = $conn->prepare($sql_check_email);
                $stmt_check->bind_param("s", $email);
                $stmt_check->execute();
                $stmt_check->store_result();
                if ($stmt_check->num_rows > 0) {
                    $_SESSION['error_message'] = "Email sudah terdaftar.";
                } else {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // Insert ke database
                    $sql_insert = "INSERT INTO users (nama_lengkap, email, username, password, role) VALUES (?, ?, ?, ?, ?)";
                    $stmt_insert = $conn->prepare($sql_insert);
                    $stmt_insert->bind_param("sssss", $nama_lengkap, $email, $username, $hashed_password, $role);

                    if ($stmt_insert->execute()) {
                        $_SESSION['success_message'] = "Pengguna berhasil ditambahkan.";
                    } else {
                        $_SESSION['error_message'] = "Gagal menambahkan pengguna: " . $stmt_insert->error;
                    }
                    $stmt_insert->close();
                }
                $stmt_check->close();
            }
        }
        // --- Edit Pengguna ---
        elseif ($action == 'edit_user_submit') {
            $user_id = $_POST['user_id'];
            $nama_lengkap = trim($_POST['nama_lengkap']);
            $email = trim($_POST['email']);
            $username = !empty($_POST['username']) ? trim($_POST['username']) : null;
            $role = trim($_POST['role']);
            $password = trim($_POST['password']); // Password bisa kosong jika tidak diubah

            // Validasi sederhana
            if (empty($nama_lengkap) || empty($email) || empty($role)) {
                $_SESSION['error_message'] = "Nama lengkap, email, dan role tidak boleh kosong.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error_message'] = "Format email tidak valid.";
            } else {
                // Cek duplikasi email (kecuali untuk user itu sendiri)
                $sql_check_email = "SELECT id FROM users WHERE email = ? AND id != ?";
                $stmt_check = $conn->prepare($sql_check_email);
                $stmt_check->bind_param("si", $email, $user_id);
                $stmt_check->execute();
                $stmt_check->store_result();
                if ($stmt_check->num_rows > 0) {
                    $_SESSION['error_message'] = "Email sudah terdaftar untuk pengguna lain.";
                } else {
                    $sql_update = "UPDATE users SET nama_lengkap = ?, email = ?, username = ?, role = ? ";
                    if (!empty($password)) {
                        if (strlen($password) < 6) {
                            $_SESSION['error_message'] = "Password minimal 6 karakter jika ingin diubah.";
                            // Hentikan proses update password, lanjutkan update data lainnya
                        } else {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $sql_update .= ", password = ? ";
                        }
                    }
                    $sql_update .= "WHERE id = ?";

                    $stmt_update = $conn->prepare($sql_update);
                    
                    if (!empty($password) && !isset($_SESSION['error_message'])) { // Jika password diubah dan tidak ada error validasi password
                        $stmt_update->bind_param("sssssi", $nama_lengkap, $email, $username, $role, $hashed_password, $user_id);
                    } else { // Jika password tidak diubah atau ada error validasi password
                        $stmt_update->bind_param("ssssi", $nama_lengkap, $email, $username, $role, $user_id);
                    }
                    
                    if ($stmt_update->execute()) {
                        $_SESSION['success_message'] = "Pengguna berhasil diperbarui.";
                    } else {
                        $_SESSION['error_message'] = "Gagal memperbarui pengguna: " . $stmt_update->error;
                    }
                    $stmt_update->close();
                }
                $stmt_check->close();
            }
        }
        // --- Hapus Pengguna ---
        elseif ($action == 'delete_user') {
            $user_id = $_POST['user_id'];
            
            // Pencegahan: Admin tidak bisa menghapus dirinya sendiri
            if ($user_id == $_SESSION['id']) {
                $_SESSION['error_message'] = "Anda tidak bisa menghapus akun Anda sendiri!";
            } else {
                $sql_delete = "DELETE FROM users WHERE id = ?";
                $stmt_delete = $conn->prepare($sql_delete);
                $stmt_delete->bind_param("i", $user_id);
                if ($stmt_delete->execute()) {
                    $_SESSION['success_message'] = "Pengguna berhasil dihapus.";
                } else {
                    $_SESSION['error_message'] = "Gagal menghapus pengguna: " . $stmt_delete->error;
                }
                $stmt_delete->close();
            }
        }
    }
    // Setelah POST request, redirect untuk mencegah resubmission
    header("location: manage_users.php");
    exit();
}

// Handle GET requests (untuk edit mode)
if (isset($_GET['action'])) {
    if ($_GET['action'] == 'edit' && isset($_GET['id'])) {
        $mode = 'edit';
        $user_id = $_GET['id'];
        $sql_select_user = "SELECT id, nama_lengkap, email, username, role FROM users WHERE id = ?";
        $stmt_select = $conn->prepare($sql_select_user);
        $stmt_select->bind_param("i", $user_id);
        $stmt_select->execute();
        $result_select = $stmt_select->get_result();
        if ($result_select->num_rows == 1) {
            $user_to_edit = $result_select->fetch_assoc();
        } else {
            $_SESSION['error_message'] = "Pengguna tidak ditemukan.";
            header("location: manage_users.php");
            exit();
        }
        $stmt_select->close();
    }
}

// --- Ambil data pengguna untuk ditampilkan ---
$users_list = [];
$sql_select_all_users = "SELECT id, nama_lengkap, email, username, role, created_at FROM users ORDER BY created_at DESC";
$result_all_users = $conn->query($sql_select_all_users);
if ($result_all_users->num_rows > 0) {
    while ($row = $result_all_users->fetch_assoc()) {
        $users_list[] = $row;
    }
}

$conn->close(); // Tutup koneksi setelah semua operasi database selesai
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengguna - Admin Sistem Pakar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body class="admin-body">

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
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
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="manage_users.php">
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
        <h1 class="mb-4 text-primary"><i class="fas fa-users-cog me-3"></i>Manajemen Pengguna</h1>
        <p class="lead">Kelola akun pengguna dan admin yang terdaftar di sistem.</p>

        <?php if ($message_content): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message_content; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card card-admin mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-user-friends me-2"></i> Daftar Pengguna</span>
                <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-plus me-1"></i> Tambah Pengguna
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nama Lengkap</th>
                                <th>Email</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Terdaftar Sejak</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($users_list)): ?>
                                <?php $no = 1; foreach ($users_list as $user): ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td><?php echo htmlspecialchars($user['nama_lengkap']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['username'] ?? '-'); ?></td>
                                        <td><span class="badge <?php echo ($user['role'] == 'admin' ? 'bg-primary' : 'bg-secondary'); ?>"><?php echo htmlspecialchars(ucfirst($user['role'])); ?></span></td>
                                        <td><?php echo date('d M Y, H:i', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <a href="manage_users.php?action=edit&id=<?php echo $user['id']; ?>" class="btn btn-warning btn-sm me-1"><i class="fas fa-edit"></i> Edit</a>
                                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteUserModal" data-user-id="<?php echo $user['id']; ?>" data-user-name="<?php echo htmlspecialchars($user['nama_lengkap']); ?>">
                                                <i class="fas fa-trash-alt"></i> Hapus
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">Tidak ada data pengguna.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="manage_users.php" method="POST">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="addUserModalLabel">Tambah Pengguna Baru</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_user">
                        <div class="mb-3">
                            <label for="add_nama_lengkap" class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" id="add_nama_lengkap" name="nama_lengkap" required>
                        </div>
                        <div class="mb-3">
                            <label for="add_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="add_email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="add_username" class="form-label">Username (Opsional)</label>
                            <input type="text" class="form-control" id="add_username" name="username">
                        </div>
                        <div class="mb-3">
                            <label for="add_password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="add_password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="add_role" class="form-label">Role</label>
                            <select class="form-select" id="add_role" name="role" required>
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Tambah Pengguna</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if ($mode == 'edit' && $user_to_edit): ?>
    <div class="modal fade show" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true" style="display: block;" aria-modal="true" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="manage_users.php" method="POST">
                    <div class="modal-header bg-warning text-white">
                        <h5 class="modal-title" id="editUserModalLabel">Edit Pengguna: <?php echo htmlspecialchars($user_to_edit['nama_lengkap']); ?></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" onclick="window.location.href='manage_users.php';"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_user_submit">
                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_to_edit['id']); ?>">
                        <div class="mb-3">
                            <label for="edit_nama_lengkap" class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" id="edit_nama_lengkap" name="nama_lengkap" value="<?php echo htmlspecialchars($user_to_edit['nama_lengkap']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email" value="<?php echo htmlspecialchars($user_to_edit['email']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">Username (Opsional)</label>
                            <input type="text" class="form-control" id="edit_username" name="username" value="<?php echo htmlspecialchars($user_to_edit['username'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="edit_role" class="form-label">Role</label>
                            <select class="form-select" id="edit_role" name="role" required>
                                <option value="user" <?php echo ($user_to_edit['role'] == 'user' ? 'selected' : ''); ?>>User</option>
                                <option value="admin" <?php echo ($user_to_edit['role'] == 'admin' ? 'selected' : ''); ?>>Admin</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_password" class="form-label">Password Baru (Kosongkan jika tidak diubah)</label>
                            <input type="password" class="form-control" id="edit_password" name="password">
                            <small class="form-text text-muted">Isi hanya jika Anda ingin mengubah password.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="window.location.href='manage_users.php';">Batal</button>
                        <button type="submit" class="btn btn-warning text-white">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        // JavaScript untuk menampilkan modal edit secara otomatis
        var editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
        editModal.show();
        // Saat modal ditutup, redirect untuk membersihkan URL
        document.getElementById('editUserModal').addEventListener('hidden.bs.modal', function () {
            window.location.href = 'manage_users.php';
        });
    </script>
    <?php endif; ?>

    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="manage_users.php" method="POST">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteUserModalLabel">Konfirmasi Hapus Pengguna</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="user_id" id="delete_user_id">
                        <p>Apakah Anda yakin ingin menghapus pengguna <strong id="delete_user_name"></strong>?</p>
                        <p class="text-danger small">Tindakan ini tidak dapat dibatalkan!</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Hapus</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        // JavaScript untuk mengisi data ke modal hapus
        var deleteUserModal = document.getElementById('deleteUserModal');
        deleteUserModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget; // Tombol yang memicu modal
            var userId = button.getAttribute('data-user-id');
            var userName = button.getAttribute('data-user-name');

            var modalUserId = deleteUserModal.querySelector('#delete_user_id');
            var modalUserName = deleteUserModal.querySelector('#delete_user_name');

            modalUserId.value = userId;
            modalUserName.textContent = userName;
        });
    </script>
</body>
</html>