<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\TaskRepository;
use App\Notifications\Telegram;

class TaskFeatureController
{
    private TaskRepository $tasks;

    public function __construct()
    {
        $this->tasks = new TaskRepository();
    }

    public function create(Request $req)
    {
        $claims = $GLOBALS['auth_user'] ?? null;
        $userId = isset($claims['sub']) ? (int)$claims['sub'] : null;
        $data = $req->body;

        // Validate
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') return Response::json(['error' => 'title is required'], 422);

        $payload = [
            'title' => $title,
            'description' => (string)($data['description'] ?? ''),
            'direction_id' => isset($data['direction_id']) ? (int)$data['direction_id'] : null,
            'due_at' => isset($data['due_at']) ? (string)$data['due_at'] : null,
            'assigned_user_id' => isset($data['assigned_user_id']) ? (int)$data['assigned_user_id'] : null,
            'links' => is_array($data['links'] ?? null) ? $data['links'] : [],
            'files' => is_array($data['files'] ?? null) ? $data['files'] : [],
            'created_by' => $userId,
        ];

        $task = $this->tasks->create($payload);

        // Notify Telegram (personal to assignee if possible)
        $title = htmlspecialchars($task['title'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $msg = "🆕 Новая задача: <b>{$title}</b>\nСтатус: {$task['status']}\nID: {$task['id']}";
        $assigneeId = $payload['assigned_user_id'] ?? null;
        if ($assigneeId) {
            // Lookup telegram_id of assignee
            $pdo = \App\Database\DB::conn();
            $st = $pdo->prepare('SELECT telegram_id FROM users WHERE id=?');
            $st->execute([(int)$assigneeId]);
            $tg = $st->fetchColumn();
            if ($tg) {
                \App\Notifications\Telegram::sendTo((string)$tg, $msg);
            } else {
                \App\Notifications\Telegram::send($msg); // fallback to global chat
            }
        } else {
            \App\Notifications\Telegram::send($msg);
        }

        return Response::json($task, 201);
    }

    public function list(Request $req)
    {
        $limit = isset($req->query['limit']) ? max(1, (int)$req->query['limit']) : 50;
        $offset = isset($req->query['offset']) ? max(0, (int)$req->query['offset']) : 0;
        $repo = $this->tasks;
        $items = $repo->list($limit, $offset);
        return Response::json(['items' => $items, 'limit' => $limit, 'offset' => $offset]);
    }

    public function get(Request $req, array $params)
    {
        $id = (int)($params['id'] ?? 0);
        $task = $this->tasks->get($id);
        if (!$task) return Response::json(['error' => 'Not Found'], 404);
        return Response::json($task);
    }

    public function addComment(Request $req, array $params)
    {
        $id = (int)($params['id'] ?? 0);
        $claims = $GLOBALS['auth_user'] ?? null;
        $userId = isset($claims['sub']) ? (int)$claims['sub'] : null;
        $text = trim((string)($req->body['text'] ?? ''));
        if ($text === '') return Response::json(['error' => 'text is required'], 422);
        $comment = $this->tasks->addComment($id, $userId, $text);
        $msg = "💬 Новый комментарий к задаче #{$id}:\n" . $text;
        // Notify assignee personally if set
        $pdo = \App\Database\DB::conn();
        $st = $pdo->prepare('SELECT u.telegram_id FROM tasks t LEFT JOIN users u ON u.id=t.assigned_user_id WHERE t.id=?');
        $st->execute([$id]);
        $tg = $st->fetchColumn();
        if ($tg) {
            \App\Notifications\Telegram::sendTo((string)$tg, $msg);
        } else {
            Telegram::send($msg);
        }
        return Response::json($comment, 201);
    }
}
