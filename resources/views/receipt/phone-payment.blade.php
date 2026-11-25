<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>CarSafe Receipt - Trip #{{ $payment->trip_id }}</title>
    <style>
        body { font-family: -apple-system, Segoe UI, Roboto, Arial, sans-serif; background: #f4f5f7; margin: 0; padding: 24px; color: #222; }
        .card { max-width: 420px; margin: 0 auto; background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,.08); padding: 24px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .paid { display: inline-block; background: #d1e7dd; color: #0f5132; font-weight: 600; padding: 4px 10px; border-radius: 6px; margin: 8px 0 16px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 8px 0; border-bottom: 1px solid #eee; }
        td:last-child { text-align: right; font-weight: 600; }
        .foot { font-size: 13px; color: #666; margin-top: 16px; }
    </style>
</head>
<body>
<div class="card">
    <h1>CarSafe</h1>
    <div class="paid">Paid by phone</div>
    <table>
        <tr><td>Trip</td><td>#{{ $payment->trip_id }}</td></tr>
        <tr><td>Amount paid</td><td>${{ number_format((float) $payment->charged_amount, 2) }}</td></tr>
        <tr><td>Date &amp; time</td><td>{{ $payment->paidAtLabel() }} ET</td></tr>
        @if($payment->card_last4)
        <tr><td>Card</td><td>ending {{ $payment->card_last4 }}</td></tr>
        @endif
        @if($payment->reference)
        <tr><td>Reference</td><td>{{ $payment->reference }}</td></tr>
        @endif
    </table>
    <p class="foot">Thank you for riding with CarSafe. Questions? Call 845-512-5555.</p>
</div>
</body>
</html>
