<?php
session_start();
require 'vendor/autoload.php';
include('db_connection.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Setel zona waktu ke Asia/Jakarta
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$email = $_SESSION['email'];

// Generate new OTP and set expiry
$otp = rand(100000, 999999);
$expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));

// Check if user is locked
$stmt = $conn->prepare("SELECT locked_until FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($locked_until);
$stmt->fetch();
$stmt->close();

if ($locked_until && new DateTime() < new DateTime($locked_until)) {
    echo "<script>alert('Your account is locked. Please try again later.');</script>";
    header("Location: login.php?locked=true");
    exit();
}

// Insert new OTP into the database
$stmt = $conn->prepare("INSERT INTO otp (user_id, email, otp_code, expires_at) VALUES (?, ?, ?, ?)");
$stmt->bind_param("isss", $user_id, $email, $otp, $expires_at);
$stmt->execute();
$stmt->close();

// Send OTP via email using PHPMailer
$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'pengirimtest@gmail.com';
    $mail->Password = 'axzh hems bmsa gzcw';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;

    // Recipients
    $mail->setFrom('pengirimtest@gmail.com', 'OTP Verification');
    $mail->addAddress($email);

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Your OTP Code';
    $mail->Body    = "Your OTP code is: $otp";

    $mail->send();
    echo "<script>alert('OTP baru telah dikirim ke email Anda.');</script>";
} catch (Exception $e) {
    echo "<script>alert('Pesan tidak dapat dikirim. Kesalahan Mailer: {$mail->ErrorInfo}');</script>";
}

header("Location: verify_otp.php");
?>
