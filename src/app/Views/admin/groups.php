<div class="admin-groups-page">
    <div class="page-header">
        <div class="breadcrumb">
            <a href="/admin">Admin</a> &raquo; Groups
        </div>
        <div class="page-actions">
            <a href="/admin/groups/create" class="btn btn-primary">+ Add Group</a>
        </div>
    </div>
    
    <div class="data-table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Maps</th>
                    <th>Sort Order</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $group): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($group['name']) ?></strong></td>
                    <td><?= $group['map_count'] ?? 0 ?></td>
                    <td><?= $group['sort_order'] ?></td>
                    <td class="actions">
                        <a href="/admin/groups/edit/<?= $group['id'] ?>" class="btn btn-sm">Edit</a>
                        <?php if ($group['id'] != 1): ?>
                        <a href="/admin/groups/delete/<?= $group['id'] ?>" class="btn btn-sm btn-danger"
                           onclick="return confirm('Are you sure you want to delete this group? Maps will be moved to Default group.')">Delete</a>
                        <?php else: ?>
                        <span class="text-muted">Default</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
