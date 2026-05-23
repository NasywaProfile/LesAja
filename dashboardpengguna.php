<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'pengguna') {
    header("Location: login.php");
    exit();
}

include 'db.php'; 

$user_id = $_SESSION['user_id'];
$nama_pengguna = $_SESSION['nama'] ?? 'Pengguna';
$foto_profil_pengguna = $_SESSION['foto_profil'] ?? 'images/default_user_avatar.png';

if (!function_exists('tampilkan_harga')) {
    function tampilkan_harga($harga, $placeholder = 'N/A')
    {
        if ($harga !== null && is_numeric($harga)) {
            return 'Rp ' . number_format($harga, 0, ',', '.');
        }
        return $placeholder;
    }
}

$teachers = [];
$sql_rekomendasi = "SELECT id_pengajar, nama_pengajar, foto_profil, keahlian, jenjang_keahlian, lokasi, harga_layanan, rating
                    FROM pengajar
                    WHERE nama_pengajar IS NOT NULL AND
                          keahlian IS NOT NULL AND jenjang_keahlian IS NOT NULL AND
                          lokasi IS NOT NULL AND harga_layanan IS NOT NULL
                    ORDER BY rating DESC, nama_pengajar ASC
                    LIMIT 3";
$result_rekomendasi = $conn->query($sql_rekomendasi);
if ($result_rekomendasi) {
    while ($row = $result_rekomendasi->fetch_assoc()) {
        $teachers[] = $row;
    }
}
//
$answered_tasks = [];
$sql_answered_tasks = "SELECT ts.id_soal, ts.soal, ts.jawaban, ts.foto_tugas, p.nama_pengajar, p.no, ts.id_pengajar, ts.rating_diberikan
                        FROM tugas_soal ts
                        LEFT JOIN pengajar p ON ts.id_pengajar = p.id_pengajar
                        WHERE ts.id_pengguna = ? 
                        AND ts.jawaban IS NOT NULL AND ts.jawaban != '' 
                        AND ts.id_pengajar IS NOT NULL 
                        AND ts.rating_diberikan IS NULL
                        ORDER BY ts.tanggal_dijawab DESC, ts.tanggal_kirim DESC";

$stmt_answered_tasks = $conn->prepare($sql_answered_tasks);
if ($stmt_answered_tasks) {
    $stmt_answered_tasks->bind_param("i", $user_id);
    $stmt_answered_tasks->execute();
    $result_answered_tasks = $stmt_answered_tasks->get_result();
    while ($row = $result_answered_tasks->fetch_assoc()) {
        $answered_tasks[] = $row;
    }
    $stmt_answered_tasks->close();
} else {
    error_log("Failed to prepare SQL for answered tasks: " . $conn->error);
}

if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error && $conn->ping()) {
    $conn->close();
}
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard Pengguna - LesAja</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet" />
    <style>
        :root {
            --background-page: #FDFAF6;
            --background-navbar: #FDFAF6;
            --text-primary-original: #213555;
            --text-light: #FFFFFF;
            --text-muted: #666666;
            --navbar-height: 70px;
            --primary-color: #213555;
            --secondary-color: #3E5879;
            --text-color: #213555;
            --background-light: #FDFAF6;
            --background-white: #fdfaf6;
            --border-color-strong: #e0e0e0;
            --border-color: #f0f0f0;
            --button-hover-secondary-color: #354969;
        }

        body {
            margin: 0;
            font-family: 'Roboto', sans-serif;
            background-color: var(--background-light);
            color: var(--text-color);
            line-height: 1.6;
            padding-top: var(--navbar-height);
        }

        * {
            box-sizing: border-box;
        }

        .app-container {
            width: 100%;
            min-height: calc(100vh - var(--navbar-height));
            display: flex;
            flex-direction: column;
        }

        .navbar {
            background-color: var(--background-navbar);
            width: 100%;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
        }

        .navbar-content {
            display: flex;
            padding: 10px 20px;
            align-items: center;
            justify-content: center;
            max-width: 1200px;
            margin: 0 auto;
        }

        .navbar-left {
            display: flex;
            align-items: center;
            gap: 32px;
        }

        .navbar-logo-button {
            border: none;
            background: none;
            cursor: pointer;
            padding: 0;
            height: 50px;
        }

        .navbar-logo-image {
            width: 50px;
            height: auto;
            display: block;
        }

        .navbar-link {
            color: #213555;
            font-size: 16px;
            font-weight: 700;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 6px;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .navbar-link.active {
            color: var(--secondary-color);
        }

        .navbar-divider {
            height: 1px;
            background-color: var(--border-color);
            width: 100%;
        }

        .teacher-recommendation-section {
            background: var(--background-light);
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 25px;
            padding: 20px;
            box-sizing: border-box;
        }

        .teacher-cards-container {
            width: 100%;
            max-width: 1200px;
            display: flex;
            justify-content: center;
            align-items: stretch;
            flex-wrap: wrap;
            gap: 20px;
        }

        .teacher-card {
            border: 1px solid var(--border-color-strong);
            border-radius: 15px;
            flex-basis: 320px;
            flex-grow: 0;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            background-color: var(--background-white);
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .teacher-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.1);
        }

        .teacher-card-image-wrapper {
            position: relative;
            width: 100%;
            height: 220px;
            background-color: #e9e9e9;
            overflow: hidden;
        }

        .teacher-card-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            display: block;
            transition: transform 0.3s ease;
        }

        .teacher-card:hover .teacher-card-image {
            transform: scale(1.05);
        }

        .teacher-card-details {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
            padding: 15px;
            box-sizing: border-box;
            flex-grow: 1;
        }

        .teacher-card-info {
            flex-grow: 1;
        }

        .teacher-card-name span {
            font-size: 18px;
            font-weight: 700;
        }

        .teacher-card-description span {
            font-size: 13px;
            color: var(--text-muted);
        }

        .teacher-card-rating {
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 8px;
        }

        .rating-stars-container {
            display: flex;
            gap: 2px;
        }

        .rating-star img {
            height: 15px;
            width: 15px;
            filter: grayscale(100%) brightness(2.5);
        }

        .rating-star img.active {
            filter: none;
        }

        .teacher-card-select-button {
            align-self: flex-start;
            margin-top: auto;
            background: var(--text-color);
            border: 1px solid var(--text-color);
            border-radius: 25px;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            padding: 8px 18px;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.2s ease;
        }

        .teacher-card-select-button span {
            color: var(--background-light);
            font-size: 14px;
            font-weight: 600;
        }

        .task-section {
            background-color: var(--background-light);
            padding: 40px 20px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }

        .task-header {
            max-width: 700px;
            margin: 0 auto 20px auto;
        }

        .task-title {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
        }

        .task-description {
            font-size: 15px;
            margin: 12px 0 0;
        }

        .task-main-layout {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: flex-start;
            gap: 30px;
            margin-top: 0;
            max-width: 800px;
            width: 100%;
        }

        .image-preview,
        .gambar-preview {
            position: relative;
            flex-basis: 380px;
            flex-grow: 1;
            max-width: 100%;
            background-color: var(--primary-color);
            padding: 20px;
            box-sizing: border-box;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            border: 2px solid var(--primary-color);
            overflow: hidden;
            margin-top: 5px;
        }

        .image-preview {
            min-height: 290px;
            height: auto;
        }

        .gambar-preview {
            min-height: 330px;
            height: 260px;
        }

        .custom-file-upload {
            background-color: var(--secondary-color);
            color: var(--text-light);
            padding: 8px 15px;
            border-radius: 20px;
            cursor: pointer;
            display: inline-block;
            font-size: 13px;
            font-weight: 600;
        }

        .task-form-and-button-wrapper {
            flex-basis: 380px;
            flex-grow: 2;
            max-width: 100%;
            text-align: left;
            margin-top: -20px;
        }

        .task-form {
            width: 100%;
        }

        .form-label,
        .student-name {
            font-weight: 700;
            color: var(--primary-color);
            display: block;
            margin-bottom: 8px;
        }

        .student-name {
            font-size: 20px;
            margin-bottom: 15px;
        }

        .teacher-info-container {
            width: 100%;
            margin-bottom: 15px;
        }

        .teacher-contact {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 15px;
            font-weight: 500;
            color: var(--secondary-color);
        }

        .question-box-textarea,
        .pertanyaan-box-textarea,
        .jawaban-box-textarea {
            width: 100%;
            border: 2px solid var(--primary-color);
            border-radius: 10px;
            padding: 14px;
            background-color: white;
            color: var(--secondary-color);
            font-weight: 500;
            margin-bottom: 14px;
            resize: vertical;
            font-family: 'Roboto', sans-serif;
            font-size: 16px;
            box-sizing: border-box;
        }

        .question-box-textarea {
            min-height: 210px;
        }

        .pertanyaan-box-textarea,
        .jawaban-box-textarea {
            min-height: 80px;
        }

        .submit-button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 90px;
            padding: 12px 24px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: background-color 0.3s ease;
            margin-right: auto; 
            margin-left: -60px; 
            margin-top: -15px;
        }

        .rate-button {
            background-color: #213555;
            color: white;
            border: none;
            border-radius: 90px;
            padding: 12px 24px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: background-color 0.3s ease;
            margin-right: auto;
            margin-left: -80px; 
        }

        #respon-pengajar-section {
            padding-left: 60px;
            padding-right: 60px;
            padding-bottom: 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 60px;
            justify-content: flex-start;
        }

        #respon-pengajar-section .task-header {
            margin-top: 0;
            margin-bottom: 20px;
        }

        .task-main-layout.no-response-layout {
            flex-grow: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            min-height: 360px;
            padding: 0;
            max-width: 800px;
        }

        .no-response-box {
            text-align: center;
            padding: 40px;
            border: 2px dashed var(--secondary-color);
            border-radius: 10px;
            width: 100%;
            max-width: 700px;
            background-color: #ffffff;
            height: 300px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .no-response-box h3 {
            font-size: 20px;
            color: var(--primary-color);
            margin: 0 0 10px 0;
            font-weight: 700;
        }

        .no-response-box p {
            font-size: 16px;
            color: var(--secondary-color);
            margin: 0;
        }

        #respon-slider-wrapper {
            width: 100%;
            max-width: 800px;
            overflow: visible; 
            min-height: 360px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        
        #respon-slider-viewport {
            width: 100%;
            overflow: hidden;
        }

        #respon-container {
            display: flex;
            transition: transform 0.5s ease-in-out;
            width: 100%;
        }

        .respon-item {
            width: 100%;
            flex-shrink: 0;
            display: flex;
            padding: 0 5px;
        }

        .btn-nav-respon {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
            background-color: rgba(255, 255, 255, 0.9);
            border: 1px solid #e0e0e0;
            border-radius: 50%;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
        }
        .btn-nav-respon:hover:not(:disabled) {
            background-color: #fff;
            transform: translateY(-50%) scale(1.05);
        }
        .btn-nav-respon svg {
            width: 24px;
            height: 24px;
            stroke: var(--primary-color);
            stroke-width: 2.5;
        }
        #tombolKiriRespon { left: -150px; }
        #tombolKananRespon { right: -150px; }
        .btn-nav-respon:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal-overlay.show {
            opacity: 1;
        }

        .modal-container {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            width: 90%;
            max-width: 480px;
            text-align: center;
            transform: scale(0.95);
            transition: transform 0.3s ease;
            position: relative;
        }

        .modal-overlay.show .modal-container {
            transform: scale(1);
        }

        .modal-close-button {
            position: absolute;
            top: 16px;
            right: 16px;
            background: none;
            border: none;
            font-size: 28px;
            color: #aaa;
            cursor: pointer;
            line-height: 1;
        }

        .rating-modal-content {
            padding: 32px;
        }

        .modal-title {
            font-size: 22px;
            font-weight: 700;
            color: #213555;
            margin-bottom: 12px;
        }

        .modal-message {
            font-size: 15px;
            color: #213555;
            margin-bottom: 24px;
            line-height: 1.5;
        }

        .rating-stars-modal {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin: 20px 0;
        }

        .rating-star-modal {
            width: 32px;
            height: 32px;
            cursor: pointer;
            filter: grayscale(100%);
            opacity: 0.7;
            transition: all 0.2s ease;
        }

        .rating-stars-modal:hover .rating-star-modal {
            opacity: 0.5;
        }

        .rating-star-modal:hover,
        .rating-star-modal.active {
            filter: grayscale(0%);
            transform: scale(1.15);
            opacity: 1;
        }

        .modal-button-submit {
            width: 100%;
            background-color: #213555;
            color: white;
            padding: 14px;
            border-radius: 34px;
            font-size: 16px;
            font-weight: 700;
            border: none;
            cursor: pointer;
        }

        .modal-button-submit:disabled {
            background-color: #95a5a6;
            cursor: not-allowed;
        }
        
        .success-modal-container {
            padding: 32px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }

        .success-icon-wrapper {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background-color: #e8f5e9;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .success-icon-wrapper svg {
            width: 40px;
            height: 40px;
            color: #4CAF50;
        }

        .success-button {
            width: 100%;
            padding: 14px;
            font-size: 16px;
            font-weight: 500;
            background-color: #213555;
            color: #fff;
            border-radius: 25px;
            border: none;
            cursor: pointer;
            margin-top: 10px;
        }

        #toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 3000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .toast {
            padding: 12px 18px;
            border-radius: 8px;
            color: #fff;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            opacity: 0;
            transform: translateX(100%);
            animation: slideInToast 0.5s forwards, fadeOutToast 0.5s 4s forwards;
        }

        .toast.error { background-color: #e74c3c; }
        .toast.success { background-color: #2ecc71; }

        @keyframes slideInToast {
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes fadeOutToast {
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }
    </style>
</head>

<body>
    <div class="app-container">
        <nav class="navbar">
            <div class="navbar-content">
                <div class="navbar-left">
                    <a href="dashboardpengguna.php" class="navbar-logo-button" aria-label="Beranda"><img src="images/logo.png" alt="Logo Perusahaan" class="navbar-logo-image" /></a>
                    <a href="dashboardpengguna.php" class="navbar-link active">Beranda</a>
                    <a href="pengajar.php" class="navbar-link">Pengajar</a>
                    <a href="jadwalpengguna.php" class="navbar-link">Jadwal</a>
                    <a href="akunpengguna.php" class="navbar-link">Akun</a>
                    <a href="logout.php" class="navbar-link">Keluar Akun</a>
                </div>
            </div>
            <div class="navbar-divider"></div>
        </nav>

        <main class="teacher-recommendation-section">
            <h1 class="task-title">Rekomendasi Pengajar Terbaik</h1>
            <div class="teacher-cards-container">
                <?php if (!empty($teachers)): ?>
                    <?php foreach ($teachers as $teacher): ?>
                        <?php
                        $foto_src = 'images/default_teacher_avatar.png';
                        if (!empty($teacher['foto_profil'])) {
                            if (file_exists($teacher['foto_profil'])) {
                                $foto_src = htmlspecialchars($teacher['foto_profil'], ENT_QUOTES, 'UTF-8');
                            } else {
                                $full_path = 'uploads/profile_pictures/' . $teacher['foto_profil'];
                                if (file_exists($full_path)) {
                                    $foto_src = htmlspecialchars($full_path, ENT_QUOTES, 'UTF-8');
                                }
                            }
                        }
                        $deskripsi_pengajar_card = "Mengajar " . htmlspecialchars($teacher['keahlian'] ?? '') . " " . htmlspecialchars($teacher['jenjang_keahlian'] ?? '') . " | " . htmlspecialchars($teacher['lokasi'] ?? '') . " | " . tampilkan_harga($teacher['harga_layanan']);
                        $pilih_link_card = "konfirmasi.php?id_pengajar=" . htmlspecialchars($teacher['id_pengajar']);
                        $rating_pengajar = (float)($teacher['rating'] ?? 0);
                        $stars_html = '';
                        for ($i = 1; $i <= 5; $i++) {
                            $is_active = ($i <= floor($rating_pengajar) && $rating_pengajar > 0) ? 'active' : '';
                            $stars_html .= '<div class="rating-star"><img src="images/star.svg" alt="Star" class="' . $is_active . '" /></div>';
                        }
                        ?>
                        <div class="teacher-card">
                            <div class="teacher-card-image-wrapper">
                                <img src="<?php echo $foto_src . '?t=' . time(); ?>" alt="Foto <?php echo htmlspecialchars($teacher['nama_pengajar']); ?>" class="teacher-card-image" onerror="this.onerror=null; this.src='images/default_teacher_avatar.png';">
                            </div>
                            <div class="teacher-card-details">
                                <div class="teacher-card-info">
                                    <h2 class="teacher-card-name"><span><?php echo htmlspecialchars($teacher['nama_pengajar']); ?></span></h2>
                                    <p class="teacher-card-description"><span><?php echo $deskripsi_pengajar_card; ?></span></p>
                                </div>
                                <div class="teacher-card-rating">
                                    <div class="rating-stars-container"><?php echo $stars_html; ?></div>
                                </div>
                                <a href="<?php echo $pilih_link_card; ?>" class="teacher-card-select-button"><span>Pilih</span></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; width: 100%;">Saat ini belum ada rekomendasi pengajar.</p>
                <?php endif; ?>
            </div>
        </main>

        <section class="task-section">
            <header class="task-header">
                <h2 class="task-title">Tanyakan Tugas</h2>
                <p class="task-description">Memungkinkan siswa mengirim soal atau pertanyaan kepada pengajar</p>
            </header>
            <form id="tanya-tugas-form" action="submit_task.php" method="POST" enctype="multipart/form-data" class="task-main-layout">
                <div class="image-preview">
                    <img id="image-preview" src="" alt="Preview Gambar Tugas" style="display: none; width: 100%; height: auto; max-height: 200px; object-fit: contain;" />
                    <div id="image-placeholder" style="width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; color: white; gap: 10px;">
                        <img src="images/up.png" alt="Placeholder icon" style="width: 90px; height: 90px;">
                        <span>Unggah Gambar Tugas Anda</span>
                    </div>
                    <div style="margin-top: 15px;">
                        <label for="actual-task-image-upload" class="custom-file-upload">Pilih File</label>
                        <input type="file" id="actual-task-image-upload" name="task_image" accept="image/*" style="display: none;" />
                    </div>
                </div>
                <div class="task-form-and-button-wrapper">
                    <div class="task-form">
                        <h3 class="student-name">Nama Pengguna: <?php echo htmlspecialchars($nama_pengguna); ?></h3>
                        <div class="question-section">
                            <label class="form-label">Pertanyaan</label>
                            <textarea id="question-textarea" name="soal_text" class="question-box-textarea" placeholder="Ketik pertanyaan Anda di sini..."></textarea>
                        </div>
                    </div>
                    <div style="width: 100%; display: flex; justify-content: flex-end; margin-top: 20px;">
                        <button type="submit" class="submit-button" id="taskSubmitButton">Kirim</button>
                    </div>
                </div>
            </form>
        </section>

        <section class="task-section" id="respon-pengajar-section">
            <header class="task-header">
                <h2 class="task-title">Respon Pengajar</h2>
                <p class="task-description">Berikut adalah tugas yang telah dijawab oleh pengajar.</p>
            </header>
            
            <div id="respon-slider-wrapper">
                <button class="btn-nav-respon" id="tombolKiriRespon" style="display: none;"><svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg></button>
                <button class="btn-nav-respon" id="tombolKananRespon" style="display: none;"><svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg></button>
                
                <div id="respon-slider-viewport">
                    <div id="respon-container">
                        <?php if (!empty($answered_tasks)): ?>
                            <?php foreach ($answered_tasks as $task): ?>
                                <div class="task-main-layout respon-item">
                                    <div class="gambar-preview">
                                        <?php if (!empty($task['foto_tugas']) && file_exists($task['foto_tugas'])): ?>
                                            <img src="<?php echo htmlspecialchars($task['foto_tugas']); ?>" alt="Gambar Tugas" style="width:100%; height:100%; object-fit:contain;" />
                                        <?php else: ?>
                                            <div style="color:white; display:flex; align-items:center; justify-content:center; width:100%; height:100%;"><span>Tidak ada gambar</span></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="task-form-and-button-wrapper">
                                        <div class="task-form">
                                            <div class="teacher-info-container">
                                                <h3 class="student-name">Pengajar: <?php echo htmlspecialchars($task['nama_pengajar']); ?></h3>
                                                <div class="teacher-contact"><img src="images/wa.png" alt="WA" width="16" height="16"> <span><?php echo htmlspecialchars($task['no']); ?></span></div>
                                            </div>
                                            <div><label class="form-label">Pertanyaan</label><textarea class="pertanyaan-box-textarea" readonly><?php echo htmlspecialchars($task['soal']); ?></textarea></div>
                                            <div><label class="form-label">Jawaban</label><textarea class="jawaban-box-textarea" readonly><?php echo htmlspecialchars($task['jawaban']); ?></textarea></div>
                                            <div style="width: 100%; display:flex; justify-content:flex-end;">
                                                <?php if (is_null($task['rating_diberikan'])): ?>
                                                    <button type="button" class="rate-button"
                                                        data-id-soal="<?php echo htmlspecialchars($task['id_soal']); ?>"
                                                        data-id-pengajar="<?php echo htmlspecialchars($task['id_pengajar']); ?>"
                                                        data-nama-pengajar="<?php echo htmlspecialchars($task['nama_pengajar']); ?>">
                                                        Berikan Rating
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="task-main-layout no-response-layout">
                                <div class="no-response-box">
                                    <h3>Belum Ada Tugas yang Dijawab</h3>
                                    <p>Tugas yang Anda kirimkan akan muncul di sini setelah dijawab oleh pengajar.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="modal-overlay" id="ratingModal">
        <div class="modal-container rating-modal-content">
            <button class="modal-close-button" data-close>&times;</button>
            <h2 class="modal-title">Berikan Rating untuk Pengajar</h2>
            <p class="modal-message">Bagikan pengalaman Anda dengan <strong id="modalTeacherName"></strong>.</p>
            <div class="rating-stars-modal">
                <img src="images/star.svg" alt="Star 1" class="rating-star-modal" data-rating="1" /><img src="images/star.svg" alt="Star 2" class="rating-star-modal" data-rating="2" /><img src="images/star.svg" alt="Star 3" class="rating-star-modal" data-rating="3" /><img src="images/star.svg" alt="Star 4" class="rating-star-modal" data-rating="4" /><img src="images/star.svg" alt="Star 5" class="rating-star-modal" data-rating="5" />
            </div>
            <button class="modal-button-submit" id="submitRatingButton">Kirim Penilaian</button>
        </div>
    </div>
    
    <div class="modal-overlay" id="taskSuccessModal">
        <div class="modal-container">
            <div class="success-modal-container">
                <div class="success-icon-wrapper">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                </div>
                <h2 class="modal-title">Tugas Terkirim</h2>
                <p class="modal-message">Terima kasih, tugas Anda telah berhasil dikirim.</p>
                <button class="success-button" id="successModalSelesaiButton">Selesai</button>
            </div>
        </div>
    </div>
    
    <div id="toast-container"></div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        
        const taskSuccessModal = document.getElementById('taskSuccessModal');
        const successModalSelesaiButton = document.getElementById('successModalSelesaiButton');

        const taskForm = document.getElementById('tanya-tugas-form');
        if (taskForm) {
            taskForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const questionTextarea = document.getElementById('question-textarea');
                const fileInput = document.getElementById('actual-task-image-upload');
                if (questionTextarea.value.trim() === '' && fileInput.files.length === 0) {
                    showToast('Harap isi pertanyaan atau unggah gambar tugas.', 'error');
                    return;
                }
                const submitButton = document.getElementById('taskSubmitButton');
                submitButton.textContent = 'Mengirim...';
                submitButton.disabled = true;
                const formData = new FormData(taskForm);
                fetch('submit_task.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showModal(taskSuccessModal);
                    } else {
                        throw new Error(data.message || 'Terjadi kesalahan.');
                    }
                })
                .catch(error => { showToast(error.message, 'error'); })
                .finally(() => {
                    submitButton.textContent = 'Kirim Tugas';
                    submitButton.disabled = false;
                });
            });

            const imagePreview = document.getElementById('image-preview');
            const imagePlaceholder = document.getElementById('image-placeholder');
            const actualImageUpload = document.getElementById('actual-task-image-upload');
            if (actualImageUpload) {
                actualImageUpload.addEventListener('change', function(event) {
                    const file = event.target.files[0];
                    if (file && imagePreview && imagePlaceholder) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            imagePreview.src = e.target.result;
                            imagePreview.style.display = 'block';
                            imagePlaceholder.style.display = 'none';
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
        }

        if (successModalSelesaiButton) {
            successModalSelesaiButton.addEventListener('click', () => {
                hideModal(taskSuccessModal);
                setTimeout(() => { location.reload(); }, 300); 
            });
        }

        const ratingModal = document.getElementById('ratingModal');
        const submitRatingButton = document.getElementById('submitRatingButton');
        let activeTaskData = {};
        let currentRating = 0;
        document.querySelectorAll('.rate-button').forEach(button => {
            button.addEventListener('click', function() {
                activeTaskData = { ...this.dataset };
                document.getElementById('modalTeacherName').textContent = activeTaskData.namaPengajar;
                showModal(ratingModal);
            });
        });

        if (submitRatingButton) {
            submitRatingButton.addEventListener('click', function() {
                if (currentRating === 0) {
                    showToast('Harap berikan minimal 1 bintang rating.', 'error'); return;
                }
                this.disabled = true; this.textContent = 'Mengirim...';
                const payload = { id_soal: activeTaskData.idSoal, id_pengajar: activeTaskData.idPengajar, rating: currentRating, review: "" };
                fetch('submit_rating_tugas.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        hideModal(ratingModal); location.reload();
                    } else {
                        throw new Error(data.message || 'Gagal menyimpan penilaian.');
                    }
                })
                .catch(error => {
                    showToast(error.message, 'error');
                    this.disabled = false; this.textContent = 'Kirim Penilaian';
                });
            });
        }

        function showModal(modal) { if (modal) { modal.style.display = 'flex'; setTimeout(() => modal.classList.add('show'), 10); } }
        function hideModal(modal) { if (modal) { modal.classList.remove('show'); setTimeout(() => { modal.style.display = 'none'; if (modal.id === 'ratingModal') resetRatingModal(); }, 300); } }
        document.querySelectorAll('[data-close], .modal-overlay').forEach(el => { el.addEventListener('click', function(event) { if (event.target === this || event.target.hasAttribute('data-close')) { const modalToClose = this.closest('.modal-overlay'); if (modalToClose) { hideModal(modalToClose); } } }); });
        function resetRatingModal() {
            currentRating = 0;
            document.querySelectorAll('.rating-star-modal.active').forEach(s => s.classList.remove('active'));
            if (submitRatingButton) {
                submitRatingButton.disabled = false; submitRatingButton.textContent = 'Kirim Penilaian';
            }
        }
        function showToast(message, type = 'error') { const container = document.getElementById('toast-container'); if (!container) return; const toast = document.createElement('div'); toast.className = `toast ${type}`; if(type === 'success') { toast.style.backgroundColor = '#2ecc71'; } toast.textContent = message; container.appendChild(toast); setTimeout(() => toast.remove(), 4500); }
        document.querySelectorAll('.rating-star-modal').forEach(star => {
            star.addEventListener('click', function() {
                currentRating = parseInt(this.dataset.rating);
                document.querySelectorAll('.rating-star-modal').forEach(s => s.classList.toggle('active', parseInt(s.dataset.rating) <= currentRating));
            });
        });

        const responContainer = document.getElementById('respon-container');
        if (responContainer) {
            const tombolKiri = document.getElementById('tombolKiriRespon');
            const tombolKanan = document.getElementById('tombolKananRespon');
            const responItems = responContainer.querySelectorAll('.respon-item');
            const totalRespon = responItems.length;
            let currentIndex = 0;

            const updateResponView = () => {
                if (!responContainer) return;
                responContainer.style.transform = `translateX(-${currentIndex * 100}%)`;
                if(tombolKiri && tombolKanan){
                    if (totalRespon > 1) {
                        tombolKiri.style.display = 'flex';
                        tombolKanan.style.display = 'flex';
                        tombolKiri.disabled = (currentIndex === 0);
                        tombolKanan.disabled = (currentIndex >= totalRespon - 1);
                    } else {
                        tombolKiri.style.display = 'none';
                        tombolKanan.style.display = 'none';
                    }
                }
            };
            
            if (tombolKanan) {
                tombolKanan.addEventListener('click', () => {
                    if (currentIndex < totalRespon - 1) { currentIndex++; updateResponView(); }
                });
            }
            if (tombolKiri) {
                tombolKiri.addEventListener('click', () => {
                    if (currentIndex > 0) { currentIndex--; updateResponView(); }
                });
            }
            updateResponView(); 
        }
    });
    </script>
</body>
</html>