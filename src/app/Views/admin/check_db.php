<div class="content-header">
    <h1>Database Integrity Check</h1>
    <div class="header-actions">
        <a href="/admin" class="btn btn-secondary">← Back to Dashboard</a>
    </div>
</div>
<br>
<div class="card">
    <div class="card-body">
        <?php if ($results['status'] === 'ok'): ?>
            <div class="alert alert-success">
                <strong>✅ Database integrity check passed.</strong> All required tables and columns are present.
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                <strong>❌ Database integrity check failed.</strong>
                <ul>
                    <?php foreach ($results['errors'] as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
                <div style="margin-top: 15px;">
                    <form method="POST" action="/admin/check-db">
                        <input type="hidden" name="fix" value="1">
                        <button type="submit" class="btn btn-primary">Attempt Auto-Fix</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <h3>Table Status</h3>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Table Name</th>
                        <th>Status</th>
                        <th>Issues</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results['tables'] as $table): ?>
                    <tr>
                        <td>
                            <code><?= htmlspecialchars($table['name']) ?></code>
                        </td>
                        <td>
                            <?php if ($table['exists']): ?>
                                <span class="badge badge-success">Exists</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Missing</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($table['missing_columns'])): ?>
                                <span class="text-danger">Missing columns: <?= implode(', ', $table['missing_columns']) ?></span>
                            <?php elseif ($table['exists']): ?>
                                <span class="text-muted">No issues</span>
                            <?php else: ?>
                                <span class="text-danger">Table missing</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.alert {
    padding: 15px;
    margin-bottom: 20px;
    border: 1px solid transparent;
    border-radius: 4px;
}
.alert-success {
    color: #155724;
    background-color: #d4edda;
    border-color: #c3e6cb;
}
.alert-danger {
    color: #721c24;
    background-color: #f8d7da;
    border-color: #f5c6cb;
}
.badge {
    display: inline-block;
    padding: 0.25em 0.4em;
    font-size: 75%;
    font-weight: 700;
    line-height: 1;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 0.25rem;
}
.badge-success {
    color: #fff;
    background-color: #28a745;
}
.badge-danger {
    color: #fff;
    background-color: #dc3545;
}
.table {
    width: 100%;
    margin-bottom: 1rem;
    color: #212529;
    border-collapse: collapse;
}
.table th,
.table td {
    padding: 0.75rem;
    vertical-align: top;
    border-top: 1px solid #dee2e6;
    text-align: left;
}
.table thead th {
    vertical-align: bottom;
    border-bottom: 2px solid #dee2e6;
}
</style>
