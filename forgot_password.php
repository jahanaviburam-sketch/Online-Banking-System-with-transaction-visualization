<?php
require_once 'db.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = $_POST['email'];
    $new_password = $_POST['new_password'];

    $conn = openDatabaseConnection("online_bankin_system");

    // check if email exists
    $stmt = $conn->prepare("SELECT * FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {

        // update password
        $update = $conn->prepare("UPDATE users SET password=? WHERE email=?");
        $update->bind_param("ss", $new_password, $email);
        $update->execute();

        $message = "✅ Password updated successfully!";
    } else {
        $message = "❌ Email not found!";
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
</head>
<body>

<h2>Reset Password</h2>

<form method="POST">
    <input type="email" name="email" placeholder="Enter your email" required><br><br>
    <input type="password" name="new_password" placeholder="Enter new password" required><br><br>

    <button type="submit">Reset Password</button>
</form>

<p><?php echo $message; ?></p>

</body>
</html>