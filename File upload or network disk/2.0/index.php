
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>轻量级云盘 - LightCloud</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f3f4f6; }
        .file-item:hover { background-color: #e5e7eb; transition: all 0.2s; }
        .modal { display: none; position: fixed; z-index: 50; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: 8px; }
        .drag-active { border-color: #3b82f6; background-color: #eff6ff; }
        /* 自定义滚动条 */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #888; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #555; }
    </style>
</head>
<body class="h-screen flex flex-col">

    <!-- 顶部导航 -->
    <header class="bg-white shadow-sm z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <i class="fa-solid fa-cloud text-blue-500 text-2xl"></i>
                <h1 class="text-xl font-bold text-gray-800">LightCloud</h1>
            </div>
            <div class="flex items-center gap-4">
                <button onclick="openUploadModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow transition flex items-center gap-2">
                    <i class="fa-solid fa-upload"></i> 上传文件
                </button>
                <button onclick="refreshFiles()" class="text-gray-600 hover:text-blue-600 p-2">
                    <i class="fa-solid fa-rotate-right"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- 主体内容 -->
    <main class="flex-1 overflow-hidden flex">
        <!-- 侧边栏 (模拟) -->
        <aside class="w-64 bg-white border-r hidden md:block">
            <div class="p-4">
                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">存储概览</div>
                <div class="w-full bg-gray-200 rounded-full h-2.5 mb-2">
                    <div class="bg-blue-600 h-2.5 rounded-full" style="width: 45%"></div>
                </div>
                <div class="text-sm text-gray-600">已用 4.5 GB / 10 GB</div>
            </div>
            <nav class="mt-4 px-2 space-y-1">
                <a href="#" class="group flex items-center px-2 py-2 text-sm font-medium rounded-md bg-blue-50 text-blue-700">
                    <i class="fa-solid fa-folder-open mr-3 text-blue-500"></i> 全部文件
                </a>
                <a href="#" class="group flex items-center px-2 py-2 text-sm font-medium rounded-md text-gray-600 hover:bg-gray-50 hover:text-gray-900">
                    <i class="fa-solid fa-image mr-3 text-gray-400 group-hover:text-gray-500"></i> 图片
                </a>
                <a href="#" class="group flex items-center px-2 py-2 text-sm font-medium rounded-md text-gray-600 hover:bg-gray-50 hover:text-gray-900">
                    <i class="fa-solid fa-file-pdf mr-3 text-gray-400 group-hover:text-gray-500"></i> 文档
                </a>
            </nav>
        </aside>

        <!-- 文件列表区域 -->
        <section class="flex-1 flex flex-col bg-gray-50 overflow-hidden">
            <!-- 面包屑导航 -->
            <div class="bg-white border-b px-6 py-3 flex items-center text-sm text-gray-600">
                <span class="cursor-pointer hover:text-blue-600" onclick="navigateTo('/')">根目录</span>
                <span id="breadcrumb-path" class="ml-2"></span>
            </div>

            <!-- 工具栏 -->
            <div class="px-6 py-3 flex justify-between items-center border-b bg-white">
                <div class="text-sm text-gray-500" id="file-count">共 0 个文件</div>
                <div class="flex gap-2">
                    <input type="text" id="search-input" placeholder="搜索文件..." class="border rounded px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <!-- 文件网格/列表 -->
            <div class="flex-1 overflow-y-auto p-6" id="file-container">
                <!-- 动态加载文件 -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4" id="file-grid">
                    <!-- 文件项模板将由JS生成 -->
                </div>
                
                <!-- 空状态 -->
                <div id="empty-state" class="hidden flex flex-col items-center justify-center h-full text-gray-400">
                    <i class="fa-regular fa-folder-open text-6xl mb-4"></i>
                    <p>暂无文件，请点击上传</p>
                </div>
            </div>
        </section>
    </main>

    <!-- 上传模态框 -->
    <div id="upload-modal" class="modal">
        <div class="modal-content relative">
            <span class="absolute top-2 right-4 text-2xl cursor-pointer text-gray-500 hover:text-black" onclick="closeUploadModal()">&times;</span>
            <h2 class="text-lg font-bold mb-4">上传文件</h2>
            
            <div id="drop-zone" class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center cursor-pointer hover:border-blue-500 transition">
                <i class="fa-solid fa-cloud-arrow-up text-4xl text-gray-400 mb-3"></i>
                <p class="text-gray-600 mb-2">拖拽文件到此处，或点击选择</p>
                <p class="text-xs text-gray-400">支持多文件、文件夹上传 (最大 20MB/文件)</p>
                <input type="file" id="file-input" multiple webkitdirectory directory class="hidden">
                <button onclick="document.getElementById('file-input').click()" class="mt-4 bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded text-sm">
                    选择文件/文件夹
                </button>
            </div>

            <div id="upload-progress-area" class="mt-4 hidden">
                <h3 class="text-sm font-semibold mb-2">上传队列</h3>
                <div id="progress-list" class="space-y-2 max-h-40 overflow-y-auto"></div>
            </div>
        </div>
    </div>

    <!-- 操作菜单模态框 (重命名/移动) -->
    <div id="action-modal" class="modal">
        <div class="modal-content">
            <h3 id="action-title" class="text-lg font-bold mb-4">操作</h3>
            <input type="text" id="action-input" class="w-full border rounded px-3 py-2 mb-4 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <div class="flex justify-end gap-2">
                <button onclick="closeActionModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded">取消</button>
                <button id="action-confirm-btn" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">确认</button>
            </div>
        </div>
    </div>

    <!-- Toast 提示 -->
    <div id="toast" class="fixed bottom-5 right-5 bg-gray-800 text-white px-6 py-3 rounded shadow-lg transform translate-y-20 opacity-0 transition-all duration-300 z-50">
        操作成功
    </div>

    <script src="app.js"></script>
</body>
</html>
