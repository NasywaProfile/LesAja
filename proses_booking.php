<?php
session_start();
include 'db.php';

date_default_timezone_set('Asia/Jakarta');

header('Content-Type: application/json');
$response = [];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['status'] = 'error';
    $response['message'] = 'Metode request tidak valid.';
    echo json_encode($response);
    exit();
}

try {
    if ($conn->connect_error) {
        throw new Exception("Koneksi database gagal: " . $conn->connect_error);
    }

    $sql = "INSERT INTO permintaan_les (
                id_pengajar, 
                id_pengguna, 
                nama_pemesan_les, 
                nama_pengajar_les,
                keahlian_les,
                jenjang_les,
                lokasi_les_diajukan,
                tanggal_pemesanan,
                tanggal_les_diajukan, 
                jam_les_diajukan, 
                harga_saat_booking, 
                status_permintaan
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Gagal menyiapkan statement SQL: " . $conn->error);
    }

    $tanggal_pemesanan_sekarang = date('Y-m-d H:i:s');
    
    $stmt->bind_param(
        "iissssssssd",
        $_POST['id_pengajar'],
        $_POST['id_pengguna'],
        $_POST['nama_pemesan_les'],
        $_POST['nama_pengajar_les'],
        $_POST['keahlian_les'],
        $_POST['jenjang_les'],
        $_POST['lokasi_les_diajukan'],
        $tanggal_pemesanan_sekarang,
        $_POST['tanggal_les_diajukan'],
        $_POST['jam_les_diajukan'],
        $_POST['harga_sesi']
    );

    if ($stmt->execute()) {
        $response['status'] = 'success';
        $response['message'] = 'Pengajuan jadwal berhasil dikirim.';
    } else {
        throw new Exception("Gagal menjalankan statement: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
    error_log("Proses Booking Error: " . $e->getMessage());
}

echo json_encode($response);
exit();
?>