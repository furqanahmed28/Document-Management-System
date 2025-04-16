<?php
// Include your database connection code here (same as in the original PHP script)

$pdo = new PDO("sqlite:dmsdb.db");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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

function edit_docnum($pdo, $department, $branch) {

        $year = date("Y");
        $departmentShortCode = strtoupper($department);
        $branchShortCode = strtoupper($branch);
        $refnoPattern = "S$branchShortCode$departmentShortCode-$year-%";
    
        // Fetch the last document number for the specific department and branch combination
        $getLatestDocNumber = "SELECT MAX(CAST(SUBSTR(refno, 10) AS INTEGER)) AS max_value 
                               FROM docdetails
                               WHERE refno LIKE :refno_pattern";
        $stmtGetDocNo = $pdo->prepare($getLatestDocNumber);
        
        // Use LIKE with department and branch codes as pattern
        
        $stmtGetDocNo->bindParam(':refno_pattern', $refnoPattern, PDO::PARAM_STR);
        $stmtGetDocNo->execute();
    
        $lastRefNo = $stmtGetDocNo->fetch(PDO::FETCH_ASSOC)['max_value'];
        if($change){
            $nextDocNumber = 1;  // Default to 1 if no previous document exists
            if ($lastRefNo) {
                $nextDocNumber = (int)$lastRefNo + 1;
            }
        } else{
            $nextDocNumber = (int)$currentRefNo;
        }
    
        $newDocNumber = str_pad($nextDocNumber, 4, "0", STR_PAD_LEFT);  // Ensure 4 digits (e.g., 0001)
        $refNo = "S$branchShortCode$departmentShortCode-$year-$newDocNumber";  // E.g., 'SIL-2025-0001'
        
        return $refNo;
    }
    

if (isset($_GET['action']) && isset($_GET['department']) && isset($_GET['branch'])) {
    $action = $_GET['action'];
    $department = $_GET['department'];
    $branch = $_GET['branch'];

    if ($action === 'create') {
        // Call create_docnum
        echo create_docnum($pdo, $department, $branch);
    } elseif ($action === 'edit') {
        // Call edit_docnum
        echo edit_docnum($pdo, $department, $branch);
    } else {
        echo "Invalid action.";
    }
} else {
    echo "Required parameters are missing.";
}

?>
