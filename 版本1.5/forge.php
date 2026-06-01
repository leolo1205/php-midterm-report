<?php
session_start();
date_default_timezone_set('Asia/Taipei');
require 'db.php';
require_once 'lib/session.php';
require_once 'lib/functions.php';

if (!isset($_SESSION['player_id'])) { header('Location: login.php'); exit; }
$user_id = (int)$_SESSION['player_id'];
$user    = $conn->query("SELECT username, gold FROM users WHERE id=$user_id")->fetch_assoc();
$eq      = get_equipment($conn, $user_id);
$table   = get_upgrade_table();

$equip_info = [
    'weapon' => ['name'=>'武器',  'icon'=>'⚔️', 'stat'=>'ATK', 'per'=>5,  'color'=>'#ef5350'],
    'armor'  => ['name'=>'護甲',  'icon'=>'🛡️', 'stat'=>'DEF', 'per'=>2,  'color'=>'#4fc3f7'],
    'helmet' => ['name'=>'頭盔',  'icon'=>'🪖', 'stat'=>'HP',  'per'=>20, 'color'=>'#66bb6a'],
];
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>鍛造 — 塔城傳說</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Segoe UI','微軟正黑體',sans-serif;background:#0d0d1a;color:#e0e0e0;padding:20px;}
.topbar{display:flex;justify-content:space-between;align-items:center;max-width:860px;margin:0 auto 20px;flex-wrap:wrap;gap:10px;}
.topbar h1{font-size:22px;color:#ffca28;letter-spacing:2px;}
.topbar .gold{font-size:15px;color:#ffca28;font-weight:bold;}
.topbar a{color:#94a3b8;font-size:13px;text-decoration:none;padding:6px 14px;border:1px solid #2a2a4a;border-radius:6px;}
.topbar a:hover{border-color:#4fc3f7;color:#4fc3f7;}

.tabs{display:flex;gap:8px;max-width:860px;margin:0 auto 20px;}
.tab{padding:11px 28px;border-radius:8px;border:1px solid #2a2a4a;background:#16213e;
     color:#94a3b8;cursor:pointer;font-size:14px;font-weight:600;transition:all .2s;}
.tab.active{border-color:#ffca28;color:#ffca28;background:rgba(255,202,40,.08);}
.tab-content{display:none;max-width:860px;margin:0 auto;}
.tab-content.active{display:block;}

.forge-card{background:#16213e;border:1px solid #2a2a4a;border-radius:14px;padding:28px;margin-bottom:20px;}
.equip-header{display:flex;align-items:center;gap:16px;margin-bottom:24px;}
.equip-icon{font-size:48px;}
.equip-title h2{font-size:20px;font-weight:700;}
.equip-title p{font-size:13px;color:#94a3b8;margin-top:4px;}

.level-display{text-align:center;margin-bottom:24px;}
.level-num{font-size:52px;font-weight:700;line-height:1;}
.level-label{font-size:12px;color:#94a3b8;margin-top:6px;letter-spacing:2px;}
.bonus-badge{display:inline-block;margin-top:10px;padding:5px 16px;border-radius:20px;font-size:14px;font-weight:700;}

.progress-wrap{background:#0d0d1a;border-radius:8px;overflow:hidden;height:14px;margin:16px 0;}
.progress-fill{height:100%;border-radius:8px;transition:width .5s ease;}

.upgrade-box{background:#0d0d1a;border:1px solid #2a2a4a;border-radius:10px;padding:20px;margin-bottom:20px;}
.upgrade-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;font-size:14px;}
.upgrade-row .label{color:#94a3b8;}
.upgrade-row .val{font-weight:700;}
.chance-bar{background:#1a1a2e;border-radius:6px;overflow:hidden;height:10px;margin:12px 0;}
.chance-fill{height:100%;background:linear-gradient(90deg,#ef5350,#ffca28,#66bb6a);transition:width .4s;}

.btn-upgrade{width:100%;padding:15px;border:none;border-radius:10px;font-size:16px;font-weight:700;
             cursor:pointer;letter-spacing:2px;transition:all .2s;}
.btn-upgrade:hover{opacity:.85;transform:translateY(-1px);}
.btn-upgrade:disabled{opacity:.4;cursor:not-allowed;transform:none;}

.result-box{padding:14px 18px;border-radius:8px;font-size:14px;font-weight:600;text-align:center;
            margin-top:16px;display:none;}
.result-success{background:rgba(102,187,106,.12);border:1px solid rgba(102,187,106,.3);color:#66bb6a;}
.result-fail{background:rgba(239,83,80,.12);border:1px solid rgba(239,83,80,.3);color:#ef9a9a;}

.stats-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:20px;}
.stat-box{background:#0d0d1a;border:1px solid #1a1a2e;border-radius:8px;padding:14px;text-align:center;}
.stat-box .sv{font-size:22px;font-weight:700;margin-bottom:4px;}
.stat-box .sl{font-size:11px;color:#555;}

.maxed-msg{text-align:center;padding:20px;color:#ffca28;font-size:16px;font-weight:700;}

@keyframes shake{0%,100%{transform:translateX(0)}25%{transform:translateX(-6px)}75%{transform:translateX(6px)}}
@keyframes glow{0%,100%{box-shadow:0 0 0 rgba(102,187,106,0)}50%{box-shadow:0 0 20px rgba(102,187,106,.4)}}
.anim-fail{animation:shake .3s ease;}
.anim-success{animation:glow .6s ease;}
</style>
</head>
<body>

<div class="topbar">
  <h1>⚒️ 裝備鍛造</h1>
  <div class="gold">💰 <?= number_format($user['gold']) ?> 金</div>
  <a href="index.php">← 返回城鎮</a>
</div>

<!-- 分頁按鈕 -->
<div class="tabs">
  <?php foreach ($equip_info as $type => $info): ?>
  <div class="tab <?= $type==='weapon'?'active':'' ?>" onclick="switchTab('<?= $type ?>')">
    <?= $info['icon'] ?> <?= $info['name'] ?>
    <span style="font-size:12px;opacity:.7;margin-left:6px;">+<?= $eq[$type]['level'] ?></span>
  </div>
  <?php endforeach; ?>
</div>

<!-- 各裝備分頁 -->
<?php foreach ($equip_info as $type => $info):
  $lv     = (int)$eq[$type]['level'];
  $maxed  = ($lv >= 10);
  $bonus  = $lv * $info['per'];
  $next   = $maxed ? null : $table[$lv];
  $pct    = $lv * 10;
  $att    = (int)$eq[$type]['attempts'];
  $suc    = (int)$eq[$type]['successes'];
  $fail   = $att - $suc;
?>
<div class="tab-content <?= $type==='weapon'?'active':'' ?>" id="tab-<?= $type ?>">
  <div class="forge-card">

    <div class="equip-header">
      <div class="equip-icon"><?= $info['icon'] ?></div>
      <div class="equip-title">
        <h2 style="color:<?= $info['color'] ?>;"><?= $info['name'] ?> +<?= $lv ?></h2>
        <p><?= $info['stat'] ?> 加成：<b style="color:<?= $info['color'] ?>;">+<?= $bonus ?></b>
           每級：+<?= $info['per'] ?> <?= $info['stat'] ?>　最高：+10</p>
      </div>
    </div>

    <!-- 等級進度條 -->
    <div class="progress-wrap">
      <div class="progress-fill" style="width:<?= $pct ?>%;background:<?= $info['color'] ?>;"></div>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:11px;color:#555;margin-bottom:20px;">
      <span>+0</span><span style="color:<?= $info['color'] ?>;font-weight:700;">+<?= $lv ?> / +10</span><span>+10</span>
    </div>

    <!-- 數據統計 -->
    <div class="stats-grid">
      <div class="stat-box">
        <div class="sv" style="color:<?= $info['color'] ?>;">+<?= $bonus ?></div>
        <div class="sl"><?= $info['stat'] ?> 加成</div>
      </div>
      <div class="stat-box">
        <div class="sv" style="color:#4fc3f7;"><?= $att ?></div>
        <div class="sl">累計嘗試</div>
      </div>
      <div class="stat-box">
        <div class="sv" style="color:#66bb6a;"><?= $suc ?> <span style="font-size:13px;color:#555;">/ <?= $fail ?> 失</span></div>
        <div class="sl">成功 / 失敗</div>
      </div>
    </div>

    <?php if ($maxed): ?>
    <div class="maxed-msg">🏆 已達最高等級 +10！</div>
    <?php else: ?>
    <!-- 升級資訊 -->
    <div class="upgrade-box">
      <div style="font-size:13px;color:#94a3b8;margin-bottom:14px;letter-spacing:1px;">升級至 +<?= $lv+1 ?></div>
      <div class="upgrade-row">
        <span class="label">費用</span>
        <span class="val" style="color:#ffca28;">💰 <?= number_format($next['cost']) ?> 金</span>
      </div>
      <div class="upgrade-row">
        <span class="label">你的金幣</span>
        <span class="val" id="gold-display-<?= $type ?>"
          style="color:<?= $user['gold']>=$next['cost']?'#66bb6a':'#ef5350' ?>;">
          <?= number_format($user['gold']) ?> 金
        </span>
      </div>
      <div class="upgrade-row" style="margin-bottom:4px;">
        <span class="label">成功機率</span>
        <span class="val" style="color:#ffca28;"><?= $next['chance'] ?>%</span>
      </div>
      <div class="chance-bar">
        <div class="chance-fill" style="width:<?= $next['chance'] ?>%;"></div>
      </div>
    </div>

    <button class="btn-upgrade" id="btn-<?= $type ?>"
      style="background:linear-gradient(135deg,<?= $info['color'] ?>88,<?= $info['color'] ?>);"
      onclick="doUpgrade('<?= $type ?>')">
      ⚒️ 嘗試強化
    </button>
    <div class="result-box" id="result-<?= $type ?>"></div>
    <?php endif; ?>

  </div>
</div>
<?php endforeach; ?>

<script>
function switchTab(type) {
    document.querySelectorAll('.tab').forEach((t,i)=>t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(t=>t.classList.remove('active'));
    document.getElementById('tab-'+type).classList.add('active');
    const idx = {'weapon':0,'armor':1,'helmet':2}[type];
    document.querySelectorAll('.tab')[idx].classList.add('active');
}

async function doUpgrade(type) {
    const btn    = document.getElementById('btn-'+type);
    const resBox = document.getElementById('result-'+type);
    btn.disabled = true;
    btn.textContent = '強化中...';

    const resp = await fetch('api/forge.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `action=upgrade&type=${type}`
    });
    const data = await resp.json();

    // 更新金幣顯示
    document.querySelector('.gold').textContent = '💰 ' + data.gold.toLocaleString() + ' 金';

    resBox.style.display = 'block';
    if (data.leveled_up) {
        resBox.className = 'result-box result-success anim-success';
        resBox.textContent = data.message;
        setTimeout(() => location.reload(), 1200);
    } else if (data.success === true && !data.leveled_up) {
        resBox.className = 'result-box result-fail anim-fail';
        resBox.textContent = data.message;
        btn.disabled = false;
        btn.textContent = '⚒️ 嘗試強化';
    } else {
        resBox.className = 'result-box result-fail';
        resBox.textContent = data.message;
        btn.disabled = false;
        btn.textContent = '⚒️ 嘗試強化';
    }
}
</script>
</body>
</html>
