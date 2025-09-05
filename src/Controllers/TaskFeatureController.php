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

        // Notify Telegram: duplicate to admin chat AND personally to assignee (if set)
        $title = htmlspecialchars($task['title'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $msg = "🆕 Новая задача: <b>{$title}</b>\nСтатус: {$task['status']}\nID: {$task['id']}";
        $assigneeId = $payload['assigned_user_id'] ?? null;
        // Always send to admin chat (global)
        \App\Notifications\Telegram::send($msg);
        // Additionally, send personally to assignee if available
        if ($assigneeId) {
            $pdo = \App\Database\DB::conn();
            $st = $pdo->prepare('SELECT telegram_id FROM users WHERE id=?');
            $st->execute([(int)$assigneeId]);
            $tg = $st->fetchColumn();
            if ($tg) {
                \App\Notifications\Telegram::sendTo((string)$tg, $msg);
            }
        }

        return Response::json($task, 201);
    }

    public function list(Request $req)
    {
        $limit = isset($req->query['limit']) ? max(1, (int)$req->query['limit']) : 50;
        $offset = isset($req->query['offset']) ? max(0, (int)$req->query['offset']) : 0;
        $claims = $GLOBALS['auth_user'] ?? null;
        $roles = is_array($claims['roles'] ?? null) ? $claims['roles'] : [];
        $userId = isset($claims['sub']) ? (int)$claims['sub'] : 0;

        if (in_array('admin', $roles, true)) {
            $items = $this->tasks->list($limit, $offset);
        } elseif (in_array('sales_manager', $roles, true)) {
            $items = $this->tasks->listMine($userId, $limit, $offset);
        } else {
            $items = [];
        }

        return Response::json(['items' => $items, 'limit' => $limit, 'offset' => $offset]);
    }

    public function get(Request $req, array $params)
    {
        $id = (int)($params['id'] ?? 0);
        $claims = $GLOBALS['auth_user'] ?? null;
        $roles = is_array($claims['roles'] ?? null) ? $claims['roles'] : [];
        $userId = isset($claims['sub']) ? (int)$claims['sub'] : 0;

        $task = $this->tasks->get($id);
        if (!$task) return Response::json(['error' => 'Not Found'], 404);
        // Visibility: admin => any; sales_manager => own or created_by; others => deny
        if (in_array('admin', $roles, true)) {
            return Response::json($task);
        }
        if (in_array('sales_manager', $roles, true)) {
            $assigned = isset($task['assigned_user_id']) ? (int)$task['assigned_user_id'] : 0;
            $created = isset($task['created_by']) ? (int)$task['created_by'] : 0;
            if ($assigned === $userId || $created === $userId) {
                return Response::json($task);
            }
        }
        return Response::json(['error' => 'Forbidden'], 403);
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
        // Always notify admin chat
        Telegram::send($msg);
        // Notify assignee personally if set
        $pdo = \App\Database\DB::conn();
        $st = $pdo->prepare('SELECT u.telegram_id FROM tasks t LEFT JOIN users u ON u.id=t.assigned_user_id WHERE t.id=?');
        $st->execute([$id]);
        $tg = $st->fetchColumn();
        if ($tg) {
            \App\Notifications\Telegram::sendTo((string)$tg, $msg);
        }
        return Response::json($comment, 201);
    }

    public function attachFile(Request $req, array $params)
    {
        $taskId = (int)($params['id'] ?? 0);
        // Two modes: JSON reference or multipart upload
        if (!empty($_FILES['file'])) {
            // Delegate to UploadController logic inline to avoid routing re-entry
            $file = $_FILES['file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                return Response::json(['error' => 'upload failed', 'code' => $file['error']], 400);
            }
            $root = dirname(__DIR__, 2);
            $uploadDir = $root . '/public/uploads';
            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $hash = bin2hex(random_bytes(16));
            $name = $hash . ($ext ? ('.' . $ext) : '');
            $dest = $uploadDir . '/' . $name;
            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                if (!rename($file['tmp_name'], $dest)) {
                    return Response::json(['error' => 'cannot save file'], 500);
                }
            }
            $url = '/uploads/' . $name;
            $rec = $this->tasks->attachFile($taskId, $file['name'], $url);
            return Response::json($rec, 201);
        }
        // JSON body: { file_name, file_url }
        $fileName = (string)($req->body['file_name'] ?? '');
        $fileUrl = (string)($req->body['file_url'] ?? '');
        if ($fileName === '' || $fileUrl === '') {
            return Response::json(['error' => 'file_name and file_url are required'], 422);
        }
        $rec = $this->tasks->attachFile($taskId, $fileName, $fileUrl);
        return Response::json($rec, 201);
    }
}
