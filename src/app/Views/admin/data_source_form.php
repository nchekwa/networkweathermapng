<div class="admin-container">
    <div class="page-header">
        <h1><?= htmlspecialchars($title) ?></h1>
    </div>
    
    <form method="post" class="form-container">
        <div class="form-group">
            <label for="name">Name *</label>
            <input type="text" id="name" name="name" required
                   value="<?= htmlspecialchars($source['name'] ?? '') ?>"
                   placeholder="e.g., Production Zabbix">
            <small>A friendly name to identify this data source</small>
        </div>
        
        <div class="form-group">
            <label for="type">Type *</label>
            <select id="type" name="type" required onchange="toggleAuthFields()">
                <?php foreach ($types as $value => $label): ?>
                <option value="<?= $value ?>" <?= ($source['type'] ?? 'zabbix') === $value ? 'selected' : '' ?>>
                    <?= htmlspecialchars($label) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="url">URL *</label>
            <input type="url" id="url" name="url" required
                   value="<?= htmlspecialchars($source['url'] ?? '') ?>"
                   placeholder="https://zabbix.example.com">
            <small>Base URL of the Zabbix server (without /api_jsonrpc.php)</small>
        </div>
        
        <fieldset class="form-fieldset">
            <legend>Authentication</legend>
            
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username"
                       value="<?= htmlspecialchars($source['username'] ?? '') ?>"
                       placeholder="Admin">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       placeholder="<?= $source ? '(unchanged)' : '' ?>">
                <?php if ($source): ?>
                <small>Leave empty to keep current password</small>
                <?php endif; ?>
            </div>
            
            <div class="form-divider">
                <span>OR</span>
            </div>
            
            <div class="form-group">
                <label for="api_token">API Token</label>
                <input type="text" id="api_token" name="api_token"
                       value="<?= htmlspecialchars($source['api_token'] ?? '') ?>"
                       placeholder="API token for Zabbix 5.4+">
                <small>For Zabbix 5.4+, you can use an API token instead of username/password</small>
            </div>
        </fieldset>
        
        <?php if ($source): ?>
        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="active" <?= $source['active'] ? 'checked' : '' ?>>
                Active
            </label>
        </div>
        <?php endif; ?>
        
        <div class="form-actions">
            <a href="/admin/data-sources" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <?= $action === 'create' ? 'Create Data Source' : 'Save Changes' ?>
            </button>
        </div>
    </form>
</div>

<style>
.form-container {
    max-width: 600px;
    background: #fff;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}
.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}
.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #007bff;
}
.form-group small {
    display: block;
    margin-top: 5px;
    color: #666;
    font-size: 12px;
}
.form-fieldset {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}
.form-fieldset legend {
    padding: 0 10px;
    font-weight: 500;
}
.form-divider {
    text-align: center;
    margin: 20px 0;
    position: relative;
}
.form-divider::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    width: 100%;
    height: 1px;
    background: #ddd;
}
.form-divider span {
    background: #fff;
    padding: 0 15px;
    position: relative;
    color: #666;
}
.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}
.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}
.checkbox-label input {
    width: auto;
}
</style>
