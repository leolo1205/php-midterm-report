<?php
require_once 'auth.php';
require_once '../db.php';

$msg = $msg_type = '';

// ── API 操作 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        die('安全驗證失敗，請重新整理頁面後再試。');
    }
    $action = $_POST['action'] ?? '';
    $uid    = (int)($_POST['user_id'] ?? 0);

    if ($uid > 0) {
        if ($action === 'ban') {
            $conn->query("UPDATE users SET is_banned=1 WHERE id=$uid");
            $msg = "玩家 ID:{$uid} 已被封鎖。"; $msg_type = 'danger';
        } elseif ($action === 'unban') {
            $conn->query("UPDATE users SET is_banned=0 WHERE id=$uid");
            $msg = "玩家 ID:{$uid} 已解除封鎖。"; $msg_type = 'success';
        } elseif ($action === 'reset') {
            $conn->query("UPDATE users SET level=1,exp=0,hp=100,max_hp=100,dmg=10,def=0,stat_points=0,max_floor=0,gold=0,last_train_time=NULL WHERE id=$uid");
            $conn->query("DELETE FROM user_skills WHERE user_id=$uid");
            $msg = "玩家 ID:{$uid} 角色已重置。"; $msg_type = 'warning';
        }
    }
    header('Location: players.php?msg='.urlencode($msg).'&type='.$msg_type);
    exit;
}

// ── 從 redirect 取得 msg ──
if (isset($_GET['msg'])) { $msg = $_GET['msg']; $msg_type = $_GET['type'] ?? 'success'; }

// ── 查詢玩家 ──
$search = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'all';

$where = [];
if ($search !== '') $where[] = "username LIKE '%".($conn->real_escape_string($search))."%'";
if ($filter === 'banned') $where[] = "is_banned=1";
if ($filter === 'active') $where[] = "is_banned=0";
$where_sql = $where ? 'WHERE '.implode(' AND ', $where) : '';

$players = $conn->query("SELECT u.*,
    (SELECT COUNT(*) FROM user_skills us WHERE us.user_id=u.id) as skill_count,
    (SELECT COUNT(*) FROM battle_logs bl WHERE bl.user_id=u.id) as battle_count
    FROM users u $where_sql ORDER BY u.level DESC, u.exp DESC");
$total = $conn->query("SELECT COUNT(*) FROM users $where_sql")->fetch_row()[0];

// ── 單一玩家詳情 ──
$detail = null;
if (isset($_GET['id'])) {
    $did = (int)$_GET['id'];
    $detail = $conn->query("SELECT * FROM users WHERE id=$did")->fetch_assoc();
    $detail_skills = $conn->query("SELECT * FROM user_skills WHERE user_id=$did");
    $detail_battles = $conn->query("SELECT * FROM battle_logs WHERE user_id=$did ORDER BY created_at DESC LIMIT 20");
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>玩家管理 — 後台管理</title>
<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/admin.css">
</head>
<body>
<?php include '_sidebar.php'; ?>

  <div class="admin-topbar">
    <div class="page-title">👥 玩家管理</div>
    <div class="breadcrumb">後台管理 / <span>玩家管理</span></div>
  </div>

  <div class="content">
    <?php if ($msg): ?>
    <div class="alert alert-<?= $msg_type === 'danger' ? 'danger' : 'success' ?>">
      <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <?php if ($detail): ?>
    <!-- ── 玩家詳情面板 ── -->
    <div class="section" style="margin-bottom:24px;">
      <div class="section-header">
        <h3>📄 玩家詳情 — <?= htmlspecialchars($detail['username']) ?></h3>
        <a href="players.php" class="btn btn-primary">← 返回列表</a>
      </div>
      <div style="padding:22px;display:grid;grid-template-columns:1fr 1fr;gap:24px;">
        <div>
          <h4 style="color:#4fc3f7;margin-bottom:14px;font-size:13px;letter-spacing:1px;">基本資訊</h4>
          <table class="tbl" style="font-size:13px;">
            <tr><td style="color:#666;width:120px;">ID</td><td>#<?= $detail['id'] ?></td></tr>
            <tr><td style="color:#666;">名稱</td><td><?= htmlspecialchars($detail['username']) ?></td></tr>
            <tr><td style="color:#666;">等級</td><td style="color:#4fc3f7;font-weight:700;">Lv.<?= $detail['level'] ?></td></tr>
            <tr><td style="color:#666;">EXP</td><td><?= $detail['exp'] ?> / <?= $detail['level']*100 ?></td></tr>
            <tr><td style="color:#666;">HP</td><td><?= $detail['hp'] ?> / <?= $detail['max_hp'] ?></td></tr>
            <tr><td style="color:#666;">傷害</td><td style="color:#ef5350;"><?= $detail['dmg'] ?></td></tr>
            <tr><td style="color:#666;">防禦</td><td style="color:#66bb6a;"><?= $detail['def'] ?></td></tr>
            <tr><td style="color:#666;">屬性點</td><td><?= $detail['stat_points'] ?></td></tr>
            <tr><td style="color:#666;">金幣</td><td style="color:#ffca28;">💰 <?= number_format($detail['gold']) ?></td></tr>
            <tr><td style="color:#666;">最高樓層</td><td><?= $detail['max_floor'] ?>F</td></tr>
            <tr><td style="color:#666;">上次訓練</td><td><?= $detail['last_train_time'] ?? '—' ?></td></tr>
            <tr><td style="color:#666;">狀態</td><td>
              <?php if ($detail['is_banned']): ?>
              <span class="tag tag-banned">🔒 已封鎖</span>
              <?php else: ?>
              <span class="tag tag-active">✅ 正常</span>
              <?php endif; ?>
            </td></tr>
          </table>
        </div>
        <div>
          <h4 style="color:#4fc3f7;margin-bottom:14px;font-size:13px;letter-spacing:1px;">技能熟練度</h4>
          <?php while($sk = $detail_skills->fetch_assoc()):
            $skill_names = ['crit'=>'爆擊熟練度','dodge'=>'閃避熟練度'];
            $need = ($sk['level']+1)*10;
            $pct = min(100, round($sk['exp']/$need*100));
          ?>
          <div style="margin-bottom:14px;">
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;">
              <span><?= $skill_names[$sk['skill_id']] ?? $sk['skill_id'] ?></span>
              <span style="color:#4fc3f7;">Lv.<?= $sk['level'] ?> (<?= $sk['exp'] ?>/<?= $need ?>)</span>
            </div>
            <div style="height:7px;background:#1a1a2e;border-radius:4px;overflow:hidden;">
              <div style="width:<?=$pct?>%;height:100%;background:linear-gradient(90deg,#1565c0,#4fc3f7);border-radius:4px;"></div>
            </div>
          </div>
          <?php endwhile; ?>

          <h4 style="color:#4fc3f7;margin:20px 0 14px;font-size:13px;letter-spacing:1px;">管理操作</h4>
          <form method="POST" onsubmit="return confirmAction(this);" data-action="reset">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= $detail['id'] ?>">
            <input type="hidden" name="action" value="reset">
            <button type="submit" class="btn btn-warning" style="width:100%;margin-bottom:10px;">
              🔄 重置角色（清除所有進度）
            </button>
          </form>
          <form method="POST" onsubmit="return confirmAction(this);" data-action="<?= $detail['is_banned'] ? 'unban' : 'ban' ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= $detail['id'] ?>">
            <input type="hidden" name="action" value="<?= $detail['is_banned'] ? 'unban' : 'ban' ?>">
            <button type="submit" class="btn <?= $detail['is_banned'] ? 'btn-success' : 'btn-danger' ?>" style="width:100%;">
              <?= $detail['is_banned'] ? '🔓 解除封鎖' : '🚫 封鎖帳號' ?>
            </button>
          </form>
        </div>
      </div>

      <!-- 近期戰鬥記錄 -->
      <div style="padding:0 22px 22px;">
        <h4 style="color:#4fc3f7;margin-bottom:14px;font-size:13px;letter-spacing:1px;">近期戰鬥紀錄（最新20筆）</h4>
        <?php if ($detail_battles->num_rows > 0): ?>
        <table class="tbl">
          <thead><tr><th>時間</th><th>樓層</th><th>結果</th><th>造成傷害</th><th>承受傷害</th><th>獲得EXP</th><th>獲得金幣</th></tr></thead>
          <tbody>
          <?php while($b=$detail_battles->fetch_assoc()): ?>
          <tr>
            <td style="font-size:12px;color:#666;"><?= $b['created_at'] ?></td>
            <td><?= $b['floor'] ?>F</td>
            <td><span class="tag tag-<?= $b['result'] ?>"><?= ['win'=>'🏆勝利','lose'=>'💀失敗','escape'=>'🏃逃跑'][$b['result']] ?></span></td>
            <td style="color:#ef5350;"><?= number_format($b['damage_dealt']) ?></td>
            <td style="color:#ef9a9a;"><?= number_format($b['damage_taken']) ?></td>
            <td style="color:#66bb6a;">+<?= number_format($b['exp_gained']) ?></td>
            <td style="color:#ffca28;">+<?= number_format($b['gold_gained']) ?></td>
          </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
        <?php else: ?>
        <div style="text-align:center;color:#444;padding:30px;">尚無戰鬥紀錄</div>
        <?php endif; ?>
      </div>
    </div>

    <?php else: ?>
    <!-- ── 玩家列表 ── -->
    <div class="section">
      <div class="section-header">
        <h3>玩家列表</h3>
        <span class="badge">共 <?= $total ?> 位玩家</span>
      </div>
      <div class="search-bar">
        <form method="GET" style="display:flex;gap:12px;flex:1;">
          <input type="text" name="q" placeholder="🔍 搜尋玩家名稱..." value="<?= htmlspecialchars($search) ?>">
          <select name="filter">
            <option value="all" <?= $filter==='all'?'selected':'' ?>>全部玩家</option>
            <option value="active" <?= $filter==='active'?'selected':'' ?>>正常玩家</option>
            <option value="banned" <?= $filter==='banned'?'selected':'' ?>>已封鎖</option>
          </select>
          <button type="submit" class="btn btn-primary">搜尋</button>
          <a href="players.php" class="btn btn-primary">重置</a>
        </form>
      </div>
      <table class="tbl">
        <thead><tr>
          <th>ID</th><th>名稱</th><th>等級</th><th>HP</th><th>傷害</th><th>防禦</th>
          <th>金幣</th><th>最高樓層</th><th>狀態</th><th>操作</th>
        </tr></thead>
        <tbody>
        <?php while($p=$players->fetch_assoc()): ?>
        <tr>
          <td style="color:#555;">#<?= $p['id'] ?></td>
          <td><b><?= htmlspecialchars($p['username']) ?></b></td>
          <td><span style="color:#4fc3f7;font-weight:700;">Lv.<?= $p['level'] ?></span></td>
          <td><?= $p['hp'] ?>/<?= $p['max_hp'] ?></td>
          <td style="color:#ef5350;"><?= $p['dmg'] ?></td>
          <td style="color:#66bb6a;"><?= $p['def'] ?></td>
          <td style="color:#ffca28;">💰<?= number_format($p['gold']) ?></td>
          <td><?= $p['max_floor'] ?>F</td>
          <td>
            <?php if ($p['is_banned']): ?>
            <span class="tag tag-banned">封鎖</span>
            <?php else: ?>
            <span class="tag tag-active">正常</span>
            <?php endif; ?>
          </td>
          <td style="display:flex;gap:6px;">
            <a href="players.php?id=<?=$p['id']?>" class="btn btn-primary">詳情</a>
            <form method="POST" onsubmit="return confirmAction(this);" data-action="reset" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="user_id" value="<?=$p['id']?>">
              <input type="hidden" name="action" value="reset">
              <button type="submit" class="btn btn-warning">重置</button>
            </form>
            <form method="POST" onsubmit="return confirmAction(this);" data-action="<?=$p['is_banned']?'unban':'ban'?>" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="user_id" value="<?=$p['id']?>">
              <input type="hidden" name="action" value="<?=$p['is_banned']?'unban':'ban'?>">
              <button type="submit" class="btn <?=$p['is_banned']?'btn-success':'btn-danger'?>">
                <?=$p['is_banned']?'解鎖':'封鎖'?>
              </button>
            </form>
          </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

  </div>
</div>

<script>
function confirmAction(form) {
  const action = form.dataset.action;
  const msgs = {
    reset: '⚠ 確定要重置此玩家的所有進度嗎？此操作無法復原！',
    ban:   '🚫 確定要封鎖此玩家帳號嗎？',
    unban: '🔓 確定要解除此玩家的封鎖嗎？'
  };
  return confirm(msgs[action] || '確定執行此操作？');
}
</script>
</body>
</html>
