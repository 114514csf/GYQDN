<?php
date_default_timezone_set('Asia/Shanghai');
/**
 * 安全文件上传与列表展示脚本
 * 注意：生产环境中，请务必在 upload 目录下配置 Web 服务器禁止执行 PHP 脚本。
 */

// 配置项
$upload_dir = './upload/';
$allow_ext = ['jpg', 'png', 'gif', 'jpeg', 'pdf', 'txt','zip','mp3','mp4','xlsx','ppt','pptx','doc','docx'];
// 允许的 MIME 类型映射 (扩展名 => MIME)
$allow_mimes = [
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'],
    'gif'  => ['image/gif'],
    'pdf'  => ['application/pdf'],
    'txt'  => ['text/plain'],
    'zip'  => ['application/zip'],
    'mp3'  => ['audio/mpeg'],
    'mp4'  => ['video/mp4'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    'ppt'  => ['application/mspowerpoint'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
    'doc'  => ['application/msword'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document']
];
$max_size = 20 * 1024 * 1024; // 20MB

// 确保上传目录存在且可写
if (!file_exists($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        die("错误：无法创建上传目录，请检查权限。");
    }
}

// 辅助函数：格式化文件大小
function formatSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576)    return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)       return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

// 辅助函数：输出 HTML 头部样式
function printHeader($title = "文件管理中心") {
    echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <script>
        let delete_i = new URL(location.href)
        delete_i.searchParams.delete('i')
        history.pushState({}, '', delete_i.href)
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f9; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h2 { color: #2c3e50; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-top: 0; }
        .btn { display: inline-block; padding: 8px 15px; background-color: #3498db; color: white; text-decoration: none; border-radius: 4px; font-size: 14px; transition: background 0.3s; }
        .btn:hover { background-color: #2980b9; }
        .btn-danger { background-color: #e74c3c; }
        .btn-danger:hover { background-color: #c0392b; }
        .btn-success { background-color: #27ae60; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        /* 列表样式 */
        .file-list { list-style: none; padding: 0; }
        .file-item { display: flex; align-items: center; justify-content: space-between; padding: 12px; border-bottom: 1px solid #eee; transition: background 0.2s; }
        .file-item:hover { background-color: #f8f9fa; }
        .file-info { display: flex; align-items: center; gap: 15px; }
        .file-thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; background: #eee; }
        .file-name { font-weight: 600; color: #2c3e50; word-break: break-all; }
        .file-meta { font-size: 12px; color: #7f8c8d; margin-top: 4px; }
        .actions { display: flex; gap: 10px; }
    </style>
</head>
<body>
<div class="container">
HTML;
}

function printFooter() {
    echo <<<HTML
    <div style="margin-top: 30px; text-align: center; font-size: 12px; color: #999;">
        &copy; 2026 File Manager
    </div>
</div>
</body>
</html>
HTML;
}

// ==========================
// 1. 文件列表预览功能 ?list=1
// ==========================
if (isset($_GET['list'])) {
    printHeader("文件列表");
    echo "<h2>📂 Upload 目录文件列表</h2>";
    
    $files = scandir($upload_dir);
    if ($files === false) {
        echo "<div class='alert alert-error'>无法读取目录内容。</div>";
    } else {
        echo "<ul class='file-list'>";
        $hasFiles = false;
        foreach ($files as $f) {
            if ($f === '.' || $f === '..') continue;
            
            // 安全检查：只允许列出已知扩展名的文件，防止列出 .htaccess 或其他系统文件
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (!in_array($ext, $allow_ext)) continue;

            $hasFiles = true;
            $file_path = $upload_dir . $f;
            $file_size = filesize($file_path);
            $file_url = $upload_dir . rawurlencode($f);
            
            // 生成缩略图逻辑
            $thumb_html = '';
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $thumb_html = "<img src='thumb.php?src={$file_url}&w=200&h=200' class='file-thumb' alt='preview'>";
            } else {
                // 默认图标占位
                $thumb_html = "<!--<div class='file-thumb' style='display:flex;align-items:center;justify-content:center;font-size:10px;color:#999;'>{$ext}</div>--><img src='icon/{$ext}.jpg' class='file-thumb' alt='preview'>";
            }

            // 转义文件名防止 XSS
            $safe_name = htmlspecialchars($f);
            
            echo "<li class='file-item'>
                <div class='file-info'>
                    {$thumb_html}
                    <div>
                        <div class='file-name'>{$safe_name}</div>
                        <div class='file-meta'>大小: " . formatSize($file_size) . "</div>
                    </div>
                </div>
                <div class='actions'>
                    <a class='btn btn-success' target='_blank' href='{$file_url}' download>下载</a>
                    <a class='btn' target='_blank' href='{$file_url}'>查看</a>
                    <a class='btn btn-danger' href='?delete={$f}' onclick=\"return confirm('确定要删除 {$safe_name} 吗？此操作不可恢复！')\">删除</a>
                </div>
            </li>";
        }
        echo "</ul>";
        
        if (!$hasFiles) {
            echo "<p style='text-align:center; color:#999;'>目录为空</p>";
        }
    }
    
    echo "<div style='margin-top:20px; text-align:center;'>
            <a class='btn btn-success' href='index.html'>⬆️ 返回上传页面</a>
          </div>";
    printFooter();
    exit;
}

// ==========================
// 2. 删除文件逻辑 ?delete=filename
// ==========================
if (isset($_GET['delete'])) {
    $del_file = $_GET['delete'];
    // 严格的安全检查：防止目录遍历攻击 (../)
    if (basename($del_file) !== $del_file || strpos($del_file, '/') !== false || strpos($del_file, '\\') !== false) {
        die("非法文件名");
    }
    
    $target_path = $upload_dir . $del_file;
    if (file_exists($target_path)) {
        // 再次检查扩展名，防止误删重要系统文件
        $ext = strtolower(pathinfo($del_file, PATHINFO_EXTENSION));
        if (in_array($ext, $allow_ext)) {
            unlink($target_path);
            header("Location: ?list=1&msg=deleted");
            exit;
        }
    }
    header("Location: ?list=1&msg=error");
    exit;
}

// ==========================
// 3. 文件上传逻辑
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!isset($_FILES['upfile'])) {
        printHeader("上传失败");
        echo "<div class='alert alert-error'>没有接收到上传文件</div>";
        echo "<a class='btn' href='index.html'>返回</a>";
        printFooter();
        exit;
    }

    $file = $_FILES['upfile'];

    // 1. 检查 PHP 上传错误
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errMsg = [
            1 => "文件超过 php.ini 中 upload_max_filesize 限制",
            2 => "文件超过表单 MAX_FILE_SIZE 限制",
            3 => "文件只有部分被上传",
            4 => "没有选择上传文件",
            6 => "找不到临时文件夹",
            7 => "文件写入失败"
        ];
        $code = $file['error'];
        $msg = isset($errMsg[$code]) ? $errMsg[$code] : "未知错误代码：{$code}";
        
        printHeader("上传失败");
        echo "<div class='alert alert-error'>上传失败：{$msg}</div>";
        echo "<a class='btn' href='index.html'>返回</a>";
        printFooter();
        exit;
    }

    // 2. 检查文件大小
    if ($file['size'] > $max_size) {
        printHeader("上传失败");
        echo "<div class='alert alert-error'>文件过大，最大允许 " . formatSize($max_size) . "</div>";
        echo "<a class='btn' href='index.html'>返回</a>";
        printFooter();
        exit;
    }

    $origin_name = $file['name'];
    $tmp_path = $file['tmp_name'];

    // 3. 获取后缀并转小写
    $ext = strtolower(pathinfo($origin_name, PATHINFO_EXTENSION));
    
    // 4. 后缀名白名单校验
    if (!in_array($ext, $allow_ext)) {
        printHeader("上传失败");
        echo "<div class='alert alert-error'>不允许上传该类型文件 (.{$ext})<br>仅支持: " . implode(', ', $allow_ext) . "</div>";
        echo "<a class='btn' href='index.html'>返回</a>";
        printFooter();
        exit;
    }

    // 5. MIME 类型校验 (防止伪造后缀)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp_path);
    
    if (isset($allow_mimes[$ext])) {
        if (!in_array($mime, $allow_mimes[$ext])) {
             printHeader("上传失败");
             echo "<div class='alert alert-error'>文件内容与其后缀名不符 (检测到 MIME: {$mime})，疑似恶意文件。</div>";
             echo "<a class='btn' href='index.html'>返回</a>";
             printFooter();
             exit;
        }
    }

    // 6. 随机重命名，防止覆盖和预测文件名
    $new_filename = date('YmdHis') . "_" . bin2hex(random_bytes(4)) . "." . $ext;
    $save_path = $upload_dir . $new_filename;

    // 7. 移动文件
    if (move_uploaded_file($tmp_path, $save_path)) {
        printHeader("上传成功");
        echo "<div class='alert alert-success'>
                <h3>✅ 上传成功！</h3>
                <p>原始文件名：{$origin_name}</p>
                <p>服务器保存名：{$new_filename}</p>
              </div>";
        
        $file_url = $upload_dir . rawurlencode($new_filename);
        
        // 如果是图片，显示预览
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            echo "<div style='text-align:center; margin: 20px 0;'>
                    <img src='{$file_url}' style='max-width:100%; max-height:300px; border:1px solid #ddd; box-shadow:0 2px 5px rgba(0,0,0,0.1);'>
                  </div>";
        }

        echo "<div style='text-align:center;'>
                <a class='btn' target='_blank' href='{$file_url}'>📄 打开文件</a>
                <a class='btn btn-success' href='?list=1'>📂 查看全部文件</a>
                <a class='btn' href='index.html'>🔄 继续上传</a>
              </div>";
    } else {
        printHeader("上传失败");
        echo "<div class='alert alert-error'>文件写入失败，请检查 upload 目录读写权限 (chmod 755/777)</div>";
        echo "<a class='btn' href='index.html'>返回</a>";
    }
    printFooter();
    exit;
}

// 如果不是 POST 也不是 GET list/delete，默认可能是在测试或直接访问
printHeader("提示");
echo "<div class='alert alert-error'>非法请求方法。请通过表单 POST 提交文件。</div>";
echo "<a class='btn' href='index.html'>返回上传页</a>";
printFooter();
?>
