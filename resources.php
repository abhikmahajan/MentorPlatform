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
    
    // Get all resources for the mentor
    $stmt = $conn->prepare("
        SELECT r.*
        FROM resources r
        WHERE r.mentor_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $resources = $stmt->fetchAll();
    error_log("Resources query result: " . print_r($resources, true));
    
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle resource upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'upload') {
    try {
        $title = sanitize_input($_POST['title']);
        $description = sanitize_input($_POST['description']);
        $category = sanitize_input($_POST['category']);
        
        // Validate input
        if (empty($title) || empty($description) || empty($category)) {
            $error = "Please fill in all required fields";
        } else {
            // Handle file upload
            if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
                $file = $_FILES['file'];
                $file_name = time() . '_' . basename($file['name']);
                $file_path = UPLOAD_PATH . 'resources/' . $file_name;
                
                // Create directory if it doesn't exist
                if (!file_exists(UPLOAD_PATH . 'resources/')) {
                    mkdir(UPLOAD_PATH . 'resources/', 0777, true);
                }
                
                // Get file type
                $type = pathinfo($file['name'], PATHINFO_EXTENSION);
                
                // Validate file type
                $allowed_types = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'mp4', 'mp3'];
                if (!in_array(strtolower($type), $allowed_types)) {
                    $error = "Invalid file type. Allowed types: " . implode(', ', $allowed_types);
                } else {
                    // Move uploaded file
                    if (move_uploaded_file($file['tmp_name'], $file_path)) {
                        error_log("File uploaded successfully to: " . $file_path);
                        
                        // Insert resource into database
                        $stmt = $conn->prepare("
                            INSERT INTO resources (mentor_id, title, description, category, type, file_name, file_path)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        
                        $relative_path = 'uploads/resources/' . $file_name;
                        $values = [
                            $user_id,
                            $title,
                            $description,
                            $category,
                            $type,
                            $file_name,
                            $relative_path
                        ];
                        error_log("Executing database insert with values: " . print_r($values, true));
                        
                        $stmt->execute($values);
                        error_log("Database insert successful. Last insert ID: " . $conn->lastInsertId());
                        
                        $success = "Resource uploaded successfully!";
                        
                        // Refresh resources list
                        $stmt = $conn->prepare("
                            SELECT r.*
                            FROM resources r
                            WHERE r.mentor_id = ?
                            ORDER BY r.created_at DESC
                        ");
                        $stmt->execute([$user_id]);
                        $resources = $stmt->fetchAll();
                        error_log("Resources refreshed. Count: " . count($resources));
                        
                    } else {
                        $error = "Failed to upload file";
                        error_log("Failed to move uploaded file to: " . $file_path);
                    }
                }
            } else {
                $error = "Please select a file to upload";
                error_log("No file uploaded or upload error: " . print_r($_FILES, true));
            }
        }
    } catch(Exception $e) {
        $error = "An unexpected error occurred: " . $e->getMessage();
        error_log("Unexpected error in resource upload: " . $e->getMessage());
    }
}

// Handle resource deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] == 'delete') {
            $resource_id = (int)$_POST['resource_id'];
            
            // Get file path
            $stmt = $conn->prepare("SELECT file_path FROM resources WHERE resource_id = ? AND mentor_id = ?");
            $stmt->execute([$resource_id, $user_id]);
            $resource = $stmt->fetch();
            
            if ($resource) {
                // Delete file
                if (file_exists($resource['file_path'])) {
                    unlink($resource['file_path']);
                }
                
                // Delete from database
                $stmt = $conn->prepare("DELETE FROM resources WHERE resource_id = ? AND mentor_id = ?");
                $stmt->execute([$resource_id, $user_id]);
                
                $success = "Resource deleted successfully!";
                
                // Refresh resources list
                $stmt = $conn->prepare("
                    SELECT r.*
                    FROM resources r
                    WHERE r.mentor_id = ?
                    ORDER BY r.created_at DESC
                ");
                $stmt->execute([$user_id]);
                $resources = $stmt->fetchAll();
            }
        }
    } catch(Exception $e) {
        $error = "An unexpected error occurred: " . $e->getMessage();
        error_log("Unexpected error in resource deletion: " . $e->getMessage());
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
        
        .resource-card {
            transition: transform 0.3s ease;
        }
        
        .resource-card:hover {
            transform: translateY(-5px);
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
        
        .file-icon {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 10px;
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
                        <a class="nav-link active" href="resources.php">
                            <i class="fas fa-book"></i> Resources
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="earnings.php">
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
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4>My Resources</h4>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadResourceModal">
                        <i class="fas fa-upload"></i> Upload Resource
                    </button>
                </div>
                
                <div class="row">
                    <?php foreach ($resources as $resource): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card resource-card">
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
                                    <span class="category-badge"><?php echo htmlspecialchars($resource['category']); ?></span>
                                    
                                    <p class="card-text mt-3"><?php echo nl2br(htmlspecialchars($resource['description'])); ?></p>
                                    
                                    <div class="mt-3">
                                        <a href="<?php echo UPLOAD_URL . 'resources/' . $resource['file_name']; ?>" 
                                           class="btn btn-sm btn-outline-primary" 
                                           target="_blank">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteResourceModal<?php echo $resource['resource_id']; ?>">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Delete Resource Modal -->
                        <div class="modal fade" id="deleteResourceModal<?php echo $resource['resource_id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Delete Resource</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>Are you sure you want to delete this resource? This action cannot be undone.</p>
                                    </div>
                                    <div class="modal-footer">
                                        <form method="POST" action="">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="resource_id" value="<?php echo $resource['resource_id']; ?>">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-danger">Delete</button>
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
    
    <!-- Upload Resource Modal -->
    <div class="modal fade" id="uploadResourceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Resource</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="upload">
                        
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" 
                                   class="form-control" 
                                   name="title" 
                                   required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" 
                                      name="description" 
                                      rows="3" 
                                      required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category" required>
                                <option value="">Select a category</option>
                                <option value="Career Development">Career Development</option>
                                <option value="Education">Education</option>
                                <option value="Personal Growth">Personal Growth</option>
                                <option value="Professional Skills">Professional Skills</option>
                                <option value="Technical Skills">Technical Skills</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Resource Type</label>
                            <select class="form-select" name="type" required>
                                <option value="">Select a type</option>
                                <option value="document">Document</option>
                                <option value="video">Video</option>
                                <option value="audio">Audio</option>
                                <option value="image">Image</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">File</label>
                            <input type="file" 
                                   class="form-control" 
                                   name="file" 
                                   required>
                            <small class="text-muted">
                                Maximum file size: <?php echo MAX_FILE_SIZE / 1024 / 1024; ?>MB<br>
                                Allowed types: <?php echo implode(', ', ALLOWED_FILE_TYPES); ?>
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 