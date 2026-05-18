<?php
/**
 * PolyPay PHP SDK Web Demo
 *
 * A complete checkout experience page built with the SDK.
 * Start with: php -S 0.0.0.0:8003 -t php-sdk/demo-web
 * Open: http://localhost:8003
 */

require_once __DIR__ . '/../autoload.php';

use PolyPay\PolyPay;

// ==================== Configuration ====================

$config = [
    'api_key'      => 'YOUR_API_KEY_HERE',
    'api_url'      => 'https://api.polypay.ai',
    'notify_url'   => 'https://your-site.com/webhook',
    'redirect_url' => 'https://your-site.com/success',
];

$polypay = new PolyPay($config['api_key'], [
    'api_url' => $config['api_url'],
]);

// ==================== API Routes ====================

$action = $_GET['action'] ?? '';

// Get payment methods
if ($action === 'methods') {
    header('Content-Type: application/json');
    try {
        $methods = $polypay->getPaymentMethods();
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

// Get merchant details
if ($action === 'merchant') {
    header('Content-Type: application/json');
    try {
        $merchant = $polypay->getMerchantDetail();
        echo json_encode(['success' => true, 'merchant' => $merchant->toArray()]);
    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Create an order
if ($action === 'create-order' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);

    try {
        $order = $polypay->createOrder([
            'mch_order_id' => 'DEMO_' . time() . '_' . substr(md5(uniqid()), 0, 6),
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
            'address'     => $order->address,
            'amount'      => $order->amount,
        ]);
    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ==================== Page Rendering ====================

$amount = isset($_GET['amount']) ? (float)$_GET['amount'] : 19.99;
$description = isset($_GET['desc']) ? htmlspecialchars($_GET['desc']) : 'SDK Demo Product';
$fiatCurrency = isset($_GET['currency']) ? htmlspecialchars($_GET['currency']) : 'USD';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PolyPay SDK Demo — Crypto Checkout</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --primary-glow: rgba(99, 102, 241, 0.25);
            --bg: #09090b;
            --surface: #18181b;
            --surface-hover: #1f1f23;
            --border: #27272a;
            --border-active: #6366f1;
            --text: #fafafa;
            --text-secondary: #a1a1aa;
            --text-muted: #71717a;
            --success: #22c55e;
            --error: #ef4444;
            --warning: #f59e0b;
            --radius: 12px;
            --radius-sm: 8px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 32px 16px;
        }

        /* === Top Bar === */
        .top-bar {
            width: 100%;
            max-width: 520px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .logo {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .logo-dot {
            width: 8px; height: 8px;
            background: var(--primary);
            border-radius: 50%;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.2); }
        }

        .badge {
            font-size: 11px;
            padding: 4px 10px;
            background: rgba(99, 102, 241, 0.12);
            color: var(--primary);
            border-radius: 20px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        /* === Main Card === */
        .checkout-card {
            width: 100%;
            max-width: 520px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            overflow: hidden;
        }

        /* === Amount Header === */
        .amount-header {
            padding: 32px 28px;
            text-align: center;
            background: linear-gradient(180deg, rgba(99, 102, 241, 0.08) 0%, transparent 100%);
            position: relative;
        }

        .amount-header::before {
            content: '';
            position: absolute;
            top: 0; left: 50%; transform: translateX(-50%);
            width: 200px; height: 1px;
            background: linear-gradient(90deg, transparent, var(--primary), transparent);
        }

        .product-name {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .amount-display {
            font-size: 48px;
            font-weight: 700;
            letter-spacing: -2px;
            line-height: 1;
            background: linear-gradient(135deg, var(--text), var(--text-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .amount-currency {
            font-size: 18px;
            font-weight: 500;
            color: var(--text-muted);
            margin-left: 6px;
            letter-spacing: 0;
        }

        .selected-info {
            display: none;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 12px;
            padding: 6px 14px;
            background: rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.2);
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
        }

        .selected-info.is-visible {
            display: inline-flex;
        }

        .selected-info img {
            width: 18px; height: 18px;
            border-radius: 50%;
        }

        .selected-info .sep {
            color: var(--text-muted);
            font-size: 11px;
        }

        /* === Content Area === */
        .checkout-body {
            padding: 24px 28px 28px;
        }

        .section-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 14px;
        }

        /* === Network Accordion === */
        .network-item {
            border: 1px solid var(--border);
            border-radius: var(--radius);
            margin-bottom: 8px;
            overflow: hidden;
            transition: border-color 0.2s;
        }

        .network-item.has-selection {
            border-color: var(--border-active);
        }

        .network-trigger {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            cursor: pointer;
            user-select: none;
            transition: background 0.15s;
        }

        .network-trigger:hover {
            background: var(--surface-hover);
        }

        .network-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .network-icon {
            width: 36px; height: 36px;
            border-radius: 10px;
            overflow: hidden;
            flex-shrink: 0;
        }

        .network-icon img {
            width: 100%; height: 100%;
            object-fit: cover;
        }

        .network-name {
            font-size: 14px;
            font-weight: 600;
        }

        .network-count {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 1px;
        }

        .arrow-icon {
            width: 20px; height: 20px;
            color: var(--text-muted);
            transition: transform 0.25s ease;
        }

        .network-item.is-expanded .arrow-icon {
            transform: rotate(180deg);
        }

        .network-panel {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .network-item.is-expanded .network-panel {
            max-height: 300px;
        }

        .token-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 8px;
            padding: 4px 16px 16px;
        }

        /* === Token Cards === */
        .token-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            padding: 16px 8px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .token-card:hover {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.06);
            transform: translateY(-1px);
        }

        .token-card.is-selected {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.1);
            box-shadow: 0 0 0 1px var(--primary), 0 4px 12px var(--primary-glow);
        }

        .token-card.is-selected .token-check {
            opacity: 1;
            transform: scale(1);
        }

        .token-check {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 18px; height: 18px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transform: scale(0.5);
            transition: all 0.2s ease;
        }

        .token-check svg {
            width: 12px; height: 12px;
            color: white;
        }

        .token-icon {
            width: 36px; height: 36px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
        }

        .token-icon img {
            width: 100%; height: 100%;
            object-fit: cover;
        }

        .token-name {
            font-size: 13px;
            font-weight: 600;
        }

        /* === Pay Button === */
        .pay-btn {
            width: 100%;
            padding: 16px;
            margin-top: 20px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-family: inherit;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            position: relative;
            overflow: hidden;
        }

        .pay-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1), transparent);
            opacity: 0;
            transition: opacity 0.2s;
        }

        .pay-btn:hover:not(:disabled)::before {
            opacity: 1;
        }

        .pay-btn:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 4px 16px var(--primary-glow);
        }

        .pay-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .pay-btn .arrow {
            transition: transform 0.2s;
        }

        .pay-btn:hover:not(:disabled) .arrow {
            transform: translateX(3px);
        }

        /* === Error Message === */
        .error-banner {
            display: none;
            padding: 12px 16px;
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: var(--radius-sm);
            color: var(--error);
            font-size: 13px;
            margin-bottom: 16px;
            align-items: center;
            gap: 8px;
        }

        /* === Loading === */
        .loading-mask {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(9, 9, 11, 0.85);
            backdrop-filter: blur(4px);
            z-index: 999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            gap: 20px;
        }

        .loading-spinner {
            width: 40px; height: 40px;
            border: 3px solid var(--border);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: rotate 0.7s linear infinite;
        }

        @keyframes rotate {
            to { transform: rotate(360deg); }
        }

        .loading-text {
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* === Footer === */
        .checkout-footer {
            text-align: center;
            padding: 16px;
            font-size: 12px;
            color: var(--text-muted);
        }

        .checkout-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        /* === Merchant Info === */
        .merchant-bar {
            width: 100%;
            max-width: 520px;
            margin-top: 16px;
            text-align: center;
            font-size: 12px;
            color: var(--text-muted);
        }

        .merchant-bar span {
            color: var(--text-secondary);
            font-weight: 500;
        }
    </style>
</head>
<body>

<div class="top-bar">
    <div class="logo">
        <div class="logo-dot"></div>
        PolyPay
    </div>
    <div class="badge">SDK Demo</div>
</div>

<div class="checkout-card">
    <div class="amount-header">
        <div class="product-name"><?php echo $description; ?></div>
        <div class="amount-display">
            <?php echo number_format($amount, 2); ?>
            <span class="amount-currency"><?php echo $fiatCurrency; ?></span>
        </div>
        <div class="selected-info" id="selectedInfo"></div>
    </div>

    <div class="checkout-body">
        <div class="error-banner" id="errorBanner">
            <svg viewBox="0 0 20 20" fill="currentColor" style="width:16px;height:16px;flex-shrink:0">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z"/>
            </svg>
            <span id="errorText"></span>
        </div>

        <div class="section-label">Select Payment Method</div>
        <div id="methodsContainer">
            <div style="text-align:center; padding:24px; color:var(--text-muted); font-size:14px;">
                <div class="loading-spinner" style="display:inline-block;width:24px;height:24px;margin-bottom:8px;"></div>
                <div>Loading payment methods...</div>
            </div>
        </div>

        <button type="button" class="pay-btn" id="payBtn" disabled>
            <span>Confirm & Pay</span>
            <svg class="arrow" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M3 8h10M9 4l4 4-4 4"/>
            </svg>
        </button>
    </div>

    <div class="loading-mask" id="loadingMask">
        <div class="loading-spinner"></div>
        <div class="loading-text">Creating order...</div>
    </div>

    <div class="checkout-footer">
        Secured by <a href="https://polypay.ai" target="_blank">PolyPay</a> · PHP SDK v1.0
    </div>
</div>

<div class="merchant-bar" id="merchantBar"></div>

<script>
(function() {
    var container = document.getElementById('methodsContainer');
    var payBtn = document.getElementById('payBtn');
    var errorBanner = document.getElementById('errorBanner');
    var errorText = document.getElementById('errorText');
    var loadingMask = document.getElementById('loadingMask');
    var merchantBar = document.getElementById('merchantBar');
    var selectedInfo = document.getElementById('selectedInfo');
    var selected = null;
    var amount = <?php echo json_encode($amount); ?>;

    // TrustWallet CDN icons, aligned with the wallet list in front-end-next.
    var TW = 'https://raw.githubusercontent.com/trustwallet/assets/master/blockchains';
    var networkIcons = {
        'tron':      TW + '/tron/info/logo.png',
        'ethereum':  TW + '/ethereum/info/logo.png',
        'bsc':       TW + '/smartchain/info/logo.png',
        'solana':    TW + '/solana/info/logo.png',
        'polygon':   TW + '/polygon/info/logo.png',
        'ton':       TW + '/ton/info/logo.png',
        'arbitrum':  TW + '/arbitrum/info/logo.png',
        'optimism':  TW + '/optimism/info/logo.png',
        'avalanche': TW + '/avalanchec/info/logo.png',
        'base':      TW + '/base/info/logo.png'
    };

    var tokenIcons = {
        'USDT': TW + '/tron/assets/TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t/logo.png',
        'USDC': TW + '/tron/assets/TEkxiTehnzSmSe2XqrBj4w32RUN966rdz8/logo.png',
        'BUSD': TW + '/smartchain/assets/0xe9e7CEA3DedcA5984780Bafc599bD69ADd087D56/logo.png',
        'DAI':  TW + '/ethereum/assets/0x6B175474E89094C44Da98b954EesdeCD1sCe3D4243/logo.png',
        'ETH':  TW + '/ethereum/info/logo.png',
        'BNB':  TW + '/smartchain/info/logo.png',
        'TRX':  TW + '/tron/info/logo.png',
        'SOL':  TW + '/solana/info/logo.png',
        'TON':  TW + '/ton/info/logo.png'
    };

    function showError(msg) {
        errorText.textContent = msg;
        errorBanner.style.display = 'flex';
        loadingMask.style.display = 'none';
    }

    function hideError() {
        errorBanner.style.display = 'none';
    }

    function getNetworkIcon(name) {
        return networkIcons[name.toLowerCase()] || '';
    }

    function getTokenIcon(name) {
        return tokenIcons[name.toUpperCase()] || '';
    }

    // Load merchant details.
    fetch('?action=merchant')
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success && d.merchant && d.merchant.name) {
                merchantBar.textContent = 'Merchant: ';
                var s = document.createElement('span');
                s.textContent = d.merchant.name;
                merchantBar.appendChild(s);
            }
        })
        .catch(function() {});

    // Load payment methods.
    fetch('?action=methods')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success || !data.methods || data.methods.length === 0) {
                container.innerHTML = '<div style="text-align:center;color:#ef4444;padding:16px;">No payment methods available</div>';
                return;
            }

            container.innerHTML = '';

            data.methods.forEach(function(m, idx) {
                var item = document.createElement('div');
                item.className = 'network-item' + (idx === 0 ? ' is-expanded' : '');

                var nIcon = getNetworkIcon(m.network);
                var count = (m.currencies || []).length;

                // Trigger
                var trigger = document.createElement('div');
                trigger.className = 'network-trigger';
                trigger.innerHTML =
                    '<div class="network-info">' +
                        '<div class="network-icon"><img src="' + nIcon + '" alt="' + m.network + '" onerror="this.style.display=\'none\'"></div>' +
                        '<div><div class="network-name">' + m.network + '</div>' +
                        '<div class="network-count">' + count + ' token' + (count > 1 ? 's' : '') + '</div></div>' +
                    '</div>' +
                    '<svg class="arrow-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>';

                trigger.onclick = function() {
                    var wasExpanded = item.classList.contains('is-expanded');
                    document.querySelectorAll('.network-item').forEach(function(el) { el.classList.remove('is-expanded'); });
                    if (!wasExpanded) item.classList.add('is-expanded');
                };

                // Panel
                var panel = document.createElement('div');
                panel.className = 'network-panel';
                var grid = document.createElement('div');
                grid.className = 'token-grid';

                (m.currencies || []).forEach(function(c) {
                    var card = document.createElement('div');
                    card.className = 'token-card';
                    card.dataset.network = m.network;
                    card.dataset.currency = c;

                    var tIcon = getTokenIcon(c);
                    card.innerHTML =
                        '<div class="token-check"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="3 8 7 12 13 4"/></svg></div>' +
                        '<div class="token-icon"><img src="' + tIcon + '" alt="' + c + '" onerror="this.style.display=\'none\'"></div>' +
                        '<div class="token-name">' + c + '</div>';

                    card.onclick = function(e) {
                        e.stopPropagation();
                        document.querySelectorAll('.token-card').forEach(function(el) { el.classList.remove('is-selected'); });
                        document.querySelectorAll('.network-item').forEach(function(el) { el.classList.remove('has-selection'); });
                        card.classList.add('is-selected');
                        item.classList.add('has-selection');
                        selected = { network: m.network, currency: c };
                        payBtn.disabled = false;
                        hideError();

                        // Update the selected summary shown at the top.
                        var ni = getNetworkIcon(m.network);
                        var ti = getTokenIcon(c);
                        selectedInfo.innerHTML =
                            '<img src="' + ni + '" alt="' + m.network + '">' +
                            '<span>' + m.network + '</span>' +
                            '<span class="sep">·</span>' +
                            '<img src="' + ti + '" alt="' + c + '">' +
                            '<span>' + c + '</span>';
                        selectedInfo.classList.add('is-visible');
                    };

                    grid.appendChild(card);
                });

                panel.appendChild(grid);
                item.appendChild(trigger);
                item.appendChild(panel);
                container.appendChild(item);
            });
        })
        .catch(function() { showError('Failed to load payment methods. Check console for details.'); });

    // Create the order.
    payBtn.onclick = function() {
        if (!selected) return;

        loadingMask.style.display = 'flex';
        hideError();

        fetch('?action=create-order', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                network: selected.network,
                currency: selected.currency,
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
