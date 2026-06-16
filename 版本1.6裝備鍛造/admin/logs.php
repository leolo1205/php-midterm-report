<?php
require_once __DIR__ . '/../includes/auth.php';
$admin = require_admin();
$pdo   = db();

$tab = $_GET['tab'] ?? 'battle';

$battles = $pdo->query("
    SELECT b.id, u.username, b.floor, b.result, b.exp_gain, b.gold_gain, b.fought_at
    FROM battle_records b
    JOIN users u ON b.user_id = u.id
    ORDER BY b.fought_at DESC
    LIMIT 100
")->fetchAll();

$trainings = $pdo->query("
    SELECT t.id, u.username, t.stat_gained, t.exp_gained, t.trained_at
    FROM training_logs t
    JOIN users u ON t.user_id = u.id
    ORDER BY t.trained_at DESC
    LIMIT 100
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>後台 — 紀錄查詢</title>
<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/admin.css">
<style>
  .admin-wrap { display:flex; min-height:100vh; }
  .sidebar {
    width:210px; background:var(--surface); border-right:1px solid var(--border);
    padding:20px 0; display:flex; flex-direction:column; gap:2px; flex-shrink:0;
  }
  .sidebar .logo { padding:0 20px 16px; font-size:1.05em; font-weight:700; color:var(--primary); border-bottom:1px solid var(--border); margin-bottom:8px; }
  .sidebar a { padding:10px 20px; color:var(--muted); text-decoration:none; font-size:.9em; display:flex; align-items:center; gap:8px; transition:background .15s,color .15s; border-left:3px solid transparent; }
  .sidebar a:hover  { background:var(--primary-dim); color:var(--primary); }
  .sidebar a.active { background:var(--primary-dim); color:var(--primary); border-left-color:var(--primary); }
  .sidebar .spacer { flex:1; }
  .sidebar .user-info { padding:14px 20px; border-top:1px solid var(--border); font-size:.82em; color:var(--muted); }

  .admin-main { flex:1; padding:32px; overflow-y:auto; }
  .page-title { font-size:1.35em; font-weight:700; color:var(--text); margin-bottom:20px; }

  /* Tab */
  .tabs { display:flex; gap:4px; margin-bottom:20px; border-bottom:2px solid var(--border); }
  .tabs a {
    padding:10px 20px; text-decoration:none; font-size:.92em; font-weight:600;
    color:var(--muted); border-bottom:3px solid transparent; margin-bottom:-2px;
    transition:color .15s;
  }
  .tabs a.active { color:var(--primary); border-bottom-color:var(--primary); }
  .tabs a:hover  { color:var(--primary); }

  table { width:100%; border-collapse:collapse; font-size:.88em; }
  th { background:var(--surface2); color:var(--muted); padding:10px 14px; text-align:left; border-bottom:2px solid var(--border); font-weight:600; font-size:.82em; }
  td { padding:10px 14px; border-bottom:1px solid var(--border); }
  tr:hover td { background:var(--surface2); }
  tr:last-child td { border-bottom:none; }

  .win  { color:var(--green); font-weight:600; }
  .lose { color:var(--red);   font-weight:600; }
  .empty-state { text-align:center; padding:48px; color:var(--muted); font-size:.95em; }
</style>
</head>
<body>
<div class="admin-wrap">

  <div class="sidebar">
    <div class="logo">⚔ 異界塔後台</div>
    <a href="dashboard.php">📊 總覽</a>
    <a href="users.php">👥 玩家管理</a>
    <a href="logs.php" class="active">📋 紀錄查詢</a>
    <a href="../game.php" style="color:var(--secondary);">🏰 前往城鎮</a>
    <div class="spacer"></div>
    <div class="user-info">
      登入：<?= htmlspecialchars($admin['username']) ?><br>
      <a href="logout.php" style="color:var(--red);text-decoration:none;">登出</a>
    </div>
  </div>

  <div class="admin-main">
    <div class="page-title">📋 紀錄查詢</div>

    <div class="tabs">
      <a href="?tab=battle"   class="<?= $tab==='battle'   ? 'active' : '' ?>">⚔ 戰鬥紀錄（<?= count($battles) ?>）</a>
      <a href="?tab=training" class="<?= $tab==='training' ? 'active' : '' ?>">🏋 訓練紀錄（<?= count($trainings) ?>）</a>
    </div>

    <?php if ($tab === 'battle'): ?>
    <div class="card" style="padding:0;overflow:hidden">
      <?php if (empty($battles)): ?>
        <div class="empty-state">尚無戰鬥紀錄<br><small>戰鬥完成後將自動記錄於此</small></div>
      <?php else: ?>
      <table>
        <thead>
          <tr><th>玩家</th><th>層數</th><th>結果</th><th>獲得 EXP</th><th>獲得金幣</th><th>時間</th></tr>
        </thead>
        <tbody>
          <?php foreach ($battles as $b): ?>
          <tr>
            <td style="font-weight:600"><?= htmlspecialchars($b['username']) ?></td>
            <td><?= $b['floor'] ?> 層</td>
            <td class="<?= $b['result'] ?>"><?= $b['result']==='win' ? '✅ 勝利' : '❌ 敗北' ?></td>
            <td style="color:var(--primary)">+<?= $b['exp_gain'] ?></td>
            <td style="color:var(--secondary)">+<?= $b['gold_gain'] ?></td>
            <td style="color:var(--muted);font-size:.85em"><?= substr($b['fought_at'],0,16) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

    <?php else: ?>
    <div class="card" style="padding:0;overflow:hidden">
      <?php if (empty($trainings)): ?>
        <div class="empty-state">尚無訓練紀錄<br><small>玩家訓練後將自動記錄於此</small></div>
      <?php else: ?>
      <table>
        <thead>
          <tr><th>玩家</th><th>提升屬性</th><th>獲得 EXP</th><th>時間</th></tr>
        </thead>
        <tbody>
          <?php foreach ($trainings as $t): ?>
          <tr>
            <td style="font-weight:600"><?= htmlspecialchars($t['username']) ?></td>
            <td style="color:var(--primary)"><?= htmlspecialchars($t['stat_gained']) ?></td>
            <td style="color:var(--primary)">+<?= $t['exp_gained'] ?></td>
            <td style="color:var(--muted);font-size:.85em"><?= substr($t['trained_at'],0,16) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div>
</div>
</body>
</html>
