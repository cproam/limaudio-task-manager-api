<?php

namespace App\Repositories;

use App\Database\DB;
use PDO;

class TaskRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = DB::conn();
    }

    public function create(array $data): array
    {
        $now = gmdate('c');
        $status = 'Новая';
        if (!empty($data['assigned_user_id'])) {
            $status = 'Ответственный назначен';
        }
        $stmt = $this->pdo->prepare('INSERT INTO tasks(title,description,direction_id,due_at,assigned_user_id,status,created_by,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $data['title'],
            $data['description'] ?? null,
            $data['direction_id'] ?? null,
            $data['due_at'] ?? null,
            $data['assigned_user_id'] ?? null,
            $status,
            $data['created_by'] ?? null,
            $now,
            $now,
        ]);
        $taskId = (int)$this->pdo->lastInsertId();

        // Links
        if (!empty($data['links']) && is_array($data['links'])) {
            $ins = $this->pdo->prepare('INSERT INTO task_links(task_id,url) VALUES(?,?)');
            foreach ($data['links'] as $url) {
                $url = trim((string)$url);
                if ($url !== '') $ins->execute([$taskId, $url]);
            }
        }

        // Files
        if (!empty($data['files']) && is_array($data['files'])) {
            $insf = $this->pdo->prepare('INSERT INTO task_files(task_id,file_name,file_url) VALUES(?,?,?)');
            foreach ($data['files'] as $f) {
                if (!isset($f['file_name'], $f['file_url'])) continue;
                $insf->execute([$taskId, $f['file_name'], $f['file_url']]);
            }
        }

        return $this->get($taskId);
    }

    public function get(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tasks WHERE id=?');
        $stmt->execute([$id]);
        $task = $stmt->fetch();
        if (!$task) return null;
        $task['links'] = $this->getLinks($id);
        $task['files'] = $this->getFiles($id);
        $task['comments'] = $this->getComments($id);
        return $task;
    }

    public function list(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tasks ORDER BY id DESC LIMIT ? OFFSET ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();
        foreach ($items as &$t) {
            $tid = (int)$t['id'];
            $t['links'] = $this->getLinks($tid);
            $t['files'] = $this->getFiles($tid);
        }
        return $items;
    }

    public function addComment(int $taskId, int $userId = null, string $text = ''): array
    {
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO comments(task_id,user_id,text,created_at) VALUES(?,?,?,?)');
        $stmt->execute([$taskId, $userId, $text, $now]);
        return ['id' => (int)$this->pdo->lastInsertId(), 'task_id' => $taskId, 'user_id' => $userId, 'text' => $text, 'created_at' => $now];
    }

    private function getLinks(int $taskId): array
    {
        $stmt = $this->pdo->prepare('SELECT url FROM task_links WHERE task_id=?');
        $stmt->execute([$taskId]);
        return array_map(fn($r) => $r['url'], $stmt->fetchAll());
    }

    private function getFiles(int $taskId): array
    {
        $stmt = $this->pdo->prepare('SELECT file_name,file_url FROM task_files WHERE task_id=?');
        $stmt->execute([$taskId]);
        return $stmt->fetchAll();
    }

    private function getComments(int $taskId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, task_id, user_id, text, created_at FROM comments WHERE task_id=? ORDER BY id ASC');
        $stmt->execute([$taskId]);
        return $stmt->fetchAll();
    }
}
