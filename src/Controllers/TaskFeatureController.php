<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\TaskRepository;
use App\Services\TaskNotificationService;
use App\Validators\TaskValidator;
use App\Routing\Route;

class TaskFeatureController
{
    private TaskRepository $tasks;
    private TaskValidator $validator;

    public function __construct()
    {
        $this->tasks = new TaskRepository();
        $this->validator = new TaskValidator();
    }

    private function getAssigneeInfo(?int $assigneeId): array
    {
        if (!$assigneeId) return ['', null];
        $pdo = \App\Database\DB::conn();
        $st = $pdo->prepare('SELECT name, telegram_id FROM users WHERE id=?');
        $st->execute([$assigneeId]);
        $row = $st->fetch();
        return $row ? [htmlspecialchars((string)$row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $row['telegram_id']] : ['', null];
    }

    #[Route('POST', '/task')]
    public function create(Request $req)
    {
        $claims = $GLOBALS['auth_user'] ?? null;
        $userId = isset($claims['sub']) ? (int)$claims['sub'] : null;
        $data = $req->body;

        // Валидация через DTO и атрибуты
        $dto = new \App\DTO\CreateTaskDTO();
        foreach ($data as $key => $value) {
            if (property_exists($dto, $key)) {
                $dto->$key = $value;
            }
        }
        $errors = $this->validator->validate($dto);
        if (!empty($errors)) {
            return Response::json(['errors' => $errors], 422);
        }

        $payload = [
            'title' => $dto->title,
            'description' => $dto->description,
            'direction_id' => $dto->direction_id,
            'urgency' => $dto->urgency,
            'due_at' => $dto->due_at,
            'assigned_user_id' => $dto->assigned_user_id,
            'links' => $dto->links ?? [],
            'files' => $dto->files ?? [],
            'created_by' => $userId,
        ];

        $task = $this->tasks->create($payload);

        // Notify Telegram: duplicate to admin chat AND personally to assignee (if set)
        $assigneeId = $payload['assigned_user_id'] ?? null;
        $deadline = $payload['due_at'] ?? null;
        [$assigneeName, $assigneeTg] = $this->getAssigneeInfo($assigneeId);
        TaskNotificationService::notifyTaskCreated($task, $assigneeName, $assigneeTg, $deadline);

        return Response::json($task, 201);
    }

    #[Route('GET', '/task')]
    public function list(Request $req)
    {
        $limit = isset($req->query['limit']) ? max(1, (int)$req->query['limit']) : 50;
        $offset = isset($req->query['offset']) ? max(0, (int)$req->query['offset']) : 0;
        $claims = $GLOBALS['auth_user'] ?? null;
        $roles = is_array($claims['roles'] ?? null) ? $claims['roles'] : [];
        $userId = isset($claims['sub']) ? (int)$claims['sub'] : 0;
        $date = null;
        if (!empty($req->query['date'])) {
            $d = (string)$req->query['date'];
            // naive YYYY-MM-DD validation
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                $date = $d;
            }
        }

        if (in_array('admin', $roles, true)) {
            $items = $this->tasks->list($limit, $offset, $date);
        } elseif (in_array('sales_manager', $roles, true)) {
            $items = $this->tasks->listMine($userId, $limit, $offset, $date);
        } else {
            $items = [];
        }

        return Response::json(['items' => $items, 'limit' => $limit, 'offset' => $offset]);
    }

    #[Route('GET', '/task/{id}')] // <-- Here is the suggested change incorporated
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

    #[Route('POST', '/task/{id}/comments')]
    public function addComment(Request $req, array $params)
    {
        $id = (int)($params['id'] ?? 0);
        $claims = $GLOBALS['auth_user'] ?? null;
        $userId = isset($claims['sub']) ? (int)$claims['sub'] : null;
        $text = trim((string)($req->body['text'] ?? ''));
        if ($text === '') return Response::json(['error' => 'text is required'], 422);
        $comment = $this->tasks->addComment($id, $userId, $text);
        $tg = TaskNotificationService::getAssigneeTg($id);
        TaskNotificationService::notifyCommentAdded($id, $text, $tg);
        return Response::json($comment, 201);
    }

    #[Route('POST', '/task/{id}/files')]
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

    #[Route('PATCH', '/task/{id}/status')]
    public function updateStatus(Request $req, array $params)
    {
        $id = (int)($params['id'] ?? 0);
        $status = (string)($req->body['status'] ?? '');
        
        // Валидация статуса через enum
        if (!\App\Enums\TaskStatus::isValid($status)) {
            return Response::json(['error' => 'Invalid status'], 422);
        }

        // Enforce visibility rules: admin can change any; sales_manager only if own/created; others forbidden
        $claims = $GLOBALS['auth_user'] ?? null;
        $roles = is_array($claims['roles'] ?? null) ? $claims['roles'] : [];
        $userId = isset($claims['sub']) ? (int)$claims['sub'] : 0;
        $task = $this->tasks->get($id);
        if (!$task) return Response::json(['error' => 'Not Found'], 404);
        if (!in_array('admin', $roles, true)) {
            if (in_array('sales_manager', $roles, true)) {
                $assigned = isset($task['assigned_user_id']) ? (int)$task['assigned_user_id'] : 0;
                $created = isset($task['created_by']) ? (int)$task['created_by'] : 0;
                if (!($assigned === $userId || $created === $userId)) {
                    return Response::json(['error' => 'Forbidden'], 403);
                }
            } else {
                return Response::json(['error' => 'Forbidden'], 403);
            }
        }

        $updated = $this->tasks->updateStatus($id, $status);
        if (!$updated) return Response::json(['error' => 'Not Found'], 404);
        // Notify Telegram about status change (admin chat + assignee personal)
        $assigneeId = isset($updated['assigned_user_id']) ? (int)$updated['assigned_user_id'] : null;
        [$assigneeName, $assigneeTg] = $this->getAssigneeInfo($assigneeId);
        TaskNotificationService::notifyStatusChanged($id, $status, $updated['title'], $updated['description'] ?? '', $assigneeName, $assigneeTg);
        return Response::json($updated);
    }

    #[Route('PATCH', '/task/{id}/urgency')]
    public function updateUrgency(Request $req, array $params)
    {
        $id = (int)($params['id'] ?? 0);
        $urgency = (int)($req->body['urgency'] ?? 5);
        $updated = $this->tasks->updateUrgency($id, $urgency);
        if (!$updated) return Response::json(['error' => 'Not Found'], 404);
        TaskNotificationService::notifyUrgencyChanged($id, $urgency, $updated['title'], $updated['description'] ?? '');
        return Response::json($updated);
    }

    #[Route('PATCH', '/task/{id}')] // <-- Suggested change incorporated
    public function patchTask(Request $req, array $params)
    {
        $id = (int)($params['id'] ?? 0);
        $deadline = (string)($req->body['deadline']);
        $updated = $this->tasks->patchDeadline($id, $deadline);
        return Response::json($updated);
    }

    #[Route('DELETE', '/task/{id}')] // <-- Route attribute added to delete method
    public function delete(Request $req, array $params)
    {
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            return Response::json(['error' => 'неверный id'], 500);
        }
        $deleted = $this->tasks->delete($id);
        return Response::json(['deleted' => $deleted]);
    }
}
