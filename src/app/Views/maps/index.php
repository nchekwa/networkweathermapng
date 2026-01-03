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
                    <?php if ($auth->isAdmin()): ?>
                    <button class="btn-icon move-map-btn" data-map-id="<?= $map['id'] ?>" data-current-group="<?= $map['group_id'] ?>" title="Move to another group">
                        ↔️
                    </button>
                    <?php endif; ?>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Move Map Modal -->
<div id="moveMapModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Move Map</h3>
            <button type="button" class="close-btn" onclick="closeMoveModal()">&times;</button>
        </div>
        <form id="moveMapForm">
            <input type="hidden" id="moveMapId" name="map_id">
            <div class="form-group">
                <label for="moveMapGroup">Select Destination Group:</label>
                <select id="moveMapGroup" name="group_id" class="form-control" required>
                    <?php foreach ($groups as $group): ?>
                    <option value="<?= $group['id'] ?>"><?= htmlspecialchars($group['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeMoveModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Move Map</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('moveMapModal');
    const form = document.getElementById('moveMapForm');
    const btns = document.querySelectorAll('.move-map-btn');
    
    btns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const mapId = this.dataset.mapId;
            const currentGroupId = this.dataset.currentGroup;
            
            document.getElementById('moveMapId').value = mapId;
            document.getElementById('moveMapGroup').value = currentGroupId;
            
            modal.style.display = 'flex';
        });
    });
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const mapId = document.getElementById('moveMapId').value;
        const groupId = document.getElementById('moveMapGroup').value;
        
        fetch(`/admin/maps/move/${mapId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ group_id: groupId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'Failed to move map'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while moving the map');
        });
    });
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target == modal) {
            closeMoveModal();
        }
    }
});

function closeMoveModal() {
    document.getElementById('moveMapModal').style.display = 'none';
}
</script>

<style>
.map-info {
    position: relative;
}

.move-map-btn {
    position: absolute;
    right: 0;
    bottom: 0;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1.2rem;
    padding: 5px;
    opacity: 0.6;
    transition: opacity 0.2s;
    z-index: 10;
}

.move-map-btn:hover {
    opacity: 1;
    transform: scale(1.1);
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    align-items: center;
    justify-content: center;
}

.modal-content {
    background-color: #fff;
    padding: 20px;
    border-radius: 8px;
    width: 100%;
    max-width: 400px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.modal-header h3 {
    margin: 0;
}

.close-btn {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #666;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background-color: #5a6268;
}
</style>
