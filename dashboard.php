<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

try {
    // Get user profile data
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    // Get role-specific data
    if ($role == 'mentor') {
        $stmt = $conn->prepare("
            SELECT mp.*, 
                   COUNT(DISTINCT s.session_id) as total_sessions,
                   COUNT(DISTINCT r.review_id) as total_reviews,
                   AVG(r.rating) as average_rating
            FROM mentor_profiles mp
            LEFT JOIN sessions s ON mp.mentor_id = s.mentor_id
            LEFT JOIN reviews r ON mp.mentor_id = r.mentor_id
            WHERE mp.mentor_id = ?
            GROUP BY mp.mentor_id
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT mp.*, 
                   COUNT(DISTINCT s.session_id) as total_sessions,
                   COUNT(DISTINCT g.goal_id) as total_goals
            FROM mentee_profiles mp
            LEFT JOIN sessions s ON mp.mentee_id = s.mentee_id
            LEFT JOIN goals g ON mp.mentee_id = g.mentee_id
            WHERE mp.mentee_id = ?
            GROUP BY mp.mentee_id
        ");
    }
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch();
    
    // Get upcoming sessions
    $stmt = $conn->prepare("
        SELECT s.*, 
               u.first_name, u.last_name, u.email
        FROM sessions s
        JOIN users u ON (s.mentor_id = u.user_id AND ? = 'mentee') 
                      OR (s.mentee_id = u.user_id AND ? = 'mentor')
        WHERE (s.mentor_id = ? OR s.mentee_id = ?)
        AND s.status = 'scheduled'
        AND s.start_time >= NOW()
        ORDER BY s.start_time ASC
        LIMIT 5
    ");
    $stmt->execute([$role, $role, $user_id, $user_id]);
    $upcoming_sessions = $stmt->fetchAll();
    
    // Get recent messages
    $stmt = $conn->prepare("
        SELECT m.*, 
               u.first_name, u.last_name, u.email
        FROM messages m
        JOIN users u ON (m.sender_id = u.user_id AND m.receiver_id = ?)
                      OR (m.receiver_id = u.user_id AND m.sender_id = ?)
        WHERE m.sender_id = ? OR m.receiver_id = ?
        ORDER BY m.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id, $user_id, $user_id, $user_id]);
    $recent_messages = $stmt->fetchAll();
    
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Mentor Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
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

    .sidebar a {
        color: #cbd5e0;
        text-decoration: none;
        display: block;
        padding: 10px 15px;
        transition: background-color 0.2s ease-in-out;
    }

    .sidebar a:hover,
    .sidebar a.active {
        background-color: #1b263b; /* slightly lighter blue on hover */
        color: #ffffff;
    }

    .main-content {
        padding: 20px;
    }

    .card {
        border: none;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        background-color: #1f1f3d;
            color:white;
    }

    .card h3 {
        margin-bottom: 15px;
        font-size: 1.75rem;
        color: #0d1b2a;
    }

    .card p {
        color: #555;
    }

    .stats-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 20px;
        background-color: #e3efff; /* soft blue background */
        border-left: 5px solid #468faf; /* blue accent bar */
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .stats-card i {
        font-size: 2rem;
        color: #468faf;
    }

    .stats-card .stat {
        text-align: right;
    }

    .stats-card .stat h4 {
        margin: 0;
        font-size: 1.5rem;
        color: #0d1b2a;
    }

    .stats-card .stat p {
        margin: 0;
        color: #555;
    }

    .table thead {
        background-color: #468faf;
        color: #fff;
    }

    .table tbody tr:nth-child(even) {
        background-color: #f0f4f8;
    }

    .btn-primary {
        background-color: #468faf;
        border-color: #468faf;
    }

    .btn-primary:hover {
        background-color: #1d4e89;
        border-color: #1d4e89;
    }

    @media (max-width: 767.98px) {
        .stats-card {
            flex-direction: column;
            text-align: center;
        }

        .stats-card .stat {
            text-align: center;
            margin-top: 10px;
        }

        .main-content {
            padding: 15px;
        }
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
                        <a class="nav-link active" href="dashboard.php">
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
                    <!-- Stats Cards -->
                    <?php if ($role == 'mentor'): ?>
                        <div class="col-md-4">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-users"></i>
                                    <h3><?php echo $profile['total_sessions']; ?></h3>
                                    <p>Total Sessions</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-star"></i>
                                    <h3><?php echo number_format($profile['average_rating'], 1); ?></h3>
                                    <p>Average Rating</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-comments"></i>
                                    <h3><?php echo $profile['total_reviews']; ?></h3>
                                    <p>Total Reviews</p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="col-md-4">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar-check"></i>
                                    <h3><?php echo $profile['total_sessions']; ?></h3>
                                    <p>Total Sessions</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-bullseye"></i>
                                    <h3><?php echo $profile['total_goals']; ?></h3>
                                    <p>Active Goals</p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="row mt-4">
                    <!-- Upcoming Sessions -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Upcoming Sessions</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($upcoming_sessions)): ?>
                                    <p class="text-muted">No upcoming sessions</p>
                                <?php else: ?>
                                    <?php foreach ($upcoming_sessions as $session): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div>
                                                <h6 class="mb-0">
                                                    <?php echo htmlspecialchars($session['first_name'] . ' ' . $session['last_name']); ?>
                                                </h6>
                                                <small class="text-muted">
                                                    <?php echo date('M d, Y h:i A', strtotime($session['start_time'])); ?>
                                                </small>
                                            </div>
                                            <a href="session-details.php?id=<?php echo $session['session_id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                View
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Messages -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Recent Messages</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_messages)): ?>
                                    <p class="text-muted">No recent messages</p>
                                <?php else: ?>
                                    <?php foreach ($recent_messages as $message): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div>
                                                <h6 class="mb-0">
                                                    <?php echo htmlspecialchars($message['first_name'] . ' ' . $message['last_name']); ?>
                                                </h6>
                                                <small class="text-muted">
                                                    <?php echo substr($message['message'], 0, 50) . '...'; ?>
                                                </small>
                                            </div>
                                            <a href="messages.php?user=<?php echo $message['sender_id'] == $user_id ? $message['receiver_id'] : $message['sender_id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                Reply
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 