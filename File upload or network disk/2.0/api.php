
<?php
/**
 * 轻量级网盘后端 API
 * 注意：生产环境请添加身份验证中间件
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 配置
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('MAX_FILE_SIZE', 20 * 1024 * 1024); // 20MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar', '7z', 'txt', 'mp3', 'wav', 'mp4', 'avi', 'exe']);

// 确保上传目录存在
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        handleList();
        break;
    case 'upload':
        handleUpload();
        break;
    case 'delete':
        handleDelete();
        break;
    case 'rename':
        handleRename();
        break;
    case 'download':
        handleDownload();
        break;
    default:
        jsonResponse(400, '无效的操作');
}

/**
 * 列出文件
 */
function handleList() {
    $path = isset($_GET['path']) ? $_GET['path'] : '/';
    $realPath = getRealPath($path);
    
    if (!$realPath || !is_dir($realPath)) {
        jsonResponse(404, '目录不存在');
    }

    $items = [];
    $files = scandir($realPath);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $fullPath = $realPath . DIRECTORY_SEPARATOR . $file;
        $isDir = is_dir($fullPath);
        
        $items[] = [
            'name' => $file,
            'type' => $isDir ? 'folder' : 'file',
            'size' => $isDir ? 0 : filesize($fullPath),
            'mtime' => date('Y-m-d H:i:s', filemtime($fullPath))
        ];
    }
    
    // 排序：文件夹在前
    usort($items, function($a, $b) {
        if ($a['type'] === $b['type']) {
            return strcmp($a['name'], $b['name']);
        }
        return $a['type'] === 'folder' ? -1 : 1;
    });

    jsonResponse(200, '成功', $items);
}

/**
 * 处理上传
 */
function handleUpload() {
    if (!isset($_FILES['file'])) {
        jsonResponse(400, '没有文件上传');
    }

    $file = $_FILES['file'];
    $basePath = isset($_POST['path']) ? $_POST['path'] : '/';
    $relativePath = isset($_POST['relative_path']) ? $_POST['relative_path'] : $file['name'];
    
    // 安全检查：防止路径遍历
    $targetDir = getRealPath($basePath);
    if (!$targetDir) {
        jsonResponse(403, '非法路径');
    }

    // 处理文件夹上传的相对路径
    $dirOfRelative = dirname($relativePath);
    if ($dirOfRelative !== '.') {
        $targetDir .= DIRECTORY_SEPARATOR . $dirOfRelative;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
    }

    $fileName = basename($relativePath);
    $targetFile = $targetDir . DIRECTORY_SEPARATOR . $fileName;

    // 检查大小
    if ($file['size'] > MAX_FILE_SIZE) {
        jsonResponse(400, '文件大小超过限制 (20MB)');
    }

    // 检查扩展名
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        jsonResponse(400, '不支持的文件类型: ' . $ext);
    }

    // 移动文件
    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        jsonResponse(200, '上传成功');
    } else {
        jsonResponse(500, '上传失败，请检查权限');
    }
}

/**
 * 删除文件/文件夹
 */
function handleDelete() {
    $path = isset($_POST['path']) ? $_POST['path'] : '';
    $type = isset($_POST['type']) ? $_POST['type'] : 'file';
    
    $realPath = getRealPath($path);
    if (!$realPath) {
        jsonResponse(403, '非法路径');
    }

    if (!file_exists($realPath)) {
        jsonResponse(404, '文件不存在');
    }

    $success = false;
    if ($type === 'folder') {
        $success = deleteDirectory($realPath);
    } else {
        $success = unlink($realPath);
    }

    if ($success) {
        jsonResponse(200, '删除成功');
    } else {
        jsonResponse(500, '删除失败');
    }
}

/**
 * 重命名
 */
function handleRename() {
    $oldPath = isset($_POST['old_path']) ? $_POST['old_path'] : '';
    $newName = isset($_POST['new_name']) ? $_POST['new_name'] : '';
    
    if (empty($newName)) {
        jsonResponse(400, '新名称不能为空');
    }

    $realOldPath = getRealPath($oldPath);
    if (!$realOldPath) {
        jsonResponse(403, '非法路径');
    }

    $parentDir = dirname($realOldPath);
    $realNewPath = $parentDir . DIRECTORY_SEPARATOR . basename($newName);

    // 简单安全检查：确保新路径仍在上传目录内
    if (strpos($realNewPath, UPLOAD_DIR) !== 0) {
        jsonResponse(403, '非法目标路径');
    }

    if (rename($realOldPath, $realNewPath)) {
        jsonResponse(200, '重命名成功');
    } else {
        jsonResponse(500, '重命名失败');
    }
}

/**
 * 下载文件
 */
function handleDownload() {
    $path = isset($_GET['path']) ? $_GET['path'] : '';
    $realPath = getRealPath($path);

    if (!$realPath || !is_file($realPath)) {
        http_response_code(404);
        echo '文件未找到';
        exit;
    }

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($realPath) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($realPath));
    
    readfile($realPath);
    exit;
}

// --- 辅助函数 ---

/**
 * 将虚拟路径转换为真实物理路径，并进行安全校验
 */
function getRealPath($virtualPath) {
    // 清理路径
    $virtualPath = str_replace(['../', '..\\'], '', $virtualPath);
    if ($virtualPath === '/' || $virtualPath === '') {
        return UPLOAD_DIR;
    }
    
    // 移除开头的斜杠
    $cleanPath = ltrim($virtualPath, '/');
    $realPath = UPLOAD_DIR . DIRECTORY_SEPARATOR . $cleanPath;
    
    // 规范化路径
    $realPath = realpath($realPath);
    
    // 安全检查：确保解析后的路径仍在 UPLOAD_DIR 内
    if ($realPath === false || strpos($realPath, UPLOAD_DIR) !== 0) {
        return false;
    }
    
    return $realPath;
}

/**
 * 递归删除目录
 */
function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return unlink($dir);
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        is_dir($path) ? deleteDirectory($path) : unlink($path);
    }
    return rmdir($dir);
}

/**
 * 统一JSON响应
 */
function jsonResponse($code, $message, $data = null) {
    http_response_code($code === 200 ? 200 : 400);
    echo json_encode([
        'code' => $code,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
