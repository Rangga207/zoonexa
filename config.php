<?php
// config.php - Database Configuration & Session Management
session_start();

// =============================================
// LOAD ENVIRONMENT VARIABLES
// =============================================
function loadEnv($path) {
    if (!file_exists($path)) return false;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\n\r\0\x0B\"");
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
    return true;
}

// Coba load .env dari beberapa lokasi
$envLoaded = loadEnv(__DIR__ . '/.env');
if (!$envLoaded) {
    // Fallback: coba satu level di atas (untuk beberapa konfigurasi hosting)
    $envLoaded = loadEnv(dirname(__DIR__) . '/.env');
}

// Fallback terakhir: load db.secret.php jika .env tidak ditemukan
// File ini bisa dibuat manual di server tanpa perlu push ke git
if (!$envLoaded && file_exists(__DIR__ . '/db.secret.php')) {
    require_once __DIR__ . '/db.secret.php';
}

// =============================================
// DATABASE CONFIG
// =============================================
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'zoonexa_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Midtrans Config
define('MIDTRANS_SERVER_KEY', getenv('MIDTRANS_SERVER_KEY') ?: 'YOUR_SERVER_KEY_HERE');
define('MIDTRANS_CLIENT_KEY', getenv('MIDTRANS_CLIENT_KEY') ?: 'YOUR_CLIENT_KEY_HERE');
define('MIDTRANS_IS_SANDBOX', getenv('MIDTRANS_IS_SANDBOX') === 'false' ? false : true);

// Groq AI Config (untuk chatbot)
define('GROQ_API_KEY', getenv('GROQ_API_KEY') ?: 'YOUR_GROQ_API_KEY_HERE');
define('GROQ_API_URL', getenv('GROQ_API_URL') ?: 'https://api.groq.com/openai/v1/chat/completions');

// =============================================
// MYSQLND COMPATIBILITY LAYER
// Beberapa shared hosting tidak punya ekstensi mysqlnd,
// sehingga $stmt->get_result() tidak tersedia.
// Wrapper ini mengemulasi get_result() tanpa mysqlnd.
// Tidak perlu mengubah file PHP lainnya.
// =============================================

class CompatMysqliResult {
    public $data = [];
    private $pos  = 0;

    public function fetch_assoc() {
        if ($this->pos >= count($this->data)) return null;
        return $this->data[$this->pos++];
    }

    public function fetch_all($mode = MYSQLI_ASSOC) {
        return $this->data;
    }

    public function num_rows() {
        return count($this->data);
    }
}

class CompatMysqliStmt {
    private $inner;

    public function __construct(mysqli_stmt $stmt) {
        $this->inner = $stmt;
    }

    // Forward property reads (e.g. $stmt->num_rows, $stmt->affected_rows)
    public function __get($name) {
        return $this->inner->$name;
    }

    public function __set($name, $value) {
        $this->inner->$name = $value;
    }

    // Forward method calls (execute, close, store_result, etc.)
    public function __call($method, $args) {
        return call_user_func_array([$this->inner, $method], $args);
    }

    // bind_param harus pakai referensi
    public function bind_param($types, &...$vars) {
        $params = [$types];
        foreach ($vars as &$var) {
            $params[] = &$var;
        }
        return call_user_func_array([$this->inner, 'bind_param'], $params);
    }

    // Emulasi get_result() tanpa mysqlnd
    public function get_result() {
        $this->inner->store_result();
        $meta   = $this->inner->result_metadata();
        $result = new CompatMysqliResult();

        if (!$meta) return $result;

        // Ambil nama kolom
        $row = [];
        while ($field = $meta->fetch_field()) {
            $row[$field->name] = null;
        }
        $meta->free();

        // Bind kolom ke $row by reference
        $refs = [];
        foreach (array_keys($row) as $col) {
            $refs[] = &$row[$col];
        }
        call_user_func_array([$this->inner, 'bind_result'], $refs);

        // Fetch semua baris
        while ($this->inner->fetch()) {
            $copy = [];
            foreach ($row as $k => $v) {
                $copy[$k] = $v;
            }
            $result->data[] = $copy;
        }

        return $result;
    }
}

class CompatMysqli extends mysqli {
    // Override prepare() agar mengembalikan CompatMysqliStmt
    #[\ReturnTypeWillChange]
    public function prepare($query) {
        $stmt = parent::prepare($query);
        if ($stmt === false) return false;
        return new CompatMysqliStmt($stmt);
    }
}

// =============================================
// DATABASE CONNECTION
// =============================================
$mysqli = new CompatMysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($mysqli->connect_error) {
    $errMsg = $mysqli->connect_error;
    error_log('Database connection failed: ' . $errMsg);
    // Tampilkan pesan yang lebih informatif (tanpa expose credentials)
    $isEnvMissing = !file_exists(__DIR__ . '/.env') && !file_exists(__DIR__ . '/db.secret.php');
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Database Error · Zoonexa</title>'
        . '<style>body{font-family:sans-serif;background:#0d1117;color:#e6edf3;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}'
        . '.box{background:#161b22;border:1px solid #30363d;border-radius:12px;padding:32px 40px;max-width:480px;text-align:center}'
        . 'h2{color:#f85149;margin-top:0}code{background:#21262d;padding:2px 8px;border-radius:6px;font-size:0.9em}'
        . 'p{color:#8b949e;line-height:1.6}</style></head><body><div class="box">'
        . '<h2>⚠️ Database Connection Error</h2>';
    if ($isEnvMissing) {
        echo '<p>File <code>.env</code> atau <code>db.secret.php</code> <strong>tidak ditemukan</strong> di server.</p>'
            . '<p>Silakan buat file <code>db.secret.php</code> di root folder dengan kredensial database production Anda.</p>'
            . '<p style="color:#58a6ff">Lihat <code>db.secret.php.example</code> untuk template.</p>';
    } else {
        echo '<p>Gagal terhubung ke database. Periksa kredensial di file <code>.env</code> atau <code>db.secret.php</code>.</p>'
            . '<p><code>' . htmlspecialchars($errMsg) . '</code></p>';
    }
    echo '</div></body></html>';
    exit;
}

$mysqli->set_charset('utf8mb4');

// =============================================
// AUTO-MIGRATE NEW SCHEMA (To prevent 500 errors on production)
// =============================================
// Temporarily disable exception mode so ALTER TABLE failures don't kill the page
mysqli_report(MYSQLI_REPORT_OFF);
try {
    $res = @$mysqli->query("SHOW COLUMNS FROM users LIKE 'last_spin_date'");
    if ($res && $res->num_rows === 0) {
        @$mysqli->query("ALTER TABLE users ADD COLUMN last_spin_date DATE DEFAULT NULL");
    }
    
    $res = @$mysqli->query("SHOW COLUMNS FROM users LIKE 'avatar_border'");
    if ($res && $res->num_rows === 0) {
        @$mysqli->query("ALTER TABLE users ADD COLUMN avatar_border VARCHAR(50) DEFAULT 'default'");
    }
    
    $res = @$mysqli->query("SHOW COLUMNS FROM milestones LIKE 'icon'");
    if ($res && $row = $res->fetch_assoc()) {
        if (stripos($row['Type'], 'varchar') === false || (int)preg_replace('/[^0-9]/', '', $row['Type']) < 255) {
            @$mysqli->query("ALTER TABLE milestones MODIFY icon VARCHAR(255)");
        }
    }
} catch (\Throwable $e) {
    // Silently ignore ALL migration errors (including mysqli_sql_exception)
}
// Re-enable exception mode for rest of app
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// =============================================
// CSRF TOKEN
// =============================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrfToken() {
    return $_SESSION['csrf_token'];
}

function verifyCsrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('Invalid CSRF token. Please go back and try again.');
    }
}

// =============================================
// HELPER FUNCTIONS - GENERAL
// =============================================

// Sanitize output
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Require login - redirect ke login.php kalau belum login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// Check if user is admin
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// =============================================
// HELPER FUNCTIONS - SUBSCRIPTION
// =============================================

// Cek subscription status user dari DB
function isSubscribed($user_id = null) {
    global $mysqli;

    if ($user_id === null) {
        if (!isLoggedIn()) return false;
        $user_id = $_SESSION['user_id'];
    }

    $stmt = $mysqli->prepare("SELECT subscription_status FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return $row['subscription_status'] == 1;
    }

    $stmt->close();
    return false;
}

// Require subscription - redirect ke subscription.php kalau belum subscribe
function requireSubscription() {
    requireLogin();
    if (!isSubscribed()) {
        header('Location: subscription.php?locked=1');
        exit;
    }
}

// Ambil data subscription terbaru (yang active) user
function getActiveSubscription($user_id = null) {
    global $mysqli;

    if ($user_id === null) {
        if (!isLoggedIn()) return null;
        $user_id = $_SESSION['user_id'];
    }

    $stmt = $mysqli->prepare("
        SELECT * FROM subscriptions 
        WHERE user_id = ? AND status = 'active' 
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row;
}

// =============================================
// HELPER FUNCTIONS - POINTS
// =============================================

// Ambil points user
function getUserPoints($user_id = null) {
    global $mysqli;

    if ($user_id === null) {
        if (!isLoggedIn()) return 0;
        $user_id = $_SESSION['user_id'];
    }

    $stmt = $mysqli->prepare("SELECT points FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return $row['points'];
    }

    $stmt->close();
    return 0;
}

// Tambah points ke user
function addPoints($points_to_add, $user_id = null) {
    global $mysqli;

    if ($user_id === null) {
        if (!isLoggedIn()) return false;
        $user_id = $_SESSION['user_id'];
    }

    $stmt = $mysqli->prepare("UPDATE users SET points = points + ? WHERE id = ?");
    $stmt->bind_param("ii", $points_to_add, $user_id);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

// =============================================
// HELPER FUNCTIONS - HEALTH MODE
// =============================================

// Ambil health mode user
function getUserHealthMode($user_id = null) {
    global $mysqli;

    if ($user_id === null) {
        if (!isLoggedIn()) return 'maintain';
        $user_id = $_SESSION['user_id'];
    }

    $stmt = $mysqli->prepare("SELECT health_mode FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return $row['health_mode'] ?? 'maintain';
    }

    $stmt->close();
    return 'maintain';
}

// Update health mode user
function setUserHealthMode($mode, $user_id = null) {
    global $mysqli;

    if ($user_id === null) {
        if (!isLoggedIn()) return false;
        $user_id = $_SESSION['user_id'];
    }

    $allowed = ['maintain', 'bulking', 'cutting'];
    if (!in_array($mode, $allowed)) return false;

    $stmt = $mysqli->prepare("UPDATE users SET health_mode = ? WHERE id = ?");
    $stmt->bind_param("si", $mode, $user_id);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

// Ambil daily targets berdasarkan mode
function getDailyTargets($mode = 'maintain') {
    $targets = [
        'maintain' => [
            'steps'    => ['target' => 8000,  'unit' => 'steps',  'points' => 100],
            'sleep'    => ['target' => 7,     'unit' => 'hours',  'points' => 100],
            'water'    => ['target' => 8,     'unit' => 'glasses','points' => 100],
            'exercise' => ['target' => 30,    'unit' => 'min',    'points' => 100],
        ],
        'bulking' => [
            'steps'    => ['target' => 10000, 'unit' => 'steps',  'points' => 125],
            'sleep'    => ['target' => 8,     'unit' => 'hours',  'points' => 125],
            'water'    => ['target' => 10,    'unit' => 'glasses','points' => 100],
            'exercise' => ['target' => 45,    'unit' => 'min',    'points' => 125],
            'calories' => ['target' => 2500,  'unit' => 'kcal',   'points' => 100],
        ],
        'cutting' => [
            'steps'    => ['target' => 12000, 'unit' => 'steps',  'points' => 150],
            'sleep'    => ['target' => 7,     'unit' => 'hours',  'points' => 100],
            'water'    => ['target' => 10,    'unit' => 'glasses','points' => 100],
            'exercise' => ['target' => 60,    'unit' => 'min',    'points' => 150],
            'calories' => ['target' => 1800,  'unit' => 'kcal',   'points' => 100],
        ],
    ];

    return $targets[$mode] ?? $targets['maintain'];
}

// =============================================
// SUBSCRIPTION ACTIVATION (shared helper)
// =============================================
function activateSubscriptionRecord($userId, $orderId, $paymentMethod = 'unknown', $transactionId = '') {
    global $mysqli;

    $startDate = date('Y-m-d');
    $endDate   = date('Y-m-d', strtotime('+30 days'));

    // Update subscription record
    $stmt = $mysqli->prepare("
        UPDATE subscriptions 
        SET status = 'active', 
            start_date = ?, 
            end_date = ?, 
            payment_method = ?,
            midtrans_transaction_id = ?
        WHERE midtrans_order_id = ? AND user_id = ?
    ");
    $stmt->bind_param("sssssi", $startDate, $endDate, $paymentMethod, $transactionId, $orderId, $userId);
    $stmt->execute();
    $stmt->close();

    // Update user subscription_status = 1
    $stmt = $mysqli->prepare("UPDATE users SET subscription_status = 1 WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();

    // Award milestones
    checkAndAwardMilestones($userId);

    error_log("Subscription activated for user $userId, order: $orderId");
}

// =============================================
// HELPER FUNCTIONS - MILESTONES
// =============================================

// Cek dan award milestones
function checkAndAwardMilestones($user_id = null) {
    global $mysqli;

    if ($user_id === null) {
        if (!isLoggedIn()) return;
        $user_id = $_SESSION['user_id'];
    }

    // Ambil semua milestones yang belum dicapai user
    $stmt = $mysqli->prepare("
        SELECT m.id, m.code, m.reward_points FROM milestones m
        WHERE m.id NOT IN (
            SELECT milestone_id FROM user_milestones WHERE user_id = ?
        )
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $pendingMilestones = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($pendingMilestones as $m) {
        $achieved = false;

        switch ($m['code']) {
            case 'first_log':
                $r = $mysqli->prepare("SELECT COUNT(*) as cnt FROM health_logs WHERE user_id = ?");
                $r->bind_param("i", $user_id);
                $r->execute();
                $achieved = $r->get_result()->fetch_assoc()['cnt'] >= 1;
                $r->close();
                break;

            case 'seven_day_streak':
                $achieved = checkConsecutiveStreak($user_id, 7);
                break;

            case 'thirty_day_streak':
                $achieved = checkConsecutiveStreak($user_id, 30);
                break;

            case 'steps_10k':
                $r = $mysqli->prepare("SELECT MAX(steps) as max_steps FROM health_logs WHERE user_id = ?");
                $r->bind_param("i", $user_id);
                $r->execute();
                $achieved = $r->get_result()->fetch_assoc()['max_steps'] >= 10000;
                $r->close();
                break;

            case 'steps_15k':
                $r = $mysqli->prepare("SELECT MAX(steps) as max_steps FROM health_logs WHERE user_id = ?");
                $r->bind_param("i", $user_id);
                $r->execute();
                $achieved = $r->get_result()->fetch_assoc()['max_steps'] >= 15000;
                $r->close();
                break;

            case 'sleep_champion':
                $r = $mysqli->prepare("SELECT MAX(sleep_hours) as max_sleep FROM health_logs WHERE user_id = ?");
                $r->bind_param("i", $user_id);
                $r->execute();
                $achieved = $r->get_result()->fetch_assoc()['max_sleep'] >= 8;
                $r->close();
                break;

            case 'sleep_perfect_week':
                $achieved = checkConsecutiveSleep($user_id, 7, 8);
                break;

            case 'total_logs_10':
                $r = $mysqli->prepare("SELECT COUNT(*) as cnt FROM health_logs WHERE user_id = ?");
                $r->bind_param("i", $user_id);
                $r->execute();
                $achieved = $r->get_result()->fetch_assoc()['cnt'] >= 10;
                $r->close();
                break;

            case 'total_logs_30':
                $r = $mysqli->prepare("SELECT COUNT(*) as cnt FROM health_logs WHERE user_id = ?");
                $r->bind_param("i", $user_id);
                $r->execute();
                $achieved = $r->get_result()->fetch_assoc()['cnt'] >= 30;
                $r->close();
                break;

            case 'total_logs_100':
                $r = $mysqli->prepare("SELECT COUNT(*) as cnt FROM health_logs WHERE user_id = ?");
                $r->bind_param("i", $user_id);
                $r->execute();
                $achieved = $r->get_result()->fetch_assoc()['cnt'] >= 100;
                $r->close();
                break;

            case 'bmi_normal':
                $r = $mysqli->prepare("SELECT COUNT(*) as cnt FROM health_logs WHERE user_id = ? AND bmi BETWEEN 18.5 AND 24.9");
                $r->bind_param("i", $user_id);
                $r->execute();
                $achieved = $r->get_result()->fetch_assoc()['cnt'] >= 1;
                $r->close();
                break;

            case 'first_subscribe':
                $achieved = isSubscribed($user_id);
                break;

            case 'points_1000':
                $achieved = getUserPoints($user_id) >= 1000;
                break;

            case 'points_5000':
                $achieved = getUserPoints($user_id) >= 5000;
                break;
        }

        if ($achieved) {
            $ins = $mysqli->prepare("INSERT IGNORE INTO user_milestones (user_id, milestone_id) VALUES (?, ?)");
            $ins->bind_param("ii", $user_id, $m['id']);
            $ins->execute();
            $ins->close();

            if ($m['reward_points'] > 0) {
                addPoints($m['reward_points'], $user_id);
            }
        }
    }
}

// Helper: cek consecutive log streak
function checkConsecutiveStreak($user_id, $days) {
    global $mysqli;

    $stmt = $mysqli->prepare("
        SELECT log_date FROM health_logs 
        WHERE user_id = ? 
        ORDER BY log_date DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $logs = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (count($logs) < $days) return false;

    $streak = 1;
    for ($i = 1; $i < count($logs); $i++) {
        $curr = new DateTime($logs[$i]['log_date']);
        $prev = new DateTime($logs[$i - 1]['log_date']);
        $diff = $prev->diff($curr)->days;

        if ($diff == 1) {
            $streak++;
            if ($streak >= $days) return true;
        } else {
            $streak = 1;
        }
    }

    return $streak >= $days;
}

// Helper: cek consecutive sleep >= threshold for N days
function checkConsecutiveSleep($user_id, $days, $minHours) {
    global $mysqli;

    $stmt = $mysqli->prepare("
        SELECT log_date, sleep_hours FROM health_logs 
        WHERE user_id = ? 
        ORDER BY log_date DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $logs = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (count($logs) < $days) return false;

    $streak = ($logs[0]['sleep_hours'] >= $minHours) ? 1 : 0;

    for ($i = 1; $i < count($logs); $i++) {
        $curr = new DateTime($logs[$i]['log_date']);
        $prev = new DateTime($logs[$i - 1]['log_date']);
        $diff = $prev->diff($curr)->days;

        if ($diff == 1 && $logs[$i]['sleep_hours'] >= $minHours) {
            $streak++;
            if ($streak >= $days) return true;
        } else {
            $streak = ($logs[$i]['sleep_hours'] >= $minHours) ? 1 : 0;
        }
    }

    return $streak >= $days;
}
?>
