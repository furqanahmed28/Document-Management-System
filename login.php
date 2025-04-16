<?php
// Start session to track logged-in users
session_start();


if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'Admin') {
        header("Location: admin.php");
        exit;
    } elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'User') {
        header("Location: index.php");
        exit;
    } elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'Dispatcher') {
        header("Location: status.php");
        exit;
    }
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // SQLite database file
    $dbFile = 'dmsdb.db';

    // Connect to SQLite database
    $pdo = new PDO("sqlite:$dbFile");

    // Get form data
    $email = $_POST['username'];
    $password = $_POST['password'];

    // Check email domain
    if (strpos($email, '@synergy.net.pk') === false) {
        $errorMessage = "Invalid email domain. Only @synergy.net.pk allowed.";
    } else {
        // Prepare and execute SQL query to find the user
        $stmt = $pdo->prepare("SELECT * FROM Users WHERE email = :email AND password = :password");
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $password); // Assumes password is stored in plain text
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Check user type and app status, then set session variables and redirect
            $_SESSION['loggedin'] = true;
            $_SESSION['login_email'] = $email;
            $_SESSION['user_type'] = $user['Type'];
            $_SESSION['login_id'] = $user['ID'];

            if ($user['Type'] === 'Admin' && $user['app'] == 1) {
                header("Location: admin.php");
            } elseif ($user['Type'] === 'User' && $user['app'] == 1) {
                header("Location: index.php");
            }  elseif ($user['Type'] === 'Dispatcher' && $user['app'] == 1) {
                    header("Location: status.php");
            } else {
                $errorMessage = "Access not allowed.";
                // Clear session on failure
                session_unset();
                session_destroy();
            }
            exit;
        } else {
            // If user is not found, show error message
            $errorMessage = "Incorrect email or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Page</title>
    <link rel="icon" type="image/x-icon" href="favicon.png">

</head>

<style>
    /* Existing styles */
        /* Reset some basic styles */
        * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    /* Make the body fill the screen and center the content */
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #57976a, #81c281);
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
        color: #fff;
        overflow: hidden;
    }

    /* Style the login box */
    .login-box {
        width: 400px;
        /* Increased width for larger form */
        padding: 40px;
        /* Increased padding for better spacing */
        background: #fff;
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        text-align: center;
        border-radius: 15px;
        box-sizing: border-box;
        transition: transform 0.3s ease-in-out;
    }

    /* Hover effect on login-box */
    .login-box:hover {
        transform: scale(1.05);
    }

    /* Style the image */
    .login-image {
        width: 120px;
        /* Larger logo */
        margin-bottom: 30px;
        /* border-radius: 50%; */
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }

    /* Style the form heading */
    h2 {
        margin-bottom: 20px;
        font-size: 30px;
        /* Larger font for heading */
        color: #333;
        font-weight: 600;
    }

    /* Style the input fields */
    .textbox {
        margin-bottom: 20px;
    }

    .textbox input {
        width: 100%;
        padding: 15px;
        /* Larger padding */
        margin: 8px 0;
        border: 1px solid #ccc;
        border-radius: 30px;
        /* Rounded edges for input fields */
        font-size: 16px;
        outline: none;
        transition: all 0.3s ease;
    }

    .textbox input:focus {
        border-color: #4CAF50;
        box-shadow: 0 0 5px rgba(76, 175, 80, 0.7);
    }

    /* Style the button */
    .btn {
        width: 100%;
        padding: 15px;
        background-color: #4CAF50;
        color: white;
        border: none;
        border-radius: 30px;
        /* Rounded button */
        font-size: 18px;
        cursor: pointer;
        transition: background-color 0.3s, transform 0.3s;
    }

    .btn:hover {
        background-color: #45a049;
        transform: translateY(-3px);
        /* Slightly lift the button on hover */
    }

    .btn:active {
        transform: translateY(1px);
        /* Button clicks give a pressed effect */
    }

    /* Make the page responsive */
    @media (max-width: 768px) {
        .login-box {
            width: 80%;
            padding: 30px;
        }

        .login-image {
            width: 100px;
        }

        h2 {
            font-size: 24px;
        }
    }

    @media (max-width: 480px) {
        .login-box {
            width: 90%;
            padding: 20px;
        }

        .login-image {
            width: 80px;
        }

        h2 {
            font-size: 20px;
        }
    }

    /* Add styles for alert messages */
    .alert {
        padding: 10px;
        margin-bottom: 20px;
        background-color: #f44336;
        color: white;
        border-radius: 5px;
        text-align: center;
    }
</style>

<body>
    <div class="login-box">
        <img src="logo-inner.png" alt="Login Image" class="login-image">
        <form action="login.php" method="post">
            <h2>Login</h2>

            <?php if (isset($errorMessage)): ?>
                <div class="alert"><?php echo $errorMessage; ?></div>
            <?php endif; ?>

            <div class="textbox">
                <input type="email" placeholder="Username" name="username" required>
            </div>
            <div class="textbox">
                <input type="password" placeholder="Password" name="password" required>
            </div>
            <input type="submit" value="Login" class="btn">
        </form>
    </div>
</body>

</html>
