<?php
$servername = "localhost";
$username = "root";
$password = "root";
$database = "biblioteca";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Conexión fallida: " . htmlspecialchars($conn->connect_error));
}

$conn->set_charset("utf8");
?>