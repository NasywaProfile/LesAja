<?php
session_start();
include 'db.php'; 

$message_content = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    if (!isset($conn) || !is_object($conn)) {
        $message_content = "Kesalahan koneksi database.";
        $message_type = "error";
    } else {
        $username_input = $conn->real_escape_string($_POST['username']);
        $password_input = $_POST['password'];

        if (empty($username_input) || empty($password_input)) {
            $message_content = "Nama Pengguna dan Kata Sandi wajib diisi!";
            $message_type = "error";
        } else {
            $user_found_but_password_incorrect = false;
            $user_not_found_at_all = true;

            $sql_pengajar = "SELECT id_pengajar, nama_pengajar, kata_sandi FROM pengajar WHERE nama_pengajar = '$username_input'";
            $result_pengajar = $conn->query($sql_pengajar);

            if ($result_pengajar && $result_pengajar->num_rows == 1) {
                $user_not_found_at_all = false;
                $tutor_row = $result_pengajar->fetch_assoc();
                
                if (password_verify($password_input, $tutor_row['kata_sandi'])) {
                    $_SESSION['user_id'] = $tutor_row['id_pengajar'];
                    $_SESSION['nama'] = $tutor_row['nama_pengajar'];
                    $_SESSION['role'] = 'pengajar';
                    header("Location: dashboardpengajar.php");
                    exit();
                } else {
                    $user_found_but_password_incorrect = true;
                }
            }
            
            $sql_pengguna = "SELECT id_pengguna, nama_pengguna, kata_sandi, role, foto_profil FROM pengguna WHERE nama_pengguna = '$username_input'";
            $result_pengguna = $conn->query($sql_pengguna);

            if ($result_pengguna && $result_pengguna->num_rows == 1) {
                $user_not_found_at_all = false;
                $user_row = $result_pengguna->fetch_assoc();
                
                if (password_verify($password_input, $user_row['kata_sandi'])) {
                    $_SESSION['user_id'] = $user_row['id_pengguna'];
                    $_SESSION['nama'] = $user_row['nama_pengguna'];
                    $_SESSION['role'] = 'pengguna';
                    $_SESSION['foto_profil'] = $user_row['foto_profil'] ?? '';
                    header("Location: dashboardpengguna.php");
                    exit;
                } else {
                    $user_found_but_password_incorrect = true;
                }
            }

            if ($user_found_but_password_incorrect) {
                $message_content = "Kata Sandi salah.";
            } elseif ($user_not_found_at_all) {
                $message_content = "Nama Pengguna tidak ditemukan.";
            }
            
            if (empty($message_content) && ($user_not_found_at_all == false)) {
                $message_content = "Kata Sandi salah.";
            }

            $message_type = "error";
        }
    }
}

if (isset($conn) && is_object($conn)) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login LesAja</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        html,
        body {
            height: 100%;
            margin: 0;
            padding: 0;
            font-family: 'Roboto', sans-serif;
            background-color: #fafaf5;
            overflow: hidden;
        }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            box-sizing: border-box;
        }

        .container {
            display: flex;
            width: 100%;
            max-width: 1100px;
            height: calc(100vh - 40px);
            max-height: 650px;
            min-height: 550px;
            border-radius: 30px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }

        .left,
        .right {
            padding: 30px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            box-sizing: border-box;
            height: 100%;
            overflow-y: auto;
        }

        .left {
            flex: 1.1;
            background-color: #f2ecdf;
            align-items: center;
            position: relative;
            text-align: center;
        }

        .left img.logo {
            position: absolute;
            top: 25px;
            left: 25px;
            width: 55px;
            height: auto;
        }

        .left h1 {
            color: #1e2e3e;
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 26px;
            font-weight: 700;
        }

        .left p {
            max-width: 360px;
            color: #5c5c5c;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .left .image-box {
            width: 100%;
            max-width: 280px;
            height: auto;
            aspect-ratio: 4 / 3.2;
            border-radius: 15px;
            margin-top: 15px;
            overflow: hidden;
        }

        .left .image-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 15px;
        }

        .right {
            flex: 1;
            background-color: #fdfdf7;
        }

        .right h2 {
            color: #1e2e3e;
            margin-top: 0;
            margin-bottom: 25px;
            font-size: 24px;
            font-weight: 700;
            text-align: center;
        }

        form {
            display: flex;
            flex-direction: column;
            width: 100%;
        }

        label {
            margin-bottom: 7px;
            font-weight: 500;
            font-size: 13px;
            color: #333;
        }

        input[type="text"],
        input[type="password"] {
            padding: 11px 14px;
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background-color: #f3f0e6;
            font-size: 14px;
            color: #333;
            box-sizing: border-box;
        }

        input:focus {
            outline: none;
            border-color: #1e2e3e;
            box-shadow: 0 0 0 2px rgba(30, 46, 62, 0.2);
        }

        button[type="submit"] {
            padding: 12px;
            background-color: #1e2e3e;
            color: white;
            font-size: 16px;
            font-weight: 500;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            margin-top: 15px;
            transition: background-color 0.3s ease;
        }

        button[type="submit"]:hover {
            background-color: #3a506b;
        }

        .register-link {
            margin-top: 25px;
            font-size: 13px;
            text-align: center;
            color: #555;
        }

        .register-link a {
            color: #1e2e3e;
            text-decoration: none;
            font-weight: 500;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        .message {
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 13px;
            text-align: center;
            border: 1px solid transparent;
            box-sizing: border-box;
        }

        .message.error {
            background-color: #ffebee;
            border-color: #f44336;
            color: #c62828;
        }

        .message.success {
            background-color: #e8f5e9;
            border-color: #4caf50;
            color: #2e7d32;
        }

        .message a {
            font-weight: bold;
            text-decoration: underline;
        }

        .message.success a {
            color: #1a5b21 !important;
        }

        .message.error a {
            color: #9d1f1f !important;
        }

        @media (max-height: 600px) {
            .container {
                max-height: calc(100vh - 20px);
                min-height: 0;
            }

            body {
                padding: 10px;
            }

            .left h1 {
                font-size: 22px;
            }

            .left p {
                font-size: 13px;
                margin-bottom: 10px;
            }

            .left .image-box {
                max-width: 220px;
                margin-top: 10px;
            }

            .right h2 {
                font-size: 20px;
                margin-bottom: 15px;
            }

            input[type="text"],
            input[type="password"],
            button[type="submit"] {
                padding: 9px 12px;
                margin-bottom: 12px;
                font-size: 13px;
            }

            button[type="submit"] {
                padding: 10px;
                font-size: 14px;
            }

            .message {
                margin-bottom: 12px;
                font-size: 12px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="left">
            <img src="images/logo.png" alt="Logo LesAja" class="logo">
            <h1>Selamat Datang LesAja!</h1>
            <p>Masuk ke akunmu untuk mengakses jadwal, tugas, dan sesi les yang sudah kamu pesan.</p>
            <div class="image-box">
                <img src="images/login.png" alt="Ilustrasi Login">
            </div>
        </div>
        <div class="right">
            <h2>Masuk Akun</h2>
            <?php
            if (!empty($message_content)) {
                echo "<div class='message " . htmlspecialchars($message_type) . "'>" . $message_content . "</div>";
            }
            ?>
            <form action="login.php" method="POST">
                <label for="username">Nama Pengguna</label>
                <input type="text" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                <label for="password">Kata Sandi</label>
                <input type="password" id="password" name="password" required>
                <button type="submit" name="login" value="Login">Masuk</button>
            </form>
            <div class="register-link">
                Belum punya akun? <a href="register.php">Daftar di sini</a>
            </div>
        </div>
    </div>
</body>

</html>