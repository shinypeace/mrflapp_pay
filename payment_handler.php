<?php
define('SECRET_KEY', 'a0646de9a0646de9a0646de96ca354d50daa064a0646de9c873c70dd4323686870203fb');

// --- Функция для проверки подписи запроса от VK ---
function check_signature($params, $secret_key) {
    // Получаем подпись из запроса
    $signature = $params['sig'];
    unset($params['sig']);

    // Сортируем параметры по ключу
    ksort($params);

    // Формируем строку для хеширования
    $str = '';
    foreach ($params as $key => $value) {
        $str .= $key . '=' . $value;
    }

    // Создаем MD5 хеш и сравниваем с подписью
    return md5($str . $secret_key) === $signature;
}

// --- Основная логика обработчика ---

// Получаем данные из POST-запроса от VK
$request_params = $_POST;

// Проверяем подпись. Если она неверна, прерываем выполнение.
if (!check_signature($request_params, SECRET_KEY)) {
    // Отвечаем VK, что подпись неверна
    header('Content-Type: application/json');
    echo json_encode([
        'error' => [
            'error_code' => 10,
            'error_msg' => 'Несовпадение подписи. Запрос отклонен.',
            'critical' => true
        ]
    ]);
    exit();
}

// --- Обработка различных типов уведомлений ---

$notification_type = $request_params['notification_type'];
$user_id = $request_params['user_id'];
$order_id = $request_params['order_id'];

$response = [];

if ($notification_type == 'order_status_change') {
    $status = $request_params['status'];

    if ($status == 'chargeable') {
        // --- ГЛАВНАЯ ЛОГИКА: ПЛАТЕЖ ПРОШЕЛ УСПЕШНО ---
        // Здесь вы должны начислить товар пользователю в вашей базе данных.

        $item_id = $request_params['item']; // Например, 'coins_100'
        
        // --- ПРИМЕР: Работа с файлом как с простой базой данных ---
        // В реальном проекте здесь будет запрос к MySQL, PostgreSQL или другой БД.
        
        $storage_file = "user_data/{$user_id}.json"; // Пример пути
        
        // Создаем папку, если ее нет
        if (!is_dir('user_data')) {
            mkdir('user_data');
        }

        // Загружаем текущие данные пользователя
        $user_data = [];
        if (file_exists($storage_file)) {
            $user_data = json_decode(file_get_contents($storage_file), true);
        }

        // Определяем количество монет для начисления
        $coins_to_add = 0;
        switch ($item_id) {
            case 'coins_100': $coins_to_add = 100; break;
            case 'coins_300': $coins_to_add = 300; break;
            case 'coins_500': $coins_to_add = 500; break;
            case 'coins_1000': $coins_to_add = 1000; break;
        }

        if ($coins_to_add > 0) {
            // Начисляем монеты
            if (!isset($user_data['totalCoins'])) {
                $user_data['totalCoins'] = 0;
            }
            $user_data['totalCoins'] += $coins_to_add;

            // Сохраняем обновленные данные
            file_put_contents($storage_file, json_encode($user_data));
            
            // Формируем успешный ответ для VK
            $response = ['order_id' => $order_id, 'app_order_id' => time()]; // app_order_id - ваш внутренний id заказа

        } else {
            // Если товар не найден
            $response = ['error' => ['error_code' => 20, 'error_msg' => 'Товар не найден.']];
        }
    } else {
        // Статус заказа изменился на другой (например, отменен)
        // Здесь можно добавить логику для других статусов, если нужно
        $response = ['order_id' => $order_id];
    }
} else {
    // Другой тип уведомления, который мы не обрабатываем
    $response = ['error' => ['error_code' => 100, 'error_msg' => 'Неверный тип уведомления.']];
}

// Отправляем финальный JSON-ответ серверу VK
header('Content-Type: application/json');
echo json_encode(['response' => $response]);

?>

