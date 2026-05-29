<?php
session_start();
include 'db.php';

if (!function_exists('tampilkan_harga')) {
    function tampilkan_harga($harga, $placeholder = 'N/A')
    {
        if ($harga !== null && is_numeric($harga)) {
            return 'Rp ' . number_format($harga, 0, ',', '.');
        }
        return $placeholder;
    }
}
//
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

$testimonials = [];
$sql_testimonials = "SELECT rr.review, rr.rating, p.nama_pengguna, pg.nama_pengajar, p.foto_profil
                       FROM rating_review rr
                       JOIN pengguna p ON rr.id_pengguna = p.id_pengguna
                       LEFT JOIN pengajar pg ON rr.id_pengajar = pg.id_pengajar
                       WHERE rr.review IS NOT NULL AND rr.review != '' AND rr.rating IS NOT NULL
                       ORDER BY rr.created_at DESC
                       LIMIT 3";
$result_testimonials = $conn->query($sql_testimonials);
if ($result_testimonials) {
    while ($row = $result_testimonials->fetch_assoc()) {
        $testimonials[] = $row;
    }
}

if (isset($conn) && is_object($conn) && !$conn->connect_error && $conn->ping()) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Landing Page - LesAja</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #fdfbf8;
            color: #1e2c4c;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 80px;
            max-width: 1200px;
            margin: 0 auto;
            border-bottom: 1px solid #ddd;
        }

        .logo {
            height: 60px;
        }

        .user-icon img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }

        .hero {
            background-color: #FDFAF6;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px;
            min-height: 80vh;
            text-align: left;
            flex-direction: row;
            max-width: 1200px;
            margin: 0 auto;
        }

        .hero-text {
            max-width: 600px;
            margin-right: 40px;
        }

        .hero-text h1 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 20px;
            line-height: 1.4;
        }

        .hero-text p {
            font-size: 16px;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .btn-primary {
            background-color: #1e2c4c;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
            display: inline-block;
        }

        .btn-primary:hover {
            background-color: #14213d;
        }

        .hero-image img {
            width: 100%;
            max-width: 450px;
            border-radius: 12px;
            object-fit: cover;
        }

        .feature-section1 {
            background-color: #ede5db;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 100px 80px;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 1200px;
            margin: 60px auto;
            min-height: 400px;
        }

        .feature-section2 {
            background-color: #fdfaf6;
            min-height: 500px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 40px 20px;
            max-width: 1200px;
            margin: 0 auto 60px;
        }

        .feature-section3 {
            background-color: #ede5db;
            min-height: 400px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 40px 20px;
            max-width: 1200px;
            margin: 0 auto 60px;
            border-radius: 12px;
        }

        .image-container {
            display: flex;
            gap: 100px;
            z-index: 1;
            margin-top: 30px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .image-container img {
            width: 300px;
            border-radius: 14px;
            object-fit: cover;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .testimonial-cards {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 40px;
            max-width: 1000px;
            margin: 0 auto;
        }

        .testimonial-card {
            background: #fff;
            border-radius: 16px;
            padding: 20px;
            width: 300px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .testimonial-card p {
            font-size: 14px;
            margin-top: 10px;
        }

        .frame-update {
            display: flex;
            flex-direction: column;
            height: auto;
            align-items: center;
            gap: 60px;
            padding: 80px 20px;
            background-color: #fdfaf6;
        }

        .frame-update .update-title {
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            color: #213555;
            font-size: 36px;
            text-align: center;
            letter-spacing: -0.5px;
        }

        .frame-update .update-content {
            display: flex;
            flex-direction: column;
            gap: 24px;
            width: 100%;
            max-width: 1200px;
        }

        .update-row {
            display: flex;
            gap: 24px;
            width: 100%;
        }

        .update-box {
            flex: 1;
            background-color: #213555;
            border-radius: 24px;
            height: 200px;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .update-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: inherit;
        }

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

        .teacher-recommendation-section {
            background: var(--background-light);
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 25px;
            padding: 40px 20px;
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
            text-align: left;
            display: block;
            font-size: 13px;
            color: var(--text-muted);
        }

        .teacher-card-info {
            text-align: left;
            flex-grow: 1;
        }

        .teacher-card-name span {
            text-align: left;
            display: block;
            font-size: 18px;
            font-weight: 700;
        }

        .teacher-card-details {
            align-items: flex-start;
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 15px;
            box-sizing: border-box;
            flex-grow: 1;
        }

        .teacher-card-rating {
            justify-content: flex-start;
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
            background: var(--primary-color);
            border: 1px solid var(--primary-color);
            border-radius: 25px;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            padding: 8px 18px;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.2s ease, border-color 0.2s ease;
        }

        .teacher-card-select-button:hover {
            background-color: var(--button-hover-secondary-color);
            border-color: var(--button-hover-secondary-color);
        }

        .teacher-card-select-button span {
            color: var(--text-light);
            font-size: 14px;
            font-weight: 600;
        }

        .pengajar-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .pengajar-title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
            color: var(--text-color);
        }

        .pengajar-subtitle {
            font-size: 18px;
            color: var(--text-muted);
        }

        .learning-services {
            background-color: #fdfaf6;
            display: flex;
            padding: 128px 80px;
            flex-direction: column;
            align-items: stretch;
            justify-content: center;
        }

        .services-header {
            display: flex;
            width: 100%;
            flex-direction: column;
            align-items: center;
            font-family: 'Roboto', sans-serif;
            color: #213555;
            text-align: center;
        }

        .services-title {
            font-size: 24px;
            font-weight: 400;
            line-height: 1;
            letter-spacing: -0.24px;
            margin: 0;
        }

        .services-subtitle {
            font-size: 48px;
            font-weight: 600;
            line-height: 1;
            letter-spacing: -0.48px;
            margin: 20px 0 0 0;
        }

        .services-container {
            display: flex;
            margin-top: 80px;
            width: 100%;
            align-items: stretch;
            gap: 24px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .service-card {
            border-radius: 24px;
            background-color: #ffffff;
            border: 1px solid #ededed;
            display: flex;
            min-width: 240px;
            padding: 32px;
            flex-direction: column;
            align-items: stretch;
            justify-content: center;
            flex: 1;
            flex-basis: 0%;
        }

        .service-image-wrapper {
            align-self: center;
            width: 200px;
            max-width: 100%;
        }

        .service-image {
            object-fit: contain;
            object-position: center;
            width: 100%;
            border-radius: 12px;
        }

        .service-content {
            display: flex;
            margin-top: 24px;
            width: 100%;
            flex-direction: column;
            align-items: stretch;
            font-family: 'Roboto', sans-serif;
            text-align: center;
        }

        .service-type {
            color: #213555;
            font-size: 24px;
            font-weight: 500;
            margin: 0;
        }

        .service-description {
            color: #213555;
            font-size: 16px;
            font-weight: 400;
            line-height: 27px;
            margin: 12px 0 0 0;
        }

        .why-choose-us-section {
            max-width: 1200px;
            margin: 0 auto 60px;
            display: flex;
            min-height: 400px;
            padding: 80px 20px;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 60px;
            width: 100%;
            box-sizing: border-box;
            background-color: #fdfaf6;
            border-radius: 12px;
        }

        .why-choose-us-title {
            color: #213555;
            text-align: center;
            font-family: 'Inter', sans-serif;
            font-size: 48px;
            font-weight: 700;
            line-height: 1.2;
            letter-spacing: -0.48px;
            margin: 0;
        }

        .why-choose-us-video-container {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            border-radius: 20px;
            position: relative;
            margin: 0;
            overflow: hidden;
        }

        .why-choose-us-feature-video {
            width: 100%;
            max-width: 800px;
            height: auto;
            border-radius: inherit;
            object-fit: cover;
        }

        .testimonial-card__user {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 15px;
            justify-content: center;
        }

        .testimonial-card__user-info {
            text-align: left;
        }

        .testimonial-card__user-name {
            font-size: 16px;
            font-weight: bold;
            color: #1e2c4c;
            margin: 0;
        }

        .user-avatar img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        @media (max-width: 991px) {
            header {
                padding: 15px 40px;
            }

            .hero {
                flex-direction: column;
                text-align: center;
                padding: 30px;
            }

            .hero-text {
                margin-right: 0;
                margin-bottom: 30px;
                max-width: 100%;
            }

            .hero-text h1 {
                font-size: 32px;
            }

            .hero-image img {
                max-width: 350px;
            }

            .feature-section1 {
                flex-direction: column;
                padding: 60px 40px;
                margin: 40px auto;
                min-height: auto;
            }

            .feature-section1 .hero-text {
                margin-top: 30px;
            }

            .feature-section2,
            .feature-section3 {
                padding: 30px 20px;
                min-height: auto;
            }

            .image-container {
                gap: 50px;
            }

            .image-container img {
                width: 250px;
            }

            .testimonial-cards {
                gap: 20px;
            }

            .testimonial-card {
                width: 100%;
                max-width: 350px;
            }

            .frame-update {
                padding: 60px 20px;
                gap: 40px;
            }

            .frame-update .update-title {
                font-size: 28px;
            }

            .update-row {
                flex-direction: column;
                gap: 20px;
            }

            .update-box {
                height: 180px;
            }

            .pengajar-header {
                margin-bottom: 20px;
            }

            .pengajar-title {
                font-size: 24px;
            }

            .pengajar-subtitle {
                font-size: 16px;
            }

            .teacher-card {
                width: 100%;
                max-width: 300px;
            }

            .learning-services {
                padding: 80px 20px;
            }

            .services-title {
                font-size: 20px;
            }

            .services-subtitle {
                font-size: 36px;
                margin-top: 15px;
            }

            .services-container {
                margin-top: 30px;
                flex-direction: column;
                align-items: center;
            }

            .service-card {
                width: 100%;
                max-width: 350px;
            }

            .why-choose-us-section {
                padding: 40px 20px;
                gap: 40px;
                min-height: auto;
            }

            .why-choose-us-title {
                font-size: 36px;
            }

            .why-choose-us-feature-video {
                max-width: 100%;
            }
        }

        @media (max-width: 768px) {
            header {
                padding: 15px 20px;
            }

            .hero {
                padding: 20px;
            }

            .hero-text h1 {
                font-size: 28px;
            }

            .hero-text p {
                font-size: 15px;
            }

            .feature-section1 {
                padding: 40px 20px;
            }

            .image-container {
                gap: 20px;
            }

            .image-container img {
                width: 100%;
                max-width: 280px;
            }

            .why-choose-us-title {
                font-size: 28px;
            }
        }
    </style>
</head>

<body>

    <header>
        <img src="images/logo.png" alt="Logo LesAja" class="logo" />
        <a href="register.php" class="user-icon">
            <img src="images/user.png" alt="User Icon" />
        </a>
    </header>

    <section class="hero">
        <div class="hero-text">
            <h1>Belajar Jadi Lebih<br>Mudah dengan LesAja!</h1>
            <p>Temukan guru les terbaik untuk membantu perjalanan belajarmu.<br />
                Pilih sesi offline atau online sesuai kebutuhanmu!</p>
            <a href="register.php" class="btn-primary">Mulai Belajar Sekarang</a>
        </div>
        <div class="hero-image">
            <img src="images/ilustrasibelajar.jpg" alt="Ilustrasi belajar" />
        </div>
    </section>

    <section class="feature-section1">
        <div class="hero-image">
            <img src="images/gurulanding1.jpg" alt="Ilustrasi guru" />
        </div>
        <div class="hero-text">
            <h1>Temukan Pengajar Sesuai <br>Kebutuhanmu!</h1>
            <p>Dapatkan pendamping belajar terbaik <br>
                Fleksibel memilih pengajar sesuai jenjang dan mata pelajaran.</p>
        </div>
    </section>

    <section class="feature-section2 teacher-recommendation-section">
        <div class="pengajar-header">
            <div class="pengajar-subtitle">Pengajar Terpopuler</div>
            <br>
            <div class="pengajar-title">Rekomendasi Pengajar Terbaik</div>
        </div>

        <div class="teacher-cards-container">
            <?php if (!empty($teachers)): ?>
                <?php foreach ($teachers as $teacher): ?>
                    <?php
                    $foto_src = 'images/default_teacher_avatar.png';
                    if (!empty($teacher['foto_profil'])) {
                        if (file_exists($teacher['foto_profil'])) {
                            $foto_src = htmlspecialchars($teacher['foto_profil']);
                        } else {
                            $uploads_dir = 'uploads/profile_pictures/';
                            if (file_exists($uploads_dir . $teacher['foto_profil'])) {
                                $foto_src = htmlspecialchars($uploads_dir . $teacher['foto_profil']);
                            }
                        }
                    }
                    $deskripsi_pengajar_card = "Mengajar " . htmlspecialchars($teacher['keahlian'] ?? '') .
                        " " . htmlspecialchars($teacher['jenjang_keahlian'] ?? '') . " | " . htmlspecialchars($teacher['lokasi'] ?? '') . " | " . tampilkan_harga($teacher['harga_layanan']);
                    $rating_pengajar = (float)($teacher['rating'] ?? 0);
                    $stars_html = '';
                    for ($i = 1; $i <= 5; $i++) {
                        $is_active = ($i <= floor($rating_pengajar) && $rating_pengajar > 0) ? 'active' : '';
                        $stars_html .= '<div class="rating-star"><img src="images/star.svg" alt="Star" class="' . $is_active . '" /></div>';
                    }
                    ?>
                    <div class="teacher-card">
                        <div class="teacher-card-image-wrapper">
                            <img src="<?php echo $foto_src; ?>" alt="Foto <?php echo htmlspecialchars
                            ($teacher['nama_pengajar']); ?>" 
                            class="teacher-card-image" onerror="this.src='images/default_teacher_avatar.png';">
                        </div>
                        <div class="teacher-card-details">
                            <div class="teacher-card-info">
                                <h2 class="teacher-card-name"><span>
                                <?php echo htmlspecialchars($teacher['nama_pengajar']); ?></span></h2>
                                <br>
                                <p class="teacher-card-description">
                                <span><?php echo $deskripsi_pengajar_card; ?></span></p>
                            </div>
                            <div class="teacher-card-rating">
                                <div class="rating-stars-container"><?php echo $stars_html; ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; width: 100%;">Belum ada rekomendasi pengajar saat ini.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="learning-services">
        <header class="services-header">
            <h2 class="services-title">Layanan Utama</h2>
            <h1 class="pengajar-title">Pilih Cara Belajarmu : Tatap Muka atau Tanya Tugas?</h1>
        </header>

        <div class="services-container">
            <article class="service-card">
                <div class="service-image-wrapper">
                    <img src="images/offline.jpg" alt="Offline Learning Service" class="service-image" />
                </div>
                <div class="service-content">
                    <h3 class="service-type">Offline</h3>
                    <p class="service-description">
                        Layanan yang memudahkan pengguna untuk bertemu langsung dengan
                        pengajar
                    </p>
                </div>
            </article>
            <article class="service-card">
                <div class="service-image-wrapper">
                    <img src="images/onlen.jpg" alt="Online Learning Service" class="service-image" />
                </div>
                <div class="service-content">
                    <h3 class="service-type">Online</h3>
                    <p class="service-description">
                        Layanan yang memudahkan pengguna bertanya langsung kepada pengajar
                        melalui platform
                    </p>
                </div>
            </article>
        </div>
    </section>

    <section class="why-choose-us-section">
        <p class="pengajar-title">Menghasilkan Lulusan Terbaik</p>
        <figure class="why-choose-us-video-container">
            <video class="why-choose-us-feature-video" controls autoplay muted loop>
                <source src="images/vid.mp4" type="video/mp4">
            </video>
        </figure>
    </section>

    <section class="feature-section3">
        <h1>Apa Kata Pengguna Kami</h1>
        <br>
        <br>
        <br>

        <div class="testimonial-cards">
            <?php if (!empty($testimonials)): ?>
                <?php foreach ($testimonials as $testimonial): ?>
                    <?php
                    $user_avatar_src = 'images/default_user_avatar.png';
                    if (!empty($testimonial['foto_profil'])) {
                        if (file_exists($testimonial['foto_profil'])) {
                            $user_avatar_src = htmlspecialchars($testimonial['foto_profil']);
                        } else {
                            $uploads_dir_user = 'uploads/profile_pictures_pengguna/';
                            if (file_exists($uploads_dir_user . $testimonial['foto_profil'])) {
                                $user_avatar_src = htmlspecialchars($uploads_dir_user . $testimonial['foto_profil']);
                            }
                        }
                    }
                    ?>
                    <article class="testimonial-card">
                        <p class="testimonial-card__content">
                            <?php echo htmlspecialchars($testimonial['review']); ?>
                        </p>
                        <br>
                        <div class="testimonial-card__user">
                            <div class="user-avatar">
                                <img src="<?php echo $user_avatar_src; ?>" alt="User Avatar">
                            </div>
                            <div class="testimonial-card__user-info">
                                <h3 class="testimonial-card__user-name"><?php echo htmlspecialchars($testimonial['nama_pengguna']); ?></h3>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; width: 100%;">Belum ada testimonial saat ini.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="frame-update">
        <p class="update-title">Jangan Ketinggalan! Update Terbaru dari LesAja</p>
        <div class="update-content">
            <div class="update-row">
                <div class="update-box"><img src="images/landing1.jpg" alt="Update 1"></div>
                <div class="update-box"><img src="images/landing2.jpg" alt="Update 2"></div>
                <div class="update-box"><img src="images/landing5.jpg" alt="Update 3"></div>
            </div>
            <div class="update-row">
                <div class="update-box"><img src="images/landing4.jpg" alt="Update 4"></div>
                <div class="update-box"><img src="images/landing6.jpg" alt="Update 5"></div>
            </div>
        </div>
    </section>
</body>

</html>