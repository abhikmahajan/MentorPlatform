<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$success = '';
$error = '';

// Get user data
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    // Get all sessions
    $stmt = $conn->prepare("
        SELECT s.*, 
               m.first_name as mentor_first_name,
               m.last_name as mentor_last_name,
               m.profile_picture as mentor_picture,
               e.first_name as mentee_first_name,
               e.last_name as mentee_last_name,
               e.profile_picture as mentee_picture
        FROM sessions s
        JOIN users m ON s.mentor_id = m.user_id
        JOIN users e ON s.mentee_id = e.user_id
        WHERE s.mentor_id = ? OR s.mentee_id = ?
        ORDER BY s.start_time DESC
    ");
    $stmt->execute([$user_id, $user_id]);
    $sessions = $stmt->fetchAll();
    
    // Get upcoming sessions
    $stmt = $conn->prepare("
        SELECT s.*, 
               m.first_name as mentor_first_name,
               m.last_name as mentor_last_name,
               m.profile_picture as mentor_picture,
               e.first_name as mentee_first_name,
               e.last_name as mentee_last_name,
               e.profile_picture as mentee_picture
        FROM sessions s
        JOIN users m ON s.mentor_id = m.user_id
        JOIN users e ON s.mentee_id = e.user_id
        WHERE (s.mentor_id = ? OR s.mentee_id = ?)
        AND s.status = 'scheduled'
        AND s.start_time >= NOW()
        ORDER BY s.start_time ASC
    ");
    $stmt->execute([$user_id, $user_id]);
    $upcoming_sessions = $stmt->fetchAll();
    
    // Get past sessions
    $stmt = $conn->prepare("
        SELECT s.*, 
               m.first_name as mentor_first_name,
               m.last_name as mentor_last_name,
               m.profile_picture as mentor_picture,
               e.first_name as mentee_first_name,
               e.last_name as mentee_last_name,
               e.profile_picture as mentee_picture
        FROM sessions s
        JOIN users m ON s.mentor_id = m.user_id
        JOIN users e ON s.mentee_id = e.user_id
        WHERE (s.mentor_id = ? OR s.mentee_id = ?)
        AND (s.status = 'completed' OR s.start_time < NOW())
        ORDER BY s.start_time DESC
    ");
    $stmt->execute([$user_id, $user_id]);
    $past_sessions = $stmt->fetchAll();
    
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle session status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && isset($_POST['session_id'])) {
    try {
        $session_id = (int)$_POST['session_id'];
        $action = $_POST['action'];
        
        // Verify user has permission to update session
        $stmt = $conn->prepare("
            SELECT * FROM sessions 
            WHERE session_id = ? AND (mentor_id = ? OR mentee_id = ?)
        ");
        $stmt->execute([$session_id, $user_id, $user_id]);
        $session = $stmt->fetch();
        
        if ($session) {
            if ($action == 'complete' && $role == 'mentor') {
                $stmt = $conn->prepare("UPDATE sessions SET status = 'completed' WHERE session_id = ?");
                $stmt->execute([$session_id]);
                $success = "Session marked as completed successfully!";
            } elseif ($action == 'cancel') {
                $stmt = $conn->prepare("UPDATE sessions SET status = 'cancelled' WHERE session_id = ?");
                $stmt->execute([$session_id]);
                $success = "Session cancelled successfully!";
            }
        } else {
            $error = "You don't have permission to update this session.";
        }
    } catch(PDOException $e) {
        $error = "An error occurred. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sessions - Mentor Platform</title>
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
        
        .sidebar .nav-link {
            color: #cbd5e0;
        text-decoration: none;
        display: block;
        padding: 10px 15px;
        transition: background-color 0.2s ease-in-out;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: #1b263b; 
        color: #ffffff;
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
            background-color: #1f1f3d;
            color:white;
        }
        
        .card-header {
            background-color: #1f1f3d;
            color:#D9F63F;
            border-bottom: 1px solid #eee;
            padding: 15px 20px;
        }
        
        .session-card {
            transition: transform 0.3s ease;
        }
        
        .session-card:hover {
            transform: translateY(-5px);
        }
        
        .session-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        
        .status-scheduled {
            background-color: #1f1f3d;
            color: #1976d2;
        }
        
        .status-completed {
            background-color: #1f1f3d;
            color: #2e7d32;
        }
        
        .status-cancelled {
            background-color: #1f1f3d;
            color: #c62828;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border: none;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
        }
        
        .profile-picture {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
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
                        <a class="nav-link" href="messages.php">
                            <i class="fas fa-comments"></i> Messages
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="sessions.php">
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
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Upcoming Sessions -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Upcoming Sessions</h5>
                        <?php if ($role == 'mentee'): ?>
                            <a href="schedule-session.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Schedule New Session
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($upcoming_sessions)): ?>
                            <p class="text-muted">No upcoming sessions</p>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($upcoming_sessions as $session): ?>
                                    <div class="col-md-6 mb-4">
                                        <div class="card session-card">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <div>
                                                        <h6 class="mb-1">
                                                            <?php if ($role == 'mentor'): ?>
                                                                <?php echo htmlspecialchars($session['mentee_first_name'] . ' ' . $session['mentee_last_name']); ?>
                                                            <?php else: ?>
                                                                <?php echo htmlspecialchars($session['mentor_first_name'] . ' ' . $session['mentor_last_name']); ?>
                                                            <?php endif; ?>
                                                        </h6>
                                                        <span class="status-badge status-scheduled">Scheduled</span>
                                                    </div>
                                                    <span class="text-muted">
                                                        <?php echo date('M d, Y h:i A', strtotime($session['start_time'])); ?>
                                                    </span>
                                                </div>
                                                
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <a href="session-details.php?id=<?php echo $session['session_id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary">
                                                            View Details
                                                        </a>
                                                        <form method="POST" action="" class="d-inline">
                                                            <input type="hidden" name="session_id" value="<?php echo $session['session_id']; ?>">
                                                            <input type="hidden" name="action" value="cancel">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                Cancel
                                                            </button>
                                                        </form>
                                                    </div>
                                                    <?php if ($role == 'mentor'): ?>
                                                        <form method="POST" action="" class="d-inline">
                                                            <input type="hidden" name="session_id" value="<?php echo $session['session_id']; ?>">
                                                            <input type="hidden" name="action" value="complete">
                                                            <button type="submit" class="btn btn-sm btn-success">
                                                                Mark Complete
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Past Sessions -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Past Sessions</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($past_sessions)): ?>
                            <p class="text-muted">No past sessions</p>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($past_sessions as $session): ?>
                                    <div class="col-md-6 mb-4">
                                        <div class="card session-card">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <div>
                                                        <h6 class="mb-1">
                                                            <?php if ($role == 'mentor'): ?>
                                                                <?php echo htmlspecialchars($session['mentee_first_name'] . ' ' . $session['mentee_last_name']); ?>
                                                            <?php else: ?>
                                                                <?php echo htmlspecialchars($session['mentor_first_name'] . ' ' . $session['mentor_last_name']); ?>
                                                            <?php endif; ?>
                                                        </h6>
                                                        <span class="status-badge status-<?php echo $session['status']; ?>">
                                                            <?php echo ucfirst($session['status']); ?>
                                                        </span>
                                                    </div>
                                                    <span class="text-muted">
                                                        <?php echo date('M d, Y h:i A', strtotime($session['start_time'])); ?>
                                                    </span>
                                                </div>
                                                
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <a href="session-details.php?id=<?php echo $session['session_id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        View Details
                                                    </a>
                                                    <?php if ($session['status'] == 'completed' && !isset($session['review_id'])): ?>
                                                        <a href="review-session.php?id=<?php echo $session['session_id']; ?>" 
                                                           class="btn btn-sm btn-primary">
                                                            Leave Review
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 