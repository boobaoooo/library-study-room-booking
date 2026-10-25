<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MEOWMEOW Library - Signup</title>
    <link rel="stylesheet" href="style.css"> 
    <link rel="stylesheet" href="signup.css">
</head>
<body>
    
<header class="main-header">
    <img src="Logo.png" alt="MEOWMEOW LIBRARY Logo" class="logo-image">
</header>

<main class="content-wrapper">

    <a href="login.php" class="back-button"></a>

    <div class="signup-container"> 
        
        <h1 class="signup-main-title">SIGNUP</h1>

        <?php if (isset($_GET['error'])): ?>
        <p style="color:red; text-align:center;"><?= htmlspecialchars($_GET['error']) ?></p>
        <?php endif; ?>

        <div class="signup-form-wrapper">
            
            <form class="signup-form" action="/librarystudyroombooking/signup_handler.php" method="POST" enctype="multipart/form-data"> 
                
                <div class="profile-image-upload">
                    Insert image here
                    <input type="file" name="profileImage" accept="image/*">
                </div>

                <label>Username</label>
                <input type="text" name="username" required>

                <label>Email</label>
                <input type="email" name="email" required>

                <label>Password</label>
                <input type="password" name="password" minlength="6" required>

                <button type="submit" class="btn btn-signup-submit">
                    SIGNUP
                </button>
                
            </form>
        </div>
    </div>
</main>

</body>
</html>
