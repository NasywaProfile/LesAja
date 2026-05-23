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

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'pengajar') {
    $response['message'] = 'Akses ditolak. Sesi Anda tidak valid.';
    echo json_encode($response);
    exit();
}

$id_pengajar = $_SESSION['user_id'];
$id_soal = $_POST['id_soal'] ?? 0;
$answer_text = $_POST['answer_text'] ?? '';

if (empty($id_soal) || empty($answer_text)) {
    $response['message'] = 'ID Soal atau teks jawaban tidak boleh kosong.';
    echo json_encode($response);
    exit();
}

try {
    if (!$conn) {
        throw new Exception("Koneksi database gagal.");
    }

    $sql = "UPDATE tugas_soal 
            SET jawaban = ?, id_pengajar = ?, tanggal_dijawab = NOW() 
            WHERE id_soal = ? AND id_pengajar IS NULL";
            
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Gagal menyiapkan statement: " . $conn->error);

    $stmt->bind_param("sii", $answer_text, $id_pengajar, $id_soal);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = 'Jawaban Anda telah berhasil dikirim!';
        } else {
            $response['message'] = 'Maaf, tugas ini sudah dijawab oleh pengajar lain.';
        }
    } else {
        throw new Exception("Gagal menyimpan jawaban: " . $stmt->error);
    }
    
    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("Error di submit_answer.php: " . $e->getMessage());
}

echo json_encode($response);
exit();
?>