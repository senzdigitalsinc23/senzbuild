<?php
declare(strict_types=1);

namespace App\Templates\Email;

/**
 * Email template for password reset
 */
class PasswordReset
{
    /**
     * Generate HTML email for password reset
     *
     * @param array $data Email data
     * @return string HTML email content
     */
    public static function generate(array $data): string
    {
        $username = $data['username'] ?? 'User';
        $newPassword = $data['new_password'] ?? 'N/A';
        $appName = $_ENV['APP_NAME'] ?? 'API Project';
        $appUrl = $_ENV['APP_URL'] ?? 'http://localhost';
        $supportEmail = $_ENV['SUPPORT_EMAIL'] ?? 'support@example.com';

        return "
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Password Reset - {$appName}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .content {
            padding: 40px 30px;
        }
        .alert-box {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            margin: 25px 0;
            border-radius: 4px;
        }
        .alert-box strong {
            color: #856404;
            font-size: 16px;
        }
        .password-box {
            background-color: #fff;
            border: 2px solid #f5576c;
            padding: 25px;
            margin: 25px 0;
            border-radius: 8px;
            text-align: center;
        }
        .password-box h3 {
            margin-top: 0;
            color: #f5576c;
        }
        .password-value {
            font-family: 'Courier New', monospace;
            font-size: 24px;
            color: #f5576c;
            font-weight: bold;
            letter-spacing: 2px;
            padding: 15px;
            background-color: #fff5f7;
            border-radius: 6px;
            margin: 15px 0;
        }
        .security-box {
            background-color: #d1ecf1;
            border-left: 4px solid #0c5460;
            padding: 20px;
            margin: 25px 0;
            border-radius: 4px;
        }
        .security-box strong {
            color: #0c5460;
        }
        .button {
            display: inline-block;
            padding: 14px 30px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
            font-weight: 600;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 25px;
            text-align: center;
            color: #6c757d;
            font-size: 14px;
            border-top: 1px solid #e9ecef;
        }
        .footer a {
            color: #f5576c;
            text-decoration: none;
        }
        ul {
            padding-left: 20px;
        }
        ul li {
            margin: 8px 0;
        }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>🔐 Password Reset Request</h1>
        </div>
        
        <div class='content'>
            <p>Hello <strong>{$username}</strong>,</p>
            
            <p>You have requested a password reset for your {$appName} account.</p>
            
            <div class='alert-box'>
                <strong>⚠️ If you did not request this password reset, please contact support immediately!</strong>
            </div>
            
            <div class='password-box'>
                <h3>Your New Temporary Password</h3>
                <div class='password-value'>{$newPassword}</div>
                <p style='color: #6c757d; font-size: 14px; margin-top: 15px;'>
                    Copy this password carefully - it's case-sensitive
                </p>
            </div>
            
            <div class='security-box'>
                <strong>🛡️ Important Security Instructions:</strong>
                <ul>
                    <li><strong>Login immediately</strong> with this temporary password</li>
                    <li><strong>Change your password</strong> as soon as you log in</li>
                    <li><strong>Choose a strong password</strong> with at least 8 characters</li>
                    <li><strong>Never share</strong> your password with anyone</li>
                    <li><strong>Delete this email</strong> after changing your password</li>
                </ul>
            </div>
            
            <div style='text-align: center;'>
                <a href='{$appUrl}/web/login' class='button'>Login Now</a>
            </div>
            
            <p style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #e9ecef;'>
                For security reasons, we recommend changing this temporary password immediately after logging in.
            </p>
            
            <p>If you continue to experience issues, please contact our support team.</p>
        </div>
        
        <div class='footer'>
            <p><strong>{$appName}</strong></p>
            <p>Need help? Contact us at <a href='mailto:{$supportEmail}'>{$supportEmail}</a></p>
            <p style='margin-top: 15px; font-size: 12px; color: #adb5bd;'>
                This is an automated message. Please do not reply to this email.
            </p>
        </div>
    </div>
</body>
</html>
        ";
    }

    /**
     * Generate plain text version
     *
     * @param array $data Email data
     * @return string Plain text email content
     */
    public static function generatePlainText(array $data): string
    {
        $username = $data['username'] ?? 'User';
        $newPassword = $data['new_password'] ?? 'N/A';
        $appName = $_ENV['APP_NAME'] ?? 'API Project';
        $appUrl = $_ENV['APP_URL'] ?? 'http://localhost';
        $supportEmail = $_ENV['SUPPORT_EMAIL'] ?? 'support@example.com';

        return "
Password Reset Request - {$appName}

Hello {$username},

You have requested a password reset for your {$appName} account.

WARNING: If you did not request this password reset, please contact support immediately!

YOUR NEW TEMPORARY PASSWORD
---------------------------
{$newPassword}

(Copy this password carefully - it's case-sensitive)

IMPORTANT SECURITY INSTRUCTIONS
--------------------------------
- Login immediately with this temporary password
- Change your password as soon as you log in
- Choose a strong password with at least 8 characters
- Never share your password with anyone
- Delete this email after changing your password

LOGIN URL
---------
{$appUrl}/web/login

For security reasons, we recommend changing this temporary password immediately after logging in.

If you continue to experience issues, please contact us at {$supportEmail}.

---
{$appName}
This is an automated message. Please do not reply to this email.
        ";
    }
}
