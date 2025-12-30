<div class="admin-users-page">
    <div class="page-header">
        <div class="breadcrumb">
            <a href="/admin">Admin</a> &raquo; Users
        </div>
        <div class="page-actions">
            <button class="btn btn-primary" onclick="addUser()">Add User</button>
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
                        <button class="btn btn-sm" onclick="editUser(<?= $user['id'] ?>)">Edit</button>
                        <?php if ($user['username'] !== 'admin'): ?>
                        <button class="btn btn-sm btn-danger" onclick="deleteUser(<?= $user['id'] ?>)">Delete</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function addUser() {
    alert('Add user functionality coming soon');
}

function editUser(id) {
    alert('Edit user functionality coming soon');
}

function deleteUser(id) {
    if (confirm('Are you sure you want to delete this user?')) {
        alert('Delete user functionality coming soon');
    }
}
</script>
