<?php
session_start();
require_once 'db.php';

// ✅ SESSION CHECK
if (!isset($_SESSION['email'])) {
    header("Location: index.html");
    exit();
}

// ✅ CSRF PROTECTION
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Invalid request");
}

$conn = openDatabaseConnection("online_bankin_system");

$email = $_SESSION['email'];
$amount = $_POST['amount'];
$type = $_POST['type'];
$category = $_POST['category'];

// ✅ INPUT VALIDATION
if (!is_numeric($amount) || $amount <= 0) {
    header("Location: dashboard.php?status=invalid_amount");
    exit();
}

// Get current balance
$stmt = $conn->prepare("SELECT balance FROM users WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

$current_balance = $row['balance'];

// ✅ LOGIC
if ($type == "deposit") {
    $new_balance = $current_balance + $amount;
    $msg = "deposit_success";
} 
elseif ($type == "withdraw") {

    if ($amount > $current_balance) {
        header("Location: dashboard.php?status=error");
        exit();
    }

    $new_balance = $current_balance - $amount;
    $msg = "withdraw_success";
} 
else {
    die("Invalid transaction type");
}

// ✅ UPDATE BALANCE
$update = $conn->prepare("UPDATE users SET balance=? WHERE email=?");
$update->bind_param("ds", $new_balance, $email);
$update->execute();

// ✅ INSERT TRANSACTION
$insert = $conn->prepare("INSERT INTO transactions (email, type, amount, category) VALUES (?, ?, ?, ?)");
$insert->bind_param("ssds", $email, $type, $amount, $category);
$insert->execute();

// ✅ CLOSE CONNECTION
$conn->close();

// ✅ REDIRECT
header("Location: dashboard.php?status=$msg");
exit();
?>