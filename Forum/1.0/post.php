
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
    <title>发布新帖 - 梦河论坛</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">

    <nav class="bg-indigo-600 text-white shadow-lg mb-8">
        <div class="container mx-auto px-4 py-4 flex justify-between items-center">
            <a href="index.php" class="text-2xl font-bold flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> 返回首页
            </a>
            <span class="font-semibold">发布新帖</span>
        </div>
    </nav>

    <main class="container mx-auto px-4 max-w-3xl flex-grow">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 border-b pb-2">填写帖子信息</h2>
            
            <form id="postForm" class="space-y-6">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="author">
                        你的名字
                    </label>
                    <input class="shadow appearance-none border rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500" 
                           id="author" type="text" placeholder="例如：张三" required>
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="title">
                        标题
                    </label>
                    <input class="shadow appearance-none border rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500" 
                           id="title" type="text" placeholder="请输入帖子标题" required>
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="content">
                        内容
                    </label>
                    <textarea class="shadow appearance-none border rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 h-48" 
                              id="content" placeholder="分享你的想法..." required></textarea>
                </div>

                <div class="flex items-center justify-end gap-4">
                    <a href="index.php" class="text-gray-500 hover:text-gray-700 font-semibold">取消</a>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-6 rounded focus:outline-none focus:shadow-outline transition duration-300">
                        <i class="fas fa-paper-plane mr-2"></i> 发布帖子
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        document.getElementById('postForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const author = document.getElementById('author').value;
            const title = document.getElementById('title').value;
            const content = document.getElementById('content').value;
            const btn = this.querySelector('button[type="submit"]');
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 发布中...';

            const formData = new FormData();
            formData.append('action', 'create');
            formData.append('author', author);
            formData.append('title', title);
            formData.append('content', content);

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    alert('发布成功！');
                    window.location.href = 'view.php?id=' + result.id;
                } else {
                    alert('发布失败: ' + result.message);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i> 发布帖子';
                }
            } catch (error) {
                alert('网络错误，请稍后重试');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i> 发布帖子';
            }
        });
    </script>
</body>
</html>
