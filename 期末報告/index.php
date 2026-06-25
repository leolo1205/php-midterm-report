<?php
ob_start();
session_start();
date_default_timezone_set('Asia/Taipei');
require 'db.php';
require_once 'lib/session.php';
require_once 'lib/functions.php';

if (!isset($_SESSION['player_id'])) { header('Location: login.php'); exit; }
$user_id = (int)$_SESSION['player_id'];
$msg = "";

// --- CSRF 驗證（所有 POST 操作） ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
    die('安全驗證失敗，請重新整理頁面後再試。');
}

// --- 處理屬性配點 ---
if (isset($_POST['add_stat'])) {
    $stat_type = $_POST['add_stat'];
    $stat_map = [
        'dmg' => ['SET dmg=dmg+3',                    '傷害 +3'],
        'hp'  => ['SET max_hp=max_hp+10,hp=hp+10',    '血量上限 +10'],
        'def' => ['SET def=def+1',                     '防禦 +1'],
    ];
    $user_check = $conn->query("SELECT stat_points FROM users WHERE id=$user_id")->fetch_assoc();
    if ($user_check['stat_points'] > 0 && isset($stat_map[$stat_type])) {
        [$set_clause, $label] = $stat_map[$stat_type];
        $conn->query("UPDATE users $set_clause,stat_points=stat_points-1 WHERE id=$user_id");
        $msg .= "<span style='color:#4caf50;'>分配完成：$label</span><br>";
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

// --- 爬塔失敗冷卻狀態 ---
$tower_fail_until_ts = !empty($user['tower_fail_until']) ? strtotime($user['tower_fail_until']) : 0;
$tower_cooling = $tower_fail_until_ts > time();
$tower_cd_secs = $tower_cooling ? ($tower_fail_until_ts - time()) : 0;

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

// --- 計算真實屬性（套入裝備倍率與技能樹加成）---
$effective = get_player_effective_stats($conn, $user_id);

if ($msg === "") {
    if ($train_state === 'cooling') $msg = "<span style='color:#a5d6a7;'>⏳ 訓練冷卻中，獎勵已領取，等待結束後可再次訓練。</span>";
    else $msg = "<span style='color:#a5d6a7;'>⛺ 目前閒置中，選擇訓練方案立即獲得獎勵。</span>";
}

$exp_needed = level_exp_required((int)$user['level']);
$exp_percent = $exp_needed > 0 ? min(100, ($user['exp'] / $exp_needed) * 100) : 0;
?>

<!DOCTYPE html>
<html>
<head>
    <title>玄墨的城鎮</title>
    <meta charset="utf-8">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="assets/style.css">
    <style>
/* index 頁面專屬 */
.index-container { display:flex; gap:15px; flex-wrap:wrap; max-width:850px; width:100%; margin:0 auto; justify-content:center; }
.panel { background:var(--bg-card); padding:15px; border-radius:10px; box-shadow:0 6px 12px rgba(0,0,0,.3); flex:1; min-width:280px; border:1px solid var(--border); display:flex; flex-direction:column; }
h2 { margin-top:0; margin-bottom:10px; font-size:20px; color:#ffffff; border-bottom:2px solid var(--accent-green); padding-bottom:8px; }
h3 { margin-top:0; margin-bottom:10px; font-size:18px; color:#ffffff; border-bottom:2px solid var(--accent-green); padding-bottom:8px; }
.progress-container { width:100%; background-color:#424242; border-radius:6px; margin:4px 0 10px 0; overflow:hidden; height:18px; position:relative; }
.progress-bar-hp { height:100%; background-color:var(--accent-green); transition:width 0.3s ease; }
.progress-text { position:absolute; width:100%; text-align:center; top:0; left:0; font-size:11px; line-height:18px; color:#fff; font-weight:bold; text-shadow:1px 1px 2px #000; }
.resource-bar { display:flex; justify-content:space-between; align-items:center; background:#22222b; padding:8px 12px; border-radius:6px; border:1px solid var(--border); margin-bottom:10px; font-size:14px; }
.stats-container { display:flex; flex-direction:column; gap:6px; margin-bottom:15px; }
.stat-row { display:flex; justify-content:space-between; align-items:center; background:#353542; padding:8px 12px; border-radius:6px; font-size:14px; }
.stat-name { font-weight:bold; color:#ccc; }
.stat-value { font-size:15px; font-weight:bold; margin-left:auto; margin-right:12px; }
.stat-raw { font-size:11px; color:var(--text-dim); margin-left:4px; font-weight:normal; }
.btn-add { background:#ff9800; color:#fff; border:none; border-radius:4px; padding:4px 10px; cursor:pointer; font-weight:bold; font-size:12px; }
.btn-add:hover { background:#fb8c00; }
.btn-train { background-color:var(--accent-green); color:white; margin-bottom:8px; border:none; padding:10px 15px; font-size:14px; border-radius:6px; width:100%; font-weight:bold; cursor:pointer; }
.btn-train:hover { opacity:.8; }
.btn-reset { background-color:transparent; color:var(--accent-red); border:1px solid var(--accent-red); font-size:12px; padding:6px; border-radius:6px; width:100%; cursor:pointer; }
.btn-reset:hover { background-color:var(--accent-red); color:white; }
@keyframes pulse { 0%{transform:scale(1);} 50%{transform:scale(1.02);box-shadow:0 0 10px rgba(255,152,0,.8);} 100%{transform:scale(1);} }
.tower-list { flex-grow:1; overflow-y:auto; max-height:380px; padding-right:8px; margin-top:5px; }
.tower-list::-webkit-scrollbar { width:6px; }
.tower-list::-webkit-scrollbar-thumb { background:#555; border-radius:3px; }
.floor-item { display:block; padding:10px; margin-bottom:8px; border-radius:6px; text-align:center; font-weight:bold; text-decoration:none; color:#fff; transition:transform 0.1s; font-size:14px; }
.floor-item:active { transform:scale(.98); }
.floor-cleared { background-color:#2e7d32; border:1px solid #1b5e20; }
.floor-current { background-color:#f57f17; border:1px solid #bc5100; color:#fff; }
.floor-locked { background-color:#424242; border:1px solid #212121; color:#757575; cursor:not-allowed; }
.opt-btn { padding:9px 6px; border-radius:7px; border:1px solid var(--border); background:var(--bg-base); color:var(--text-muted); font-size:12px; font-weight:600; text-align:center; transition:all .15s; }
.opt-btn.selected { border-color:var(--accent-blue); color:var(--accent-blue); background:rgba(79,195,247,.1); }
.opt-btn:hover { border-color:var(--accent-blue); color:var(--text-primary); }
.mode-card { background:var(--bg-base); border:2px solid var(--border); border-radius:10px; padding:18px 12px; text-align:center; cursor:pointer; transition:all .2s; }
.mode-card:hover { border-color:var(--accent-blue); }
.mode-card.selected { border-color:var(--accent-blue); background:rgba(79,195,247,.08); }
    </style>
</head>
<body>
<?php require '_sidebar.php'; ?>
<div class="page-body">
<div class="index-container">
    <div class="panel">
        <h2>🧑‍🚀 <?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?> <span style="font-size: 14px; color: #aaa;">(Lv. <?php echo $user['level']; ?>)</span></h2>

        <div class='msg-box'><?php echo $msg; ?></div>

        <p style="margin-bottom: 4px; font-weight: bold; color: #bbb; font-size: 14px;">✨ EXP</p>
        <div class="progress-container">
            <div class="progress-bar-hp" style="width: <?php echo $exp_percent; ?>%;"></div>
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
            <!-- 傷害：真實值，原數值放在名稱旁 -->
            <div class="stat-row">
                <span class="stat-name">
                    ⚔️ 傷害
                    <span class="stat-raw">(<?= $effective['atk']['raw'] ?>)</span>
                </span>
                <span class="stat-value" style="color:#64b5f6;"
                    title="傷害：原始 <?= $effective['atk']['raw'] ?> + 技能樹加成 <?= $effective['atk']['flat'] ?>，裝備 +<?= $effective['atk']['equip_level'] ?> 倍率 <?= number_format($effective['atk']['mult'], 2) ?>x = <?= $effective['atk']['value'] ?>">
                    <?= $effective['atk']['value'] ?>
                </span>
                <?php if($user['stat_points'] > 0): ?>
                <form method="post" style="margin:0;"><?= csrf_field() ?>
                    <button type="submit" name="add_stat" value="dmg" class="btn-add">+3</button>
                </form>
                <?php endif; ?>
            </div>

            <!-- 防禦：真實值，原數值放在名稱旁 -->
            <div class="stat-row">
                <span class="stat-name">
                    🛡️ 防禦
                    <span class="stat-raw">(<?= $effective['def']['raw'] ?>)</span>
                </span>
                <span class="stat-value" style="color:#64b5f6;"
                    title="防禦：原始 <?= $effective['def']['raw'] ?> + 技能樹加成 <?= $effective['def']['flat'] ?>，裝備 +<?= $effective['def']['equip_level'] ?> 倍率 <?= number_format($effective['def']['mult'], 2) ?>x = <?= $effective['def']['value'] ?>">
                    <?= $effective['def']['value'] ?>
                </span>
                <?php if($user['stat_points'] > 0): ?>
                <form method="post" style="margin:0;"><?= csrf_field() ?>
                    <button type="submit" name="add_stat" value="def" class="btn-add">+1</button>
                </form>
                <?php endif; ?>
            </div>

            <!-- 血量上限：真實值，原數值放在名稱旁 -->
            <div class="stat-row">
                <span class="stat-name">
                    ❤️ 血量上限
                    <span class="stat-raw">(<?= $effective['hp']['raw'] ?>)</span>
                </span>
                <span class="stat-value" style="color:#ef5350;"
                    title="血量上限：原始 <?= $effective['hp']['raw'] ?> + 技能樹加成 <?= $effective['hp']['flat'] ?>，裝備 +<?= $effective['hp']['equip_level'] ?> 倍率 <?= number_format($effective['hp']['mult'], 2) ?>x = <?= $effective['hp']['value'] ?>">
                    <?= $effective['hp']['value'] ?>
                </span>
                <?php if($user['stat_points'] > 0): ?>
                <form method="post" style="margin:0;"><?= csrf_field() ?>
                    <button type="submit" name="add_stat" value="hp" class="btn-add">+10</button>
                </form>
                <?php endif; ?>
            </div>

            <!-- 閃避率、爆擊率、爆擊傷害（不受裝備影響，維持原顯示） -->
            <div class="stat-row"><span class="stat-name">🍃 閃避率</span><span class="stat-value" style="color:#81c784;"><?php echo $actual_dodge_rate; ?>%</span></div>
            <div class="stat-row"><span class="stat-name">💥 爆擊率</span><span class="stat-value" style="color:#ff8a65;"><?php echo $actual_crit_rate; ?>%</span></div>
            <div class="stat-row"><span class="stat-name">🔥 爆擊傷害 (固定)</span><span class="stat-value" style="color:#ffb74d;">150%</span></div>
        </div>

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
                <?= csrf_field() ?>
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
            <?= csrf_field() ?>
            <button type="submit" name="reset_account" class="btn-reset">🚨 重置帳號</button>
        </form>
    </div>

    <div class="panel">
        <h3>🏰 爬塔挑戰 (上限 100 層)</h3>
        <p style="font-size: 14px;">目前最高通關層數：第 <b style="color: #4caf50; font-size: 18px;"><?php echo $user['max_floor']; ?></b> 層</p>

        <?php if ($tower_cooling): ?>
        <div style="background:#3b1a1a; border:1px solid #f44336; border-radius:8px; padding:12px; margin-bottom:12px; text-align:center;">
            <div style="color:#f44336; font-weight:bold; margin-bottom:4px;">⛔ 爬塔冷卻中</div>
            <div style="color:#aaa; font-size:13px;">上次挑戰失敗，休息後再出征</div>
            <div id="tower-cd-display" style="color:#ffca28; font-size:18px; font-weight:bold; margin-top:6px;"></div>
        </div>
        <?php endif; ?>

        <div class="tower-list">
            <?php
            for ($i = 1; $i <= 100; $i++) {
                $ms = ($i % 10 === 0) ? "border-left:3px solid #ff9800;" : "";
                if ($tower_cooling) {
                    echo "<div class='floor-item floor-locked' title='爬塔冷卻中' style='{$ms}'>⛔ 第 $i 層 (冷卻中)</div>";
                } elseif ($i <= $user['max_floor']) {
                    echo "<div class='floor-item floor-cleared' onclick='openAutoModal($i)' style='cursor:pointer;{$ms}'>✅ 第 $i 層 (反覆探索)</div>";
                } elseif ($i == $user['max_floor'] + 1) {
                    echo "<div class='floor-item floor-current' id='current-floor' onclick='openAutoModal($i)' style='cursor:pointer;{$ms}'>⚔️ 挑戰第 $i 層</div>";
                } else {
                    echo "<div class='floor-item floor-locked' style='{$ms}'>🔒 第 $i 層 (未解鎖)</div>";
                }
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

        </div>

        <!-- 撤退保險 -->
        <div style="height:1px;background:#1f2937;margin:8px 0 16px;"></div>
        <div style="margin-bottom:4px;">
            <div style="font-size:11px;color:#94a3b8;letter-spacing:1px;margin-bottom:8px;">🛡️ 撤退保險（血量 ≤ 30% 時自動撤退，不算失敗）</div>
            <div style="display:flex;gap:8px;">
                <label style="flex:1;cursor:pointer;"><input type="radio" name="retreat_insure" value="insure_yes" style="display:none;">
                    <div class="opt-btn" data-group="retreat_insure" data-val="insure_yes"
                         <?php if ($user['gold'] < 1000): ?>style="opacity:0.4;cursor:not-allowed;"<?php endif; ?>>
                        💰 支付 1000 金
                        <span style="font-size:10px;color:#aaa;display:block;margin-top:2px;">
                            <?= $user['gold'] < 1000 ? '（金幣不足）' : "（持有 {$user['gold']} 金）" ?>
                        </span>
                    </div>
                </label>
                <label style="flex:1;cursor:pointer;"><input type="radio" name="retreat_insure" value="insure_no" checked style="display:none;">
                    <div class="opt-btn selected" data-group="retreat_insure" data-val="insure_no">🚫 不投保</div>
                </label>
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
<?php if ($tower_cooling): ?>
let towerCd = <?= $tower_cd_secs ?>;
const towerCdEl = document.getElementById('tower-cd-display');
function fmtTower(s) {
    const m = Math.floor(s/60), sec = s%60;
    return `${String(m).padStart(2,'0')}:${String(sec).padStart(2,'0')}`;
}
if (towerCdEl) towerCdEl.textContent = fmtTower(towerCd);
const towerTimer = setInterval(() => {
    towerCd--;
    if (towerCd <= 0) { clearInterval(towerTimer); location.reload(); return; }
    if (towerCdEl) towerCdEl.textContent = fmtTower(towerCd);
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
    const retreat_insure = document.querySelector('input[name="retreat_insure"]:checked').value;
    const fields = { floor: selectedFloor, mode: selectedMode, retreat_insure };
    if (selectedMode === 'auto') {
        fields.merchant = document.querySelector('input[name="merchant"]:checked').value;
        fields.buy_exp  = document.querySelector('input[name="buy_exp"]:checked').value;
    }
    fields.csrf_token = document.querySelector('meta[name="csrf-token"]').content;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'tower.php';
    for (const [k, v] of Object.entries(fields)) {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = k; inp.value = v;
        form.appendChild(inp);
    }
    document.body.appendChild(form);
    form.submit();
}

// 點背景關閉
document.getElementById('auto-modal').addEventListener('click', function(e) {
    if (e.target === this) closeAutoModal();
});
</script>
</div><!-- /index-container -->
</div><!-- /page-body -->
</body>
</html>