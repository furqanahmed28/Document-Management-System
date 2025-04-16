<?php
session_start(); 
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['user_type'] !== 'Admin') {
    header("Location: login.php");
    exit;
}

$login_email = $_SESSION['login_email'];
$login_id = $_SESSION['login_id'];
// Assuming a PDO connection to your SQLite database is established
$pdo = new PDO("sqlite:dmsdb.db"); // Make sure the path to your database is correct



// Check if the form was submitted and process the input
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the posted data
    $type = $_POST['type']; // 'department', 'branch', 'address', 'signatory'
    $name = $_POST['name'];
    $code = $_POST['code'];
    

    // Step 1: Insert into the database based on the type
    if ($type && $name) {
        try {
            // Verify if code already exists in the respective table for each type
            if ($type == 'address') {
                // Check if the code already exists in the Addresses table
                $checkAddressQuery = "SELECT COUNT(*) FROM Addresses WHERE code = :code";
                $stmt = $pdo->prepare($checkAddressQuery);
                $stmt->bindParam(':code', $code);
                $stmt->execute();
                if ($stmt->fetchColumn() > 0) {
                    echo "<script>alert('Address code already exists.');</script>";
                } else {
                    // Insert into Addresses table
                    $insertAddressQuery = "INSERT INTO Addresses (name, code) VALUES (:name, :code)";
                    $stmt = $pdo->prepare($insertAddressQuery);
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':code', $code);
                    $stmt->execute();
                    echo "<script>alert('Address has been inserted successfully.');</script>";
                }
            } elseif ($type == 'signatory') {
                // Check if the user_id already exists in the Signatory table
                $user_id = $_POST['user_id'];
                $checkSignatoryQuery = "SELECT COUNT(*) FROM Signatory WHERE user_id = :user_id";
                $stmt = $pdo->prepare($checkSignatoryQuery);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
                if ($stmt->fetchColumn() > 0) {
                    echo "<script>alert('User ID already exists in the signatory table.');</script>";
                } else {
                    // Insert into Signatory table
                    $insertSignatoryQuery = "INSERT INTO Signatory (user_id) VALUES (:user_id)";
                    $stmt = $pdo->prepare($insertSignatoryQuery);
                    $stmt->bindParam(':user_id', $user_id);
                    $stmt->execute();
                    echo "<script>alert('Signatory has been inserted successfully.');</script>";
                }
            } elseif ($type == 'department') {
                // Check if the department code already exists in the Departments table
                $checkDepartmentQuery = "SELECT COUNT(*) FROM Departments WHERE DeptCode = :code";
                $stmt = $pdo->prepare($checkDepartmentQuery);
                $stmt->bindParam(':code', $code);
                $stmt->execute();
                if ($stmt->fetchColumn() > 0) {
                    echo "<script>alert('Department code already exists.');</script>";
                } else {
                    // Insert into Departments table
                    $insertDepartmentQuery = "INSERT INTO Departments (DeptName, DeptCode) VALUES (:name, :code)";
                    $stmt = $pdo->prepare($insertDepartmentQuery);
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':code', $code);
                    $stmt->execute();
                    echo "<script>alert('Department has been inserted successfully.');</script>";
                }
            } elseif ($type == 'branch') {
                // Check if the branch code already exists in the Branch table
                $checkBranchQuery = "SELECT COUNT(*) FROM Branch WHERE BCode = :code";
                $stmt = $pdo->prepare($checkBranchQuery);
                $stmt->bindParam(':code', $code);
                $stmt->execute();
                if ($stmt->fetchColumn() > 0) {
                    echo "<script>alert('Branch code already exists.');</script>";
                } else {
                    // Insert into Branch table
                    $insertBranchQuery = "INSERT INTO Branch (City, BCode) VALUES (:name, :code)";
                    $stmt = $pdo->prepare($insertBranchQuery);
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':code', $code);
                    $stmt->execute();
                    echo "<script>alert('Branch has been inserted successfully.');</script>";
                }
            }
        } catch (PDOException $e) {
            echo "<script>alert('Error: " . addslashes($e->getMessage()) . "');</script>";
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Fields</title>
    <link rel="icon" type="image/x-icon" href="favicon.png">
</head>
<style>
        /* General reset and styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        

        body {
    font-family: 'Arial', sans-serif;
    background-color: #f4f7fc;
    display: flex;
    flex-direction: column; /* Stack header and form */
    align-items: center;
    justify-content: flex-start;
    min-height: 100vh;
    color: #333;
    padding: 20px 0; /* Add some space on top and bottom */
}
        header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 105%; /* Full width */
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

.container {
    width: 100%;
    max-width: 900px; /* Align with header */
    background-color: #fff;
    padding: 30px;
    border-radius: 20px;
    box-shadow: 0 6px 24px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    margin-top: 20px; /* Add spacing below header */
}

/* Center text */
h2 {
    text-align: center;
    color: #357c3c;
    font-size: 2rem;
    margin-bottom: 20px;
    font-weight: bold;
}

/* Form layout improvements */
.form-section {
    width: 100%;
    margin-bottom: 20px;
    padding: 20px;
    background: linear-gradient(135deg, #d2d9dc, #e9f1f5);
    border-radius: 15px;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: column;
}

/* Form groups should be aligned properly */
.form-group {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    width: 100%;
    margin-bottom: 15px;
}

/* Input fields */
.form-group input,
.form-group select {
    width: 48%;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid #ccc;
    font-size: 16px;
    background-color: #f8f9fb;
    transition: all 0.3s ease;
}

        .form-section:hover {
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }

        .form-section h3 {
            color: #357c3c;
            font-size: 1.6rem;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            color: #555;
            margin-bottom: 8px;
            font-size: 1.1rem;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border-radius: 10px;
            border: 1px solid #ccc;
            font-size: 16px;
            outline: none;
            background-color: #f8f9fb;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            border-color: #357c3c;
            background-color: #e9f1f5;
            box-shadow: 0 0 8px rgba(53, 124, 60, 0.2);
        }

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
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .btn-add:hover {
            background-color: #2a6330;
            transform: scale(1.05);
        }

        .btn-add:focus {
            outline: none;
        }

        .form-section .form-group {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
        }

        .form-section .form-group input {
            width: 48%;
        }

        .form-section .form-group button {
            width: 48%;
            margin-top: 10px;
        }
        .form-group select {
            width: 48%; /* Same width as input fields */
            padding: 12px 15px;
            border-radius: 10px;
            border: 1px solid #ccc;
            font-size: 16px;
            outline: none;
            background-color: #f8f9fb;
            transition: all 0.3s ease;
        }

        /* Focus effect for input/select fields */
        .form-group select:focus {
            border-color: #357c3c;
            background-color: #e9f1f5;
            box-shadow: 0 0 8px rgba(53, 124, 60, 0.2);
        }

        @media (max-width: 768px) {
    header {
        flex-direction: column;
        align-items: center;
        text-align: center;
        padding: 30px 20px;
        height: auto;
    }

    .header-left {
        margin-bottom: 15px;
    }

    .header-right {
        align-items: center;
    }

    .header-info {
        flex-direction: column;
        gap: 10px;
    }

    .button-group {
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }

    .logout,
    .back-btn {
        width: 100%;
        max-width: 200px;
    }
}

@media (max-width: 480px) {
    header {
        padding: 20px 15px;
    }

    .logout,
    .back-btn {
        font-size: 14px;
        padding: 8px 16px;
    }

    .header-info {
        gap: 8px;
    }
}

    </style>
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
    <h2>Add Fields</h2>

    <!-- Department Section -->
    <div class="form-section">
    <h3>Department</h3>
    <form method="post">
        <input type="hidden" name="type" value="department">
        <div class="form-group">
            <label for="department-name">Department Name</label>
            <input type="text" name="name" id="department-name" placeholder="Enter Department Name" required>
        </div>
        <div class="form-group">
            <label for="department-code">Short Code</label>
            <input type="text" name="code" id="department-code" placeholder="Enter Department Code" required>
        </div>
        <button type="submit" class="btn-add">Add Department</button>
    </form>
</div>

<!-- Branch Section -->
<div class="form-section">
    <h3>Branch</h3>
    <form method="post">
        <input type="hidden" name="type" value="branch">
        <div class="form-group">
            <label for="branch-name">Branch Name</label>
            <input type="text" name="name" id="branch-name" placeholder="Enter Branch Name" required>
        </div>
        <div class="form-group">
            <label for="branch-code">Short Code</label>
            <input type="text" name="code" id="branch-code" placeholder="Enter Branch Code" required>
        </div>
        <button type="submit" class="btn-add">Add Branch</button>
    </form>
</div>

<!-- Address Section -->
<div class="form-section">
    <h3>Addresses</h3>
    <form method="post">
        <input type="hidden" name="type" value="address">
        <div class="form-group">
            <label for="address-name">Address Name</label>
            <input type="text" name="name" id="address-name" placeholder="Enter Address Name" required>
        </div>
        <div class="form-group">
            <label for="address-code">Short Code</label>
            <input type="text" name="code" id="address-code" placeholder="Enter Address Code" required>
        </div>
        <button type="submit" class="btn-add">Add Address</button>
    </form>
</div>

<!-- Signatory Section -->
<div class="form-section">
    <h3>Signatory</h3>
    <form method="post">
        <input type="hidden" name="type" value="signatory">
        <input type="hidden" id="user_id" name="user_id">
        <!-- Name Dropdown -->
        <div class="form-group">
            <label for="signatory-name">Name</label>
            <select id="signatory-name" name="name" class="select2" required>
                <option value="">Search & Select Name</option>
            </select>
        </div>

        <!-- Email Dropdown -->
        <div class="form-group">
            <label for="signatory-email">Email</label>
            <select id="signatory-email" name="code" class="select2" required>
                <option value="">Search & Select Email</option>
            </select>
        </div>

        <button type="submit" class="btn-add">Add Signatory</button>
    </form>
</div>

<!-- Include jQuery & Select2 -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">

<script>
$(document).ready(function () {
    // Initialize Select2
    $('.select2').select2({ placeholder: "Search & Select", allowClear: true });

    // Flags to prevent loop
    let updatingName = false;
    let updatingEmail = false;

    // Fetch users from the database (ensure that you have the necessary data)
    $.getJSON('get_users.php', function(users) {
        users.forEach(user => {
            // Populate the select dropdowns for name and email
            $('#signatory-name').append(new Option(user.name, user.name));
            $('#signatory-email').append(new Option(user.email, user.email));
        });
    });

    // Handle when the Name is changed
    $('#signatory-name').change(function() {
        if (updatingEmail) return;  // If updating email, return early

        var selectedName = $(this).val();
        if (selectedName) {
            updatingName = true;  // Set the flag to true to avoid triggering email update

            $.getJSON('get_users.php', function(users) {
                var user = users.find(u => u.name === selectedName);
                if (user) {
                    // Set the email without triggering the change event
                    $('#signatory-email').val(user.email).trigger('change');
                    
                    // Set the user_id in the hidden input field
                    $('#user_id').val(user.user_id);
                    console.log('user_id from Name Change:', $('#user_id').val());  // Log the user_id

                    updatingName = false;  // Reset the flag
                }
            });
        }
    });

    // Handle when the Email is changed
    $('#signatory-email').change(function() {
        if (updatingName) return;  // If updating name, return early

        var selectedEmail = $(this).val();
        if (selectedEmail) {
            updatingEmail = true;  // Set the flag to true to avoid triggering name update

            $.getJSON('get_users.php', function(users) {
                var user = users.find(u => u.email === selectedEmail);
                if (user) {
                    // Set the name without triggering the change event
                    $('#signatory-name').val(user.name).trigger('change');

                    // Set the user_id in the hidden input field
                    $('#user_id').val(user.user_id);

                    updatingEmail = false;  // Reset the flag
                }
            });
        }
    });

    // Trigger the user_id update when either name or email changes
    $('#signatory-name, #signatory-email').on('change', function() {
        var selectedName = $('#signatory-name').val();
        var selectedEmail = $('#signatory-email').val();
        
        // If both name and email are selected, update user_id
        if (selectedName || selectedEmail) {
            $.getJSON('get_users.php', function(users) {
                var user = users.find(u => u.name === selectedName || u.email === selectedEmail);
                if (user) {
                    // Update user_id
                    $('#user_id').val(user.user_id);
                }
            });
        }
    });
});


</script>


</body>
</html>
