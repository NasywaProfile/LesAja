<?php
session_start();
header('Content-Type: application/json');
include 'db.php'; 

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $response['message'] = 'Invalid JSON input.';
        echo json_encode($response);
        exit();
    }

    $id_les = $data['id_les'] ?? null;
    $id_pengajar = $data['id_pengajar'] ?? null;
    $rating = $data['rating'] ?? null;
    $review = $data['review'] ?? null;
    $id_pengguna_session = $_SESSION['user_id'] ?? null;

    if (!$id_les || !$id_pengajar || !is_numeric($rating) || $rating < 1 || $rating > 5 || !$id_pengguna_session) {
        $response['message'] = 'Data tidak lengkap atau tidak valid.';
        error_log("Data rating tidak lengkap/valid: id_les={$id_les}, id_pengajar={$id_pengajar}, rating={$rating}, id_pengguna_session={$id_pengguna_session}");
        echo json_encode($response);
        exit();
    }

    try {
        if (!isset($conn) || !is_object($conn) || $conn->connect_error) {
            throw new Exception("Koneksi ke basis data gagal.");
        }

        $conn->begin_transaction();

        $sql_check_rating = "SELECT COUNT(*) FROM rating_review WHERE id_les = ? AND id_pengguna = ?";
        $stmt_check = $conn->prepare($sql_check_rating);
        $stmt_check->bind_param("ii", $id_les, $id_pengguna_session);
        $stmt_check->execute();
        $stmt_check->bind_result($count);
        $stmt_check->fetch();
        $stmt_check->close();

        if ($count > 0) {
            $sql_insert_rating = "UPDATE rating_review SET rating = ?, review = ?, updated_at = CURRENT_TIMESTAMP WHERE id_les = ? AND id_pengguna = ?";
            $stmt_insert = $conn->prepare($sql_insert_rating);
            $stmt_insert->bind_param("disi", $rating, $review, $id_les, $id_pengguna_session);
            $response['message'] = 'Rating berhasil diperbarui.';
        } else {
            $sql_insert_rating = "INSERT INTO rating_review (id_les, id_pengguna, rating, review) VALUES (?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert_rating);
            $stmt_insert->bind_param("iids", $id_les, $id_pengguna_session, $rating, $review);
            $response['message'] = 'Rating berhasil disimpan.';
        }
        
        if (!$stmt_insert->execute()) {
            throw new Exception("Gagal menyimpan/memperbarui rating. Error: " . $stmt_insert->error);
        }
        $stmt_insert->close();

        $sql_update_avg_rating_corrected = "
            UPDATE pengajar
            SET rating = (
                SELECT AVG(rr.rating)
                FROM rating_review rr
                JOIN permintaan_les pl ON rr.id_les = pl.id_permintaan
                WHERE pl.id_pengajar = ?
            )
            WHERE id_pengajar = ?
        ";

        $stmt_update_avg = $conn->prepare($sql_update_avg_rating_corrected);
        $stmt_update_avg->bind_param("ii", $id_pengajar, $id_pengajar);
        if (!$stmt_update_avg->execute()) {
            throw new Exception("Gagal memperbarui rata-rata rating pengajar. Error: " . $stmt_update_avg->error);
        }
        $stmt_update_avg->close();
        
        $conn->commit();
        $response['success'] = true;

    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        error_log("Error pada rating.php: " . $e->getMessage());
    } finally {
        if (isset($conn) && is_object($conn) && !$conn->connect_error && $conn->ping()) {
            $conn->close();
        }
    }
} else {
    $response['message'] = 'Metode request tidak diizinkan.';
}

echo json_encode($response);
?>