<?php
/**
 * Payment success redirect page
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Complete — PolyPay</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #09090b;
            color: #fafafa;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .card {
            text-align: center;
            max-width: 400px;
            padding: 48px 32px;
            background: #18181b;
            border: 1px solid #27272a;
            border-radius: 20px;
        }
        .icon {
            width: 64px; height: 64px;
            margin: 0 auto 20px;
            background: rgba(34, 197, 94, 0.12);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .icon svg { width: 32px; height: 32px; color: #22c55e; }
        h1 { font-size: 24px; font-weight: 700; margin-bottom: 8px; }
        p { font-size: 14px; color: #a1a1aa; line-height: 1.6; }
        .back-btn {
            display: inline-block;
            margin-top: 24px;
            padding: 12px 28px;
            background: #6366f1;
            color: white;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
        }
        .back-btn:hover { background: #4f46e5; transform: translateY(-1px); }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                <polyline points="4 12 9 17 20 6"/>
            </svg>
        </div>
        <h1>Payment Submitted</h1>
        <p>Your payment has been submitted. The merchant will confirm the transaction once it's verified on the blockchain.</p>
        <a href="/" class="back-btn">← Back to Checkout</a>
    </div>
</body>
</html>
