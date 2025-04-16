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

?>




<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

    <link rel="icon" type="image/x-icon" href="favicon.png">
</head>
<style>
/* Reuse the same CSS styles as in index.php */
/* Reuse the same CSS styles as in index.php */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Roboto', sans-serif;
    background-color: #f1f4f7;
    color: #333;
}

header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%; /* Full width */
    height: 100px;
    padding: 60px 5%;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    background: white;
    border-radius: 8px;
    position: relative;
}

/* Logo stays on the left */
.header-left img {
    max-height: 40px;
}

.header-right {
    display: flex;
    flex-direction: column;
    align-items: flex-end; /* Align items to the right */
}

.header-info {
    display: flex;
    align-items: center;
    gap: 15px; /* Space between email and logout */
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

/* Navigation Bar Styling */
.navigation {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 30px;
    background-color:rgb(255, 255, 255); /* Set the background color of the navbar */
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    border-radius: 5px;
    margin-top: 20px;
    color: white; /* Text color for the navbar */
}

.add-button {
    padding: 12px 25px;
    background-color: #357c3c;
    color: white;
    border: none;
    cursor: pointer;
    border-radius: 5px;
    font-size: 16px;
    transition: background-color 0.3s ease;
}

.add-button:hover {
    background-color: #2c6b2e;
}

.search input {
    padding: 12px;
    width: 250px;
    border: 1px solid #ccc;
    border-radius: 5px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.search input:focus {
    border-color: #357c3c;
    outline: none;
}

/* Data Table Container */
.datatable-container {
    padding: 25px;
    background-color: #fff;
    margin: 30px auto;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    max-width: 2000px;
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
    vertical-align: middle; /* Ensures all table cells are vertically aligned in the middle */
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



/* Pagination */
.pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.pagination button {
    padding: 10px 15px;
    background-color: #357c3c;
    color: white;
    border: none;
    cursor: pointer;
    border-radius: 5px;
    font-size: 14px;
    transition: background-color 0.3s ease;
}

.pagination button:hover {
    background-color: #2c6b2e;
}

.pagination button:disabled {
    background-color: #c1c1c1;
    cursor: not-allowed;
}

/* User Email Styles */
.user-email {
    margin-right: 30px;
    font-weight: bold;
    color: #333;
}
/* Ensure the comment column wraps text after expanding */

/* Ensure the comment column wraps text after expanding */
table td.comment {
    width: 200px; /* Adjust to your preferred width */
    white-space: normal; /* Allow text to wrap */
    word-wrap: break-word; /* Break long words if necessary */
    transition: height 0.3s ease-in; /* Smooth height transition */
}

/* Style for expanded comment and attachment sections */
.expanded-comment,
.expanded-attachments {
    padding: 10px;
    background-color: #f1f1f1;
    border: 1px solid #ddd;
    margin-top: 10px;
    display: none;
    white-space: normal; /* Ensure text wraps */
    word-wrap: break-word; /* Break long words */
    transition: height 0.3s ease-in; /* Smooth height transition */
}

/* Ensure the comment text is truncated initially */
.comment-text {
    display: inline-block;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%; /* Adjust to fit content */
}

/* Style for the + and - icons */
.view-comment,
.view-attachments {
    color: #357c3c; /* Green color */
    cursor: pointer;
    font-size: 20px; /* Set a proper size for the icons */
    font-weight: bold;
    display: inline-block;
    margin-left: 10px; /* Add some spacing */
    transition: color 0.3s ease-in; /* Smooth transition for color change */
}

/* Change color of icon on hover */
.view-comment:hover,
.view-attachments:hover {
    color: #2c6b2e; /* Darker green on hover */
}

/* Optional: Make the table row height uniform */
table tr {
    height: 60px;
    transition: all 0.3s ease;
}
.nav-section {
    display: flex;
    justify-content: space-between; /* Distributes space evenly */
    flex-wrap: wrap;
    width: 75%;
    gap: 10px; /* Optional spacing between wrapped rows */
}

/* Responsive Styles */
@media (max-width: 768px) {
    header {
        flex-direction: column;
        align-items: center;
        padding: 30px 20px;
        text-align: center;
        height: auto;
    }

    .header-right {
        align-items: center;
        margin-top: 15px;
    }

    .header-info {
        flex-direction: column;
        gap: 10px;
    }

    .button-group {
        align-items: center;
        width: 100%;
    }

    .logout,
    .back-btn {
        width: 100%;
    }
    .navigation {
        flex-direction: column;
        align-items: stretch;
        gap: 15px;
    }

    .nav-section {
        flex-direction: column;
        width: 100%;
        gap: 10px;
        justify-content: unset; /* Remove even spacing on small screens */
        align-items: stretch;
    }

    .add-button {
        width: 100%;
    }

    .search {
        width: 100%;
    }

    .search input {
        width: 100%;
        margin-top: 10px;
    }

    .datatable-container {
        margin: 10px;
    }

    table th div {
        margin-top: 5px;
    }
}

@media (max-width: 480px) {
    header {
        flex-direction: column;
        align-items: center;
        padding: 20px 15px;
        height: auto;
        text-align: center;
    }

    .header-right {
        align-items: center;
        margin-top: 15px;
    }

    .header-info {
        flex-direction: column;
        gap: 10px;
    }

    .button-group {
        align-items: center;
        width: 100%;
    }

    .logout,
    .back-btn {
        width: 100%;
    }

    .navigation {
        flex-direction: column;
        align-items: stretch;
        gap: 15px;
    }

    .nav-section {
        flex-direction: column;
        gap: 10px;
        width: 100%;
        justify-content: unset;
        align-items: stretch;
    }

    .add-button {
        width: 100%;
    }

    .search {
        width: 100%;
    }

    .search input {
        width: 100%;
    }

    .datatable-container {
        margin: 10px;
    }

    table th div {
        margin-top: 5px;
    }

    .filter-input {
        width: 100%;
    }
}


</style>

<body>

    <!-- Header -->
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

    <!-- Navigation Section -->
    <!-- Navigation Section -->
<div class="navigation">
    <div class="nav-section">
        <button class="add-button" onclick="users()">Users</button>
    </div>
    <div class="search">
        <input type="text" id="search" placeholder="Search..." oninput="searchTable()">
    </div>
</div>



    <!-- Datatable Section -->
    <div class="datatable-container">
    <table id="datatable">
        <thead>
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



    <!-- Pagination -->

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
function users() {
            window.location.href = 'add_user.php';
        }
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


<!--php 10 rows-->