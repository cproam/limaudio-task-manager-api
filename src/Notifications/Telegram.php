<?php

namespace App\Notifications;

use App\Support\Env;

class Telegram
{
    public static function send(string $message): void
    {
        $token = Env::get('TELEGRAM_BOT_TOKEN');
        $chatId = Env::get('TELEGRAM_CHAT_ID');
        if (!$token || !$chatId) return; // silently skip if not configured
        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        $payload = http_build_query([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ]);
        // Use file_get_contents to avoid curl dependency
        @file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 5,
            ],
        ]));
    }

    public static function sendTo(string $chatId, string $message): void
    {
        $token = Env::get('TELEGRAM_BOT_TOKEN');
        if (!$token || !$chatId) return;
        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        $payload = http_build_query([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ]);
        @file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 5,
            ],
        ]));
    }
}
