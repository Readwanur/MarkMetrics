<?php
session_start();
include('../connect2db.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

$step = 'id';          
$error_msg = '';
$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_otp'])) {
    $user_id = trim($_POST['institutional_id']);

    $sql = "SELECT id, name, email FROM users WHERE id = '$user_id'";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        $to_email = $user['email'];
        $user_name = $user['name'];

        // Generate a 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['otp']            = $otp;
        $_SESSION['otp_user_id']    = $user_id;
        $_SESSION['otp_email']      = $to_email;
        $_SESSION['user_name'] = $user_name;
        $_SESSION['otp_expires_at'] = time() + 300; // 5 minutes

        $subject = 'Identity Verification';
        $body = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <p>Hello <b>$user_name</b>,</p>
            <p>Your One-Time Password (OTP) for verifying your MarkMetrics account is:</p>
            <h1 style='letter-spacing: 4px; color: green; text-align:center;'>$otp</h1>
            <p>This code will expire in <b>5 minutes</b>.</p>
            <p>If you did not request this verification, please ignore this email.</p><br>
            <p>Regards,<br>
            <b style='color: #f58220;'>MarkMetrics Team</b></p>
        </body>
        </html>";

        // Send via PHPMailer
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
            $mail->addAddress($to_email, $user_name);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();

            // OTP success mssg
            $email_parts = explode('@', $to_email);
            $masked = substr($email_parts[0], 0, 3) . '***@' . $email_parts[1];
            $success_msg = "OTP sent to $masked";
            $step = 'otp';
        } catch (Exception $e) {
            $error_msg = "Failed to send OTP. Please try again later.";
            $step = 'id';
        }
    } else {
        $error_msg = "No account found with that Institutional ID.";
        $step = 'id';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_otp'])) {
    if (isset($_SESSION['otp_user_id']) && isset($_SESSION['otp_email'])) {
        $to_email  = $_SESSION['otp_email'];
        $user_id   = $_SESSION['otp_user_id'];

        // Re-generate OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['otp']            = $otp;
        $_SESSION['otp_expires_at'] = time() + 300;

        $subject = 'Identity Verification';
        $body = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <p>Hello <b>$user_name</b>,</p>
            <p>Your new One-Time Password (OTP) is:</p>
            <h1 style='letter-spacing: 4px; color: green; text-align:center;'>$otp</h1>
            <p>This code will expire in <b>5 minutes</b>.</p>
            <p>If you did not request this, please ignore this email.</p><br>
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

            $email_parts = explode('@', $to_email);
            $masked = substr($email_parts[0], 0, 3) . '***@' . $email_parts[1];
            $success_msg = "New OTP sent to $masked";
        } catch (Exception $e) {
            $error_msg = "Failed to resend OTP.";
        }
        $step = 'otp';
    } else {
        $error_msg = "Session expired. Please start over.";
        $step = 'id';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $entered_otp = trim($_POST['otp']);

    if (!isset($_SESSION['otp']) || !isset($_SESSION['otp_expires_at'])) {
        $error_msg = "Session expired. Please request a new OTP.";
        $step = 'id';
    } elseif (time() > $_SESSION['otp_expires_at']) {
        $error_msg = "OTP has expired. Please request a new one.";
        unset($_SESSION['otp'], $_SESSION['otp_expires_at']);
        $step = 'otp';
    } elseif ($entered_otp === $_SESSION['otp']) {
        // OTP matches — redirect to reset password page
        $_SESSION['otp_verified'] = true;
        header("Location: ../ResetPass/resetPass.php");
        exit;
    } else {
        $error_msg = "Incorrect OTP. Please try again.";
        $step = 'otp';
    }
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>MarkMetrics | Identity Verification</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="forgotPass.css">
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

        <!-- Identity Verification Card -->
        <div class="glass-card">
            <!-- Subtle Decorative Gradient -->
            <div class="card-glow"></div>
            <div class="card-content">
                <header class="card-header">
                    <h2>Identity Verification</h2>
                    <p>Provide your institutional identifier to receive a secure access code.</p>
                </header>

                <!-- ── Status Messages ── -->
                <?php if ($error_msg): ?>
                    <div class="msg msg-error">
                        <span class="material-symbols-outlined">error</span>
                        <?php echo htmlspecialchars($error_msg); ?>
                    </div>
                <?php endif; ?>
                <?php if ($success_msg): ?>
                    <div class="msg msg-success">
                        <span class="material-symbols-outlined">check_circle</span>
                        <?php echo htmlspecialchars($success_msg); ?>
                    </div>
                <?php endif; ?>

                <!-- ── Phase 1: Institutional ID ── -->
                <form method="POST" action="forgotPass.php">
                    <div class="form-group">
                        <div class="input-block">
                            <label class="input-label" for="institutional_id">Institutional ID</label>
                            <div class="input-wrap">
                                <input id="institutional_id" name="institutional_id" placeholder="e.g. 0112330784"
                                    type="text" required
                                     value="<?php echo ($step === 'otp' && isset($_SESSION['otp_user_id'])) ? htmlspecialchars($_SESSION['otp_user_id']) : ''; ?>"
                                    <?php echo ($step === 'otp') ? 'readonly' : ''; ?> />
                                <span class="material-symbols-outlined input-icon">fingerprint</span>
                            </div>
                        </div>
                        <?php if ($step === 'id'): ?>
                            <button class="btn-primary" type="submit" name="send_otp" id="sendOtpBtn">
                                Send OTP
                                <span class="material-symbols-outlined">arrow_forward</span>
                            </button>
                        <?php endif; ?>
                    </div>
                </form>

                <!-- Spacer / Tonal Shift -->
                <div class="step-divider">
                    <div class="step-divider-line"></div>
                    <span class="step-divider-text">Verification Step</span>
                    <div class="step-divider-line"></div>
                </div>

                <!-- ── Phase 2: OTP ── -->
                <form method="POST" action="forgotPass.php">
                    <div class="form-group otp-section <?php echo ($step === 'otp') ? 'active' : ''; ?>" id="otpSection">
                        <div class="input-block">
                            <div class="otp-header">
                                <label for="otp">One-Time Password</label>
                            </div>
                            <input class="otp-input" id="otp" name="otp" placeholder="Enter 6-digit code"
                                type="text" maxlength="6" required />
                        </div>
                        <button class="btn-secondary" type="submit" name="verify_otp">Verify Identity</button>
                    </div>
                </form>

                <!-- Resend OTP (only visible in OTP step) -->
                <?php if ($step === 'otp'): ?>
                    <form method="POST" action="forgotPass.php" class="resend-form">
                        <button class="resend-btn" type="submit" name="resend_otp">Resend Code</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Support Info -->
        <div class="support-link">
            <p>Remember your password? <a href="../Login/login.php">Back to Login</a></p>
        </div>
    </main>

    <!-- Footer -->
    <footer class="page-footer">
        <div class="footer-links">
            <a href="#">Support</a>
            <a href="#">Security Policy</a>
            <a href="#">Terms</a>
        </div>
        <p class="footer-copy">© 2026 MarkMetrics. Institutional Privacy Applied.</p>
    </footer>

    <!-- Background Decorative Elements -->
    <div class="bg-decor">
        <div class="bg-blob-1"></div>
        <div class="bg-blob-2"></div>
    </div>
</body>

</html>