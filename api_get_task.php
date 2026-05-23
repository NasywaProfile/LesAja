<?php
header('Content-Type: application/json');
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'pengajar') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit();
}

$offset = filter_input(INPUT_GET, 'offset', FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 0]]);

$sql_tugas = "SELECT ts.id_soal, ts.soal, ts.foto_tugas, u.nama_pengguna
              FROM tugas_soal ts
              JOIN pengguna u ON ts.id_pengguna = u.id_pengguna
              WHERE ts.jawaban IS NULL AND ts.id_pengajar IS NULL
              ORDER BY ts.tanggal_kirim ASC
              LIMIT 1 OFFSET ?";

$stmt_tugas = $conn->prepare($sql_tugas);
$stmt_tugas->bind_param("i", $offset);
$stmt_tugas->execute();
$result_tugas = $stmt_tugas->get_result();
$response = [];

if ($result_tugas && $result_tugas->num_rows > 0) {
    $tugas = $result_tugas->fetch_assoc();
    $response['success'] = true;
    $response['task'] = $tugas;
} else {
    $response['success'] = false;
    $response['message'] = 'Tidak ada tugas lagi.';
}

$stmt_tugas->close();
$conn->close();

echo json_encode($response);
?>