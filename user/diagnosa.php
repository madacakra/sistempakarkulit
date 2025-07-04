<?php
// BARIS PERTAMA HARUS <?php TANPA SPASI ATAUPUN BARIS KOSONG SEBELUMNYA
session_start();

// Proteksi halaman: Cek apakah user sudah login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $_SESSION['error_message'] = "Anda harus login untuk mengakses halaman ini.";
    header("location: login.php");
    exit();
}

// Sertakan file koneksi database. Path ini relatif dari user/ ke config/
require_once '../config/database.php'; 

// --- Fungsi-fungsi Utama ---

/**
 * Fungsi untuk menghitung kombinasi Certainty Factor.
 * Rumus dari dokumen proposal Anda:
 * - Jika kedua CF positif: CF[H, E] = CF[lama] + CF[baru] * (1 – CF[lama])
 * - Jika kedua CF negatif: CF[H, E] = CF[lama] + CF[baru] * (1 + CF[lama])
 * - Jika salah satu CF negatif: CF[H, E] = (CF[lama] + CF[baru]) / (1 – min{|CF[lama]|, |CF[baru]|})
 */
function calculateCF($cf_lama, $cf_baru) {
    if ($cf_lama >= 0 && $cf_baru >= 0) {
        return $cf_lama + $cf_baru * (1 - $cf_lama);
    } elseif ($cf_lama < 0 && $cf_baru < 0) {
        return $cf_lama + $cf_baru * (1 + $cf_lama);
    } else {
        $min_abs = min(abs($cf_lama), abs($cf_baru));
        // Hindari division by zero jika 1 - min_abs sangat dekat dengan 0
        if (abs(1 - $min_abs) < 0.000001) { 
            return ($cf_lama + $cf_baru) > 0 ? 1.0 : -1.0; 
        }
        return ($cf_lama + $cf_baru) / (1 - $min_abs);
    }
}

/**
 * Fungsi untuk menginterpretasikan nilai Certainty Factor.
 * Sesuai Tabel 2.1 dari dokumen proposal Anda.
 */
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
        return "Tidak Diketahui"; // Untuk nilai di luar rentang 0-1 (negatif)
    }
}

/**
 * Fungsi utama untuk memproses diagnosis langkah demi langkah.
 * Mengelola state diagnosis di sesi, menghitung CF, dan memilih gejala berikutnya.
 */
function process_diagnosis_step($conn, $user_answer_symptom_id = null, $user_answer_cfe_value = null) {
    // Inisialisasi/reset state diagnosis
    if (!isset($_SESSION['diagnosis_state']) || $user_answer_symptom_id === 'reset') {
        $_SESSION['diagnosis_state'] = [
            'asked_symptoms' => [],        // Gejala yang sudah ditanyakan: [id => CFE_value (float)]
            'current_disease_cf' => [],    // Akumulasi CF per penyakit: [id => total_cf]
            'diagnosis_complete' => false, // Flag jika diagnosis selesai
            'final_diagnosis_result' => null, // Hasil diagnosis akhir
            'next_symptom_to_ask' => null, // Gejala berikutnya yang akan ditanyakan
            'gejala_terpilih_final_ids' => [], // ID gejala yang dijawab dengan CFE > 0
            'gejala_terpilih_final_names' => [], // Nama gejala yang dijawab dengan CFE > 0
            'iteration' => 0,              // Jumlah pertanyaan yang sudah diajukan
            'all_symptom_ids' => []        // Cache semua ID gejala untuk fallback
        ];
        // Cache semua ID gejala saat inisialisasi untuk efisiensi
        $sql_all_symptoms = $conn->query("SELECT id FROM symptoms");
        if ($sql_all_symptoms) {
            while ($row = $sql_all_symptoms->fetch_assoc()) {
                $_SESSION['diagnosis_state']['all_symptom_ids'][] = $row['id'];
            }
            $sql_all_symptoms->free();
        } else {
            error_log("Error caching all symptoms: " . $conn->error);
        }
        // Jika reset, kita perlu menentukan gejala pertama lagi
        $user_answer_symptom_id = null; // Set null agar tidak memproses "reset" sebagai jawaban
        $user_answer_cfe_value = null; // Set null
    }

    $state = &$_SESSION['diagnosis_state']; // Referensi ke state sesi

    // Jika ada jawaban dari pengguna (bukan reset), proses jawaban tersebut
    if ($user_answer_symptom_id) { // Sudah tidak perlu cek !== 'reset' di sini karena sudah dihandle di atas
        $state['asked_symptoms'][$user_answer_symptom_id] = (float)$user_answer_cfe_value; // Simpan CFE yang diberikan user
        $state['iteration']++;

        // Jika CFE positif, tambahkan ke gejala terpilih untuk riwayat
        if ((float)$user_answer_cfe_value > 0) {
            $stmt_symptom_name = $conn->prepare("SELECT nama_gejala FROM symptoms WHERE id = ?");
            if ($stmt_symptom_name) {
                $stmt_symptom_name->bind_param("s", $user_answer_symptom_id);
                $stmt_symptom_name->execute();
                $result_symptom_name = $stmt_symptom_name->get_result();
                $symptom_name_row = $result_symptom_name->fetch_assoc();
                $stmt_symptom_name->close();

                if ($symptom_name_row) {
                    if (!in_array($user_answer_symptom_id, $state['gejala_terpilih_final_ids'])) {
                         $state['gejala_terpilih_final_ids'][] = $user_answer_symptom_id;
                         $state['gejala_terpilih_final_names'][] = $symptom_name_row['nama_gejala'];
                    }
                }
            } else {
                error_log("Error preparing symptom name query: " . $conn->error);
            }
        }
    }

    // --- Logika Forward Chaining & Certainty Factor Kalkulasi ---
    // (Dijalankan di setiap langkah untuk memperbarui CF berdasarkan semua jawaban)

    $state['current_disease_cf'] = []; // Reset CF untuk kalkulasi baru di iterasi ini

    // Ambil semua aturan (rules) dari database
    $rules = [];
    $sql_all_rules = "SELECT r.id, r.id_penyakit, r.id_gejala, r.cf_rule, d.nama_penyakit, d.deskripsi AS deskripsi_penyakit, d.solusi AS solusi_penyakit FROM rules r JOIN diseases d ON r.id_penyakit = d.id";
    $result_all_rules = $conn->query($sql_all_rules);
    if ($result_all_rules) {
        while ($row = $result_all_rules->fetch_assoc()) {
            $rules[] = $row;
        }
        $result_all_rules->free();
    } else {
        error_log("Error fetching all rules: " . $conn->error);
        $state['diagnosis_complete'] = true;
        $state['final_diagnosis_result'] = null;
        return $state;
    }

    // Iterasi setiap aturan untuk menghitung CF penyakit
    foreach ($rules as $rule) {
        $id_penyakit = $rule['id_penyakit'];
        $id_gejala = $rule['id_gejala'];
        $cf_rule = (float) $rule['cf_rule'];

        $cf_evidence = 0.0; // Default jika gejala belum ditanyakan atau dijawab 0

        // Ambil CFE dari jawaban pengguna untuk gejala ini jika sudah ditanyakan
        if (isset($state['asked_symptoms'][$id_gejala])) {
            $cf_evidence = $state['asked_symptoms'][$id_gejala]; // Gunakan CFE dari user
        }

        // Hanya proses jika ada CF_evidence yang signifikan (bukan 0)
        if ($cf_evidence !== 0.0) {
            $cf_combined_for_rule = $cf_evidence * $cf_rule;

            // Akumulasikan CF untuk penyakit ini
            if (!isset($state['current_disease_cf'][$id_penyakit])) {
                $state['current_disease_cf'][$id_penyakit] = [
                    'id_penyakit' => $id_penyakit,
                    'nama_penyakit' => $rule['nama_penyakit'],
                    'deskripsi_penyakit' => $rule['deskripsi_penyakit'],
                    'solusi_penyakit' => $rule['solusi_penyakit'],
                    'cf_total' => $cf_combined_for_rule // CF awal untuk penyakit ini
                ];
            } else {
                // Kombinasikan CF dengan nilai yang sudah ada
                $state['current_disease_cf'][$id_penyakit]['cf_total'] = calculateCF(
                    $state['current_disease_cf'][$id_penyakit]['cf_total'],
                    $cf_combined_for_rule
                );
            }
        }
    }

    // Urutkan penyakit berdasarkan CF tertinggi
    $potential_diagnoses = array_values($state['current_disease_cf']);
    usort($potential_diagnoses, function($a, $b) {
        return $b['cf_total'] <=> $a['cf_total'];
    });

    // --- Kriteria Penyelesaian Diagnosis ---
    // Diagnosis dianggap selesai jika CF penyakit tertinggi di atas ambang batas.
    $diagnosis_threshold = 0.85; // <--- AMBANG BATAS DIAGNOSIS (Bisa dinaikkan ke 0.9 untuk lebih kritis)

    if (!empty($potential_diagnoses) && $potential_diagnoses[0]['cf_total'] >= $diagnosis_threshold) {
        $state['diagnosis_complete'] = true;
        $state['final_diagnosis_result'] = $potential_diagnoses[0];
        // Pastikan CF total tidak di bawah 0 agar interpretasi tidak "Tidak Diketahui"
        $state['final_diagnosis_result']['cf_total'] = max(0, $state['final_diagnosis_result']['cf_total']); 
        $state['final_diagnosis_result']['matched_gejala_ids'] = $state['gejala_terpilih_final_ids'];
        $state['final_diagnosis_result']['matched_gejala_names'] = $state['gejala_terpilih_final_names'];
        return $state;
    }

    // --- Pemilihan Gejala Berikutnya (Intelligent Question Selection) ---
    $next_symptom_id = null;
    $unasked_symptoms_priorities = []; 

    // Strategi: Prioritaskan gejala yang belum ditanyakan yang paling mungkin mempengaruhi CF penyakit kandidat.
    if (!empty($potential_diagnoses)) {
        foreach ($potential_diagnoses as $diag_candidate) {
            // Hanya pertimbangkan penyakit yang memiliki CF positif atau belum sepenuhnya dibantah (CF > 0 atau mendekati 0)
            // Dan memiliki CF yang cukup signifikan untuk diinvestigasi lebih lanjut
            if ($diag_candidate['cf_total'] > 0.1 || ($diag_candidate['cf_total'] <= 0.1 && $diag_candidate['cf_total'] >= -0.1 && $state['iteration'] < 5)) { // Investigate low CF in early stages
                $id_penyakit_candidate = $diag_candidate['id_penyakit'];
                $stmt_related_symptoms = $conn->prepare("SELECT id_gejala, cf_rule FROM rules WHERE id_penyakit = ?");
                if ($stmt_related_symptoms) {
                    $stmt_related_symptoms->bind_param("s", $id_penyakit_candidate);
                    $stmt_related_symptoms->execute();
                    $result_related_symptoms = $stmt_related_symptoms->get_result();
                    while($row = $result_related_symptoms->fetch_assoc()){
                        $s_id = $row['id_gejala'];
                        $s_cf_rule = (float) $row['cf_rule'];
                        // Hanya tambahkan gejala yang belum ditanyakan
                        if (!isset($state['asked_symptoms'][$s_id])) {
                            // Prioritas: (semakin tinggi CF rule gejala) * (semakin relevan dengan kandidat teratas)
                            $unasked_symptoms_priorities[$s_id] = ($unasked_symptoms_priorities[$s_id] ?? 0) + $s_cf_rule; 
                        }
                    }
                    $stmt_related_symptoms->close();
                } else {
                    error_log("Error preparing related symptoms query: " . $conn->error);
                }
            }
        }
    }
    
    // Fallback: Jika tidak ada gejala yang diprioritaskan dari kandidat yang menjanjikan, ambil dari semua gejala yang belum ditanyakan.
    // Ini memastikan sistem tidak stuck jika semua jalur utama sudah dieksplorasi.
    if (empty($unasked_symptoms_priorities) && !empty($state['all_symptom_ids'])) {
        foreach ($state['all_symptom_ids'] as $s_id) {
            if (!isset($state['asked_symptoms'][$s_id])) {
                // Beri prioritas rendah untuk fallback agar tidak mengganggu prioritas utama
                $unasked_symptoms_priorities[$s_id] = ($unasked_symptoms_priorities[$s_id] ?? 0) + 0.01; 
            }
        }
    }
    

    // Pilih gejala dengan prioritas tertinggi
    if (!empty($unasked_symptoms_priorities)) {
        arsort($unasked_symptoms_priorities); // Urutkan dari prioritas tertinggi
        $next_symptom_id = key($unasked_symptoms_priorities); // Ambil ID gejala dengan prioritas tertinggi
    }
    
    // Batasan iterasi untuk mencegah loop tak terbatas
    $max_iterations = 25; // Anda bisa sesuaikan ini, misal dua kali jumlah penyakit atau jumlah gejala total

    // Jika sudah mencapai batas iterasi atau tidak ada lagi gejala untuk ditanyakan
    if ($state['iteration'] >= $max_iterations || !$next_symptom_id) {
        $state['diagnosis_complete'] = true;
        // Jika tidak dapat menegakkan diagnosis dengan kepastian tinggi,
        // berikan penyakit teratas yang ditemukan (meskipun CF-nya rendah) untuk ditampilkan
        // di halaman hasil sebagai "diagnosis tidak pasti".
        if (!empty($potential_diagnoses)) {
             $state['final_diagnosis_result'] = $potential_diagnoses[0];
             // Pastikan final CF tidak kurang dari 0 agar interpretasi tidak "Tidak Diketahui"
             $state['final_diagnosis_result']['cf_total'] = max(0, $state['final_diagnosis_result']['cf_total']); 
             $state['final_diagnosis_result']['matched_gejala_ids'] = $state['gejala_terpilih_final_ids'];
             $state['final_diagnosis_result']['matched_gejala_names'] = $state['gejala_terpilih_final_names'];
        } else {
            $state['final_diagnosis_result'] = null; // Sama sekali tidak ada penyakit yang cocok
        }
        return $state;
    }

    $state['next_symptom_to_ask'] = $next_symptom_id;
    return $state;
}


// --- Tangani Permintaan AJAX (POST) ---
// Blok ini akan dieksekusi jika permintaan berasal dari AJAX (dari JavaScript di bawah)
// dan akan mengirim respons JSON, lalu berhenti (exit).
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    // Set header respons ke JSON sebelum output apapun
    header('Content-Type: application/json');

    $symptom_id = $_POST['symptom_id'] ?? null;
    $answer_cfe_value = $_POST['answer_cfe_value'] ?? null; // Menerima nilai CFE

    // Periksa apakah input JSON diterima dengan benar, jika tidak, coba dari raw input
    if (is_null($symptom_id) && is_null($answer_cfe_value)) {
        $input = json_decode(file_get_contents('php://input'), true);
        $symptom_id = $input['symptom_id'] ?? null;
        $answer_cfe_value = $input['answer_cfe_value'] ?? null;
    }
    
    // Pastikan koneksi database aktif untuk fungsi diagnosis
    // Jika $conn sudah di-require_once di atas, ini akan menggunakan koneksi yang sama.
    if (!$conn->ping()) { // Cek jika koneksi sudah mati
        $conn->close();
        // Rekoneksi jika diperlukan. Untuk kasus ini, karena $conn di-require_once di atas, seharusnya aktif.
        // Jika ini jadi masalah, Anda bisa buat koneksi baru di sini atau ubah logic koneksi.
    }

    // Proses langkah diagnosis
    $diagnosis_state_result = process_diagnosis_step($conn, $symptom_id, $answer_cfe_value);
    
    // Jika diagnosis selesai, simpan ke database dan siapkan respons akhir
    if ($diagnosis_state_result['diagnosis_complete']) {
        if ($diagnosis_state_result['final_diagnosis_result']) {
            $final_diagnosis = $diagnosis_state_result['final_diagnosis_result'];
            $user_id = $_SESSION['id'];
            $diagnosed_disease_id = $final_diagnosis['id_penyakit'];
            $cf_result = $final_diagnosis['cf_total'];
            $gejala_dipilih_json = json_encode($final_diagnosis['matched_gejala_ids']);
            
            // Simpan riwayat diagnosis
            $sql_insert_diagnosa = "INSERT INTO diagnoses (user_id, diagnosed_disease_id, cf_result, gejala_dipilih) VALUES (?, ?, ?, ?)";
            if ($stmt_insert_diagnosa = $conn->prepare($sql_insert_diagnosa)) {
                $stmt_insert_diagnosa->bind_param("isds", $user_id, $diagnosed_disease_id, $cf_result, $gejala_dipilih_json);
                if (!$stmt_insert_diagnosa->execute()) {
                    error_log("Error saving diagnosis history: " . $stmt_insert_diagnosa->error);
                }
                $stmt_insert_diagnosa->close();
            } else {
                error_log("Error preparing diagnosis history insert: " . $conn->error);
            }
        }
        
        // Hapus state diagnosis dari session setelah selesai
        unset($_SESSION['diagnosis_state']);
    }

    // Ambil nama gejala berikutnya jika ada
    $next_symptom_name = null;
    if ($diagnosis_state_result['next_symptom_to_ask']) {
        $stmt_next_symptom = $conn->prepare("SELECT nama_gejala FROM symptoms WHERE id = ?");
        if ($stmt_next_symptom) {
            $stmt_next_symptom->bind_param("s", $diagnosis_state_result['next_symptom_to_ask']);
            $stmt_next_symptom->execute();
            $result_next_symptom = $stmt_next_symptom->get_result();
            $row_next_symptom = $result_next_symptom->fetch_assoc();
            $next_symptom_name = $row_next_symptom['nama_gejala'] ?? null;
            $stmt_next_symptom->close();
        } else {
            error_log("Error preparing next symptom query: " . $conn->error);
        }
    }
    
    // Kirim respons JSON
    echo json_encode([
        'status' => 'success',
        'diagnosis_complete' => $diagnosis_state_result['diagnosis_complete'],
        'final_diagnosis' => $diagnosis_state_result['final_diagnosis_result'],
        'next_symptom_id' => $diagnosis_state_result['next_symptom_to_ask'],
        'next_symptom_name' => $next_symptom_name,
        'iteration' => $diagnosis_state_result['iteration'],
        'current_disease_cf' => $diagnosis_state_result['current_disease_cf']
    ]);
    $conn->close(); // Tutup koneksi setelah respons JSON dikirim
    exit(); // SANGAT PENTING: Hentikan eksekusi skrip setelah mengirim JSON
}

// --- Tampilan Awal Halaman Diagnosa (Non-AJAX Load) ---
// Bagian ini hanya akan dieksekusi jika permintaan BUKAN POST AJAX.

// Pastikan koneksi database aktif untuk inisialisasi tampilan
if (!$conn->ping()) { // Cek jika koneksi sudah mati
    $conn->close(); // Tutup koneksi lama jika ada
    require_once '../config/database.php'; // Rekoneksi
}

// Inisialisasi proses diagnosis untuk pertama kali
$initial_state = process_diagnosis_step($conn);
$first_symptom_id = $initial_state['next_symptom_to_ask'];

$first_symptom_name = null;
if ($first_symptom_id) {
    $stmt_first_symptom = $conn->prepare("SELECT nama_gejala FROM symptoms WHERE id = ?");
    if ($stmt_first_symptom) {
        $stmt_first_symptom->bind_param("s", $first_symptom_id);
        $stmt_first_symptom->execute();
        $result_first_symptom = $stmt_first_symptom->get_result();
        $row_first_symptom = $result_first_symptom->fetch_assoc();
        $first_symptom_name = $row_first_symptom['nama_gejala'] ?? null;
        $stmt_first_symptom->close();
    } else {
        error_log("Error preparing first symptom query: " . $conn->error);
    }
}
$conn->close(); // Tutup koneksi setelah tampilan awal disiapkan
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnosa Penyakit Kulit - Sistem Pakar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* CSS khusus untuk halaman diagnosa */
        body {
            background-color: #f0f2f5;
            padding-top: 70px; /* Sesuaikan dengan tinggi navbar */
        }
        .diagnosa-container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 25px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }
        .question-card {
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border-radius: 12px;
            padding: 30px;
            text-align: center;
        }
        .question-card .question-text {
            font-size: 1.6rem;
            font-weight: 600;
            color: #343a40;
            margin-bottom: 30px;
        }
        .answer-buttons .btn {
            font-size: 1.1rem;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 50px;
            margin: 5px;
            min-width: 120px;
        }
        .progress-section {
            margin-top: 30px;
            text-align: left;
        }
        .progress-section .progress-bar {
            background-color: #28a745;
        }
        .progress-label {
            font-weight: 600;
            margin-bottom: 5px;
            color: #495057;
        }
        .result-card {
            border: none;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border-radius: 15px;
        }
        /* Style untuk dropdown jawaban interpretasi */
        .answer-dropdown {
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .answer-dropdown select {
            width: 100%;
            max-width: 300px;
            margin: auto;
            border-radius: 8px;
            padding: 10px;
        }
        .answer-dropdown button {
            margin-top: 15px;
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
                        <a class="nav-link active" aria-current="page" href="user/diagnosa.php">Diagnosa</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle btn btn-outline-light text-white ms-lg-3" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['nama_lengkap']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="profile.php">Profil Saya</a></li>
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
        <div class="diagnosa-container">
            <h1 class="mb-4 text-primary text-center"><i class="fas fa-stethoscope me-2"></i> Diagnosis Penyakit Kulit</h1>
            <p class="text-muted text-center mb-4">Jawablah pertanyaan berikut dengan tingkat keyakinan Anda.</p>

            <div id="diagnosis-area">
                <?php if ($first_symptom_id && $first_symptom_name): ?>
                    <div class="question-card mt-4" data-symptom-id="<?php echo htmlspecialchars($first_symptom_id); ?>">
                        <p class="question-text">Apakah Anda mengalami: <strong><?php echo htmlspecialchars($first_symptom_name); ?></strong>?</p>
                        <div class="answer-dropdown">
                            <select class="form-select" id="cf_evidence_select">
                                <option value="0.0">Tidak Pasti (0%)</option>
                                <option value="0.2">Kurang Pasti (20%)</option>
                                <option value="0.4">Mungkin (40%)</option>
                                <option value="0.6">Kemungkinan Besar (60%)</option>
                                <option value="0.8">Hampir Pasti (80%)</option>
                                <option value="1.0">Pasti (100%)</option>
                                <option value="-1.0">Tidak Mengalami (-100%)</option>
                            </select>
                            <button class="btn btn-primary mt-3" id="submit_answer_btn"><i class="fas fa-paper-plane me-2"></i>Jawab</button>
                        </div>
                    </div>
                    <div class="progress-section mt-4">
                        <p class="progress-label">Progress Diagnosa:</p>
                        <div class="progress" role="progressbar" aria-label="Progress Diagnosa" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar" style="width: 0%">0%</div>
                        </div>
                        <p class="small mt-2 text-muted" id="cf-info">Gejala ditanyakan: 0. Penyakit teratas saat ini: Belum Ada (CF: 0%).</p>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning text-center" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i> Tidak ada gejala yang tersedia untuk memulai diagnosis. Mohon hubungi administrator.
                    </div>
                    <div class="text-center mt-3">
                        <a href="user/diagnosa.php" class="btn btn-secondary"><i class="fas fa-redo-alt me-2"></i> Mulai Ulang Diagnosis</a>
                        <a href="../index.php" class="btn btn-outline-primary"><i class="fas fa-home me-2"></i> Kembali ke Beranda</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="text-center mt-5">
                <button id="resetDiagnosisBtn" class="btn btn-outline-secondary"><i class="fas fa-undo me-2"></i> Mulai Diagnosis Ulang</button>
            </div>

        </div>
    </div>

    <footer id="kontak" class="footer mt-auto py-4 bg-dark text-white">
        <div class="container text-center">
            <p class="mb-1">&copy; 2025 Sistem Pakar Diagnosa Penyakit Kulit. Hak Cipta Dilindungi.</p>
            <p class="mb-0">Institut Teknologi dan Bisnis Ahmad Dahlan, Jakarta</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        // JavaScript untuk menangani alur diagnosis
        document.addEventListener('DOMContentLoaded', function() {
            const diagnosisArea = document.getElementById('diagnosis-area');
            const resetBtn = document.getElementById('resetDiagnosisBtn');

            // Fungsi untuk mengirim jawaban ke server via AJAX
            async function sendAnswer(symptomId, answerCfeValue) {
                const currentCard = diagnosisArea.querySelector('.question-card');
                if (currentCard) {
                    currentCard.style.opacity = '0.5'; // Beri efek loading
                    currentCard.style.pointerEvents = 'none'; // Nonaktifkan klik
                }

                try {
                    const response = await fetch(window.location.pathname, { // Menggunakan path halaman saat ini
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest' // Menandai ini request AJAX
                        },
                        body: JSON.stringify({
                            symptom_id: symptomId,
                            answer_cfe_value: answerCfeValue
                        })
                    });

                    // Periksa jika respons bukan OK (misal 404, 500)
                    if (!response.ok) {
                        const errorText = await response.text();
                        throw new Error(`HTTP error! status: ${response.status}, body: ${errorText}`);
                    }

                    const data = await response.json(); // Coba parsing respons sebagai JSON

                    if (data.status === 'success') {
                        if (data.diagnosis_complete) {
                            displayFinalDiagnosis(data.final_diagnosis);
                        } else {
                            displayNextQuestion(data.next_symptom_id, data.next_symptom_name, data.iteration, data.current_disease_cf);
                        }
                    } else {
                        displayError(data.message || 'Terjadi kesalahan pada proses diagnosis.');
                    }
                } catch (error) {
                    console.error('Error during AJAX request:', error);
                    displayError('Terjadi kesalahan jaringan atau server. Detail: ' + error.message);
                }
            }

            // Fungsi untuk menampilkan pertanyaan berikutnya
            function displayNextQuestion(nextSymptomId, nextSymptomName, iteration, currentCFs) {
                let html = '';
                if (nextSymptomId && nextSymptomName) {
                    html = `
                        <div class="question-card mt-4" data-symptom-id="${nextSymptomId}">
                            <p class="question-text">Apakah Anda mengalami: <strong>${nextSymptomName}</strong>?</p>
                            <div class="answer-dropdown">
                                <select class="form-select" id="cf_evidence_select">
                                    <option value="0.0">Tidak Pasti (0%)</option>
                                    <option value="0.2">Kurang Pasti (20%)</option>
                                    <option value="0.4">Mungkin (40%)</option>
                                    <option value="0.6">Kemungkinan Besar (60%)</option>
                                    <option value="0.8">Hampir Pasti (80%)</option>
                                    <option value="1.0">Pasti (100%)</option>
                                    <option value="-1.0">Tidak Mengalami (-100%)</option>
                                </select>
                                <button class="btn btn-primary mt-3" id="submit_answer_btn"><i class="fas fa-paper-plane me-2"></i>Jawab</button>
                            </div>
                        </div>
                    `;
                    updateProgressBar(iteration, currentCFs);
                } else {
                    html = `
                        <div class="alert alert-warning text-center mt-4" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i> Sistem tidak dapat menegakkan diagnosis berdasarkan gejala yang diberikan.
                            <p class="mt-2">Silakan coba diagnosis ulang atau konsultasikan ke dokter.</p>
                        </div>
                        <div class="text-center mt-3">
                            <a href="user/diagnosa.php" class="btn btn-secondary"><i class="fas fa-redo-alt me-2"></i> Mulai Ulang Diagnosis</a>
                            <a href="../index.php" class="btn btn-outline-primary"><i class="fas fa-home me-2"></i> Kembali ke Beranda</a>
                        </div>
                    `;
                }
                diagnosisArea.innerHTML = html;
                addAnswerButtonListeners(); // Re-attach event listeners
            }

            // Fungsi untuk menampilkan hasil diagnosis akhir
            function displayFinalDiagnosis(finalDiagnosis) {
                if (!finalDiagnosis) {
                    displayNextQuestion(null, null, 0, {}); // Tampilkan pesan tidak dapat mendiagnosa
                    return;
                }

                const cfPercentage = Math.round(finalDiagnosis.cf_total * 10000) / 100;
                const cfInterpretation = interpretCF(finalDiagnosis.cf_total);

                let matchedGejalaHtml = '';
                if (finalDiagnosis.matched_gejala_names && finalDiagnosis.matched_gejala_names.length > 0) {
                    matchedGejalaHtml = `
                        <h5 class="mt-4"><i class="fas fa-list-check me-2"></i>Gejala yang Anda alami:</h5>
                        <ul class="list-group list-group-flush">
                            ${finalDiagnosis.matched_gejala_names.map(name => `<li class="list-group-item"><i class="fas fa-check-circle text-success me-2"></i>${name}</li>`).join('')}
                        </ul>
                    `;
                }

                const html = `
                    <div class="card result-card mt-4 p-4">
                        <h3 class="text-primary text-center mb-4"><i class="fas fa-vial me-2"></i>Diagnosis Selesai!</h3>
                        <div class="alert alert-info text-center" role="alert">
                            <p class="mb-0">Berdasarkan gejala yang Anda alami, kemungkinan besar Anda menderita:</p>
                            <h4 class="text-primary mt-2 fw-bold">${finalDiagnosis.nama_penyakit}</h4>
                        </div>

                        <div class="row mt-4">
                            <div class="col-md-6">
                                <h5><i class="fas fa-tag me-2"></i>Tingkat Kepastian (CF):</h5>
                                <div class="progress mb-2" role="progressbar" aria-valuenow="${cfPercentage}" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar" style="width: ${cfPercentage}%">${cfPercentage}%</div>
                                </div>
                                <p class="small text-muted">Interpretasi: <strong>${cfInterpretation}</strong></p>
                            </div>
                            <div class="col-md-6">
                                <h5><i class="fas fa-calendar-alt me-2"></i>Tanggal Diagnosa:</h5>
                                <p>${new Date().toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                            </div>
                        </div>

                        <h5 class="mt-4"><i class="fas fa-file-alt me-2"></i>Deskripsi Penyakit:</h5>
                        <p>${finalDiagnosis.deskripsi_penyakit}</p>

                        <h5 class="mt-4"><i class="fas fa-medkit me-2"></i>Solusi/Penanganan:</h5>
                        <p>${finalDiagnosis.solusi_penyakit}</p>
                        
                        ${matchedGejalaHtml}

                        <div class="text-center mt-5">
                            <a href="user/diagnosa.php" class="btn btn-primary btn-lg"><i class="fas fa-redo-alt me-2"></i> Diagnosa Ulang</a>
                            <a href="user/diagnoses_history.php" class="btn btn-outline-secondary btn-lg ms-3"><i class="fas fa-history me-2"></i> Lihat Riwayat</a>
                        </div>
                    </div>
                `;
                diagnosisArea.innerHTML = html;
                resetBtn.style.display = 'none';
            }

            // Fungsi untuk menampilkan pesan error
            function displayError(message) {
                diagnosisArea.innerHTML = `
                    <div class="alert alert-danger text-center mt-4" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> ${message}
                    </div>
                    <div class="text-center mt-3">
                        <a href="user/diagnosa.php" class="btn btn-secondary"><i class="fas fa-redo-alt me-2"></i> Mulai Ulang Diagnosis</a>
                        <a href="../index.php" class="btn btn-outline-primary"><i class="fas fa-home me-2"></i> Kembali ke Beranda</a>
                    </div>
                `;
                resetBtn.style.display = 'none';
            }

            // Fungsi untuk menginterpretasikan CF (sesuai Tabel 2.1)
            function interpretCF(cfValue) {
                if (cfValue === 0) return "Tidak Pasti";
                if (cfValue > 0 && cfValue <= 0.2) return "Kurang Pasti";
                if (cfValue > 0.2 && cfValue <= 0.4) return "Mungkin";
                if (cfValue > 0.4 && cfValue <= 0.6) return "Kemungkinan Besar";
                if (cfValue > 0.6 && cfValue <= 0.8) return "Hampir Pasti";
                if (cfValue > 0.8 && cfValue <= 1.0) return "Pasti";
                return "Tidak Diketahui";
            }

            // Fungsi untuk update progress bar dan info CF tertinggi
            function updateProgressBar(iteration, currentCFs) {
                const progressBarContainer = diagnosisArea.querySelector('.progress-section');
                const progressBar = progressBarContainer ? progressBarContainer.querySelector('.progress-bar') : null;
                const cfInfo = progressBarContainer ? progressBarContainer.querySelector('#cf-info') : null;

                let maxCF = 0;
                let topDiseaseName = 'Belum Ada';
                if (Object.keys(currentCFs).length > 0) {
                    const sortedCFs = Object.values(currentCFs).sort((a, b) => b.cf_total - a.cf_total);
                    maxCF = sortedCFs[0].cf_total;
                    topDiseaseName = sortedCFs[0].nama_penyakit;
                }
                
                const progressValue = Math.min(100, Math.round(maxCF * 100 * (1 / 0.85)));
                
                if (progressBar) { 
                    progressBar.style.width = `${progressValue}%`;
                    progressBar.setAttribute('aria-valuenow', progressValue);
                    progressBar.textContent = `${progressValue}%`;
                }
                if (cfInfo) { 
                     cfInfo.innerHTML = `Gejala ditanyakan: ${iteration}. Penyakit teratas saat ini: <strong>${topDiseaseName}</strong> (CF: ${Math.round(maxCF * 100)}%).`;
                }
            }

            // Tambahkan event listener ke tombol Jawab (dropdown)
            function addAnswerButtonListeners() {
                const submitAnswerBtn = diagnosisArea.querySelector('#submit_answer_btn');
                if (submitAnswerBtn) {
                    submitAnswerBtn.addEventListener('click', function() {
                        const questionCard = this.closest('.question-card');
                        const symptomId = questionCard.dataset.symptomId; // Pastikan ini 'data-symptom-id'
                        const cfEvidenceSelect = questionCard.querySelector('#cf_evidence_select');
                        const answerCfeValue = cfEvidenceSelect.value;
                        sendAnswer(symptomId, answerCfeValue);
                    });
                }
            }

            // Event listener untuk tombol reset
            resetBtn.addEventListener('click', function() {
                if (confirm('Apakah Anda yakin ingin memulai ulang diagnosis? Semua progress akan hilang.')) {
                    sendAnswer('reset', null); 
                }
            });

            // Inisialisasi: tambahkan event listener untuk pertanyaan pertama (saat DOMContentLoaded)
            addAnswerButtonListeners();
        });
    </script>
</body>
</html>