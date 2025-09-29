<?php

namespace App\Services;

use App\Notifications\Telegram;
use App\Database\DB;

class TaskNotificationService
{
    /**
     * Уведомление о создании задачи
     */
    public static function notifyTaskCreated(array $task, string $assigneeName = '', ?string $assigneeTg = null): void
    {
        $title = htmlspecialchars($task['title'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $descRaw = (string)($task['description'] ?? '');
        $desc = $descRaw !== '' ? htmlspecialchars($descRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '';
        $assigneeLine = $assigneeName !== '' ? "\nОтветственный: {$assigneeName}" : '';
        $descLine = $desc !== '' ? "\nОписание: {$desc}" : '';
        $msg = "🆕 Новая задача: <b>{$title}</b>{$descLine}{$assigneeLine}\nСтатус: {$task['status']}\nID: {$task['id']}";
        Telegram::send($msg);
        if ($assigneeTg) {
            Telegram::sendTo($assigneeTg, $msg);
        }
    }

    /**
     * Уведомление о новом комментарии
     */
    public static function notifyCommentAdded(int $taskId, string $commentText, ?string $assigneeTg = null): void
    {
        $msg = "💬 Новый комментарий к задаче #{$taskId}:\n" . $commentText;
        Telegram::send($msg);
        if ($assigneeTg) {
            Telegram::sendTo($assigneeTg, $msg);
        }
    }

    /**
     * Уведомление об изменении статуса
     */
    public static function notifyStatusChanged(int $taskId, string $status, string $title, string $description = '', string $assigneeName = '', ?string $assigneeTg = null): void
    {
        $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $descEsc = $description !== '' ? htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '';
        $assigneeLine = $assigneeName !== '' ? "\nОтветственный: {$assigneeName}" : '';
        $descLine = $descEsc !== '' ? "\nОписание: {$descEsc}" : '';
        $msg = "🔄 Обновление статуса задачи #{$taskId}: <b>{$status}</b>\n<b>{$titleEsc}</b>{$descLine}{$assigneeLine}";
        Telegram::send($msg);
        if ($assigneeTg) {
            Telegram::sendTo($assigneeTg, $msg);
        }
    }

    /**
     * Уведомление об изменении срочности
     */
    public static function notifyUrgencyChanged(int $taskId, string $urgency, string $title, string $description = ''): void
    {
        $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $descEsc = $description !== '' ? htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '';
        $descLine = $descEsc !== '' ? "\nОписание: {$descEsc}" : '';
        $msg = "🔄 Обновление срочности задачи #{$taskId}: <b>{$urgency}</b>\n<b>{$titleEsc}</b>{$descLine}";
        Telegram::send($msg);
    }

    /**
     * Уведомление о дедлайне
     */
    public static function notifyDeadline(int $taskId, string $title, string $timeLeft, ?string $assigneeTg = null): void
    {
        if (str_contains($timeLeft, 'просрочено')) {
            $msg = "⛔ Задача #{$taskId} ({$title}) — {$timeLeft}";
        } else {
            $msg = "⚠️ Задача #{$taskId} ({$title}) — дедлайн через {$timeLeft}";
        }
        if (str_contains($timeLeft, 'Примите задачу')) {
            $msg = "⚠️ {$timeLeft} #{$taskId}";
        }
        Telegram::send($msg);
        if ($assigneeTg) {
            Telegram::sendTo($assigneeTg, $msg);
        }
    }

    /**
     * Получить telegram_id ответственного по taskId
     */
    public static function getAssigneeTg(int $taskId): ?string
    {
        $pdo = DB::conn();
        $st = $pdo->prepare('SELECT u.telegram_id FROM tasks t LEFT JOIN users u ON u.id=t.assigned_user_id WHERE t.id=?');
        $st->execute([$taskId]);
        return $st->fetchColumn() ?: null;
    }
}
