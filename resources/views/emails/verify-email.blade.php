<!DOCTYPE html>
<html>
<head>
    <title>Email Verification</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #2d3748;
            background-color: #f7fafc;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            padding: 40px;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h2 {
            color: #2f855a;
            font-size: 28px;
            margin: 0;
            padding-bottom: 10px;
        }
        .otp-container {
            background-color: #f0fff4;
            border: 2px solid #c6f6d5;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 30px 0;
        }
        .otp-code {
            font-size: 32px;
            font-weight: bold;
            color: #2f855a;
            letter-spacing: 4px;
            font-family: 'Courier New', monospace;
        }
        .message {
            color: #4a5568;
            font-size: 16px;
            margin: 20px 0;
        }
        .expiry {
            color: #718096;
            font-size: 14px;
            font-style: italic;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 13px;
            color: #718096;
        }
        .disclaimer {
            background-color: #fff5f5;
            border-left: 4px solid #feb2b2;
            padding: 15px;
            margin: 20px 0;
            font-size: 14px;
            color: #4a5568;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Welcome to CommEase</h2>
        </div>

        <div class="message">
            Thank you for registering with CommEase. To complete your registration, please use the following verification code:
        </div>

        <div class="otp-container">
            <div class="otp-code">{{ $otp }}</div>
        </div>

        <div class="expiry">
            This code will expire in 10 minutes
        </div>

        <div class="disclaimer">
            If you did not request this verification, please ignore this email and ensure your account is secure.
        </div>

        <div class="footer">
            <p>This is an automated message, please do not reply to this email.</p>
            <p>© 2024 CommEase. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
