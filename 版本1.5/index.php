<?php
session_start();
date_default_timezone_set('Asia/Taipei');
require 'db.php';
require_once 'lib/functions.php';

if (!isset($_SESSION['player_id'])) { header('Location: login.php'); exit; }
$user_id = (int)$_SESSION['player_id'];
$msg = "";

// --- 處理屬性配點 ---
if (isset($_POST['add_stat'])) {
    $stat_type = $_POST['add_stat'];
    $sql_check = "SELECT stat_points FROM users WHERE id = $user_id";
    $user_check = $conn->query($sql_check)->fetch_assoc();
    if ($user_check['stat_points'] > 0) {
        $update_sql = "";
        if ($stat_type === 'dmg') {
            $update_sql = "UPDATE users SET dmg = dmg + 3, stat_points = stat_points - 1 WHERE id = $user_id";
            $msg .= "<span style='color:#4caf50;'>分配完成：傷害 +3</span><br>";
        } elseif ($stat_type === 'hp') {
            $update_sql = "UPDATE users SET max_hp = max_hp + 10, hp = hp + 10, stat_points = stat_points - 1 WHERE id = $user_id";
            $msg .= "<span style='color:#4caf50;'>分配完成：血量上限 +10</span><br>";
        } elseif ($stat_type === 'def') {
            $update_sql = "UPDATE users SET def = def + 1, stat_points = stat_points - 1 WHERE id = $user_id";
            $msg .= "<span style='color:#4caf50;'>分配完成：防禦 +1</span><br>";
        }
        if ($update_sql) $conn->query($update_sql);
    }
}

// --- 處理重置帳號 ---
if (isset($_POST['reset_account'])) {
    $reset_sql = "UPDATE users SET level=1, exp=0, hp=100, max_hp=100, dmg=10, def=0, stat_points=0, max_floor=0, gold=0, last_train_time=NULL WHERE id = $user_id";
    $conn->query($reset_sql);
    $conn->query("DELETE FROM user_skills WHERE user_id = $user_id"); 
    $msg .= "<span style='color:#f44336;'><b>🚨 帳號已成功重置。一切重新開始。</b></span><br>";
}

// --- 處理訓練（立即發獎）---
if (isset($_POST['start_train'])) {
    $plan_key = $_POST['plan'] ?? 'short';
    $r = start_training($conn, $user_id, $plan_key);
    if ($r['success']) {
        $msg .= "<span style='color:#ffeb3b;'>💪 {$r['label']}訓練開始！立即獲得 <b>{$r['exp_gained']} EXP</b> 與 <b>{$r['stat_gained']} 屬性點</b>！</span><br>";
    } else {
        $msg .= "<span style='color:#ef9a9a;'>⏳ {$r['message']}</span><br>";
    }
}

// --- 自動升級判定 ---
$lv_result = process_levelup($conn, $user_id);
if ($lv_result['leveled_up']) {
    $msg .= "<span style='color:#ffd700;'><b>🎉 恭喜升級至 Lv.{$lv_result['new_level']}！全屬性提升！</b></span><br>";
}
$user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();

// --- 計算訓練冷卻狀態 ---
$cooldown     = check_training_cooldown($conn, $user_id);
$train_state  = $cooldown['is_training'] ? 'cooling' : 'idle';
$remaining_cd = $cooldown['seconds_remaining'];
$train_plans  = get_train_plans();

// 讀取技能爆擊率與閃避率
$crit_lvl = 0;
$dodge_lvl = 0;
$skill_res = $conn->query("SELECT skill_id, level FROM user_skills WHERE user_id = $user_id");
if ($skill_res && $skill_res->num_rows > 0) {
    while($row = $skill_res->fetch_assoc()) {
        if ($row['skill_id'] === 'crit') $crit_lvl = $row['level'];
        if ($row['skill_id'] === 'dodge') $dodge_lvl = $row['level'];
    }
}
$actual_crit_rate = 10 + $crit_lvl;
$actual_dodge_rate = 10 + $dodge_lvl;

if ($msg === "") {
    if ($train_state === 'cooling') $msg = "<span style='color:#a5d6a7;'>⏳ 訓練冷卻中，獎勵已領取，等待結束後可再次訓練。</span>";
    else $msg = "<span style='color:#a5d6a7;'>⛺ 目前閒置中，選擇訓練方案立即獲得獎勵。</span>";
}

$exp_needed = $user['level'] * 100;
$exp_percent = min(100, ($user['exp'] / $exp_needed) * 100);
?>

<!DOCTYPE html>
<html>
<head>
    <title>玄墨的城鎮</title>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #1e1e24; color: #e0e0e0; padding: 20px; margin: 0; }
        .container { display: flex; gap: 15px; flex-wrap: wrap; max-width: 850px; width: 100%; margin: 0 auto; justify-content: center; }
        .panel { background: #2b2b36; padding: 15px; border-radius: 10px; box-shadow: 0 6px 12px rgba(0,0,0,0.3); flex: 1; min-width: 280px; border: 1px solid #3f3f4e; display: flex; flex-direction: column;}
        h2 { margin-top: 0; margin-bottom: 10px; font-size: 20px; color: #ffffff; border-bottom: 2px solid #4caf50; padding-bottom: 8px; }
        h3 { margin-top: 0; margin-bottom: 10px; font-size: 18px; color: #ffffff; border-bottom: 2px solid #4caf50; padding-bottom: 8px; }
        .msg { background: #1a4325; color: #a5d6a7; padding: 10px; border-radius: 6px; margin-bottom: 10px; border: 1px solid #2e7d32; line-height: 1.5; font-size: 14px; min-height: 22px; transition: all 0.3s ease;}
        .progress-container { width: 100%; background-color: #424242; border-radius: 6px; margin: 4px 0 10px 0; overflow: hidden; height: 18px; position: relative; }
        .progress-bar { height: 100%; background-color: #4caf50; transition: width 0.3s ease; }
        .progress-text { position: absolute; width: 100%; text-align: center; top: 0; left: 0; font-size: 11px; line-height: 18px; color: #fff; font-weight: bold; text-shadow: 1px 1px 2px #000; }
        .resource-bar { display: flex; justify-content: space-between; align-items: center; background: #22222b; padding: 8px 12px; border-radius: 6px; border: 1px solid #3f3f4e; margin-bottom: 10px; font-size: 14px;}
        .stats-container { display: flex; flex-direction: column; gap: 6px; margin-bottom: 15px;}
        .stat-row { display: flex; justify-content: space-between; align-items: center; background: #353542; padding: 8px 12px; border-radius: 6px; font-size: 14px;}
        .stat-name { font-weight: bold; color: #ccc;}
        .stat-value { font-size: 15px; font-weight: bold; margin-left: auto; margin-right: 12px;}
        .btn-add { background: #ff9800; color: #fff; border: none; border-radius: 4px; padding: 4px 10px; cursor: pointer; font-weight: bold; font-size: 12px;}
        .btn-add:hover { background: #fb8c00;}
        button { border: none; padding: 10px 15px; font-size: 14px; border-radius: 6px; width: 100%; font-weight: bold; transition: opacity 0.2s; cursor: pointer; }
        button:hover { opacity: 0.8; }
        .btn-train { background-color: #4caf50; color: white; margin-bottom: 8px; }
        .btn-claim { background-color: #ff9800; color: white; margin-bottom: 8px; animation: pulse 1.5s infinite; }
        .btn-reset { background-color: transparent; color: #f44336; border: 1px solid #f44336; font-size: 12px; padding: 6px;}
        .btn-reset:hover { background-color: #f44336; color: white; }
        @keyframes pulse { 0% { transform: scale(1); box-shadow: 0 0 0 rgba(255, 152, 0, 0.4); } 50% { transform: scale(1.02); box-shadow: 0 0 10px rgba(255, 152, 0, 0.8); } 100% { transform: scale(1); box-shadow: 0 0 0 rgba(255, 152, 0, 0.4); } }
        .tower-list { flex-grow: 1; overflow-y: auto; max-height: 380px; padding-right: 8px; margin-top: 5px; }
        .tower-list::-webkit-scrollbar { width: 6px; }
        .tower-list::-webkit-scrollbar-thumb { background: #555; border-radius: 3px; }
        .floor-item { display: block; padding: 10px; margin-bottom: 8px; border-radius: 6px; text-align: center; font-weight: bold; text-decoration: none; color: #fff; transition: transform 0.1s; font-size: 14px;}
        .floor-item:active { transform: scale(0.98); }
        .floor-cleared { background-color: #2e7d32; border: 1px solid #1b5e20; }
        .floor-current { background-color: #f57f17; border: 1px solid #bc5100; color: #fff; } 
        .floor-locked { background-color: #424242; border: 1px solid #212121; color: #757575; cursor: not-allowed; }
        .opt-btn {
            padding:9px 6px;border-radius:7px;border:1px solid #2a2a4a;
            background:#0d0d1a;color:#94a3b8;font-size:12px;font-weight:600;
            text-align:center;transition:all .15s;
        }
        .opt-btn.selected { border-color:#4fc3f7;color:#4fc3f7;background:rgba(79,195,247,.1); }
        .opt-btn:hover { border-color:#4fc3f7;color:#e0e0e0; }
        .mode-card {
            background:#0d0d1a;border:2px solid #2a2a4a;border-radius:10px;
            padding:18px 12px;text-align:center;cursor:pointer;transition:all .2s;
        }
        .mode-card:hover { border-color:#4fc3f7; }
        .mode-card.selected { border-color:#4fc3f7;background:rgba(79,195,247,.08); }
    </style>
</head>
<body>

<div class="container">
    <div class="panel">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h2 style="margin-bottom: 0;">🧑‍🚀 <?php echo $user['username']; ?> <span style="font-size: 14px; color: #aaa;">(Lv. <?php echo $user['level']; ?>)</span></h2>
            <a href="logout.php" style="text-decoration: none;">
                <button type="button" style="background: transparent; color: #f44336; border: 1px solid #f44336; padding: 5px 12px; font-size: 12px; width: auto; border-radius: 5px; cursor: pointer; white-space: nowrap;" onmouseover="this.style.background='#f44336';this.style.color='#fff';" onmouseout="this.style.background='transparent';this.style.color='#f44336';">登出</button>
            </a>
        </div>
        
        <div class='msg'><?php echo $msg; ?></div>

        <p style="margin-bottom: 4px; font-weight: bold; color: #bbb; font-size: 14px;">✨ EXP</p>
        <div class="progress-container">
            <div class="progress-bar" style="width: <?php echo $exp_percent; ?>%;"></div>
            <div class="progress-text"><?php echo $user['exp']; ?> / <?php echo $exp_needed; ?> (<?php echo round($exp_percent, 1); ?>%)</div>
        </div>
        
        <div class="resource-bar">
            <span style="font-weight: bold;">💰 金幣: <span style="color: gold;"><?php echo $user['gold']; ?></span></span>
            <?php if($user['stat_points'] > 0): ?>
                <span style="color: #ffeb3b; font-weight: bold; display: flex; align-items: center; gap: 4px;">
                    ✨ 可用屬性點: 
                    <span style="background: #ff9800; color: #fff; padding: 2px 6px; border-radius: 10px; font-size: 12px;"><?php echo $user['stat_points']; ?></span>
                </span>
            <?php endif; ?>
        </div>

        <div class="stats-container">
            <div class="stat-row"><span class="stat-name">⚔️ 傷害</span><span class="stat-value" style="color: #64b5f6;"><?php echo $user['dmg']; ?></span><?php if($user['stat_points'] > 0): ?><form method="post" style="margin:0;"><button type="submit" name="add_stat" value="dmg" class="btn-add">+3</button></form><?php endif; ?></div>
            <div class="stat-row"><span class="stat-name">🛡️ 防禦</span><span class="stat-value" style="color: #64b5f6;"><?php echo $user['def']; ?></span><?php if($user['stat_points'] > 0): ?><form method="post" style="margin:0;"><button type="submit" name="add_stat" value="def" class="btn-add">+1</button></form><?php endif; ?></div>
            <div class="stat-row"><span class="stat-name">❤️ 血量上限</span><span class="stat-value" style="color: #ef5350;"><?php echo $user['max_hp']; ?></span><?php if($user['stat_points'] > 0): ?><form method="post" style="margin:0;"><button type="submit" name="add_stat" value="hp" class="btn-add">+10</button></form><?php endif; ?></div>
            <!-- 閃避率套用動態數值，拿掉(固定) -->
            <div class="stat-row"><span class="stat-name">🍃 閃避率</span><span class="stat-value" style="color: #81c784;"><?php echo $actual_dodge_rate; ?>%</span></div>
            <div class="stat-row"><span class="stat-name">💥 爆擊率</span><span class="stat-value" style="color: #ff8a65;"><?php echo $actual_crit_rate; ?>%</span></div>
            <div class="stat-row"><span class="stat-name">🔥 爆擊傷害 (固定)</span><span class="stat-value" style="color: #ffb74d;">150%</span></div>
        </div>
        
        <a href="skills.php" style="text-decoration: none;">
            <button type="button" style="background-color: #9c27b0; color: white; margin-bottom: 8px;">📖 查看被動技能</button>
        </a>
        <a href="forge.php" style="text-decoration: none;">
            <button type="button" style="background-color: #b8860b; color: white; margin-bottom: 8px;">⚒️ 裝備鍛造</button>
        </a>
        <a href="arena.php" style="text-decoration: none;">
            <button type="button" style="background-color: #b71c1c; color: white; margin-bottom: 8px;">🏟️ 競技場</button>
        </a>

        <!-- 訓練方案 -->
        <?php if ($train_state === 'idle'): ?>
        <div style="display:flex;flex-direction:column;gap:7px;">
          <?php
          $plan_styles = [
            'short'  => ['bg'=>'#2e7d32','border'=>'#1b5e20','icon'=>'⚡','desc'=>'+50 EXP  +1 屬性點'],
            'medium' => ['bg'=>'#1565c0','border'=>'#0d47a1','icon'=>'🔥','desc'=>'+300 EXP  +3 屬性點'],
            'long'   => ['bg'=>'#6a1b9a','border'=>'#4a148c','icon'=>'💎','desc'=>'+1500 EXP  +10 屬性點'],
          ];
          foreach ($train_plans as $key => $plan):
            $ps = $plan_styles[$key];
          ?>
          <form method="post" style="margin:0;">
            <input type="hidden" name="start_train" value="1">
            <input type="hidden" name="plan" value="<?= $key ?>">
            <button type="submit" style="
              background:<?= $ps['bg'] ?>;border:1px solid <?= $ps['border'] ?>;
              color:#fff;width:100%;padding:11px 14px;border-radius:7px;
              cursor:pointer;font-size:13px;font-weight:bold;
              display:flex;justify-content:space-between;align-items:center;">
              <span><?= $ps['icon'] ?> <?= $plan['label'] ?>訓練</span>
              <span style="font-size:12px;color:rgba(255,255,255,.8);"><?= $ps['desc'] ?></span>
            </button>
          </form>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div id="cooldown-box" style="background:#1a1a2e;border:1px solid #2a2a4a;border-radius:7px;padding:13px;text-align:center;">
          <div style="font-size:13px;color:#94a3b8;margin-bottom:6px;">⏳ 訓練冷卻中</div>
          <div id="cd-timer" style="font-size:22px;font-weight:bold;color:#ffca28;"></div>
          <div style="font-size:11px;color:#555;margin-top:6px;">冷卻結束後可再次選擇訓練</div>
        </div>
        <?php endif; ?>

        <form method="post" onsubmit="return confirm('⚠️ 警告：即將重置帳號！\n等級、屬性、金幣、塔層數將全部歸零。\n\n確定要重新開始嗎？');">
            <button type="submit" name="reset_account" class="btn-reset">🚨 重置帳號</button>
        </form>
    </div>

    <div class="panel">
        <h3>🏰 爬塔挑戰 (上限 20 層)</h3>
        <p style="font-size: 14px;">目前最高通關層數：第 <b style="color: #4caf50; font-size: 18px;"><?php echo $user['max_floor']; ?></b> 層</p>

        <div class="tower-list">
            <?php
            for ($i = 1; $i <= 20; $i++) {
                if ($i <= $user['max_floor'])
                    echo "<div class='floor-item floor-cleared' onclick='openAutoModal($i)' style='cursor:pointer;'>✅ 第 $i 層 (反覆探索)</div>";
                elseif ($i == $user['max_floor'] + 1)
                    echo "<div class='floor-item floor-current' id='current-floor' onclick='openAutoModal($i)' style='cursor:pointer;'>⚔️ 挑戰第 $i 層</div>";
                else
                    echo "<div class='floor-item floor-locked'>🔒 第 $i 層 (未解鎖)</div>";
            }
            ?>
        </div>
    </div>
</div>

<!-- ── 出發設定 Modal ── -->
<div id="auto-modal" style="
  display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);
  z-index:999;align-items:center;justify-content:center;">
  <div style="
    background:#16213e;border:1px solid #2a2a4a;border-radius:16px;
    padding:32px;width:440px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.6);">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <h3 style="color:#e0e0e0;font-size:17px;">🗺️ 出發設定</h3>
      <span id="modal-floor-label" style="color:#4fc3f7;font-size:14px;font-weight:700;"></span>
    </div>

    <!-- 模式選擇 -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:24px;">
      <div id="mode-manual" class="mode-card selected" onclick="setMode('manual')">
        <div style="font-size:28px;margin-bottom:6px;">🎮</div>
        <div style="font-size:14px;font-weight:700;color:#e0e0e0;">手動模式</div>
        <div style="font-size:11px;color:#94a3b8;margin-top:4px;">遇到事件時<br>自行做出選擇</div>
      </div>
      <div id="mode-auto" class="mode-card" onclick="setMode('auto')">
        <div style="font-size:28px;margin-bottom:6px;">⚙️</div>
        <div style="font-size:14px;font-weight:700;color:#e0e0e0;">自動模式</div>
        <div style="font-size:11px;color:#94a3b8;margin-top:4px;">依預設設定<br>全程自動執行</div>
      </div>
    </div>

    <!-- 自動模式設定（預設隱藏） -->
    <div id="auto-settings" style="display:none;">
      <div style="height:1px;background:#1f2937;margin-bottom:20px;"></div>

      <!-- 商人選擇 -->
      <div style="margin-bottom:16px;">
        <div style="font-size:11px;color:#94a3b8;letter-spacing:1px;margin-bottom:8px;">🧙 遇到神秘商人時</div>
        <div style="display:flex;gap:8px;">
          <label style="flex:1;cursor:pointer;"><input type="radio" name="merchant" value="merch_A" style="display:none;">
            <div class="opt-btn" data-group="merchant" data-val="merch_A">🔴 拍紅按鈕</div></label>
          <label style="flex:1;cursor:pointer;"><input type="radio" name="merchant" value="merch_B" style="display:none;">
            <div class="opt-btn" data-group="merchant" data-val="merch_B">🔵 拍藍按鈕</div></label>
          <label style="flex:1;cursor:pointer;"><input type="radio" name="merchant" value="merch_leave" checked style="display:none;">
            <div class="opt-btn selected" data-group="merchant" data-val="merch_leave">🚶 離開</div></label>
        </div>
      </div>

      <!-- 購買 EXP -->
      <div style="margin-bottom:16px;">
        <div style="font-size:11px;color:#94a3b8;letter-spacing:1px;margin-bottom:8px;">📚 遇到傳授經驗的老者時</div>
        <div style="display:flex;gap:8px;">
          <label style="flex:1;cursor:pointer;"><input type="radio" name="buy_exp" value="exp_yes" style="display:none;">
            <div class="opt-btn" data-group="buy_exp" data-val="exp_yes">💰 支付金幣</div></label>
          <label style="flex:1;cursor:pointer;"><input type="radio" name="buy_exp" value="exp_no" checked style="display:none;">
            <div class="opt-btn selected" data-group="buy_exp" data-val="exp_no">🚶 跳過</div></label>
        </div>
      </div>

      <!-- 緊急撤退 -->
      <div style="margin-bottom:20px;">
        <div style="font-size:11px;color:#94a3b8;letter-spacing:1px;margin-bottom:8px;">🩸 HP 低於以下比例時自動撤退</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <?php foreach([0=>['不撤退','#555'],20=>['20%','#ff9800'],30=>['30%','#ef5350'],50=>['50%','#b71c1c']] as $pct=>$info): ?>
          <label style="cursor:pointer;"><input type="radio" name="retreat_hp" value="<?= $pct ?>" <?= $pct===0?'checked':'' ?> style="display:none;">
            <div class="opt-btn <?= $pct===0?'selected':'' ?>" data-group="retreat_hp" data-val="<?= $pct ?>"
                 style="<?= $pct>0?"border-color:{$info[1]};":'' ?>"><?= $info[0] ?></div></label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div style="display:flex;gap:10px;margin-top:4px;">
      <button onclick="closeAutoModal()"
        style="flex:1;padding:12px;background:transparent;border:1px solid #2a2a4a;border-radius:8px;color:#94a3b8;cursor:pointer;font-size:14px;">
        取消
      </button>
      <button onclick="startTower()" id="start-btn"
        style="flex:2;padding:12px;background:linear-gradient(135deg,#1565c0,#4fc3f7);border:none;border-radius:8px;color:#fff;font-weight:700;cursor:pointer;font-size:14px;letter-spacing:1px;">
        🎮 手動出發！
      </button>
    </div>
  </div>
</div>

<script>
<?php if ($train_state === 'cooling'): ?>
let cd = <?= $remaining_cd ?>;
const timerEl = document.getElementById('cd-timer');
function fmt(s) {
    const h = Math.floor(s/3600), m = Math.floor((s%3600)/60), sec = s%60;
    if (h > 0) return `${h}:${String(m).padStart(2,'0')}:${String(sec).padStart(2,'0')}`;
    return `${String(m).padStart(2,'0')}:${String(sec).padStart(2,'0')}`;
}
timerEl.textContent = fmt(cd);
const t = setInterval(() => {
    cd--;
    if (cd <= 0) { clearInterval(t); location.reload(); return; }
    timerEl.textContent = fmt(cd);
}, 1000);
<?php endif; ?>
const cur = document.getElementById('current-floor');
if (cur) cur.scrollIntoView({ behavior:'auto', block:'center' });

// ── 出發設定 Modal ──
let selectedFloor = 1;
let selectedMode  = 'manual';

function openAutoModal(floor) {
    selectedFloor = floor;
    document.getElementById('modal-floor-label').textContent = '第 ' + floor + ' 層';
    document.getElementById('auto-modal').style.display = 'flex';
}
function closeAutoModal() {
    document.getElementById('auto-modal').style.display = 'none';
}

function setMode(mode) {
    selectedMode = mode;
    document.getElementById('mode-manual').classList.toggle('selected', mode === 'manual');
    document.getElementById('mode-auto').classList.toggle('selected', mode === 'auto');
    document.getElementById('auto-settings').style.display = mode === 'auto' ? 'block' : 'none';
    document.getElementById('start-btn').textContent = mode === 'auto' ? '⚙️ 自動出發！' : '🎮 手動出發！';
}

// 選項按鈕切換
document.querySelectorAll('.opt-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const group = btn.dataset.group;
        document.querySelectorAll(`.opt-btn[data-group="${group}"]`).forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
        document.querySelector(`input[name="${group}"][value="${btn.dataset.val}"]`).checked = true;
    });
});

function startTower() {
    if (selectedMode === 'manual') {
        window.location.href = 'tower.php?floor=' + selectedFloor + '&mode=manual';
    } else {
        const merchant   = document.querySelector('input[name="merchant"]:checked').value;
        const buy_exp    = document.querySelector('input[name="buy_exp"]:checked').value;
        const retreat_hp = document.querySelector('input[name="retreat_hp"]:checked').value;
        const params = new URLSearchParams({ floor: selectedFloor, mode: 'auto', merchant, buy_exp, retreat_hp });
        window.location.href = 'tower.php?' + params.toString();
    }
}

// 點背景關閉
document.getElementById('auto-modal').addEventListener('click', function(e) {
    if (e.target === this) closeAutoModal();
});
</script>

</body>
</html>