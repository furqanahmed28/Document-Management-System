<?php
session_start();
$dbFile = 'dmsdb.db';
$pdo = new PDO("sqlite:$dbFile");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['user_type'] !== 'Admin') && ($_SESSION['user_type'] !== 'User')) {
    header("Location: login.php");
    exit;
}


$login_email = $_SESSION['login_email'];
$login_id = $_SESSION['login_id'];

$itemsPerPage = 10;  // Number of items per page
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;  // Get current page from URL, default to 1

// Calculate the starting index for the current page
$startIndex = ($currentPage - 1) * $itemsPerPage;


$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'signed';

$statusCondition = "AND d.Status = 2"; // Default to Signed
$selectFields = "d.RefNo, d.Addresse, u.email AS Signatory, d.Status, d.Details";

if ($statusFilter === 'dispatched') {
    $statusCondition = "AND d.Status = 3";
    $selectFields .= ", MAX(sa.ChangedAt) AS DispatchDate, dt.Courier, dt.TrackingId";
} elseif ($statusFilter === 'all') {
    $statusCondition = "AND d.Status IN (2,3)";
    $selectFields .= ", MAX(sa.ChangedAt) AS LastUpdateDate, dt.Courier, dt.TrackingId";
} else {
    $selectFields .= ", MAX(sa.ChangedAt) AS SignedDate";
}

$query = "
    SELECT 
        $selectFields
    FROM 
        DocDetails d
    JOIN 
        StatusAudit sa ON d.RefNo = sa.RefNo
    LEFT JOIN 
        DocTrack dt ON d.RefNo = dt.RefNo
    JOIN 
        Signatory s ON d.Signatory = s.ID
    JOIN 
        users u ON u.id = s.user_id
    LEFT JOIN 
        usersbranch ub ON ub.user_id = $login_id
    LEFT JOIN 
        usersdept ud ON ud.user_id = $login_id
    WHERE 
        sa.ChangedAt = (
            SELECT MAX(ChangedAt) 
            FROM StatusAudit 
            WHERE RefNo = d.RefNo
        )
    AND 
        (
            (SELECT type FROM users WHERE id = $login_id) = 'Admin'
            OR 
            (d.sendto = ub.branch AND d.department = ud.dept)
            Or
            (s.user_id =$login_id)
        )
    $statusCondition
    GROUP BY 
        d.RefNo, d.Addresse, u.email, d.Status
    ORDER BY 
        sa.ChangedAt DESC
        LIMIT :limit OFFSET :offset";


//echo $query;

$stmt = $pdo->prepare($query);
$stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $startIndex, PDO::PARAM_INT);
$stmt->execute();
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalItemsQuery = "select count(*) as total from
(SELECT 
        $selectFields
    FROM 
        DocDetails d
    JOIN 
        StatusAudit sa ON d.RefNo = sa.RefNo
    LEFT JOIN 
        DocTrack dt ON d.RefNo = dt.RefNo
    JOIN 
        Signatory s ON d.Signatory = s.ID
    JOIN 
        users u ON u.id = s.user_id
    LEFT JOIN 
        usersbranch ub ON ub.user_id = $login_id
    LEFT JOIN 
        usersdept ud ON ud.user_id = $login_id
    WHERE 
        sa.ChangedAt = (
            SELECT MAX(ChangedAt) 
            FROM StatusAudit 
            WHERE RefNo = d.RefNo
        )
    AND 
        (
            (SELECT type FROM users WHERE id = $login_id) = 'Admin'
            OR 
            (d.sendto = ub.branch AND d.department = ud.dept)
            Or
            (s.user_id =$login_id)
        )
    $statusCondition
    GROUP BY 
        d.RefNo, d.Addresse, u.email, d.Status
    ORDER BY 
        sa.ChangedAt DESC) as counts
";

$totalStmt = $pdo->query($totalItemsQuery);
$totalItems = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalItems / $itemsPerPage);  // Calculate total pages
if ($totalPages <1){
    $totalPages = 1;}



// Handle Dispatch Request via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dispatch_refno'])) {
    $refNo = $_POST['dispatch_refno'];

    // Start transaction to ensure atomicity
    try {
        $pdo->beginTransaction();

        // Check if the current status is 'Signed' (Status = 2), only then update to 'Dispatched' (Status = 3)
        $checkStatusQuery = "SELECT Status FROM DocDetails WHERE RefNo = :refNo";
        $statusStmt = $pdo->prepare($checkStatusQuery);
        $statusStmt->execute([':refNo' => $refNo]);
        $currentStatus = $statusStmt->fetchColumn();

        if ($currentStatus != 2) {
            echo json_encode(['success' => false, 'message' => 'Document is not in Signed status.']);
            exit;
        }

        // Insert into StatusAudit first

        // Update DocDetails to set Status to 3 (Dispatched)
        $updateQuery = "UPDATE DocDetails SET Status = 3, UpdatedBy = :updatedBy WHERE RefNo = :refNo AND Status = 2";
        $updateStmt = $pdo->prepare($updateQuery);
        $updateStmt->execute([
            ':updatedBy' => $login_id,
            ':refNo' => $refNo
        ]);

        // Commit the transaction if everything is successful
        $pdo->commit();

        echo json_encode(['success' => true]);

    }
        catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false, 
                'message' => 'An error occurred: ' . $e->getMessage()
            ]);
            exit;
        }
        

    exit;
}
$currentPage = max(1, min($currentPage, $totalPages));

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Status</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

    <link rel="icon" type="image/x-icon" href="favicon.png">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<style>
/* Reset & General Styles */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
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
/* Modal Styles */

#dispatch-modal {
    display: none; /* Hidden by default */
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5); /* Semi-transparent background */
    z-index: 1000;
    justify-content: center;
    align-items: center;
    overflow: auto;
}

.modal-content {
    background-color: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    max-width: 500px;
    width: 100%;
    margin: auto; /* Centers the content inside the modal */
    display: flex;
    flex-direction: column;
    align-items: center;
    animation: fadeIn 0.3s ease-in-out;
}

.modal-content h2 {
    font-size: 20px;
    color: #333;
    margin-bottom: 20px;
    text-align: center;
}

.modal-content label {
    font-size: 14px;
    color: #333;
    margin-bottom: 8px;
    display: block;
}

.modal-content input {
    padding: 12px;
    width: 100%;
    border: 1px solid #ccc;
    border-radius: 5px;
    font-size: 14px;
    background-color: #f9f9f9;
    margin-bottom: 15px;
    box-sizing: border-box;
    transition: border-color 0.3s ease;
}

.modal-content input:focus {
    border-color: #357c3c;
    outline: none;
}

.modal-content button {
    padding: 12px 25px;
    background-color: #357c3c;
    color: white;
    border: none;
    border-radius: 5px;
    font-size: 16px;
    cursor: pointer;
    transition: background-color 0.3s ease;
    margin-bottom: 15px;
}

.modal-content button:hover {
    background-color: #2c6b2e;
}

.modal-content .close-btn {
    padding: 10px 15px;
    background-color: #d2d9dc;
    color: #357c3c;
    border: none;
    cursor: pointer;
    border-radius: 5px;
    font-size: 14px;
    margin-top: 15px;
    transition: background-color 0.3s ease;
}

.modal-content .close-btn:hover {
    background-color: #357c3c;
    color: white;
}

/* Fade In Effect */
@keyframes fadeIn {
    0% {
        opacity: 0;
        transform: scale(0.8);
    }
    100% {
        opacity: 1;
        transform: scale(1);
    }
}

/* Overlay Background Styles */
#dispatch-modal {
    display: flex;
    justify-content: center;
    align-items: center;
}

/* Adjusting for smaller screens */
@media (max-width: 768px) {
    .modal-content {
        padding: 20px;
        width: 90%;
    }

    .modal-content h2 {
        font-size: 18px;
    }
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

/* Navigation */
.navigation {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 30px;
    background-color: white;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    border-radius: 5px;
    margin-top: 20px;
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

/* Tabs */
.tabs {
    display: flex;
    justify-content: center;
    margin: 20px 0;
}

.tabs a {
    padding: 12px 25px;
    margin: 0 10px;
    text-decoration: none;
    font-size: 16px;
    font-weight: 500;
    background: #ddd;
    color: #333;
    border-radius: 5px;
    transition: background 0.3s ease;
}

.tabs a.active {
    background: #357c3c;
    color: white;
}

.tabs a:hover {
    background: #2c6b2e;
    color: white;
}

/* Table Styling */
.datatable-container {
    padding: 25px;
    background-color: white;
    margin: 30px auto;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    max-width: 1200px;
    overflow-x: auto;
}

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
}

table th {
    background-color: #d2d9dc;
    font-weight: bold;
}

table td {
    background-color: #f9f9f9;
}

table td:hover {
    background-color: #f1f1f1;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

/* Dispatch Button */
.dispatch-btn {
    background-color: #ff9800;
    color: white;
    border: none;
    padding: 10px 15px;
    cursor: pointer;
    border-radius: 5px;
    font-size: 14px;
    transition: background-color 0.3s ease;
}

.dispatch-btn:hover {
    background-color: #e68900;
}

/* Filter Input Styles */
th div {
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

/* Adjust table header to fit the input fields below the label */
table th {
    padding: 15px;
    background-color: #d2d9dc;
    font-weight: bold;
    text-align: left;
}

table th div {
    margin-top: 5px;
}

table th,
table td {
    text-align: left;
}

table td {
    padding: 15px;
    background-color: #f9f9f9;
}

table td:hover {
    background-color: #f1f1f1;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

/* Responsive Styles */
/* Responsive Styles */
@media (max-width: 768px) {
    .navigation {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;

    }

    .search input {
        width: 100%;
        margin-top: 10px;
    }

    .tabs {
        flex-direction: column;
        align-items: center;
        padding: 0 20px;
    }

    .tabs a {
        width: 100%;
        max-width: 300%;
        text-align: center;
        margin-bottom: 10px;
    }

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
}

@media (max-width: 480px) {
    .navigation {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }

    .search input {
        width: 100%;
    }

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

    .datatable-container {
        margin: 10px;
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

    <!-- Navigation -->
    <div class="navigation">
        <!-- <button class="add-button" onclick="window.location.href='index.php'">Back</button> -->
        <div class="search">
            <input type="text" id="search" placeholder="Search..." oninput="searchTable()">
        </div>
    </div>

    <div class="tabs">
    <a href="?status=signed" class="<?= $statusFilter === 'signed' ? 'active' : '' ?>">Signed</a>
    <a href="?status=dispatched" class="<?= $statusFilter === 'dispatched' ? 'active' : '' ?>">Dispatched</a>
    <a href="?status=all" class="<?= $statusFilter === 'all' ? 'active' : '' ?>">All</a>
</div>

<!-- Datatable Section -->
<div class="datatable-container">
    <table>
    <thead>
        <tr>
            <th>Ref No<div><input type="text" class="filter-input" id="filter-refno" placeholder="Filter Ref No"></div></th>
            <th>Addresse<div><input type="text" class="filter-input" id="filter-addresse" placeholder="Filter Addresse"></div></th>
            <th>Signatory<div><input type="text" class="filter-input" id="filter-signatory" placeholder="Filter Signatory"></div></th>
            <th>Status<div><input type="text" class="filter-input" id="filter-status" placeholder="Filter Status"></div></th>

            <?php if ($statusFilter === 'signed'): ?>
                <th>Signed Date<div><input type="text" class="filter-input" id="filter-signed-date" placeholder="Filter Signed Date"></div></th>
                <th>Details<div><input type="text" class="filter-input" id="filter-details" placeholder="Filter Details"></div></th>
                <th>Action</th>

            <?php elseif ($statusFilter === 'dispatched'): ?>
                <th>Dispatch Date<div><input type="text" class="filter-input" id="filter-dispatch-date" placeholder="Filter Dispatch Date"></div></th>
                <th>Details<div><input type="text" class="filter-input" id="filter-details" placeholder="Filter Details"></div></th>
                <th>Courier<div><input type="text" class="filter-input" id="filter-courier" placeholder="Filter Courier"></div></th>
                <th>Tracking Id<div><input type="text" class="filter-input" id="filter-tracking-id" placeholder="Filter Tracking Id"></div></th>

            <?php else: ?> <!-- For "All" -->
                <th>Last Update Date<div><input type="text" class="filter-input" id="filter-last-update-date" placeholder="Filter Last Update Date"></div></th>
                <th>Details<div><input type="text" class="filter-input" id="filter-details" placeholder="Filter Details"></div></th>
                <th>Courier<div><input type="text" class="filter-input" id="filter-courier" placeholder="Filter Courier"></div></th>
                <th>Tracking Id<div><input type="text" class="filter-input" id="filter-tracking-id" placeholder="Filter Tracking Id"></div></th>
                <th>Action</th>
            <?php endif; ?>
        </tr>
    </thead>


    <tbody>
    <?php foreach ($documents as $doc): ?>
        <tr>
            <td><?= htmlspecialchars($doc['RefNo']) ?></td>
            <td><?= htmlspecialchars($doc['Addresse']) ?></td>
            <td><?= htmlspecialchars($doc['Signatory']) ?></td>
            <td><?= $doc['Status'] == 2 ? 'Signed' : 'Dispatched' ?></td>

            <?php if ($statusFilter === 'signed'): ?>
                <td><?= htmlspecialchars($doc['SignedDate']) ?></td>
                <td><?= htmlspecialchars($doc['Details']) ?></td>

                <td>
                    <?php if ($doc['Details'] != 'Post'): ?>
                        <button class="dispatch-btn" data-refno="<?= $doc['RefNo'] ?>">Dispatch</button>
                    <?php endif; ?>
                </td>
            <?php elseif ($statusFilter === 'dispatched'): ?>
                <td><?= htmlspecialchars($doc['DispatchDate']) ?></td>
                <td><?= htmlspecialchars($doc['Details']) ?></td>

                <td><?= htmlspecialchars($doc['Courier']) ?></td>
                <td><?= htmlspecialchars($doc['TrackingId']) ?></td>
            <?php else: ?>
                <td><?= htmlspecialchars($doc['LastUpdateDate']) ?></td>
                <td><?= htmlspecialchars($doc['Details']) ?></td>

                <td><?= htmlspecialchars($doc['Courier']) ?></td>
                <td><?= htmlspecialchars($doc['TrackingId']) ?></td>
                <td>
                    <?php if ($doc['Status'] == 2 && $doc['Details'] != 'Post'): ?>
                        <button class="dispatch-btn" data-refno="<?= $doc['RefNo'] ?>">Dispatch</button>
                    <?php endif; ?>
                </td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
</tbody>

    </table>
    <div class="pagination">
    <!-- Previous Button -->
    <button onclick="window.location.href='dispatch.php?page=<?= max(1, $currentPage - 1) ?>&status=<?= htmlspecialchars($statusFilter) ?>'" <?=$currentPage <= 1 ? 'disabled' : '' ?>>Previous</button>
    
    <span>Page <?= $currentPage ?> of <?= $totalPages ?></span>
    
    <!-- Next Button -->
    <button onclick="window.location.href='dispatch.php?page=<?= min($totalPages, $currentPage + 1) ?>&status=<?= htmlspecialchars($statusFilter) ?>'" <?=$currentPage >= $totalPages ? 'disabled' : '' ?>>Next</button>
</div>

</div>







<script>
document.addEventListener("DOMContentLoaded", function () {
    const filters = document.querySelectorAll(".filter-input");

    filters.forEach(filter => {
        filter.addEventListener("input", filterTable);
    });

    function filterTable() {
        let table = document.querySelector("table");
        let rows = table.querySelectorAll("tbody tr");

        rows.forEach(row => {
            let showRow = true;

            filters.forEach(filter => {
                let columnIndex = filter.closest("th").cellIndex;
                let filterValue = filter.value.toLowerCase();
                let cell = row.cells[columnIndex];

                if (cell && filterValue) {
                    let cellText = cell.textContent.toLowerCase();
                    if (!cellText.includes(filterValue)) {
                        showRow = false;
                    }
                }
            });

            row.style.display = showRow ? "" : "none";
        });
    }
});
</script>



    <script>
        // Dispatch Document via AJAX

        $(document).on("click", ".dispatch-btn", function() {
            let refNo = $(this).data("refno");
            let button = $(this);
            
            if (confirm("Are you sure you want to dispatch document "+refNo+"?")) {
                $.post("dispatch.php", { dispatch_refno: refNo }, function(response) {
                    let data = JSON.parse(response);
                    if (data.success) {
                        alert("Document dispatched successfully!");
                        button.closest("tr").find("td:nth-child(4)").text("Dispatched");
                        button.remove(); 
                    } else {
                        alert("Failed to dispatch the document.");
                    }
                });
            }
        });
   
        function logout() {
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
