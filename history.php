<?php include 'sidebar.php'; ?>

<div class="main-content">
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'conn.php';

// Get user information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id_number = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get user sit-in history
$id_number = $user['id_number'];
$history_stmt = $pdo->prepare("SELECT s.*, 
                             (SELECT COUNT(*) FROM feedback f WHERE f.sit_in_id = s.id) as has_feedback
                             FROM sit_ins s 
                             WHERE s.id_number = ? 
                             ORDER BY s.date DESC, s.time_in DESC");
$history_stmt->execute([$id_number]);
$history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sit-In History</title>
    <link rel="stylesheet" href="modern-style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .history-table {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            background: #f8f9fa;
            color: #6c757d;
            font-weight: 600;
            text-align: left;
            padding: 15px;
            border-bottom: 2px solid #e9ecef;
        }

        .table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            color: #2c3e50;
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-active {
            background: #cce5ff;
            color: #004085;
        }

        .btn-feedback {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-feedback:hover {
            background: #0056b3;
            transform: translateY(-1px);
        }

        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-secondary {
            background: #e9ecef;
            color: #6c757d;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            color: #6c757d;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #dee2e6;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(30, 41, 59, 0.4);
            justify-content: center;
            align-items: center;
            transition: opacity 0.2s;
        }
        .modal.show {
            display: flex;
            animation: fadeIn 0.2s;
        }
        .modal-dialog {
            max-width: 600px;
            width: 95vw;
            margin: 0 auto;
        }
        .modal-content {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(30,41,59,0.18);
            overflow: hidden;
            padding: 0;
            animation: modalPop 0.25s;
        }
        .modal-header {
            background: #f1f5f9;
            padding: 24px 24px 12px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .modal-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1e293b;
        }
        .btn-close {
            background: none;
            border: none;
            font-size: 1.7rem;
            color: #64748b;
            cursor: pointer;
            transition: color 0.2s;
        }
        .btn-close:hover {
            color: #ef4444;
        }
        .modal-body {
            padding: 24px;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes modalPop {
            from { transform: translateY(40px) scale(0.98); opacity: 0; }
            to { transform: translateY(0) scale(1); opacity: 1; }
        }

        @media (max-width: 768px) {
            .table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="history-container">
            <div class="page-header">
                <h2>My Sit-In History</h2>
            </div>

            <?php if (empty($history)): ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <p>You have no sit-in history yet.</p>
                </div>
            <?php else: ?>
                <div class="history-table">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Lab</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Purpose</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $record): ?>
                                <tr>
                                    <td>
                                        <?php
                                        // Parse the date from the datetime field
                                        $datetime = strtotime($record['date']);
                                        if ($datetime) {
                                            echo date('F d, Y', $datetime);
                                        } else {
                                            // Fallback if parsing fails
                                            echo htmlspecialchars(substr($record['date'], 0, 10));
                                        }
                                        ?>
                                    </td>
                                    <td><?= htmlspecialchars($record['lab']) ?></td>
                                    <td>
                                        <?php
                                        // Format time_in
                                        $time_in = strtotime($record['time_in']);
                                        if ($time_in) {
                                            echo date('h:i A', $time_in);
                                        } else {
                                            echo htmlspecialchars($record['time_in']);
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        if (!empty($record['time_out'])) {
                                            $time_out = strtotime($record['time_out']);
                                            if ($time_out) {
                                                echo date('h:i A', $time_out);
                                            } else {
                                                echo htmlspecialchars($record['time_out']);
                                            }
                                        } else {
                                            echo 'Active';
                                        }
                                        ?>
                                    </td>
                                    <td><?= htmlspecialchars($record['purpose']) ?></td>
                                    <td>
                                        <?php if ($record['time_out']): ?>
                                            <span class="status-badge status-completed">
                                                <i class="fas fa-check-circle"></i>
                                                Completed
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-active">
                                                <i class="fas fa-clock"></i>
                                                Active
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($record['time_out'] && $record['has_feedback'] == 0): ?>
                                            <button class="btn-feedback feedback-btn" 
                                                    data-id="<?= $record['id'] ?>"
                                                    data-lab="<?= $record['lab'] ?>"
                                                    data-date="<?= $record['date'] ?>">
                                                <i class="fas fa-comment-alt"></i>
                                                Give Feedback
                                            </button>
                                        <?php elseif ($record['has_feedback'] > 0): ?>
                                            <span class="badge badge-success">
                                                <i class="fas fa-check"></i>
                                                Feedback Submitted
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">
                                                <i class="fas fa-clock"></i>
                                                In Progress
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Feedback Modal -->
    <div id="feedbackModal" class="modal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Submit Feedback</h5>
                    <button type="button" class="btn-close" id="closeFeedbackModal" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="feedbackForm" method="post" action="submit_feedback.php">
                        <input type="hidden" id="sit_in_id" name="sit_in_id">
                        <input type="hidden" id="id_number" name="id_number" value="<?= $id_number ?>">
                        <input type="hidden" id="lab" name="lab">
                        <input type="hidden" id="date" name="date">
                        
                        <div class="form-group">
                            <label for="comments" class="form-label">Comments</label>
                            <textarea class="form-control" id="comments" name="comments" rows="4" 
                                    placeholder="Share your experience or suggestions..." required></textarea>
                        </div>
                        
                        <div class="text-center" style="margin-top: 20px;">
                            <button type="submit" class="btn-feedback">
                                <i class="fas fa-paper-plane"></i>
                                Submit Feedback
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const feedbackModal = document.getElementById('feedbackModal');
            const closeBtn = document.getElementById('closeFeedbackModal');
            // Setup feedback button listeners
            document.querySelectorAll('.feedback-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const sitInId = this.getAttribute('data-id');
                    const lab = this.getAttribute('data-lab');
                    const date = this.getAttribute('data-date');
                    document.getElementById('sit_in_id').value = sitInId;
                    document.getElementById('lab').value = lab;
                    document.getElementById('date').value = date;
                    feedbackModal.classList.add('show');
                });
            });
            closeBtn.addEventListener('click', function() {
                feedbackModal.classList.remove('show');
            });
            // Hide modal when clicking outside modal-content
            feedbackModal.addEventListener('click', function(e) {
                if (e.target === feedbackModal) {
                    feedbackModal.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>
</div>