<?php
// Mulai session (opsional, tapi baik untuk konsistensi meskipun tidak langsung digunakan untuk otorisasi di sini)
session_start();

// Sertakan file koneksi database. Sesuaikan path jika file database.php berada di lokasi lain.
require_once '../config/database.php';

// Atur header untuk mengindikasikan bahwa respons adalah JSON
header('Content-Type: application/json');

// Periksa apakah request adalah POST dan content type adalah JSON
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    // Ambil body request dan decode dari JSON
    $input_data = json_decode(file_get_contents("php://input"), true);

    // Ambil array ID gejala dari input data. Jika tidak ada, atur sebagai array kosong.
    $symptom_ids = $input_data['symptom_ids'] ?? [];

    $symptom_names = []; // Array untuk menyimpan nama-nama gejala yang ditemukan

    // Pastikan $symptom_ids adalah array dan tidak kosong
    if (!empty($symptom_ids) && is_array($symptom_ids)) {
        // Buat placeholder untuk prepared statement secara dinamis
        // Contoh: jika ada 3 ID, akan menjadi '?, ?, ?'
        $placeholders = implode(',', array_fill(0, count($symptom_ids), '?'));
        
        // Buat string tipe data untuk bind_param (semuanya 's' karena ID gejala adalah string/VARCHAR)
        // Contoh: jika ada 3 ID, akan menjadi 'sss'
        $types = str_repeat('s', count($symptom_ids));

        // Query SQL untuk mengambil nama_gejala berdasarkan ID yang ada di array
        $sql = "SELECT nama_gejala FROM symptoms WHERE id IN ($placeholders)";
        
        // Siapkan prepared statement
        if ($stmt = $conn->prepare($sql)) {
            // Bind parameter secara dinamis.
            // Parameter pertama bind_param adalah string tipe data ($types),
            // kemudian diikuti oleh setiap elemen dari array $symptom_ids sebagai parameter terpisah.
            $stmt->bind_param($types, ...$symptom_ids);
            
            // Jalankan statement
            $stmt->execute();
            
            // Ambil hasilnya
            $result = $stmt->get_result();

            // Ambil setiap baris hasil dan tambahkan nama_gejala ke array $symptom_names
            while ($row = $result->fetch_assoc()) {
                $symptom_names[] = $row['nama_gejala'];
            }
            
            // Tutup statement
            $stmt->close();
        } else {
            // Jika persiapan statement gagal, log error dan kirim respons error
            error_log("Error preparing get_symptom_names_by_ids query: " . $conn->error);
            echo json_encode(['status' => 'error', 'message' => 'Failed to prepare query.']);
            $conn->close();
            exit();
        }
    }

    // Tutup koneksi database
    $conn->close();

    // Kirim respons sukses dengan daftar nama gejala
    echo json_encode(['status' => 'success', 'symptom_names' => $symptom_names]);
    exit();

} else {
    // Jika request bukan POST atau content type bukan JSON, kirim respons error
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method or content type.']);
    exit();
}
?>