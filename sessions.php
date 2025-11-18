<?php
include 'config.php';

$conn = getConnection();

$success_message = '';
$error_message = '';

// Add new session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_session'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $session_date = $_POST['session_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $total_rooms = intval($_POST['total_rooms']);
    $seats_per_room = intval($_POST['seats_per_room']);
    $rows_per_room = intval($_POST['rows_per_room']);
    $seats_per_row = intval($_POST['seats_per_row']);
    
    $result = $conn->query("INSERT INTO exam_sessions 
        (session_name, session_date, start_time, end_time, total_rooms, seats_per_room, rows_per_room, seats_per_row) 
        VALUES ('$name', '$session_date', '$start_time', '$end_time', $total_rooms, $seats_per_room, $rows_per_room, $seats_per_row)");
    
    if ($result) {
        $session_id = $conn->insert_id;
        
        // Add selected exams to this session
        if (isset($_POST['exam_ids']) && is_array($_POST['exam_ids'])) {
            foreach ($_POST['exam_ids'] as $exam_id) {
                $exam_id = intval($exam_id);
                $conn->query("INSERT INTO session_exams (session_id, exam_id) VALUES ($session_id, $exam_id)");
            }
        }
        $success_message = "✅ Exam session created successfully!";
    }
}

// Update session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_session'])) {
    $session_id = intval($_POST['session_id']);
    $name = $conn->real_escape_string($_POST['name']);
    $session_date = $_POST['session_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $total_rooms = intval($_POST['total_rooms']);
    $seats_per_room = intval($_POST['seats_per_room']);
    $rows_per_room = intval($_POST['rows_per_room']);
    $seats_per_row = intval($_POST['seats_per_row']);
    
    $result = $conn->query("UPDATE exam_sessions SET 
        session_name = '$name',
        session_date = '$session_date',
        start_time = '$start_time',
        end_time = '$end_time',
        total_rooms = $total_rooms,
        seats_per_room = $seats_per_room,
        rows_per_room = $rows_per_room,
        seats_per_row = $seats_per_row
        WHERE id = $session_id");
    
    if ($result) {
        // Delete old exam associations
        $conn->query("DELETE FROM session_exams WHERE session_id = $session_id");
        
        // Add new exam associations
        if (isset($_POST['exam_ids']) && is_array($_POST['exam_ids'])) {
            foreach ($_POST['exam_ids'] as $exam_id) {
                $exam_id = intval($exam_id);
                $conn->query("INSERT INTO session_exams (session_id, exam_id) VALUES ($session_id, $exam_id)");
            }
        }
        $success_message = "✅ Exam session updated successfully!";
    }
}

// Delete session
if (isset($_GET['delete']) && isset($_GET['session_id'])) {
    $session_id = intval($_GET['session_id']);
    
    // Check if session has seating arrangements
    $check = $conn->query("SELECT COUNT(*) as count FROM seating_arrangements WHERE session_id = $session_id")->fetch_assoc();
    
    if ($check['count'] > 0) {
        $error_message = "⚠️ Cannot delete session with existing seating arrangements. Please delete seating first.";
    } else {
        // Delete session exams first
        $conn->query("DELETE FROM session_exams WHERE session_id = $session_id");
        // Delete session
        $conn->query("DELETE FROM exam_sessions WHERE id = $session_id");
        $success_message = "✅ Exam session deleted successfully!";
    }
}

// Get session for editing
$edit_session = null;
$edit_session_exams = [];
if (isset($_GET['edit']) && isset($_GET['session_id'])) {
    $session_id = intval($_GET['session_id']);
    $edit_session = $conn->query("SELECT * FROM exam_sessions WHERE id = $session_id")->fetch_assoc();
    
    if ($edit_session) {
        $exams_result = $conn->query("SELECT exam_id FROM session_exams WHERE session_id = $session_id");
        while ($row = $exams_result->fetch_assoc()) {
            $edit_session_exams[] = $row['exam_id'];
        }
    }
}

// Get all available exams
$available_exams = $conn->query("
    SELECT e.*, c.name as course_name, c.code as course_code, d.name as dept_name
    FROM exams e
    JOIN courses c ON e.course_id = c.id
    JOIN departments d ON c.department_id = d.id
    ORDER BY e.exam_date, e.start_time
");

// Get all sessions with their exams
$sessions = $conn->query("
    SELECT es.*, 
    COUNT(DISTINCT se.exam_id) as exam_count,
    COUNT(DISTINCT sa.student_id) as seated_students
    FROM exam_sessions es
    LEFT JOIN session_exams se ON es.id = se.session_id
    LEFT JOIN seating_arrangements sa ON es.id = sa.session_id
    GROUP BY es.id
    ORDER BY es.session_date DESC
");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Sessions</title>

     <link rel ="stylesheet" href="style.css">
    <script src ="script.js"></script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1rem 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .navbar h1 { font-size: 1.5rem; }
        .container { display: flex; min-height: calc(100vh - 70px); }
        .sidebar { width: 250px; background: white; padding: 2rem 0; box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
        .sidebar a { display: block; padding: 1rem 2rem; color: #333; text-decoration: none; transition: all 0.3s; }
        .sidebar a:hover, .sidebar a.active { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .main-content { flex: 1; padding: 2rem; }
        .card { background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 1.5rem; }
        .card h2 { margin-bottom: 1rem; color: #333; }
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            font-weight: 500;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 0.5rem; color: #666; font-weight: 500; }
        .form-group input, .form-group select { padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem; }
        .exam-selection {
            border: 1px solid #ddd;
            padding: 1rem;
            border-radius: 5px;
            max-height: 200px;
            overflow-y: auto;
            background: #f8f9fa;
        }
        .exam-checkbox {
            display: flex;
            align-items: center;
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            background: white;
            border-radius: 5px;
        }
        .exam-checkbox input {
            margin-right: 0.75rem;
            width: 18px;
            height: 18px;
        }
        .exam-checkbox label {
            margin: 0;
            cursor: pointer;
            flex: 1;
        }
        .btn { padding: 0.75rem 1.5rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; transition: all 0.3s; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4); }
        .btn-secondary { background: #6c757d; }
        .btn-danger { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .btn-small { padding: 0.5rem 1rem; font-size: 0.85rem; margin-right: 0.5rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; color: #333; }
        .badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.85rem; font-weight: 500; }
        .badge-primary { background: #e3f2fd; color: #1976d2; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .action-buttons { display: flex; gap: 0.5rem; }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>📚 Exam Seating Management System</h1>
    </div>
    
    <div class="container">
        <div class="sidebar">
            <a href="index.php"> Dashboard</a>
            <a href="departments.php"> Departments/Batches</a>
            <a href="students.php"> Students</a>
            <a href="courses.php"> Courses</a>
            <a href="student_course_enrollment.php"> Course Enrollment</a>
            <a href="sessions.php" class="active"> Exam Sessions</a>
            <a href="exams.php"> Exams</a>
            <a href="enroll_students.php"> Enroll Students</a>
            <a href="visual_seating.php"> Visual Seating Plan</a>
        </div>
        
        <div class="main-content">
            <h2 style="margin-bottom: 1.5rem; color: #333;">Exam Session Management</h2>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <div class="card">
                <h2><?php echo $edit_session ? '✏️ Edit Exam Session' : '➕ Create New Exam Session'; ?></h2>
                
                <form method="POST">
                    <?php if ($edit_session): ?>
                        <input type="hidden" name="session_id" value="<?php echo $edit_session['id']; ?>">
                        <a href="sessions.php" class="btn btn-cancel">← Cancel Edit</a>
                    <?php endif; ?>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Session Name</label>
                            <input type="text" name="name" placeholder="e.g., Morning Session" 
                                   value="<?php echo $edit_session ? htmlspecialchars($edit_session['session_name']) : ''; ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Session Date</label>
                            <input type="date" name="session_date" 
                                   value="<?php echo $edit_session ? $edit_session['session_date'] : ''; ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Start Time</label>
                            <input type="time" name="start_time" 
                                   value="<?php echo $edit_session ? $edit_session['start_time'] : ''; ?>" required>
                        </div>
                        <div class="form-group">
                            <label>End Time</label>
                            <input type="time" name="end_time" 
                                   value="<?php echo $edit_session ? $edit_session['end_time'] : ''; ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Total Rooms</label>
                            <input type="number" name="total_rooms" 
                                   value="<?php echo $edit_session ? $edit_session['total_rooms'] : '1'; ?>" min="1" required>
                        </div>
                        <div class="form-group">
                            <label>Seats Per Room</label>
                            <input type="number" name="seats_per_room" 
                                   value="<?php echo $edit_session ? $edit_session['seats_per_room'] : '30'; ?>" min="10" required>
                        </div>
                        <div class="form-group">
                            <label>Rows Per Room</label>
                            <input type="number" name="rows_per_room" 
                                   value="<?php echo $edit_session ? $edit_session['rows_per_room'] : '15'; ?>" min="5" required>
                        </div>
                        <div class="form-group">
                            <label>Seats Per Row</label>
                            <input type="number" name="seats_per_row" 
                                   value="<?php echo $edit_session ? $edit_session['seats_per_row'] : '2'; ?>" min="2" max="4" required>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-top: 1rem;">
                        <label>Select Exams for this Session (Multiple allowed)</label>
                        <div class="exam-selection">
                            <?php 
                            $available_exams->data_seek(0);
                            while ($exam = $available_exams->fetch_assoc()): 
                            ?>
                            <div class="exam-checkbox">
                                <input type="checkbox" name="exam_ids[]" value="<?php echo $exam['id']; ?>" 
                                       id="exam_<?php echo $exam['id']; ?>"
                                       <?php echo (in_array($exam['id'], $edit_session_exams)) ? 'checked' : ''; ?>>
                                <label for="exam_<?php echo $exam['id']; ?>">
                                    <strong><?php echo htmlspecialchars($exam['name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($exam['course_code'] . ' - ' . $exam['course_name'] . ' (' . $exam['dept_name'] . ')'); ?></small><br>
                                    <small style="color: #666;">Date: <?php echo date('M d, Y', strtotime($exam['exam_date'])); ?> | Time: <?php echo date('h:i A', strtotime($exam['start_time'])); ?></small>
                                </label>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    
                    <button type="submit" name="<?php echo $edit_session ? 'update_session' : 'add_session'; ?>" class="btn" style="margin-top: 1rem;">
                        <?php echo $edit_session ? '💾 Update Session' : '➕ Create Session'; ?>
                    </button>
                </form>
            </div>
            
            <div class="card">
                <h2>All Exam Sessions</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Session Name</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Rooms</th>
                            <th>Capacity</th>
                            <th>Exams Count</th>
                            <th>Students Seated</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($session = $sessions->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($session['session_name']); ?></strong></td>
                            <td><?php echo date('M d, Y', strtotime($session['session_date'])); ?></td>
                            <td><?php echo date('h:i A', strtotime($session['start_time'])); ?> - <?php echo date('h:i A', strtotime($session['end_time'])); ?></td>
                            <td><?php echo $session['total_rooms'] ?? 1; ?></td>
                            <td><?php echo $session['seats_per_room'] ?? 30; ?> seats/room</td>
                            <td><span class="badge badge-primary"><?php echo $session['exam_count']; ?> Exams</span></td>
                            <td><span class="badge badge-success"><?php echo $session['seated_students']; ?> Students</span></td>
                            <td>
                                <?php if ($session['seated_students'] > 0): ?>
                                    <span class="badge badge-success">✓ Seating Generated</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">⚠ Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="?edit=1&session_id=<?php echo $session['id']; ?>" class="btn btn-small">
                                        ✏️ Edit
                                    </a>
                                    <a href="?delete=1&session_id=<?php echo $session['id']; ?>" 
                                       class="btn btn-danger btn-small"
                                       onclick="return confirm('Are you sure you want to delete this session? This action cannot be undone.')">
                                        🗑️ Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>