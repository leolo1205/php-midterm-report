<?php
session_start();
require '../db.php';
require_once '../lib/functions.php';
require '../lib/session.php';

header('Content-Type: application/json; charset=utf-8');
$t0 = microtime(true);

$user_id = get_player_id();
if (!$user_id) {
    echo json_encode(['success'=>false,'message'=>'未登入']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'choose_archetype') {
    $archetype = $_POST['archetype'] ?? '';
    $result = choose_archetype($conn, $user_id, $archetype);
    log_api($conn,'skills','choose_archetype',$user_id,$result['success']?'success':'fail',
        (int)((microtime(true)-$t0)*1000),['archetype'=>$archetype],$result);
    echo json_encode($result);
    exit;
}

if ($action === 'unlock_node') {
    $result = unlock_node($conn, $user_id);
    log_api($conn,'skills','unlock_node',$user_id,$result['success']?'success':'fail',
        (int)((microtime(true)-$t0)*1000),[],$result);
    echo json_encode($result);
    exit;
}

echo json_encode(['success'=>false,'message'=>'未知操作']);
