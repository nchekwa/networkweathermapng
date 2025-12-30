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

.form-actions {
    margin-top: 2rem;
    display: flex;
    gap: 1rem;
}
</style>
