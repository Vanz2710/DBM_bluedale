<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unsubscribe</title>
    <style>
        body { margin: 0; background: #f1f5f9; font-family: Arial, Helvetica, sans-serif; color: #172033; }
        .card { max-width: 440px; margin: 8vh auto; background: #fff; border-radius: 12px; padding: 36px; box-shadow: 0 12px 40px rgba(15,23,42,.12); text-align: center; }
        h1 { font-size: 20px; margin: 0 0 10px; }
        p { color: #64748b; font-size: 14px; line-height: 1.6; }
        .email { font-weight: 700; color: #172033; }
        button { margin-top: 18px; height: 42px; padding: 0 22px; border: none; border-radius: 8px; background: #dc2626; color: #fff; font-size: 14px; font-weight: 700; cursor: pointer; }
        .done { color: #166534; font-weight: 700; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="card">
        @if ($done)
            <h1>You're unsubscribed</h1>
            <p><span class="email">{{ $email }}</span> will no longer receive marketing emails from us.</p>
            <p class="done">✓ Preference saved</p>
        @else
            <h1>Unsubscribe?</h1>
            <p>You are about to unsubscribe <span class="email">{{ $email }}</span> from all marketing emails.</p>
            <form method="POST" action="{{ url('/email/unsubscribe/' . $token) }}">
                @csrf
                <button type="submit">Yes, unsubscribe me</button>
            </form>
        @endif
    </div>
</body>
</html>
