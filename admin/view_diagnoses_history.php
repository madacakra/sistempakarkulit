<?php
// BARIS PERTAMA HARUS <?php TANPA SPASI ATAUPUN BARIS KOSONG SEBELUMNYA
session_start();

// Sertakan file koneksi database. Path ini relatif dari admin/ ke config/
require_once '../config/database.php'; 

// --- BLOK PHP UNTUK MENANGANI PERMINTAAN AJAX POST (DELETE DIAGNOSIS) ---
// Blok ini harus berada di paling atas, sebelum output HTML apapun.
// Jika request ini AJAX POST, script akan exit() setelah merespon.
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_diagnosis') {
    // Pastikan ini adalah request AJAX yang mengharapkan JSON respons
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {

        // Set header respons ke JSON sebelum output apapun
        header('Content-Type: application/json');

        $diagnosis_id = $_POST['diagnosis_id'] ?? null;

        if ($diagnosis_id) {
            $sql_delete = "DELETE FROM diagnoses WHERE id = ?";
            if ($stmt_delete = $conn->prepare($sql_delete)) {
                $stmt_delete->bind_param("i", $diagnosis_id);
                if ($stmt_delete->execute()) {
                    echo json_encode(['status' => 'success', 'message' => 'Riwayat diagnosa berhasil dihapus.']);
                } else {
                    // Log error database untuk debugging internal
                    error_log("Gagal menghapus riwayat diagnosa: " . $stmt_delete->error);
                    echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus riwayat diagnosa: ' . $stmt_delete->error]);
                }
                $stmt_delete->close();
            } else {
                // Log error persiapan statement
                error_log("Gagal menyiapkan statement delete: " . $conn->error);
                echo json_encode(['status' => 'error', 'message' => 'Gagal menyiapkan statement delete: ' . $conn->error]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'ID Diagnosa tidak valid.']);
        }
        $conn->close(); // Tutup koneksi setelah operasi AJAX selesai
        exit(); // SANGAT PENTING: Hentikan eksekusi skrip PHP setelah mengirim respons JSON
    }
    // Jika bukan request AJAX POST delete, script akan melanjutkan ke bawah (merender HTML)
}

// --- LOGIKA PHP UNTUK MENSIAPKAN DATA TAMPILAN HTML ---
// Bagian ini hanya akan dieksekusi jika request BUKAN AJAX POST delete.

// Proteksi halaman: Cek apakah user sudah login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $_SESSION['error_message'] = "Anda harus login untuk mengakses halaman ini.";
    header("location: ../login.php"); // Redirect ke halaman login di root
    exit();
}

// Proteksi role: Cek apakah user adalah admin
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = "Anda tidak memiliki hak akses ke halaman ini.";
    header("location: ../unauthorized.php");
    exit();
}

// Penanganan pesan feedback dari sesi (misal dari redirect sebelumnya)
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

// Ambil Riwayat Diagnosa untuk Tampilan HTML
$diagnoses_history = [];
$sql_history = "
    SELECT 
        d.id AS diagnosis_id,
        u.nama_lengkap AS user_name,
        dis.nama_penyakit AS diagnosed_disease_name,
        d.cf_result,
        d.gejala_dipilih,
        d.tanggal_diagnosa
    FROM 
        diagnoses d
    JOIN 
        users u ON d.user_id = u.id
    JOIN 
        diseases dis ON d.diagnosed_disease_id = dis.id
    ORDER BY 
        d.tanggal_diagnosa DESC
";
$result_history = $conn->query($sql_history);

if ($result_history && $result_history->num_rows > 0) {
    while ($row = $result_history->fetch_assoc()) {
        $diagnoses_history[] = $row;
    }
}

// Fungsi untuk menginterpretasikan nilai Certainty Factor
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
        return "Tidak Diketahui";
    }
}

$conn->close(); // Tutup koneksi setelah semua data diambil untuk tampilan HTML
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Diagnosa - Admin Sistem Pakar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        /* CSS khusus untuk halaman riwayat diagnosa jika ada */
        .table-responsive {
            margin-top: 20px;
        }
        .gejala-cell {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
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
                <a class="nav-link" href="manage_rules.php">
                    <i class="fas fa-list-ol me-2"></i> Manajemen Aturan
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="view_diagnoses_history.php">
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
        <h1 class="mb-4 text-primary"><i class="fas fa-history me-3"></i>Riwayat Diagnosa</h1>
        <p class="lead">Lihat semua riwayat diagnosa yang telah dilakukan oleh pengguna sistem.</p>

        <?php if ($message_content): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message_content; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card card-admin mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-chart-line me-2"></i> Daftar Riwayat Diagnosa</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Tanggal</th>
                                <th>Pengguna</th>
                                <th>Penyakit Terdiagnosa</th>
                                <th>CF Hasil</th>
                                <th>Interpretasi CF</th>
                                <th>Gejala Dipilih</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($diagnoses_history)): ?>
                                <?php $no = 1; foreach ($diagnoses_history as $diagnosis): ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td><?php echo date('d M Y, H:i', strtotime($diagnosis['tanggal_diagnosa'])); ?></td>
                                        <td><?php echo htmlspecialchars($diagnosis['user_name']); ?></td>
                                        <td><?php echo htmlspecialchars($diagnosis['diagnosed_disease_name']); ?></td>
                                        <td><?php echo htmlspecialchars(number_format($diagnosis['cf_result'], 2)); ?> (<?php echo round($diagnosis['cf_result'] * 100, 2); ?>%)</td>
                                        <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars(interpretCF($diagnosis['cf_result'])); ?></span></td>
                                        <td>
                                            <span class="gejala-cell">
                                                <?php 
                                                    $gejala_ids_json = $diagnosis['gejala_dipilih'];
                                                    $gejala_ids_arr = json_decode($gejala_ids_json, true);
                                                    if (!empty($gejala_ids_arr)) {
                                                        echo "Total: " . count($gejala_ids_arr) . " gejala.";
                                                    } else {
                                                        echo "-";
                                                    }
                                                ?>
                                            </span>
                                            <?php if (!empty($gejala_ids_arr)): ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary mt-1" data-bs-toggle="modal" data-bs-target="#gejalaDetailModal" data-gejala-ids='<?php echo htmlspecialchars($gejala_ids_json); ?>' data-diagnosis-id="<?php echo $diagnosis['diagnosis_id']; ?>">
                                                    Lihat Detail
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteDiagnosisModal" data-diagnosis-id="<?php echo $diagnosis['diagnosis_id']; ?>" data-diagnosis-info="Diagnosa oleh <?php echo htmlspecialchars($diagnosis['user_name']); ?> untuk <?php echo htmlspecialchars($diagnosis['diagnosed_disease_name']); ?> (<?php echo date('d M Y', strtotime($diagnosis['tanggal_diagnosa'])); ?>)">
                                                <i class="fas fa-trash-alt"></i> Hapus
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">Tidak ada riwayat diagnosa.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="gejalaDetailModal" tabindex="-1" aria-labelledby="gejalaDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="gejalaDetailModalLabel">Detail Gejala Diagnosa #<span id="modal_diagnosis_id"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Gejala yang dipilih:</strong></p>
                    <ul id="gejala_detail_list" class="list-group list-group-flush">
                        </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteDiagnosisModal" tabindex="-1" aria-labelledby="deleteDiagnosisModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" id="deleteDiagnosisForm">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteDiagnosisModalLabel">Konfirmasi Hapus Riwayat Diagnosa</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_diagnosis">
                        <input type="hidden" name="diagnosis_id" id="delete_diagnosis_id">
                        <p>Apakah Anda yakin ingin menghapus riwayat diagnosa ini: <strong id="delete_diagnosis_info"></strong>?</p>
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
        // JavaScript untuk Modal Lihat Detail Gejala
        var gejalaDetailModal = document.getElementById('gejalaDetailModal');
        gejalaDetailModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget; // Tombol yang memicu modal
            var gejalaIdsJson = button.getAttribute('data-gejala-ids');
            var diagnosisId = button.getAttribute('data-diagnosis-id');

            var modalDiagnosisId = gejalaDetailModal.querySelector('#modal_diagnosis_id');
            var gejalaListElement = gejalaDetailModal.querySelector('#gejala_detail_list');
            gejalaListElement.innerHTML = ''; // Kosongkan daftar sebelumnya

            modalDiagnosisId.textContent = diagnosisId;

            try {
                var gejalaIds = JSON.parse(gejalaIdsJson);
                if (gejalaIds.length > 0) {
                    // Panggil endpoint AJAX untuk mendapatkan nama gejala
                    // Path relatif dari current folder admin/ ke get_symptom_names_by_ids.php
                    fetch('get_symptom_names_by_ids.php', { 
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ symptom_ids: gejalaIds })
                    })
                    .then(response => {
                        // Periksa apakah respons adalah OK (status 200-299)
                        if (!response.ok) {
                            // Jika tidak OK, coba baca sebagai teks biasa untuk debugging
                            return response.text().then(text => {
                                throw new Error('Network response was not ok, status: ' + response.status + ' body: ' + text);
                            });
                        }
                        return response.json(); // Mengasumsikan respons adalah JSON
                    })
                    .then(data => {
                        if (data.status === 'success') {
                            if (data.symptom_names && data.symptom_names.length > 0) { // Cek data.symptom_names tidak null/undefined
                                data.symptom_names.forEach(symptomName => {
                                    var li = document.createElement('li');
                                    li.className = 'list-group-item';
                                    li.textContent = symptomName;
                                    gejalaListElement.appendChild(li);
                                });
                            } else {
                                gejalaListElement.innerHTML = '<li class="list-group-item text-muted">Tidak ada nama gejala ditemukan untuk ID ini.</li>';
                            }
                        } else {
                            gejalaListElement.innerHTML = '<li class="list-group-item text-danger">Gagal memuat nama gejala: ' + (data.message || 'Unknown error') + '</li>';
                            console.error("Error fetching symptom names:", data.message);
                        }
                    })
                    .catch(error => {
                        gejalaListElement.innerHTML = '<li class="list-group-item text-danger">Terjadi kesalahan jaringan atau server: ' + error.message + '</li>';
                        console.error("Network error:", error);
                    });
                } else {
                    gejalaListElement.innerHTML = '<li class="list-group-item text-muted">Tidak ada gejala yang dicatat untuk diagnosa ini.</li>';
                }
            } catch (e) {
                gejalaListElement.innerHTML = '<li class="list-group-item text-danger">Data gejala tidak valid. Kesalahan parsing: ' + e.message + '</li>';
                console.error("JSON parsing error:", e);
            }
        });

        // JavaScript untuk Modal Hapus Diagnosa
        var deleteDiagnosisModal = document.getElementById('deleteDiagnosisModal');
        deleteDiagnosisModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget; // Tombol yang memicu modal
            var diagnosisId = button.getAttribute('data-diagnosis-id');
            var diagnosisInfo = button.getAttribute('data-diagnosis-info');

            var modalDiagnosisId = deleteDiagnosisModal.querySelector('#delete_diagnosis_id');
            var modalDiagnosisInfo = deleteDiagnosisModal.querySelector('#delete_diagnosis_info');

            modalDiagnosisId.value = diagnosisId;
            modalDiagnosisInfo.textContent = diagnosisInfo;
        });

        // JavaScript untuk Proses Hapus Diagnosa via AJAX (menggunakan fetch)
        document.getElementById('deleteDiagnosisForm').addEventListener('submit', function(e) {
            e.preventDefault(); // Mencegah form submit default

            const form = e.target;
            const formData = new FormData(form);

            // Menggunakan form.action untuk memastikan URL tujuan adalah URL form (view_diagnoses_history.php)
            // Ini akan mengirim POST request ke admin/view_diagnoses_history.php
            fetch(form.action, { 
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Periksa apakah respons adalah OK (status 200-299)
                if (!response.ok) {
                    // Jika tidak OK, coba baca sebagai teks biasa untuk debugging
                    return response.text().then(text => {
                        throw new Error('Network response was not ok, status: ' + response.status + ' body: ' + text);
                    });
                }
                return response.json(); // Mengasumsikan respons adalah JSON
            })
            .then(data => {
                if (data.status === 'success') {
                    // Tampilkan pesan sukses dan reload halaman
                    alert(data.message || 'Riwayat diagnosa berhasil dihapus.');
                    location.reload(); 
                } else {
                    alert('Gagal menghapus diagnosa: ' + (data.message || 'Unknown error.'));
                }
            })
            .catch(error => {
                // Tangani kesalahan jaringan atau parsing JSON
                console.error('Error during AJAX request:', error);
                alert('Terjadi kesalahan jaringan atau server saat menghapus diagnosa: ' + error.message);
            });
        });
    </script>
</body>
</html>