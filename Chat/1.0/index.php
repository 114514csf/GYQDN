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
    <title> 微聊 </title>
    <!-- 引入 Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- 引入 Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* 自定义滚动条 */
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
        .message-bubble {
            max-width: 70%;
            word-wrap: break-word;
        }
        /* 动画效果 */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeIn 0.3s ease-out forwards;
        }
    </style>
</head>
<body class="bg-gray-100 h-screen flex flex-col font-sans text-gray-800">

    <?php
    session_start();
    
    // 处理登录逻辑
    if (isset($_POST['login_nickname'])) {
        $_SESSION['nickname'] = htmlspecialchars($_POST['login_nickname']);
        header("Location: index.php");
        exit;
    }

    // 处理登出
    if (isset($_GET['logout'])) {
        session_destroy();
        header("Location: index.php");
        exit;
    }

    // 如果未登录，显示登录界面
    if (!isset($_SESSION['nickname'])) {
    ?>
    <div class="flex items-center justify-center h-full bg-gradient-to-br from-blue-500 to-purple-600">
        <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-md mx-4">
            <div class="text-center mb-6">
                <i class="fa-solid fa-comments text-5xl text-blue-500 mb-4"></i>
                <h1 class="text-2xl font-bold text-gray-800">欢迎加入微聊</h1>
                <p class="text-gray-500 text-sm mt-2">请输入昵称开始聊天</p>
            </div>
            <form method="POST" class="space-y-4">
                <div>
                    <input type="text" name="login_nickname" placeholder="您的昵称" required 
                        class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition duration-200">
                </div>
                <button type="submit" 
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition duration-200 shadow-lg transform hover:-translate-y-1">
                    进入聊天室
                </button>
            </form>
        </div>
    </div>
    <?php
    } else {
        // 已登录，显示聊天界面
        $nickname = $_SESSION['nickname'];
    ?>
    <div class="flex flex-col h-full max-w-4xl mx-auto w-full bg-white shadow-xl md:my-4 md:rounded-xl overflow-hidden">
        
        <!-- 头部导航 -->
        <header class="bg-white border-b border-gray-200 p-4 flex justify-between items-center z-10">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-400 to-blue-600 flex items-center justify-center text-white font-bold shadow-md">
                    <?php echo strtoupper(substr($nickname, 0, 1)); ?>
                </div>
                <div>
                    <h2 class="font-bold text-lg text-gray-800">微聊大厅</h2>
                    <p class="text-xs text-green-500 flex items-center">
                        <span class="w-2 h-2 bg-green-500 rounded-full mr-1"></span> 在线
                    </p>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-sm text-gray-500 hidden sm:inline">当前用户: <strong class="text-blue-600"><?php echo $nickname; ?></strong></span>
                <a href="?logout=1" class="text-gray-400 hover:text-red-500 transition duration-200" title="退出登录">
                    <i class="fa-solid fa-right-from-bracket text-xl"></i>
                </a>
            </div>
        </header>

        <!-- 聊天内容区域 -->
        <main id="chat-container" class="flex-1 overflow-y-auto p-4 bg-gray-50 space-y-4 scroll-smooth">
            <!-- 消息将通过 JS 动态加载到这里 -->
            <div class="text-center text-gray-400 text-sm mt-10" id="loading-tip">
                <i class="fa-solid fa-circle-notch fa-spin"></i> 正在加载历史记录...
            </div>
        </main>

        <!-- 底部输入框 -->
        <footer class="bg-white border-t border-gray-200 p-4">
            <form id="chat-form" class="flex items-end space-x-2">
                <div class="flex-1 relative">
                    <textarea id="message-input" rows="1" placeholder="输入消息..." 
                        class="w-full px-4 py-3 bg-gray-100 rounded-xl border-none focus:ring-2 focus:ring-blue-300 focus:bg-white outline-none resize-none transition duration-200"
                        style="min-height: 48px; max-height: 120px;"></textarea>
                </div>
                <button type="submit" 
                    class="bg-blue-600 hover:bg-blue-700 text-white p-3 rounded-xl shadow-md transition duration-200 flex items-center justify-center w-12 h-12">
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </form>
        </footer>
    </div>

    <script>
        const nickname = "<?php echo $nickname; ?>";
        const chatContainer = document.getElementById('chat-container');
        const chatForm = document.getElementById('chat-form');
        const messageInput = document.getElementById('message-input');
        let lastMessageId = 0;
        let isFetching = false;

        // 自动调整 textarea 高度
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });

        // 发送消息
        chatForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const content = messageInput.value.trim();
            if (!content) return;

            // 乐观更新 UI
            appendMessage({
                id: Date.now(), // 临时ID
                nickname: nickname,
                content: content,
                timestamp: new Date().toISOString(),
                isMe: true
            });
            
            messageInput.value = '';
            messageInput.style.height = '48px';
            scrollToBottom();

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'send', nickname: nickname, content: content })
                });
                const result = await response.json();
                if (!result.success) {
                    alert('发送失败: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
            }
        });

        // 获取消息
        async function fetchMessages() {
            if (isFetching) return;
            isFetching = true;
            
            try {
                const response = await fetch(`api.php?action=get&last_id=${lastMessageId}`);
                const data = await response.json();
                
                if (data.success && data.messages.length > 0) {
                    document.getElementById('loading-tip')?.remove();
                    
                    data.messages.forEach(msg => {
                        // 避免重复添加自己刚刚乐观更新的消息
                        if (msg.isMe && msg.nickname === nickname) {
                            // 这里简单处理，实际项目中应该用服务器返回的真实ID替换临时ID
                            // 为简化演示，我们只添加非自己的消息，或者重新渲染整个列表（性能较差但数据一致）
                            // 更好的方式是：只追加服务器返回的新消息，如果是自己的消息且已存在则跳过
                        } else {
                            appendMessage({...msg, isMe: msg.nickname === nickname});
                        }
                    });
                    
                    // 更新最后一条消息ID
                    const lastMsg = data.messages[data.messages.length - 1];
                    if (lastMsg.id > lastMessageId) {
                        lastMessageId = lastMsg.id;
                    }
                    
                    // 只有当用户不在底部时才自动滚动？通常聊天软件总是滚动到底部
                    scrollToBottom();
                }
            } catch (error) {
                console.error('Fetch error:', error);
            } finally {
                isFetching = false;
            }
        }

        // 追加消息到 DOM
        function appendMessage(msg) {
            // 检查是否已存在（防止重复）
            if (document.getElementById(`msg-${msg.id}`)) return;

            const div = document.createElement('div');
            div.id = `msg-${msg.id}`;
            div.className = `flex w-full animate-fade-in ${msg.isMe ? 'justify-end' : 'justify-start'}`;
            
            const time = new Date(msg.timestamp).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            
            div.innerHTML = `
                <div class="flex flex-col ${msg.isMe ? 'items-end' : 'items-start'} max-w-[70%]">
                    <div class="flex items-center space-x-2 mb-1 ${msg.isMe ? 'flex-row-reverse space-x-reverse' : ''}">
                        <span class="text-xs text-gray-500 font-medium">${escapeHtml(msg.nickname)}</span>
                        <span class="text-[10px] text-gray-400">${time}</span>
                    </div>
                    <div class="message-bubble px-4 py-2 rounded-2xl shadow-sm text-sm leading-relaxed ${
                        msg.isMe 
                        ? 'bg-blue-600 text-white rounded-tr-none' 
                        : 'bg-white text-gray-800 border border-gray-100 rounded-tl-none'
                    }">
                        ${escapeHtml(msg.content)}
                    </div>
                </div>
            `;
            
            chatContainer.appendChild(div);
        }

        function scrollToBottom() {
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }

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

        // 初始加载
        fetchMessages();
        
        // 轮询间隔 (毫秒)
        setInterval(fetchMessages, 1000);

        // 监听 Enter 键发送 (Shift+Enter 换行)
        messageInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                chatForm.dispatchEvent(new Event('submit'));
            }
        });
    </script>
    <?php } ?>
</body>
</html>
