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
require_once '../config/database.php'; // Path ini harus benar jika diagnoses_history.php ada di folder user/

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

// Ambil ID pengguna yang sedang login
$current_user_id = $_SESSION['id'];

// --- Logika Ambil Riwayat Diagnosa Pengguna ---
$diagnoses_history = [];
$sql_history = "
    SELECT 
        d.id AS diagnosis_id,
        dis.nama_penyakit AS diagnosed_disease_name,
        d.cf_result,
        d.gejala_dipilih, /* Kolom ini menyimpan JSON */
        d.tanggal_diagnosa
    FROM 
        diagnoses d
    JOIN 
        diseases dis ON d.diagnosed_disease_id = dis.id
    WHERE
        d.user_id = ? /* Filter berdasarkan user_id yang sedang login */
    ORDER BY 
        d.tanggal_diagnosa DESC
";

if ($stmt_history = $conn->prepare($sql_history)) {
    $stmt_history->bind_param("i", $current_user_id);
    if ($stmt_history->execute()) {
        $result_history = $stmt_history->get_result();

        if ($result_history->num_rows > 0) {
            while ($row = $result_history->fetch_assoc()) {
                // Dekode JSON dari kolom gejala_dipilih
                $gejala_ids_arr = json_decode($row['gejala_dipilih'], true);
                
                // Tambahkan penanganan jika JSON invalid atau null
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("JSON Decode Error for diagnosis_id " . $row['diagnosis_id'] . ": " . json_last_error_msg());
                    $row['gejala_dipilih_decoded'] = []; // Set sebagai array kosong jika error
                    $row['gejala_dipilih_count'] = 0;
                } else {
                    $row['gejala_dipilih_decoded'] = $gejala_ids_arr;
                    $row['gejala_dipilih_count'] = count($gejala_ids_arr);
                }
                $diagnoses_history[] = $row;
            }
        }
    } else {
        // Log error eksekusi query
        error_log("Error executing user diagnoses history query: " . $stmt_history->error);
        $message_type = "danger";
        $message_content = "Terjadi kesalahan saat memuat riwayat diagnosa: " . $stmt_history->error;
    }
    $stmt_history->close();
} else {
    // Log error persiapan statement
    error_log("Error preparing user diagnoses history query: " . $conn->error);
    $message_type = "danger";
    $message_content = "Terjadi kesalahan sistem saat memuat riwayat diagnosa. Mohon coba lagi nanti.";
}

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

$conn->close(); // Tutup koneksi setelah semua data diambil
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Diagnosa Saya - Sistem Pakar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* CSS khusus untuk halaman riwayat diagnosa user */
        body {
            background-color: #f0f2f5;
            padding-top: 70px; /* Space for fixed navbar */
        }
        .history-container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 0 25px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }
        .history-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .history-header .icon {
            font-size: 4rem;
            color: #17a2b8; /* Info color */
            margin-bottom: 15px;
        }
        .history-header h2 {
            font-weight: 700;
            color: #343a40;
        }
        .table-responsive {
            margin-top: 20px;
        }
        .gejala-cell {
            max-width: 250px; /* Lebar maksimum sel gejala */
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .badge-cf {
            font-size: 0.85em;
            padding: 0.4em 0.6em;
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
                            <li><a class="dropdown-item" href="profile.php">Profil Saya</a></li>
                            <li><a class="dropdown-item active" href="diagnoses_history.php">Riwayat Diagnosa</a></li>
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
        <div class="history-container">
            <div class="history-header">
                <div class="icon"><i class="fas fa-history"></i></div>
                <h2>Riwayat Diagnosa Saya</h2>
                <p class="text-muted">Lihat kembali diagnosa yang pernah Anda lakukan.</p>
            </div>

            <?php if ($message_content): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message_content; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tanggal Diagnosa</th>
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
                                    <td><?php echo htmlspecialchars($diagnosis['diagnosed_disease_name']); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($diagnosis['cf_result'], 2)); ?> (<?php echo round($diagnosis['cf_result'] * 100, 2); ?>%)</td>
                                    <td><span class="badge bg-info badge-cf"><?php echo htmlspecialchars(interpretCF($diagnosis['cf_result'])); ?></span></td>
                                    <td>
                                        <span class="gejala-cell">
                                            <?php 
                                                // Gunakan data yang sudah di-decoded dari PHP
                                                if (!empty($diagnosis['gejala_dipilih_decoded'])) {
                                                    echo "Total: " . $diagnosis['gejala_dipilih_count'] . " gejala.";
                                                } else {
                                                    echo "-";
                                                }
                                            ?>
                                        </span>
                                        <?php if (!empty($diagnosis['gejala_dipilih_decoded'])): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary mt-1" data-bs-toggle="modal" data-bs-target="#gejalaDetailModal" data-gejala-ids='<?php echo htmlspecialchars(json_encode($diagnosis['gejala_dipilih_decoded'])); ?>' data-diagnosis-id="<?php echo $diagnosis['diagnosis_id']; ?>">
                                                Lihat Detail
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="../user/diagnosa.php" class="btn btn-success btn-sm"><i class="fas fa-redo"></i> Diagnosa Lagi</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">Anda belum memiliki riwayat diagnosa.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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
                    // Path relatif dari folder user/ ke admin/get_symptom_names_by_ids.php
                    fetch('../admin/get_symptom_names_by_ids.php', { 
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
    </script>
</body>
</html>