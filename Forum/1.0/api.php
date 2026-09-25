
<?php
/**
 * api.php - 论坛后端逻辑处理
 * 负责读写JSON数据文件
 */

header('Content-Type: application/json; charset=utf-8');

// 数据目录
$DATA_DIR = __DIR__ . '/data';
$POSTS_FILE = $DATA_DIR . '/posts.json';
$USERS_FILE = $DATA_DIR . '/users.json'; // 简单模拟用户，实际项目中应更复杂

// 确保数据目录存在
if (!is_dir($DATA_DIR)) {
    mkdir($DATA_DIR, 0755, true);
}

// 初始化文件如果不存在
if (!file_exists($POSTS_FILE)) {
    file_put_contents($POSTS_FILE, json_encode([]));
}

/**
 * 读取所有帖子
 */
function getPosts() {
    global $POSTS_FILE;
    $content = file_get_contents($POSTS_FILE);
    return $content ? json_decode($content, true) : [];
}

/**
 * 保存帖子数组
 */
function savePosts($posts) {
    global $POSTS_FILE;
    // 使用 LOCK_EX 防止并发写入冲突
    file_put_contents($POSTS_FILE, json_encode($posts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

/**
 * 获取单个帖子
 */
function getPostById($id) {
    $posts = getPosts();
    foreach ($posts as $post) {
        if ($post['id'] == $id) {
            return $post;
        }
    }
    return null;
}

/**
 * 创建新帖子
 */
function createPost($title, $content, $author) {
    if (empty($title) || empty($content)) {
        return ['success' => false, 'message' => '标题和内容不能为空'];
    }

    $posts = getPosts();
    $newId = count($posts) > 0 ? max(array_column($posts, 'id')) + 1 : 1;
    
    $newPost = [
        'id' => $newId,
        'title' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
        'content' => htmlspecialchars($content, ENT_QUOTES, 'UTF-8'),
        'author' => htmlspecialchars($author, ENT_QUOTES, 'UTF-8'),
        'created_at' => date('Y-m-d H:i:s'),
        'replies' => []
    ];

    array_unshift($posts, $newPost); // 新帖子排在前面
    savePosts($posts);

    return ['success' => true, 'id' => $newId];
}

/**
 * 添加回复
 */
function addReply($postId, $content, $author) {
    if (empty($content)) {
        return ['success' => false, 'message' => '回复内容不能为空'];
    }

    $posts = getPosts();
    $found = false;
    
    foreach ($posts as &$post) {
        if ($post['id'] == $postId) {
            $reply = [
                'id' => count($post['replies']) + 1,
                'content' => htmlspecialchars($content, ENT_QUOTES, 'UTF-8'),
                'author' => htmlspecialchars($author, ENT_QUOTES, 'UTF-8'),
                'created_at' => date('Y-m-d H:i:s')
            ];
            $post['replies'][] = $reply;
            $found = true;
            break;
        }
    }

    if ($found) {
        savePosts($posts);
        return ['success' => true];
    } else {
        return ['success' => false, 'message' => '帖子不存在'];
    }
}

// 路由处理
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        echo json_encode(getPosts());
        break;
        
    case 'detail':
        $id = $_GET['id'] ?? 0;
        $post = getPostById($id);
        if ($post) {
            echo json_encode($post);
        } else {
            echo json_encode(['error' => 'Post not found']);
        }
        break;

    case 'create':
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';
        $author = $_POST['author'] ?? '匿名游客';
        $result = createPost($title, $content, $author);
        echo json_encode($result);
        break;

    case 'reply':
        $postId = $_POST['post_id'] ?? 0;
        $content = $_POST['content'] ?? '';
        $author = $_POST['author'] ?? '匿名游客';
        $result = addReply($postId, $content, $author);
        echo json_encode($result);
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}
