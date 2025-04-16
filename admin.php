<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['user_type'] !== 'Admin') {
    header("Location: login.php");
    exit;
}
$login_email = $_SESSION['login_email'];
$login_id = $_SESSION['login_id'];
// SQLite database file and PDO connection
$dbFile = 'dmsdb.db';
$pdo = new PDO("sqlite:$dbFile");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Pagination logic
$itemsPerPage = 10;  // Number of items per page
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;  // Get current page from URL, default to 1

// Calculate the starting index for the current page
$startIndex = ($currentPage - 1) * $itemsPerPage;

// Fetch data from the database for pagination
$query = "SELECT d.ID, 
       d.RefNo, 
       COALESCE(dd.deptname, d.Department) AS Department,  -- Use d.Department if dd.deptname is NULL
       COALESCE(b.city, d.SendTo) AS SendTo,  -- Use 'N/A' if city is NULL
       d.Addresse, 
       COALESCE(u.name, d.Signatory) AS `Signatory`,  -- Use 'Unknown' if user name is NULL
       d.Date, 
       d.Subject, 
       d.Comment, 
       coalesce(ss.status, d.status) as `Status`
        FROM DocDetails d
        LEFT JOIN signatory s ON d.signatory = s.ID  -- Left join with signatory table
        LEFT JOIN users u ON u.ID = s.user_id  -- Join users table
        LEFT JOIN Departments dd ON d.Department = dd.deptcode  -- Left join with Departments table
        LEFT JOIN branch b ON d.sendto = b.bcode
        LEFT JOIN status ss ON d.status = ss.id  -- Left join with branch table
        order by date desc
        LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($query);
$stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $startIndex, PDO::PARAM_INT);
$stmt->execute();

// Fetch the total number of records to calculate total pages
$totalItemsQuery = "SELECT COUNT(*) AS total FROM DocDetails";
$totalStmt = $pdo->query($totalItemsQuery);
$totalItems = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalItems / $itemsPerPage);  // Calculate total pages
if ($totalPages <1){
    $totalPages = 1;}
// Fetch files for each document
$data = [];
while ($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $refNo = $item['RefNo'];

    // Query to fetch the file names associated with the given RefNo
    $fileQuery = "SELECT FileName FROM DocFiles WHERE RefNo = :refNo";
    $fileStmt = $pdo->prepare($fileQuery);
    $fileStmt->bindValue(':refNo', $refNo, PDO::PARAM_STR);
    $fileStmt->execute();

    // Fetch file names and store them in the array
    $files = [];
    while ($file = $fileStmt->fetch(PDO::FETCH_ASSOC)) {
        // Store only the file name for display
        $files[] = $file['FileName'];
    }
    
    // Add file names to the item for display in the table
    $item['files'] = $files;
    
    // Generate file paths for downloading
    $filePaths = [];
    foreach ($files as $file) {
        // Assuming the files are stored in 'uploads/{refNo}/' directory
        $filePaths[] = 'uploads/' . $refNo . '/' . $file;  // Relative URL for download
    }

    // Add the file paths for downloading to the item
    $item['filePaths'] = $filePaths;
    
    // Add the item (document details) to the data array
    $data[] = $item;
}




// Ensure the current page is within valid range
$currentPage = max(1, min($currentPage, $totalPages));

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin</title>
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

.logout {
    width: 100px; /* Ensures both buttons are the same width */
    text-align: center;
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

    .logout\ {
        width: 100%;
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

    .logout {
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

    </div>
    </header>

    <!-- Navigation Section -->
    <!-- Navigation Section -->
<div class="navigation">
    <div class="nav-section">
        <button class="add-button" onclick="addItem()">Add</button>
        <button class="add-button" onclick="addFields()">Add Fields</button>
        <button class="add-button" onclick="users()">Users</button>
        <button class="add-button" onclick="dispatch()">Dispatch</button>
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
                <th>S/N</th>
                <th>Ref# <input type="text" class="filter-input" id="filter-ref" placeholder="" style="width: 100px;"></th>
                <th>Department <input type="text" class="filter-input" id="filter-department" placeholder="" style="width: 100px;"></th>
                <th>Send To <input type="text" class="filter-input" id="filter-sendto" placeholder="" style="width: 100px;"></th>
                <th>Status <input type="text" class="filter-input" id="filter-status" placeholder="" style="width: 100px;"></th>
                <th>Address <input type="text" class="filter-input" id="filter-address" placeholder="" style="width: 100px;"></th>
                <th>Signatory <input type="text" class="filter-input" id="filter-signatory" placeholder="" style="width: 100px;"></th>
                <th>Date <input type="text" class="filter-input" id="filter-date" placeholder="" style="width: 100px;"></th>
                <th>Subject <input type="text" class="filter-input" id="filter-subject" placeholder="" style="width: 100px;"></th>
                <th>Comment <input type="text" class="filter-input" id="filter-comment" placeholder="" style="width: 100px;"></th>
                <th>Edit</th>
                <th>Attachment</th>
            </tr>
        </thead>
        <tbody id="data-table-body">
            <?php foreach ($data as $index => $item): ?>
                <tr class="data-row">
                    <td class="sn"><?= $startIndex + $index + 1 ?></td>
                    <td class="ref"><?= htmlspecialchars($item['RefNo']) ?></td>
                    <td class="department"><?= htmlspecialchars($item['Department']) ?></td>
                    <td class="sendto"><?= htmlspecialchars($item['SendTo']) ?></td>
                    <td class="status"><?= htmlspecialchars($item['Status']) ?></td>
                    <td class="address"><?= htmlspecialchars($item['Addresse']) ?></td>
                    <td class="signatory"><?= htmlspecialchars($item['Signatory']) ?></td>
                    <td class="date"><?= htmlspecialchars($item['Date']) ?></td>
                    <td class="subject"><?= htmlspecialchars($item['Subject']) ?></td>
                    <td class="comment">
    <div class="comment-text">
        <?php
        // Truncate to 2 words for display
        $comment = htmlspecialchars($item['Comment']);
        $words = explode(' ', $comment);
        $displayText = implode(' ', array_slice($words, 0, 2)); // Get the first 2 words
        echo $displayText;
        ?>
    </div>
    <a href="javascript:void(0);" class="view-comment"><i class="fas fa-plus"></i></a> <!-- View toggle for comment -->
    <div class="expanded-comment">
        <p><?= htmlspecialchars($item['Comment']) ?></p> <!-- Full comment -->
    </div>
</td>


                    <td>
                        <a href="edit.php?refno=<?= urlencode($item['RefNo']) ?>" class="edit-icon">
                            <i class="fas fa-edit" style="color:green;"></i>
                        </a>
                    </td>

                    <td class="attachment">
    <?php if (!empty($item['files'])): ?>
        <a href="javascript:void(0);" class="view-attachments"><i class="fas fa-plus"></i></a> <!-- View toggle for attachments -->
        <div class="expanded-attachments" style="display: none;">
            <?php foreach ($item['files'] as $index => $file): ?>
                <a href="<?= htmlspecialchars($item['filePaths'][$index]) ?>" download="<?= htmlspecialchars($file) ?>" class="download-link">
                    <i class="fas fa-download" style="color:blue;"></i> <?= htmlspecialchars($file) ?><br>
                </a><br>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        No file
    <?php endif; ?>
</td>


                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="pagination">
        <button onclick="window.location.href='admin.php?page=<?= max(1, $currentPage - 1) ?>'" <?=$currentPage <= 1 ? 'disabled' : '' ?>>Previous</button>
        <span>Page <?= $currentPage ?> of <?= $totalPages ?></span>
        <button onclick="window.location.href='admin.php?page=<?= min($totalPages, $currentPage + 1) ?>'" <?=$currentPage >= $totalPages ? 'disabled' : '' ?>>Next</button>
    </div>
</div>



    <!-- Pagination -->

</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function() {
        // Toggle Comment View
        $('.view-comment').click(function() {
            var row = $(this).closest('tr');
            var commentSection = row.find('.expanded-comment');
            commentSection.slideToggle(300); // Smooth slide animation

            // Toggle between + and - icons
            var icon = $(this).find('i');
            icon.toggleClass('fa-plus fa-minus'); // Smartly toggle between + and -
        });

        // Toggle Attachment View
        $('.view-attachments').click(function() {
            var row = $(this).closest('tr');
            var attachmentSection = row.find('.expanded-attachments');
            attachmentSection.slideToggle(300); // Smooth slide animation

            // Toggle between + and - icons
            var icon = $(this).find('i');
            icon.toggleClass('fa-plus fa-minus'); // Smartly toggle between + and -
        });
    });
</script>

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


        function addItem() {
            window.location.href = 'add.php';
        }
        function users() {
            window.location.href = 'users';
        }

        function addFields() {
            window.location.href = 'addfields.php';
        }
        function dispatch() {
            window.location.href = 'dispatch.php';
        }

        function searchTable() {
            const query = document.getElementById('search').value.toLowerCase();
            const table = document.getElementById('datatable');
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                let match = false;
                cells.forEach(cell => {
                    if (cell.textContent.toLowerCase().includes(query)) {
                        match = true;
                    }
                });
                row.style.display = match ? '' : 'none';
            });
        }

        // Function to filter data based on column inputs
        const filterInputs = document.querySelectorAll('.filter-input');
        filterInputs.forEach(input => {
            input.addEventListener('input', () => {
                filterTable();
            });
        });

        function filterTable() {
            const rows = document.querySelectorAll('#datatable tbody tr');
            rows.forEach(row => {
                let match = true;
                filterInputs.forEach(input => {
                    const column = input.id.replace('filter-', '');
                    const cell = row.querySelector(`.${column}`);
                    if (cell && !cell.textContent.toLowerCase().includes(input.value.toLowerCase())) {
                        match = false;
                    }
                });
                row.style.display = match ? '' : 'none';
            });
        }
    </script>

</body>

</html>


<!--php 10 rows-->