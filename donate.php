<?php
// Простий донейт-пейдж для кнопки "На морозиво"
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="favicon.ico?v=20260702" type="image/x-icon">
    <link rel="icon" href="favicon.ico?v=20260702" sizes="any">
    <link rel="icon" type="image/svg+xml" href="favicon.svg?v=20260702">
    <link rel="apple-touch-icon" href="apple-touch-icon.png?v=20260702">
    <link rel="manifest" href="site.webmanifest?v=20260702">
    <title>На морозиво — ArtSasha</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Comic Sans MS', cursive, sans-serif;
            background: linear-gradient(180deg, #fffbeb 0%, #fef3c7 100%);
            color: #333;
        }
        .donate-page {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.12);
            padding: 36px;
            max-width: 520px;
            width: calc(100% - 40px);
            text-align: center;
        }
        h1 {
            margin: 0 0 18px;
            font-size: 2.1rem;
            color: #ff5757;
        }
        p {
            margin: 0 0 24px;
            line-height: 1.75;
            color: #545454;
        }
        .donate-card {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            border-radius: 18px;
            background: #fffee4;
            border: 1px solid #ffdf8c;
            box-shadow: inset 0 2px 0 rgba(255,255,255,0.8);
            font-size: 1rem;
        }
        .donate-card span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #fff0e6;
            border: 1px solid #ffd9c7;
            font-size: 1.2rem;
        }
        .actions {
            margin-top: 32px;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 12px;
        }
        .actions a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 18px;
            border-radius: 999px;
            text-decoration: none;
            font-weight: bold;
            color: white;
            background: #ff5757;
            box-shadow: 0 10px 20px rgba(255,87,87,0.25);
        }
        .actions a.secondary {
            background: #f3f4f6;
            color: #374151;
            box-shadow: none;
        }
    </style>
</head>
<body>
    <div class="donate-page">
        <h1>На морозиво ☃️</h1>
        <p>Підтримай ArtSasha невеликим донатом, щоб у нас з’явився фонд на морозиво та нові творчі матеріали. Дякуємо, що ти з нами!</p>
        <div class="donate-card">
            <span>🍦</span>
            <div>
                <div>Скарбничка для добрих людей</div>
                <small>Кинь гроші, хто хоче</small>
            </div>
        </div>
        <div class="actions">
            <a href="https://www.privat24.ua/send/4dhl5" target="_blank" rel="noopener noreferrer">Задонатити</a>
            <a href="index.php" class="secondary">Повернутись назад</a>
        </div>
    </div>
</body>
</html>
