<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Team Invitation') }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 0;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #1a9988, #15806f);
            padding: 32px 40px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            font-size: 24px;
            margin: 0;
            font-weight: 600;
        }
        .content {
            padding: 40px;
        }
        .content p {
            font-size: 16px;
            line-height: 1.6;
            color: #4a5568;
            margin-bottom: 16px;
        }
        .content .highlight {
            font-weight: 600;
            color: #1a9988;
        }
        .btn-container {
            text-align: center;
            margin: 32px 0;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #1a9988, #15806f);
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 40px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            letter-spacing: 0.3px;
            transition: opacity 0.2s;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .footer {
            padding: 24px 40px;
            text-align: center;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }
        .footer p {
            font-size: 13px;
            color: #94a3b8;
            margin: 0;
        }
        .info-box {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 16px;
            margin: 20px 0;
        }
        .info-box p {
            font-size: 14px;
            color: #166534;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 {{ __("You're Invited!") }}</h1>
        </div>

        <div class="content">
            <p>{{ __('Hello') }} <span class="highlight">{{ $invitedUser->name }}</span>,</p>

            <p>{{ __('You have been invited to join') }} <span class="highlight">{{ $companyName }}</span>. {{ __('Click the button below to verify your account and set up your password.') }}</p>

            <div class="btn-container">
                <a href="{{ $invitationUrl }}" class="btn">{{ __('Verify My Account') }}</a>
            </div>

            <div class="info-box">
                <p>✉️ {{ __('This invitation was sent to') }} <strong>{{ $invitedUser->email }}</strong>. {{ __('If you did not expect this invitation, you can safely ignore this email.') }}</p>
            </div>
        </div>

        <div class="footer">
            <p>© {{ date('Y') }} {{ $companyName }}. {{ __('All rights reserved.') }}</p>
        </div>
    </div>
</body>
</html>
