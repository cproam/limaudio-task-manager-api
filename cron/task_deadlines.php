<?php

// CLI cron скрипт: проверяет задачи и отправляет Telegram уведомления при 30%, 10%, 0% оставшегося времени

// Загрузка автозагрузки
$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';
if (file_exists($autoload)) require $autoload;
else {
    spl_autoload_register(function ($class) use ($root) {
        $prefix = 'App\\';
        $baseDir = $root . '/src/';
        if (str_starts_with($class, $prefix)) {
            $relative = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
            if (file_exists($file)) require $file;
        }
    });
}

use App\Database\DB;
use App\Support\Env;
use App\Services\TaskNotificationService;

Env::load($root . '/.env');
DB::migrate();

$pdo = DB::conn();

// Получить задачи с датами выполнения
$stmt = $pdo->query("SELECT t.id, t.title, t.created_at, t.due_at, t.notified_30, t.notified_10, t.notified_0, u.telegram_id AS assignee_tg FROM tasks t LEFT JOIN users u ON u.id=t.assigned_user_id WHERE t.due_at IS NOT NULL");
$tasks = $stmt->fetchAll();
$now = time();

foreach ($tasks as $t) {
    $created = strtotime($t['created_at']);
    $due = strtotime($t['due_at']);
    if (!$created || !$due || $due <= $created) continue; // пропустить недействительные
    $total = $due - $created;
    $left = $due - $now;
    $pctLeft = $left / $total; // 0..1

    $id = (int)$t['id'];
    $title = $t['title'];
    $leftSeconds = $left;
    $days = floor(abs($leftSeconds) / (24 * 3600));
    $hours = floor((abs($leftSeconds) % (24 * 3600)) / 3600);
    $minutes = floor((abs($leftSeconds) % 3600) / 60);
    
    if ($days > 0) {
        $timeLeft = $days . ' дн., ' . $hours . ' ч.';
    } elseif ($hours > 0) {
        $timeLeft = $hours . ' ч., ' . $minutes . ' мин.';
    } else {
        $timeLeft = $minutes . ' мин.';
    }
    
    if ($left < 0) {
        $timeLeft = 'просрочено';
    }

    // 30%
    if ($pctLeft <= 0.30 && !$t['notified_30'] && $left > 0) {
        TaskNotificationService::notifyDeadline($id, $title, $timeLeft, $t['assignee_tg']);
        $pdo->prepare('UPDATE tasks SET notified_30=1 WHERE id=?')->execute([$id]);
    }
    // 10%
    if ($pctLeft <= 0.10 && !$t['notified_10'] && $left > 0) {
        TaskNotificationService::notifyDeadline($id, $title, $timeLeft, $t['assignee_tg']);
        $pdo->prepare('UPDATE tasks SET notified_10=1 WHERE id=?')->execute([$id]);
    }
    // 0% или просрочено
    if ($left <= 0 && !$t['notified_0']) {
        TaskNotificationService::notifyDeadline($id, $title, $timeLeft, $t['assignee_tg']);
        $pdo->prepare('UPDATE tasks SET notified_0=1, status=?, updated_at=? WHERE id=?')->execute(['Задача просрочена', gmdate('c'), $id]);
    }
}

echo "Готово\n";
