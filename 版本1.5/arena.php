<?php
session_start();
date_default_timezone_set('Asia/Taipei');
require 'db.php';
require_once 'lib/session.php';
require_once 'lib/functions.php';

if (!isset($_SESSION['player_id'])) { header('Location: login.php'); exit; }
$user_id = (int)$_SESSION['player_id'];
ensure_pvp_ranking($conn, $user_id);

// 我的資料
$my = $conn->query("SELECT r.*,u.username,u.level,u.dmg,u.def,u.max_hp FROM pvp_rankings r JOIN users u ON r.user_id=u.id WHERE r.user_id=$user_id")->fetch_assoc();
$my_rank = (int)$conn->query("SELECT COUNT(*)+1 AS r FROM pvp_rankings WHERE rating>{$my['rating']}")->fetch_row()[0];
$cd = (int)$conn->query("SELECT GREATEST(0, 60 - TIMESTAMPDIFF(SECOND, last_challenge, NOW())) FROM pvp_rankings WHERE user_id=$user_id")->fetch_row()[0];
$eq = get_equipment_bonus($conn, $user_id);

// 排行榜 Top 20
$rankings = [];
$res = $conn->query("SELECT r.user_id,r.rating,r.wins,r.losses,r.streak,u.username,u.level,u.is_bot FROM pvp_rankings r JOIN users u ON r.user_id=u.id ORDER BY r.rating DESC LIMIT 20");
while ($r = $res->fetch_assoc()) $rankings[] = $r;

// 可挑戰對手（積分最近的前10人，含電腦玩家）
$opponents = [];
$res = $conn->query("SELECT r.user_id,r.rating,r.wins,r.losses,u.username,u.level,u.is_bot FROM pvp_rankings r JOIN users u ON r.user_id=u.id WHERE r.user_id!=$user_id ORDER BY ABS(r.rating-{$my['rating']}) ASC LIMIT 10");
while ($r = $res->fetch_assoc()) $opponents[] = $r;

// 最近對戰紀錄
$history = [];
$res = $conn->query("SELECT b.id,b.winner_id,b.challenger_rating_change,b.defender_rating_change,b.created_at,uc.username AS cn,ud.username AS dn,uw.username AS wn FROM pvp_battles b JOIN users uc ON b.challenger_id=uc.id JOIN users ud ON b.defender_id=ud.id JOIN users uw ON b.winner_id=uw.id WHERE b.challenger_id=$user_id OR b.defender_id=$user_id ORDER BY b.created_at DESC LIMIT 5");
while ($r = $res->fetch_assoc()) $history[] = $r;
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>競技場 — 塔城傳說</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Segoe UI','微軟正黑體',sans-serif;background:#0d0d1a;color:#e0e0e0;padding:20px;}
.topbar{display:flex;justify-content:space-between;align-items:center;max-width:1000px;margin:0 auto 20px;flex-wrap:wrap;gap:10px;}
.topbar h1{font-size:22px;color:#ef5350;letter-spacing:2px;}
.topbar a{color:#94a3b8;font-size:13px;text-decoration:none;padding:6px 14px;border:1px solid #2a2a4a;border-radius:6px;}
.topbar a:hover{border-color:#4fc3f7;color:#4fc3f7;}
.grid{display:grid;grid-template-columns:320px 1fr;gap:20px;max-width:1000px;margin:0 auto;}
.card{background:#16213e;border:1px solid #2a2a4a;border-radius:12px;overflow:hidden;margin-bottom:20px;}
.card-header{padding:14px 20px;border-bottom:1px solid #2a2a4a;display:flex;justify-content:space-between;align-items:center;}
.card-header h3{font-size:14px;font-weight:700;color:#e0e0e0;}
.card-body{padding:20px;}

/* 我的戰績 */
.rating-big{font-size:52px;font-weight:700;color:#ef5350;line-height:1;text-align:center;margin:8px 0;}
.rating-rank{text-align:center;font-size:13px;color:#94a3b8;margin-bottom:16px;}
.stats-row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:16px;}
.stat-box{background:#0d0d1a;border:1px solid #1a1a2e;border-radius:8px;padding:10px;text-align:center;}
.stat-box .sv{font-size:20px;font-weight:700;}
.stat-box .sl{font-size:10px;color:#555;margin-top:3px;}
.cd-bar{background:#0d0d1a;border:1px solid #2a2a4a;border-radius:7px;padding:10px 14px;text-align:center;font-size:13px;color:#ffca28;margin-bottom:12px;display:none;}

/* 排行榜 */
.rank-table{width:100%;border-collapse:collapse;font-size:13px;}
.rank-table th{padding:10px 14px;text-align:left;font-size:11px;color:#555;border-bottom:1px solid #1a1a2e;background:#0d0d1a;}
.rank-table td{padding:11px 14px;border-bottom:1px solid #1a1a2e;color:#ccc;}
.rank-table tr:last-child td{border-bottom:none;}
.rank-table tr:hover td{background:rgba(79,195,247,.03);}
.rank-num{font-weight:700;width:36px;}
.rank-1{color:#ffd700;} .rank-2{color:#c0c0c0;} .rank-3{color:#cd7f32;}

/* 對手列表 */
.opp-row{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid #1a1a2e;}
.opp-row:last-child{border-bottom:none;}
.opp-info{flex:1;}
.opp-name{font-size:14px;font-weight:600;color:#e0e0e0;}
.opp-meta{font-size:11px;color:#555;margin-top:3px;}
.opp-rating{font-size:16px;font-weight:700;color:#ef5350;margin:0 16px;}
.btn-challenge{padding:7px 16px;background:rgba(239,83,80,.1);border:1px solid rgba(239,83,80,.4);color:#ef5350;border-radius:6px;cursor:pointer;font-size:12px;font-weight:700;transition:all .2s;}
.btn-challenge:hover{background:rgba(239,83,80,.2);}
.btn-challenge:disabled{opacity:.4;cursor:not-allowed;}

/* 戰鬥 Modal */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:999;align-items:center;justify-content:center;}
.modal-bg.show{display:flex;}
.modal{background:#16213e;border:1px solid #2a2a4a;border-radius:14px;width:520px;max-width:95vw;max-height:85vh;overflow-y:auto;}
.modal-head{padding:20px 24px;border-bottom:1px solid #2a2a4a;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;background:#16213e;z-index:1;}
.modal-head h3{font-size:16px;color:#e0e0e0;}
.modal-close{background:none;border:none;color:#555;font-size:20px;cursor:pointer;padding:4px 8px;}
.modal-close:hover{color:#e0e0e0;}
.modal-body{padding:20px 24px;}
.log-line{padding:7px 12px;border-radius:6px;font-size:13px;margin-bottom:6px;line-height:1.5;}
.log-system{background:#1a1a2e;color:#94a3b8;}
.log-attack{background:#1a0d0d;color:#ef9a9a;}
.log-crit{background:#2a1000;color:#ffca28;font-weight:700;}
.log-dodge{background:#0d1a0d;color:#66bb6a;}
.log-result{background:rgba(239,83,80,.12);border:1px solid rgba(239,83,80,.3);color:#ef5350;font-weight:700;font-size:14px;text-align:center;padding:14px;}
.result-banner{text-align:center;padding:20px 0;margin-bottom:16px;}
.result-banner .big{font-size:28px;font-weight:700;}
.result-banner .sub{font-size:13px;color:#94a3b8;margin-top:6px;}

/* 週獎勵提示 */
.weekly-hint{background:#1a1000;border:1px solid #3a2800;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:12px;color:#ffca28;}
</style>
</head>
<body>

<div class="topbar">
  <h1>🏟️ 競技場</h1>
  <div style="display:flex;gap:10px;align-items:center;">
    <span style="font-size:13px;color:#94a3b8;">每週一 0:00 結算金幣獎勵</span>
    <a href="index.php">← 返回城鎮</a>
  </div>
</div>

<div class="grid">

  <!-- 左欄：我的戰績 -->
  <div>
    <div class="card">
      <div class="card-header"><h3>⚔️ 我的戰績</h3><span style="font-size:12px;color:#555;">第 <?= $my_rank ?> 名</span></div>
      <div class="card-body">
        <div style="font-size:11px;color:#555;text-align:center;margin-bottom:4px;letter-spacing:2px;">積分</div>
        <div class="rating-big"><?= number_format($my['rating']) ?></div>
        <div class="rating-rank">#<?= $my_rank ?> · Lv.<?= $my['level'] ?> · <?= htmlspecialchars($my['username']) ?></div>

        <div id="cd-bar" class="cd-bar" style="<?= $cd>0?'display:block':'' ?>">
          ⏳ 挑戰冷卻：<span id="cd-num"><?= $cd ?></span> 秒
        </div>

        <div class="stats-row">
          <div class="stat-box"><div class="sv" style="color:#66bb6a;"><?= $my['wins'] ?></div><div class="sl">勝場</div></div>
          <div class="stat-box"><div class="sv" style="color:#ef5350;"><?= $my['losses'] ?></div><div class="sl">敗場</div></div>
          <div class="stat-box"><div class="sv" style="color:#ffca28;"><?= $my['streak'] ?></div><div class="sl">連勝</div></div>
        </div>

        <div style="font-size:11px;color:#555;margin-bottom:8px;">我的戰鬥數值（含裝備）</div>
        <div class="stats-row">
          <div class="stat-box"><div class="sv" style="color:#ef5350;"><?= $my['dmg']+$eq['atk'] ?></div><div class="sl">ATK</div></div>
          <div class="stat-box"><div class="sv" style="color:#4fc3f7;"><?= $my['def']+$eq['def'] ?></div><div class="sl">DEF</div></div>
          <div class="stat-box"><div class="sv" style="color:#66bb6a;"><?= $my['max_hp']+$eq['hp'] ?></div><div class="sl">HP</div></div>
        </div>

        <!-- 近期紀錄 -->
        <?php if ($history): ?>
        <div style="font-size:11px;color:#555;margin:12px 0 8px;letter-spacing:1px;">最近對戰</div>
        <?php foreach($history as $h):
          $won = ($h['winner_id'] == $user_id);
          $opp = ($h['cn'] === $my['username']) ? $h['dn'] : $h['cn'];
          $change = ($h['challenger_id'] ?? 0) == $user_id ? $h['challenger_rating_change'] : $h['defender_rating_change'];
        ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid #1a1a2e;font-size:12px;">
          <span><?= $won?'✅':'❌' ?> vs <?= htmlspecialchars($opp) ?></span>
          <span style="cursor:pointer;color:#4fc3f7;" onclick="showBattle(<?= $h['id'] ?>)">查看</span>
          <span style="color:<?= $change>=0?'#66bb6a':'#ef5350' ?>;"><?= $change>=0?"+$change":$change ?></span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- 週獎勵說明 -->
    <div class="weekly-hint">
      🏆 <b>每週金幣獎勵</b><br>
      第1名 10,000金 · 第2名 5,000金 · 第3名 2,000金<br>
      4-10名 1,000金 · 11-20名 500金 · 每10名再減半
    </div>
  </div>

  <!-- 右欄 -->
  <div>
    <!-- 挑戰名單 -->
    <div class="card" style="margin-bottom:20px;">
      <div class="card-header"><h3>🗡️ 挑戰對手</h3><span style="font-size:12px;color:#555;">按積分排序（最近的前10人）</span></div>
      <div class="card-body" style="padding:10px 20px;">
        <?php if (empty($opponents)): ?>
        <div style="text-align:center;color:#444;padding:20px;">目前沒有其他玩家</div>
        <?php else: foreach($opponents as $opp):
          $diff = $opp['rating'] - $my['rating'];
          $diff_str = ($diff>=0?'+':'').$diff;
          $diff_color = $diff>0?'#ef5350':($diff<0?'#66bb6a':'#94a3b8');
          $is_bot = (bool)$opp['is_bot'];
        ?>
        <div class="opp-row">
          <div class="opp-info">
            <div class="opp-name">
              <?php if($is_bot): ?><span style="font-size:10px;background:#1a1a2e;color:#4fc3f7;border:1px solid #2a2a4a;padding:2px 6px;border-radius:4px;margin-right:5px;">🤖 電腦</span><?php endif; ?>
              <?= htmlspecialchars($opp['username']) ?>
              <span style="font-size:11px;color:#555;margin-left:6px;">Lv.<?= $opp['level'] ?></span>
            </div>
            <div class="opp-meta">Lv.<?= $opp['level'] ?> 玩家</div>
          </div>
          <div class="opp-rating"><?= $opp['rating'] ?>
            <span style="font-size:11px;color:<?= $diff_color ?>;">(<?= $diff_str ?>)</span>
          </div>
          <button class="btn-challenge" id="btn-<?= $opp['user_id'] ?>"
            onclick="doChallenge(<?= $opp['user_id'] ?>, '<?= htmlspecialchars($opp['username']) ?>')"
            <?= $cd>0?'disabled':'' ?>>
            ⚔️ 挑戰
          </button>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- 排行榜 -->
    <div class="card">
      <div class="card-header"><h3>🏆 積分排行榜</h3><span style="font-size:12px;color:#555;">Top 20</span></div>
      <table class="rank-table">
        <thead><tr><th>#</th><th>玩家</th><th>等級</th><th>積分</th><th>勝</th><th>敗</th><th>連勝</th></tr></thead>
        <tbody>
        <?php foreach($rankings as $i=>$r):
          $rank   = $i+1;
          $is_me  = ($r['user_id'] == $user_id);
          $is_bot = (bool)$r['is_bot'];
          $medal  = $rank===1?'🥇':($rank===2?'🥈':($rank===3?'🥉':$rank));
          $cls    = $rank<=3?"rank-{$rank}":'';
        ?>
        <tr style="<?= $is_me?'background:rgba(79,195,247,.05);':($is_bot?'opacity:.85':'') ?>">
          <td class="rank-num <?= $cls ?>"><?= $medal ?></td>
          <td style="font-weight:600;color:<?= $is_me?'#4fc3f7':'#e0e0e0' ?>;">
            <?php if($is_bot): ?><span style="font-size:10px;color:#4fc3f7;margin-right:4px;">🤖</span><?php endif; ?>
            <?= htmlspecialchars($r['username']) ?><?= $is_me?' ◀':'' ?>
          </td>
          <td style="color:#555;">Lv.<?= $r['level'] ?></td>
          <td style="font-weight:700;color:#ef5350;"><?= $r['rating'] ?></td>
          <?php if ($is_me || $is_bot): ?>
          <td style="color:#66bb6a;"><?= $r['wins'] ?></td>
          <td style="color:#ef5350;"><?= $r['losses'] ?></td>
          <td style="color:#ffca28;"><?= $r['streak']>0?$r['streak'].'🔥':'-' ?></td>
          <?php else: ?>
          <td style="color:#2a2a4a;">—</td>
          <td style="color:#2a2a4a;">—</td>
          <td style="color:#2a2a4a;">—</td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- 戰鬥結果 Modal -->
<div class="modal-bg" id="battle-modal">
  <div class="modal">
    <div class="modal-head">
      <h3 id="modal-title">戰鬥結果</h3>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-body" id="modal-body">載入中...</div>
  </div>
</div>

<script>
let cdTimer = null;
let cd = <?= $cd ?>;

function startCd(sec) {
    cd = sec;
    const bar = document.getElementById('cd-bar');
    const num = document.getElementById('cd-num');
    if (sec <= 0) { bar.style.display='none'; enableButtons(); return; }
    bar.style.display = 'block';
    num.textContent = cd;
    document.querySelectorAll('.btn-challenge').forEach(b=>b.disabled=true);
    cdTimer = setInterval(() => {
        cd--;
        num.textContent = cd;
        if (cd <= 0) {
            clearInterval(cdTimer);
            bar.style.display = 'none';
            enableButtons();
        }
    }, 1000);
}

function enableButtons() {
    document.querySelectorAll('.btn-challenge').forEach(b=>b.disabled=false);
}

async function doChallenge(defenderId, defenderName) {
    if (!confirm(`確定要挑戰 ${defenderName} 嗎？`)) return;
    document.querySelectorAll('.btn-challenge').forEach(b=>b.disabled=true);

    const resp = await fetch('api/pvp.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=challenge&defender_id=${defenderId}`
    });
    const data = await resp.json();

    if (!data.success) {
        alert(data.message);
        if (data.message.includes('冷卻')) startCd(60);
        else enableButtons();
        return;
    }

    startCd(60);
    showBattleResult(data);
}

function showBattleResult(data) {
    const modal = document.getElementById('battle-modal');
    const body  = document.getElementById('modal-body');
    const title = document.getElementById('modal-title');

    const won   = (data.winner_id === <?= $user_id ?>);
    const gain  = data.rating_gain;
    const color = gain >= 0 ? '#66bb6a' : '#ef5350';
    title.textContent = won ? '🏆 勝利！' : '💀 敗北...';

    // 更新左側積分顯示
    if (data.new_rating !== undefined) {
        const ratingEl = document.querySelector('.rating-big');
        if (ratingEl) ratingEl.textContent = data.new_rating.toLocaleString();
    }

    let html = `<div class="result-banner">
        <div class="big" style="color:${won?'#66bb6a':'#ef5350'}">${won?'🏆 你贏了！':'💀 你輸了...'}</div>
        <div style="margin-top:14px;display:flex;justify-content:center;gap:24px;">
          <div style="text-align:center;">
            <div style="font-size:28px;font-weight:700;color:${color};">${gain>=0?'+':''}${gain}</div>
            <div style="font-size:11px;color:#555;margin-top:3px;">積分變動</div>
          </div>
          <div style="text-align:center;">
            <div style="font-size:28px;font-weight:700;color:#4fc3f7;">${data.new_rating}</div>
            <div style="font-size:11px;color:#555;margin-top:3px;">目前積分</div>
          </div>
          <div style="text-align:center;">
            <div style="font-size:28px;font-weight:700;color:#94a3b8;">${data.rounds}</div>
            <div style="font-size:11px;color:#555;margin-top:3px;">回合數</div>
          </div>
        </div>
    </div>`;

    data.battle_log.forEach(l => {
        const cls = {system:'log-system',attack:'log-attack',crit:'log-crit',dodge:'log-dodge',result:'log-result'}[l.type]||'log-system';
        html += `<div class="log-line ${cls}">${l.text}</div>`;
    });

    body.innerHTML = html;
    modal.classList.add('show');
}

async function showBattle(battleId) {
    const modal = document.getElementById('battle-modal');
    const body  = document.getElementById('modal-body');
    document.getElementById('modal-title').textContent = '戰鬥回放';
    body.innerHTML = '載入中...';
    modal.classList.add('show');

    const resp = await fetch(`api/pvp.php?action=get_battle&battle_id=${battleId}`);
    const data = await resp.json();
    if (!data.success) { body.innerHTML = '載入失敗'; return; }

    let html = '';
    data.log.forEach(l => {
        const cls = {system:'log-system',attack:'log-attack',crit:'log-crit',dodge:'log-dodge',result:'log-result'}[l.type]||'log-system';
        html += `<div class="log-line ${cls}">${l.text}</div>`;
    });
    body.innerHTML = html;
}

function closeModal() {
    document.getElementById('battle-modal').classList.remove('show');
}
document.getElementById('battle-modal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

if (cd > 0) startCd(cd);
</script>
</body>
</html>
