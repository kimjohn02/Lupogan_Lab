<?php
include "db.php";

/* ============================
   CANCEL BOOKING
   ============================ */
if (isset($_GET['cancel_id'])) {
    $cancel_id = (int)$_GET['cancel_id'];
    mysqli_query($conn, "UPDATE bookings SET status='CANCELLED' WHERE booking_id=$cancel_id AND status='PENDING'");
    header("Location: bookings_list.php?success=cancelled");
    exit;
}

/* ============================
   STATUS FILTER
   ============================ */
$allowed_statuses = ['ALL', 'PENDING', 'PAID', 'CANCELLED'];
$filter = isset($_GET['status']) && in_array($_GET['status'], $allowed_statuses) ? $_GET['status'] : 'ALL';

$where = ($filter !== 'ALL') ? "WHERE b.status = '$filter'" : "";

$sql = "
  SELECT b.*, c.full_name AS client_name, s.service_name
  FROM bookings b
  JOIN clients c ON b.client_id = c.client_id
  JOIN services s ON b.service_id = s.service_id
  $where
  ORDER BY b.booking_id DESC
";
$result = mysqli_query($conn, $sql);

/* ============================
   COUNTS PER STATUS
   ============================ */
$counts = ['ALL' => 0, 'PENDING' => 0, 'PAID' => 0, 'CANCELLED' => 0];
$cnt_res = mysqli_query($conn, "SELECT status, COUNT(*) AS c FROM bookings GROUP BY status");
while ($row = mysqli_fetch_assoc($cnt_res)) {
    if (isset($counts[$row['status']])) {
        $counts[$row['status']] = $row['c'];
        $counts['ALL'] += $row['c'];
    }
}

$success = $_GET['success'] ?? '';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Bookings</title>
  <link rel="stylesheet" href="color.css">
  <style>
    /* ---- Filter Tabs ---- */
    .filter-tabs {
      display: flex;
      gap: 8px;
      max-width: 960px;
      margin: 20px auto 0;
      padding: 0 32px;
      flex-wrap: wrap;
    }
    .filter-tab {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 7px 16px;
      border-radius: 6px;
      font-size: 0.8rem;
      font-weight: 600;
      letter-spacing: 0.04em;
      border: 1px solid var(--border);
      color: var(--text-muted);
      background: var(--surface);
      cursor: pointer;
      text-decoration: none;
      transition: all 0.2s ease;
    }
    .filter-tab:hover { color: var(--text); border-color: var(--accent-dim); opacity: 1; }
    .filter-tab.active { background: var(--accent); color: #0f1923; border-color: var(--accent); }
    .filter-tab .count {
      background: rgba(0,0,0,0.15);
      border-radius: 20px;
      padding: 1px 7px;
      font-size: 0.72rem;
    }
    .filter-tab.active .count { background: rgba(0,0,0,0.2); }

    /* ---- Status Badges ---- */
    .badge {
      display: inline-block;
      padding: 3px 11px;
      border-radius: 20px;
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
    }
    .badge-pending  { background: rgba(240,160,69,0.15);  color: #f0a045; }
    .badge-paid     { background: rgba(56,201,176,0.15);  color: #38c9b0; }
    .badge-cancelled{ background: rgba(122,154,181,0.12); color: #7a9ab5; }

    /* ---- Payment action link ---- */
    td a[href*="payment"] {
      color: #38c9b0;
      border-color: rgba(56,201,176,0.3);
      background: rgba(56,201,176,0.08);
    }
    td a[href*="payment"]:hover {
      background: #38c9b0;
      color: #0f1923;
      opacity: 1;
    }

    /* ---- Success banner ---- */
    .success-banner {
      max-width: 960px;
      margin: 16px auto 0;
      padding: 11px 20px;
      border-radius: 8px;
      background: rgba(56,201,176,0.12);
      border: 1px solid rgba(56,201,176,0.3);
      color: #38c9b0;
      font-size: 0.88rem;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    /* ---- Empty state ---- */
    .empty-state {
      max-width: 960px;
      margin: 40px auto;
      text-align: center;
      color: var(--text-muted);
      font-size: 0.92rem;
      padding: 0 32px;
    }
    .empty-state .icon { font-size: 2.5rem; margin-bottom: 10px; }

    /* ---- Actions bar ---- */
    .actions-bar {
      max-width: 960px;
      margin: 20px auto 0;
      padding: 0 32px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .btn-primary {
      display: inline-block;
      background: var(--accent);
      color: #0f1923;
      font-weight: 700;
      font-size: 0.83rem;
      padding: 8px 18px;
      border-radius: 6px;
      letter-spacing: 0.03em;
      box-shadow: 0 2px 12px rgba(56,201,176,0.25);
      transition: background 0.2s, transform 0.2s, box-shadow 0.2s;
      text-decoration: none;
    }
    .btn-primary:hover { background: #fff; color: #0f1923; transform: translateY(-1px); opacity: 1; }
  </style>
</head>
<body>
<?php include "nav.php"; ?>

<h2>Bookings</h2>

<?php if ($success === 'created'): ?>
  <div class="success-banner" style="margin-left:32px;margin-right:32px;">
    ✓ &nbsp; Booking created successfully!
  </div>
<?php elseif ($success === 'cancelled'): ?>
  <div class="success-banner" style="margin-left:32px;margin-right:32px;">
    ✓ &nbsp; Booking cancelled.
  </div>
<?php elseif ($success === 'paid'): ?>
  <div class="success-banner" style="margin-left:32px;margin-right:32px;">
    ✓ &nbsp; Payment recorded successfully!
  </div>
<?php endif; ?>

<!-- Actions Bar -->
<div class="actions-bar">
  <span style="color:var(--text-muted);font-size:.9rem;">
    Total: <strong style="color:var(--text)"><?php echo $counts['ALL']; ?></strong> bookings
  </span>
  <a class="btn-primary" href="bookings_create.php">+ Create Booking</a>
</div>

<!-- Filter Tabs -->
<div class="filter-tabs">
  <?php foreach (['ALL','PENDING','PAID','CANCELLED'] as $s):
    $active = ($filter === $s) ? 'active' : '';
  ?>
    <a class="filter-tab <?php echo $active; ?>"
       href="bookings_list.php?status=<?php echo $s; ?>">
      <?php echo ucfirst(strtolower($s)); ?>
      <span class="count"><?php echo $counts[$s]; ?></span>
    </a>
  <?php endforeach; ?>
</div>

<!-- Table -->
<?php $rows = mysqli_num_rows($result); ?>

<?php if ($rows === 0): ?>
  <div class="empty-state">
    <div class="icon">📋</div>
    <p style="padding:0;margin:0;color:var(--text-muted);">
      No bookings found<?php echo ($filter !== 'ALL') ? " with status <strong>$filter</strong>" : ''; ?>.
    </p>
  </div>
<?php else: ?>
  <div style="max-width:960px;margin:0 auto;padding:0 32px;">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Client</th>
          <th>Service</th>
          <th>Date</th>
          <th>Hours</th>
          <th>Rate/hr</th>
          <th>Total</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($b = mysqli_fetch_assoc($result)): ?>
          <tr>
            <td style="color:var(--text-muted);font-size:.82rem;">#<?php echo $b['booking_id']; ?></td>
            <td><strong><?php echo htmlspecialchars($b['client_name']); ?></strong></td>
            <td><?php echo htmlspecialchars($b['service_name']); ?></td>
            <td><?php echo date('M d, Y', strtotime($b['booking_date'])); ?></td>
            <td><?php echo $b['hours']; ?> hr<?php echo $b['hours'] != 1 ? 's' : ''; ?></td>
            <td style="color:var(--text-muted);">₱<?php echo number_format($b['hourly_rate_snapshot'],2); ?></td>
            <td><strong>₱<?php echo number_format($b['total_cost'],2); ?></strong></td>
            <td>
              <?php
                $status = strtolower($b['status']);
                $badge  = match($b['status']) {
                  'PENDING'   => 'badge-pending',
                  'PAID'      => 'badge-paid',
                  'CANCELLED' => 'badge-cancelled',
                  default     => 'badge-pending'
                };
              ?>
              <span class="badge <?php echo $badge; ?>"><?php echo $b['status']; ?></span>
            </td>
            <td>
              <?php if ($b['status'] === 'PENDING'): ?>
                <a href="payment_process.php?booking_id=<?php echo $b['booking_id']; ?>">
                  Pay
                </a>
                &nbsp;
                <a href="bookings_list.php?cancel_id=<?php echo $b['booking_id']; ?>"
                   style="color:var(--danger);border-color:rgba(224,92,107,0.3);background:rgba(224,92,107,0.08);"
                   onclick="return confirm('Cancel this booking?')">
                  Cancel
                </a>
              <?php elseif ($b['status'] === 'PAID'): ?>
                <span style="color:var(--text-muted);font-size:.8rem;">Completed</span>
              <?php else: ?>
                <span style="color:var(--text-muted);font-size:.8rem;">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

</body>
</html>