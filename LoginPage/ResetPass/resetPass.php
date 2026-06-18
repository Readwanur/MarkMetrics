<?php
session_start();
include('../connect2db.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

$success_msg = "";
$error_msg = "";

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    if(!empty($_POST['new_password']) && !empty($_POST['confirm_password'])){
        if($_POST['new_password'] == $_POST['confirm_password']){
            $new_password = $_POST['new_password'];
            $new_password_safe = mysqli_real_escape_string($conn, $new_password);
            
            $sql = "UPDATE users
                    SET password_hash = '{$new_password_safe}'
                    WHERE id = '{$_SESSION['otp_user_id']}'";
            $result = mysqli_query($conn, $sql);
            
            if($result){
                $success_msg = "Password changed successfully! Redirecting to login...";
                
                // Email notification
                if (isset($_SESSION['otp_email'])) {
                    $to_email = $_SESSION['otp_email'];
                    $user_name = $_SESSION['user_name'];
                    
                    $subject = 'Password Changed Successfully';
                    $body = "
                    <html>
                    <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
                        <p>Hello <b>$user_name</b>,</p>
                        <p>Your password for your MarkMetrics account has been successfully changed.</p>
                        <p>If you did not authorize this change, please contact support immediately.</p><br>
                        <p>Regards,<br>
                        <b style='color: #f58220;'>MarkMetrics Team</b></p>
                    </body>
                    </html>";
                    
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com';
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'markmetrics.otp@gmail.com';
                        $mail->Password   = 'kgok zcix dsym lwhj';
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                        $mail->Port       = 465;

                        $mail->setFrom('markmetrics.otp@gmail.com', 'MarkMetrics Support');
                        $mail->addAddress($to_email);

                        $mail->isHTML(true);
                        $mail->Subject = $subject;
                        $mail->Body    = $body;

                        $mail->send();
                    } catch (Exception $e) {
                        // ignore error
                    }
                }
                
                // Log password reset to audit_logs
                $safe_reset_id = mysqli_real_escape_string($conn, $_SESSION['otp_user_id']);
                $safe_reset_name = mysqli_real_escape_string($conn, $_SESSION['user_name'] ?? 'Unknown');
                $reset_desc = "<strong>$safe_reset_name</strong> ($safe_reset_id) reset their password";
                mysqli_query($conn, "INSERT INTO audit_logs (user_id, action_type, description) VALUES ('$safe_reset_id', 'password_reset', '$reset_desc')");

                session_destroy();
                header("refresh:3;url=../Login/login.php");
                exit();
            } else {
                $error_msg = "Error updating password. Please try again.";
            }
        } else {
            $error_msg = "Passwords do not match.";
        }
    } else {
        $error_msg = "Please fill in both input fields.";
    }
}
mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>MarkMetrics | Reset Password</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Manrope:wght@400;600;700;800&display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="resetPass.css">
</head>

<body>
    <!-- Global Layout Wrapper -->
    <main class="page-wrapper">

        <!-- Logo Section -->
        <div class="logo-section">
            <div class="logo-icon-box">
                <span class="material-symbols-outlined">school</span>
            </div>
            <h1 class="logo-title">MarkMetrics</h1>
            <p class="logo-subtitle">Institutional Security Portal</p>
        </div>

        <!-- Main Reset Card -->
        <div class="glass-card">
            <header class="card-header">
                <h2>Set New Password</h2>
                <p>
                    Your identity has been verified. Please choose a strong, unique password to secure your
                    institutional record.
                </p>
            </header>

            <!-- Status Messages -->
            <?php if (!empty($error_msg)): ?>
                <div class="msg msg-error">
                    <span class="material-symbols-outlined">error</span>
                    <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($success_msg)): ?>
                <div class="msg msg-success">
                    <span class="material-symbols-outlined">check_circle</span>
                    <?php echo htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>

            <form class="reset-form" method="post" action="resetPass.php">
                <!-- Field: New Password -->
                <div class="field-group">
                    <label class="field-label" for="new_password">New Password</label>
                    <div class="field-wrap">
                        <span class="material-symbols-outlined field-icon">lock</span>
                        <input id="new_password" name="new_password" placeholder="••••••••••••" type="password" />
                    </div>
                </div>

                <!-- Field: Confirm Password -->
                <div class="field-group">
                    <label class="field-label" for="confirm_password">Confirm Password</label>
                    <div class="field-wrap">
                        <span class="material-symbols-outlined field-icon">lock_reset</span>
                        <input id="confirm_password" name="confirm_password" placeholder="••••••••••••"
                            type="password" />
                    </div>
                </div>

                <!-- Instruction List
                <div class="requirements-box">
                    <div class="requirement-item">
                        <span class="material-symbols-outlined">check_circle</span>
                        <span>At least 12 characters recommended</span>
                    </div>
                    <div class="requirement-item">
                        <span class="material-symbols-outlined">check_circle</span>
                        <span>Include numbers and special symbols</span>
                    </div>
                </div> -->

                <!-- Action Button -->
                <button class="cta-btn" type="submit">Save Password</button>
            </form>

            <!-- Back to Sign In -->
            <div class="back-section">
                <a class="back-link" href="../Login/login.php">
                    <span class="material-symbols-outlined">arrow_back</span>
                    Return to sign in
                </a>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="page-footer">
        <div class="footer-links">
            <a href="#">Support</a>
            <a href="#">Security Policy</a>
            <a href="#">Terms</a>
        </div>
        <p class="footer-copy">© <?php echo SYSTEM_YEAR; ?> MarkMetrics. Institutional Privacy Applied.</p>
    </footer>

    <!-- Background Decorative Elements -->
    <div class="bg-decor">
        <div class="bg-blob-1"></div>
        <div class="bg-blob-2"></div>
    </div>
</body>

</html>