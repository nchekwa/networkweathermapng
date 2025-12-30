<div class="editor-page">
    <div class="page-header">
        <h1>Map Editor</h1>
        <div class="page-actions">
            <a href="/admin/maps/create" class="btn btn-primary">+ Create New Map</a>
        </div>
    </div>
    
    <?php if (empty($maps)): ?>
    <div class="empty-state">
        <p>No maps available to edit.</p>
        <p><a href="/admin/maps/create" class="btn btn-primary">Create your first map</a></p>
    </div>
    <?php else: ?>
    <div class="maps-list">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Config File</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($maps as $map): ?>
                <tr>
                    <td><?= htmlspecialchars($map['name']) ?></td>
                    <td><code><?= htmlspecialchars($map['config_file']) ?></code></td>
                    <td>
                        <span class="status-badge <?= $map['active'] ? 'status-active' : 'status-inactive' ?>">
                            <?= $map['active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td>
                        <a href="/editor/edit/<?= $map['id'] ?>" class="btn btn-sm btn-primary">Edit Config</a>
                        <a href="/map/<?= $map['id'] ?>" class="btn btn-sm btn-secondary">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
