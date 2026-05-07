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

// --- 處理三階段訓練 ---
if (isset($_POST['start_train'])) {
    $conn->query("UPDATE users SET last_train_time = NOW() WHERE id = $user_id");
    $msg .= "💪 訓練開始！請等待倒數結束。<br>";
}
if (isset($_POST['claim_train'])) {
    $r = claim_training_reward($conn, $user_id);
    if ($r['success']) {
        $msg .= "<span style='color:#ffeb3b;'>🎁 訓練完成！獲得 {$r['exp_gained']} EXP 與 <b>1 點自由屬性點</b>！</span><br>";
    }
}

// --- 自動升級判定 ---
$lv_result = process_levelup($conn, $user_id);
if ($lv_result['leveled_up']) {
    $msg .= "<span style='color:#ffd700;'><b>🎉 恭喜升級至 Lv.{$lv_result['new_level']}！全屬性提升！</b></span><br>";
}
$user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();

// --- 計算訓練狀態 ---
$cooldown = check_training_cooldown($conn, $user_id);
$train_state = !$cooldown['is_training'] ? 'idle' : ($cooldown['can_claim'] ? 'claim' : 'training');
$remaining_cd = $cooldown['seconds_remaining'];

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
    if ($train_state === 'claim') $msg = "<span style='color:#ffeb3b;'>✨ 訓練已結束，請點擊下方按鈕領取獎勵！</span>";
    elseif ($train_state === 'training') $msg = "<span style='color:#a5d6a7;'>⏳ 正在進行嚴格的屬性鍛鍊中...</span>";
    else $msg = "<span style='color:#a5d6a7;'>⛺ 目前閒置中，隨時可以開始新的訓練。</span>";
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
    </style>
</head>
<body>

<div class="container">
    <div class="panel">
        <div style="display: flex; justify-content: space-between; align-items: baseline;">
            <h2>🧑‍🚀 <?php echo $user['username']; ?> <span style="font-size: 14px; color: #aaa;">(Lv. <?php echo $user['level']; ?>)</span></h2>
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

        <form method="post" id="trainForm">
            <?php if ($train_state === 'idle'): ?>
                <button type="submit" name="start_train" class="btn-train">💪 開始訓練 (10秒)</button>
            <?php elseif ($train_state === 'claim'): ?>
                <button type="submit" name="claim_train" class="btn-claim">🎁 領取訓練獎勵</button>
            <?php else: ?>
                <button type="submit" name="start_train" id="trainBtn" class="btn-train" disabled style="background-color: #555; cursor: not-allowed;">💪 訓練中...</button>
            <?php endif; ?>
        </form>

        <form method="post" onsubmit="return confirm('⚠️ 警告：即將重置帳號！\n等級、屬性、金幣、塔層數將全部歸零。\n\n確定要重新開始嗎？');">
            <button type="submit" name="reset_account" class="btn-reset">🚨 重置帳號</button>
        </form>
    </div>

    <div class="panel">
        <!-- 塔上限變更為 20 層 -->
        <h3>🏰 爬塔挑戰 (上限 20 層)</h3>
        <p style="font-size: 14px;">目前最高通關層數：第 <b style="color: #4caf50; font-size: 18px;"><?php echo $user['max_floor']; ?></b> 層</p>
        
        <div class="tower-list">
            <?php
            $max_display_floors = 20; 
            for ($i = 1; $i <= $max_display_floors; $i++) {
                if ($i <= $user['max_floor']) echo "<a href='tower.php?floor=$i' class='floor-item floor-cleared'>✅ 第 $i 層 (反覆探索)</a>";
                elseif ($i == $user['max_floor'] + 1) echo "<a href='tower.php?floor=$i' id='current-floor' class='floor-item floor-current'>⚔️ 挑戰第 $i 層</a>";
                else echo "<div class='floor-item floor-locked'>🔒 第 $i 層 (未解鎖)</div>";
            }
            ?>
        </div>
    </div>
</div>

<script>
<?php if ($train_state === 'training'): ?>
    let remainingCd = <?php echo $remaining_cd; ?>;
    const trainBtn = document.getElementById('trainBtn');
    const timer = setInterval(() => {
        trainBtn.innerHTML = `💪 訓練中 剩餘 (${remainingCd}秒)`;
        remainingCd--;
        if (remainingCd < 0) {
            clearInterval(timer);
            window.location.href = 'index.php'; 
        }
    }, 1000);
    trainBtn.innerHTML = `💪 訓練中 剩餘 (${remainingCd}秒)`;
<?php endif; ?>

const currentFloorElement = document.getElementById('current-floor');
if (currentFloorElement) currentFloorElement.scrollIntoView({ behavior: 'auto', block: 'center' });
</script>

</body>
</html>