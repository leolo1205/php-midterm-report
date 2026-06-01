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

/**
 * 訓練方案定義
 * duration_sec = 冷卻秒數（點擊後需等多久才能再訓練）
 * exp / stat   = 點擊後立即給予的獎勵
 */
function get_train_plans() {
    return [
        'short'  => ['label' => '10 分鐘', 'duration_sec' =>   600, 'exp' =>   50, 'stat' =>  1],
        'medium' => ['label' => '1 小時',  'duration_sec' =>  3600, 'exp' =>  300, 'stat' =>  3],
        'long'   => ['label' => '8 小時',  'duration_sec' => 28800, 'exp' => 1500, 'stat' => 10],
    ];
}

/**
 * 查詢訓練冷卻狀態
 * @return array ['is_training','seconds_remaining','duration_sec','plan_key']
 */
function check_training_cooldown($conn, $user_id) {
    $row = $conn->query("SELECT last_train_time, train_duration FROM users WHERE id=$user_id")->fetch_assoc();
    if (!$row || !$row['last_train_time']) {
        return ['is_training' => false, 'seconds_remaining' => 0, 'duration_sec' => 0, 'plan_key' => ''];
    }
    $duration  = (int)$row['train_duration'];
    $elapsed   = time() - strtotime($row['last_train_time']);
    $remaining = max(0, $duration - $elapsed);

    // 冷卻已過，自動清除
    if ($elapsed >= $duration) {
        $conn->query("UPDATE users SET last_train_time=NULL, train_duration=0 WHERE id=$user_id");
        return ['is_training' => false, 'seconds_remaining' => 0, 'duration_sec' => 0, 'plan_key' => ''];
    }

    // 反推目前的方案 key
    $plan_key = '';
    foreach (get_train_plans() as $key => $plan) {
        if ($plan['duration_sec'] === $duration) { $plan_key = $key; break; }
    }

    return [
        'is_training'       => true,
        'seconds_remaining' => $remaining,
        'duration_sec'      => $duration,
        'plan_key'          => $plan_key,
    ];
}

/**
 * 開始訓練（立即發獎，啟動冷卻）
 * @param string $plan_key  'short' | 'medium' | 'long'
 * @return array ['success','exp_gained','stat_gained','duration_sec','message']
 */
function start_training($conn, $user_id, $plan_key = 'short') {
    $plans = get_train_plans();
    if (!isset($plans[$plan_key])) $plan_key = 'short';
    $plan = $plans[$plan_key];

    // 檢查是否還在冷卻中
    $status = check_training_cooldown($conn, $user_id);
    if ($status['is_training']) {
        return ['success' => false, 'message' => "訓練冷卻中，剩餘 {$status['seconds_remaining']} 秒"];
    }

    $exp  = $plan['exp'];
    $stat = $plan['stat'];
    $dur  = $plan['duration_sec'];

    // 立即發獎 + 記錄冷卻
    $conn->query("UPDATE users SET last_train_time=NOW(), train_duration=$dur, exp=exp+$exp, stat_points=stat_points+$stat WHERE id=$user_id");
    $conn->query("INSERT INTO training_logs (user_id,exp_gained,stat_points_gained) VALUES ($user_id,$exp,$stat)");

    return [
        'success'      => true,
        'exp_gained'   => $exp,
        'stat_gained'  => $stat,
        'duration_sec' => $dur,
        'label'        => $plan['label'],
        'message'      => "訓練開始！獲得 {$exp} EXP 與 {$stat} 屬性點",
    ];
}

/**
 * 舊版相容：claim_training_reward 不再需要（獎勵改為立即發放）
 * 保留以免其他地方呼叫時報錯
 */
function claim_training_reward($conn, $user_id) {
    return ['success' => false, 'message' => '獎勵已在訓練開始時發放'];
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
//  裝備系統
// ════════════════════════════════════════

/**
 * 升級費用與成功機率表（+0 到 +9，代表升到下一級的成本）
 */
function get_upgrade_table() {
    return [
        0 => ['cost' => 200,   'chance' => 90],
        1 => ['cost' => 400,   'chance' => 80],
        2 => ['cost' => 700,   'chance' => 70],
        3 => ['cost' => 1200,  'chance' => 60],
        4 => ['cost' => 2000,  'chance' => 50],
        5 => ['cost' => 3000,  'chance' => 40],
        6 => ['cost' => 4500,  'chance' => 33],
        7 => ['cost' => 6500,  'chance' => 25],
        8 => ['cost' => 9000,  'chance' => 15],
        9 => ['cost' => 13000, 'chance' =>  8],
    ];
}

/**
 * 裝備加成計算（每級固定值）
 */
function equip_bonus_per_level($type) {
    return ['weapon' => 5, 'armor' => 2, 'helmet' => 20][$type] ?? 0;
}

/**
 * 取得玩家所有裝備狀態，若該裝備不存在自動初始化
 * @return array ['weapon'=>['level'=>...], 'armor'=>..., 'helmet'=>...]
 */
function get_equipment($conn, $user_id) {
    $types  = ['weapon', 'armor', 'helmet'];
    $result = [];
    foreach ($types as $t) {
        $row = $conn->query("SELECT * FROM user_equipment WHERE user_id=$user_id AND equip_type='$t'")->fetch_assoc();
        if (!$row) {
            $conn->query("INSERT INTO user_equipment (user_id,equip_type,level,attempts,successes) VALUES ($user_id,'$t',0,0,0)");
            $row = ['user_id'=>$user_id,'equip_type'=>$t,'level'=>0,'attempts'=>0,'successes'=>0];
        }
        $result[$t] = $row;
    }
    return $result;
}

/**
 * 取得裝備總加成
 * @return array ['atk'=>..., 'def'=>..., 'hp'=>...]
 */
function get_equipment_bonus($conn, $user_id) {
    $eq = get_equipment($conn, $user_id);
    return [
        'atk' => (int)$eq['weapon']['level'] * 5,
        'def' => (int)$eq['armor']['level']  * 2,
        'hp'  => (int)$eq['helmet']['level'] * 20,
    ];
}

/**
 * 嘗試強化裝備
 * @return array ['success','leveled_up','new_level','cost','chance','message']
 */
function upgrade_equipment($conn, $user_id, $type) {
    $types = ['weapon', 'armor', 'helmet'];
    if (!in_array($type, $types)) return ['success' => false, 'message' => '無效的裝備類型'];

    $table  = get_upgrade_table();
    $eq     = get_equipment($conn, $user_id);
    $level  = (int)$eq[$type]['level'];

    if ($level >= 10) return ['success' => false, 'message' => '已達最高等級 +10'];

    $cost   = $table[$level]['cost'];
    $chance = $table[$level]['chance'];

    // 檢查金幣
    $user = $conn->query("SELECT gold FROM users WHERE id=$user_id")->fetch_assoc();
    if ((int)$user['gold'] < $cost) {
        return ['success' => false, 'message' => "金幣不足（需要 {$cost} 金）"];
    }

    // 扣除金幣
    $conn->query("UPDATE users SET gold=gold-$cost WHERE id=$user_id");

    // 判斷成功
    $rolled   = rand(1, 100);
    $leveled  = ($rolled <= $chance);
    $new_level = $leveled ? $level + 1 : $level;

    $conn->query("UPDATE user_equipment SET
        level=$new_level,
        attempts=attempts+1,
        successes=successes+".($leveled?1:0)."
        WHERE user_id=$user_id AND equip_type='$type'");

    $names = ['weapon'=>'武器','armor'=>'護甲','helmet'=>'頭盔'];
    return [
        'success'   => true,
        'leveled_up'=> $leveled,
        'old_level' => $level,
        'new_level' => $new_level,
        'cost'      => $cost,
        'chance'    => $chance,
        'rolled'    => $rolled,
        'message'   => $leveled
            ? "✅ 強化成功！{$names[$type]} +{$level} → +{$new_level}"
            : "❌ 強化失敗！{$names[$type]} 維持 +{$level}（扣除 {$cost} 金）",
    ];
}

// ════════════════════════════════════════
//  PVP 競技場
// ════════════════════════════════════════

/**
 * 確保玩家有 pvp_rankings 紀錄，沒有則自動建立
 */
function ensure_pvp_ranking($conn, $user_id) {
    $r = $conn->query("SELECT user_id FROM pvp_rankings WHERE user_id=$user_id")->fetch_assoc();
    if (!$r) $conn->query("INSERT INTO pvp_rankings (user_id) VALUES ($user_id)");
}

/**
 * 取得玩家完整 PVP 資料（含裝備加成、技能）
 */
function get_pvp_fighter($conn, $user_id) {
    $u  = $conn->query("SELECT id,username,dmg,def,hp,max_hp,level FROM users WHERE id=$user_id")->fetch_assoc();
    $eq = get_equipment_bonus($conn, $user_id);
    $dodge_lvl = 0; $crit_lvl = 0;
    $sr = $conn->query("SELECT skill_id,level FROM user_skills WHERE user_id=$user_id");
    if ($sr) while ($row = $sr->fetch_assoc()) {
        if ($row['skill_id']==='dodge') $dodge_lvl = (int)$row['level'];
        if ($row['skill_id']==='crit')  $crit_lvl  = (int)$row['level'];
    }
    return [
        'id'         => (int)$u['id'],
        'username'   => $u['username'],
        'level'      => (int)$u['level'],
        'hp'         => (int)$u['max_hp'] + $eq['hp'],
        'max_hp'     => (int)$u['max_hp'] + $eq['hp'],
        'atk'        => (int)$u['dmg']    + $eq['atk'],
        'def'        => (int)$u['def']    + $eq['def'],
        'crit_rate'  => 10 + $crit_lvl,
        'dodge_rate' => 10 + $dodge_lvl,
    ];
}

/**
 * 模擬 PVP 戰鬥，回傳完整結果
 */
function simulate_pvp_battle($conn, $challenger_id, $defender_id) {
    $a = get_pvp_fighter($conn, $challenger_id);
    $b = get_pvp_fighter($conn, $defender_id);

    // 先攻判定（閃避率高者先攻，相同則隨機）
    if ($a['dodge_rate'] > $b['dodge_rate'])      $first = 'a';
    elseif ($b['dodge_rate'] > $a['dodge_rate'])  $first = 'b';
    else $first = (rand(0,1) ? 'a' : 'b');

    $log = [];
    $log[] = ['type'=>'system', 'text'=>
        "⚔️ {$a['username']}（Lv.{$a['level']}）VS {$b['username']}（Lv.{$b['level']}）"];
    $log[] = ['type'=>'system', 'text'=>
        ($first==='a' ? "🎯 {$a['username']}" : "🎯 {$b['username']}") . " 閃避率較高，先手出擊！"];

    $round = 0;
    $order = ($first === 'a') ? ['a','b'] : ['b','a'];

    while ($a['hp'] > 0 && $b['hp'] > 0) {
        $round++;
        foreach ($order as $turn) {
            if ($a['hp'] <= 0 || $b['hp'] <= 0) break;
            [$atk_f, $def_f] = ($turn==='a') ? [$a,$b] : [$b,$a];
            $hit = calculate_damage($atk_f['atk'], $def_f['def'], $atk_f['crit_rate'], $def_f['dodge_rate']);
            if ($hit['dodged']) {
                $log[] = ['type'=>'dodge', 'text'=>
                    "💨 {$def_f['username']} 閃避了 {$atk_f['username']} 的攻擊！"];
            } else {
                if ($turn==='a') $b['hp'] -= $hit['damage'];
                else             $a['hp'] -= $hit['damage'];
                $remaining = max(0, ($turn==='a') ? $b['hp'] : $a['hp']);
                $prefix = $hit['crit'] ? "💥 爆擊！" : "";
                $log[] = ['type'=>($hit['crit']?'crit':'attack'), 'text'=>
                    "{$prefix}{$atk_f['username']} 造成 {$hit['damage']} 傷害。{$def_f['username']} 剩餘 HP：{$remaining}"];
            }
        }
    }

    $winner = ($a['hp'] > 0) ? $a : $b;
    $loser  = ($a['hp'] > 0) ? $b : $a;
    $log[]  = ['type'=>'result', 'text'=>"🏆 {$winner['username']} 獲勝！（共 {$round} 回合）"];

    return ['winner_id'=>$winner['id'], 'loser_id'=>$loser['id'], 'rounds'=>$round, 'log'=>$log];
}

/**
 * 計算積分變動
 */
function calc_rating_change($winner_rating, $loser_rating, $winner_streak) {
    $diff = $winner_rating - $loser_rating;
    if ($diff > 100)       { $w_gain = 10; $l_loss = 30; }
    elseif ($diff < -100)  { $w_gain = 30; $l_loss = 10; }
    else                   { $w_gain = 20; $l_loss = 20; }
    if ($winner_streak >= 3) $w_gain += 5;
    return ['winner_gain' => $w_gain, 'loser_loss' => $l_loss];
}

/**
 * 執行 PVP 挑戰（完整流程）
 */
function do_pvp_challenge($conn, $challenger_id, $defender_id) {
    // 不能挑戰自己
    if ($challenger_id === $defender_id) return ['success'=>false,'message'=>'不能挑戰自己'];

    ensure_pvp_ranking($conn, $challenger_id);
    ensure_pvp_ranking($conn, $defender_id);

    // 冷卻檢查（1分鐘，用 MySQL 時間避免時區誤差）
    $remaining = (int)$conn->query("SELECT GREATEST(0, 60 - TIMESTAMPDIFF(SECOND, last_challenge, NOW())) FROM pvp_rankings WHERE user_id=$challenger_id")->fetch_row()[0];
    if ($remaining > 0) {
        return ['success'=>false, 'message'=>"挑戰冷卻中，剩餘 {$remaining} 秒"];
    }

    // 模擬戰鬥
    $result = simulate_pvp_battle($conn, $challenger_id, $defender_id);
    $winner_id = $result['winner_id'];
    $loser_id  = $result['loser_id'];

    // 取得積分
    $wr = $conn->query("SELECT rating,streak FROM pvp_rankings WHERE user_id=$winner_id")->fetch_assoc();
    $lr = $conn->query("SELECT rating FROM pvp_rankings WHERE user_id=$loser_id")->fetch_assoc();
    $change = calc_rating_change((int)$wr['rating'], (int)$lr['rating'], (int)$wr['streak']);

    $w_new = max(0, $wr['rating'] + $change['winner_gain']);
    $l_new = max(0, $lr['rating'] - $change['loser_loss']);

    // 更新積分
    $conn->query("UPDATE pvp_rankings SET
        rating=$w_new, wins=wins+1, streak=streak+1, last_challenge=NOW()
        WHERE user_id=$winner_id");
    $conn->query("UPDATE pvp_rankings SET
        rating=$l_new, losses=losses+1, streak=0
        WHERE user_id=$loser_id");
    // 更新挑戰方冷卻（若挑戰方是敗者則上面沒更新 last_challenge）
    if ($loser_id === $challenger_id)
        $conn->query("UPDATE pvp_rankings SET last_challenge=NOW() WHERE user_id=$challenger_id");

    // 寫入對戰紀錄
    $log_json = $conn->real_escape_string(json_encode($result['log'], JSON_UNESCAPED_UNICODE));
    $w_change = ($winner_id===$challenger_id) ? $change['winner_gain'] : -$change['loser_loss'];
    $d_change = ($defender_id===$winner_id)   ? $change['winner_gain'] : -$change['loser_loss'];
    $conn->query("INSERT INTO pvp_battles
        (challenger_id,defender_id,winner_id,challenger_rating_change,defender_rating_change,battle_log)
        VALUES ($challenger_id,$defender_id,$winner_id,$w_change,$d_change,'$log_json')");

    $challenger_won    = ($winner_id === $challenger_id);
    $rating_gain       = $challenger_won ? $change['winner_gain']  : -$change['loser_loss'];
    $challenger_new_rating = $challenger_won ? $w_new : $l_new;

    return [
        'success'              => true,
        'winner_id'            => $winner_id,
        'loser_id'             => $loser_id,
        'battle_log'           => $result['log'],
        'rounds'               => $result['rounds'],
        'rating_change'        => ($rating_gain >= 0 ? "+{$rating_gain}" : "{$rating_gain}"),
        'rating_gain'          => $rating_gain,
        'new_rating'           => $challenger_new_rating,
        'battle_id'            => $conn->insert_id,
    ];
}

/**
 * 週結算：依積分發放金幣，清零積分
 */
function pvp_weekly_settle($conn) {
    $players = [];
    $res = $conn->query("SELECT r.user_id, r.rating FROM pvp_rankings r JOIN users u ON r.user_id=u.id WHERE u.is_bot=0 ORDER BY r.rating DESC");
    while ($r = $res->fetch_assoc()) $players[] = $r;

    $rewarded = 0;
    foreach ($players as $i => $p) {
        $rank = $i + 1;
        if ($rank === 1)          $gold = 10000;
        elseif ($rank === 2)      $gold = 5000;
        elseif ($rank === 3)      $gold = 2000;
        elseif ($rank <= 10)      $gold = 1000;
        else {
            $tier = floor(($rank - 11) / 10);
            $gold = max(0, (int)(500 / pow(2, $tier)));
        }
        if ($gold > 0) {
            $conn->query("UPDATE users SET gold=gold+$gold WHERE id={$p['user_id']}");
            $rewarded++;
        }
    }
    // 重置真人玩家積分為 1000（電腦玩家不重置）
    $conn->query("UPDATE pvp_rankings r JOIN users u ON r.user_id=u.id SET r.rating=1000, r.wins=0, r.losses=0, r.streak=0 WHERE u.is_bot=0");
    return ['settled' => count($players), 'rewarded' => $rewarded];
}

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
