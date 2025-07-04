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

// Mode default: Tampilkan daftar aturan
$mode = 'view';
$rule_to_edit = null; // Untuk menyimpan data aturan yang akan diedit

// Ambil daftar penyakit dan gejala untuk dropdown di form
$diseases_options = [];
$symptoms_options = [];

$sql_diseases = "SELECT id, nama_penyakit FROM diseases ORDER BY nama_penyakit ASC";
$result_diseases = $conn->query($sql_diseases);
if ($result_diseases->num_rows > 0) {
    while ($row = $result_diseases->fetch_assoc()) {
        $diseases_options[] = $row;
    }
}

$sql_symptoms = "SELECT id, nama_gejala FROM symptoms ORDER BY nama_gejala ASC";
$result_symptoms = $conn->query($sql_symptoms);
if ($result_symptoms->num_rows > 0) {
    while ($row = $result_symptoms->fetch_assoc()) {
        $symptoms_options[] = $row;
    }
}


// Handle request POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        // --- Tambah Aturan ---
        if ($action == 'add_rule') {
            $id_penyakit = trim($_POST['id_penyakit']);
            $id_gejala = trim($_POST['id_gejala']);
            $cf_rule = (float)$_POST['cf_rule'];

            // Validasi sederhana
            if (empty($id_penyakit) || empty($id_gejala) || !is_numeric($cf_rule) || $cf_rule < 0 || $cf_rule > 1) {
                $_SESSION['error_message'] = "Penyakit, gejala, dan nilai Certainty Factor (0.00-1.00) tidak boleh kosong dan harus valid.";
            } else {
                // Cek duplikasi aturan (penyakit-gejala)
                $sql_check_rule = "SELECT id FROM rules WHERE id_penyakit = ? AND id_gejala = ?";
                $stmt_check = $conn->prepare($sql_check_rule);
                $stmt_check->bind_param("ss", $id_penyakit, $id_gejala);
                $stmt_check->execute();
                $stmt_check->store_result();
                if ($stmt_check->num_rows > 0) {
                    $_SESSION['error_message'] = "Aturan untuk kombinasi penyakit dan gejala ini sudah ada.";
                } else {
                    // Insert ke database
                    $sql_insert = "INSERT INTO rules (id_penyakit, id_gejala, cf_rule) VALUES (?, ?, ?)";
                    $stmt_insert = $conn->prepare($sql_insert);
                    $stmt_insert->bind_param("ssd", $id_penyakit, $id_gejala, $cf_rule); // 'd' for double/float

                    if ($stmt_insert->execute()) {
                        $_SESSION['success_message'] = "Aturan berhasil ditambahkan.";
                    } else {
                        $_SESSION['error_message'] = "Gagal menambahkan aturan: " . $stmt_insert->error;
                    }
                    $stmt_insert->close();
                }
                $stmt_check->close();
            }
        }
        // --- Edit Aturan ---
        elseif ($action == 'edit_rule_submit') {
            $rule_id = $_POST['rule_id']; // ID primary key dari tabel rules
            $id_penyakit = trim($_POST['id_penyakit']);
            $id_gejala = trim($_POST['id_gejala']);
            $cf_rule = (float)$_POST['cf_rule'];

            // Validasi sederhana
            if (empty($id_penyakit) || empty($id_gejala) || !is_numeric($cf_rule) || $cf_rule < 0 || $cf_rule > 1) {
                $_SESSION['error_message'] = "Penyakit, gejala, dan nilai Certainty Factor (0.00-1.00) tidak boleh kosong dan harus valid.";
            } else {
                // Cek duplikasi aturan (penyakit-gejala), kecuali untuk aturan yang sedang diedit
                $sql_check_rule = "SELECT id FROM rules WHERE id_penyakit = ? AND id_gejala = ? AND id != ?";
                $stmt_check = $conn->prepare($sql_check_rule);
                $stmt_check->bind_param("ssi", $id_penyakit, $id_gejala, $rule_id);
                $stmt_check->execute();
                $stmt_check->store_result();
                if ($stmt_check->num_rows > 0) {
                    $_SESSION['error_message'] = "Aturan untuk kombinasi penyakit dan gejala ini sudah ada.";
                } else {
                    // Update ke database
                    $sql_update = "UPDATE rules SET id_penyakit = ?, id_gejala = ?, cf_rule = ? WHERE id = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->bind_param("ssdi", $id_penyakit, $id_gejala, $cf_rule, $rule_id);
                    
                    if ($stmt_update->execute()) {
                        $_SESSION['success_message'] = "Aturan berhasil diperbarui.";
                    } else {
                        $_SESSION['error_message'] = "Gagal memperbarui aturan: " . $stmt_update->error;
                    }
                    $stmt_update->close();
                }
                $stmt_check->close();
            }
        }
        // --- Hapus Aturan ---
        elseif ($action == 'delete_rule') {
            $rule_id = $_POST['rule_id'];
            
            $sql_delete = "DELETE FROM rules WHERE id = ?";
            $stmt_delete = $conn->prepare($sql_delete);
            $stmt_delete->bind_param("i", $rule_id);
            if ($stmt_delete->execute()) {
                $_SESSION['success_message'] = "Aturan berhasil dihapus.";
            } else {
                $_SESSION['error_message'] = "Gagal menghapus aturan: " . $stmt_delete->error;
            }
            $stmt_delete->close();
        }
    }
    // Setelah POST request, redirect untuk mencegah resubmission
    header("location: manage_rules.php");
    exit();
}

// Handle GET requests (untuk edit mode)
if (isset($_GET['action'])) {
    if ($_GET['action'] == 'edit' && isset($_GET['id'])) {
        $mode = 'edit';
        $rule_id = $_GET['id'];
        $sql_select_rule = "SELECT id, id_penyakit, id_gejala, cf_rule FROM rules WHERE id = ?";
        $stmt_select = $conn->prepare($sql_select_rule);
        $stmt_select->bind_param("i", $rule_id);
        $stmt_select->execute();
        $result_select = $stmt_select->get_result();
        if ($result_select->num_rows == 1) {
            $rule_to_edit = $result_select->fetch_assoc();
        } else {
            $_SESSION['error_message'] = "Aturan tidak ditemukan.";
            header("location: manage_rules.php");
            exit();
        }
        $stmt_select->close();
    }
}

// --- Ambil data aturan untuk ditampilkan ---
$rules_list = [];
$sql_select_all_rules = "
    SELECT 
        r.id, 
        r.id_penyakit, 
        d.nama_penyakit, 
        r.id_gejala, 
        s.nama_gejala, 
        r.cf_rule 
    FROM 
        rules r
    JOIN 
        diseases d ON r.id_penyakit = d.id
    JOIN 
        symptoms s ON r.id_gejala = s.id
    ORDER BY 
        r.id_penyakit, r.id_gejala ASC
";
$result_all_rules = $conn->query($sql_select_all_rules);
if ($result_all_rules->num_rows > 0) {
    while ($row = $result_all_rules->fetch_assoc()) {
        $rules_list[] = $row;
    }
}

$conn->close(); // Tutup koneksi setelah semua operasi database selesai
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Aturan - Admin Sistem Pakar</title>
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
                <a class="nav-link" href="manage_symptoms.php">
                    <i class="fas fa-head-side-cough me-2"></i> Manajemen Gejala
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="manage_rules.php">
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
        <h1 class="mb-4 text-primary"><i class="fas fa-list-ol me-3"></i>Manajemen Aturan</h1>
        <p class="lead">Kelola basis aturan sistem pakar untuk diagnosis penyakit kulit.</p>

        <?php if ($message_content): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message_content; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card card-admin mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-sitemap me-2"></i> Daftar Aturan (IF Gejala THEN Penyakit)</span>
                <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addRuleModal">
                    <i class="fas fa-plus me-1"></i> Tambah Aturan
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Penyakit (ID)</th>
                                <th>Gejala (ID)</th>
                                <th>CF Rule</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($rules_list)): ?>
                                <?php $no = 1; foreach ($rules_list as $rule): ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td><?php echo htmlspecialchars($rule['nama_penyakit']); ?> (<?php echo htmlspecialchars($rule['id_penyakit']); ?>)</td>
                                        <td><?php echo htmlspecialchars($rule['nama_gejala']); ?> (<?php echo htmlspecialchars($rule['id_gejala']); ?>)</td>
                                        <td><?php echo htmlspecialchars(number_format($rule['cf_rule'], 2)); ?></td>
                                        <td>
                                            <a href="manage_rules.php?action=edit&id=<?php echo $rule['id']; ?>" class="btn btn-warning btn-sm me-1"><i class="fas fa-edit"></i> Edit</a>
                                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteRuleModal" data-rule-id="<?php echo htmlspecialchars($rule['id']); ?>" data-rule-desc="IF <?php echo htmlspecialchars($rule['nama_gejala']); ?> THEN <?php echo htmlspecialchars($rule['nama_penyakit']); ?>">
                                                <i class="fas fa-trash-alt"></i> Hapus
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">Tidak ada data aturan.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addRuleModal" tabindex="-1" aria-labelledby="addRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="manage_rules.php" method="POST">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="addRuleModalLabel">Tambah Aturan Baru</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_rule">
                        <div class="mb-3">
                            <label for="add_id_penyakit" class="form-label">Penyakit</label>
                            <select class="form-select" id="add_id_penyakit" name="id_penyakit" required>
                                <option value="">Pilih Penyakit</option>
                                <?php foreach ($diseases_options as $disease): ?>
                                    <option value="<?php echo htmlspecialchars($disease['id']); ?>"><?php echo htmlspecialchars($disease['nama_penyakit']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="add_id_gejala" class="form-label">Gejala</label>
                            <select class="form-select" id="add_id_gejala" name="id_gejala" required>
                                <option value="">Pilih Gejala</option>
                                <?php foreach ($symptoms_options as $symptom): ?>
                                    <option value="<?php echo htmlspecialchars($symptom['id']); ?>"><?php echo htmlspecialchars($symptom['nama_gejala']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="add_cf_rule" class="form-label">Certainty Factor (CF Rule)</label>
                            <input type="number" step="0.01" min="0" max="1" class="form-control" id="add_cf_rule" name="cf_rule" required placeholder="Contoh: 0.8">
                            <div class="form-text">Nilai CF Rule antara 0.00 hingga 1.00 (didapatkan dari pakar).</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Tambah Aturan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if ($mode == 'edit' && $rule_to_edit): ?>
    <div class="modal fade show" id="editRuleModal" tabindex="-1" aria-labelledby="editRuleModalLabel" aria-hidden="true" style="display: block;" aria-modal="true" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="manage_rules.php" method="POST">
                    <div class="modal-header bg-warning text-white">
                        <h5 class="modal-title" id="editRuleModalLabel">Edit Aturan ID: <?php echo htmlspecialchars($rule_to_edit['id']); ?></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" onclick="window.location.href='manage_rules.php';"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_rule_submit">
                        <input type="hidden" name="rule_id" value="<?php echo htmlspecialchars($rule_to_edit['id']); ?>">
                        <div class="mb-3">
                            <label for="edit_id_penyakit" class="form-label">Penyakit</label>
                            <select class="form-select" id="edit_id_penyakit" name="id_penyakit" required>
                                <option value="">Pilih Penyakit</option>
                                <?php foreach ($diseases_options as $disease): ?>
                                    <option value="<?php echo htmlspecialchars($disease['id']); ?>" <?php echo ($disease['id'] == $rule_to_edit['id_penyakit'] ? 'selected' : ''); ?>>
                                        <?php echo htmlspecialchars($disease['nama_penyakit']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_id_gejala" class="form-label">Gejala</label>
                            <select class="form-select" id="edit_id_gejala" name="id_gejala" required>
                                <option value="">Pilih Gejala</option>
                                <?php foreach ($symptoms_options as $symptom): ?>
                                    <option value="<?php echo htmlspecialchars($symptom['id']); ?>" <?php echo ($symptom['id'] == $rule_to_edit['id_gejala'] ? 'selected' : ''); ?>>
                                        <?php echo htmlspecialchars($symptom['nama_gejala']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_cf_rule" class="form-label">Certainty Factor (CF Rule)</label>
                            <input type="number" step="0.01" min="0" max="1" class="form-control" id="edit_cf_rule" name="cf_rule" value="<?php echo htmlspecialchars($rule_to_edit['cf_rule']); ?>" required>
                            <div class="form-text">Nilai CF Rule antara 0.00 hingga 1.00.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="window.location.href='manage_rules.php';">Batal</button>
                        <button type="submit" class="btn btn-warning text-white">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        // JavaScript untuk menampilkan modal edit secara otomatis
        var editModal = new bootstrap.Modal(document.getElementById('editRuleModal'));
        editModal.show();
        // Saat modal ditutup, redirect untuk membersihkan URL
        document.getElementById('editRuleModal').addEventListener('hidden.bs.modal', function () {
            window.location.href = 'manage_rules.php';
        });
    </script>
    <?php endif; ?>

    <div class="modal fade" id="deleteRuleModal" tabindex="-1" aria-labelledby="deleteRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="manage_rules.php" method="POST">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteRuleModalLabel">Konfirmasi Hapus Aturan</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_rule">
                        <input type="hidden" name="rule_id" id="delete_rule_id">
                        <p>Apakah Anda yakin ingin menghapus aturan <strong id="delete_rule_desc"></strong>?</p>
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
        var deleteRuleModal = document.getElementById('deleteRuleModal');
        deleteRuleModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget; // Tombol yang memicu modal
            var ruleId = button.getAttribute('data-rule-id');
            var ruleDesc = button.getAttribute('data-rule-desc'); // Ambil deskripsi aturan

            var modalRuleId = deleteRuleModal.querySelector('#delete_rule_id');
            var modalRuleDesc = deleteRuleModal.querySelector('#delete_rule_desc');

            modalRuleId.value = ruleId;
            modalRuleDesc.textContent = ruleDesc;
        });
    </script>
</body>
</html>