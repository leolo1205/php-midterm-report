<?php
require_once 'auth.php';
require_once '../db.php';
require_once '../lib/functions.php';

$msg = '';
if (isset($_POST['weekly_settle'])) {
    if (!csrf_verify()) {
        die('安全驗證失敗，請重新整理頁面後再試。');
    }
    $r = pvp_weekly_settle($conn);
    $msg = "✅ 週結算完成！共 {$r['settled']} 位玩家，{$r['rewarded']} 位獲得獎勵。";
}

$r1 = $conn->query("SELECT COUNT(*) FROM pvp_battles");
$total_battles = ($r1 !== false) ? ($r1->fetch_row()[0] ?? 0) : 0;
$r2 = $conn->query("SELECT COUNT(*) FROM pvp_rankings");
$total_players = ($r2 !== false) ? ($r2->fetch_row()[0] ?? 0) : 0;
$r3 = $conn->query("SELECT COUNT(*) FROM pvp_battles WHERE DATE(created_at)=CURDATE()");
$today_battles = ($r3 !== false) ? ($r3->fetch_row()[0] ?? 0) : 0;

$rankings = [];
$res = $conn->query("SELECT r.*,u.username,u.level FROM pvp_rankings r JOIN users u ON r.user_id=u.id ORDER BY r.rating DESC LIMIT 20");
if ($res !== false) while ($r = $res->fetch_assoc()) $rankings[] = $r;

$recent = [];
$res = $conn->query("SELECT b.id,b.created_at,b.challenger_rating_change,b.defender_rating_change,uc.username AS cn,ud.username AS dn,uw.username AS wn FROM pvp_battles b JOIN users uc ON b.challenger_id=uc.id JOIN users ud ON b.defender_id=ud.id JOIN users uw ON b.winner_id=uw.id ORDER BY b.created_at DESC LIMIT 20");
if ($res !== false) while ($r = $res->fetch_assoc()) $recent[] = $r;
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>競技場管理 — 後台</title>
<?php include '_sidebar.php'; ?>

  <div class="topbar">
    <div class="page-title">🏟️ 競技場管理</div>
    <div class="breadcrumb">後台管理 / <span>競技場</span></div>
  </div>

  <div class="content">

    <?php if ($msg): ?>
    <div class="alert alert-success" style="margin-bottom:20px;"><?= $msg ?></div>
    <?php endif; ?>

    <!-- 統計 -->
    <div class="stat-grid" style="margin-bottom:24px;">
      <div class="stat-card blue"><div class="label">參與玩家</div><div class="value"><?= $total_players ?></div></div>
      <div class="stat-card red"><div class="label">總對戰場次</div><div class="value"><?= $total_battles ?></div></div>
      <div class="stat-card green"><div class="label">今日場次</div><div class="value"><?= $today_battles ?></div></div>
      <div class="stat-card yellow">
        <div class="label">週結算</div>
        <div class="value" style="font-size:18px;">每週一 0:00</div>
        <div class="sub">
          <form method="POST" style="margin-top:8px;" onsubmit="return confirm('確定執行週結算？此操作將發放金幣並重置所有積分。');">
            <?= csrf_field() ?>
            <button type="submit" name="weekly_settle" class="btn btn-danger" style="width:100%;">立即執行結算</button>
          </form>
        </div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">

      <!-- 積分排行榜 -->
      <div class="section">
        <div class="section-header"><h3>🏆 積分排行榜</h3><span class="badge">Top 20</span></div>
        <table class="tbl">
          <thead><tr><th>#</th><th>玩家</th><th>等級</th><th>積分</th><th>勝</th><th>敗</th><th>連勝</th><th>週獎勵</th></tr></thead>
          <tbody>
          <?php foreach($rankings as $i=>$r):
            $rank = $i+1;
            $medal = $rank===1?'🥇':($rank===2?'🥈':($rank===3?'🥉':$rank));
            if ($rank===1)       $reward=10000;
            elseif($rank===2)    $reward=5000;
            elseif($rank===3)    $reward=2000;
            elseif($rank<=10)    $reward=1000;
            else { $tier=floor(($rank-11)/10); $reward=max(0,(int)(500/pow(2,$tier))); }
          ?>
          <tr>
            <td><b><?= $medal ?></b></td>
            <td><?= htmlspecialchars($r['username']) ?></td>
            <td style="color:#555;">Lv.<?= $r['level'] ?></td>
            <td style="color:#ef5350;font-weight:700;"><?= $r['rating'] ?></td>
            <td style="color:#66bb6a;"><?= $r['wins'] ?></td>
            <td style="color:#ef5350;"><?= $r['losses'] ?></td>
            <td style="color:#ffca28;"><?= $r['streak']>0?$r['streak'].'🔥':'-' ?></td>
            <td style="color:#ffca28;"><?= $reward>0?number_format($reward).' 金':'-' ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- 最近對戰 -->
      <div class="section">
        <div class="section-header"><h3>⚔️ 最近對戰記錄</h3><span class="badge">最近 20 場</span></div>
        <table class="tbl">
          <thead><tr><th>挑戰方</th><th>被挑戰</th><th>勝者</th><th>積分</th><th>時間</th></tr></thead>
          <tbody>
          <?php foreach($recent as $b): ?>
          <tr>
            <td><?= htmlspecialchars($b['cn']) ?></td>
            <td><?= htmlspecialchars($b['dn']) ?></td>
            <td><b style="color:#66bb6a;"><?= htmlspecialchars($b['wn']) ?></b></td>
            <td style="font-size:12px;">
              <span style="color:<?= $b['challenger_rating_change']>=0?'#66bb6a':'#ef5350' ?>;"><?= $b['challenger_rating_change']>=0?'+':'' ?><?= $b['challenger_rating_change'] ?></span>
              /
              <span style="color:<?= $b['defender_rating_change']>=0?'#66bb6a':'#ef5350' ?>;"><?= $b['defender_rating_change']>=0?'+':'' ?><?= $b['defender_rating_change'] ?></span>
            </td>
            <td style="color:#555;font-size:11px;"><?= substr($b['created_at'],5,14) ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    </div>
  </div>
</div>
</body>
</html>
