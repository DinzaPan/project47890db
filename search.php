<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['q']) || empty(trim($_GET['q']))) {
    echo json_encode([]);
    exit;
}

$searchQuery = trim($_GET['q']);
$searchParam = "%$searchQuery%";

$stmt = $pdo->prepare("
    SELECT a.*, u.username, u.profile_pic, 
           AVG(r.rating) as avg_rating, 
           COUNT(r.id) as review_count 
    FROM addons a 
    JOIN usuarios u ON a.user_id = u.id 
    LEFT JOIN reviews r ON a.id = r.addon_id 
    WHERE a.title LIKE :search OR u.username LIKE :search 
    GROUP BY a.id 
    ORDER BY a.created_at DESC 
    LIMIT 10
");

$stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
$stmt->execute();

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($results);