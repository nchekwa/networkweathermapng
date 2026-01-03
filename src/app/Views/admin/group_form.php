<div class="content-header">
    <h1><?= htmlspecialchars($title) ?></h1>
    <div class="header-actions">
        <a href="/admin/groups" class="btn btn-secondary">← Back to Groups</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= $action === 'create' ? '/admin/groups/create' : '/admin/groups/edit/' . $group['id'] ?>">
            <div class="form-group">
                <label for="name">Group Name *</label>
                <input type="text" id="name" name="name" class="form-control" 
                       value="<?= htmlspecialchars($group['name'] ?? '') ?>" 
                       placeholder="Enter group name" required>
            </div>
            
            <div class="form-group">
                <label>User Access</label>
                <div class="users-list">
                    <?php if (empty($users)): ?>
                        <p class="text-muted">No users available.</p>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <?php if ($user['username'] !== 'admin'): ?>
                            <label class="checkbox-label">
                                <input type="checkbox" name="users[]" value="<?= $user['id'] ?>" 
                                    <?= in_array($user['id'], $groupUsers ?? []) ? 'checked' : '' ?>>
                                <?= htmlspecialchars($user['username']) ?> 
                                <span class="text-muted small">(<?= htmlspecialchars($user['role']) ?>)</span>
                            </label>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <small class="form-text text-muted">Select users who can access this map group.</small>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $action === 'create' ? 'Create Group' : 'Save Changes' ?>
                </button>
                <a href="/admin/groups" class="btn btn-secondary">Cancel</a>
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

.users-list {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 1rem;
    max-height: 200px;
    overflow-y: auto;
    max-width: 500px;
    background-color: #fff;
}

.users-list .checkbox-label {
    display: flex;
    align-items: center;
    margin-bottom: 0.5rem;
    cursor: pointer;
}

.users-list .checkbox-label:last-child {
    margin-bottom: 0;
}

.users-list .checkbox-label input {
    margin-right: 0.5rem;
    width: 16px;
    height: 16px;
}

.text-muted {
    color: #6c757d;
}

.small {
    font-size: 0.85em;
    margin-left: 0.5rem;
}

.form-text {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.875rem;
}

.form-actions {
    margin-top: 2rem;
    display: flex;
    gap: 1rem;
}
</style>
