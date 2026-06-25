<?php
ob_start();
session_start();

// 玩家已登入直接進主城鎮
if (isset($_SESSION['player_id'])) { header('Location: index.php'); exit; }
// admin 在此頁面只是想預覽前台，不自動跳回後台（後台有專屬入口 admin/login.php）

require_once 'db.php';
require_once 'lib/session.php';

$player_error = '';
$admin_error  = '';

// ── 速率限制工具（以 Session 為基礎，以 IP 為 key） ──
function _rl_get(string $prefix): array {
    $key = $prefix . '_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
    if (!isset($_SESSION[$key])) $_SESSION[$key] = ['fails' => 0, 'since' => 0];
    if ($_SESSION[$key]['fails'] > 0 && (time() - $_SESSION[$key]['since']) >= 900) {
        $_SESSION[$key] = ['fails' => 0, 'since' => 0]; // 15 分鐘後自動解鎖
    }
    return [$key, $_SESSION[$key]];
}
function _rl_fail(string $key): void {
    $_SESSION[$key]['fails']++;
    if ($_SESSION[$key]['fails'] === 1) $_SESSION[$key]['since'] = time();
}
function _rl_ok(string $key): void { $_SESSION[$key] = ['fails' => 0, 'since' => 0]; }
function _rl_locked(array $rl): bool { return $rl['fails'] >= 5; }
function _rl_wait(array $rl): int { return (int)ceil((900 - (time() - $rl['since'])) / 60); }

// ── 玩家登入 ──
if (isset($_POST['type']) && $_POST['type'] === 'player') {
    if (!csrf_verify()) { $player_error = '安全驗證失敗，請重新整理後再試。'; goto skip_player; }
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    [$rl_key, $rl] = _rl_get('rl_player');

    if (_rl_locked($rl)) {
        $player_error = '登入失敗次數過多，請等待 ' . _rl_wait($rl) . ' 分鐘後再試。';
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, is_banned FROM users WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $player = $stmt->get_result()->fetch_assoc();

        if (!$player || !password_verify($password, $player['password'])) {
            $player_error = '帳號或密碼錯誤'; // 統一訊息，防止帳號列舉
            _rl_fail($rl_key);
        } elseif ($player['is_banned']) {
            $player_error = '此帳號已被停用，請聯絡管理員';
        } else {
            _rl_ok($rl_key);
            session_regenerate_id(true);
            $_SESSION['player_id']   = $player['id'];
            $_SESSION['player_name'] = $player['username'];
            header('Location: index.php');
            exit;
        }
    }
    skip_player:;
}

// ── 管理員登入 ──
if (isset($_POST['type']) && $_POST['type'] === 'admin') {
    if (!csrf_verify()) { $admin_error = '安全驗證失敗，請重新整理後再試。'; goto skip_admin; }
    $username = trim($_POST['admin_username'] ?? '');
    $password = $_POST['admin_password'] ?? '';

    [$rl_key, $rl] = _rl_get('rl_admin');

    if (_rl_locked($rl)) {
        $admin_error = '登入失敗次數過多，請等待 ' . _rl_wait($rl) . ' 分鐘後再試。';
    } else {
        $stmt = $conn->prepare("SELECT id, username, password FROM admin_users WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();

        if ($admin && password_verify($password, $admin['password'])) {
            _rl_ok($rl_key);
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id']        = $admin['id'];
            $_SESSION['admin_user']      = $admin['username'];
            header('Location: admin/index.php');
            exit;
        }
        $admin_error = '管理員帳號或密碼錯誤';
        _rl_fail($rl_key);
    }
    skip_admin:;
}

$active = (isset($_POST['type']) && $_POST['type'] === 'admin') ? 'admin' : 'player';
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>登入 — 塔城傳說</title>
<link rel="stylesheet" href="assets/style.css">
<style>
/* login 頁面專屬 */
body { display:flex; align-items:center; justify-content:center; overflow:hidden; }
.bg-glow { position:fixed; pointer-events:none; border-radius:50%; filter:blur(120px); opacity:.15; }
.bg-glow.g1 { width:600px; height:600px; background:#1565c0; top:-100px; left:-200px; }
.bg-glow.g2 { width:500px; height:500px; background:#4a148c; bottom:-100px; right:-100px; }
.bg-glow.g3 { width:300px; height:300px; background:#006064; top:50%; left:50%; transform:translate(-50%,-50%); }
.login-container { display:flex; width:820px; min-height:520px; background:#111827; border:1px solid #1f2937; border-radius:20px; overflow:hidden; box-shadow:0 30px 80px rgba(0,0,0,.7); position:relative; z-index:1; }
.panel { flex:1; padding:48px 44px; display:flex; flex-direction:column; transition:all .35s ease; }
.panel.player-panel { background:linear-gradient(160deg,#0d1b2a 0%,#111827 100%); border-right:1px solid #1f2937; }
.panel.admin-panel  { background:linear-gradient(160deg,#1a0d2e 0%,#111827 100%); }
.panel-header { text-align:center; margin-bottom:36px; }
.panel-header .icon { font-size:50px; display:block; margin-bottom:12px; }
.panel-header h2 { font-size:20px; font-weight:700; letter-spacing:2px; }
.panel-header p { font-size:12px; color:#4b5563; margin-top:6px; letter-spacing:1px; }
.player-panel .panel-header h2 { color:var(--accent-blue); }
.admin-panel  .panel-header h2 { color:#ce93d8; }
.divider { width:1px; background:linear-gradient(180deg,transparent,#2d3748,transparent); position:relative; flex-shrink:0; }
.divider::after { content:'OR'; position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:#111827; color:#374151; padding:8px 4px; font-size:11px; letter-spacing:1px; border-radius:4px; }
.form-group { margin-bottom:18px; }
.form-group label { display:block; font-size:11px; color:#6b7280; margin-bottom:7px; letter-spacing:1.5px; text-transform:uppercase; }
.form-group input { width:100%; padding:12px 15px; background:#0d111a; border:1px solid #1f2937; border-radius:8px; color:#e5e7eb; font-size:14px; transition:border-color .2s,box-shadow .2s; }
.form-group input::placeholder { color:#374151; }
.form-group input:focus { outline:none; }
.player-panel .form-group input:focus { border-color:var(--accent-blue); box-shadow:0 0 0 3px rgba(79,195,247,.08); }
.admin-panel  .form-group input:focus { border-color:#ce93d8; box-shadow:0 0 0 3px rgba(206,147,216,.08); }
.btn-submit { width:100%; padding:13px; border:none; border-radius:8px; font-size:14px; font-weight:700; cursor:pointer; letter-spacing:2px; transition:opacity .2s,transform .1s; margin-top:4px; }
.btn-submit:hover { opacity:.9; transform:translateY(-1px); }
.btn-submit:active { transform:translateY(0); }
.player-panel .btn-submit { background:linear-gradient(135deg,#1565c0,#4fc3f7); color:#fff; }
.admin-panel  .btn-submit { background:linear-gradient(135deg,#4a148c,#ce93d8); color:#fff; }
.error-msg { padding:10px 13px; border-radius:7px; font-size:12px; margin-bottom:18px; text-align:center; background:rgba(239,83,80,.1); border:1px solid rgba(239,83,80,.3); color:#ef9a9a; }
.hint { margin-top:auto; padding-top:24px; text-align:center; font-size:11px; color:#374151; line-height:1.8; }
.hint b { color:#4b5563; }
@media(max-width:640px) {
  .login-container { flex-direction:column; width:95%; min-height:auto; }
  .panel.player-panel { border-right:none; border-bottom:1px solid #1f2937; }
  .divider { display:none; }
}
</style>
</head>
<body>

<div class="bg-glow g1"></div>
<div class="bg-glow g2"></div>
<div class="bg-glow g3"></div>

<div class="login-container">

  <!-- ── 玩家登入 ── -->
  <div class="panel player-panel">
    <div class="panel-header">
      <span class="icon">⚔️</span>
      <h2>玩家登入</h2>
      <p>TAR GAME · PLAYER</p>
    </div>

    <?php if ($player_error): ?>
    <div class="error-msg">⚠ <?= htmlspecialchars($player_error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <input type="hidden" name="type" value="player">
      <?= csrf_field() ?>
      <div class="form-group">
        <label>玩家名稱</label>
        <input type="text" name="username"
               placeholder="輸入角色名稱"
               value="<?= ($active==='player') ? htmlspecialchars($_POST['username']??'') : '' ?>"
               required>
      </div>
      <div class="form-group">
        <label>密碼</label>
        <input type="password" name="password" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn-submit">進入遊戲</button>
    </form>

    <div class="hint">
      還沒有帳號？<a href="register.php" style="color:#4fc3f7;text-decoration:none;font-weight:600;">立即註冊</a><br>
      <span style="margin-top:4px;display:inline-block;">註冊後直接進入遊戲，免重新登入</span>
    </div>
  </div>

  <div class="divider"></div>

  <!-- ── 管理員登入 ── -->
  <div class="panel admin-panel">
    <div class="panel-header">
      <span class="icon">🛡️</span>
      <h2>管理員登入</h2>
      <p>ADMIN PORTAL</p>
    </div>

    <?php if ($admin_error): ?>
    <div class="error-msg">⚠ <?= htmlspecialchars($admin_error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <input type="hidden" name="type" value="admin">
      <?= csrf_field() ?>
      <div class="form-group">
        <label>管理員帳號</label>
        <input type="text" name="admin_username"
               autocomplete="off"
               placeholder="admin"
               value="<?= ($active==='admin') ? htmlspecialchars($_POST['admin_username']??'') : '' ?>"
               required>
      </div>
      <div class="form-group">
        <label>密碼</label>
        <input type="password" name="admin_password" autocomplete="new-password" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn-submit">進入後台</button>
    </form>

    <div class="hint">
      僅限授權管理人員登入<br>
      如有問題請聯絡系統管理員
    </div>
  </div>

</div><!-- /login-container -->

<script>
// 若有 player error 則 focus 玩家欄
<?php if ($player_error): ?>
document.querySelector('.player-panel input[name="username"]').focus();
<?php elseif ($admin_error): ?>
document.querySelector('.admin-panel input[name="username"]').focus();
<?php endif; ?>
</script>
</body>
</html>
