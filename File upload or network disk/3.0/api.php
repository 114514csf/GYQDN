<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

if (!is_dir(STORAGE_ROOT)) {
    if (!mkdir(STORAGE_ROOT, 0777, true) && !is_dir(STORAGE_ROOT)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Storage directory could not be created.']);
        exit;
    }
}

function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function sanitizeFileName(string $name): string
{
    $name = trim($name);
    $name = str_replace(['\\', '/'], '', $name);
    $name = preg_replace('/[\x00-\x1F\x7F]+/', '', $name);
    $name = trim($name, " .");
    return $name === '' ? 'unnamed' : $name;
}

function normalizePath(string $path): string
{
    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#/+#', '/', $path);
    return $path;
}

function joinPath(string $base, string $child): string
{
    $base = rtrim(normalizePath($base), '/');
    $child = ltrim(normalizePath($child), '/');

    if ($base === '') {
        return '/' . $child;
    }

    if ($child === '') {
        return $base;
    }

    return $base . '/' . $child;
}

function resolvePath(string $userPath): string
{
    $root = realpath(STORAGE_ROOT) ?: STORAGE_ROOT;

    if ($userPath === '' || $userPath === '/') {
        return $root;
    }

    $relative = normalizePath($userPath);
    if ($relative[0] !== '/') {
        $relative = '/' . $relative;
    }

    $target = $root . $relative;
    $real = realpath($target) ?: $target;

    $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    if ($real !== $root && strncmp($real, $rootPrefix, strlen($rootPrefix)) !== 0) {
        throw new RuntimeException('Invalid path.');
    }

    return $real;
}

function responseError(string $message, int $status = 400): void
{
    jsonResponse(['success' => false, 'message' => $message], $status);
}

function formatSize(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $size = (float)$bytes;
    $index = 0;

    while ($size >= 1024 && $index < count($units) - 1) {
        $size /= 1024;
        $index++;
    }

    return round($size, 2) . ' ' . $units[$index];
}

function listDirectory(string $dir): array
{
    $entries = [];
    $items = scandir($dir);

    foreach ($items as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }

        $full = $dir . DIRECTORY_SEPARATOR . $name;
        $isDir = is_dir($full);

        $entries[] = [
            'name' => $name,
            'type' => $isDir ? 'dir' : 'file',
            'size' => $isDir ? 0 : filesize($full),
            'modified' => date('Y-m-d H:i:s', filemtime($full)),
        ];
    }

    usort($entries, function ($a, $b) {
        if ($a['type'] !== $b['type']) {
            return $a['type'] === 'dir' ? -1 : 1;
        }
        return strcmp($a['name'], $b['name']);
    });

    return $entries;
}

function uniqueFilePath(string $dir, string $filename): string
{
    $base = $dir . DIRECTORY_SEPARATOR . $filename;
    if (!file_exists($base)) {
        return $base;
    }

    $info = pathinfo($filename);
    $name = $info['filename'] ?? 'file';
    $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
    $counter = 1;

    do {
        $candidate = $dir . DIRECTORY_SEPARATOR . $name . ' (' . $counter . ')' . $ext;
        $counter++;
    } while (file_exists($candidate));

    return $candidate;
}

function copyRecursive(string $src, string $dst): void
{
    if (is_dir($src)) {
        if (!is_dir($dst) && !mkdir($dst, 0777, true) && !is_dir($dst)) {
            throw new RuntimeException('Failed to create destination directory');
        }

        $items = scandir($src);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $from = $src . DIRECTORY_SEPARATOR . $item;
            $to = $dst . DIRECTORY_SEPARATOR . $item;
            copyRecursive($from, $to);
        }
        return;
    }

    copy($src, $dst);
}

function deleteRecursive(string $path): void
{
    if (is_dir($path)) {
        $items = scandir($path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            deleteRecursive($path . DIRECTORY_SEPARATOR . $item);
        }
        rmdir($path);
        return;
    }

    unlink($path);
}

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? 'list';
    $path = $_POST['path'] ?? $_GET['path'] ?? '/';

    if (!in_array($action, ['list', 'mkdir', 'upload', 'delete', 'rename', 'copy', 'move', 'download'], true)) {
        responseError('Invalid action.', 400);
    }

    if ($action === 'list') {
        $dir = resolvePath($path);

        if (!is_dir($dir)) {
            responseError('Directory not found.', 404);
        }

        jsonResponse([
            'success' => true,
            'path' => $path,
            'entries' => listDirectory($dir),
        ]);
    }

    if ($action === 'mkdir') {
        $dir = resolvePath($path);
        $name = sanitizeFileName((string)($_POST['name'] ?? ''));

        if ($name === '') {
            responseError('Folder name is required.', 400);
        }

        $target = $dir . DIRECTORY_SEPARATOR . $name;

        if (file_exists($target)) {
            responseError('A folder or file with that name already exists.', 409);
        }

        if (!mkdir($target, 0777, true) && !is_dir($target)) {
            responseError('Failed to create folder.', 500);
        }

        jsonResponse(['success' => true, 'message' => 'Folder created.']);
    }

    if ($action === 'upload') {
        if (!isset($_FILES['files'])) {
            responseError('No files uploaded.', 400);
        }

        $dir = resolvePath($path);

        if (!is_dir($dir)) {
            responseError('Destination directory not found.', 404);
        }

        $uploaded = [];

        $fileNames = $_FILES['files']['name'];
        $tmpNames = $_FILES['files']['tmp_name'];
        $errors = $_FILES['files']['error'];
        $sizes = $_FILES['files']['size'];

        foreach ($fileNames as $index => $name) {
            if ($errors[$index] !== UPLOAD_ERR_OK) {
                responseError('One or more files failed to upload.', 400);
            }

            if ((int)$sizes[$index] > MAX_FILE_SIZE) {
                responseError('A file exceeds the 20 MB limit: ' . $name, 400);
            }

            $safeName = sanitizeFileName((string)$name);
            $dest = uniqueFilePath($dir, $safeName);

            if (!move_uploaded_file($tmpNames[$index], $dest)) {
                responseError('Could not save uploaded file: ' . $name, 500);
            }

            $uploaded[] = $safeName;
        }

        jsonResponse([
            'success' => true,
            'message' => 'Upload complete.',
            'files' => $uploaded,
        ]);
    }

    if ($action === 'delete') {
        $name = sanitizeFileName((string)($_POST['name'] ?? ''));
        if ($name === '') {
            responseError('Item name is required.', 400);
        }

        $dir = resolvePath($path);
        $target = $dir . DIRECTORY_SEPARATOR . $name;

        if (!file_exists($target)) {
            responseError('Item not found.', 404);
        }

        deleteRecursive($target);

        jsonResponse(['success' => true, 'message' => 'Deleted successfully.']);
    }

    if ($action === 'rename') {
        $oldName = sanitizeFileName((string)($_POST['name'] ?? ''));
        $newName = sanitizeFileName((string)($_POST['new_name'] ?? ''));

        if ($oldName === '' || $newName === '') {
            responseError('Old and new names are required.', 400);
        }

        $dir = resolvePath($path);
        $src = $dir . DIRECTORY_SEPARATOR . $oldName;
        $dst = $dir . DIRECTORY_SEPARATOR . $newName;

        if (!file_exists($src)) {
            responseError('Item not found.', 404);
        }

        if (file_exists($dst)) {
            responseError('A file or folder with that name already exists.', 409);
        }

        if (!rename($src, $dst)) {
            responseError('Failed to rename item.', 500);
        }

        jsonResponse(['success' => true, 'message' => 'Renamed successfully.']);
    }

    if ($action === 'copy') {
        $name = sanitizeFileName((string)($_POST['name'] ?? ''));
        $newName = sanitizeFileName((string)($_POST['new_name'] ?? $name));

        if ($name === '') {
            responseError('Item name is required.', 400);
        }

        $dir = resolvePath($path);
        $src = $dir . DIRECTORY_SEPARATOR . $name;
        $dest = uniqueFilePath($dir, $newName);

        if (!file_exists($src)) {
            responseError('Source item not found.', 404);
        }

        copyRecursive($src, $dest);

        jsonResponse(['success' => true, 'message' => 'Copied successfully.']);
    }

    if ($action === 'move') {
        $name = sanitizeFileName((string)($_POST['name'] ?? ''));
        $targetDir = $_POST['target_dir'] ?? $path;
        $newName = sanitizeFileName((string)($_POST['new_name'] ?? $name));

        if ($name === '') {
            responseError('Item name is required.', 400);
        }

        $sourceDir = resolvePath($path);
        $source = $sourceDir . DIRECTORY_SEPARATOR . $name;

        if (!file_exists($source)) {
            responseError('Source item not found.', 404);
        }

        $destDir = resolvePath($targetDir);
        $dest = $destDir . DIRECTORY_SEPARATOR . $newName;

        if (file_exists($dest)) {
            responseError('A file or folder with that destination name already exists.', 409);
        }

        if (!rename($source, $dest)) {
            responseError('Failed to move item.', 500);
        }

        jsonResponse(['success' => true, 'message' => 'Moved successfully.']);
    }

    if ($action === 'download') {
        $item = $_GET['path'] ?? '/';
        $file = resolvePath($item);

        if (!is_file($file)) {
            http_response_code(404);
            echo 'File not found.';
            exit;
        }

        $name = basename($file);

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '\"', $name) . '"');
        header('Content-Length: ' . filesize($file));
        header('Pragma: public');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        readfile($file);
        exit;
    }
} catch (Throwable $e) {
    responseError($e->getMessage(), 500);
}