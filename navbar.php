<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$is_admin = !empty($_SESSION['is_admin']);
$current = basename($_SERVER['PHP_SELF']);
?>

<nav class="nav">
    <button class="menu-toggle" aria-label="Menü">☰</button>
    <a href="index.php" class="brand">
        <img src="assets/logo.png" alt="Felveo Logo" class="logo">
    </a>
    
    <div class="links">
        <?php if ($is_admin): ?>
            <!-- ADMIN MENÜ -->
            <a href="admin_dashboard.php" class="<?php echo $current === 'admin_dashboard.php' ? 'primary' : ''; ?>">👥 Diákok</a>
            <a href="admin_settings.php" class="<?php echo $current === 'admin_settings.php' ? 'primary' : ''; ?>">⚙️ Beállítások</a>
            <a href="import.php" class="<?php echo $current === 'import.php' ? 'primary' : ''; ?>">📤 Import</a>
            <a href="admin_change_password.php" class="<?php echo $current === 'admin_change_password.php' ? 'primary' : ''; ?>">🔑 Jelszó</a>
            <a href="admin_logout.php" class="logout">🚪 Kijelentkezés</a>
        <?php else: ?>
            <!-- FELHASZNÁLÓ MENÜ -->
            <a href="eredmeny.php" class="<?php echo $current === 'eredmeny.php' ? 'primary' : ''; ?>">📋 Eredmények</a>
            <a href="student_view.php" class="<?php echo $current === 'student_view.php' ? 'primary' : ''; ?>">📄 Dolgozataim</a>
            <a href="admin_login.php" class="primary">🔐 Admin</a>
        <?php endif; ?>
        <button id="themeToggle" class="theme-btn">🌙</button>
    </div>
</nav>
