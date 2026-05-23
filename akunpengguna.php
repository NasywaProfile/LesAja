<?php
session_start();
include 'db.php'; 

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'pengguna') {
    header("Location: login.php?error=auth_required");
    exit();
}

$id_pengguna_session = $_SESSION['user_id'];
$pesan_update = ""; 

$pengguna = null;
if (isset($conn) && $conn instanceof mysqli) {
    $stmt_pengguna_awal = $conn->prepare("SELECT id_pengguna, nama_pengguna, foto_profil FROM pengguna WHERE id_pengguna = ?");
    if ($stmt_pengguna_awal) {
        $id_pengguna_int = (int)$id_pengguna_session; 
        $stmt_pengguna_awal->bind_param("i", $id_pengguna_int);
        $stmt_pengguna_awal->execute();
        $pengguna = $stmt_pengguna_awal->get_result()->fetch_assoc();
        $stmt_pengguna_awal->close();
    } else {
        error_log("Prepare statement failed in akunpengguna.php: " . $conn->error);
        $pesan_update = "Kesalahan internal: Gagal menyiapkan query data pengguna.";
    }
}

if ($pengguna) {
    if (!isset($_SESSION['foto_profil']) || $_SESSION['foto_profil'] !== $pengguna['foto_profil']) {
        $_SESSION['foto_profil'] = $pengguna['foto_profil'];
    }
    if (!isset($_SESSION['nama']) || $_SESSION['nama'] !== $pengguna['nama_pengguna']) {
        $_SESSION['nama'] = $pengguna['nama_pengguna'];
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_edit_akun'])) {
    if (!isset($conn) || !$conn instanceof mysqli) {
        $pesan_update = "Kesalahan koneksi database.";
    } else {
        $nama_pengguna_baru = mysqli_real_escape_string($conn, $_POST['nama_pengguna_baru']);
        $sandi_baru = $_POST['sandi_baru'];

        if (empty($nama_pengguna_baru)) {
            $pesan_update = "Nama lengkap tidak boleh kosong.";
        } else {
            //
            $sql_update = "UPDATE pengguna SET nama_pengguna = ?";
            $params_type = "s";
            $params_array = [$nama_pengguna_baru];

            if (!empty($sandi_baru)) {
                $hashed_password = password_hash($sandi_baru, PASSWORD_DEFAULT);
                $sql_update .= ", kata_sandi = ?";
                $params_type .= "s";
                $params_array[] = $hashed_password;
            }

            if (isset($_FILES['foto_profil_baru']) && $_FILES['foto_profil_baru']['error'] == 0) {
                $target_dir = "uploads/profile_pictures_pengguna/";
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }

                $imageFileType = strtolower(pathinfo($_FILES["foto_profil_baru"]["name"], PATHINFO_EXTENSION));
                $nama_file_unik = uniqid('user_', true) . '.' . $imageFileType;
                $target_file = $target_dir . $nama_file_unik;
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                //
                if (in_array($imageFileType, $allowed_types)) {
                    if (move_uploaded_file($_FILES["foto_profil_baru"]["tmp_name"], $target_file)) {
                        if (!empty($pengguna['foto_profil']) && file_exists($pengguna['foto_profil'])) {
                            unlink($pengguna['foto_profil']);
                        }
                        $sql_update .= ", foto_profil = ?";
                        $params_type .= "s";
                        $params_array[] = $target_file;
                    } else {
                        $pesan_update = "Gagal mengunggah file foto.";
                    }
                } else {
                    $pesan_update = "Format file tidak valid. Pastikan formatnya adalah JPG, PNG, atau GIF.";
                }
            }

            if (empty($pesan_update)) {
                $sql_update .= " WHERE id_pengguna = ?";
                $params_type .= "i"; 
                
                $id_pengguna_int_update = (int)$id_pengguna_session;
                $params_array[] = $id_pengguna_int_update;

                $stmt_update = $conn->prepare($sql_update);
                if ($stmt_update) {
                    $stmt_update->bind_param($params_type, ...$params_array);
                    if ($stmt_update->execute()) {
                        $pesan_update = "Data diri berhasil diperbarui.";

                        $_SESSION['nama'] = $nama_pengguna_baru;
                        $pengguna['nama_pengguna'] = $nama_pengguna_baru;

                        if (isset($target_file) && !empty($target_file)) {
                            $_SESSION['foto_profil'] = $target_file;
                            $pengguna['foto_profil'] = $target_file;
                        }
                    } else {
                        $pesan_update = "Gagal memperbarui data: " . $stmt_update->error;
                    }
                    $stmt_update->close();
                } else {
                    $pesan_update = "Gagal menyiapkan pembaruan: " . $conn->error;
                }
            }
        }
    }
}

$riwayat_les = [];
if (isset($conn) && $conn instanceof mysqli) {
    $stmt_riwayat = $conn->prepare("
        SELECT 
            pl.tanggal_les_diajukan, pl.jam_les_diajukan, pl.lokasi_les_diajukan,
            pl.jenjang_les, pl.keahlian_les, pl.harga_saat_booking,
            p.nama_pengajar, p.no AS no_pengajar
        FROM permintaan_les pl 
        JOIN pengajar p ON pl.id_pengajar = p.id_pengajar
        WHERE pl.id_pengguna = ? AND pl.status_permintaan = 'selesai'
        ORDER BY pl.tanggal_les_diajukan DESC, pl.jam_les_diajukan DESC
    ");
    if ($stmt_riwayat) {
        $id_pengguna_int_riwayat = (int)$id_pengguna_session;
        $stmt_riwayat->bind_param("i", $id_pengguna_int_riwayat); 
        $stmt_riwayat->execute();
        $result_riwayat = $stmt_riwayat->get_result();
        while ($row = $result_riwayat->fetch_assoc()) {
            $riwayat_les[] = $row;
        }
        $stmt_riwayat->close();
    }
}

if (!function_exists('tampilkan')) {
    function tampilkan($data, $placeholder = 'Belum diisi')
    {
        return !empty($data) ? htmlspecialchars($data, ENT_QUOTES, 'UTF-8') : $placeholder;
    }
}
if (!function_exists('format_tanggal_badge_riwayat')) {
    function format_tanggal_badge_riwayat($tanggal_sql)
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
}
if (!function_exists('tampilkan_harga')) {
    function tampilkan_harga($harga, $placeholder = 'N/A')
    {
        if ($harga !== null && is_numeric($harga)) {
            return 'Rp ' . number_format($harga, 0, ',', '.');
        }
        return $placeholder;
    }
}
if (!function_exists('format_jam_wib')) {
    function format_jam_wib($jam_sql)
    {
        if (empty($jam_sql)) return 'N/A';
        return (new DateTime($jam_sql))->format('H:i') . ' WIB';
    }
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
    <title>Akun Saya - <?php echo htmlspecialchars($pengguna['nama_pengguna'] ?? 'Pengguna'); ?></title>
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

        .nav-link.active {
            background-color: #e0e5ec;
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

        .form-container.form-content {
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

        .form-section-title {
            text-align: center;
            width: 100%;
            margin-bottom: 5px;
        }

        .form-heading {
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

        .text-input-display {
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

        .text-input-display input {
            border: none;
            outline: none;
            flex-grow: 1;
            background-color: transparent;
            font-family: 'Roboto', sans-serif;
            font-size: 14px;
            color: var(--text-color);
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

        .history-section {
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

        .history-header {
            display: flex;
            padding: 24px;
            align-items: center;
            width: 100%;
            border-bottom: 1px solid var(--border-color);
        }

        .section-title {
            font-weight: 700;
            font-size: 24px;
            color: #213555;
            width: 100%;
            line-height: 1.4;
            text-align: center;
        }

        .history-list {
            display: flex;
            flex-direction: column;
            width: 100%;
            padding: 0 24px 24px 24px;
        }

        .history-card {
            display: flex;
            padding: 18px 0;
            align-items: center;
            gap: 24px;
            width: 100%;
            border-bottom: 1px solid #e8e8e8;
        }

        .history-card:last-child {
            border-bottom: none;
        }

        .history-content {
            display: flex;
            align-items: center;
            gap: 20px;
            flex: 1;
        }

        .date-badge {
            display: flex;
            min-width: 90px;
            padding: 10px 8px;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            border-radius: 12px;
            background-color: #213555;
            color: #fff;
            text-align: center;
            flex-shrink: 0;
        }

        .date-day {
            font-weight: 700;
            font-size: 14px;
        }

        .date-number {
            font-weight: 700;
            font-size: 28px;
            line-height: 1.2;
            margin: 2px 0;
        }

        .date-month {
            font-weight: 700;
            font-size: 14px;
        }

        .history-details {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
            flex: 1;
        }

        .teacher-name {
            font-weight: 700;
            font-size: 18px;
            color: #2c3e50;
        }

        .contact-info {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .phone-icon {
            width: 14px;
            height: 14px;
        }

        .phone-number {
            font-size: 14px;
            color: var(--secondary-color);
        }

        .location-info {
            color: #586a7e;
            font-size: 13px;
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
            padding: 4px 10px;
            border-radius: 4px;
            display: inline-block;
        }

        .no-history-message {
            text-align: center;
            padding: 30px;
            color: var(--secondary-color);
        }

        @media (max-width: 992px) {
            .hero-section {
                flex-direction: column;
                padding: 20px;
                gap: 20px;
            }

            .hero-image-container {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="page-wrapper">
        <nav class="navigation">
            <div class="nav-content">
                <div class="nav-left">
                    <a href="dashboardpengguna.php" class="logo-button" aria-label="Beranda">
                        <img src="images/logo.png" alt="Logo Perusahaan" class="logo-image" />
                    </a>
                    <a href="dashboardpengguna.php" class="nav-link">Beranda</a>
                    <a href="pengajar.php" class="nav-link">Pengajar</a>
                    <a href="jadwalpengguna.php" class="nav-link">Jadwal</a>
                    <a href="akunpengguna.php" class="nav-link">Akun</a>
                    <a href="logout.php" class="nav-link">Keluar Akun</a>
                </div>
            </div>
            <div class="nav-divider"></div>
        </nav>
        <div class="main-container">
            <section class="hero-section">
                <div class="hero-image-container">
                    <?php
                    if (!empty($pengguna['foto_profil']) && file_exists($pengguna['foto_profil'])): ?>
                        <img src="<?php echo htmlspecialchars($pengguna['foto_profil']) . '?t=' . time(); ?>" alt="Foto Profil <?php echo htmlspecialchars($pengguna['nama_pengguna'] ?? 'Pengguna'); ?>">
                    <?php else: ?>
                        <div class="hero-image-placeholder">
                            <i class="fas fa-user-circle"></i>
                            <span>Foto profil belum diunggah</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="hero-form-wrapper">
                    <form class="form-container form-content" method="POST" action="akunpengguna.php" enctype="multipart/form-data">
                        <div class="form-section-title">
                            <h1 class="form-heading">Ubah Data Diri Pengguna</h1>
                        </div>
                        <?php if (!empty($pesan_update)): ?>
                            <div class="feedback-message <?php echo (strpos(strtolower($pesan_update), 'berhasil') !== false) ? 'success' : 'error'; ?>">
                                <?php echo htmlspecialchars($pesan_update); ?>
                            </div>
                        <?php endif; ?>
                        <div class="form-fields">
                            <div class="input-group">
                                <label class="input-label" for="nama_penggunaPenggunaEdit">Nama Pengguna</label>
                                <div class="text-input-display">
                                    <input type="text" name="nama_pengguna_baru" id="nama_penggunaPenggunaEdit" value="<?php echo htmlspecialchars($pengguna['nama_pengguna'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="input-group">
                                <label class="input-label" for="kataSandiEdit">Kata Sandi</label>
                                <div class="text-input-display">
                                    <input type="password" name="sandi_baru" id="kataSandiEdit" placeholder="********">
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

            <main class="main-content1">
                <div class="content-wrapper1">
                    <section class="history-section">
                        <div class="history-container">
                            <header class="history-header">
                                <h1 class="section-title">Melihat Riwayat Sesi</h1>
                            </header>
                            <div class="history-list">
                                <?php if (empty($riwayat_les)): ?>
                                    <p class="no-history-message">Belum ada riwayat les yang selesai.</p>
                                <?php else: ?>
                                    <?php foreach ($riwayat_les as $riwayat): ?>
                                        <?php
                                        $tanggal_badge = format_tanggal_badge_riwayat($riwayat['tanggal_les_diajukan']);
                                        $jam_les = format_jam_wib($riwayat['jam_les_diajukan']);
                                        $harga_les = tampilkan_harga($riwayat['harga_saat_booking']);
                                        ?>
                                        <article class="history-card">
                                            <div class="history-content">
                                                <div class="date-badge">
                                                    <span class="date-day"><?php echo htmlspecialchars($tanggal_badge['day']); ?></span>
                                                    <span class="date-number"><?php echo htmlspecialchars($tanggal_badge['number']); ?></span>
                                                    <span class="date-month"><?php echo htmlspecialchars($tanggal_badge['month_year']); ?></span>
                                                </div>
                                                <div class="history-details">
                                                    <div class="teacher-name"><?php echo htmlspecialchars($riwayat['nama_pengajar']); ?></div>

                                                    <?php if (!empty($riwayat['no_pengajar'])): ?>
                                                        <div class="contact-info">
                                                            <img src="images/wa.png" alt="Telepon" class="phone-icon">
                                                            <span class="phone-number"><?php echo htmlspecialchars($riwayat['no_pengajar']); ?></span>
                                                        </div>
                                                    <?php endif; ?>

                                                    <div class="location-info">
                                                        <span>Lokasi: <?php echo nl2br(htmlspecialchars($riwayat['lokasi_les_diajukan'])); ?></span>
                                                        <span>Waktu Les: <?php echo htmlspecialchars($jam_les); ?></span>
                                                    </div>
                                                    <span class="lesson-info">
                                                        <?php echo htmlspecialchars($riwayat['jenjang_les']); ?> -
                                                        <?php echo htmlspecialchars($riwayat['keahlian_les']); ?> |
                                                        <?php echo htmlspecialchars($harga_les); ?>
                                                    </span>
                                                </div>
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
    <?php if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    } ?>
</body>

</html>