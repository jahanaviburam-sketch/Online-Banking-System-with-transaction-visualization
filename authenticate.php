<?php
require_once 'db.php';
session_start();

$conn = openDatabaseConnection("online_bankin_system");

$email = $_POST['email'];
$password = $_POST['password'];

$sql = "SELECT * FROM users WHERE email=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {

    if (password_verify($password, $row['password'])) {

        // ✅ FIXED SESSION VARIABLES
        $_SESSION['email'] = $row['email'];
        $_SESSION['user_id'] = $row['user_id'];

        header("Location: dashboard.php");
        exit();

    } else {
        echo "Invalid Password";
    }

} else {
    echo "User not found";
}
?>