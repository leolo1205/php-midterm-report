<?php
ob_start();
$t_start = microtime(true);
session_start();
date_default_timezone_set('Asia/Taipei');
require 'db.php';
require_once 'lib/session.php';
require_once 'lib/functions.php';
require 'tower_story.php';
require 'tower_monsters.php';

if (!isset($_SESSION['player_id'])) { header('Location: login.php'); exit; }
$user_id = (int)$_SESSION['player_id'];
$stmt = $conn->prepare("SELECT id, username, level, exp, hp, max_hp, dmg, def, gold, stat_points, max_floor, is_banned, tower_fail_until FROM users WHERE id=?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$_eff = get_player_effective_stats($conn, $user_id);

$monster_db = [];
$mob_res = $conn->query("SELECT * FROM monster_stats");
if ($mob_res) { while ($row = $mob_res->fetch_assoc()) { $monster_db[$row['level']] = $row; } }

// 初始化樓層（必須 POST + CSRF，防止 CSRF 偽造扣金幣）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['floor'])) {
    if (!csrf_verify()) {
        die("<h2 style='color:white; text-align:center;'>安全驗證失敗，請重新整理後再試。<br><a href='index.php'>⬅ 返回</a></h2>");
    }
    $target_floor = (int)$_POST['floor'];
    if ($target_floor < 1 || $target_floor > 100 || $target_floor > $user['max_floor'] + 1) {
        die("<h2 style='color:white; text-align:center;'>領域展開失敗：未解鎖！<br><a href='index.php'>⬅ 返回</a></h2>");
    }

    // 失敗冷卻檢查（使用已取得的 $user，避免欄位不存在時崩潰）
    $fail_until_ts = !empty($user['tower_fail_until']) ? strtotime($user['tower_fail_until']) : 0;
    if ($fail_until_ts > time()) {
        $secs = $fail_until_ts - time();
        $mins = (int)($secs / 60);
        $rem_s = $secs % 60;
        die("<div style='font-family:sans-serif; background:#1e1e24; color:#e0e0e0; min-height:100vh; display:flex; align-items:center; justify-content:center; flex-direction:column;'>
            <h2 style='color:#f44336;'>⛔ 爬塔冷卻中</h2>
            <p style='color:#aaa;'>上次挑戰失敗，需休息一小時才能再次出征。</p>
            <p style='color:#ffca28; font-size:20px; font-weight:bold;'>剩餘 {$mins} 分 {$rem_s} 秒</p>
            <a href='index.php' style='color:#64b5f6; margin-top:16px;'>⬅ 返回城鎮</a>
        </div>");
    }
    // 撤退保險（原子扣款：WHERE gold >= 1000 防止競態）
    $retreat_insure = ($_POST['retreat_insure'] ?? 'insure_no') === 'insure_yes';
    if ($retreat_insure) {
        $stmt = $conn->prepare("UPDATE users SET gold = gold - 1000 WHERE id = ? AND gold >= 1000");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            $stmt->close();
            die("<div style='font-family:sans-serif;background:#1e1e24;color:#e0e0e0;min-height:100vh;display:flex;align-items:center;justify-content:center;flex-direction:column;'><h2 style='color:#f44336;'>💸 金幣不足</h2><p>撤退保險需要 1000 金幣。</p><a href='index.php' style='color:#64b5f6;'>⬅ 返回</a></div>");
        }
        $stmt->close();
    }
    // 儲存模式設定（白名單驗證所有值）
    $mode = ($_POST['mode'] ?? 'manual') === 'auto' ? 'auto' : 'manual';
    $valid_merchant = ['merch_A', 'merch_B', 'merch_leave'];
    $valid_buy_exp  = ['exp_yes', 'exp_no'];
    $merchant_val   = $_POST['merchant'] ?? 'merch_leave';
    $buy_exp_val    = $_POST['buy_exp']  ?? 'exp_no';
    $_SESSION['auto_settings'] = [
        'mode'       => $mode,
        'merchant'   => in_array($merchant_val, $valid_merchant, true) ? $merchant_val : 'merch_leave',
        'buy_exp'    => in_array($buy_exp_val,  $valid_buy_exp,  true) ? $buy_exp_val  : 'exp_no',
        'retreat_hp' => 0,
    ];
    $_SESSION['run'] = ['floor' => $target_floor, 'node' => 1, 'hp' => (int)$_eff['hp']['value'], 'gold' => 0, 'exp' => 0, 'buffs' => ['dmg'=>0, 'def'=>0, 'max_hp'=>0], 'skill_gains' => [], 'log' => '', 'state' => 'auto', 'retreat_insured' => $retreat_insure];
    header("Location: tower.php"); exit;
}
if (!isset($_SESSION['run'])) { header("Location: index.php"); exit; }

$run = &$_SESSION['run'];
$target_floor = $run['floor'];
$story_nodes = [5, 10, 15, 20, 25, 29];

// 讀取技能
$crit_lvl = 0; $dodge_lvl = 0;
$skill_stmt = $conn->prepare("SELECT skill_id, level FROM user_skills WHERE user_id = ?");
$skill_stmt->bind_param('i', $user_id);
$skill_stmt->execute();
$skill_res = $skill_stmt->get_result();
while ($row = $skill_res->fetch_assoc()) {
    if ($row['skill_id'] === 'crit')  $crit_lvl  = $row['level'];
    if ($row['skill_id'] === 'dodge') $dodge_lvl = $row['level'];
}
$skill_stmt->close();
$p_crit_rate = 10 + $crit_lvl; $p_dodge_rate = 10 + $dodge_lvl; 

$new_log = ""; $old_log = $run['log']; 

// 處理 POST 抉擇
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify()) { die('安全驗證失敗，請重新整理頁面後再試。'); }
    $action = $_POST['action']; $node = $run['node'];
    $post_new = "<div class='node-box reveal-item hidden-item' data-delay='100'>"; $post_old = "<div class='node-box'>";
    $add_post_line = function($text, $delay=800) use (&$post_new, &$post_old) {
        $post_new .= "<div class='reveal-item hidden-item' data-delay='$delay'>$text</div>";
        $post_old .= "<div>$text</div>";
    };
    $add_post_line("<h4 class='node-title'>節點 $node / 30 - 抉擇結果</h4>", 500);

    if ($run['state'] === 'wait_merchant') {
        if ($action === 'merch_leave') {
            $add_post_line("<p>你轉身離開了商人。</p>");
            $run['state'] = 'auto';
        } else {
            $btn = ($action === 'merch_A') ? "紅" : "藍";
            $add_post_line("<p>按下了 $btn 按鈕...</p>", 500);
            if (rand(1, 100) <= 50) {
                $run['buffs']['dmg'] += 10;
                $add_post_line("<p style='color:#4caf50;'>🎉 獲得傷害 +10！</p>");
            } else {
                $eff_max_hp = (int)$_eff['hp']['value'] + (int)($run['buffs']['max_hp'] ?? 0);
                $dmg = floor($eff_max_hp * 0.3);
                $run['hp'] -= $dmg;
                $add_post_line("<p style='color:#f44336;'>💀 箱子爆炸，受傷 {$dmg}！</p>");
            }
            $run['state'] = ($run['hp'] > 0) ? 'auto' : 'dead';
        }
    } elseif ($run['state'] === 'wait_exp') {
        $cost = 5 * $target_floor;
        if ($action === 'exp_yes' && $run['gold'] >= $cost) {
            // 只從本局金幣扣，保持數值非負
            $run['gold'] -= $cost;
            $run['exp'] += (10 * $target_floor);
            $add_post_line("<p style='color:#64b5f6;'>✨ 交易成功！獲得 EXP！</p>");
        } elseif ($action === 'exp_yes') {
            $add_post_line("<p style='color:#f44336;'>💸 本局金幣不足，無法交易。</p>");
        } else {
            $add_post_line("<p>你繼續前進。</p>");
        }
        $run['state'] = 'auto';
    }
    $post_new .= "</div>"; $post_old .= "</div>"; $new_log .= $post_new; $run['log'] .= $post_old; $run['node']++; 
}

// 自動模式設定
$auto = $_SESSION['auto_settings'] ?? ['merchant'=>'merch_leave','buy_exp'=>'exp_no','retreat_hp'=>0];

// 預先計算有效屬性（供 stat-snap 與面板使用）
$_eff = get_player_effective_stats($conn, $user_id);

// 事件自動推動迴圈
while ($run['state'] === 'auto' && $run['node'] <= 30) {

    $node = $run['node'];
    $event = null; 
    
    $node_new = "<div class='node-box reveal-item hidden-item' data-delay='150'>"; $node_old = "<div class='node-box'>";
    $add_line = function($text, $delay = 800) use (&$node_new, &$node_old) {
        $node_new .= "<div class='reveal-item hidden-item' data-delay='$delay'>$text</div>";
        $node_old .= "<div>$text</div>";
    };
    $add_line("<h4 class='node-title'>節點 $node / 30</h4>", 500);

    if (in_array($node, $story_nodes)) { 
        $add_line(get_story($target_floor, $node), 3000); 
    } elseif ($node == 30) { 
        $event = 'boss'; 
    } else {
        $weights = ['monster'=>60, 'gold'=>30, 'heal'=>20, 'buff'=>10, 'rest'=>25, 'trap'=>10, 'merchant'=>20, 'buy_exp'=>20, 'curse'=>5, 'blessing'=>1];
        $total = array_sum($weights); $rand = rand(1, $total); $curr = 0;
        foreach ($weights as $e => $w) { $curr += $w; if ($rand <= $curr) { $event = $e; break; } }
    }

    $stop_loop = false;

    if ($event !== null) {
        if ($event === 'monster' || $event === 'boss') {
            require 'tower_combat.php';
        } else {
            require 'tower_events.php';
            // 自動模式：事件要求等待輸入時，直接套用預設設定
            if ($auto['mode'] === 'auto') {
                if ($run['state'] === 'wait_merchant') {
                    $choice = $auto['merchant'];
                    if ($choice === 'merch_leave') {
                        $node_new .= "<div class='reveal-item hidden-item' data-delay='400'><p>⚙️ 自動：轉身離開商人。</p></div>";
                        $node_old .= "<div><p>⚙️ 自動：轉身離開商人。</p></div>";
                        $run['state'] = 'auto';
                    } else {
                        $btn = ($choice === 'merch_A') ? '紅' : '藍';
                        $node_new .= "<div class='reveal-item hidden-item' data-delay='400'><p>⚙️ 自動：按下 {$btn} 按鈕...</p></div>";
                        $node_old .= "<div><p>⚙️ 自動：按下 {$btn} 按鈕...</p></div>";
                        if (rand(1, 100) <= 50) {
                            $run['buffs']['dmg'] += 10;
                            $node_new .= "<div class='reveal-item hidden-item' data-delay='600'><p style='color:#4caf50;'>🎉 獲得傷害 +10！</p></div>";
                            $node_old .= "<div><p style='color:#4caf50;'>🎉 獲得傷害 +10！</p></div>";
                        } else {
                            $eff_max_hp = (int)$_eff['hp']['value'] + (int)($run['buffs']['max_hp'] ?? 0);
                            $dmg = floor($eff_max_hp * 0.3);
                            $run['hp'] -= $dmg;
                            $node_new .= "<div class='reveal-item hidden-item' data-delay='600'><p style='color:#f44336;'>💀 箱子爆炸，受傷 {$dmg}！</p></div>";
                            $node_old .= "<div><p style='color:#f44336;'>💀 箱子爆炸，受傷 {$dmg}！</p></div>";
                        }
                        $run['state'] = ($run['hp'] > 0) ? 'auto' : 'dead';
                    }
                    $stop_loop = false;
                } elseif ($run['state'] === 'wait_exp') {
                    $cost = 5 * $target_floor; $gain = 10 * $target_floor;
                    if ($auto['buy_exp'] === 'exp_yes' && $run['gold'] >= $cost) {
                        // 只從本局金幣扣，確保不變負
                        $run['gold'] -= $cost; $run['exp'] += $gain;
                        $node_new .= "<div class='reveal-item hidden-item' data-delay='400'><p style='color:#64b5f6;'>⚙️ 自動：支付 {$cost} 金，獲得 {$gain} EXP！</p></div>";
                        $node_old .= "<div><p style='color:#64b5f6;'>⚙️ 自動：支付 {$cost} 金，獲得 {$gain} EXP！</p></div>";
                    } else {
                        $node_new .= "<div class='reveal-item hidden-item' data-delay='400'><p>⚙️ 自動：跳過老者。</p></div>";
                        $node_old .= "<div><p>⚙️ 自動：跳過老者。</p></div>";
                    }
                    $run['state'] = 'auto';
                    $stop_loop = false;
                }
            }
        }
        // 手動模式：遇到需要等待輸入的事件就停住
        if ($auto['mode'] === 'manual' && in_array($run['state'], ['wait_merchant', 'wait_exp'])) {
            $stop_loop = true;
        }
    }

    $new_log .= $node_new . "</div>"; $run['log'] .= $node_old . "</div>";
    $new_log .= "<div class='stat-snap hidden-item' data-delay='1'"
        . " data-hp='" . (int)$run['hp'] . "'"
        . " data-mhp='" . ((int)$_eff['hp']['value'] + (int)($run['buffs']['max_hp'] ?? 0)) . "'"
        . " data-atk='" . ((int)$_eff['atk']['value'] + (int)($run['buffs']['dmg'] ?? 0)) . "'"
        . " data-def='" . ((int)$_eff['def']['value'] + (int)($run['buffs']['def'] ?? 0)) . "'"
        . " data-gold='" . (int)$run['gold'] . "'"
        . " data-node='" . (int)$run['node'] . "'"
        . " style='position:absolute;width:0;height:0;overflow:hidden;'></div>";
    if ($stop_loop) break;
    $run['node']++;
}

// 結算畫面
if (($run['state'] === 'auto' && $run['node'] > 30) || $run['state'] === 'dead' || $run['state'] === 'retreat') {
    $f_gold = $run['gold']; 
    $f_exp = $run['exp'];
    $crit_gain_display = 0; 
    $dodge_gain_display = 0;

    // 處理技能升級
    if (isset($run['skill_gains']) && !empty($run['skill_gains'])) {
        foreach ($run['skill_gains'] as $s_id => $gained_exp) {
            if (!in_array($s_id, ['crit', 'dodge'], true)) continue;
            if ($s_id === 'crit') $crit_gain_display = $gained_exp;
            if ($s_id === 'dodge') $dodge_gain_display = $gained_exp;

            $sel = $conn->prepare("SELECT level, exp FROM user_skills WHERE user_id=? AND skill_id=?");
            $sel->bind_param('is', $user_id, $s_id);
            $sel->execute();
            $row = $sel->get_result()->fetch_assoc();
            $sel->close();

            $s_lvl = (int)($row['level'] ?? 0);
            $s_exp = (int)($row['exp']   ?? 0) + $gained_exp;
            while ($s_exp >= ($s_lvl + 1) * 10) { $s_exp -= ($s_lvl + 1) * 10; $s_lvl++; }

            $ins = $conn->prepare("INSERT INTO user_skills (user_id, skill_id, level, exp) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE level=VALUES(level), exp=VALUES(exp)");
            $ins->bind_param('isis', $user_id, $s_id, $s_lvl, $s_exp);
            $ins->execute();
            $ins->close();
        }
    }
    
    $is_win = ($run['state'] !== 'retreat' && $run['hp'] > 0);
    if ($is_win && $target_floor > $user['max_floor']) {
        $stmt = $conn->prepare("UPDATE users SET max_floor=? WHERE id=?");
        $stmt->bind_param('ii', $target_floor, $user_id);
        $stmt->execute(); $stmt->close();
    }
    // 死亡不給獎勵；勝利與撤退才寫入，且確保 gold 非負
    if ($run['state'] !== 'dead') {
        $safe_gold = max(0, $f_gold);
        $stmt = $conn->prepare("UPDATE users SET gold=gold+?, exp=exp+? WHERE id=?");
        $stmt->bind_param('iii', $safe_gold, $f_exp, $user_id);
        $stmt->execute(); $stmt->close();
    }

    $result_type = $is_win ? 'win' : ($run['state'] === 'retreat' ? 'escape' : 'lose');
    $stmt = $conn->prepare("INSERT INTO battle_logs (user_id, floor, result, exp_gained, gold_gained) VALUES (?,?,?,?,?)");
    $stmt->bind_param('iisii', $user_id, $target_floor, $result_type, $f_exp, $f_gold);
    $stmt->execute(); $stmt->close();

    $ms = (int)((microtime(true) - $t_start) * 1000);
    log_api($conn, 'tower', $result_type, $user_id, 'success', $ms, [
        'floor' => $target_floor,
        'nodes' => $run['node'],
        'mode'  => ($_SESSION['auto_settings']['mode'] ?? 'manual'),
    ], [
        'gold' => $f_gold,
        'exp'  => $f_exp,
        'hp_remaining' => $run['hp'],
    ]);

    // 失敗時設定 1 小時冷卻（撤退不算失敗）
    if ($run['state'] === 'dead') {
        $stmt = $conn->prepare("UPDATE users SET tower_fail_until=DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id=?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute(); $stmt->close();
    }

    // 組裝結算 HTML 排版
    if ($run['state'] === 'retreat') {
        $title_msg = "🛡️ 撤退成功！帶走了 $f_gold 金、$f_exp EXP。";
        $title_color = "#ff9800";
    } elseif ($is_win) {
        $title_msg = "🎉 成功突破第 $target_floor 層！";
        $title_color = "#ffca28";
    } else {
        $title_msg = "💀 挑戰失敗結算";
        $title_color = "#f44336";
    }
    
    $end_html = "<h3 style='color: $title_color; margin-top: 0;'>$title_msg</h3>";
    $end_html .= "<p>總計獲得：<span style='color:gold;'>$f_gold 金幣</span>, <span style='color:#64b5f6;'>$f_exp EXP</span>";
    if ($crit_gain_display > 0) $end_html .= "<br><span style='color:#ce93d8;'>💥 爆擊熟練度 +$crit_gain_display</span>";
    if ($dodge_gain_display > 0) $end_html .= "<br><span style='color:#81c784;'>🍃 閃避熟練度 +$dodge_gain_display</span>";
    $end_html .= "</p>";
    
    $new_log .= "<div class='node-box success-box reveal-item hidden-item' data-delay='1000'>$end_html<a href='index.php' class='back-btn'>⬅ 結算並返回城鎮</a></div>";
    
    unset($_SESSION['run']); 
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>探索第 <?php echo $target_floor; ?> 層</title>
    <meta charset="utf-8">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; padding: 20px; background-color: #1e1e24; color: #e0e0e0; margin: 0; }
        .tower-container { max-width: 700px; margin: 0 auto; padding-bottom: 80px; }
        .node-box { background: #2b2b36; border: 1px solid #3f3f4e; padding: 20px; margin-bottom: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.2); }
        .node-title { margin-top: 0; color: #9e9e9e; border-bottom: 1px solid #3f3f4e; padding-bottom: 8px;}
        .combat-log { background: #1a1a20; padding: 10px; border-radius: 6px; font-family: monospace; font-size: 14px; color: #bbb; line-height: 1.8; margin: 10px 0; border-left: 3px solid #64b5f6;}
        .success-box { border: 2px solid #ffca28; background: #332d18; text-align: center; }
        .back-btn { display: inline-block; background: #4caf50; color: white; text-decoration: none; padding: 12px 20px; border-radius: 6px; font-weight: bold; margin-top: 10px;}
        .btn-action { color: white; border: none; padding: 10px 15px; font-size: 14px; border-radius: 6px; cursor: pointer; font-weight: bold; }
        .hidden-item { display: none; }
        .page-body { padding-left: 0 !important; }
        .tower-stats-panel {
            position: fixed; right: 20px; top: 50%; transform: translateY(-50%);
            background: #2b2b36; border: 1px solid #3f3f4e; border-radius: 12px;
            padding: 16px; width: 158px; box-shadow: 0 4px 20px rgba(0,0,0,.5); z-index: 100;
            font-family: 'Segoe UI', sans-serif;
        }
        @media (max-width: 960px) { .tower-stats-panel { display: none; } }
    </style>
</head>
<body>
<?php if (isset($_SESSION['run'])): ?>
<script>
let stayingInTower = false;
document.addEventListener('submit', function() { stayingInTower = true; });
window.addEventListener('beforeunload', function(e) {
    if (!stayingInTower) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        navigator.sendBeacon('api/tower_forfeit.php', new URLSearchParams({csrf_token: csrfToken}));
        e.preventDefault();
        e.returnValue = '';
    }
});
</script>
<?php endif; ?>
<?php
$panel_hp     = max(0, (int)($run['hp'] ?? 0));
$panel_max_hp = max(1, (int)$_eff['hp']['value'] + (int)($run['buffs']['max_hp'] ?? 0));
$panel_atk    = (int)$_eff['atk']['value'] + (int)($run['buffs']['dmg'] ?? 0);
$panel_def    = (int)$_eff['def']['value'] + (int)($run['buffs']['def'] ?? 0);
$hp_pct       = min(100, (int)round($panel_hp / $panel_max_hp * 100));
$hp_color     = $hp_pct > 60 ? '#4caf50' : ($hp_pct > 30 ? '#ff9800' : '#f44336');
$run_node     = min(30, max(1, (int)($run['node'] ?? 1)));
$run_gold_now = (int)($run['gold'] ?? 0);
$buf_dmg      = (int)($run['buffs']['dmg'] ?? 0);
$buf_def      = (int)($run['buffs']['def'] ?? 0);
?>
<div class="tower-stats-panel">
    <div style="text-align:center;margin-bottom:12px;padding-bottom:10px;border-bottom:1px solid #3f3f4e;">
        <div style="font-size:13px;font-weight:700;color:#e0e0e0;"><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></div>
        <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Lv.<?= (int)$user['level'] ?> ／ 第 <?= $target_floor ?> 層</div>
    </div>
    <div style="margin-bottom:12px;">
        <div style="font-size:10px;color:#94a3b8;margin-bottom:4px;">❤️ HP</div>
        <div style="background:#1a1a20;border-radius:4px;height:7px;overflow:hidden;">
            <div id="sp-hp-bar" style="width:<?= $hp_pct ?>%;height:100%;background:<?= $hp_color ?>;transition:width .3s,background .3s;"></div>
        </div>
        <div id="sp-hp-txt" style="font-size:11px;color:#aaa;margin-top:3px;text-align:right;"><?= $panel_hp ?> / <?= $panel_max_hp ?></div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:12px;">
        <div style="background:#1a1a20;border-radius:6px;padding:7px;text-align:center;">
            <div style="font-size:9px;color:#94a3b8;">⚔️ ATK</div>
            <div id="sp-atk" style="font-size:15px;font-weight:700;color:#ff9800;"><?= $panel_atk ?></div>
            <?php if ($buf_dmg > 0): ?><div style="font-size:9px;color:#4caf50;">+<?= $buf_dmg ?></div><?php endif; ?>
        </div>
        <div style="background:#1a1a20;border-radius:6px;padding:7px;text-align:center;">
            <div style="font-size:9px;color:#94a3b8;">🛡️ DEF</div>
            <div id="sp-def" style="font-size:15px;font-weight:700;color:#64b5f6;"><?= $panel_def ?></div>
            <?php if ($buf_def > 0): ?><div style="font-size:9px;color:#4caf50;">+<?= $buf_def ?></div><?php endif; ?>
        </div>
    </div>
    <div style="font-size:11px;color:#94a3b8;border-top:1px solid #3f3f4e;padding-top:10px;">
        <div style="display:flex;justify-content:space-between;margin-bottom:5px;">
            <span>📍 節點</span><span id="sp-node" style="color:#e0e0e0;"><?= $run_node ?> / 30</span>
        </div>
        <div style="display:flex;justify-content:space-between;">
            <span>💰 本局</span><span id="sp-gold" style="color:gold;"><?= $run_gold_now ?> 金</span>
        </div>
    </div>
</div>
<div class="page-body">
<div class="tower-container">
    <h2>⚔️ 探索第 <?php echo $target_floor; ?> 層</h2>
    <?php echo $old_log; echo $new_log; ?>
</div>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // 【核心修改】一載入就瞬間移動到最底下，避免看到頂端造成頭暈
    window.scrollTo(0, document.body.scrollHeight);

    const items = document.querySelectorAll('.hidden-item');
    let currentIndex = 0;
    
    function updateStats(el) {
        const hp   = Math.max(0, parseInt(el.dataset.hp)   || 0);
        const mhp  = Math.max(1, parseInt(el.dataset.mhp)  || 1);
        const atk  = parseInt(el.dataset.atk)  || 0;
        const def  = parseInt(el.dataset.def)  || 0;
        const gold = parseInt(el.dataset.gold) || 0;
        const node = parseInt(el.dataset.node) || 0;
        const pct  = Math.min(100, Math.round(hp / mhp * 100));
        const col  = pct > 60 ? '#4caf50' : (pct > 30 ? '#ff9800' : '#f44336');

        const bar = document.getElementById('sp-hp-bar');
        if (bar) { bar.style.width = pct + '%'; bar.style.background = col; }
        const txt = document.getElementById('sp-hp-txt');
        if (txt) txt.textContent = hp + ' / ' + mhp;
        const atkEl = document.getElementById('sp-atk');
        if (atkEl) atkEl.textContent = atk;
        const defEl = document.getElementById('sp-def');
        if (defEl) defEl.textContent = def;
        const goldEl = document.getElementById('sp-gold');
        if (goldEl) goldEl.textContent = gold + ' 金';
        const nodeEl = document.getElementById('sp-node');
        if (nodeEl) nodeEl.textContent = node + ' / 30';
    }

    function revealNext() {
        if (currentIndex < items.length) {
            let el = items[currentIndex];
            el.classList.remove('hidden-item');
            currentIndex++;

            if (el.classList.contains('stat-snap')) {
                updateStats(el);
                setTimeout(revealNext, 1);
                return;
            }

            window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
            let delay = parseInt(el.getAttribute('data-delay')) || 1000;
            setTimeout(revealNext, delay);
        }
    }

    if(items.length > 0) setTimeout(revealNext, 250);
});
</script>
</div><!-- /page-body -->
</body>
</html>