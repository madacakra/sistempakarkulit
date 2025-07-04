<?php
// Mulai session PHP
session_start();

// Proteksi halaman: Pastikan user sudah login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $_SESSION['error_message'] = "Anda harus login untuk mengakses halaman ini.";
    header("location: login.php");
    exit();
}

// Sertakan file koneksi database
require_once 'config/database.php';

// Fungsi untuk menghitung kombinasi Certainty Factor
function calculateCF($cf_lama, $cf_baru) {
    if ($cf_lama >= 0 && $cf_baru >= 0) {
        return $cf_lama + $cf_baru * (1 - $cf_lama);
    } elseif ($cf_lama < 0 && $cf_baru < 0) {
        return $cf_lama + $cf_baru * (1 + $cf_lama);
    } else {
        // Rumus untuk CF berbeda tanda: CF1 + CF2 / (1 - min(|CF1|, |CF2|))
        // Pastikan pembagi tidak nol untuk menghindari "division by zero"
        $min_abs = min(abs($cf_lama), abs($cf_baru));
        if (1 - $min_abs == 0) {
            // Tangani kasus khusus jika penyebut mendekati nol
            return ($cf_lama + $cf_baru) > 0 ? 1.0 : -1.0; // Atau nilai batas lain
        }
        return ($cf_lama + $cf_baru) / (1 - $min_abs);
    }
}

// Cek apakah form diagnosis telah disubmit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil gejala yang dipilih oleh user
    $selected_gejala_ids = isset($_POST['gejala']) ? $_POST['gejala'] : [];

    // Jika tidak ada gejala yang dipilih
    if (empty($selected_gejala_ids)) {
        $_SESSION['error_message'] = "Anda belum memilih gejala apapun. Silakan pilih setidaknya satu gejala.";
        header("location: diagnosa.php");
        exit();
    }

    // Ubah array ID gejala menjadi string yang aman untuk query IN
    // Gunakan prepared statements atau setidaknya pastikan setiap elemen aman
    // Untuk klausa IN yang dinamis, ini pendekatan umum, tapi pastikan data_type untuk bind_param
    // jika menggunakan execute prepared statement untuk klausa IN
    $placeholders = implode(',', array_fill(0, count($selected_gejala_ids), '?'));
    $types = str_repeat('s', count($selected_gejala_ids)); // Asumsi ID gejala adalah string

    // Step 1: Forward Chaining - Dapatkan semua aturan yang relevan
    // Filter aturan berdasarkan gejala yang dipilih oleh user
    $sql_rules = "
        SELECT
            r.id, /* ID aturan */
            r.id_penyakit,
            r.id_gejala,
            r.cf_rule,
            d.nama_penyakit,
            d.deskripsi AS deskripsi_penyakit,
            d.solusi AS solusi_penyakit,
            s.nama_gejala
        FROM
            rules r
        JOIN
            diseases d ON r.id_penyakit = d.id
        JOIN
            symptoms s ON r.id_gejala = s.id
        WHERE
            r.id_gejala IN ($placeholders)
        ORDER BY
            r.id_penyakit, r.id_gejala
    ";
    
    $stmt_rules = $conn->prepare($sql_rules);
    if ($stmt_rules) {
        $stmt_rules->bind_param($types, ...$selected_gejala_ids);
        $stmt_rules->execute();
        $result_rules = $stmt_rules->get_result();

        $relevant_rules = [];
        if ($result_rules->num_rows > 0) {
            while ($row = $result_rules->fetch_assoc()) {
                $relevant_rules[] = $row;
            }
        }
        $stmt_rules->close();
    } else {
        $_SESSION['error_message'] = "Terjadi kesalahan saat menyiapkan aturan diagnosis.";
        error_log("Error preparing rules query: " . $conn->error);
        header("location: diagnosa.php");
        exit();
    }
    
    if (empty($relevant_rules)) {
        $_SESSION['error_message'] = "Tidak ada diagnosa yang cocok dengan gejala yang Anda pilih. Coba pilih gejala lain atau hubungi dokter.";
        header("location: diagnosa.php");
        exit();
    }

    // Step 2: Certainty Factor Calculation
    $diagnosed_diseases = []; // Array untuk menyimpan hasil CF per penyakit

    foreach ($relevant_rules as $rule) {
        $id_penyakit = $rule['id_penyakit'];
        $nama_penyakit = $rule['nama_penyakit'];
        $deskripsi_penyakit = $rule['deskripsi_penyakit'];
        $solusi_penyakit = $rule['solusi_penyakit'];
        $cf_rule = (float) $rule['cf_rule']; // Convert to float

        // Asumsi CF Evidence (CF[E]) adalah 1.0 (pasti dialami) jika gejala dipilih.
        $cf_evidence = 1.0; 

        // Hitung CF gabungan untuk aturan spesifik ini: CF[H, E] = CF[E] * CF[Rule]
        $cf_combined_for_rule = $cf_evidence * $cf_rule;

        // Inisialisasi CF untuk penyakit jika belum ada, atau kombinasikan
        if (!isset($diagnosed_diseases[$id_penyakit])) {
            $diagnosed_diseases[$id_penyakit] = [
                'id_penyakit' => $id_penyakit, // Kunci baru
                'cf_total' => $cf_combined_for_rule,
                'nama_penyakit' => $nama_penyakit,
                'deskripsi_penyakit' => $deskripsi_penyakit,
                'solusi_penyakit' => $solusi_penyakit,
                'matched_gejala_ids' => [], // Untuk menyimpan ID gejala yang cocok
                'matched_gejala_names' => [] // Untuk menyimpan nama gejala yang cocok
            ];
        } else {
            $diagnosed_diseases[$id_penyakit]['cf_total'] = calculateCF(
                $diagnosed_diseases[$id_penyakit]['cf_total'],
                $cf_combined_for_rule
            );
        }

        // Tambahkan ID dan nama gejala yang cocok ke daftar gejala untuk penyakit ini
        if (!in_array($rule['id_gejala'], $diagnosed_diseases[$id_penyakit]['matched_gejala_ids'])) {
            $diagnosed_diseases[$id_penyakit]['matched_gejala_ids'][] = $rule['id_gejala'];
            $diagnosed_diseases[$id_penyakit]['matched_gejala_names'][] = $rule['nama_gejala'];
        }
    }

    // Step 3: Urutkan hasil diagnosis berdasarkan CF tertinggi
    usort($diagnosed_diseases, function($a, $b) {
        return $b['cf_total'] <=> $a['cf_total'];
    });

    // Step 4: Tentukan hasil diagnosis akhir (penyakit dengan CF tertinggi)
    $final_diagnosis = null;
    if (!empty($diagnosed_diseases)) {
        $final_diagnosis = $diagnosed_diseases[0]; // Penyakit dengan CF tertinggi

        // Filter hasil jika ada penyakit dengan CF yang sangat rendah (opsional, tentukan threshold)
        if ($final_diagnosis['cf_total'] <= 0.2) { // Contoh threshold rendah
            $_SESSION['error_message'] = "Berdasarkan gejala yang Anda pilih, kami belum dapat menentukan diagnosa dengan kepastian tinggi. Silakan periksa kembali gejala atau konsultasikan ke dokter.";
            header("location: diagnosa.php");
            exit();
        }
    } else {
        $_SESSION['error_message'] = "Tidak ada diagnosa yang dapat dihasilkan dari gejala yang Anda pilih.";
        header("location: diagnosa.php");
        exit();
    }

    // Step 5: Simpan Riwayat Diagnosa
    $user_id = $_SESSION['id'];
    $diagnosed_disease_id_for_db = $final_diagnosis['id_penyakit']; // Mengambil kunci id_penyakit yang sudah ada
    $cf_result = $final_diagnosis['cf_total'];
    $gejala_dipilih_json = json_encode($final_diagnosis['matched_gejala_ids']); // Simpan ID gejala dalam format JSON

    $sql_insert_diagnosa = "INSERT INTO diagnoses (user_id, diagnosed_disease_id, cf_result, gejala_dipilih) VALUES (?, ?, ?, ?)";
    if ($stmt_insert_diagnosa = $conn->prepare($sql_insert_diagnosa)) {
        $stmt_insert_diagnosa->bind_param("isds", $user_id, $diagnosed_disease_id_for_db, $cf_result, $gejala_dipilih_json);
        if (!$stmt_insert_diagnosa->execute()) {
            error_log("Error saving diagnosis history: " . $stmt_insert_diagnosa->error);
            // Tidak perlu hentikan user, ini hanya logging
        }
        $stmt_insert_diagnosa->close();
    } else {
        error_log("Error preparing diagnosis history insert: " . $conn->error);
    }
    
    // Step 6: Simpan hasil diagnosis ke session dan redirect ke halaman hasil
    $_SESSION['diagnosa_result'] = $final_diagnosis;
    $_SESSION['selected_gejala_names'] = $final_diagnosis['matched_gejala_names']; // Menggunakan nama gejala yang sudah terkumpul

    // Tutup koneksi database di akhir skrip, setelah semua operasi selesai
    $conn->close();

    header("location: hasil_diagnosa.php");
    exit();

} else {
    // Jika diakses tanpa POST request
    header("location: diagnosa.php");
    exit();
}
?>