<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendVerificationEmail($email, $activation_token, $display_name) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // Set the SMTP server to send through
        $mail->SMTPAuth = true;
        $mail->Username = 'sahaku502@gmail.com';
        $mail->Password = 'zbca evim lnym iwbi';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('noreply@yourdomain.com', 'Not Note App');
        $mail->addAddress($email, $display_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Verify your Not note account';
        
        $verification_link = "http://localhost/ltw-noteapp-final/backend/api/verify.php?token=" . urlencode($activation_token);
        
        $mail->Body = "
        <html>
        <head>
            <title>Verify your Not note account</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #9b5e35; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .button { display: inline-block; padding: 12px 24px; background-color: #9b5e35; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Welcome to Not!</h1>
                </div>
                <div class='content'>
                    <h2>Hello, {$display_name}!</h2>
                    <p>Thank you for registering with Not. To complete your registration and verify your account, please click the button below:</p>
                    <a href='{$verification_link}' class='button'>Verify Your Account</a>
                    <p>Or copy and paste this link into your browser:</p>
                    <p><a href='{$verification_link}'>{$verification_link}</a></p>
                    <p>If you did not create this account, please ignore this email.</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message, please do not reply to this email.</p>
                    <p>© 2025 Not Note App. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

function sendOTPEmail($email, $otp, $display_name) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'sahaku502@gmail.com';
        $mail->Password = 'zbca evim lnym iwbi';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('noreply@yourdomain.com', 'Not Note App');
        $mail->addAddress($email, $display_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset OTP - Not Note App';
        
        $mail->Body = "
        <html>
        <head>
            <title>Password Reset OTP - Not Note App</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #9b5e35; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .otp-box { background-color: #f6f0e7; border: 2px solid #000000; padding: 20px; text-align: center; margin: 20px 0; border-radius: 10px; }
                .otp-code { font-size: 32px; font-weight: bold; color: #9b5e35; letter-spacing: 5px; }
                .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Password Reset Request</h1>
                </div>
                <div class='content'>
                    <h2>Hello, {$display_name}!</h2>
                    <p>We received a request to reset your password for your Not note account.</p>
                    
                    <p>Please use the following OTP code to reset your password:</p>
                    <div class='otp-box'>
                        <div class='otp-code'>{$otp}</div>
                        <p style='margin: 10px 0; color: #666; font-size: 14px;'>This code will expire in 10 minutes</p>
                    </div>
                    
                    <p><strong>Instructions:</strong></p>
                    <ol>
                        <li>Go back to the password reset page</li>
                        <li>Enter this 6-digit code</li>
                        <li>Create your new password</li>
                    </ol>
                    
                    <p>If you did not request this password reset, please ignore this email. Your password will remain unchanged.</p>
                    
                    <p><strong>Security Note:</strong> This OTP will expire in 10 minutes for your security.</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message, please do not reply to this email.</p>
                    <p>© 2025 Not Note App. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("OTP email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

function sendPasswordResetEmail($email, $reset_token, $otp, $display_name) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'sahaku502@gmail.com';
        $mail->Password = 'zbca evim lnym iwbi';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('noreply@yourdomain.com', 'Not Note App');
        $mail->addAddress($email, $display_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Reset your Not note password';
        
        $reset_link = "http://localhost/ltw-noteapp-final/backend/api/reset_password_link.php?token=" . urlencode($reset_token);
        
        $mail->Body = "
        <html>
        <head>
            <title>Reset your Not note password</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #9b5e35; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .button { display: inline-block; padding: 12px 24px; background-color: #9b5e35; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .otp-box { background-color: #e8f5e8; border: 2px solid #9b5e35; padding: 15px; text-align: center; margin: 20px 0; border-radius: 5px; }
                .otp-code { font-size: 24px; font-weight: bold; color: #9b5e35; letter-spacing: 3px; }
                .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Password Reset Request</h1>
                </div>
                <div class='content'>
                    <h2>Hello, {$display_name}!</h2>
                    <p>We received a request to reset your password for your Not note account.</p>
                    
                    <h3>Option 1: Click the Reset Link</h3>
                    <p>Click the button below to reset your password directly:</p>
                    <a href='{$reset_link}' class='button'>Reset Password</a>
                    
                    <h3>Option 2: Use the OTP Code</h3>
                    <p>Or use this OTP code on the password reset page:</p>
                    <div class='otp-box'>
                        <div class='otp-code'>{$otp}</div>
                        <p style='margin: 5px 0; color: #666;'>This code expires in 1 hour</p>
                    </div>
                    
                    <p>If you did not request this password reset, please ignore this email. Your password will remain unchanged.</p>
                    
                    <p><strong>Security Note:</strong> This link and OTP will expire in 1 hour for your security.</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message, please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Password reset email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>
