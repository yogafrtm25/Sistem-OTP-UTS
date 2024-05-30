<?php
session_start();
include('db_connection.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$locked_until = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_SESSION['email'];
    $otp_code = $_POST['otp_code'];

    $stmt = $conn->prepare("SELECT id, expires_at, failed_attempts, locked_until FROM otp WHERE email = ? AND otp_code = ? ORDER BY expires_at DESC LIMIT 1");
    $stmt->bind_param("ss", $email, $otp_code);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($otp_id, $expires_at, $failed_attempts, $locked_until);
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
                            document.getElementById('countdown').innerHTML = 'You can now try again.';
                        }
                    }, 1000);
                    alert('Akun terkunci. Silakan coba lagi dalam ' + $minutes_left + ' menit ' + $seconds_left + ' detik.');
                }
            </script>";
        } elseif (new DateTime() > new DateTime($expires_at)) {
            echo "<script>alert('OTP telah kedaluwarsa pada $expires_at.');</script>";
        } else {
            // OTP is valid
            echo "<script>alert('Login berhasil!');</script>";

            // Clear OTP from database
            $stmt = $conn->prepare("DELETE FROM otp WHERE id = ?");
            $stmt->bind_param("i", $otp_id);
            $stmt->execute();

            // Redirect to dashboard
            header("Location: dashboard.php");
            exit();
        }
    } else {
        // Increment failed attempts
        $stmt = $conn->prepare("SELECT id, failed_attempts FROM otp WHERE email = ? ORDER BY expires_at DESC LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($otp_id, $failed_attempts);
            $stmt->fetch();

            $failed_attempts++;
            if ($failed_attempts >= 3) {
                $locked_until = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                // Lock the account
                $stmt = $conn->prepare("UPDATE users SET locked_until = ? WHERE email = ?");
                $stmt->bind_param("ss", $locked_until, $email);
                $stmt->execute();

                // Lock OTP
                $stmt = $conn->prepare("UPDATE otp SET locked_until = ? WHERE id = ?");
                $stmt->bind_param("si", $locked_until, $otp_id);
                $stmt->execute();

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
                                document.getElementById('countdown').innerHTML = 'You can now try again.';
                            }
                        }, 1000);
                        alert('Akun terkunci karena beberapa kali upaya OTP gagal. Akun akan terkunci sampai $locked_until.');
                    }
                </script>";
                header("Location: login.php?locked=true");
                exit();
            } else {
                $stmt = $conn->prepare("UPDATE otp SET failed_attempts = ? WHERE id = ?");
                $stmt->bind_param("ii", $failed_attempts, $otp_id);
                $stmt->execute();

                echo "<script>alert('Kode OTP salah. Anda memiliki " . (3 - $failed_attempts) . " kesempatan lagi.');</script>";
            }
        } else {
            echo "<script>alert('Kode OTP salah.');</script>";
        }
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify OTP</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <h2>Verify OTP</h2>
        <form method="post">
            OTP: <input type="text" name="otp_code" required><br>
            <button type="submit">Verify OTP</button>
        </form>
        <div id="countdown"></div>
    </div>
</body>
</html>
