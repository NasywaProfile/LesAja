<?php
session_start();
include 'db.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Terjadi kesalahan yang tidak diketahui.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Metode request tidak valid.';
    echo json_encode($response);
    exit();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pengguna') {
    $response['message'] = 'Akses ditolak. Anda harus login sebagai pengguna.';
    echo json_encode($response);
    exit();
}
$id_pengguna_session = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$id_les = $data['id_les'] ?? 0;
$id_pengajar = $data['id_pengajar'] ?? 0;
$rating = $data['rating'] ?? 0;
$review = $data['review'] ?? '';

if (empty($id_les) || empty($id_pengajar) || empty($rating)) {
    $response['message'] = 'Data tidak lengkap. ID Les, ID Pengajar, dan Rating wajib diisi.';
    echo json_encode($response);
    exit();
}

try {
    if (!$conn) throw new Exception("Koneksi database gagal.");

    $conn->begin_transaction();

    $sql_update_status = "UPDATE permintaan_les SET status_permintaan = 'selesai' WHERE id_permintaan = ? AND id_pengguna = ?";
    $stmt_status = $conn->prepare($sql_update_status);
    if (!$stmt_status) throw new Exception("Gagal prepare status: " . $conn->error);
    $stmt_status->bind_param("ii", $id_les, $id_pengguna_session);
    if (!$stmt_status->execute()) throw new Exception("Gagal update status: " . $stmt_status->error);
    $stmt_status->close();

    $sql_insert_rating = "INSERT INTO rating_review (id_les, id_pengguna, id_pengajar, rating, review) VALUES (?, ?, ?, ?, ?)";
    $stmt_rating = $conn->prepare($sql_insert_rating);
    if (!$stmt_rating) throw new Exception("Gagal prepare rating: " . $conn->error);
    $stmt_rating->bind_param("iiids", $id_les, $id_pengguna_session, $id_pengajar, $rating, $review);
    if (!$stmt_rating->execute()) throw new Exception("Gagal insert rating: " . $stmt_rating->error);
    $stmt_rating->close();

    $sql_get_avg = "SELECT AVG(rating) as avg_rating FROM rating_review WHERE id_pengajar = ?";
    $stmt_get = $conn->prepare($sql_get_avg);
    if (!$stmt_get) throw new Exception("Gagal prepare get_avg: " . $conn->error);
    $stmt_get->bind_param("i", $id_pengajar);
    if (!$stmt_get->execute()) throw new Exception("Gagal execute get_avg: " . $stmt_get->error);

    $result_avg = $stmt_get->get_result();
    $avg_data = $result_avg->fetch_assoc();
    $new_average_rating = $avg_data['avg_rating'];
    $stmt_get->close();

    if ($new_average_rating !== null) {
        $sql_update_pengajar = "UPDATE pengajar SET rating = ? WHERE id_pengajar = ?";
        $stmt_update = $conn->prepare($sql_update_pengajar);
        if (!$stmt_update) throw new Exception("Gagal prepare update_pengajar: " . $conn->error);
        $stmt_update->bind_param("di", $new_average_rating, $id_pengajar);
        if (!$stmt_update->execute()) throw new Exception("Gagal execute update_pengajar: " . $stmt_update->error);
        $stmt_update->close();
    }

    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Penilaian berhasil disimpan.';
} catch (Exception $e) {
    if ($conn) $conn->rollback();
    $response['message'] = $e->getMessage();
    error_log("Error di proses_penilaian.php: " . $e->getMessage());
} finally {
    if ($conn) $conn->close();
}

echo json_encode($response);
exit();
