<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Signing Complete: {{ $session->contract->title ?? 'Contract' }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9fafb;">
    <div style="background-color: #ffffff; border-radius: 8px; padding: 32px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2 style="color: #059669; margin-top: 0;">Signing Complete</h2>

        <p style="color: #374151; font-size: 16px;">
            All parties have signed the following document:
        </p>

        <div style="background-color: #ecfdf5; border-radius: 6px; padding: 16px; margin: 24px 0;">
            <p style="margin: 0; font-weight: 600; color: #065f46;">
                {{ $session->contract->title ?? 'Contract' }}
            </p>
            <p style="margin: 8px 0 0 0; color: #047857; font-size: 14px;">
                Completed on {{ $session->completed_at?->format('F j, Y \a\t g:i A') ?? now()->format('F j, Y \a\t g:i A') }}
            </p>
        </div>

        <p style="color: #374151; font-size: 16px;">
            The signed document is now available in CCRS. All signers will retain a copy of this confirmation.
        </p>

        <h3 style="color: #374151; font-size: 14px; margin-bottom: 8px;">Signers:</h3>
        <ul style="color: #6b7280; font-size: 14px; padding-left: 20px;">
            @foreach ($session->signers as $signer)
                <li>{{ $signer->signer_name }} ({{ $signer->signer_email }}) &mdash; signed {{ $signer->signed_at?->format('M j, Y') }}</li>
            @endforeach
        </ul>

        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 24px 0;">

        <p style="color: #9ca3af; font-size: 12px; margin-bottom: 0;">
            This is an automated message from CCRS (Contract & Merchant Agreement Repository System).
            Do not reply to this email.
        </p>
    </div>
</body>
</html>
