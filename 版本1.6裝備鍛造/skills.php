<?php
session_start();
require 'db.php';

if (!isset($_SESSION['player_id'])) { header('Location: login.php'); exit; }
$user_id = (int)$_SESSION['player_id'];
$user = $conn->query("SELECT username, level FROM users WHERE id=$user_id")->fetch_assoc();

// 撈取玩家技能紀錄
$skills_query = $conn->query("SELECT * FROM user_skills WHERE user_id = $user_id");
$user_skills = [];
if ($skills_query) {
    while($row = $skills_query->fetch_assoc()) {
        $user_skills[$row['skill_id']] = $row;
    }
}

// 讀取爆擊熟練度
$crit_lvl = isset($user_skills['crit']) ? $user_skills['crit']['level'] : 0;
$crit_exp = isset($user_skills['crit']) ? $user_skills['crit']['exp'] : 0;
$crit_req = ($crit_lvl + 1) * 10; 
$crit_percent = min(100, ($crit_exp / $crit_req) * 100);

// 讀取閃避熟練度
$dodge_lvl = isset($user_skills['dodge']) ? $user_skills['dodge']['level'] : 0;
$dodge_exp = isset($user_skills['dodge']) ? $user_skills['dodge']['exp'] : 0;
$dodge_req = ($dodge_lvl + 1) * 10; 
$dodge_percent = min(100, ($dodge_exp / $dodge_req) * 100);

?>
<!DOCTYPE html>
<html>
<head>
    <title>被動技能</title>
    <meta charset="utf-8">
    <link rel="stylesheet" href="assets/style.css">
    <style>
/* skills 頁面專屬 */
.skills-wrap { max-width:640px; margin:0 auto; }
.skills-wrap h2 { margin-top:0; color:#9c27b0; border-bottom:2px solid #9c27b0; padding-bottom:10px; margin-bottom:20px; }
.skill-card { display:flex; align-items:center; background:#353542; padding:15px; border-radius:8px; margin-bottom:15px; border:1px solid var(--accent-green); transition:all 0.3s ease; }
.skill-locked { filter:grayscale(100%); opacity:0.6; border:1px solid #555; }
.skill-icon { font-size:40px; margin-right:20px; background:#22222b; width:60px; height:60px; display:flex; justify-content:center; align-items:center; border-radius:10px; flex-shrink:0; }
.skill-info { flex-grow:1; }
.skill-info h4 { margin:0 0 5px 0; color:#fff; font-size:18px; }
.skill-info p { margin:0 0 10px 0; color:#bbb; font-size:14px; line-height:1.4; }
.progress-wrapper { margin-top:10px; }
.progress-label { font-size:12px; color:#ffeb3b; font-weight:bold; margin-bottom:4px; display:block; }
.progress-container { width:100%; background-color:#22222b; border-radius:4px; overflow:hidden; height:16px; position:relative; border:1px solid #444; }
.progress-bar-crit  { height:100%; background-color:#ff9800; transition:width 0.3s ease; }
.progress-bar-dodge { height:100%; background-color:var(--accent-green); transition:width 0.3s ease; }
.progress-text { position:absolute; width:100%; text-align:center; top:0; left:0; font-size:11px; line-height:16px; color:#fff; font-weight:bold; text-shadow:1px 1px 2px #000; }
.back-btn { display:block; text-align:center; background-color:#555; color:white; text-decoration:none; padding:12px; border-radius:6px; font-weight:bold; margin-top:20px; }
.back-btn:hover { background-color:#666; }
    </style>
</head>
<body>
<?php require '_sidebar.php'; ?>
<div class="page-body">
<div class="skills-wrap">
    <h2>📖 被動技能</h2>
    <p style="color:#aaa; font-size: 14px;">滿足特定條件即可獲得熟練度，並自動提升被動效果。</p>

    <!-- 技能 1：爆擊熟練 -->
    <div class="skill-card <?php echo $crit_lvl == 0 ? 'skill-locked' : ''; ?>">
        <div class="skill-icon">💥</div>
        <div class="skill-info">
            <h4>爆擊熟練 <?php echo $crit_lvl == 0 ? "<span style='color:#888;'>(未解鎖)</span>" : "<span style='color:#ffeb3b;'>(Lv.$crit_lvl)</span>"; ?></h4>
            <p>提升戰鬥中發現敵人弱點的能力。<br>
               目前效果：<span style="color:#ff8a65;">爆擊率 +<?php echo $crit_lvl; ?>%</span><br>
               <span style="font-size:12px; color:#888;">(獲取方式：戰鬥中每觸發 1 次爆擊，熟練度 +1)</span>
            </p>
            
            <div class="progress-wrapper">
                <span class="progress-label">當前熟練度進度：</span>
                <div class="progress-container">
                    <div class="progress-bar-crit" style="width: <?php echo $crit_percent; ?>%;"></div>
                    <div class="progress-text"><?php echo $crit_exp; ?> / <?php echo $crit_req; ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 技能 2：閃避熟練 -->
    <div class="skill-card <?php echo $dodge_lvl == 0 ? 'skill-locked' : ''; ?>">
        <div class="skill-icon">🍃</div>
        <div class="skill-info">
            <h4>閃避熟練 <?php echo $dodge_lvl == 0 ? "<span style='color:#888;'>(未解鎖)</span>" : "<span style='color:#ffeb3b;'>(Lv.$dodge_lvl)</span>"; ?></h4>
            <p>提升戰鬥中靈活躲避攻擊的能力。<br>
               目前效果：<span style="color:#81c784;">閃避率 +<?php echo $dodge_lvl; ?>%</span><br>
               <span style="font-size:12px; color:#888;">(獲取方式：戰鬥中每觸發 1 次閃避，熟練度 +1)</span>
            </p>
            
            <div class="progress-wrapper">
                <span class="progress-label">當前熟練度進度：</span>
                <div class="progress-container">
                    <div class="progress-bar progress-bar-dodge" style="width: <?php echo $dodge_percent; ?>%;"></div>
                    <div class="progress-text"><?php echo $dodge_exp; ?> / <?php echo $dodge_req; ?></div>
                </div>
            </div>
        </div>
    </div>

    <a href="index.php" class="back-btn">⬅ 返回城鎮</a>
</div><!-- /skills-wrap -->
</div><!-- /page-body -->

</body>
</html>