<?php
function openDatabaseConnection($databaseName = 'online_bankin_system') {
    $connection = new mysqli(
    "localhost",
    "your_username",
    "your_password",
    "your_database_name"
);
    if ($connection->connect_error) {
        die("Connection failed: " . $connection->connect_error);
    }

    return $connection;
}

$conn = openDatabaseConnection();
?>
