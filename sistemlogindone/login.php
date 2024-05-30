<?php
session_start();
include('db_connection.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, password, locked_until FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $hashed_password, $locked_until);
        $stmt->fetch();

        if ($locked_until && new DateTime() < new DateTime($locked_until)) {
            $locked_until_time = new DateTime($locked_until);
            $current_time = new DateTime();
            $interval = $locked_until_time->diff($current_time);
            $minutes_left = $interval->i;
            $seconds_left = $interval->s;
            echo "<script>
                var lockedUntil = new Date('$locked_until').getTime();
                var now = new Date().getTime();
                var countdown = (lockedUntil - now) / 1000;
                window.onload = function() {
                    var countdownTimer = setInterval(function() {
                        countdown--;
                        var minutes = Math.floor(countdown / 60);
                        var seconds = Math.floor(countdown % 60);
                        document.getElementById('countdown').innerHTML = minutes + 'm ' + seconds + 's ';
                        if (countdown <= 0) {
                            clearInterval(countdownTimer);
                            document.getElementById('countdown').innerHTML = 'Loginlah dalam beberapa menit lagi.';
                        }
                    }, 1000);
                    alert('Akun terkunci. Silakan coba lagi dalam ' + $minutes_left + ' menit ' + $seconds_left + ' detik.');
                }
            </script>";
        } elseif (password_verify($password, $hashed_password)) {
            $_SESSION['user_id'] = $id;
            $_SESSION['email'] = $email;

            // Generate OTP and set expiry
            $otp = rand(100000, 999999);
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 minutes'));

            $stmt = $conn->prepare("INSERT INTO otp (user_id, email, otp_code, expires_at) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $id, $email, $otp, $expires_at);
            $stmt->execute();

            // Send OTP via email
            include 'send_otp.php';

            header("Location: verify_otp.php");
        } else {
            echo "<script>alert('Email atau kata sandi tidak valid.');</script>";
        }
    } else {
        echo "<script>alert('Tidak ada pengguna yang ditemukan dengan email ini.');</script>";
    }
    $stmt->close();
    $conn->close();
}

if (isset($_GET['locked'])) {
    echo "<script>alert('Akun Anda telah dikunci selama 10 menit karena beberapa kali upaya OTP gagal.');</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <h2>Login</h2>
        <form method="post">
            Email: <input type="email" name="email" required><br>
            Password: <input type="password" name="password" required><br>
            <button type="submit">Login</button>
        </form>
        <p>Belum punya akun? <a href="register.php">Daftar!</a></p>
        <div id="countdown"></div>
    </div>
</body>
</html>
