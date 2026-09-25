<?php
/**
 * PHP 微聊后端 API
 * 功能：处理消息的发送和获取，数据存储在 messages.json
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$dataFile = __DIR__ . '/messages.json';
$maxMessages = 100; // 限制最大存储消息数，防止文件过大

// 初始化文件如果不存在
if (!file_exists($dataFile)) {
    file_put_contents($dataFile, json_encode([]));
}

/**
 * 读取所有消息
 */
function getMessages() {
    global $dataFile;
    $content = file_get_contents($dataFile);
    if ($content === false) {
        return [];
    }
    $messages = json_decode($content, true);
    return is_array($messages) ? $messages : [];
}

/**
 * 保存消息
 */
function saveMessages($messages) {
    global $dataFile, $maxMessages;
    
    // 如果消息过多，保留最新的 N 条
    if (count($messages) > $maxMessages) {
        $messages = array_slice($messages, -$maxMessages);
    }
    
    // 重新索引数组以确保 JSON 格式正确
    $messages = array_values($messages);
    
    file_put_contents($dataFile, json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

/**
 * 生成唯一ID
 */
function generateId() {
    return uniqid('msg_', true);
}

// 路由处理
$action = $_GET['action'] ?? $_POST['action'] ?? 'send';

switch ($action) {
    case 'get':
        handleGet();
        break;
    case 'send':
        handleSend();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

/**
 * 处理获取消息请求
 */
function handleGet() {
    $lastId = $_GET['last_id'] ?? '';
    $messages = getMessages();
    $newMessages = [];

    foreach ($messages as $msg) {
        // 如果 last_id 为空，返回所有消息（或最近几条）
        // 否则只返回 ID 大于 last_id 的消息
        if (empty($lastId) || $msg['id'] > $lastId) {
            $newMessages[] = $msg;
        }
    }

    echo json_encode([
        'success' => true,
        'messages' => $newMessages
    ]);
}

/**
 * 处理发送消息请求
 */
function handleSend() {
    // 获取 POST 原始数据
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['username']) || !isset($input['message'])) {
        echo json_encode(['success' => false, 'error' => 'Missing parameters']);
        return;
    }

    $username = trim($input['username']);
    $message = trim($input['message']);

    if (empty($username) || empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Username and message cannot be empty']);
        return;
    }

    // 简单安全过滤
    $username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    $newMessage = [
        'id' => generateId(),
        'username' => $username,
        'message' => $message,
        'timestamp' => time()
    ];

    $messages = getMessages();
    $messages[] = $newMessage;
    saveMessages($messages);

    echo json_encode(['success' => true, 'message' => $newMessage]);
}
