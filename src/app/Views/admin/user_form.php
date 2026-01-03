<div class="content-header">
    <h1><?= htmlspecialchars($title) ?></h1>
    <div class="header-actions">
        <a href="/admin/users" class="btn btn-secondary">← Back to Users</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= $action === 'create' ? '/admin/users/create' : '/admin/users/edit/' . $userData['id'] ?>">
            <?php if ($action === 'create'): ?>
            <div class="form-group">
                <label for="username">Username *</label>
                <input type="text" id="username" name="username" class="form-control" required>
            </div>
            <?php else: ?>
            <div class="form-group">
                <label>Username</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($userData['username'] ?? '') ?>" disabled>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($userData['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="role">Role</label>
                <select id="role" name="role" class="form-control" <?= ($action === 'edit' && ($userData['username'] ?? '') === 'admin') ? 'disabled' : '' ?>>
                    <?php $currentRole = $userData['role'] ?? 'viewer'; ?>
                    <option value="viewer" <?= $currentRole === 'viewer' ? 'selected' : '' ?>>Viewer</option>
                    <option value="admin" <?= $currentRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
                <?php if ($action === 'edit' && ($userData['username'] ?? '') === 'admin'): ?>
                    <input type="hidden" name="role" value="admin">
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="active" value="1" <?= (($userData['active'] ?? 0) ? 'checked' : '') ?> <?= ($action === 'edit' && ($userData['username'] ?? '') === 'admin') ? 'disabled' : '' ?>>
                    Active
                </label>
                <?php if ($action === 'edit' && ($userData['username'] ?? '') === 'admin'): ?>
                    <input type="hidden" name="active" value="1">
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password"><?= $action === 'create' ? 'Password *' : 'New Password' ?></label>
                <input type="password" id="password" name="password" class="form-control" <?= $action === 'create' ? 'required' : '' ?>>
            </div>

            <div class="form-group">
                <label for="confirm_password"><?= $action === 'create' ? 'Confirm Password *' : 'Confirm New Password' ?></label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" <?= $action === 'create' ? 'required' : '' ?>>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $action === 'create' ? 'Create User' : 'Save Changes' ?>
                </button>
                <a href="/admin/users" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<style>
.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.form-control {
    width: 100%;
    max-width: 500px;
    padding: 0.5rem 0.75rem;
    font-size: 1rem;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.form-control:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

.form-control:disabled {
    background-color: #f5f5f5;
    cursor: not-allowed;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
}

.form-actions {
    margin-top: 2rem;
    display: flex;
    gap: 1rem;
}
</style>
