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
    $q   = $conn->query("SELECT last_train_time, train_duration FROM users WHERE id=$user_id");
    $row = ($q !== false) ? $q->fetch_assoc() : null;
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
        $q   = $conn->query("SELECT * FROM user_equipment WHERE user_id=$user_id AND equip_type='$t'");
        $row = ($q !== false) ? $q->fetch_assoc() : null;
        if (!$row) {
            if ($q !== false) {
                $conn->query("INSERT INTO user_equipment (user_id,equip_type,level,attempts,successes) VALUES ($user_id,'$t',0,0,0)");
            }
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
    $q = $conn->query("SELECT user_id FROM pvp_rankings WHERE user_id=$user_id");
    if ($q !== false && !$q->fetch_assoc()) {
        $conn->query("INSERT INTO pvp_rankings (user_id) VALUES ($user_id)");
    }
}

/**
 * 取得玩家完整 PVP 資料（含裝備加成、被動技能、技能樹數值加成）
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
    $build = get_skill_build($conn, $user_id);
    $sb    = get_skill_stat_bonus($build);
    return [
        'id'         => (int)$u['id'],
        'username'   => $u['username'],
        'level'      => (int)$u['level'],
        'hp'         => (int)$u['max_hp'] + $eq['hp'] + $sb['hp'],
        'max_hp'     => (int)$u['max_hp'] + $eq['hp'] + $sb['hp'],
        'atk'        => (int)$u['dmg']    + $eq['atk'] + $sb['atk'],
        'def'        => (int)$u['def']    + $eq['def'] + $sb['def'],
        'crit_rate'  => 10 + $crit_lvl + $sb['crit'],
        'dodge_rate' => 10 + $dodge_lvl,
        'build'      => $build,   // 技能樹 build，供戰鬥技能使用
    ];
}

/**
 * 模擬 PVP 戰鬥（含技能樹效果），回傳完整結果
 */
function simulate_pvp_battle($conn, $challenger_id, $defender_id) {
    $a = get_pvp_fighter($conn, $challenger_id);
    $b = get_pvp_fighter($conn, $defender_id);

    // 各自的技能戰鬥狀態與侵蝕層數
    $ss_a = skill_combat_init();  $ss_b = skill_combat_init();
    $corr_on_b = 0;  // a 對 b 的侵蝕層數
    $corr_on_a = 0;  // b 對 a 的侵蝕層數

    // 先攻判定
    if ($a['dodge_rate'] > $b['dodge_rate'])      $first = 'a';
    elseif ($b['dodge_rate'] > $a['dodge_rate'])  $first = 'b';
    else $first = (rand(0,1) ? 'a' : 'b');

    $log = [];
    $log[] = ['type'=>'system', 'text'=>
        "⚔️ {$a['username']}（Lv.{$a['level']}）VS {$b['username']}（Lv.{$b['level']}）"];
    $log[] = ['type'=>'system', 'text'=>
        ($first==='a' ? "🎯 {$a['username']}" : "🎯 {$b['username']}") . " 先手出擊！"];

    $round = 0;
    $order = ($first === 'a') ? ['a','b'] : ['b','a'];

    while ($a['hp'] > 0 && $b['hp'] > 0 && $round < 200) {
        $round++;

        // ── 回合開始：技能效果 ──
        foreach (['a','b'] as $side) {
            $f   = &$$side;
            $ss  = $side === 'a' ? $ss_a : $ss_b;
            $rs  = skill_round_start($f['build'], $ss, $f['hp'], $f['max_hp']);
            $opp = $side === 'a' ? 'b' : 'a';
            if ($rs['heal'] > 0) {
                $f['hp'] = min($f['max_hp'], $f['hp'] + $rs['heal']);
                $log[] = ['type'=>'system', 'text' => "🌿 {$f['username']} 生命脈動 +{$rs['heal']} HP"];
            }
            if ($rs['reflect'] > 0) {
                $$opp['hp'] -= $rs['reflect'];
                $log[] = ['type'=>'system', 'text' => "🌵 {$f['username']} 荊棘爆發！{${$opp}['username']} 受到 {$rs['reflect']} 傷害"];
            }
            if ($side === 'a') $ss_a = $ss; else $ss_b = $ss;
            unset($f);
        }
        if ($a['hp'] <= 0 || $b['hp'] <= 0) break;

        // ── 攻守回合 ──
        foreach ($order as $turn) {
            if ($a['hp'] <= 0 || $b['hp'] <= 0) break;

            [$atk_f, $def_f, $ss_atk, $ss_def, $corr_ref] = $turn === 'a'
                ? [$a, $b, &$ss_a, &$ss_b, &$corr_on_b]
                : [$b, $a, &$ss_b, &$ss_a, &$corr_on_a];

            // 報復之刃：本回合必爆
            $crit_r = $atk_f['crit_rate'];
            if ($ss_atk['vengeance_ready']) { $crit_r = 100; $ss_atk['vengeance_ready'] = false; }

            // 侵蝕削減後的有效防禦
            $eff_def = skill_get_effective_def($def_f['build'], $def_f['hp'], $def_f['max_hp'],
                                               max(0, $def_f['def'] - $corr_ref));

            // 獵殺本能加成
            $hunt = skill_hunting_bonus($atk_f['build'], $def_f['hp'], $def_f['max_hp']);
            $eff_atk = (int)($atk_f['atk'] * (1 + $hunt));

            $hit = calculate_damage($eff_atk, $eff_def, $crit_r, $def_f['dodge_rate']);

            if ($hit['dodged']) {
                $log[] = ['type'=>'dodge', 'text'=>"💨 {$def_f['username']} 閃避了 {$atk_f['username']} 的攻擊！"];
            } else {
                if ($turn==='a') $b['hp'] -= $hit['damage']; else $a['hp'] -= $hit['damage'];
                $prefix = $hit['crit'] ? "💥 爆擊！" : "";
                $remaining = max(0, ($turn==='a') ? $b['hp'] : $a['hp']);
                $log[] = ['type'=>($hit['crit']?'crit':'attack'), 'text'=>
                    "{$prefix}{$atk_f['username']} 造成 {$hit['damage']} 傷害。{$def_f['username']} 剩餘 HP：{$remaining}"];

                // 攻擊技能效果
                $sa = skill_on_player_attack($atk_f['build'], $ss_atk, $hit,
                                             $def_f['hp'], $def_f['max_hp'], $corr_ref);
                if ($sa['extra'] > 0) {
                    if ($turn==='a') $b['hp'] -= $sa['extra']; else $a['hp'] -= $sa['extra'];
                    $log[] = ['type'=>'system', 'text' => "{$atk_f['username']} {$sa['log']}"];
                }

                // 受傷技能效果
                $dtaken = $hit['damage'];
                $take_r = skill_on_player_take_dmg($def_f['build'], $ss_def, $hit, $dtaken);
                if ($take_r['log']) {
                    $log[] = ['type'=>'system', 'text' => "{$def_f['username']} {$take_r['log']}"];
                }
            }

            // 不滅之軀
            foreach (['a','b'] as $side) {
                if ($$side['hp'] <= 0) {
                    $ss_check = $side === 'a' ? $ss_a : $ss_b;
                    $ud = skill_check_undying($$side['build'], $ss_check, $$side['max_hp']);
                    if ($ud['revived']) {
                        $$side['hp'] = $ud['new_hp'];
                        $log[] = ['type'=>'system', 'text' => $ud['log']];
                    }
                    if ($side === 'a') $ss_a = $ss_check; else $ss_b = $ss_check;
                }
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
    if ($res !== false) while ($r = $res->fetch_assoc()) $players[] = $r;

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
//  技能樹系統
// ════════════════════════════════════════

/**
 * 各節點解鎖費用（線性成長，共 9 節點，總計 50,000 金）
 */
function get_node_costs() {
    return [1 => 1500, 2 => 2500, 3 => 3500, 4 => 4500,
            5 => 5500, 6 => 6500, 7 => 7500, 8 => 8500, 9 => 10000];
}

/**
 * 三流派各 9 個節點定義
 * type: 'stat'（數值節點）或 'skill'（技能節點）
 */
function get_archetype_nodes() {
    return [
        'assault' => [
            1 => ['type'=>'stat',  'label'=>'ATK +3',      'atk'=>3],
            2 => ['type'=>'stat',  'label'=>'ATK +3',      'atk'=>3],
            3 => ['type'=>'skill', 'label'=>'🩸 血肉渴望', 'skill'=>'blood_thirst',      'desc'=>'每次攻擊追加敵方最大HP×1.4%真實傷害'],
            4 => ['type'=>'stat',  'label'=>'ATK +5',      'atk'=>5],
            5 => ['type'=>'stat',  'label'=>'爆擊率 +5%',  'crit'=>5],
            6 => ['type'=>'skill', 'label'=>'💀 穿心一擊', 'skill'=>'pierce_heart',      'desc'=>'每4回合造成敵方最大HP×8%真實傷害'],
            7 => ['type'=>'stat',  'label'=>'ATK +5',      'atk'=>5],
            8 => ['type'=>'stat',  'label'=>'爆擊率 +5%',  'crit'=>5],
            9 => ['type'=>'skill', 'label'=>'🔥 獵殺本能', 'skill'=>'hunting_instinct',  'desc'=>'敵方HP<60%傷害+15%，HP<25%再+15%'],
        ],
        'guardian' => [
            1 => ['type'=>'stat',  'label'=>'DEF +2',      'def'=>2],
            2 => ['type'=>'stat',  'label'=>'DEF +2',      'def'=>2],
            3 => ['type'=>'skill', 'label'=>'🌵 荊棘之壁', 'skill'=>'thorn_wall',        'desc'=>'受傷時積累荊棘值，每4回合反彈積累×62%'],
            4 => ['type'=>'stat',  'label'=>'DEF +3',      'def'=>3],
            5 => ['type'=>'stat',  'label'=>'HP +20',      'hp'=>20],
            6 => ['type'=>'skill', 'label'=>'⚙️ 鋼鐵意志', 'skill'=>'iron_will',         'desc'=>'HP低於40%時防禦力×1.3'],
            7 => ['type'=>'stat',  'label'=>'DEF +3',      'def'=>3],
            8 => ['type'=>'stat',  'label'=>'HP +20',      'hp'=>20],
            9 => ['type'=>'skill', 'label'=>'⚡ 報復之刃', 'skill'=>'vengeance_blade',   'desc'=>'被暴擊後下次攻擊必定暴擊'],
        ],
        'vitality' => [
            1 => ['type'=>'stat',  'label'=>'HP +20',      'hp'=>20],
            2 => ['type'=>'stat',  'label'=>'HP +20',      'hp'=>20],
            3 => ['type'=>'skill', 'label'=>'🌿 生命脈動', 'skill'=>'life_pulse',        'desc'=>'每回合恢復最大HP×4%'],
            4 => ['type'=>'stat',  'label'=>'HP +30',      'hp'=>30],
            5 => ['type'=>'stat',  'label'=>'DEF +1',      'def'=>1],
            6 => ['type'=>'skill', 'label'=>'🧪 侵蝕詛咒', 'skill'=>'corrosion_curse',   'desc'=>'每次攻擊削減敵方DEF-3(上限-30)，追加層數×1.15真傷'],
            7 => ['type'=>'stat',  'label'=>'HP +30',      'hp'=>30],
            8 => ['type'=>'stat',  'label'=>'DEF +2',      'def'=>2],
            9 => ['type'=>'skill', 'label'=>'🔮 不滅之軀', 'skill'=>'undying_body',      'desc'=>'HP首次歸零時復活至25%，下次受傷減半'],
        ],
    ];
}

/** 取得玩家技能樹資料，無資料時回傳預設值 */
function get_skill_build($conn, $user_id) {
    $q   = $conn->query("SELECT archetype, nodes_unlocked FROM user_skill_build WHERE user_id=$user_id");
    $row = ($q !== false) ? $q->fetch_assoc() : null;
    return $row ?: ['archetype' => null, 'nodes_unlocked' => 0];
}

/** 計算已解鎖數值節點的屬性加總 */
function get_skill_stat_bonus($build) {
    $bonus = ['atk' => 0, 'def' => 0, 'hp' => 0, 'crit' => 0];
    if (!$build['archetype'] || $build['nodes_unlocked'] <= 0) return $bonus;
    $nodes = get_archetype_nodes()[$build['archetype']] ?? [];
    for ($i = 1; $i <= (int)$build['nodes_unlocked']; $i++) {
        $n = $nodes[$i] ?? [];
        if (($n['type'] ?? '') === 'stat') {
            foreach (['atk','def','hp','crit'] as $k) {
                if (isset($n[$k])) $bonus[$k] += $n[$k];
            }
        }
    }
    return $bonus;
}

/** 確認指定技能是否在已解鎖節點內 */
function has_skill($build, $skill_name) {
    if (!$build['archetype'] || $build['nodes_unlocked'] <= 0) return false;
    $nodes = get_archetype_nodes()[$build['archetype']] ?? [];
    for ($i = 1; $i <= (int)$build['nodes_unlocked']; $i++) {
        if (($nodes[$i]['skill'] ?? '') === $skill_name) return true;
    }
    return false;
}

/** 初始化戰鬥技能狀態 */
function skill_combat_init() {
    return [
        'round'           => 0,
        'thorns_acc'      => 0,
        'pierce_cd'       => 0,
        'corr_stacks'     => 0,
        'vengeance_ready' => false,
        'undying_used'    => false,
        'undying_immune'  => false,
    ];
}

/**
 * 每回合開始時的技能效果
 * @return array ['heal'=>int, 'reflect'=>int, 'log'=>string]
 */
function skill_round_start($build, &$ss, $my_hp, $my_max_hp) {
    $heal = $reflect = 0; $log = '';
    $ss['round']++;
    if (!$build['archetype']) return compact('heal','reflect','log');

    // 生命脈動（血量流 節點3）
    if (has_skill($build, 'life_pulse')) {
        $heal = max(1, (int)($my_max_hp * 0.04));
        $log .= "🌿 生命脈動：回復 {$heal} HP。";
    }
    // 荊棘之壁爆發（防禦流 節點3，每4回合）
    if (has_skill($build, 'thorn_wall') && $ss['round'] % 4 === 0 && $ss['thorns_acc'] > 0) {
        $reflect = (int)($ss['thorns_acc'] * 0.62);
        $log .= "🌵 荊棘之壁爆發：反彈 {$reflect} 傷害！";
        $ss['thorns_acc'] = 0;
    }
    return compact('heal','reflect','log');
}

/**
 * 玩家攻擊命中後的技能效果
 * @param array  $hit_result  calculate_damage() 的回傳值
 * @param int    &$enemy_corr 侵蝕層數（傳址，函式內累積）
 * @return array ['extra'=>int, 'log'=>string]
 */
function skill_on_player_attack($build, &$ss, $hit_result, $enemy_hp, $enemy_max_hp, &$enemy_corr) {
    $extra = 0; $log = '';
    if (!$build['archetype'] || !$hit_result['hit']) return compact('extra','log');

    // 血肉渴望：1.4% 敵方最大HP 真傷
    if (has_skill($build, 'blood_thirst')) {
        $td = max(1, (int)($enemy_max_hp * 0.014));
        $extra += $td;
        $log .= "🩸 血肉渴望：真傷 {$td}。";
    }
    // 穿心一擊：每4回合 8% 最大HP 真傷
    if (has_skill($build, 'pierce_heart')) {
        $ss['pierce_cd']++;
        if ($ss['pierce_cd'] >= 4) {
            $ss['pierce_cd'] = 0;
            $td = max(1, (int)($enemy_max_hp * 0.08));
            $extra += $td;
            $log .= "💀 穿心一擊：真傷 {$td}！";
        }
    }
    // 侵蝕詛咒：DEF -3（上限30層），層數×1.15 真傷
    if (has_skill($build, 'corrosion_curse')) {
        $enemy_corr = min(30, $enemy_corr + 3);
        $td = (int)($enemy_corr * 1.15);
        $extra += $td;
        $log .= "🧪 侵蝕：DEF -{$enemy_corr}，追加真傷 {$td}。";
    }
    return compact('extra','log');
}

/**
 * 玩家受到攻擊後的技能效果
 * @return array ['log'=>string]
 */
function skill_on_player_take_dmg($build, &$ss, $hit_result, $dmg_taken) {
    $log = '';
    if (!$build['archetype'] || !$hit_result['hit']) return ['log'=>$log];

    // 荊棘之壁積累
    if (has_skill($build, 'thorn_wall') && $dmg_taken > 0) {
        $ss['thorns_acc'] += $dmg_taken;
    }
    // 報復之刃：被暴擊後下次攻擊必爆
    if (has_skill($build, 'vengeance_blade') && $hit_result['crit']) {
        $ss['vengeance_ready'] = true;
        $log .= "⚡ 報復之刃就緒！";
    }
    return ['log' => $log];
}

/**
 * 計算有效防禦（鋼鐵意志：HP < 40% 時 DEF × 1.3）
 */
function skill_get_effective_def($build, $my_hp, $my_max_hp, $base_def) {
    if (!has_skill($build, 'iron_will') || $my_max_hp <= 0) return $base_def;
    return ($my_hp / $my_max_hp) < 0.40 ? (int)($base_def * 1.3) : $base_def;
}

/**
 * 獵殺本能傷害加成倍率（0.0 / 0.15 / 0.30）
 */
function skill_hunting_bonus($build, $enemy_hp, $enemy_max_hp) {
    if (!has_skill($build, 'hunting_instinct') || $enemy_max_hp <= 0) return 0.0;
    $pct = $enemy_hp / $enemy_max_hp;
    $b = 0.0;
    if ($pct < 0.60) $b += 0.15;
    if ($pct < 0.25) $b += 0.15;
    return $b;
}

/**
 * 不滅之軀死亡攔截
 * @return array ['revived'=>bool, 'new_hp'=>int, 'log'=>string]
 */
function skill_check_undying($build, &$ss, $my_max_hp) {
    if (!has_skill($build, 'undying_body') || $ss['undying_used']) {
        return ['revived' => false, 'new_hp' => 0, 'log' => ''];
    }
    $new_hp = max(1, (int)($my_max_hp * 0.25));
    $ss['undying_used']   = true;
    $ss['undying_immune'] = true;
    return ['revived' => true, 'new_hp' => $new_hp,
            'log' => "🔮 不滅之軀：從死亡邊緣復活！回復 {$new_hp} HP，下次受傷減半！"];
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
