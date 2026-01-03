<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'NetworkWeathermapNG') ?></title>
    <link rel="stylesheet" href="/assets/css/weathermap.css">
    <script src="/assets/js/weathermap.js" defer></script>
</head>
<body>
    <header class="main-header">
        <div class="header-container">
            <div class="logo">
                <a href="/">NetworkWeathermapNG</a>
            </div>
            <nav class="main-nav">
                <ul>
                    <li><a href="/" class="<?= ($_SERVER['REQUEST_URI'] === '/' || str_starts_with($_SERVER['REQUEST_URI'], '/maps')) ? 'active' : '' ?>">Maps</a></li>
                    <?php if ($auth->isAdmin()): ?>
                    <li><a href="/editor" class="<?= str_starts_with($_SERVER['REQUEST_URI'], '/editor') ? 'active' : '' ?>">Editor</a></li>
                    <li><a href="/admin" class="<?= str_starts_with($_SERVER['REQUEST_URI'], '/admin') ? 'active' : '' ?>">Admin</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            <div class="user-menu">
                <?php if ($user): ?>
                    <span class="username"><?= htmlspecialchars($user['username']) ?></span>
                    <a href="/account/password" class="login-btn">Change Password</a>
                    <a href="/logout" class="logout-btn">Logout</a>
                <?php else: ?>
                    <a href="/login" class="login-btn">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
    <main class="main-content">
        <?php 
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        if (!empty($flash)): 
        ?>
        <div class="flash-messages">
            <?php if (isset($flash['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($flash['success']) ?></div>
            <?php endif; ?>
            <?php if (isset($flash['error'])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($flash['error']) ?></div>
            <?php endif; ?>
            <?php if (isset($flash['info'])): ?>
            <div class="alert alert-info"><?= htmlspecialchars($flash['info']) ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?= $content ?>
    </main>
    
    <footer class="main-footer">
        <div class="footer-container">
            <p>&copy; <?= date('Y') ?> NetworkWeathermapNG</p>
        </div>
    </footer>
</body>
</html>
