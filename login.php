<?php
require_once 'db.php';
session_start();

$conn = new mysqli("localhost:3", "root", "", "online_banking_system");

$email = $_POST['email'];
$password = $_POST['password'];

$sql = "SELECT * FROM users WHERE email='$email' AND password='$password'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $_SESSION['email'] = $email;
    header("Location: dashboard.php");
} else {
    echo "Invalid email or password";
}
?>