<div class="maps-page">
    <div class="page-header">
        <h1>Network Weathermaps</h1>
        <div class="page-actions">
            <a href="/cycle" class="btn btn-primary">Cycle Maps</a>
        </div>
    </div>
    
    <?php if (count($groups) > 1): ?>
    <div class="map-groups-tabs">
        <ul class="tabs">
            <li><a href="?group_id=0" class="<?= $currentGroup === 0 ? 'active' : '' ?>">All</a></li>
            <?php foreach ($groups as $group): ?>
            <li>
                <a href="?group_id=<?= $group['id'] ?>" 
                   class="<?= $currentGroup === (int)$group['id'] ? 'active' : '' ?>">
                    <?= htmlspecialchars($group['name']) ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <?php if (empty($maps)): ?>
    <div class="empty-state">
        <p>No maps available.</p>
        <?php if ($auth->isAdmin()): ?>
        <p><a href="/admin/maps">Create your first map</a></p>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="maps-grid">
        <?php foreach ($maps as $map): ?>
        <div class="map-card">
            <a href="/map/<?= $map['id'] ?>" class="map-link">
                <div class="map-thumbnail">
                    <img src="/map/<?= $map['id'] ?>/thumb?fast=1" 
                         alt="<?= htmlspecialchars($map['name']) ?>"
                         loading="lazy">
                </div>
                <div class="map-info">
                    <h3><?= htmlspecialchars($map['title_cache'] ?: $map['name']) ?></h3>
                    <?php if ($map['group_name']): ?>
                    <span class="map-group"><?= htmlspecialchars($map['group_name']) ?></span>
                    <?php endif; ?>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
