<?php
session_start();
include 'db.php'; 

$jadwal_list = [];
$error_message_jadwal = null;

try {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'pengguna') {
        header("Location: login.php?error=auth_required_schedule");
        exit();
    }

    if (!isset($conn) || !is_object($conn) || $conn->connect_error) {
        throw new Exception("Koneksi ke basis data gagal.");
    }

    $id_pengguna_session = $_SESSION['user_id'];
    $nama_pengguna_login = $_SESSION['nama'] ?? 'Pengguna';
    $foto_profil_pengguna_login = $_SESSION['foto_profil'] ?? 'images/default_user_avatar.png';

    $sql_jadwal = "
        SELECT * FROM (
            SELECT * FROM (
                SELECT pl.id_permintaan, pl.tanggal_les_diajukan, pl.jam_les_diajukan,
                       pl.lokasi_les_diajukan, pl.jenjang_les, pl.keahlian_les,
                       pl.harga_saat_booking, pl.status_permintaan, pl.id_pengajar,
                       p.nama_pengajar, p.no AS no_pengajar
                FROM permintaan_les AS pl
                JOIN pengajar AS p ON pl.id_pengajar = p.id_pengajar
                WHERE pl.id_pengguna = ? AND pl.status_permintaan IN ('diterima', 'selesai')
            ) AS semua_item
            ORDER BY FIELD(status_permintaan, 'diterima', 'selesai'), tanggal_les_diajukan DESC, jam_les_diajukan DESC
            LIMIT 2
        ) AS dua_item_teratas
        ORDER BY FIELD(status_permintaan, 'diterima', 'selesai'), tanggal_les_diajukan DESC, jam_les_diajukan DESC
    ";

    $stmt_jadwal = $conn->prepare($sql_jadwal);
    if ($stmt_jadwal === false) {
        throw new Exception("Query jadwal gagal. Error: " . $conn->error);
    }

    $stmt_jadwal->bind_param("i", $id_pengguna_session);
    $stmt_jadwal->execute();
    $result_jadwal = $stmt_jadwal->get_result();
    while ($row = $result_jadwal->fetch_assoc()) {
        $jadwal_list[] = $row;
    }
    $stmt_jadwal->close();
    
    if (isset($conn) && is_object($conn) && !$conn->connect_error && $conn->ping()) {
        $conn->close();
    }
} catch (Exception $e) {
    $error_message_jadwal = "Terjadi kesalahan sistem saat mengambil data jadwal Anda.";
    error_log("Error pada jadwalpengguna.php: " . $e->getMessage());
}

if (!function_exists('tampilkan_harga')) {
    function tampilkan_harga($harga, $placeholder = 'N/A') {
        if ($harga !== null && is_numeric($harga)) {
            return 'Rp ' . number_format($harga, 0, ',', '.');
        }
        return $placeholder;
    }
}
if (!function_exists('format_tanggal_badge')) {
    function format_tanggal_badge($tanggal_sql) {
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
if (!function_exists('format_jam_wib')) {
    function format_jam_wib($jam_sql) {
        if (empty($jam_sql)) return 'N/A';
        return (new DateTime($jam_sql))->format('H:i') . ' WIB';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Pembelajaran - <?php echo htmlspecialchars($nama_pengguna_login); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Roboto', sans-serif; background-color: #fdfaf6; color: #213555; }
        .app-container { display: flex; width: 100%; flex-direction: column; align-items: flex-start; min-height: 100vh; }
        .navbar { background-color: #fdfaf6; width: 100%; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); position: fixed; top: 0; left: 0; z-index: 1000; }
        .navbar-content { display: flex; padding: 10px 20px; align-items: center; justify-content: center; max-width: 1200px; margin: 0 auto; }
        .navbar-left { display: flex; align-items: center; gap: 32px; }
        .navbar-logo-button { border: none; background: none; cursor: pointer; padding: 0; height: 50px; }
        .navbar-logo-image { width: 50px; height: auto; display: block; }
        .navbar-link { color: #213555; font-size: 16px; font-weight: 700; text-decoration: none; padding: 8px 12px; border-radius: 6px; transition: background-color 0.3s ease; }
        .navbar-divider { height: 1px; background-color: #e0e5ec; width: 100%; }
        .main-content { display: flex; flex-grow: 1; padding: 100px 40px 40px 40px; flex-direction: column; align-items: center; gap: 30px; width: 100%; background-color: #f5efe7; }
        .content-wrapper { display: flex; flex-direction: column; align-items: center; gap: 30px; width: 100%; max-width: 1100px; background-color: #fdfaf6; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07); }
        .schedule-section { display: flex; width: 100%; flex-direction: column; align-items: flex-start; border-radius: 16px; background-color: #ffffff; border: 1px solid #e0e5ec; box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06); overflow: hidden; }
        .schedule-container { width: 100%; }
        .schedule-header { display: flex; padding: 20px 25px; align-items: center; justify-content: space-between; gap: 20px; width: 100%; background-color: #f8f9fa; border-bottom: 1px solid #e0e5ec; }
        .header-content { display: flex; flex-direction: column; align-items: flex-start; gap: 4px; flex: 1; }
        .section-title { font-weight: 700; font-size: 22px; color: #213555; line-height: 1.3; }
        .section-description { font-weight: 400; font-size: 14px; color: #455a64; line-height: 1.5; }
        .schedule-list { display: flex; flex-direction: column; align-items: flex-start; width: 100%; }
        .schedule-card { display: flex; padding: 18px 25px; align-items: center; gap: 20px; width: 100%; border-bottom: 1px solid #e8e8e8; }
        .schedule-card:last-child { border-bottom: none; }
        .message-box { padding: 30px 25px; text-align: center; color: #586a7e; font-size: 1.0em; width: 100%; }
        .schedule-content { display: flex; align-items: center; gap: 20px; flex: 1; }
        .date-badge { display: flex; min-width: 85px; max-width: 85px; padding: 10px 8px; flex-direction: column; justify-content: center; align-items: center; border-radius: 10px; background-color: #213555; color: #fff; text-align: center; flex-shrink: 0; }
        .schedule-details { display: flex; flex-direction: column; align-items: flex-start; gap: 8px; flex: 1; overflow: hidden; }
        .teacher-name { font-weight: 700; font-size: 17px; color: #2c3e50; }
        .contact-info { display: flex; align-items: center; gap: 6px; }
        .phone-icon { width: 14px; height: 14px; }
        .phone-number { font-size: 13px; color: #3E5879; }
        .location-info { color: #586a7e; font-size: 12.5px; font-weight: 400; line-height: 1.5; }
        .location-info span { display: block; }
        .lesson-info { font-weight: 500; font-size: 13px; color: #34495e; background-color: #ecf0f1; padding: 4px 8px; border-radius: 4px; display: inline-block; }
        .status-display { display: flex; flex-direction: row; align-items: center; gap: 10px; }
        .status-button { display: flex; padding: 8px 18px; min-width: 110px; align-items: center; justify-content: center; border-radius: 20px; border: none; font-weight: 700; font-size: 13px; color: #fff; transition: background-color 0.3s ease, opacity 0.3s ease; }
        .status-button.berlangsung { background-color: #213555; cursor: pointer; }
        .status-button.berlangsung:hover { background-color: #2f4a7a; }
        .status-button.selesai { background-color: #808080; cursor: default; }
        .status-button:disabled { opacity: 0.7; cursor: not-allowed; }

        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(5px); display: none; align-items: center; justify-content: center; z-index: 2000; opacity: 0; transition: opacity 0.3s ease; }
        .modal-overlay.show { opacity: 1; }
        .modal-container { background: #fff; border-radius: 16px; padding: 24px; width: 90%; max-width: 480px; text-align: center; transform: scale(0.95); transition: transform 0.3s ease; position: relative; }
        .modal-overlay.show .modal-container { transform: scale(1); }
        .modal-close-button { position: absolute; top: 16px; right: 16px; background: none; border: none; font-size: 28px; color: #aaa; cursor: pointer; line-height: 1; }
        
        .rating-modal-content { padding: 32px; }
        .modal-title { font-size: 22px; font-weight: 700; color: #213555; margin-bottom: 12px; }
        .modal-message { font-size: 15px; color: #213555; margin-bottom: 24px; line-height: 1.5; }

        .rating-stars-modal { display: flex; justify-content: center; gap: 8px; margin: 20px 0; }
        .rating-star-modal { width: 32px; height: 32px; cursor: pointer; filter: grayscale(100%); opacity: 0.7; transition: all 0.2s ease; }
        .rating-stars-modal:hover .rating-star-modal { opacity: 0.5; }
        .rating-star-modal:hover, .rating-star-modal.active { filter: grayscale(0%); transform: scale(1.15); opacity: 1; }
        textarea.review-input { width: 100%; padding: 12px; border: 1px solid #e0e5ec; border-radius: 8px; min-height: 100px; font-family: 'Roboto', sans-serif; font-size: 15px; resize: vertical; margin-top: 10px; }
        .modal-button-submit { width: 100%; background-color: #213555; color: white; padding: 14px; border-radius: 34px; font-size: 16px; font-weight: 700; cursor: pointer; }
        .modal-button-submit:disabled { background-color: #95a5a6; cursor: not-allowed; }

        #toast-container { position: fixed; top: 20px; right: 20px; z-index: 3000; display: flex; flex-direction: column; gap: 10px; }
        .toast { padding: 12px 18px; border-radius: 8px; color: #fff; font-size: 14px; font-weight: 500; box-shadow: 0 4px 12px rgba(0,0,0,0.15); opacity: 0; transform: translateX(100%); animation: slideIn 0.5s forwards, fadeOut 0.5s 4s forwards; }
        .toast--error { background-color: #e74c3c; }
        @keyframes slideIn { to { opacity: 1; transform: translateX(0); } }
        @keyframes fadeOut { to { opacity: 0; transform: translateX(100%); } }
    </style>
</head>
<body>
    <div class="app-container">
        <nav class="navbar">
            <div class="navbar-content">
                <div class="navbar-left">
                    <a href="dashboardpengguna.php" class="navbar-logo-button" aria-label="Beranda">
                        <img src="images/logo.png" alt="Logo Perusahaan" class="navbar-logo-image" />
                    </a>
                    <a href="dashboardpengguna.php" class="navbar-link">Beranda</a>
                    <a href="pengajar.php" class="navbar-link">Pengajar</a>
                    <a href="jadwalpengguna.php" class="navbar-link active">Jadwal</a>
                    <a href="akunpengguna.php" class="navbar-link">Akun</a>
                    <a href="logout.php" class="navbar-link">Keluar Akun</a>
                </div>
            </div>
            <div class="navbar-divider"></div>
        </nav>
        <main class="main-content">
            <div class="content-wrapper">
                <section class="schedule-section">
                    <div class="schedule-container">
                        <header class="schedule-header">
                            <div class="header-content">
                                <h1 class="section-title">Jadwal Pembelajaran</h1>
                                <p class="section-description">Menampilkan Jadwal Pembelajaran Anda</p>
                            </div>
                        </header>
                        <div class="schedule-list">
                            <?php if ($error_message_jadwal): ?>
                                <div class="message-box"><p><?php echo htmlspecialchars($error_message_jadwal); ?></p></div>
                            <?php elseif (empty($jadwal_list)): ?>
                                <div class="message-box"><p>Tidak ada jadwal untuk ditampilkan.</p></div>
                            <?php else: ?>
                                <?php foreach ($jadwal_list as $jadwal): ?>
                                    <?php
                                    $tanggal_badge = format_tanggal_badge($jadwal['tanggal_les_diajukan']);
                                    $jam_les = format_jam_wib($jadwal['jam_les_diajukan']);
                                    $harga_les = tampilkan_harga($jadwal['harga_saat_booking']);
                                    ?>
                                    <article class="schedule-card">
                                        <div class="schedule-content">
                                            <div class="date-badge">
                                                <span class="date-day"><?php echo htmlspecialchars($tanggal_badge['day']); ?></span>
                                                <span class="date-number"><?php echo htmlspecialchars($tanggal_badge['number']); ?></span>
                                                <span class="date-month"><?php echo htmlspecialchars($tanggal_badge['month_year']); ?></span>
                                            </div>
                                            <div class="schedule-details">
                                                <div class="teacher-name"><?php echo htmlspecialchars($jadwal['nama_pengajar']); ?></div>
                                                <?php if (!empty($jadwal['no_pengajar'])): ?>
                                                    <div class="contact-info">
                                                        <img src="images/wa.png" alt="Telepon" class="phone-icon">
                                                        <span class="phone-number"><?php echo htmlspecialchars($jadwal['no_pengajar']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="location-info">
                                                    <span>Diajukan di: <?php echo nl2br(htmlspecialchars($jadwal['lokasi_les_diajukan'])); ?></span>
                                                    <span>Waktu Les: <?php echo htmlspecialchars($jam_les); ?></span>
                                                </div>
                                                <span class="lesson-info">
                                                    <?php echo htmlspecialchars($jadwal['jenjang_les']); ?> -
                                                    <?php echo htmlspecialchars($jadwal['keahlian_les']); ?> |
                                                    <?php echo htmlspecialchars($harga_les); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="status-display">
                                            <?php if ($jadwal['status_permintaan'] == 'diterima'): ?>
                                                <button class="status-button berlangsung"
                                                        data-id="<?php echo $jadwal['id_permintaan']; ?>"
                                                        data-id-pengajar="<?php echo $jadwal['id_pengajar']; ?>"
                                                        data-nama-pengajar="<?php echo htmlspecialchars($jadwal['nama_pengajar']); ?>">
                                                    Berlangsung
                                                </button>
                                            <?php elseif ($jadwal['status_permintaan'] == 'selesai'): ?>
                                                <button class="status-button selesai" disabled>Selesai</button>
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

    <div class="modal-overlay" id="ratingModal">
        <div class="modal-container rating-modal-content">
            <button class="modal-close-button" data-close>&times;</button>
            <h2 class="modal-title">Berikan Rating untuk Pengajar</h2>
            <p class="modal-message">Bagikan pengalaman Anda dengan <strong id="modalTeacherName"></strong>.</p>
            <div class="rating-stars-modal">
                <img src="images/star.svg" alt="Star 1" class="rating-star-modal" data-rating="1" />
                <img src="images/star.svg" alt="Star 2" class="rating-star-modal" data-rating="2" />
                <img src="images/star.svg" alt="Star 3" class="rating-star-modal" data-rating="3" />
                <img src="images/star.svg" alt="Star 4" class="rating-star-modal" data-rating="4" />
                <img src="images/star.svg" alt="Star 5" class="rating-star-modal" data-rating="5" />
            </div>
            <textarea class="review-input" id="reviewText" placeholder="Tulis ulasan Anda di sini... (opsional)"></textarea>
            <br>
            <br>
            <button class="modal-button-submit" id="submitRatingButton">Kirim Penilaian</button>
        </div>
    </div>
    
    <div id="toast-container"></div>

    <script>
document.addEventListener('DOMContentLoaded', function() {
    const ratingModal = document.getElementById('ratingModal');
    const submitRatingButton = document.getElementById('submitRatingButton');
    
    let activeLessonData = {};
    let currentRating = 0;

    document.querySelectorAll('.status-button.berlangsung').forEach(button => {
        button.addEventListener('click', function() {
            activeLessonData = { ...this.dataset };
            document.getElementById('modalTeacherName').textContent = activeLessonData.namaPengajar;
            showModal(ratingModal);
        });
    });

    submitRatingButton.addEventListener('click', function() {
        if (currentRating === 0) {
            showToast('Harap berikan minimal 1 bintang rating.', 'error');
            return;
        }

        const reviewValue = document.getElementById('reviewText').value;
        this.disabled = true;
        this.textContent = 'Mengirim...';

        const payload = {
            id_les: activeLessonData.id,
            id_pengajar: activeLessonData.idPengajar,
            rating: currentRating,
            review: reviewValue
        };

        fetch('proses_penilaian.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(response => {
            if (!response.ok) {
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    return response.json().then(errorData => {
                        throw new Error(errorData.message || 'Terjadi kesalahan saat memproses penilaian.');
                    });
                } else {
                    throw new Error('Terjadi kesalahan yang tidak diketahui. Respon bukan JSON.');
                }
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                hideModal(ratingModal);
                location.reload(); 
            } else {
                throw new Error(data.message || 'Gagal menyimpan penilaian.');
            }
        })
        .catch(error => {
            showToast(error.message, 'error');
            this.disabled = false;
            this.textContent = 'Kirim Penilaian';
        });
    });
    
    function showModal(modal) {
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('show'), 10);
    }

    function hideModal(modal) {
        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
            resetRatingModal();
        }, 300);
    }

    document.querySelectorAll('[data-close], .modal-overlay').forEach(el => {
        el.addEventListener('click', function(event) {
            if (event.target === this || event.target.hasAttribute('data-close')) {
                hideModal(this.closest('.modal-overlay'));
            }
        });
    });

    function resetRatingModal() {
        currentRating = 0;
        document.querySelectorAll('.rating-star-modal.active').forEach(s => s.classList.remove('active'));
        document.getElementById('reviewText').value = '';
        submitRatingButton.disabled = false;
        submitRatingButton.textContent = 'Kirim Penilaian';
    }

    function showToast(message, type = 'error') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast toast--${type}`;
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 4500);
    }

    document.querySelectorAll('.rating-star-modal').forEach(star => {
        star.addEventListener('click', function() {
            const rating = parseInt(this.dataset.rating);
            currentRating = rating;

            document.querySelectorAll('.rating-star-modal').forEach(s => {
                s.classList.toggle('active', parseInt(s.dataset.rating) <= rating);
            });
        });
    });
});
</script>
</body>
</html>