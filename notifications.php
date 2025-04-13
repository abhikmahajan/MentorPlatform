<?php
require_once 'config.php';
require_once 'includes/NotificationHelper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

try {
    // Get user data
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    // Initialize notification helper
    $notificationHelper = new NotificationHelper($conn);
    
    // Handle mark as read action
    if (isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
        $notification_id = (int)$_POST['notification_id'];
        if ($notificationHelper->markAsRead($notification_id, $user_id)) {
            $success = "Notification marked as read.";
        } else {
            $error = "Failed to mark notification as read.";
        }
    }
    
    // Handle mark all as read action
    if (isset($_POST['mark_all_read'])) {
        if ($notificationHelper->markAllAsRead($user_id)) {
            $success = "All notifications marked as read.";
        } else {
            $error = "Failed to mark all notifications as read.";
        }
    }
    
    // Get notifications
    $notifications = $notificationHelper->getRecent($user_id);
    
    // Get unread count for AJAX
    if (isset($_GET['get_unread_count'])) {
        echo $notificationHelper->getUnreadCount($user_id);
        exit();
    }
    
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Mentor Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4a90e2;
            --secondary-color: #2c3e50;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            background-color: white;
            min-height: 100vh;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            padding: 20px 0;
        }
        
        .sidebar .nav-link {
            color: var(--secondary-color);
            padding: 10px 20px;
            margin: 5px 0;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
        }
        
        .main-content {
            padding: 20px;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid #eee;
            padding: 15px 20px;
        }
        
        .profile-picture {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
        }
        
        .notification-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.3s ease;
        }
        
        .notification-item:hover {
            background-color: #f8f9fa;
        }
        
        .notification-item.unread {
            background-color: #e3f2fd;
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        
        .notification-icon.message {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .notification-icon.session {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .notification-icon.review {
            background-color: #fff3e0;
            color: #f57c00;
        }
        
        .notification-icon.goal {
            background-color: #f3e5f5;
            color: #7b1fa2;
        }
        
        .notification-icon.resource {
            background-color: #e0f2f1;
            color: #00796b;
        }
        
        .notification-time {
            font-size: 0.8rem;
            color: #666;
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-weight: normal;
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
                    <p class="text-muted"><?php echo ucfirst($user['role']); ?></p>
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
                        <a class="nav-link" href="messages.php">
                            <i class="fas fa-comments"></i> Messages
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="sessions.php">
                            <i class="fas fa-calendar"></i> Sessions
                        </a>
                    </li>
                    <?php if ($user['role'] === 'mentor'): ?>
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
                        <li class="nav-item">
                            <a class="nav-link" href="analytics.php">
                                <i class="fas fa-chart-bar"></i> Analytics
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="goals.php">
                                <i class="fas fa-bullseye"></i> Goals
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="find-mentor.php">
                                <i class="fas fa-search"></i> Find Mentor
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link active" href="notifications.php">
                            <i class="fas fa-bell"></i> Notifications
                            <?php if ($notificationHelper->getUnreadCount($user_id) > 0): ?>
                                <span class="badge bg-danger notification-badge">
                                    <?php echo $notificationHelper->getUnreadCount($user_id); ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4>Notifications</h4>
                    <?php if (!empty($notifications)): ?>
                        <form method="POST" action="" class="d-inline">
                            <button type="submit" name="mark_all_read" class="btn btn-outline-primary">
                                <i class="fas fa-check-double"></i> Mark All as Read
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                
                <div class="card">
                    <div class="card-body p-0">
                        <?php if (empty($notifications)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                                <h5>No notifications yet</h5>
                                <p class="text-muted">You'll see your notifications here when you receive them.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                                    <div class="d-flex align-items-start">
                                        <div class="notification-icon <?php echo $notification['type']; ?>">
                                            <?php
                                            switch ($notification['type']) {
                                                case 'message':
                                                    echo '<i class="fas fa-comment"></i>';
                                                    break;
                                                case 'session_request':
                                                case 'session_update':
                                                    echo '<i class="fas fa-calendar"></i>';
                                                    break;
                                                case 'review':
                                                    echo '<i class="fas fa-star"></i>';
                                                    break;
                                                case 'goal_update':
                                                    echo '<i class="fas fa-bullseye"></i>';
                                                    break;
                                                case 'resource':
                                                    echo '<i class="fas fa-book"></i>';
                                                    break;
                                            }
                                            ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                                <?php if (!$notification['is_read']): ?>
                                                    <form method="POST" action="" class="d-inline">
                                                        <input type="hidden" name="notification_id" value="<?php echo $notification['notification_id']; ?>">
                                                        <button type="submit" name="mark_read" class="btn btn-sm btn-link text-primary">
                                                            <i class="fas fa-check"></i> Mark as Read
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                            <p class="mb-1"><?php echo nl2br(htmlspecialchars($notification['message'])); ?></p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="notification-time">
                                                    <?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?>
                                                </small>
                                                <?php if ($notification['link']): ?>
                                                    <a href="<?php echo htmlspecialchars($notification['link']); ?>" class="btn btn-sm btn-outline-primary">
                                                        View Details
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to update unread count in the navigation
        function updateUnreadCount() {
            fetch('notifications.php?get_unread_count=1')
                .then(response => response.text())
                .then(count => {
                    const badge = document.querySelector('.notification-badge');
                    if (badge) {
                        if (count > 0) {
                            badge.textContent = count;
                            badge.style.display = 'inline';
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                });
        }
        
        // Update unread count every 30 seconds
        setInterval(updateUnreadCount, 30000);
        updateUnreadCount(); // Initial check
    </script>
</body>
</html> 