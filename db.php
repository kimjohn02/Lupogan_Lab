<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "kim";
 
$conn = mysqli_connect($host, $user, $pass, $dbname);
 
if (!$conn) {
  die("Database connection failed: " . mysqli_connect_error());
}

function getStats($conn) {
    $stats = [];
    $stats['clients'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM clients"))['c'];
    $stats['services'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM services"))['c'];
    $stats['bookings'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM bookings"))['c'];
    $revRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT IFNULL(SUM(amount_paid),0) AS s FROM payments"));
    $stats['revenue'] = $revRow['s'];
    return $stats;
}
?>