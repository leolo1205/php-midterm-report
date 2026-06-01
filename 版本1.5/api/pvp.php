<?php
header('Content-Type: application/json; charset=utf-8');
$t_start = microtime(true);
require_once '../db.php';
require_once '../lib/session.php';
require_once '../lib/functions.php';

$user_id = get_player_id();
if (!$user_id) { echo json_encode(['success'=>false,'message'=>'未登入']); exit; }

$action = trim($_REQUEST['action'] ?? '');
$result = [];

try {
    switch ($action) {

        case 'get_status':
            ensure_pvp_ranking($conn, $user_id);
            $my = $conn->query("SELECT r.*,u.username,u.level FROM pvp_rankings r JOIN users u ON r.user_id=u.id WHERE r.user_id=$user_id")->fetch_assoc();
            $rank_row = $conn->query("SELECT COUNT(*)+1 AS r FROM pvp_rankings WHERE rating>(SELECT rating FROM pvp_rankings WHERE user_id=$user_id)")->fetch_assoc();
            $cd = (int)$conn->query("SELECT GREATEST(0, 60 - TIMESTAMPDIFF(SECOND, last_challenge, NOW())) FROM pvp_rankings WHERE user_id=$user_id")->fetch_row()[0];
            $result = ['success'=>true, 'my'=>$my, 'rank'=>(int)$rank_row['r'], 'cooldown'=>$cd];
            break;

        case 'get_rankings':
            $rows = [];
            $res = $conn->query("SELECT r.user_id,r.rating,r.wins,r.losses,r.streak,u.username,u.level FROM pvp_rankings r JOIN users u ON r.user_id=u.id ORDER BY r.rating DESC LIMIT 20");
            while ($r = $res->fetch_assoc()) $rows[] = $r;
            $result = ['success'=>true, 'rankings'=>$rows];
            break;

        case 'get_opponents':
            ensure_pvp_ranking($conn, $user_id);
            $rows = [];
            $res = $conn->query("SELECT r.user_id,r.rating,r.wins,r.losses,u.username,u.level FROM pvp_rankings r JOIN users u ON r.user_id=u.id WHERE r.user_id!=$user_id ORDER BY ABS(r.rating-(SELECT rating FROM pvp_rankings WHERE user_id=$user_id)) ASC LIMIT 10");
            while ($r = $res->fetch_assoc()) $rows[] = $r;
            $result = ['success'=>true, 'opponents'=>$rows];
            break;

        case 'challenge':
            $defender_id = (int)($_REQUEST['defender_id'] ?? 0);
            if (!$defender_id) { $result=['success'=>false,'message'=>'請指定對手']; break; }
            $result = do_pvp_challenge($conn, $user_id, $defender_id);
            break;

        case 'get_battle':
            $bid = (int)($_REQUEST['battle_id'] ?? 0);
            $row = $conn->query("SELECT battle_log,winner_id FROM pvp_battles WHERE id=$bid AND (challenger_id=$user_id OR defender_id=$user_id)")->fetch_assoc();
            if (!$row) { $result=['success'=>false,'message'=>'找不到該場對戰']; break; }
            $result = ['success'=>true,'log'=>json_decode($row['battle_log'],true),'winner_id'=>(int)$row['winner_id']];
            break;

        case 'get_history':
            $rows = [];
            $res = $conn->query("SELECT b.*,uc.username AS challenger_name,ud.username AS defender_name,uw.username AS winner_name FROM pvp_battles b JOIN users uc ON b.challenger_id=uc.id JOIN users ud ON b.defender_id=ud.id JOIN users uw ON b.winner_id=uw.id WHERE b.challenger_id=$user_id OR b.defender_id=$user_id ORDER BY b.created_at DESC LIMIT 20");
            while ($r = $res->fetch_assoc()) { unset($r['battle_log']); $rows[] = $r; }
            $result = ['success'=>true, 'history'=>$rows];
            break;

        default:
            $result = ['success'=>false,'message'=>'未知的 action'];
    }
} catch (Exception $e) {
    $result = ['success'=>false,'message'=>'伺服器錯誤：'.$e->getMessage()];
}

$result['_ms'] = (int)((microtime(true)-$t_start)*1000);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
