<?php
include "db.php";


if (!isset($_GET['booking_id']) || !(int)$_GET['booking_id']) {
    header("Location: bookings_list.php");
    exit;
}

$booking_id = (int)$_GET['booking_id'];

// Fetch booking with client & service info
$stmt = $conn->prepare("
    SELECT b.*, c.full_name AS client_name, s.service_name
    FROM bookings b
    JOIN clients c ON b.client_id = c.client_id
    JOIN services s ON b.service_id = s.service_id
    WHERE b.booking_id = ?
");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    header("Location: bookings_list.php");
    exit;
}

if ($booking['status'] === 'CANCELLED') {
    header("Location: bookings_list.php?error=cancelled");
    exit;
}

// Get total already paid
$stmt = $conn->prepare("SELECT IFNULL(SUM(amount_paid),0) AS paid FROM payments WHERE booking_id = ?");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$paidRow    = $stmt->get_result()->fetch_assoc();
$total_paid = (float)$paidRow['paid'];
$stmt->close();

$balance = $booking['total_cost'] - $total_paid;

// Fetch payment history for this booking
$stmt = $conn->prepare("SELECT * FROM payments WHERE booking_id = ? ORDER BY payment_id DESC");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$payment_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$error = "";


if (isset($_POST['pay'])) {
    $amount = (float)$_POST['amount_paid'];
    $method = $_POST['method'];
    $notes  = trim($_POST['notes'] ?? '');
    $allowed_methods = ['CASH', 'GCASH', 'CARD', 'BANK_TRANSFER'];

    if ($amount <= 0) {
        $error = "Amount must be greater than ₱0.00.";
    } elseif ($amount > $balance + 0.009) {
        $error = "Amount of ₱" . number_format($amount,2) . " exceeds remaining balance of ₱" . number_format($balance,2) . ".";
    } elseif (!in_array($method, $allowed_methods)) {
        $error = "Invalid payment method selected.";
    } else {
        $stmt = $conn->prepare("INSERT INTO payments (booking_id, amount_paid, method, notes, payment_date) VALUES (?, ?, ?, ?, CURDATE())");
        $stmt->bind_param("idss", $booking_id, $amount, $method, $notes);
        $stmt->execute();
        $stmt->close();

        // Recompute
        $stmt = $conn->prepare("SELECT IFNULL(SUM(amount_paid),0) AS paid FROM payments WHERE booking_id = ?");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $newPaid     = (float)$stmt->get_result()->fetch_assoc()['paid'];
        $stmt->close();
        $new_balance = $booking['total_cost'] - $newPaid;

        if ($new_balance <= 0.009) {
            $stmt = $conn->prepare("UPDATE bookings SET status='PAID' WHERE booking_id=?");
            $stmt->bind_param("i", $booking_id);
            $stmt->execute();
            $stmt->close();
            header("Location: bookings_list.php?success=paid");
            exit;
        }

        header("Location: payment_process.php?booking_id=$booking_id&success=partial");
        exit;
    }
}

$partial_success = isset($_GET['success']) && $_GET['success'] === 'partial';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Process Payment — Booking #<?php echo $booking_id; ?></title>
  <link rel="stylesheet" href="color.css">
  <style>
    .page-wrap { max-width: 960px; margin: 0 auto; padding: 0 32px 60px; }

    .summary-card, .form-card, .history-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      box-shadow: var(--shadow);
    }
    .summary-card { margin-top: 24px; }

    .card-header {
      background: var(--surface2);
      padding: 13px 20px;
      border-bottom: 1px solid var(--border);
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: var(--text-muted);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    /* Progress */
    .progress-wrap { padding: 16px 20px; border-bottom: 1px solid var(--border); }
    .progress-label { display: flex; justify-content: space-between; font-size: 0.78rem; color: var(--text-muted); margin-bottom: 8px; }
    .progress-bar { height: 8px; background: var(--border); border-radius: 4px; overflow: hidden; }
    .progress-fill { height: 100%; background: var(--accent); border-radius: 4px; transition: width 0.4s ease; }

    /* Summary grid */
    .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); }
    .summary-item {
      padding: 16px 20px;
      border-right: 1px solid var(--border);
      border-bottom: 1px solid var(--border);
    }
    .summary-item:nth-child(3n)       { border-right: none; }
    .summary-item:nth-last-child(-n+3) { border-bottom: none; }
    .s-label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.07em; color: var(--text-muted); margin-bottom: 4px; }
    .s-value { font-size: 0.95rem; font-weight: 600; color: var(--text); }

    /* Layout */
    .layout { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }

    /* Form inside card */
    form { background: none; border: none; border-radius: 0; box-shadow: none; padding: 20px; margin: 0; max-width: 100%; }
    .field { margin-bottom: 16px; }

    /* Method buttons */
    .method-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .method-btn {
      display: flex; align-items: center; gap: 8px;
      padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px;
      background: var(--bg); color: var(--text-muted);
      font-size: 0.82rem; font-weight: 600; cursor: pointer;
      transition: all 0.2s; letter-spacing: 0.02em;
    }
    .method-btn:hover { border-color: var(--accent-dim); color: var(--text); }
    .method-btn.selected { border-color: var(--accent); background: rgba(56,201,176,0.1); color: var(--accent); }

    /* Change box */
    .change-box {
      background: var(--surface2); border: 1px solid var(--border);
      border-radius: 8px; padding: 12px 14px; margin-top: 8px;
      display: flex; justify-content: space-between; align-items: center;
      font-size: 0.85rem; color: var(--text-muted);
    }
    .change-val { font-weight: 700; color: var(--text); }
    .change-val.positive { color: var(--accent); }
    .change-val.negative { color: var(--danger); }

    /* History */
    .history-empty { padding: 32px 20px; text-align: center; color: var(--text-muted); font-size: 0.88rem; }
    .history-item {
      display: flex; align-items: center; justify-content: space-between;
      padding: 13px 18px; border-bottom: 1px solid var(--border); font-size: 0.88rem;
    }
    .history-item:last-child { border-bottom: none; }
    .history-item .h-left { display: flex; flex-direction: column; gap: 3px; }
    .h-method {
      display: inline-block; padding: 2px 9px; border-radius: 20px;
      font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em;
      background: rgba(56,201,176,0.12); color: var(--accent);
    }
    .h-date { color: var(--text-muted); font-size: 0.78rem; }
    .h-amount { font-weight: 700; font-size: 0.95rem; color: var(--text); }

    /* Alerts */
    .alert { border-radius: 8px; padding: 11px 16px; font-size: 0.87rem; font-weight: 500; margin: 16px 20px 0; display: flex; align-items: center; gap: 8px; }
    .alert-error   { background: rgba(224,92,107,0.12); border:1px solid rgba(224,92,107,0.3); color:#f08090; }
    .alert-success { background: rgba(56,201,176,0.12); border:1px solid rgba(56,201,176,0.3); color:#38c9b0; margin:16px 0 0; }

    /* Submit btn */
    .submit-btn {
      width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px;
      background: var(--accent); color: #0f1923;
      font-family: 'DM Sans', sans-serif; font-weight: 700; font-size: 0.9rem;
      letter-spacing: 0.04em; padding: 12px 28px; border: none; border-radius: 8px;
      cursor: pointer; box-shadow: 0 2px 14px rgba(56,201,176,0.3);
      transition: background 0.2s, transform 0.2s, box-shadow 0.2s; margin-top: 4px;
    }
    .submit-btn:hover { background: #fff; transform: translateY(-1px); box-shadow: 0 4px 20px rgba(56,201,176,0.4); }

    .back-link { display: block; color: var(--text-muted); font-size: 0.85rem; margin-top: 20px; }
    .back-link:hover { color: var(--accent); opacity: 1; }

    input[type="number"], textarea {
      width: 100%; background: var(--bg); border: 1px solid var(--border);
      border-radius: 7px; padding: 10px 14px; color: var(--text);
      font-family: 'DM Sans', sans-serif; font-size: 0.92rem; outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
      appearance: none; -webkit-appearance: none;
    }
    input[type="number"]:focus, textarea:focus {
      border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow);
    }
    textarea { min-height: 70px; resize: vertical; }

    @media (max-width: 700px) {
      .layout { grid-template-columns: 1fr; }
      .summary-grid { grid-template-columns: 1fr 1fr; }
    }
  </style>
</head>
<body>
<?php include "nav.php"; ?>

<div class="page-wrap">
  <h2>Process Payment</h2>
  <a class="back-link" href="bookings_list.php">← Back to Bookings</a>

  <?php if ($partial_success): ?>
    <div class="alert alert-success">✓ Partial payment recorded. Please collect the remaining balance of <strong>₱<?php echo number_format($balance,2); ?></strong>.</div>
  <?php endif; ?>

  <!-- Booking Summary -->
  <div class="summary-card">
    <div class="card-header">
      <span>📋 Booking #<?php echo $booking_id; ?></span>
      <?php
        $badge_style = match($booking['status']) {
          'PAID'      => 'background:rgba(56,201,176,0.15);color:#38c9b0;',
          'CANCELLED' => 'background:rgba(122,154,181,0.12);color:#7a9ab5;',
          default     => 'background:rgba(240,160,69,0.15);color:#f0a045;'
        };
      ?>
      <span style="padding:3px 12px;border-radius:20px;font-size:0.72rem;font-weight:700;letter-spacing:0.06em;<?php echo $badge_style; ?>"><?php echo $booking['status']; ?></span>
    </div>

    <?php $pct = $booking['total_cost'] > 0 ? min(100, ($total_paid / $booking['total_cost']) * 100) : 0; ?>
    <div class="progress-wrap">
      <div class="progress-label">
        <span>Payment Progress</span>
        <span><?php echo number_format($pct, 1); ?>% paid</span>
      </div>
      <div class="progress-bar">
        <div class="progress-fill" style="width:<?php echo $pct; ?>%"></div>
      </div>
    </div>

    <div class="summary-grid">
      <div class="summary-item">
        <div class="s-label">Client</div>
        <div class="s-value"><?php echo htmlspecialchars($booking['client_name']); ?></div>
      </div>
      <div class="summary-item">
        <div class="s-label">Service</div>
        <div class="s-value"><?php echo htmlspecialchars($booking['service_name']); ?></div>
      </div>
      <div class="summary-item">
        <div class="s-label">Date</div>
        <div class="s-value"><?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></div>
      </div>
      <div class="summary-item">
        <div class="s-label">Total Cost</div>
        <div class="s-value">₱<?php echo number_format($booking['total_cost'],2); ?></div>
      </div>
      <div class="summary-item">
        <div class="s-label">Total Paid</div>
        <div class="s-value" style="color:var(--accent);">₱<?php echo number_format($total_paid,2); ?></div>
      </div>
      <div class="summary-item">
        <div class="s-label">Balance Due</div>
        <div class="s-value" style="color:<?php echo $balance > 0 ? 'var(--danger)' : 'var(--accent)'; ?>;">
          ₱<?php echo number_format($balance,2); ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Two-column layout -->
  <div class="layout">

    <!-- Payment Form -->
    <div class="form-card">
      <div class="card-header">💳 Record Payment</div>
      <?php if ($booking['status'] === 'PAID'): ?>
        <div style="padding:40px 20px;text-align:center;color:var(--accent);font-weight:600;">
          ✓ This booking is fully paid.
        </div>
      <?php else: ?>
        <?php if ($error): ?>
          <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post">
          <input type="hidden" name="method" id="method_input" value="CASH">

          <div class="field">
            <label>Payment Method</label>
            <div class="method-grid">
              <div class="method-btn selected" data-method="CASH" onclick="selectMethod(this)">
                <span>💵</span> Cash
              </div>
              <div class="method-btn" data-method="GCASH" onclick="selectMethod(this)">
                <span>📱</span> GCash
              </div>
              <div class="method-btn" data-method="BANK_TRANSFER" onclick="selectMethod(this)">
                <span>🏦</span> Bank Transfer
              </div>
              <div class="method-btn" data-method="CARD" onclick="selectMethod(this)">
                <span>💳</span> Card
              </div>
            </div>
          </div>

          <div class="field">
            <label>Amount Paid (₱)</label>
            <input type="number" name="amount_paid" id="amount_paid"
                   step="0.01" min="0.01"
                   max="<?php echo number_format($balance,2,'.',''); ?>"
                   value="<?php echo number_format($balance,2,'.',''); ?>"
                   placeholder="0.00" oninput="updateChange()" required>
            <div class="change-box">
              <span id="changeLabel">Change</span>
              <span class="change-val positive" id="changeVal">₱0.00</span>
            </div>
          </div>

          <div class="field">
            <label>Notes (optional)</label>
            <textarea name="notes" placeholder="Reference number, remarks..."></textarea>
          </div>

          <button type="submit" name="pay" class="submit-btn">✓ &nbsp; Save Payment</button>
        </form>
      <?php endif; ?>
    </div>

    <!-- Payment History -->
    <div class="history-card">
      <div class="card-header">
        <span>🧾 Payment History</span>
        <span><?php echo count($payment_history); ?> record<?php echo count($payment_history) !== 1 ? 's' : ''; ?></span>
      </div>
      <?php if (empty($payment_history)): ?>
        <div class="history-empty">No payments recorded yet.</div>
      <?php else: ?>
        <?php foreach ($payment_history as $p): ?>
          <div class="history-item">
            <div class="h-left">
              <span class="h-method"><?php echo htmlspecialchars($p['method']); ?></span>
              <span class="h-date">
                <?php echo !empty($p['payment_date']) ? date('M d, Y', strtotime($p['payment_date'])) : 'N/A'; ?>
                <?php if (!empty($p['notes'])): ?>
                  &nbsp;·&nbsp;<em style="color:var(--text-muted);font-size:.75rem;"><?php echo htmlspecialchars($p['notes']); ?></em>
                <?php endif; ?>
              </span>
            </div>
            <div class="h-amount">₱<?php echo number_format($p['amount_paid'],2); ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>
</div>

<script>
const balanceDue = <?php echo (float)$balance; ?>;

function selectMethod(el) {
  document.querySelectorAll('.method-btn').forEach(b => b.classList.remove('selected'));
  el.classList.add('selected');
  document.getElementById('method_input').value = el.dataset.method;
}

function formatPHP(n) {
  return '₱' + Math.abs(n).toLocaleString('en-PH', { minimumFractionDigits:2, maximumFractionDigits:2 });
}

function updateChange() {
  const paid   = parseFloat(document.getElementById('amount_paid').value) || 0;
  const change = paid - balanceDue;
  const el     = document.getElementById('changeVal');
  const lbl    = document.getElementById('changeLabel');

  if (change >= 0) {
    el.textContent  = formatPHP(change);
    el.className    = 'change-val positive';
    lbl.textContent = 'Change';
  } else {
    el.textContent  = '-' + formatPHP(change);
    el.className    = 'change-val negative';
    lbl.textContent = 'Remaining Balance';
  }
}

document.addEventListener('DOMContentLoaded', updateChange);
</script>
</body>
</html>