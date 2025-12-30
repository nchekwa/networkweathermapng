<?php if ($fullscreen): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Map Cycle - Zabbix Weathermap</title>
    <link rel="stylesheet" href="/assets/css/weathermap.css">
    <style>
        body { margin: 0; padding: 0; background: #1a1a1a; }
        .cycle-fullscreen { width: 100vw; height: 100vh; display: flex; flex-direction: column; }
        .cycle-controls { background: rgba(0,0,0,0.8); padding: 10px; color: #fff; text-align: center; }
        .cycle-map-container { flex: 1; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .cycle-map-container img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .cycle-map { display: none; }
        .cycle-map.active { display: block; }
    </style>
</head>
<body>
<?php endif; ?>

<div class="<?= $fullscreen ? 'cycle-fullscreen' : 'cycle-page' ?>">
    <div class="cycle-controls">
        <button id="cycle-prev" class="btn btn-sm">&laquo; Prev</button>
        <button id="cycle-pause" class="btn btn-sm">Pause</button>
        <button id="cycle-next" class="btn btn-sm">Next &raquo;</button>
        <span class="cycle-info">
            Map <span id="current-map">1</span> of <span id="total-maps"><?= count($maps) ?></span>
        </span>
        <?php if (!$fullscreen): ?>
        <a href="/cycle?fullscreen=1&group=<?= $groupId ?>" class="btn btn-sm">Fullscreen</a>
        <a href="/" class="btn btn-sm">Exit</a>
        <?php else: ?>
        <a href="/cycle?group=<?= $groupId ?>" class="btn btn-sm">Exit Fullscreen</a>
        <?php endif; ?>
    </div>
    
    <div class="cycle-map-container">
        <?php foreach ($maps as $index => $map): ?>
        <div class="cycle-map <?= $index === 0 ? 'active' : '' ?>" data-map-id="<?= $map['id'] ?>">
            <img src="/map/<?= $map['id'] ?>/image?t=<?= time() ?>" 
                 alt="<?= htmlspecialchars($map['name']) ?>">
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
(function() {
    const maps = document.querySelectorAll('.cycle-map');
    const totalMaps = maps.length;
    const refreshInterval = <?= $refreshInterval ?> * 1000;
    const cycleInterval = Math.max(5000, refreshInterval / totalMaps);
    
    let currentIndex = 0;
    let isPaused = false;
    let timer = null;
    
    function showMap(index) {
        maps.forEach((map, i) => {
            map.classList.toggle('active', i === index);
        });
        document.getElementById('current-map').textContent = index + 1;
    }
    
    function nextMap() {
        currentIndex = (currentIndex + 1) % totalMaps;
        showMap(currentIndex);
    }
    
    function prevMap() {
        currentIndex = (currentIndex - 1 + totalMaps) % totalMaps;
        showMap(currentIndex);
    }
    
    function startCycle() {
        if (timer) clearInterval(timer);
        timer = setInterval(nextMap, cycleInterval);
    }
    
    function stopCycle() {
        if (timer) clearInterval(timer);
        timer = null;
    }
    
    document.getElementById('cycle-prev').addEventListener('click', function() {
        prevMap();
        if (!isPaused) { stopCycle(); startCycle(); }
    });
    
    document.getElementById('cycle-next').addEventListener('click', function() {
        nextMap();
        if (!isPaused) { stopCycle(); startCycle(); }
    });
    
    document.getElementById('cycle-pause').addEventListener('click', function() {
        isPaused = !isPaused;
        this.textContent = isPaused ? 'Play' : 'Pause';
        if (isPaused) {
            stopCycle();
        } else {
            startCycle();
        }
    });
    
    // Refresh images periodically
    setInterval(function() {
        maps.forEach(function(mapDiv) {
            const img = mapDiv.querySelector('img');
            if (img) {
                const src = img.src.split('?')[0];
                img.src = src + '?t=' + Date.now();
            }
        });
    }, refreshInterval);
    
    // Start cycling
    if (totalMaps > 1) {
        startCycle();
    }
})();
</script>

<?php if ($fullscreen): ?>
</body>
</html>
<?php endif; ?>
