<div class="admin-maps-page">
    <div class="page-header">
        <div class="breadcrumb">
            <a href="/admin">Admin</a> &raquo; Maps
        </div>
        <div class="page-actions">
            <a href="/admin/maps/create" class="btn btn-primary">+ Add Map</a>
        </div>
    </div>
    
    <?php if (empty($maps)): ?>
    <div class="empty-state">
        <p>No maps configured yet.</p>
        <p><a href="/admin/maps/create" class="btn btn-primary">Create your first weathermap</a></p>
    </div>
    <?php else: ?>
    <div class="data-table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Config File</th>
                    <th>Group</th>
                    <th>Status</th>
                    <th>Last Run</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($maps as $map): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($map['name']) ?></strong></td>
                    <td><code><?= htmlspecialchars($map['config_file']) ?></code></td>
                    <td><?= htmlspecialchars($map['group_name'] ?? 'Default') ?></td>
                    <td>
                        <span class="status-badge <?= $map['active'] ? 'status-active' : 'status-inactive' ?>">
                            <?= $map['active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td><?= $map['last_run'] ? htmlspecialchars($map['last_run']) : 'Never' ?></td>
                    <td class="actions">
                        <a href="/editor/edit/<?= $map['id'] ?>" class="btn btn-sm" title="Edit Config">Config</a>
                        <a href="/admin/maps/edit/<?= $map['id'] ?>" class="btn btn-sm" title="Edit Properties">Edit</a>
                        <a href="/map/<?= $map['id'] ?>" class="btn btn-sm" title="View Map">View</a>
                        <a href="/admin/maps/duplicate/<?= $map['id'] ?>" class="btn btn-sm" title="Duplicate">Copy</a>
                        <a href="/admin/maps/delete/<?= $map['id'] ?>" class="btn btn-sm btn-danger" 
                           onclick="return confirm('Are you sure you want to delete this map?')" title="Delete">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
