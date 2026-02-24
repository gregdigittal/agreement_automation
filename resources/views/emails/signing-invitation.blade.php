<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Please Sign: {{ $signer->session->contract->title ?? 'Contract' }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9fafb;">
    <div style="background-color: #ffffff; border-radius: 8px; padding: 32px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2 style="color: #4f46e5; margin-top: 0;">Document Signing Request</h2>

        <p style="color: #374151; font-size: 16px;">
            Hello {{ $signer->signer_name }},
        </p>

        <p style="color: #374151; font-size: 16px;">
            You have been requested to review and sign the following document:
        </p>

        <div style="background-color: #f3f4f6; border-radius: 6px; padding: 16px; margin: 24px 0;">
            <p style="margin: 0; font-weight: 600; color: #111827;">
                {{ $signer->session->contract->title ?? 'Contract' }}
            </p>
        </div>

        <div style="text-align: center; margin: 32px 0;">
            <a href="{{ route('signing.show', $signer->token) }}"
               style="display: inline-block; background-color: #4f46e5; color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 6px; font-size: 16px; font-weight: 600;">
                Review &amp; Sign Document
            </a>
        </div>

        <p style="color: #6b7280; font-size: 14px;">
            This link will expire on {{ $signer->token_expires_at?->format('F j, Y \a\t g:i A') ?? 'in 7 days' }}.
            Please complete your signature before the expiry date.
        </p>

        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 24px 0;">

        <p style="color: #9ca3af; font-size: 12px; margin-bottom: 0;">
            This is an automated message from CCRS (Contract & Merchant Agreement Repository System).
            If you did not expect this request, please disregard this email.
        </p>
    </div>
</body>
</html>
