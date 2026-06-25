<?php
/**
 * 訓練系統
 * 包含：訓練方案、冷卻檢查、開始訓練、舊版領取相容
 */

/**
 * 訓練方案定義
 */
function get_train_plans() {
    return [
        'short' => [
            'label' => '10 分鐘',
            'duration_sec' => 600,
            'exp' => 50,
            'stat' => 1,
        ],
        'medium' => [
            'label' => '1 小時',
            'duration_sec' => 3600,
            'exp' => 300,
            'stat' => 3,
        ],
        'long' => [
            'label' => '8 小時',
            'duration_sec' => 28800,
            'exp' => 1500,
            'stat' => 10,
        ],
    ];
}

/**
 * 查詢訓練冷卻狀態
 */
function check_training_cooldown($conn, $user_id) {
    $user_id = (int)$user_id;
    $q = $conn->query("SELECT last_train_time, train_duration FROM users WHERE id=$user_id");
    $row = ($q !== false) ? $q->fetch_assoc() : null;

    if (!$row || !$row['last_train_time']) {
        return [
            'is_training' => false,
            'seconds_remaining' => 0,
            'duration_sec' => 0,
        ];
    }

    $duration = (int)$row['train_duration'];
    $elapsed = time() - strtotime($row['last_train_time'] . ' UTC');
    $remaining = max(0, $duration - $elapsed);

    if ($elapsed >= $duration) {
        $conn->query("UPDATE users SET last_train_time=NULL, train_duration=0 WHERE id=$user_id");

        return [
            'is_training' => false,
            'seconds_remaining' => 0,
            'duration_sec' => 0,
        ];
    }

    return [
        'is_training' => true,
        'seconds_remaining' => $remaining,
        'duration_sec' => $duration,
    ];
}

/**
 * 開始訓練，立即發放獎勵並啟動冷卻
 */
function start_training($conn, $user_id, $plan_key = 'short') {
    $user_id = (int)$user_id;
    $plans = get_train_plans();

    if (!isset($plans[$plan_key])) {
        $plan_key = 'short';
    }

    $plan = $plans[$plan_key];
    $status = check_training_cooldown($conn, $user_id);

    if ($status['is_training']) {
        return [
            'success' => false,
            'message' => "訓練冷卻中，剩餘 {$status['seconds_remaining']} 秒",
        ];
    }

    $exp = (int)$plan['exp'];
    $stat = (int)$plan['stat'];
    $dur = (int)$plan['duration_sec'];

    // 原子性更新：WHERE 確保 last_train_time 仍為 NULL（防止並發雙重領獎）
    $stmt = $conn->prepare("UPDATE users SET last_train_time=NOW(), train_duration=?, exp=exp+?, stat_points=stat_points+? WHERE id=? AND last_train_time IS NULL");
    $stmt->bind_param('iiii', $dur, $exp, $stat, $user_id);
    $stmt->execute();
    if ($stmt->affected_rows !== 1) {
        $stmt->close();
        return ['success' => false, 'message' => '訓練已在冷卻中，無法重複領取'];
    }
    $stmt->close();

    $stmt2 = $conn->prepare("INSERT INTO training_logs (user_id, exp_gained, stat_points_gained) VALUES (?,?,?)");
    $stmt2->bind_param('iii', $user_id, $exp, $stat);
    $stmt2->execute();
    $stmt2->close();

    return [
        'success' => true,
        'exp_gained' => $exp,
        'stat_gained' => $stat,
        'duration_sec' => $dur,
        'label' => $plan['label'],
        'message' => "訓練開始！獲得 {$exp} EXP 與 {$stat} 屬性點",
    ];
}

