<?php
// Database connection
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['user_type'] !== 'Admin') {
    header("Location: login.php");
    exit;
}
$login_email = $_SESSION['login_email'];
$login_id = $_SESSION['login_id'];
$pdo = new PDO("sqlite:dmsdb.db");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Fetch all users
$query = "SELECT u.id, u.name, u.email, u.app, u.Type, b.City as branch, d.DeptName as dept 
          FROM Users u
          LEFT JOIN UsersBranch ub ON u.id = ub.user_id 
          LEFT JOIN branch b ON ub.branch = b.BCode 
          LEFT JOIN UsersDept ud ON u.id = ud.user_id 
          LEFT JOIN Departments d ON ud.dept = d.DeptCode";
$users = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Fetch user data for editing
$userData = null;
if (isset($_GET['user_id']) && filter_var($_GET['user_id'], FILTER_VALIDATE_INT)) {
    $userId = $_GET['user_id'];
    $stmt = $pdo->prepare("SELECT ID, name, email, Type FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userData) {
        $userData = ['error' => 'User not found'];
    }
}

// Handle form submission for editing user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $user_id = $_POST['user_id'];
    $branch = $_POST['branch'];
    $dept = $_POST['dept'];
    $type = $_POST['type'];
    $password = $_POST['password'];

    try {
        // Update user information
        $updateQuery = "UPDATE Users SET Type = :type" . ($password ? ", password = :password" : "") . " WHERE id = :user_id";
        $stmt = $pdo->prepare($updateQuery);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':type', $type);
        if ($password) $stmt->bindParam(':password', $password);
        $stmt->execute();

        // Update user branch
        $pdo->prepare("DELETE FROM UsersBranch WHERE user_id = :user_id")->execute([':user_id' => $user_id]);
        $pdo->prepare("INSERT INTO UsersBranch (user_id, branch) VALUES (:user_id, :branch)")->execute([':user_id' => $user_id, ':branch' => $branch]);

        // Update user department
        $pdo->prepare("DELETE FROM UsersDept WHERE user_id = :user_id")->execute([':user_id' => $user_id]);
        $pdo->prepare("INSERT INTO UsersDept (user_id, dept) VALUES (:user_id, :dept)")->execute([':user_id' => $user_id, ':dept' => $dept]);

        echo "<script>alert('User updated successfully!'); window.location.href = window.location.href;</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Error updating user: " . $e->getMessage() . "');</script>";
    }
}

// Add new user functionality (optional)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    // Add user logic as per your initial code
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
    <link rel="icon" type="image/x-icon" href="favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
/* General reset */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Arial', sans-serif;
    background-color: #f4f7fc;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    min-height: 100vh;
    color: #333;
    padding: 20px 0;
}

/* Header Styling */
header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 105%;
    height: 100px;
    padding: 20px 5%;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    position: relative;
    margin-bottom: 20px;
}

/* Logo on the left */
.header-left img {
    max-height: 40px;
}

/* Right-side email and buttons */
.header-right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.header-info {
    display: flex;
    align-items: center;
    gap: 15px;
}
.logout,
.back-btn {
    width: 100px; /* Ensures both buttons are the same width */
    text-align: center;
}

.back-btn {
    margin-top: 10px; /* Adds spacing below the logout button */
}

/* Email styling */
.user-email {
    font-weight: bold;
}

/* Container for Logout & Back buttons (Stacked in a column) */
.button-group {
    display: flex;
    flex-direction: column;
    gap: 10px; /* Space between buttons */
}

/* Logout button */
.logout {
    background-color: #d2d9dc;
    color: #357c3c;
    border: none;
    padding: 10px 20px;
    cursor: pointer;
    border-radius: 5px;
    font-size: 16px;
    transition: background-color 0.3s ease, color 0.3s ease;
}

.logout:hover {
    background-color: #357c3c;
    color: white;
}

/* Back button */
.back-btn {
    background-color: #6c757d;
    color: white;
    border: none;
    padding: 10px 20px;
    cursor: pointer;
    border-radius: 5px;
    font-size: 16px;
    transition: background-color 0.3s ease;
}

.back-btn:hover {
    background-color: #5a6268;
}

.datatable-container {
    padding: 25px;
    background-color: #fff;
    margin: 30px auto;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    max-width: 100%;
    overflow-x: auto;
}

/* Table Styles */
table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

table th,
table td {
    padding: 15px;
    border: 1px solid #ddd;
    text-align: left;
    font-size: 14px;
    vertical-align: middle;
}

table th {
    background-color: #d2d9dc;
    font-weight: bold;
    position: relative;
}

/* Filter Input Fields in Table Headers */
table th div {
    margin-top: 5px;
}

.filter-input {
    padding: 8px;
    width: 100%;
    border: 1px solid #ccc;
    border-radius: 5px;
    font-size: 14px;
    background-color: #f9f9f9;
    box-sizing: border-box;
    margin-top: 5px;
    transition: all 0.3s ease;
}

.filter-input:focus {
    border-color: #357c3c;
    outline: none;
}

/* Data Cells */
table td {
    background-color: #f9f9f9;
}

table td:hover {
    background-color: #f1f1f1;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

/* Add User Button */
.btn-add {
    display: inline-block;
    padding: 12px 25px;
    background-color: #357c3c;
    color: #fff;
    font-size: 1.1rem;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 20px;
    transition: background-color 0.3s ease, transform 0.2s ease;
    text-align: center;
    text-decoration: none;
}

.btn-add:hover {
    background-color: #2a6330;
    transform: scale(1.05);
}
/* Enhanced Modal Styles */
#editModal .modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    width: 500px;
    max-width: 90%;
    position: relative;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

#editModal h3 {
    color: #357c3c;
    font-size: 1.8rem;
    margin-bottom: 20px;
    text-align: center;
}

#editModal .form-group {
    margin-bottom: 20px;
}

#editModal .form-group label {
    font-weight: bold;
    color: #555;
    margin-bottom: 8px;
    font-size: 1.1rem;
}

#editModal .form-group input,
#editModal .form-group select {
    width: 100%;
    padding: 12px 15px;
    border-radius: 10px;
    border: 1px solid #ccc;
    font-size: 16px;
    outline: none;
    background-color: #f8f9fb;
    transition: all 0.3s ease;
}

#editModal .form-group input:focus,
#editModal .form-group select:focus {
    border-color: #357c3c;
    background-color: #e9f1f5;
    box-shadow: 0 0 8px rgba(53, 124, 60, 0.2);
}

#editModal .password-group {
    position: relative;
}

#editModal .password-wrapper {
    position: relative;
}

#editModal .toggle-password {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: #357c3c;
    font-size: 1.2rem;
    transition: color 0.3s ease;
    margin-top:-8px;
}

#editModal .toggle-password:hover {
    color: #2a6330;
}

#editModal .btn-add {
    width: 100%;
    padding: 12px;
    background-color: #357c3c;
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 1.1rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

#editModal .btn-add:hover {
    background-color: #2a6330;
    transform: scale(1.02);
}
        #editModal .form-group button:focus {
            outline: none;
        }

        /* Modal display control */
        .modal-open {
            display: block !important;
        }

        /* Modal Content */
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 500px;
            max-width: 90%;
            border-radius: 10px;
            position: relative;
        }

        /* Close button styles for modal */
        .close-btn {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            position: absolute;
            top: 10px;
            right: 25px;
        }

        .close-btn:hover,
        .close-btn:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            #editModal .form-section {
                width: 90%;
                /* Ensure the modal is smaller on smaller screens */
                padding: 15px;
            }

            .datatable-container {
        margin: 10px;
        padding: 15px;
    }

    table th,
    table td {
        padding: 10px;
        font-size: 12px;
    }

    .btn-add {
        font-size: 1rem;
        padding: 10px 20px;
    }

            .form-group input,
            .form-group select {
                width: 100%;
            }
        }

        /* Centering the modal vertically and horizontally */
        #editModal {
            display: flex;
            justify-content: center;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            /* Black background with transparency */
        }

        #editModal .form-section {
            background: #fff;
            /* Ensure the background is white */
            padding: 30px;
            border-radius: 10px;
            width: 500px;
            max-width: 90%;
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
            /* Scrollable content inside the modal */
            max-height: 80vh;
            /* Keep the modal content within the viewable area */
            height: auto;
        }
        
        @media (max-width: 480px) {
            #editModal .form-section {
                width: 90%;
                /* Ensure the modal is smaller on smaller screens */
                padding: 15px;
            }

            .datatable-container {
        margin: 10px;
        padding: 15px;
    }

    table th,
    table td {
        padding: 8px;
        font-size: 12px;
    }

    .btn-add {
        font-size: 0.9rem;
        padding: 8px 18px;
    }
}


            .form-group input,
            .form-group select {
                width: 100%;
            }
        

        /* Centering the modal vertically and horizontally */
        #editModal {
            display: flex;
            justify-content: center;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            /* Black background with transparency */
        }

        #editModal .form-section {
            background: #fff;
            /* Ensure the background is white */
            padding: 30px;
            border-radius: 10px;
            width: 500px;
            max-width: 90%;
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
            /* Scrollable content inside the modal */
            max-height: 80vh;
            /* Keep the modal content within the viewable area */
            height: auto;
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
        <h2>User List</h2>
        <a href="add_user.php" class="btn-add">Add User</a>

        <!-- Users Table -->
        <div class="datatable-container">
        <table id="datatable">
        <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Department</th>
                    <th>Branch</th>
                    <th>Type</th>
                    <th>App</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($user['name']) ?>
                    </td>
                    <td>
                        <?= htmlspecialchars($user['email']) ?>
                    </td>
                    <td>
                        <?= htmlspecialchars($user['dept']) ?>
                    </td>
                    <td>
                        <?= htmlspecialchars($user['branch']) ?>
                    </td>
                    <td>
                        <?= htmlspecialchars($user['Type']) ?>
                    </td>
                    <td>
                        <?= $user['app'] == 1 ? 'Approved' : 'Pending' ?>
                    </td>
                    <td><a href="javascript:void(0)" onclick="editUser(<?= $user['ID'] ?>)">✎</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Edit Modal -->
   <!-- Edit Modal -->
<div id="editModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal()">×</span>
        <h3>Edit User</h3>
        <form method="POST" id="editUserForm">
            <input type="hidden" name="user_id" id="user_id">
            <div class="form-group">
                <label>Email:</label>
                <span id="edit-email"></span>
            </div>
            <div class="form-group">
                <label>Name:</label>
                <span id="edit-name"></span>
            </div>
            <div class="form-group">
                <label for="edit-type">User Type</label>
                <select name="type" id="edit-type" required>
                    <option value="User">User</option>
                    <option value="Admin">Admin</option>
                    <option value="Dispatcher">Dispatcher</option>
                </select>
            </div>
            <div class="form-group">
                <label for="edit-department">Department</label>
                <select name="dept" id="edit-department" required>
                    <option value="">Select Department</option>
                    <?php
                    $departments = $pdo->query("SELECT * FROM Departments")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($departments as $dept) {
                        echo "<option value='{$dept['DeptCode']}'>{$dept['DeptName']}</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="edit-branch">Branch</label>
                <select name="branch" id="edit-branch" required>
                    <option value="">Select Branch</option>
                    <?php
                    $branches = $pdo->query("SELECT * FROM branch")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($branches as $branch) {
                        echo "<option value='{$branch['BCode']}'>{$branch['City']}</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="form-group password-group">
                <label for="edit-password">Password (Leave blank to keep the same)</label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="edit-password">
                    <i class="fas fa-eye toggle-password" onclick="togglePasswordVisibility()"></i>
                </div>
            </div>
            <button type="submit" name="edit_user" class="btn-add">Update User</button>
        </form>
    </div>
</div>

    <script>
// Function to open the modal and populate it with user data
function editUser(userId) {
    fetch(`fetch_users.php?user_id=${userId}`)
        .then(response => response.json())
        .then(user => {
            if (user) {
                // Populate the form with user data
                document.getElementById('user_id').value = user.ID;
                document.getElementById('edit-email').textContent = user.email;
                document.getElementById('edit-name').textContent = user.name;
                document.getElementById('edit-type').value = user.Type;

                // Set selected department by name (auto-select based on dept name)
                setDropdownValueByText('edit-department', user.dept);

                // Set selected branch by name (auto-select based on branch name)
                setDropdownValueByText('edit-branch', user.branch);

                // Set password field to empty (do not show the actual password)
                document.getElementById('edit-password').value = user.password;

                // Open the modal
                document.getElementById('editModal').style.display = 'block';
                console.log(user);
            } else {
                alert('User not found');
            }
        })
        .catch(error => alert('Error fetching user data'));
}

// Function to set a dropdown value based on the visible text
function setDropdownValueByText(selectId, valueText) {
    let selectElement = document.getElementById(selectId);
    if (selectElement) {
        for (let option of selectElement.options) {
            if (option.text === valueText) {
                selectElement.value = option.value; // Set the value of the matching option
                break;
            }
        }
    }
}

// Close the modal
function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}

function togglePasswordVisibility() {
    const passwordField = document.getElementById('edit-password');
    const eyeIcon = document.querySelector('.toggle-password');
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        eyeIcon.classList.remove('fa-eye');
        eyeIcon.classList.add('fa-eye-slash');
    } else {
        passwordField.type = 'password';
        eyeIcon.classList.remove('fa-eye-slash');
        eyeIcon.classList.add('fa-eye');
    }
}

// Close modal if clicked outside of it
window.onclick = function (event) {
    if (event.target === document.getElementById('editModal')) {
        closeModal();
    }
};

// Logout function
function logout() {
    // Make an AJAX request to clear the session
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

</script>
</body>

</html>
