<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get user data
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    // Get all conversations
    $stmt = $conn->prepare("
        SELECT DISTINCT 
            u.user_id,
            u.first_name,
            u.last_name,
            u.profile_picture,
            u.role,
            (SELECT message 
             FROM messages 
             WHERE (sender_id = ? AND receiver_id = u.user_id) 
                OR (sender_id = u.user_id AND receiver_id = ?)
             ORDER BY created_at DESC 
             LIMIT 1) as last_message,
            (SELECT created_at 
             FROM messages 
             WHERE (sender_id = ? AND receiver_id = u.user_id) 
                OR (sender_id = u.user_id AND receiver_id = ?)
             ORDER BY created_at DESC 
             LIMIT 1) as last_message_time,
            (SELECT COUNT(*) 
             FROM messages 
             WHERE sender_id = u.user_id 
                AND receiver_id = ? 
                AND is_read = 0) as unread_count
        FROM messages m
        JOIN users u ON (m.sender_id = u.user_id AND m.receiver_id = ?)
                      OR (m.receiver_id = u.user_id AND m.sender_id = ?)
        WHERE m.sender_id = ? OR m.receiver_id = ?
        GROUP BY u.user_id
        ORDER BY last_message_time DESC
    ");
    $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
    $conversations = $stmt->fetchAll();
    
    // Get messages for selected conversation
    $selected_user_id = isset($_GET['user']) ? (int)$_GET['user'] : null;
    $messages = [];
    $selected_user = null;
    
    if ($selected_user_id) {
        // Get selected user data
        $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$selected_user_id]);
        $selected_user = $stmt->fetch();
        
        if ($selected_user) {
            $stmt = $conn->prepare("
                SELECT m.*, 
                       u.first_name, u.last_name, u.profile_picture
                FROM messages m
                JOIN users u ON m.sender_id = u.user_id
                WHERE (m.sender_id = ? AND m.receiver_id = ?)
                   OR (m.sender_id = ? AND m.receiver_id = ?)
                ORDER BY m.created_at ASC
            ");
            $stmt->execute([$user_id, $selected_user_id, $selected_user_id, $user_id]);
            $messages = $stmt->fetchAll();
            
            // Mark messages as read
            $stmt = $conn->prepare("
                UPDATE messages 
                SET is_read = 1 
                WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
            ");
            $stmt->execute([$selected_user_id, $user_id]);
        }
    }
    
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle new message submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message']) && $selected_user_id) {
    try {
        $message = sanitize_input($_POST['message']);
        $attachment_url = null;
        
        // Handle file upload if present
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            $file = $_FILES['attachment'];
            $file_name = $file['name'];
            $file_tmp = $file['tmp_name'];
            $file_size = $file['size'];
            $file_error = $file['error'];
            
            // Get file extension
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Allowed file types
            $allowed = array('pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png');
            
            if (in_array($file_ext, $allowed)) {
                if ($file_error === 0) {
                    if ($file_size <= MAX_FILE_SIZE) {
                        // Generate unique filename
                        $file_new_name = uniqid('message_') . '.' . $file_ext;
                        $file_destination = UPLOAD_PATH . $file_new_name;
                        
                        if (move_uploaded_file($file_tmp, $file_destination)) {
                            $attachment_url = $file_new_name;
                        }
                    }
                }
            }
        }
        
        // Insert message
        $stmt = $conn->prepare("
            INSERT INTO messages (sender_id, receiver_id, message, attachment_url)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $selected_user_id, $message, $attachment_url]);
        
        // Redirect to refresh the page
        header("Location: messages.php?user=" . $selected_user_id);
        exit();
        
    } catch(PDOException $e) {
        $error = "Error sending message. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Mentor Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #8E2DE2;
            --secondary-color: #4A00E0;
            --accent-color: #D9F63F;
            --highlight-color: #FDFE70;
            --card-bg: #1f1f3d;
            --footer-bg: #15152d;
            --nav-bg: #0d0d2b;
            --white: #ffffff;
            --light-gray: #cccccc;
        }
        
        body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color:rgb(170, 175, 182); /* light grey background */
        color: #333;
    }
        
    .sidebar {
        background-color: #0d1b2a; /* dark blue */
        color: #fff;
        min-height: 100vh;
    }
        
        .profile-picture {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
        }
        
        .sidebar .nav-link {
            color: #cbd5e0;
        text-decoration: none;
        display: block;
        padding: 10px 15px;
        transition: background-color 0.2s ease-in-out;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: #1b263b; /* slightly lighter blue on hover */
            color: #ffffff;
        }
        
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
        }
        
        .main-content {
            height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 20px;
            overflow-y: hidden;
        }
        
        .conversation-list {
            height: calc(100vh - 100px);
            overflow-y: auto;
            background-color: #1f1f3d;
            color:white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .conversation-item {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            background-color: #ffffff;
            color: #1f1f3d;
            display: block;
        }
        
        .conversation-item:hover {
            background-color: var(--light-gray);
        }
        
        .conversation-item.active {
            background-color: #e3f2fd;
        }
        
        .conversation-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
        }
        
        .chat-container {
            height: calc(100vh - 100px);
            flex: 1;
            display: flex;
            flex-direction: column;
            background-color: #1f1f3d;
            color:white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .chat-header {
            padding: 15px 20px;
            background-color: #1f1f3d;
            color:white;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
        }
        
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background-color: #ffffff;
            color: #1f1f3d;
            background-image: url("data:image/svg+xml,%3Csvg width='20' height='20' viewBox='0 0 20 20' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23f9f9f9' fill-opacity='1' fill-rule='evenodd'%3E%3Ccircle cx='3' cy='3' r='3'/%3E%3Ccircle cx='13' cy='13' r='3'/%3E%3C/g%3E%3C/svg%3E");
        }
        
        .message {
            margin-bottom: 20px;
            display: flex;
            align-items: flex-end;
            gap: 8px;
            max-width: 85%;
            background-color: #ffffff;
            color: #1f1f3d;
        }
        
        .message.sent {
            flex-direction: row-reverse;
            margin-left: auto;
            
        }
        
        .message-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            
        }
        
        .message-content {
            padding: 12px 16px;
            border-radius: 20px;
            background-color: #ffffff;
            color: #1f1f3d;
            position: relative;
        }
        
        .message.sent .message-content {
            background-color: #ffffff;
            color: #1f1f3d;
            color: white;
            border-bottom-right-radius: 4px;
        }
        
        .message.received .message-content {
            border-bottom-left-radius: 4px;
        }
        
        .message-time {
            font-size: 0.75rem;
            background-color: #ffffff;
            color: #1f1f3d;
            margin-top: 4px;
            display: block;
        }
        
        .message.sent .message-time {
            text-align: right;
            background-color: #ffffff;
            color: #1f1f3d;
            opacity: 0.8;
        }
        
        .chat-input {
            padding: 20px;
            background-color: white;
            border-top: 1px solid var(--border-color);
        }
        
        .chat-input .input-group {
            background-color: var(--light-gray);
            border-radius: 25px;
            padding: 5px;
        }
        
        .chat-input input[type="text"] {
            border: none;
            background: none;
            padding: 10px 15px;
        }
        
        .chat-input input[type="text"]:focus {
            box-shadow: none;
        }
        
        .chat-input .btn {
            border-radius: 50%;
            width: 40px;
            height: 40px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .attachment-preview {
            margin-top: 10px;
            padding: 10px;
            background-color: var(--light-gray);
            border-radius: 10px;
            display: none;
        }
        
        .attachment-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 10px;
        }
        
        .unread-badge {
            background-color: var(--message-sent);
            color: white;
            border-radius: 12px;
            padding: 2px 8px;
            font-size: 0.75rem;
            margin-left: 8px;
        }
        
        /* Custom scrollbar */
        .chat-messages::-webkit-scrollbar,
        .conversation-list::-webkit-scrollbar {
            width: 6px;
        }
        
        .chat-messages::-webkit-scrollbar-track,
        .conversation-list::-webkit-scrollbar-track {
            background: var(--light-gray);
        }
        
        .chat-messages::-webkit-scrollbar-thumb,
        .conversation-list::-webkit-scrollbar-thumb {
            background: #ccc;
            border-radius: 3px;
        }
        
        .chat-messages::-webkit-scrollbar-thumb:hover,
        .conversation-list::-webkit-scrollbar-thumb:hover {
            background: #bbb;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="text-center mb-4">
                    <img src="<?php echo $user['profile_picture'] ? UPLOAD_URL . $user['profile_picture'] : 'assets/images/default-avatar.png'; ?>" 
                         alt="Profile Picture" 
                         class="profile-picture">
                    <h5><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h5>
                    <p class="text-muted"><?php echo ucfirst($role); ?></p>
                </div>
                
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="fas fa-user"></i> Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="messages.php">
                            <i class="fas fa-comments"></i> Messages
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="sessions.php">
                            <i class="fas fa-calendar"></i> Sessions
                        </a>
                    </li>
                    <?php if ($role == 'mentor'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="resources.php">
                                <i class="fas fa-book"></i> Resources
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="earnings.php">
                                <i class="fas fa-dollar-sign"></i> Earnings
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="goals.php">
                                <i class="fas fa-bullseye"></i> Goals
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="mentee-resources.php">
                                <i class="fas fa-book"></i> Resources
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="find-mentor.php">
                                <i class="fas fa-search"></i> Find Mentor
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="row">
                    <!-- Conversations List -->
                    <div class="col-md-4">
                        <div class="conversation-list">
                            <?php foreach ($conversations as $conv): ?>
                                <a href="messages.php?user=<?php echo $conv['user_id']; ?>" 
                                   class="conversation-item <?php echo $selected_user_id == $conv['user_id'] ? 'active' : ''; ?>">
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo $conv['profile_picture'] ? UPLOAD_PATH . $conv['profile_picture'] : 'assets/images/default-avatar.png'; ?>" 
                                             alt="Profile Picture" 
                                             class="conversation-avatar">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0">
                                                <?php echo htmlspecialchars($conv['first_name'] . ' ' . $conv['last_name']); ?>
                                                <?php if ($conv['unread_count'] > 0): ?>
                                                    <span class="unread-badge"><?php echo $conv['unread_count']; ?></span>
                                                <?php endif; ?>
                                            </h6>
                                            <small class="text-muted">
                                                <?php echo substr($conv['last_message'], 0, 50) . '...'; ?>
                                            </small>
                                            <div class="text-muted" style="font-size: 0.8rem;">
                                                <?php echo date('M d, h:i A', strtotime($conv['last_message_time'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Chat Area -->
                    <div class="col-md-8">
                        <?php if ($selected_user_id && $selected_user): ?>
                            <div class="chat-container">
                                <div class="chat-header">
                                    <img src="<?php echo $selected_user['profile_picture'] ? UPLOAD_PATH . $selected_user['profile_picture'] : 'assets/images/default-avatar.png'; ?>" 
                                         alt="Profile Picture" 
                                         class="conversation-avatar">
                                    <div>
                                        <h6 class="mb-0">
                                            <?php echo htmlspecialchars($selected_user['first_name'] . ' ' . $selected_user['last_name']); ?>
                                        </h6>
                                        <small class="text-muted"><?php echo ucfirst($selected_user['role']); ?></small>
                                    </div>
                                </div>
                                
                                <div class="chat-messages" id="chat-messages">
                                    <?php if (!empty($messages)): ?>
                                        <?php foreach ($messages as $message): ?>
                                            <div class="message <?php echo $message['sender_id'] == $user_id ? 'sent' : 'received'; ?>">
                                                <img src="<?php echo $message['profile_picture'] ? UPLOAD_PATH . $message['profile_picture'] : 'assets/images/default-avatar.png'; ?>" 
                                                     alt="Profile Picture" 
                                                     class="message-avatar">
                                                <div class="message-content">
                                                    <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                                    <?php if ($message['attachment_url']): ?>
                                                        <div class="mt-2">
                                                            <?php
                                                            $ext = strtolower(pathinfo($message['attachment_url'], PATHINFO_EXTENSION));
                                                            if (in_array($ext, ['jpg', 'jpeg', 'png'])): ?>
                                                                <img src="<?php echo UPLOAD_PATH . $message['attachment_url']; ?>" 
                                                                     alt="Attachment" 
                                                                     class="img-fluid rounded">
                                                            <?php else: ?>
                                                                <a href="<?php echo UPLOAD_PATH . $message['attachment_url']; ?>" 
                                                                   class="btn btn-sm btn-outline-primary" 
                                                                   target="_blank">
                                                                    <i class="fas fa-paperclip"></i> Download Attachment
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="message-time">
                                                        <?php echo date('h:i A', strtotime($message['created_at'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-center text-muted my-4">
                                            <p>No messages yet. Start the conversation!</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <form method="POST" action="" class="chat-input" enctype="multipart/form-data">
                                    <div class="input-group">
                                        <label for="attachment" class="btn btn-outline-secondary">
                                            <i class="fas fa-paperclip"></i>
                                        </label>
                                        <input type="file" 
                                               id="attachment" 
                                               name="attachment" 
                                               class="d-none" 
                                               accept="image/*,.pdf,.doc,.docx">
                                        <input type="text" 
                                               class="form-control" 
                                               name="message" 
                                               placeholder="Type your message..." 
                                               required>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </div>
                                    <div class="attachment-preview" id="attachment-preview"></div>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="chat-container d-flex align-items-center justify-content-center">
                                <div class="text-center text-muted">
                                    <i class="fas fa-comments fa-3x mb-3"></i>
                                    <h5>Select a conversation to start messaging</h5>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Scroll to bottom of chat messages
        const chatMessages = document.getElementById('chat-messages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Preview attachment before upload
        document.getElementById('attachment').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('attachment-preview');
            
            if (file) {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                        preview.style.display = 'block';
                    }
                    reader.readAsDataURL(file);
                } else {
                    preview.innerHTML = `<i class="fas fa-file"></i> ${file.name}`;
                    preview.style.display = 'block';
                }
            } else {
                preview.style.display = 'none';
            }
        });
        
        // Auto-refresh messages every 30 seconds
        if (window.location.search.includes('user=')) {
            setInterval(function() {
                window.location.reload();
            }, 30000);
        }
    </script>
</body>
</html> 