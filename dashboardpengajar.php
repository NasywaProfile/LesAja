<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'pengajar') {
    header("Location: login.php");
    exit();
}

$id_pengajar_session = $_SESSION['user_id'];
$stmt_rating = $conn->prepare("SELECT rating FROM pengajar WHERE id_pengajar = ?");
$stmt_rating->bind_param("i", $id_pengajar_session);
$stmt_rating->execute();
$result_rating = $stmt_rating->get_result();
$rating_rata_rata = 'N/A';
$rating_bintang_integer = 0;
if ($row_rating = $result_rating->fetch_assoc()) {
    if ($row_rating['rating'] !== null) {
        $rating_rata_rata = number_format($row_rating['rating'], 1);
        $rating_bintang_integer = floor($row_rating['rating']);
    }
}
$stmt_rating->close();
$stmt_diajarkan = $conn->prepare("SELECT COUNT(*) AS total_diajarkan FROM permintaan_les WHERE id_pengajar = ? AND status_permintaan = 'selesai'");
$stmt_diajarkan->bind_param("i", $id_pengajar_session);
$stmt_diajarkan->execute();
$result_diajarkan = $stmt_diajarkan->get_result();
$jumlah_pengguna_diajarkan = $result_diajarkan->fetch_assoc()['total_diajarkan'] ?? 0;
$stmt_diajarkan->close();
$stmt_soal_dijawab = $conn->prepare("SELECT COUNT(*) AS total_dijawab FROM tugas_soal WHERE id_pengajar = ? AND jawaban IS NOT NULL AND jawaban != ''");
$stmt_soal_dijawab->bind_param("i", $id_pengajar_session);
$stmt_soal_dijawab->execute();
$result_soal_dijawab = $stmt_soal_dijawab->get_result();
$jumlah_soal_dijawab = $result_soal_dijawab->fetch_assoc()['total_dijawab'] ?? 0;
$stmt_soal_dijawab->close();

$stmt_total = $conn->prepare("SELECT COUNT(ts.id_soal) as total FROM tugas_soal ts JOIN pengguna u ON ts.id_pengguna = u.id_pengguna WHERE ts.jawaban IS NULL AND ts.id_pengajar IS NULL");
$stmt_total->execute();
$total_tugas_belum_dijawab = $stmt_total->get_result()->fetch_assoc()['total'] ?? 0;
$stmt_total->close();

$tugas_saat_ini = null;
if ($total_tugas_belum_dijawab > 0) {
    $sql_tugas = "SELECT ts.id_soal, ts.soal, ts.foto_tugas, u.nama_pengguna FROM tugas_soal ts JOIN pengguna u ON ts.id_pengguna = u.id_pengguna WHERE ts.jawaban IS NULL AND ts.id_pengajar IS NULL ORDER BY ts.tanggal_kirim ASC LIMIT 1 OFFSET 0";
    $result_tugas = $conn->query($sql_tugas);
    if ($result_tugas && $result_tugas->num_rows > 0) {
        $tugas_saat_ini = $result_tugas->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pengajar - LesAja</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #213555;
            --secondary-color: #3E5879;
            --background-light: #FDFAF6;
            --background-white: #fdfaf6;
            --border-color: #f0f0f0;
            --text-color: #213555;
            --navbar-dashboard-height: 93px;
        }

        body {
            margin: 0;
            font-family: 'Roboto';
            color: var(--text-color);
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
            border-radius: 8px;
            padding: 0;
            border: none;
            background: none;
            cursor: pointer;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
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

        main.teacher-dashboard {
            padding-top: var(--navbar-dashboard-height);
            background-color: var(--background-light);
        }

        .statistics-section {
            background-color: #fdfaf6;
            padding: 87px 80px;
            text-align: center;
        }

        .statistics-title {
            font-size: 48px;
            line-height: 1.2;
            margin: 0;
        }

        .statistics-grid {
            display: flex;
            justify-content: center;
            gap: 48px;
            margin-top: 80px;
        }

        .stat-card {
            border-left: 2px solid var(--secondary-color);
            padding-left: 32px;
            text-align: left;
            flex: 1;
        }

        .stat-number {
            font-size: 80px;
            line-height: 1.3;
            margin: 0;
            color: var(--primary-color);
        }

        .stat-description {
            font-size: 24px;
            line-height: 34px;
            margin: 8px 0 0;
            color: var(--secondary-color);
        }

        .stat-rating-stars {
            display: flex;
            align-items: center;
            margin-top: 10px;
            gap: 5px;
        }

        .stat-rating-star {
            width: 30px;
            height: 30px;
            filter: grayscale(100%);
            transition: filter 0.2s ease;
        }

        .stat-rating-star.active {
            filter: grayscale(0%);
        }

        .stat-rating-value {
            display: flex;
            align-items: baseline;
            margin-top: 5px;
        }

        .stat-rating-number {
            font-size: 28px;
            font-weight: bold;
            color: var(--primary-color);
            margin-left: 0;
        }

        .stat-rating-total {
            font-size: 20px;
            color: var(--secondary-color);
            margin-left: 5px;
        }

        .task-section {
            background-color: var(--background-light);
            padding: 40px 20px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            margin-top: 20px;
        }

        .task-header {
            max-width: 700px;
            margin: 0 auto 20px auto;
            text-align: center;
        }

        .task-title {
            font-size: 24px;
            line-height: 1.2;
            margin: 0;
        }

        .task-description {
            font-size: 15px;
            margin: 12px 0 0;
        }

        .image-preview {
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
            min-height: 320px;
            height: 260px;
            border: 2px solid var(--primary-color);
            overflow: hidden;
            margin-top: 10px;
            margin-left: 50px
        }

        .preview-image {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: contain;
            object-position: center;
            background-color: var(--primary-color);
            border-radius: 0;
        }

        .image-preview-placeholder {
            width: 100%;
            height: 100%;
            background-color: var(--primary-color);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: #fff;
            font-size: 60px;
            border-radius: 0;
            overflow: hidden;
            border: none;
            gap: 10px;
            padding: 20px;
            box-sizing: border-box;
        }

        .image-preview-placeholder img {
            width: 80px;
            height: 80px;
            margin-bottom: 10px;
            filter: invert(100%) brightness(1.5);
        }

        .image-preview-placeholder span {
            font-size: 16px;
            color: #fff;
            text-align: center;
        }

        .task-form-and-button-wrapper {
            flex-basis: 380px;
            flex-grow: 2;
            max-width: 100%;
            text-align: left;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .task-form {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-size: 15px;
            display: block;
            margin-bottom: 10px;
            font-weight: 700;
            color: var(--primary-color);
        }

        .student-name {
            font-size: 20px;
            font-weight: 700;
            margin-top: 0;
            margin-bottom: 15px;
            color: var(--primary-color);
        }

        .question-section,
        .answer-section {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .question-box {
            width: 85%;
            border: 2px solid var(--primary-color);
            border-radius: 10px;
            padding: 14px;
            min-height: 110px;
            background-color: white;
            color: var(--secondary-color);
            font-weight: 500;
            margin-bottom: 14px;
            resize: vertical;
            font-family: 'Roboto', sans-serif;
            font-size: 16px;
            box-sizing: border-box;
        }

        .answer-box-textarea {
            width: 85%;
            border: 2px solid var(--primary-color);
            border-radius: 10px;
            padding: 14px;
            height: calc(50% - 30px);
            min-height: 110px;
            background-color: white;
            color: var(--secondary-color);
            font-weight: 500;
            margin-bottom: 14px;
            resize: vertical;
            font-family: 'Roboto', sans-serif;
            font-size: 16px;
            box-sizing: border-box;
            margin-top: 0px;
        }

        .answer-box-textarea::placeholder {
            color: #a0a0a0;
            font-weight: 400;
        }

        .submit-button-container {
            width: 100%;
            display: flex;
            justify-content: center;
            margin-top: 10px;
        }

        .submit-button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 90px;
            padding: 12px 24px;
            font-size: 16px;
            font-weight: 700;
            width: auto;
            min-width: 150px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            margin-left: 0;
            margin-top: 0;
            margin-bottom: 20px;
        }

        .submit-button:hover:not(:disabled) {
            background-color: var(--secondary-color);
        }

        .submit-button:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }

        .task-main-layout {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            width: 100%;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .task-nav-button {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            background-color: #ffffff;
            border: 2px solid var(--primary-color);
            border-radius: 50%;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease, opacity 0.3s ease;
            text-decoration: none;
        }

        .task-nav-button:hover:not(:disabled) {
            background-color: var(--primary-color);
            transform: scale(1.1);
        }

        .task-nav-button:hover:not(:disabled) img {
            filter: invert(1) brightness(2);
        }

        .task-nav-button img {
            width: 24px;
            height: 24px;
            transition: filter 0.3s ease;
        }

        .task-nav-button:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            border-color: #ccc;
        }

        .task-nav-button:disabled img {
            filter: grayscale(1);
        }

        .slider-viewport {
            flex-grow: 1;
            max-width: 980px;
            overflow: hidden;
        }

        .slider-track {
            display: flex;
            position: relative;
            transition: transform 0.35s ease-in-out;
        }

        .task-content-wrapper {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: flex-start;
            gap: 30px;
            width: 980px;
            flex-shrink: 0;
        }

        .task-content-wrapper.task-content-wrapper-empty {
            align-items: center;
            min-height: 360px;
        }

        .no-tasks-box {
            text-align: center;
            padding: 40px;
            border: 2px dashed var(--secondary-color);
            border-radius: 10px;
            width: 100%;
            max-width: 700px;
            background-color: #ffffff;
            height: 200px;
        }

        .no-tasks-box h3 {
            font-size: 20px;
            color: var(--primary-color);
            margin: 0 0 10px 0;
            font-weight: 700;
            margin-top: 60px;
        }

        .no-tasks-box p {
            font-size: 16px;
            color: var(--secondary-color);
            margin: 0;
        }

        .success-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 3000;
            backdrop-filter: blur(5px);
        }

        .success-modal-container {
            background: #FFFFFF;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            padding: 24px;
            width: 90%;
            max-width: 400px;
            text-align: center;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }

        .success-modal-close-btn {
            position: absolute;
            top: 16px;
            right: 16px;
            background: none;
            border: none;
            font-size: 24px;
            color: #888;
            cursor: pointer;
            line-height: 1;
        }

        .success-modal-icon-wrapper {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background-color: #e8f5e9;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .success-modal-icon-wrapper svg {
            width: 40px;
            height: 40px;
            color: #4CAF50;
        }

        .success-modal-title {
            font-size: 24px;
            font-weight: 700;
            color: #213555;
            margin: 0;
        }

        .success-modal-message {
            font-size: 14px;
            color: #586a7e;
            line-height: 1.5;
            margin: 0;
        }

        .success-modal-button {
            background: #435E8B;
            color: #FFFFFF;
            border: none;
            border-radius: 25px;
            padding: 12px 24px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.2s ease;
        }

        #toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 4000;
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

        .toast.error {
            background-color: #e74c3c;
        }

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
    <main class="teacher-dashboard">
        <nav class="navigation">
            <div class="nav-content">
                <div class="nav-left"><a href="dashboardpengajar.php" class="logo-button" aria-label="Beranda"><img src="images/logo.png" alt="Logo Perusahaan" class="logo-image" /></a><a href="dashboardpengajar.php" class="nav-link active">Beranda</a><a href="manajemenpengajar.php" class="nav-link">Manajemen</a><a href="akunpengajar.php" class="nav-link">Akun</a><a href="logout.php" class="nav-link">Keluar Akun</a></div>
            </div>
            <div class="nav-divider"></div>
        </nav>
        <section class="statistics-section">
            <header class="statistics-header">
                <h1 class="statistics-title">Statistik Pengajar Anda</h1>
            </header>
            <div class="statistics-grid">
                <article class="stat-card">
                    <h2 class="stat-number"><?php echo htmlspecialchars($jumlah_pengguna_diajarkan); ?></h2>
                    <p class="stat-description">Jumlah Sesi Les Selesai</p>
                </article>
                <article class="stat-card">
                    <h2 class="stat-number"><?php echo htmlspecialchars($jumlah_soal_dijawab); ?></h2>
                    <p class="stat-description">Tugas Dijawab</p>
                </article>
                <article class="stat-card">
                    <div class="stat-rating-stars"><?php for ($i = 1; $i <= 5; $i++): ?><img src="images/star.svg" alt="Star" class="stat-rating-star <?php echo ($i <= $rating_bintang_integer) ? 'active' : ''; ?>" /><?php endfor; ?></div>
                    <div class="stat-rating-value"><span class="stat-rating-number"><?php echo htmlspecialchars($rating_rata_rata); ?></span><span class="stat-rating-total">/ 5</span></div><br>
                    <p class="stat-description">Rating Rata-rata</p>
                </article>
            </div>
        </section>

        <section class="task-section">
            <header class="task-header">
                <h2 class="task-title">Jawab Tugas</h2>
                <p class="task-description">Kelola dan berikan jawaban terbaik untuk tugas yang dikirimkan oleh pengguna</p>
            </header>
            <form action="submit_answer.php" method="POST" id="main-task-form">
                <div class="task-main-layout">
                    <button id="prev-task-btn" type="button" class="task-nav-button prev" title="Tugas Sebelumnya">
                        <img src="https://api.iconify.design/material-symbols/arrow-back-ios-new.svg?color=%23213555" alt="Panah Kiri" />
                    </button>
                    <div class="slider-viewport">
                        <div id="slider-track" class="slider-track">
                            <?php if ($tugas_saat_ini): ?>
                                <div class="task-content-wrapper">
                                    <div class="image-preview">
                                        <img src="<?php echo !empty($tugas_saat_ini['foto_tugas']) ? htmlspecialchars($tugas_saat_ini['foto_tugas']) : ''; ?>" alt="Foto Tugas" class="preview-image" style="display: <?php echo !empty($tugas_saat_ini['foto_tugas']) ? 'block' : 'none'; ?>;" />
                                        <div class="image-preview-placeholder" style="display: <?php echo !empty($tugas_saat_ini['foto_tugas']) ? 'none' : 'flex'; ?>;">
                                            <img src="images/up.png" alt="Placeholder icon">
                                            <span><?php echo !empty($tugas_saat_ini['foto_tugas']) ? '' : 'Tidak ada gambar untuk tugas ini.'; ?></span>
                                        </div>
                                    </div>
                                    <div class="task-form-and-button-wrapper">
                                        <div class="task-form">
                                            <h3 class="student-name">Nama Pengguna: <?php echo htmlspecialchars($tugas_saat_ini['nama_pengguna']); ?></h3>
                                            <div class="question-section">
                                                <label class="form-label">Pertanyaan</label>
                                                <div class="question-box"><?php echo nl2br(htmlspecialchars($tugas_saat_ini['soal'])); ?></div>
                                            </div>
                                            <div class="answer-section">
                                                <label class="form-label">Jawaban Anda</label>
                                                <textarea name="answer_text" class="answer-box-textarea" placeholder="Ketik jawaban Anda di sini..."></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="task-content-wrapper task-content-wrapper-empty">
                                    <div class="no-tasks-box">
                                        <h3>Tidak Ada Tugas untuk Dijawab</h3>
                                        <p>Saat ini semua tugas sudah terjawab. Silakan periksa kembali nanti!</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button id="next-task-btn" type="button" class="task-nav-button next" title="Tugas Berikutnya">
                        <img src="https://api.iconify.design/material-symbols/arrow-forward-ios.svg?color=%23213555" alt="Panah Kanan" />
                    </button>
                </div>
                <input id="task-id-input" type="hidden" name="id_soal" value="<?php echo htmlspecialchars($tugas_saat_ini['id_soal'] ?? ''); ?>">
                <div class="submit-button-container">
                    <button id="submit-answer-btn" type="submit" class="submit-button" <?php if (!$tugas_saat_ini) echo 'style="display:none;"'; ?> disabled>Kirim</button>
                </div>
            </form>
        </section>
    </main>

    <div class="success-modal-overlay" id="answerSuccessModal">
        <div class="success-modal-container">
            <button class="success-modal-close-btn" data-close-success>&times;</button>
            <div class="success-modal-icon-wrapper">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
            </div>
            <h2 class="success-modal-title">Jawaban Terkirim</h2>
            <p class="success-modal-message">Jawaban Anda telah berhasil dikirim dan akan segera diterima oleh pengguna.</p>
            <button class="success-modal-button" data-close-success>Selesai</button>
        </div>
    </div>

    <div id="toast-container"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let currentOffset = 0;
            const totalTasks = <?php echo $total_tugas_belum_dijawab; ?>;
            let isAnimating = false;

            const prevBtn = document.getElementById('prev-task-btn');
            const nextBtn = document.getElementById('next-task-btn');
            const track = document.getElementById('slider-track');
            const viewport = document.querySelector('.slider-viewport');
            const taskIdInput = document.getElementById('task-id-input');
            const mainForm = document.getElementById('main-task-form');
            const submitBtn = document.getElementById('submit-answer-btn');

            const successModal = document.getElementById('answerSuccessModal');

            if (mainForm) {
                mainForm.addEventListener('submit', function(event) {
                    event.preventDefault();

                    const answerTextarea = this.querySelector('.answer-box-textarea');
                    if (!answerTextarea || answerTextarea.value.trim() === '') {
                        showToast('Harap isi jawaban Anda sebelum mengirim.', 'error');
                        return;
                    }

                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Mengirim...';

                    const formData = new FormData(mainForm);

                    fetch('submit_answer.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                successModal.style.display = 'flex';
                            } else {
                                throw new Error(data.message || 'Gagal mengirim jawaban.');
                            }
                        })
                        .catch(error => {
                            showToast(error.message, 'error');
                        })
                        .finally(() => {
                            submitBtn.textContent = 'Tugas Dijawab';
                        });
                });
            }

            function closeSuccessModal() {
                successModal.style.display = 'none';
                window.location.reload();
            }

            successModal.querySelectorAll('[data-close-success]').forEach(el => {
                el.addEventListener('click', closeSuccessModal);
            });

            function showToast(message, type = 'error') {
                const container = document.getElementById('toast-container');
                const toast = document.createElement('div');
                toast.className = `toast ${type}`;
                toast.textContent = message;
                container.appendChild(toast);
                setTimeout(() => {
                    toast.remove();
                }, 4500);
            }

            track.addEventListener('input', function(event) {
                if (event.target && event.target.matches('.answer-box-textarea')) {
                    if (event.target.value.trim() !== '') {
                        submitBtn.disabled = false;
                    } else {
                        submitBtn.disabled = true;
                    }
                }
            });

            function createTaskCard(task) {
                const hasImage = task.foto_tugas && task.foto_tugas.length > 0;
                const soalText = (task.soal || '...').replace(/\n/g, '<br>');
                return `
            <div class="task-content-wrapper">
                <div class="image-preview">
                    <img src="${hasImage ? task.foto_tugas : ''}" alt="Foto Tugas" class="preview-image" style="display: ${hasImage ? 'block' : 'none'};" />
                    <div class="image-preview-placeholder" style="display: ${hasImage ? 'none' : 'flex'};">
                        <img src="images/up.png" alt="Placeholder icon">
                        <span>${hasImage ? '' : 'Tidak ada gambar untuk tugas ini.'}</span>
                    </div>
                </div>
                <div class="task-form-and-button-wrapper">
                       <div class="task-form">
                            <h3 class="student-name">Nama Pengguna: ${task.nama_pengguna || '...'}</h3>
                            <div class="question-section">
                                <label class="form-label">Pertanyaan</label>
                                <div class="question-box">${soalText}</div>
                            </div>
                            <div class="answer-section">
                                <label class="form-label">Jawaban Anda</label>
                                <textarea name="answer_text" class="answer-box-textarea" placeholder="Ketik jawaban Anda di sini..."></textarea>
                            </div>
                    </div>
                </div>
            </div>`;
            }

            function updateNavButtons() {
                prevBtn.disabled = currentOffset <= 0;
                nextBtn.disabled = (currentOffset + 1) >= totalTasks;
            }

            function slide(direction) {
                if (isAnimating) return;
                const isNext = direction === 'next';
                const newOffset = isNext ? currentOffset + 1 : currentOffset - 1;
                if (newOffset < 0 || newOffset >= totalTasks) return;
                isAnimating = true;

                fetch(`api_get_task.php?offset=${newOffset}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            const newCardHTML = createTaskCard(data.task);
                            const cardWidth = viewport.offsetWidth;

                            submitBtn.disabled = true;
                            taskIdInput.value = data.task.id_soal;

                            if (isNext) {
                                track.insertAdjacentHTML('beforeend', newCardHTML);
                                track.style.transform = `translateX(-${cardWidth}px)`;
                            } else {
                                track.insertAdjacentHTML('afterbegin', newCardHTML);
                                track.style.transition = 'none';
                                track.style.transform = `translateX(-${cardWidth}px)`;
                                setTimeout(() => {
                                    track.style.transition = 'transform 0.35s ease-in-out';
                                    track.style.transform = 'translateX(0)';
                                }, 20);
                            }
                            setTimeout(() => {
                                if (isNext) {
                                    track.style.transition = 'none';
                                    track.firstElementChild.remove();
                                    track.style.transform = 'translateX(0)';
                                } else {
                                    track.lastElementChild.remove();
                                }
                                setTimeout(() => {
                                    track.style.transition = 'transform 0.35s ease-in-out';
                                    isAnimating = false;
                                    currentOffset = newOffset;
                                    updateNavButtons();
                                }, 20);
                            }, 350);
                        } else {
                            isAnimating = false;
                        }
                    }).catch(() => isAnimating = false);
            }

            nextBtn.addEventListener('click', () => slide('next'));
            prevBtn.addEventListener('click', () => slide('prev'));
            updateNavButtons();
        });
    </script>
</body>

</html>