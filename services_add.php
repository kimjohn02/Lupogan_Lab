<?php
include "db.php";

$message = "";

// Repopulate on validation error
$form = [
    'service_name' => '',
    'description'  => '',
    'hourly_rate'  => '',
    'is_active'    => '1',
];

if (isset($_POST['save'])) {
    $form['service_name'] = trim($_POST['service_name']);
    $form['description']  = trim($_POST['description']);
    $form['hourly_rate']  = trim($_POST['hourly_rate']);
    $form['is_active']    = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

    if ($form['service_name'] === '' || $form['hourly_rate'] === '') {
        $message = "Service name and hourly rate are required!";
    } elseif (!is_numeric($form['hourly_rate']) || (float)$form['hourly_rate'] <= 0) {
        $message = "Hourly rate must be a number greater than 0.";
    } else {
        $service_name = mysqli_real_escape_string($conn, $form['service_name']);
        $description  = mysqli_real_escape_string($conn, $form['description']);
        $hourly_rate  = (float)$form['hourly_rate'];
        $is_active    = (int)$form['is_active'];

        $sql = "INSERT INTO services (service_name, description, hourly_rate, is_active)
                VALUES ('$service_name', '$description', $hourly_rate, $is_active)";

        if (!mysqli_query($conn, $sql)) {
            $message = "Database error: " . mysqli_error($conn);
        } else {
            header("Location: services_list.php?success=created");
            exit;
        }
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Add Service</title>
  <link rel="stylesheet" href="color.css">
  <style>
    .page-wrap { max-width: 960px; margin: 0 auto; padding: 0 32px 60px; }

    .back-link { display: block; color: var(--text-muted); font-size: .85rem; margin-top: 20px; }
    .back-link:hover { color: var(--accent); opacity: 1; }

    form { max-width: 560px; margin-top: 24px; }
    .field { margin-bottom: 20px; }

    /* ₱/hr prefix on rate input */
    .input-prefix { display: flex; align-items: stretch; }
    .input-prefix .prefix {
      display: flex; align-items: center; padding: 0 14px;
      background: var(--surface2); border: 1px solid var(--border);
      border-right: none; border-radius: 7px 0 0 7px;
      color: var(--text-muted); font-size: .92rem; font-weight: 600; white-space: nowrap;
    }
    .input-prefix input { border-radius: 0 7px 7px 0 !important; flex: 1; }

    /* Active / Inactive toggle */
    .toggle-group { display: flex; gap: 10px; }
    .toggle-opt {
      flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px;
      padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px;
      background: var(--bg); color: var(--text-muted); font-size: .85rem; font-weight: 600;
      cursor: pointer; transition: all .2s; user-select: none;
    }
    .toggle-opt:hover { border-color: var(--accent-dim); color: var(--text); }
    .toggle-opt.selected-yes { border-color: var(--accent); background: rgba(56,201,176,.1); color: var(--accent); }
    .toggle-opt.selected-no  { border-color: rgba(224,92,107,.4); background: rgba(224,92,107,.08); color: var(--danger); }

    /* Character counter */
    .char-hint { font-size: .75rem; color: var(--text-muted); margin-top: 5px; text-align: right; }

    /* Submit row */
    .submit-row { display: flex; gap: 12px; align-items: center; margin-top: 8px; }
    .btn-cancel {
      display: inline-block; padding: 11px 22px; border-radius: 8px;
      font-size: .88rem; font-weight: 600; color: var(--text-muted);
      border: 1px solid var(--border); background: transparent;
      cursor: pointer; transition: all .2s; text-decoration: none;
    }
    .btn-cancel:hover { color: var(--text); border-color: var(--text-muted); opacity: 1; }
  </style>
</head>
<body>
<?php include "nav.php"; ?>

<div class="page-wrap">
  <h2>Add Service</h2>
  <a class="back-link" href="services_list.php">← Back to Services</a>

  <?php if ($message): ?>
    <p style="color:red; margin-top:16px;"><?php echo htmlspecialchars($message); ?></p>
  <?php endif; ?>

  <form method="post">
    <input type="hidden" name="is_active" id="is_active_input"
           value="<?php echo (int)$form['is_active']; ?>">

    <!-- Service Name -->
    <div class="field">
      <label>Service Name <span style="color:var(--danger);">*</span></label>
      <input type="text" name="service_name"
             placeholder="e.g. Deep Cleaning"
             value="<?php echo htmlspecialchars($form['service_name']); ?>"
             maxlength="100" oninput="updateCount(this,'nameCount')" required>
      <div class="char-hint">
        <span id="nameCount"><?php echo strlen($form['service_name']); ?></span>/100
      </div>
    </div>

    <!-- Description -->
    <div class="field">
      <label>Description</label>
      <textarea name="description" rows="4"
                placeholder="Describe what this service includes..."
                maxlength="500"
                oninput="updateCount(this,'descCount')"><?php echo htmlspecialchars($form['description']); ?></textarea>
      <div class="char-hint">
        <span id="descCount"><?php echo strlen($form['description']); ?></span>/500
      </div>
    </div>

    <!-- Hourly Rate -->
    <div class="field">
      <label>Hourly Rate <span style="color:var(--danger);">*</span></label>
      <div class="input-prefix">
        <span class="prefix">₱ / hr</span>
        <input type="number" name="hourly_rate" step="0.01" min="0.01"
               placeholder="0.00"
               value="<?php echo htmlspecialchars($form['hourly_rate']); ?>" required>
      </div>
    </div>

    <!-- Status Toggle -->
    <div class="field">
      <label>Status</label>
      <div class="toggle-group">
        <div class="toggle-opt <?php echo $form['is_active'] == '1' ? 'selected-yes' : ''; ?>"
             onclick="setActive('1', this)">
          ✓ &nbsp;Active
        </div>
        <div class="toggle-opt <?php echo $form['is_active'] == '0' ? 'selected-no' : ''; ?>"
             onclick="setActive('0', this)">
          ✕ &nbsp;Inactive
        </div>
      </div>
    </div>

    <!-- Buttons -->
    <div class="submit-row">
      <button type="submit" name="save">Save Service</button>
      <a class="btn-cancel" href="services_list.php">Cancel</a>
    </div>
  </form>
</div>

<script>
function updateCount(el, spanId) {
  document.getElementById(spanId).textContent = el.value.length;
}

function setActive(val, el) {
  document.getElementById('is_active_input').value = val;
  document.querySelectorAll('.toggle-opt').forEach(o => o.classList.remove('selected-yes','selected-no'));
  el.classList.add(val === '1' ? 'selected-yes' : 'selected-no');
}
</script>
</body>
</html>