<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>{{ $notification->subject }}</title></head>
<body style="font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #4f46e5;">CCRS Notification</h2>
    <p>{{ $notification->body }}</p>
    <hr>
    <p style="font-size: 12px; color: #6b7280;">
        This is an automated notification from CCRS.
        Do not reply to this email.
    </p>
</body>
</html>
