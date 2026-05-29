<?php
session_start();
include 'db.php'; 

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'pengajar') {
    header("Location: login.php");
    exit();
}

$id_pengajar_session = $_SESSION['user_id'];
$pesan_update_akun = "";
$pesan_update_statistik = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_edit_akun'])) {
    if (!isset($conn) || !$conn instanceof mysqli) {
        $pesan_update_akun = "Kesalahan koneksi database.";
    } else {
        $nama_pengguna_baru = $conn->real_escape_string($_POST['nama_pengguna_baru']);
        $kata_sandi_baru = $_POST['kata_sandi_baru'];

        if (empty($nama_pengguna_baru)) {
            $pesan_update_akun = "Nama pengajar tidak boleh kosong.";
        } else {
            $sql_update_akun = "UPDATE pengajar SET nama_pengajar = ?";
            $params_type = "s";
            $params_array = [$nama_pengguna_baru];

            if (!empty($kata_sandi_baru)) {
                $hashed_password = password_hash($kata_sandi_baru, PASSWORD_DEFAULT);
                $sql_update_akun .= ", kata_sandi = ?";
                $params_type .= "s";
                $params_array[] = $hashed_password;
            }

            if (isset($_FILES['foto_profil_baru']) && $_FILES['foto_profil_baru']['error'] == 0) {
                $target_dir = "uploads/profile_pictures/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }

                $imageFileType = strtolower(pathinfo($_FILES["foto_profil_baru"]["name"], PATHINFO_EXTENSION));
                $nama_file_unik = uniqid('profil_', true) . '.' . $imageFileType;
                $target_file = $target_dir . $nama_file_unik;
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

                if (in_array($imageFileType, $allowed_types)) {
                    if (move_uploaded_file($_FILES["foto_profil_baru"]["tmp_name"], $target_file)) {
                        $stmt_get_old_photo = $conn->prepare("SELECT foto_profil FROM pengajar WHERE id_pengajar = ?");
                        if ($stmt_get_old_photo) {
                            $stmt_get_old_photo->bind_param("i", $id_pengajar_session);
                            $stmt_get_old_photo->execute();
                            $result_old_photo = $stmt_get_old_photo->get_result();
                            if ($old_photo_data = $result_old_photo->fetch_assoc()) {
                                if (!empty($old_photo_data['foto_profil']) && file_exists($old_photo_data['foto_profil'])) {
                                    unlink($old_photo_data['foto_profil']);
                                }
                            }
                            $stmt_get_old_photo->close();
                        }
                        $sql_update_akun .= ", foto_profil = ?";
                        $params_type .= "s";
                        $params_array[] = $target_file;
                    } else {
                        $pesan_update_akun = "Maaf, terjadi kesalahan saat mengunggah file foto profil Anda.";
                    }
                } else {
                    $pesan_update_akun = "Format file tidak valid. Pastikan formatnya adalah JPG, PNG, atau GIF.";
                }
            }
            if (empty($pesan_update_akun)) {
                $sql_update_akun .= " WHERE id_pengajar = ?";
                $params_type .= "i";
                $params_array[] = $id_pengajar_session;
                $stmt_update_akun = $conn->prepare($sql_update_akun);
                if ($stmt_update_akun) {
                    $stmt_update_akun->bind_param($params_type, ...$params_array);
                    if ($stmt_update_akun->execute()) {
                        $pesan_update_akun = "Data diri berhasil diperbarui.";
                        if ($nama_pengguna_baru !== $_SESSION['nama']) {
                            $_SESSION['nama'] = $nama_pengguna_baru;
                        }
                    } else {
                        $pesan_update_akun = "Gagal memperbarui data diri: " . $stmt_update_akun->error;
                    }
                    $stmt_update_akun->close();
                } else {
                    $pesan_update_akun = "Gagal menyiapkan statement pembaruan data diri: " . $conn->error;
                }
            }
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_simpan_statistik'])) {
    if (!isset($conn) || !$conn instanceof mysqli) {
        $pesan_update_statistik = "Kesalahan koneksi database.";
    } else {
        $jenjang_keahlian_baru = $_POST['jenjang_keahlian_baru'] ?? null;
        $keahlian_baru = $_POST['keahlian_baru'] ?? null;
        $harga_layanan_baru = $_POST['harga_layanan_baru'] ?? null;
        $no_handphone_baru = $_POST['no_handphone_baru'] ?? null;
        $riwayat_pendidikan_baru = $_POST['riwayat_pendidikan_baru'] ?? null;
        $lokasi_baru = $_POST['lokasi_baru'] ?? null;
        $harga_layanan_numeric = null;
        if ($harga_layanan_baru !== null) {
            $harga_cleaned = str_replace(['Rp ', '.'], '', $harga_layanan_baru);
            if (is_numeric($harga_cleaned)) {
                $harga_layanan_numeric = filter_var($harga_cleaned, FILTER_SANITIZE_NUMBER_INT);
            }
        }
        $no_handphone_cleaned = preg_replace('/[^0-9]/', '', $no_handphone_baru);

        $stmt_update_statistik = $conn->prepare("UPDATE pengajar SET jenjang_keahlian = ?, keahlian = ?, harga_layanan = ?, no = ?, riwayat_pendidikan = ?, lokasi = ? WHERE id_pengajar = ?");
        if ($stmt_update_statistik) {
            $stmt_update_statistik->bind_param("ssdsssi", $jenjang_keahlian_baru, $keahlian_baru, $harga_layanan_numeric, $no_handphone_cleaned, $riwayat_pendidikan_baru, $lokasi_baru, $id_pengajar_session);
            if ($stmt_update_statistik->execute()) {
                $pesan_update_statistik = "Statistik dan informasi tambahan berhasil diperbarui.";
            } else {
                $pesan_update_statistik = "Gagal memperbarui statistik: " . $stmt_update_statistik->error;
            }
            $stmt_update_statistik->close();
        } else {
            $pesan_update_statistik = "Gagal menyiapkan statement pembaruan statistik: " . $conn->error;
        }
    }
}

$pengajar = null;
if (isset($conn) && $conn instanceof mysqli) {
    $stmt_select = $conn->prepare("SELECT nama_pengajar, foto_profil, jenjang_keahlian, keahlian, harga_layanan, no, riwayat_pendidikan, lokasi FROM pengajar WHERE id_pengajar = ?");
    if ($stmt_select) {
        $stmt_select->bind_param("i", $id_pengajar_session);
        $stmt_select->execute();
        $result = $stmt_select->get_result();
        $pengajar = $result->fetch_assoc();
        $stmt_select->close();
    }
}

$riwayat_mengajar_list = [];
if (isset($conn) && $conn instanceof mysqli) {
    $sql_riwayat = "
        SELECT
            pl.id_permintaan,
            pl.nama_pemesan_les,
            pl.keahlian_les,
            pl.jenjang_les,
            pl.lokasi_les_diajukan,
            pl.tanggal_les_diajukan,
            pl.jam_les_diajukan,
            pl.harga_saat_booking,
            rr.rating,
            rr.review
        FROM permintaan_les pl
        LEFT JOIN rating_review rr ON pl.id_permintaan = rr.id_les
        WHERE pl.id_pengajar = ? AND pl.status_permintaan = 'selesai'
        ORDER BY pl.tanggal_les_diajukan DESC, pl.jam_les_diajukan DESC
    ";
    $stmt_riwayat = $conn->prepare($sql_riwayat);
    if ($stmt_riwayat) {
        $stmt_riwayat->bind_param("i", $id_pengajar_session);
        $stmt_riwayat->execute();
        $result_riwayat = $stmt_riwayat->get_result();
        while ($row = $result_riwayat->fetch_assoc()) {
            $riwayat_mengajar_list[] = $row;
        }
        $stmt_riwayat->close();
    }
}

function tampilkan($data, $placeholder = 'Belum diisi')
{
    return !empty($data) ? htmlspecialchars($data, ENT_QUOTES, 'UTF-8') : $placeholder;
}

function tampilkan_harga($harga, $placeholder = 'Belum diisi')
{
    if ($harga !== null && is_numeric($harga)) {
        return 'Rp ' . number_format($harga, 0, ',', '.');
    }
    return $placeholder;
}

function format_tanggal_badge($tanggal_sql)
{
    if (empty($tanggal_sql)) return ['day' => 'N/A', 'number' => '-', 'month_year' => 'Tidak Valid'];
    try {
        $date = new DateTime($tanggal_sql);
        $hariIndonesia = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $bulanIndonesia = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];
        return ['day' => $hariIndonesia[(int)$date->format('w')], 'number' => $date->format('d'), 'month_year' => $bulanIndonesia[(int)$date->format('n')] . ' ' . $date->format('Y')];
    } catch (Exception $e) {
        return ['day' => 'Error', 'number' => '!', 'month_year' => 'Format Tgl'];
    }
}

function format_jam_wib($jam_sql)
{
    if (empty($jam_sql)) return 'N/A';
    return (new DateTime($jam_sql))->format('H:i') . ' WIB';
}
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title>Akun Pengajar</title>
    <style>
        :root {
            --primary-color: #213555;
            --secondary-color: #3E5879;
            --background-light: #FDFAF6;
            --background-white: #fdfaf6;
            --border-color: #E6E8EC;
            --text-color: #213555;
        }

        body,
        html {
            margin: 0;
            padding: 0;
            width: 100%;
            font-family: 'Roboto', sans-serif;
            background-color: #f5efe7;
            color: var(--text-color);
        }

        .page-wrapper {
            background: var(--background-white);
            min-height: 100vh;
            width: 100%;
            display: flex;
            flex-direction: column;
            padding-top: 73px;
        }

        .main-container {
            width: 100%;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .navigation {
            background-color: var(--background-light, #fdfaf6);
            width: 100%;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
        }

        .nav-content {
            display: flex;
            padding: 10px 20px;
            align-items: center;
            justify-content: center;
            max-width: 1200px;
            margin: 0 auto;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 32px;
        }

        .logo-button {
            border: none;
            background: none;
            cursor: pointer;
            height: 52px;
            display: flex;
            align-items: center;
        }

        .logo-image {
            width: 50px;
            height: auto;
            display: block;
        }

        .nav-link {
            color: #213555;
            font-size: 16px;
            font-weight: 700;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 6px;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .nav-divider {
            height: 1px;
            background-color: var(--border-color);
            width: 100%;
        }

        .hero-section {
            background: var(--background-light);
            display: flex;
            align-items: stretch;
            gap: 25px;
            padding: 25px 40px;
            box-sizing: border-box;
            width: 100%;
        }

        .hero-image-container {
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background-color: #f0f0f0;
            border: 1px solid var(--border-color);
            width: 475px;
            height: 475px;
            flex-shrink: 0;
        }

        .hero-image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .hero-image-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--secondary-color);
            text-align: center;
        }

        .hero-image-placeholder i {
            font-size: 70px;
            margin-bottom: 10px;
        }

        .hero-form-wrapper {
            display: flex;
            flex-direction: column;
            justify-content: center;
            flex: 1;
        }

        .form-container.form-content,
        .statistics-container {
            background: var(--background-white);
            border: 1px solid var(--primary-color);
            border-radius: 18px;
            width: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 15px;
            padding: 20px;
            box-sizing: border-box;
            flex-grow: 1;
        }

        .form-section-title,
        .history-header {
            text-align: center;
            width: 100%;
            margin-bottom: 5px;
        }

        .form-heading,
        .history-title {
            color: var(--primary-color);
            font-size: 20px;
            font-weight: 700;
        }

        .form-fields {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .input-group {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .input-label {
            color: var(--primary-color);
            font-size: 14px;
            font-weight: 700;
        }

        .text-input-display,
        .select-display {
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 20px;
            min-height: 40px;
            width: 100%;
            display: flex;
            align-items: center;
            padding: 8px 12px;
            box-sizing: border-box;
        }

        .text-input-display input,
        .text-input-display textarea,
        .select-display select {
            border: none;
            outline: none;
            flex-grow: 1;
            background-color: transparent;
            font-family: 'Roboto', sans-serif;
            font-size: 14px;
            color: var(--text-color);
        }

        .text-input-display textarea {
            resize: vertical;
            min-height: 60px;
        }

        .form-actions {
            width: 100%;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 10px;
        }

        .button-primary {
            background: var(--primary-color);
            border: 1px solid var(--primary-color);
            border-radius: 20px;
            color: var(--background-light);
            font-size: 14px;
            font-weight: 700;
            padding: 8px 18px;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .button-primary:hover {
            background-color: var(--secondary-color);
        }

        .feedback-message {
            padding: 8px 10px;
            margin-bottom: 10px;
            border-radius: 4px;
            text-align: center;
            font-size: 0.9em;
        }

        .feedback-message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .feedback-message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .statistics-section {
            padding: 40px;
            background-color: #f5efe7;
        }

        .main-content1 {
            display: flex;
            flex-grow: 1;
            padding: 40px 20px;
            flex-direction: column;
            align-items: center;
            gap: 40px;
            width: 100%;
            background-color: #f5efe7;
            box-sizing: border-box;
        }

        .content-wrapper1 {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 40px;
            width: 100%;
            max-width: 1200px;
            background-color: transparent;
        }

        .request-section {
            display: flex;
            width: 100%;
            flex-direction: column;
            align-items: flex-start;
            border-radius: 24px;
            background-color: #fdfaf6;
            border: 1px solid var(--primary-color);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .request-header {
            display: flex;
            padding: 20px 25px;
            align-items: center;
            width: 100%;
            background-color: #f8f9fa;
            border-bottom: 1px solid #e0e5ec;
        }

        .section-title {
            font-weight: 700;
            font-size: 22px;
            color: #213555;
            width: 100%;
            text-align: center;
        }

        .request-list {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            width: 100%;
        }

        .request-card {
            display: flex;
            padding: 18px 25px;
            align-items: center;
            gap: 20px;
            width: 100%;
            border-bottom: 1px solid #e8e8e8;
            box-sizing: border-box;
        }

        .request-card:last-child {
            border-bottom: none;
        }

        .no-requests-message {
            padding: 30px 25px;
            text-align: center;
            color: #586a7e;
            font-size: 1.0em;
            width: 100%;
        }

        .request-content {
            display: flex;
            align-items: center;
            gap: 20px;
            flex: 1;
        }

        .date-badge {
            display: flex;
            min-width: 85px;
            max-width: 85px;
            padding: 10px 8px;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            border-radius: 10px;
            background-color: #213555;
            color: #fff;
            text-align: center;
            flex-shrink: 0;
        }

        .request-details {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 6px;
            flex: 1;
            overflow: hidden;
        }

        .student-name {
            font-weight: 700;
            font-size: 17px;
            color: #2c3e50;
        }

        .location-info {
            color: #586a7e;
            font-size: 12.5px;
            font-weight: 400;
            line-height: 1.5;
        }

        .location-info span {
            display: block;
        }

        .lesson-info {
            font-weight: 500;
            font-size: 13px;
            color: #34495e;
            background-color: #ecf0f1;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 2px;
        }

        .contact-info {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 4px;
        }

        .whatsapp-icon {
            width: 14px;
            height: 14px;
        }

        .phone-number {
            font-size: 12.5px;
            color: #3E5879;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: var(--background-white);
            margin: auto;
            padding: 20px;
            border: 1px solid var(--primary-color);
            border-radius: 18px;
            width: 80%;
            max-width: 500px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            position: relative;
            animation-name: animatetop;
            animation-duration: 0.4s
        }

        @keyframes animatetop {
            from {
                top: -300px;
                opacity: 0
            }

            to {
                top: 0;
                opacity: 1
            }
        }

        .close-button {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            position: absolute;
            top: 10px;
            right: 20px;
        }

        .close-button:hover,
        .close-button:focus {
            color: var(--primary-color);
            text-decoration: none;
        }

        .modal-header {
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            margin-bottom: 15px;
            text-align: center;
        }

        .modal-header h2 {
            margin: 0;
            color: var(--primary-color);
            font-size: 20px;
        }

        .modal-body {
            font-size: 16px;
            line-height: 1.6;
            color: var(--text-color);
        }

        .review-button {
            background-color: #213555;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background-color 0.3s ease;
            margin-left: 700px;
        }

        .review-button:hover {
            background-color: #3E5879;
        }

        .review-button.disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        
        .rating-display {
            font-size: 1.1em;
            color: #213555;
            margin-right: 5px;
        }

        @media (max-width: 992px) {
            .hero-section {
                flex-direction: column;
                padding: 20px;
            }

            .hero-image-container {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .request-card {
                flex-direction: column;
                align-items: stretch;
            }
            .review-button {
                width: 100%;
                margin-top: 10px;
                margin-left: 0;
            }
        }
    </style>
</head>

<body>
    <nav class="navigation">
        <div class="nav-content">
            <div class="nav-left">
                <a href="dashboardpengajar.php" class="logo-button" aria-label="Beranda">
                    <img src="images/logo.png" alt="Logo Perusahaan" class="logo-image" />
                </a>
                <a href="dashboardpengajar.php" class="nav-link">Beranda</a>
                <a href="manajemenpengajar.php" class="nav-link">Manajemen</a>
                <a href="akunpengajar.php" class="nav-link">Akun</a>
                <a href="logout.php" class="nav-link">Keluar Akun</a>
            </div>
        </div>
        <div class="nav-divider"></div>
    </nav>

    <div class="page-wrapper">
        <div class="main-container">
            <section class="hero-section">
                <div class="hero-image-container">
                    <?php if (!empty($pengajar['foto_profil']) && file_exists($pengajar['foto_profil'])): ?>
                        <img src="<?php echo htmlspecialchars($pengajar['foto_profil']) . '?t=' . time(); ?>" alt="Foto Profil Pengajar">
                    <?php else: ?>
                        <div class="hero-image-placeholder">
                            <i class="fas fa-user-circle"></i>
                            <span>Foto profil belum diunggah</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="hero-form-wrapper">
                    <form class="form-container form-content" method="POST" action="akunpengajar.php" enctype="multipart/form-data">
                        <div class="form-section-title">
                            <h2 class="form-heading">Ubah Data Diri Pengajar</h2>
                        </div>
                        <?php if (!empty($pesan_update_akun)): ?>
                            <div class="feedback-message <?php echo (strpos(strtolower($pesan_update_akun), 'berhasil') !== false) ? 'success' : 'error'; ?>">
                                <?php echo htmlspecialchars($pesan_update_akun); ?>
                            </div>
                        <?php endif; ?>
                        <div class="form-fields">
                            <div class="input-group">
                                <label class="input-label" for="namaPenggunaEdit">Nama Pengguna</label>
                                <div class="text-input-display">
                                    <input type="text" name="nama_pengguna_baru" id="namaPenggunaEdit" value="<?php echo tampilkan($pengajar['nama_pengajar'] ?? '', ''); ?>">
                                </div>
                            </div>
                            <div class="input-group">
                                <label class="input-label" for="kataSandiEdit">Kata Sandi</label>
                                <div class="text-input-display">
                                    <input type="password" name="kata_sandi_baru" id="kataSandiEdit" placeholder="********">
                                </div>
                            </div>
                            <div class="input-group">
                                <label class="input-label" for="fotoProfilBaru">Ganti Foto Profil</label>
                                <div class="text-input-display" style="padding: 5px 12px;">
                                    <input type="file" name="foto_profil_baru" id="fotoProfilBaru" style="font-size:0.9em;">
                                </div>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="submit_edit_akun" class="button-primary">Edit Akun</button>
                        </div>
                    </form>
                </div>
            </section>
            <section class="statistics-section">
                <form class="statistics-container" method="POST" action="akunpengajar.php">
                    <div class="history-header">
                        <h2 class="history-title">Statistik & Informasi Tambahan</h2>
                    </div>
                    <?php if (!empty($pesan_update_statistik)): ?>
                        <div class="feedback-message <?php echo (strpos(strtolower($pesan_update_statistik), 'berhasil') !== false) ? 'success' : 'error'; ?>">
                            <?php echo htmlspecialchars($pesan_update_statistik); ?>
                        </div>
                    <?php endif; ?>
                    <div class="form-fields">
                        <div class="input-group">
                            <label class="input-label" for="jenjangKeahlianEdit">Jenjang Pendidikan</label>
                            <div class="select-display">
                                <select name="jenjang_keahlian_baru" id="jenjangKeahlianEdit">
                                    <option value="" <?php if (empty($pengajar['jenjang_keahlian'])) echo 'selected'; ?>>Pilih Jenjang</option>
                                    <option value="SD" <?php if (($pengajar['jenjang_keahlian'] ?? '') == 'SD') echo 'selected'; ?>>SD</option>
                                    <option value="SMP" <?php if (($pengajar['jenjang_keahlian'] ?? '') == 'SMP') echo 'selected'; ?>>SMP</option>
                                    <option value="SMA" <?php if (($pengajar['jenjang_keahlian'] ?? '') == 'SMA') echo 'selected'; ?>>SMA</option>
                                </select>
                            </div>
                        </div>
                        <div class="input-group">
                            <label class="input-label" for="keahlianEdit">Keahlian</label>
                            <div class="select-display">
                                <select name="keahlian_baru" id="keahlianEdit">
                                    <option value="" <?php if (empty($pengajar['keahlian'])) echo 'selected'; ?>>Pilih Keahlian</option>
                                    <option value="Matematika" <?php if (($pengajar['keahlian'] ?? '') == 'Matematika') echo 'selected'; ?>>Matematika</option>
                                    <option value="Fisika" <?php if (($pengajar['keahlian'] ?? '') == 'Fisika') echo 'selected'; ?>>Fisika</option>
                                    <option value="Kimia" <?php if (($pengajar['keahlian'] ?? '') == 'Kimia') echo 'selected'; ?>>Kimia</option>
                                    <option value="Biologi" <?php if (($pengajar['keahlian'] ?? '') == 'Biologi') echo 'selected'; ?>>Biologi</option>
                                    <option value="Bahasa Indonesia" <?php if (($pengajar['keahlian'] ?? '') == 'Bahasa Indonesia') echo 'selected'; ?>>Bahasa Indonesia</option>
                                    <option value="Bahasa Inggris" <?php if (($pengajar['keahlian'] ?? '') == 'Bahasa Inggris') echo 'selected'; ?>>Bahasa Inggris</option>
                                    <option value="Ekonomi" <?php if (($pengajar['keahlian'] ?? '') == 'Ekonomi') echo 'selected'; ?>>Ekonomi</option>
                                    <option value="Sejarah" <?php if (($pengajar['keahlian'] ?? '') == 'Sejarah') echo 'selected'; ?>>Sejarah</option>
                                    <option value="Geografi" <?php if (($pengajar['keahlian'] ?? '') == 'Geografi') echo 'selected'; ?>>Geografi</option>
                                    <option value="Sosiologi" <?php if (($pengajar['keahlian'] ?? '') == 'Sosiologi') echo 'selected'; ?>>Sosiologi</option>
                                    <option value="Ilmu Pengetahuan Alam" <?php if (($pengajar['keahlian'] ?? '') == 'Ilmu Pengetahuan Alam') echo 'selected'; ?>>Ilmu Pengetahuan Alam</option>
                                </select>
                            </div>
                        </div>
                        <div class="input-group">
                            <label class="input-label" for="hargaLayananEdit">Harga</label>
                            <div class="text-input-display">
                                <input type="text" name="harga_layanan_baru" id="hargaLayananEdit" value="<?php echo tampilkan_harga($pengajar['harga_layanan'] ?? '', ''); ?>" placeholder="Contoh: Rp 75.000">
                            </div>
                        </div>
                        <div class="input-group">
                            <label class="input-label" for="noHandphoneEdit">No Handphone</label>
                            <div class="text-input-display">
                                <input type="text" name="no_handphone_baru" id="noHandphoneEdit" value="<?php echo tampilkan($pengajar['no'] ?? '', ''); ?>" placeholder="08xxxxxxxxxx">
                            </div>
                        </div>
                        <div class="input-group">
                            <label class="input-label" for="riwayatPendidikanEdit">Riwayat Pendidikan</label>
                            <div class="text-input-display" style="align-items: flex-start;">
                                <textarea name="riwayat_pendidikan_baru" id="riwayatPendidikanEdit" placeholder="Contoh: S1 Teknik Informatika - Universitas ABC (2020)&#10;SMA Negeri 1 Teladan (2016)"><?php echo tampilkan($pengajar['riwayat_pendidikan'] ?? '', ''); ?></textarea>
                            </div>
                        </div>
                        <div class="input-group">
                            <label class="input-label" for="lokasiEdit">Lokasi</label>
                            <div class="text-input-display" style="align-items: flex-start;">
                                <textarea name="lokasi_baru" id="lokasiEdit" placeholder="Contoh: Nakoa Malang&#10;Lokasi Pertemuan dengan Siswa"><?php echo tampilkan($pengajar['lokasi'] ?? '', ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="submit_simpan_statistik" class="button-primary">Simpan Info</button>
                    </div>
                </form>
            </section>

            <main class="main-content1">
                <div class="content-wrapper1">
                    <section class="request-section">
                        <div class="request-container">
                            <header class="request-header">
                                <h1 class="section-title">Melihat Riwayat Sesi</h1>
                            </header>
                            <div class="request-list">
                                <?php if (empty($riwayat_mengajar_list)): ?>
                                    <div class="no-requests-message">
                                        <p>Belum ada riwayat mengajar yang selesai.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($riwayat_mengajar_list as $riwayat): ?>
                                        <?php
                                        $tanggal_badge = format_tanggal_badge($riwayat['tanggal_les_diajukan']);
                                        $jam_les = format_jam_wib($riwayat['jam_les_diajukan']);
                                        $harga_les = tampilkan_harga($riwayat['harga_saat_booking']);
                                        $review_tersedia = !empty($riwayat['review']);
                                        $rating_tersedia = ($riwayat['rating'] !== null);
                                        ?>
                                        <article class="request-card">
                                            <div class="request-content">
                                                <div class="date-badge">
                                                    <span class="date-day"><?php echo htmlspecialchars($tanggal_badge['day']); ?></span>
                                                    <span class="date-number"><?php echo htmlspecialchars($tanggal_badge['number']); ?></span>
                                                    <span class="date-month"><?php echo htmlspecialchars($tanggal_badge['month_year']); ?></span>
                                                </div>
                                                <div class="request-details">
                                                    <div class="student-name"><?php echo htmlspecialchars($riwayat['nama_pemesan_les']); ?></div>
                                                    <div class="location-info">
                                                        <span>Diajukan di: <?php echo nl2br(htmlspecialchars($riwayat['lokasi_les_diajukan'])); ?></span>
                                                        <span>Waktu Les: <?php echo htmlspecialchars($jam_les); ?></span>
                                                    </div>
                                                    <span class="lesson-info">
                                                        <?php echo htmlspecialchars($riwayat['jenjang_les']); ?> -
                                                        <?php echo htmlspecialchars($riwayat['keahlian_les']); ?> |
                                                        <?php echo htmlspecialchars($harga_les); ?>
                                                    </span>
                                                </div>
                                                <?php if ($review_tersedia || $rating_tersedia): ?>
                                                    <button type="button" class="review-button"
                                                        data-id-les="<?php echo htmlspecialchars($riwayat['id_permintaan']); ?>"
                                                        data-review-text="<?php echo htmlspecialchars($riwayat['review'] ?? 'Tidak ada ulasan teks.'); ?>"
                                                        data-rating="<?php echo htmlspecialchars($riwayat['rating'] ?? 0); ?>">
                                                        Lihat Ulasan
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="review-button disabled" disabled>
                                                        Belum Ada Ulasan
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>

    <div id="reviewModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <div class="modal-header">
                <h2>Ulasan Sesi Mengajar</h2>
            </div>
            <div class="modal-body">
                <p><strong>Rating:</strong> <span id="modalRating"></span></p>
                <p><strong>Ulasan:</strong></p>
                <p id="modalReviewText"></p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const hargaInput = document.getElementById('hargaLayananEdit');
            if (hargaInput) {
                const formatHarga = (value) => {
                    let number_string = value.replace(/[^,\d]/g, '').toString();
                    if (number_string) {
                        return 'Rp ' + parseInt(number_string).toLocaleString('id-ID');
                    }
                    return '';
                };
                hargaInput.addEventListener('input', function(e) {
                    e.target.value = formatHarga(e.target.value);
                });
                if (hargaInput.value) {
                    hargaInput.value = formatHarga(hargaInput.value);
                }
            }

            const reviewModal = document.getElementById('reviewModal');
            const closeButton = document.getElementsByClassName('close-button')[0];
            const modalRating = document.getElementById('modalRating');
            const modalReviewText = document.getElementById('modalReviewText');
            const reviewButtons = document.querySelectorAll('.review-button:not(.disabled)');

            reviewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const reviewText = this.getAttribute('data-review-text');
                    const rating = parseFloat(this.getAttribute('data-rating'));

                    modalReviewText.textContent = reviewText;
                    
                    let starsHtml = '';
                    if (rating > 0) {
                        for (let i = 0; i < Math.floor(rating); i++) {
                            starsHtml += '<i class="fas fa-star rating-display"></i>';
                        }
                        if (rating % 1 !== 0) {
                            starsHtml += '<i class="fas fa-star-half-alt rating-display"></i>';
                        }
                        modalRating.innerHTML = starsHtml + ` (${rating.toFixed(1)}/5)`;
                    } else {
                        modalRating.textContent = 'Tidak ada rating.';
                    }

                    reviewModal.style.display = 'flex';
                });
            });

            closeButton.addEventListener('click', function() {
                reviewModal.style.display = 'none';
            });

            window.addEventListener('click', function(event) {
                if (event.target == reviewModal) {
                    reviewModal.style.display = 'none';
                }
            });
        });
    </script>
    <?php if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    } ?>
</body>

</html>