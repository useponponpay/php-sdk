<?php
/**
 * PonponPay PHP SDK - full payment page example
 *
 * This example shows how to build a complete cryptocurrency checkout page with the SDK.
 * It includes backend order creation, frontend payment-method selection, and automatic redirect.
 *
 * Deployment:
 *   1. Place this file in your web root
 *   2. Update the configuration below
 *   3. Visit https://your-site.com/payment_page.php?amount=10&description=TestProduct
 */

// Load via Composer
// require_once __DIR__ . '/../vendor/autoload.php';

// Load without Composer
require_once __DIR__ . '/../autoload.php';

use PonponPay\PonponPay;
use PonponPay\Exception\ApiException;

// ==================== Configuration ====================

$config = [
    'api_key'      => 'YOUR_API_KEY_HERE',  // Replace with your API Key
    'notify_url'   => 'https://your-site.com/webhook.php',  // Callback URL
    'redirect_url' => 'https://your-site.com/success',       // Redirect after successful payment
];

// ==================== Handle Requests ====================

$amount = isset($_GET['amount']) ? (float)$_GET['amount'] : 0;
$description = isset($_GET['description']) ? htmlspecialchars($_GET['description']) : '';
$fiatCurrency = isset($_GET['currency']) ? htmlspecialchars($_GET['currency']) : 'USD';

// AJAX request: fetch payment methods
if (isset($_GET['action']) && $_GET['action'] === 'methods') {
    header('Content-Type: application/json');
    try {
        $ponponpay = new PonponPay($config['api_key']);
        $methods = $ponponpay->getPaymentMethods();
        $result = [];
        foreach ($methods as $method) {
            $result[] = $method->toArray();
        }
        echo json_encode(['success' => true, 'methods' => $result]);
    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// AJAX request: create an order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'create-order') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);

    try {
        $ponponpay = new PonponPay($config['api_key']);
        $order = $ponponpay->createOrder([
            'mch_order_id' => 'SDK_' . time() . '_' . substr(md5(uniqid()), 0, 8),
            'currency'     => $input['currency'] ?? '',
            'network'      => $input['network'] ?? '',
            'amount'       => (float)($input['amount'] ?? 0),
            'notify_url'   => $config['notify_url'],
            'redirect_url' => $config['redirect_url'],
        ]);

        echo json_encode([
            'success'     => true,
            'payment_url' => $order->paymentUrl,
            'trade_id'    => $order->tradeId,
        ]);
    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Validate the amount
if ($amount <= 0) {
    http_response_code(400);
    echo 'Missing or invalid amount parameter. Usage: ?amount=10&description=Product';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay with Crypto - PonponPay</title>
    <style>
        :root {
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --bg: #0f0f14;
            --card-bg: #1a1a24;
            --card-border: #2a2a3a;
            --text: #e4e4e7;
            --text-muted: #71717a;
            --success: #22c55e;
            --error: #ef4444;
            --radius: 12px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .checkout-container {
            width: 100%;
            max-width: 440px;
            background: var(--card-bg);
            border-radius: 20px;
            border: 1px solid var(--card-border);
            overflow: hidden;
        }

        .header {
            padding: 28px 24px;
            text-align: center;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(99, 102, 241, 0.05));
            border-bottom: 1px solid var(--card-border);
        }

        .header .brand {
            font-size: 13px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-bottom: 12px;
        }

        .header .amount {
            font-size: 36px;
            font-weight: 700;
            color: var(--text);
        }

        .header .amount .currency {
            font-size: 16px;
            color: var(--text-muted);
            margin-left: 4px;
        }

        .header .desc {
            font-size: 14px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .content {
            padding: 20px 24px 24px;
        }

        .section-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }

        .network-group {
            margin-bottom: 8px;
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            overflow: hidden;
        }

        .network-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .network-header:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        .network-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: 14px;
        }

        .network-body {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }

        .network-group.is-open .network-body {
            max-height: 500px;
        }

        .network-group.is-open .chevron {
            transform: rotate(180deg);
        }

        .chevron {
            width: 20px;
            height: 20px;
            color: var(--text-muted);
            transition: transform 0.3s;
        }

        .currency-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
            gap: 8px;
            padding: 0 16px 16px;
        }

        .method-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            padding: 14px 8px;
            border-radius: 10px;
            border: 1px solid var(--card-border);
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }

        .method-card:hover {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.08);
        }

        .method-card.selected {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.12);
        }

        .method-card.selected::after {
            content: '✓';
            position: absolute;
            top: 4px;
            right: 6px;
            font-size: 11px;
            color: var(--primary);
            font-weight: 700;
        }

        .method-currency {
            font-size: 12px;
            font-weight: 600;
        }

        .network-logo {
            width: 28px;
            height: 28px;
            border-radius: 50%;
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            margin-top: 20px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover:not(:disabled) {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }

        .btn-submit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .error-msg {
            display: none;
            padding: 12px;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: var(--radius);
            color: var(--error);
            font-size: 13px;
            margin-bottom: 16px;
        }

        .loading-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            gap: 16px;
        }

        .spinner {
            width: 36px;
            height: 36px;
            border: 3px solid var(--card-border);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .footer {
            text-align: center;
            padding: 16px;
            font-size: 12px;
            color: var(--text-muted);
            border-top: 1px solid var(--card-border);
        }

        .footer a {
            color: var(--primary);
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="checkout-container">
    <div class="header">
        <div class="brand">🔐 Crypto Payment</div>
        <div class="amount">
            <?php echo number_format($amount, 2); ?>
            <span class="currency"><?php echo $fiatCurrency; ?></span>
        </div>
        <?php if ($description): ?>
            <div class="desc"><?php echo $description; ?></div>
        <?php endif; ?>
    </div>

    <div class="content">
        <div class="error-msg" id="errorMsg"></div>
        <div class="section-title">Select Payment Method</div>
        <div id="methodsContainer">
            <div style="text-align:center; padding:20px; color:var(--text-muted); font-size:14px;">
                Loading methods...
            </div>
        </div>
        <button type="button" class="btn-submit" id="submitBtn" disabled>
            Confirm & Pay →
        </button>
    </div>

    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
        <div style="color:var(--primary); font-weight:500;">Processing...</div>
    </div>

    <div class="footer">
        Secured by <a href="https://ponponpay.com" target="_blank">PonponPay</a>
    </div>
</div>

<script>
(function() {
    var container = document.getElementById('methodsContainer');
    var submitBtn = document.getElementById('submitBtn');
    var errorMsg = document.getElementById('errorMsg');
    var overlay = document.getElementById('loadingOverlay');
    var selectedValue = null;
    var pageUrl = window.location.pathname;
    var amount = <?php echo json_encode($amount); ?>;

    function showError(msg) {
        errorMsg.textContent = msg;
        errorMsg.style.display = 'block';
        overlay.style.display = 'none';
    }

    function buildIcon(label, bg) {
        var s = label.slice(0, 4).toUpperCase();
        var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 28 28">'
            + '<rect width="28" height="28" rx="14" fill="' + bg + '"/>'
            + '<text x="14" y="17" text-anchor="middle" font-family="Arial" font-size="10" font-weight="700" fill="#fff">' + s + '</text></svg>';
        return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg);
    }

    // Fetch payment methods.
    fetch(pageUrl + '?action=methods')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success || !data.methods || data.methods.length === 0) {
                container.innerHTML = '<div style="text-align:center;color:#ef4444;padding:12px;">No payment methods available</div>';
                return;
            }
            container.innerHTML = '';
            data.methods.forEach(function(m, i) {
                var group = document.createElement('div');
                group.className = 'network-group' + (i === 0 ? ' is-open' : '');

                var header = document.createElement('div');
                header.className = 'network-header';
                header.innerHTML = '<div class="network-title"><img class="network-logo" src="' + buildIcon(m.network, '#6366f1') + '" alt="' + m.network + '">' + m.network + '</div>'
                    + '<svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>';
                header.onclick = function() {
                    var open = group.classList.contains('is-open');
                    document.querySelectorAll('.network-group').forEach(function(g) { g.classList.remove('is-open'); });
                    if (!open) group.classList.add('is-open');
                };

                var body = document.createElement('div');
                body.className = 'network-body';
                var grid = document.createElement('div');
                grid.className = 'currency-grid';

                (m.currencies || []).forEach(function(c) {
                    var card = document.createElement('div');
                    card.className = 'method-card';
                    card.dataset.value = m.network + '|' + c;
                    card.innerHTML = '<img class="network-logo" src="' + buildIcon(c, '#4f46e5') + '" alt="' + c + '">'
                        + '<div class="method-currency">' + c + '</div>';
                    card.onclick = function(e) {
                        e.stopPropagation();
                        document.querySelectorAll('.method-card').forEach(function(c) { c.classList.remove('selected'); });
                        card.classList.add('selected');
                        selectedValue = card.dataset.value;
                        submitBtn.disabled = false;
                    };
                    grid.appendChild(card);
                });

                body.appendChild(grid);
                group.appendChild(header);
                group.appendChild(body);
                container.appendChild(group);
            });
        })
        .catch(function() { showError('Failed to load payment methods'); });

    // Submit the order.
    submitBtn.onclick = function() {
        if (!selectedValue) return;
        var parts = selectedValue.split('|');

        overlay.style.display = 'flex';
        errorMsg.style.display = 'none';

        fetch(pageUrl + '?action=create-order', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                network: parts[0],
                currency: parts[1],
                amount: amount
            })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.payment_url) {
                window.location.href = data.payment_url;
            } else {
                showError(data.error || 'Failed to create order');
            }
        })
        .catch(function() { showError('Network error. Please try again.'); });
    };
})();
</script>

</body>
</html>
