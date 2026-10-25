<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MEOWMEOW Library - Login</title>
    <link rel="stylesheet" href="style.css"> 
</head>
<body>
    
<div class="bg-login">    
<header class="main-header">
    <img src="Logo.png" alt="MEOWMEOW LIBRARY Logo" class="logo-image">
</header>

<main class="content-wrapper">
    <div class="login-container"> 
        
        <h1 class="main-title">Login</h1>

        <?php if(isset($_GET['error'])): ?>
        <p style="color:red; text-align:center;">
            <?php echo htmlspecialchars($_GET['error']); ?>
        </p>
        <?php endif; ?>

        <form class="login-form" action="login_handler.php" method="POST">
    
            <label for="email">Email</label>
            <input type="text" id="email" name="email" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <button type="submit" class="btn btn-login">
                LOGIN
            </button>
            
            <div class="signup-link-section">
                <p class="small-link">Don't have an account?</p>
                
                <a href="signup.php" class="btn btn-signup">
                    SIGN UP
                </a>
            </div>

        </form>

    </div>
</main>
</div>

</body>
</html>
