<div class="admin-container">
    <div class="page-header">
        <h1>Data Sources</h1>
        <a href="/admin/data-sources/create" class="btn btn-primary">+ Add Source</a>
    </div>
    
    <p class="page-description">
        Manage data sources for fetching network metrics. Data sources provide hosts, interfaces, and traffic data for your maps.
    </p>
    
    <div id="test-log" class="test-log hidden">
        <div class="test-log-header">
            <div>
                <div class="test-log-title">Last Test Result</div>
                <div id="test-log-source" class="test-log-source text-muted"></div>
            </div>
            <span id="test-log-status" class="badge badge-secondary">Pending</span>
        </div>
        <div class="test-log-body">
            <p id="test-log-message" class="test-log-message text-muted">Run a test to see details.</p>
            <div class="test-log-grid">
                <div>
                    <h4>cURL Command</h4>
                    <pre id="test-log-curl">Run a test to generate command.</pre>
                </div>
                <div>
                    <h4>Request Payload</h4>
                    <pre id="test-log-request">{}</pre>
                </div>
                <div>
                    <h4>Response</h4>
                    <pre id="test-log-response">{}</pre>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (empty($sources)): ?>
    <div class="empty-state">
        <p>No data sources configured yet.</p>
        <p>Add a data source to start fetching network data for your maps.</p>
        <a href="/admin/data-sources/create" class="btn btn-primary">Add Your First Data Source</a>
    </div>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Type</th>
                <th>URL</th>
                <th>Status</th>
                <th>Last Check</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sources as $source): ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($source['name']) ?></strong>
                    <?php if (!$source['active']): ?>
                    <span class="badge badge-warning">Inactive</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge badge-info"><?= htmlspecialchars(ucfirst($source['type'])) ?></span>
                </td>
                <td class="text-muted"><?= htmlspecialchars($source['url']) ?></td>
                <td>
                    <?php 
                    $statusClass = match($source['status']) {
                        'connected' => 'badge-success',
                        'error' => 'badge-danger',
                        default => 'badge-secondary'
                    };
                    ?>
                    <span class="badge <?= $statusClass ?>"><?= htmlspecialchars(ucfirst($source['status'])) ?></span>
                </td>
                <td class="text-muted">
                    <?= $source['last_check'] ? date('Y-m-d H:i', strtotime($source['last_check'])) : 'Never' ?>
                </td>
                <td class="actions">
                    <button 
                        type="button"
                        class="btn btn-sm btn-secondary"
                        onclick="testConnection(<?= $source['id'] ?>, this)"
                        data-source-name="<?= htmlspecialchars($source['name']) ?>"
                    >Test</button>
                    <a href="/admin/data-sources/edit/<?= $source['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                    <a href="/admin/data-sources/delete/<?= $source['id'] ?>" 
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('Are you sure you want to delete this data source?')">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<script>
const logBox = document.getElementById('test-log');
const logStatus = document.getElementById('test-log-status');
const logSource = document.getElementById('test-log-source');
const logMessage = document.getElementById('test-log-message');
const logCurl = document.getElementById('test-log-curl');
const logRequest = document.getElementById('test-log-request');
const logResponse = document.getElementById('test-log-response');

function testConnection(sourceId, btn) {
    const originalText = btn.textContent;
    btn.textContent = 'Testing...';
    btn.disabled = true;
    const sourceName = btn.dataset.sourceName || 'Data Source #' + sourceId;
    logSource.textContent = sourceName;
    logBox.classList.remove('hidden');
    setLogState('badge-secondary', 'Running…', 'Testing connection...');
    
    fetch('/admin/data-sources/test/' + sourceId)
        .then(r => r.json())
        .then(data => {
            updateLog(data);
        })
        .catch(err => {
            setLogState('badge-danger', 'Error', err.message);
            logCurl.textContent = '—';
            logRequest.textContent = '{}';
            logResponse.textContent = '{}';
        })
        .finally(() => {
            btn.textContent = originalText;
            btn.disabled = false;
        });
}

function updateLog(data) {
    if (!data) {
        setLogState('badge-danger', 'Error', 'No response returned');
        logCurl.textContent = '—';
        logRequest.textContent = '{}';
        logResponse.textContent = '{}';
        return;
    }
    
    const badgeClass = data.success ? 'badge-success' : 'badge-danger';
    const statusText = data.success ? 'Success' : 'Failed';
    const message = data.success
        ? (data.message || 'Connection OK') + (data.version ? ` (Zabbix ${data.version})` : '')
        : (data.error || 'Connection failed');
    
    setLogState(badgeClass, statusText, message);
    
    const debug = data.debug || {};
    logCurl.textContent = debug.curl || '—';
    logRequest.textContent = formatJson(debug.request);
    logResponse.textContent = formatJson(debug.response);
}

function setLogState(badgeClass, statusText, message) {
    logStatus.className = 'badge ' + badgeClass;
    logStatus.textContent = statusText;
    logMessage.textContent = message;
}

function formatJson(value) {
    if (!value) {
        return '{}';
    }
    try {
        return JSON.stringify(value, null, 2);
    } catch (e) {
        return String(value);
    }
}
</script>

<style>
.page-description {
    color: #666;
    margin-bottom: 20px;
}
.empty-state {
    text-align: center;
    padding: 40px;
    background: #f8f9fa;
    border-radius: 8px;
    margin: 20px 0;
}
.empty-state p {
    margin: 10px 0;
    color: #666;
}

.test-log {
    border: 1px solid #e1e5ee;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 25px;
    background: #fdfefe;
    box-shadow: 0 5px 20px rgba(20,44,83,0.08);
}
.test-log.hidden {
    display: none;
}
.test-log-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}
.test-log-title {
    font-weight: 600;
    font-size: 16px;
}
.test-log-source {
    font-size: 13px;
}
.test-log-message {
    font-size: 14px;
    margin-bottom: 15px;
}
.test-log-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 15px;
}
.test-log-grid pre {
    background: #101828;
    color: #e4f0ff;
    padding: 15px;
    border-radius: 8px;
    font-size: 12px;
    max-height: 260px;
    overflow: auto;
}
.test-log-grid h4 {
    margin-bottom: 6px;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #475467;
}
</style>
