<?php
function connectDB() {
    $host = 'localhost';
    $dbname = 'BaimeElRahma';
    $username = 'root'; // Replace with your DB username
    $password = ''; // Replace with your DB password
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("❌ Erreur de connexion à la base de données : " . $e->getMessage());
    }
}
?>