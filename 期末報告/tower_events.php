<?php
// --- 非戰鬥事件執行邏輯 ---

if ($event === 'merchant') {
    $run['state'] = 'wait_merchant';
    if (($auto['mode'] ?? 'manual') === 'manual') {
        $form_html = "<p>「嘿嘿嘿... 冒險者，要不要來抽個盲盒？一半天堂，一半地獄喔...」</p><form method='post' style='display:flex; gap:10px; margin-top:10px; flex-wrap: wrap;' onsubmit='this.querySelectorAll(\"button\").forEach(b=>{b.disabled=true;b.style.opacity=\"0.5\";});'>" . csrf_field() . "<button type='submit' name='action' value='merch_A' class='btn-action' style='background:#f44336;'>🔴 拍下紅色按鈕</button><button type='submit' name='action' value='merch_B' class='btn-action' style='background:#2196f3;'>🔵 拍下藍色按鈕</button><button type='submit' name='action' value='merch_leave' class='btn-action' style='background:#757575;'>🚶 轉身離開</button></form>";
        $add_line($form_html, 100);
        $stop_loop = true;
    } else {
        $add_line("<p>「嘿嘿嘿... 冒險者，要不要來抽個盲盒？一半天堂，一半地獄喔...」</p>", 100);
    }

} elseif ($event === 'buy_exp') {
    $run['state'] = 'wait_exp';
    $cost = 5 * $target_floor; $gain = 10 * $target_floor;
    if (($auto['mode'] ?? 'manual') === 'manual') {
        $form_html = "<p>「知識就是力量，給我 $cost 金幣，我傳授你 $gain 經驗值。」</p><form method='post' style='display:flex; gap:10px; margin-top:10px;' onsubmit='this.querySelectorAll(\"button\").forEach(b=>{b.disabled=true;b.style.opacity=\"0.5\";});'>" . csrf_field() . "<button type='submit' name='action' value='exp_yes' class='btn-action' style='background:#4caf50;'>💰 支付金幣</button><button type='submit' name='action' value='exp_no' class='btn-action' style='background:#757575;'>🚶 轉身離開</button></form>";
        $add_line($form_html, 100);
        $stop_loop = true;
    } else {
        $add_line("<p>「知識就是力量，給我 $cost 金幣，我傳授你 $gain 經驗值。」</p>", 100);
    }

} elseif ($event === 'gold') {
    $found_gold = rand(20, 50) * $target_floor; $run['gold'] += $found_gold;
    $add_line("<p>💰 發現寶箱！獲得 <span style='color:gold;'>$found_gold 金幣</span>！</p>", 1000);

} elseif ($event === 'heal') {
    $eff_max_hp = $user['max_hp'] + $run['buffs']['max_hp'];
    $heal = floor($eff_max_hp * 0.2);
    $run['hp'] = min($eff_max_hp, $run['hp'] + $heal);
    $add_line("<p>🧪 找到神聖甘泉，恢復 20% 生命。<span style='color:#4caf50;'>+$heal HP</span> (目前: {$run['hp']})</p>", 1000);

} elseif ($event === 'buff') {
    $buff_types = ['dmg' => '傷害', 'def' => '防禦']; 
    $keys = array_keys($buff_types); 
    $b_key = $keys[array_rand($keys)]; 
    $b_val = rand(2, 5) * $target_floor; 
    $run['buffs'][$b_key] += $b_val; 
    $current_val = $user[$b_key] + $run['buffs'][$b_key];
    $add_line("<p>🌟 你觸碰了發光的石碑，獲得臨時強化！<br><span style='color:#64b5f6;'>{$buff_types[$b_key]} +$b_val (當前: $current_val)</span></p>", 1000);

} elseif ($event === 'rest') {
    $rest_texts = ["你找到了一個避風的角落，生起營火稍微休息了一下。", "周圍暫時沒有危險，你坐下來整理裝備，喘了口氣。", "此處風景不錯，你停下腳步欣賞了一會兒，感覺精神好多了。", "你靠在樹幹上閉目養神，微風拂過，帶走了一絲疲憊。"];
    $random_text = $rest_texts[array_rand($rest_texts)];
    $eff_max_hp = $user['max_hp'] + $run['buffs']['max_hp'];
    $heal = max(1, floor($eff_max_hp * 0.05));
    $run['hp'] = min($eff_max_hp, $run['hp'] + $heal);
    $add_line("<p>⛺ $random_text <span style='color:#4caf50;'>回復 $heal HP</span> (目前: {$run['hp']})</p>", 1000);

} elseif ($event === 'trap') {
    $trap_dmg = rand(10, 25) * $target_floor; 
    $run['hp'] -= $trap_dmg;
    $add_line("<p style='color:#f44336;'>⚠️ 糟糕！你踩到了隱藏的陷阱，受到 $trap_dmg 點傷害！ (目前 HP: {$run['hp']})</p>", 1000);
    if ($run['hp'] <= 0) $run['state'] = 'dead';

} elseif ($event === 'curse') {
    $buff_types = ['dmg' => '傷害', 'def' => '防禦'];
    $keys = array_keys($buff_types);
    $b_key = $keys[array_rand($keys)];
    $b_val = rand(1, 3);
    // 詛咒不讓 buff 跌到負值（最多讓總值降至基礎值）
    $run['buffs'][$b_key] = max(-$user[$b_key], $run['buffs'][$b_key] - $b_val);
    $current_val = $user[$b_key] + $run['buffs'][$b_key];
    $add_line("<p style='color:#ba68c8;'>👿 遭遇惡毒的詛咒！你感覺力量正在流失...<br><span style='color:#e53935;'>{$buff_types[$b_key]} -$b_val (當前: $current_val)</span></p>", 1000);

} elseif ($event === 'blessing') {
    // 祝福每次固定增加基礎值的 20%，避免複利無限成長
    $max_hp_cap = $user['max_hp'] * 2; // 上限：基礎 max_hp 的 2 倍
    $run['buffs']['dmg']    += ceil($user['dmg']    * 0.2);
    $run['buffs']['def']    += ceil($user['def']    * 0.2);
    $new_hp_buff = $run['buffs']['max_hp'] + ceil($user['max_hp'] * 0.2);
    $run['buffs']['max_hp'] = min($max_hp_cap - $user['max_hp'], $new_hp_buff);
    $run['hp'] = $user['max_hp'] + $run['buffs']['max_hp'];
    $add_line("<p style='color:#ffd700; font-weight:bold; font-size: 16px;'>✨ 奇蹟降臨！神明的祝福籠罩著你！<br>生命值完全恢復，且全屬性提升 20%！</p>", 1500);
}
?>