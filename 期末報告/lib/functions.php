<?php
/**
 * 共用函式總入口
 * 第二階段拆分後：
 * - 升級系統：lib_leveling.php
 * - 裝備鍛造：lib_equipment.php
 * - 怪物生成：lib_monsters.php
 * - 基礎戰鬥公式：lib_combat.php
 * - 訓練系統：lib_training.php
 * - API 紀錄：lib_api_log.php
 *
 * 本檔暫時保留：
 * - PVP 競技場
 * - 技能樹系統
 */

require_once __DIR__ . '/lib_leveling.php';
require_once __DIR__ . '/lib_equipment.php';
require_once __DIR__ . '/lib_monsters.php';
require_once __DIR__ . '/lib_combat.php';
require_once __DIR__ . '/lib_training.php';
require_once __DIR__ . '/lib_api_log.php';

// ════════════════════════════════════════
//  PVP 競技場
// ════════════════════════════════════════

/**
 * 確保玩家有 pvp_rankings 紀錄，沒有則自動建立
 */
function ensure_pvp_ranking($conn, $user_id) {
    $user_id = (int)$user_id;
    $q = $conn->query("SELECT user_id FROM pvp_rankings WHERE user_id=$user_id");

    if ($q !== false && !$q->fetch_assoc()) {
        $conn->query("INSERT INTO pvp_rankings (user_id) VALUES ($user_id)");
    }
}

/**
 * 取得玩家完整 PVP 資料
 */
function get_pvp_fighter($conn, $user_id) {
    $user_id = (int)$user_id;
    $res = $conn->query("SELECT id, username, dmg, def, hp, max_hp, level FROM users WHERE id=$user_id");
    $u = ($res !== false) ? $res->fetch_assoc() : null;

    if (!$u) {
        return [
            'id' => $user_id,
            'username' => 'Unknown',
            'level' => 1,
            'hp' => 1,
            'max_hp' => 1,
            'atk' => 1,
            'def' => 0,
            'crit_rate' => 10,
            'dodge_rate' => 10,
            'build' => ['archetype' => null, 'nodes_unlocked' => 0],
        ];
    }

    $eq = get_equipment_bonus($conn, $user_id);
    $dodge_lvl = 0;
    $crit_lvl = 0;

    $sr = $conn->query("SELECT skill_id, level FROM user_skills WHERE user_id=$user_id");
    if ($sr) {
        while ($row = $sr->fetch_assoc()) {
            if ($row['skill_id'] === 'dodge') {
                $dodge_lvl = (int)$row['level'];
            }

            if ($row['skill_id'] === 'crit') {
                $crit_lvl = (int)$row['level'];
            }
        }
    }

    $build = get_skill_build($conn, $user_id);
    $sb = get_skill_stat_bonus($build);

    $base_hp = (int)$u['max_hp'] + $sb['hp'];
    $base_atk = (int)$u['dmg'] + $sb['atk'];
    $base_def = (int)$u['def'] + $sb['def'];

    return [
        'id' => (int)$u['id'],
        'username' => $u['username'],
        'level' => (int)$u['level'],
        'hp' => max(1, (int)floor($base_hp * $eq['hp_mult'])),
        'max_hp' => max(1, (int)floor($base_hp * $eq['hp_mult'])),
        'atk' => max(1, (int)floor($base_atk * $eq['atk_mult'])),
        'def' => max(0, (int)floor($base_def * $eq['def_mult'])),
        'crit_rate' => 10 + $crit_lvl + $sb['crit'],
        'dodge_rate' => 10 + $dodge_lvl,
        'build' => $build,
    ];
}

function _pvp_do_attack(array &$atk, array &$def, array &$ss_atk, array &$ss_def, int &$corr_on_def, array &$log): void {
    $crit_r = $atk['crit_rate'];
    if ($ss_atk['vengeance_ready']) { $crit_r = 100; $ss_atk['vengeance_ready'] = false; }

    $eff_def = skill_get_effective_def($def['build'], $def['hp'], $def['max_hp'], max(0, $def['def'] - $corr_on_def));
    $hunt    = skill_hunting_bonus($atk['build'], $def['hp'], $def['max_hp']);
    $hit     = calculate_damage((int)($atk['atk'] * (1 + $hunt)), $eff_def, $crit_r, $def['dodge_rate']);

    if ($hit['dodged']) {
        $log[] = ['type' => 'dodge', 'text' => "💨 {$def['username']} 閃避了 {$atk['username']} 的攻擊！"];
        return;
    }

    $def['hp'] -= $hit['damage'];
    $prefix = $hit['crit'] ? "💥 爆擊！" : "";
    $log[] = ['type' => ($hit['crit'] ? 'crit' : 'attack'),
              'text' => "{$prefix}{$atk['username']} 造成 {$hit['damage']} 傷害。{$def['username']} 剩餘 HP：" . max(0, $def['hp'])];

    $sa = skill_on_player_attack($atk['build'], $ss_atk, $hit, $def['hp'], $def['max_hp'], $corr_on_def);
    if ($sa['extra'] > 0) { $def['hp'] -= $sa['extra']; $log[] = ['type' => 'system', 'text' => "{$atk['username']} {$sa['log']}"]; }

    $take_r = skill_on_player_take_dmg($def['build'], $ss_def, $hit, $hit['damage']);
    if ($take_r['log']) { $log[] = ['type' => 'system', 'text' => "{$def['username']} {$take_r['log']}"]; }
}

function _pvp_undying_check(array &$fighter, array &$ss, array &$log): void {
    if ($fighter['hp'] <= 0) {
        $ud = skill_check_undying($fighter['build'], $ss, $fighter['max_hp']);
        if ($ud['revived']) { $fighter['hp'] = $ud['new_hp']; $log[] = ['type' => 'system', 'text' => $ud['log']]; }
    }
}

/**
 * 模擬 PVP 戰鬥（含技能樹效果）
 */
function simulate_pvp_battle($conn, $challenger_id, $defender_id) {
    $a = get_pvp_fighter($conn, $challenger_id);
    $b = get_pvp_fighter($conn, $defender_id);

    $ss_a = skill_combat_init();
    $ss_b = skill_combat_init();
    $corr_on_b = 0;
    $corr_on_a = 0;

    if ($a['dodge_rate'] > $b['dodge_rate']) {
        $first = 'a';
    } elseif ($b['dodge_rate'] > $a['dodge_rate']) {
        $first = 'b';
    } else {
        $first = (rand(0, 1) ? 'a' : 'b');
    }

    $log = [];
    $log[] = [
        'type' => 'system',
        'text' => "⚔️ {$a['username']}（Lv.{$a['level']}）VS {$b['username']}（Lv.{$b['level']}）",
    ];
    $log[] = [
        'type' => 'system',
        'text' => ($first === 'a' ? "🎯 {$a['username']}" : "🎯 {$b['username']}") . " 先手出擊！",
    ];

    $round = 0;
    $order = ($first === 'a') ? ['a', 'b'] : ['b', 'a'];

    while ($a['hp'] > 0 && $b['hp'] > 0 && $round < 200) {
        $round++;

        $rs = skill_round_start($a['build'], $ss_a, $a['hp'], $a['max_hp']);
        if ($rs['heal'] > 0) { $a['hp'] = min($a['max_hp'], $a['hp'] + $rs['heal']); $log[] = ['type' => 'system', 'text' => "🌿 {$a['username']} 生命脈動 +{$rs['heal']} HP"]; }
        if ($rs['reflect'] > 0) { $b['hp'] -= $rs['reflect']; $log[] = ['type' => 'system', 'text' => "🌵 {$a['username']} 荊棘爆發！{$b['username']} 受到 {$rs['reflect']} 傷害"]; }

        $rs = skill_round_start($b['build'], $ss_b, $b['hp'], $b['max_hp']);
        if ($rs['heal'] > 0) { $b['hp'] = min($b['max_hp'], $b['hp'] + $rs['heal']); $log[] = ['type' => 'system', 'text' => "🌿 {$b['username']} 生命脈動 +{$rs['heal']} HP"]; }
        if ($rs['reflect'] > 0) { $a['hp'] -= $rs['reflect']; $log[] = ['type' => 'system', 'text' => "🌵 {$b['username']} 荊棘爆發！{$a['username']} 受到 {$rs['reflect']} 傷害"]; }

        if ($a['hp'] <= 0 || $b['hp'] <= 0) {
            break;
        }

        foreach ($order as $turn) {
            if ($a['hp'] <= 0 || $b['hp'] <= 0) break;

            if ($turn === 'a') {
                _pvp_do_attack($a, $b, $ss_a, $ss_b, $corr_on_b, $log);
            } else {
                _pvp_do_attack($b, $a, $ss_b, $ss_a, $corr_on_a, $log);
            }

            _pvp_undying_check($a, $ss_a, $log);
            _pvp_undying_check($b, $ss_b, $log);
        }
    }

    $winner = ($a['hp'] > 0) ? $a : $b;
    $loser = ($a['hp'] > 0) ? $b : $a;
    $log[] = ['type' => 'result', 'text' => "🏆 {$winner['username']} 獲勝！（共 {$round} 回合）"];

    return [
        'winner_id' => $winner['id'],
        'loser_id' => $loser['id'],
        'rounds' => $round,
        'log' => $log,
    ];
}

/**
 * 計算積分變動
 */
function calc_rating_change($winner_rating, $loser_rating, $winner_streak) {
    $diff = $winner_rating - $loser_rating;

    if ($diff > 100) {
        $w_gain = 10;
        $l_loss = 30;
    } elseif ($diff < -100) {
        $w_gain = 30;
        $l_loss = 10;
    } else {
        $w_gain = 20;
        $l_loss = 20;
    }

    if ($winner_streak >= 3) {
        $w_gain += 5;
    }

    return [
        'winner_gain' => $w_gain,
        'loser_loss' => $l_loss,
    ];
}

/**
 * 執行 PVP 挑戰
 */
function do_pvp_challenge($conn, $challenger_id, $defender_id) {
    $challenger_id = (int)$challenger_id;
    $defender_id = (int)$defender_id;

    if ($challenger_id === $defender_id) {
        return ['success' => false, 'message' => '不能挑戰自己'];
    }

    ensure_pvp_ranking($conn, $challenger_id);
    ensure_pvp_ranking($conn, $defender_id);

    $remaining_res = $conn->query("SELECT GREATEST(0, 60 - TIMESTAMPDIFF(SECOND, last_challenge, NOW())) FROM pvp_rankings WHERE user_id=$challenger_id");
    $remaining = ($remaining_res !== false) ? (int)($remaining_res->fetch_row()[0] ?? 0) : 0;

    if ($remaining > 0) {
        return [
            'success' => false,
            'message' => "挑戰冷卻中，剩餘 {$remaining} 秒",
        ];
    }

    $result = simulate_pvp_battle($conn, $challenger_id, $defender_id);
    $winner_id = (int)$result['winner_id'];
    $loser_id = (int)$result['loser_id'];

    $wr = $conn->query("SELECT rating, streak FROM pvp_rankings WHERE user_id=$winner_id")->fetch_assoc();
    $lr = $conn->query("SELECT rating FROM pvp_rankings WHERE user_id=$loser_id")->fetch_assoc();

    $change = calc_rating_change((int)$wr['rating'], (int)$lr['rating'], (int)$wr['streak']);
    $w_new = max(0, (int)$wr['rating'] + $change['winner_gain']);
    $l_new = max(0, (int)$lr['rating'] - $change['loser_loss']);

    $conn->query("UPDATE pvp_rankings SET rating=$w_new, wins=wins+1, streak=streak+1 WHERE user_id=$winner_id");
    $conn->query("UPDATE pvp_rankings SET rating=$l_new, losses=losses+1, streak=0 WHERE user_id=$loser_id");
    // 挑戰者無論勝負都進冷卻；防守方也更新以防止同一人反覆被挑戰
    $conn->query("UPDATE pvp_rankings SET last_challenge=NOW() WHERE user_id IN ($challenger_id, $defender_id)");

    $log_json = $conn->real_escape_string(json_encode($result['log'], JSON_UNESCAPED_UNICODE));
    $w_change = ($winner_id === $challenger_id) ? $change['winner_gain'] : -$change['loser_loss'];
    $d_change = ($defender_id === $winner_id) ? $change['winner_gain'] : -$change['loser_loss'];

    $conn->query("INSERT INTO pvp_battles
        (challenger_id, defender_id, winner_id, challenger_rating_change, defender_rating_change, battle_log)
        VALUES ($challenger_id, $defender_id, $winner_id, $w_change, $d_change, '$log_json')");

    $challenger_won = ($winner_id === $challenger_id);
    $rating_gain = $challenger_won ? $change['winner_gain'] : -$change['loser_loss'];
    $challenger_new_rating = $challenger_won ? $w_new : $l_new;

    return [
        'success' => true,
        'winner_id' => $winner_id,
        'loser_id' => $loser_id,
        'battle_log' => $result['log'],
        'rounds' => $result['rounds'],
        'rating_change' => ($rating_gain >= 0 ? "+{$rating_gain}" : "{$rating_gain}"),
        'rating_gain' => $rating_gain,
        'new_rating' => $challenger_new_rating,
        'battle_id' => $conn->insert_id,
    ];
}

/**
 * 週結算：依積分發放金幣，清零積分
 */
function pvp_weekly_settle($conn) {
    $has_is_bot = false;
    $col = $conn->query("SHOW COLUMNS FROM users LIKE 'is_bot'");

    if ($col !== false && $col->num_rows > 0) {
        $has_is_bot = true;
    }

    $where = $has_is_bot ? 'WHERE u.is_bot=0' : '';
    $players = [];

    $res = $conn->query("SELECT r.user_id, r.rating FROM pvp_rankings r JOIN users u ON r.user_id=u.id $where ORDER BY r.rating DESC");
    if ($res !== false) {
        while ($r = $res->fetch_assoc()) {
            $players[] = $r;
        }
    }

    $rewarded = 0;

    foreach ($players as $i => $p) {
        $rank = $i + 1;

        if ($rank === 1) {
            $gold = 10000;
        } elseif ($rank === 2) {
            $gold = 5000;
        } elseif ($rank === 3) {
            $gold = 2000;
        } elseif ($rank <= 10) {
            $gold = 1000;
        } else {
            $tier = floor(($rank - 11) / 10);
            $gold = max(0, (int)(500 / pow(2, $tier)));
        }

        if ($gold > 0) {
            $uid = (int)$p['user_id'];
            $conn->query("UPDATE users SET gold=gold+$gold WHERE id=$uid");
            $rewarded++;
        }
    }

    $reset_where = $has_is_bot ? 'WHERE u.is_bot=0' : '';
    // 賽季重置：高於 1000 的玩家重置到 1000；低於 1000 的保留現有積分（不送免費加分）
    $conn->query("UPDATE pvp_rankings r JOIN users u ON r.user_id=u.id SET r.rating=LEAST(r.rating, 1000), r.wins=0, r.losses=0, r.streak=0 $reset_where");

    return [
        'settled' => count($players),
        'rewarded' => $rewarded,
    ];
}

// ════════════════════════════════════════
//  技能樹系統
// ════════════════════════════════════════

/**
 * 各節點解鎖費用
 */
function get_node_costs() {
    return [
        1 => 1500,
        2 => 2500,
        3 => 3500,
        4 => 4500,
        5 => 5500,
        6 => 6500,
        7 => 7500,
        8 => 8500,
        9 => 10000,
    ];
}

/**
 * 三流派各 9 個節點定義
 */
function get_archetype_nodes() {
    return [
        'assault' => [
            1 => ['type' => 'stat', 'label' => 'ATK +3', 'atk' => 3],
            2 => ['type' => 'stat', 'label' => 'ATK +3', 'atk' => 3],
            3 => ['type' => 'skill', 'label' => '🩸 血肉渴望', 'skill' => 'blood_thirst', 'desc' => '每次攻擊追加敵方最大HP×1.4%真實傷害'],
            4 => ['type' => 'stat', 'label' => 'ATK +5', 'atk' => 5],
            5 => ['type' => 'stat', 'label' => '爆擊率 +5%', 'crit' => 5],
            6 => ['type' => 'skill', 'label' => '💀 穿心一擊', 'skill' => 'pierce_heart', 'desc' => '每4回合造成敵方最大HP×8%真實傷害'],
            7 => ['type' => 'stat', 'label' => 'ATK +5', 'atk' => 5],
            8 => ['type' => 'stat', 'label' => '爆擊率 +5%', 'crit' => 5],
            9 => ['type' => 'skill', 'label' => '🔥 獵殺本能', 'skill' => 'hunting_instinct', 'desc' => '敵方HP<60%傷害+15%，HP<25%再+15%'],
        ],
        'guardian' => [
            1 => ['type' => 'stat', 'label' => 'DEF +2', 'def' => 2],
            2 => ['type' => 'stat', 'label' => 'DEF +2', 'def' => 2],
            3 => ['type' => 'skill', 'label' => '🌵 荊棘之壁', 'skill' => 'thorn_wall', 'desc' => '每次受傷立即將實際承受傷害反彈給敵方'],
            4 => ['type' => 'stat', 'label' => 'DEF +3', 'def' => 3],
            5 => ['type' => 'stat', 'label' => 'HP +20', 'hp' => 20],
            6 => ['type' => 'skill', 'label' => '⚙️ 鋼鐵意志', 'skill' => 'iron_will', 'desc' => 'HP低於40%時防禦力×1.3'],
            7 => ['type' => 'stat', 'label' => 'DEF +3', 'def' => 3],
            8 => ['type' => 'stat', 'label' => 'HP +20', 'hp' => 20],
            9 => ['type' => 'skill', 'label' => '⚡ 報復之刃', 'skill' => 'vengeance_blade', 'desc' => '被暴擊後下次攻擊必定暴擊'],
        ],
        'vitality' => [
            1 => ['type' => 'stat', 'label' => 'HP +20', 'hp' => 20],
            2 => ['type' => 'stat', 'label' => 'HP +20', 'hp' => 20],
            3 => ['type' => 'skill', 'label' => '🌿 生命脈動', 'skill' => 'life_pulse', 'desc' => '每回合恢復最大HP×4%'],
            4 => ['type' => 'stat', 'label' => 'HP +30', 'hp' => 30],
            5 => ['type' => 'stat', 'label' => 'DEF +1', 'def' => 1],
            6 => ['type' => 'skill', 'label' => '🧪 侵蝕詛咒', 'skill' => 'corrosion_curse', 'desc' => '每次攻擊削減敵方DEF×0.5%（上限50%），追加削減量×1.15真傷'],
            7 => ['type' => 'stat', 'label' => 'HP +30', 'hp' => 30],
            8 => ['type' => 'stat', 'label' => 'DEF +2', 'def' => 2],
            9 => ['type' => 'skill', 'label' => '🔮 不滅之軀', 'skill' => 'undying_body', 'desc' => 'HP首次歸零時復活至25%，下次受傷減半'],
        ],
    ];
}

/**
 * 取得玩家技能樹資料
 */
function get_skill_build($conn, $user_id) {
    $user_id = (int)$user_id;
    $q = $conn->query("SELECT archetype, nodes_unlocked FROM user_skill_build WHERE user_id=$user_id");
    $row = ($q !== false) ? $q->fetch_assoc() : null;

    return $row ?: [
        'archetype' => null,
        'nodes_unlocked' => 0,
    ];
}

/**
 * 計算已解鎖數值節點的屬性加總
 */
function get_skill_stat_bonus($build) {
    $bonus = [
        'atk' => 0,
        'def' => 0,
        'hp' => 0,
        'crit' => 0,
    ];

    if (!$build['archetype'] || $build['nodes_unlocked'] <= 0) {
        return $bonus;
    }

    $nodes = get_archetype_nodes()[$build['archetype']] ?? [];

    for ($i = 1; $i <= (int)$build['nodes_unlocked']; $i++) {
        $n = $nodes[$i] ?? [];

        if (($n['type'] ?? '') === 'stat') {
            foreach (['atk', 'def', 'hp', 'crit'] as $k) {
                if (isset($n[$k])) {
                    $bonus[$k] += $n[$k];
                }
            }
        }
    }

    return $bonus;
}

/**
 * 確認指定技能是否在已解鎖節點內
 */
function has_skill($build, $skill_name) {
    if (!$build['archetype'] || $build['nodes_unlocked'] <= 0) {
        return false;
    }

    $nodes = get_archetype_nodes()[$build['archetype']] ?? [];

    for ($i = 1; $i <= (int)$build['nodes_unlocked']; $i++) {
        if (($nodes[$i]['skill'] ?? '') === $skill_name) {
            return true;
        }
    }

    return false;
}

/**
 * 初始化戰鬥技能狀態
 */
function skill_combat_init() {
    return [
        'round' => 0,
        'thorns_acc' => 0,
        'pierce_cd' => 0,
        'corr_stacks' => 0,
        'vengeance_ready' => false,
        'undying_used' => false,
        'undying_immune' => false,
    ];
}

/**
 * 每回合開始時的技能效果
 */
function skill_round_start($build, &$ss, $my_hp, $my_max_hp) {
    $heal = 0;
    $reflect = 0;
    $log = '';
    $ss['round']++;

    if (!$build['archetype']) {
        return compact('heal', 'reflect', 'log');
    }

    if (has_skill($build, 'life_pulse')) {
        $heal = max(1, (int)($my_max_hp * 0.04));
        $log .= "🌿 生命脈動：回復 {$heal} HP。";
    }


    return compact('heal', 'reflect', 'log');
}

/**
 * 玩家攻擊命中後的技能效果
 */
function skill_on_player_attack($build, &$ss, $hit_result, $enemy_hp, $enemy_max_hp, &$enemy_corr) {
    $extra = 0;
    $log = '';

    if (!$build['archetype'] || !$hit_result['hit']) {
        return compact('extra', 'log');
    }

    if (has_skill($build, 'blood_thirst')) {
        $td = max(1, (int)($enemy_max_hp * 0.014));
        $extra += $td;
        $log .= "🩸 血肉渴望：真傷 {$td}。";
    }

    if (has_skill($build, 'pierce_heart')) {
        $ss['pierce_cd']++;

        if ($ss['pierce_cd'] >= 4) {
            $ss['pierce_cd'] = 0;
            $td = max(1, (int)($enemy_max_hp * 0.08));
            $extra += $td;
            $log .= "💀 穿心一擊：真傷 {$td}！";
        }
    }

    if (has_skill($build, 'corrosion_curse') && $enemy_corr > 0) {
        $td = (int)($enemy_corr * 1.15);
        $extra += $td;
        $log .= "🧪 侵蝕：DEF -{$enemy_corr}%，追加真傷 {$td}。";
    }

    return compact('extra', 'log');
}

/**
 * 玩家受到攻擊後的技能效果
 */
function skill_on_player_take_dmg($build, &$ss, $hit_result, $dmg_taken) {
    $log = '';

    if (!$build['archetype'] || !$hit_result['hit']) {
        return ['log' => $log];
    }

    $reflect = 0;

    if (has_skill($build, 'thorn_wall') && $dmg_taken > 0) {
        $reflect = $dmg_taken;
        $log .= "🌵 荊棘反彈 {$reflect} 傷害！";
    }

    if (has_skill($build, 'vengeance_blade') && $hit_result['crit']) {
        $ss['vengeance_ready'] = true;
        $log .= "⚡ 報復之刃就緒！";
    }

    return ['log' => $log, 'reflect' => $reflect];
}

/**
 * 計算有效防禦
 */
function skill_get_effective_def($build, $my_hp, $my_max_hp, $base_def) {
    if (!has_skill($build, 'iron_will') || $my_max_hp <= 0) {
        return $base_def;
    }

    return ($my_hp / $my_max_hp) < 0.40 ? (int)($base_def * 1.3) : $base_def;
}

/**
 * 獵殺本能傷害加成倍率
 */
function skill_hunting_bonus($build, $enemy_hp, $enemy_max_hp) {
    if (!has_skill($build, 'hunting_instinct') || $enemy_max_hp <= 0) {
        return 0.0;
    }

    $pct = $enemy_hp / $enemy_max_hp;
    $bonus = 0.0;

    if ($pct < 0.60) {
        $bonus += 0.15;
    }

    if ($pct < 0.25) {
        $bonus += 0.15;
    }

    return $bonus;
}

/**
 * 不滅之軀死亡攔截
 */
function skill_check_undying($build, &$ss, $my_max_hp) {
    if (!has_skill($build, 'undying_body') || $ss['undying_used']) {
        return [
            'revived' => false,
            'new_hp' => 0,
            'log' => '',
        ];
    }

    $new_hp = max(1, (int)($my_max_hp * 0.25));
    $ss['undying_used'] = true;
    $ss['undying_immune'] = true;

    return [
        'revived' => true,
        'new_hp' => $new_hp,
        'log' => "🔮 不滅之軀：從死亡邊緣復活！回復 {$new_hp} HP，下次受傷減半！",
    ];
}