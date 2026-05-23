<?php
session_start();
include 'db.php'; 

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode request tidak valid.']);
    exit();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'pengguna') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak. Silakan login.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Gagal mem-parsing data JSON.']);
    exit();
}

$id_soal = filter_var($data['id_soal'] ?? null, FILTER_VALIDATE_INT);
$id_pengajar = filter_var($data['id_pengajar'] ?? null, FILTER_VALIDATE_INT);
$rating = filter_var($data['rating'] ?? null, FILTER_VALIDATE_INT);
$review = isset($data['review']) ? trim(htmlspecialchars($data['review'])) : null;
if (empty($review)) {
    $review = null;
}
$user_id = $_SESSION['user_id'];

if (!$id_soal || !$id_pengajar || !$rating || $rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Data yang dikirim tidak lengkap atau tidak valid.']);
    exit();
}

$conn->begin_transaction();

try {
    $sql_update_tugas = "UPDATE tugas_soal SET rating_diberikan = ?, review_text = ? WHERE id_soal = ? AND id_pengguna = ? AND rating_diberikan IS NULL";
    $stmt_update_tugas = $conn->prepare($sql_update_tugas);
    if ($stmt_update_tugas === false) {
        throw new Exception("SQL Error (prepare update tugas): " . $conn->error);
    }
    $stmt_update_tugas->bind_param("isii", $rating, $review, $id_soal, $user_id);
    $stmt_update_tugas->execute();

    if ($stmt_update_tugas->affected_rows === 0) {
        throw new Exception("Gagal memperbarui rating. Anda mungkin sudah pernah memberikan rating untuk tugas ini.");
    }
    $stmt_update_tugas->close();

    $sql_recalculate_rating = "
        SELECT AVG(rating_diberikan) AS rating_rata_rata
        FROM tugas_soal
        WHERE id_pengajar = ? AND rating_diberikan IS NOT NULL
    ";
    $stmt_recalculate = $conn->prepare($sql_recalculate_rating);
    if ($stmt_recalculate === false) {
        throw new Exception("SQL Error (prepare recalculate rating): " . $conn->error);
    }
    $stmt_recalculate->bind_param("i", $id_pengajar);
    $stmt_recalculate->execute();
    $result_recalculate = $stmt_recalculate->get_result();
    $row = $result_recalculate->fetch_assoc();
    $rating_rata_rata_baru = $row['rating_rata_rata'] ?? 0;
    $stmt_recalculate->close();

    $sql_update_pengajar = "UPDATE pengajar SET rating = ? WHERE id_pengajar = ?";
    $stmt_update_pengajar = $conn->prepare($sql_update_pengajar);
    if ($stmt_update_pengajar === false) {
        throw new Exception("SQL Error (prepare update pengajar): " . $conn->error);
    }
    $stmt_update_pengajar->bind_param("di", $rating_rata_rata_baru, $id_pengajar);
    $stmt_update_pengajar->execute();
    $stmt_update_pengajar->close();

    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Terima kasih! Rating dan ulasan Anda telah disimpan.']);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>