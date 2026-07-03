<?php
// Підключення до бази даних
$host = 'localhost';
$user = 'root';
$pass = 'aTyc2708';
$db   = 'artsasha_db';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) { 
    die(json_encode(['error' => 'Помилка підключення'])); 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    
    // Перевіряємо, яку дію хоче виконати користувач (лайк чи дизлайк)
    $action = isset($_POST['action']) ? $_POST['action'] : 'like';
    
    if ($action === 'unlike') {
        // Зменшуємо лічильник лайків на 1, але не нижче 0
        $stmt = $conn->prepare("UPDATE drawings SET likes = GREATEST(0, likes - 1) WHERE id = ?");
    } else {
        // Звичайне збільшення лічильника на 1
        $stmt = $conn->prepare("UPDATE drawings SET likes = likes + 1 WHERE id = ?");
    }
    
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $res = $conn->query("SELECT likes FROM drawings WHERE id = $id");
        $row = $res->fetch_assoc();
        echo json_encode(['success' => true, 'likes' => (int)$row['likes']]);
    } else {
        echo json_encode(['success' => false]);
    }
    $stmt->close();
    exit;
}
echo json_encode(['success' => false]);
?>
