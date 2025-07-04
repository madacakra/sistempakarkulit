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

// Mode default: Tampilkan daftar gejala
$mode = 'view';
$symptom_to_edit = null; // Untuk menyimpan data gejala yang akan diedit

// Handle request POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        // --- Tambah Gejala ---
        if ($action == 'add_symptom') {
            $id_gejala = trim($_POST['id_gejala']);
            $nama_gejala = trim($_POST['nama_gejala']);

            // Validasi sederhana
            if (empty($id_gejala) || empty($nama_gejala)) {
                $_SESSION['error_message'] = "ID Gejala dan Nama Gejala tidak boleh kosong.";
            } else {
                // Cek duplikasi ID Gejala
                $sql_check_id = "SELECT id FROM symptoms WHERE id = ?";
                $stmt_check = $conn->prepare($sql_check_id);
                $stmt_check->bind_param("s", $id_gejala);
                $stmt_check->execute();
                $stmt_check->store_result();
                if ($stmt_check->num_rows > 0) {
                    $_SESSION['error_message'] = "ID Gejala sudah ada. Harap gunakan ID lain.";
                } else {
                    // Insert ke database
                    $sql_insert = "INSERT INTO symptoms (id, nama_gejala) VALUES (?, ?)"; // nilai_cf default ke 0.00
                    $stmt_insert = $conn->prepare($sql_insert);
                    $stmt_insert->bind_param("ss", $id_gejala, $nama_gejala);

                    if ($stmt_insert->execute()) {
                        $_SESSION['success_message'] = "Gejala berhasil ditambahkan.";
                    } else {
                        $_SESSION['error_message'] = "Gagal menambahkan gejala: " . $stmt_insert->error;
                    }
                    $stmt_insert->close();
                }
                $stmt_check->close();
            }
        }
        // --- Edit Gejala ---
        elseif ($action == 'edit_symptom_submit') {
            $old_id_gejala = $_POST['old_id_gejala']; // ID lama untuk WHERE clause
            $id_gejala = trim($_POST['id_gejala']); // ID baru
            $nama_gejala = trim($_POST['nama_gejala']);

            // Validasi sederhana
            if (empty($id_gejala) || empty($nama_gejala)) {
                $_SESSION['error_message'] = "ID Gejala dan Nama Gejala tidak boleh kosong.";
            } else {
                // Cek duplikasi ID Gejala (kecuali untuk gejala itu sendiri)
                $sql_check_id = "SELECT id FROM symptoms WHERE id = ? AND id != ?";
                $stmt_check = $conn->prepare($sql_check_id);
                $stmt_check->bind_param("ss", $id_gejala, $old_id_gejala);
                $stmt_check->execute();
                $stmt_check->store_result();
                if ($stmt_check->num_rows > 0) {
                    $_SESSION['error_message'] = "ID Gejala sudah digunakan oleh gejala lain.";
                } else {
                    // Update ke database
                    $sql_update = "UPDATE symptoms SET id = ?, nama_gejala = ? WHERE id = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->bind_param("sss", $id_gejala, $nama_gejala, $old_id_gejala);
                    
                    if ($stmt_update->execute()) {
                        $_SESSION['success_message'] = "Gejala berhasil diperbarui.";
                    } else {
                        $_SESSION['error_message'] = "Gagal memperbarui gejala: " . $stmt_update->error;
                    }
                    $stmt_update->close();
                }
                $stmt_check->close();
            }
        }
        // --- Hapus Gejala ---
        elseif ($action == 'delete_symptom') {
            $id_gejala = $_POST['id_gejala'];
            
            // Catatan: Karena ada FOREIGN KEY CASCADE di tabel `rules`,
            // penghapusan gejala di sini akan otomatis menghapus aturan yang terkait.
            $sql_delete = "DELETE FROM symptoms WHERE id = ?";
            $stmt_delete = $conn->prepare($sql_delete);
            $stmt_delete->bind_param("s", $id_gejala);
            if ($stmt_delete->execute()) {
                $_SESSION['success_message'] = "Gejala berhasil dihapus. Aturan terkait juga dihapus.";
            } else {
                $_SESSION['error_message'] = "Gagal menghapus gejala: " . $stmt_delete->error;
            }
            $stmt_delete->close();
        }
    }
    // Setelah POST request, redirect untuk mencegah resubmission
    header("location: manage_symptoms.php");
    exit();
}

// Handle GET requests (untuk edit mode)
if (isset($_GET['action'])) {
    if ($_GET['action'] == 'edit' && isset($_GET['id'])) {
        $mode = 'edit';
        $symptom_id = $_GET['id'];
        $sql_select_symptom = "SELECT id, nama_gejala FROM symptoms WHERE id = ?";
        $stmt_select = $conn->prepare($sql_select_symptom);
        $stmt_select->bind_param("s", $symptom_id);
        $stmt_select->execute();
        $result_select = $stmt_select->get_result();
        if ($result_select->num_rows == 1) {
            $symptom_to_edit = $result_select->fetch_assoc();
        } else {
            $_SESSION['error_message'] = "Gejala tidak ditemukan.";
            header("location: manage_symptoms.php");
            exit();
        }
        $stmt_select->close();
    }
}

// --- Ambil data gejala untuk ditampilkan ---
$symptoms_list = [];
$sql_select_all_symptoms = "SELECT id, nama_gejala FROM symptoms ORDER BY nama_gejala ASC";
$result_all_symptoms = $conn->query($sql_select_all_symptoms);
if ($result_all_symptoms->num_rows > 0) {
    while ($row = $result_all_symptoms->fetch_assoc()) {
        $symptoms_list[] = $row;
    }
}

$conn->close(); // Tutup koneksi setelah semua operasi database selesai
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Gejala - Admin Sistem Pakar</title>
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
                <a class="nav-link" href="manage_diseases.php">
                    <i class="fas fa-viruses me-2"></i> Manajemen Penyakit
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="manage_symptoms.php">
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
        <h1 class="mb-4 text-primary"><i class="fas fa-head-side-cough me-3"></i>Manajemen Gejala</h1>
        <p class="lead">Kelola daftar gejala yang digunakan dalam proses diagnosis sistem pakar.</p>

        <?php if ($message_content): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message_content; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card card-admin mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-list me-2"></i> Daftar Gejala</span>
                <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addSymptomModal">
                    <i class="fas fa-plus me-1"></i> Tambah Gejala
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama Gejala</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($symptoms_list)): ?>
                                <?php foreach ($symptoms_list as $symptom): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($symptom['id']); ?></td>
                                        <td><?php echo htmlspecialchars($symptom['nama_gejala']); ?></td>
                                        <td>
                                            <a href="manage_symptoms.php?action=edit&id=<?php echo $symptom['id']; ?>" class="btn btn-warning btn-sm me-1"><i class="fas fa-edit"></i> Edit</a>
                                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteSymptomModal" data-symptom-id="<?php echo htmlspecialchars($symptom['id']); ?>" data-symptom-name="<?php echo htmlspecialchars($symptom['nama_gejala']); ?>">
                                                <i class="fas fa-trash-alt"></i> Hapus
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center">Tidak ada data gejala.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addSymptomModal" tabindex="-1" aria-labelledby="addSymptomModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="manage_symptoms.php" method="POST">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="addSymptomModalLabel">Tambah Gejala Baru</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_symptom">
                        <div class="mb-3">
                            <label for="add_id_gejala" class="form-label">ID Gejala (Contoh: G001)</label>
                            <input type="text" class="form-control" id="add_id_gejala" name="id_gejala" required maxlength="10">
                            <div class="form-text">ID harus unik dan maksimal 10 karakter.</div>
                        </div>
                        <div class="mb-3">
                            <label for="add_nama_gejala" class="form-label">Nama Gejala</label>
                            <input type="text" class="form-control" id="add_nama_gejala" name="nama_gejala" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Tambah Gejala</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if ($mode == 'edit' && $symptom_to_edit): ?>
    <div class="modal fade show" id="editSymptomModal" tabindex="-1" aria-labelledby="editSymptomModalLabel" aria-hidden="true" style="display: block;" aria-modal="true" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="manage_symptoms.php" method="POST">
                    <div class="modal-header bg-warning text-white">
                        <h5 class="modal-title" id="editSymptomModalLabel">Edit Gejala: <?php echo htmlspecialchars($symptom_to_edit['nama_gejala']); ?></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" onclick="window.location.href='manage_symptoms.php';"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_symptom_submit">
                        <input type="hidden" name="old_id_gejala" value="<?php echo htmlspecialchars($symptom_to_edit['id']); ?>">
                        <div class="mb-3">
                            <label for="edit_id_gejala" class="form-label">ID Gejala</label>
                            <input type="text" class="form-control" id="edit_id_gejala" name="id_gejala" value="<?php echo htmlspecialchars($symptom_to_edit['id']); ?>" required maxlength="10">
                            <div class="form-text">ID harus unik dan maksimal 10 karakter. Perubahan ID akan mempengaruhi aturan terkait.</div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_nama_gejala" class="form-label">Nama Gejala</label>
                            <input type="text" class="form-control" id="edit_nama_gejala" name="nama_gejala" value="<?php echo htmlspecialchars($symptom_to_edit['nama_gejala']); ?>" required>
                        </div>
                        </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="window.location.href='manage_symptoms.php';">Batal</button>
                        <button type="submit" class="btn btn-warning text-white">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        // JavaScript untuk menampilkan modal edit secara otomatis
        var editModal = new bootstrap.Modal(document.getElementById('editSymptomModal'));
        editModal.show();
        // Saat modal ditutup, redirect untuk membersihkan URL
        document.getElementById('editSymptomModal').addEventListener('hidden.bs.modal', function () {
            window.location.href = 'manage_symptoms.php';
        });
    </script>
    <?php endif; ?>

    <div class="modal fade" id="deleteSymptomModal" tabindex="-1" aria-labelledby="deleteSymptomModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="manage_symptoms.php" method="POST">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteSymptomModalLabel">Konfirmasi Hapus Gejala</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_symptom">
                        <input type="hidden" name="id_gejala" id="delete_symptom_id">
                        <p>Apakah Anda yakin ingin menghapus gejala <strong id="delete_symptom_name"></strong>?</p>
                        <p class="text-danger small">Menghapus gejala akan **menghapus semua aturan yang terkait** dengan gejala ini!</p>
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
        var deleteSymptomModal = document.getElementById('deleteSymptomModal');
        deleteSymptomModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget; // Tombol yang memicu modal
            var symptomId = button.getAttribute('data-symptom-id');
            var symptomName = button.getAttribute('data-symptom-name');

            var modalSymptomId = deleteSymptomModal.querySelector('#delete_symptom_id');
            var modalSymptomName = deleteSymptomModal.querySelector('#delete_symptom_name');

            modalSymptomId.value = symptomId;
            modalSymptomName.textContent = symptomName;
        });
    </script>
</body>
</html>