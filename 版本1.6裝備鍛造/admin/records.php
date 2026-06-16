<?php
require_once 'auth.php';
require_once '../db.php';

$tab      = $_GET['tab'] ?? 'battle';
$uid_flt  = (int)($_GET['uid'] ?? 0);
$date_flt = $_GET['date'] ?? '';
$result_flt = $_GET['result'] ?? '';
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;

// 白名單：只允許合法的 result 值
$allowed_results = ['win', 'lose', 'escape'];
if (!in_array($result_flt, $allowed_results, true)) $result_flt = '';

// 日期格式強制標準化，防止 SQL 注入
if ($date_flt !== '') {
    $ts = strtotime($date_flt);
    $date_flt = $ts ? date('Y-m-d', $ts) : '';
}

// ── 玩家清單（供篩選下拉） ──
$players_list = $conn->query("SELECT id, username FROM users ORDER BY username");

// ── 建立 WHERE 條件 ──
function build_where($uid, $date, $extra=[]) {
    $w = [];
    if ($uid > 0) $w[] = "user_id=$uid";
    if ($date)   $w[] = "DATE(created_at)='$date'";
    foreach ($extra as $e) $w[] = $e;
    return $w ? 'WHERE '.implode(' AND ', $w) : '';
}

if ($tab === 'battle') {
    $extra = $result_flt ? ["result='$result_flt'"] : [];
    $where = build_where($uid_flt, $date_flt, $extra);
    $total = $conn->query("SELECT COUNT(*) FROM battle_logs $where")->fetch_row()[0];
    $rows  = $conn->query("SELECT bl.*, u.username FROM battle_logs bl LEFT JOIN users u ON bl.user_id=u.id $where ORDER BY bl.created_at DESC LIMIT $per_page OFFSET $offset");
    $total_pages = max(1, ceil($total / $per_page));

    // 勝負統計（各自帶入 result 條件重新建構 WHERE）
    $win_count    = $conn->query("SELECT COUNT(*) FROM battle_logs ".build_where($uid_flt, $date_flt, ["result='win'"]))->fetch_row()[0] ?? 0;
    $lose_count   = $conn->query("SELECT COUNT(*) FROM battle_logs ".build_where($uid_flt, $date_flt, ["result='lose'"]))->fetch_row()[0] ?? 0;
    $escape_count = $conn->query("SELECT COUNT(*) FROM battle_logs ".build_where($uid_flt, $date_flt, ["result='escape'"]))->fetch_row()[0] ?? 0;
} else {
    $where = build_where($uid_flt, $date_flt);
    $total = $conn->query("SELECT COUNT(*) FROM training_logs $where")->fetch_row()[0];
    $rows  = $conn->query("SELECT tl.*, u.username FROM training_logs tl LEFT JOIN users u ON tl.user_id=u.id $where ORDER BY tl.created_at DESC LIMIT $per_page OFFSET $offset");
    $total_pages = max(1, ceil($total / $per_page));
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>紀錄查詢 — 後台管理</title>
<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/admin.css">
<style>
.pagination{display:flex;gap:6px;align-items:center;padding:16px 22px;}
.page-btn{
  padding:6px 13px;border-radius:6px;border:1px solid #2a2a4a;
  background:#0d0d1a;color:#888;font-size:13px;cursor:pointer;text-decoration:none;
  transition:all .2s;
}
.page-btn:hover,.page-btn.active{border-color:#4fc3f7;color:#4fc3f7;background:rgba(79,195,247,.1);}
.page-info{font-size:12px;color:#8899b0;margin-left:auto;}
</style>
</head>
<body>
<?php include '_sidebar.php'; ?>

  <div class="admin-topbar">
    <div class="page-title">📋 紀錄查詢</div>
    <div class="breadcrumb">後台管理 / <span>紀錄查詢</span></div>
  </div>

  <div class="content">

    <?php if ($tab === 'battle'): ?>
    <!-- 戰鬥統計卡 -->
    <div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px;">
      <div class="stat-card"><div class="label">總戰鬥次數</div><div class="value" style="color:#e040fb;"><?= number_format($total) ?></div></div>
      <div class="stat-card green"><div class="label">勝利</div><div class="value"><?= number_format($win_count) ?></div></div>
      <div class="stat-card red"><div class="label">失敗</div><div class="value"><?= number_format($lose_count) ?></div></div>
      <div class="stat-card yellow"><div class="label">逃跑</div><div class="value"><?= number_format($escape_count) ?></div></div>
    </div>
    <?php endif; ?>

    <div class="section">
      <!-- Tabs -->
      <div class="tabs">
        <a href="?tab=battle" class="tab-btn <?= $tab==='battle'?'active':'' ?>">⚔️ 戰鬥紀錄</a>
        <a href="?tab=training" class="tab-btn <?= $tab==='training'?'active':'' ?>">💪 訓練紀錄</a>
      </div>

      <!-- 篩選列 -->
      <div class="search-bar">
        <form method="GET" style="display:flex;gap:12px;flex:1;flex-wrap:wrap;">
          <input type="hidden" name="tab" value="<?= $tab ?>">
          <select name="uid" style="min-width:160px;">
            <option value="0">全部玩家</option>
            <?php while($pl=$players_list->fetch_assoc()): ?>
            <option value="<?=$pl['id']?>" <?=$uid_flt==$pl['id']?'selected':''?>><?= htmlspecialchars($pl['username']) ?></option>
            <?php endwhile; ?>
          </select>
          <input type="date" name="date" value="<?= htmlspecialchars($date_flt) ?>" style="color:#e0e0e0;">
          <?php if ($tab === 'battle'): ?>
          <select name="result">
            <option value="">全部結果</option>
            <option value="win" <?=$result_flt==='win'?'selected':''?>>🏆 勝利</option>
            <option value="lose" <?=$result_flt==='lose'?'selected':''?>>💀 失敗</option>
            <option value="escape" <?=$result_flt==='escape'?'selected':''?>>🏃 逃跑</option>
          </select>
          <?php endif; ?>
          <button type="submit" class="btn btn-primary">套用篩選</button>
          <a href="?tab=<?=$tab?>" class="btn btn-primary">清除</a>
          <span style="margin-left:auto;color:#555;font-size:13px;align-self:center;">共 <?= number_format($total) ?> 筆</span>
        </form>
      </div>

      <?php if ($tab === 'battle'): ?>
      <!-- 戰鬥紀錄 -->
      <table class="tbl">
        <thead><tr>
          <th>#</th><th>時間</th><th>玩家</th><th>樓層</th><th>結果</th>
          <th>造成傷害</th><th>承受傷害</th><th>獲得EXP</th><th>獲得金幣</th>
        </tr></thead>
        <tbody>
        <?php if ($rows && $rows->num_rows > 0):
              while($r=$rows->fetch_assoc()): ?>
        <tr>
          <td style="color:#444;font-size:12px;"><?= $r['id'] ?></td>
          <td style="font-size:12px;color:#666;"><?= $r['created_at'] ?></td>
          <td>
            <a href="players.php?id=<?=$r['user_id']?>" style="color:#4fc3f7;text-decoration:none;">
              <?= htmlspecialchars($r['username'] ?? '—') ?>
            </a>
          </td>
          <td><b><?= $r['floor'] ?>F</b></td>
          <td><span class="tag tag-<?=$r['result']?>"><?=['win'=>'🏆 勝利','lose'=>'💀 失敗','escape'=>'🏃 逃跑'][$r['result']]?></span></td>
          <td style="color:#ef5350;"><?= number_format($r['damage_dealt']) ?></td>
          <td style="color:#ef9a9a;"><?= number_format($r['damage_taken']) ?></td>
          <td style="color:#66bb6a;">+<?= number_format($r['exp_gained']) ?></td>
          <td style="color:#ffca28;">+<?= number_format($r['gold_gained']) ?></td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="9" style="text-align:center;color:#444;padding:40px;">尚無戰鬥紀錄</td></tr>
        <?php endif; ?>
        </tbody>
      </table>

      <?php else: ?>
      <!-- 訓練紀錄 -->
      <table class="tbl">
        <thead><tr>
          <th>#</th><th>時間</th><th>玩家</th><th>獲得EXP</th><th>獲得屬性點</th>
        </tr></thead>
        <tbody>
        <?php if ($rows && $rows->num_rows > 0):
              while($r=$rows->fetch_assoc()): ?>
        <tr>
          <td style="color:#444;font-size:12px;"><?= $r['id'] ?></td>
          <td style="font-size:12px;color:#666;"><?= $r['created_at'] ?></td>
          <td>
            <a href="players.php?id=<?=$r['user_id']?>" style="color:#4fc3f7;text-decoration:none;">
              <?= htmlspecialchars($r['username'] ?? '—') ?>
            </a>
          </td>
          <td style="color:#66bb6a;">+<?= number_format($r['exp_gained']) ?> EXP</td>
          <td style="color:#4fc3f7;">+<?= $r['stat_points_gained'] ?> 點</td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="5" style="text-align:center;color:#444;padding:40px;">尚無訓練紀錄</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
      <?php endif; ?>

      <!-- 分頁 -->
      <?php if ($total_pages > 1): ?>
      <div class="pagination">
        <?php
        $base = "?tab=$tab&uid=$uid_flt&date=".urlencode($date_flt)."&result=".urlencode($result_flt);
        if ($page > 1) echo "<a href='{$base}&page=".($page-1)."' class='page-btn'>← 上一頁</a>";
        for ($i = max(1,$page-2); $i <= min($total_pages,$page+2); $i++) {
            $act = $i == $page ? 'active' : '';
            echo "<a href='{$base}&page=$i' class='page-btn $act'>$i</a>";
        }
        if ($page < $total_pages) echo "<a href='{$base}&page=".($page+1)."' class='page-btn'>下一頁 →</a>";
        ?>
        <span class="page-info">第 <?=$page?> / <?=$total_pages?> 頁，共 <?=number_format($total)?> 筆</span>
      </div>
      <?php endif; ?>

    </div><!-- /section -->
  </div><!-- /content -->
</div><!-- /main -->
</body>
</html>
