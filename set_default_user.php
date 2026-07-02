<?php
// set_default_user.php - Set default Suresh user for testing (remove later)
session_start();

// Set default session values for testing
if (!isset($_SESSION['username'])) {
    $_SESSION['username'] = 'Suresh';
    $_SESSION['userid'] = 1;
    $_SESSION['Role'] = 'Admin';
}

// Redirect to details page or wherever you want to go
$redirectTo = isset($_GET['redirect']) ? $_GET['redirect'] : 'listBookingsv2.php';
header('Location: ' . $redirectTo);
exit;
?>