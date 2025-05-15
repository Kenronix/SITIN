<?php
session_start();
include 'conn.php'; // Database connection

// Import PhpSpreadsheet classes at the top level, outside any function or conditional block
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Get filter parameters
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$lab_filter = $_GET['lab'] ?? '';
$search = $_GET['search'] ?? '';

// Base query for sit-ins
$query = "SELECT s.*, CONCAT(u.firstname, ' ', u.lastname) as student_name, 
          u.course, u.year_level
          FROM sit_ins s 
          JOIN users u ON s.id_number = u.id_number 
          WHERE s.date BETWEEN ? AND ?";

$params = [$start_date, $end_date];

// Add lab filter if specified
if (!empty($lab_filter)) {
    $query .= " AND s.lab = ?";
    $params[] = $lab_filter;
}

// Add search filter if specified
if (!empty($search)) {
    $query .= " AND (s.id_number LIKE ? OR CONCAT(u.firstname, ' ', u.lastname) LIKE ? OR s.purpose LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

$query .= " ORDER BY s.date DESC, s.time_in DESC";

// Get sit-ins
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$sit_ins = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available labs for filter
$labs_stmt = $pdo->query("SELECT DISTINCT lab FROM sit_ins ORDER BY lab");
$available_labs = $labs_stmt->fetchAll(PDO::FETCH_COLUMN);

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
                $formatted_date = date('M d, Y', strtotime($row['date']));
                $status = getSitInStatus($row);
                $rows_js .= json_encode([
                    $row['id_number'],
                    $row['student_name'],
                    $row['lab'],
                    date('h:i A', strtotime($row['time_in'])),
                    date('h:i A', strtotime($row['time_out'])),
                    $purpose,
                    $status,
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
                $status = getSitInStatus($row);
                $formatted_row = [
                    $row['id_number'],
                    $row['student_name'],
                    $row['lab'],
                    date('h:i A', strtotime($row['time_in'])),
                    date('h:i A', strtotime($row['time_out'])),
                    $row['purpose'],
                    $status,
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
                $status = getSitInStatus($row);
                echo "<tr>";
                echo "<td>" . $row['id_number'] . "</td>";
                echo "<td>" . $row['student_name'] . "</td>";
                echo "<td>" . $row['lab'] . "</td>";
                echo "<td>" . date('h:i A', strtotime($row['time_in'])) . "</td>";
                echo "<td>" . date('h:i A', strtotime($row['time_out'])) . "</td>";
                echo "<td>" . $row['purpose'] . "</td>";
                echo "<td>" . $status . "</td>";
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

// Helper function to determine status
function getSitInStatus($row) {
    if (isset($row['time_out']) && !empty($row['time_out'])) {
        return 'Completed';
    }
    return 'Active';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sit-In Reports | Admin Panel</title>
    <link rel="stylesheet" href="admin_style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="sidebar">
        <h2><i class="fas fa-laptop-code"></i> Admin Panel</h2>
        <ul>
            <li><a href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li><a href="reservations.php"><i class="fas fa-calendar-check"></i> Pending Reservation</a></li>
            <li><a href="current_sitin.php"><i class="fas fa-users"></i> Current Sit-In</a></li>
            <li><a href="sitin_reports.php" class="active"><i class="fas fa-file-alt"></i> Sit-In Reports</a></li>
            <li><a href="students.php"><i class="fas fa-user-graduate"></i> Students</a></li>
            <li><a href="announcement.php"><i class="fas fa-bullhorn"></i> Announcement</a></li>
            <li><a href="feedback.php"><i class="fas fa-comment-alt"></i> Feedback</a></li>
            <li><a href="labsched.php"><i class="fas fa-clock"></i> Lab Schedule</a></li>
            <li><a href="resources.php"><i class="fas fa-book"></i> Lab Resources</a></li>
            <li><a href="leaderboard.php"><i class="fas fa-trophy"></i> Leaderboard</a></li>
            <li><a href="pc_management.php"><i class="fas fa-desktop"></i> PC Management</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="content">
        <div class="content-header">
            <h1><i class="fas fa-file-alt"></i> Sit-In Reports</h1>
            <div class="header-actions">
                <div class="export-buttons">
                    <a href="?export=pdf<?= !empty($lab_filter) ? '&lab='.urlencode($lab_filter) : '' ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date='.urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date='.urlencode($end_date) : '' ?>" class="btn btn-secondary">
                        <i class="fas fa-file-pdf"></i> Export PDF
                    </a>
                    <a href="?export=csv<?= !empty($lab_filter) ? '&lab='.urlencode($lab_filter) : '' ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date='.urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date='.urlencode($end_date) : '' ?>" class="btn btn-secondary">
                        <i class="fas fa-file-csv"></i> Export CSV
                    </a>
                    <a href="?export=excel<?= !empty($lab_filter) ? '&lab='.urlencode($lab_filter) : '' ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date='.urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date='.urlencode($end_date) : '' ?>" class="btn btn-secondary">
                        <i class="fas fa-file-excel"></i> Export Excel
                    </a>
                    <button onclick="printReport()" class="btn btn-secondary">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-container">
            <form id="filterForm" class="filters-form" method="GET">
                <div class="filter-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date" 
                           value="<?= htmlspecialchars($start_date) ?>" class="form-control">
                </div>
                <div class="filter-group">
                    <label for="end_date">End Date</label>
                    <input type="date" id="end_date" name="end_date" 
                           value="<?= htmlspecialchars($end_date) ?>" class="form-control">
                </div>
                <div class="filter-group">
                    <label for="lab">Lab</label>
                    <select id="lab" name="lab" class="form-control">
                        <option value="">All Labs</option>
                        <?php foreach ($available_labs as $lab): ?>
                            <option value="<?= htmlspecialchars($lab) ?>" 
                                    <?= $lab_filter === $lab ? 'selected' : '' ?>>
                                Lab <?= htmlspecialchars($lab) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" 
                           value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Search by ID, name, or purpose" class="form-control">
                </div>
                <div class="filter-group">
                    <label for=""></label>
                    <button type="submit" class="btn btn-primary apply-filters-btn">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Sit-In Records Table -->
        <div class="table-container">
            <div class="table-header">
                <h3>Sit-In Records</h3>
                <span class="record-count"><?= count($sit_ins) ?> records found</span>
            </div>
            
            <?php if (!empty($sit_ins)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Course & Year</th>
                            <th>Lab</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Purpose</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sit_ins as $sitin): ?>
                            <tr>
                                <td>
                                    <span class="date-badge">
                                        <i class="far fa-calendar-alt"></i>
                                        <?= date('M d, Y', strtotime($sitin['date'])) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($sitin['id_number']) ?></td>
                                <td>
                                    <div class="student-info">
                                        <span class="student-name"><?= htmlspecialchars($sitin['student_name']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="course-badge">
                                        <?= htmlspecialchars($sitin['course']) ?> - 
                                        <?= htmlspecialchars($sitin['year_level']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="lab-badge">
                                        <i class="fas fa-laptop"></i>
                                        <?= htmlspecialchars($sitin['lab']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="time-badge">
                                        <i class="far fa-clock"></i>
                                        <?= date('h:i A', strtotime($sitin['time_in'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($sitin['time_out']): ?>
                                        <span class="time-badge">
                                            <i class="far fa-clock"></i>
                                            <?= date('h:i A', strtotime($sitin['time_out'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="status status-pending"><?= getSitInStatus($sitin) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($sitin['purpose']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt fa-3x"></i>
                    <h3>No Sit-In Records Found</h3>
                    <p>Try adjusting your filters to see more results.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <style>
        /* Update styles */
        .export-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-secondary {
            background-color: var(--secondary-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            transition: background-color 0.2s;
        }

        .btn-secondary:hover {
            background-color: var(--secondary-color-dark);
        }

        @media print {
            /* Hide everything except the print content */
            body * {
                visibility: hidden;
            }
            
            .print-content,
            .print-content * {
                visibility: visible;
            }
            
            .print-content {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }

            .print-header {
                text-align: center;
                margin-bottom: 20px;
                padding: 20px;
                border-bottom: 2px solid #000;
            }

            .print-header h1 {
                font-size: 24px;
                margin: 0;
                color: #000;
            }

            .print-header h2 {
                font-size: 18px;
                margin: 10px 0;
                color: #333;
            }

            .print-header p {
                font-size: 14px;
                margin: 5px 0;
                color: #666;
            }

            .print-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }

            .print-table th,
            .print-table td {
                border: 1px solid #000;
                padding: 8px;
                text-align: left;
                font-size: 12px;
            }

            .print-table th {
                background-color: #f5f5f5 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .print-footer {
                margin-top: 20px;
                text-align: center;
                font-size: 12px;
                color: #666;
            }
        }

        /* Additional styles specific to sit-in reports */
        .filters-container {
            background: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .filters-form {
            display: inline-flex;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: center;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .record-count {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .table-container {
            overflow-x: auto;
        }
    </style>

    <script>
        function printReport() {
            // Create print content
            const printWindow = window.open('', '_blank');
            const table = document.querySelector('.table-container table');
            const filters = document.querySelector('.filters-form');
            
            // Get filter values
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const lab = document.getElementById('lab').value;
            const search = document.getElementById('search').value;
            
            // Create print content HTML
            const printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        .print-header { text-align: center; margin-bottom: 20px; }
                        .print-header h1 { font-size: 24px; margin: 0; }
                        .print-header h2 { font-size: 18px; margin: 10px 0; }
                        .print-header p { font-size: 14px; margin: 5px 0; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        th, td { border: 1px solid #000; padding: 8px; text-align: left; font-size: 12px; }
                        th { background-color: #f5f5f5; }
                        @media print {
                            @page { margin: 1cm; }
                        }
                    </style>
                </head>
                <body>
                    <div class="print-header">
                        <h1>University of Cebu</h1>
                        <h2>College of Computer Studies</h2>
                        <h3>Computer Laboratory Sit-in Monitoring System</h3>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Course & Year</th>
                                <th>Lab</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Purpose</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${Array.from(table.querySelectorAll('tbody tr')).map(row => `
                                <tr>
                                    ${Array.from(row.querySelectorAll('td')).map(cell => {
                                        // Remove badges and icons for clean print
                                        const content = cell.innerHTML.replace(/<[^>]*>/g, '');
                                        return `<td>${content.trim()}</td>`;
                                    }).join('')}
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </body>
                </html>
            `;
            
            // Write to print window and print
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.focus();
            
            // Wait for content to load then print
            printWindow.onload = function() {
                printWindow.print();
                printWindow.close();
            };
        }
    </script>
</body>
</html>