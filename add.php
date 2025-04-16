<?php
session_start(); 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

//Load Composer's autoloader
require 'vendor/autoload.php';
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

$login_email = $_SESSION['login_email'];
$login_id = $_SESSION['login_id'];
date_default_timezone_set('Asia/Karachi'); // Set timezone to UTC+5 (Asia/Karachi)

// Get today's date in YYYY-MM-DD format for input[type="date"]
$todayDate = date("Y-m-d H:i");  // Correct format for SQLite
 // Formats the current date and time correctly for the input

$dbFile = 'dmsdb.db';
$pdo = new PDO("sqlite:$dbFile");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Fetch data from the database
$departmentsQuery = "SELECT DeptName, DeptCode FROM Departments where DeptCode in (select dept from usersdept where user_id=$login_id)";
$departmentsStmt = $pdo->query($departmentsQuery);
$departments = $departmentsStmt->fetchAll(PDO::FETCH_ASSOC);

$branchesQuery = "SELECT City, BCode FROM Branch where BCode in (select branch from usersbranch where user_id=$login_id)";
$branchesStmt = $pdo->query($branchesQuery);
$branches = $branchesStmt->fetchAll(PDO::FETCH_ASSOC);

$addressesQuery = "SELECT name FROM Addresses";
$addressesStmt = $pdo->query($addressesQuery);
$addresses = $addressesStmt->fetchAll(PDO::FETCH_ASSOC);

$signatoriesQuery = "SELECT s.ID, u.email, u.name 
    FROM Signatory s
    JOIN Users u ON s.user_id = u.ID
    --JOIN UsersDept d ON d.user_id = s.user_id
    --JOIN UsersDept d_login ON d_login.dept = d.dept AND d_login.user_id = $login_id
    --JOIN UsersBranch b ON b.user_id = s.user_id
    --JOIN UsersBranch b_login ON b_login.branch = b.branch AND b_login.user_id = $login_id
    ";
$signatoriesStmt = $pdo->query($signatoriesQuery);
$signatories = $signatoriesStmt->fetchAll(PDO::FETCH_ASSOC);


function create_docnum($pdo, $department, $branch) {
    $year = date("Y");
    $departmentShortCode = strtoupper($department);
    $branchShortCode = strtoupper($branch);

    // Fetch the last document number for the specific department and branch combination
    $getLatestDocNumber = "SELECT MAX(CAST(SUBSTR(refno, 10) AS INTEGER)) AS max_value 
                           FROM docdetails
                           WHERE refno LIKE :refno_pattern";
    $stmtGetDocNo = $pdo->prepare($getLatestDocNumber);
    
    // Use LIKE with department and branch codes as pattern
    $refnoPattern = "S$branchShortCode$departmentShortCode-$year-%";
    $stmtGetDocNo->bindParam(':refno_pattern', $refnoPattern, PDO::PARAM_STR);
    $stmtGetDocNo->execute();

    $lastRefNo = $stmtGetDocNo->fetch(PDO::FETCH_ASSOC)['max_value'];

    $nextDocNumber = 1;  // Default to 1 if no previous document exists
    if ($lastRefNo) {
        $nextDocNumber = (int)$lastRefNo + 1;
    }

    $newDocNumber = str_pad($nextDocNumber, 4, "0", STR_PAD_LEFT);  // Ensure 4 digits (e.g., 0001)
    $refNo = "S$branchShortCode$departmentShortCode-$year-$newDocNumber";  // E.g., 'SIL-2025-0001'
    
    return $refNo;
}


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
        WHERE u.app = 1 AND u.Type != 'Admin'
    ");
    $stmt->bindParam(':login_id', $login_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Call the function and pass $login_id
$users = fetchUsers($pdo, $login_id);



function store_docdetails($pdo, $doc_details) {
    // Prepare the SQL statement with placeholders
    global $login_id;
    $sql = "INSERT INTO DocDetails (RefNo, Department, SendTo, Addresse, Signatory, Date, Subject, Comment, Status, UpdatedBy, Details) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1,? , ?)";
    //$sql2= "Insert into UserRights(refno, user_id,rights) values (?, ?, 5)";
    $sql3= "Insert into UserRights(refno, user_id,rights) values (?, ?, 4)";
    // Prepare the statement
    $stmt = $pdo->prepare($sql);
    //$stmt2 = $pdo->prepare($sql2);
    $stmt3 = $pdo->prepare($sql3);
    // Execute the statement with the passed parameters
    if ($stmt->execute([
        $doc_details['RefNo'],
        $doc_details['department'],
        $doc_details['sendto'],
        $doc_details['address'],
        $doc_details['signatory'],
        $doc_details['date'],
        $doc_details['subject'],
        $doc_details['comment'],
        $login_id,
        $doc_details['details']
    ])) {
        //$stmt2->execute([$doc_details['RefNo'],$doc_details['signatory']]);
        $stmt3->execute([$doc_details['RefNo'],(int)$login_id]);
        // Success message
        echo "<script>alert('Document details stored successfully');</script>";

        $refno = $doc_details['RefNo']; // Get the generated RefNo

        // Directory path for uploads
        $uploadDir = "uploads/" . $refno . "/";

        // Create folder if it doesn't exist
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                echo "Failed to create folders...";
                return false;
            }
        }

        function uploadFile($fileInputName, $uploadDir, $refno, $pdo) {
            if (isset($_FILES[$fileInputName])) {
                $file = $_FILES[$fileInputName];
        
                // Check if the file is uploaded successfully
                if ($file['error'] === UPLOAD_ERR_OK) {
                    $tmpName = $file['tmp_name'];
                    $fileName = basename($file['name']);
                    $uploadPath = $uploadDir . $fileName;
        
                    // Check if the file already exists in the destination directory
                    $originalFileName = $fileName;
                    $fileCounter = 1;
        
                    while (file_exists($uploadPath)) {
                        // If file exists, append the counter to the filename (e.g., file1, file2, etc.)
                        $fileName = pathinfo($originalFileName, PATHINFO_FILENAME) . '-' . $fileCounter . '.' . pathinfo($originalFileName, PATHINFO_EXTENSION);
                        $uploadPath = $uploadDir . $fileName;
                        $fileCounter++;
                    }
        
                    // Move the uploaded file to the new directory with the updated filename
                    if (move_uploaded_file($tmpName, $uploadPath)) {
                        echo "File uploaded successfully: " . $fileName . "<br>";
        
                        // Insert the file into DocFiles table with the new name
                        $sql = "INSERT INTO DocFiles (RefNo, FileName) VALUES (?, ?)";
                        $stmt = $pdo->prepare($sql);
        
                        if ($stmt->execute([$refno, $fileName])) {
                            echo "File information inserted into DocFiles table: " . $fileName . "<br>";
                        } else {
                            echo "Error inserting file information into database: " . $fileName . "<br>";
                        }
                    } else {
                        echo "Error uploading file: " . $fileName . "<br>";
                    }
                } else {
                    echo "Error with file upload: " . $file['error'] . "<br>";
                }
            }
        }
        

        // Call the uploadFile function for each file input
        uploadFile('file1', $uploadDir, $refno, $pdo);  // Main document
        uploadFile('file2', $uploadDir, $refno, $pdo);  // Supporting document 1
        uploadFile('file3', $uploadDir, $refno, $pdo);  // Supporting document 2

        
        echo "Files uploaded successfully to folder: " . $refno . "/files";
        return true;
    } else {
        // Error message
        echo "<script>alert('Error storing document details');</script>";
        return false;
    }
}
function assign_user_rights($pdo, $refno, $users) {
    try {
        $stmt = $pdo->prepare("INSERT INTO UserRights (refno, user_id, rights) VALUES (:refno, :user_id, :rights)");
        
        foreach ($users as $user) {
            $stmt->execute([
                ':refno'   => $refno,
                ':user_id' => $user['user_id'],
                ':rights'  => $user['rights']
            ]);
        }
        return true;
    } catch (PDOException $e) {
        error_log("Error assigning user rights: " . $e->getMessage());
        return false;
    }
}

function generate_email_template($refNo, $department, $branch, $address, $signatory, $date, $subject, $comment) {
    // HTML Template for the email
    $emailContent = "
    <html>
    <head>
        <style>
            body {
                font-family: Arial, sans-serif;
                color: #333;
                background-color: #f4f4f4;
                margin: 0;
                padding: 0;
            }
            .container {
                max-width: 600px;
                margin: 20px auto;
                padding: 20px;
                background-color: #ffffff;
                border-radius: 8px;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            }
            h2 {
                color: #0066cc;
                font-size: 24px;
            }
            p {
                font-size: 16px;
                line-height: 1.5;
                margin-bottom: 15px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            th, td {
                padding: 10px;
                border: 1px solid #ddd;
                text-align: left;
            }
            th {
                background-color: #f2f2f2;
                font-weight: bold;
            }
            .footer {
                margin-top: 20px;
                font-size: 14px;
                text-align: center;
                color: #777;
            }
            .highlight {
                color: #0066cc;
                font-weight: bold;
            }
        </style>
    </head>
    <body>

        <div class='container'>
            <h2>Synergy Computers (Pvt) Ltd</h2>
            <h3>Document Details: $subject (Ref#: $refNo)</h3>

            <p>Dear Team,</p>
            <p>The document with reference number <strong>$refNo</strong> has been created. Please review the details below:</p>

            <table>
                <tr>
                    <th>Ref#</th>
                    <td class='highlight'>$refNo</td>
                </tr>
                <tr>
                    <th>Department</th>
                    <td>$department</td>
                </tr>
                <tr>
                    <th>Send To (Branch)</th>
                    <td>$branch</td>
                </tr>
                <tr>
                    <th>Address</th>
                    <td>$address</td>
                </tr>
                <tr>
                    <th>Signatory</th>
                    <td>$signatory</td>
                </tr>
                <tr>
                    <th>Date Created</th>
                    <td>$date</td>
                </tr>
                <tr>
                    <th>Subject</th>
                    <td>$subject</td>
                </tr>
                <tr>
                    <th>Comment</th>
                    <td>$comment</td>
                </tr>
            </table>

            <p>Please verify the above details. Once confirmed, the document will proceed to the next stage for review and signing by <strong>$signatory</strong>.</p>

            <p>If you have any questions or require further details, please feel free to contact us.</p>

            <div class='footer'>
                <p>Best regards,<br><strong>Synergy Computers (Pvt) Ltd</strong><br>Document Management Team</p>
            </div>
        </div>

    </body>
    </html>
    ";

    return $emailContent;
}

// Function to send email using PHPMailer (SMTP)
function send_email($toEmail, $subject, $emailContent) {
    $mail = new PHPMailer(true);
    
    try {
        // SMTP Configuration
        $mail->isSMTP();                                            // Set mailer to use SMTP
        $mail->Host       = 'smtp.gmail.com';                    // Set SMTP server (replace with your SMTP server)
        $mail->SMTPAuth   = true;                                     // Enable SMTP authentication
        $mail->Username   = 'furqan.ahmed665@gmail.com';              // SMTP username (your email)
        $mail->Password   = 'rpax dxam dnyb qgces';                    // SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;           // Enable TLS encryption
        $mail->Port       = 587;                                      // TCP port to connect to
        
        // Sender's Email Address (From)
        $mail->setFrom('furqan.ahmed665@gmail.com', 'Synergy Computers (Pvt) Ltd');
        
        // Recipient's Email Address (To)
        $mail->addAddress($toEmail);

        // Subject and body
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $emailContent;

        // Send email
        $mail->send();
        echo 'Message has been sent successfully';
    } catch (Exception $e) {
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}
// Step 2: Insert data into DocDetails and DocFiles
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve form data and assign to variables
    $department = $_POST["department"];  // Department (DeptCode)
    $branch = $_POST["sendto"];          // Branch (BCode)
    $address = $_POST["address"];        // Address name
    $signatory = $_POST["signatory"];    // Signatory email
    $dateInput = $_POST['date']; // e.g., "2025-01-12T23:40"
    $date = str_replace("T", " ", $dateInput); // Convert to "2025-01-12 23:40"             // Date
    $subject = $_POST["subject"];        // Subject
    $comment = $_POST["comment"];
    if (isset($_POST["dispatchdetails"]) && $_POST["dispatchdetails"] === 'Post') {
        $dispatchdetails = "Post";  // If 'Post' is selected
    } elseif (isset($_POST["dispatchdetails"]) && $_POST["dispatchdetails"] === 'Email' && isset($_POST["email"]) && !empty($_POST["email"])) {
        $dispatchdetails = $_POST["email"];  // If 'Email' is selected, use the email entered
    } else {
        // Handle the case where no dispatch details are provided
        $dispatchdetails = null;  // You can adjust this to handle errors more gracefully
    }
    

    // Generate the RefNo (you should have a function like create_docnum)
    $refNo = create_docnum($pdo, $department, $branch);  // Assuming you have a function to generate the RefNo

    // Prepare the document details array
    $doc_details = [
        "RefNo"     => $refNo,        // The generated RefNo (e.g., SFK-0001-2025)
        "department" => $department,  // Department (DeptCode)
        "sendto"     => $branch,     // Branch (BCode)
        "address"    => $address,    // Address name
        "signatory"  => $signatory,  // Signatory email
        "date"       => $date,       // Date
        "subject"    => $subject,    // Subject
        "comment"    => $comment,
        "details" => $dispatchdetails    // Comment
    ];
    $comp = store_docdetails($pdo, $doc_details);
    // Store assigned user rights
    if ($comp === true && isset($_POST['assigned_users'])) {
        $users = json_decode($_POST['assigned_users'], true);
        assign_user_rights($pdo, $refNo, $users);
        $userEmails = [];
        foreach ($users as $userId) {
            $email = get_user_email($pdo, $userId); // Retrieve email for each user
            if ($email) {
                $userEmails[] = $email;  // Add valid email to the array
            }
        }
        $signatoryEmail = get_user_email($pdo, $signatory);
        if ($signatoryEmail) {
            $userEmails[] = $signatoryEmail;  // Add signatory email to the array
        }
        /*
        $userEmails = array_unique($userEmails);
        $emailContent = generate_email_template($refNo, $department, $branch, $address, $signatory, $date, $subject, $comment);
        foreach ($userEmails as $email) {
            send_email($email, "Document Notification: $subject (Ref#: $refNo)", $emailContent);
        }
            */
    }
    if ($comp === true) {
        // After document details are stored, check session for user type and redirect
        if (isset($_SESSION['user_type'])) {
            if ($_SESSION['user_type'] === 'Admin') {
                // Redirect Admin to admin.php
                echo "<meta http-equiv='refresh' content='0; url=admin.php'>";
                exit;  // Stop further execution after redirect
            } elseif ($_SESSION['user_type'] === 'User') {
                // Redirect User to index.php
                echo "<meta http-equiv='refresh' content='0; url=index.php'>";
                exit;  // Stop further execution after redirect
            }
        }
    }
}
function get_user_email($pdo, $userId) {
    $query = "SELECT email FROM users WHERE id = :userId";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':userId' => $userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['email'] ?? null;  // Return email or null if not found
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modern Request Form</title>
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

/* ====== Header Styles ====== */
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
<!-- Request Form -->
<div class="form-container">
    <h1>Request Form</h1>
    <form method="post" enctype="multipart/form-data">
    <!-- Dynamic Department Field -->
    <div class="form-group">
        <label for="department" class="required">Department</label>
        <select id="department" name="department" required onchange="generateRefNo()">
            <?php foreach ($departments as $department): ?>
                <option value="<?= htmlspecialchars($department['DeptCode']) ?>"><?= htmlspecialchars($department['DeptName']) ?> (<?= htmlspecialchars($department['DeptCode']) ?>)</option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Dynamic Branch Field -->
    <div class="form-group">
        <label for="sendto" class="required">Branch</label>
        <select id="sendto" name="sendto" required onchange="generateRefNo()">
            <?php foreach ($branches as $branch): ?>
                <option value="<?= htmlspecialchars($branch['BCode']) ?>"><?= htmlspecialchars($branch['City']) ?> (<?= htmlspecialchars($branch['BCode']) ?>)</option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- RefNo Field (Auto-generated) -->
    <div class="form-group">
        <label for="refno">RefNo (Auto-generated)</label>
        <input type="text" id="refno" name="refno" readonly>
    </div>


    <!-- Dynamic Address Field -->
    <div class="form-group">
        <label for="address" class="required">Address</label>
        <select id="address" name="address" required>
            <?php foreach ($addresses as $address): ?>
                <option value="<?= htmlspecialchars($address['name']) ?>"><?= htmlspecialchars($address['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Dynamic Signatory Field -->
    <div class="form-group">
        <label for="signatory" class="required">Signatory</label>
        <select id="signatory" name="signatory" required>
            <?php foreach ($signatories as $signatory): ?>
                <option value="<?= htmlspecialchars($signatory['ID']) ?>"><?= htmlspecialchars($signatory['email']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Other static fields -->
    <div class="form-group">
    <label for="datetime" class="required">Date</label>
    <input type="datetime-local" id="datetime" name="date" value="<?= htmlspecialchars($todayDate) ?>" required>
</div>


    <div class="form-group">
        <label for="subject" class="required">Subject</label>
        <textarea id="subject" name="subject" rows="3" required></textarea>
    </div>

    <div class="form-group">
        <label for="comment" class="required">Comments</label>
        <textarea id="comment" name="comment" required></textarea>
    </div>

    <div class="form-group">
    <label for="dispatchdetails" class="required">Dispatch Details</label>
    <select id="dispatchdetails" name="dispatchdetails" required onchange="toggleEmailField()">
        <option value="" disabled selected>Please select</option>
        <option value="Email">Email</option>
        <option value="Post">Post</option>
    </select>
</div>

<!-- Email input field, initially hidden -->
<div class="form-group" id="emailField" style="display:none;">
    <label for="email">Enter Email</label>
    <input type="email" id="email" name="email" placeholder="Enter your email" />
</div>


    <div class="form-group">
        <label for="file1" class="file-upload-label">Main document</label>
        <input type="file" id="file1" name="file1" required>
    </div>

    <div class="form-group">
        <label for="file2" class="file-upload-label">Supporting document</label>
        <input type="file" id="file2" name="file2">
    </div>

    <div class="form-group">
        <label for="file3" class="file-upload-label">Supporting document</label>
        <input type="file" id="file3" name="file3">
    </div>
    <div class="form-group">
                <button type="button" class="submit-btn" onclick="openModal()">Assign Users</button>
     </div>
    <div class="form-group">
        <button type="submit" class="submit-btn">Submit</button>
    </div>
</form>

</div>

<!-- Modal for User Management -->
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
            <ul id="userList" class="user-list"></ul>
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
function toggleEmailField() {
        var dispatchDetails = document.getElementById("dispatchdetails").value;
        var emailField = document.getElementById("emailField");

        // Show the email input field if "Email" is selected, hide otherwise
        if (dispatchDetails === "Email") {
            emailField.style.display = "block";
        } else {
            emailField.style.display = "none";
        }
    }
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
</html>
