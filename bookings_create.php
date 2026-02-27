<?php
include "db.php";

$clients  = mysqli_query($conn, "SELECT * FROM clients ORDER BY full_name ASC");
$services = mysqli_query($conn, "SELECT * FROM services WHERE is_active=1 ORDER BY service_name ASC");

// Build service rates array for JS
$service_rates = [];
$sr = mysqli_query($conn, "SELECT service_id, hourly_rate FROM services WHERE is_active=1");
while ($row = mysqli_fetch_assoc($sr)) {
    $service_rates[$row['service_id']] = $row['hourly_rate'];
}

$error = "";

if (isset($_POST['create'])) {
    $client_name_input = trim($_POST['client_name_input'] ?? '');
    $client_id         = (int)$_POST['client_id'];
    $service_id        = (int)$_POST['service_id'];
    $booking_date      = $_POST['booking_date'];
    $hours             = (int)$_POST['hours'];

    // Look up client by name if no client_id resolved
    if (!$client_id && $client_name_input !== '') {
        $stmt = $conn->prepare("SELECT client_id FROM clients WHERE full_name = ? LIMIT 1");
        $stmt->bind_param("s", $client_name_input);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $client_id = $row['client_id'] ?? 0;
    }

    if (!$client_id || !$service_id || !$booking_date || $hours < 1) {
        if (!$client_id) $error = "Client not found. Please type an existing client name.";
        else $error = "All fields are required and hours must be at least 1.";
    } else {
        $stmt = $conn->prepare("SELECT hourly_rate FROM services WHERE service_id = ?");
        $stmt->bind_param("i", $service_id);
        $stmt->execute();
        $s    = $stmt->get_result()->fetch_assoc();
        $rate = $s['hourly_rate'] ?? 0;
        $stmt->close();

        $total = $rate * $hours;

        $stmt = $conn->prepare("INSERT INTO bookings (client_id, service_id, booking_date, hours, hourly_rate_snapshot, total_cost, status) VALUES (?, ?, ?, ?, ?, ?, 'PENDING')");
        $stmt->bind_param("iisidd", $client_id, $service_id, $booking_date, $hours, $rate, $total);
        $stmt->execute();
        $stmt->close();

        header("Location: bookings_list.php?success=created");
        exit;
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Create Booking</title>
  <link rel="stylesheet" href="color.css">
  <style>
    /* ---- Cost Preview Card ---- */
    .cost-preview {
      background: var(--surface2);
      border: 1px solid var(--accent-dim);
      border-radius: var(--radius);
      padding: 18px 20px;
      margin-top: 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
    }
    .cost-preview .label {
      font-size: 0.78rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.07em;
      color: var(--text-muted);
      margin-bottom: 0;
    }
    .cost-preview .amount {
      font-size: 1.6rem;
      font-weight: 700;
      color: var(--accent);
      letter-spacing: -0.02em;
    }
    .cost-preview .breakdown {
      font-size: 0.82rem;
      color: var(--text-muted);
      margin-top: 2px;
    }
    .field-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }
    .field { margin-bottom: 20px; }
    input[type="date"] {
      width: 100%;
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: 7px;
      padding: 10px 14px;
      color: var(--text);
      font-family: 'DM Sans', sans-serif;
      font-size: 0.92rem;
      outline: none;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
      appearance: none;
      -webkit-appearance: none;
      color-scheme: dark;
    }
    input[type="date"]:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px var(--accent-glow);
    }
    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      color: var(--text-muted);
      font-size: 0.85rem;
      max-width: 960px;
      margin: 20px auto 0;
      padding: 0 32px;
      display: block;
    }
    .back-link:hover { color: var(--accent); opacity: 1; }
    form { max-width: 560px; }
  </style>
</head>
<body>
<?php include "nav.php"; ?>

<h2>Create Booking</h2>

<a class="back-link" href="bookings_list.php">← Back to Bookings</a>

<?php if ($error): ?>
  <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<form method="post">

  <div class="field" style="position:relative;">
    <label>Client Name</label>
    <input
      type="text"
      id="client_name_input"
      name="client_name_input"
      placeholder="Type client name..."
      autocomplete="off"
      value="<?php echo htmlspecialchars($_POST['client_name_input'] ?? ''); ?>"
      oninput="filterClients(this.value)"
      onfocus="showDropdown()"
      onblur="hideDropdown()"
      required
    >
    <input type="hidden" name="client_id" id="client_id" value="<?php echo (int)($_POST['client_id'] ?? 0); ?>">

    <!-- Custom dropdown -->
    <div id="clientDropdown" style="
      display:none;
      position:absolute;
      top:100%;
      left:0; right:0;
      background:var(--surface2);
      border:1px solid var(--accent-dim);
      border-top:none;
      border-radius:0 0 8px 8px;
      max-height:200px;
      overflow-y:auto;
      z-index:50;
      box-shadow:0 8px 24px rgba(0,0,0,0.4);
    ">
      <?php
        $clients = mysqli_query($conn, "SELECT client_id, full_name FROM clients ORDER BY full_name ASC");
        while ($c = mysqli_fetch_assoc($clients)):
      ?>
        <div
          class="client-option"
          data-id="<?php echo $c['client_id']; ?>"
          data-name="<?php echo htmlspecialchars($c['full_name']); ?>"
          onmousedown="selectClient(<?php echo $c['client_id']; ?>, '<?php echo addslashes(htmlspecialchars($c['full_name'])); ?>')"
          style="padding:10px 16px;cursor:pointer;font-size:0.9rem;color:var(--text);transition:background 0.15s;"
          onmouseover="this.style.background='rgba(56,201,176,0.1)'"
          onmouseout="this.style.background=''"
        >
          <?php echo htmlspecialchars($c['full_name']); ?>
        </div>
      <?php endwhile; ?>
    </div>
  </div>

  <div class="field">
    <label>Service</label>
    <select name="service_id" id="service_id" required onchange="updatePreview()">
      <option value="">— Select a service —</option>
      <?php
        $services = mysqli_query($conn, "SELECT * FROM services WHERE is_active=1 ORDER BY service_name ASC");
        while ($s = mysqli_fetch_assoc($services)):
      ?>
        <option value="<?php echo $s['service_id']; ?>"
                data-rate="<?php echo $s['hourly_rate']; ?>"
                data-name="<?php echo htmlspecialchars($s['service_name']); ?>"
          <?php echo (isset($_POST['service_id']) && $_POST['service_id']==$s['service_id']) ? 'selected' : ''; ?>>
          <?php echo htmlspecialchars($s['service_name']); ?> — ₱<?php echo number_format($s['hourly_rate'],2); ?>/hr
        </option>
      <?php endwhile; ?>
    </select>
  </div>

  <div class="field-row">
    <div class="field">
      <label>Booking Date</label>
      <input type="date" name="booking_date" id="booking_date" required
             value="<?php echo $_POST['booking_date'] ?? date('Y-m-d'); ?>">
    </div>
    <div class="field">
      <label>Hours</label>
      <input type="number" name="hours" id="hours" min="1" max="24" value="<?php echo $_POST['hours'] ?? 1; ?>"
             required oninput="updatePreview()">
    </div>
  </div>

  <!-- Live Cost Preview -->
  <div class="cost-preview" id="costPreview">
    <div>
      <div class="label">Estimated Total</div>
      <div class="amount" id="totalAmount">₱0.00</div>
      <div class="breakdown" id="costBreakdown">Select a service and enter hours</div>
    </div>
    <div style="text-align:right;">
      <div class="label">Status</div>
      <span style="display:inline-block;margin-top:4px;padding:4px 14px;border-radius:20px;background:rgba(240,160,69,0.15);color:#f0a045;font-size:0.78rem;font-weight:700;letter-spacing:0.05em;">PENDING</span>
    </div>
  </div>

  <button type="submit" name="create" style="margin-top:24px;">
    ✓ &nbsp; Create Booking
  </button>

</form>

<script>
const rates = <?php echo json_encode($service_rates); ?>;

function formatPHP(num) {
  return '₱' + num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function updatePreview() {
  const sel   = document.getElementById('service_id');
  const hours = parseFloat(document.getElementById('hours').value) || 0;
  const opt   = sel.options[sel.selectedIndex];

  if (!sel.value || !opt) {
    document.getElementById('totalAmount').textContent    = '₱0.00';
    document.getElementById('costBreakdown').textContent  = 'Select a service and enter hours';
    return;
  }

  const rate    = parseFloat(opt.dataset.rate) || 0;
  const name    = opt.dataset.name;
  const total   = rate * hours;

  document.getElementById('totalAmount').textContent   = formatPHP(total);
  document.getElementById('costBreakdown').textContent = `${name} · ${formatPHP(rate)}/hr × ${hours} hr${hours !== 1 ? 's' : ''}`;
}

// Run on load in case of POST error repopulation
document.addEventListener('DOMContentLoaded', updatePreview);

// ---- Client Autocomplete ----
function showDropdown() {
  filterClients(document.getElementById('client_name_input').value);
  document.getElementById('clientDropdown').style.display = 'block';
}

function hideDropdown() {
  setTimeout(() => {
    document.getElementById('clientDropdown').style.display = 'none';
  }, 150);
}

function filterClients(query) {
  const q       = query.toLowerCase().trim();
  const options = document.querySelectorAll('.client-option');
  const dd      = document.getElementById('clientDropdown');
  let   visible = 0;

  options.forEach(opt => {
    const name = opt.dataset.name.toLowerCase();
    if (!q || name.includes(q)) {
      opt.style.display = 'block';
      visible++;
    } else {
      opt.style.display = 'none';
    }
  });

  dd.style.display = visible > 0 ? 'block' : 'none';

  // If input cleared, also clear hidden id
  if (!query) document.getElementById('client_id').value = '';
}

function selectClient(id, name) {
  document.getElementById('client_name_input').value = name;
  document.getElementById('client_id').value         = id;
  document.getElementById('clientDropdown').style.display = 'none';
}
</script>

</body>
</html>