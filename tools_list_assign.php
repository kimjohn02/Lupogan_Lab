<?php
include "db.php";
 
$message = "";
 
// ASSIGN TOOL
if (isset($_POST['assign'])) {
  $booking_id = $_POST['booking_id'];
  $tool_id = $_POST['tool_id'];
  $qty = $_POST['qty_used'];
 
  // Safe check available
$stmt = $conn->prepare("SELECT quantity_available FROM tools WHERE tool_id = ?");
$stmt->bind_param("i", $tool_id);
$stmt->execute();
$toolRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($qty > $toolRow['quantity_available']) {
  $message = "Not enough available tools!";
} else {
  // Safe insert
  $stmt = $conn->prepare("INSERT INTO booking_tools (booking_id, tool_id, qty_used) VALUES (?, ?, ?)");
  $stmt->bind_param("iii", $booking_id, $tool_id, $qty);
  $stmt->execute();
  $stmt->close();

  // Safe update inventory
  $stmt = $conn->prepare("UPDATE tools SET quantity_available = quantity_available - ? WHERE tool_id = ?");
  $stmt->bind_param("ii", $qty, $tool_id);
  $stmt->execute();
  $stmt->close();

  $message = "Tool assigned successfully!";
}

}
 
$tools = mysqli_query($conn, "SELECT * FROM tools ORDER BY tool_name ASC");
$bookings = mysqli_query($conn, "SELECT booking_id FROM bookings ORDER BY booking_id DESC");
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Tools</title>
<link rel="stylesheet" href="color.css">
</head>
<body>
<?php include "nav.php"; ?>
 
<h2>Tools / Inventory</h2>
<p style="color:green;"><?php echo $message; ?></p>
 
<h3>Available Tools</h3>
<table border="1" cellpadding="8">
  <tr><th>Name</th><th>Total</th><th>Available</th></tr>
  <?php while($t = mysqli_fetch_assoc($tools)) { ?>
    <tr>
      <td><?php echo $t['tool_name']; ?></td>
      <td><?php echo $t['quantity_total']; ?></td>
      <td><?php echo $t['quantity_available']; ?></td>
    </tr>
  <?php } ?>
</table>
 
<hr>
 
<h3>Assign Tool to Booking</h3>
<form method="post">
  <label>Booking ID</label><br>
  <select name="booking_id">
    <?php while($b = mysqli_fetch_assoc($bookings)) { ?>
      <option value="<?php echo $b['booking_id']; ?>">#<?php echo $b['booking_id']; ?></option>
    <?php } ?>
  </select><br><br>
 
  <label>Tool</label><br>
  <select name="tool_id">
    <?php
      $tools2 = mysqli_query($conn, "SELECT * FROM tools ORDER BY tool_name ASC");
      while($t2 = mysqli_fetch_assoc($tools2)) {
    ?>
      <option value="<?php echo $t2['tool_id']; ?>">
        <?php echo $t2['tool_name']; ?> (Avail: <?php echo $t2['quantity_available']; ?>)
      </option>
    <?php } ?>
  </select><br><br>
 
  <label>Qty Used</label><br>
  <input type="number" name="qty_used" min="1" value="1"><br><br>
 
  <button type="submit" name="assign">Assign</button>
</form>
 
</body>
</html>