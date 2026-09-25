<?php
// 设置 PHP 默认时区为中国标准时间
date_default_timezone_set('Asia/Shanghai');



header('Content-Type: application/json');
session_start();

// 数据文件路径
$dataFile = __DIR__ . '/messages.json';

// 初始化文件如果不存在
if (!file_exists($dataFile)) {
    file_put_contents($dataFile, json_encode([]));
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'send';

switch ($action) {
    case 'send':
        handleSend();
        break;
    case 'get':
        handleGet();
        break;
    default:
        echo json_encode(['success' => false, 'message' => '无效的操作']);
}

function handleSend() {
    global $dataFile;
    
    // 获取 POST 数据
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['nickname']) || !isset($input['content'])) {
        echo json_encode(['success' => false, 'message' => '缺少参数']);
        return;
    }

    $nickname = trim($input['nickname']);
    $content = trim($input['content']);

    if (empty($nickname) || empty($content)) {
        echo json_encode(['success' => false, 'message' => '内容不能为空']);
        return;
    }

    // 读取现有消息
    $messages = json_decode(file_get_contents($dataFile), true) ?: [];
    
    // 生成新消息
    $newMessage = [
        'id' => time() . rand(100, 999), // 简单ID生成，生产环境建议用 UUID 或 数据库自增ID
        'nickname' => $nickname,
        'content' => $content,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    // 追加消息
    $messages[] = $newMessage;

    // 限制消息数量，防止文件过大 (保留最近 1000 条)
    if (count($messages) > 100) {
        $messages = array_slice($messages, -1000);
    }

    // 写入文件
    // 使用 LOCK_EX 确保并发写入安全
    if (file_put_contents($dataFile, json_encode($messages, JSON_UNESCAPED_UNICODE), LOCK_EX)) {
        echo json_encode(['success' => true, 'message' => '发送成功']);
    } else {
        echo json_encode(['success' => false, 'message' => '写入文件失败']);
    }
}

function handleGet() {
    global $dataFile;
    
    $lastId = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;
    
    $messages = json_decode(file_get_contents($dataFile), true) ?: [];
    
    $newMessages = [];
    
    foreach ($messages as $msg) {
        // 简单比较 ID (注意：这里的 ID 是时间戳+随机数，严格来说应该用索引或真正的时间比较)
        // 为了演示简单，我们假设 ID 越大越新。
        // 更稳健的做法是比较 timestamp
        if ($msg['id'] > $lastId) {
            $newMessages[] = $msg;
        }
    }
    
    echo json_encode([
        'success' => true,
        'messages' => $newMessages
    ]);
}
