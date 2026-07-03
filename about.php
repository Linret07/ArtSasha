<?php
// about.php
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
    <title>Про авторку | ArtSasha</title>
    <style>
        :root {
            --accent: #ff5757;
            --accent-soft: #ffe4e4;
            --blue: #5ce1e6;
            --ink: #2f2f2f;
            --muted: #6b7280;
            --bg: #fffaf3;
            --card: #ffffff;
            --border: #f3d7c7;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--ink);
            background: linear-gradient(180deg, #fffef8 0%, var(--bg) 100%);
            line-height: 1.7;
        }

        .page-shell {
            max-width: 1100px;
            margin: 0 auto;
            padding: 24px 18px 48px;
        }

        header.site-header {
            width: 100%;
            background: #ffffff;
            box-shadow: 0 8px 24px rgba(255,87,87,0.06);
            padding: 14px 0;
            margin-bottom: 24px;
        }

        .topbar-inner {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 0 18px;
            border-radius: 999px;
            border: 1px solid var(--border);
        }

        @media (min-width: 1700px) {
            .topbar-inner {
                max-width: none;
                width: 100%;
                border-radius: 0;
                border: none;
                padding: 0 32px;
                box-shadow: none;
            }
        }

        body.dark-theme header.site-header {
            background: rgba(15,23,42,0.95);
            box-shadow: 0 8px 24px rgba(0,0,0,0.5);
        }
        body.dark-theme .topbar-inner {
            border-color: rgba(148,163,184,0.12);
            background: rgba(17,24,39,0.9);
            color: #f8fafc;
        }

        .brand {
            font-weight: 800;
            color: var(--accent);
            text-decoration: none;
            font-size: 1.05rem;
        }

        .topnav {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .topnav a {
            text-decoration: none;
            color: var(--ink);
            font-weight: 600;
            padding: 8px 12px;
            border-radius: 999px;
        }

        .topnav a:hover {
            background: var(--accent-soft);
            color: var(--accent);
        }

        .hero-card {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 24px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 28px;
            padding: 28px;
            box-shadow: 0 18px 45px rgba(0,0,0,0.06);
            margin-bottom: 24px;
        }

        .hero-text h1 {
            margin: 0 0 10px;
            font-size: clamp(1.8rem, 3vw, 2.7rem);
            color: var(--accent);
        }

        .hero-text p {
            margin: 0 0 16px;
            color: var(--muted);
            font-size: 1.02rem;
        }

        .hero-image {
            width: 100%;
            border-radius: 22px;
            object-fit: cover;
            min-height: 240px;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 18px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 16px;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 700;
            border: 1px solid transparent;
        }

        .btn-primary {
            background: var(--accent);
            color: #fff;
        }

        .btn-secondary {
            background: #f8f8f8;
            color: var(--ink);
            border-color: #e5e7eb;
        }

        .info-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin-top: 16px;
        }

        .info-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 18px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.04);
        }

        .info-card h3 {
            margin-top: 0;
            color: var(--accent);
        }

        .info-card ul {
            margin: 0;
            padding-left: 18px;
            color: var(--muted);
        }

        @media (max-width: 800px) {
            .hero-card { grid-template-columns: 1fr; }
            .info-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="topbar-inner">
            <a class="brand" href="index.php">ArtSasha</a>
            <nav class="topnav">
                <a href="index.php">Головна</a>
                <a href="donate.php">На морозиво</a>
            </nav>
        </div>
    </header>
    <div class="page-shell">

        <section class="hero-card">
            <div class="hero-text">
                <h1>Про авторку</h1>
                <p>Привіт! Я Саша — маленька художниця, яка любить створювати яскраві світи, малювати, фантазувати й ділитися своїми роботами з вами.</p>
                <p>Цей сайт — про творчість, дружню атмосферу та добрі моменти, які народжуються у процесі малювання.</p>
                <div class="hero-actions">
                    <a class="btn btn-primary" href="index.php">Переглянути роботи</a>
                    <a class="btn btn-secondary" href="donate.php">Підтримати</a>
                </div>
            </div>
            <img class="hero-image" src="img/cosmos.avif" alt="Творчий світ ArtSasha">
        </section>

        <div class="info-grid">
            <div class="info-card">
                <h3>Що мене надихає</h3>
                <ul>
                    <li>мальовничі історії та кольори;</li>
                    <li>природа, тварини та казкові образи;</li>
                    <li>ідеї, які народжуються прямо під час творчості.</li>
                </ul>
            </div>
            <div class="info-card">
                <h3>Що тут є</h3>
                <ul>
                    <li>галерея робіт;</li>
                    <li>коментарі та спілкування;</li>
                    <li>можливість підтримати творчий процес.</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>