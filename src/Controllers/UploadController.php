<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Routing\Route;

class UploadController
{
    #[Route('POST', '/upload')]
    public function upload(Request $req)
    {
        if (empty($_FILES['file'])) {
            return Response::json(['error' => 'file is required'], 422);
        }
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
            // Fallback when upload is not via HTTP POST (e.g., some dev servers)
            if (!rename($file['tmp_name'], $dest)) {
                return Response::json(['error' => 'cannot save file'], 500);
            }
        }
        $url = '/uploads/' . $name;
        return Response::json(['file_name' => $file['name'], 'file_url' => $url, 'hash' => $hash], 201);
    }
}
