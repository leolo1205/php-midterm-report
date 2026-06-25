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
        return $hit;  // 回傳完整 hit 結果（含 crit/dodged，供技能判斷用）
    }
}

// ── 讀取玩家技能 Build ──
$p_build = get_skill_build($conn, $user_id);
$sb      = get_skill_stat_bonus($p_build);
$skill_ss = skill_combat_init();  // 技能戰鬥狀態
$enemy_corr = 0.0;                // 侵蝕詛咒：敵方 DEF 削減百分比（0~50）

// 開始初始化戰鬥數據
$is_boss = ($event === 'boss');
$f_data = get_floor_data($target_floor);
$m_lvl = $is_boss ? $f_data['boss_level'] : $f_data['mob_level'];
$m_name = $is_boss ? "<span style='color:#f44336; font-weight:bold;'>💀 Boss: Lv.$m_lvl {$f_data['boss_name']}</span>" : "🦇 Lv.$m_lvl {$f_data['mob_name']}";

// 備援公式：從第20層的已知數值線性延伸（每級 hp+220 dmg+16 def+3 exp+96 gold+70）
$m_stats = isset($monster_db[$m_lvl]) ? $monster_db[$m_lvl] : [
    'hp'   => 2000 + ($m_lvl - 20) * 220,
    'dmg'  => 180  + ($m_lvl - 20) * 16,
    'def'  => 30   + ($m_lvl - 20) * 3,
    'exp'  => 800  + ($m_lvl - 20) * 96,
    'gold' => 600  + ($m_lvl - 20) * 70,
];

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

// 套入真實屬性：原始屬性 + 技能樹固定加成，再乘上裝備倍率
$effective = get_player_effective_stats($conn, $user_id);

$p_dmg = (int)$effective['atk']['value'] + (int)($run['buffs']['dmg'] ?? 0);
$p_def_base = (int)$effective['def']['value'] + (int)($run['buffs']['def'] ?? 0);
$p_max_hp_with_bonus = (int)$effective['hp']['value'] + (int)($run['buffs']['max_hp'] ?? 0);
$p_crit_rate_eff = $p_crit_rate + (int)($sb['crit'] ?? 0);

while ($run['hp'] > 0 && $m_hp > 0) {

    // ── 回合開始：技能效果（生命脈動回血、荊棘爆發）──
    $rs = skill_round_start($p_build, $skill_ss, $run['hp'], $p_max_hp_with_bonus);
    if ($rs['heal'] > 0) {
        $run['hp'] = min($p_max_hp_with_bonus, $run['hp'] + $rs['heal']);
        $node_new .= "<div class='reveal-item hidden-item' data-delay='200'><span style='color:#66bb6a;'>{$rs['log']}</span></div>";
        $node_old .= "<div><span style='color:#66bb6a;'>{$rs['log']}</span></div>";
    }
    if ($rs['reflect'] > 0) {
        $m_hp -= $rs['reflect'];
        $rlog = $rs['log'] . " 敵方受到 {$rs['reflect']} 傷害！";
        $node_new .= "<div class='reveal-item hidden-item' data-delay='200'><span style='color:#4fc3f7;'>{$rlog}</span></div>";
        $node_old .= "<div><span style='color:#4fc3f7;'>{$rlog}</span></div>";
        if ($m_hp <= 0) break;
    }

    // ── 玩家攻擊 ──
    // 先疊加本回合侵蝕，再套用（當回合即生效）
    if (has_skill($p_build, 'corrosion_curse')) {
        $enemy_corr = min(50, $enemy_corr + 0.5);
    }
    $eff_m_def_corroded = max(0, (int)($m_def * (1 - $enemy_corr / 100)));  // 侵蝕削減後的怪物防禦（百分比削減）
    $eff_m_def = ($boss_vars['defense_turns'] > 0) ? $eff_m_def_corroded * 3 : $eff_m_def_corroded;

    // 報復之刃：本回合必爆
    $p_crit_this_turn = $p_crit_rate_eff;
    if ($skill_ss['vengeance_ready']) {
        $p_crit_this_turn = 100;
        $skill_ss['vengeance_ready'] = false;
    }

    // 獵殺本能傷害加成
    $hunt_bonus = skill_hunting_bonus($p_build, $m_hp, $m_max_hp);
    $p_dmg_this_turn = (int)($p_dmg * (1 + $hunt_bonus));

    $hit_result = execute_attack($user['username'], $m_hp, $p_dmg_this_turn, $eff_m_def, $p_crit_this_turn, $m_dodge, true, false, false, $node_new, $node_old, $run);

    // 攻擊命中後的技能效果
    $sa = skill_on_player_attack($p_build, $skill_ss, $hit_result, $m_hp, $m_max_hp, $enemy_corr);
    if ($sa['extra'] > 0) {
        $m_hp -= $sa['extra'];
        $node_new .= "<div class='reveal-item hidden-item' data-delay='150'><span style='color:#ce93d8;'>{$sa['log']}</span></div>";
        $node_old .= "<div><span style='color:#ce93d8;'>{$sa['log']}</span></div>";
        if ($m_hp <= 0) break;
    }

    if ($hit_result['hit'] && $boss_id === 'boar_king') {
        $boss_vars['anger']++; $m_crit++;
        $anger_msg = "<span style='color:#ff5722; font-size:12px;'>(野豬王看起來更加憤怒了，爆擊率提升！)</span>";
        $node_new .= "<div class='reveal-item hidden-item' data-delay='200'>$anger_msg</div>"; $node_old .= "<div>$anger_msg</div>";
    }

    if ($boss_id === 'boar_king' && $m_hp > 0 && $m_hp < ($m_max_hp * 0.5) && !isset($boss_vars['triggered_def'])) {
        $boss_vars['defense_turns'] = 2; $boss_vars['triggered_def'] = true;
        $def_msg = "<b style='color:#2196f3;'>🛡️ 野豬王進入了絕對防禦狀態！(防禦力 x3，兩回合不進行攻擊)</b>";
        $node_new .= "<div class='reveal-item hidden-item' data-delay='600'>$def_msg</div>"; $node_old .= "<div>$def_msg</div>";
    }

    if ($m_hp <= 0) break;

    // ── 怪物攻擊 ──
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
            // 鋼鐵意志：低血量時防禦加成
            $p_def_eff = skill_get_effective_def($p_build, $run['hp'], $p_max_hp_with_bonus, $p_def_base);
            $eff_m_dmg = $is_special_atk ? floor($m_dmg * 1.4) : $m_dmg;

            // 不滅之軀免疫：復活後下一次受傷減半
            if ($skill_ss['undying_immune']) {
                $eff_m_dmg = (int)ceil($eff_m_dmg / 2);
            }

            $mon_hit = execute_attack(strip_tags($m_name), $run['hp'], $eff_m_dmg, $p_def_eff, $m_crit, $p_dodge_rate, false, true, $is_special_atk, $node_new, $node_old, $run);

            // 不滅之軀：命中後消耗免疫
            if ($skill_ss['undying_immune'] && $mon_hit['hit'] && $mon_hit['damage'] > 0) {
                $skill_ss['undying_immune'] = false;
                $node_new .= "<div class='reveal-item hidden-item' data-delay='150'><span style='color:#ce93d8;'>🛡️ 不滅之軀：傷害減半！</span></div>";
                $node_old  .= "<div><span style='color:#ce93d8;'>🛡️ 不滅之軀：傷害減半！</span></div>";
            }

            // 受傷後技能效果（荊棘反彈、報復之刃）
            $dtaken = $mon_hit['hit'] ? $mon_hit['damage'] : 0;
            $take_r = skill_on_player_take_dmg($p_build, $skill_ss, $mon_hit, $dtaken);
            if ($take_r['log']) {
                $node_new .= "<div class='reveal-item hidden-item' data-delay='150'><span style='color:#4fc3f7;'>{$take_r['log']}</span></div>";
                $node_old .= "<div><span style='color:#4fc3f7;'>{$take_r['log']}</span></div>";
            }
            if (!empty($take_r['reflect']) && $take_r['reflect'] > 0) {
                $m_hp -= $take_r['reflect'];
                if ($m_hp <= 0) break;
            }
        }
    }

    // HP 歸零時不滅之軀攔截
    if ($run['hp'] <= 0) {
        $ud = skill_check_undying($p_build, $skill_ss, $p_max_hp_with_bonus);
        if ($ud['revived']) {
            $run['hp'] = $ud['new_hp'];
            $node_new .= "<div class='reveal-item hidden-item' data-delay='300'><span style='color:#ce93d8;font-weight:bold;'>{$ud['log']}</span></div>";
            $node_old .= "<div><span style='color:#ce93d8;'>{$ud['log']}</span></div>";
        }
    }
    // 撤退保險觸發
    if ($run['hp'] > 0 && !empty($run['retreat_insured']) && $run['hp'] <= (int)($p_max_hp_with_bonus * 0.3)) {
        $node_new .= "<div class='reveal-item hidden-item' data-delay='300'><span style='color:#ff9800;font-weight:bold;'>🛡️ 撤退保險觸發！立即撤出！</span></div>";
        $node_old .= "<div><span style='color:#ff9800;font-weight:bold;'>🛡️ 撤退保險觸發！立即撤出！</span></div>";
        $run['state'] = 'retreat';
        break;
    }
}

$node_new .= "</div>"; $node_old .= "</div>";

if ($run['hp'] <= 0) {
    $add_line("<p style='color:#f44336; font-weight:bold;'>你被擊敗了... 探索中斷。</p>", 1000);
    $run['state'] = 'dead';
} elseif ($run['state'] === 'retreat') {
    $add_line("<p style='color:#ff9800;'>🛡️ 你緊急撤出了戰場，保住了身上的金幣與經驗！</p>", 1000);
} else {
    $run['exp'] += $m_exp; $run['gold'] += $m_gold;
    $add_line("<p style='color:#4caf50;'>戰鬥勝利！剩餘 HP: {$run['hp']}，獲得 <span style='color:#64b5f6;'>$m_exp EXP</span> 與 <span style='color:gold;'>$m_gold 金幣</span>。</p>", 1000);
}
