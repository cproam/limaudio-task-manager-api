<?php

namespace App\Repositories;

use App\Database\DB;
use App\Enums\TaskStatus;
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
        $stmt = $this->pdo->prepare('INSERT INTO tasks(title,description,direction_id,due_at,assigned_user_id,status,urgency,created_by,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $data['title'],
            $data['description'] ?? null,
            $data['direction_id'] ?? null,
            $data['due_at'] ?? null,
            $data['assigned_user_id'] ?? null,
            $status,
            $data['urgency'],
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

    public function list(int $limit = 50, int $offset = 0, ?string $date = null): array
    {
        if ($date) {
            $stmt = $this->pdo->prepare('SELECT * FROM tasks WHERE due_at LIKE ? ORDER BY id DESC LIMIT ? OFFSET ?');
            $stmt->bindValue(1, $date . '%', PDO::PARAM_STR);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->bindValue(3, $offset, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt = $this->pdo->prepare('SELECT * FROM tasks ORDER BY id DESC LIMIT ? OFFSET ?');
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->bindValue(2, $offset, PDO::PARAM_INT);
            $stmt->execute();
        }
        $items = $stmt->fetchAll();
        foreach ($items as &$t) {
            $tid = (int)$t['id'];
            $t['links'] = $this->getLinks($tid);
            $t['files'] = $this->getFiles($tid);
        }
        return $items;
    }

    public function listMine(int $userId, int $limit = 50, int $offset = 0, ?string $date = null): array
    {
        if ($date) {
            $stmt = $this->pdo->prepare('SELECT * FROM tasks WHERE (assigned_user_id = ? OR created_by = ?) AND due_at LIKE ? ORDER BY id DESC LIMIT ? OFFSET ?');
            $stmt->bindValue(1, $userId, PDO::PARAM_INT);
            $stmt->bindValue(2, $userId, PDO::PARAM_INT);
            $stmt->bindValue(3, $date . '%', PDO::PARAM_STR);
            $stmt->bindValue(4, $limit, PDO::PARAM_INT);
            $stmt->bindValue(5, $offset, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt = $this->pdo->prepare('SELECT * FROM tasks WHERE assigned_user_id = ? OR created_by = ? ORDER BY id DESC LIMIT ? OFFSET ?');
            $stmt->bindValue(1, $userId, PDO::PARAM_INT);
            $stmt->bindValue(2, $userId, PDO::PARAM_INT);
            $stmt->bindValue(3, $limit, PDO::PARAM_INT);
            $stmt->bindValue(4, $offset, PDO::PARAM_INT);
            $stmt->execute();
        }
        $items = $stmt->fetchAll();
        foreach ($items as &$t) {
            $tid = (int)$t['id'];
            $t['links'] = $this->getLinks($tid);
            $t['files'] = $this->getFiles($tid);
        }
        return $items;
    }

    public function addComment(int $taskId, int $userId, string $text = ''): array
    {
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO comments(task_id,user_id,text,created_at) VALUES(?,?,?,?)');
        $stmt->execute([$taskId, $userId, $text, $now]);
        return ['id' => (int)$this->pdo->lastInsertId(), 'task_id' => $taskId, 'user_id' => $userId, 'text' => $text, 'created_at' => $now];
    }

    public function addLink(int $taskId, string $url): ?array
    {
        $check = $this->pdo->prepare('SELECT id FROM tasks WHERE id=?');
        $check->execute([$taskId]);
        if ($check->fetchColumn() === false) {
            return null;
        }

        $stmt = $this->pdo->prepare('INSERT INTO task_links(task_id,url) VALUES(?,?)');
        $stmt->execute([$taskId, $url]);

        $id = (int)$this->pdo->lastInsertId();
        return ['id' => $id, 'task_id' => $taskId, 'url' => $url];
    }

    public function attachFile(int $taskId, string $fileName, string $fileUrl): array
    {
        $stmt = $this->pdo->prepare('INSERT INTO task_files(task_id,file_name,file_url) VALUES(?,?,?)');
        $stmt->execute([$taskId, $fileName, $fileUrl]);
        $id = (int)$this->pdo->lastInsertId();
        return ['id' => $id, 'task_id' => $taskId, 'file_name' => $fileName, 'file_url' => $fileUrl];
    }

    public function deleteFileById(int $taskId, int $fileId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM task_files WHERE id=? AND task_id=?');
        $stmt->execute([$fileId, $taskId]);
        return $stmt->rowCount() > 0;
    }

    public function updateStatus(int $taskId, string $status): ?array
    {
        $stmt = $this->pdo->prepare('UPDATE tasks SET status=?, updated_at=? WHERE id=?');
        $stmt->execute([$status, gmdate('c'), $taskId]);
        if ($stmt->rowCount() === 0) return null;
        return $this->get($taskId);
    }

    public function updateUrgency(int $taskId, int $urgency): ?array
    {
        $stmt = $this->pdo->prepare('UPDATE tasks SET urgency=?, updated_at=? WHERE id=?');
        $stmt->execute([$urgency, gmdate('c'), $taskId]);
        if ($stmt->rowCount() === 0) return null;
        return $this->get($taskId);
    }

    public function patch(int $taskId, array $fields): ?array
    {
        $task = $this->get($taskId);
        if (!$task) return null;

        $sets = [];
        $vals = [];
        if (isset($fields['deadline'])) {
            $sets[] = 'due_at=?';
            $vals[] = (string)$fields['deadline'];
        }
        if (isset($fields['description'])) {
            $sets[] = 'description=?';
            $vals[] = (string)$fields['description'];
        }
        if (isset($fields['notified_30'])) {
            $sets[] = 'notified_30=?';
            $vals[] = $fields['notified_30'];
        }
        if (isset($fields['notified_10'])) {
            $sets[] = 'notified_10=?';
            $vals[] = $fields['notified_10'];
        }
        if (isset($fields['notified_0'])) {
            $sets[] = 'notified_0=?';
            $vals[] = $fields['notified_0'];
        }
        if (isset($fields['notified_pending'])) {
            $sets[] = 'notified_pending=?';
            $vals[] = $fields['notified_pending'];
        }
        if ($sets) {
            $sets[] = 'updated_at=?';
            $vals[] = gmdate('c');
            $vals[] = $taskId;
            $sql = 'UPDATE tasks SET ' . implode(',', $sets) . ' WHERE id=?';
            $this->pdo->prepare($sql)->execute($vals);
        }

        return $this->get($taskId);
    }

    public function delete(int $taskId)
    {
        $stmt = $this->pdo->prepare('DELETE FROM tasks WHERE id=?');
        $stmt->execute([$taskId]);
        return $stmt->rowCount() > 0;
    }

    private function getLinks(int $taskId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, url FROM task_links WHERE task_id=? ORDER BY id ASC');
        $stmt->execute([$taskId]);
        return $stmt->fetchAll();
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

    public function updateComment(int $taskId, int $commentId, string $text): ?array
    {
        $comment = $this->findComment($taskId, $commentId);
        if (!$comment) {
            return null;
        }

        if ($comment['text'] !== $text) {
            $stmt = $this->pdo->prepare('UPDATE comments SET text=? WHERE id=? AND task_id=?');
            $stmt->execute([$text, $commentId, $taskId]);
            $comment = $this->findComment($taskId, $commentId);
        }

        return $comment;
    }

    public function deleteComment(int $taskId, int $commentId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM comments WHERE id=? AND task_id=?');
        $stmt->execute([$commentId, $taskId]);
        return $stmt->rowCount() > 0;
    }

    public function updateLink(int $taskId, int $linkId, string $url): ?array
    {
        $link = $this->findLink($taskId, $linkId);
        if (!$link) {
            return null;
        }

        if ($link['url'] !== $url) {
            $stmt = $this->pdo->prepare('UPDATE task_links SET url=? WHERE id=? AND task_id=?');
            $stmt->execute([$url, $linkId, $taskId]);
            $link = $this->findLink($taskId, $linkId);
        }

        return $link;
    }

    public function deleteLinkById(int $taskId, int $linkId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM task_links WHERE id=? AND task_id=?');
        $stmt->execute([$linkId, $taskId]);
        return $stmt->rowCount() > 0;
    }

    private function findComment(int $taskId, int $commentId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, task_id, user_id, text, created_at FROM comments WHERE id=? AND task_id=?');
        $stmt->execute([$commentId, $taskId]);
        $comment = $stmt->fetch();
        return $comment ?: null;
    }

    private function findLink(int $taskId, int $linkId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, task_id, url FROM task_links WHERE id=? AND task_id=?');
        $stmt->execute([$linkId, $taskId]);
        $link = $stmt->fetch();
        return $link ?: null;
    }
}
