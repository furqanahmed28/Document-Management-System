<?php
// Assuming a PDO connection to your SQLite database is established
$pdo = new PDO("sqlite:dmsdb.db"); // Ensure the correct path to your database

if (isset($_GET['user_id'])) {
    $userId = $_GET['user_id'];

    // Validate user ID
    if (filter_var($userId, FILTER_VALIDATE_INT)) {
        // Fetch the user data for editing
        $stmt = $pdo->prepare("SELECT u.id, u.name, u.email, u.Type, b.City as branch, d.DeptName as dept, u.password 
                               FROM Users u
                               LEFT JOIN UsersBranch ub ON u.id = ub.user_id 
                               LEFT JOIN branch b ON ub.branch = b.BCode 
                               LEFT JOIN UsersDept ud ON u.id = ud.user_id 
                               LEFT JOIN Departments d ON ud.dept = d.DeptCode
                               WHERE u.id = :user_id");

        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Return the user data as JSON
        if ($user) {
            echo json_encode($user);
        } else {
            echo json_encode(null); // In case the user doesn't exist
        }
    } else {
        echo json_encode(null); // Invalid user_id
    }
}
?>
