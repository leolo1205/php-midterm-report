<?php
session_start();
if (isset($_SESSION['admin_logged_in'])) { header('Location: index.php'); exit; }
require_once '../db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $conn->prepare("SELECT id, username, password FROM admin_users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id']        = $admin['id'];
        $_SESSION['admin_user']      = $admin['username'];
        header('Location: index.php');
        exit;
    }
    $error = '帳號或密碼錯誤，請重試';
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>後台管理 — 登入</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{
  min-height:100vh;
  background:radial-gradient(ellipse at center,#0d1b2a 0%,#0d0d1a 70%);
  display:flex;align-items:center;justify-content:center;
  font-family:'Segoe UI','微軟正黑體',sans-serif;
  color:#e0e0e0;
}
.card{
  background:#16213e;
  border:1px solid #2a2a4a;
  border-radius:14px;
  padding:48px 44px;
  width:400px;
  box-shadow:0 0 60px rgba(79,195,247,.12),0 20px 40px rgba(0,0,0,.5);
}
.logo{text-align:center;margin-bottom:36px;}
.logo .icon{font-size:52px;display:block;margin-bottom:14px;}
.logo h1{font-size:22px;color:#4fc3f7;letter-spacing:3px;font-weight:700;}
.logo p{font-size:12px;color:#555;margin-top:6px;letter-spacing:1px;}
.divider{height:1px;background:linear-gradient(90deg,transparent,#2a2a4a,transparent);margin:0 0 28px;}

.form-group{margin-bottom:22px;}
.form-group label{
  display:block;font-size:12px;color:#888;
  margin-bottom:8px;letter-spacing:1.5px;text-transform:uppercase;
}
.form-group input{
  width:100%;padding:13px 16px;
  background:#0d0d1a;border:1px solid #2a2a4a;border-radius:8px;
  color:#e0e0e0;font-size:15px;transition:border-color .2s,box-shadow .2s;
}
.form-group input:focus{outline:none;border-color:#4fc3f7;box-shadow:0 0 0 3px rgba(79,195,247,.1);}
.form-group input::placeholder{color:#444;}

.btn-login{
  width:100%;padding:14px;
  background:linear-gradient(135deg,#1565c0 0%,#4fc3f7 100%);
  border:none;border-radius:8px;color:#fff;
  font-size:15px;font-weight:700;cursor:pointer;
  letter-spacing:3px;transition:opacity .2s,transform .1s;
  margin-top:6px;
}
.btn-login:hover{opacity:.92;transform:translateY(-1px);}
.btn-login:active{transform:translateY(0);}

.error-msg{
  background:rgba(239,83,80,.12);border:1px solid rgba(239,83,80,.4);
  color:#ef9a9a;padding:11px 15px;border-radius:7px;
  font-size:13px;margin-bottom:22px;text-align:center;
}
.footer-links{text-align:center;margin-top:24px;}
.footer-links a{color:#444;font-size:12px;text-decoration:none;transition:color .2s;}
.footer-links a:hover{color:#4fc3f7;}
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <span class="icon">⚔️</span>
    <h1>後台管理系統</h1>
    <p>TAR GAME · ADMIN PORTAL</p>
  </div>
  <div class="divider"></div>
  <?php if ($error): ?>
  <div class="error-msg">⚠ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST" autocomplete="off">
    <div class="form-group">
      <label>管理員帳號</label>
      <input type="text" name="username" placeholder="admin" required autofocus>
    </div>
    <div class="form-group">
      <label>密碼</label>
      <input type="password" name="password" placeholder="••••••••" required>
    </div>
    <button type="submit" class="btn-login">登　入</button>
  </form>
  <div class="footer-links"><a href="../index.php">← 返回遊戲前台</a></div>
</div>
</body>
</html>
