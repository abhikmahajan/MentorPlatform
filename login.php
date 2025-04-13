<?php
require_once 'config.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        try {
            $stmt = $conn->prepare("SELECT user_id, email, password, role FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Invalid email or password";
            }
        } catch(PDOException $e) {
            $error = "An error occurred. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Mentor Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    body {
        background: linear-gradient(135deg, #1b0d3f, #2d1062, #512b91);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-family: 'Segoe UI', sans-serif;
    }
    .login-container {
        max-width: 400px;
        width: 100%;
        margin: 0 auto;
        padding: 20px;
    }
    .card {
        border: none;
        border-radius: 20px;
        background: #1e1457;
        box-shadow: 0 0 30px rgba(186, 85, 211, 0.2);
    }
    .card-header {
        background: linear-gradient(135deg, #6c2bd9, #9b51e0);
        color: white;
        text-align: center;
        border-radius: 20px 20px 0 0 !important;
        padding: 25px;
    }
    .form-control {
        border-radius: 8px;
        padding: 12px;
        border: 1px solid #9b51e0;
        background: #281b63;
        color: #fff;
    }
    .form-control:focus {
        background-color: #281b63;
        border-color: #c084fc;
        color: #fff;
        box-shadow: 0 0 0 0.2rem rgba(195, 142, 253, 0.25);
    }
    .input-group-text {
        background: #2d206c;
        color: #c084fc;
        border: none;
        border-right: 1px solid #9b51e0;
    }
    .btn-primary {
        background-color: #f2c94c;
        color: #000;
        border: none;
        padding: 12px;
        width: 100%;
        border-radius: 8px;
        font-weight: bold;
    }
    .btn-primary:hover {
        background-color: #ffde59;
        color: #000;
    }
    .form-check-label {
        color: #ccc;
    }
    .alert-danger {
        background-color: #ff4d4d;
        color: white;
        border: none;
        border-radius: 8px;
    }
    .text-decoration-none {
        color: #f2c94c;
    }
    .text-decoration-none:hover {
        color: #fff;
    }
    .social-login {
        text-align: center;
        margin-top: 20px;
    }
    .social-login a {
        margin: 0 10px;
        color: #f2c94c;
        font-size: 24px;
        transition: color 0.3s ease;
    }
    .social-login a:hover {
        color: #fff;
    }
</style>

</head>
<body>
    <div class="login-container">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">Welcome Back</h3>
                <p class="mb-0">Login to your account</p>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary mb-3">Login</button>
                    
                    <div class="text-center">
                        <a href="forgot-password.php" class="text-decoration-none">Forgot Password?</a>
                    </div>
                </form>
                
                <div class="social-login">
                    <p class="text-muted">Or login with</p>
                    <a href="#"><i class="fab fa-google"></i></a>
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-linkedin"></i></a>
                </div>
                
                <div class="text-center mt-4">
                    <p class="mb-0">Don't have an account? <a href="register.php" class="text-decoration-none">Sign up</a></p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 