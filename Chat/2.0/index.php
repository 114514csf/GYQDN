
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
    <title>微聊</title>
    <!-- 引入 Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- 引入 Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* 自定义滚动条样式 */
        ::-webkit-scrollbar {
            width: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1; 
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1; 
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8; 
        }
        .message-enter {
            animation: slideIn 0.3s ease-out forwards;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-gray-100 h-screen flex flex-col font-sans text-gray-800">

    <!-- 头部导航 -->
    <header class="bg-white shadow-md p-4 flex justify-between items-center z-10">
        <div class="flex items-center space-x-2">
            <i class="fa-solid fa-comments text-blue-500 text-2xl"></i>
            <h1 class="text-xl font-bold text-gray-700">微聊</h1>
        </div>
        <div class="text-sm text-gray-500">
            <span id="connection-status" class="flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-green-500"></span> 在线
            </span>
        </div>
    </header>

    <!-- 主聊天区域 -->
    <main class="flex-1 overflow-hidden flex flex-col max-w-3xl mx-auto w-full bg-white shadow-lg my-4 rounded-lg">
        
        <!-- 消息列表容器 -->
        <div id="chat-box" class="flex-1 overflow-y-auto p-4 space-y-4 bg-slate-50">
            <!-- 消息将通过 JS 动态插入这里 -->
            <div class="text-center text-gray-400 text-sm mt-10" id="empty-state">
                <i class="fa-regular fa-paper-plane text-4xl mb-2"></i>
                <p>暂无消息，开始聊天吧！</p>
            </div>
        </div>

        <!-- 输入区域 -->
        <div class="p-4 bg-white border-t border-gray-200">
            <form id="chat-form" class="flex gap-2">
                <input type="text" id="username" placeholder="昵称" class="w-1/4 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition" required maxlength="10">
                <input type="text" id="message-input" placeholder="输入消息..." class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition" required autocomplete="off">
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg font-medium transition duration-200 flex items-center gap-2">
                    <span>发送</span>
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </form>
        </div>
    </main>

    <!-- 页脚 -->
    <footer class="text-center text-gray-400 text-xs py-2">
        &copy; 2026 PHP Micro Chat. Data stored in JSON.
    </footer>

    <script>
        const chatBox = document.getElementById('chat-box');
        const chatForm = document.getElementById('chat-form');
        const usernameInput = document.getElementById('username');
        const messageInput = document.getElementById('message-input');
        const emptyState = document.getElementById('empty-state');
        
        let lastMessageId = 0;
        let isFetching = false;

        // 初始化：从 LocalStorage 恢复用户名
        if(localStorage.getItem('chat_username')) {
            usernameInput.value = localStorage.getItem('chat_username');
        }

        // 保存用户名到 LocalStorage
        usernameInput.addEventListener('change', () => {
            localStorage.setItem('chat_username', usernameInput.value);
        });

        // 发送消息
        chatForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const username = usernameInput.value.trim();
            const message = messageInput.value.trim();

            if (!username || !message) return;

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'send',
                        username: username,
                        message: message
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    messageInput.value = '';
                    // 立即刷新消息列表以显示新消息
                    fetchMessages(true);
                } else {
                    alert('发送失败: ' + result.error);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('网络错误，请稍后重试');
            }
        });

        // 获取消息
        async function fetchMessages(force = false) {
            if (isFetching && !force) return;
            isFetching = true;

            try {
                // 使用长轮询模拟实时性，或者简单轮询
                // 这里为了演示简单，使用带 last_id 参数的请求
                const response = await fetch(`api.php?action=get&last_id=${lastMessageId}`);
                const data = await response.json();

                if (data.success && data.messages.length > 0) {
                    if (emptyState) emptyState.style.display = 'none';
                    
                    data.messages.forEach(msg => {
                        appendMessage(msg);
                        lastMessageId = msg.id; // 更新最后一条消息ID
                    });
                    
                    // 自动滚动到底部
                    chatBox.scrollTop = chatBox.scrollHeight;
                }
            } catch (error) {
                console.error('Fetch error:', error);
            } finally {
                isFetching = false;
            }
        }

        // 渲染单条消息
        function appendMessage(msg) {
            const isMe = msg.username === usernameInput.value;
            const div = document.createElement('div');
            div.className = `flex w-full message-enter ${isMe ? 'justify-end' : 'justify-start'}`;
            
            const time = new Date(msg.timestamp * 1000).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});

            div.innerHTML = `
                <div class="flex flex-col ${isMe ? 'items-end' : 'items-start'} max-w-[70%]">
                    <div class="flex items-baseline gap-2 mb-1">
                        <span class="text-xs font-bold text-gray-600">${escapeHtml(msg.username)}</span>
                        <span class="text-[10px] text-gray-400">${time}</span>
                    </div>
                    <div class="px-4 py-2 rounded-2xl shadow-sm text-sm break-words ${
                        isMe 
                        ? 'bg-blue-500 text-white rounded-br-none' 
                        : 'bg-white text-gray-800 border border-gray-200 rounded-bl-none'
                    }">
                        ${escapeHtml(msg.message)}
                    </div>
                </div>
            `;
            chatBox.appendChild(div);
        }

        // 简单的 HTML 转义防止 XSS
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        // 启动轮询
        setInterval(() => fetchMessages(), 2000);
        // 初始加载
        fetchMessages(true);

    </script>
</body>
</html>
