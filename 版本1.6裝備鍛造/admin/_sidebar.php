<?php
$current = basename($_SERVER['PHP_SELF']);
function nav($href, $icon, $label, $current) {
    $active = ($current === $href) ? 'active' : '';
    echo "<a href='$href' class='sidebar-link $active'>$icon <span>$label</span></a>";
}
?>
<aside class="sidebar">
  <div class="sidebar-logo">
    <span class="logo-icon">⚔️</span>
    <h2>後台管理</h2>
    <p>TAR GAME ADMIN</p>
  </div>
  <nav class="sidebar-nav">
    <div class="sidebar-section">主選單</div>
    <?php nav('index.php',      '📊', '系統總覽', $current); ?>
    <?php nav('players.php',    '👥', '玩家管理', $current); ?>
    <?php nav('records.php',    '📋', '紀錄查詢', $current); ?>
    <div class="sidebar-section" style="margin-top:12px;">遊戲系統</div>
    <?php nav('arena.php',      '🏟️', '競技場管理', $current); ?>
    <div class="sidebar-section" style="margin-top:12px;">系統層</div>
    <?php nav('db_layer.php',   '🗄️', '資料庫層', $current); ?>
    <?php nav('api_module.php', '🔌', 'API 模組', $current); ?>
    <div class="sidebar-section" style="margin-top:12px;">遊戲前台</div>
    <a href="../index.php" class="sidebar-link" target="_blank">🏠 <span>主城鎮</span></a>
    <a href="../tower.php" class="sidebar-link" target="_blank">🗼 <span>塔探索</span></a>
  </nav>
  <div class="sidebar-footer">
    <div class="sidebar-player">
      <div class="avatar">👤</div>
      <div>
        <div class="player-name"><?= htmlspecialchars($_SESSION['admin_user'] ?? 'Admin') ?></div>
        <div class="player-level">超級管理員</div>
      </div>
    </div>
    <a href="logout.php" class="sidebar-logout">⏻ 登出</a>
  </div>
</aside>
<div class="main">
