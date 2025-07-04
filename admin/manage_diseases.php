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

// Mode default: Tampilkan daftar penyakit
$mode = 'view';
$disease_to_edit = null; // Untuk menyimpan data penyakit yang akan diedit

// Handle request POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        // --- Tambah Penyakit ---
        if ($action == 'add_disease') {
            $id_penyakit = trim($_POST['id_penyakit']);
            $nama_penyakit = trim($_POST['nama_penyakit']);
            $deskripsi = trim($_POST['deskripsi']);
            $solusi = trim($_POST['solusi']);

            // Validasi sederhana
            if (empty($id_penyakit) || empty($nama_penyakit)) {
                $_SESSION['error_message'] = "ID Penyakit dan Nama Penyakit tidak boleh kosong.";
            } else {
                // Cek duplikasi ID Penyakit
                $sql_check_id = "SELECT id FROM diseases WHERE id = ?";
                $stmt_check = $conn->prepare($sql_check_id);
                $stmt_check->bind_param("s", $id_penyakit);
                $stmt_check->execute();
                $stmt_check->store_result();
                if ($stmt_check->num_rows > 0) {
                    $_SESSION['error_message'] = "ID Penyakit sudah ada. Harap gunakan ID lain.";
                } else {
                    // Insert ke database
                    $sql_insert = "INSERT INTO diseases (id, nama_penyakit, deskripsi, solusi) VALUES (?, ?, ?, ?)";
                    $stmt_insert = $conn->prepare($sql_insert);
                    $stmt_insert->bind_param("ssss", $id_penyakit, $nama_penyakit, $deskripsi, $solusi);

                    if ($stmt_insert->execute()) {
                        $_SESSION['success_message'] = "Penyakit berhasil ditambahkan.";
                    } else {
                        $_SESSION['error_message'] = "Gagal menambahkan penyakit: " . $stmt_insert->error;
                    }
                    $stmt_insert->close();
                }
                $stmt_check->close();
            }
        }
        // --- Edit Penyakit ---
        elseif ($action == 'edit_disease_submit') {
            $old_id_penyakit = $_POST['old_id_penyakit']; // ID lama untuk WHERE clause
            $id_penyakit = trim($_POST['id_penyakit']); // ID baru
            $nama_penyakit = trim($_POST['nama_penyakit']);
            $deskripsi = trim($_POST['deskripsi']);
            $solusi = trim($_POST['solusi']);

            // Validasi sederhana
            if (empty($id_penyakit) || empty($nama_penyakit)) {
                $_SESSION['error_message'] = "ID Penyakit dan Nama Penyakit tidak boleh kosong.";
            } else {
                // Cek duplikasi ID Penyakit (kecuali untuk penyakit itu sendiri)
                $sql_check_id = "SELECT id FROM diseases WHERE id = ? AND id != ?";
                $stmt_check = $conn->prepare($sql_check_id);
                $stmt_check->bind_param("ss", $id_penyakit, $old_id_penyakit);
                $stmt_check->execute();
                $stmt_check->store_result();
                if ($stmt_check->num_rows > 0) {
                    $_SESSION['error_message'] = "ID Penyakit sudah digunakan oleh penyakit lain.";
                } else {
                    // Update ke database
                    $sql_update = "UPDATE diseases SET id = ?, nama_penyakit = ?, deskripsi = ?, solusi = ? WHERE id = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->bind_param("sssss", $id_penyakit, $nama_penyakit, $deskripsi, $solusi, $old_id_penyakit);
                    
                    if ($stmt_update->execute()) {
                        $_SESSION['success_message'] = "Penyakit berhasil diperbarui.";
                    } else {
                        $_SESSION['error_message'] = "Gagal memperbarui penyakit: " . $stmt_update->error;
                    }
                    $stmt_update->close();
                }
                $stmt_check->close();
            }
        }
        // --- Hapus Penyakit ---
        elseif ($action == 'delete_disease') {
            $id_penyakit = $_POST['id_penyakit'];
            
            // Catatan: Karena ada FOREIGN KEY CASCADE di tabel `rules` dan `diagnoses`,
            // penghapusan penyakit di sini akan otomatis menghapus aturan dan riwayat diagnosa yang terkait.
            $sql_delete = "DELETE FROM diseases WHERE id = ?";
            $stmt_delete = $conn->prepare($sql_delete);
            $stmt_delete->bind_param("s", $id_penyakit);
            if ($stmt_delete->execute()) {
                $_SESSION['success_message'] = "Penyakit berhasil dihapus. Aturan dan riwayat diagnosa terkait juga dihapus.";
            } else {
                $_SESSION['error_message'] = "Gagal menghapus penyakit: " . $stmt_delete->error;
            }
            $stmt_delete->close();
        }
    }
    // Setelah POST request, redirect untuk mencegah resubmission
    header("location: manage_diseases.php");
    exit();
}

// Handle GET requests (untuk edit mode)
if (isset($_GET['action'])) {
    if ($_GET['action'] == 'edit' && isset($_GET['id'])) {
        $mode = 'edit';
        $disease_id = $_GET['id'];
        $sql_select_disease = "SELECT id, nama_penyakit, deskripsi, solusi FROM diseases WHERE id = ?";
        $stmt_select = $conn->prepare($sql_select_disease);
        $stmt_select->bind_param("s", $disease_id);
        $stmt_select->execute();
        $result_select = $stmt_select->get_result();
        if ($result_select->num_rows == 1) {
            $disease_to_edit = $result_select->fetch_assoc();
        } else {
            $_SESSION['error_message'] = "Penyakit tidak ditemukan.";
            header("location: manage_diseases.php");
            exit();
        }
        $stmt_select->close();
    }
}

// --- Ambil data penyakit untuk ditampilkan ---
$diseases_list = [];
$sql_select_all_diseases = "SELECT id, nama_penyakit, deskripsi, solusi FROM diseases ORDER BY nama_penyakit ASC";
$result_all_diseases = $conn->query($sql_select_all_diseases);
if ($result_all_diseases->num_rows > 0) {
    while ($row = $result_all_diseases->fetch_assoc()) {
        $diseases_list[] = $row;
    }
}

$conn->close(); // Tutup koneksi setelah semua operasi database selesai
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Penyakit - Admin Sistem Pakar</title>
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
                <a class="nav-link" href="manage_users.php">
                    <i class="fas fa-users-cog me-2"></i> Manajemen Pengguna
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="manage_diseases.php">
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
        <h1 class="mb-4 text-primary"><i class="fas fa-viruses me-3"></i>Manajemen Penyakit</h1>
        <p class="lead">Kelola daftar penyakit kulit yang dapat didiagnosa oleh sistem.</p>

        <?php if ($message_content): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message_content; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card card-admin mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-disease me-2"></i> Daftar Penyakit</span>
                <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addDiseaseModal">
                    <i class="fas fa-plus me-1"></i> Tambah Penyakit
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama Penyakit</th>
                                <th>Deskripsi</th>
                                <th>Solusi</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($diseases_list)): ?>
                                <?php foreach ($diseases_list as $disease): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($disease['id']); ?></td>
                                        <td><?php echo htmlspecialchars($disease['nama_penyakit']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($disease['deskripsi'], 0, 100)) . (strlen($disease['deskripsi']) > 100 ? '...' : ''); ?></td>
                                        <td><?php echo htmlspecialchars(substr($disease['solusi'], 0, 100)) . (strlen($disease['solusi']) > 100 ? '...' : ''); ?></td>
                                        <td>
                                            <a href="manage_diseases.php?action=edit&id=<?php echo $disease['id']; ?>" class="btn btn-warning btn-sm me-1"><i class="fas fa-edit"></i> Edit</a>
                                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteDiseaseModal" data-disease-id="<?php echo htmlspecialchars($disease['id']); ?>" data-disease-name="<?php echo htmlspecialchars($disease['nama_penyakit']); ?>">
                                                <i class="fas fa-trash-alt"></i> Hapus
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">Tidak ada data penyakit.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addDiseaseModal" tabindex="-1" aria-labelledby="addDiseaseModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form action="manage_diseases.php" method="POST">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="addDiseaseModalLabel">Tambah Penyakit Baru</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_disease">
                        <div class="mb-3">
                            <label for="add_id_penyakit" class="form-label">ID Penyakit (Contoh: P001)</label>
                            <input type="text" class="form-control" id="add_id_penyakit" name="id_penyakit" required maxlength="10">
                            <div class="form-text">ID harus unik dan maksimal 10 karakter.</div>
                        </div>
                        <div class="mb-3">
                            <label for="add_nama_penyakit" class="form-label">Nama Penyakit</label>
                            <input type="text" class="form-control" id="add_nama_penyakit" name="nama_penyakit" required>
                        </div>
                        <div class="mb-3">
                            <label for="add_deskripsi" class="form-label">Deskripsi</label>
                            <textarea class="form-control" id="add_deskripsi" name="deskripsi" rows="4"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="add_solusi" class="form-label">Solusi / Penanganan</label>
                            <textarea class="form-control" id="add_solusi" name="solusi" rows="4"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Tambah Penyakit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if ($mode == 'edit' && $disease_to_edit): ?>
    <div class="modal fade show" id="editDiseaseModal" tabindex="-1" aria-labelledby="editDiseaseModalLabel" aria-hidden="true" style="display: block;" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form action="manage_diseases.php" method="POST">
                    <div class="modal-header bg-warning text-white">
                        <h5 class="modal-title" id="editDiseaseModalLabel">Edit Penyakit: <?php echo htmlspecialchars($disease_to_edit['nama_penyakit']); ?></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" onclick="window.location.href='manage_diseases.php';"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_disease_submit">
                        <input type="hidden" name="old_id_penyakit" value="<?php echo htmlspecialchars($disease_to_edit['id']); ?>">
                        <div class="mb-3">
                            <label for="edit_id_penyakit" class="form-label">ID Penyakit</label>
                            <input type="text" class="form-control" id="edit_id_penyakit" name="id_penyakit" value="<?php echo htmlspecialchars($disease_to_edit['id']); ?>" required maxlength="10">
                            <div class="form-text">ID harus unik dan maksimal 10 karakter. Perubahan ID akan mempengaruhi aturan terkait.</div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_nama_penyakit" class="form-label">Nama Penyakit</label>
                            <input type="text" class="form-control" id="edit_nama_penyakit" name="nama_penyakit" value="<?php echo htmlspecialchars($disease_to_edit['nama_penyakit']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_deskripsi" class="form-label">Deskripsi</label>
                            <textarea class="form-control" id="edit_deskripsi" name="deskripsi" rows="4"><?php echo htmlspecialchars($disease_to_edit['deskripsi']); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_solusi" class="form-label">Solusi / Penanganan</label>
                            <textarea class="form-control" id="edit_solusi" name="solusi" rows="4"><?php echo htmlspecialchars($disease_to_edit['solusi']); ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="window.location.href='manage_diseases.php';">Batal</button>
                        <button type="submit" class="btn btn-warning text-white">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        // JavaScript untuk menampilkan modal edit secara otomatis
        var editModal = new bootstrap.Modal(document.getElementById('editDiseaseModal'));
        editModal.show();
        // Saat modal ditutup, redirect untuk membersihkan URL
        document.getElementById('editDiseaseModal').addEventListener('hidden.bs.modal', function () {
            window.location.href = 'manage_diseases.php';
        });
    </script>
    <?php endif; ?>

    <div class="modal fade" id="deleteDiseaseModal" tabindex="-1" aria-labelledby="deleteDiseaseModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="manage_diseases.php" method="POST">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteDiseaseModalLabel">Konfirmasi Hapus Penyakit</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_disease">
                        <input type="hidden" name="id_penyakit" id="delete_disease_id">
                        <p>Apakah Anda yakin ingin menghapus penyakit <strong id="delete_disease_name"></strong>?</p>
                        <p class="text-danger small">Menghapus penyakit akan **menghapus semua aturan dan riwayat diagnosa yang terkait** dengan penyakit ini!</p>
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
        var deleteDiseaseModal = document.getElementById('deleteDiseaseModal');
        deleteDiseaseModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget; // Tombol yang memicu modal
            var diseaseId = button.getAttribute('data-disease-id');
            var diseaseName = button.getAttribute('data-disease-name');

            var modalDiseaseId = deleteDiseaseModal.querySelector('#delete_disease_id');
            var modalDiseaseName = deleteDiseaseModal.querySelector('#delete_disease_name');

            modalDiseaseId.value = diseaseId;
            modalDiseaseName.textContent = diseaseName;
        });
    </script>
</body>
</html>