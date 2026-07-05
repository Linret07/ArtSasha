<?php
session_start();

$host = 'localhost';
$user = 'root';
$pass = 'aTyc2708';
$db   = 'artsasha_db';

$conn = new mysqli($host, $user, $pass, $db);
$conn->set_charset('utf8mb4');

$message = '';
$status  = 'info';

if (isset($_GET['email']) && $_GET['email'] !== '') {
    $email = trim($_GET['email']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Невірна електронна адреса.';
        $status  = 'error';
    } else {
        $email_safe = $conn->real_escape_string($email);
        $result = $conn->query("DELETE FROM subscribers WHERE email = '$email_safe'");
        if ($conn->affected_rows > 0) {
            $message = 'Ви успішно відписалися від оновлень. Шкода бачити вас йдучими! 😢';
            $status  = 'success';
        } else {
            $message = 'Цю адресу не знайдено серед підписників.';
            $status  = 'info';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Відписка — ArtSasha</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', Roboto, sans-serif; background: #f7f9fc; color: #333; }
        .box { background: white; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); padding: 36px; max-width: 440px; width: calc(100% - 40px); text-align: center; }
        h1 { margin: 0 0 16px; font-size: 1.8rem; color: #ff5757; }
        .msg { padding: 14px 18px; border-radius: 12px; font-size: 15px; line-height: 1.5; margin: 0 0 24px; }
        .msg.success { background: #ecfdf3; color: #166534; border: 1px solid #86efac; }
        .msg.error   { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .msg.info    { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
        a.btn { display: inline-block; padding: 12px 28px; border-radius: 999px; background: #5ce1e6; color: #333; text-decoration: none; font-weight: bold; transition: background 0.2s; }
        a.btn:hover { background: #ffde59; }
    </style>
</head>
<body>
    <div class="box">
        <h1>ArtSasha ✨</h1>
        <?php if ($message): ?>
            <p class="msg <?php echo $status; ?>"><?php echo htmlspecialchars($message); ?></p>
        <?php else: ?>
            <p class="msg info">Невірне посилання для відписки.</p>
        <?php endif; ?>
        <a href="index.php" class="btn">← На головну</a>
    </div>
</body>
</html>
