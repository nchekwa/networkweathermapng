<div class="content-header">
    <h1><?= htmlspecialchars($title) ?></h1>
    <div class="header-actions">
        <a href="/admin/maps" class="btn btn-secondary">← Back to Maps</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= $action === 'create' ? '/admin/maps/create' : '/admin/maps/edit/' . $map['id'] ?>">
            <div class="form-group">
                <label for="name">Map Name *</label>
                <input type="text" id="name" name="name" class="form-control" 
                       value="<?= htmlspecialchars($map['name'] ?? '') ?>" 
                       placeholder="Enter map name" required>
            </div>
            
            <div class="form-group">
                <label for="group_id">Group</label>
                <select id="group_id" name="group_id" class="form-control">
                    <?php foreach ($groups as $group): ?>
                    <option value="<?= $group['id'] ?>" 
                            <?= ($map['group_id'] ?? 1) == $group['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($group['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <?php if ($action === 'edit'): ?>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="active" value="1" 
                           <?= ($map['active'] ?? 0) ? 'checked' : '' ?>>
                    Active (visible to users)
                </label>
            </div>
            
            <div class="form-group">
                <label>Config File</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($map['config_file'] ?? '') ?>" disabled>
                <small class="text-muted">Configuration file cannot be changed after creation</small>
            </div>
            <?php endif; ?>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $action === 'create' ? 'Create Map' : 'Save Changes' ?>
                </button>
                <a href="/admin/maps" class="btn btn-secondary">Cancel</a>
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

.text-muted {
    color: #6c757d;
    font-size: 0.875rem;
}

.form-actions {
    margin-top: 2rem;
    display: flex;
    gap: 1rem;
}
</style>
