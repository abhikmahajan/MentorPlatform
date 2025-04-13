<?php
require_once 'config.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentor Platform - Connect with Expert Mentors</title>
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
            line-height: 1.6;
            background-color: var(--nav-bg);
            color: var(--white);
        }

        .hero-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: var(--white);
            padding: 100px 0;
            margin-bottom: 50px;
        }

        .feature-card {
            background-color: var(--card-bg);
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 30px;
            color: var(--white);
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(255, 255, 255, 0.1);
        }

        .feature-icon {
            font-size: 2.5rem;
            color: var(--accent-color);
            margin-bottom: 20px;
        }

        .cta-button {
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            text-transform: uppercase;
            background-color: var(--highlight-color);
            color: #000;
            border: none;
            transition: all 0.3s ease;
        }

        .cta-button:hover {
            background-color: var(--accent-color);
            color: #000;
            box-shadow: 0 0 15px var(--highlight-color);
        }

        .navbar {
            background-color: var(--nav-bg);
            box-shadow: 0 2px 4px rgba(255, 255, 255, 0.05);
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--highlight-color) !important;
        }

        .navbar-nav .nav-link {
            color: var(--light-gray) !important;
            font-weight: 500;
            margin-left: 15px;
        }

        .navbar-nav .btn {
            background-color: var(--highlight-color);
            color: #000 !important;
            font-weight: 600;
            transition: background 0.3s ease;
        }

        .navbar-nav .btn:hover {
            background-color: var(--accent-color);
            color: #000 !important;
        }

        #how-it-works {
            background-color: var(--card-bg);
            color: var(--white);
        }

        footer {
            background-color: var(--footer-bg);
            color: var(--light-gray);
        }

        footer h5 {
            color: var(--highlight-color);
        }

        footer a {
            color: var(--light-gray);
            text-decoration: none;
        }

        footer a:hover {
            color: var(--highlight-color);
        }

        .social-links a {
            font-size: 1.25rem;
            color: var(--highlight-color) !important;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">Mentor Platform</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#how-it-works">How It Works</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-primary text-white px-3" href="register.php">Sign Up</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">Connect with Expert Mentors</h1>
                    <p class="lead mb-4">Find the perfect mentor to guide you in your professional and personal development journey.</p>
                    <a href="register.php" class="btn btn-light btn-lg cta-button">Get Started</a>
                </div>
                <div class="col-lg-6">
                    <img src="assets/images/hero.png" alt="Mentorship Illustration" class="img-fluid">
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">Why Choose Our Platform?</h2>
            <div class="row">
                <div class="col-md-4">
                    <div class="card feature-card text-center p-4">
                        <i class="fas fa-user-graduate feature-icon"></i>
                        <h3>Expert Mentors</h3>
                        <p>Connect with experienced professionals who can guide you in your career and personal growth.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card text-center p-4">
                        <i class="fas fa-calendar-check feature-icon"></i>
                        <h3>Flexible Scheduling</h3>
                        <p>Book sessions at your convenience with our easy-to-use scheduling system.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card text-center p-4">
                        <i class="fas fa-chart-line feature-icon"></i>
                        <h3>Track Progress</h3>
                        <p>Monitor your development with our comprehensive goal tracking system.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section id="how-it-works" class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">How It Works</h2>
            <div class="row">
                <div class="col-md-3 text-center">
                    <div class="mb-4">
                        <i class="fas fa-user-plus fa-3x text-warning"></i>
                    </div>
                    <h4>1. Create Account</h4>
                    <p>Sign up as a mentor or mentee</p>
                </div>
                <div class="col-md-3 text-center">
                    <div class="mb-4">
                        <i class="fas fa-search fa-3x text-warning"></i>
                    </div>
                    <h4>2. Find Match</h4>
                    <p>Search for the perfect mentor</p>
                </div>
                <div class="col-md-3 text-center">
                    <div class="mb-4">
                        <i class="fas fa-calendar-alt fa-3x text-warning"></i>
                    </div>
                    <h4>3. Schedule Session</h4>
                    <p>Book your mentoring session</p>
                </div>
                <div class="col-md-3 text-center">
                    <div class="mb-4">
                        <i class="fas fa-rocket fa-3x text-warning"></i>
                    </div>
                    <h4>4. Start Learning</h4>
                    <p>Begin your growth journey</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5>Mentor Platform</h5>
                    <p>Connecting mentors and mentees for professional growth.</p>
                </div>
                <div class="col-md-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="#">About Us</a></li>
                        <li><a href="#">Contact</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Connect With Us</h5>
                    <div class="social-links">
                        <a href="#" class="me-3"><i class="fab fa-facebook"></i></a>
                        <a href="#" class="me-3"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="me-3"><i class="fab fa-linkedin"></i></a>
                    </div>
                </div>
            </div>
            <hr class="mt-4">
            <div class="text-center">
                <p class="mb-0">&copy; 2024 Mentor Platform. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
