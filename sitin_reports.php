<?php
session_start();
include 'conn.php'; // Database connection

// Import PhpSpreadsheet classes at the top level, outside any function or conditional block
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Default query
$query = "SELECT s.id_number, CONCAT(u.firstname, ' ', u.lastname) AS student_name, 
          s.lab, s.time_in, s.time_out, s.purpose, date(s.time_in) AS date,
          CASE WHEN s.time_out IS NOT NULL THEN 'completed' ELSE 'active' END AS status
          FROM sit_ins s 
          JOIN users u ON s.id_number = u.id_number 
          WHERE s.time_out IS NOT NULL";

// Filters
$lab_filter = isset($_GET['lab']) ? $_GET['lab'] : '';
$purpose_filter = isset($_GET['purpose']) ? $_GET['purpose'] : '';

// Apply filters if set
$params = [];
if (!empty($lab_filter)) {
    $query .= " AND s.lab = :lab";
    $params[':lab'] = $lab_filter;
}

if (!empty($purpose_filter)) {
    $query .= " AND s.purpose = :purpose";
    $params[':purpose'] = $purpose_filter;
}

$query .= " ORDER BY s.time_in DESC";

// Get distinct labs and purposes for filter dropdowns
$labs_stmt = $pdo->query("SELECT DISTINCT lab FROM sit_ins ORDER BY lab");
$labs = $labs_stmt->fetchAll(PDO::FETCH_COLUMN);

$purposes_stmt = $pdo->query("SELECT DISTINCT purpose FROM sit_ins ORDER BY purpose");
$purposes = $purposes_stmt->fetchAll(PDO::FETCH_COLUMN);

// Execute the filtered query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$sitin_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Define the header for all exports
$report_header = "University of Cebu College of Computer Studies Computer Laboratory Sit-in Monitoring System Report";

// Handle export requests
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    $filename = 'sitin_reports_' . date('Y-m-d');
    
    // Clone the query for export (potentially with different formatting)
    $export_stmt = $pdo->prepare($query);
    $export_stmt->execute($params);
    $export_data = $export_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    switch ($export_type) {
        case 'pdf':
            // Prepare the data in JavaScript format
            $rows_js = '';
            foreach ($export_data as $row) {
                $purpose = strlen($row['purpose']) > 25 ? substr($row['purpose'], 0, 23) . '...' : $row['purpose'];
                $formatted_date = date('M d, Y', strtotime($row['time_in'])); // Extract and format the date
                $rows_js .= json_encode([
                    $row['id_number'],
                    $row['student_name'],
                    $row['lab'],
                    date('h:i A', strtotime($row['time_in'])),
                    date('h:i A', strtotime($row['time_out'])),
                    $purpose,
                    $row['status'],
                    $formatted_date
                ]) . ",\n";
            }
        
            echo '<!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Exporting PDF...</title>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
            </head>
            <body>
            <script>
                window.onload = async function() {
                    const { jsPDF } = window.jspdf;
                    const doc = new jsPDF("p", "pt", "a4");
        
                    // Header
                    doc.setFontSize(14);
                    doc.text("University of Cebu", 300, 20, { align: "center" });
                    doc.setFontSize(14);
                    doc.text("College of Computer Studies", 300, 40, { align: "center" });
                    doc.setFontSize(12);
                    doc.text("Computer Laboratory Sit-in Monitoring System Report", 300, 60, { align: "center" });
                    doc.setFontSize(10);
                    doc.text("Generated on: ' . date('F d, Y') . '", 300, 80, { align: "center" });
        
                    // Filters (if any)
                    const filters = "' . 
                        (!empty($lab_filter) ? "Lab: " . addslashes($lab_filter) . " " : "") . 
                        (!empty($purpose_filter) ? "Purpose: " . addslashes($purpose_filter) : "") . '";

        
                    // Table with Status & Date
                    const headers = [["ID Number", "Student Name", "Lab", "Time In", "Time Out", "Purpose", "Status", "Date"]];
                    const rows = [
                        ' . $rows_js . '
                    ];
        
                    doc.autoTable({
                        head: headers,
                        body: rows,
                        startY: filters.trim() !== "" ? 130 : 100,
                        styles: { fontSize: 9 },
                        headStyles: { fillColor: [0, 57, 107] },
                    });
        
                    // Summary
                    doc.setFontSize(10);
                    doc.text("Total Records: ' . count($export_data) . '", 40, doc.lastAutoTable.finalY + 20);
        
                    // Save
                    doc.save("' . $filename . '.pdf");
                };
            </script>
            </body>
            </html>';
            exit;
        
        
            
        case 'csv':
            // Create CSV
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            
            $output = fopen('php://output', 'w');
            
            // Header row with institutional header
            fputcsv($output, [$report_header]);
            fputcsv($output, ['Generated on: ' . date('F d, Y')]);
            fputcsv($output, []); // Empty row for spacing
            
            // Column headers
            fputcsv($output, ['ID Number', 'Student Name', 'Lab', 'Time In', 'Time Out', 'Purpose', 'Status', 'Date']);
            
            // Data rows
            foreach ($export_data as $row) {
                $formatted_row = [
                    $row['id_number'],
                    $row['student_name'],
                    $row['lab'],
                    date('h:i A', strtotime($row['time_in'])),
                    date('h:i A', strtotime($row['time_out'])),
                    $row['purpose'],
                    $row['status'],
                    date('F d, Y', strtotime($row['date']))
                ];
                fputcsv($output, $formatted_row);
            }
            
            fclose($output);
            exit;
            
        case 'excel':
            // Set headers for Excel download
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
            header('Cache-Control: max-age=0');
            
            // Create Excel-compatible HTML
            echo "<!DOCTYPE html>";
            echo "<html>";
            echo "<head>";
            echo "<meta charset='UTF-8'>";
            echo "<style>";
            echo "body { font-family: Arial, sans-serif; }";
            echo "h1 { text-align: center; color: #333; }";
            echo "h2 { text-align: center; color: #666; }";
            echo "table { border-collapse: collapse; width: 100%; }";
            echo "th, td { border: 1px solid black; padding: 8px; }";
            echo "th { background-color: #f2f2f2; font-weight: bold; }";
            echo ".header { background-color: #4CAF50; color: white; font-weight: bold; }";
            echo "</style>";
            echo "</head>";
            echo "<body>";
            
            echo "<h1>" . $report_header . "</h1>";
            echo "<h2>Generated on: " . date('F d, Y') . "</h2>";
            
            // Filter information
            if (!empty($lab_filter) || !empty($purpose_filter)) {
                echo "<p><strong>Filters applied:</strong> ";
                if (!empty($lab_filter)) echo "Lab: $lab_filter ";
                if (!empty($purpose_filter)) echo "Purpose: $purpose_filter";
                echo "</p>";
            }
            
            echo "<table>";
            echo "<tr class='header'>";
            echo "<th>ID Number</th>";
            echo "<th>Student Name</th>";
            echo "<th>Lab</th>";
            echo "<th>Time In</th>";
            echo "<th>Time Out</th>";
            echo "<th>Purpose</th>";
            echo "<th>Status</th>";
            echo "<th>Date</th>";
            echo "</tr>";
            
            foreach ($export_data as $row) {
                echo "<tr>";
                echo "<td>" . $row['id_number'] . "</td>";
                echo "<td>" . $row['student_name'] . "</td>";
                echo "<td>" . $row['lab'] . "</td>";
                echo "<td>" . date('h:i A', strtotime($row['time_in'])) . "</td>";
                echo "<td>" . date('h:i A', strtotime($row['time_out'])) . "</td>";
                echo "<td>" . $row['purpose'] . "</td>";
                echo "<td>" . $row['status'] . "</td>";
                echo "<td>" . date('F d, Y', strtotime($row['date'])) . "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
            
            // Summary information
            echo "<p><strong>Total Records:</strong> " . count($export_data) . "</p>";
            echo "<p><strong>Report Generated:</strong> " . date('Y-m-d H:i:s') . "</p>";
            
            echo "</body>";
            echo "</html>";
            exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sit-In Reports</title>
    <link rel="stylesheet" href="admins.css">
    <style>
        .filters {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f5f5f5;
            border-radius: 5px;
        }
        
        .filters form {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filters select, .filters button {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .filters button {
            background-color: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
        }
        
        .filters button:hover {
            background-color: #45a049;
        }
        
        .export-options {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .export-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            color: white;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .export-btn.pdf {
            background-color: #f44336;
        }
        
        .export-btn.csv {
            background-color: #2196F3;
        }
        
        .export-btn.excel {
            background-color: #4CAF50;
        }
        
        .export-btn.print {
            background-color: #607D8B;
        }
        
        .export-btn:hover {
            opacity: 0.9;
        }
        
        .status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .count-info {
            margin-bottom: 15px;
            font-style: italic;
            color: #666;
        }
        
        /* Print-specific styles */
        @media print {
            .sidebar, .filters, .export-options, .count-info {
                display: none !important;
            }
            
            body {
                margin: 0;
                padding: 20px;
                font-family: Arial, sans-serif;
            }
            
            .content {
                margin-left: 0 !important;
                width: 100% !important;
            }
            
            h1 {
                text-align: center;
                margin-bottom: 5px;
            }
            
            .report-header {
                text-align: center;
                font-size: 18px;
                font-weight: bold;
                margin-bottom: 20px;
            }
            
            .report-date {
                text-align: center;
                font-size: 14px;
                margin-bottom: 20px;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
            }
            
            th, td {
                border: 1px solid #000;
                padding: 8px;
            }
            
            th {
                background-color: #f2f2f2 !important;
                color: #000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .status.status-completed {
                background-color: #d4edda !important;
                color: #155724 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>Admin Panel</h2>
        <ul>
            <li><a href="admin_dashboard.php">Dashboard</a></li>
            <li><a href="reservations.php">Pending Reservation</a></li>
            <li><a href="current_sitin.php">Current Sit-In</a></li>
            <li><a href="sitin_reports.php" class="active">Sit-In Reports</a></li>
            <li><a href="students.php">Students</a></li>
            <li><a href="announcement.php">Announcement</a></li>
            <li><a href="feedback.php">Feedback</a></li>
            <li><a href="labsched.php">Lab Schedule</a></li>
            <li><a href="resources.php">Lab Resources</a></li>
            <li><a href="leaderboard.php">Leaderboard</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </div>
    <div class="content">
        <h1>Sit-In Reports</h1>
        
        <!-- These headers will show when printing -->

        
        <div class="filters">
            <form method="GET" action="">
                <div>
                    <label for="lab">Filter by Lab:</label>
                    <select name="lab" id="lab">
                        <option value="">All Labs</option>
                        <option value="524" <?= ($lab_filter == '524') ? 'selected' : '' ?>>524</option>
                        <option value="526" <?= ($lab_filter == '526') ? 'selected' : '' ?>>526</option>
                        <option value="528" <?= ($lab_filter == '528') ? 'selected' : '' ?>>528</option>
                        <option value="530" <?= ($lab_filter == '530') ? 'selected' : '' ?>>530</option>
                        <option value="542" <?= ($lab_filter == '542') ? 'selected' : '' ?>>542</option>
                        <option value="544" <?= ($lab_filter == '544') ? 'selected' : '' ?>>544</option>
                        <option value="517" <?= ($lab_filter == '517') ? 'selected' : '' ?>>517</option>
                        <?php foreach ($labs as $lab): ?>
                            <?php if (!in_array($lab, ['524', '526', '528', '530', '542', '544', '517'])): ?>
                                <option value="<?= htmlspecialchars($lab) ?>" <?= ($lab_filter == $lab) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($lab) ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="purpose">Filter by Purpose:</label>
                    <select name="purpose" id="purpose">
                        <option value="">All Purposes</option>
                        <option value="C Programming" <?= ($purpose_filter == 'C Programming') ? 'selected' : '' ?>>C Programming</option>
                        <option value="Java Programming" <?= ($purpose_filter == 'Java Programming') ? 'selected' : '' ?>>Java Programming</option>
                        <option value="C# Programming" <?= ($purpose_filter == 'C# Programming') ? 'selected' : '' ?>>C# Programming</option>
                        <option value="System Integration and Architecture" <?= ($purpose_filter == 'System Integration and Architecture') ? 'selected' : '' ?>>System Integration and Architecture</option>
                        <option value="Embedded Systems and IOT" <?= ($purpose_filter == 'Embedded Systems and IOT') ? 'selected' : '' ?>>Embedded Systems and IOT</option>
                        <option value="Digital Logic Design" <?= ($purpose_filter == 'Digital Logic Design') ? 'selected' : '' ?>>Digital Logic Design</option>
                        <option value="Computer Application" <?= ($purpose_filter == 'Computer Application') ? 'selected' : '' ?>>Computer Application</option>
                        <option value="Database" <?= ($purpose_filter == 'Database') ? 'selected' : '' ?>>Database</option>
                        <option value="Project Management" <?= ($purpose_filter == 'Project Management') ? 'selected' : '' ?>>Project Management</option>
                        <option value="Python Programming" <?= ($purpose_filter == 'Python Programming') ? 'selected' : '' ?>>Python Programming</option>
                        <option value="Web Design" <?= ($purpose_filter == 'Web Design') ? 'selected' : '' ?>>Web Design</option>
                        <option value="Mobile Application Development" <?= ($purpose_filter == 'Mobile Application Development') ? 'selected' : '' ?>>Mobile Application Development</option>
                        <option value="Artificial Intelligence" <?= ($purpose_filter == 'Artificial Intelligence') ? 'selected' : '' ?>>Artificial Intelligence</option>
                        <option value="Web Development" <?= ($purpose_filter == 'Web Development') ? 'selected' : '' ?>>Web Development</option>
                        <option value="Others" <?= ($purpose_filter == 'Others') ? 'selected' : '' ?>>Others</option>
                        <?php foreach ($purposes as $purpose): ?>
                            <?php if (!in_array($purpose, ['C Programming', 'Java Programming', 'C# Programming','System Integration and Architecture','Embedded Systems and IOT','Digital Logic Design','Computer Application','Database','Project Management','Python Programming','Web Design','Mobile Application Development','Artificial Intelligence','Others'])): ?>
                                <option value="<?= htmlspecialchars($purpose) ?>" <?= ($purpose_filter == $purpose) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($purpose) ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit">Apply Filters</button>
               
            </form>
        </div>
        
        <div class="export-options">
            <h4>Generate Reports:</h4>
            <a href="?export=pdf<?= !empty($lab_filter) ? '&lab='.urlencode($lab_filter) : '' ?><?= !empty($purpose_filter) ? '&purpose='.urlencode($purpose_filter) : '' ?>" class="export-btn pdf">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M5.5 3a.5.5 0 0 1 .5.5V9h5a.5.5 0 0 1 0 1H6v2.5a.5.5 0 0 1-1 0v-9a.5.5 0 0 1 .5-.5z"/>
                    <path d="M4 1h8a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2z"/>
                </svg>
                Export as PDF
            </a>
            <a href="?export=csv<?= !empty($lab_filter) ? '&lab='.urlencode($lab_filter) : '' ?><?= !empty($purpose_filter) ? '&purpose='.urlencode($purpose_filter) : '' ?>" class="export-btn csv">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M4.5 3a.5.5 0 0 0-.5.5v9a.5.5 0 0 0 .5.5h7a.5.5 0 0 0 .5-.5v-9a.5.5 0 0 0-.5-.5h-7z"/>
                    <path d="M12 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h8z"/>
                </svg>
                Export as CSV
            </a>
            <a href="?export=excel<?= !empty($lab_filter) ? '&lab='.urlencode($lab_filter) : '' ?><?= !empty($purpose_filter) ? '&purpose='.urlencode($purpose_filter) : '' ?>" class="export-btn excel">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2zm2 .5v11a.5.5 0 0 0 .5.5h7a.5.5 0 0 0 .5-.5v-11a.5.5 0 0 0-.5-.5h-7a.5.5 0 0 0-.5.5z"/>
                </svg>
                Export as Excel
            </a>
            <button onclick="window.print();" class="export-btn print">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
                    <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2H5zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1z"/>
                </svg>
                Print Report
            </button>
        </div>

        <div class="count-info">
            Showing <?= count($sitin_reports) ?> records 
            <?php if (!empty($lab_filter) || !empty($purpose_filter)): ?>
                with applied filters
            <?php endif; ?>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>ID Number</th>
                    <th>Student Name</th>
                    <th>Lab</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Purpose</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($sitin_reports)): ?>
                    <?php foreach ($sitin_reports as $report): ?>
                        <tr>
                            <td><?= htmlspecialchars($report['id_number']) ?></td>
                            <td><?= htmlspecialchars($report['student_name']) ?></td>
                            <td><?= htmlspecialchars($report['lab']) ?></td>
                            <td><?= htmlspecialchars(date('h:i A', strtotime($report['time_in']))) ?></td>
                            <td><?= htmlspecialchars(date('h:i A', strtotime($report['time_out']))) ?></td>
                            <td><?= htmlspecialchars($report['purpose']) ?></td>
                            <td><span class="status status-completed"><?= htmlspecialchars($report['status']) ?></span></td>
                            <td><?= htmlspecialchars(date('F d, Y', strtotime($report['date']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="no-data">No sit-in reports available.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <script>
            // JavaScript to handle printing with header
            function printTable() {
                window.print();
            }
        </script>
    </div>
</body>
</html>