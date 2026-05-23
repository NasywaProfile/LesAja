<?php
session_start();

header('Content-Type: application/json');

include 'db.php'; 

function send_json_response($success, $message, $data = []) {
    $response = ['success' => $success, 'message' => $message];
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(false, 'Metode request tidak valid. Harus POST.');
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'pengguna') {
    http_response_code(401); 
    send_json_response(false, 'Akses ditolak. Anda harus login sebagai pengguna.');
}

$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    send_json_response(false, 'Data JSON yang dikirim tidak valid.');
}

$id_soal = $data['id_soal'] ?? null;
$id_pengajar = $data['id_pengajar'] ?? null;
$rating = $data['rating'] ?? null;
$user_id = $_SESSION['user_id'];

if (empty($id_soal) || empty($id_pengajar) || empty($rating)) {
    send_json_response(false, 'Data tidak lengkap. id_soal, id_pengajar, dan rating diperlukan.');
}

$id_soal = filter_var($id_soal, FILTER_VALIDATE_INT);
$id_pengajar = filter_var($id_pengajar, FILTER_VALIDATE_INT);
$rating = filter_var($rating, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);

if ($id_soal === false || $id_pengajar === false || $rating === false) {
    send_json_response(false, 'Tipe data tidak valid. Pastikan ID adalah angka dan rating antara 1-5.');
}

$conn->begin_transaction();

try {
    $sql_update_tugas = "UPDATE tugas_soal SET rating_diberikan = ? WHERE id_soal = ? AND id_pengguna = ?";
    $stmt_tugas = $conn->prepare($sql_update_tugas);
    if (!$stmt_tugas) {
        throw new Exception("Gagal menyiapkan statement update tugas: " . $conn->error);
    }
    $stmt_tugas->bind_param("iii", $rating, $id_soal, $user_id);
    $stmt_tugas->execute();

    if ($stmt_tugas->affected_rows === 0) {
        throw new Exception("Gagal memperbarui rating. Tugas tidak ditemukan atau bukan milik Anda.");
    }
    $stmt_tugas->close();

    $sql_avg_rating = "SELECT AVG(rating_diberikan) as avg_rating FROM tugas_soal WHERE id_pengajar = ? AND rating_diberikan IS NOT NULL";
    $stmt_avg = $conn->prepare($sql_avg_rating);
    if (!$stmt_avg) {
        throw new Exception("Gagal menyiapkan statement rata-rata rating: " . $conn->error);
    }
    $stmt_avg->bind_param("i", $id_pengajar);
    $stmt_avg->execute();
    $result_avg = $stmt_avg->get_result();
    $new_avg_rating = $result_avg->fetch_assoc()['avg_rating'];
    $stmt_avg->close();
    
    $sql_update_pengajar = "UPDATE pengajar SET rating = ? WHERE id_pengajar = ?";
    $stmt_pengajar = $conn->prepare($sql_update_pengajar);
    if (!$stmt_pengajar) {
        throw new Exception("Gagal menyiapkan statement update pengajar: " . $conn->error);
    }
    $stmt_pengajar->bind_param("di", $new_avg_rating, $id_pengajar);
    $stmt_pengajar->execute();
    $stmt_pengajar->close();

    $conn->commit();
    send_json_response(true, "Penilaian berhasil dikirim. Terima kasih!");

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    send_json_response(false, "Terjadi kesalahan pada database: " . $e->getMessage());
}

$conn->close();
?>