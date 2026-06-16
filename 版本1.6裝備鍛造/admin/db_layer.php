<?php
require_once 'auth.php';
require_once '../db.php';
require_once '../lib/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
    die('安全驗證失敗，請重新整理頁面後再試。');
}

if (!function_exists('level_exp_required')) {
    function level_exp_required($level) {
        $level = max(1, (int)$level);
        return 25 * $level * ($level + 1) + 50;
    }
}

if (!function_exists('levelup_stat_bonus')) {
    function levelup_stat_bonus($level) {
        $level = max(1, (int)$level);
        $tier = intdiv($level - 1, 3) + 1;

        return [
            'hp' => 10 * $tier,
            'dmg' => 3 * $tier,
            'def' => 1 * $tier,
            'tier' => $tier,
        ];
    }
}

function db_scalar($conn, $sql, $default = 0) {
    $res = $conn->query($sql);
    if ($res === false) {
        return $default;
    }

    $row = $res->fetch_row();
    return $row[0] ?? $default;
}

// ── DB 連線資訊 ──
$db_ver = db_scalar($conn, "SELECT VERSION()", '未知');
$db_size = db_scalar(
    $conn,
    "SELECT ROUND(SUM(data_length + index_length) / 1024, 1)
     FROM information_schema.tables
     WHERE table_schema = 'targame'",
    0
);
$conn_id = $conn->thread_id;

// ── 各資料表統計 ──
$tables = [
    'users',
    'user_skills',
    'user_equipment',
    'user_skill_build',
    'monster_stats',
    'battle_logs',
    'training_logs',
    'pvp_rankings',
    'pvp_battles',
    'api_logs',
    'admin_users',
];

$table_stats = [];
foreach ($tables as $t) {
    $safe_table = $conn->real_escape_string($t);
    $count_res = $conn->query("SELECT COUNT(*) FROM `$safe_table`");
    $count = ($count_res !== false) ? ($count_res->fetch_row()[0] ?? 0) : '—';

    $size_res = $conn->query("
        SELECT ROUND((data_length + index_length) / 1024, 1) AS size_kb
        FROM information_schema.tables
        WHERE table_schema = 'targame'
          AND table_name = '$safe_table'
    ");
    $size_row = ($size_res !== false) ? $size_res->fetch_assoc() : null;

    $table_stats[$t] = [
        'rows' => $count,
        'size_kb' => $size_row['size_kb'] ?? 0,
    ];
}

// ── 怪物生成預覽（POST）──
$preview_monster = null;
if (isset($_POST['preview_floor'])) {
    $preview_floor = max(1, min(20, (int)$_POST['preview_floor']));
    $preview_type = ($_POST['preview_type'] ?? 'mob') === 'boss' ? 'boss' : 'mob';
    $preview_monster = generate_monster($conn, $preview_floor, $preview_type);
}

// ── 傷害計算模擬（POST）──
$dmg_sim = null;
if (isset($_POST['sim_atk'])) {
    $sim_atk = max(1, (int)$_POST['sim_atk']);
    $sim_def = max(0, (int)$_POST['sim_def']);
    $sim_crit = max(0, min(100, (int)$_POST['sim_crit']));
    $sim_dodge = max(0, min(100, (int)$_POST['sim_dodge']));

    $total_dmg = 0;
    $crits = 0;
    $dodges = 0;

    for ($i = 0; $i < 100; $i++) {
        $r = calculate_damage($sim_atk, $sim_def, $sim_crit, $sim_dodge);
        $total_dmg += $r['damage'];

        if ($r['crit']) {
            $crits++;
        }

        if ($r['dodged']) {
            $dodges++;
        }
    }

    $dmg_sim = [
        'avg' => round($total_dmg / 100, 1),
        'crit_pct' => $crits,
        'dodge_pct' => $dodges,
        'atk' => $sim_atk,
        'def' => $sim_def,
        'crit' => $sim_crit,
        'dodge' => $sim_dodge,
    ];
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>資料庫層 — 後台管理</title>
<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/admin.css">
</head>
<body>
<?php include '_sidebar.php'; ?>

    <div class="admin-topbar">
        <div class="page-title">🗄️ 資料庫層</div>
        <div class="breadcrumb">後台管理 / <span>資料庫層</span></div>
    </div>

    <div class="content">

        <!-- ── DB 連線狀態 ── -->
        <div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px;">
            <div class="stat-card green">
                <div class="label">連線狀態</div>
                <div class="value">正常</div>
                <div class="sub">mysqli thread #<?= htmlspecialchars((string)$conn_id) ?></div>
            </div>

            <div class="stat-card blue">
                <div class="label">資料庫版本</div>
                <div class="value" style="font-size:22px;"><?= htmlspecialchars((string)$db_ver) ?></div>
                <div class="sub">MariaDB / MySQL</div>
            </div>

            <div class="stat-card yellow">
                <div class="label">資料庫大小</div>
                <div class="value"><?= number_format((float)$db_size, 1) ?> <span style="font-size:18px;">KB</span></div>
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
                <thead>
                    <tr>
                        <th>資料表</th>
                        <th>用途</th>
                        <th>資料筆數</th>
                        <th>大小</th>
                        <th>狀態</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $table_labels = [
                        'users' => '玩家主資料',
                        'user_skills' => '被動技能熟練度',
                        'user_equipment' => '裝備強化資料',
                        'user_skill_build' => '技能樹流派資料',
                        'monster_stats' => '怪物等級數值',
                        'battle_logs' => '爬塔戰鬥紀錄',
                        'training_logs' => '訓練紀錄',
                        'pvp_rankings' => 'PVP 積分排名',
                        'pvp_battles' => 'PVP 對戰紀錄',
                        'api_logs' => 'API 呼叫紀錄',
                        'admin_users' => '後台管理員',
                    ];

                    foreach ($tables as $table):
                        $rows = $table_stats[$table]['rows'];
                        $exists = ($rows !== '—');
                    ?>
                    <tr>
                        <td><b style="color:#4fc3f7;"><?= htmlspecialchars($table) ?></b></td>
                        <td><?= htmlspecialchars($table_labels[$table] ?? '—') ?></td>
                        <td style="color:#ffca28;"><?= is_numeric($rows) ? number_format((int)$rows) : '—' ?></td>
                        <td style="color:#94a3b8;"><?= number_format((float)$table_stats[$table]['size_kb'], 1) ?> KB</td>
                        <td>
                            <?php if ($exists): ?>
                                <span class="badge" style="border-color:#66bb6a;color:#66bb6a;">OK</span>
                            <?php else: ?>
                                <span class="badge" style="border-color:#ef5350;color:#ef5350;">MISSING</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ── 工具區 ── -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">

            <!-- 怪物生成預覽 -->
            <div class="section">
                <div class="section-header">
                    <h3>👹 怪物生成預覽</h3>
                    <span class="badge">generate_monster()</span>
                </div>

                <form method="POST" style="display:grid;grid-template-columns:1fr 1fr auto;gap:10px;margin-bottom:18px;">
                    <?= csrf_field() ?>
                    <input
                        type="number"
                        name="preview_floor"
                        min="1"
                        max="20"
                        value="<?= htmlspecialchars($_POST['preview_floor'] ?? '1') ?>"
                        style="background:#0d0d1a;border:1px solid #2a2a4a;border-radius:7px;color:#e0e0e0;padding:10px;"
                    >

                    <select
                        name="preview_type"
                        style="background:#0d0d1a;border:1px solid #2a2a4a;border-radius:7px;color:#e0e0e0;padding:10px;"
                    >
                        <option value="mob" <?= ($_POST['preview_type'] ?? '') === 'mob' ? 'selected' : '' ?>>小怪</option>
                        <option value="boss" <?= ($_POST['preview_type'] ?? '') === 'boss' ? 'selected' : '' ?>>BOSS</option>
                    </select>

                    <button type="submit" class="btn btn-primary">預覽</button>
                </form>

                <?php if ($preview_monster): ?>
                <?php
                    $stats = $preview_monster['stats'];
                    $type_label = $preview_monster['type'] === 'boss' ? 'BOSS' : '小怪';
                    $name = $preview_monster['type'] === 'boss'
                        ? $preview_monster['boss_name']
                        : $preview_monster['mob_name'];
                ?>
                <div style="background:#1a1a2e;border:1px solid #2a2a4a;border-radius:10px;padding:16px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                        <div>
                            <div style="font-size:17px;font-weight:700;color:#e0e0e0;">
                                <?= htmlspecialchars($name) ?>
                            </div>
                            <div style="font-size:12px;color:#8899b0;margin-top:3px;">
                                第 <?= (int)$preview_monster['floor'] ?> 層 · <?= $type_label ?> · Lv.<?= (int)$stats['level'] ?>
                            </div>
                        </div>
                        <?php if ($preview_monster['is_special']): ?>
                            <span class="badge" style="border-color:#ffca28;color:#ffca28;">特殊層</span>
                        <?php endif; ?>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px;text-align:center;">
                        <div style="background:#0d0d1a;padding:10px;border-radius:7px;">
                            <div style="color:#ef5350;font-weight:700;"><?= number_format((int)$stats['hp']) ?></div>
                            <div style="font-size:11px;color:#666;">HP</div>
                        </div>
                        <div style="background:#0d0d1a;padding:10px;border-radius:7px;">
                            <div style="color:#ff8a65;font-weight:700;"><?= number_format((int)$stats['dmg']) ?></div>
                            <div style="font-size:11px;color:#666;">DMG</div>
                        </div>
                        <div style="background:#0d0d1a;padding:10px;border-radius:7px;">
                            <div style="color:#4fc3f7;font-weight:700;"><?= number_format((int)$stats['def']) ?></div>
                            <div style="font-size:11px;color:#666;">DEF</div>
                        </div>
                        <div style="background:#0d0d1a;padding:10px;border-radius:7px;">
                            <div style="color:#66bb6a;font-weight:700;"><?= number_format((int)$stats['exp']) ?></div>
                            <div style="font-size:11px;color:#666;">EXP</div>
                        </div>
                        <div style="background:#0d0d1a;padding:10px;border-radius:7px;">
                            <div style="color:#ffca28;font-weight:700;"><?= number_format((int)$stats['gold']) ?></div>
                            <div style="font-size:11px;color:#666;">GOLD</div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div style="text-align:center;color:#444;padding:30px;">輸入樓層後點擊預覽</div>
                <?php endif; ?>
            </div>

            <!-- 傷害計算模擬 -->
            <div class="section">
                <div class="section-header">
                    <h3>⚔️ 傷害計算模擬</h3>
                    <span class="badge">calculate_damage()</span>
                </div>

                <form method="POST" style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:18px;">
                    <?= csrf_field() ?>
                    <input
                        type="number"
                        name="sim_atk"
                        min="1"
                        value="<?= htmlspecialchars($_POST['sim_atk'] ?? '100') ?>"
                        placeholder="ATK"
                        style="background:#0d0d1a;border:1px solid #2a2a4a;border-radius:7px;color:#e0e0e0;padding:10px;"
                    >

                    <input
                        type="number"
                        name="sim_def"
                        min="0"
                        value="<?= htmlspecialchars($_POST['sim_def'] ?? '20') ?>"
                        placeholder="DEF"
                        style="background:#0d0d1a;border:1px solid #2a2a4a;border-radius:7px;color:#e0e0e0;padding:10px;"
                    >

                    <input
                        type="number"
                        name="sim_crit"
                        min="0"
                        max="100"
                        value="<?= htmlspecialchars($_POST['sim_crit'] ?? '10') ?>"
                        placeholder="CRIT %"
                        style="background:#0d0d1a;border:1px solid #2a2a4a;border-radius:7px;color:#e0e0e0;padding:10px;"
                    >

                    <input
                        type="number"
                        name="sim_dodge"
                        min="0"
                        max="100"
                        value="<?= htmlspecialchars($_POST['sim_dodge'] ?? '10') ?>"
                        placeholder="DODGE %"
                        style="background:#0d0d1a;border:1px solid #2a2a4a;border-radius:7px;color:#e0e0e0;padding:10px;"
                    >

                    <button type="submit" class="btn btn-primary" style="grid-column:1 / -1;">模擬 100 次</button>
                </form>

                <?php if ($dmg_sim): ?>
                <div style="background:#0d0d1a;border:1px solid #2a2a4a;border-radius:10px;padding:16px;">
                    <div style="font-size:13px;color:#8899b0;margin-bottom:12px;">
                        ATK <?= number_format($dmg_sim['atk']) ?> /
                        DEF <?= number_format($dmg_sim['def']) ?> /
                        CRIT <?= number_format($dmg_sim['crit']) ?>% /
                        DODGE <?= number_format($dmg_sim['dodge']) ?>%
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;text-align:center;">
                        <div style="background:#1a1a2e;padding:12px;border-radius:7px;">
                            <div style="font-size:22px;font-weight:700;color:#4fc3f7;"><?= number_format($dmg_sim['avg'], 1) ?></div>
                            <div style="font-size:11px;color:#666;">平均傷害</div>
                        </div>
                        <div style="background:#1a1a2e;padding:12px;border-radius:7px;">
                            <div style="font-size:22px;font-weight:700;color:#ffca28;"><?= number_format($dmg_sim['crit_pct']) ?>%</div>
                            <div style="font-size:11px;color:#666;">實際爆擊率</div>
                        </div>
                        <div style="background:#1a1a2e;padding:12px;border-radius:7px;">
                            <div style="font-size:22px;font-weight:700;color:#66bb6a;"><?= number_format($dmg_sim['dodge_pct']) ?>%</div>
                            <div style="font-size:11px;color:#666;">實際閃避率</div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div style="text-align:center;color:#444;padding:30px;">輸入參數後點擊模擬</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── 升級閾值表 ── -->
        <div class="section">
            <div class="section-header">
                <h3>📈 升級閾值查詢表</h3>
                <span class="badge">process_levelup()</span>
            </div>

            <table class="tbl">
                <thead>
                    <tr>
                        <th>等級</th>
                        <th>升級所需 EXP</th>
                        <th>累計 EXP</th>
                        <th>升級後 HP+</th>
                        <th>升級後傷害+</th>
                        <th>升級後防禦+</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $cum = 0;
                    for ($lv = 1; $lv <= 20; $lv++):
                        $need = level_exp_required($lv);
                        $bonus = levelup_stat_bonus($lv);
                    ?>
                    <tr>
                        <td><b style="color:#4fc3f7;">Lv.<?= $lv ?></b></td>
                        <td style="color:#ffca28;"><?= number_format($need) ?> EXP</td>
                        <td style="color:#94a3b8;"><?= number_format($cum) ?></td>
                        <td style="color:#66bb6a;">+<?= number_format($bonus['hp']) ?></td>
                        <td style="color:#ef5350;">+<?= number_format($bonus['dmg']) ?></td>
                        <td style="color:#4fc3f7;">+<?= number_format($bonus['def']) ?></td>
                    </tr>
                    <?php
                        $cum += $need;
                    endfor;
                    ?>
                </tbody>
            </table>
        </div>

    </div><!-- /content -->
</div><!-- /main -->
</body>
</html>