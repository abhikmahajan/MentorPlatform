<?php
require_once 'config.php';

// Check if user is logged in and is a mentor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'mentor') {
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
    
    // Get mentor profile data
    $stmt = $conn->prepare("SELECT * FROM mentor_profiles WHERE mentor_id = ?");
    $stmt->execute([$user_id]);
    $mentor_profile = $stmt->fetch();
    
    // Get earnings summary
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_sessions,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_sessions,
            SUM(CASE WHEN status = 'completed' THEN hourly_rate * TIMESTAMPDIFF(HOUR, start_time, end_time) ELSE 0 END) as total_earnings,
            AVG(CASE WHEN status = 'completed' THEN hourly_rate * TIMESTAMPDIFF(HOUR, start_time, end_time) ELSE 0 END) as average_earnings_per_session
        FROM sessions
        WHERE mentor_id = ?
    ");
    $stmt->execute([$user_id]);
    $earnings_summary = $stmt->fetch();
    
    // Get recent sessions with earnings
    $stmt = $conn->prepare("
        SELECT s.*, 
               u.first_name as mentee_first_name,
               u.last_name as mentee_last_name,
               u.profile_picture as mentee_picture,
               (s.hourly_rate * TIMESTAMPDIFF(HOUR, s.start_time, s.end_time)) as session_earnings
        FROM sessions s
        JOIN users u ON s.mentee_id = u.user_id
        WHERE s.mentor_id = ?
        ORDER BY s.start_time DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $recent_sessions = $stmt->fetchAll();
    
    // Get monthly earnings for the past 6 months
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(start_time, '%Y-%m') as month,
            SUM(CASE WHEN status = 'completed' THEN hourly_rate * TIMESTAMPDIFF(HOUR, start_time, end_time) ELSE 0 END) as monthly_earnings
        FROM sessions
        WHERE mentor_id = ?
        AND start_time >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(start_time, '%Y-%m')
        ORDER BY month DESC
    ");
    $stmt->execute([$user_id]);
    $monthly_earnings = $stmt->fetchAll();
    
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Earnings - Mentor Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .stats-card {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }
        
        .stats-card .card-body {
            padding: 20px;
        }
        
        .stats-card .stat-value {
            font-size: 2rem;
            font-weight: bold;
        }
        
        .stats-card .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border: none;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
        }
        
        .session-card {
            transition: transform 0.3s ease;
        }
        
        .session-card:hover {
            transform: translateY(-5px);
        }
        
        .mentee-picture {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .table{
            background-color: #1f1f3d;
            color: #ffffff;
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
                    <p class="text-muted">Mentor</p>
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
                        <a class="nav-link" href="resources.php">
                            <i class="fas fa-book"></i> Resources
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="earnings.php">
                            <i class="fas fa-dollar-sign"></i> Earnings
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
                
                <h4 class="mb-4">Earnings Overview</h4>
                
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="stat-value">$<?php echo number_format($earnings_summary['total_earnings'], 2); ?></div>
                                <div class="stat-label">Total Earnings</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="stat-value">$<?php echo number_format($earnings_summary['average_earnings_per_session'], 2); ?></div>
                                <div class="stat-label">Average per Session</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="stat-value"><?php echo $earnings_summary['completed_sessions']; ?></div>
                                <div class="stat-label">Completed Sessions</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="stat-value">$<?php echo number_format($mentor_profile['hourly_rate'], 2); ?></div>
                                <div class="stat-label">Hourly Rate</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Earnings Chart -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Monthly Earnings</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="earningsChart"></canvas>
                    </div>
                </div>
                
                <!-- Recent Sessions -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Sessions</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Mentee</th>
                                        <th>Date</th>
                                        <th>Duration</th>
                                        <th>Status</th>
                                        <th>Earnings</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_sessions as $session): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?php echo $session['mentee_picture'] ? UPLOAD_PATH . $session['mentee_picture'] : 'assets/images/default-avatar.png'; ?>" 
                                                         alt="Mentee Picture" 
                                                         class="mentee-picture me-2">
                                                    <div>
                                                        <?php echo htmlspecialchars($session['mentee_first_name'] . ' ' . $session['mentee_last_name']); ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($session['start_time'])); ?></td>
                                            <td><?php echo round((strtotime($session['end_time']) - strtotime($session['start_time'])) / 3600); ?> hours</td>
                                            <td>
                                                <span class="badge bg-<?php echo $session['status'] == 'completed' ? 'success' : ($session['status'] == 'scheduled' ? 'primary' : 'danger'); ?>">
                                                    <?php echo ucfirst($session['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($session['status'] == 'completed'): ?>
                                                    $<?php echo number_format($session['session_earnings'], 2); ?>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Earnings Chart
        const ctx = document.getElementById('earningsChart').getContext('2d');
        const monthlyData = <?php echo json_encode(array_reverse($monthly_earnings)); ?>;
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: monthlyData.map(item => {
                    const date = new Date(item.month + '-01');
                    return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                }),
                datasets: [{
                    label: 'Monthly Earnings',
                    data: monthlyData.map(item => item.monthly_earnings),
                    borderColor: '#4a90e2',
                    backgroundColor: 'rgba(74, 144, 226, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html> 