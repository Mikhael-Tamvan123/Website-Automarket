<?php
// includes/favorite_action.php
require_once __DIR__ . '/../config.php';
require_once 'favorite_manager.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'pembeli') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $car_id = intval($_POST['car_id']);
    $action = $_POST['action'];
    $user_id = $_SESSION['user_id'];
    
    if ($action == 'toggle') {
        if ($favoriteManager->isFavorite($user_id, $car_id)) {
            // Remove from favorites
            $success = $favoriteManager->removeFavorite($user_id, $car_id);
            echo json_encode(['success' => $success, 'is_favorite' => false]);
        } else {
            // Add to favorites
            $success = $favoriteManager->addFavorite($user_id, $car_id);
            echo json_encode(['success' => $success, 'is_favorite' => true]);
        }
    }
}
?>