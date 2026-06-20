<?php
require_once 'db.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = $_POST['fullname'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = $_POST['password'];

    // ✅ VALIDATION
    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        $message = "Error: All fields are required!";
    } else {

        // 🔐 HASH PASSWORD
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // ✅ DB CONNECTION
        $conn = openDatabaseConnection("online_bankin_system");

        // ✅ CORRECT COLUMN NAME (full_name)
        $sql = "INSERT INTO users (full_name, email, phone, password) VALUES (?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $name, $email, $phone, $hashed_password);

        if ($stmt->execute()) {
            $message = "Registration successful!";
        } else {
            $message = "Error: Could not register user.";
        }

        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Signup</title>

<style>
body {
    margin: 0;
    font-family: Arial;
    background: linear-gradient(to right, #4e73df, #1cc88a);
    height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
}
.container {
    background: white;
    padding: 30px;
    width: 350px;
    border-radius: 12px;
    text-align: center;
}
input {
    width: 100%;
    padding: 10px;
    margin: 10px 0;
}
button {
    width: 100%;
    padding: 10px;
    background: #4e73df;
    color: white;
    border: none;
}
.message {
    color: green;
}
.error {
    color: red;
}
</style>
</head>

<body>

<div class="container">
    <h2>Create Account</h2>

    <form method="POST">
        <input type="text" name="fullname" placeholder="Full Name" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="text" name="phone" placeholder="Phone Number" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Register</button>
    </form>

    <?php if (!empty($message)): ?>
        <p class="<?php echo strpos($message, 'Error') !== false ? 'error' : 'message'; ?>">
            <?php echo $message; ?>
        </p>
    <?php endif; ?>

</div>

</body>
</html>