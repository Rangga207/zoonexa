<?php
require 'config.php';
requireLogin();

$page_title = 'AI Health Assistant';
$user_id = $_SESSION['user_id'];

// =============================================
// GET USER DATA (aligned with actual schema)
// =============================================
function getUserChatbotData($user_id) {
    global $mysqli;

    $userData = [
        'username'            => 'User',
        'health_mode'         => 'maintain',
        'points'              => 0,
        'subscription_active' => false,
    ];

    $stmt = $mysqli->prepare("
        SELECT username, health_mode, points, subscription_status
        FROM users
        WHERE id = ?
    ");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $userData['username']            = $row['username'] ?? 'User';
            $userData['health_mode']         = $row['health_mode'] ?? 'maintain';
            $userData['points']              = (int)($row['points'] ?? 0);
            $userData['subscription_active'] = (bool)($row['subscription_status'] ?? false);
        }
    }
    return $userData;
}

// =============================================
// GET HEALTH DATA (aligned with actual schema)
// health_logs columns: id, user_id, log_date, steps, sleep_hours, weight_kg, bmi
// =============================================
function getUserHealthData($user_id) {
    global $mysqli;

    $healthData = [
        'recent_logs' => [],
        'averages'    => [],
        'streak'      => 0,
    ];

    // Recent logs (last 7)
    $stmt = $mysqli->prepare("
        SELECT log_date, steps, sleep_hours, weight_kg, bmi
        FROM health_logs
        WHERE user_id = ?
        ORDER BY log_date DESC
        LIMIT 7
    ");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $healthData['recent_logs'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    // Averages
    $stmt = $mysqli->prepare("
        SELECT
            ROUND(AVG(steps))       as avg_steps,
            ROUND(AVG(sleep_hours), 1) as avg_sleep,
            ROUND(AVG(weight_kg), 1)   as avg_weight,
            ROUND(AVG(bmi), 1)         as avg_bmi,
            COUNT(*)                   as total_logs
        FROM health_logs
        WHERE user_id = ?
    ");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && $row['total_logs'] > 0) {
            $healthData['averages'] = [
                'steps'      => (int)($row['avg_steps'] ?? 0),
                'sleep'      => (float)($row['avg_sleep'] ?? 0),
                'weight_kg'  => (float)($row['avg_weight'] ?? 0),
                'bmi'        => (float)($row['avg_bmi'] ?? 0),
                'total_logs' => (int)($row['total_logs'] ?? 0),
            ];
        }
    }

    // Streak: count logs in last 7 days as a simple streak indicator
    $stmt = $mysqli->prepare("
        SELECT COUNT(*) as streak
        FROM health_logs
        WHERE user_id = ? AND log_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $healthData['streak'] = (int)($row['streak'] ?? 0);
    }

    return $healthData;
}

$userData   = getUserChatbotData($user_id);
$healthData = getUserHealthData($user_id);

// =============================================
// GROQ AI
// =============================================
function callGroqAI($user_message, $context_data) {
    $api_key = GROQ_API_KEY;
    $api_url = GROQ_API_URL;

    if (empty($api_key) || $api_key === 'YOUR_GROQ_API_KEY_HERE') {
        return "Groq API key is not configured. Please contact the admin.";
    }

    // Build context text
    $info = $context_data['user_info'];
    $hd   = $context_data['health_data'];

    $ctx  = "USER PROFILE:\n";
    $ctx .= "- Name: {$info['username']}\n";
    $ctx .= "- Health Mode: {$info['health_mode']}\n";
    $ctx .= "- Points: {$info['points']}\n";
    $ctx .= "- Subscription: " . ($info['subscription_active'] ? 'Active' : 'Inactive') . "\n\n";

    $ctx .= "HEALTH DATA (from actual DB):\n";
    if (!empty($hd['averages'])) {
        $avg = $hd['averages'];
        $ctx .= "- Avg daily steps: {$avg['steps']}\n";
        $ctx .= "- Avg sleep: {$avg['sleep']} hours/night\n";
        $ctx .= "- Avg weight: {$avg['weight_kg']} kg\n";
        $ctx .= "- Avg BMI: {$avg['bmi']}\n";
        $ctx .= "- Total logs: {$avg['total_logs']}\n";
    } else {
        $ctx .= "- No health logs recorded yet\n";
    }
    $ctx .= "- Check-in streak (last 7 days): {$hd['streak']} days\n\n";

    $ctx .= "ZOONEXA SYSTEM:\n";
    $ctx .= "- Subscription: Rp 10,000/month via Midtrans\n";
    $ctx .= "- Payments: bank transfer, e-wallet (GoPay/OVO/Dana/ShopeePay), QRIS\n";
    $ctx .= "- Points earned only after subscribing via daily task completion\n";
    $ctx .= "- Health modes: Maintain, Bulking, Cutting\n";
    $ctx .= "- Tracked metrics: steps, sleep hours, weight (kg), BMI\n";
    $ctx .= "- Today: " . date('Y-m-d') . "\n";

    // Build messages — prepend history then add system
    $history = [];
    if (!empty($_SESSION['chat_history'])) {
        $recent = array_slice($_SESSION['chat_history'], -5); // last 5 exchanges
        foreach ($recent as $h) {
            $history[] = ["role" => "user",      "content" => $h['user']];
            $history[] = ["role" => "assistant",  "content" => $h['assistant']];
        }
    }

    $messages = array_merge(
        [["role" => "system", "content" =>
            "You are Zoonexa Health Assistant — a friendly, encouraging AI health coach built into the Zoonexa health tracking app.

CONTEXT ABOUT THIS USER:
{$ctx}

YOUR RULES:
1. Always personalize responses using the user's actual data above.
2. Keep responses concise — 2-3 short paragraphs max, or use bullet points for lists.
3. For medical questions, recommend consulting a healthcare professional.
4. Respond in the same language the user uses (English or Indonesian).
5. If user hasn't logged health data, encourage them to start.
6. Explain how subscribing unlocks points, milestones, and rewards."]],
        $history,
        [["role" => "user", "content" => $user_message]]
    );

    $payload = json_encode([
        "model"       => "llama-3.1-8b-instant",
        "messages"    => $messages,
        "temperature" => 0.7,
        "max_tokens"  => 600,
    ]);

    $context = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => implode("\r\n", [
                'Content-Type: application/json',
                "Authorization: Bearer $api_key",
                'Content-Length: ' . strlen($payload),
            ]),
            'content'       => $payload,
            'timeout'       => 15,
            'ignore_errors' => true,  // allows reading error responses
        ],
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ],
    ]);

    $response  = @file_get_contents($api_url, false, $context);
    $http_code = 0;

    // Parse HTTP status code from response headers
    if (!empty($http_response_header)) {
        foreach ($http_response_header as $header) {
            if (preg_match('#HTTP/\S+\s+(\d+)#', $header, $m)) {
                $http_code = (int)$m[1];
            }
        }
    }

    if ($response === false) {
        error_log("Groq request failed: unable to connect to $api_url");
        return "Connection to AI failed. Please try again in a moment.";
    }

    if ($http_code !== 200) {
        error_log("Groq HTTP $http_code: $response");
        return "AI is busy. Please try again in a few seconds.";
    }

    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content']
        ?? "Sorry, I cannot respond right now. Please try again!";
}

// Parse markdown → HTML
function parseMarkdownToHtml($text) {
    $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.+?)\*/s',     '<em>$1</em>',         $text);
    $text = preg_replace('/_(.+?)_/s',       '<em>$1</em>',         $text);
    $text = preg_replace('/^- (.+)$/m',      '• $1',                $text);
    $text = preg_replace('/^\* (.+)$/m',     '• $1',                $text);
    $text = nl2br($text);
    return $text;
}

// =============================================
// SIMPLE RATE LIMIT: max 30 messages/hour per user
// =============================================
if (!isset($_SESSION['chat_rate'])) {
    $_SESSION['chat_rate'] = ['count' => 0, 'reset' => time() + 3600];
}
if (time() > $_SESSION['chat_rate']['reset']) {
    $_SESSION['chat_rate'] = ['count' => 0, 'reset' => time() + 3600];
}
$rateLimited = $_SESSION['chat_rate']['count'] >= 30;

// Initialize chat history
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}

// =============================================
// HANDLE POST
// =============================================
$ai_response       = '';
$new_message_added = false;
$posted_message    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $user_message = trim($_POST['message']);

    if (!empty($user_message) && !$rateLimited) {
        $_SESSION['chat_rate']['count']++;

        $context_data = [
            'user_info'   => $userData,
            'health_data' => $healthData,
        ];

        $ai_response = callGroqAI($user_message, $context_data);

        // Add to history BEFORE rendering
        $_SESSION['chat_history'][] = [
            'user'      => $user_message,
            'assistant' => $ai_response,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        // Keep last 20
        if (count($_SESSION['chat_history']) > 20) {
            array_shift($_SESSION['chat_history']);
        }

        $new_message_added = true;
        $posted_message    = $user_message;

    } elseif ($rateLimited) {
        $ai_response = "You have sent too many messages. Please try again in 1 hour!";
    }
}

// Snapshot history for rendering (all of it, already includes new message)
$chat_history = $_SESSION['chat_history'];

include 'header.php';
?>

<style>
/* ============================================= */
/* CHATBOT PAGE STYLES - MOBILE FIRST */
/* ============================================= */

/* Tab switcher (mobile only) */
.chat-tabs {
    display: none;
    gap: 0;
    background: var(--bg-secondary);
    border-radius: 12px;
    padding: 4px;
    margin-bottom: 14px;
    border: 1px solid var(--border);
}
.chat-tab-btn {
    flex: 1;
    padding: 9px 12px;
    border: none;
    border-radius: 9px;
    background: transparent;
    color: var(--text-muted);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}
.chat-tab-btn.active {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    box-shadow: 0 2px 8px rgba(22,160,133,0.3);
}

/* Stats row */
.chatbot-stats-row {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}
.stat-pill {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px 16px;
    display: flex;
    flex-direction: column;
    gap: 2px;
    flex: 1;
    min-width: 80px;
}
.stat-pill .pill-label { font-size: 10px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px; }
.stat-pill .pill-value { font-size: 18px; font-weight: 700; color: var(--primary); line-height: 1.2; }

/* Main layout */
.chatbot-layout {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    align-items: start;
}

.chat-container {
    display: flex;
    flex-direction: column;
    height: 560px;
    background: var(--bg-card);
    border-radius: 14px;
    border: 1px solid var(--border);
    overflow: hidden;
    box-shadow: var(--shadow-md);
}

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    background: var(--bg-secondary);
}

.chat-messages::-webkit-scrollbar { width: 5px; }
.chat-messages::-webkit-scrollbar-track { background: transparent; }
.chat-messages::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }

.message {
    max-width: 80%;
    animation: msgIn 0.25s ease-out;
}

@keyframes msgIn {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
}

.user-message { align-self: flex-end; }
.bot-message  { align-self: flex-start; }

.message-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 5px;
    font-size: 12px;
    gap: 12px;
}
.message-header strong { color: var(--text-dark); display: flex; align-items: center; gap: 5px; }
.message-time { color: var(--text-muted); font-size: 11px; white-space: nowrap; }

.message-content {
    padding: 11px 15px;
    border-radius: 12px;
    line-height: 1.65;
    font-size: 14px;
    word-wrap: break-word;
}
.user-message .message-content {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border-bottom-right-radius: 4px;
}
.bot-message .message-content {
    background: var(--bg-card);
    color: var(--text-body);
    border: 1px solid var(--border);
    border-bottom-left-radius: 4px;
    box-shadow: var(--shadow-sm);
}
.bot-message .message-content strong { color: var(--primary); }
.user-message .message-content strong { color: white; }

.chat-input-area {
    display: flex;
    gap: 8px;
    padding: 12px 14px;
    background: var(--bg-card);
    border-top: 1px solid var(--border);
}
.chat-input-area input {
    flex: 1;
    padding: 11px 14px;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: var(--bg-secondary);
    color: var(--text-body);
    font-size: 14px;
}
.chat-input-area input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(22,160,133,0.1);
}
.chat-input-area button {
    padding: 11px 18px;
    border-radius: 10px;
    border: none;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 7px;
    transition: all 0.2s;
    white-space: nowrap;
}
.chat-input-area button:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(22,160,133,0.3); }
.chat-input-area button:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

/* Sidebar panel */
.sidebar-panel { display: flex; flex-direction: column; gap: 0; }
.health-summary { display: flex; flex-direction: column; gap: 9px; margin-top: 12px; }
.health-metric {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    background: var(--bg-secondary);
    border-radius: 10px;
    border: 1px solid var(--border);
}
.metric-icon {
    width: 36px; height: 36px;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; border-radius: 9px; font-size: 15px; flex-shrink: 0;
}
.metric-info strong { display: block; font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 1px; }
.metric-value { font-size: 14px; font-weight: 600; color: var(--text-dark); margin: 0; }

.no-data { text-align: center; padding: 24px 16px; color: var(--text-muted); }
.no-data p { margin-bottom: 12px; font-size: 14px; }
.btn-record {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 9px 18px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; border-radius: 8px; font-weight: 600; font-size: 13px;
    transition: all 0.2s; text-decoration: none;
}
.btn-record:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(22,160,133,0.3); color: white; }

.quick-questions { margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--border); }
.quick-questions h4 { color: var(--text-dark); margin-bottom: 10px; font-size: 14px; display: flex; align-items: center; gap: 7px; }
.question-buttons { display: flex; flex-direction: column; gap: 6px; }
.question-btn {
    width: 100%; padding: 9px 13px;
    background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px;
    color: var(--text-body); font-size: 13px; cursor: pointer;
    transition: all 0.2s; display: flex; align-items: center; gap: 7px;
    text-align: left; font-family: inherit;
}
.question-btn:hover {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; border-color: var(--primary); transform: translateX(3px);
}

.recent-logs { margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--border); }
.recent-logs h4 { color: var(--text-dark); margin-bottom: 10px; font-size: 14px; display: flex; align-items: center; gap: 7px; }
.logs-table table { width: 100%; border-collapse: collapse; font-size: 12px; }
.logs-table th { text-align: left; padding: 7px 8px; background: var(--bg-secondary); color: var(--text-dark); font-weight: 600; border-bottom: 2px solid var(--border); }
.logs-table td { padding: 7px 8px; border-bottom: 1px solid var(--border); color: var(--text-body); }
.logs-table tr:last-child td { border-bottom: none; }

/* ============================================= */
/* RESPONSIVE */
/* ============================================= */
@media (max-width: 900px) {
    .chatbot-layout { grid-template-columns: 1fr; }
    .chat-container { height: 500px; }
}

@media (max-width: 640px) {
    /* Show tab switcher, hide sidebar by default */
    .chat-tabs { display: flex; }
    .chatbot-layout { display: block; }

    .chat-panel { display: block; }
    .sidebar-panel { display: none; }
    .sidebar-panel.tab-active { display: block; }
    .chat-panel.tab-hidden { display: none; }

    /* Chat fills screen height */
    .chat-container {
        height: calc(100svh - 260px);
        min-height: 320px;
        border-radius: 12px;
    }

    /* Smaller stats row */
    .chatbot-stats-row { gap: 7px; margin-bottom: 12px; }
    .stat-pill { padding: 8px 10px; min-width: 0; }
    .stat-pill .pill-label { font-size: 9px; }
    .stat-pill .pill-value { font-size: 16px; }

    /* Input area touch-friendly */
    .chat-input-area { padding: 10px 12px; gap: 8px; }
    .chat-input-area input { font-size: 16px; /* prevents iOS zoom */ padding: 12px 14px; }
    .chat-input-area button span { display: none; }
    .chat-input-area button { padding: 12px 14px; }

    /* Messages */
    .message { max-width: 90%; }
    .message-content { font-size: 14px; }

    /* Page header compact */
    .page-header h1 { font-size: 20px; }
    .page-header p { font-size: 13px; }

    /* Quick questions horizontal scroll on mobile */
    .question-buttons { flex-direction: row; flex-wrap: wrap; }
    .question-btn { flex: 1; min-width: 140px; font-size: 12px; padding: 8px 10px; }
}
</style>

<section class="page-section">

  <!-- Page Header -->
  <div class="page-header">
    <div>
      <h1><i class="fas fa-robot" style="color: var(--primary);"></i> AI Health Assistant</h1>
      <p class="muted">Powered by Zoonexa AI &middot; Get personalized health insights</p>
    </div>
  </div>

  <!-- Stats Row -->
  <div class="chatbot-stats-row">
    <div class="stat-pill">
      <span class="pill-label">Health Points</span>
      <span class="pill-value"><?php echo number_format($userData['points']); ?></span>
    </div>
    <div class="stat-pill">
      <span class="pill-label">Mode</span>
      <span class="pill-value" style="font-size: 15px; text-transform: capitalize;"><?php echo e($userData['health_mode']); ?></span>
    </div>
    <div class="stat-pill">
      <span class="pill-label">Streak (7 days)</span>
      <span class="pill-value"><?php echo $healthData['streak']; ?> <span style="font-size:12px;color:var(--text-muted)">days</span></span>
    </div>
    <div class="stat-pill">
      <span class="pill-label">Subscription</span>
      <span class="pill-value" style="font-size:13px; color: <?php echo $userData['subscription_active'] ? 'var(--success)' : 'var(--warning)'; ?>;">
        <?php echo $userData['subscription_active'] ? 'Active' : 'Free'; ?>
      </span>
    </div>
  </div>

  <!-- Mobile Tab Switcher -->
  <div class="chat-tabs" id="chatTabs">
    <button class="chat-tab-btn active" id="tabChat" onclick="switchTab('chat')">
      <i class="fas fa-comments"></i> Chat
    </button>
    <button class="chat-tab-btn" id="tabSummary" onclick="switchTab('summary')">
      <i class="fas fa-chart-bar"></i> Ringkasan
    </button>
  </div>

  <div class="chatbot-layout">

    <!-- ========= Left: Chat Interface ========= -->
    <div class="chat-panel" id="chatPanel">
    <div class="chat-container">
      <div class="chat-messages" id="chatContainer">

        <!-- Welcome Message -->
        <div class="message bot-message">
          <div class="message-header">
            <strong><i class="fas fa-robot"></i> Zoonexa AI</strong>
            <span class="message-time">Now</span>
          </div>
          <div class="message-content">
            Hi <strong><?php echo e($userData['username']); ?></strong>, I'm Zoonexa AI &mdash; your health assistant.<br><br>
            <?php if (!empty($healthData['averages'])): ?>
              <strong>Your data at a glance:</strong><br>
              Steps: <strong><?php echo number_format($healthData['averages']['steps']); ?>/day avg</strong><br>
              Sleep: <strong><?php echo $healthData['averages']['sleep']; ?> hrs/night avg</strong><br>
              Weight: <strong><?php echo $healthData['averages']['weight_kg']; ?> kg avg</strong><br>
              BMI: <strong><?php echo $healthData['averages']['bmi']; ?></strong><br><br>
            <?php else: ?>
              You have no health data yet. <a href="health_log.php">Start your first log</a> to get personalized insights.<br><br>
            <?php endif; ?>
            What can I help you with today?
          </div>
        </div>

        <!-- Chat History (all messages, including the just-posted one) -->
        <?php foreach ($chat_history as $chat): ?>
          <div class="message user-message">
            <div class="message-header">
              <strong><i class="fas fa-user"></i> You</strong>
              <span class="message-time"><?php echo date('H:i', strtotime($chat['timestamp'])); ?></span>
            </div>
            <div class="message-content"><?php echo e($chat['user']); ?></div>
          </div>
          <div class="message bot-message">
            <div class="message-header">
              <strong><i class="fas fa-robot"></i> Zoonexa AI</strong>
              <span class="message-time"><?php echo date('H:i', strtotime($chat['timestamp'])); ?></span>
            </div>
            <div class="message-content"><?php echo parseMarkdownToHtml($chat['assistant']); ?></div>
          </div>
        <?php endforeach; ?>

      </div><!-- end chat-messages -->

      <!-- Chat Form -->
      <form method="POST" action="" class="chat-input-area" id="chatForm">
        <input
          type="text"
          name="message"
          id="messageInput"
          placeholder="Tanya tentang kesehatanmu..."
          autocomplete="off"
          required
          <?php echo $rateLimited ? 'disabled' : ''; ?>
        >
        <button type="submit" id="sendButton" <?php echo $rateLimited ? 'disabled' : ''; ?>>
          <i class="fas fa-paper-plane"></i>
          <span>Kirim</span>
        </button>
      </form>

      <?php if ($rateLimited): ?>
        <div style="padding: 8px 16px 12px; font-size: 12px; color: var(--warning); text-align: center;">
          ⚠️ Batas pesan tercapai. Coba lagi dalam 1 jam.
        </div>
      <?php endif; ?>
    </div><!-- end chat-container -->
    </div><!-- end chat-panel -->


    <!-- ========= Right: Health Summary Sidebar ========= -->
    <div class="sidebar-panel" id="summaryPanel">
    <div class="card" style="padding: 20px;">
      <h3 style="font-size: 15px; margin-bottom: 4px;"><i class="fas fa-chart-bar"></i> Health Summary</h3>

      <div class="health-summary">
        <?php if (!empty($healthData['averages'])): ?>
          <div class="health-metric">
            <div class="metric-icon"><i class="fas fa-walking"></i></div>
            <div class="metric-info">
              <strong>Steps</strong>
              <p class="metric-value"><?php echo number_format($healthData['averages']['steps']); ?> avg/day</p>
            </div>
          </div>
          <div class="health-metric">
            <div class="metric-icon"><i class="fas fa-moon"></i></div>
            <div class="metric-info">
              <strong>Sleep</strong>
              <p class="metric-value"><?php echo $healthData['averages']['sleep']; ?> hrs avg/night</p>
            </div>
          </div>
          <div class="health-metric">
            <div class="metric-icon"><i class="fas fa-weight"></i></div>
            <div class="metric-info">
              <strong>Weight</strong>
              <p class="metric-value"><?php echo $healthData['averages']['weight_kg']; ?> kg avg</p>
            </div>
          </div>
          <div class="health-metric">
            <div class="metric-icon"><i class="fas fa-chart-line"></i></div>
            <div class="metric-info">
              <strong>BMI</strong>
              <p class="metric-value"><?php echo $healthData['averages']['bmi']; ?> avg</p>
            </div>
          </div>
          <div class="health-metric">
            <div class="metric-icon"><i class="fas fa-fire"></i></div>
            <div class="metric-info">
              <strong>Log Streak</strong>
              <p class="metric-value"><?php echo $healthData['streak']; ?> of last 7 days</p>
            </div>
          </div>
        <?php else: ?>
          <div class="no-data">
            <p><i class="fas fa-clipboard-list"></i> No health data yet.</p>
            <a href="health_log.php" class="btn-record"><i class="fas fa-plus-circle"></i> Start Logging</a>
          </div>
        <?php endif; ?>
      </div>

      <!-- Quick Questions -->
      <div class="quick-questions">
        <h4><i class="fas fa-lightbulb"></i> Try asking:</h4>
        <div class="question-buttons">
          <?php
          $quickQuestions = [
            ['fas fa-bed',        'Tips for better sleep?'],
            ['fas fa-dumbbell',   'Exercise recommendations for my mode?'],
            ['fas fa-chart-line', 'Analyze my health progress'],
            ['fas fa-utensils',   'Nutrition advice for my goals?'],
            ['fas fa-bullseye',   'Which health mode suits me?'],
            ['fas fa-crown',      'What does a subscription unlock?'],
          ];
          foreach ($quickQuestions as $q):
          ?>
          <form method="POST" action="" style="margin: 0;">
            <input type="hidden" name="message" value="<?php echo e($q[1]); ?>">
            <button type="submit" class="question-btn">
              <i class="fas <?php echo $q[0]; ?>"></i> <?php echo e($q[1]); ?>
            </button>
          </form>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Recent Logs -->
      <?php if (!empty($healthData['recent_logs'])): ?>
      <div class="recent-logs">
        <h4><i class="fas fa-calendar-alt"></i> Recent Logs</h4>
        <div class="logs-table">
          <table>
            <thead>
              <tr>
                <th>Date</th>
                <th>Steps</th>
                <th>Sleep</th>
                <th>BMI</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (array_slice($healthData['recent_logs'], 0, 5) as $log): ?>
              <tr>
                <td><?php echo date('M d', strtotime($log['log_date'])); ?></td>
                <td><?php echo number_format($log['steps'] ?? 0); ?></td>
                <td><?php echo number_format($log['sleep_hours'] ?? 0, 1); ?>h</td>
                <td><?php echo number_format($log['bmi'] ?? 0, 1); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>
    </div><!-- end card -->
    </div><!-- end sidebar-panel -->

  </div><!-- end chatbot-layout -->
</section>

<script>
// Auto-scroll ke bawah
function scrollToBottom() {
    const c = document.getElementById('chatContainer');
    if (c) c.scrollTop = c.scrollHeight;
}

window.addEventListener('load', () => {
    scrollToBottom();
    const inp = document.getElementById('messageInput');
    if (inp) inp.focus();
});

// Loading state on submit
document.getElementById('chatForm')?.addEventListener('submit', function (e) {
    const inp = document.getElementById('messageInput');
    const btn = document.getElementById('sendButton');
    if (!inp || inp.value.trim() === '') { e.preventDefault(); return; }
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Mengirim...</span>';
    }
});

// Mobile tab switcher
function switchTab(tab) {
    const chatPanel    = document.getElementById('chatPanel');
    const summaryPanel = document.getElementById('summaryPanel');
    const tabChat      = document.getElementById('tabChat');
    const tabSummary   = document.getElementById('tabSummary');

    if (tab === 'chat') {
        chatPanel.classList.remove('tab-hidden');
        summaryPanel.classList.remove('tab-active');
        tabChat.classList.add('active');
        tabSummary.classList.remove('active');
        setTimeout(scrollToBottom, 100);
    } else {
        chatPanel.classList.add('tab-hidden');
        summaryPanel.classList.add('tab-active');
        tabSummary.classList.add('active');
        tabChat.classList.remove('active');
    }
}

setTimeout(scrollToBottom, 300);
</script>

<?php include 'footer.php'; ?>
