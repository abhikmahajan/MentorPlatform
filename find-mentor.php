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
    
    // Get mentee profile data
    $stmt = $conn->prepare("SELECT * FROM mentee_profiles WHERE mentee_id = ?");
    $stmt->execute([$user_id]);
    $mentee_profile = $stmt->fetch();
    
    // Get search parameters
    $search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
    $category = isset($_GET['category']) ? sanitize_input($_GET['category']) : '';
    $min_rate = isset($_GET['min_rate']) ? (float)$_GET['min_rate'] : 0;
    $max_rate = isset($_GET['max_rate']) ? (float)$_GET['max_rate'] : 1000;
    $availability = isset($_GET['availability']) ? sanitize_input($_GET['availability']) : '';
    $sort = isset($_GET['sort']) ? sanitize_input($_GET['sort']) : 'rating';
    
    // Build query for mentor search
    $query = "
        SELECT u.*, 
               mp.*,
               AVG(r.rating) as average_rating,
               COUNT(DISTINCT r.review_id) as total_reviews,
               COUNT(DISTINCT s.session_id) as total_sessions
        FROM users u
        JOIN mentor_profiles mp ON u.user_id = mp.mentor_id
        LEFT JOIN reviews r ON u.user_id = r.mentor_id
        LEFT JOIN sessions s ON u.user_id = s.mentor_id
        WHERE u.role = 'mentor'
    ";
    
    $params = [];
    
    if ($search) {
        $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.skills LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
    }
    
    if ($category) {
        $query .= " AND u.skills LIKE ?";
        $params[] = "%$category%";
    }
    
    if ($min_rate > 0) {
        $query .= " AND mp.hourly_rate >= ?";
        $params[] = $min_rate;
    }
    
    if ($max_rate < 1000) {
        $query .= " AND mp.hourly_rate <= ?";
        $params[] = $max_rate;
    }
    
    if ($availability) {
        $query .= " AND mp.availability LIKE ?";
        $params[] = "%$availability%";
    }
    
    $query .= " GROUP BY u.user_id";
    
    // Add sorting
    switch ($sort) {
        case 'rating':
            $query .= " ORDER BY average_rating DESC";
            break;
        case 'price_low':
            $query .= " ORDER BY mp.hourly_rate ASC";
            break;
        case 'price_high':
            $query .= " ORDER BY mp.hourly_rate DESC";
            break;
        case 'sessions':
            $query .= " ORDER BY total_sessions DESC";
            break;
        default:
            $query .= " ORDER BY average_rating DESC";
    }
    
    // Get mentors
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $mentors = $stmt->fetchAll();
    
    // Get unique categories for filter
    $stmt = $conn->prepare("
        SELECT DISTINCT skills as category
        FROM users
        WHERE role = 'mentor' AND skills IS NOT NULL
        ORDER BY skills
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Mentor - Mentor Platform</title>
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
        
        .card:hover {
            transform: translateY(-5px);
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
        
        .mentor-picture {
            width: 80px;
            height: 80px;
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
        
        .rating {
            color: #ffc107;
        }
        
        .skill-tag {
            display: inline-block;
            padding: 5px 10px;
            margin: 5px;
            background-color: #e3f2fd;
            color: #1976d2;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        
        .filter-section {
            background-color: #1f1f3d;
            color: #D9F63F;
            padding: 20px;
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
                        <a class="nav-link active" href="find-mentor.php">
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
                
                <h4 class="mb-4">Find Your Perfect Mentor</h4>
                
                <!-- Search Filters -->
                <div class="filter-section">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" 
                                   class="form-control" 
                                   name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   placeholder="Search by name, skills, or expertise">
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>" 
                                            <?php echo $category == $cat ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Hourly Rate Range</label>
                            <div class="input-group">
                                <input type="number" 
                                       class="form-control" 
                                       name="min_rate" 
                                       value="<?php echo $min_rate; ?>"
                                       placeholder="Min">
                                <span class="input-group-text">-</span>
                                <input type="number" 
                                       class="form-control" 
                                       name="max_rate" 
                                       value="<?php echo $max_rate; ?>"
                                       placeholder="Max">
                            </div>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Sort By</label>
                            <select class="form-select" name="sort">
                                <option value="rating" <?php echo $sort == 'rating' ? 'selected' : ''; ?>>Highest Rated</option>
                                <option value="price_low" <?php echo $sort == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="price_high" <?php echo $sort == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                <option value="sessions" <?php echo $sort == 'sessions' ? 'selected' : ''; ?>>Most Sessions</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Mentors List -->
                <div class="row">
                    <?php foreach ($mentors as $mentor): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <img src="<?php echo $mentor['profile_picture'] ? UPLOAD_PATH . $mentor['profile_picture'] : 'assets/images/default-avatar.png'; ?>" 
                                             alt="Mentor Picture" 
                                             class="mentor-picture me-3">
                                        <div>
                                            <h5 class="card-title mb-1">
                                                <?php echo htmlspecialchars($mentor['first_name'] . ' ' . $mentor['last_name']); ?>
                                            </h5>
                                            <div class="mentor-stats">
                                                <div class="d-flex justify-content-between mb-2">
                                                    <div>
                                                        <i class="fas fa-star text-warning"></i>
                                                        <?php echo number_format($mentor['average_rating'], 1); ?> 
                                                        (<?php echo $mentor['total_reviews']; ?> reviews)
                                                    </div>
                                                    <div>
                                                        <i class="fas fa-calendar-check text-primary"></i>
                                                        <?php echo $mentor['total_sessions']; ?> sessions
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-primary fw-bold">
                                                $<?php echo number_format($mentor['hourly_rate'], 2); ?>/hour
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <p class="card-text"><?php echo nl2br(htmlspecialchars($mentor['bio'])); ?></p>
                                    
                                    <div class="mb-3">
                                        <strong>Expertise:</strong>
                                        <?php
                                        $expertise = explode(',', $mentor['skills']);
                                        foreach ($expertise as $skill) {
                                            echo '<span class="skill-tag">' . htmlspecialchars(trim($skill)) . '</span>';
                                        }
                                        ?>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar-check"></i> <?php echo $mentor['total_sessions']; ?> completed sessions
                                            </small>
                                        </div>
                                        <a href="schedule-session.php?mentor_id=<?php echo $mentor['user_id']; ?>" 
                                           class="btn btn-primary">
                                            <i class="fas fa-calendar-plus"></i> Schedule Session
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($mentors)): ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                No mentors found matching your criteria. Try adjusting your search filters.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 