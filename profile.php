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
    
    // Get role-specific profile data
    if ($role == 'mentor') {
        $stmt = $conn->prepare("SELECT * FROM mentor_profiles WHERE mentor_id = ?");
    } else {
        $stmt = $conn->prepare("SELECT * FROM mentee_profiles WHERE mentee_id = ?");
    }
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch();
    
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Handle profile picture upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
            error_log("Profile picture upload attempt started");
            error_log("File data: " . print_r($_FILES['profile_picture'], true));
            
            $file = $_FILES['profile_picture'];
            $file_name = $file['name'];
            $file_tmp = $file['tmp_name'];
            $file_size = $file['size'];
            $file_error = $file['error'];
            
            // Get file extension
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            error_log("File extension: " . $file_ext);
            
            // Allowed file types
            $allowed = array('jpg', 'jpeg', 'png');
            
            if (in_array($file_ext, $allowed)) {
                if ($file_error === 0) {
                    if ($file_size <= MAX_FILE_SIZE) {
                        // Generate unique filename
                        $file_new_name = uniqid('profile_') . '.' . $file_ext;
                        $file_destination = UPLOAD_PATH . $file_new_name;
                        error_log("Attempting to move file to: " . $file_destination);
                        
                        if (move_uploaded_file($file_tmp, $file_destination)) {
                            error_log("File moved successfully");
                            // Update user profile picture in database
                            $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?");
                            $stmt->execute([$file_new_name, $user_id]);
                            error_log("Database updated with new profile picture: " . $file_new_name);
                            $success = "Profile picture updated successfully!";
                        } else {
                            error_log("Failed to move uploaded file");
                            $error = "Error uploading file";
                        }
                    } else {
                        error_log("File size too large: " . $file_size);
                        $error = "File size too large";
                    }
                } else {
                    error_log("File upload error: " . $file_error);
                    $error = "Error uploading file";
                }
            } else {
                error_log("Invalid file type: " . $file_ext);
                $error = "Invalid file type";
            }
        }
        
        // Update user information
        $first_name = sanitize_input($_POST['first_name']);
        $last_name = sanitize_input($_POST['last_name']);
        $bio = sanitize_input($_POST['bio']);
        $skills = sanitize_input($_POST['skills']);
        $interests = sanitize_input($_POST['interests']);
        $location = sanitize_input($_POST['location']);
        
        $stmt = $conn->prepare("
            UPDATE users 
            SET first_name = ?, last_name = ?, bio = ?, skills = ?, interests = ?, location = ?
            WHERE user_id = ?
        ");
        $stmt->execute([$first_name, $last_name, $bio, $skills, $interests, $location, $user_id]);
        
        // Update role-specific information
        if ($role == 'mentor') {
            $hourly_rate = sanitize_input($_POST['hourly_rate']);
            $availability = sanitize_input($_POST['availability']);
            
            $stmt = $conn->prepare("
                UPDATE mentor_profiles 
                SET hourly_rate = ?, availability = ?
                WHERE mentor_id = ?
            ");
            $stmt->execute([$hourly_rate, $availability, $user_id]);
        } else {
            $goals = sanitize_input($_POST['goals']);
            $preferred_topics = sanitize_input($_POST['preferred_topics']);
            
            $stmt = $conn->prepare("
                UPDATE mentee_profiles 
                SET goals = ?, preferred_mentoring_topics = ?
                WHERE mentee_id = ?
            ");
            $stmt->execute([$goals, $preferred_topics, $user_id]);
        }
        
        $success = "Profile updated successfully!";
        
        // Refresh user data
        $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($role == 'mentor') {
            $stmt = $conn->prepare("SELECT * FROM mentor_profiles WHERE mentor_id = ?");
        } else {
            $stmt = $conn->prepare("SELECT * FROM mentee_profiles WHERE mentee_id = ?");
        }
        $stmt->execute([$user_id]);
        $profile = $stmt->fetch();
        
    } catch(PDOException $e) {
        $error = "An error occurred. Please try again later.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Mentor Platform</title>
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
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        background-color: #1f1f3d;
            color:white;
        }
        
        .card-header {
            margin-bottom: 15px;
        font-size: 1.75rem;
        background-color: #1f1f3d;
            color: #D9F63F;
        }
        
        .profile-picture {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
        }
        
        .profile-picture-large {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 20px;
        }
        
        .profile-picture-container {
            position: relative;
            display: inline-block;
        }
        
        .profile-picture-upload {
            position: absolute;
            bottom: 0;
            right: 0;
            background: var(--primary-color);
            color: white;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .profile-picture-upload:hover {
            background: var(--secondary-color);
        }
        
        .form-control {
            border-radius: 5px;
            padding: 10px;
        }
        
        .btn-primary {
            background-color: #0d1b2a;;
            border: none;
            padding: 10px 20px;
        }
        
        .btn-primary:hover {
            background-color: #1b263b;        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="text-center mb-4">
                    <?php
                    error_log("User data: " . print_r($user, true));
                    error_log("Profile picture path: " . ($user['profile_picture'] ? UPLOAD_URL . $user['profile_picture'] : 'assets/images/default-avatar.png'));
                    ?>
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
                        <a class="nav-link active" href="profile.php">
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
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Edit Profile</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="text-center mb-4">
                                <div class="profile-picture-container">
                                    <img src="<?php echo $user['profile_picture'] ? UPLOAD_URL . $user['profile_picture'] : 'assets/images/default-avatar.png'; ?>" 
                                         alt="Profile Picture" 
                                         class="profile-picture-large">
                                    <label for="profile_picture" class="profile-picture-upload">
                                        <i class="fas fa-camera"></i>
                                    </label>
                                    <input type="file" 
                                           id="profile_picture" 
                                           name="profile_picture" 
                                           accept="image/*" 
                                           style="display: none;">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="first_name" 
                                           name="first_name" 
                                           value="<?php echo htmlspecialchars($user['first_name']); ?>" 
                                           required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="last_name" 
                                           name="last_name" 
                                           value="<?php echo htmlspecialchars($user['last_name']); ?>" 
                                           required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="bio" class="form-label">Bio</label>
                                <textarea class="form-control" 
                                          id="bio" 
                                          name="bio" 
                                          rows="3"><?php echo htmlspecialchars($user['bio']); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="skills" class="form-label">Skills</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="skills" 
                                       name="skills" 
                                       value="<?php echo htmlspecialchars($user['skills']); ?>"
                                       placeholder="Enter skills separated by commas">
                            </div>
                            
                            <div class="mb-3">
                                <label for="interests" class="form-label">Interests</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="interests" 
                                       name="interests" 
                                       value="<?php echo htmlspecialchars($user['interests']); ?>"
                                       placeholder="Enter interests separated by commas">
                            </div>
                            
                            <div class="mb-3">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="location" 
                                       name="location" 
                                       value="<?php echo htmlspecialchars($user['location']); ?>">
                            </div>
                            
                            <?php if ($role == 'mentor'): ?>
                                <div class="mb-3">
                                    <label for="hourly_rate" class="form-label">Hourly Rate ($)</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="hourly_rate" 
                                           name="hourly_rate" 
                                           value="<?php echo htmlspecialchars($profile['hourly_rate']); ?>"
                                           min="0" 
                                           step="0.01">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="availability" class="form-label">Availability</label>
                                    <textarea class="form-control" 
                                              id="availability" 
                                              name="availability" 
                                              rows="3"><?php echo htmlspecialchars($profile['availability']); ?></textarea>
                                </div>
                            <?php else: ?>
                                <div class="mb-3">
                                    <label for="goals" class="form-label">Goals</label>
                                    <textarea class="form-control" 
                                              id="goals" 
                                              name="goals" 
                                              rows="3"><?php echo htmlspecialchars($profile['goals']); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="preferred_topics" class="form-label">Preferred Mentoring Topics</label>
                                    <textarea class="form-control" 
                                              id="preferred_topics" 
                                              name="preferred_topics" 
                                              rows="3"><?php echo htmlspecialchars($profile['preferred_mentoring_topics']); ?></textarea>
                                </div>
                            <?php endif; ?>
                            
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Preview profile picture before upload
        document.getElementById('profile_picture').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.querySelector('.profile-picture').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html> 