<?php
/**
 * 訓練 API
 * 回傳格式：JSON
 * 支援 actions：cooldown_check / start_train / claim_reward / add_stat
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

try {
    switch ($action) {

        // ── 冷卻判定 ──
        case 'cooldown_check':
            $cooldown = check_training_cooldown($conn, $user_id);
            $user = $conn->query("SELECT level, exp, stat_points, last_train_time FROM users WHERE id=$user_id")->fetch_assoc();
            $result = [
                'success'           => true,
                'is_training'       => $cooldown['is_training'],
                'can_claim'         => $cooldown['can_claim'],
                'seconds_remaining' => $cooldown['seconds_remaining'],
                'cooldown_total'    => TRAIN_COOLDOWN_SEC,
                'stat_points'       => (int)$user['stat_points'],
                'exp'               => (int)$user['exp'],
                'level'             => (int)$user['level'],
                'exp_needed'        => $user['level'] * 100,
            ];
            break;

        // ── 開始訓練 ──
        case 'start_train':
            $cooldown = check_training_cooldown($conn, $user_id);
            if ($cooldown['is_training']) {
                $result = ['success' => false, 'message' => '訓練已在進行中'];
                $status = 'fail';
                break;
            }
            $r = start_training($conn, $user_id);
            $result = ['success' => true, 'message' => '訓練開始！', 'cooldown' => $r['cooldown']];
            break;

        // ── 屬性提升（領取獎勵） ──
        case 'claim_reward':
            $r = claim_training_reward($conn, $user_id);
            if ($r['success']) {
                $lv = process_levelup($conn, $user_id);
                $r['leveled_up']    = $lv['leveled_up'];
                $r['new_level']     = $lv['new_level'];
                $r['levels_gained'] = $lv['levels_gained'];
            }
            $result = $r;
            if (!$r['success']) $status = 'fail';
            break;

        // ── 屬性配點 ──
        case 'add_stat':
            $stat = $_REQUEST['stat'] ?? '';
            $user = $conn->query("SELECT stat_points FROM users WHERE id=$user_id")->fetch_assoc();
            if ((int)$user['stat_points'] <= 0) {
                $result = ['success' => false, 'message' => '沒有可用的屬性點']; $status = 'fail'; break;
            }
            $map = ['dmg' => 'dmg=dmg+3', 'hp' => 'max_hp=max_hp+10,hp=hp+10', 'def' => 'def=def+1'];
            if (!isset($map[$stat])) {
                $result = ['success' => false, 'message' => '無效的屬性類型']; $status = 'fail'; break;
            }
            $conn->query("UPDATE users SET {$map[$stat]}, stat_points=stat_points-1 WHERE id=$user_id");
            $gains = ['dmg' => '傷害 +3', 'hp' => 'HP上限 +10', 'def' => '防禦 +1'];
            $result = ['success' => true, 'message' => "分配完成：{$gains[$stat]}", 'stat' => $stat];
            break;

        default:
            $result = ['success' => false, 'message' => '未知的 action', 'code' => 400];
            $status = 'fail';
    }
} catch (Exception $e) {
    $result = ['success' => false, 'message' => '伺服器錯誤：' . $e->getMessage()];
    $status = 'fail';
}

$ms = (int)(( microtime(true) - $t_start ) * 1000);
log_api($conn, 'train', $action, $user_id, $status, $ms, ['action' => $action], $result);
$result['_ms'] = $ms;

echo json_encode($result, JSON_UNESCAPED_UNICODE);
