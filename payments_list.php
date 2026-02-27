<?php
include "db.php";


$allowed_methods = ['ALL', 'CASH', 'GCASH', 'CARD', 'BANK_TRANSFER'];
$filter = isset($_GET['method']) && in_array($_GET['method'], $allowed_methods) ? $_GET['method'] : 'ALL';

$where = ($filter !== 'ALL') ? "WHERE p.method = '$filter'" : "";

$sql = "
  SELECT p.*, b.booking_date, b.total_cost, c.full_name, s.service_name
  FROM payments p
  JOIN bookings b ON p.booking_id = b.booking_id
  JOIN clients c  ON b.client_id = c.client_id
  JOIN services s ON b.service_id = s.service_id
  $where
  ORDER BY p.payment_id DESC
";
$result = mysqli_query($conn, $sql);


$totals_res = mysqli_query($conn, "SELECT method, COUNT(*) AS cnt, SUM(amount_paid) AS total FROM payments GROUP BY method");
$totals = [];
$grand_total = 0;
$grand_count = 0;
while ($row = mysqli_fetch_assoc($totals_res)) {
    $totals[$row['method']] = $row;
    $grand_total += $row['total'];
    $grand_count += $row['cnt'];
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Payments</title>
  <link rel="stylesheet" href="color.css">
  <style>
    .page-wrap { max-width: 960px; margin: 0 auto; padding: 0 32px 60px; }

    /* ---- Summary Cards ---- */
    .summary-row {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
      gap: 14px;
      margin-top: 24px;
    }
    .sum-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 16px 18px;
      box-shadow: var(--shadow);
      transition: border-color 0.2s, transform 0.2s;
      cursor: default;
    }
    .sum-card:hover { border-color: var(--accent-dim); transform: translateY(-2px); }
    .sum-card .sc-label {
      font-size: 0.72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.07em;
      color: var(--text-muted);
      margin-bottom: 6px;
    }
    .sum-card .sc-value {
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--accent);
      letter-spacing: -0.02em;
    }
    .sum-card .sc-count {
      font-size: 0.78rem;
      color: var(--text-muted);
      margin-top: 2px;
    }

    /* ---- Filter Tabs ---- */
    .filter-tabs {
      display: flex; gap: 8px; margin-top: 20px; flex-wrap: wrap;
    }
    .filter-tab {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 7px 16px; border-radius: 6px; font-size: 0.8rem; font-weight: 600;
      letter-spacing: 0.04em; border: 1px solid var(--border); color: var(--text-muted);
      background: var(--surface); cursor: pointer; text-decoration: none;
      transition: all 0.2s;
    }
    .filter-tab:hover { color: var(--text); border-color: var(--accent-dim); opacity: 1; }
    .filter-tab.active { background: var(--accent); color: #0f1923; border-color: var(--accent); }
    .filter-tab .count {
      background: rgba(0,0,0,0.15); border-radius: 20px; padding: 1px 7px; font-size: 0.72rem;
    }

    /* ---- Table ---- */
    .table-wrap { margin-top: 20px; }
    table { width: 100%; }

    /* ---- Method badge ---- */
    .m-badge {
      display: inline-block; padding: 3px 10px; border-radius: 20px;
      font-size: 0.72rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase;
    }
    .m-cash  { background: rgba(56,201,176,0.12);  color: #38c9b0; }
    .m-gcash { background: rgba(56,130,243,0.12);  color: #5b9ef0; }
    .m-card  { background: rgba(240,160,69,0.12);  color: #f0a045; }
    .m-bank  { background: rgba(190,120,240,0.12); color: #c47ef0; }

    /* Empty state */
    .empty-state { padding: 48px 20px; text-align: center; color: var(--text-muted); font-size: 0.9rem; }
    .empty-state .icon { font-size: 2.5rem; margin-bottom: 10px; }
  </style>
</head>
<body>
<?php include "nav.php"; ?>

<div class="page-wrap">
  <h2>Payments</h2>

  <!-- Summary Cards -->
  <div class="summary-row">
    <div class="sum-card">
      <div class="sc-label">Total Revenue</div>
      <div class="sc-value">₱<?php echo number_format($grand_total,2); ?></div>
      <div class="sc-count"><?php echo $grand_count; ?> payment<?php echo $grand_count != 1 ? 's' : ''; ?></div>
    </div>
    <?php
      $method_icons = ['CASH' => '💵', 'GCASH' => '📱', 'CARD' => '💳', 'BANK_TRANSFER' => '🏦'];
      foreach ($method_icons as $m => $icon):
        $t = $totals[$m] ?? ['total' => 0, 'cnt' => 0];
    ?>
    <div class="sum-card">
      <div class="sc-label"><?php echo $icon; ?> <?php echo $m; ?></div>
      <div class="sc-value">₱<?php echo number_format($t['total'],2); ?></div>
      <div class="sc-count"><?php echo $t['cnt']; ?> record<?php echo $t['cnt'] != 1 ? 's' : ''; ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Filter Tabs -->
  <div class="filter-tabs">
    <?php
      $tab_labels = [
        'ALL'           => ['label' => 'All',           'count' => $grand_count],
        'CASH'          => ['label' => '💵 Cash',        'count' => $totals['CASH']['cnt'] ?? 0],
        'GCASH'         => ['label' => '📱 GCash',       'count' => $totals['GCASH']['cnt'] ?? 0],
        'CARD'          => ['label' => '💳 Card',        'count' => $totals['CARD']['cnt'] ?? 0],
        'BANK_TRANSFER' => ['label' => '🏦 Bank',        'count' => $totals['BANK_TRANSFER']['cnt'] ?? 0],
      ];
      foreach ($tab_labels as $key => $tab):
        $active = ($filter === $key) ? 'active' : '';
    ?>
      <a class="filter-tab <?php echo $active; ?>" href="payments_list.php?method=<?php echo $key; ?>">
        <?php echo $tab['label']; ?>
        <span class="count"><?php echo $tab['count']; ?></span>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Table -->
  <div class="table-wrap">
    <?php $rows = mysqli_num_rows($result); ?>
    <?php if ($rows === 0): ?>
      <div class="empty-state">
        <div class="icon">💰</div>
        <p>No payments found<?php echo ($filter !== 'ALL') ? " for <strong>$filter</strong>" : ''; ?>.</p>
      </div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Client</th>
            <th>Service</th>
            <th>Booking #</th>
            <th>Method</th>
            <th>Amount Paid</th>
            <th>Date</th>
            <th>Notes</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($p = mysqli_fetch_assoc($result)): ?>
            <tr>
              <td style="color:var(--text-muted);font-size:.82rem;">#<?php echo $p['payment_id']; ?></td>
              <td><strong><?php echo htmlspecialchars($p['full_name']); ?></strong></td>
              <td style="color:var(--text-muted);"><?php echo htmlspecialchars($p['service_name']); ?></td>
              <td>
                <a href="payment_process.php?booking_id=<?php echo $p['booking_id']; ?>"
                   style="color:var(--accent);font-size:.82rem;">#<?php echo $p['booking_id']; ?></a>
              </td>
              <td>
                <?php
                  $m_class = match($p['method']) {
                    'CASH'          => 'm-cash',
                    'GCASH'         => 'm-gcash',
                    'CARD'          => 'm-card',
                    'BANK_TRANSFER' => 'm-bank',
                    default         => 'm-cash'
                  };
                ?>
                <span class="m-badge <?php echo $m_class; ?>"><?php echo $p['method']; ?></span>
              </td>
              <td><strong>₱<?php echo number_format($p['amount_paid'],2); ?></strong></td>
              <td style="color:var(--text-muted);">
                <?php echo !empty($p['payment_date']) ? date('M d, Y', strtotime($p['payment_date'])) : '—'; ?>
              </td>
              <td style="color:var(--text-muted);font-size:.82rem;font-style:italic;">
                <?php echo !empty($p['notes']) ? htmlspecialchars($p['notes']) : '—'; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

</div>
</body>
</html>