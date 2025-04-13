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
    
    if (!$user) {
        header("Location: logout.php");
        exit();
    }
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Get session ID from URL
$session_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$session_id) {
    header("Location: sessions.php");
    exit();
}

try {
    // Get session details
    $stmt = $conn->prepare("
        SELECT s.*, 
               m.first_name as mentor_first_name,
               m.last_name as mentor_last_name,
               m.profile_picture as mentor_picture,
               m.email as mentor_email,
               e.first_name as mentee_first_name,
               e.last_name as mentee_last_name,
               e.profile_picture as mentee_picture,
               e.email as mentee_email,
               mp.hourly_rate
        FROM sessions s
        JOIN users m ON s.mentor_id = m.user_id
        JOIN users e ON s.mentee_id = e.user_id
        JOIN mentor_profiles mp ON s.mentor_id = mp.mentor_id
        WHERE s.session_id = ? AND (s.mentor_id = ? OR s.mentee_id = ?)
    ");
    $stmt->execute([$session_id, $user_id, $user_id]);
    $session = $stmt->fetch();
    
    if (!$session) {
        header("Location: sessions.php");
        exit();
    }
    
    // Get session review if exists
    $stmt = $conn->prepare("
        SELECT r.*, 
               u.first_name, u.last_name, u.profile_picture
        FROM reviews r
        JOIN users u ON r.mentee_id = u.user_id
        WHERE r.session_id = ?
    ");
    $stmt->execute([$session_id]);
    $review = $stmt->fetch();
    
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle session status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    try {
        $action = $_POST['action'];
        
        if ($action == 'complete' && $role == 'mentor') {
            $stmt = $conn->prepare("UPDATE sessions SET status = 'completed' WHERE session_id = ?");
            $stmt->execute([$session_id]);
            $success = "Session marked as completed successfully!";
        } elseif ($action == 'cancel') {
            $stmt = $conn->prepare("UPDATE sessions SET status = 'cancelled' WHERE session_id = ?");
            $stmt->execute([$session_id]);
            $success = "Session cancelled successfully!";
        }
        
        // Refresh session data
        $stmt = $conn->prepare("
            SELECT s.*, 
                   m.first_name as mentor_first_name,
                   m.last_name as mentor_last_name,
                   m.profile_picture as mentor_picture,
                   m.email as mentor_email,
                   e.first_name as mentee_first_name,
                   e.last_name as mentee_last_name,
                   e.profile_picture as mentee_picture,
                   e.email as mentee_email,
                   mp.hourly_rate
            FROM sessions s
            JOIN users m ON s.mentor_id = m.user_id
            JOIN users e ON s.mentee_id = e.user_id
            JOIN mentor_profiles mp ON s.mentor_id = mp.mentor_id
            WHERE s.session_id = ? AND (s.mentor_id = ? OR s.mentee_id = ?)
        ");
        $stmt->execute([$session_id, $user_id, $user_id]);
        $session = $stmt->fetch();
        
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
    <title>Session Details - Mentor Platform</title>
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
            color: #ffffff;
        }
        
        .card-header {
            background-color: #1f1f3d;
            color: #D9F63F;
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
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        
        .status-scheduled {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .status-completed {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-cancelled {
            background-color: #ffebee;
            color: #c62828;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border: none;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
        }
        
        .review-card {
            background-color: #1f1f3d;
            color: #ffffff;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .review-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .rating {
            color: #ffc107;
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
                
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Session Details</h5>
                        <span class="status-badge status-<?php echo $session['status']; ?>">
                            <?php echo ucfirst($session['status']); ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Mentor Information -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <img src="<?php echo $session['mentor_picture'] ? UPLOAD_URL . $session['mentor_picture'] : 'assets/images/default-avatar.png'; ?>" 
                                             alt="Mentor Picture" 
                                             class="profile-picture mb-3">
                                        <h5><?php echo htmlspecialchars($session['mentor_first_name'] . ' ' . $session['mentor_last_name']); ?></h5>
                                        <p class="text-muted">Mentor</p>
                                        <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($session['mentor_email']); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Mentee Information -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <img src="<?php echo $session['mentee_picture'] ? UPLOAD_URL . $session['mentee_picture'] : 'assets/images/default-avatar.png'; ?>" 
                                             alt="Mentee Picture" 
                                             class="profile-picture mb-3">
                                        <h5><?php echo htmlspecialchars($session['mentee_first_name'] . ' ' . $session['mentee_last_name']); ?></h5>
                                        <p class="text-muted">Mentee</p>
                                        <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($session['mentee_email']); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <!-- Session Details -->
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="mb-3">Session Information</h6>
                                <p><strong>Date:</strong> <?php echo date('M d, Y', strtotime($session['start_time'])); ?></p>
                                <p><strong>Time:</strong> <?php echo date('h:i A', strtotime($session['start_time'])); ?> - <?php echo date('h:i A', strtotime($session['end_time'])); ?></p>
                                <p><strong>Duration:</strong> <?php echo round((strtotime($session['end_time']) - strtotime($session['start_time'])) / 3600); ?> hours</p>
                                <p><strong>Total Cost:</strong> $<?php echo number_format($session['hourly_rate'] * round((strtotime($session['end_time']) - strtotime($session['start_time'])) / 3600), 2); ?></p>
                            </div>
                            
                            <div class="col-md-6">
                                <h6 class="mb-3">Session Notes</h6>
                                <p><?php echo nl2br(htmlspecialchars($session['notes'] ?? 'No notes provided.')); ?></p>
                            </div>
                        </div>
                        
                        <!-- Session Actions -->
                        <div class="mt-4">
                            <?php if ($session['status'] == 'scheduled'): ?>
                                <div class="d-flex justify-content-between">
                                    <a href="messages.php?user=<?php echo $role == 'mentor' ? $session['mentee_id'] : $session['mentor_id']; ?>" 
                                       class="btn btn-outline-primary">
                                        <i class="fas fa-comments"></i> Send Message
                                    </a>
                                    
                                    <div>
                                        <?php if ($role == 'mentor'): ?>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="action" value="complete">
                                                <button type="submit" class="btn btn-success">
                                                    <i class="fas fa-check"></i> Mark Complete
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <form method="POST" action="" class="d-inline">
                                            <input type="hidden" name="action" value="cancel">
                                            <button type="submit" class="btn btn-danger">
                                                <i class="fas fa-times"></i> Cancel Session
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Session Review -->
                        <?php if ($session['status'] == 'completed'): ?>
                            <?php if ($review): ?>
                                <div class="review-card">
                                    <div class="d-flex align-items-center mb-3">
                                        <img src="<?php echo $review['profile_picture'] ? UPLOAD_URL . $review['profile_picture'] : 'assets/images/default-avatar.png'; ?>" 
                                             alt="Reviewer Picture" 
                                             class="review-avatar me-3">
                                        <div>
                                            <h6 class="mb-0">
                                                <?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?>
                                            </h6>
                                            <div class="rating">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                </div>
                            <?php elseif ($role == 'mentee'): ?>
                                <div class="text-center mt-4">
                                    <a href="review-session.php?id=<?php echo $session_id; ?>" class="btn btn-primary">
                                        <i class="fas fa-star"></i> Leave Review
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 