<div class="admin-users-page">
    <div class="page-header">
        <div class="breadcrumb">
            <a href="/admin">Admin</a> &raquo; Users
        </div>
        <div class="page-actions">
            <a class="btn btn-primary" href="/admin/users/create">Add User</a>
        </div>
    </div>
    
    <div class="data-table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                    <td><?= htmlspecialchars($user['email'] ?? '-') ?></td>
                    <td>
                        <span class="role-badge role-<?= $user['role'] ?>">
                            <?= ucfirst($user['role']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge <?= $user['active'] ? 'status-active' : 'status-inactive' ?>">
                            <?= $user['active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($user['created_at'] ?? '-') ?></td>
                    <td class="actions">
                        <a class="btn btn-sm" href="/admin/users/edit/<?= $user['id'] ?>">Edit</a>
                        <?php if ($user['username'] !== 'admin'): ?>
                        <a class="btn btn-sm btn-danger" href="/admin/users/delete/<?= $user['id'] ?>" onclick="return confirm('Are you sure you want to delete this user?')">Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

