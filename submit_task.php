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
    send_json_response(false, 'Metode request tidak valid.');
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pengguna') {
    http_response_code(401);
    send_json_response(false, 'Akses ditolak. Silakan login terlebih dahulu.');
}

$user_id = $_SESSION['user_id'];
$soal_text = $_POST['soal_text'] ?? '';
$foto_path = null;

if (empty($soal_text) && (!isset($_FILES['task_image']) || $_FILES['task_image']['error'] !== UPLOAD_ERR_OK)) {
    send_json_response(false, 'Harap isi pertanyaan atau unggah gambar tugas.');
}

if (isset($_FILES['task_image']) && $_FILES['task_image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'uploads/task_images/';
    
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_info = pathinfo($_FILES['task_image']['name']);
    $file_ext = strtolower($file_info['extension']);
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];

    if (!in_array($file_ext, $allowed_ext)) {
        send_json_response(false, 'Format file tidak diizinkan. Hanya JPG, PNG, GIF yang diperbolehkan.');
    }

    $unique_name = uniqid('task-', true) . '.' . $file_ext;
    $target_file = $upload_dir . $unique_name;

    if (move_uploaded_file($_FILES['task_image']['tmp_name'], $target_file)) {
        $foto_path = $target_file;
    } else {
        send_json_response(false, 'Gagal memindahkan file yang diunggah.');
    }
}

try {
    $sql = "INSERT INTO tugas_soal (id_pengguna, soal, foto_tugas, tanggal_kirim) VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Gagal menyiapkan statement: " . $conn->error);
    }
    
    $stmt->bind_param("iss", $user_id, $soal_text, $foto_path);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        send_json_response(true, 'Tugas berhasil dikirim!');
    } else {
        throw new Exception("Gagal menyimpan tugas ke database.");
    }

} catch (Exception $e) {
    http_response_code(500);
    send_json_response(false, 'Terjadi kesalahan pada server: ' . $e->getMessage());
}
?>