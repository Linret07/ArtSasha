const express = require('express');
const mongoose = require('mongoose');
const cors = require('cors');

const app = express();
app.use(cors());
app.use(express.json({ limit: '10mb' })); // Дозволяємо великі файли (малюнки)

// 1. Підключення до бази даних MongoDB (локальна або в хмарі MongoDB Atlas)
mongoose.connect('mongodb://localhost:27017/artsasha')
    .then(() => console.log('Успішно підключено до MongoDB'))
    .catch(err => console.error('Помилка підключення до БД:', err));

// 2. Створення схеми (моделі) для малюнка в базі даних
const DrawingSchema = new mongoose.Schema({
    title: String,
    age: String,
    image: String // Малюнок зберігається у форматі Base64 (текстовий рядок)
});
const Drawing = mongoose.model('Drawing', DrawingSchema);

// 3. Маршрут для отримання всіх малюнків з бази даних
app.get('/api/drawings', async (req, res) => {
    try {
        const drawings = await Drawing.find().sort({ _id: -1 }); // Нові малюнки спочатку
        res.json(drawings);
    } catch (err) {
        res.status(500).json({ message: err.message });
    }
});

// 4. Маршрут для додавання нового малюнка в базу даних
app.post('/api/drawings', async (req, res) => {
    const newDrawing = new Drawing({
        title: req.body.title,
        age: req.body.age,
        image: req.body.image
    });
    try {
        const savedDrawing = await newDrawing.save();
        res.status(201).json(savedDrawing);
    } catch (err) {
        res.status(400).json({ message: err.message });
    }
});

// Запуск сервера на 5000 порту
app.listen(5000, () => console.log('Сервер працює на порту 5000'));
