<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ArtSasha — Галерея з базою даних</title>
    <style>
        body { font-family: 'Comic Sans MS', cursive, sans-serif; background-color: #f0fdf4; color: #333; margin: 0; padding: 20px; text-align: center; }
        header { background-color: #ffffff; padding: 30px 20px; border-radius: 20px; box-shadow: 0 8px 16px rgba(0,0,0,0.05); max-width: 800px; margin: 0 auto; }
        .logo { font-size: 48px; font-weight: bold; display: inline-block; }
        .logo .art { color: #ff5757; text-shadow: 2px 2px 0 #ffde59; }
        .logo .sasha { color: #5ce1e6; text-shadow: 2px 2px 0 #ff5757; margin-left: -2px; }

        /* Секція форми */
        .form-container { background: white; max-width: 400px; margin: 30px auto; padding: 20px; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); text-align: left; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input { width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 8px; box-sizing: border-box; font-family: inherit; }
        .submit-btn { width: 100%; background: #ffde59; border: none; padding: 12px; font-size: 16px; font-weight: bold; border-radius: 8px; cursor: pointer; font-family: inherit; transition: 0.2s; }
        .submit-btn:hover { background: #ff5757; color: white; }

        /* Галерея */
        .gallery { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px; margin-top: 40px; }
        .card { background: white; padding: 15px; border-radius: 16px; box-shadow: 0 6px 12px rgba(0,0,0,0.08); }
        .card img { width: 100%; height: 220px; object-fit: cover; border-radius: 10px; }
        .card h3 { margin: 15px 0 5px 0; color: #333; }
        .card p { margin: 0; color: #ff5757; font-weight: bold; }
    </style>
</head>
<body>

<header>
    <div class="logo"><span class="art">Art</span><span class="sasha">Sasha</span>🎨</div>
    <p>Малюнки завантажуються прямо з бази даних</p>
</header>

<!-- Форма додавання -->
<div class="form-container">
    <h3 style="margin-top:0; text-align:center; color:#ff5757;">Додати новий малюнок</h3>
    <form id="artForm">
        <div class="form-group">
            <label>Назва роботи:</label>
            <input type="text" id="title" required>
        </div>
        <div class="form-group">
            <label>Вік автора:</label>
            <input type="text" id="age" required>
        </div>
        <div class="form-group">
            <label>Файл малюнка:</label>
            <input type="file" id="image" accept="image/*" required>
        </div>
        <button type="submit" class="submit-btn">Зберегти в базу даних 💾</button>
    </form>
</div>

<!-- Динамічна галерея -->
<div class="gallery" id="galleryContainer"></div>

<script>
    const API_URL = 'http://localhost:5000/api/drawings';
    const gallery = document.getElementById('galleryContainer');
    const form = document.getElementById('artForm');

    // 1. ФУНКЦІЯ: Завантаження малюнків з бази даних при відкритті сайту
    async function loadDrawings() {
        try {
            const response = await fetch(API_URL);
            const drawings = await response.json();
            gallery.innerHTML = ''; // Очищуємо старі дані

            drawings.forEach(art => {
                const card = document.createElement('div');
                card.className = 'card';
                card.innerHTML = `
                        <img src="${art.image}" alt="${art.title}">
                        <h3>${art.title}</h3>
                        <p>Вік: ${art.age}</p>
                    `;
                gallery.appendChild(card);
            });
        } catch (err) {
            console.error('Не вдалося завантажити малюнки:', err);
        }
    }

    // 2. ФУНКЦІЯ: Перетворення картинки в рядок Base64
    function toBase64(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = () => resolve(reader.result);
            reader.onerror = error => reject(error);
        });
    }

    // 3. ОБРОБНИК: Відправка форми на сервер в БД
    form.onsubmit = async function(e) {
        e.preventDefault();

        const title = document.getElementById('title').value;
        const age = document.getElementById('age').value;
        const fileInput = document.getElementById('image').files[0];

        try {
            const base64Image = await toBase64(fileInput);

            // Відправляємо POST-запит на Node.js сервер
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ title, age, image: base64Image })
            });

            if (response.ok) {
                form.reset();
                loadDrawings(); // Перезавантажуємо галерею з новими даними з БД
            }
        } catch (err) {
            alert('Помилка при збереженні малюнка');
            console.error(err);
        }
    };

    // Запуск завантаження при старті сторінки
    loadDrawings();
</script>
</body>
</html>
