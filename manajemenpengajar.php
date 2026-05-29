<?php
session_start();

include 'db.php'; 

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'pengajar') {
    header("Location: login.php?error=auth_required_teacher_management");
    exit();
}

$id_pengajar_session = $_SESSION['user_id'];
$nama_pengajar_login = $_SESSION['nama'] ?? 'Pengajar';

$permintaan_les_list = [];
$error_message_manajemen = null;
$has_active_lesson = false;

if (isset($conn) && is_object($conn)) {
    $sql_permintaan = "
        (SELECT id_permintaan, nama_pemesan_les, keahlian_les, jenjang_les,
                lokasi_les_diajukan, tanggal_les_diajukan, jam_les_diajukan, harga_saat_booking,
                status_permintaan, tanggal_pemesanan
        FROM permintaan_les
        WHERE id_pengajar = ? AND status_permintaan = 'diterima'
        ORDER BY tanggal_les_diajukan ASC, jam_les_diajukan ASC
        LIMIT 1)

        UNION ALL

        (SELECT id_permintaan, nama_pemesan_les, keahlian_les, jenjang_les,
                lokasi_les_diajukan, tanggal_les_diajukan, jam_les_diajukan, harga_saat_booking,
                status_permintaan, tanggal_pemesanan
        FROM permintaan_les
        WHERE id_pengajar = ? AND status_permintaan = 'pending'
        ORDER BY tanggal_pemesanan ASC, tanggal_les_diajukan ASC, jam_les_diajukan ASC
        LIMIT 2)

        UNION ALL

        (SELECT id_permintaan, nama_pemesan_les, keahlian_les, jenjang_les,
                lokasi_les_diajukan, tanggal_les_diajukan, jam_les_diajukan, harga_saat_booking,
                status_permintaan, tanggal_pemesanan
        FROM permintaan_les
        WHERE id_pengajar = ? AND status_permintaan = 'selesai'
        ORDER BY tanggal_les_diajukan DESC, jam_les_diajukan DESC
        LIMIT 2)
    ";

    $stmt_permintaan = $conn->prepare($sql_permintaan);
    
    if ($stmt_permintaan) {
        $stmt_permintaan->bind_param("iii", $id_pengajar_session, $id_pengajar_session, $id_pengajar_session);
        $stmt_permintaan->execute();
        $result_permintaan = $stmt_permintaan->get_result();

        $permintaan_les_list = [];
        while ($row = $result_permintaan->fetch_assoc()) {
            $permintaan_les_list[] = $row;
            if ($row['status_permintaan'] === 'diterima') {
                $has_active_lesson = true;
            }
        }
        
        usort($permintaan_les_list, function($a, $b) {
            $status_order = ['diterima' => 1, 'pending' => 2, 'selesai' => 3];
            $order_a = $status_order[$a['status_permintaan']] ?? 99;
            $order_b = $status_order[$b['status_permintaan']] ?? 99;

            if ($order_a === $order_b) {
                $datetime_a = strtotime($a['tanggal_les_diajukan'] . ' ' . $a['jam_les_diajukan']);
                $datetime_b = strtotime($b['tanggal_les_diajukan'] . ' ' . $b['jam_les_diajukan']);
                if ($a['status_permintaan'] === 'selesai') {
                    return $datetime_b - $datetime_a;
                }
                return $datetime_a - $datetime_b;
            }
            return $order_a - $order_b;
        });

        $filtered_permintaan_les_list = array_slice($permintaan_les_list, 0, 2);

        $stmt_permintaan->close();
    } else {
        $error_message_manajemen = "Terjadi kesalahan sistem saat menyiapkan data permintaan Anda.";
        error_log("Manajemen Pengajar (ID: {$id_pengajar_session}): Gagal prepare statement - " . $conn->error);
    }
} else {
    $error_message_manajemen = "Koneksi ke basis data gagal.";
    error_log("Manajemen Pengajar: Koneksi database tidak tersedia.");
}


if (!function_exists('tampilkan_harga_manajemen')) {
    function tampilkan_harga_manajemen($harga, $placeholder = 'N/A') {
        if ($harga !== null && is_numeric($harga)) {
            return 'Rp ' . number_format($harga, 0, ',', '.');
        }
        return $placeholder;
    }
}
if (!function_exists('format_tanggal_badge_manajemen')) {
    function format_tanggal_badge_manajemen($tanggal_sql) {
        if (empty($tanggal_sql)) return ['day' => 'N/A', 'number' => '-', 'month_year' => 'Tidak Valid'];
        try {
            $date = new DateTime($tanggal_sql);
            $hariIndonesia = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            $bulanIndonesia = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];
            return ['day' => $hariIndonesia[(int)$date->format('w')], 'number' => $date->format('d'), 'month_year' => $bulanIndonesia[(int)$date->format('n')] . ' ' . $date->format('Y')];
        } catch (Exception $e) { return ['day' => 'Error', 'number' => '!', 'month_year' => 'Format Tgl']; }
    }
}
if (!function_exists('format_jam_wib_manajemen')) {
    function format_jam_wib_manajemen($jam_sql) {
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
    <title>Manajemen Permintaan Les - <?php echo htmlspecialchars($nama_pengajar_login); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Roboto', sans-serif; background-color: #fdfaf6; color: #213555; }
        .app-container { display: flex; width: 100%; flex-direction: column; align-items: flex-start; min-height: 100vh; }
        .navigation { background-color: #fdfaf6; width: 100%; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); position: fixed; top: 0; left: 0; z-index: 1000; }
        .nav-content { display: flex; padding: 10px 20px; align-items: center; justify-content: center; max-width: 1200px; margin: 0 auto; }
        .nav-left { display: flex; align-items: center; gap: 32px; }
        .logo-button { border-radius: 8px; padding: 0; border: none; background: none; cursor: pointer; height: 52px; display: flex; align-items: center; justify-content: center; }
        .logo-image { width: 50px; height: auto; display: block; }
        .nav-link { color: #213555; font-size: 16px; font-weight: 700; text-decoration: none; padding: 8px 12px; border-radius: 6px; transition: background-color 0.3s ease, color 0.3s ease; }
        .nav-divider { height: 1px; background-color: #e0e5ec; width: 100%; }
        .main-content { display: flex; flex-grow: 1; padding: 100px 40px 40px 40px; flex-direction: column; align-items: center; gap: 30px; width: 100%; background-color: #f5efe7; }
        .content-wrapper { display: flex; flex-direction: column; align-items: center; gap: 30px; width: 100%; max-width: 1100px; background-color: #fdfaf6; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.07); }
        .request-section { display: flex; width: 100%; flex-direction: column; align-items: flex-start; border-radius: 16px; background-color: #ffffff; border: 1px solid #e0e5ec; box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06); overflow: hidden; }
        .request-container { width: 100%; }
        .request-header { display: flex; padding: 20px 25px; align-items: center; justify-content: space-between; gap: 20px; width: 100%; background-color: #f8f9fa; border-bottom: 1px solid #e0e5ec; }
        .header-content { display: flex; flex-direction: column; align-items: flex-start; gap: 4px; flex: 1; }
        .section-title { font-weight: 700; font-size: 22px; color: #213555; line-height: 1.3; }
        .section-description { font-weight: 400; font-size: 14px; color: #455a64; line-height: 1.5; }
        .request-list { display: flex; flex-direction: column; align-items: flex-start; width: 100%; }
        .request-card { display: flex; padding: 18px 25px; align-items: center; gap: 20px; width: 100%; border-bottom: 1px solid #e8e8e8; transition: background-color 0.2s ease, opacity 0.5s ease, transform 0.5s ease; }
        .request-card.fade-out { opacity: 0; transform: scale(0.95); }
        .request-card:hover { background-color: #f7f4f0; }
        .request-card:last-child { border-bottom: none; }
        .no-requests-message { padding: 30px 25px; text-align: center; color: #586a7e; font-size: 1.0em; width: 100%; }
        .request-content { display: flex; align-items: center; gap: 20px; flex: 1; }
        .date-badge { display: flex; min-width: 85px; max-width: 85px; padding: 10px 8px; flex-direction: column; justify-content: center; align-items: center; border-radius: 10px; background-color: #213555; color: #fff; text-align: center; flex-shrink: 0; }
        .request-details { display: flex; flex-direction: column; align-items: flex-start; gap: 6px; flex: 1; overflow: hidden; }
        .student-name { font-weight: 700; font-size: 17px; color: #2c3e50; }
        .location-info { color: #586a7e; font-size: 12.5px; font-weight: 400; line-height: 1.5; }
        .location-info span { display: block; }
        .lesson-info { font-weight: 500; font-size: 13px; color: #34495e; background-color: #ecf0f1; padding: 4px 8px; border-radius: 4px; display: inline-block; margin-top: 2px; }
        .action-buttons { display: flex; flex-direction: row; align-items: center; gap: 10px; }
        .btn { display: flex; padding: 8px 18px; min-width: 90px; align-items: center; justify-content: center; border-radius: 20px; border: none; font-weight: 500; font-size: 13px; color: #fff; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 3px 6px rgba(0,0,0,0.15); }
        .btn:active { transform: translateY(0px); box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .btn--accept { background-color: #213555; }
        .btn--accept:hover { background-color: #2c4a7a; }
        .btn--reject { background-color: #c0392b; }
        .btn--reject:hover { background-color: #d64541; }
        .btn--running, .btn--finished, .btn--disabled { 
            background-color: #808080;
            color: #ffffff;
            cursor: not-allowed;
        }
        .btn--running:hover, .btn--finished:hover, .btn--disabled:hover { 
            background-color: #707070;
            transform: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .modal-overlay { 
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(5px); 
            display: none; align-items: center; justify-content: center; 
            z-index: 2000; opacity: 0; transition: opacity 0.3s ease; 
        }
        .modal-overlay.show { opacity: 1; }
        .modal-container { 
            background: #fff; border-radius: 12px; padding: 24px; 
            width: 90%; max-width: 400px; text-align: center; 
            transform: scale(0.95); transition: transform 0.3s ease; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .modal-overlay.show .modal-container { transform: scale(1); }
        .modal-title { font-size: 20px; font-weight: 700; color: #2c3e50; margin-bottom: 12px; }
        .modal-message { font-size: 15px; color: #455a64; margin-bottom: 24px; line-height: 1.5; }
        .modal-button-group { display: flex; gap: 12px; justify-content: center; }
        .modal-button-secondary, .modal-button-primary { 
            flex: 1; padding: 10px; border: none; border-radius: 24px; 
            font-size: 15px; font-weight: 500; cursor: pointer; transition: background-color 0.2s; 
        }
        .modal-button-secondary { background-color: #ecf0f1; color: #34495e; }
        .modal-button-secondary:hover { background-color: #dbe0e2; }
        .modal-button-primary { background-color: #c0392b; color: #fff; }
        .modal-button-primary.accept { background-color: #213555; }

        #alertModal {
            background: rgba(0, 0, 0, 0.4);
        }

        #alertModal .modal-container {
            background-color: #FFFFFF;
            border: 1px solid #E6E8EC;
            color: #213555;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }

        #alertModal .modal-icon {
            font-size: 60px;
            margin-bottom: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 20px auto;
            transition: all 0.3s ease;
        }
        
        #alertModal .modal-icon.warning {
            color: #e74c3c;
            border: 3px solid #e74c3c;
        }

        #alertModal .modal-icon.success {
            color: #2ecc71;
            border: 3px solid #2ecc71;
        }

        #alertModal .modal-icon i {
             line-height: 1;
        }

        #alertModal .modal-title {
            font-size: 24px;
            font-weight: 700;
            color: #213555;
            margin-bottom: 10px;
        }

        #alertModal .modal-message {
            font-size: 16px;
            color: #586a7e;
            margin-bottom: 30px;
        }

        #alertModal .modal-button-single {
            background-color: #213555;
            color: #fff;
            padding: 12px 25px;
            border-radius: 25px;
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 700;
            transition: background-color 0.3s ease;
            width: 100%;
            max-width: 200px;
            margin: 0 auto;
            display: block;
        }

        #alertModal .modal-button-single:hover {
            background-color: #3E5879;
        }

        @media (max-width: 768px) {
            .main-content { padding: 90px 20px 20px 20px; }
            .content-wrapper { padding: 15px; }
            .request-card { flex-direction: column; align-items: stretch; padding: 15px; }
            .action-buttons { flex-direction: row; justify-content: space-around; width: 100%; margin-top: 15px; }
            .btn { flex-grow: 1; margin: 0 5px; }
            
            #alertModal .modal-container {
                padding: 20px;
            }
            #alertModal .modal-icon {
                font-size: 50px;
                width: 70px;
                height: 70px;
            }
            #alertModal .modal-title {
                font-size: 20px;
            }
            #alertModal .modal-message {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <nav class="navigation">
             <div class="nav-content">
                <div class="nav-left">
                    <a href="dashboardpengajar.php" class="logo-button" aria-label="Beranda">
                        <img src="images/logo.png" alt="Logo Perusahaan" class="logo-image" />
                    </a>
                    <a href="dashboardpengajar.php" class="nav-link">Beranda</a>
                    <a href="manajemenpengajar.php" class="nav-link active">Manajemen</a>
                    <a href="akunpengajar.php" class="nav-link">Akun</a>
                    <a href="logout.php" class="nav-link">Keluar Akun</a>
                </div>
            </div>
            <div class="nav-divider"></div>
        </nav>
        <main class="main-content">
            <div class="content-wrapper">
                <section class="request-section">
                    <div class="request-container">
                        <header class="request-header">
                            <div class="header-content">
                                <h1 class="section-title">Daftar Permintaan Les Baru</h1>
                                <p class="section-description">Lihat daftar permintaan les yang masuk dari siswa.</p>
                            </div>
                        </header>
                        <div class="request-list">
                            <?php if ($error_message_manajemen): ?>
                                <div class="no-requests-message"><p><?php echo htmlspecialchars($error_message_manajemen); ?></p></div>
                            <?php elseif (empty($filtered_permintaan_les_list)): ?> 
                                <div class="no-requests-message"><p>Tidak ada jadwal untuk ditampilkan.</p></div>
                            <?php else: ?>
                                <?php foreach ($filtered_permintaan_les_list as $permintaan): ?> 
                                    <?php
                                        $tanggal_badge = format_tanggal_badge_manajemen($permintaan['tanggal_les_diajukan']);
                                        $jam_les = format_jam_wib_manajemen($permintaan['jam_les_diajukan']);
                                        $harga_les = tampilkan_harga_manajemen($permintaan['harga_saat_booking']);
                                    ?>
                                    <article class="request-card" data-id-permintaan="<?php echo $permintaan['id_permintaan']; ?>">
                                        <div class="request-content">
                                            <div class="date-badge">
                                                <span class="date-day"><?php echo htmlspecialchars($tanggal_badge['day']); ?></span>
                                                <span class="date-number"><?php echo htmlspecialchars($tanggal_badge['number']); ?></span>
                                                <span class="date-month"><?php echo htmlspecialchars($tanggal_badge['month_year']); ?></span>
                                            </div>
                                            <div class="request-details">
                                                <div class="student-name"><?php echo htmlspecialchars($permintaan['nama_pemesan_les']); ?></div>
                                                <div class="location-info">
                                                    <span>Lokasi Pengajar: <?php echo nl2br(htmlspecialchars($permintaan['lokasi_les_diajukan'])); ?></span>
                                                    <span>Waktu Les: <?php echo htmlspecialchars($jam_les); ?></span>
                                                </div>
                                                <span class="lesson-info">
                                                    <?php echo htmlspecialchars($permintaan['jenjang_les']); ?> -
                                                    <?php echo htmlspecialchars($permintaan['keahlian_les']); ?> |
                                                    <?php echo htmlspecialchars($harga_les); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="action-buttons">
                                            <?php if ($permintaan['status_permintaan'] == 'pending'): ?>
                                                <a href="#" class="btn btn--accept action-btn" data-aksi="terima" data-id="<?php echo $permintaan['id_permintaan']; ?>" data-initial-status="pending">Terima</a>
                                                <a href="#" class="btn btn--reject action-btn" data-aksi="tolak" data-id="<?php echo $permintaan['id_permintaan']; ?>" data-initial-status="pending">Tolak</a>
                                            <?php elseif ($permintaan['status_permintaan'] == 'diterima'): ?>
                                                <button class="btn btn--running" disabled>Berjalan</button>
                                            <?php elseif ($permintaan['status_permintaan'] == 'selesai'): ?>
                                                <button class="btn btn--finished" disabled>Selesai</button>
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

    <div id="alertModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-icon">
                <i class="fas fa-exclamation-circle"></i> 
            </div>
            <h2 class="modal-title" id="alertModalTitle">Perhatian!</h2> 
            <p class="modal-message" id="alertMessage"></p>
            <button id="alertCloseButton" class="modal-button-single">Oke</button>
        </div>
    </div>

    <?php
        if (isset($conn) && is_object($conn) && $conn->ping()) {
            $conn->close();
        }
    ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const requestList = document.querySelector('.request-list');
        
        const alertModal = document.getElementById('alertModal');
        const alertModalTitle = document.getElementById('alertModalTitle');
        const alertMessage = document.getElementById('alertMessage');
        const alertIcon = alertModal.querySelector('.modal-icon i'); 
        const alertIconContainer = alertModal.querySelector('.modal-icon'); 
        const alertCloseButton = document.getElementById('alertCloseButton');

        const hasActiveLesson = <?php echo json_encode($has_active_lesson); ?>; 

        function manageAcceptButtons(disableAll = false) {
            const acceptButtons = document.querySelectorAll('.action-btn[data-aksi="terima"][data-initial-status="pending"]');
            
            if (disableAll || hasActiveLesson) {
                acceptButtons.forEach(button => {
                    button.classList.add('btn--disabled');
                    button.setAttribute('disabled', 'disabled');
                });
            } else {
                acceptButtons.forEach(button => {
                    button.classList.remove('btn--disabled');
                    button.removeAttribute('disabled');
                });
            }
        }

        manageAcceptButtons();

        requestList.addEventListener('click', function(event) {
            const actionButton = event.target.closest('.action-btn');
            if (!actionButton) return;

            if (actionButton.hasAttribute('disabled')) {
                showAlertModal("Anda memiliki satu les yang sedang berjalan. Anda tidak dapat menerima les lain sebelum les tersebut selesai.", "Perhatian!", 'warning');
                return;
            }

            event.preventDefault();

            const aksi = actionButton.dataset.aksi;
            const idPermintaan = actionButton.dataset.id;
            const card = actionButton.closest('.request-card');
            
            executeAction(idPermintaan, aksi, card);
        });

        function executeAction(id, aksi, card) {
            fetch(`aksi_permintaan.php?id_permintaan=${id}&aksi=${aksi}`)
                .then(response => {
                    if (!response.ok) throw new Error('Respons jaringan tidak baik.');
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success') {
                        updateCardUI(card, aksi);
                        if (aksi === 'terima') {
                            manageAcceptButtons(true);
                            showAlertModal("Permintaan les berhasil diterima. Semua permintaan 'Terima' lainnya telah dinonaktifkan.", "Peringatan", 'warning');
                        } else if (aksi === 'tolak') { 
                            showAlertModal("Permintaan les berhasil ditolak.", "Sukses!", 'success'); 
                        }
                    } else {
                        showAlertModal(data.message || 'Gagal memperbarui status.', "Ooppss!", 'warning');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlertModal('Terjadi kesalahan saat menghubungi server.', "Ooppss!", 'warning');
                });
        }

        function updateCardUI(card, aksi) {
            const currentButtons = card.querySelector('.action-buttons');
            if (aksi === 'terima') {
                currentButtons.innerHTML = '<button class="btn btn--running" disabled>Berjalan</button>';
            } else if (aksi === 'tolak') {
                card.classList.add('fade-out');
                setTimeout(() => {
                    card.remove();
                    const remainingCards = requestList.querySelectorAll('.request-card');
                    if (remainingCards.length === 0) {
                        requestList.innerHTML = '<div class="no-requests-message"><p>Tidak ada jadwal untuk ditampilkan.</p></div>';
                    }
                }, 500);
            }
        }
        
        function hideModal(modalElement) {
            modalElement.classList.remove('show');
            setTimeout(() => {
                modalElement.style.display = 'none';
            }, 300);
        }

        function showAlertModal(message, title = "Informasi", type = 'warning') {
            alertModalTitle.textContent = title;
            alertMessage.textContent = message;
            
            alertIconContainer.classList.remove('warning', 'success');
            alertIcon.classList.remove('fa-exclamation-circle', 'fa-check-circle');

            if (type === 'success') {
                alertIcon.classList.add('fa-check-circle');
                alertIconContainer.classList.add('success');
            } else { 
                alertIcon.classList.add('fa-exclamation-circle');
                alertIconContainer.classList.add('warning');
            }

            alertModal.style.display = 'flex';
            setTimeout(() => alertModal.classList.add('show'), 10);
        }

        alertCloseButton.addEventListener('click', () => hideModal(alertModal));
        alertModal.addEventListener('click', (event) => {
            if (event.target === alertModal) {
                hideModal(alertModal);
            }
        });
    });
    </script>
</body>
</html>