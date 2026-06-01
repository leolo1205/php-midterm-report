<?php
session_start();
date_default_timezone_set('Asia/Taipei');
require 'db.php';
require_once 'lib/functions.php';
require 'tower_story.php';
require 'tower_monsters.php';

if (!isset($_SESSION['player_id'])) { header('Location: login.php'); exit; }
$user_id = (int)$_SESSION['player_id'];
$result = $conn->query("SELECT * FROM users WHERE id = $user_id");
$user = $result->fetch_assoc();

$monster_db = [];
$mob_res = $conn->query("SELECT * FROM monster_stats");
if ($mob_res) { while ($row = $mob_res->fetch_assoc()) { $monster_db[$row['level']] = $row; } }

// 初始化樓層
if (isset($_GET['floor'])) {
    $target_floor = (int)$_GET['floor'];
    if ($target_floor < 1 || $target_floor > 20 || $target_floor > $user['max_floor'] + 1) {
        die("<h2 style='color:white; text-align:center;'>領域展開失敗：未解鎖！<br><a href='index.php'>⬅ 返回</a></h2>");
    }
    // 儲存模式設定
    $mode = ($_GET['mode'] ?? 'manual') === 'auto' ? 'auto' : 'manual';
    $_SESSION['auto_settings'] = [
        'mode'       => $mode,
        'merchant'   => $_GET['merchant']   ?? 'merch_leave',
        'buy_exp'    => $_GET['buy_exp']    ?? 'exp_no',
        'retreat_hp' => (int)($_GET['retreat_hp'] ?? 0),
    ];
    $_SESSION['run'] = ['floor' => $target_floor, 'node' => 1, 'hp' => $user['max_hp'], 'gold' => 0, 'exp' => 0, 'buffs' => ['dmg'=>0, 'def'=>0, 'max_hp'=>0], 'skill_gains' => [], 'log' => '', 'state' => 'auto'];
    header("Location: tower.php"); exit;
}
if (!isset($_SESSION['run'])) { header("Location: index.php"); exit; }

$run = &$_SESSION['run'];
$target_floor = $run['floor'];
$story_nodes = [5, 10, 15, 20, 25, 29];

// 讀取技能
$crit_lvl = 0; $dodge_lvl = 0;
$skill_res = $conn->query("SELECT skill_id, level FROM user_skills WHERE user_id = $user_id");
if ($skill_res) { while($row = $skill_res->fetch_assoc()) { if ($row['skill_id'] === 'crit') $crit_lvl = $row['level']; if ($row['skill_id'] === 'dodge') $dodge_lvl = $row['level']; } }
$p_crit_rate = 10 + $crit_lvl; $p_dodge_rate = 10 + $dodge_lvl; 

$new_log = ""; $old_log = $run['log']; 

// 處理 POST 抉擇
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
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
                $dmg = floor($user['max_hp'] * 0.3);
                $run['hp'] -= $dmg;
                $add_post_line("<p style='color:#f44336;'>💀 箱子爆炸，受傷 {$dmg}！</p>");
            }
            $run['state'] = ($run['hp'] > 0) ? 'auto' : 'dead';
        }
    } elseif ($run['state'] === 'wait_exp') {
        if ($action === 'exp_yes' && ($user['gold'] + $run['gold']) >= (5 * $target_floor)) {
            $run['gold'] -= (5 * $target_floor);
            $run['exp'] += (10 * $target_floor);
            $add_post_line("<p style='color:#64b5f6;'>✨ 交易成功！獲得 EXP！</p>");
        } else {
            $add_post_line("<p>你繼續前進。</p>");
        }
        $run['state'] = 'auto';
    }
    $post_new .= "</div>"; $post_old .= "</div>"; $new_log .= $post_new; $run['log'] .= $post_old; $run['node']++; 
}

// 自動模式設定
$auto = $_SESSION['auto_settings'] ?? ['merchant'=>'merch_leave','buy_exp'=>'exp_no','retreat_hp'=>0];

// 事件自動推動迴圈
while ($run['state'] === 'auto' && $run['node'] <= 30) {

    // HP 緊急撤退判定
    if ($auto['retreat_hp'] > 0) {
        $max_hp_now = $user['max_hp'] + $run['buffs']['max_hp'];
        $hp_pct = $max_hp_now > 0 ? ($run['hp'] / $max_hp_now * 100) : 100;
        if ($hp_pct <= $auto['retreat_hp']) {
            $retreat_msg = "<p style='color:#ff9800;font-weight:bold;'>🩸 HP 過低（" . round($hp_pct) . "%），自動撤退！</p>";
            $node_new = "<div class='node-box reveal-item hidden-item' data-delay='150'>$retreat_msg</div>";
            $node_old = "<div class='node-box'>$retreat_msg</div>";
            $new_log .= $node_new; $run['log'] .= $node_old;
            $run['state'] = 'retreat';
            break;
        }
    }
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
                            $dmg = floor($user['max_hp'] * 0.3);
                            $run['hp'] -= $dmg;
                            $node_new .= "<div class='reveal-item hidden-item' data-delay='600'><p style='color:#f44336;'>💀 箱子爆炸，受傷 {$dmg}！</p></div>";
                            $node_old .= "<div><p style='color:#f44336;'>💀 箱子爆炸，受傷 {$dmg}！</p></div>";
                        }
                        $run['state'] = ($run['hp'] > 0) ? 'auto' : 'dead';
                    }
                    $stop_loop = false;
                } elseif ($run['state'] === 'wait_exp') {
                    $cost = 5 * $target_floor; $gain = 10 * $target_floor;
                    if ($auto['buy_exp'] === 'exp_yes' && ($user['gold'] + $run['gold']) >= $cost) {
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
    }

    $new_log .= $node_new . "</div>"; $run['log'] .= $node_old . "</div>";
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
            if ($s_id === 'crit') $crit_gain_display = $gained_exp;
            if ($s_id === 'dodge') $dodge_gain_display = $gained_exp;
            
            $row = $conn->query("SELECT level, exp FROM user_skills WHERE user_id=$user_id AND skill_id='$s_id'")->fetch_assoc();
            $s_lvl = $row['level'] ?? 0; 
            $s_exp = ($row['exp'] ?? 0) + $gained_exp;
            
            while ($s_exp >= ($s_lvl + 1) * 10) { 
                $s_exp -= ($s_lvl + 1) * 10; 
                $s_lvl++; 
            }
            $conn->query("INSERT INTO user_skills (user_id, skill_id, level, exp) VALUES ($user_id, '$s_id', $s_lvl, $s_exp) ON DUPLICATE KEY UPDATE level = $s_lvl, exp = $s_exp");
        }
    }
    
    $is_win = ($run['hp'] > 0);
    if ($is_win && $target_floor > $user['max_floor']) {
        $conn->query("UPDATE users SET max_floor = $target_floor WHERE id = $user_id");
    }
    $conn->query("UPDATE users SET gold = gold + $f_gold, exp = exp + $f_exp WHERE id = $user_id");
    $result_type = $is_win ? 'win' : 'lose';
    $conn->query("INSERT INTO battle_logs (user_id, floor, result, exp_gained, gold_gained) VALUES ($user_id, $target_floor, '$result_type', $f_exp, $f_gold)");

    // 組裝結算 HTML 排版
    $title_msg = $is_win ? "🎉 成功突破第 $target_floor 層！" : "💀 挑戰失敗結算";
    $title_color = $is_win ? "#ffca28" : "#f44336";
    
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
    </style>
</head>
<body>
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
    
    function revealNext() {
        if (currentIndex < items.length) {
            let el = items[currentIndex];
            el.classList.remove('hidden-item');
            
            // 只有在新事件逐一顯示時，才使用平滑滾動
            window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
            
            let delay = parseInt(el.getAttribute('data-delay')) || 1000;
            currentIndex++;
            setTimeout(revealNext, delay);
        }
    }
    
    if(items.length > 0) setTimeout(revealNext, 250);
});
</script>
</body>
</html>