<?php
// --- 戰鬥執行邏輯 ---

// 使用 function_exists 避免在迴圈中重複定義導致噴錯
if (!function_exists('execute_attack')) {
    function execute_attack($attacker, &$def_hp, $atk_dmg, $def_def, $crit_rate, $dodge_rate, $is_attacker_player, $is_defender_player, $is_special_atk = false, &$node_new, &$node_old, &$run) {
        $hit = calculate_damage($atk_dmg, $def_def, $crit_rate, $dodge_rate);
        $line = "";
        if ($hit['dodged']) {
            $line .= "<span style='color:#888;'>$attacker 的攻擊被閃避了！</span>";
            if ($is_defender_player) {
                if (!isset($run['skill_gains']['dodge'])) $run['skill_gains']['dodge'] = 0;
                $run['skill_gains']['dodge']++;
            }
        } else {
            if ($hit['crit'] && $is_attacker_player) {
                if (!isset($run['skill_gains']['crit'])) $run['skill_gains']['crit'] = 0;
                $run['skill_gains']['crit']++;
            }
            $def_hp -= $hit['damage'];
            if ($is_special_atk) $line .= "🌪️ <b>毀滅衝撞！</b> ";
            if ($hit['crit']) $line .= "<span>💥 <b>爆擊！</b></span> ";
            $line .= "$attacker 造成了 <span style='color:#ff9800;'>{$hit['damage']}</span> 點傷害。";
            if ($def_def > 0) $line .= " <span style='color:#666; font-size:12px;'>(被抵抗 $def_def 點)</span>";
        }
        $node_new .= "<div class='reveal-item hidden-item' data-delay='600'>$line</div>";
        $node_old .= "<div>$line</div>";
        return $hit['hit'];
    }
}

// 開始初始化戰鬥數據
$is_boss = ($event === 'boss');
$f_data = get_floor_data($target_floor);
$m_lvl = $is_boss ? $f_data['boss_level'] : $f_data['mob_level'];
$m_name = $is_boss ? "<span style='color:#f44336; font-weight:bold;'>💀 Boss: Lv.$m_lvl {$f_data['boss_name']}</span>" : "🦇 Lv.$m_lvl {$f_data['mob_name']}";

$m_stats = isset($monster_db[$m_lvl]) ? $monster_db[$m_lvl] : ['hp' => $m_lvl * 100, 'dmg' => $m_lvl * 10, 'def' => floor($m_lvl * 1.5), 'exp' => $m_lvl * 40, 'gold' => $m_lvl * 30];

// BOSS 狀態初始化
$is_special_boss = ($is_boss && $f_data['is_special']);
$boss_id = $is_special_boss ? $f_data['special_data']['id'] : null;
$boss_vars = ['anger' => 0, 'defense_turns' => 0, 'energy' => 0]; 

if ($is_special_boss) {
    $m_hp = floor($m_stats['hp'] * $f_data['special_data']['hp_mult']);
    $m_dmg = floor($m_stats['dmg'] * $f_data['special_data']['dmg_mult']);
    $m_def = floor($m_stats['def'] * $f_data['special_data']['def_mult']);
    $m_crit = $f_data['special_data']['base_crit'];
    $m_dodge = $f_data['special_data']['base_dodge'];
    $m_max_hp = $m_hp;
} else {
    $m_hp = $m_stats['hp']; $m_dmg = $m_stats['dmg']; $m_def = $m_stats['def'];
    $m_crit = 10; $m_dodge = 10; $m_max_hp = $m_hp;
}

$m_exp = $m_stats['exp']; $m_gold = $m_stats['gold'];
$add_line("<p>遭遇敵人：$m_name (HP: $m_hp)</p>", 1000);
$node_new .= "<div class='combat-log reveal-item hidden-item' data-delay='300'>";
$node_old .= "<div class='combat-log'>";

$p_dmg = $user['dmg'] + $run['buffs']['dmg']; 
$p_def = $user['def'] + $run['buffs']['def'];

while ($run['hp'] > 0 && $m_hp > 0) {
    // 玩家攻擊
    $eff_m_def = ($boss_vars['defense_turns'] > 0) ? $m_def * 3 : $m_def;
    $hit_success = execute_attack($user['username'], $m_hp, $p_dmg, $eff_m_def, $p_crit_rate, $m_dodge, true, false, false, $node_new, $node_old, $run);
    
    if ($hit_success && $boss_id === 'boar_king') {
        $boss_vars['anger']++; $m_crit++;
        $anger_msg = "<span style='color:#ff5722; font-size:12px;'>(野豬王看起來更加憤怒了，爆擊率提升！)</span>";
        $node_new .= "<div class='reveal-item hidden-item' data-delay='200'>$anger_msg</div>"; $node_old .= "<div>$anger_msg</div>";
    }

    if ($boss_id === 'boar_king' && $m_hp > 0 && $m_hp < ($m_max_hp * 0.5) && !isset($boss_vars['triggered_def'])) {
        $boss_vars['defense_turns'] = 2; $boss_vars['triggered_def'] = true;
        $def_msg = "<b style='color:#2196f3;'>🛡️ 野豬王進入了絕對防禦狀態！(防禦力 x3，兩回合不進行攻擊)</b>";
        $node_new .= "<div class='reveal-item hidden-item' data-delay='600'>$def_msg</div>"; $node_old .= "<div>$def_msg</div>";
    }

    if ($m_hp > 0) {
        if ($boss_vars['defense_turns'] > 0) {
            $boss_vars['defense_turns']--;
            $def_log = "<span style='color:#2196f3;'>💤 野豬王正在防禦，本回合沒有攻擊。</span>";
            $node_new .= "<div class='reveal-item hidden-item' data-delay='600'>$def_log</div>"; $node_old .= "<div>$def_log</div>";
        } else {
            $is_special_atk = false; $skip_normal_attack = false;
            if ($boss_id === 'boar_king') {
                $boss_vars['energy']++;
                if ($boss_vars['energy'] === 4) {
                    $charge_msg = "<b style='color:#ff9800;'>🐗 野豬王正在蓄力，準備發動毀滅衝撞！</b>";
                    $node_new .= "<div class='reveal-item hidden-item' data-delay='600'>$charge_msg</div>"; $node_old .= "<div>$charge_msg</div>";
                    $skip_normal_attack = true; 
                } elseif ($boss_vars['energy'] >= 5) {
                    $is_special_atk = true; $boss_vars['energy'] = 0; 
                }
            }
            if (!$skip_normal_attack) {
                $eff_m_dmg = $is_special_atk ? floor($m_dmg * 1.4) : $m_dmg;
                execute_attack(strip_tags($m_name), $run['hp'], $eff_m_dmg, $p_def, $m_crit, $p_dodge_rate, false, true, $is_special_atk, $node_new, $node_old, $run);
            }
        }
    }
}
$node_new .= "</div>"; $node_old .= "</div>"; 

if ($run['hp'] <= 0) {
    $add_line("<p style='color:#f44336; font-weight:bold;'>你被擊敗了... 探索中斷。</p>", 1000);
    $run['state'] = 'dead';
} else {
    $run['exp'] += $m_exp; $run['gold'] += $m_gold;
    $add_line("<p style='color:#4caf50;'>戰鬥勝利！剩餘 HP: {$run['hp']}，獲得 <span style='color:#64b5f6;'>$m_exp EXP</span> 與 <span style='color:gold;'>$m_gold 金幣</span>。</p>", 1000);
}
?>