<?php
$host = 'localhost';
$user = 'root';
$pass = 'aTyc2708';
$db   = 'artsasha_db';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) { die(json_encode(['error' => 'Помилка підключення'])); }
$conn->set_charset("utf8mb4");

$action = isset($_GET['action']) ? $_GET['action'] : '';

function readSmtpResponse($socket) {
    $response = '';
    while ($line = fgets($socket, 515)) {
        $response .= $line;
        if (substr($line, 3, 1) === ' ') {
            break;
        }
    }
    return $response;
}

function sendSmtpEmail($to, $subject, $message) {
    $smtpHost = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
    $smtpPort = (int)(getenv('SMTP_PORT') ?: 587);
    $smtpUsername = getenv('SMTP_USERNAME') ?: 'your_email@gmail.com';
    $smtpPassword = getenv('SMTP_PASSWORD') ?: 'your_app_password';
    $smtpFromEmail = getenv('SMTP_FROM_EMAIL') ?: $smtpUsername;
    $smtpFromName = getenv('SMTP_FROM_NAME') ?: 'ArtSasha';
    $smtpEncryption = strtolower(getenv('SMTP_ENCRYPTION') ?: 'tls');

    if ($smtpUsername === 'your_email@gmail.com' || $smtpPassword === 'your_app_password') {
        return false;
    }

    $socket = fsockopen($smtpHost, $smtpPort, $errno, $errstr, 15);
    if (!$socket) {
        return false;
    }

    $response = readSmtpResponse($socket);
    if (strpos($response, '220') !== 0) {
        fclose($socket);
        return false;
    }

    fputs($socket, "EHLO localhost\r\n");
    readSmtpResponse($socket);

    if ($smtpEncryption === 'tls') {
        fputs($socket, "STARTTLS\r\n");
        $tlsResponse = readSmtpResponse($socket);
        if (strpos($tlsResponse, '220') !== 0) {
            fclose($socket);
            return false;
        }
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        fputs($socket, "EHLO localhost\r\n");
        readSmtpResponse($socket);
    }

    fputs($socket, "AUTH LOGIN\r\n");
    if (strpos(readSmtpResponse($socket), '334') !== 0) {
        fclose($socket);
        return false;
    }

    fputs($socket, base64_encode($smtpUsername) . "\r\n");
    if (strpos(readSmtpResponse($socket), '334') !== 0) {
        fclose($socket);
        return false;
    }

    fputs($socket, base64_encode($smtpPassword) . "\r\n");
    if (strpos(readSmtpResponse($socket), '235') !== 0) {
        fclose($socket);
        return false;
    }

    $fromEncoded = $smtpFromName !== '' ? '=?UTF-8?B?' . base64_encode($smtpFromName) . '?=' : $smtpFromEmail;
    fputs($socket, "MAIL FROM:<{$smtpFromEmail}>\r\n");
    if (strpos(readSmtpResponse($socket), '250') !== 0) {
        fclose($socket);
        return false;
    }

    fputs($socket, "RCPT TO:<{$to}>\r\n");
    if (strpos(readSmtpResponse($socket), '250') !== 0 && strpos(readSmtpResponse($socket), '251') !== 0) {
        fclose($socket);
        return false;
    }

    fputs($socket, "DATA\r\n");
    if (strpos(readSmtpResponse($socket), '354') !== 0) {
        fclose($socket);
        return false;
    }

    $boundary = "-----=" . md5(uniqid((string) mt_rand(), true));
    $emailBody = "Content-Type: text/plain; charset=UTF-8\r\n\r\n" . $message . "\r\n.";
    $headers = "From: {$fromEncoded} <{$smtpFromEmail}>\r\n";
    $headers .= "To: {$to}\r\n";
    $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: 7bit\r\n";
    $headers .= "Date: " . date('D, d M Y H:i:s O') . "\r\n";
    $headers .= "X-Mailer: ArtSasha\r\n";

    fputs($socket, $headers . "\r\n" . $emailBody . "\r\n");
    fputs($socket, "\r\n.\r\n");
    $result = readSmtpResponse($socket);
    fputs($socket, "QUIT\r\n");
    readSmtpResponse($socket);
    fclose($socket);

    return strpos($result, '250') === 0 || strpos($result, '251') === 0 || strpos($result, '354') === 0;
}

function sendCommentNotification($conn, $drawing_id, $name, $text) {
    $to = 'galasirinska@gmail.com';
    $subject = 'Новий коментар на ArtSasha';

    $drawing_title = '';
    $title_stmt = $conn->prepare("SELECT title FROM drawings WHERE id = ?");
    if ($title_stmt) {
        $title_stmt->bind_param('i', $drawing_id);
        if ($title_stmt->execute()) {
            $title_result = $title_stmt->get_result();
            if ($title_result && $title_result->num_rows > 0) {
                $title_row = $title_result->fetch_assoc();
                $drawing_title = $title_row['title'] ?? '';
            }
        }
        $title_stmt->close();
    }

    $message = "Новий коментар на ArtSasha\n\n";
    $message .= "Робота: " . ($drawing_title !== '' ? $drawing_title : 'ID ' . $drawing_id) . "\n";
    $message .= "Ім'я: " . $name . "\n";
    $message .= "Текст: " . $text . "\n";
    $message .= "Час: " . date('d.m.Y H:i:s') . "\n";

    sendSmtpEmail($to, $subject, $message);
}

// 1. ОТРИМАННЯ КОМЕНТАРІВ
if ($action === 'get' && isset($_GET['drawing_id'])) {
    $drawing_id = (int)$_GET['drawing_id'];
    $result = $conn->query("SELECT id, name, text, DATE_FORMAT(created_at, '%d.%m.%Y %H:%i') as date FROM comments WHERE drawing_id = $drawing_id ORDER BY id DESC");
    
    $comments = [];
    while ($row = $result->fetch_assoc()) {
        $comments[] = $row;
    }
    echo json_encode($comments);
    exit;
}

// 2. ДОДАВАННЯ НОВОГО КОМЕНТАРЯ
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $drawing_id = (int)$_POST['drawing_id'];
    $name = trim($_POST['name']);
    $text = trim($_POST['text']);

    if (empty($name) || empty($text)) {
        echo json_encode(['success' => false, 'error' => 'Заповніть усі поля']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO comments (drawing_id, name, text) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $drawing_id, $name, $text);
    
    if ($stmt->execute()) {
        sendCommentNotification($conn, $drawing_id, $name, $text);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Помилка збереження']);
    }
    $stmt->close();
    exit;
}
// 3. ВИДАЛЕННЯ КОМЕНТАРЯ (Доступно тільки для Адміна)
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Перевіряємо сесію адміна (переконайтеся, що session_start() є у вашому файлі)
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    
    // Перевірка, чи користувач справді є адміном (використовуйте вашу точну назву змінної сесії)
    $is_admin = isset($_SESSION['admin']) || isset($_SESSION['is_admin']); 

    if (!$is_admin) {
        echo json_encode(['success' => false, 'error' => 'Немає прав для видалення']);
        exit;
    }

    $comment_id = (int)$_POST['comment_id'];

    $stmt = $conn->prepare("DELETE FROM comments WHERE id = ?");
    $stmt->bind_param("i", $comment_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Помилка видалення']);
    }
    $stmt->close();
    exit;
}

?>
