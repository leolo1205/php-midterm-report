<?php
/**
 * 共用函式庫
 * 包含：怪物生成、傷害計算、訓練邏輯、升級處理、API 記錄
 */

// ════════════════════════════════════════
//  怪物生成
// ════════════════════════════════════════

/**
 * 依樓層取得怪物與 BOSS 基本資料
 */
function generate_monster($conn, $floor, $type = 'mob') {
    $mob_level  = $floor;
    $boss_level = $floor + 2;

    $mob_names  = ['哥布林','骷髏兵','蝙蝠怪','石像鬼','暗影狼','毒蜥蜴','冰雪精靈','熔岩魔','死靈法師','惡魔騎士'];
    $boss_names = ['哥布林王','死靈巫師','冰霜龍','熔岩魔王','黑暗領主','野豬王','深淵使者','混沌巨人','時空裂縫','終焉之神'];
    $mob_name   = $mob_names[($floor - 1) % count($mob_names)];
    $boss_name  = $boss_names[($floor - 1) % count($boss_names)];

    $is_special = ($floor % 5 === 0);
    $special_data = null;
    if ($is_special) {
        $special_data = [
            'id'       => 'boar_king',
            'hp_mult'  => 2.5,
            'dmg_mult' => 1.3,
            'def_mult' => 1.2,
            'base_crit'  => 15,
            'base_dodge' => 5,
        ];
    }

    $target = ($type === 'boss') ? $boss_level : $mob_level;
    $row = $conn->query("SELECT * FROM monster_stats WHERE level=$target")->fetch_assoc();
    if (!$row) {
        $row = [
            'level' => $target,
            'hp'    => $target * 100,
            'dmg'   => $target * 10,
            'def'   => (int)($target * 1.5),
            'exp'   => $target * 40,
            'gold'  => $target * 30,
        ];
    }

    return [
        'floor'       => $floor,
        'type'        => $type,
        'mob_level'   => $mob_level,
        'boss_level'  => $boss_level,
        'mob_name'    => $mob_name,
        'boss_name'   => $boss_name,
        'is_special'  => $is_special,
        'special_data'=> $special_data,
        'stats'       => $row,
    ];
}

/**
 * 取得怪物等級對應的完整屬性
 */
function get_monster_stats($conn, $level) {
    $row = $conn->query("SELECT * FROM monster_stats WHERE level=$level")->fetch_assoc();
    return $row ?: [
        'level' => $level,
        'hp'    => $level * 100,
        'dmg'   => $level * 10,
        'def'   => (int)($level * 1.5),
        'exp'   => $level * 40,
        'gold'  => $level * 30,
    ];
}

// ════════════════════════════════════════
//  傷害計算
// ════════════════════════════════════════

/**
 * 計算單次攻擊結果（純函式，不操作 DB）
 * @return array ['hit','dodged','crit','raw_atk','damage']
 */
function calculate_damage($atk, $def, $crit_rate = 10, $dodge_rate = 10) {
    if (rand(1, 100) <= $dodge_rate) {
        return ['hit' => false, 'dodged' => true, 'crit' => false, 'raw_atk' => 0, 'damage' => 0];
    }
    $crit    = (rand(1, 100) <= $crit_rate);
    $raw_atk = $crit ? (int)floor($atk * 1.5) : $atk;
    $damage  = max(1, $raw_atk - $def);
    return ['hit' => true, 'dodged' => false, 'crit' => $crit, 'raw_atk' => $raw_atk, 'damage' => $damage];
}

/**
 * 計算防禦姿態下的減傷結果
 * 防禦姿態：防禦力暫時 x2，承受傷害減半（最少 1）
 */
function calculate_defense_stance($atk, $def, $crit_rate = 10, $dodge_rate = 10) {
    $result = calculate_damage($atk, $def * 2, $crit_rate, $dodge_rate);
    $result['damage'] = max(1, (int)floor($result['damage'] * 0.5));
    $result['stance'] = 'defense';
    return $result;
}

/**
 * 計算逃跑成功機率並回傳結果
 * 基礎成功率 40%，每有 5 點閃避熟練度 +10%，上限 80%
 */
function calculate_escape($dodge_level = 0) {
    $rate    = min(80, 40 + $dodge_level * 10);
    $success = (rand(1, 100) <= $rate);
    return ['success' => $success, 'rate' => $rate];
}

// ════════════════════════════════════════
//  訓練邏輯
// ════════════════════════════════════════

define('TRAIN_COOLDOWN_SEC', 10);
define('TRAIN_EXP_REWARD',   50);
define('TRAIN_STAT_REWARD',   1);

/**
 * 查詢訓練冷卻狀態
 * @return array ['can_claim','seconds_remaining','is_training']
 */
function check_training_cooldown($conn, $user_id) {
    $row = $conn->query("SELECT last_train_time FROM users WHERE id=$user_id")->fetch_assoc();
    if (!$row || !$row['last_train_time']) {
        return ['can_claim' => false, 'seconds_remaining' => 0, 'is_training' => false];
    }
    $elapsed = time() - strtotime($row['last_train_time']);
    $remaining = max(0, TRAIN_COOLDOWN_SEC - $elapsed);
    return [
        'can_claim'         => ($elapsed >= TRAIN_COOLDOWN_SEC),
        'seconds_remaining' => $remaining,
        'is_training'       => true,
    ];
}

/**
 * 開始訓練
 */
function start_training($conn, $user_id) {
    $conn->query("UPDATE users SET last_train_time=NOW() WHERE id=$user_id");
    return ['started' => true, 'cooldown' => TRAIN_COOLDOWN_SEC];
}

/**
 * 領取訓練獎勵（含驗證冷卻）
 * @return array ['success','exp_gained','stat_gained','message']
 */
function claim_training_reward($conn, $user_id) {
    $status = check_training_cooldown($conn, $user_id);
    if (!$status['is_training']) {
        return ['success' => false, 'message' => '尚未開始訓練'];
    }
    if (!$status['can_claim']) {
        return ['success' => false, 'message' => "冷卻中，剩餘 {$status['seconds_remaining']} 秒", 'seconds_remaining' => $status['seconds_remaining']];
    }
    $conn->query("UPDATE users SET last_train_time=NULL, exp=exp+".TRAIN_EXP_REWARD.", stat_points=stat_points+".TRAIN_STAT_REWARD." WHERE id=$user_id");
    $conn->query("INSERT INTO training_logs (user_id,exp_gained,stat_points_gained) VALUES ($user_id,".TRAIN_EXP_REWARD.",".TRAIN_STAT_REWARD.")");
    return ['success' => true, 'exp_gained' => TRAIN_EXP_REWARD, 'stat_gained' => TRAIN_STAT_REWARD, 'message' => '訓練完成！'];
}

// ════════════════════════════════════════
//  升級處理
// ════════════════════════════════════════

/**
 * 處理升級（可連續升多級）
 * @return array ['leveled_up','levels_gained','new_level','new_exp']
 */
function process_levelup($conn, $user_id) {
    $user = $conn->query("SELECT level,exp,hp,max_hp FROM users WHERE id=$user_id")->fetch_assoc();
    $lvl = (int)$user['level'];
    $exp = (int)$user['exp'];
    $gained = 0;
    $hp_add = 0; $dmg_add = 0; $def_add = 0;

    while ($exp >= $lvl * 100) {
        $exp    -= $lvl * 100;
        $lvl++;
        $gained++;
        $hp_add  += 10;
        $dmg_add += 3;
        $def_add += 1;
    }

    if ($gained > 0) {
        $new_max_hp = $user['max_hp'] + $hp_add;
        $conn->query("UPDATE users SET
            level=$lvl, exp=$exp,
            max_hp=$new_max_hp, hp=$new_max_hp,
            dmg=dmg+$dmg_add, def=def+$def_add
            WHERE id=$user_id");
    }

    return [
        'leveled_up'   => ($gained > 0),
        'levels_gained'=> $gained,
        'new_level'    => $lvl,
        'new_exp'      => $exp,
    ];
}

/**
 * 計算升到指定等級所需的累計 EXP
 */
function exp_needed_for_level($level) {
    $total = 0;
    for ($i = 1; $i < $level; $i++) $total += $i * 100;
    return $total;
}

// ════════════════════════════════════════
//  API 記錄
// ════════════════════════════════════════

/**
 * 寫入 API 呼叫記錄
 */
function log_api($conn, $api_name, $action, $user_id, $status, $response_ms, $request_data = [], $response_data = []) {
    $req = $conn->real_escape_string(json_encode($request_data,   JSON_UNESCAPED_UNICODE));
    $res = $conn->real_escape_string(json_encode($response_data,  JSON_UNESCAPED_UNICODE));
    $uid = $user_id ? (int)$user_id : 'NULL';
    $api = $conn->real_escape_string($api_name);
    $act = $conn->real_escape_string($action);
    $sts = ($status === 'success') ? 'success' : 'fail';
    $ms  = (int)$response_ms;
    $conn->query("INSERT INTO api_logs (api_name,action,user_id,status,response_ms,request_data,response_data)
                  VALUES ('$api','$act',$uid,'$sts',$ms,'$req','$res')");
}
