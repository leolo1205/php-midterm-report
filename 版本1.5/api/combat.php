<?php
/**
 * 戰鬥 API
 * 回傳格式：JSON
 * 支援 actions：normal_attack / defense_stance / try_escape / victory_settle / defeat_settle
 */
header('Content-Type: application/json; charset=utf-8');
$t_start = microtime(true);

require_once '../db.php';
require_once '../lib/session.php';
require_once '../lib/functions.php';

$user_id = get_player_id();
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => '未登入', 'code' => 401]);
    exit;
}

$action = trim($_REQUEST['action'] ?? '');
$result = [];
$status = 'success';

// 從 request 取得戰鬥參數
$p_atk      = (int)($_REQUEST['p_atk']      ?? 0);
$p_def      = (int)($_REQUEST['p_def']      ?? 0);
$p_crit     = (int)($_REQUEST['p_crit']     ?? 10);
$p_dodge    = (int)($_REQUEST['p_dodge']    ?? 10);
$m_atk      = (int)($_REQUEST['m_atk']      ?? 0);
$m_def      = (int)($_REQUEST['m_def']      ?? 0);
$m_crit     = (int)($_REQUEST['m_crit']     ?? 10);
$m_dodge    = (int)($_REQUEST['m_dodge']    ?? 10);
$floor      = (int)($_REQUEST['floor']      ?? 1);
$exp_gained = (int)($_REQUEST['exp_gained'] ?? 0);
$gold_gained= (int)($_REQUEST['gold_gained']?? 0);

try {
    switch ($action) {

        // ── 普通攻擊（玩家攻擊怪物）──
        case 'normal_attack':
            $hit = calculate_damage($p_atk, $m_def, $p_crit, $m_dodge);
            $result = [
                'success'  => true,
                'attacker' => 'player',
                'hit'      => $hit['hit'],
                'dodged'   => $hit['dodged'],
                'crit'     => $hit['crit'],
                'damage'   => $hit['damage'],
                'message'  => $hit['dodged'] ? '攻擊被閃避！'
                            : ($hit['crit'] ? "💥 爆擊！造成 {$hit['damage']} 傷害" : "造成 {$hit['damage']} 傷害"),
            ];
            break;

        // ── 防禦姿態（玩家承受攻擊時的防禦模式）──
        case 'defense_stance':
            $hit = calculate_defense_stance($m_atk, $p_def, $m_crit, $p_dodge);
            $result = [
                'success'  => true,
                'attacker' => 'monster',
                'stance'   => 'defense',
                'hit'      => $hit['hit'],
                'dodged'   => $hit['dodged'],
                'crit'     => $hit['crit'],
                'damage'   => $hit['damage'],
                'message'  => $hit['dodged'] ? '🛡️ 防禦姿態：完全閃避！'
                            : "🛡️ 防禦姿態：減傷後承受 {$hit['damage']} 傷害",
            ];
            break;

        // ── 嘗試逃跑 ──
        case 'try_escape':
            $dodge_lvl = (int)($_REQUEST['dodge_level'] ?? 0);
            $esc = calculate_escape($dodge_lvl);
            if ($esc['success']) {
                // 逃跑成功，記錄 battle_log
                $conn->query("INSERT INTO battle_logs (user_id,floor,result,exp_gained,gold_gained) VALUES ($user_id,$floor,'escape',0,0)");
            }
            $result = [
                'success'      => true,
                'escaped'      => $esc['success'],
                'escape_rate'  => $esc['rate'],
                'message'      => $esc['success'] ? '🏃 成功逃脫！' : '❌ 逃跑失敗！',
            ];
            break;

        // ── 勝利結算 ──
        case 'victory_settle':
            $crit_exp  = (int)($_REQUEST['crit_exp']  ?? 0);
            $dodge_exp = (int)($_REQUEST['dodge_exp'] ?? 0);

            // 更新金幣與 EXP
            $conn->query("UPDATE users SET gold=gold+$gold_gained, exp=exp+$exp_gained WHERE id=$user_id");
            // 更新最高樓層
            $user = $conn->query("SELECT max_floor FROM users WHERE id=$user_id")->fetch_assoc();
            if ($floor > (int)$user['max_floor']) {
                $conn->query("UPDATE users SET max_floor=$floor WHERE id=$user_id");
            }
            // 寫入戰鬥記錄
            $conn->query("INSERT INTO battle_logs (user_id,floor,result,exp_gained,gold_gained) VALUES ($user_id,$floor,'win',$exp_gained,$gold_gained)");

            // 技能熟練度更新
            $skill_updates = [];
            foreach (['crit' => $crit_exp, 'dodge' => $dodge_exp] as $sid => $gained) {
                if ($gained <= 0) continue;
                $row = $conn->query("SELECT level,exp FROM user_skills WHERE user_id=$user_id AND skill_id='$sid'")->fetch_assoc();
                $slvl = (int)($row['level'] ?? 0);
                $sexp = (int)($row['exp']   ?? 0) + $gained;
                while ($sexp >= ($slvl + 1) * 10) { $sexp -= ($slvl + 1) * 10; $slvl++; }
                $conn->query("INSERT INTO user_skills (user_id,skill_id,level,exp) VALUES ($user_id,'$sid',$slvl,$sexp) ON DUPLICATE KEY UPDATE level=$slvl,exp=$sexp");
                $skill_updates[$sid] = ['level' => $slvl, 'exp' => $sexp];
            }

            // 升級判定
            $lv = process_levelup($conn, $user_id);
            $result = [
                'success'       => true,
                'exp_gained'    => $exp_gained,
                'gold_gained'   => $gold_gained,
                'leveled_up'    => $lv['leveled_up'],
                'new_level'     => $lv['new_level'],
                'levels_gained' => $lv['levels_gained'],
                'skill_updates' => $skill_updates,
                'message'       => "🏆 勝利！獲得 {$exp_gained} EXP 與 {$gold_gained} 金幣",
            ];
            break;

        // ── 敗北結算 ──
        case 'defeat_settle':
            $conn->query("INSERT INTO battle_logs (user_id,floor,result,exp_gained,gold_gained) VALUES ($user_id,$floor,'lose',0,0)");
            // 敗北後 HP 歸 1（不直接死亡）
            $conn->query("UPDATE users SET hp=1 WHERE id=$user_id");
            $result = [
                'success' => true,
                'result'  => 'lose',
                'message' => '💀 你被擊敗了...',
            ];
            break;

        default:
            $result = ['success' => false, 'message' => '未知的 action', 'code' => 400];
            $status = 'fail';
    }
} catch (Exception $e) {
    $result = ['success' => false, 'message' => '伺服器錯誤：' . $e->getMessage()];
    $status = 'fail';
}

$ms = (int)((microtime(true) - $t_start) * 1000);
log_api($conn, 'combat', $action, $user_id, $status, $ms,
    ['action'=>$action,'floor'=>$floor,'p_atk'=>$p_atk,'m_atk'=>$m_atk],
    $result);
$result['_ms'] = $ms;

echo json_encode($result, JSON_UNESCAPED_UNICODE);
