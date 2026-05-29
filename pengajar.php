<?php
session_start();
include 'db.php'; 

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'pengguna') {
    header("Location: login.php");
    exit();
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

$teachers = [];
if (isset($conn) && is_object($conn)) {

    $sql = "SELECT id_pengajar, nama_pengajar, foto_profil, keahlian, jenjang_keahlian, lokasi, harga_layanan, rating
            FROM pengajar
            WHERE 1=1";

    $params = [];
    $types = '';

    if (isset($_GET['lokasi']) && !empty(trim($_GET['lokasi']))) {
        $lokasi_keyword = trim($_GET['lokasi']);
        $sql .= " AND lokasi LIKE ?";
        $types .= 's';
        $params[] = "%" . $lokasi_keyword . "%";
    }

    if (isset($_GET['mapel']) && !empty(trim($_GET['mapel']))) {
        $mapel_keyword = trim($_GET['mapel']);
        $sql .= " AND keahlian = ?";
        $types .= 's';
        $params[] = $mapel_keyword;
    }

    if (isset($_GET['jenjang']) && !empty(trim($_GET['jenjang']))) {
        $jenjang_keyword = trim($_GET['jenjang']);
        $sql .= " AND jenjang_keahlian = ?";
        $types .= 's';
        $params[] = $jenjang_keyword;
    }

    $sql .= " ORDER BY nama_pengajar ASC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $teachers[] = $row;
            }
        }
        $stmt->close();
    }
    if (isset($conn) && is_object($conn) && !$conn->connect_error && $conn->ping()) {
        $conn->close();
    }
}

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    $teachers_for_json = [];
    foreach ($teachers as $teacher) {
        $teacher['harga_layanan_formatted'] = tampilkan_harga($teacher['harga_layanan']);
        $teacher['rating'] = (float)($teacher['rating'] ?? 0); 
        $teachers_for_json[] = $teacher;
    }
    echo json_encode($teachers_for_json);
    exit();
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Cari Pengajar - LesAja</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900&display=swap" rel="stylesheet" />
    <style>
        :root {
            --background-page: rgba(253, 250, 246, 1);
            --background-navbar: rgba(253, 250, 246, 1);
            --text-primary-original: rgba(33, 53, 85, 1);
            --text-muted: rgba(102, 102, 102, 1);
            --navbar-height: 70px;
            --text-color: #213555;
            --background-light: #FDFAF6;
            --background-white: #fdfaf6;
            --border-color: #ededed;
            --secondary-color: #3E5879;
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
            margin: 0;
            padding: 0;
        }

        .section-padding {
            padding: 20px 5%;
        }

        .container-padding {
            padding: 20px 5%;
        }

        .gradient-text {
            background: var(--text-primary-original);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
            -webkit-text-fill-color: transparent;
        }

        .gradient-text-gray {
            background: var(--text-muted);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
            -webkit-text-fill-color: transparent;
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

        .navbar-divider {
            height: 1px;
            background-color: var(--border-color);
            width: 100%;
        }

        #search-filter-section {
            background: rgba(253, 250, 246, 0.8);
        }

        #search-filter-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
            max-width: 1280px;
            margin: 0 auto;
        }

        .search-intro-text {
            font-size: 16px;
            font-weight: 700;
            line-height: 150%;
        }

        .filter-row {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        #location-search-wrapper {
            flex-grow: 1;
            min-width: 250px;
            display: flex;
            align-items: center;
            border: 1px solid var(--text-color);
            border-radius: 30px;
            padding: 0px 12px;
            height: 40px;
            background-color: #fff;
        }

        #location-search-wrapper img {
            width: 20px;
            height: 20px;
            margin-right: 10px;
        }

        #search-lokasi-input {
            border: none;
            outline: none;
            height: 100%;
            width: 100%;
            font-size: 16px;
            font-family: 'Roboto', sans-serif;
            background: transparent;
        }

        .filter-select {
            border: 1px solid var(--text-color);
            border-radius: 30px;
            padding: 0 35px 0 15px;
            height: 40px;
            font-size: 16px;
            font-weight: 700;
            font-family: 'Roboto', sans-serif;
            color: var(--text-color);
            background-color: #fff;
            cursor: pointer;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%23213555' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 1rem center;
            background-repeat: no-repeat;
            background-size: 1.2em;
        }

        .filter-options {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
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
            min-height: 480px;
        }

        .teacher-card {
            border: 1px solid var(--border-color);
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
            min-height: 480px;
        }

        .teacher-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.1);
        }

        .teacher-card-image-wrapper {
            position: relative;
            width: 100%;
            height: 220px;
            display: flex;
            justify-content: center;
            align-items: center;
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

        .card-image-placeholder-icon {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 100%;
            background-color: #e0e0e0;
            font-size: 40px;
            color: #a0a0a0;
            position: absolute;
            top: 0;
            left: 0;
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
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 2px;
            flex-grow: 1;
        }

        .teacher-card-name {
            margin-bottom: 0;
        }

        .teacher-card-name span {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-color);
        }

        .teacher-card-description {
            margin-block-start: 0;
            margin-block-end: 0;
        }

        .teacher-card-description span {
            color: var(--text-muted);
            font-size: 13px;
        }

        .teacher-card-rating {
            display: flex;
            flex-direction: row;
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
            transition: filter 0.2s ease;
        }
        
        .rating-star img.active {
            filter: grayscale(0%);
        }

        .rating-stars-container.no-rating .rating-star img {
            filter: grayscale(100%) brightness(2.5);
            opacity: 0.7;
        }
        
        .rating-number-text {
            display: none;
        }

        .teacher-card-select-button {
            align-self: flex-start;
            margin-top: auto;
            background: var(--text-color);
            border: 1px solid var(--text-color);
            border-radius: 25px;
            padding: 8px 18px;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.2s ease, color 0.2s ease;
        }

        .teacher-card-select-button span {
            color: var(--background-light);
            font-size: 14px;
            font-weight: 600;
        }

        .teacher-card-select-button:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
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
                    <a href="pengajar.php" class="navbar-link active">Pengajar</a>
                    <a href="jadwalpengguna.php" class="navbar-link">Jadwal</a>
                    <a href="akunpengguna.php" class="navbar-link">Akun</a>
                    <a href="logout.php" class="navbar-link">Keluar Akun</a>
                </div>
            </div>
            <div class="navbar-divider"></div>
        </nav>

        <main id="main-content">
            <section id="search-filter-section" class="section-padding">
                <div id="search-filter-container" class="container-padding">
                    <div id="search-header">
                        <span class="search-intro-text gradient-text">Cari Pengajar Sesuai Dengan Kebutuhanmu</span>
                    </div>
                    <div class="filter-row">
                        <div id="location-search-wrapper">
                            <img src="images/cari.svg" alt="Search Icon" />
                            <input type="text" id="search-lokasi-input" placeholder="Cari Lokasi">
                        </div>
                        <div class="filter-options">
                            <select id="search-mapel-select" class="filter-select">
                                <option value="">Mata Pelajaran</option>
                                <option value="Matematika">Matematika</option>
                                <option value="Fisika">Fisika</option>
                                <option value="Kimia">Kimia</option>
                                <option value="Biologi">Biologi</option>
                                <option value="Bahasa Inggris">Bahasa Inggris</option>
                                <option value="Bahasa Indonesia">Bahasa Indonesia</option>
                                <option value="Ekonomi">Ekonomi</option>
                                <option value="Sejarah">Sejarah</option>
                                <option value="Geografi">Geografi</option>
                                <option value="Sosiologi">Sosiologi</option>
                                <option value="Ilmu Pengetahuan Alam">Ilmu Pengetahuan Alam</option>
                            </select>
                            <select id="search-jenjang-select" class="filter-select">
                                <option value="">Jenjang Pendidikan</option>
                                <option value="SD">SD</option>
                                <option value="SMP">SMP</option>
                                <option value="SMA">SMA</option>
                            </select>
                        </div>
                    </div>
                </div>
            </section>

            <main class="teacher-recommendation-section">
                <div class="teacher-cards-container" id="teacher-cards-container">
                    <p style="text-align: center; width: 100%; color: #555; padding: 20px 0;">Memuat data pengajar...</p>
                </div>
            </main>
        </main>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('search-lokasi-input');
        const mapelSelect = document.getElementById('search-mapel-select');
        const jenjangSelect = document.getElementById('search-jenjang-select');
        const teacherContainer = document.getElementById('teacher-cards-container');

        function createTeacherCard(teacher) {
            let harga = 'N/A';
            if (teacher.harga_layanan && !isNaN(teacher.harga_layanan)) {
                harga = 'Rp ' + new Intl.NumberFormat('id-ID').format(teacher.harga_layanan);
            }

            let fotoSrc = teacher.foto_profil ? teacher.foto_profil + '?t=' + new Date().getTime() : 'images/default_teacher_avatar.png';
            const deskripsi = `Mengajar ${teacher.keahlian || ''} ${teacher.jenjang_keahlian || ''} | ${teacher.lokasi || ''} | ${harga}`;
            const linkPilih = `konfirmasi.php?id_pengajar=${teacher.id_pengajar}`;

            const ratingPengajar = teacher.rating ?? 0;
            const ratingBintangPenuh = Math.floor(ratingPengajar);
            let starsHtml = '';
            const noRatingClass = (ratingPengajar === 0) ? 'no-rating' : '';

            for (let i = 1; i <= 5; i++) {
                const isActive = (i <= ratingBintangPenuh && ratingPengajar > 0) ? 'active' : '';
                starsHtml += `<div class="rating-star"><img src="images/star.svg" alt="Star" class="${isActive}" /></div>`;
            }

            return `
                <div class="teacher-card">
                    <div class="teacher-card-image-wrapper">
                        <img src="${fotoSrc}" alt="Foto ${teacher.nama_pengajar}" class="teacher-card-image" onerror="this.onerror=null; this.src='images/default_teacher_avatar.png';">
                    </div>
                    <div class="teacher-card-details">
                        <div class="teacher-card-info">
                            <h2 class="teacher-card-name"><span>${teacher.nama_pengajar}</span></h2>
                            <p class="teacher-card-description"><span>${deskripsi}</span></p>
                        </div>
                        <div class="teacher-card-rating">
                            <div class="rating-stars-container ${noRatingClass}">
                                ${starsHtml}
                            </div>
                        </div>
                        <a href="${linkPilih}" class="teacher-card-select-button"><span>Pilih</span></a>
                    </div>
                </div>
            `;
        }

        function fetchAndUpdateTeachers() {
            const lokasi = searchInput.value;
            const mapel = mapelSelect.value;
            const jenjang = jenjangSelect.value;
            
            const cacheBuster = new Date().getTime();
            const url = `pengajar.php?lokasi=${encodeURIComponent(lokasi)}&mapel=${encodeURIComponent(mapel)}&jenjang=${encodeURIComponent(jenjang)}&t=${cacheBuster}`;

            fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok ' + response.statusText);
                    }
                    return response.json();
                })
                .then(teachers => {
                    teacherContainer.innerHTML = '';
                    if (teachers.length === 0) {
                        teacherContainer.innerHTML = '<p style="text-align: center; width: 100%; color: #555; padding: 20px 0;">Tidak ada pengajar yang cocok dengan kriteria Anda.</p>';
                    } else {
                        let allCardsHTML = '';
                        teachers.forEach(teacher => {
                            allCardsHTML += createTeacherCard(teacher);
                        });
                        teacherContainer.innerHTML = allCardsHTML;
                    }
                })
                .catch(error => {
                    console.error('Error fetching teachers:', error);
                    teacherContainer.innerHTML = '<p style="text-align: center; width: 100%; color: red; padding: 20px 0;">Terjadi kesalahan saat memuat data pengajar.</p>';
                });
        }

        fetchAndUpdateTeachers();

        searchInput.addEventListener('input', fetchAndUpdateTeachers);
        mapelSelect.addEventListener('change', fetchAndUpdateTeachers);
        jenjangSelect.addEventListener('change', fetchAndUpdateTeachers);
    });
    </script>
    </body>

</html>