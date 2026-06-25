<?php
ob_start();
session_start();
date_default_timezone_set('Asia/Taipei');
require 'db.php';
require_once 'lib/session.php';
require_once 'lib/functions.php';

if (!isset($_SESSION['player_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['player_id'];
$_csrf = csrf_token();

$user = $conn->query("SELECT username, gold, level FROM users WHERE id=$user_id")->fetch_assoc();
$eq = get_equipment($conn, $user_id);


$equip_info = [
    'weapon' => [
        'name' => '武器',
        'icon' => '⚔️',
        'stat' => 'ATK',
        'color' => '#ef5350',
    ],
    'armor' => [
        'name' => '護甲',
        'icon' => '🛡️',
        'stat' => 'DEF',
        'color' => '#4fc3f7',
    ],
    'helmet' => [
        'name' => '頭盔',
        'icon' => '🪖',
        'stat' => 'HP',
        'color' => '#66bb6a',
    ],
];

$max_level = FORGE_MAX_LEVEL;
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>鍛造 — 塔城傳說</title>
<meta name="csrf-token" content="<?= htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="assets/style.css">
<style>
/* forge 頁面專屬 */
.forge-container { max-width: 1080px; margin: 0 auto; }
.topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px; }
.topbar h1 { font-size:24px; color:var(--accent); letter-spacing:2px; }
.topbar-right { display:flex; gap:14px; align-items:center; flex-wrap:wrap; }
.gold { font-size:16px; color:var(--accent); font-weight:bold; }
.tabs { display:flex; gap:10px; margin-bottom:24px; flex-wrap:wrap; }
.tab { min-width:170px; padding:14px 28px; border-radius:9px; border:1px solid var(--border); background:var(--bg-card); color:var(--text-muted); cursor:pointer; font-size:15px; font-weight:700; transition:all .2s; text-align:left; }
.tab:hover { border-color:var(--accent-blue); color:var(--text-primary); }
.tab.active { border-color:var(--accent); color:var(--accent); background:rgba(255,202,40,.08); }
.tab .tab-level { font-size:12px; color:var(--text-dim); margin-left:6px; }
.tab-content { display:none; }
.tab-content.active { display:block; }
.forge-card { background:var(--bg-card); border:1px solid var(--border); border-radius:16px; padding:34px 36px; margin-bottom:20px; box-shadow:0 12px 28px rgba(0,0,0,.28); }
.equip-header { display:flex; align-items:center; gap:18px; margin-bottom:28px; }
.equip-icon { font-size:54px; width:74px; text-align:center; }
.equip-title h2 { font-size:24px; font-weight:800; }
.equip-title p { font-size:14px; color:var(--text-muted); margin-top:6px; line-height:1.7; }
.level-bar { margin:28px 0 24px; }
.bar-mid { font-weight:700; }
.action-row { display:grid; grid-template-columns:1fr auto; gap:16px; align-items:center; }
.next-box { background:#101629; border:1px solid #25304f; border-radius:12px; padding:16px 18px; display:flex; justify-content:space-between; gap:14px; flex-wrap:wrap; }
.next-box span { font-size:13px; color:var(--text-muted); }
.next-box b { color:var(--accent); }
.btn-upgrade { min-width:210px; padding:15px 24px; border:none; border-radius:10px; background:linear-gradient(135deg,#c79100,#ffca28); color:#111827; font-size:15px; font-weight:900; cursor:pointer; letter-spacing:1px; transition:transform .12s,filter .2s; }
.btn-upgrade:hover { filter:brightness(1.08); }
.btn-upgrade:active { transform:scale(.98); }
.btn-upgrade:disabled { background:#374151; color:#6b7280; cursor:not-allowed; filter:none; }
.max-note { text-align:center; color:var(--accent); font-size:16px; font-weight:800; margin-top:18px; }
.small-note { font-size:12px; color:var(--text-dim); margin-top:12px; text-align:center; line-height:1.7; }
@media(max-width:760px) {
  .tabs{gap:8px;} .tab{flex:1;min-width:100px;text-align:center;padding:12px 10px;}
  .forge-card{padding:24px 18px;} .equip-icon{font-size:42px;width:52px;}
  .equip-title h2{font-size:21px;} .action-row{grid-template-columns:1fr;}
  .btn-upgrade{width:100%;min-width:0;}
}
</style>
</head>
<body>
<?php require '_sidebar.php'; ?>
<div class="page-body">
<div class="forge-container">

<div class="topbar">
    <h1>⚒️ 裝備鍛造</h1>
    <div class="topbar-right">
        <span class="gold">💰 <span id="gold-display"><?= number_format((int)$user['gold']) ?></span> 金</span>
    </div>
</div>

<div class="tabs">
    <?php foreach ($equip_info as $type => $info): ?>
    <?php $lv = (int)$eq[$type]['level']; ?>
    <div class="tab <?= $type === 'weapon' ? 'active' : '' ?>" data-type="<?= $type ?>" onclick="switchTab('<?= $type ?>')">
        <?= $info['icon'] ?> <?= $info['name'] ?>
        <span class="tab-level" id="tab-level-<?= $type ?>">+<?= $lv ?></span>
    </div>
    <?php endforeach; ?>
</div>

<?php foreach ($equip_info as $type => $info): ?>
<?php
$lv = (int)$eq[$type]['level'];
$att = (int)$eq[$type]['attempts'];
$suc = (int)$eq[$type]['successes'];
$fail = max(0, $att - $suc);
$mult = equipment_multiplier($lv);
$mult_pct = ($mult - 1) * 100;
$progress = min(100, ($lv / $max_level) * 100);
$next_cost = $lv < $max_level ? forge_upgrade_cost($lv) : null;
$next_chance = $lv < $max_level ? forge_upgrade_chance($lv) : null;
?>
<div class="tab-content <?= $type === 'weapon' ? 'active' : '' ?>" id="tab-<?= $type ?>">
    <div class="forge-card" data-equip="<?= $type ?>" data-color="<?= $info['color'] ?>">
        <div class="equip-header">
            <div class="equip-icon"><?= $info['icon'] ?></div>
            <div class="equip-title">
                <h2 style="color:<?= $info['color'] ?>;">
                    <?= $info['name'] ?> <span id="<?= $type ?>-title-level">+<?= $lv ?></span>
                </h2>
                <p>
                    <?= $info['stat'] ?> 倍率：
                    <b style="color:<?= $info['color'] ?>;" id="<?= $type ?>-mult-text"><?= number_format($mult, 4) ?>x</b>
                    <span id="<?= $type ?>-mult-pct">（+<?= number_format($mult_pct, 2) ?>%）</span><br>
                    每強化 1 級，對應數值倍率再提升 1%。最高：+<?= $max_level ?>
                </p>
            </div>
        </div>

        <div class="level-bar">
            <div class="bar-track">
                <div class="bar-fill" id="<?= $type ?>-bar" style="width:<?= $progress ?>%;background:<?= $info['color'] ?>;"></div>
            </div>
            <div class="bar-labels">
                <span>+0</span>
                <span class="bar-mid" id="<?= $type ?>-bar-mid" style="color:<?= $info['color'] ?>;">+<?= $lv ?> / +<?= $max_level ?></span>
                <span>+<?= $max_level ?></span>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-card">
                <div class="value" style="color:<?= $info['color'] ?>;" id="<?= $type ?>-mult-card"><?= number_format($mult, 4) ?>x</div>
                <div class="label">目前倍率</div>
            </div>
            <div class="info-card">
                <div class="value" style="color:#4fc3f7;" id="<?= $type ?>-attempts"><?= number_format($att) ?></div>
                <div class="label">累計嘗試</div>
            </div>
            <div class="info-card">
                <div class="value" style="color:#66bb6a;">
                    <span id="<?= $type ?>-successes"><?= number_format($suc) ?></span>
                    <span style="font-size:15px;color:#64748b;"> / </span>
                    <span id="<?= $type ?>-fails" style="font-size:15px;color:#94a3b8;"><?= number_format($fail) ?> 失</span>
                </div>
                <div class="label">成功 / 失敗</div>
            </div>
            <div class="info-card">
                <div class="value" style="color:#ffca28;" id="<?= $type ?>-chance-card">
                    <?= $next_chance !== null ? $next_chance . '%' : 'MAX' ?>
                </div>
                <div class="label">下級成功率</div>
            </div>
        </div>

        <?php if ($lv >= $max_level): ?>
            <div class="max-note" id="<?= $type ?>-max-note">🏆 已達最高等級 +<?= $max_level ?>！</div>
            <button type="button" class="btn-upgrade" id="<?= $type ?>-btn" disabled style="display:none;">已達最高</button>
        <?php else: ?>
            <div class="action-row" id="<?= $type ?>-action-row">
                <div class="next-box">
                    <span>下一級：<b id="<?= $type ?>-next-level">+<?= $lv + 1 ?></b></span>
                    <span>費用：<b id="<?= $type ?>-next-cost"><?= number_format($next_cost) ?></b> 金</span>
                    <span>成功率：<b id="<?= $type ?>-next-chance"><?= $next_chance ?></b>%</span>
                </div>
                <button type="button" class="btn-upgrade" id="<?= $type ?>-btn" onclick="upgradeEquip('<?= $type ?>')">
                    強化 <?= $info['name'] ?>
                </button>
            </div>
            <div class="max-note" id="<?= $type ?>-max-note" style="display:none;">🏆 已達最高等級 +<?= $max_level ?>！</div>
        <?php endif; ?>

        <div class="small-note">
            強化無論成功或失敗都會消耗金幣。成功時等級 +1；失敗時等級不變。
        </div>
    </div>
</div>
<?php endforeach; ?>

<div id="toast"></div>

<script>
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
const MAX_LEVEL = <?= (int)$max_level ?>;
const EQUIP_INFO = <?= json_encode($equip_info, JSON_UNESCAPED_UNICODE) ?>;

function calcMultiplier(level) {
    level = Math.max(0, Math.min(MAX_LEVEL, parseInt(level || 0, 10)));
    return Math.pow(1.01, level);
}

function calcCost(level) {
    level = Math.max(0, parseInt(level || 0, 10));
    const next = level + 1;
    return Math.floor(100 * next * (next + 1) / 2);
}

function calcChance(level) {
    level = Math.max(0, parseInt(level || 0, 10));
    const next = level + 1;

    if (next > MAX_LEVEL) return 0;

    const block = Math.floor((next - 1) / 10);
    const step = (next - 1) % 10;
    const chance = 100 - (block * 10) - (step * 2);

    return Math.max(1, Math.min(100, chance));
}

function fmtNumber(value) {
    return Number(value || 0).toLocaleString('en-US');
}

function fmtMult(value) {
    return Number(value || 1).toFixed(4) + 'x';
}

function switchTab(type) {
    localStorage.setItem('forge_active_tab', type);

    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.toggle('active', tab.dataset.type === type);
    });

    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.toggle('active', content.id === 'tab-' + type);
    });
}

function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    toast.textContent = message || '操作完成';
    toast.className = '';
    toast.classList.add(type, 'show');

    clearTimeout(window.__forgeToastTimer);
    window.__forgeToastTimer = setTimeout(() => {
        toast.classList.remove('show');
    }, 3200);
}

function updateEquipUI(type, item) {
    if (!item) return;

    const level = parseInt(item.level || 0, 10);
    const attempts = parseInt(item.attempts || 0, 10);
    const successes = parseInt(item.successes || 0, 10);
    const fails = Math.max(0, attempts - successes);
    const mult = item.multiplier ? Number(item.multiplier) : calcMultiplier(level);
    const multPct = (mult - 1) * 100;
    const color = EQUIP_INFO[type]?.color || '#4fc3f7';

    document.getElementById('tab-level-' + type).textContent = '+' + level;
    document.getElementById(type + '-title-level').textContent = '+' + level;
    document.getElementById(type + '-mult-text').textContent = fmtMult(mult);
    document.getElementById(type + '-mult-pct').textContent = '（+' + multPct.toFixed(2) + '%）';
    document.getElementById(type + '-bar').style.width = Math.min(100, (level / MAX_LEVEL) * 100) + '%';
    document.getElementById(type + '-bar').style.background = color;
    document.getElementById(type + '-bar-mid').textContent = '+' + level + ' / +' + MAX_LEVEL;
    document.getElementById(type + '-mult-card').textContent = fmtMult(mult);
    document.getElementById(type + '-attempts').textContent = fmtNumber(attempts);
    document.getElementById(type + '-successes').textContent = fmtNumber(successes);
    document.getElementById(type + '-fails').textContent = fmtNumber(fails) + ' 失';

    const chanceCard = document.getElementById(type + '-chance-card');
    const actionRow = document.getElementById(type + '-action-row');
    const maxNote = document.getElementById(type + '-max-note');
    const btn = document.getElementById(type + '-btn');

    if (level >= MAX_LEVEL || item.maxed) {
        chanceCard.textContent = 'MAX';
        if (actionRow) actionRow.style.display = 'none';
        if (maxNote) maxNote.style.display = 'block';
        if (btn) {
            btn.disabled = true;
            btn.style.display = 'none';
        }
        return;
    }

    const nextCost = item.next_cost ?? calcCost(level);
    const nextChance = item.next_chance ?? calcChance(level);

    chanceCard.textContent = nextChance + '%';
    if (actionRow) actionRow.style.display = 'grid';
    if (maxNote) maxNote.style.display = 'none';
    if (btn) {
        btn.disabled = false;
        btn.style.display = '';
    }

    document.getElementById(type + '-next-level').textContent = '+' + (level + 1);
    document.getElementById(type + '-next-cost').textContent = fmtNumber(nextCost);
    document.getElementById(type + '-next-chance').textContent = nextChance;
}

async function refreshStatus(keepType = null) {
    try {
        const res = await fetch('api/forge.php?action=get_status', {
            credentials: 'same-origin',
        });
        const data = await res.json();

        if (!data.success) {
            showToast(data.message || '讀取鍛造狀態失敗', 'err');
            return;
        }

        if (typeof data.gold !== 'undefined') {
            document.getElementById('gold-display').textContent = fmtNumber(data.gold);
        }

        Object.keys(data.equipment || {}).forEach(type => {
            updateEquipUI(type, data.equipment[type]);
        });

        if (keepType) {
            switchTab(keepType);
        }
    } catch (err) {
        showToast('鍛造狀態讀取失敗，請稍後再試', 'err');
    }
}

async function upgradeEquip(type) {
    const btn = document.getElementById(type + '-btn');
    if (!btn || btn.disabled) return;

    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = '強化中...';

    try {
        const body = new URLSearchParams();
        body.set('action', 'upgrade');
        body.set('type', type);
        body.set('csrf_token', CSRF_TOKEN);

        const res = await fetch('api/forge.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': CSRF_TOKEN,
            },
            body,
            credentials: 'same-origin',
        });

        const data = await res.json();

        if (typeof data.gold !== 'undefined') {
            document.getElementById('gold-display').textContent = fmtNumber(data.gold);
        }

        showToast(data.message || '強化完成', data.leveled_up ? 'ok' : 'err');

        await refreshStatus(type);
        switchTab(type);
    } catch (err) {
        showToast('強化請求失敗，請稍後再試', 'err');
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const saved = localStorage.getItem('forge_active_tab') || 'weapon';
    switchTab(saved);
});
</script>

</div><!-- /forge-container -->
</div><!-- /page-body -->
</body>
</html>