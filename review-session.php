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

// Get session ID from URL
$session_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$session_id) {
    header("Location: sessions.php");
    exit();
}

try {
    // Get user data
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    // Get session details
    $stmt = $conn->prepare("
        SELECT s.*, 
               m.first_name as mentor_first_name,
               m.last_name as mentor_last_name,
               m.profile_picture as mentor_picture
        FROM sessions s
        JOIN users m ON s.mentor_id = m.user_id
        WHERE s.session_id = ? AND s.mentee_id = ? AND s.status = 'completed'
    ");
    $stmt->execute([$session_id, $user_id]);
    $session = $stmt->fetch();
    
    if (!$session) {
        header("Location: sessions.php");
        exit();
    }
    
    // Check if review already exists
    $stmt = $conn->prepare("SELECT * FROM reviews WHERE session_id = ?");
    $stmt->execute([$session_id]);
    if ($stmt->fetch()) {
        header("Location: session-details.php?id=" . $session_id);
        exit();
    }
    
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $rating = (int)$_POST['rating'];
        $comment = sanitize_input($_POST['comment']);
        
        // Validate input
        if ($rating < 1 || $rating > 5) {
            $error = "Please select a valid rating";
        } elseif (empty($comment)) {
            $error = "Please provide a comment";
        } else {
            // Insert review
            $stmt = $conn->prepare("
                INSERT INTO reviews (session_id, mentor_id, mentee_id, rating, comment)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$session_id, $session['mentor_id'], $user_id, $rating, $comment]);
            
            // Update mentor's average rating
            $stmt = $conn->prepare("
                UPDATE mentor_profiles 
                SET rating = (
                    SELECT AVG(rating) 
                    FROM reviews 
                    WHERE mentor_id = ?
                )
                WHERE mentor_id = ?
            ");
            $stmt->execute([$session['mentor_id'], $session['mentor_id']]);
            
            $success = "Review submitted successfully!";
            
            // Redirect to session details page after 2 seconds
            header("refresh:2;url=session-details.php?id=" . $session_id);
        }
    } catch(PDOException $e) {
        error_log("Review submission error: " . $e->getMessage());
        $error = "An error occurred. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Session - Mentor Platform</title>
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
        
        .rating-stars {
            font-size: 2rem;
            display: inline-flex;
            gap: 4px;
        }
        
        .rating-stars i {
            color: #ddd; /* Default grey color */
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .rating-stars i:hover,
        .rating-stars i.hover,
        .rating-stars i.active {
            color: #ffc107; /* Yellow color on hover/active */
            transform: scale(1.1);
        }
        
        .rating-stars .fas {
            -webkit-text-stroke: 1px #ffc107;
        }
        
        .rating-label {
            font-size: 1rem;
            margin-top: 8px;
            min-height: 24px;
            color: #666;
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
                    <img src="<?php echo $user['profile_picture'] ? UPLOAD_PATH . $user['profile_picture'] : 'assets/images/default-avatar.png'; ?>" 
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
                        <h5 class="mb-0">Review Session</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6 text-center">
                                <img src="<?php echo $session['mentor_picture'] ? UPLOAD_PATH . $session['mentor_picture'] : 'assets/images/default-avatar.png'; ?>" 
                                     alt="Mentor Picture" 
                                     class="profile-picture">
                                <h5><?php echo htmlspecialchars($session['mentor_first_name'] . ' ' . $session['mentor_last_name']); ?></h5>
                                <p class="text-muted">Mentor</p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="mb-3">Session Information</h6>
                                <p><strong>Date:</strong> <?php echo date('M d, Y', strtotime($session['start_time'])); ?></p>
                                <p><strong>Time:</strong> <?php echo date('h:i A', strtotime($session['start_time'])); ?> - <?php echo date('h:i A', strtotime($session['end_time'])); ?></p>
                                <p><strong>Duration:</strong> <?php echo round((strtotime($session['end_time']) - strtotime($session['start_time'])) / 3600); ?> hours</p>
                            </div>
                        </div>
                        
                        <form method="POST" action="" id="reviewForm">
                            <div class="mb-4">
                                <label class="form-label">Rating</label>
                                <div class="text-center">
                                    <div class="rating-stars">
                                        <i class="fas fa-star" data-rating="1" title="Poor"></i>
                                        <i class="fas fa-star" data-rating="2" title="Fair"></i>
                                        <i class="fas fa-star" data-rating="3" title="Good"></i>
                                        <i class="fas fa-star" data-rating="4" title="Very Good"></i>
                                        <i class="fas fa-star" data-rating="5" title="Excellent"></i>
                                    </div>
                                    <div class="rating-label" id="ratingLabel"></div>
                                </div>
                                <input type="hidden" name="rating" id="rating" required>
                            </div>
                            
                            <div class="mb-4">
                                <label for="comment" class="form-label">Your Review</label>
                                <textarea class="form-control" 
                                          id="comment" 
                                          name="comment" 
                                          rows="5" 
                                          placeholder="Share your experience with this mentoring session..."
                                          required></textarea>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="session-details.php?id=<?php echo $session_id; ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Session
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> Submit Review
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
        // Rating stars functionality
        const stars = document.querySelectorAll('.rating-stars i');
        const ratingInput = document.getElementById('rating');
        const ratingLabel = document.getElementById('ratingLabel');
        let selectedRating = 0;
        
        const ratingText = {
            0: '',
            1: 'Poor - Not satisfied with the mentoring',
            2: 'Fair - Some room for improvement',
            3: 'Good - Met expectations',
            4: 'Very Good - Above expectations',
            5: 'Excellent - Outstanding mentoring'
        };
        
        function updateStars(rating, isHover = false) {
            stars.forEach(star => {
                const starRating = parseInt(star.dataset.rating);
                if (starRating <= rating) {
                    star.classList.add(isHover ? 'hover' : 'active');
                } else {
                    star.classList.remove(isHover ? 'hover' : 'active');
                }
            });
            
            if (!isHover) {
                selectedRating = rating;
                ratingInput.value = rating;
            }
            ratingLabel.textContent = ratingText[rating];
        }
        
        stars.forEach(star => {
            // Hover effect
            star.addEventListener('mouseenter', function() {
                const rating = parseInt(this.dataset.rating);
                updateStars(rating, true);
            });
            
            // Click event
            star.addEventListener('click', function() {
                const rating = parseInt(this.dataset.rating);
                updateStars(rating);
            });
        });
        
        // Mouse leave rating container
        document.querySelector('.rating-stars').addEventListener('mouseleave', function() {
            updateStars(selectedRating);
        });
        
        // Form validation
        document.getElementById('reviewForm').addEventListener('submit', function(e) {
            if (!ratingInput.value) {
                e.preventDefault();
                alert('Please select a rating');
                return false;
            }
            
            const comment = document.getElementById('comment').value.trim();
            if (!comment) {
                e.preventDefault();
                alert('Please provide a review comment');
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html> 