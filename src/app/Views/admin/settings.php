<div class="admin-settings-page">
    <div class="page-header">
        <div class="breadcrumb">
            <a href="/admin">Admin</a> &raquo; Settings
        </div>
    </div>
    
    <div class="settings-container">
        <form method="POST" action="/admin/settings/save" class="settings-form">
            <div class="settings-section">
                <h2>Map Settings</h2>
                <div class="form-group">
                    <label>Output Format</label>
                    <select name="map_output_format">
                        <option value="png" <?= getenv('MAP_OUTPUT_FORMAT') === 'png' ? 'selected' : '' ?>>PNG</option>
                        <option value="jpg" <?= getenv('MAP_OUTPUT_FORMAT') === 'jpg' ? 'selected' : '' ?>>JPEG</option>
                        <option value="gif" <?= getenv('MAP_OUTPUT_FORMAT') === 'gif' ? 'selected' : '' ?>>GIF</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Thumbnail Size (px)</label>
                    <input type="number" name="map_thumb_size" 
                           value="<?= htmlspecialchars(getenv('MAP_THUMB_SIZE') ?: '250') ?>" 
                           min="100" max="500">
                </div>
                <div class="form-group">
                    <label>Refresh Interval (seconds)</label>
                    <input type="number" name="map_refresh_interval" 
                           value="<?= htmlspecialchars(getenv('MAP_REFRESH_INTERVAL') ?: '300') ?>" 
                           min="60" max="3600">
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </div>
        </form>
        
        <div class="settings-info">
            <h3>Environment Variables</h3>
            <p>Most settings are configured via environment variables in the <code>.env</code> file or Docker environment.</p>
            <p>Changes made here will only persist until the application restarts. For permanent changes, update your environment configuration.</p>
        </div>
    </div>
</div>

<style>
.settings-container {
    max-width: 800px;
}
.settings-section {
    background: var(--card-bg);
    padding: 25px;
    border-radius: 8px;
    margin-bottom: 20px;
}
.settings-section h2 {
    margin: 0 0 20px;
    font-size: 18px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
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
.form-group select {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 14px;
}
.form-group small {
    display: block;
    margin-top: 5px;
    color: var(--text-muted);
    font-size: 12px;
}
.form-actions {
    margin-top: 20px;
}
.settings-info {
    background: #e8f4fd;
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid var(--info-color);
}
.settings-info h3 {
    margin: 0 0 10px;
    font-size: 14px;
}
.settings-info p {
    margin: 0 0 10px;
    font-size: 13px;
}
.settings-info p:last-child {
    margin-bottom: 0;
}
</style>
