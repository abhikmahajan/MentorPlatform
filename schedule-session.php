<?php
require_once 'config.php';

// Check if user is logged in and is a mentee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'mentee') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get user data
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    // Get available mentors
    $stmt = $conn->prepare("
        SELECT u.*, mp.hourly_rate, mp.availability
        FROM users u
        JOIN mentor_profiles mp ON u.user_id = mp.mentor_id
        WHERE u.user_id IN (
            SELECT DISTINCT mentor_id 
            FROM mentor_profiles 
            WHERE mentor_id NOT IN (
                SELECT mentor_id 
                FROM sessions 
                WHERE status = 'scheduled' 
                AND start_time >= NOW()
            )
        )
    ");
    $stmt->execute();
    $mentors = $stmt->fetchAll();
    
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle session scheduling
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $mentor_id = (int)$_POST['mentor_id'];
        $start_time = sanitize_input($_POST['start_time']);
        $duration = (int)$_POST['duration'];
        $notes = sanitize_input($_POST['notes']);
        
        // Validate input
        if (empty($mentor_id) || empty($start_time) || empty($duration)) {
            $error = "Please fill in all required fields";
        } else {
            // Check if mentor is available
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM sessions 
                WHERE mentor_id = ? 
                AND status = 'scheduled' 
                AND start_time <= DATE_ADD(?, INTERVAL ? HOUR)
                AND DATE_ADD(start_time, INTERVAL 1 HOUR) >= ?
            ");
            $stmt->execute([$mentor_id, $start_time, $duration, $start_time]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                $error = "Mentor is not available at this time";
            } else {
                // Calculate end time
                $end_time = date('Y-m-d H:i:s', strtotime($start_time . ' + ' . $duration . ' hours'));
                
                // Insert session
                $stmt = $conn->prepare("
                    INSERT INTO sessions (mentor_id, mentee_id, start_time, end_time, notes)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$mentor_id, $user_id, $start_time, $end_time, $notes]);
                
                $success = "Session scheduled successfully!";
                
                // Redirect to sessions page after 2 seconds
                header("refresh:2;url=sessions.php");
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
    <title>Schedule Session - Mentor Platform</title>
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
        
        .mentor-card {
            transition: transform 0.3s ease;
            cursor: pointer;
        }
        
        .mentor-card:hover {
            transform: translateY(-5px);
        }
        
        .mentor-card.selected {
            border: 2px solid var(--primary-color);
        }
        
        .mentor-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border: none;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
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
                    <p class="text-muted">Mentee</p>
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
                    <div class="card-header">
                        <h5 class="mb-0">Schedule New Session</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="scheduleForm">
                            <!-- Mentor Selection -->
                            <div class="mb-4">
                                <label class="form-label">Select Mentor</label>
                                <div class="row">
                                    <?php foreach ($mentors as $mentor): ?>
                                        <div class="col-md-4 mb-3">
                                            <div class="card mentor-card" onclick="selectMentor(<?php echo $mentor['user_id']; ?>)">
                                                <div class="card-body text-center">
                                                    <img src="<?php echo $mentor['profile_picture'] ? UPLOAD_URL . $mentor['profile_picture'] : 'assets/images/default-avatar.png'; ?>" 
                                                         alt="Profile Picture" 
                                                         class="mentor-avatar mb-3">
                                                    <h6 class="mb-1">
                                                        <?php echo htmlspecialchars($mentor['first_name'] . ' ' . $mentor['last_name']); ?>
                                                    </h6>
                                                    <p class="text-muted mb-1">$<?php echo number_format($mentor['hourly_rate'], 2); ?>/hour</p>
                                                    <small class="text-muted"><?php echo htmlspecialchars($mentor['availability']); ?></small>
                                                    <input type="radio" 
                                                           name="mentor_id" 
                                                           value="<?php echo $mentor['user_id']; ?>" 
                                                           class="d-none" 
                                                           required>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Session Details -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="start_time" class="form-label">Start Time</label>
                                    <input type="datetime-local" 
                                           class="form-control" 
                                           id="start_time" 
                                           name="start_time" 
                                           required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="duration" class="form-label">Duration (hours)</label>
                                    <select class="form-select" id="duration" name="duration" required>
                                        <option value="">Select duration</option>
                                        <option value="1">1 hour</option>
                                        <option value="2">2 hours</option>
                                        <option value="3">3 hours</option>
                                        <option value="4">4 hours</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Session Notes (Optional)</label>
                                <textarea class="form-control" 
                                          id="notes" 
                                          name="notes" 
                                          rows="3" 
                                          placeholder="What would you like to discuss in this session?"></textarea>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="sessions.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Sessions
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-calendar-check"></i> Schedule Session
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectMentor(mentorId) {
            // Remove selected class from all cards
            document.querySelectorAll('.mentor-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked card
            event.currentTarget.classList.add('selected');
            
            // Check the radio button
            document.querySelector(`input[value="${mentorId}"]`).checked = true;
        }
        
        // Form validation
        document.getElementById('scheduleForm').addEventListener('submit', function(e) {
            const mentorSelected = document.querySelector('input[name="mentor_id"]:checked');
            if (!mentorSelected) {
                e.preventDefault();
                alert('Please select a mentor');
            }
        });
    </script>
</body>
</html> 