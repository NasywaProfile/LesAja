<?php
session_start();
include 'db.php';

$message_content = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['daftar'])) {
    if (!isset($conn) || !$conn instanceof mysqli) {
        $message_content = "Kesalahan koneksi database.";
        $message_type = "error";
    } else {
        $nama_pengguna_form = $conn->real_escape_string($_POST['nama_pengguna']);
        $password = $_POST['password'];
        $role = $conn->real_escape_string($_POST['role']);

        if (empty($nama_pengguna_form) || empty($password) || empty($role)) {
            $message_content = "Nama Pengguna, Kata Sandi, dan Role harus diisi!";
            $message_type = "error";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            if ($role == 'pengguna') {
                $check_username_sql = "SELECT nama_pengguna FROM pengguna WHERE nama_pengguna = '$nama_pengguna_form'";
                $result_check = $conn->query($check_username_sql);

                if ($result_check && $result_check->num_rows > 0) {
                    $message_content = "Nama Pengguna sudah terdaftar sebagai pengguna.";
                    $message_type = "error";
                } else {
                    //
                    $sql = "INSERT INTO pengguna (nama_pengguna, kata_sandi, role) VALUES ('$nama_pengguna_form', '$hashed_password', '$role')";
                    if ($conn->query($sql) === TRUE) {
                        $message_content = "Registrasi sebagai pengguna berhasil! Silakan <a href='login.php'>Masuk</a>.";
                        $message_type = "success";
                    } else {
                        $message_content = "Error saat registrasi pengguna: " . $conn->error;
                        $message_type = "error";
                    }
                }
            } elseif ($role == 'pengajar') {
                $check_username_sql_pengajar = "SELECT nama_pengajar FROM pengajar WHERE nama_pengajar = '$nama_pengguna_form'";
                $result_check_pengajar = $conn->query($check_username_sql_pengajar);

                if ($result_check_pengajar && $result_check_pengajar->num_rows > 0) {
                    $message_content = "Nama Pengguna sudah terdaftar sebagai pengajar.";
                    $message_type = "error";
                } else {
                    $sql = "INSERT INTO pengajar (nama_pengajar, kata_sandi, role) VALUES ('$nama_pengguna_form', '$hashed_password', 'pengajar')";
                    if ($conn->query($sql) === TRUE) {
                        $message_content = "Registrasi sebagai pengajar berhasil! Silakan <a href='login.php'>Masuk</a>.";
                        $message_type = "success";
                    } else {
                        $message_content = "Error saat registrasi pengajar: " . $conn->error;
                        $message_type = "error";
                    }
                }
            } else {
                $message_content = "Role tidak valid.";
                $message_type = "error";
            }
        }
    }
}
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar LesAja</title>
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
            margin-bottom: 20px;
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
        input[type="password"],
        select {
            padding: 11px 14px;
            margin-bottom: 16px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background-color: #f3f0e6;
            font-size: 14px;
            color: #333;
            box-sizing: border-box;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #1e2e3e;
            box-shadow: 0 0 0 2px rgba(30, 46, 62, 0.2);
        }

        button[type="submit"] {
            padding: 11px;
            background-color: #1e2e3e;
            color: white;
            font-size: 15px;
            font-weight: 500;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            margin-top: 12px;
            transition: background-color 0.3s ease;
        }

        button[type="submit"]:hover {
            background-color: #3a506b;
        }

        .login-link {
            margin-top: 20px;
            font-size: 13px;
            text-align: center;
            color: #555;
        }

        .login-link a {
            color: #1e2e3e;
            text-decoration: none;
            font-weight: 500;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .message {
            padding: 10px 15px;
            margin-bottom: 18px;
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

        .message.success a {
            color: #1a5b21 !important;
            font-weight: bold;
            text-decoration: underline;
        }

        .message.error a {
            color: #9d1f1f !important;
            font-weight: bold;
            text-decoration: underline;
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
            select,
            button[type="submit"] {
                padding: 9px 12px;
                margin-bottom: 12px;
                font-size: 13px;
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
            <p>Daftar sekarang dan mulai perjalanan belajarmu bersama tutor berkualitas, baik online maupun offline!</p>
            <div class="image-box">
                <img src="images/login.png" alt="Ilustrasi Belajar">
            </div>
        </div>
        <div class="right">
            <h2>Daftar Akun</h2>
            <?php
            if (!empty($message_content)) {
                echo "<div class='message " . htmlspecialchars($message_type) . "'>" . $message_content . "</div>";
            }
            ?>
            <form action="register.php" method="POST">
                <label for="nama_pengguna">Nama Pengguna</label>
                <input type="text" id="nama_pengguna" name="nama_pengguna" value="<?php echo isset($_POST['nama_pengguna']) ? htmlspecialchars($_POST['nama_pengguna']) : ''; ?>" required>
                <label for="password">Kata Sandi</label>
                <input type="password" id="password" name="password" required>
                <label for="role">Pilih Role</label>
                <select id="role" name="role" required>
                    <option value="">Pilih Role (Pengguna/Pengajar)</option>
                    <option value="pengguna" <?php echo (isset($_POST['role']) && $_POST['role'] == 'pengguna') ? 'selected' : ''; ?>>Pengguna</option>
                    <option value="pengajar" <?php echo (isset($_POST['role']) && $_POST['role'] == 'pengajar') ? 'selected' : ''; ?>>Pengajar</option>
                </select>
                <button type="submit" name="daftar" value="Daftar">Daftar</button>
            </form>
            <div class="login-link">
                Sudah punya akun? <a href="login.php">Masuk</a>
            </div>
        </div>
    </div>
</body>

</html>