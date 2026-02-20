<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $notification->subject }}</title>
</head>
<body>
    <div style="font-family: sans-serif; max-width: 600px; margin: 0 auto;">
        <h2>{{ $notification->subject }}</h2>
        <div>{!! nl2br(e($notification->body ?? '')) !!}</div>
    </div>
</body>
</html>
