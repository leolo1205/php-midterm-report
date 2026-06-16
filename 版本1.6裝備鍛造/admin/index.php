<?php
require_once 'auth.php';
require_once '../db.php';

// ── 統計資料 ──
$total_players   = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
$active_today    = $conn->query("SELECT COUNT(*) FROM users WHERE DATE(last_train_time)=CURDATE()")->fetch_row()[0];
$total_gold      = $conn->query("SELECT SUM(gold) FROM users")->fetch_row()[0] ?? 0;
$max_floor       = $conn->query("SELECT MAX(max_floor) FROM users")->fetch_row()[0] ?? 0;
$banned_count    = $conn->query("SELECT COUNT(*) FROM users WHERE is_banned=1")->fetch_row()[0];
$battles_today   = $conn->query("SELECT COUNT(*) FROM battle_logs WHERE DATE(created_at)=CURDATE()")->fetch_row()[0];
$trainings_today = $conn->query("SELECT COUNT(*) FROM training_logs WHERE DATE(created_at)=CURDATE()")->fetch_row()[0];
$battles_total   = $conn->query("SELECT COUNT(*) FROM battle_logs")->fetch_row()[0];

// ── 排行榜 ──
$rank_level = $conn->query("SELECT id,username,level,exp,max_floor FROM users WHERE is_banned=0 ORDER BY level DESC,exp DESC LIMIT 10");
$rank_gold  = $conn->query("SELECT id,username,level,gold FROM users WHERE is_banned=0 ORDER BY gold DESC LIMIT 10");
$rank_floor = $conn->query("SELECT id,username,level,max_floor FROM users WHERE is_banned=0 ORDER BY max_floor DESC,level DESC LIMIT 10");
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>系統總覽 — 後台管理</title>
<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/admin.css">
</head>
<body>
<?php include '_sidebar.php'; ?>

  <!-- TOPBAR -->
  <div class="admin-topbar">
    <div class="page-title">📊 系統總覽</div>
    <div class="breadcrumb">後台管理 / <span>系統總覽</span></div>
    <div style="font-size:12px;color:#8899b0;"><?= date('Y-m-d H:i:s') ?></div>
  </div>

  <div class="content">

    <!-- 今日統計 Cards -->
    <div class="stat-grid" style="grid-template-columns:repeat(4,1fr);">
      <div class="stat-card blue">
        <div class="label">玩家總數</div>
        <div class="value"><?= number_format($total_players) ?></div>
        <div class="sub">封鎖：<?= $banned_count ?> 人</div>
      </div>
      <div class="stat-card green">
        <div class="label">今日活躍</div>
        <div class="value"><?= number_format($active_today) ?></div>
        <div class="sub">已訓練玩家</div>
      </div>
      <div class="stat-card yellow">
        <div class="label">金幣流通量</div>
        <div class="value"><?= number_format($total_gold) ?></div>
        <div class="sub">全伺服器合計</div>
      </div>
      <div class="stat-card red">
        <div class="label">最高樓層</div>
        <div class="value"><?= $max_floor ?>F</div>
        <div class="sub">塔探索記錄</div>
      </div>
    </div>

    <!-- 今日統計 第二行 -->
    <div class="stat-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:28px;">
      <div class="stat-card">
        <div class="label">今日戰鬥次數</div>
        <div class="value" style="color:#e040fb;"><?= number_format($battles_today) ?></div>
        <div class="sub">累計：<?= number_format($battles_total) ?> 次</div>
      </div>
      <div class="stat-card">
        <div class="label">今日訓練次數</div>
        <div class="value" style="color:#4db6ac;"><?= number_format($trainings_today) ?></div>
        <div class="sub">today</div>
      </div>
      <div class="stat-card">
        <div class="label">今日時間</div>
        <div class="value" style="font-size:20px;color:#888;"><?= date('H:i') ?></div>
        <div class="sub"><?= date('Y年m月d日') ?></div>
      </div>
    </div>

    <!-- 排行榜 Tabs -->
    <div class="section">
      <div class="section-header">
        <h3>🏆 玩家排行榜</h3>
        <span class="badge">TOP 10</span>
      </div>
      <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('level',this)">⭐ 等級排行</button>
        <button class="tab-btn" onclick="switchTab('gold',this)">💰 金幣排行</button>
        <button class="tab-btn" onclick="switchTab('floor',this)">🗼 樓層排行</button>
      </div>

      <!-- 等級排行 -->
      <div id="tab-level" class="tab-content active">
        <table class="tbl">
          <thead><tr>
            <th>排名</th><th>玩家名稱</th><th>等級</th><th>EXP</th><th>最高樓層</th><th>操作</th>
          </tr></thead>
          <tbody>
          <?php $i=1; while($r=$rank_level->fetch_assoc()): $cls=($i==1?'gold':($i==2?'silver':($i==3?'bronze':''))); ?>
          <tr>
            <td><span class="rank <?=$cls?>"><?=($i<=3?['🥇','🥈','🥉'][$i-1]:$i)?></span></td>
            <td><?= htmlspecialchars($r['username']) ?></td>
            <td><b style="color:#4fc3f7;">Lv.<?= $r['level'] ?></b></td>
            <td><?= number_format($r['exp']) ?> / <?= number_format($r['level']*100) ?></td>
            <td><?= $r['max_floor'] ?>F</td>
            <td><a href="players.php?id=<?=$r['id']?>" class="btn btn-primary">詳情</a></td>
          </tr>
          <?php $i++; endwhile; ?>
          </tbody>
        </table>
      </div>

      <!-- 金幣排行 -->
      <div id="tab-gold" class="tab-content">
        <table class="tbl">
          <thead><tr>
            <th>排名</th><th>玩家名稱</th><th>等級</th><th>金幣</th><th>操作</th>
          </tr></thead>
          <tbody>
          <?php $i=1; while($r=$rank_gold->fetch_assoc()): $cls=($i==1?'gold':($i==2?'silver':($i==3?'bronze':''))); ?>
          <tr>
            <td><span class="rank <?=$cls?>"><?=($i<=3?['🥇','🥈','🥉'][$i-1]:$i)?></span></td>
            <td><?= htmlspecialchars($r['username']) ?></td>
            <td>Lv.<?= $r['level'] ?></td>
            <td><b style="color:#ffca28;">💰 <?= number_format($r['gold']) ?></b></td>
            <td><a href="players.php?id=<?=$r['id']?>" class="btn btn-primary">詳情</a></td>
          </tr>
          <?php $i++; endwhile; ?>
          </tbody>
        </table>
      </div>

      <!-- 樓層排行 -->
      <div id="tab-floor" class="tab-content">
        <table class="tbl">
          <thead><tr>
            <th>排名</th><th>玩家名稱</th><th>等級</th><th>最高樓層</th><th>操作</th>
          </tr></thead>
          <tbody>
          <?php $i=1; while($r=$rank_floor->fetch_assoc()): $cls=($i==1?'gold':($i==2?'silver':($i==3?'bronze':''))); ?>
          <tr>
            <td><span class="rank <?=$cls?>"><?=($i<=3?['🥇','🥈','🥉'][$i-1]:$i)?></span></td>
            <td><?= htmlspecialchars($r['username']) ?></td>
            <td>Lv.<?= $r['level'] ?></td>
            <td><b style="color:#e040fb;">🗼 <?= $r['max_floor'] ?> 層</b></td>
            <td><a href="players.php?id=<?=$r['id']?>" class="btn btn-primary">詳情</a></td>
          </tr>
          <?php $i++; endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /content -->
</div><!-- /main -->

<script>
function switchTab(tab, el) {
  document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-'+tab).classList.add('active');
  el.classList.add('active');
}
// Auto refresh every 30s
setTimeout(()=>location.reload(), 30000);
</script>
</body>
</html>
