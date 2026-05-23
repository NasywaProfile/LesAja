<?php
session_start();
include 'db.php';

header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => 'Terjadi kesalahan yang tidak diketahui.'];

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'pengajar') {
    $response['message'] = 'Akses ditolak. Anda harus login sebagai pengajar.';
    echo json_encode($response);
    exit();
}
$id_pengajar_session = $_SESSION['user_id'];

if (!isset($_GET['id_permintaan']) || !isset($_GET['aksi'])) {
    $response['message'] = 'Parameter tidak lengkap.';
    echo json_encode($response);
    exit();
}

$id_permintaan = (int)$_GET['id_permintaan'];
$aksi = $_GET['aksi'];

$status_baru = '';
if ($aksi == 'terima') {
    $status_baru = 'diterima';
} elseif ($aksi == 'tolak') {
    $status_baru = 'ditolak';
} else {
    $response['message'] = 'Aksi tidak valid.';
    echo json_encode($response);
    exit();
}

if (isset($conn) && $conn instanceof mysqli) {
    $sql = "UPDATE permintaan_les SET status_permintaan = ? WHERE id_permintaan = ? AND id_pengajar = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("sii", $status_baru, $id_permintaan, $id_pengajar_session);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $response['status'] = 'success';
                $response['message'] = 'Status permintaan berhasil diperbarui.';
            } else {
                $response['message'] = 'Permintaan tidak ditemukan atau Anda tidak memiliki izin.';
            }
        } else {
            $response['message'] = 'Gagal memperbarui status: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $response['message'] = 'Gagal menyiapkan statement: ' . $conn->error;
    }
    $conn->close();
} else {
    $response['message'] = 'Koneksi database gagal.';
}

echo json_encode($response);
exit();
?>