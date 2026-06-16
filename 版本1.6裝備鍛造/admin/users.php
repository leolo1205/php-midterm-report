<?php
require_once __DIR__ . '/../includes/auth.php';
$admin = require_admin();
$pdo   = db();

// 封鎖 / 解鎖 / 重置
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tid    = (int)($_POST['target_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($tid && $tid !== $admin['id']) {
        if ($action === 'ban') {
            $ban = (int)($_POST['ban'] ?? 0);
            $pdo->prepare('UPDATE users SET is_banned=? WHERE id=?')->execute([$ban, $tid]);
        } elseif ($action === 'reset') {
            $pdo->prepare("UPDATE users SET
                level=1, exp=0, str=10, agi=10, con=10, intel=10, per=10, cha=10,
                gold=0, max_floor=0, hp=100, max_hp=100, last_train_time=NULL
                WHERE id=?")->execute([$tid]);
        }
    }
    header('Location: users.php'); exit;
}

$search  = trim($_GET['q'] ?? '');
$players = $pdo->prepare("
    SELECT id, username, is_banned, created_at,
           level, exp, max_floor, gold,
           str, agi, con, intel, per, cha,
           hp, max_hp, last_train_time
    FROM users
    WHERE role='player'" . ($search ? " AND username LIKE ?" : "") . "
    ORDER BY max_floor DESC, level DESC
");
$players->execute($search ? ["%$search%"] : []);
$players = $players->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>後台 — 玩家管理</title>
<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/admin.css">
<style>
  .admin-wrap  { display:flex; min-height:100vh; }
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

  .admin-main  { flex:1; padding:32px; overflow-y:auto; }
  .page-title  { font-size:1.35em; font-weight:700; color:var(--text); margin-bottom:20px; }

  .toolbar { display:flex; align-items:center; gap:12px; margin-bottom:20px; flex-wrap:wrap; }
  .search-box {
    padding:8px 14px; border:1.5px solid var(--border); border-radius:8px;
    font-size:.9em; font-family:var(--font); background:var(--surface2); color:var(--text); width:220px;
  }
  .search-box:focus { outline:none; border-color:var(--primary); }

  table { width:100%; border-collapse:collapse; font-size:.88em; }
  th { background:var(--surface2); color:var(--muted); padding:10px 12px; text-align:left; border-bottom:2px solid var(--border); font-weight:600; font-size:.82em; white-space:nowrap; }
  td { padding:9px 12px; border-bottom:1px solid var(--border); vertical-align:middle; }
  tr:hover td { background:var(--surface2); }
  tr:last-child td { border-bottom:none; }

  .badge-ban { background:rgba(239,68,68,.12); color:var(--red); padding:2px 8px; border-radius:4px; font-size:.8em; font-weight:600; }
  .badge-ok  { background:rgba(16,185,129,.12); color:var(--green); padding:2px 8px; border-radius:4px; font-size:.8em; font-weight:600; }

  .detail-row { display:none; }
  .detail-row td { background:var(--surface2); padding:16px 20px; }
  .stat-grid { display:flex; flex-wrap:wrap; gap:8px 20px; font-size:.88em; }
  .stat-grid span { color:var(--muted); }
  .stat-grid b { color:var(--primary); }

  .btn-sm { padding:5px 11px; font-size:.8em; border:none; border-radius:6px; cursor:pointer; font-family:var(--font); font-weight:600; transition:filter .15s; }
  .btn-sm:hover { filter:brightness(1.1); }

  tr.expandable:hover { cursor:pointer; }
</style>
</head>
<body>
<div class="admin-wrap">

  <div class="sidebar">
    <div class="logo">⚔ 異界塔後台</div>
    <a href="dashboard.php">📊 總覽</a>
    <a href="users.php" class="active">👥 玩家管理</a>
    <a href="logs.php">📋 紀錄查詢</a>
    <a href="../game.php" style="color:var(--secondary);">🏰 前往城鎮</a>
    <div class="spacer"></div>
    <div class="user-info">
      登入：<?= htmlspecialchars($admin['username']) ?><br>
      <a href="logout.php" style="color:var(--red);text-decoration:none;">登出</a>
    </div>
  </div>

  <div class="admin-main">
    <div class="page-title">👥 玩家管理（共 <?= count($players) ?> 人）</div>

    <div class="toolbar">
      <form method="GET" style="display:contents">
        <input class="search-box" name="q" placeholder="搜尋玩家名稱…"
               value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-primary" style="padding:8px 16px;font-size:.88em">搜尋</button>
        <?php if($search): ?>
          <a href="users.php" class="btn btn-ghost" style="padding:8px 16px;font-size:.88em">清除</a>
        <?php endif; ?>
      </form>
      <span style="color:var(--muted);font-size:.85em;margin-left:auto">點擊列可展開屬性詳情</span>
    </div>

    <div class="card" style="padding:0;overflow:hidden">
    <table>
      <thead>
        <tr>
          <th>玩家名稱</th>
          <th>等級</th>
          <th>最高層數</th>
          <th>HP</th>
          <th>金幣</th>
          <th>最後訓練</th>
          <th>狀態</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($players as $p): $rid = 'r'.$p['id']; ?>
        <tr class="expandable" onclick="toggleDetail('<?= $rid ?>')">
          <td style="font-weight:600"><?= htmlspecialchars($p['username']) ?></td>
          <td>Lv. <?= $p['level'] ?></td>
          <td style="color:var(--primary);font-weight:700"><?= $p['max_floor'] ?> 層</td>
          <td style="font-size:.85em"><?= $p['hp'] ?> / <?= $p['max_hp'] ?></td>
          <td style="color:var(--secondary)"><?= number_format($p['gold']) ?></td>
          <td style="color:var(--muted);font-size:.82em">
            <?= $p['last_train_time'] ? substr($p['last_train_time'],0,16) : '從未訓練' ?>
          </td>
          <td>
            <?= $p['is_banned']
              ? '<span class="badge-ban">封鎖中</span>'
              : '<span class="badge-ok">正常</span>' ?>
          </td>
          <td onclick="event.stopPropagation()">
            <!-- 封鎖/解鎖 -->
            <form method="POST" style="display:inline">
              <input type="hidden" name="target_id" value="<?= $p['id'] ?>">
              <input type="hidden" name="action"    value="ban">
              <input type="hidden" name="ban"        value="<?= $p['is_banned'] ? 0 : 1 ?>">
              <button type="submit"
                      class="btn-sm <?= $p['is_banned'] ? 'btn-green' : 'btn-red' ?>"
                      style="background:<?= $p['is_banned'] ? 'var(--green)' : 'var(--red)' ?>;color:#fff"
                      onclick="return confirm('確定要<?= $p['is_banned'] ? '解鎖' : '封鎖' ?>此玩家？')">
                <?= $p['is_banned'] ? '解鎖' : '封鎖' ?>
              </button>
            </form>
            <!-- 重置 -->
            <form method="POST" style="display:inline;margin-left:6px">
              <input type="hidden" name="target_id" value="<?= $p['id'] ?>">
              <input type="hidden" name="action"    value="reset">
              <button type="submit" class="btn-sm"
                      style="background:var(--muted);color:#fff"
                      onclick="return confirm('確定重置此玩家角色資料？此操作不可還原！')">
                重置
              </button>
            </form>
          </td>
        </tr>
        <!-- 展開的屬性詳情列 -->
        <tr class="detail-row" id="<?= $rid ?>">
          <td colspan="8">
            <div class="stat-grid">
              <div><span>STR </span><b><?= $p['str'] ?></b></div>
              <div><span>AGI </span><b><?= $p['agi'] ?></b></div>
              <div><span>CON </span><b><?= $p['con'] ?></b></div>
              <div><span>INT </span><b><?= $p['intel'] ?></b></div>
              <div><span>PER </span><b><?= $p['per'] ?></b></div>
              <div><span>CHA </span><b><?= $p['cha'] ?></b></div>
              <div style="margin-left:16px"><span>EXP </span><b><?= $p['exp'] ?> / <?= $p['level']*100 ?></b></div>
              <div><span>加入時間 </span><b style="color:var(--muted);font-weight:400"><?= substr($p['created_at']??'',0,10) ?></b></div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>

<script>
function toggleDetail(id) {
  const row = document.getElementById(id);
  if (!row) return;
  row.style.display = row.style.display === 'table-row' ? 'none' : 'table-row';
}
</script>
</body>
</html>
