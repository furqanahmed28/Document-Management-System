<?php
$pdo = new PDO("sqlite:dmsdb.db"); 
$query = "SELECT ID as user_id, name, email FROM Users WHERE app = 1";
$stmt = $pdo->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($users);
?>
