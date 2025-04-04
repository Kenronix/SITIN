<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'conn.php';

// Get user information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
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
    <link rel="stylesheet" href="index.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .container {
            max-width: 900px;
            margin: 20px auto;
            padding: 20px;
        }
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .history-table th, .history-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .history-table th {
            background-color: #f2f2f2;
        }
        .status-completed {
            color: green;
            font-weight: bold;
        }
        .status-active {
            color: blue;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <a href="index.php">Home</a>
            <a href="history.php" class="active">History</a>
            <a href="reservation.php">Reservation</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="container">
        <h2>My Sit-In History</h2>
        
        <?php if (empty($history)): ?>
            <div class="alert alert-info">You have no sit-in history yet.</div>
        <?php else: ?>
            <table class="history-table">
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
                            <td><?= date('F d, Y', strtotime($record['date'])) ?></td>
                            <td><?= htmlspecialchars($record['lab']) ?></td>
                            <td><?= date('h:i A', strtotime($record['time_in'])) ?></td>
                            <td>
                                <?= $record['time_out'] ? date('h:i A', strtotime($record['time_out'])) : 'Active' ?>
                            </td>
                            <td><?= htmlspecialchars($record['purpose']) ?></td>
                            <td>
                                <?php if ($record['time_out']): ?>
                                    <span class="status-completed">Completed</span>
                                <?php else: ?>
                                    <span class="status-active">Active</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($record['time_out'] && $record['has_feedback'] == 0): ?>
                                    <button class="btn btn-sm btn-primary feedback-btn" 
                                            data-id="<?= $record['id'] ?>"
                                            data-lab="<?= $record['lab'] ?>"
                                            data-date="<?= $record['date'] ?>">
                                        Give Feedback
                                    </button>
                                <?php elseif ($record['has_feedback'] > 0): ?>
                                    <span class="badge bg-success">Feedback Submitted</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">In Progress</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Feedback Modal -->
    <div class="modal fade" id="feedbackModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Submit Feedback</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="feedbackForm" method="post" action="submit_feedback.php">
                        <input type="hidden" id="sit_in_id" name="sit_in_id">
                        <input type="hidden" id="id_number" name="id_number" value="<?= $id_number ?>">
                        <input type="hidden" id="lab" name="lab">
                        <input type="hidden" id="date" name="date">
                        
                        <div class="mb-3">
                            <label for="comments" class="form-label">Comments</label>
                            <textarea class="form-control" id="comments" name="comments" rows="4" placeholder="Share your experience or suggestions..." required></textarea>
                        </div>
                        
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary">Submit Feedback</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize feedback modal
        document.addEventListener('DOMContentLoaded', function() {
            const feedbackModal = new bootstrap.Modal(document.getElementById('feedbackModal'));
            
            // Setup feedback button listeners
            document.querySelectorAll('.feedback-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const sitInId = this.getAttribute('data-id');
                    const lab = this.getAttribute('data-lab');
                    const date = this.getAttribute('data-date');
                    
                    document.getElementById('sit_in_id').value = sitInId;
                    document.getElementById('lab').value = lab;
                    document.getElementById('date').value = date;
                    
                    feedbackModal.show();
                });
            });
        });
    </script>
</body>
</html>