<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'pengguna') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

$sql = "SELECT ts.id_soal, ts.soal, ts.jawaban, ts.foto_tugas, p.nama_pengajar, p.no, ts.id_pengajar, ts.rating_diberikan
        FROM tugas_soal ts
        LEFT JOIN pengajar p ON ts.id_pengajar = p.id_pengajar
        WHERE ts.id_pengguna = ? 
        AND ts.jawaban IS NOT NULL AND ts.jawaban != '' 
        AND ts.id_pengajar IS NOT NULL 
        AND ts.rating_diberikan IS NULL
        ORDER BY ts.tanggal_dijawab DESC, ts.tanggal_kirim DESC
        LIMIT 1 OFFSET ?";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("ii", $user_id, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($task = $result->fetch_assoc()) {
        if (!empty($task['foto_tugas']) && !file_exists($task['foto_tugas'])) {
            $task['foto_tugas'] = ''; 
        }
        echo json_encode(['success' => true, 'task' => $task]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Tidak ada tugas lagi.']);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal mempersiapkan statement SQL.']);
}

$conn->close();
?>