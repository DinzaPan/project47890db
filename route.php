<?php
require_once 'config.php';

header('Content-Type: application/json');

$searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';
$searchType = isset($_GET['type']) ? trim($_GET['type']) : 'all';
$limit = isset($_GET['limit']) ? min(10, max(1, intval($_GET['limit']))) : 5;

$results = [];

try {
    if (empty($searchQuery)) {
        echo json_encode(['success' => false, 'message' => 'Query parameter "q" is required']);
        exit;
    }

    if ($searchType === 'all' || $searchType === 'users') {
        // Buscar usuarios
        $stmt = $pdo->prepare("SELECT id, username, profile_pic, is_verified FROM usuarios 
                              WHERE username LIKE ? OR email LIKE ?
                              LIMIT ?");
        $stmt->execute(["%$searchQuery%", "%$searchQuery%", $limit]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($users as $user) {
            $results[] = [
                'type' => 'user',
                'id' => $user['id'],
                'username' => $user['username'],
                'profile_pic' => 'uploads/' . ($user['profile_pic'] ?? 'default.png'),
                'is_verified' => (bool)$user['is_verified'],
                'url' => 'profile.php?id=' . $user['id']
            ];
        }
    }

    if ($searchType === 'all' || $searchType === 'addons') {
        // Buscar addons
        $stmt = $pdo->prepare("SELECT a.id, a.title, a.description, a.cover_image, u.username 
                              FROM addons a 
                              JOIN usuarios u ON a.user_id = u.id 
                              WHERE a.title LIKE ? OR a.description LIKE ? OR u.username LIKE ?
                              LIMIT ?");
        $stmt->execute(["%$searchQuery%", "%$searchQuery%", "%$searchQuery%", $limit]);
        $addons = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($addons as $addon) {
            $results[] = [
                'type' => 'addon',
                'id' => $addon['id'],
                'title' => $addon['title'],
                'description' => $addon['description'],
                'cover_image' => 'uploads/' . ($addon['cover_image'] ?? 'default-cover.png'),
                'author' => $addon['username'],
                'url' => 'index.php?view=addon&id=' . $addon['id']
            ];
        }
    }

    if ($searchType === 'all' || $searchType === 'reviews') {
        // Buscar reseñas
        $stmt = $pdo->prepare("SELECT r.id, r.comment, r.rating, u.username, a.title as addon_title 
                              FROM reviews r 
                              JOIN usuarios u ON r.user_id = u.id 
                              JOIN addons a ON r.addon_id = a.id 
                              WHERE r.comment LIKE ? OR u.username LIKE ? OR a.title LIKE ?
                              LIMIT ?");
        $stmt->execute(["%$searchQuery%", "%$searchQuery%", "%$searchQuery%", $limit]);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($reviews as $review) {
            $results[] = [
                'type' => 'review',
                'id' => $review['id'],
                'comment' => $review['comment'],
                'rating' => $review['rating'],
                'author' => $review['username'],
                'addon_title' => $review['addon_title'],
                'url' => 'index.php?view=addon&id=' . $review['id'] . '#review-' . $review['id']
            ];
        }
    }

    echo json_encode(['success' => true, 'results' => $results]);
} catch (PDOException $e) {
    error_log("Search error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}