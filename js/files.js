/**
 * files.php — 文件浏览页交互
 * 处理网格/列表视图切换 + localStorage 持久化
 */
function switchView(view) {
    const grid = document.getElementById('viewGrid');
    const list = document.getElementById('viewList');
    const gridBtn = document.getElementById('viewGridBtn');
    const listBtn = document.getElementById('viewListBtn');

    if (!grid || !list) return;

    if (view === 'grid') {
        grid.style.display = 'grid';
        list.style.display = 'none';
        gridBtn.classList.add('active');
        listBtn.classList.remove('active');
    } else {
        grid.style.display = 'none';
        list.style.display = 'flex';
        listBtn.classList.add('active');
        gridBtn.classList.remove('active');
    }
    try { localStorage.setItem('filesView', view); } catch(e) { /* ignore */ }
}

document.addEventListener('DOMContentLoaded', function() {
    try {
        const saved = localStorage.getItem('filesView');
        if (saved === 'list') switchView('list');
    } catch(e) { /* ignore */ }
});
