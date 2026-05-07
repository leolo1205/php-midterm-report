<?php
require_once 'auth.php';
require_once '../db.php';

// ── 訓練 API 統計 ──
$train_today   = $conn->query("SELECT COUNT(*) FROM api_logs WHERE api_name='train' AND DATE(created_at)=CURDATE()")->fetch_row()[0] ?? 0;
$train_ok      = $conn->query("SELECT COUNT(*) FROM api_logs WHERE api_name='train' AND status='success' AND DATE(created_at)=CURDATE()")->fetch_row()[0] ?? 0;
$train_fail    = $train_today - $train_ok;
$train_avg_ms  = $conn->query("SELECT ROUND(AVG(response_ms),1) FROM api_logs WHERE api_name='train' AND DATE(created_at)=CURDATE()")->fetch_row()[0] ?? 0;
$train_actions = [];
foreach (['cooldown_check','start_train','claim_reward','add_stat'] as $a) {
    $train_actions[$a] = $conn->query("SELECT COUNT(*) FROM api_logs WHERE api_name='train' AND action='$a' AND DATE(created_at)=CURDATE()")->fetch_row()[0] ?? 0;
}

// ── 戰鬥 API 統計 ──
$combat_today  = $conn->query("SELECT COUNT(*) FROM api_logs WHERE api_name='combat' AND DATE(created_at)=CURDATE()")->fetch_row()[0] ?? 0;
$combat_ok     = $conn->query("SELECT COUNT(*) FROM api_logs WHERE api_name='combat' AND status='success' AND DATE(created_at)=CURDATE()")->fetch_row()[0] ?? 0;
$combat_fail   = $combat_today - $combat_ok;
$combat_avg_ms = $conn->query("SELECT ROUND(AVG(response_ms),1) FROM api_logs WHERE api_name='combat' AND DATE(created_at)=CURDATE()")->fetch_row()[0] ?? 0;
$combat_actions = [];
foreach (['normal_attack','defense_stance','try_escape','victory_settle','defeat_settle'] as $a) {
    $combat_actions[$a] = $conn->query("SELECT COUNT(*) FROM api_logs WHERE api_name='combat' AND action='$a' AND DATE(created_at)=CURDATE()")->fetch_row()[0] ?? 0;
}

// 勝/逃/敗率（取 victory_settle / try_escape(success) / defeat_settle 的比例）
$settle_total = ($combat_actions['victory_settle'] + $combat_actions['defeat_settle']) ?: 1;
$win_rate  = round($combat_actions['victory_settle'] / $settle_total * 100, 1);
$lose_rate = round($combat_actions['defeat_settle']  / $settle_total * 100, 1);

// ── 最近 50 筆 API 記錄 ──
$logs_res = $conn->query("SELECT * FROM api_logs ORDER BY created_at DESC LIMIT 50");
$api_logs = [];
while ($r = $logs_res->fetch_assoc()) $api_logs[] = $r;
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>API 模組 — 後台管理</title>
<?php include '_sidebar.php'; ?>

  <div class="topbar">
    <div class="page-title">🔌 API 模組</div>
    <div class="breadcrumb">後台管理 / <span>API 模組</span></div>
  </div>

  <div class="content">

    <!-- ── 訓練 API 監控 ── -->
    <div class="section" style="margin-bottom:24px;">
      <div class="section-header">
        <h3>🏋️ 訓練 API 監控</h3>
        <span class="badge">api/train.php · 今日統計</span>
      </div>
      <div style="padding:20px;">
        <div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
          <div class="stat-card blue">
            <div class="label">今日呼叫</div>
            <div class="value"><?= number_format($train_today) ?></div>
            <div class="sub">所有 action 合計</div>
          </div>
          <div class="stat-card green">
            <div class="label">成功次數</div>
            <div class="value" style="color:#66bb6a;"><?= $train_ok ?></div>
            <div class="sub">成功率 <?= $train_today > 0 ? round($train_ok/$train_today*100,1) : 0 ?>%</div>
          </div>
          <div class="stat-card" style="border-color:#ef5350;">
            <div class="label">失敗次數</div>
            <div class="value" style="color:#ef5350;"><?= $train_fail ?></div>
            <div class="sub">失敗率 <?= $train_today > 0 ? round($train_fail/$train_today*100,1) : 0 ?>%</div>
          </div>
          <div class="stat-card yellow">
            <div class="label">平均回應</div>
            <div class="value" style="font-size:26px;"><?= $train_avg_ms ?></div>
            <div class="sub">毫秒 (ms)</div>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">
          <?php
          $action_labels = [
            'cooldown_check' => ['🕐','冷卻查詢','#4fc3f7'],
            'start_train'    => ['▶️','開始訓練','#66bb6a'],
            'claim_reward'   => ['🎁','領取獎勵','#ffca28'],
            'add_stat'       => ['📊','屬性配點','#e040fb'],
          ];
          foreach ($train_actions as $a => $cnt):
            [$icon, $label, $color] = $action_labels[$a];
          ?>
          <div style="background:#0d0d1a;border:1px solid #2a2a4a;border-radius:8px;padding:14px;text-align:center;">
            <div style="font-size:22px;margin-bottom:6px;"><?= $icon ?></div>
            <div style="font-size:20px;font-weight:700;color:<?= $color ?>;"><?= $cnt ?></div>
            <div style="font-size:11px;color:#94a3b8;margin-top:4px;"><?= $label ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- ── 戰鬥 API 監控 ── -->
    <div class="section" style="margin-bottom:24px;">
      <div class="section-header">
        <h3>⚔️ 戰鬥 API 監控</h3>
        <span class="badge">api/combat.php · 今日統計</span>
      </div>
      <div style="padding:20px;">
        <div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
          <div class="stat-card blue">
            <div class="label">今日呼叫</div>
            <div class="value"><?= number_format($combat_today) ?></div>
            <div class="sub">所有 action 合計</div>
          </div>
          <div class="stat-card green">
            <div class="label">勝率</div>
            <div class="value" style="color:#66bb6a;"><?= $win_rate ?>%</div>
            <div class="sub">victory_settle</div>
          </div>
          <div class="stat-card" style="border-color:#ef5350;">
            <div class="label">敗率</div>
            <div class="value" style="color:#ef5350;"><?= $lose_rate ?>%</div>
            <div class="sub">defeat_settle</div>
          </div>
          <div class="stat-card yellow">
            <div class="label">平均回應</div>
            <div class="value" style="font-size:26px;"><?= $combat_avg_ms ?></div>
            <div class="sub">毫秒 (ms)</div>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;">
          <?php
          $ca_labels = [
            'normal_attack'  => ['⚔️', '普通攻擊',  '#ef5350'],
            'defense_stance' => ['🛡️', '防禦姿態',  '#4fc3f7'],
            'try_escape'     => ['🏃', '嘗試逃跑',  '#ffca28'],
            'victory_settle' => ['🏆', '勝利結算',  '#66bb6a'],
            'defeat_settle'  => ['💀', '敗北結算',  '#9e9e9e'],
          ];
          foreach ($combat_actions as $a => $cnt):
            [$icon, $label, $color] = $ca_labels[$a];
          ?>
          <div style="background:#0d0d1a;border:1px solid #2a2a4a;border-radius:8px;padding:14px;text-align:center;">
            <div style="font-size:22px;margin-bottom:6px;"><?= $icon ?></div>
            <div style="font-size:20px;font-weight:700;color:<?= $color ?>;"><?= $cnt ?></div>
            <div style="font-size:11px;color:#94a3b8;margin-top:4px;"><?= $label ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- ── API 即時記錄 + 測試工具 ── -->
    <div style="display:grid;grid-template-columns:1.6fr 1fr;gap:24px;margin-bottom:24px;">

      <!-- 即時記錄 -->
      <div class="section">
        <div class="section-header">
          <h3>📋 API 呼叫即時記錄</h3>
          <span class="badge">最近 50 筆</span>
        </div>
        <div style="overflow-x:auto;">
          <table class="tbl" style="font-size:12px;">
            <thead><tr>
              <th style="width:40px;">#</th>
              <th>API</th>
              <th>Action</th>
              <th>玩家</th>
              <th>狀態</th>
              <th>回應(ms)</th>
              <th>時間</th>
            </tr></thead>
            <tbody>
            <?php if (empty($api_logs)): ?>
            <tr><td colspan="7" style="text-align:center;color:#444;padding:30px;">尚無 API 記錄</td></tr>
            <?php else: foreach ($api_logs as $i => $lg): ?>
            <tr>
              <td style="color:#555;"><?= $i+1 ?></td>
              <td>
                <span style="display:inline-block;padding:2px 7px;border-radius:4px;font-size:11px;font-weight:600;
                  background:<?= $lg['api_name']==='train' ? '#1a3a2a' : '#2a1a1a' ?>;
                  color:<?= $lg['api_name']==='train' ? '#66bb6a' : '#ef5350' ?>;">
                  <?= $lg['api_name'] ?>
                </span>
              </td>
              <td style="color:#94a3b8;font-size:11px;"><?= htmlspecialchars($lg['action']) ?></td>
              <td style="color:#b0bec5;"><?= $lg['user_id'] ?? '<span style="color:#555;">—</span>' ?></td>
              <td>
                <span class="tag <?= $lg['status']==='success' ? 'tag-active' : 'tag-lose' ?>">
                  <?= $lg['status'] ?>
                </span>
              </td>
              <td style="color:<?= $lg['response_ms'] > 200 ? '#ffca28' : '#66bb6a' ?>;">
                <?= $lg['response_ms'] ?>
              </td>
              <td style="color:#555;font-size:11px;"><?= substr($lg['created_at'],5,14) ?></td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- API 測試工具 -->
      <div class="section">
        <div class="section-header">
          <h3>🧪 API 測試工具</h3>
          <span class="badge">直接呼叫端點</span>
        </div>
        <div style="padding:20px;">
          <div style="margin-bottom:16px;">
            <label style="font-size:11px;color:#94a3b8;display:block;margin-bottom:6px;letter-spacing:1px;">選擇 API</label>
            <select id="api_sel" style="width:100%;padding:9px 14px;background:#0d0d1a;border:1px solid #2a2a4a;border-radius:7px;color:#e0e0e0;margin-bottom:10px;" onchange="updateActions()">
              <option value="train">train (訓練 API)</option>
              <option value="combat">combat (戰鬥 API)</option>
            </select>
            <label style="font-size:11px;color:#94a3b8;display:block;margin-bottom:6px;letter-spacing:1px;">選擇 Action</label>
            <select id="act_sel" style="width:100%;padding:9px 14px;background:#0d0d1a;border:1px solid #2a2a4a;border-radius:7px;color:#e0e0e0;margin-bottom:10px;" onchange="updateParams()">
            </select>
          </div>

          <div id="param_area" style="margin-bottom:14px;"></div>

          <button onclick="runTest()" class="btn btn-primary" style="width:100%;margin-bottom:14px;">▶ 發送請求</button>

          <div style="font-size:11px;color:#94a3b8;margin-bottom:6px;">回應結果：</div>
          <pre id="api_result" style="background:#050510;border:1px solid #1a1a3a;border-radius:7px;padding:14px;font-size:12px;color:#4fc3f7;min-height:80px;max-height:300px;overflow-y:auto;white-space:pre-wrap;word-break:break-all;">（尚未發送請求）</pre>

          <div id="api_meta" style="display:none;margin-top:10px;font-size:11px;color:#555;display:flex;gap:16px;">
            <span>狀態：<span id="meta_status">—</span></span>
            <span>耗時：<span id="meta_ms">—</span> ms</span>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /content -->
</div><!-- /main -->

<script>
const API_DEFS = {
  train: {
    cooldown_check: [],
    start_train:    [],
    claim_reward:   [],
    add_stat:       [{ name:'stat', label:'屬性類型', type:'select', options:['dmg','hp','def'] }],
  },
  combat: {
    normal_attack:   [
      { name:'p_atk', label:'玩家攻擊力', type:'number', def:50 },
      { name:'m_def', label:'怪物防禦力', type:'number', def:10 },
      { name:'p_crit', label:'爆擊率(%)', type:'number', def:15 },
      { name:'m_dodge', label:'怪物閃避(%)', type:'number', def:10 },
    ],
    defense_stance:  [
      { name:'m_atk', label:'怪物攻擊力', type:'number', def:40 },
      { name:'p_def', label:'玩家防禦力', type:'number', def:12 },
      { name:'m_crit', label:'怪物爆擊(%)', type:'number', def:10 },
      { name:'p_dodge', label:'玩家閃避(%)', type:'number', def:15 },
    ],
    try_escape:      [
      { name:'dodge_level', label:'閃避熟練度', type:'number', def:2 },
      { name:'floor', label:'樓層', type:'number', def:1 },
    ],
    victory_settle:  [
      { name:'exp_gained',  label:'獲得 EXP',  type:'number', def:200 },
      { name:'gold_gained', label:'獲得金幣', type:'number', def:150 },
      { name:'floor',       label:'樓層',     type:'number', def:1 },
      { name:'crit_exp',    label:'爆擊EXP',  type:'number', def:10 },
      { name:'dodge_exp',   label:'閃避EXP',  type:'number', def:5 },
    ],
    defeat_settle:   [
      { name:'floor', label:'樓層', type:'number', def:1 },
    ],
  }
};

function updateActions() {
  const api = document.getElementById('api_sel').value;
  const sel = document.getElementById('act_sel');
  sel.innerHTML = '';
  Object.keys(API_DEFS[api]).forEach(a => {
    const opt = document.createElement('option');
    opt.value = a; opt.textContent = a;
    sel.appendChild(opt);
  });
  updateParams();
}

function updateParams() {
  const api = document.getElementById('api_sel').value;
  const act = document.getElementById('act_sel').value;
  const params = API_DEFS[api][act] || [];
  const area = document.getElementById('param_area');
  if (!params.length) { area.innerHTML = '<div style="color:#555;font-size:12px;">此 action 無需額外參數</div>'; return; }
  let html = '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">';
  params.forEach(p => {
    html += `<div>
      <div style="font-size:10px;color:#94a3b8;margin-bottom:4px;">${p.label}</div>`;
    if (p.type === 'select') {
      html += `<select id="p_${p.name}" style="width:100%;padding:7px 10px;background:#0d0d1a;border:1px solid #2a2a4a;border-radius:6px;color:#e0e0e0;font-size:12px;">`;
      p.options.forEach(o => html += `<option value="${o}">${o}</option>`);
      html += '</select>';
    } else {
      html += `<input type="number" id="p_${p.name}" value="${p.def ?? 0}"
        style="width:100%;padding:7px 10px;background:#0d0d1a;border:1px solid #2a2a4a;border-radius:6px;color:#e0e0e0;font-size:12px;box-sizing:border-box;">`;
    }
    html += '</div>';
  });
  html += '</div>';
  area.innerHTML = html;
}

async function runTest() {
  const api = document.getElementById('api_sel').value;
  const act = document.getElementById('act_sel').value;
  const params = API_DEFS[api][act] || [];
  const body = new URLSearchParams({ action: act });
  params.forEach(p => {
    const el = document.getElementById('p_' + p.name);
    if (el) body.append(p.name, el.value);
  });

  const pre = document.getElementById('api_result');
  const meta = document.getElementById('api_meta');
  pre.textContent = '請求中…';
  meta.style.display = 'none';

  const t0 = performance.now();
  try {
    const resp = await fetch(`/targame/api/${api}.php`, { method:'POST', body });
    const ms = Math.round(performance.now() - t0);
    const data = await resp.json();
    pre.textContent = JSON.stringify(data, null, 2);
    pre.style.color = data.success ? '#66bb6a' : '#ef5350';
    document.getElementById('meta_status').textContent = data.success ? '✅ success' : '❌ fail';
    document.getElementById('meta_ms').textContent = ms;
    meta.style.display = 'flex';
  } catch(e) {
    pre.textContent = '請求失敗：' + e.message;
    pre.style.color = '#ef5350';
  }
}

updateActions();
</script>
</body>
</html>
