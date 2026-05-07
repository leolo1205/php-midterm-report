<?php
require_once 'auth.php';
require_once '../db.php';
require_once '../lib/functions.php';

// ── DB 連線資訊 ──
$db_ver   = $conn->query("SELECT VERSION() AS v")->fetch_assoc()['v'];
$db_size  = $conn->query("SELECT ROUND(SUM(data_length+index_length)/1024,1) AS s FROM information_schema.tables WHERE table_schema='targame'")->fetch_assoc()['s'];
$conn_id  = $conn->thread_id;

// ── 各資料表統計 ──
$tables = ['users','user_skills','monster_stats','battle_logs','training_logs','api_logs','admin_users'];
$table_stats = [];
foreach ($tables as $t) {
    $cnt  = $conn->query("SELECT COUNT(*) FROM `$t`")->fetch_row()[0] ?? 0;
    $info = $conn->query("SELECT ROUND((data_length+index_length)/1024,1) AS sz FROM information_schema.tables WHERE table_schema='targame' AND table_name='$t'")->fetch_assoc();
    $table_stats[$t] = ['rows' => $cnt, 'size_kb' => $info['sz'] ?? 0];
}

// ── 怪物生成預覽（POST）──
$preview_monster = null;
if (isset($_POST['preview_floor'])) {
    $pf   = max(1, min(20, (int)$_POST['preview_floor']));
    $pt   = $_POST['preview_type'] ?? 'mob';
    $preview_monster = generate_monster($conn, $pf, $pt);
}

// ── 傷害計算模擬（POST）──
$dmg_sim = null;
if (isset($_POST['sim_atk'])) {
    $runs = [];
    $s_atk   = max(1,(int)$_POST['sim_atk']);
    $s_def   = max(0,(int)$_POST['sim_def']);
    $s_crit  = max(0,min(100,(int)$_POST['sim_crit']));
    $s_dodge = max(0,min(100,(int)$_POST['sim_dodge']));
    $total_dmg = 0; $crits = 0; $dodges = 0;
    for ($i = 0; $i < 100; $i++) {
        $r = calculate_damage($s_atk, $s_def, $s_crit, $s_dodge);
        $total_dmg += $r['damage'];
        if ($r['crit'])   $crits++;
        if ($r['dodged']) $dodges++;
    }
    $dmg_sim = ['avg' => round($total_dmg/100,1), 'crit_pct' => $crits, 'dodge_pct' => $dodges, 'atk'=>$s_atk,'def'=>$s_def,'crit'=>$s_crit,'dodge'=>$s_dodge];
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>資料庫層 — 後台管理</title>
<?php include '_sidebar.php'; ?>

  <div class="topbar">
    <div class="page-title">🗄️ 資料庫層</div>
    <div class="breadcrumb">後台管理 / <span>資料庫層</span></div>
  </div>

  <div class="content">

    <!-- ── DB 連線狀態 ── -->
    <div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px;">
      <div class="stat-card green">
        <div class="label">連線狀態</div>
        <div class="value" style="font-size:22px;">✅ 已連線</div>
        <div class="sub">Thread ID: <?= $conn_id ?></div>
      </div>
      <div class="stat-card blue">
        <div class="label">MariaDB 版本</div>
        <div class="value" style="font-size:18px;"><?= $db_ver ?></div>
        <div class="sub">資料庫: targame</div>
      </div>
      <div class="stat-card yellow">
        <div class="label">資料庫大小</div>
        <div class="value"><?= $db_size ?> <span style="font-size:18px;">KB</span></div>
        <div class="sub">所有資料表合計</div>
      </div>
      <div class="stat-card">
        <div class="label">資料表數量</div>
        <div class="value" style="color:#e040fb;"><?= count($tables) ?></div>
        <div class="sub">targame 資料庫</div>
      </div>
    </div>

    <!-- ── 資料表概覽 ── -->
    <div class="section" style="margin-bottom:24px;">
      <div class="section-header">
        <h3>📋 資料表概覽</h3>
        <span class="badge">共用函式庫 · 資料來源</span>
      </div>
      <table class="tbl">
        <thead><tr>
          <th>資料表名稱</th><th>用途說明</th><th>筆數</th><th>大小 (KB)</th><th>狀態</th>
        </tr></thead>
        <tbody>
        <?php
        $desc = [
            'users'         => '玩家帳號、屬性、進度',
            'user_skills'   => '玩家技能熟練度',
            'monster_stats' => '怪物等級屬性資料（怪物生成來源）',
            'battle_logs'   => '戰鬥記錄（勝/敗/逃）',
            'training_logs' => '訓練記錄',
            'api_logs'      => 'API 呼叫記錄',
            'admin_users'   => '管理員帳號',
        ];
        foreach ($table_stats as $t => $s): ?>
        <tr>
          <td><code style="color:#4fc3f7;background:#0d0d1a;padding:2px 8px;border-radius:4px;"><?= $t ?></code></td>
          <td style="color:#94a3b8;"><?= $desc[$t] ?? '—' ?></td>
          <td><b><?= number_format($s['rows']) ?></b></td>
          <td><?= $s['size_kb'] ?></td>
          <td><span class="tag tag-active">正常</span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">

      <!-- ── 怪物生成預覽 ── -->
      <div class="section">
        <div class="section-header"><h3>🐉 怪物生成預覽</h3><span class="badge">generate_monster()</span></div>
        <div style="padding:20px;">
          <form method="POST" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
            <select name="preview_floor" style="padding:9px 14px;background:#0d0d1a;border:1px solid #2a2a4a;border-radius:7px;color:#e0e0e0;">
              <?php for($i=1;$i<=20;$i++): ?>
              <option value="<?=$i?>" <?=(isset($pf)&&$pf==$i)?'selected':''?>>第 <?=$i?> 層</option>
              <?php endfor; ?>
            </select>
            <select name="preview_type" style="padding:9px 14px;background:#0d0d1a;border:1px solid #2a2a4a;border-radius:7px;color:#e0e0e0;">
              <option value="mob"  <?=(isset($pt)&&$pt==='mob') ?'selected':''?>>普通怪物</option>
              <option value="boss" <?=(isset($pt)&&$pt==='boss')?'selected':''?>>BOSS</option>
            </select>
            <button type="submit" class="btn btn-primary">生成預覽</button>
          </form>
          <?php if ($preview_monster): $s = $preview_monster['stats']; ?>
          <div style="background:#0d0d1a;border:1px solid #2a2a4a;border-radius:8px;padding:16px;">
            <div style="font-size:15px;font-weight:700;color:#ffca28;margin-bottom:12px;">
              <?= $preview_monster['type']==='boss'
                ? "💀 BOSS: Lv.{$preview_monster['boss_level']} {$preview_monster['boss_name']}"
                : "🐺 Lv.{$preview_monster['mob_level']} {$preview_monster['mob_name']}" ?>
              <?php if ($preview_monster['is_special'] && $preview_monster['type']==='boss'): ?>
              <span class="tag tag-lose" style="margin-left:8px;">特殊BOSS</span>
              <?php endif; ?>
            </div>
            <?php
            $hp  = $s['hp'];  $dmg = $s['dmg']; $def = $s['def'];
            $exp = $s['exp']; $gold= $s['gold'];
            if ($preview_monster['is_special'] && $preview_monster['type']==='boss' && $preview_monster['special_data']) {
                $sd = $preview_monster['special_data'];
                $hp  = floor($hp  * $sd['hp_mult']);
                $dmg = floor($dmg * $sd['dmg_mult']);
                $def = floor($def * $sd['def_mult']);
            }
            ?>
            <table style="width:100%;font-size:13px;">
              <tr><td style="color:#666;padding:4px 0;">HP</td><td style="color:#ef5350;font-weight:700;"><?= number_format($hp) ?></td>
                  <td style="color:#666;padding:4px 0;">傷害</td><td style="color:#ff9800;font-weight:700;"><?= $dmg ?></td></tr>
              <tr><td style="color:#666;padding:4px 0;">防禦</td><td style="color:#66bb6a;"><?= $def ?></td>
                  <td style="color:#666;padding:4px 0;">EXP</td><td style="color:#4fc3f7;"><?= $exp ?></td></tr>
              <tr><td style="color:#666;padding:4px 0;">金幣</td><td style="color:#ffca28;">💰<?= $gold ?></td>
                  <td style="color:#666;padding:4px 0;">樓層</td><td><?= $preview_monster['floor'] ?>F</td></tr>
            </table>
          </div>
          <?php else: ?>
          <div style="text-align:center;color:#444;padding:30px;">選擇樓層與類型後點擊生成</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ── 傷害計算模擬 ── -->
      <div class="section">
        <div class="section-header"><h3>⚔️ 傷害計算模擬器</h3><span class="badge">calculate_damage()</span></div>
        <div style="padding:20px;">
          <form method="POST" style="margin-bottom:20px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
              <?php
              $fields = [['sim_atk','攻擊力',$dmg_sim['atk']??50],['sim_def','防禦力',$dmg_sim['def']??10],
                         ['sim_crit','爆擊率 (%)',$dmg_sim['crit']??15],['sim_dodge','閃避率 (%)',$dmg_sim['dodge']??10]];
              foreach ($fields as [$name,$label,$val]): ?>
              <div>
                <div style="font-size:11px;color:#94a3b8;margin-bottom:5px;letter-spacing:1px;"><?= $label ?></div>
                <input type="number" name="<?= $name ?>" value="<?= $val ?>" min="0" max="9999"
                  style="width:100%;padding:9px 12px;background:#0d0d1a;border:1px solid #2a2a4a;border-radius:7px;color:#e0e0e0;">
              </div>
              <?php endforeach; ?>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">模擬 100 次攻擊</button>
          </form>
          <?php if ($dmg_sim): ?>
          <div style="background:#0d0d1a;border:1px solid #2a2a4a;border-radius:8px;padding:16px;">
            <div style="font-size:13px;color:#94a3b8;margin-bottom:10px;">100 次攻擊模擬結果：</div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;text-align:center;">
              <div style="background:#1a1a2e;padding:12px;border-radius:7px;">
                <div style="font-size:22px;font-weight:700;color:#4fc3f7;"><?= $dmg_sim['avg'] ?></div>
                <div style="font-size:11px;color:#666;">平均傷害</div>
              </div>
              <div style="background:#1a1a2e;padding:12px;border-radius:7px;">
                <div style="font-size:22px;font-weight:700;color:#ffca28;"><?= $dmg_sim['crit_pct'] ?>%</div>
                <div style="font-size:11px;color:#666;">實際爆擊率</div>
              </div>
              <div style="background:#1a1a2e;padding:12px;border-radius:7px;">
                <div style="font-size:22px;font-weight:700;color:#66bb6a;"><?= $dmg_sim['dodge_pct'] ?>%</div>
                <div style="font-size:11px;color:#666;">實際閃避率</div>
              </div>
            </div>
          </div>
          <?php else: ?>
          <div style="text-align:center;color:#444;padding:30px;">輸入參數後點擊模擬</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ── 升級閾值表 ── -->
    <div class="section">
      <div class="section-header"><h3>📈 升級閾值查詢表</h3><span class="badge">process_levelup()</span></div>
      <table class="tbl">
        <thead><tr>
          <th>等級</th><th>升級所需 EXP</th><th>累計 EXP</th><th>升級後 HP+</th><th>升級後傷害+</th><th>升級後防禦+</th>
        </tr></thead>
        <tbody>
        <?php
        $cum = 0;
        for ($lv = 1; $lv <= 20; $lv++):
            $need = $lv * 100;
            $cum += ($lv > 1 ? ($lv-1)*100 : 0);
        ?>
        <tr>
          <td><b style="color:#4fc3f7;">Lv.<?= $lv ?></b></td>
          <td style="color:#ffca28;"><?= number_format($need) ?> EXP</td>
          <td style="color:#94a3b8;"><?= number_format($cum) ?></td>
          <td style="color:#66bb6a;">+10</td>
          <td style="color:#ef5350;">+3</td>
          <td style="color:#4fc3f7;">+1</td>
        </tr>
        <?php endfor; ?>
        </tbody>
      </table>
    </div>

  </div><!-- /content -->
</div><!-- /main -->
</body>
</html>
