<?php
ob_start();
session_start();
date_default_timezone_set('Asia/Taipei');
require 'db.php';
require_once 'lib/session.php';
require_once 'lib/functions.php';

if (!isset($_SESSION['player_id'])) { header('Location: login.php'); exit; }
$user_id = (int)$_SESSION['player_id'];
$user    = $conn->query("SELECT username, gold, level FROM users WHERE id=$user_id")->fetch_assoc();
$build   = get_skill_build($conn, $user_id);
$bonus   = get_skill_stat_bonus($build);
$costs   = get_node_costs();
$all_nodes = get_archetype_nodes();
$_csrf   = csrf_token();

$arch_info = [
    'assault'  => ['name'=>'攻擊流',  'icon'=>'⚔️',  'color'=>'#ef5350', 'color2'=>'#b71c1c',
                   'desc'=>'真實傷害流派，透過生命比例傷害克制血量流', 'beats'=>'血量流'],
    'guardian' => ['name'=>'防禦流',  'icon'=>'🛡️',  'color'=>'#4fc3f7', 'color2'=>'#0277bd',
                   'desc'=>'荊棘反傷流派，累積傷害反擊克制攻擊流',    'beats'=>'攻擊流'],
    'vitality' => ['name'=>'血量流',  'icon'=>'💚',  'color'=>'#66bb6a', 'color2'=>'#2e7d32',
                   'desc'=>'回復侵蝕流派，持續削弱克制防禦流',         'beats'=>'防禦流'],
];
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8') ?>">
<title>技能樹 — 塔城傳說</title>
<link rel="stylesheet" href="assets/style.css">
<style>
/* skills_build 頁面專屬 */
.sb-wrap { max-width:920px; margin:0 auto; }
.page-topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px; }
.page-topbar h1 { font-size:22px; color:var(--accent); letter-spacing:2px; }
.topbar-right { display:flex; gap:10px; align-items:center; }
.gold { font-size:15px; color:var(--accent); font-weight:bold; }
.triangle-hint { margin-bottom:20px; background:var(--bg-card); border:1px solid var(--border); border-radius:10px; padding:12px 20px; font-size:12px; color:var(--text-muted); text-align:center; }
.triangle-hint b { color:var(--accent); }
.arch-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:24px; }
.arch-card { background:var(--bg-card); border:2px solid var(--border); border-radius:14px; padding:22px 18px; text-align:center; cursor:pointer; transition:all .2s; }
.arch-card:hover { transform:translateY(-2px); }
.arch-card.selected { box-shadow:0 0 0 2px var(--c); border-color:var(--c); }
.arch-icon { font-size:40px; margin-bottom:10px; }
.arch-name { font-size:17px; font-weight:700; margin-bottom:6px; }
.arch-desc { font-size:11px; color:var(--text-muted); line-height:1.6; margin-bottom:10px; }
.arch-beats { font-size:11px; padding:3px 10px; border-radius:10px; display:inline-block; }
.arch-btn { margin-top:14px; width:100%; padding:9px; border:none; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; transition:opacity .2s; }
.arch-btn:hover { opacity:.85; }
.arch-btn:disabled { opacity:.4; cursor:not-allowed; }
.build-section { }
.build-header { background:var(--bg-card); border:1px solid var(--border); border-radius:12px; padding:20px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; }
.build-header h2 { font-size:18px; font-weight:700; }
.bonus-tags { display:flex; gap:8px; flex-wrap:wrap; }
.bonus-tag { padding:4px 12px; border-radius:12px; font-size:12px; font-weight:700; }
.node-tree { display:flex; flex-direction:column; gap:10px; }
.node-row { display:flex; align-items:center; gap:12px; }
.node-connector { width:3px; height:30px; background:var(--border); margin-left:28px; }
.node-card { flex:1; border:2px solid; border-radius:12px; padding:14px 18px; display:flex; align-items:center; gap:14px; transition:all .2s; }
.node-card.unlocked { background:rgba(255,255,255,.04); }
.node-card.available { cursor:pointer; }
.node-card.available:hover { transform:translateX(3px); }
.node-card.locked { opacity:.45; filter:grayscale(.6); }
.node-num { width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:14px; flex-shrink:0; }
.node-info { flex:1; }
.node-label { font-size:14px; font-weight:700; margin-bottom:3px; }
.node-desc { font-size:11px; color:var(--text-muted); }
.node-type-badge { font-size:10px; padding:2px 8px; border-radius:6px; font-weight:700; }
.node-action { flex-shrink:0; text-align:right; }
.check-icon { font-size:22px; }
.cost-badge { font-size:12px; color:var(--accent); font-weight:700; white-space:nowrap; }
.btn-unlock { padding:7px 16px; border:none; border-radius:8px; font-size:12px; font-weight:700; cursor:pointer; transition:all .2s; white-space:nowrap; }
.btn-unlock:hover { opacity:.85; transform:translateY(-1px); }
.btn-unlock:disabled { opacity:.4; cursor:not-allowed; transform:none; }
.change-arch-area { margin:24px auto 0; text-align:center; }
.btn-change { padding:10px 24px; background:transparent; border:1px solid #4b5563; border-radius:8px; color:var(--text-muted); cursor:pointer; font-size:13px; transition:all .2s; }
.btn-change:hover { border-color:var(--accent-red); color:var(--accent-red); }
@media(max-width:640px) { .arch-grid{grid-template-columns:1fr;} }
</style>
</head>
<body>
<?php require '_sidebar.php'; ?>
<div class="page-body">
<div class="sb-wrap">

<div class="page-topbar">
  <h1>⚔️ 技能樹</h1>
  <div class="topbar-right">
    <span class="gold">💰 <span id="gold-display"><?= number_format($user['gold']) ?></span> 金</span>
    <a href="index.php">← 返回城鎮</a>
  </div>
</div>

<div class="triangle-hint">
  <b>三角相剋機制：</b>
  ⚔️ 攻擊流 克制 💚 血量流 &nbsp;→&nbsp;
  💚 血量流 克制 🛡️ 防禦流 &nbsp;→&nbsp;
  🛡️ 防禦流 克制 ⚔️ 攻擊流
  &nbsp;｜&nbsp; 克制效果來自技能機制，非傷害倍率
</div>

<!-- 流派選擇 -->
<div class="arch-grid">
<?php foreach ($arch_info as $key => $info):
  $is_selected = ($build['archetype'] === $key);
  $color = $info['color'];
?>
  <div class="arch-card <?= $is_selected ? 'selected' : '' ?>"
       style="--c:<?= $color ?>; border-color:<?= $is_selected ? $color : '#2a2a4a' ?>;">
    <div class="arch-icon"><?= $info['icon'] ?></div>
    <div class="arch-name" style="color:<?= $color ?>"><?= $info['name'] ?></div>
    <div class="arch-desc"><?= $info['desc'] ?></div>
    <div class="arch-beats" style="background:<?= $color ?>22;color:<?= $color ?>;">
      克制 <?= $info['beats'] ?>
    </div>
    <?php if (!$is_selected): ?>
    <button class="arch-btn" style="background:<?= $color ?>;color:#fff;"
            onclick="selectArch('<?= $key ?>', '<?= $info['name'] ?>')">
      <?= $build['archetype'] ? '更換流派（2000 金）' : '選擇此流派（免費）' ?>
    </button>
    <?php else: ?>
    <div style="margin-top:14px;padding:9px;border-radius:8px;background:<?= $color ?>22;color:<?= $color ?>;font-size:13px;font-weight:700;">✅ 目前流派</div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
</div>

<!-- 節點樹（有流派才顯示） -->
<?php if ($build['archetype']): ?>
<?php
$arch_key   = $build['archetype'];
$info       = $arch_info[$arch_key];
$nodes      = $all_nodes[$arch_key];
$unlocked   = (int)$build['nodes_unlocked'];
$color      = $info['color'];
?>
<div class="build-section">
  <div class="build-header">
    <h2 style="color:<?= $color ?>"><?= $info['icon'] ?> <?= $info['name'] ?> 技能樹</h2>
    <div class="bonus-tags">
      <?php if ($bonus['atk']  > 0): ?><span class="bonus-tag" style="background:#ef535022;color:#ef5350;">ATK +<?= $bonus['atk'] ?></span><?php endif; ?>
      <?php if ($bonus['def']  > 0): ?><span class="bonus-tag" style="background:#4fc3f722;color:#4fc3f7;">DEF +<?= $bonus['def'] ?></span><?php endif; ?>
      <?php if ($bonus['hp']   > 0): ?><span class="bonus-tag" style="background:#66bb6a22;color:#66bb6a;">HP +<?= $bonus['hp'] ?></span><?php endif; ?>
      <?php if ($bonus['crit'] > 0): ?><span class="bonus-tag" style="background:#ffca2822;color:#ffca28;">爆擊率 +<?= $bonus['crit'] ?>%</span><?php endif; ?>
      <?php if ($unlocked === 0): ?><span style="font-size:12px;color:#555;">尚未解鎖任何節點</span><?php endif; ?>
    </div>
    <div style="font-size:13px;color:#555;">已解鎖 <?= $unlocked ?> / 9 節點</div>
  </div>

  <div class="node-tree">
  <?php foreach ($nodes as $idx => $node):
    $state = $idx <= $unlocked ? 'unlocked' : ($idx === $unlocked + 1 ? 'available' : 'locked');
    $is_skill = ($node['type'] === 'skill');
    $border_color = $state === 'unlocked' ? $color : ($state === 'available' ? '#4b5563' : '#1f2937');
    $num_bg = $state === 'unlocked' ? $color : ($state === 'available' ? '#2a2a4a' : '#1a1a2a');
    $cost = $costs[$idx];
    $gold = (int)$user['gold'];
  ?>
  <?php if ($idx > 1): ?>
  <div class="node-connector" style="background:<?= $idx <= $unlocked ? $color.'44' : '#2a2a4a' ?>;"></div>
  <?php endif; ?>
  <div class="node-row">
    <div class="node-card <?= $state ?>"
         style="border-color:<?= $border_color ?>;"
         <?= $state === 'available' ? "onclick=\"unlockNode({$idx}, {$cost})\"" : '' ?>>
      <div class="node-num" style="background:<?= $num_bg ?>;color:#fff;"><?= $idx ?></div>
      <div class="node-info">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:3px;">
          <span class="node-label"><?= $node['label'] ?></span>
          <?php if ($is_skill): ?>
          <span class="node-type-badge" style="background:<?= $color ?>33;color:<?= $color ?>;">技能</span>
          <?php else: ?>
          <span class="node-type-badge" style="background:#2a2a4a;color:#94a3b8;">數值</span>
          <?php endif; ?>
        </div>
        <?php if (isset($node['desc'])): ?>
        <div class="node-desc"><?= $node['desc'] ?></div>
        <?php endif; ?>
      </div>
      <div class="node-action">
        <?php if ($state === 'unlocked'): ?>
          <span class="check-icon" style="color:<?= $color ?>">✅</span>
        <?php elseif ($state === 'available'): ?>
          <div>
            <div class="cost-badge">💰 <?= number_format($cost) ?></div>
            <button class="btn-unlock" id="btn-node-<?= $idx ?>"
                    style="background:<?= $color ?>;color:#fff;margin-top:6px;"
                    onclick="event.stopPropagation(); unlockNode(<?= $idx ?>, <?= $cost ?>)"
                    <?= $gold < $cost ? 'disabled' : '' ?>>
              <?= $gold < $cost ? '金幣不足' : '解鎖' ?>
            </button>
          </div>
        <?php else: ?>
          <span style="color:#2a2a4a;font-size:20px;">🔒</span>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  </div>

  <?php if ($unlocked >= 9): ?>
  <div style="text-align:center;margin-top:24px;font-size:18px;color:<?= $color ?>;font-weight:700;">
    🏆 已解鎖所有技能節點！
  </div>
  <?php endif; ?>
</div>

<div class="change-arch-area">
  <button class="btn-change" onclick="changeArch()">↩ 更換流派（2000 金，節點全重置）</button>
</div>
<?php else: ?>
<div style="text-align:center;color:#4b5563;padding:40px;font-size:15px;">
  ☝️ 請選擇一個流派以開始技能樹之旅
</div>
<?php endif; ?>

<div id="toast"></div>

</div><!-- /sb-wrap -->
</div><!-- /page-body -->

<script>
const _csrf = document.querySelector('meta[name="csrf-token"]').content;

function showToast(msg, ok = true) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'show ' + (ok ? 'ok' : 'err');
    clearTimeout(t._timer);
    t._timer = setTimeout(() => { t.className = ''; }, 2800);
}

async function post(action, body = {}) {
    const form = new URLSearchParams({ action, ...body });
    const resp = await fetch('api/skill_build.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': _csrf },
        body: form
    });
    return resp.json();
}

async function selectArch(arch, name) {
    const hasCurrent = <?= $build['archetype'] ? 'true' : 'false' ?>;
    if (hasCurrent) {
        if (!confirm(`確定要更換到「${name}」流派嗎？\n將扣除 2000 金幣，所有已解鎖節點全部重置。`)) return;
    } else {
        if (!confirm(`選擇「${name}」流派？（免費，選定後可花 2000 金更換）`)) return;
    }
    const data = await post('select_archetype', { archetype: arch });
    if (data.success) {
        document.getElementById('gold-display').textContent = data.gold.toLocaleString();
        showToast(data.message, true);
        setTimeout(() => location.reload(), 800);
    } else {
        showToast(data.message, false);
    }
}

async function unlockNode(idx, cost) {
    const data = await post('unlock_node');
    if (data.success) {
        document.getElementById('gold-display').textContent = data.gold.toLocaleString();
        showToast(`✅ ${data.message}`, true);
        setTimeout(() => location.reload(), 700);
    } else {
        showToast(data.message, false);
    }
}

function changeArch() {
    document.querySelector('.arch-grid').scrollIntoView({ behavior: 'smooth' });
}
</script>
</body>
</html>
