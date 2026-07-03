<?php
// 1. СТАРТ СЕСІЇ (має бути на самому початку без жодних пробілів перед <?php)
session_start();

// HTTP security and cache headers
header_remove('X-Powered-By');
header_remove('Server');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, no-cache');
header('Referrer-Policy: no-referrer-when-downgrade');

// 2. НАЛАШТУВАННЯ БАЗИ ДАНИХ (XAMPP MySQL)
$host = 'localhost';
$user = 'root';
$pass = 'aTyc2708'; // <-- Пароль від MySQL
$db   = 'artsasha_db';

$conn = new mysqli($host, $user, $pass);
$conn->query("CREATE DATABASE IF NOT EXISTS $db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->select_db($db);
$conn->query("CREATE TABLE IF NOT EXISTS drawings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    age VARCHAR(50) NOT NULL,
    category VARCHAR(50) NOT NULL DEFAULT 'Малювання',
    subcategory VARCHAR(50) NOT NULL DEFAULT 'Тварини',
    tags TEXT NOT NULL DEFAULT '',
    image_data LONGTEXT NOT NULL
)");
// Автоматична перевірка та створення колонки для лайків
$conn->query("ALTER TABLE drawings ADD COLUMN IF NOT EXISTS likes INT DEFAULT 0");

$category_check = $conn->query("SHOW COLUMNS FROM drawings LIKE 'category'");
if ($category_check->num_rows == 0) {
    $conn->query("ALTER TABLE drawings ADD COLUMN category VARCHAR(50) NOT NULL DEFAULT 'Малювання'");
}
$subcategory_check = $conn->query("SHOW COLUMNS FROM drawings LIKE 'subcategory'");
if ($subcategory_check->num_rows == 0) {
    $conn->query("ALTER TABLE drawings ADD COLUMN subcategory VARCHAR(50) NOT NULL DEFAULT 'Тварини'");
}
$tags_check = $conn->query("SHOW COLUMNS FROM drawings LIKE 'tags'");
if ($tags_check->num_rows == 0) {
    $conn->query("ALTER TABLE drawings ADD COLUMN tags TEXT NOT NULL DEFAULT ''");
}

// Автоматичне створення таблиці для коментарів, якщо її ще немає
$conn->query("
    CREATE TABLE IF NOT EXISTS comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        drawing_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        text TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (drawing_id) REFERENCES drawings(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$conn->query("CREATE TABLE IF NOT EXISTS subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$correct_password = 'sasha2026'; 
$auth_error = false;
$subscribe_message = '';
$subscribe_status = '';

if (isset($_SESSION['subscribe_message'])) {
    $subscribe_message = $_SESSION['subscribe_message'];
    $subscribe_status = $_SESSION['subscribe_status'] ?? 'success';
    unset($_SESSION['subscribe_message'], $_SESSION['subscribe_status']);
}

$categories = [
    'Всі' => [],
    'Моделювання' => ['Тварини', 'Люди', 'Посуд', 'Кераміка', 'Повітряний пластилін', 'Поробки','Фантазія', 'Інше'],
    'Малювання' => ['Тварини', 'Рослини', 'Природа', 'Люди', 'Фантазія', 'Традиції та культура', 'Інше'],
    'Компʼютерна графіка' => ['Тварини', 'Рослини', 'Природа', 'Люди', 'Фантазія', 'Традиції та культура', 'Інше'],
];

$filter_category = isset($_GET['category']) ? $conn->real_escape_string($_GET['category']) : null;
$filter_subcategory = isset($_GET['subcategory']) ? $conn->real_escape_string($_GET['subcategory']) : null;
$search_query = isset($_GET['search']) ? trim($conn->real_escape_string($_GET['search'])) : null;

// Оброби запит на ВИХІД
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['is_admin']);
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Оброби запит на ВХІД через форму
if (isset($_POST['password'])) {
    if ($_POST['password'] === $correct_password) {
        $_SESSION['is_admin'] = true;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $auth_error = true;
    }
}

// Обробка підписки з футера
if (isset($_POST['subscribe_submit'])) {
    $email = trim($_POST['subscribe_email'] ?? '');
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $subscribe_message = 'Будь ласка, введіть дійсну електронну адресу.';
        $subscribe_status = 'error';
    } else {
        $email_safe = $conn->real_escape_string($email);
        $exists = $conn->query("SELECT id FROM subscribers WHERE email = '$email_safe'")->fetch_assoc();
        if ($exists) {
            $subscribe_message = 'Ця адреса вже підписана на оновлення.';
            $subscribe_status = 'info';
        } else {
            $conn->query("INSERT INTO subscribers (email) VALUES ('$email_safe')");
            $subscribe_message = 'Дякуємо! Ви успішно підписані.';
            $subscribe_status = 'success';
        }
    }

    $_SESSION['subscribe_message'] = $subscribe_message;
    $_SESSION['subscribe_status'] = $subscribe_status;
    header('Location: ' . $_SERVER['PHP_SELF'] . '#subscribe-form');
    exit();
}

// Визначаємо статус адміна СУВОРO з сесії
$is_admin = (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);

// ЛОГІКА ВИДАЛЕННЯ МАЛЮНКА (Працює безвідмовно)
if (isset($_POST['delete_id'])) {
    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
        $delete_id = intval($_POST['delete_id']);
        $conn->query("DELETE FROM drawings WHERE id = $delete_id");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// 3. ОБРОБКА ЗАВАНТАЖЕННЯ МАЛЮНКІВ (З НАЛОЖЕННЯМ ВОДЯНОГО ЗНАКУ)
if (isset($_POST['upload']) && $is_admin) {
    $title = $conn->real_escape_string($_POST['title']);
    $age = $conn->real_escape_string($_POST['age']);

    $selected_tags = isset($_POST['tags']) ? $_POST['tags'] : [];
    if (!is_array($selected_tags)) {
        $selected_tags = [$selected_tags];
    }

    $category_candidates = [];
    $subcategory_candidates = [];
    $all_tag_values = [];

    foreach ($selected_tags as $tag) {
        $tag = trim($tag);
        if ($tag === '') {
            continue;
        }

        if (strpos($tag, '::') !== false) {
            list($tag_category, $tag_subcategory) = explode('::', $tag, 2);
            $tag_category = trim($tag_category);
            $tag_subcategory = trim($tag_subcategory);

            if ($tag_category !== '' && $tag_category !== 'Всі') {
                $category_candidates[] = $tag_category;
                $all_tag_values[] = $conn->real_escape_string($tag_category);
            }
            if ($tag_subcategory !== '') {
                $subcategory_candidates[] = $tag_subcategory;
                $all_tag_values[] = $conn->real_escape_string($tag_subcategory);
            }
        } else {
            $all_tag_values[] = $conn->real_escape_string($tag);
            $subcategory_candidates[] = $tag;
        }
    }

    $all_tag_values = array_values(array_unique($all_tag_values));
    $category = !empty($category_candidates) ? $category_candidates[0] : 'Малювання';
    $subcategory = !empty($subcategory_candidates) ? $subcategory_candidates[0] : 'Тварини';
    $tags_string = implode(',', $all_tag_values);
    
    if ($_FILES['image']['tmp_name']) {
        $source_file = $_FILES['image']['tmp_name'];
        $img_info = getimagesize($source_file);
        $mime = $img_info['mime'];

        // Створюємо зображення в пам'яті залежно від його типу
        if ($mime == 'image/jpeg' || $mime == 'image/jpg') {
            $image = imagecreatefromjpeg($source_file);
        } elseif ($mime == 'image/png') {
            $image = imagecreatefrompng($source_file);
        } elseif ($mime == 'image/gif') {
            $image = imagecreatefromgif($source_file);
        } else {
            $image = false;
        }

        if ($image) {
            $img_w = imagesx($image);
            $img_h = imagesy($image);

            // Текст водяного знаку
            $text = "ArtSasha"; 
            
            // Шлях до файлу шрифту (переконайтеся, що файл font.ttf лежить поруч з цим скриптом)
            $font_path = __DIR__ . '/font.ttf'; 

            if (file_exists($font_path)) {
                // Великий красивий шрифт для великих фото з телефону (5% від ширини)
                $font_size = max(16, round($img_w * 0.05)); 

                // Розрахунок меж тексту
                $bbox = imageftbbox($font_size, 0, $font_path, $text);
                $text_w = abs($bbox[2] - $bbox[0]);
                $text_h = abs($bbox[5] - $bbox[1]);

                // Позиція в правому нижньому кутку
                $x = $img_w - $text_w - round($img_w * 0.03);
                $y = $img_h - round($img_h * 0.03);

                // Кольори (Білий текст + Чорна тінь для читаємості на будь-якому фоні)
                $shadow_color = imagecolorallocatealpha($image, 0, 0, 0, 70);
                $text_color = imagecolorallocatealpha($image, 255, 255, 255, 40);

                // Нанесення тексту
                imagefttext($image, $font_size, 0, $x + 3, $y + 3, $shadow_color, $font_path, $text);
                imagefttext($image, $font_size, 0, $x, $y, $text_color, $font_path, $text);
            } else {
                // Запасний варіант дрібним стандартним шрифтом GD (якщо font.ttf не знайдено)
                $font = 5;
                $text_w = imagefontwidth($font) * strlen($text);
                $text_h = imagefontheight($font);
                $x = $img_w - $text_w - 15;
                $y = $img_h - $text_h - 15;
                $shadow_color = imagecolorallocatealpha($image, 0, 0, 0, 60);
                $text_color = imagecolorallocatealpha($image, 255, 255, 255, 40);
                imagestring($image, $font, $x + 2, $y + 2, $text, $shadow_color);
                imagestring($image, $font, $x, $y, $text, $text_color);
            }

            // Зберігаємо оброблену картинку з якістю 90%
            ob_start();
            imagejpeg($image, null, 90); 
            $img_data = ob_get_clean();
            imagedestroy($image);

            $base64 = 'data:image/jpeg;base64,' . base64_encode($img_data);
        } else {
            // Якщо GD не зміг відкрити файл — беремо оригінал
            $img_type = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $img_data = file_get_contents($source_file);
            $base64 = 'data:image/' . $img_type . ';base64,' . base64_encode($img_data);
        }
        
        $conn->query("INSERT INTO drawings (title, age, category, subcategory, tags, image_data) VALUES ('$title', '$age', '$category', '$subcategory', '$tags_string', '$base64')");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// 4. ОТРИМАННЯ МАЛЮНКІВ З БД
$conditions = [];
if ($filter_category && $filter_category !== 'Всі') {
    $conditions[] = "FIND_IN_SET('$filter_category', tags)";
}
if ($filter_subcategory) {
    $conditions[] = "FIND_IN_SET('$filter_subcategory', tags)";
}
if ($search_query) {
    $conditions[] = "(title LIKE '%$search_query%' OR age LIKE '%$search_query%' OR tags LIKE '%$search_query%')";
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Пагінація: максимум 20 записів на сторінку
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int) $_GET['page'] : 1;
$perPage = isset($_GET['per']) && is_numeric($_GET['per']) && (int)$_GET['per'] > 0 ? (int)$_GET['per'] : 20;
$offset = ($page - 1) * $perPage;

// Загальна кількість робіт для поточного фільтра
$countResult = $conn->query("SELECT COUNT(*) AS total FROM drawings $where");
$totalCount = $countResult ? (int) $countResult->fetch_assoc()['total'] : 0;
$totalPages = (int) ceil($totalCount / $perPage);

$result = $conn->query("SELECT drawings.*, COALESCE(comment_counts.comment_count, 0) AS comment_count
    FROM drawings
    LEFT JOIN (
        SELECT drawing_id, COUNT(*) AS comment_count
        FROM comments
        GROUP BY drawing_id
    ) AS comment_counts ON drawings.id = comment_counts.drawing_id
    $where ORDER BY drawings.id DESC LIMIT $perPage OFFSET $offset");

// Автоматичний підрахунок статистики для картки "Всі"
$statsQuery = $conn->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN tags LIKE '%Малювання%' THEN 1 ELSE 0 END) AS count_paint,
        SUM(CASE WHEN tags LIKE '%Моделювання%' THEN 1 ELSE 0 END) AS count_model,
        SUM(CASE WHEN tags LIKE '%Комп%графіка%' THEN 1 ELSE 0 END) AS count_digital
    FROM drawings
");

if ($statsQuery) {
    $stats = $statsQuery->fetch_assoc();
} else {
    $stats = ['total' => 0, 'count_paint' => 0, 'count_model' => 0, 'count_digital' => 0];
}
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
    <meta name="description" content="Офіційна онлайн-галерея творчих робіт Олександри. Дивіться малюнки, комп'ютерну графіку та унікальні поробки з моделювання в одному місці!">
    <title>ArtSasha — Магічний світ творчості Олександри | Галерея робіт</title>
    <link rel="stylesheet" href="assets/header-mobile.css">
    <script>
        (function(){
            var isMobile = window.matchMedia('(max-width:520px)').matches;
            var category = <?php echo json_encode($filter_category); ?>;
            var per = <?php echo isset($_GET['per']) ? (int)$_GET['per'] : 'null'; ?>;
            if (isMobile && category === 'Всі' && per !== 10) {
                var url = new URL(window.location.href);
                url.searchParams.set('per', '10');
                url.searchParams.set('page', '1');
                window.location.replace(url.toString());
            }
        })();
    </script>
    
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap');
*{font-family: 'Inter', sans-serif !important; box-sizing: border-box; margin: 0; padding: 0;}
/* 2. ЗАСТОСУВАННЯ ТА ЖОРСТКА ФІКСАЦІЯ НА УСІХ ПРИСТРОЯХ */
html, body {
    font-family: 'Inter', sans-serif !important;
    
    /* Блокуємо самовільну зміну масштабу тексту на різних телефонах */
    -webkit-text-size-adjust: 100%; 
    -moz-text-size-adjust: 100%;
    -ms-text-size-adjust: 100%;
    text-size-adjust: 100%;
}

    
        body { font-family: 'Comic Sans MS', cursive, sans-serif; background-color: #f0fdf4; color: #333; margin: 0; padding: 20px; text-align: center; }
        
        header { 
            background-color: #ffffff; 
            border-radius: 18px; 
            box-shadow: 0 8px 18px rgba(0,0,0,0.08); 
            max-width: 100%; 
            margin: 0 auto;
            position: relative;
            padding: 22px;
        }
        
@keyframes logoGlow {
            0% { transform: scale(1); }
            50% { transform: scale(1.04); }
            100% { transform: scale(1); }
        }
        @keyframes logoColor {
            0% { filter: hue-rotate(0deg); }
            50% { filter: hue-rotate(15deg); }
            100% { filter: hue-rotate(0deg); }
        }

        .logo { 
            font-family: 'Pangolin', 'Comic Sans MS', cursive, sans-serif; 
            font-size: 48px; 
            font-weight: bold; 
            display: inline-flex;
            align-items: center;
            gap: 8px;
            letter-spacing: 1px;
            animation: logoGlow 4s ease-in-out infinite;
            text-decoration: none;
            color: inherit;
        }

        .logo:hover { opacity: 0.92; }

        .logo .art, .logo .sasha {
            animation: logoColor 6s ease-in-out infinite;
        }
        .logo .art { color: #ff5757; text-shadow: 2px 2px 0px #ffde59; }
        .logo .sasha { color: #5ce1e6; text-shadow: 2px 2px 0px #ff5757; margin-left: -2px; }
        .logo .palette { margin-left: 8px; font-size: 1.056em; }
        .logo .palette-img { width: 1.056em; height: 1.056em; display: inline-block; vertical-align: middle; object-fit: contain; }
        
        /* КНОПКИ СПРАВА ЗВЕРХУ */
        .header-top {
            display: grid;
            grid-template-columns: minmax(400px, 1fr) auto minmax(400px, 1fr);
            align-items: center;
            justify-items: stretch;
            gap: 20px;
            width: 100%;
            max-width: 1500px;
            margin: 0 auto 16px;
        }
        .header-top > .search-form { justify-self: start; }
        .header-top > .logo-wrapper { justify-self: center; }
        .header-top > .header-buttons { justify-self: end; }
        @media (min-width: 1700px) {
            .header-top {
                max-width: 100%;
                width: 100%;
                padding: 0 32px;
                box-sizing: border-box;
            }
        }
        @media (max-width: 820px) {
            .header-top {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                text-align: center;
            }
            .logo-wrapper { align-items: center; text-align: center; }
            .header-top > .search-form,
            .header-top > .header-buttons { justify-self: center; }
        }
        .logo-wrapper { display: inline-flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; text-align: center; min-width: 0; }
        .header-buttons {

            display: inline-flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .login-top-btn, .donate-btn {
            position: static;
            background-color: #ffde59;
            color: #333;
            border: none;
            padding: 10px 15px;
            font-size: 14px;
            font-family: inherit;
            font-weight: bold;
            border-radius: 20px;
            cursor: pointer;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .login-top-btn:hover, .donate-btn:hover { background-color: #ff5757; color: white; }
        
        .donate-btn {
            background-color: #ff9f9f;
            color: #5c1a1a;
            border: 1px solid #f8b4b4;
        }
        .donate-btn:hover {
            background-color: #ff5757;
            color: white;
        }

        .logout-btn {
            background-color: #ffeeed;
            color: #e53e3e;
            border: 1px solid #fed7d7;
        }
        .logout-btn:hover {
            background-color: #e53e3e;
            color: white;
            border-color: #e53e3e;
        }
        .theme-toggle-btn {
            position: static;
            background: linear-gradient(135deg, #4f46e5 0%, #1d4ed8 50%, #0f172a 100%);
            color: #f8fafc;
            border: 1px solid rgba(255,255,255,0.18);
            padding: 10px 16px;
            font-size: 14px;
            font-family: inherit;
            font-weight: bold;
            border-radius: 28px;
            cursor: pointer;
            box-shadow: 0 8px 22px rgba(31,41,55,0.18);
            transition: transform 0.25s ease, box-shadow 0.25s ease, background 0.25s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .theme-toggle-btn:hover {
            transform: translateY(-1px);
            background: linear-gradient(135deg, #4338ca 0%, #2563eb 50%, #1f2937 100%);
        }
        body.dark-theme {
            background-color: #020617;
            background-image:
                linear-gradient(180deg, rgba(2,5,23,0.75), rgba(2,5,23,0.75)),
                url('img/cosmos.avif');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            color: #f8fafc;
        }
        body.dark-theme::before {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            background-image:
                radial-gradient(circle at 16% 22%, rgba(255,255,255,0.14), transparent 12%),
                radial-gradient(circle at 55% 14%, rgba(168,85,247,0.08), transparent 14%),
                radial-gradient(circle at 82% 64%, rgba(59,130,246,0.1), transparent 11%),
                radial-gradient(circle at 28% 78%, rgba(248,113,113,0.08), transparent 10%),
                radial-gradient(circle at 67% 84%, rgba(251,191,36,0.08), transparent 9%),
                radial-gradient(circle at 40% 35%, rgba(135,206,250,0.05), transparent 7%);
            opacity: 0.8;
            mix-blend-mode: screen;
            z-index: -1;
        }
        body.dark-theme header,
        body.dark-theme .admin-form-wrap,
        body.dark-theme .category-card,
        body.dark-theme .card,
        body.dark-theme .comments-content,
        body.dark-theme .admin-left,
        body.dark-theme .admin-right,
        body.dark-theme .admin-right .tag-checkboxes,
        body.dark-theme .gallery,
        body.dark-theme .site-footer,
        body.dark-theme .footer-bottom,
        body.dark-theme .site-stats,
        body.dark-theme .site-footer,
        body.dark-theme .footer-bottom,
        body.dark-theme .site-stats {
            background: rgba(15,23,42,0.95);
            color: #f8fafc;
            border-color: rgba(148,163,184,0.16);
        }
        body.dark-theme .comments-content {
            background: rgba(15,23,42,0.95);
        }
        body.dark-theme .category-card h3,
        body.dark-theme .category-card p,
        body.dark-theme .card h3,
        body.dark-theme .card p,
        body.dark-theme .tag-item-btn,
        body.dark-theme .tags-row-title,
        body.dark-theme .gallery-title,
        body.dark-theme .tag-label,
        body.dark-theme .comment-item,
        body.dark-theme .comment-meta,
        body.dark-theme .comment-text,
        body.dark-theme .footer-column h4,
        body.dark-theme .footer-column p,
        body.dark-theme .footer-bottom,
        body.dark-theme .site-stats,
        body.dark-theme .site-stats span,
        body.dark-theme .site-stats strong {
            color: #f8fafc;
        }
        body.dark-theme .comment-item {
            background: rgba(15,23,42,0.94);
            border-color: rgba(71,85,105,0.3);
            color: #f8fafc;
        }
        body.dark-theme .comment-meta { color: #cbd5e1; }
        body.dark-theme .comment-text { color: #e2e8f0; }
        body.dark-theme .comment-form input,
        body.dark-theme .comment-form textarea {
            background: #0f172a;
            border-color: #334155;
            color: #f8fafc;
        }
        body.dark-theme .footer-column { background: rgba(15,23,42,0.85); border: 1px solid rgba(148,163,184,0.15); }
        body.dark-theme .footer-column h4,
        body.dark-theme .footer-column p { color: #f8fafc; }
        body.dark-theme .footer-subscribe-form input,
        body.dark-theme .footer-subscribe-form button { background: #111827; color: #f8fafc; border-color: #334155; }
        body.dark-theme .footer-subscribe-message.success {
            background: rgba(22, 101, 52, 0.28);
            color: #dcfce7;
            border-color: rgba(74, 222, 128, 0.5);
        }
        body.dark-theme .footer-subscribe-message.error {
            background: rgba(153, 27, 27, 0.28);
            color: #fee2e2;
            border-color: rgba(248, 113, 113, 0.45);
        }
        body.dark-theme .footer-subscribe-message.info {
            background: rgba(29, 78, 216, 0.24);
            color: #dbeafe;
            border-color: rgba(96, 165, 250, 0.45);
        }
        body.dark-theme .site-stats { background: rgba(15,23,42,0.9); }
        body.dark-theme .site-stats,
        body.dark-theme .site-stats span,
        body.dark-theme .site-stats strong {
            color: #f8fafc;
        }
        body.dark-theme .tag-item-btn {
            background: rgba(30,41,59,0.75);
            color: #f8fafc;
            border-color: rgba(148,163,184,0.24);
        }
        body.dark-theme .tag-checkbox-label {
            background: rgba(15,23,42,0.85);
            color: #f8fafc;
            border-color: rgba(100,116,139,0.3);
        }
        body.dark-theme .header-top,
        body.dark-theme .logo-wrapper,
        body.dark-theme .header-buttons,
        body.dark-theme .search-form input,
        body.dark-theme .search-form button,
        body.dark-theme .form-input-field,
        body.dark-theme .form-file-field,
        body.dark-theme .tag-checkbox-label,
        body.dark-theme .view-btn,
        body.dark-theme .delete-btn,
        body.dark-theme .form-submit-btn {
            background: #111827;
            color: #f8fafc;
            border-color: #334155;
        }
        body.dark-theme .search-form input,
        body.dark-theme .form-input-field,
        body.dark-theme .form-file-field,
        body.dark-theme .tag-checkbox-label {
            background: #111827;
        }
        body.dark-theme .view-btn { background: #2563eb; color: #fff; }
        body.dark-theme .view-btn:hover { background: #1d4ed8; }
        body.dark-theme .delete-btn { background: #7c2d12; color: #f8fafc; }
        body.dark-theme .logout-btn { background: #111827; color: #f8fafc; border-color: #334155; }
        body.dark-theme .login-top-btn { background: #2563eb; color: #fff; }
        body.dark-theme .footer-bottom { border-top-color: rgba(148,163,184,0.18); color: #cbd5e1; }
        body.dark-theme .theme-toggle-btn { background: linear-gradient(135deg, #0f172a 0%, #4338ca 40%, #2563eb 100%); }
        
        /* Секція додавання малюнків */
        .admin-box { background: white; width: min(100%, 960px); margin: 30px auto; padding: 24px; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); text-align: left; }
        .search-form { display: flex; gap: 0; flex: 1 1 320px; min-width: 240px; max-width: 420px; }
        .search-form input { flex: 1; padding: 10px 14px; border: 2px solid #ddd; border-right: none; border-radius: 12px 0 0 12px; font-family: inherit; }
        .search-form button { padding: 10px 18px; border: 2px solid #ddd; border-left: none; border-radius: 0 12px 12px 0; background: #5ce1e6; color: #333; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .search-form button:hover { background: #ffde59; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 8px; box-sizing: border-box; font-family: inherit; }
        .btn { width: 100%; background: #ffde59; border: none; padding: 12px; font-size: 16px; font-weight: bold; border-radius: 8px; cursor: pointer; font-family: inherit; transition: 0.2s; }
        .btn:hover { background: #ff5757; color: white; }
        .upload-form { display: flex; flex-direction: column; gap: 18px; width: 100%; }
        .upload-grid { display: flex; gap: 24px; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; max-width: 100%; }
        .upload-main { flex: 2 1 520px; min-width: 280px; }
        .upload-tags { flex: 1 1 320px; min-width: 280px; background: #f9fafb; padding: 18px; border-radius: 14px; }
        .upload-tags .tag-checkboxes { max-height: 400px; overflow-y: auto; padding-right: 4px; }
        .upload-tags .form-group { margin-bottom: 0; }
        .upload-tags .tag-group { justify-content: flex-start; }
        .upload-tags label.checkbox-pill { width: auto; }
        .admin-box h3 { margin-bottom: 24px; }

        /* СПЛИВАЮЧЕ МОДАЛЬНЕ ВІКНО */
        .modal {
            display: <?php echo $auth_error ? 'flex' : 'none'; ?>;
            position: fixed;
            z-index: 1000;
            left: 0; top: 0; width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.6);
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            padding: 25px;
            border-radius: 16px;
            width: 100%;
            max-width: 320px;
            text-align: left;
            position: relative;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        .close-btn {
            position: absolute;
            right: 15px; top: 10px;
            font-size: 24px;
            cursor: pointer;
            color: #aaa;
        }
        .close-btn:hover { color: #333; }

        /* Галерея */
		.gallery { 
			display: grid; 
			/* repeat(auto-fit...) автоматично підлаштовує кількість карток під екран */
			grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); 
			gap: 20px; 
			margin-top: 40px; 
			padding: 10px;
			}
        .card { background: white; padding: 15px; border-radius: 16px; box-shadow: 0 6px 12px rgba(0,0,0,0.08); }
        .card img { 
            width: 100%; 
            height: 220px; 
            object-fit: contain; 
            border-radius: 10px; 
            background-color: #fafafa; 
            border: 1px solid #edf2f7;
            box-sizing: border-box;
        }
        .card h3 { margin: 15px 0 5px 0; color: #333; }
        .card p { margin: 0; color: #ff5757; font-weight: bold; }
        
        .pagination-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            margin: 24px auto 40px;
        }
        .page-info {
            color: #666;
            font-size: 14px;
        }
        .pagination {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            justify-content: center;
        }
        .page-btn,
        .page-number {
            display: inline-flex;
            min-width: 40px;
            padding: 10px 14px;
            border-radius: 12px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            border: 1px solid #e2e8f0;
            color: #334155;
            background: #fff;
            transition: background-color 0.2s, color 0.2s, transform 0.2s;
        }
        .page-btn:hover,
        .page-number:hover {
            background: #f8fafc;
            color: #111827;
            transform: translateY(-1px);
        }
        .page-active {
            background: #334155;
            color: white;
            border-color: #334155;
            pointer-events: none;
        }
        .page-ellipsis {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 24px;
            color: #94a3b8;
            font-size: 14px;
            padding: 10px 4px;
        }

        footer {
            padding: 40px 20px;
            margin-top: 60px;
            color: #334155;
        }
        
        .footer-bottom {
            border-top: 1px solid rgba(148, 163, 184, 0.24);
            padding-top: 18px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 12px;
            color: #64748b;
            font-size: 14px;
        }
  

        .view-btn {
            background-color: #5ce1e6;
            color: #333;
            border: none;
            padding: 8px 16px;
            font-size: 14px;
            font-family: inherit;
            font-weight: bold;
            border-radius: 20px;
            cursor: pointer;
            transition: 0.2s;
            margin-top: 10px;
        }
        .view-btn:hover { 
            background-color: #ffde59; 
            transform: scale(1.05); 
        }
        
        .delete-btn {
            background-color: #ffeeed;
            color: #e53e3e;
            border: 1px solid #fed7d7;
            padding: 6px 12px;
            font-size: 12px;
            font-family: inherit;
            font-weight: bold;
            border-radius: 15px;
            cursor: pointer;
            transition: 0.2s;
            margin-top: 8px;
        }
        .delete-btn:hover {
            background-color: #e53e3e;
            color: white;
        }

        .categories-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 30px;
            padding: 10px;
        }
        .category-card { background: white; padding: 22px; border-radius: 18px; box-shadow: 0 8px 18px rgba(0,0,0,0.08); overflow: hidden; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 18px; }
        .category-content { width: 100%; }
        .category-card h3 { margin-top: 0; font-size: 24px; color: #333; }
        .subcategory-list { justify-content: center; }
        .category-card p { color: #666; margin-bottom: 16px; }
        .subcategory-list { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 12px; }
        .subcategory-link {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 999px;
            background: #ffde59;
            color: #333;
            text-decoration: none;
            font-weight: bold;
            transition: 0.2s;
        }
        .subcategory-link:hover { background: #ff5757; color: white; }
        .category-button {
            display: inline-block;
            margin-top: 12px;
            padding: 10px 18px;
            border-radius: 999px;
            background: #5ce1e6;
            color: #333;
            text-decoration: none;
            font-weight: bold;
            transition: 0.2s;
        }
        .category-button:hover { background: #ffde59; }
        .tag-checkboxes { display: grid; gap: 12px; }
        .tag-group { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .tag-group strong { margin-right: 10px; color: #333; }
        .checkbox-pill { display: inline-flex; align-items: center; gap: 6px; background: #f7f3ff; border: 1px solid #ddd; border-radius: 999px; padding: 8px 12px; font-size: 14px; cursor: pointer; }
        .checkbox-pill input { margin: 0; }
        
        .tag-pill { display: inline-block; background: #ffde59; color: #333; padding: 6px 12px; border-radius: 999px; margin-right: 8px; margin-top: 8px; font-weight: bold; font-size: 13px; }
        .tag-pill + .tag-pill { margin-left: 4px; }
        
        body, input, select, button, textarea { box-sizing: border-box; }

        /* Стилі для блоку статистики в картці "Всі" */
        .site-stats {
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: 100%;
            margin-top: 15px;
            text-align: left;
            background: #f7fdf9;
            padding: 14px;
            border-radius: 14px;
            border: 1px dashed #5ce1e6;
            box-sizing: border-box;
        }
        .stat-row {
            display: flex;
            justify-content: space-between;
            font-size: 15px;
            color: #4a5568;
        }
        .stat-row strong {
            color: #2d3748;
        }
        .stat-total {
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
            margin-top: 6px;
            font-weight: bold;
        }


        /* Налаштування спеціально для екранів смартфонів */
        @media (max-width: 820px) {
            .header-top {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                text-align: center;
                gap: 18px;
            }

            /* ФІКС ПОШУКУ ДЛЯ СМАРТФОНІВ */
            .header-top > .search-form { 
                flex: none;          /* Забороняємо формі розтягуватися */
                width: 100%;         /* Дозволяємо адаптуватися по ширині */
                max-width: 320px;    /* Задаємо акуратну максимальну ширину */
                height: 42px;        /* Фіксуємо висоту форми пошуку */
                display: flex;       /* Щоб інпут і кнопка стояли в один рядок */
            }
            .header-top > .search-form input,
            .header-top > .search-form button {
                height: 100%;        /* Змушуємо інпут та кнопку бути на всю висоту форми (42px) */
                box-sizing: border-box;
            }

            .logo-wrapper { align-items: center; text-align: center; }
            .header-top > .search-form,
            .header-top > .header-buttons { justify-self: center; }

            .category-card { padding: 18px; }
            .upload-grid { flex-direction: column; }
            .upload-main, .upload-tags { min-width: auto; width: 100%; }
            .admin-box { padding: 18px; }
            .gallery { padding: 0 8px; }
        }

        @media (max-width: 600px) {
            body { padding: 10px; }
            header { padding: 20px 12px; }
            .logo { font-size: 30px; }
            .login-top-btn {
                width: 100%;
                justify-content: center;
                margin-top: 0;
            }
            .card img { height: auto; max-height: 260px; }
            .category-card { padding: 16px; }
            .upload-grid { gap: 14px; }
            .upload-tags { max-height: 280px; }

            .modal-content { margin: 12px; width: calc(100% - 24px); max-width: 340px; }
            #viewerCaption { font-size: 18px; padding: 0 10px; bottom: 15px; }
        }
        @media (min-width: 1400px) {
            body { padding: 40px 80px; }
            header { max-width: 1400px; margin: 0 auto; padding: 40px 40px; }
            .logo { font-size: 64px; }
            .categories-row { gap: 30px; padding: 0; }
            .category-card { padding: 32px; gap: 24px; }
            .category-card h3 { font-size: 32px; }
            .category-card p { font-size: 18px; }
            .category-button, .subcategory-link, .login-top-btn, .btn { font-size: 16px; }
            .gallery { grid-template-columns: repeat(4, minmax(260px, 1fr)); gap: 30px; }
            .card { padding: 24px; }
            .card img { height: 300px; }
            .admin-box { max-width: 540px; margin: 40px auto; padding: 30px; }
            .form-group input, .form-group select, .btn { font-size: 16px; }
        }
        /* Нове індивідуальне оформлення для тегів під малюнками */
        .tag-item-btn {
            display: inline-block;
            background-color: #f1f5f9; /* ніжний світло-сірий колір замість жовтого */
            color: #475569;            /* спокійний темно-сірий колір тексту */
            border: 1px solid #cbd5e1; /* тонка акуратна рамка */
            padding: 4px 10px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 999px;      /* ідеально закруглені овальні плашки */
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        /* Ефект при наведенні мишкою або кліку */
        .tag-item-btn:hover {
            background-color: #dcfce7; /* змінюється на ваш фірмовий блакитний */
            color: #14532d;               /* темний текст для контрасту */
            border-color: #5ce1e6;
        }

        .btn-to-top {
            position: fixed;
            bottom: 52px;
            right: 52px;
            width: 67px;
            height: 67px;
            background: radial-gradient(circle at 30% 30%, #ffb347 0%, #ff4d4d 55%, #e11d48 100%);
            color: white;
            border: 2px solid rgba(255,255,255,0.8);
            border-radius: 50%;
            font-size: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 10px 25px rgba(255,87,87,0.35);
            z-index: 999;
            transition: transform 0.2s ease, opacity 0.3s ease, box-shadow 0.2s ease;
            opacity: 0;
            visibility: hidden;
            margin-bottom: 150px;
        }
        .btn-to-top:hover {
            transform: translateY(-2px) scale(1.1);
            box-shadow: 0 12px 28px rgba(255,87,87,0.45);
        }
        .btn-to-top::before {
            content: '🚀';
            font-size: 26px;
            position: relative;
            top: -1px;
        }
        .btn-to-top::after {
            content: '';
            position: absolute;
            bottom: 8px;
            width: 16px;
            height: 16px;
            background: radial-gradient(circle, rgba(255,255,255,0.9) 0%, rgba(255,209,64,0.9) 35%, rgba(255,116,0,0.8) 100%);
            border-radius: 50% 50% 45% 45%;
            filter: blur(1px);
            animation: rocket-flame 0.35s ease-in-out infinite;
        }
        @keyframes rocket-flame {
            0%, 100% { transform: translateY(0) scaleX(1); opacity: 0.9; }
            50% { transform: translateY(3px) scaleX(1.15); opacity: 0.6; }
        }
        /* Стилі для кнопочки лайка */
        .like-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 12px;
        }
        .like-btn {
            background: #fff5f5;
            border: 1px solid #fecaca;
            color: #ef4444;
            padding: 6px 14px;
            font-size: 14px;
            font-weight: bold;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .like-btn:hover {
            background: #fee2e2;
            transform: scale(1.05);
        }
            .like-btn.liked {
            animation: pulseLike 0.4s ease-in-out;
            background: #fbcfe8; /* насичений червоний фон */
            color: #9d174d;      /* білий колір цифри */
            border-color:  #fbcfe8;
        }

        @keyframes pulseLike {
            0% { transform: scale(1); }
            50% { transform: scale(1.3); }
            100% { transform: scale(1); }
        }
        /* Стилі для чарівного вікна коментарів */
        .comments-modal {
            display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center;
            -webkit-backdrop-filter: blur(4px);
            backdrop-filter: blur(4px);
        }
        .comments-content {
            background: white; padding: 25px; border-radius: 20px; width: 90%; max-width: 500px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1); position: relative; animation: modalSlide 0.3s ease;
        }
        .comments-list { max-height: 250px; overflow-y: auto; margin: 15px 0; padding-right: 5px; text-align: left; }
        .comment-item { background: #f0fdf4; padding: 12px 14px 12px 14px; border-radius: 12px; margin-bottom: 10px; border: 1px solid #e2e8f0; position: relative; }
        .comment-meta { font-size: 12px; color: #718096; display: flex; justify-content: space-between; gap: 10px; margin-bottom: 6px; }
        .comment-name { font-weight: bold; color: #2d3748; }
        .comment-text { font-size: 14px; color: #4a5568; word-break: break-word; }
        .comment-delete-btn { position: absolute; top: 12px; right: 12px; width: 30px; height: 30px; border-radius: 50%; border: 1px solid rgba(220,38,38,0.25); background: rgba(220,38,38,0.12); color: #dc2626; display: inline-flex; align-items: center; justify-content: center; font-size: 16px; cursor: pointer; }
        .comment-delete-btn:hover { background: rgba(220,38,38,0.18); }

        /* Форма коментарів */
        .comment-form { display: flex; flex-direction: column; gap: 10px; }
        @keyframes modalSlide { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .close-comments { position: absolute; right: 20px; top: 15px; font-size: 28px; cursor: pointer; color: #aaa; }
        .close-comments:hover { color: #ff5757; }
        
        /* Список коментарів */
        .comments-list { max-height: 250px; overflow-y: auto; margin: 15px 0; padding-right: 5px; text-align: left; }
        .comment-item { background: #f0fdf4; padding: 12px 14px; border-radius: 12px; margin-bottom: 10px; border: 1px solid #e2e8f0; position: relative; }
        .comment-meta { font-size: 12px; color: #718096; display: flex; justify-content: space-between; gap: 10px; margin-bottom: 6px; }
        .comment-name { font-weight: bold; color: #2d3748; }
        .comment-text { font-size: 14px; color: #4a5568; word-break: break-word; }
        .comment-delete-btn { position: absolute; top: 12px; right: 12px; width: 32px; height: 32px; border-radius: 50%; border: 1px solid rgba(220,38,38,0.25); background: rgba(220,38,38,0.12); color: #dc2626; display: inline-flex; align-items: center; justify-content: center; font-size: 16px; cursor: pointer; }
        .comment-delete-btn:hover { background: rgba(220,38,38,0.18); }
        
        /* Форма коментарів */
        .comment-form { display: flex; flex-direction: column; gap: 10px; }
        .comment-form input, .comment-form textarea {
            width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 12px; font-family: inherit; box-sizing: border-box;
        }
        .comment-form textarea { resize: none; height: 70px; }
        /* Стилі для великих стрілок, притиснутих до малюнка */
        .slider-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 0, 0, 0.4); /* темніший фон для контрасту */
            color: #ffffff;
            border: 2px solid rgba(255, 255, 255, 0.4);
            width: 60px;  /* Збільшили розмір */
            height: 60px;
            font-size: 32px; /* Збільшили стрілочку */
            font-weight: bold;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            z-index: 2050;
            -webkit-user-select: none;
            user-select: none;
        }
        .slider-arrow:hover {
            background: #5ce1e6; /* Ваші фірмові кольори при наведенні */
            color: #333;
            transform: translateY(-50%) scale(1.1);
            border-color: #5ce1e6;
        }
        
        /* Стилі для великого хрестика */
        .close-slider {
            position: absolute; 
            right: 20px; 
            top: 15px; 
            font-size: 50px; /* Зробили величезним */
            color: rgba(255, 255, 255, 0.7); 
            cursor: pointer; 
            z-index: 2100;
            transition: color 0.2s;
            line-height: 1;
        }
        .close-slider:hover {
            color: #ff5757;
        }

        /* Посуваємо стрілки ближче до малюнка на великих екранах */
        @media (min-width: 1000px) {
            .slider-arrow[onclick*="-1"] { left: calc(50% - 40vw); } /* ліва стрілка */
            .slider-arrow[onclick*="1"] { right: calc(50% - 40vw); } /* права стрілка */
        }

        /* Адаптація для телефонів (тут стрілки залишаються по боках, щоб не перекривати малюнок) */
        @media (max-width: 600px) {
            .slider-arrow {
                width: 46px;
                height: 46px;
                font-size: 22px;
            }
            .slider-arrow[onclick*="-1"] { left: 10px; }
            .slider-arrow[onclick*="1"] { right: 10px; }
            .close-slider { font-size: 44px; right: 15px; top: 10px; }
        }

        /* Чарівна панель форми додавання */
        .admin-form-wrap {
            background: #ffffff; 
            padding: 20px; 
            border-radius: 24px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.05); 
            max-width: 960px; 
            margin: 26px auto 32px;
            text-align: left;
            border: 2px dashed #bbf7d0;
        }
        .admin-form-wrap h3 { 
            color: #ff5757; 
            margin: 0 0 18px;
            text-align: center;
            font-size: 20px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        
        .admin-main-form { display: flex; flex-direction: column; gap: 18px; }
        .admin-form-grid { display: grid; grid-template-columns: minmax(300px, 1.45fr) minmax(260px, 1fr); gap: 18px; align-items: start; }
        .admin-left, .admin-right { background: #f8fafc; padding: 18px; border-radius: 20px; border: 1px solid #e2e8f0; }
        .admin-left { display: flex; flex-direction: column; gap: 14px; }
        .admin-left .form-group-title,
        .admin-left .form-group-age,
        .admin-left .form-group-file { margin-bottom: 0; }
        .admin-left .form-input-field,
        .admin-left .form-file-field { width: 100%; }
        .admin-left .form-submit-btn { width: 100%; margin-top: 4px; }
        .admin-right { display: flex; flex-direction: column; gap: 14px; }
        .admin-right .tag-category { display: flex; flex-direction: column; gap: 10px; }
        .admin-right .tag-category strong { color: #1f2937; }
        .admin-right .tag-checkboxes { max-height: 360px; overflow-y: auto; padding-right: 4px; display: grid; gap: 12px; }
        .admin-side-title { margin: 0 0 6px 0; font-size: 14px; color: #475569; }
        .tags-row { display: grid; gap: 10px; }
        .tags-row strong { display: block; font-size: 14px; color: #334155; }
        .tags-row .tag-checkbox-label { width: 100%; }
        .tag-checkbox-label { padding: 8px 12px; }
        
        /* Рядок з основними полями */
        .form-row-inputs { display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; width: 100%; }
        .form-group-title { flex: 1 1 100%; }
        .form-group-age { flex: 1 1 40%; min-width: 140px; }
        .form-group-file { flex: 1 1 55%; min-width: 180px; }
        
        .form-label-text { display: block; font-weight: bold; margin-bottom: 8px; font-size: 14px; color: #475569; }
        
        /* Гарні закруглені поля введення */
        .form-input-field { 
            width: 100%; padding: 11px 15px; border: 2px solid #e2e8f0; border-radius: 16px; 
            box-sizing: border-box; font-family: inherit; transition: all 0.2s ease; background: #f8fafc;
        }
        .form-input-field:focus { border-color: #5ce1e6; background: #ffffff; outline: none; box-shadow: 0 0 0 3px rgba(92,225,230,0.15); }
        
        /* Стильне поле вибору файлу */
        .form-file-field { 
            width: 100%; padding: 8px 12px; border: 2px dashed #cbd5e1; border-radius: 16px; 
            box-sizing: border-box; background: #f8fafc; font-family: inherit; font-size: 14px; cursor: pointer; transition: all 0.2s;
        }
        .form-file-field:hover { border-color: #5ce1e6; background: #f0fdfa; }
        
        /* Кнопка Зберегти (велика та соковита) */
        .form-submit-btn {
            background-color: #ffde59; color: #333; border: none; padding: 12px 30px; 
            font-size: 16px; font-weight: bold; border-radius: 16px; cursor: pointer; 
            height: 44px; display: inline-flex; align-items: center; gap: 8px; 
            box-shadow: 0 4px 12px rgba(255,222,89,0.4); transition: all 0.2s ease; white-space: nowrap;
        }
        .form-submit-btn:hover { 
            background-color: #ff5757; color: white; 
            transform: translateY(-2px); box-shadow: 0 6px 15px rgba(255,87,87,0.4); 
        }

        /* Блок підкатегорій (ніжний пастельний фон) */
        .form-tags-section { background: #f4fbf7; padding: 20px; border-radius: 20px; border: 1px solid #dcfce7; }
        .form-tags-section > span { display: block; font-weight: bold; margin-bottom: 15px; font-size: 14px; color: #166534; }
        .form-tags-scroll { max-height: 180px; overflow-y: auto; padding-right: 5px; }
        
        .tags-row { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 16px; }
        .tags-row:last-child { margin-bottom: 0; }
        .tags-row-title { font-weight: bold; font-size: 13px; min-width: 110px; color: #1e3a1e; }
        
        /* СТИЛЬНІ ПЛАШКИ ЗАМІСТЬ СУХИХ ЧЕКБОКСІВ */
        .tag-checkbox-label {
            display: inline-flex; align-items: center; gap: 6px; background: #ffffff; 
            padding: 6px 14px; border-radius: 20px; border: 1px solid #cbd5e1; 
            font-size: 13px; font-weight: 600; color: #475569; cursor: pointer; 
            -webkit-user-select: none; user-select: none; transition: all 0.2s ease; box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        /* Ефект наведення на плашку */
        .tag-checkbox-label:hover { 
            border-color: #5ce1e6; background: #f0fdfa; color: #0891b2; transform: translateY(-1px);
        }
        /* Стиль плашки, коли всередині неї вибрано чекбокс (активний стан) */
        .tag-checkbox-label:has(input:checked) {
            background: #ffde59; 
            border-color: #ffde59; 
            color: #333;
            box-shadow: 0 3px 8px rgba(255,222,89,0.3);
        }
        /* Робимо сам рідний квадратик чекбокса акуратнішим */
        .tag-checkbox-label input { 
            accent-color: #ff5757; cursor: pointer; width: 14px; height: 14px; margin: 0; 
        }
       .site-footer {
  background-color: #ffffff; /* Біле тло, як і картки */
  padding: 40px 20px 20px 20px;
  margin-top: 50px;
  border-top: 1px solid #e0e0e0;
  font-family: 'Segoe UI', Roboto, sans-serif;
}

.footer-container {
  max-width: 1200px;
  margin: 0 auto;
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 30px;
}

.footer-column {
  flex: 1;
  min-width: 250px;
}

.footer-column h4 {
  color: #333;
  margin-bottom: 15px;
  font-size: 1.2rem;
}

.footer-column p {
  color: #666;
  font-size: 0.9rem;
  line-height: 1.5;
}

/* Стилі для посилань */
.footer-links {
  list-style: none;
  padding: 0;
  margin: 0;
}

.footer-links li {
  margin-bottom: 10px;
}

.footer-links a {
  color: #5ce1e6; /* Ваш фірмовий блакитний колір */
  text-decoration: none;
  font-size: 0.95rem;
  transition: color 0.2s;
}

.footer-links a:hover {
  color: #ff6b8b; /* Рожевий колір при наведенні, як кнопки донату */
}

/* Повідомлення підписки */
.footer-subscribe-message {
  margin: 10px 0 12px;
  padding: 10px 12px;
  border-radius: 10px;
  font-weight: 700;
  display: block;
  line-height: 1.4;
}

.footer-subscribe-message.success {
  background: #ecfdf3;
  color: #166534;
  border: 1px solid #86efac;
}

.footer-subscribe-message.error {
  background: #fef2f2;
  color: #991b1b;
  border: 1px solid #fecaca;
}

.footer-subscribe-message.info {
  background: #eff6ff;
  color: #1d4ed8;
  border: 1px solid #bfdbfe;
}

/* Компактна форма підписки */
.footer-subscribe-form {
  display: flex;
  margin-top: 15px;
  border: 2px solid #5ce1e6;
  border-radius: 25px;
  overflow: hidden;
  scroll-margin-top: 120px;
}

.footer-subscribe-form input {
  flex: 1;
  border: none;
  padding: 10px 15px;
  outline: none;
  font-size: 0.9rem;
}

.footer-subscribe-form button {
  background: #5ce1e6;
  color: #fff;
  border: none;
  padding: 10px 20px;
  font-weight: bold;
  cursor: pointer;
  transition: background 0.2s;
}

.footer-subscribe-form button:hover {
  background: #45c4c8;
}

/* Нижня плашка */
.footer-bottom {
  max-width: 1200px;
  margin: 30px auto 0 auto;
  padding-top: 20px;
  border-top: 1px solid #eeeeee;
  display: flex;
  justify-content: space-between;
  flex-wrap: wrap;
  color: #888;
  font-size: 0.85rem;
}

/* Адаптація під мобільні телефони */
@media (max-width: 768px) {
  .footer-container {
    flex-direction: column;
  }
  .footer-bottom {
    flex-direction: column;
    gap: 10px;
    text-align: center;
  }
}

    </style>
</head>
<body>
    <script>
        try {
            if (localStorage.getItem('artsashaTheme') === 'dark') {
                document.body.classList.add('dark-theme');
            }
        } catch (e) {
            console.warn('Theme init failed', e);
        }
    </script>

    <header>
        <div class="header-top">
            <form method="GET" class="search-form">
                <input type="text" name="search" autocomplete="off" placeholder="Пошук за словом..." value="<?php echo htmlspecialchars($search_query); ?>">
                <?php if ($filter_category): ?><input type="hidden" name="category" value="<?php echo htmlspecialchars($filter_category); ?>"><?php endif; ?>
                <?php if ($filter_subcategory): ?><input type="hidden" name="subcategory" value="<?php echo htmlspecialchars($filter_subcategory); ?>"><?php endif; ?>
                <button type="submit">Пошук</button>
            </form>
            <div class="logo-wrapper">
                <a href="https://atcraft.com.ua/ArtSasha/index.php" class="logo">
                    <span class="art">Art</span><span class="sasha">Sasha</span>
                    <span class="palette">
                        <img src="assets/palette.png" alt="palette" class="palette-img">
                    </span>
                </a>
                <p>Магічний світ творчості Олександри</p>
            </div>
            <div class="header-buttons">
				    
		<a href="donate.php" class="donate-btn">🍦 На морозиво</a>
		<!---->
                <?php if (!$is_admin): ?>
                    <button type="button" class="login-top-btn logout-btn" onclick="openLoginModal()">Увійти</button>
                <?php else: ?>
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>?action=logout" class="login-top-btn logout-btn">Вийти 🚪</a>
                <?php endif; ?>
                <button id="themeToggleBtn" type="button" class="theme-toggle-btn" onclick="toggleTheme()">🌌 Космічна тема</button>
            </div>
        </div>
    </header>

    <div id="loginModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" id="closeModalBtn">&times;</span>
            <h2 style="margin-top:0; color:#ff5757;">Увійти</h2>
            <?php if ($auth_error): ?>
                <p style="color:#e53e3e; margin-bottom:16px;">Невірний пароль. Спробуйте ще раз.</p>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label for="loginPassword">Пароль</label>
                    <input id="loginPassword" type="password" name="password" required placeholder="Введіть пароль" class="form-input-field">
                </div>
                <button type="submit" class="btn">Увійти</button>
            </form>
        </div>
    </div>

    <div class="categories-row">
        <?php foreach ($categories as $cat => $subs): ?>
            <div class="category-card">
                <div class="category-content">
                    <h3><?php echo htmlspecialchars($cat); ?></h3>
                    <p><?php
                        if ($cat === 'Малювання') {
                            echo 'Ручні роботи з фарб, олівців і пастелі.';
                        } elseif ($cat === 'Компʼютерна графіка') {
                            echo 'Цифрові ілюстрації';
                        } elseif ($cat === 'Моделювання') {
                            echo 'Поробки з картону, глини, пластиліну';
                        } else {
                            echo 'Усі роботи в одній галереї.';
                        }
                    ?></p>
                    <a class="category-button" href="?category=<?php echo rawurlencode($cat); ?>">Переглянути <?php echo htmlspecialchars($cat); ?></a>
                    <div class="subcategory-list" style="<?php echo $cat === 'Всі' ? 'display:block;' : ''; ?>">
                        <?php if ($cat === 'Всі'): ?>
                            <!-- Автоматична статистика для картки "Всі" -->
                            <div class="site-stats">
                                <div class="stat-row">
                                    <span>🎨 Малюнків:</span>
                                    <strong><?php echo (int)$stats['count_paint']; ?></strong>
                                </div>
                                <div class="stat-row">
                                    <span>🧩 Поробок:</span>
                                    <strong><?php echo (int)$stats['count_model']; ?></strong>
                                </div>
                                <div class="stat-row">
                                    <span>💻 Цифрових робіт:</span>
                                    <strong><?php echo (int)$stats['count_digital']; ?></strong>
                                </div>
                                <div class="stat-row stat-total">
                                    <span>✨ Разом у галереї:</span>
                                    <strong style="color: #ff5757;"><?php echo (int)$stats['total']; ?></strong>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Стандартні підкатегорії для інших карток -->
                            <?php foreach ($subs as $sub): ?>
                                <a class="subcategory-link" href="?category=<?php echo rawurlencode($cat); ?>&subcategory=<?php echo rawurlencode($sub); ?>"><?php echo htmlspecialchars($sub); ?></a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        <?php endforeach; ?>
    </div>

<!-- ========================================================================= -->
<!-- ГОЛОВНА ПЕРЕВІРКА: Форма додавання виводиться ТІЛЬКИ якщо Саша авторизована -->
<!-- ========================================================================= -->
<?php if ($is_admin): ?>

    <div class="admin-form-wrap">
        <h3>Привіт, Олександра! Додай свою роботу</h3>
        
        <form method="POST" enctype="multipart/form-data" class="admin-main-form">
            <div class="admin-form-grid">
                <div class="admin-left">
                    <div class="form-group-title">
                        <label class="form-label-text">Назва роботи:</label>
                        <input type="text" name="title" required placeholder="Наприклад: Золота осінь" class="form-input-field">
                    </div>
                    
                    <div class="form-group-age">
                        <label class="form-label-text">Вік малювання:</label>
                        <input type="text" name="age" required placeholder="Наприклад: 7 років" class="form-input-field">
                    </div>
                    
                    <div class="form-group-file">
                        <label class="form-label-text">Оберіть файл малюнка:</label>
                        <input type="file" name="image" required class="form-file-field">
                    </div>
                    
                    <button type="submit"  name="upload" class="form-submit-btn">Зберегти 💾</button>
                </div>

                <div class="admin-right">
                    <div class="tag-category">
                        <span class="admin-side-title">Категорії / теги</span>
                        <div class="tag-checkboxes">
                            <div class="tags-row">
                                <strong>Моделювання</strong>
                                <?php 
                                $model_tags = ['Тварини', 'Люди', 'Посуд', 'Кераміка', 'Повітряний пластилін', 'Поробки', 'Фантазія', 'Інше'];
                                foreach($model_tags as $t): ?>
                                    <label class="tag-checkbox-label">
                                        <input type="checkbox" name="tags[]" value="<?php echo $t; ?>"> <?php echo $t; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <div class="tags-row">
                                <strong>Малювання</strong>
                                <?php 
                                $draw_tags = ['Тварини', 'Рослини', 'Природа', 'Люди', 'Фантазія', 'Традиції та культура', 'Інше'];
                                foreach($draw_tags as $t): ?>
                                    <label class="tag-checkbox-label">
                                        <input type="checkbox" name="tags[]" value="<?php echo $t; ?>"> <?php echo $t; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <div class="tags-row">
                                <strong>Комп'ютерна графіка</strong>
                                <?php 
                                $crypto_tags = ['Тварини', 'Рослини', 'Природа', 'Люди', 'Фантазія', 'Традиції та культура', 'Інше'];
                                foreach($crypto_tags as $t): ?>
                                    <label class="tag-checkbox-label">
                                        <input type="checkbox" name="tags[]" value="<?php echo $t; ?>"> <?php echo $t; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

<?php endif; ?> 


    <!-- ГАЛЕРЕЯ -->
<h2 class="gallery-title" style="margin-top: 40px;">Галерея робіт</h2>
<div class="gallery" id="gallery">
    <?php if ($result && $result->num_rows > 0): ?>
        <?php while($row = $result->fetch_assoc()): ?>
            <div class="card">
                <!-- Тепер малюнок клікабельний і відкривається повністю -->
                <img src="<?php echo $row['image_data']; ?>" 
                     alt="Малюнок Олександри — <?php echo htmlspecialchars($row['title'], ENT_QUOTES); ?>" 
                     onclick="viewFullImage('<?php echo $row['image_data']; ?>', '<?php echo htmlspecialchars($row['title'], ENT_QUOTES); ?>')" 
                     style="cursor: pointer; transition: transform 0.2s ease;" 
                     onmouseover="this.style.transform='scale(1.02)'" 
                     onmouseout="this.style.transform='scale(1)'">
                     
                <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                <p>Вік: <?php echo htmlspecialchars($row['age']); ?></p>
                
                <?php if (!empty($row['tags'])): ?>
                    <!-- Ваш існуючий блок тегів залишається тут без змін -->
                    <div class="tags-block" style="margin: 12px 0 0 0; color: #333; font-size: 14px; text-align: left; display: flex; flex-wrap: wrap; align-items: center; gap: 8px;">
                        <strong class="tag-label" style="white-space: nowrap;">Теги:</strong>
                        <div style="display: flex; flex-wrap: wrap; gap: 6px; align-items: center;">
                            <?php foreach (explode(',', $row['tags']) as $tag): ?>
                                <a class="tag-item-btn" href="?search=<?php echo urlencode(trim($tag)); ?>">
                                    <?php echo htmlspecialchars(trim($tag)); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Новий оновлений рядок кнопок дій -->
                <div style="display: flex; gap: 10px; align-items: center; justify-content: center; margin-top: 15px; width: 100%;">
                    
                      <!-- Кнопка коментарів з лічильником -->
                    <button class="view-btn" onclick="openComments(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['title'], ENT_QUOTES); ?>')" style="margin-top: 0; width: 200px; background-color: #5ce1e6; color: #333; border-color: #5ce1e6; white-space: nowrap; box-sizing: border-box;">
                        Коментарі 💬 (<span class="btn-comment-count" data-btn-id="<?php echo $row['id']; ?>"><?php echo (int) $row['comment_count']; ?></span>)
                    </button>




                    <!-- Кнопка лайка (залишається вашою ідеальною робочою кнопкою) -->
                    <button class="like-btn" data-id="<?php echo $row['id']; ?>" onclick="sendLike(this, <?php echo $row['id']; ?>)" style="padding: 8px 14px; border-radius: 20px; height: 35px; box-sizing: border-box; display: inline-flex; align-items: center; justify-content: center; margin: 0;">
                        💖&nbsp;<span class="like-count"><?php echo (int)$row['likes']; ?></span>
                    </button>
                    
                </div>
                    <!-- КНОПКА ВИДАЛЕННЯ -->
                    <?php if ($is_admin): ?>
                        <form method="POST" style="margin-top: 10px;" onsubmit="return confirm('Ви впевнені, що хочете видалити малюнок «<?php echo htmlspecialchars($row['title'], ENT_QUOTES); ?>»?');">
                            <input type="hidden" name="delete_id" value="<?php echo $row['id']; ?>">
                            <button type="submit" class="delete-btn">Видалити ❌</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="grid-column: 1/-1; color: #777;">Тут поки немає жодного малюнка. Олександра, увійди зверху справа та завантаж перший шедевр! ✨</p>
        <?php endif; ?>
    </div>

    <?php if ($totalCount > $perPage): ?>
        <div class="pagination-wrap">
            <div class="page-info">
                Показано <?php echo min($totalCount, $offset + 1); ?> &ndash; <?php echo min($totalCount, $offset + $result->num_rows); ?> з <?php echo $totalCount; ?> робіт
            </div>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-btn">&lt; Назад</a>
                <?php endif; ?>
                <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    if ($startPage > 1) {
                        echo '<a class="page-btn" href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '">1</a>';
                        if ($startPage > 2) {
                            echo '<span class="page-ellipsis">...</span>';
                        }
                    }
                    for ($p = $startPage; $p <= $endPage; $p++) {
                        if ($p == $page) {
                            echo '<span class="page-btn page-active">' . $p . '</span>';
                        } else {
                            echo '<a class="page-btn" href="?' . http_build_query(array_merge($_GET, ['page' => $p])) . '">' . $p . '</a>';
                        }
                    }
                    if ($endPage < $totalPages) {
                        if ($endPage < $totalPages - 1) {
                            echo '<span class="page-ellipsis">...</span>';
                        }
                        echo '<a class="page-btn" href="?' . http_build_query(array_merge($_GET, ['page' => $totalPages])) . '">' . $totalPages . '</a>';
                    }
                ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-btn">Вперед &gt;</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    <!-- Модальне вікно перегляду зображень (Слайдер) -->
    <div id="imageModal" class="modal" style="display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.85); align-items: center; justify-content: center; -webkit-backdrop-filter: blur(5px); backdrop-filter: blur(5px);">
        
        <!-- Великий хрестик закриття з новим класом -->
        <span class="close-slider" onclick="closeFullImage()">&times;</span>
        
        <!-- Стрілка Вліво (без фіксованого style) -->
        <button class="slider-arrow arrow-left" onclick="changeImage(-1)">&#10094;</button>
        
        <!-- Контейнер для фото та підпису -->
        <div style="max-width: 85%; max-height: 85%; display: flex; flex-direction: column; align-items: center; justify-content: center; position: relative;">
            <img id="modalImage" src="" alt="" style="max-width: 100%; max-height: 75vh; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); object-fit: contain;">
            <h3 id="modalImageTitle" style="color: white; margin-top: 15px; font-family: inherit; text-shadow: 0 2px 4px rgba(0,0,0,0.6);"></h3>
        </div>
        
        <!-- Стрілка Вправо (без фіксованого style) -->
        <button class="slider-arrow arrow-right" onclick="changeImage(1)">&#10095;</button>
    </div>

    <div id="commentsModal" class="comments-modal">
        <div class="comments-content">
            <span class="close-comments" id="closeCommentsBtn">&times;</span>
            <h2 id="commentsTitle" style="margin-top:0; color:#5ce1e6;">Коментарі</h2>
            <div id="commentsError" style="color:#e53e3e; display:none; margin-bottom:10px;"></div>
            <div id="commentsList" class="comments-list"></div>
            <form id="commentsForm" class="comment-form">
                <input type="hidden" id="commentsDrawingId" name="drawing_id" value="">
                <input type="text" id="commentName" name="name" autocomplete="name" placeholder="Ваше ім'я" required>
                <textarea id="commentText" name="text" autocomplete="off" placeholder="Ваш коментар" required></textarea>
                <button type="submit" class="btn">Додати коментар</button>
            </form>
        </div>
    </div>

    <footer class="site-footer">
  <div class="footer-container">
    
    <!-- Колонка 1: Про нас -->
    <div class="footer-column">
      <h4>Art Sasha ✨</h4>
      <p>Творча галерея малюнків, поробок та комп'ютерної графіки маленької художниці.</p>
    </div>

    <!-- Колонка 2: Корисні посилання -->
    <div class="footer-column">
      <h4>Корисні посилання</h4>
      <ul class="footer-links">
        <li><a href="about.php">Про авторку</a></li>
        <li><a href="index.php">Всі роботи</a></li>
        <li><a href="donate.php">Підтримати (На морозиво)</a></li>
        <li><a href="mailto:galasirinska@gmail.com">Контакти</a></li>
      </ul>
    </div>

    <!-- Колонка 3: Блок підписки -->
    <div class="footer-column">
      <h4>Підписка на оновлення</h4>
      <p>Отримуйте сповіщення про нові шедеври!</p>
      <?php if ($subscribe_message !== ''): ?>
        <p class="footer-subscribe-message <?php echo $subscribe_status; ?>"><?php echo htmlspecialchars($subscribe_message); ?></p>
      <?php endif; ?>
            <form method="POST" class="footer-subscribe-form" id="subscribe-form">
                <input type="email" name="subscribe_email" autocomplete="email" placeholder="Ваш e-mail..." required>
                <button type="submit" name="subscribe_submit">Підписатись</button>
            </form>
    </div>

  </div>


        <div class="footer-bottom">
            <span>© <?php echo date('Y'); ?> ArtSasha. Всі права захищені.</span>
            <span>Створено з любов'ю для маленької художниці 💖</span>
        </div>
    </footer>

    <button class="btn-to-top" id="scrollToTopBtn" title="Вгору"></button>

    <script>
        window.openLoginModal = function() {
            const loginModal = document.getElementById('loginModal');
            if (loginModal) {
                loginModal.style.display = 'flex';
            }
        };

        window.closeLoginModal = function() {
            const loginModal = document.getElementById('loginModal');
            if (loginModal) {
                loginModal.style.display = 'none';
            }
        };

        const closeLoginBtn = document.getElementById('closeModalBtn');
        if (closeLoginBtn) {
            closeLoginBtn.onclick = window.closeLoginModal;
        }
        const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
        
// СИСТЕМА ЧАРІВНОГО СЛАЙДЕРА ДЛЯ МАЛЮНКІВ
let currentImageIndex = 0;
let allGalleryImages = [];

function viewFullImage(clickedSrc, clickedTitle) {
    allGalleryImages = [];
    
    // Збираємо актуальний список усіх картинок, які зараз є на сторінці
    const imgElements = document.querySelectorAll(".card img");
    imgElements.forEach((img, index) => {
        allGalleryImages.push({
            src: img.getAttribute("src"),
            title: img.getAttribute("alt").replace("Малюнок Олександри — ", "")
        });
        // Запам'ятовуємо індекс картинки, на яку клікнули
        if (img.getAttribute("src") === clickedSrc) {
            currentImageIndex = index;
        }
    });

    // Показуємо модальне вікно та оновлюємо вміст
    document.getElementById("imageModal").style.display = "flex";
    updateModalImage();
}

function closeFullImage() {
    document.getElementById("imageModal").style.display = "none";
}

function updateModalImage() {
    const currentData = allGalleryImages[currentImageIndex];
    if (currentData) {
        document.getElementById("modalImage").src = currentData.src;
        document.getElementById("modalImageTitle").textContent = currentData.title;
    }
}

function changeImage(direction) {
    if (allGalleryImages.length <= 1) return;
    
    currentImageIndex += direction;
    
    if (currentImageIndex >= allGalleryImages.length) {
        currentImageIndex = 0;
    } else if (currentImageIndex < 0) {
        currentImageIndex = allGalleryImages.length - 1;
    }
    
    updateModalImage();
}

// Керування клавіатурой та закриття при кліку на фон
document.addEventListener("keydown", (e) => {
    const imgModal = document.getElementById("imageModal");
    if (imgModal && imgModal.style.display === "flex") {
        if (e.key === "ArrowRight") changeImage(1);
        if (e.key === "ArrowLeft") changeImage(-1);
        if (e.key === "Escape") closeFullImage();
    }
});

window.onclick = function(event) {
    const imgModal = document.getElementById("imageModal");
    const loginModal = document.getElementById('loginModal');
    const commentsModal = document.getElementById('commentsModal');
    
    if (event.target === imgModal) {
        closeFullImage();
    }
    if (event.target === loginModal) {
        loginModal.style.display = 'none';
    }
    if (event.target === commentsModal) {
        commentsModal.style.display = 'none';
    }
}

        window.openComments = function(drawingId, drawingTitle) {
            const commentsModal = document.getElementById('commentsModal');
            const commentsTitle = document.getElementById('commentsTitle');
            const drawingIdInput = document.getElementById('commentsDrawingId');
            const commentsError = document.getElementById('commentsError');

            if (!commentsModal || !commentsTitle || !drawingIdInput) {
                return;
            }

            currentCommentsDrawingId = drawingId;
            drawingIdInput.value = drawingId;
            commentsTitle.textContent = drawingTitle;
            if (commentsError) {
                commentsError.textContent = '';
                commentsError.style.display = 'none';
            }

            loadComments(drawingId, true);
        }

        function loadComments(drawingId, showModal = false) {
            const commentsList = document.getElementById('commentsList');
            const commentsModal = document.getElementById('commentsModal');
            const commentsError = document.getElementById('commentsError');

            if (!commentsList) {
                return;
            }

            commentsList.innerHTML = '<p style="color: #64748b;">Завантаження коментарів...</p>';
            fetch('comments_ajax.php?action=get&drawing_id=' + encodeURIComponent(drawingId))
                .then((response) => response.json())
                .then((data) => {
                    if (!Array.isArray(data)) {
                        throw new Error('Невірний формат відповіді');
                    }

                    if (data.length === 0) {
                        commentsList.innerHTML = '<p style="color: #64748b; margin: 0;">Поки нема коментарів. Станьте першим!</p>';
                    } else {
                        commentsList.innerHTML = data.map(function(comment) {
                            return '<div class="comment-item"><div class="comment-meta"><span class="comment-name">' +
                                escapeHtml(comment.name) + '</span><span>' + escapeHtml(comment.date) + '</span></div>' +
                                '<div class="comment-text">' + escapeHtml(comment.text) + '</div>' +
                                (isAdmin ? '<button class="comment-delete-btn" aria-label="Видалити коментар" onclick="deleteComment(' + comment.id + ', ' + drawingId + ')">✖</button>' : '') +
                                '</div>';
                        }).join('');
                    }
                    updateCommentCountBadge(drawingId, data.length);
                    if (showModal && commentsModal) {
                        commentsModal.style.display = 'flex';
                    }
                })
                .catch(function(error) {
                    if (commentsError) {
                        commentsError.textContent = 'Помилка завантаження коментарів.';
                        commentsError.style.display = 'block';
                    }
                    commentsList.innerHTML = '<p style="color: #e53e3e; margin: 0;">Не вдалося завантажити коментарі.</p>';
                    console.error(error);
                });
        }

        function deleteComment(commentId, drawingId) {
            if (!confirm('Ви впевнені, що хочете видалити цей коментар?')) {
                return;
            }
            const formData = new FormData();
            formData.append('comment_id', commentId);

            fetch('comments_ajax.php?action=delete', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(result) {
                if (result.success) {
                    loadComments(drawingId, true);
                } else {
                    alert(result.error || 'Не вдалося видалити коментар.');
                }
            })
            .catch(function(error) {
                console.error('Delete comment error:', error);
                alert('Сталася помилка при видаленні коментаря.');
            });
        }

        function submitComment(event) {
            event.preventDefault();
            const nameField = document.getElementById('commentName');
            const textField = document.getElementById('commentText');
            const drawingIdInput = document.getElementById('commentsDrawingId');
            const commentsError = document.getElementById('commentsError');

            if (!nameField || !textField || !drawingIdInput) {
                return;
            }

            const name = nameField.value.trim();
            const text = textField.value.trim();
            const drawingId = drawingIdInput.value;

            if (!name || !text) {
                if (commentsError) {
                    commentsError.textContent = 'Будь ласка, заповніть усі поля.';
                    commentsError.style.display = 'block';
                }
                return;
            }

            const formData = new FormData();
            formData.append('drawing_id', drawingId);
            formData.append('name', name);
            formData.append('text', text);

            fetch('comments_ajax.php?action=add', {
                method: 'POST',
                body: formData,
            })
                .then(function(response) {
                    return response.json();
                })
                .then(function(result) {
                    if (result.success) {
                        nameField.value = '';
                        textField.value = '';
                        if (commentsError) {
                            commentsError.textContent = '';
                            commentsError.style.display = 'none';
                        }
                        loadComments(drawingId, true);
                    } else {
                        if (commentsError) {
                            commentsError.textContent = result.error || 'Не вдалося додати коментар.';
                            commentsError.style.display = 'block';
                        }
                    }
                })
                .catch(function(error) {
                    if (commentsError) {
                        commentsError.textContent = 'Помилка при збереженні коментаря.';
                        commentsError.style.display = 'block';
                    }
                    console.error(error);
                });
        }

        function updateCommentCountBadge(drawingId, count) {
            var badges = document.querySelectorAll('.btn-comment-count[data-btn-id="' + drawingId + '"]');
            badges.forEach(function(badge) {
                badge.textContent = count;
            });
        }

        function escapeHtml(text) {
            return String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        const commentsForm = document.getElementById('commentsForm');
        if (commentsForm) {
            commentsForm.addEventListener('submit', submitComment);
        }

        const closeCommentsBtn = document.getElementById('closeCommentsBtn');
        if (closeCommentsBtn) {
            closeCommentsBtn.addEventListener('click', function() {
                const commentsModal = document.getElementById('commentsModal');
                if (commentsModal) {
                    commentsModal.style.display = 'none';
                }
            });
        }

        document.addEventListener('keydown', function(e) {
            const commentsModal = document.getElementById('commentsModal');
            if (commentsModal && commentsModal.style.display === 'flex' && e.key === 'Escape') {
                commentsModal.style.display = 'none';
            }
        });

        function getLikedDrawings() {
            try {
                return JSON.parse(localStorage.getItem('likedDrawings') || '[]');
            } catch (e) {
                return [];
            }
        }

        function setLikedDrawing(drawingId) {
            const liked = getLikedDrawings();
            if (liked.indexOf(drawingId) === -1) {
                liked.push(drawingId);
                localStorage.setItem('likedDrawings', JSON.stringify(liked));
            }
        }

        function isDrawingLiked(drawingId) {
            return getLikedDrawings().indexOf(drawingId) !== -1;
        }

        function initLikedButtons() {
            document.querySelectorAll('.like-btn').forEach(function(button) {
                const drawingId = parseInt(button.getAttribute('data-id'), 10);
                if (!drawingId || isNaN(drawingId)) {
                    return;
                }
                if (isDrawingLiked(drawingId)) {
                    button.classList.add('liked');
                    button.disabled = true;
                }
            });
        }

        function sendLike(button, drawingId) {
            if (!button || isDrawingLiked(drawingId)) {
                return;
            }
            button.disabled = true;
            const likeCount = button.querySelector('.like-count');

            fetch('like.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'id=' + encodeURIComponent(drawingId) + '&action=like'
            })
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    if (data && data.success) {
                        if (likeCount) {
                            likeCount.textContent = data.likes;
                        }
                        button.classList.add('liked');
                        setLikedDrawing(drawingId);
                    } else {
                        button.disabled = false;
                    }
                })
                .catch(function(error) {
                    console.error('Like error:', error);
                    button.disabled = false;
                });
        }

        function setThemeButtonText() {
            const btn = document.getElementById('themeToggleBtn');
            if (!btn) return;
            if (document.body.classList.contains('dark-theme')) {
                btn.textContent = '☀️ Денна тема';
            } else {
                btn.textContent = '🌌 Космічна тема';
            }
        }

        function toggleTheme() {
            document.body.classList.toggle('dark-theme');
            const isDark = document.body.classList.contains('dark-theme');
            localStorage.setItem('artsashaTheme', isDark ? 'dark' : 'light');
            setThemeButtonText();
        }

        function initTheme() {
            const theme = localStorage.getItem('artsashaTheme');
            if (theme === 'dark') {
                document.body.classList.add('dark-theme');
            }
            setThemeButtonText();
        }

        document.addEventListener('DOMContentLoaded', function() {
            initLikedButtons();
            initTheme();

            const scrollBtn = document.getElementById('scrollToTopBtn');
            if (scrollBtn) {
                scrollBtn.addEventListener('click', function() {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            }

            window.addEventListener('scroll', function() {
                if (!scrollBtn) return;
                if (window.pageYOffset > 220) {
                    scrollBtn.style.opacity = '1';
                    scrollBtn.style.visibility = 'visible';
                } else {
                    scrollBtn.style.opacity = '0';
                    scrollBtn.style.visibility = 'hidden';
                }
            });
        });
</script>
</body>
</html>


