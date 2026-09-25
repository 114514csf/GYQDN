
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
    <title>帖子详情 - 梦河论坛</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .reply-item { border-left: 3px solid #e2e8f0; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">

    <nav class="bg-indigo-600 text-white shadow-lg">
        <div class="container mx-auto px-4 py-4 flex justify-between items-center">
            <a href="index.php" class="text-xl font-bold flex items-center gap-2">
                <i class="fas fa-home"></i> 首页
            </a>
            <a href="post.php" class="bg-white text-indigo-600 px-3 py-1 rounded text-sm font-semibold hover:bg-indigo-50">
                发帖
            </a>
        </div>
    </nav>

    <main class="container mx-auto px-4 py-8 max-w-4xl flex-grow">
        <!-- 帖子主体 -->
        <div id="post-detail" class="bg-white rounded-lg shadow-lg overflow-hidden mb-8">
            <!-- 动态加载内容 -->
            <div class="p-8 text-center text-gray-500">加载中...</div>
        </div>

        <!-- 回复区域 -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-2">
                <i class="fas fa-comments text-indigo-500"></i> 回复列表
            </h3>
            
            <div id="replies-list" class="space-y-6 mb-8">
                <!-- 回复列表动态加载 -->
            </div>

            <!-- 回复表单 -->
            <div class="bg-gray-50 p-6 rounded-lg border border-gray-200">
                <h4 class="font-bold text-gray-700 mb-4">发表回复</h4>
                <form id="replyForm" class="space-y-4">
                    <input type="hidden" id="current-post-id">
                    <div>
                        <input class="shadow-sm border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-1 focus:ring-indigo-500" 
                               id="reply-author" type="text" placeholder="你的名字" required>
                    </div>
                    <div>
                        <textarea class="shadow-sm border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-1 focus:ring-indigo-500 h-24" 
                                  id="reply-content" placeholder="写下你的回复..." required></textarea>
                    </div>
                    <div class="text-right">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded transition">
                            提交回复
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        const urlParams = new URLSearchParams(window.location.search);
        const postId = urlParams.get('id');

        if (!postId) {
            window.location.href = 'index.php';
        }

        // 加载帖子详情
        async function loadPostDetail() {
            try {
                const response = await fetch(`api.php?action=detail&id=${postId}`);
                const post = await response.json();

                if (!post || post.error) {
                    document.getElementById('post-detail').innerHTML = `
                        <div class="p-10 text-center">
                            <i class="fas fa-exclamation-circle text-4xl text-red-500 mb-4"></i>
                            <h2 class="text-xl font-bold">帖子不存在</h2>
                            <a href="index.php" class="text-indigo-600 mt-4 inline-block">返回首页</a>
                        </div>
                    `;
                    return;
                }

                document.getElementById('current-post-id').value = post.id;
                
                // 渲染主帖
                const detailContainer = document.getElementById('post-detail');
                detailContainer.innerHTML = `
                    <div class="p-8">
                        <div class="flex justify-between items-start mb-4">
                            <h1 class="text-3xl font-bold text-gray-900">${post.title}</h1>
                            <span class="text-sm text-gray-500 bg-gray-100 px-3 py-1 rounded-full">${post.created_at}</span>
                        </div>
                        <div class="flex items-center gap-3 mb-6 text-sm text-gray-600 border-b pb-4">
                            <i class="fas fa-user-circle text-xl text-indigo-500"></i>
                            <span class="font-semibold">${post.author}</span>
                            <span class="text-gray-400">|</span>
                            <span>ID: #${post.id}</span>
                        </div>
                        <div class="prose max-w-none text-gray-800 leading-relaxed whitespace-pre-wrap">
                            ${post.content}
                        </div>
                    </div>
                `;

                // 渲染回复
                renderReplies(post.replies || []);

            } catch (error) {
                console.error(error);
                document.getElementById('post-detail').innerHTML = '<p class="p-8 text-red-500">加载出错</p>';
            }
        }

        function renderReplies(replies) {
            const list = document.getElementById('replies-list');
            if (replies.length === 0) {
                list.innerHTML = '<p class="text-gray-500 text-center italic">暂无回复，抢沙发！</p>';
                return;
            }

            list.innerHTML = replies.map(reply => `
                <div class="reply-item pl-4 py-2">
                    <div class="flex justify-between items-center mb-2">
                        <span class="font-bold text-indigo-700 text-sm">${reply.author}</span>
                        <span class="text-xs text-gray-400">${reply.created_at}</span>
                    </div>
                    <p class="text-gray-700 text-sm whitespace-pre-wrap">${reply.content}</p>
                </div>
            `).join('');
        }

        // 提交回复
        document.getElementById('replyForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const postId = document.getElementById('current-post-id').value;
            const author = document.getElementById('reply-author').value;
            const content = document.getElementById('reply-content').value;
            const btn = this.querySelector('button');

            btn.disabled = true;
            btn.innerText = '提交中...';

            const formData = new FormData();
            formData.append('action', 'reply');
            formData.append('post_id', postId);
            formData.append('author', author);
            formData.append('content', content);

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    document.getElementById('reply-content').value = '';
                    loadPostDetail(); // 重新加载以显示新回复
                } else {
                    alert('回复失败: ' + result.message);
                }
            } catch (error) {
                alert('网络错误');
            } finally {
                btn.disabled = false;
                btn.innerText = '提交回复';
            }
        });

        // 初始化
        loadPostDetail();
    </script>
</body>
</html>
