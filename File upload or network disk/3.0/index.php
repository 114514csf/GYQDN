<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lightweight PHP Network Disk</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            color: #1f2937;
        }

        .app {
            max-width: 1200px;
            margin: 32px auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,.08);
            overflow: hidden;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 24px;
            background: linear-gradient(135deg, #1d4ed8, #2563eb);
            color: white;
        }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding: 18px 24px;
            border-bottom: 1px solid #e5e7eb;
            background: #fff;
        }

        button, .button-link {
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 14px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        button.secondary {
            background: #e5e7eb;
            color: #111827;
        }

        button.danger {
            background: #dc2626;
        }

        .upload-wrap {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        input[type="file"] {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 8px;
            background: white;
        }

        .breadcrumb {
            padding: 16px 24px;
            border-bottom: 1px solid #e5e7eb;
            background: #f8fafc;
            word-break: break-all;
        }

        .breadcrumb a {
            color: #1d4ed8;
            text-decoration: none;
            margin-right: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            text-align: left;
            font-size: 12px;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: #6b7280;
            background: #f9fafb;
            padding: 14px 20px;
            border-bottom: 1px solid #e5e7eb;
        }

        tbody td {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        .name-cell {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            font-weight: 600;
            color: #1f2937;
        }

        .icon {
            width: 26px;
            height: 26px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            font-weight: bold;
        }

        .dir-icon { background: #dbeafe; color: #1d4ed8; }
        .file-icon { background: #e5e7eb; color: #374151; }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .mini-btn {
            padding: 6px 10px;
            font-size: 12px;
            border-radius: 6px;
        }

        .empty {
            padding: 30px 20px;
            text-align: center;
            color: #6b7280;
        }

        @media (max-width: 700px) {
            .topbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .upload-wrap {
                width: 100%;
                margin-left: 0;
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
<div class="app">
    <div class="topbar">
        <h2 style="margin:0;">PHP Network Disk</h2>
        <div id="currentPathText">/</div>
    </div>

    <div class="toolbar">
        <button id="newFolderBtn">New Folder</button>
        <button id="renameBtn" class="secondary">Rename</button>
        <button id="copyBtn" class="secondary">Copy</button>
        <button id="moveBtn" class="secondary">Move</button>
        <button id="deleteBtn" class="danger">Delete</button>

        <div class="upload-wrap">
            <input id="fileInput" type="file" multiple>
            <button id="uploadBtn">Upload</button>
        </div>
    </div>

    <div id="breadcrumb" class="breadcrumb"></div>

    <table>
        <thead>
            <tr>
                <th style="width:50px;">
                    <input id="selectAll" type="checkbox">
                </th>
                <th>Name</th>
                <th style="width:120px;">Type</th>
                <th style="width:120px;">Size</th>
                <th style="width:220px;">Actions</th>
            </tr>
        </thead>
        <tbody id="fileList"></tbody>
    </table>
</div>

<script>
    const state = {
        currentPath: '/'
    };

    const $ = (sel) => document.querySelector(sel);

    function apiCall(action, params = {}, method = 'POST') {
        const body = new URLSearchParams();
        body.append('action', action);
        body.append('path', state.currentPath);

        Object.entries(params).forEach(([key, value]) => {
            body.append(key, value);
        });

        return fetch('api.php', {
            method,
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body
        }).then(res => res.json());
    }

    function normalizePath(path) {
        if (!path || path === '/') return '/';
        return path.replace(/\\/g, '/').replace(/\/+/g, '/');
    }

    function joinPath(base, child) {
        base = normalizePath(base);
        child = normalizePath(child);

        if (base === '/') {
            return '/' + child.replace(/^\/+/, '');
        }

        return base.replace(/\/+$/, '') + '/' + child.replace(/^\/+/, '');
    }

    function formatSize(bytes) {
        if (!bytes) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        let size = bytes;
        let unitIndex = 0;

        while (size >= 1024 && unitIndex < units.length - 1) {
            size /= 1024;
            unitIndex++;
        }

        return `${size.toFixed(size >= 10 || unitIndex === 0 ? 0 : 2)} ${units[unitIndex]}`;
    }

    function renderBreadcrumb() {
        const breadcrumb = $('#breadcrumb');
        const items = ['/'];

        const parts = state.currentPath.split('/').filter(Boolean);
        let current = '';

        parts.forEach(part => {
            current += '/' + part;
            items.push(current);
        });

        breadcrumb.innerHTML = items.map((item, index) => {
            const label = index === 0 ? 'Home' : item.split('/').filter(Boolean).pop();
            const href = item === '/' ? '/' : item;

            if (index === items.length - 1) {
                return `<span style="font-weight:600;">${label}</span>`;
            }

            return `<a href="#" data-path="${href}">${label}</a> / `;
        }).join('');
    }

    function getSelectedNames() {
        return [...document.querySelectorAll('.select-item:checked')].map(el => el.value);
    }

    function renderEntries(entries) {
        const tbody = $('#fileList');
        tbody.innerHTML = '';

        if (!entries.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="empty">No files or folders in this directory.</td></tr>';
            return;
        }

        entries.forEach(item => {
            const row = document.createElement('tr');

            row.innerHTML = `
                <td><input class="select-item" type="checkbox" value="${item.name}"></td>
                <td>
                    <div class="name-cell" data-type="${item.type}" data-name="${item.name}">
                        <span class="icon ${item.type === 'dir' ? 'dir-icon' : 'file-icon'}">${item.type === 'dir' ? 'ð' : 'ð'}</span>
                        <span>${item.name}</span>
                    </div>
                </td>
                <td>${item.type === 'dir' ? 'Folder' : 'File'}</td>
                <td>${item.type === 'dir' ? '-' : formatSize(item.size)}</td>
                <td>
                    <div class="actions">
                        ${item.type === 'file' ? `<a class="button-link mini-btn" href="api.php?action=download&path=${encodeURIComponent(joinPath(state.currentPath, item.name))}" target="_blank">Download</a>` : ''}
                        <button class="mini-btn secondary" data-action="rename" data-name="${item.name}">Rename</button>
                        <button class="mini-btn secondary" data-action="copy" data-name="${item.name}">Copy</button>
                        <button class="mini-btn secondary" data-action="move" data-name="${item.name}">Move</button>
                        <button class="mini-btn danger" data-action="delete" data-name="${item.name}">Delete</button>
                    </div>
                </td>
            `;

            tbody.appendChild(row);
        });

        $('#selectAll').checked = false;

        [...document.querySelectorAll('.name-cell')].forEach(cell => {
            cell.addEventListener('click', () => {
                const type = cell.dataset.type;
                const name = cell.dataset.name;

                if (type === 'dir') {
                    state.currentPath = joinPath(state.currentPath, name);
                    loadFiles();
                }
            });
        });

        [...document.querySelectorAll('[data-action]')].forEach(btn => {
            btn.addEventListener('click', async () => {
                const action = btn.dataset.action;
                const name = btn.dataset.name;

                if (action === 'delete') {
                    await performDelete(name);
                    return;
                }

                if (action === 'rename') {
                    const newName = prompt('Enter new name:', name);
                    if (!newName || !newName.trim()) return;
                    await apiCall('rename', { name, new_name: newName.trim() });
                    loadFiles();
                    return;
                }

                if (action === 'copy') {
                    const newName = prompt('Copy name:', name);
                    if (!newName || !newName.trim()) return;
                    await apiCall('copy', { name, new_name: newName.trim() });
                    loadFiles();
                    return;
                }

                if (action === 'move') {
                    const targetDir = prompt('Move to folder path (leave blank to use current folder):', state.currentPath);
                    const newName = prompt('New name (optional, keep same if blank):', name);
                    await apiCall('move', {
                        name,
                        target_dir: targetDir && targetDir.trim() ? targetDir.trim() : state.currentPath,
                        new_name: newName ? newName.trim() : name
                    });
                    loadFiles();
                }
            });
        });
    }

    async function loadFiles() {
        try {
            const response = await fetch('api.php?action=list&path=' + encodeURIComponent(state.currentPath), {
                method: 'GET'
            });

            const data = await response.json();

            if (!data.success) {
                alert(data.message || 'Failed to load files.');
                return;
            }

            renderBreadcrumb();
            $('#currentPathText').textContent = state.currentPath === '/' ? '/' : state.currentPath;
            renderEntries(data.entries);
        } catch (err) {
            alert('Failed to load files.');
        }
    }

    async function performDelete(name) {
        const ok = confirm(`Delete "${name}"?`);
        if (!ok) return;

        const result = await apiCall('delete', { name });
        if (!result.success) {
            alert(result.message || 'Delete failed.');
            return;
        }

        loadFiles();
    }

    $('#newFolderBtn').addEventListener('click', async () => {
        const folderName = prompt('Folder name:');
        if (!folderName || !folderName.trim()) return;

        const result = await apiCall('mkdir', { name: folderName.trim() });
        if (!result.success) {
            alert(result.message || 'Failed to create folder.');
            return;
        }

        loadFiles();
    });

    $('#renameBtn').addEventListener('click', async () => {
        const selected = getSelectedNames();
        if (!selected.length) {
            alert('Select an item first.');
            return;
        }

        if (selected.length > 1) {
            alert('Please select only one item to rename.');
            return;
        }

        const name = selected[0];
        const newName = prompt('Enter new name:', name);
        if (!newName || !newName.trim()) return;

        const result = await apiCall('rename', { name, new_name: newName.trim() });
        if (!result.success) {
            alert(result.message || 'Rename failed.');
            return;
        }

        loadFiles();
    });

    $('#copyBtn').addEventListener('click', async () => {
        const selected = getSelectedNames();
        if (!selected.length) {
            alert('Select an item first.');
            return;
        }

        if (selected.length > 1) {
            alert('Please select only one item to copy.');
            return;
        }

        const sourceName = selected[0];
        const newName = prompt('Copy as:', sourceName);
        if (!newName || !newName.trim()) return;

        const result = await apiCall('copy', { name: sourceName, new_name: newName.trim() });
        if (!result.success) {
            alert(result.message || 'Copy failed.');
            return;
        }

        loadFiles();
    });

    $('#moveBtn').addEventListener('click', async () => {
        const selected = getSelectedNames();
        if (!selected.length) {
            alert('Select an item first.');
            return;
        }

        if (selected.length > 1) {
            alert('Please select only one item to move.');
            return;
        }

        const name = selected[0];
        const targetDir = prompt('Move to folder path (leave blank to use current folder):', state.currentPath);
        const newName = prompt('New name (optional, keep same if blank):', name);

        const result = await apiCall('move', {
            name,
            target_dir: targetDir && targetDir.trim() ? targetDir.trim() : state.currentPath,
            new_name: newName && newName.trim() ? newName.trim() : name
        });

        if (!result.success) {
            alert(result.message || 'Move failed.');
            return;
        }

        loadFiles();
    });

    $('#deleteBtn').addEventListener('click', async () => {
        const selected = getSelectedNames();
        if (!selected.length) {
            alert('Select an item first.');
            return;
        }

        const confirmDelete = confirm(`Delete ${selected.length} selected item(s)?`);
        if (!confirmDelete) return;

        for (const name of selected) {
            const result = await apiCall('delete', { name });
            if (!result.success) {
                alert(result.message || 'Delete failed for ' + name);
                break;
            }
        }

        loadFiles();
    });

    $('#uploadBtn').addEventListener('click', async () => {
        const input = $('#fileInput');
        if (!input.files.length) {
            alert('Please choose one or more files.');
            return;
        }

        const formData = new FormData();
        Array.from(input.files).forEach(file => {
            formData.append('files[]', file);
        });
        formData.append('action', 'upload');
        formData.append('path', state.currentPath);

        const response = await fetch('api.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (!result.success) {
            alert(result.message || 'Upload failed.');
            return;
        }

        input.value = '';
        loadFiles();
    });

    $('#selectAll').addEventListener('change', function () {
        document.querySelectorAll('.select-item').forEach(cb => {
            cb.checked = this.checked;
        });
    });

    $('#breadcrumb').addEventListener('click', function (event) {
        event.preventDefault();
        const link = event.target.closest('a[data-path]');
        if (!link) return;

        state.currentPath = normalizePath(link.dataset.path);
        loadFiles();
    });

    loadFiles();
</script>
</body>
</html>
