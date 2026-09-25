
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
    <title>梦河论坛</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f3f4f6; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <!-- 导航栏 -->
    <nav class="bg-indigo-600 text-white shadow-lg">
        <div class="container mx-auto px-4 py-4 flex justify-between items-center">
            <a href="index.php" class="text-2xl font-bold flex items-center gap-2">
                <i class="fas fa-comments"></i> 梦河论坛
            </a>
            <div>
                <a href="post.php" class="bg-white text-indigo-600 px-4 py-2 rounded-full font-semibold hover:bg-indigo-50 transition shadow">
                    <i class="fas fa-pen-nib"></i> 发布新帖
                </a>
            </div>
        </div>
    </nav>

    <!-- 主内容区 -->
    <main class="container mx-auto px-4 py-8 flex-grow">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800">最新帖子</h2>
            <button onclick="loadPosts()" class="text-indigo-600 hover:text-indigo-800">
                <i class="fas fa-sync-alt"></i> 刷新
            </button>
        </div>

        <!-- 帖子列表容器 -->
        <div id="posts-container" class="grid gap-6 md:grid-cols-1 lg:grid-cols-2">
            <!-- 加载中状态 -->
            <div class="col-span-full text-center py-10 text-gray-500">
                <i class="fas fa-spinner fa-spin text-3xl"></i>
                <p class="mt-2">加载帖子中...</p>
            </div>
        </div>
    </main>

    <!-- 页脚 -->
    <footer class="bg-gray-800 text-gray-400 py-6 text-center">
        <p>&copy; 2026 梦河论坛</p>
    </footer>

    <script>
        // 加载帖子列表
        async function loadPosts() {
            const container = document.getElementById('posts-container');
            try {
                const response = await fetch('api.php?action=list');
                const posts = await response.json();
                
                container.innerHTML = '';
                
                if (posts.length === 0) {
                    container.innerHTML = `
                        <div class="col-span-full text-center py-10 bg-white rounded-lg shadow">
                            <i class="fas fa-inbox text-4xl text-gray-300 mb-3"></i>
                            <p class="text-gray-500">暂无帖子，快来发布第一个吧！</p>
                        </div>
                    `;
                    return;
                }

                posts.forEach(post => {
                    const card = document.createElement('div');
                    card.className = 'bg-white rounded-lg shadow p-6 card-hover transition duration-300 border-l-4 border-indigo-500 cursor-pointer';
                    card.onclick = () => window.location.href = `view.php?id=${post.id}`;
                    
                    // 截取内容预览
                    const previewContent = post.content.length > 100 ? post.content.substring(0, 100) + '...' : post.content;

                    card.innerHTML = `
                        <div class="flex justify-between items-start mb-2">
                            <h3 class="text-xl font-bold text-gray-800 hover:text-indigo-600 truncate">${post.title}</h3>
                            <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">${post.created_at}</span>
                        </div>
                        <p class="text-gray-600 mb-4 text-sm line-clamp-3">${previewContent}</p>
                        <div class="flex justify-between items-center text-sm text-gray-500 border-t pt-3">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-user-circle"></i>
                                <span>${post.author}</span>
                            </div>
                            <div class="flex items-center gap-4">
                                <span><i class="fas fa-comment-alt"></i> ${post.replies ? post.replies.length : 0} 回复</span>
                                <span class="text-indigo-500 font-medium">查看详情 &rarr;</span>
                            </div>
                        </div>
                    `;
                    container.appendChild(card);
                });
            } catch (error) {
                console.error('Error:', error);
                container.innerHTML = '<p class="text-red-500 text-center">加载失败，请检查服务器配置。</p>';
            }
        }

        // 页面加载时执行
        document.addEventListener('DOMContentLoaded', loadPosts);
    </script>
</body>
</html>
