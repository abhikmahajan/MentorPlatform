<?php
require_once 'config.php';

// Check if user is logged in and is a mentee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'mentee') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$success = '';
$error = '';

try {
    // Get user data
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    // Get all goals for the mentee
    $stmt = $conn->prepare("
        SELECT g.goal_id, g.title, g.description, g.target_date, g.status, g.created_at
        FROM goals g
        WHERE g.mentee_id = ?
        ORDER BY g.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $goals = $stmt->fetchAll();
    
    // Debug goals data
    error_log("Goals data: " . print_r($goals, true));
    
    // Get goal progress for each goal
    foreach ($goals as &$goal) {
        $stmt = $conn->prepare("
            SELECT * FROM goal_progress 
            WHERE goal_id = ? 
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$goal['goal_id']]);
        $latest_progress = $stmt->fetch();
        
        // Set overall progress
        $goal['overall_progress'] = $latest_progress ? $latest_progress['progress_percentage'] : 0;
    }
    
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle goal creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] == 'create') {
            $title = sanitize_input($_POST['title']);
            $description = sanitize_input($_POST['description']);
            $target_date = sanitize_input($_POST['target_date']);
            
            if (empty($title) || empty($description) || empty($target_date)) {
                $error = "Please fill in all required fields";
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO goals (mentee_id, title, description, target_date)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$user_id, $title, $description, $target_date]);
                
                $success = "Goal created successfully!";
                $_SESSION['success_message'] = $success;
                
                // Redirect immediately after creating the goal
                header("Location: goals.php");
                exit();
            }
        }
        
        // Handle goal progress update
        elseif ($_POST['action'] == 'update_progress') {
            $goal_id = (int)$_POST['goal_id'];
            $progress_value = (int)$_POST['progress_value'];
            $notes = sanitize_input($_POST['notes']);
            
            if ($progress_value < 0 || $progress_value > 100) {
                $error = "Progress value must be between 0 and 100";
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO goal_progress (goal_id, progress_percentage, update_text)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$goal_id, $progress_value, $notes]);
                
                $success = "Progress updated successfully!";
                
                // Refresh goals list after updating progress
                $stmt = $conn->prepare("
                    SELECT g.goal_id, g.title, g.description, g.target_date, g.status, g.created_at
                    FROM goals g
                    WHERE g.mentee_id = ?
                    ORDER BY g.created_at DESC
                ");
                $stmt->execute([$user_id]);
                $goals = $stmt->fetchAll();
                
                // Refresh progress data for each goal
                foreach ($goals as &$goal) {
                    $stmt = $conn->prepare("
                        SELECT * FROM goal_progress 
                        WHERE goal_id = ? 
                        ORDER BY created_at DESC
                        LIMIT 1
                    ");
                    $stmt->execute([$goal['goal_id']]);
                    $latest_progress = $stmt->fetch();
                    
                    // Set overall progress
                    $goal['overall_progress'] = $latest_progress ? $latest_progress['progress_percentage'] : 0;
                }
            }
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
    <title>Goals - Mentor Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4a90e2;
            --secondary-color: #2c3e50;
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
        
        .goal-card {
            transition: transform 0.3s ease;
        }
        
        .goal-card:hover {
            transform: translateY(-5px);
        }
        
        .progress {
            height: 10px;
            border-radius: 5px;
        }
        
        .progress-bar {
            background-color: var(--primary-color);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border: none;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
        }
        
        .category-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            background-color: #e3f2fd;
            color: #1976d2;
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
                        <a class="nav-link" href="sessions.php">
                            <i class="fas fa-calendar"></i> Sessions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="goals.php">
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
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['success_message']; ?></div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4>My Goals</h4>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createGoalModal">
                        <i class="fas fa-plus"></i> Create New Goal
                    </button>
                </div>
                
                <div class="row">
                    <?php foreach ($goals as $goal): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card goal-card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3"><?php echo htmlspecialchars($goal['title']); ?></h5>
                                    
                                    <p class="card-text text-muted mb-3"><?php echo nl2br(htmlspecialchars($goal['description'])); ?></p>
                                    
                                    <div class="progress mb-3">
                                        <div class="progress-bar" 
                                             role="progressbar" 
                                             style="width: <?php echo round($goal['overall_progress']); ?>%"
                                             aria-valuenow="<?php echo round($goal['overall_progress']); ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <span class="text-muted">Progress: </span>
                                            <span class="fw-bold"><?php echo round($goal['overall_progress']); ?>%</span>
                                        </div>
                                        <div>
                                            <span class="text-muted">Target: </span>
                                            <span class="fw-bold"><?php echo date('M d, Y', strtotime($goal['target_date'])); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <button type="button" 
                                                class="btn btn-primary btn-sm" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#updateProgressModal<?php echo $goal['goal_id']; ?>">
                                            <i class="fas fa-chart-line"></i> Update Progress
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Update Progress Modal -->
                        <div class="modal fade" id="updateProgressModal<?php echo $goal['goal_id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Update Progress</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST" action="">
                                        <div class="modal-body">
                                            <input type="hidden" name="action" value="update_progress">
                                            <input type="hidden" name="goal_id" value="<?php echo $goal['goal_id']; ?>">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Progress Value (%)</label>
                                                <input type="number" 
                                                       class="form-control" 
                                                       name="progress_value" 
                                                       min="0" 
                                                       max="100" 
                                                       required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Notes</label>
                                                <textarea class="form-control" 
                                                          name="notes" 
                                                          rows="3" 
                                                          placeholder="Add any notes about your progress..."></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary">Update Progress</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Create Goal Modal -->
    <div class="modal fade" id="createGoalModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Goal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Target Date</label>
                            <input type="date" class="form-control" name="target_date" required>
                        </div>
                        
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Create Goal</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 