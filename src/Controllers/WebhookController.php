<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Notifications\Telegram;
use App\Routing\Route;

class WebhookController
{
    // Telegram webhook endpoint
    #[Route('POST', '/webhook/telegram')]
    public function telegram(Request $req)
    {
        $update = $req->body;
        if (!is_array($update)) {
            return Response::json(['ok' => true]);
        }

        $message = $update['message'] ?? $update['edited_message'] ?? null;
        if (!$message) {
            return Response::json(['ok' => true]);
        }
        $chat = $message['chat'] ?? [];
        $chatId = $chat['id'] ?? null;
        $text = trim((string)($message['text'] ?? ''));

        if ($chatId && $text !== '') {
            // Normalize command (may come as /myid or /myid@botname)
            $cmd = strtolower(preg_split('/\s+/', $text)[0] ?? '');
            if ($cmd === '/myid' || str_starts_with($cmd, '/myid@')) {
                $msg = "Ваш id, передайте его администратору: " . $chatId;
                Telegram::sendTo((string)$chatId, $msg);
            }
        }

        // Always 200 OK for Telegram
        return Response::json(['ok' => true]);
    }
}
