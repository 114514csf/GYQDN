
// 全局状态
let currentPath = '/';
let filesData = [];

// 初始化
document.addEventListener('DOMContentLoaded', () => {
    loadFiles();
    setupDragAndDrop();
    setupSearch();
});

// API 基础路径
const API_BASE = 'api.php';

// 加载文件列表
async function loadFiles() {
    try {
        const response = await fetch(`${API_BASE}?action=list&path=${encodeURIComponent(currentPath)}`);
        const result = await response.json();
        
        if (result.code === 200) {
            filesData = result.data;
            renderFiles(filesData);
            updateBreadcrumb();
        } else {
            showToast(result.message || '加载失败', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('网络错误', 'error');
    }
}

// 渲染文件列表
function renderFiles(files) {
    const grid = document.getElementById('file-grid');
    const emptyState = document.getElementById('empty-state');
    const countLabel = document.getElementById('file-count');
    
    grid.innerHTML = '';
    countLabel.textContent = `共 ${files.length} 个项目`;

    if (files.length === 0) {
        emptyState.classList.remove('hidden');
        return;
    }
    emptyState.classList.add('hidden');

    files.forEach(file => {
        const icon = getFileIcon(file.type, file.name);
        const isFolder = file.type === 'folder';
        
        const card = document.createElement('div');
        card.className = 'bg-white p-4 rounded-lg shadow-sm border border-gray-100 file-item relative group cursor-pointer';
        card.innerHTML = `
            <div class="flex items-center justify-between mb-2">
                <i class="${icon} text-3xl ${isFolder ? 'text-yellow-500' : 'text-blue-400'}"></i>
                <div class="relative">
                    <button onclick="event.stopPropagation(); showContextMenu(event, '${file.name}', '${file.type}')" class="text-gray-400 hover:text-gray-600 p-1">
                        <i class="fa-solid fa-ellipsis-vertical"></i>
                    </button>
                    <!-- 下拉菜单 -->
                    <div id="menu-${file.name}" class="hidden absolute right-0 mt-1 w-32 bg-white border rounded shadow-lg z-20 text-sm">
                        ${!isFolder ? `<a href="#" onclick="downloadFile('${file.name}')" class="block px-4 py-2 hover:bg-gray-100"><i class="fa-solid fa-download mr-2"></i>下载</a>` : ''}
                        <a href="#" onclick="renameItem('${file.name}', '${file.type}')" class="block px-4 py-2 hover:bg-gray-100"><i class="fa-solid fa-pen mr-2"></i>重命名</a>
                        <a href="#" onclick="deleteItem('${file.name}', '${file.type}')" class="block px-4 py-2 hover:bg-red-50 text-red-600"><i class="fa-solid fa-trash mr-2"></i>删除</a>
                    </div>
                </div>
            </div>
            <div class="truncate font-medium text-gray-700" title="${file.name}">${file.name}</div>
            <div class="text-xs text-gray-400 mt-1">${isFolder ? '文件夹' : formatSize(file.size)}</div>
        `;
        
        // 点击文件夹进入
        if (isFolder) {
            card.onclick = () => {
                currentPath = currentPath === '/' ? `/${file.name}` : `${currentPath}/${file.name}`;
                loadFiles();
            };
        }
        
        grid.appendChild(card);
    });
}

// 获取文件图标
function getFileIcon(type, name) {
    if (type === 'folder') return 'fa-solid fa-folder';
    const ext = name.split('.').pop().toLowerCase();
    const map = {
        'jpg': 'fa-solid fa-image', 'png': 'fa-solid fa-image', 'gif': 'fa-solid fa-image',
        'pdf': 'fa-solid fa-file-pdf',
        'doc': 'fa-solid fa-file-word', 'docx': 'fa-solid fa-file-word',
        'xls': 'fa-solid fa-file-excel', 'xlsx': 'fa-solid fa-file-excel',
        'ppt': 'fa-solid fa-file-powerpoint', 'pptx': 'fa-solid fa-file-powerpoint',
        'zip': 'fa-solid fa-file-zipper', 'rar': 'fa-solid fa-file-zipper', '7z': 'fa-solid fa-file-zipper',
        'txt': 'fa-solid fa-file-lines',
        'mp3': 'fa-solid fa-music', 'wav': 'fa-solid fa-music',
        'mp4': 'fa-solid fa-video', 'avi': 'fa-solid fa-video',
        'exe': 'fa-solid fa-gear'
    };
    return map[ext] || 'fa-solid fa-file';
}

// 格式化文件大小
function formatSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// 更新面包屑
function updateBreadcrumb() {
    const pathSpan = document.getElementById('breadcrumb-path');
    if (currentPath === '/') {
        pathSpan.innerHTML = '';
    } else {
        const parts = currentPath.split('/').filter(p => p);
        let html = '';
        let accumPath = '';
        parts.forEach((part, index) => {
            accumPath += `/${part}`;
            html += `<span class="mx-1">/</span><span class="cursor-pointer hover:text-blue-600" onclick="navigateTo('${accumPath}')">${part}</span>`;
        });
        pathSpan.innerHTML = html;
    }
}

function navigateTo(path) {
    currentPath = path;
    loadFiles();
}

function refreshFiles() {
    loadFiles();
    showToast('已刷新');
}

// 搜索功能
function setupSearch() {
    const input = document.getElementById('search-input');
    input.addEventListener('input', (e) => {
        const term = e.target.value.toLowerCase();
        const filtered = filesData.filter(f => f.name.toLowerCase().includes(term));
        renderFiles(filtered);
    });
}

// 上下文菜单
function showContextMenu(e, name, type) {
    e.stopPropagation();
    // 隐藏所有其他菜单
    document.querySelectorAll('[id^="menu-"]').forEach(el => el.classList.add('hidden'));
    const menu = document.getElementById(`menu-${name}`);
    if (menu) {
        menu.classList.remove('hidden');
        // 点击外部关闭
        const closeMenu = () => {
            menu.classList.add('hidden');
            document.removeEventListener('click', closeMenu);
        };
        setTimeout(() => document.addEventListener('click', closeMenu), 0);
    }
}

// 删除文件
async function deleteItem(name, type) {
    if (!confirm(`确定要删除 ${name} 吗？`)) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('path', currentPath === '/' ? name : `${currentPath}/${name}`);
        formData.append('type', type);

        const res = await fetch(API_BASE, { method: 'POST', body: formData });
        const result = await res.json();
        
        if (result.code === 200) {
            showToast('删除成功');
            loadFiles();
        } else {
            showToast(result.message, 'error');
        }
    } catch (err) {
        showToast('操作失败', 'error');
    }
}

// 重命名
let renameTarget = null;
function renameItem(name, type) {
    renameTarget = { name, type };
    document.getElementById('action-title').innerText = '重命名';
    document.getElementById('action-input').value = name;
    document.getElementById('action-modal').style.display = 'block';
    
    const btn = document.getElementById('action-confirm-btn');
    btn.onclick = async () => {
        const newName = document.getElementById('action-input').value.trim();
        if (!newName) return;
        
        try {
            const formData = new FormData();
            formData.append('action', 'rename');
            formData.append('old_path', currentPath === '/' ? name : `${currentPath}/${name}`);
            formData.append('new_name', newName);
            
            const res = await fetch(API_BASE, { method: 'POST', body: formData });
            const result = await res.json();
            
            if (result.code === 200) {
                showToast('重命名成功');
                closeActionModal();
                loadFiles();
            } else {
                showToast(result.message, 'error');
            }
        } catch (err) {
            showToast('操作失败', 'error');
        }
    };
}

function closeActionModal() {
    document.getElementById('action-modal').style.display = 'none';
    renameTarget = null;
}

// 下载文件
function downloadFile(name) {
    const url = `${API_BASE}?action=download&path=${encodeURIComponent(currentPath === '/' ? name : `${currentPath}/${name}`)}`;
    window.location.href = url;
}

// 上传模态框逻辑
function openUploadModal() {
    document.getElementById('upload-modal').style.display = 'block';
    document.getElementById('upload-progress-area').classList.add('hidden');
    document.getElementById('progress-list').innerHTML = '';
}

function closeUploadModal() {
    document.getElementById('upload-modal').style.display = 'none';
}

function setupDragAndDrop() {
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('file-input');

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('drag-active');
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('drag-active');
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('drag-active');
        handleFiles(e.dataTransfer.files);
    });

    fileInput.addEventListener('change', (e) => {
        handleFiles(e.target.files);
    });
}

async function handleFiles(fileList) {
    if (!fileList.length) return;
    
    document.getElementById('upload-progress-area').classList.remove('hidden');
    const progressList = document.getElementById('progress-list');
    
    // 限制单个文件大小 20MB
    const MAX_SIZE = 20 * 1024 * 1024; 

    for (let i = 0; i < fileList.length; i++) {
        const file = fileList[i];
        
        // 检查大小
        if (file.size > MAX_SIZE) {
            addProgressItem(file.name, 'error', `文件过大 (>20MB)`);
            continue;
        }

        // 构造相对路径 (针对文件夹上传)
        let relativePath = file.webkitRelativePath || file.name;
        // 如果在子目录上传，需要拼接当前路径
        if (currentPath !== '/') {
             // 简单处理：如果webkitRelativePath存在，它通常包含完整树结构，这里我们假设上传到当前目录
             // 对于复杂的文件夹结构保持，后端需要解析 webkitRelativePath
        }

        const itemId = 'prog-' + i;
        addProgressItem(file.name, 'pending', '等待中...', itemId);

        const formData = new FormData();
        formData.append('action', 'upload');
        formData.append('file', file);
        formData.append('path', currentPath);
        // 传递相对路径以便后端重建文件夹结构
        if (file.webkitRelativePath) {
            formData.append('relative_path', file.webkitRelativePath);
        }

        try {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', API_BASE);
            
            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    updateProgressItem(itemId, 'uploading', `${percent}%`);
                }
            };

            xhr.onload = () => {
                if (xhr.status === 200) {
                    const res = JSON.parse(xhr.responseText);
                    if (res.code === 200) {
                        updateProgressItem(itemId, 'success', '完成');
                    } else {
                        updateProgressItem(itemId, 'error', res.message);
                    }
                } else {
                    updateProgressItem(itemId, 'error', 'HTTP Error');
                }
            };
            
            xhr.onerror = () => updateProgressItem(itemId, 'error', '网络错误');
            
            xhr.send(formData);
        } catch (err) {
            updateProgressItem(itemId, 'error', '异常');
        }
    }
    
    // 上传开始后刷新列表（实际项目中应在所有上传完成后刷新，或使用WebSocket）
    setTimeout(loadFiles, 2000);
}

function addProgressItem(name, status, msg, id) {
    const div = document.createElement('div');
    div.id = id;
    div.className = 'flex justify-between items-center text-sm p-2 bg-gray-50 rounded';
    div.innerHTML = `<span class="truncate w-2/3">${name}</span><span class="status-text text-gray-500">${msg}</span>`;
    document.getElementById('progress-list').appendChild(div);
}

function updateProgressItem(id, status, msg) {
    const el = document.getElementById(id);
    if (!el) return;
    const span = el.querySelector('.status-text');
    span.innerText = msg;
    if (status === 'success') span.className = 'status-text text-green-600';
    if (status === 'error') span.className = 'status-text text-red-600';
    if (status === 'uploading') span.className = 'status-text text-blue-600';
}

function showToast(msg, type = 'success') {
    const toast = document.getElementById('toast');
    toast.innerText = msg;
    toast.className = `fixed bottom-5 right-5 px-6 py-3 rounded shadow-lg transform transition-all duration-300 z-50 ${type === 'error' ? 'bg-red-600' : 'bg-gray-800'} text-white`;
    toast.style.transform = 'translateY(0)';
    toast.style.opacity = '1';
    
    setTimeout(() => {
        toast.style.transform = 'translateY(20px)';
        toast.style.opacity = '0';
    }, 3000);
}
