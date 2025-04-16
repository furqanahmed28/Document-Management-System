<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['user_type'] !== 'Admin') {
    header("Location: login.php");
    exit;
}
$login_email = $_SESSION['login_email'];
$login_id = $_SESSION['login_id'];
// Include database connection
$pdo = new PDO("sqlite:dmsdb.db");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize and collect form data
    $name = htmlspecialchars($_POST['name']);
    $email = htmlspecialchars($_POST['email']);
    $type = $_POST['type'];
    $dept = $_POST['dept'];
    $branch = $_POST['branch'];
    $password = $_POST['password'];  // Hash the password

    // Prepare SQL to insert user
    $sql = "INSERT INTO Users (name, email, Type, password, app) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name, $email, $type, $password, '1']);
    $user_id = $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO UsersBranch (user_id, branch) VALUES (:user_id, :branch)")->execute([':user_id' => $user_id, ':branch' => $branch]);
    $pdo->prepare("INSERT INTO UsersDept (user_id, dept) VALUES (:user_id, :dept)")->execute([':user_id' => $user_id, ':dept' => $dept]);


    // Redirect to the user list or display success message
    header('Location: users.php');
    exit();
}

// Fetch departments and branches for dropdowns
$departments = $pdo->query("SELECT * FROM Departments")->fetchAll(PDO::FETCH_ASSOC);
$branches = $pdo->query("SELECT * FROM branch")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New User</title>
    <link rel="icon" type="image/x-icon" href="favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f1f1f1;
            margin: 0;
            padding: 0;
            color: #333;
        }

        header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 97%; /* Full width */
    padding: 30px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    background: white;
    border-radius: 8px;
    position: relative;
}
.password-wrapper {
    position: relative;
    width: 100%;
}

.password-wrapper input {
    width: 100%;
    padding-right: 40px; /* Space for the eye icon */
}

.toggle-password {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    font-size: 18px;
    color: #555;
}

.toggle-password:hover {
    color: #27ae60;
}

/* Logo stays on the left */
.header-left img {
    max-height: 40px;
}

.header-right {
    display: flex;
    flex-direction: column;
    align-items: flex-end; /* Align items to the right */
    gap: 10px; /* Space between elements */
}

.header-info {
    display: flex;
    align-items: center;
    gap: 15px; /* Space between email and logout */
}


        .user-email {
            font-weight: bold;
        }

        .button-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .logout, .back-btn, button[type="submit"] {
            width: 100px;
            text-align: center;
            padding: 10px;
            font-size: 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .logout {
            background-color: #d2d9dc;
            color: #357c3c;
        }

        .logout:hover {
            background-color: #357c3c;
            color: white;
        }

        .back-btn {
            background-color: #6c757d;
            color: white;
        }

        .back-btn:hover {
            background-color: #5a6268;
        }

        .container {
            max-width: 600px;
            margin: 10px auto;
            padding: 20px;
            border-radius: 10px;
            background-color: #ffffff;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        h2 {
            text-align: center;
            font-size: 24px;
            color: #2c3e50;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #34495e;
        }

        input, select {
            width: 100%;
            padding: 12px;
            font-size: 14px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
            background-color: #f9f9f9;
            transition: border 0.3s ease;
        }

        input:focus, select:focus {
            border-color: #27ae60;
            outline: none;
            background-color: #fff;
        }

        button[type="submit"] {
            width: 100%;
            background-color: #27ae60;
            color: #fff;
        }

        button[type="submit"]:hover {
            background-color: #2ecc71;
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }

            h2 {
                font-size: 20px;
            }

            button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<header>
    <div class="header-left">
        <img src="logo-inner.png" alt="Logo" class="logo">
    </div>
    <div class="header-right">
        <div class="header-info">
            <span class="user-email"><?php echo htmlspecialchars($login_email ?? 'Guest'); ?></span>
            <button class="logout" onclick="logout()">Logout</button>
        </div>
        <button class="back-btn" onclick="window.location.href='login.php'">Home</button>
        </div>
</header>

<div class="container">
    <h2>Add New User</h2>
    <form method="POST" action="add_user.php">
        <div class="form-group">
            <label for="name">Full Name</label>
            <input type="text" name="name" id="name" required>
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" name="email" id="email" required>
        </div>

        <div class="form-group">
            <label for="type">User Type</label>
            <select name="type" id="type" required>
                <option value="">Select User Type</option>
                <option value="Admin">Admin</option>
                <option value="User">User</option>
                <option value="Dispatcher">Dispatcher</option>
            </select>
        </div>

        <div class="form-group">
            <label for="dept">Department</label>
            <select name="dept" id="dept" required>
                <option value="">Select Department</option>
                <?php foreach ($departments as $dept) {
                    echo "<option value='{$dept['DeptCode']}'>{$dept['DeptName']}</option>";
                } ?>
            </select>
        </div>

        <div class="form-group">
            <label for="branch">Branch</label>
            <select name="branch" id="branch" required>
                <option value="">Select Branch</option>
                <?php foreach ($branches as $branch) {
                    echo "<option value='{$branch['BCode']}'>{$branch['City']}</option>";
                } ?>
            </select>
        </div>

<!-- Password Field -->
<div class="form-group password-group">
    <label for="password">Password</label>
    <div class="password-wrapper">
        <input type="password" name="password" id="password" class="form-control" required>
        <span class="toggle-password" onclick="togglePassword()">
            👁️
        </span>
    </div>
</div>


        <div class="form-group">
            <button type="submit">Add User</button>
        </div>
    </form>
</div>

<script>
        function logout() {
            // Make an AJAX request to the server to clear the session
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'logout.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function () {
                if (xhr.status === 200) {
                    // Redirect to the login page after session is cleared
                    window.location.href = 'login.php';
                } else {
                    console.error('Failed to log out');
                }
            };
            xhr.send();
        }
    function togglePassword() {
    var passwordInput = document.getElementById("password");
    if (passwordInput.type === "password") {
        passwordInput.type = "text";
    } else {
        passwordInput.type = "password";
    }
}

</script>
</body>
</html>
