<?php
include "db.php";

$stats = getStats($conn);
$clients = $stats['clients'];
$services = $stats['services'];
$bookings = $stats['bookings'];
$revenue = $stats['revenue'];
?>

<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Dashboard</title>
  <link rel="stylesheet" href="color.css">
</head>
<body>
<?php include "nav.php"; ?>
 
<h2>Dashboard</h2>
 
<ul>
  <li>Total Clients: <b><?php echo $clients; ?></b></li>
  <li>Total Services: <b><?php echo $services; ?></b></li>
  <li>Total Bookings: <b><?php echo $bookings; ?></b></li>
  <li>Total Revenue: <b>₱<?php echo number_format($revenue,2); ?></b></li>
</ul>
 
<p>
  Quick links:
  <a href="clients_add.php">Add Client</a> |
  <a href="/assessment_beginner/pages/bookings_create.php">Create Booking</a>
</p>
 
</body>
</html>

