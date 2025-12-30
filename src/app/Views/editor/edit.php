<div class="editor-fullscreen">
    <!-- Top Toolbar -->
    <div class="editor-toolbar">
        <div class="toolbar-left">
            <a href="/editor" class="btn btn-sm btn-secondary">← Back</a>
            <span class="toolbar-title"><?= htmlspecialchars($map['name'] ?? $mapFile) ?></span>
            <span class="toolbar-file">(<?= htmlspecialchars($mapFile) ?>)</span>
        </div>
        <div class="toolbar-center">
            <button class="btn btn-sm" id="btn-toggle-view" onclick="toggleView()">
                <span id="view-mode-text">Show Config</span>
            </button>
            <button class="btn btn-sm btn-info" onclick="previewMap()">⟳ Render Preview</button>
        </div>
        <div class="toolbar-right">
            <button class="btn btn-sm btn-success" onclick="saveMap()">💾 Save</button>
            <a href="/map/<?= $mapId ?>" class="btn btn-sm" target="_blank">👁 View Live</a>
        </div>
    </div>
    
    <!-- Main Editor Area -->
    <div class="editor-workspace">
        <!-- Left Panel - Tools -->
        <div class="editor-panel editor-tools">
            <div class="panel-section">
                <h4>Tools</h4>
                <div class="tool-grid">
                    <button class="tool-btn active" data-tool="select" title="Select & Move">
                        <span class="tool-icon">↖</span>
                        <span class="tool-label">Select</span>
                    </button>
                    <button class="tool-btn" data-tool="node" title="Add Node">
                        <span class="tool-icon">◉</span>
                        <span class="tool-label">Node</span>
                    </button>
                    <button class="tool-btn" data-tool="link" title="Add Link">
                        <span class="tool-icon">↔</span>
                        <span class="tool-label">Link</span>
                    </button>
                    <button class="tool-btn" data-tool="delete" title="Delete">
                        <span class="tool-icon">✕</span>
                        <span class="tool-label">Delete</span>
                    </button>
                </div>
            </div>
            <div class="panel-section">
                <h4>Properties</h4>
                <div id="properties-panel">
                    <p class="hint">Select an element to edit</p>
                </div>
            </div>
        </div>
        
        <!-- Center - Map Canvas -->
        <div class="editor-canvas-area">
            <div class="canvas-wrapper" id="canvas-wrapper">
                <div id="map-preview" class="map-canvas">
                    <div class="canvas-placeholder">
                        <p>Click <strong>Render Preview</strong> to see the map</p>
                        <p class="hint">Or edit the config and render</p>
                    </div>
                </div>
            </div>
            
            <!-- Config Editor (hidden by default, toggle with button) -->
            <div class="config-panel" id="config-panel" style="display: none;">
                <div class="config-header">
                    <h4>WeatherMap Configuration</h4>
                    <button class="btn btn-sm" onclick="toggleView()">✕ Close</button>
                </div>
                <textarea id="map-config" class="config-editor"><?= htmlspecialchars($mapConfig) ?></textarea>
            </div>
        </div>
        
        <!-- Right Panel - Elements List -->
        <div class="editor-panel editor-elements">
            <div class="panel-section">
                <h4>Map Info</h4>
                <div class="info-list">
                    <div class="info-item">
                        <span class="info-label">Status</span>
                        <span class="status-badge <?= ($map['active'] ?? 0) ? 'status-active' : 'status-inactive' ?>">
                            <?= ($map['active'] ?? 0) ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Last Run</span>
                        <span><?= $map['last_run'] ?? 'Never' ?></span>
                    </div>
                </div>
            </div>
            <div class="panel-section">
                <h4>Nodes</h4>
                <div id="nodes-list" class="element-list">
                    <p class="hint">No nodes defined</p>
                </div>
            </div>
            <div class="panel-section">
                <h4>Links</h4>
                <div id="links-list" class="element-list">
                    <p class="hint">No links defined</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Status Bar -->
    <div class="editor-statusbar">
        <span id="status-message">Ready</span>
        <span class="status-right">
            <span id="cursor-pos">-</span>
        </span>
    </div>
</div>

<style>
/* Fullscreen Editor Layout */
.editor-fullscreen {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    flex-direction: column;
    background: #1a1a2e;
    z-index: 1000;
}

/* Toolbar */
.editor-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 15px;
    background: #16213e;
    border-bottom: 1px solid #0f3460;
    color: #fff;
}
.toolbar-left, .toolbar-center, .toolbar-right {
    display: flex;
    align-items: center;
    gap: 10px;
}
.toolbar-title {
    font-weight: 600;
    font-size: 16px;
}
.toolbar-file {
    color: #888;
    font-size: 12px;
}

/* Workspace */
.editor-workspace {
    flex: 1;
    display: flex;
    overflow: hidden;
}

/* Side Panels */
.editor-panel {
    width: 220px;
    background: #16213e;
    border-right: 1px solid #0f3460;
    overflow-y: auto;
    color: #e0e0e0;
}
.editor-panel:last-child {
    border-right: none;
    border-left: 1px solid #0f3460;
}
.panel-section {
    padding: 12px;
    border-bottom: 1px solid #0f3460;
}
.panel-section h4 {
    margin: 0 0 10px;
    font-size: 11px;
    text-transform: uppercase;
    color: #888;
    letter-spacing: 0.5px;
}

/* Tool Grid */
.tool-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px;
}
.tool-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 10px 5px;
    background: #1a1a2e;
    border: 1px solid #0f3460;
    border-radius: 4px;
    color: #e0e0e0;
    cursor: pointer;
    transition: all 0.2s;
}
.tool-btn:hover {
    background: #0f3460;
    border-color: #e94560;
}
.tool-btn.active {
    background: #e94560;
    border-color: #e94560;
    color: #fff;
}
.tool-icon {
    font-size: 18px;
    margin-bottom: 4px;
}
.tool-label {
    font-size: 10px;
    text-transform: uppercase;
}

/* Canvas Area */
.editor-canvas-area {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: #0d0d1a;
    position: relative;
}
.canvas-wrapper {
    flex: 1;
    overflow: auto;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.map-canvas {
    background: #fff;
    box-shadow: 0 4px 20px rgba(0,0,0,0.5);
    min-width: 400px;
    min-height: 300px;
}
.canvas-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px;
    color: #666;
    text-align: center;
}
.canvas-placeholder p {
    margin: 5px 0;
}
#map-preview img {
    display: block;
    max-width: 100%;
}

/* Config Panel */
.config-panel {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(13, 13, 26, 0.95);
    padding: 15px;
    display: flex;
    flex-direction: column;
}
.config-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    color: #fff;
}
.config-header h4 {
    margin: 0;
}
.config-editor {
    flex: 1;
    width: 100%;
    font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
    font-size: 13px;
    line-height: 1.5;
    padding: 15px;
    background: #1a1a2e;
    color: #e0e0e0;
    border: 1px solid #0f3460;
    border-radius: 4px;
    resize: none;
}
.config-editor:focus {
    outline: none;
    border-color: #e94560;
}

/* Info List */
.info-list {
    font-size: 12px;
}
.info-item {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    border-bottom: 1px solid #0f3460;
}
.info-label {
    color: #888;
}

/* Element List */
.element-list {
    max-height: 200px;
    overflow-y: auto;
}
.element-item {
    padding: 6px 8px;
    margin: 2px 0;
    background: #1a1a2e;
    border-radius: 3px;
    font-size: 12px;
    cursor: pointer;
}
.element-item:hover {
    background: #0f3460;
}

/* Status Bar */
.editor-statusbar {
    display: flex;
    justify-content: space-between;
    padding: 5px 15px;
    background: #0f3460;
    color: #888;
    font-size: 11px;
}

/* Hints */
.hint {
    color: #666;
    font-size: 11px;
    font-style: italic;
}

/* Button Styles */
.btn-sm {
    padding: 5px 12px;
    font-size: 12px;
}
.btn-success {
    background: #28a745;
    color: #fff;
    border-color: #28a745;
}
.btn-success:hover {
    background: #218838;
}
.btn-info {
    background: #17a2b8;
    color: #fff;
    border-color: #17a2b8;
}
.btn-info:hover {
    background: #138496;
}

/* Notifications */
.notification {
    position: fixed;
    top: 60px;
    right: 20px;
    padding: 12px 20px;
    border-radius: 4px;
    color: #fff;
    font-weight: 500;
    z-index: 9999;
    animation: slideIn 0.3s ease;
}
.notification-success { background: #28a745; }
.notification-error { background: #dc3545; }
.notification.fade-out {
    animation: fadeOut 0.3s ease forwards;
}
@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
@keyframes fadeOut {
    from { opacity: 1; }
    to { opacity: 0; }
}
.text-danger { color: #dc3545; }
</style>

<script>
const mapId = <?= json_encode($mapId ?? 0) ?>;
const mapFile = <?= json_encode($mapFile) ?>;
let configVisible = false;

function toggleView() {
    const configPanel = document.getElementById('config-panel');
    const viewModeText = document.getElementById('view-mode-text');
    
    configVisible = !configVisible;
    configPanel.style.display = configVisible ? 'flex' : 'none';
    viewModeText.textContent = configVisible ? 'Hide Config' : 'Show Config';
}

function setStatus(message) {
    document.getElementById('status-message').textContent = message;
}

function saveMap() {
    const config = document.getElementById('map-config').value;
    setStatus('Saving...');
    
    fetch('/editor/save/' + mapId, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'config=' + encodeURIComponent(config)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showNotification('Map saved successfully!', 'success');
            setStatus('Saved');
            parseConfig(config);
        } else {
            showNotification('Error: ' + (data.error || 'Failed to save'), 'error');
            setStatus('Save failed');
        }
    })
    .catch(err => {
        showNotification('Error: ' + err.message, 'error');
        setStatus('Error');
    });
}

function previewMap() {
    const config = document.getElementById('map-config').value;
    const previewDiv = document.getElementById('map-preview');
    
    setStatus('Saving and rendering...');
    previewDiv.innerHTML = '<div class="canvas-placeholder"><p>Rendering map...</p></div>';
    
    fetch('/editor/save/' + mapId, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'config=' + encodeURIComponent(config)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            setStatus('Rendering...');
            return fetch('/editor/preview/' + mapId);
        } else {
            throw new Error(data.error || 'Failed to save');
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            previewDiv.innerHTML = '<img src="' + data.image + '?t=' + Date.now() + '" alt="Map Preview">';
            setStatus('Ready');
            parseConfig(config);
        } else {
            previewDiv.innerHTML = '<div class="canvas-placeholder"><p class="text-danger">Render Error: ' + (data.error || 'Failed to render') + '</p></div>';
            setStatus('Render failed');
        }
    })
    .catch(err => {
        previewDiv.innerHTML = '<div class="canvas-placeholder"><p class="text-danger">Error: ' + err.message + '</p></div>';
        setStatus('Error');
    });
}

function parseConfig(config) {
    // Parse nodes and links from config
    const nodesList = document.getElementById('nodes-list');
    const linksList = document.getElementById('links-list');
    
    const nodes = [];
    const links = [];
    
    const lines = config.split('\n');
    let currentType = null;
    let currentName = null;
    
    for (const line of lines) {
        const trimmed = line.trim();
        if (trimmed.startsWith('NODE ') && !trimmed.startsWith('NODE DEFAULT')) {
            currentType = 'node';
            currentName = trimmed.substring(5).trim();
            nodes.push(currentName);
        } else if (trimmed.startsWith('LINK ') && !trimmed.startsWith('LINK DEFAULT')) {
            currentType = 'link';
            currentName = trimmed.substring(5).trim();
            links.push(currentName);
        }
    }
    
    // Update nodes list
    if (nodes.length > 0) {
        nodesList.innerHTML = nodes.map(n => 
            '<div class="element-item" onclick="highlightElement(\'node\', \'' + n + '\')">' + n + '</div>'
        ).join('');
    } else {
        nodesList.innerHTML = '<p class="hint">No nodes defined</p>';
    }
    
    // Update links list
    if (links.length > 0) {
        linksList.innerHTML = links.map(l => 
            '<div class="element-item" onclick="highlightElement(\'link\', \'' + l + '\')">' + l + '</div>'
        ).join('');
    } else {
        linksList.innerHTML = '<p class="hint">No links defined</p>';
    }
}

function highlightElement(type, name) {
    // Show config panel and scroll to element
    if (!configVisible) {
        toggleView();
    }
    
    const config = document.getElementById('map-config');
    const searchStr = type.toUpperCase() + ' ' + name;
    const pos = config.value.indexOf(searchStr);
    
    if (pos !== -1) {
        config.focus();
        config.setSelectionRange(pos, pos + searchStr.length);
        // Scroll to selection
        const lineNumber = config.value.substring(0, pos).split('\n').length;
        config.scrollTop = (lineNumber - 5) * 20;
    }
}

function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = 'notification notification-' + type;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('fade-out');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Tool selection
document.querySelectorAll('.tool-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        setStatus('Tool: ' + this.dataset.tool);
    });
});

// Parse config on load
document.addEventListener('DOMContentLoaded', function() {
    const config = document.getElementById('map-config').value;
    parseConfig(config);
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey || e.metaKey) {
        if (e.key === 's') {
            e.preventDefault();
            saveMap();
        } else if (e.key === 'e') {
            e.preventDefault();
            toggleView();
        }
    }
});
</script>
