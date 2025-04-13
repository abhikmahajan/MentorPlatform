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
    
    // Get time period filter
    $period = isset($_GET['period']) ? sanitize_input($_GET['period']) : '30';
    
    // Get overall statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT s.session_id) as total_sessions,
            COUNT(DISTINCT CASE WHEN s.status = 'completed' THEN s.session_id END) as completed_sessions,
            COUNT(DISTINCT CASE WHEN s.status = 'cancelled' THEN s.session_id END) as cancelled_sessions,
            COUNT(DISTINCT s.mentee_id) as unique_mentees,
            AVG(r.rating) as average_rating,
            COUNT(DISTINCT r.review_id) as total_reviews,
            SUM(CASE WHEN s.status = 'completed' THEN s.hourly_rate * TIMESTAMPDIFF(HOUR, s.start_time, s.end_time) ELSE 0 END) as total_earnings,
            AVG(CASE WHEN s.status = 'completed' THEN s.hourly_rate * TIMESTAMPDIFF(HOUR, s.start_time, s.end_time) ELSE 0 END) as average_session_earnings
        FROM sessions s
        LEFT JOIN reviews r ON s.session_id = r.session_id
        WHERE s.mentor_id = ?
        AND s.start_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->execute([$user_id, $period]);
    $stats = $stmt->fetch();
    
    // Get monthly earnings for the past 12 months
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(start_time, '%Y-%m') as month,
            COUNT(DISTINCT session_id) as total_sessions,
            SUM(CASE WHEN status = 'completed' THEN hourly_rate * TIMESTAMPDIFF(HOUR, start_time, end_time) ELSE 0 END) as earnings
        FROM sessions
        WHERE mentor_id = ?
        AND start_time >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(start_time, '%Y-%m')
        ORDER BY month DESC
    ");
    $stmt->execute([$user_id]);
    $monthly_stats = $stmt->fetchAll();
    
    // Get recent reviews with mentee details
    $stmt = $conn->prepare("
        SELECT r.*, 
               u.first_name as mentee_first_name,
               u.last_name as mentee_last_name,
               u.profile_picture as mentee_picture,
               s.start_time as session_date
        FROM reviews r
        JOIN sessions s ON r.session_id = s.session_id
        JOIN users u ON s.mentee_id = u.user_id
        WHERE s.mentor_id = ?
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_reviews = $stmt->fetchAll();
    
    // Get session completion rate by day of week
    $stmt = $conn->prepare("
        SELECT 
            DAYNAME(start_time) as day_of_week,
            COUNT(*) as total_sessions,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_sessions
        FROM sessions
        WHERE mentor_id = ?
        AND start_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DAYNAME(start_time)
        ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')
    ");
    $stmt->execute([$user_id, $period]);
    $daily_stats = $stmt->fetchAll();
    
    // Get expertise distribution
    $stmt = $conn->prepare("
        SELECT 
            expertise,
            COUNT(DISTINCT s.session_id) as session_count
        FROM sessions s
        JOIN mentor_profiles mp ON s.mentor_id = mp.mentor_id
        WHERE s.mentor_id = ?
        AND s.start_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY expertise
    ");
    $stmt->execute([$user_id, $period]);
    $expertise_stats = $stmt->fetchAll();
    
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - Mentor Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .mentee-picture {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
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
        
        .rating {
            color: #ffc107;
        }
        
        .period-selector {
            background-color: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="text-center mb-4">
                    <img src="<?php echo $user['profile_picture'] ? UPLOAD_PATH . $user['profile_picture'] : 'assets/images/default-avatar.png'; ?>" 
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
                        <a class="nav-link" href="earnings.php">
                            <i class="fas fa-dollar-sign"></i> Earnings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="analytics.php">
                            <i class="fas fa-chart-bar"></i> Analytics
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
                
                <h4 class="mb-4">Analytics Dashboard</h4>
                
                <!-- Period Selector -->
                <div class="period-selector">
                    <form method="GET" action="" class="row align-items-center">
                        <div class="col-md-4">
                            <label class="form-label">Time Period</label>
                            <select class="form-select" name="period" onchange="this.form.submit()">
                                <option value="7" <?php echo $period == '7' ? 'selected' : ''; ?>>Last 7 days</option>
                                <option value="30" <?php echo $period == '30' ? 'selected' : ''; ?>>Last 30 days</option>
                                <option value="90" <?php echo $period == '90' ? 'selected' : ''; ?>>Last 90 days</option>
                                <option value="365" <?php echo $period == '365' ? 'selected' : ''; ?>>Last year</option>
                            </select>
                        </div>
                    </form>
                </div>
                
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="stat-value"><?php echo $stats['total_sessions']; ?></div>
                                <div class="stat-label">Total Sessions</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="stat-value">$<?php echo number_format($stats['total_earnings'], 2); ?></div>
                                <div class="stat-label">Total Earnings</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="stat-value"><?php echo $stats['unique_mentees']; ?></div>
                                <div class="stat-label">Unique Mentees</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="stat-value">
                                    <?php echo round(($stats['completed_sessions'] / $stats['total_sessions']) * 100); ?>%
                                </div>
                                <div class="stat-label">Completion Rate</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Monthly Earnings</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="earningsChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Session Completion by Day</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="dailyStatsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Reviews and Expertise -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Recent Reviews</h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($recent_reviews as $review): ?>
                                    <div class="mb-3 pb-3 border-bottom">
                                        <div class="d-flex align-items-center mb-2">
                                            <img src="<?php echo $review['mentee_picture'] ? UPLOAD_PATH . $review['mentee_picture'] : 'assets/images/default-avatar.png'; ?>" 
                                                 alt="Mentee Picture" 
                                                 class="mentee-picture me-2">
                                            <div>
                                                <h6 class="mb-0">
                                                    <?php echo htmlspecialchars($review['mentee_first_name'] . ' ' . $review['mentee_last_name']); ?>
                                                </h6>
                                                <small class="text-muted">
                                                    <?php echo date('M d, Y', strtotime($review['session_date'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                        <div class="rating mb-2">
                                            <?php
                                            for ($i = 1; $i <= 5; $i++) {
                                                echo $i <= $review['rating'] ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                            }
                                            ?>
                                        </div>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Expertise Distribution</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="expertiseChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Monthly Earnings Chart
        const earningsCtx = document.getElementById('earningsChart').getContext('2d');
        const monthlyData = <?php echo json_encode(array_reverse($monthly_stats)); ?>;
        
        new Chart(earningsCtx, {
            type: 'line',
            data: {
                labels: monthlyData.map(item => {
                    const date = new Date(item.month + '-01');
                    return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                }),
                datasets: [{
                    label: 'Earnings',
                    data: monthlyData.map(item => item.earnings),
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
        
        // Daily Stats Chart
        const dailyCtx = document.getElementById('dailyStatsChart').getContext('2d');
        const dailyData = <?php echo json_encode($daily_stats); ?>;
        
        new Chart(dailyCtx, {
            type: 'doughnut',
            data: {
                labels: dailyData.map(item => item.day_of_week),
                datasets: [{
                    data: dailyData.map(item => (item.completed_sessions / item.total_sessions) * 100),
                    backgroundColor: [
                        '#4a90e2',
                        '#2c3e50',
                        '#e74c3c',
                        '#2ecc71',
                        '#f1c40f',
                        '#9b59b6',
                        '#1abc9c'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Expertise Chart
        const expertiseCtx = document.getElementById('expertiseChart').getContext('2d');
        const expertiseData = <?php echo json_encode($expertise_stats); ?>;
        
        new Chart(expertiseCtx, {
            type: 'bar',
            data: {
                labels: expertiseData.map(item => item.expertise),
                datasets: [{
                    label: 'Sessions',
                    data: expertiseData.map(item => item.session_count),
                    backgroundColor: '#4a90e2'
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
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html> 