<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'db_connect.php';

// --- Data Fetching (Reusing logic from MA.php) ---
$user_id = $_SESSION['id'];
$user_role = $_SESSION['role'];
$admin_id = 1; // Assuming admin user ID is 1

// --- Fetch data for the logged-in user ---
$driver_id_result = $conn->query("SELECT id FROM drivers WHERE user_id = $user_id");
$driver_id = $driver_id_result->num_rows > 0 ? $driver_id_result->fetch_assoc()['id'] : null;

// Admin Data
$active_vehicles = $conn->query("SELECT COUNT(*) as count FROM vehicles WHERE status = 'En Route'")->fetch_assoc()['count'];
$pending_alerts = $conn->query("SELECT COUNT(*) as count FROM alerts WHERE status = 'Pending'")->fetch_assoc()['count'];
$live_trips_result = $conn->query("SELECT v.type as vehicle_type, v.model, d.name as driver_name, t.status FROM trips t JOIN vehicles v ON t.vehicle_id = v.id JOIN drivers d ON t.driver_id = d.id WHERE t.status IN ('En Route', 'Breakdown', 'Idle') ORDER BY t.pickup_time DESC LIMIT 5");

// Driver Data
$driver_info = null;
$trip_summary = null;
if ($user_role === 'driver' && $driver_id) {
    $driver_info_sql = "SELECT d.name, v.type as vehicle_type, v.model as vehicle_model, d.status FROM drivers d LEFT JOIN vehicles v ON d.id = v.assigned_driver_id WHERE d.id = ?";
    $stmt = $conn->prepare($driver_info_sql);
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $driver_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $trip_summary_sql = "SELECT t.trip_code, t.destination, t.pickup_time, t.eta, t.status FROM trips t WHERE t.driver_id = ? AND t.status IN ('En Route', 'Scheduled') ORDER BY t.pickup_time DESC LIMIT 1";
    $stmt = $conn->prepare($trip_summary_sql);
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $trip_summary = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Messaging and Alerts Data
$messages_result = $conn->query("SELECT u_sender.username as sender, u_receiver.username as receiver, message_text, sent_at FROM messages JOIN users u_sender ON messages.sender_id = u_sender.id JOIN users u_receiver ON messages.receiver_id = u_receiver.id WHERE (sender_id = $admin_id AND receiver_id = $user_id) OR (sender_id = $user_id AND receiver_id = $admin_id) ORDER BY sent_at ASC");
$sos_alerts_result = $conn->query("SELECT a.created_at, d.name as driver_name, a.description, a.status FROM alerts a JOIN drivers d ON a.driver_id = d.id WHERE a.alert_type = 'SOS' ORDER BY a.created_at DESC LIMIT 5");
$active_drivers_for_messaging = $conn->query("SELECT d.id, d.name, u.id as user_id FROM drivers d JOIN users u ON d.user_id = u.id WHERE d.status = 'Active'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0">
    <title>Mobile App - Logistics II</title>
    <style>
        :root {
            --primary-color: #4e73df; --dark-bg: #1a1a2e; --dark-card: #16213e;
            --text-light: #f8f9fa; --text-dark: #212529; --success-color: #1cc88a;
            --danger-color: #e74a3b; --border-radius: 0.35rem; --secondary-color: #f8f9fc;
            --shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        html, body { 
            margin: 0; 
            padding: 0; 
            height: 100%; 
            width: 100%;
            font-family: 'Segoe UI', system-ui, sans-serif; 
            background-color: var(--secondary-color); 
            overflow: hidden; 
        }
        .app-wrapper { 
            width: 100%; 
            height: 100%; 
            display: flex; 
            flex-direction: column; 
            overflow: hidden; 
            position: relative; 
        }
        .app-header { 
            background: var(--primary-color); 
            color: white; 
            padding: 15px 20px; 
            text-align: center; 
            font-weight: 600; 
            font-size: 1.2rem; 
            flex-shrink: 0; 
            position: relative;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            z-index: 10;
        }
        .app-header .back-link { 
            position: absolute; 
            left: 20px; 
            top: 50%; 
            transform: translateY(-50%); 
            color: white; 
            text-decoration: none; 
            font-size: 1.5rem; 
            line-height: 1; 
        }
        .app-content { 
            flex-grow: 1; 
            overflow-y: auto; 
            padding: 15px; 
        }
        .app-content .page { display: none; }
        .app-content .page.active { display: block; animation: fadeIn 0.3s; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .bottom-nav { 
            display: flex; 
            background: #fff; 
            border-top: 1px solid #ddd; 
            flex-shrink: 0; 
            box-shadow: 0 -2px 5px rgba(0,0,0,0.1);
        }
        .nav-item, a.nav-item { 
            flex: 1; 
            text-align: center; 
            padding: 10px 5px; 
            cursor: pointer; 
            color: #777; 
            transition: all 0.2s; 
            border-top: 3px solid transparent; 
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
        }
        .nav-item.active { 
            color: var(--primary-color); 
            border-top-color: var(--primary-color); 
            font-weight: 600; 
        }
        .nav-item svg { width: 24px; height: 24px; margin-bottom: 3px; }
        .card { 
            background-color: white; 
            border-radius: var(--border-radius); 
            box-shadow: var(--shadow); 
            padding: 1rem; 
            margin-bottom: 1rem; 
        }
        h4 { margin: 0 0 10px 0; color: var(--primary-color); }
        .status-badge { padding: 0.25em 0.6em; font-size: 0.75rem; font-weight: 700; border-radius: 10rem; color: #fff; }
        .status-en.route, .status-completed { background-color: var(--success-color); }
        .status-breakdown { background-color: var(--danger-color); }
        .status-idle, .status-scheduled { background-color: #6c757d; }
        .btn { padding: 0.75rem 1rem; border: none; border-radius: 4px; font-size: 1rem; cursor: pointer; text-decoration: none; display: block; text-align: center; font-weight: 600; }
        .btn-danger { background-color: var(--danger-color); color: white; }
        .btn-primary { background-color: var(--primary-color); color: white; }
        .chat-box { height: calc(100vh - 250px); overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: var(--border-radius); margin-bottom: 10px; font-size: 0.9rem; }
        .chat-input { display: flex; gap: 5px; }
        .chat-input input, .chat-input select { width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; flex-grow: 1; }
        .message { margin-bottom: 8px; padding: 5px; }
        .message.sent { text-align: right; }
        .message.received { text-align: left; }
        .message .bubble { display: inline-block; padding: 8px 12px; border-radius: 15px; max-width: 80%; }
        .message.sent .bubble { background-color: var(--primary-color); color: white; }
        .message.received .bubble { background-color: #e4e6eb; color: var(--text-dark); }
        .sos-button-container { text-align: center; padding: 2rem 0; }
        .sos-button { width: 150px; height: 150px; border-radius: 50%; background: var(--danger-color); color: white; font-size: 2.5rem; font-weight: bold; border: 5px solid #fff; box-shadow: 0 0 20px rgba(231, 74, 59, 0.5); display: flex; align-items: center; justify-content: center; margin: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class="app-wrapper">
        <div class="app-header">
            <?php if ($user_role !== 'driver'): ?>
                <a href="MA.php" class="back-link">&larr;</a>
            <?php endif; ?>
            SLATE Mobile Command
        </div>
        <div class="app-content">
            <!-- Dashboard Page -->
            <div class="page active" id="dashboard">
                <?php if ($user_role === 'driver'): ?>
                    <h4>Driver Dashboard</h4>
                    <div class="card">
                        <?php if ($driver_info): ?>
                            <p><strong>Driver:</strong> <?php echo htmlspecialchars($driver_info['name']); ?></p>
                            <p><strong>Vehicle:</strong> <?php echo htmlspecialchars($driver_info['vehicle_type'] . ' ' . $driver_info['vehicle_model']); ?></p>
                        <?php else: ?>
                            <p>No driver info linked.</p>
                        <?php endif; ?>
                    </div>
                    <div class="card">
                        <h4>Current Trip</h4>
                        <?php if ($trip_summary): ?>
                            <p><strong>Trip:</strong> <?php echo htmlspecialchars($trip_summary['trip_code']); ?></p>
                            <p><strong>Destination:</strong> <?php echo htmlspecialchars($trip_summary['destination']); ?></p>
                            <p><strong>Status:</strong> <span class="status-badge status-<?php echo strtolower(str_replace(' ', '.', $trip_summary['status'])); ?>"><?php echo htmlspecialchars($trip_summary['status']); ?></span></p>
                        <?php else: ?>
                            <p>No active trip.</p>
                        <?php endif; ?>
                    </div>
                <?php elseif ($user_role === 'admin' || $user_role === 'staff'): ?>
                    <h4>Admin Fleet Overview</h4>
                    <div class="card">
                        <p><strong>Active Vehicles:</strong> <?php echo $active_vehicles; ?></p>
                        <p><strong>Pending Alerts:</strong> <span style="color:var(--danger-color); font-weight:bold;"><?php echo $pending_alerts; ?></span></p>
                    </div>
                    <div class="card">
                        <h4>Live Trip Status</h4>
                        <table>
                            <tbody>
                                <?php mysqli_data_seek($live_trips_result, 0); while($trip = $live_trips_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($trip['driver_name']); ?><br><small><?php echo htmlspecialchars($trip['vehicle_type']); ?></small></td>
                                    <td><span class="status-badge status-<?php echo strtolower(str_replace(' ', '.', $trip['status'])); ?>"><?php echo htmlspecialchars($trip['status']); ?></span></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <!-- Messages Page -->
            <div class="page" id="messages">
                <h4>Messages</h4>
                <div class="chat-box" id="chatBox">
                     <?php mysqli_data_seek($messages_result, 0); while($msg = $messages_result->fetch_assoc()): 
                        $is_sent = ($msg['sender'] === $_SESSION['username']);
                     ?>
                        <div class="message <?php echo $is_sent ? 'sent' : 'received'; ?>">
                            <div class="bubble">
                                <?php echo htmlspecialchars($msg['message_text']); ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <form id="messageForm" action="MA.php" method="POST" class="chat-input">
                    <?php if ($user_role !== 'driver'): ?>
                        <select name="receiver_id" required>
                            <option value="">To Driver:</option>
                            <?php mysqli_data_seek($active_drivers_for_messaging, 0); while($driver = $active_drivers_for_messaging->fetch_assoc()): ?>
                            <option value="<?php echo $driver['user_id']; ?>"><?php echo htmlspecialchars($driver['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    <?php endif; ?>
                    <input type="text" name="message_text" id="message_text_input" placeholder="Type a message..." required>
                    <input type="hidden" name="send_message" value="1">
                    <button type="submit" class="btn-primary" style="padding: 0.5rem;">Send</button>
                </form>
            </div>

            <!-- Alerts/SOS Page -->
            <div class="page" id="alerts">
                <?php if ($user_role === 'driver'): ?>
                    <h4>Emergency SOS</h4>
                    <div class="sos-button-container">
                        <form action="MA.php" method="POST">
                            <input type="hidden" name="description" value="Emergency SOS sent from app">
                            <button type="submit" name="send_sos" class="sos-button">SOS</button>
                        </form>
                        <p style="text-align:center; margin-top:1rem; color:#666;">Press only in case of emergency.</p>
                    </div>
                <?php elseif ($user_role === 'admin' || $user_role === 'staff'): ?>
                    <h4>SOS Alerts</h4>
                    <div class="card">
                        <table>
                            <thead><tr><th>Driver</th><th>Time</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php mysqli_data_seek($sos_alerts_result, 0); while($alert = $sos_alerts_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($alert['driver_name']); ?></td>
                                    <td><?php echo htmlspecialchars(date('h:i A', strtotime($alert['created_at']))); ?></td>
                                    <td><span class="status-badge status-<?php echo strtolower($alert['status']); ?>"><?php echo htmlspecialchars($alert['status']); ?></span></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="bottom-nav">
            <div class="nav-item active" data-page="dashboard">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
                Dashboard
            </div>
            <div class="nav-item" data-page="messages">
               <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
                Messages
            </div>
            <div class="nav-item" data-page="alerts">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                <?php echo $user_role === 'driver' ? 'SOS' : 'Alerts'; ?>
            </div>
            <a href="logout.php" class="nav-item">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M16 17v-3H9v-4h7V7l5 5-5 5M14 2a2 2 0 012 2v2h-2V4H5v16h9v-2h2v2a2 2 0 01-2 2H5a2 2 0 01-2-2V4a2 2 0 012-2h9z"/></svg>
                Logout
            </a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab switching logic
            document.querySelectorAll('.nav-item[data-page]').forEach(item => {
                item.addEventListener('click', () => {
                    document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
                    document.querySelectorAll('.page').forEach(page => page.classList.remove('active'));
                    
                    item.classList.add('active');
                    document.getElementById(item.dataset.page).classList.add('active');
                });
            });

            // AJAX form submission for messaging
            const messageForm = document.getElementById('messageForm');
            if (messageForm) {
                messageForm.addEventListener('submit', function(event) {
                    event.preventDefault(); 

                    const formData = new FormData(messageForm);
                    const messageInput = document.getElementById('message_text_input');
                    const chatBox = document.getElementById('chatBox');
                    const messageText = messageInput.value.trim();

                    if (messageText === '') return;

                    // Append new message immediately to the chatbox
                    const newMessageDiv = document.createElement('div');
                    newMessageDiv.className = 'message sent';
                    newMessageDiv.innerHTML = `<div class="bubble">${messageText.replace(/</g, "&lt;").replace(/>/g, "&gt;")}</div>`;
                    chatBox.appendChild(newMessageDiv);
                    chatBox.scrollTop = chatBox.scrollHeight;
                    messageInput.value = '';

                    const submitButton = messageForm.querySelector('button[type="submit"]');
                    submitButton.disabled = true;

                    fetch('MA.php', {
                        method: 'POST',
                        body: formData
                    })
                    .catch(error => {
                        console.error('Error sending message:', error);
                        // Optional: Show an error message or revert the optimistic update
                        chatBox.removeChild(newMessageDiv);
                        messageInput.value = messageText;
                        alert('Could not send message. Please try again.');
                    })
                    .finally(() => {
                        submitButton.disabled = false;
                    });
                });
            }
        });
    </script>
</body>
</html>

