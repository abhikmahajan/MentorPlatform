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

try {
    // Get user data
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    // Get all available resources from mentors
    $stmt = $conn->prepare("
        SELECT r.*, 
               u.first_name, u.last_name,
               CASE WHEN gr.resource_id IS NOT NULL THEN 1 ELSE 0 END as is_accessed
        FROM resources r
        JOIN users u ON r.mentor_id = u.user_id
        LEFT JOIN goal_resources gr ON r.resource_id = gr.resource_id AND gr.mentee_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $resources = $stmt->fetchAll();
    
    // Debug log
    error_log("Resources fetched: " . print_r($resources, true));
    
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle resource access tracking
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'access') {
    try {
        $resource_id = (int)$_POST['resource_id'];
        
        // Record resource access
        $stmt = $conn->prepare("
            INSERT INTO goal_resources (resource_id, mentee_id, viewed_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE viewed_at = NOW()
        ");
        $stmt->execute([$resource_id, $user_id]);
        
        // Get the resource URL and redirect
        $stmt = $conn->prepare("SELECT file_name FROM resources WHERE resource_id = ?");
        $stmt->execute([$resource_id]);
        $resource = $stmt->fetch();
        
        if ($resource) {
            header("Location: " . UPLOAD_URL . "resources/" . $resource['file_name']);
            exit();
        }
    } catch(PDOException $e) {
        $error = "Error accessing resource: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resources - Mentor Platform</title>
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
        
        .resource-card {
            transition: transform 0.3s ease;
        }
        
        .resource-card:hover {
            transform: translateY(-5px);
        }
        
        .category-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .file-icon {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .mentor-info {
            font-size: 0.9rem;
            color: #D9F63F;
        }
        
        .accessed-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            background-color: #e8f5e9;
            color: #2e7d32;
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
                        <a class="nav-link active" href="mentee-resources.php">
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
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4>Learning Resources</h4>
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-filter"></i> Filter by Category
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="filterResources('all')">All Categories</a></li>
                            <li><a class="dropdown-item" href="#" onclick="filterResources('Career Development')">Career Development</a></li>
                            <li><a class="dropdown-item" href="#" onclick="filterResources('Education')">Education</a></li>
                            <li><a class="dropdown-item" href="#" onclick="filterResources('Personal Growth')">Personal Growth</a></li>
                            <li><a class="dropdown-item" href="#" onclick="filterResources('Professional Skills')">Professional Skills</a></li>
                            <li><a class="dropdown-item" href="#" onclick="filterResources('Technical Skills')">Technical Skills</a></li>
                        </ul>
                    </div>
                </div>
                
                <div class="row" id="resourcesContainer">
                    <?php foreach ($resources as $resource): ?>
                        <div class="col-md-6 col-lg-4 mb-4 resource-item" data-category="<?php echo htmlspecialchars($resource['category']); ?>">
                            <div class="card resource-card">
                                <?php if ($resource['is_accessed']): ?>
                                    <div class="accessed-badge">
                                        <i class="fas fa-check"></i> Accessed
                                    </div>
                                <?php endif; ?>
                                <div class="card-body text-center">
                                    <?php
                                    $icon_class = 'fas fa-file';
                                    switch ($resource['type']) {
                                        case 'document':
                                            $icon_class = 'fas fa-file-alt';
                                            break;
                                        case 'video':
                                            $icon_class = 'fas fa-video';
                                            break;
                                        case 'audio':
                                            $icon_class = 'fas fa-headphones';
                                            break;
                                        case 'image':
                                            $icon_class = 'fas fa-image';
                                            break;
                                    }
                                    ?>
                                    <div class="file-icon">
                                        <i class="<?php echo $icon_class; ?>"></i>
                                    </div>
                                    
                                    <h5 class="card-title"><?php echo htmlspecialchars($resource['title']); ?></h5>
                                    <span class="category-badge mb-2"><?php echo htmlspecialchars($resource['category']); ?></span>
                                    
                                    <p class="mentor-info">
                                        <i class="fas fa-user"></i> 
                                        <?php echo htmlspecialchars($resource['first_name'] . ' ' . $resource['last_name']); ?>
                                    </p>
                                    
                                    <p class="card-text mt-2"><?php echo nl2br(htmlspecialchars($resource['description'])); ?></p>
                                    
                                    <div class="mt-3">
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="action" value="access">
                                            <input type="hidden" name="resource_id" value="<?php echo $resource['resource_id']; ?>">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-download"></i> Access Resource
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function filterResources(category) {
            const resources = document.querySelectorAll('.resource-item');
            resources.forEach(resource => {
                if (category === 'all' || resource.dataset.category === category) {
                    resource.style.display = 'block';
                } else {
                    resource.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html> 