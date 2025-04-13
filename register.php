<?php
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = sanitize_input($_POST['first_name']);
    $last_name = sanitize_input($_POST['last_name']);
    $role = sanitize_input($_POST['role']);

    if (empty($email) || empty($password) || empty($confirm_password) || empty($first_name) || empty($last_name)) {
        $error = "Please fill in all fields";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long";
    } else {
        try {
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                $error = "Email already registered";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (email, password, first_name, last_name, role) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$email, $hashed_password, $first_name, $last_name, $role]);

                $user_id = $conn->lastInsertId();
                if ($role == 'mentor') {
                    $stmt = $conn->prepare("INSERT INTO mentor_profiles (mentor_id) VALUES (?)");
                    $stmt->execute([$user_id]);
                } else {
                    $stmt = $conn->prepare("INSERT INTO mentee_profiles (mentee_id) VALUES (?)");
                    $stmt->execute([$user_id]);
                }

                $success = "Registration successful! Please login.";
            }
        } catch(PDOException $e) {
            $error = "An error occurred: " . $e->getMessage();
            error_log("Registration Error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Mentor Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
    background: linear-gradient(135deg, #0e0435, #6c21c2);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
}

.register-container {
    max-width: 500px;
    width: 100%;
    padding: 20px;
}

.card {
    background-color: rgba(255, 255, 255, 0.06);
    border: 2px solid #ffffff22;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
    backdrop-filter: blur(8px);
    color: #fff;
}

.card-header {
    background: linear-gradient(135deg, #6c21c2, #c438d6);
    color: #fff;
    text-align: center;
    border-radius: 16px 16px 0 0;
    padding: 25px;
}

.card-header h3 {
    font-weight: bold;
}

.form-control {
    border-radius: 10px;
    padding: 12px;
    font-size: 15px;
    border: none;
    background-color: #ffffffdd;
    color: #000;
}

.form-control:focus {
    box-shadow: 0 0 0 3px rgba(108, 33, 194, 0.3);
}

.btn-primary {
    background-color: #fdf12a;
    color: #000;
    border: none;
    padding: 12px;
    font-weight: 600;
    border-radius: 10px;
    transition: transform 0.2s ease;
}

.btn-primary:hover {
    background-color: #f7e600;
    transform: scale(1.02);
}

.role-selector {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}

.role-option {
    flex: 1;
    padding: 18px;
    border: 2px solid #fdf12a;
    border-radius: 12px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease-in-out;
    background-color: #ffffffdd;
    color: #000;
}

.role-option.selected {
    background-color: #fdf12a;
    color: #000;
    transform: scale(1.05);
    border-color: #fff;
}

.role-option i {
    font-size: 22px;
    margin-bottom: 6px;
}

.role-option h5 {
    margin: 5px 0 3px;
}

.text-link a {
    text-decoration: none;
    color: #ecff74;
    font-weight: 600;
}

.text-link a:hover {
    text-decoration: underline;
}

    </style>
</head>
<body>
    <div class="register-container">
        <div class="card">
            <div class="card-header">
                <h3>Create Account</h3>
                <p class="mb-0">Join our mentor platform</p>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <form method="POST" action="" id="registerForm">
                    <div class="role-selector">
                        <div class="role-option" data-role="mentor">
                            <i class="fas fa-user-graduate"></i>
                            <h5>Mentor</h5>
                            <p class="mb-0">Share your expertise</p>
                        </div>
                        <div class="role-option" data-role="mentee">
                            <i class="fas fa-user"></i>
                            <h5>Mentee</h5>
                            <p class="mb-0">Learn and grow</p>
                        </div>
                    </div>

                    <input type="hidden" name="role" id="role" required>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>

                    <button type="submit" class="btn btn-primary mb-3">Create Account</button>

                    <div class="text-center text-link">
                        <p class="mb-0">Already have an account? <a href="login.php">Login</a></p>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const roleOptions = document.querySelectorAll('.role-option');
            const roleInput = document.getElementById('role');

            roleOptions.forEach(option => {
                option.addEventListener('click', function() {
                    roleOptions.forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                    roleInput.value = this.dataset.role;
                });
            });

            document.getElementById('registerForm').addEventListener('submit', function(e) {
                if (!roleInput.value) {
                    e.preventDefault();
                    alert('Please select a role');
                }
            });
        });
    </script>
</body>
</html>
