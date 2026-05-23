<?php
session_start();

include 'db.php'; 

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'pengguna') {
    header("Location: login.php?error=access_denied_konfirmasi");
    exit();
}
$id_pengguna_session = $_SESSION['user_id'];
$nama_pengguna_session = $_SESSION['nama'] ?? 'Siswa'; 

if (!isset($_GET['id_pengajar']) || !filter_var($_GET['id_pengajar'], FILTER_VALIDATE_INT) || $_GET['id_pengajar'] <= 0) {
    header("Location: dashboardpengguna.php?error=invalid_teacher_id");
    exit();
}
$id_pengajar_target = (int)$_GET['id_pengajar'];

$teacher_details = null;
$koneksi_database_berhasil = false;

try {
    if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
        throw new Exception("Koneksi ke basis data gagal.");
    }
    $koneksi_database_berhasil = true; 

    $sql = "SELECT nama_pengajar, foto_profil, riwayat_pendidikan, lokasi, no, keahlian, jenjang_keahlian, harga_layanan, rating
            FROM pengajar
            WHERE id_pengajar = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $id_pengajar_target);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $teacher_details = $result->fetch_assoc();
        }
        $stmt->close();
    } else {
        $_SESSION['error_message_konfirmasi'] = "Terjadi kesalahan saat mengambil data pengajar. Silakan coba lagi.";
        error_log("Gagal menyiapkan statement untuk detail pengajar (konfirmasi): " . $conn->error);
    }

} catch (Exception $e) {
    $_SESSION['error_message_konfirmasi'] = "Terjadi kesalahan sistem: " . $e->getMessage();
    error_log("Error di konfirmasi.php (ambil detail pengajar): " . $e->getMessage());
} finally {
    if ($koneksi_database_berhasil && isset($conn) && $conn instanceof mysqli && $conn->ping()) {
        $conn->close();
    }
}

if ($teacher_details === null) {
    if (!isset($_SESSION['error_message_konfirmasi'])) {
        $_SESSION['error_message_konfirmasi'] = "Pengajar yang Anda cari tidak ditemukan.";
    }
}

if ($teacher_details !== null) {
    if (!function_exists('tampilkan_harga_konfirmasi')) {
        function tampilkan_harga_konfirmasi($harga, $placeholder = 'N/A') {
            if ($harga !== null && is_numeric($harga)) {
                return 'Rp ' . number_format($harga, 0, ',', '.');
            }
            return $placeholder;
        }
    }

    $nama_tampil_konfirmasi = htmlspecialchars($teacher_details['nama_pengajar'] ?? 'Informasi Tidak Tersedia', ENT_QUOTES, 'UTF-8');
    $foto_tampil_konfirmasi = 'images/default_teacher_avatar.png'; 
    if (!empty($teacher_details['foto_profil'])) {
        $base_upload_path = 'uploads/profile_pictures/';
        $full_path_foto = $base_upload_path . $teacher_details['foto_profil'];

        if (file_exists($full_path_foto)) {
            $foto_tampil_konfirmasi = htmlspecialchars($full_path_foto, ENT_QUOTES, 'UTF-8') . '?t=' . time();
        } elseif (file_exists($teacher_details['foto_profil'])) {
            $foto_tampil_konfirmasi = htmlspecialchars($teacher_details['foto_profil'], ENT_QUOTES, 'UTF-8') . '?t=' . time();
        }
    }

    $riwayat_pendidikan_tampil_konfirmasi = !empty($teacher_details['riwayat_pendidikan']) ? nl2br(htmlspecialchars($teacher_details['riwayat_pendidikan'], ENT_QUOTES, 'UTF-8')) : 'Riwayat pendidikan belum diisi.';
    $lokasi_tampil_konfirmasi = !empty($teacher_details['lokasi']) ? nl2br(htmlspecialchars($teacher_details['lokasi'], ENT_QUOTES, 'UTF-8')) : 'Lokasi mengajar belum diisi.';
    $no_hp_tampil_konfirmasi = htmlspecialchars($teacher_details['no'] ?? 'Belum diisi', ENT_QUOTES, 'UTF-8');
    $keahlian_tampil_konfirmasi = htmlspecialchars($teacher_details['keahlian'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $jenjang_tampil_konfirmasi = htmlspecialchars($teacher_details['jenjang_keahlian'] ?? '', ENT_QUOTES, 'UTF-8');
    $harga_tampil_konfirmasi = tampilkan_harga_konfirmasi($teacher_details['harga_layanan']);

    $rating_pengajar_konfirmasi = (float)($teacher_details['rating'] ?? 0);
    $rating_bintang_penuh_konfirmasi = floor($rating_pengajar_konfirmasi);
    $rating_html_output = '';
    $no_rating_class_konfirmasi = ($rating_pengajar_konfirmasi == 0) ? 'no-rating' : '';
    for ($i = 1; $i <= 5; $i++) {
        $is_active = ($i <= $rating_bintang_penuh_konfirmasi && $rating_pengajar_konfirmasi > 0) ? 'active' : '';
        $rating_html_output .= '<img src="images/star.svg" alt="Bintang" class="star-icon-img ' . $is_active . '">';
    }

} else {
    $nama_tampil_konfirmasi = 'Pengajar Tidak Tersedia';
    $foto_tampil_konfirmasi = 'images/default_teacher_avatar.png';
    $riwayat_pendidikan_tampil_konfirmasi = 'N/A';
    $lokasi_tampil_konfirmasi = 'N/A';
    $no_hp_tampil_konfirmasi = 'N/A';
    $keahlian_tampil_konfirmasi = 'N/A';
    $jenjang_tampil_konfirmasi = '';
    $harga_tampil_konfirmasi = 'N/A';
    $rating_html_output = '<span style="color: #888;">Belum ada rating</span>'; 
    $no_rating_class_konfirmasi = 'no-rating';
}

$form_action_konfirmasi = "proses_booking.php";
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet" />
    <title>Konfirmasi Pemesanan - <?php echo $nama_tampil_konfirmasi; ?></title>
    <style>
        html,body { position: relative; min-height: 100vh; margin: 0; font-family: "Roboto", sans-serif; background: #fcfaf8; color: #2c3e50; overflow-y: auto; overflow-x: hidden; }
        
        .back-button {
            position: absolute;
            top: 18px;
            left: 18px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            background-color: #ffffff;
            color: #213555;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            border-radius: 20px;
            border: 1px solid #e9e3d9;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: all 0.2s ease-in-out;
            z-index: 10;
        }
        .back-button:hover {
            background-color: #f8f5f0;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .back-button svg {
            width: 18px;
            height: 18px;
        }

        .page-container { width: 100%; display: flex; flex-direction: column; padding: 18px; box-sizing: border-box; min-height: calc(100vh - 36px); }
        .content-wrapper { width: 100%; max-width: 1100px; display: flex; flex-direction: column; gap: 15px; margin: auto; flex-grow: 1; min-height: 0; padding-top: 40px; }
        .section-title-header { width: 100%; max-width: 768px; margin: 0 auto; display: flex; flex-direction: column; align-items: center; gap: 8px; text-align: center; flex-shrink: 0; padding-bottom: 10px; }
        .section-title-header .heading { font-size: 28px; font-weight: 700; line-height: 1.2; color: #213555; }
        .section-title-header .sub-heading { font-size: 13.5px; font-weight: 400; line-height: 1.4; color: #586a7e; max-width: 600px; }
        .main-content-area { width: 100%; display: flex; flex-direction: row; gap: 20px; align-items: stretch; flex-grow: 1; min-height: 0; }
        .profile-image-column { flex: 1 1 calc(50% - 10px); max-width: calc(50% - 10px); }
        .profile-image-column img { width: 100%; height: 100%; object-fit: cover; object-position: center 20%; border-radius: 12px; border: 1px solid #e8e2d9; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06); }
        .right-info-group-column { flex: 1 1 calc(50% - 10px); max-width: calc(50% - 10px); display: flex; flex-direction: row; gap: 15px; }
        .info-section { display: flex; flex-direction: column; gap: 8px; flex: 1 1 calc(50% - 7.5px); max-width: calc(50% - 7.5px); }
        .section-label { font-size: 15px; font-weight: 600; color: #3e5062; margin-bottom: 4px; }
        .info-section .info-card, .info-section .detail-item-container { display: flex; flex-direction: column; flex-grow: 1; overflow: hidden; min-height: 0; }
        .teacher-bio-card .card-text-block { background: #f8f5f0; border-radius: 10px; padding: 12px; border: 1px solid #e9e3d9; display: flex; flex-direction: column; flex-grow: 1; min-height: 0; overflow: hidden; }
        .info-card .card-text { font-size: 12.5px; line-height: 1.5; color: #4a5c6e; flex-grow: 1; overflow-y: auto; padding-right: 5px; }
        .info-card .card-text strong { font-weight: 500; color: #2c3e50; }
        .teacher-bio-card .contact-info { background: #f8f5f0; border-radius: 10px; padding: 8px 12px; border: 1px solid #e9e3d9; margin-top: 8px; display: flex; align-items: center; gap: 8px; }
        .contact-info .icon-img { width: 14px; height: 14px; }
        .contact-info .phone-number { font-size: 12.5px; font-weight: 500; color: #213555; }
        .booking-details-section .detail-item-container { justify-content: flex-start; }
        .detail-item { margin-bottom: 8px; }
        .detail-item .item-label { font-size: 12px; color: #586a7e; margin-bottom: 3px; }
        .detail-item .item-value-box { background: #f8f5f0; border-radius: 8px; padding: 9px 10px; display: flex; align-items: center; border: 1px solid #e9e3d9; }
        .detail-item .item-value-text { font-size: 12.5px; font-weight: 500; color: #344e6f; width: 100%; }
        .item-value-box input { width: 100%; border: none; background: transparent; font-family: "Roboto", sans-serif; font-size: 12.5px; font-weight: 500; color: #344e6f; outline: none; padding: 0; }
        .rating-stars { display: flex; gap: 3px; align-items: center; }
        .rating-stars .star-icon-img { width: 15px; height: 15px; filter: grayscale(100%); }
        .rating-stars .star-icon-img.active { filter: grayscale(0%); }
        .rating-stars.no-rating .star-icon-img { opacity: 0.7; }
        .confirmation-button-container { width: 100%; display: flex; justify-content: center; padding: 10px 0; margin-top: 10px; }
        .confirmation-button { background: #213555; border-radius: 20px; min-width: 220px; padding: 10px 20px; cursor: pointer; border: none; transition: all 0.2s ease; box-shadow: 0 3px 8px rgba(33, 53, 85, 0.15); }
        .confirmation-button:hover { background: #2c4670; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(33, 53, 85, 0.2); }
        .confirmation-button .button-label { color: #fcfcfc; font-size: 13px; font-weight: 500; }
        .message-box { text-align: center; padding: 12px; border-radius: 5px; margin-bottom: 15px; }
        .error-box { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; }
        .success-box { color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); display: none; align-items: center; justify-content: center; z-index: 1000; backdrop-filter: blur(5px); }
        .modal-container { background: #FFFFFF; border-radius: 16px; box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12); padding: 24px; width: 90%; max-width: 400px; text-align: center; position: relative; display: flex; flex-direction: column; align-items: center; gap: 16px; }
        .modal-close-btn { position: absolute; top: 16px; right: 16px; background: none; border: none; font-size: 24px; color: #888; cursor: pointer; }
        .modal-icon-wrapper { width: 72px; height: 72px; border-radius: 50%; background-color: #e8f5e9; display: flex; align-items: center; justify-content: center; }
        .modal-icon-wrapper svg { width: 40px; height: 40px; color: #4CAF50; }
        .modal-title { font-size: 24px; font-weight: 700; color: #213555; }
        .modal-message { font-size: 14px; color: #586a7e; }
        .modal-button { background: #213555; color: #FFFFFF; border: none; border-radius: 25px; padding: 12px 24px; font-size: 16px; font-weight: 500; cursor: pointer; width: 100%; }
        
        @media (max-width: 768px) {
            .back-button { top: 10px; left: 10px; padding: 6px 10px; font-size: 12px; }
            .content-wrapper { padding-top: 50px; }
            .main-content-area { flex-direction: column; }
            .profile-image-column, .right-info-group-column { max-width: 100%; }
            .right-info-group-column { flex-direction: column; }
            .info-section { max-width: 100%;}
        }
    </style>
</head>
<body>
    <div class="page-container">
        <a href="dashboardpengguna.php" class="back-button">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
            </svg>
            <span>Kembali</span>
        </a>

        <div class="content-wrapper">
            <div class="section-title-header">
                <span class="heading">Konfirmasi Pemesanan</span>
                <?php if ($teacher_details !== null): ?>
                <span class="sub-heading">
                    Anda memilih pengajar <strong><?php echo $nama_tampil_konfirmasi; ?></strong>. Mohon periksa kembali jadwal pertemuan Anda.
                </span>
                <?php endif; ?>
            </div>

            <div id="ajaxErrorContainer" style="display: none;"></div>

            <?php if (isset($_SESSION['error_message_konfirmasi'])): ?>
                <div class="message-box error-box">
                    <?php echo htmlspecialchars($_SESSION['error_message_konfirmasi']); unset($_SESSION['error_message_konfirmasi']); ?>
                </div>
            <?php endif; ?>

            <?php if ($teacher_details !== null): ?>
            <div class="main-content-area">
                <div class="profile-image-column">
                    <img src="<?php echo $foto_tampil_konfirmasi; ?>" alt="Foto <?php echo $nama_tampil_konfirmasi; ?>" />
                </div>

                <div class="right-info-group-column">
                    <div class="info-section teacher-details-section">
                        <span class="section-label">Tentang Pengajar</span>
                        <div class="info-card teacher-bio-card">
                            <div class="card-text-block">
                                <span class="card-text">
                                    <strong><?php echo $nama_tampil_konfirmasi; ?></strong><br /><br />
                                    <strong>Riwayat Pendidikan:</strong><br />
                                    <?php echo $riwayat_pendidikan_tampil_konfirmasi; ?><br /><br />
                                    <strong>Area Mengajar Utama:</strong><br />
                                    <?php echo $lokasi_tampil_konfirmasi; ?>
                                </span>
                                <div class="contact-info">
                                    <img src="images/wa.png" alt="WhatsApp" class="icon-img"> <span class="phone-number"><?php echo $no_hp_tampil_konfirmasi; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="info-section booking-details-section">
                        <span class="section-label">Detail Layanan & Jadwal</span>
                        <div class="detail-item-container">
                               <form id="bookingFormKonfirmasi" action="<?php echo htmlspecialchars($form_action_konfirmasi, ENT_QUOTES, 'UTF-8'); ?>" method="POST">
                                    <input type="hidden" name="id_pengajar" value="<?php echo htmlspecialchars($id_pengajar_target, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="id_pengguna" value="<?php echo htmlspecialchars($id_pengguna_session, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="harga_sesi" value="<?php echo htmlspecialchars($teacher_details['harga_layanan'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="nama_pengajar_les" value="<?php echo $nama_tampil_konfirmasi; ?>">
                                    <input type="hidden" name="nama_pemesan_les" value="<?php echo htmlspecialchars($nama_pengguna_session, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="keahlian_les" value="<?php echo $keahlian_tampil_konfirmasi; ?>">
                                    <input type="hidden" name="jenjang_les" value="<?php echo $jenjang_tampil_konfirmasi; ?>">
                                    <input type="hidden" name="lokasi_les_diajukan" value="<?php echo htmlspecialchars($teacher_details['lokasi'] ?? 'Akan dikonfirmasi', ENT_QUOTES, 'UTF-8'); ?>">
                                    
                                    <div class="detail-item">
                                        <span class="item-label">Nama Pengajar</span>
                                        <div class="item-value-box"> <span class="item-value-text"><?php echo $nama_tampil_konfirmasi; ?></span> </div>
                                    </div>
                                    <div class="detail-item">
                                        <span class="item-label">Jenjang / Keahlian</span>
                                        <div class="item-value-box"> <span class="item-value-text"><?php echo $jenjang_tampil_konfirmasi; ?> / <?php echo $keahlian_tampil_konfirmasi; ?></span> </div>
                                    </div>
                                    <div class="detail-item">
                                        <span class="item-label">Harga per Sesi</span>
                                        <div class="item-value-box"> <span class="item-value-text"><?php echo $harga_tampil_konfirmasi; ?></span> </div>
                                    </div>
                                    <div class="detail-item">
                                        <span class="item-label">Rating Pengajar</span>
                                        <div class="item-value-box rating-stars <?php echo $no_rating_class_konfirmasi; ?>">
                                            <?php echo $rating_html_output; ?>
                                        </div>
                                    </div>
                                    <div class="detail-item">
                                        <span class="item-label">Atur Tanggal Pertemuan</span>
                                        <div class="item-value-box">
                                            <input type="date" name="tanggal_les_diajukan" required min="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                    </div>
                                    <div class="detail-item">
                                        <span class="item-label">Atur Jam Pertemuan</span>
                                        <div class="item-value-box">
                                            <input type="time" name="jam_les_diajukan" required>
                                        </div>
                                    </div>
                                </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="confirmation-button-container">
                <button class="confirmation-button" type="submit" form="bookingFormKonfirmasi">
                    <span class="button-label">Ajukan Jadwal & Pesan Sekarang</span>
                </button>
            </div>
            <?php else: ?>
                <div class="main-content-area" style="justify-content: center; text-align:center; flex-grow:1;">
                    <p>Informasi pengajar tidak dapat dimuat. Silakan periksa pesan di atas atau kembali ke halaman sebelumnya.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="successModal" class="modal-overlay">
        <div class="modal-container">
            <button id="closeModalBtn" class="modal-close-btn">&times;</button>
            <div class="modal-icon-wrapper">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
            </div>
            <h2 class="modal-title">Pemesanan Berhasil</h2>
            <p class="modal-message">Jawaban telah berhasil dikirim. Silakan cek kembali jika diperlukan.</p>
            <button id="modalSelesaiBtn" class="modal-button">Selesai</button>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const bookingForm = document.getElementById('bookingFormKonfirmasi');
        const successModal = document.getElementById('successModal');
        const closeModalBtn = document.getElementById('closeModalBtn');
        const modalSelesaiBtn = document.getElementById('modalSelesaiBtn');
        const ajaxErrorContainer = document.getElementById('ajaxErrorContainer');

        if (bookingForm) {
            bookingForm.addEventListener('submit', function(event) {
                event.preventDefault(); 
                ajaxErrorContainer.style.display = 'none';
                ajaxErrorContainer.innerHTML = '';
                const formData = new FormData(bookingForm);
                const actionUrl = bookingForm.getAttribute('action');

                fetch(actionUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success') {
                        successModal.style.display = 'flex';
                    } else {
                        ajaxErrorContainer.className = 'message-box error-box';
                        ajaxErrorContainer.innerHTML = '<strong>Gagal:</strong> ' + (data.message || 'Terjadi kesalahan.');
                        ajaxErrorContainer.style.display = 'block';
                        window.scrollTo(0, 0); 
                    }
                })
                .catch(error => {
                    let userMessage = '<strong>Error:</strong> Tidak dapat memproses permintaan. ';
                    if (error instanceof SyntaxError) {
                        userMessage += 'Respons dari server tidak valid. Hubungi administrator.';
                    } else {
                        userMessage += 'Gagal terhubung ke server. Periksa koneksi internet Anda.';
                    }
                    ajaxErrorContainer.className = 'message-box error-box';
                    ajaxErrorContainer.innerHTML = userMessage;
                    ajaxErrorContainer.style.display = 'block';
                    window.scrollTo(0, 0);
                });
            });
        }
        
        function hideModal() {
            successModal.style.display = 'none';
        }

        function handleSelesai() {
            hideModal();
        }

        if (closeModalBtn) closeModalBtn.addEventListener('click', hideModal);
        if (modalSelesaiBtn) modalSelesaiBtn.addEventListener('click', handleSelesai);
        if (successModal) {
            successModal.addEventListener('click', function(event) {
                if (event.target === successModal) {
                    hideModal();
                }
            });
        }
    });
    </script>

</body>
</html>