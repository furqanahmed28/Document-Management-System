<?php
session_start(); 
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}
$login_email = $_SESSION['login_email'];
$login_id = $_SESSION['login_id'];
$user_type = $_SESSION['user_type'];
function check_rights($pdo, $refno, $login_id) {
    // Prepare the SQL query to get max rights
    $rights_query = "SELECT 
    CASE 
        WHEN Status != 1 THEN 0  -- If Status is not 1, set max_rights to 0
        ELSE max_rights         -- Else, use the calculated max_rights
    END AS max_rights
FROM (
    SELECT 
        CASE 
            WHEN s.user_id = :login_id AND d.Signatory = s.id THEN 5  -- Highest priority: Signatory
            --WHEN u.id IS NOT NULL AND u.type = 'Admin' THEN 3        -- Second priority: Admin
            ELSE COALESCE(MAX(ur.rights), 0)                     -- Lowest priority: UserRights table
        END AS max_rights, 
        (SELECT d.Status FROM DocDetails d WHERE d.RefNo = :refno) AS Status
    FROM users u
    LEFT JOIN userrights ur ON ur.user_id = u.id AND ur.refno = :refno
    LEFT JOIN signatory s ON s.user_id = u.id
    LEFT JOIN DocDetails d ON d.Signatory = s.id AND d.RefNo = :refno
    WHERE u.id = :login_id
) AS subquery


        ";
    $stmt = $pdo->prepare($rights_query);
    
    // Bind the parameters
    $stmt->bindParam(':refno', $refno, PDO::PARAM_STR);
    $stmt->bindParam(':login_id', $login_id, PDO::PARAM_INT);
    $stmt->execute();
    $rights = $stmt->fetch(PDO::FETCH_ASSOC);
    return $rights['max_rights'] ?? null; // Return null if no rights found
}


date_default_timezone_set('Asia/Karachi');
$dbFile = 'dmsdb.db';
$pdo = new PDO("sqlite:$dbFile");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);



// Fetch data for dynamic dropdowns
$departmentsQuery = "SELECT DeptName, DeptCode FROM Departments where DeptCode in (select dept from usersdept where user_id=$login_id)";
$branchesQuery = "SELECT City, BCode FROM Branch where BCode in (select branch from usersbranch where user_id=$login_id)";
$addressesQuery = "SELECT name FROM Addresses";
$signatoriesQuery = "SELECT s.ID, u.email, u.name 
    FROM Signatory s
    JOIN Users u ON s.user_id = u.ID
    --JOIN UsersDept d ON d.user_id = s.user_id
    --JOIN UsersDept d_login ON d_login.dept = d.dept AND d_login.user_id = $login_id
    --JOIN UsersBranch b ON b.user_id = s.user_id
    --JOIN UsersBranch b_login ON b_login.branch = b.branch AND b_login.user_id = $login_id
    ";
$departments = $pdo->query($departmentsQuery)->fetchAll(PDO::FETCH_ASSOC);
$branches = $pdo->query($branchesQuery)->fetchAll(PDO::FETCH_ASSOC);
$addresses = $pdo->query($addressesQuery)->fetchAll(PDO::FETCH_ASSOC);
$signatories = $pdo->query($signatoriesQuery)->fetchAll(PDO::FETCH_ASSOC);

// Fetch users where app = 1 and type != 'admin'
function fetchUsers($pdo, $login_id) {
    $stmt = $pdo->prepare("
        SELECT u.ID, u.name, u.email,  bb.bcode as branch
        FROM Users u
        JOIN UsersDept d ON d.user_id = u.ID
        JOIN UsersDept d_login ON d_login.dept = d.dept AND d_login.user_id = :login_id
        JOIN UsersBranch b ON b.user_id = u.ID
        join Branch bb on b.branch = bb.bcode
        --JOIN UsersBranch b_login ON b_login.branch = b.branch AND b_login.user_id = :login_id
        WHERE u.app = 1 AND u.Type != 'admin'
    ");
    $stmt->bindParam(':login_id', $login_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Call the function and pass $login_id
$users = fetchUsers($pdo, $login_id);
function fetchAssignedUsers($pdo, $refno) {
    $stmt = $pdo->prepare("
        SELECT ur.user_id, ur.rights, u.name, u.email 
        FROM UserRights ur
        JOIN Users u ON ur.user_id = u.ID
        WHERE ur.refno = :refno
        and rights in (1,3)
    ");
    $stmt->execute([':refno' => $refno]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function assign_user_rights($pdo, $refno, $users) {
    try {
        $pdo->beginTransaction(); // Start transaction

        // Fetch existing users assigned to this reference
        $stmt = $pdo->prepare("SELECT user_id FROM UserRights WHERE refno = :refno");
        $stmt->execute([':refno' => $refno]);
        $existingUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $newUserIDs = array_column($users, 'user_id');

        // Delete users who were removed
        $stmt = $pdo->prepare("DELETE FROM UserRights WHERE refno = :refno AND rights NOT IN (4, 5) AND user_id NOT IN (" . implode(',', array_map('intval', $newUserIDs)) . ")");
        $stmt->execute([':refno' => $refno]);

        // Prepare statements for insert and update
        $insertStmt = $pdo->prepare("INSERT INTO UserRights (refno, user_id, rights) VALUES (:refno, :user_id, :rights)");
        $updateStmt = $pdo->prepare("UPDATE UserRights SET rights = :rights WHERE refno = :refno AND user_id = :user_id");

        foreach ($users as $user) {
            if (in_array($user['user_id'], $existingUsers)) {
                // Update rights if user already exists
                $updateStmt->execute([
                    ':refno'   => $refno,
                    ':user_id' => $user['user_id'],
                    ':rights'  => $user['rights']
                ]);
            } else {
                // Insert new user rights
                $insertStmt->execute([
                    ':refno'   => $refno,
                    ':user_id' => $user['user_id'],
                    ':rights'  => $user['rights']
                ]);
            }
        }

        $pdo->commit(); // Commit transaction
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack(); // Rollback on error
        error_log("Error assigning user rights: " . $e->getMessage());
        return false;
    }
}

// Fetch users when loading the edit page

if (isset($_GET['refno'])) {
    $refNo = $_GET['refno'];
    
    try {
        // Fetch the document details using RefNo
        $docQuery = "SELECT * FROM DocDetails WHERE RefNo = ?";
        $docStmt = $pdo->prepare($docQuery);
        $docStmt->execute([$refNo]);
        $docDetails = $docStmt->fetch(PDO::FETCH_ASSOC);

        if (!$docDetails) {
            // Document not found
            echo "<script>alert('Document not found.');</script>";
            if( $user_type !== 'Admin'){
                header("Location: index.php");
            }
            else{
                header("Location: admin.php");
            }

            exit;
        }
        // Check rights for the user
        $max_rights = check_rights($pdo, $refNo, $login_id);
        if ($max_rights <  3) {
            echo "<script>
            alert('Not Allowed.');
            // After the alert, check the user type and redirect
            window.onload = function() {
                var userType = '" . $user_type . "';
                if (userType !== 'Admin') {
                    window.location.href = 'index.php'; // Redirect to index.php for non-admin
                } else {
                    window.location.href = 'admin.php'; // Redirect to admin.php for admin
                }
            };
          </script>";
          exit;
        }

        $filesQuery = "SELECT FileName FROM DocFiles WHERE RefNo = ?";
        $filesStmt = $pdo->prepare($filesQuery);
        $filesStmt->execute([$refNo]);
        $files = $filesStmt->fetchAll(PDO::FETCH_ASSOC);
        $assignedUsers = fetchAssignedUsers($pdo, $refNo);

    } catch (PDOException $e) {
        echo "<script>alert('Error: " . $e->getMessage() . "');</script>";
        exit;
    }

} else {
    // Handle the case where 'refno' is not provided
    echo "<script>alert('No ID provided.');</script>";
    exit;
}


// Update document details
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve form data and assign to variables
    $department = $_POST["department"];
    $branch = $_POST["sendto"];
    $address = $_POST["address"];
    $signatory = $_POST["signatory"];
    $dateInput = $_POST['date'];  // e.g., "2025-01-12T23:40"
    $date = str_replace("T", " ", $dateInput);  // Convert to "2025-01-12 23:40"
    $subject = $_POST["subject"];
    $status = $_POST["status_hidden"];  // Hidden status field
    $comment = $_POST["comment"];
    
    // Check the dispatchdetails (Email or Post) and set the value of 'details'
    $dispatchdetails = $_POST["dispatchdetails"];
    if ($dispatchdetails === 'Post') {
        $details = "Post";  // If Post is selected, set details as Post
    } elseif (isset($_POST["email"]) && !empty($_POST["email"])) {
        $details = $_POST["email"];  // If email is provided, set details as the email
    } else {
        $details = null;  // If no dispatch details provided, set it to null or handle error
    }

    // Assuming you're fetching the current RefNo from the database to update the correct record
    // $refNo can either come from a GET request or be passed into the form
    $refNo = $docDetails['RefNo'];  // Assuming this is already set from the database fetch

    // Prepare the SQL update query
    $updateDocQuery = "UPDATE DocDetails SET Department = ?, SendTo = ?, Addresse = ?, Signatory = ?, Date = ?, Subject = ?, Comment = ?, Status = ?, UpdatedBy = ?, Details = ? WHERE RefNo = ?";
    $updateStmt = $pdo->prepare($updateDocQuery);

    // Execute the SQL statement with the form data
    $updateStmt->execute([
        $department,
        $branch,
        $address,
        $signatory,
        $date,
        $subject,
        $comment,
        $status,
        $login_id,  // The user ID of the person performing the update
        $details,  // The dispatch details (either Post or email)
        $refNo     // The RefNo of the record to update
    ]);
    if (!empty($_POST['assigned_users'])) {
        $users = json_decode($_POST['assigned_users'], true);

        // Validate JSON structure
        if (!is_array($users)) {
            throw new Exception("Invalid user data format.");
        }

        assign_user_rights($pdo, $refNo, $users);
    }
    // Directory path for uploads
// Directory path for uploads
$uploadDir = "uploads/" . $refNo . "/";

// Handle file uploads
foreach (['file1', 'file2', 'file3'] as $key => $fileInputName) {
    // Check if a new file is uploaded
    if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] === UPLOAD_ERR_OK) {
        // Get the old file name from the hidden input
        $existingFile = $_POST["existing_$fileInputName"] ?? null;

        // Delete the old file if it exists
        if ($existingFile && file_exists($uploadDir . $existingFile)) {
            unlink($uploadDir . $existingFile);

            // Remove the old file entry from the database
            $deleteFileQuery = "DELETE FROM DocFiles WHERE RefNo = ? AND FileName = ?";
            $deleteStmt = $pdo->prepare($deleteFileQuery);
            $deleteStmt->execute([$refNo, $existingFile]);
        }

        // Upload the new file
        $tmpName = $_FILES[$fileInputName]['tmp_name'];
        $fileName = basename($_FILES[$fileInputName]['name']);
        $uploadPath = $uploadDir . $fileName;

        // Create the directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Check if the file already exists and rename it if necessary
        $originalFileName = $fileName;
        $fileCounter = 1;
        
        while (file_exists($uploadPath)) {
            // If file exists, append the counter to the filename (e.g., file1, file2, etc.)
            $fileName = pathinfo($originalFileName, PATHINFO_FILENAME) . '-' . $fileCounter . '.' . pathinfo($originalFileName, PATHINFO_EXTENSION);
            $uploadPath = $uploadDir . $fileName;
            $fileCounter++;
        }

        // Move the uploaded file to the correct path
        if (move_uploaded_file($tmpName, $uploadPath)) {
            // Insert the new file into the database
            $insertFileQuery = "INSERT INTO DocFiles (RefNo, FileName) VALUES (?, ?)";
            $insertStmt = $pdo->prepare($insertFileQuery);
            $insertStmt->execute([$refNo, $fileName]);
        }
    }
}



    echo "<script>alert('Document updated successfully');</script>";
    if( $user_type !== 'Admin'){
        header("Location: index.php");
    }
    else{
        header("Location: admin.php");
    }
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Document</title>
    <link rel="icon" type="image/x-icon" href="favicon.png">
</head>
<style>
/* ====== General Reset ====== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Roboto', sans-serif;
    background-color: #f4f7fb;
    display: flex;
    flex-direction: column;
    align-items: center;
    min-height: 100vh;
    color: #343a40;
    padding: 20px;
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

/* ====== Form Container ====== */
.form-container {
    background: #ffffff;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    width: 100%;
    max-width: 800px;
    transition: all 0.3s ease-in-out;
    margin-top: 20px;
}

.form-container:hover {
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
    transform: scale(1.02);
}

h1 {
    text-align: center;
    font-size: 30px;
    color: #4CAF50;
    margin-bottom: 20px;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    font-family: 'Poppins', sans-serif;
}

/* ====== Form Styling ====== */
form {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

label {
    font-size: 16px;
    color: #495057;
    font-weight: 500;
    letter-spacing: 0.5px;
}

select,
input,
textarea {
    padding: 12px 15px;
    border: 2px solid #ced4da;
    border-radius: 8px;
    font-size: 16px;
    background-color: #f8f9fa;
    transition: border 0.3s ease, box-shadow 0.3s ease;
    width: 100%;
}

select:focus,
input:focus,
textarea:focus {
    border-color: #4CAF50;
    box-shadow: 0 0 8px rgba(76, 175, 80, 0.3);
}

/* ====== Button Styling ====== */
button.submit-btn {
    background: linear-gradient(135deg, #4CAF50, #388e3c);
    color: #fff;
    padding: 14px 22px;
    border: none;
    border-radius: 50px;
    font-size: 18px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 5px 12px rgba(76, 175, 80, 0.3);
    letter-spacing: 1px;
    font-weight: 600;
    width: 100%;
    max-width: 250px;
    align-self: center;
}

button.submit-btn:hover {
    background: linear-gradient(135deg, #388e3c, #2c6e28);
    transform: translateY(-3px);
}

/* ====== File Upload ====== */
.file-upload-wrapper {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.file-upload-label {
    padding: 12px 18px;
    background-color: #f8f9fa;
    border: 2px solid #ced4da;
    border-radius: 8px;
    font-size: 16px;
    text-align: center;
    cursor: pointer;
    transition: background-color 0.3s ease, border 0.3s ease;
}

.file-upload-label:hover {
    background-color: #f1f3f5;
    border-color: #4CAF50;
}

.file-name {
    font-size: 14px;
    color: #6c757d;
}

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            padding-top: 60px;
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 30px;
            border-radius: 12px;
            width: 80%;
            max-width: 600px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease-in-out;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }

        .modal-header h2 {
            font-size: 24px;
            color: #4CAF50;
            font-weight: 600;
        }

        .close-btn {
            font-size: 28px;
            color: #aaa;
            cursor: pointer;
        }

        .close-btn:hover {
            color: #333;
        }

        .modal-body {
            padding-top: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 2px solid #ced4da;
            font-size: 16px;
            outline: none;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            border-color: #4CAF50;
        }

        .add-user-btn {
            width: 100%;
            padding: 12px;
            background-color: #4CAF50;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 10px;
            transition: background-color 0.3s ease;
        }

        .add-user-btn:hover {
            background-color: #388e3c;
        }

        /* User List Styles */
        .user-list {
            list-style-type: none;
            padding: 0;
            margin-top: 20px;
        }

        .user-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border: 2px solid #f0f0f0;
            border-radius: 8px;
            margin-bottom: 10px;
            background-color: #f9f9f9;
            transition: background-color 0.3s ease;
        }

        .user-list li:hover {
            background-color: #e8f5e9;
        }
        .user-actions {
    display: flex;
    align-items: center;
    gap: 10px; /* Space between dropdown and button */
}

.user-actions select,
.user-actions button {
    background-color: #007bff;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    transition: background-color 0.3s ease;
}

.user-actions button {
    background-color: #dc3545; /* Red color for delete button */
}

.user-actions select:hover {
    background-color: #0056b3;
}

.user-actions button:hover {
    background-color: #b52b3a;
}

/* ====== Keyframes ====== */
@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
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
<div class="form-container">
    <h1>Edit Document</h1>
    <form method="post" enctype="multipart/form-data">
        <!-- RefNo Field (Readonly) -->
        <div class="form-group">
            <label for="refno">RefNo</label>
            <input type="text" id="refno" name="refno" value="<?= htmlspecialchars($docDetails['RefNo']) ?>" readonly>
        </div>

<!-- Readonly Department Field -->
<div class="form-group">
    <label for="department" class="required">Department</label>
    <input type="text" id="departmentname" name="departmentname" 
        value="<?php 
            foreach ($departments as $department) {
                if ($department['DeptCode'] == $docDetails['Department']) {
                    echo htmlspecialchars($department['DeptName']);
                }
            }
        ?>" readonly>
    <input type="hidden" name="department" 
        value="<?= isset($docDetails['Department']) ? htmlspecialchars($docDetails['Department']) : '' ?>">
</div>

<!-- Readonly Branch Field -->
<div class="form-group">
    <label for="sendto" class="required">Branch</label>
    <input type="text" id="sendtoname" name="sendtoname" 
        value="<?php 
            foreach ($branches as $branch) {
                if ($branch['BCode'] == $docDetails['SendTo']) {
                    echo htmlspecialchars($branch['City']);
                }
            }
        ?>" readonly>
    <input type="hidden" name="sendto" 
        value="<?= isset($docDetails['SendTo']) ? htmlspecialchars($docDetails['SendTo']) : '' ?>">
</div>

<!-- Include Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet">

<!-- Address Dropdown with Search -->
<div class="form-group">
    <label for="address" class="required">Address</label>
    <select id="address" name="address" class="select2" required>
        <option value="">Search & Select Address</option>
        <?php foreach ($addresses as $address): ?>
            <option value="<?= htmlspecialchars($address['name']) ?>" <?= $address['name'] == $docDetails['Addresse'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($address['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<!-- Include jQuery & Select2 JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>

<script>
    $(document).ready(function() {
        $('#address').select2({
            placeholder: "Search & Select Address",
            allowClear: true
        });
    });
</script>


        <!-- Other fields -->
        <div class="form-group">
            <label for="signatory" class="required">Signatory</label>
            <select id="signatory" name="signatory" required>
                <?php foreach ($signatories as $signatory): ?>
                    <option value="<?= htmlspecialchars($signatory['ID']) ?>" 
                        <?= $signatory['ID'] == $docDetails['Signatory'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($signatory['email']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
    <label for="status" class="required">Status</label>
    <select id="status" name="status" <?= ($docDetails['Status'] == 1 && $max_rights == 5) ? '' : 'disabled' ?> required>
        <option value="1" <?= $docDetails['Status'] == 1 ? 'selected' : '' ?>>Draft</option>
        <option value="2" <?= $docDetails['Status'] == 2 ? 'selected' : '' ?>>Signed</option>
    </select>
</div>

<!-- Hidden Input to Store Status -->
<input type="hidden" id="hidden_status" name="status_hidden" value="<?= htmlspecialchars($docDetails['Status']) ?>">

<script>
    document.addEventListener("DOMContentLoaded", function () {
        let statusSelect = document.getElementById("status");
        let hiddenStatus = document.getElementById("hidden_status");

        if (!statusSelect.disabled) { // Only attach event if it's editable
            statusSelect.addEventListener("change", function() {
                hiddenStatus.value = this.value;
            });
        }
    });
</script>




        <div class="form-group">
    <label for="date" class="required">Date</label>
    <input type="datetime-local" id="date" name="date" 
        value="<?= htmlspecialchars(date('Y-m-d\TH:i', strtotime($docDetails['Date']))) ?>" required>
</div>

<div class="form-group">
    <label for="subject" class="required">Subject</label>
    <textarea id="subject" name="subject" rows="3" required><?= htmlspecialchars($docDetails['Subject']) ?></textarea>
</div>

<div class="form-group">
    <label for="comment" class="required">Comments</label>
    <textarea id="comment" name="comment" required><?= htmlspecialchars($docDetails['Comment']) ?></textarea>
</div>
<div class="form-group">
    <label for="dispatchdetails" class="required">Dispatch Details</label>
    <select id="dispatchdetails" name="dispatchdetails" required onchange="toggleEmailField()">
        <option value="" disabled <?php echo ($docDetails['Details'] == '' ? 'selected' : ''); ?>>Please select</option>
        <option value="Email" <?php echo ($docDetails['Details'] != '' && filter_var($docDetails['Details'], FILTER_VALIDATE_EMAIL) ? 'selected' : ''); ?>>Email</option>
        <option value="Post" <?php echo ($docDetails['Details'] == 'Post' ? 'selected' : ''); ?>>Post</option>
    </select>
</div>

<!-- Email input field, initially hidden -->
<div class="form-group" id="emailField" style="display:<?= ($docDetails['Details'] == 'Email' && filter_var($docDetails['Details'], FILTER_VALIDATE_EMAIL)) ? 'block' : 'none'; ?>;">
    <label for="email">Enter Email</label>
    <input type="email" id="email" name="email" placeholder="Enter your email" value="<?= htmlspecialchars($docDetails['Details']) ?>" />
</div>

<script>
    // JavaScript to toggle the email input field based on selection and make email required
    function toggleEmailField() {
        var dispatchDetails = document.getElementById('dispatchdetails').value;
        var emailField = document.getElementById('emailField');
        var emailInput = document.getElementById('email'); // Get the email input element

        // Show email field if 'Email' is selected, otherwise hide it and reset the email input
        if (dispatchDetails === 'Email') {
            emailField.style.display = 'block';
            emailInput.setAttribute('required', 'true'); // Make email input required
        } else {
            emailField.style.display = 'none';
            emailInput.value = ""; // Reset the email field to an empty string
            emailInput.removeAttribute('required'); // Remove the required attribute
        }
    }

    // Trigger toggleEmailField on page load in case the email option was selected previously
    window.onload = function() {
        toggleEmailField();
    }
</script>

        <!-- File uploads -->
<!-- File uploads -->
        <div class="form-group">
            <label for="file1" class="file-upload-label">Main document</label>
            <input type="file" id="file1" name="file1">
            <?php if (!empty($files[0]['FileName'])): ?>
                <p>Existing file: 
                    <a href="uploads/<?= $docDetails['RefNo'] ?>/<?= $files[0]['FileName'] ?>" download>
                        <?= $files[0]['FileName'] ?>
                    </a>
                </p>
                <input type="hidden" name="existing_file1" value="<?= $files[0]['FileName'] ?>">
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="file2" class="file-upload-label">Supporting document</label>
            <input type="file" id="file2" name="file2">
            <?php if (!empty($files[1]['FileName'])): ?>
                <p>Existing file: 
                    <a href="uploads/<?= $docDetails['RefNo'] ?>/<?= $files[1]['FileName'] ?>" download>
                        <?= $files[1]['FileName'] ?>
                    </a>
                </p>
                <input type="hidden" name="existing_file2" value="<?= $files[1]['FileName'] ?>">
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="file3" class="file-upload-label">Supporting document</label>
            <input type="file" id="file3" name="file3">
            <?php if (!empty($files[2]['FileName'])): ?>
                <p>Existing file: 
                    <a href="uploads/<?= $docDetails['RefNo'] ?>/<?= $files[2]['FileName'] ?>" download>
                        <?= $files[2]['FileName'] ?>
                    </a>
                </p>
                <input type="hidden" name="existing_file3" value="<?= $files[2]['FileName'] ?>">
            <?php endif; ?>
        </div>
        <div class="form-group">
                <button type="button" class="submit-btn" onclick="openModal()">Assign Users</button>
     </div>
        <div class="form-group">
            <button type="submit" class="submit-btn">Update</button>
        </div>

    </form>
</div>
<div id="userModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Assign Users</h2>
            <span class="close-btn" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body">
            <!-- Dropdown for selecting user -->
            <div class="form-group">
                <label for="userName">Select User</label>
                <select id="userName" class="select2">
                    <option value="">Select a user</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= htmlspecialchars($user['ID']) ?>" data-email="<?= htmlspecialchars($user['email']) ?>" data-branch="<?= htmlspecialchars($user['branch']) ?>">
        <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['email']) ?>) - <?= htmlspecialchars($user['branch']) ?>
    </option>
                    <?php endforeach; ?>
                </select>

                <button type="button" class="add-user-btn" onclick="addUser()">Assign User</button>
            </div>

            <!-- User list with roles -->
            <ul id="userList" class="user-list">
                <?php foreach ($assignedUsers as $user): ?>
                    <li data-user-id="<?= htmlspecialchars($user['user_id']) ?>">
                        <span> (<?= htmlspecialchars($user['email']) ?>)</span>
                        <input type="hidden" name="user_ids[]" value="<?= htmlspecialchars($user['user_id']) ?>">
                        <div class="user-actions">
                            <select onchange="updateRole(this)">
                                <option value="view" <?= $user['rights'] == 1 ? 'selected' : '' ?>>View</option>
                                <option value="edit" <?= $user['rights'] == 3 ? 'selected' : '' ?>>Edit</option>
                            </select>
                            <button onclick="deleteUser(this)">Delete</button>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

        </div>
    </div>
</div>
<!-- Include Select2 CSS -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />

<!-- Include jQuery (required for Select2) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Include Select2 JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>


<script>
        document.getElementById('file1').addEventListener('change', function (e) {
            const fileName = e.target.files[0] ? e.target.files[0].name : 'No file selected';
            document.getElementById('file-name1').textContent = fileName;
        });

        document.getElementById('file2').addEventListener('change', function (e) {
            const fileName = e.target.files[0] ? e.target.files[0].name : 'No file selected';
            document.getElementById('file-name2').textContent = fileName;
        });

        document.getElementById('file3').addEventListener('change', function (e) {
            const fileName = e.target.files[0] ? e.target.files[0].name : 'No file selected';
            document.getElementById('file-name3').textContent = fileName;
        });
    </script>

    <script>
// Open Modal
function openModal() {
    document.getElementById('userModal').style.display = "block";
}

// Close Modal
function closeModal() {
    document.getElementById('userModal').style.display = "none";
}

// Add User to List
// Initialize Select2 for the user selection dropdown
$(document).ready(function() {
    $('.select2').select2({
        placeholder: "Search & Select User",
        allowClear: true
    });
});

// Add User to List
function addUser() {
    const userDropdown = document.getElementById('userName');
    const userID = userDropdown.value; // Get selected user's ID
    const userEmail = userDropdown.options[userDropdown.selectedIndex].getAttribute('data-email');

    if (userID) {
        const userList = document.getElementById('userList');
        const listItem = document.createElement('li');
        listItem.setAttribute('data-user-id', userID); // Store user ID in the list item
        
        listItem.innerHTML = `
            <span>${userEmail}</span> <!-- Show email instead of ID -->
            <input type="hidden" name="user_ids[]" value="${userID}"> <!-- Hidden field for ID -->
            <div class="user-actions">
                <select onchange="updateRole(this)">
                    <option value="view">View</option>
                    <option value="edit">Edit</option>
                </select>
                <button onclick="deleteUser(this)">Delete</button>
            </div>
        `;
        userList.appendChild(listItem);
        
        // Clear selection
        userDropdown.value = '';
        $('#userName').trigger('change');
    }
}


// Other functions like updateRole and deleteUser remain the same
function getAssignedUsers() {
    let users = [];
    document.querySelectorAll("#userList li").forEach(li => {
        let userID = li.querySelector("input[name='user_ids[]']").value; // Get hidden user ID
        let role = li.querySelector("select").value;
        let rights = role === "view" ? 1 : 3;

        users.push({
            user_id: userID, // Store user ID, not email
            rights: rights
        });
    });

    return JSON.stringify(users);
}


// Modify form submission
document.querySelector("form").addEventListener("submit", function(event) {
    let hiddenInput = document.createElement("input");
    hiddenInput.type = "hidden";
    hiddenInput.name = "assigned_users";
    hiddenInput.value = getAssignedUsers();
    this.appendChild(hiddenInput);
});


// Update User Role
function updateRole(select) {
    const role = select.value;
    const userName = select.parentElement.parentElement.querySelector('span').textContent;
    console.log(`${userName} has been assigned ${role} role.`); // You can update role in backend or UI
}

// Delete User
function deleteUser(button) {
    if (confirm("Are you sure you want to delete this user?")) {
        button.parentElement.parentElement.remove(); // Remove the user from the list
    }
}

// Trigger when department or branch fields are changed
        function generateRefNo() {
            const department = document.getElementById('department').value;
            const branch = document.getElementById('sendto').value;

            if (department && branch) {
                const xhr = new XMLHttpRequest();
                xhr.open('GET', `generate_refno.php?action=create&department=${department}&branch=${branch}`, true);
                xhr.onload = function () {
                    if (xhr.status === 200) {
                        document.getElementById('refno').value = xhr.responseText;
                    }
                };
                xhr.send();
            }
        }

        // Call this function on page load to generate RefNo for the default selected values
        window.onload = function() {
            generateRefNo();
        };
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

    </script>
</body>
<script>/*
        function generateRefNo() {
            const department = document.getElementById('department').value;
            const branch = document.getElementById('sendto').value;

            if (department && branch) {
                const xhr = new XMLHttpRequest();
                xhr.open('GET', `generate_refno.php?action=edit&department=${department}&branch=${branch}`, true);
                xhr.onload = function () {
                    if (xhr.status === 200) {
                        document.getElementById('refno').value = xhr.responseText;
                    }
                };
                xhr.send();
            }
        }

        // Call this function on page load to generate RefNo for the default selected values
        window.onload = function() {
            generateRefNo();
        };*/
</script>

</html>
