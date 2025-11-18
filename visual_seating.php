<?php
// visual_seating.php - Enhanced with Course Spacing Algorithm
include 'config.php';

$conn = getConnection();

// Generate visual seating arrangement with course spacing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_seating'])) {
    $session_id = intval($_POST['session_id']);
    
    // Clear existing seating
    $conn->query("DELETE FROM seating_arrangements WHERE session_id = $session_id");
    
    // Get session details
    $session = $conn->query("SELECT * FROM exam_sessions WHERE id = $session_id")->fetch_assoc();
    
    if ($session) {
        // Get all students enrolled in session exams
        $students_query = "
            SELECT s.*, e.id as exam_id, c.code as course_code, c.name as course_name, 
                   d.code as dept_code, d.name as dept_name, b.name as batch_name
            FROM exam_enrollments ee
            JOIN exams e ON ee.exam_id = e.id
            JOIN session_exams se ON e.id = se.exam_id
            JOIN students s ON ee.student_id = s.id
            JOIN courses c ON e.course_id = c.id
            JOIN departments d ON s.department_id = d.id
            JOIN batches b ON s.batch_id = b.id
            WHERE se.session_id = $session_id
            ORDER BY RAND()
        ";
        
        $students_result = $conn->query($students_query);
        $all_students = [];
        
        while ($student = $students_result->fetch_assoc()) {
            $all_students[] = $student;
        }
        
        if (count($all_students) > 0) {
            // Group students by course/exam
            $grouped_by_course = [];
            foreach ($all_students as $student) {
                $course_key = $student['exam_id'];
                if (!isset($grouped_by_course[$course_key])) {
                    $grouped_by_course[$course_key] = [];
                }
                $grouped_by_course[$course_key][] = $student;
            }
            
            // Prepare course groups
            $course_groups = array_values($grouped_by_course);
            $course_pointers = array_fill(0, count($course_groups), 0);
            
            // Seating parameters
            $total_rooms = $session['total_rooms'];
            $rows_per_room = $session['rows_per_room'];
            $min_gap_rows = 2; // Minimum 2 rows gap between same course students
            
            // Track last position of each course
            $last_course_position = []; // course_id => ['room' => X, 'row' => Y, 'position' => 'LEFT/RIGHT']
            
            $current_room = 1;
            $current_row = 1;
            $seat_position = 'LEFT';
            $total_seated = 0;
            $max_attempts = count($all_students) * 10;
            $attempts = 0;
            
            while ($total_seated < count($all_students) && $attempts < $max_attempts) {
                $attempts++;
                $seated_this_round = false;
                
                // Try to seat a student from each course group
                for ($i = 0; $i < count($course_groups); $i++) {
                    if ($course_pointers[$i] >= count($course_groups[$i])) continue;
                    
                    $student = $course_groups[$i][$course_pointers[$i]];
                    $exam_id = intval($student['exam_id']);
                    
                    // Check if this position violates the spacing rule
                    $can_seat_here = true;
                    
                    if (isset($last_course_position[$exam_id])) {
                        $last = $last_course_position[$exam_id];
                        
                        // Same room check
                        if ($last['room'] == $current_room) {
                            $row_diff = abs($current_row - $last['row']);
                            
                            // If in same or adjacent row, skip
                            if ($row_diff < $min_gap_rows) {
                                $can_seat_here = false;
                            }
                            
                            // If same row but different position, absolutely not allowed
                            if ($current_row == $last['row']) {
                                $can_seat_here = false;
                            }
                        }
                    }
                    
                    if ($can_seat_here) {
                        // Check if seat is already occupied
                        $check = $conn->query("SELECT id FROM seating_arrangements 
                            WHERE session_id = $session_id 
                            AND room_number = $current_room 
                            AND seat_row = $current_row 
                            AND seat_position = '$seat_position'");
                        
                        if ($check->num_rows == 0) {
                            // Seat is available, insert student
                            $stmt = $conn->prepare("INSERT INTO seating_arrangements 
                                (session_id, student_id, exam_id, room_number, seat_row, seat_position) 
                                VALUES (?, ?, ?, ?, ?, ?)");
                            
                            $student_id = intval($student['id']);
                            
                            $stmt->bind_param("iiiiss", $session_id, $student_id, $exam_id, 
                                              $current_room, $current_row, $seat_position);
                            
                            if ($stmt->execute()) {
                                // Update last position for this course
                                $last_course_position[$exam_id] = [
                                    'room' => $current_room,
                                    'row' => $current_row,
                                    'position' => $seat_position
                                ];
                                
                                $course_pointers[$i]++;
                                $total_seated++;
                                $seated_this_round = true;
                                
                                $stmt->close();
                                break; // Seat one student per iteration
                            }
                            
                            $stmt->close();
                        }
                    }
                }
                
                // Move to next position
                if ($seat_position == 'LEFT') {
                    $seat_position = 'RIGHT';
                } else {
                    $seat_position = 'LEFT';
                    $current_row++;
                    
                    // Move to next room if needed
                    if ($current_row > $rows_per_room) {
                        $current_room++;
                        $current_row = 1;
                        
                        if ($current_room > $total_rooms) {
                            // Wrap around or break
                            break;
                        }
                    }
                }
            }
            
            $success_message = "✅ Successfully generated seating for $total_seated students with proper spacing!";
        } else {
            $error_message = "❌ No students enrolled in this session!";
        }
    }
}

// Get all sessions
$sessions = $conn->query("
    SELECT es.*, COUNT(DISTINCT se.exam_id) as exam_count
    FROM exam_sessions es
    LEFT JOIN session_exams se ON es.id = se.session_id
    GROUP BY es.id
    ORDER BY es.session_date DESC
");

// Get selected session
$selected_session = isset($_GET['session_id']) ? intval($_GET['session_id']) : null;
$session_info = null;
$seating_data = [];

if ($selected_session) {
    $session_info = $conn->query("SELECT * FROM exam_sessions WHERE id = $selected_session")->fetch_assoc();
    
    if ($session_info) {
        // Get all seating arrangements
        $seating_result = $conn->query("
            SELECT sa.*, s.name as student_name, s.roll_number,
                   c.code as course_code, c.name as course_name,
                   d.code as dept_code, d.name as dept_name,
                   b.name as batch_name
            FROM seating_arrangements sa
            JOIN students s ON sa.student_id = s.id
            JOIN exams e ON sa.exam_id = e.id
            JOIN courses c ON e.course_id = c.id
            JOIN departments d ON s.department_id = d.id
            JOIN batches b ON s.batch_id = b.id
            WHERE sa.session_id = $selected_session
            ORDER BY sa.room_number, sa.seat_row, sa.seat_position
        ");
        
        while ($seat = $seating_result->fetch_assoc()) {
            $room = $seat['room_number'];
            $row = $seat['seat_row'];
            $pos = $seat['seat_position'];
            
            if (!isset($seating_data[$room])) {
                $seating_data[$room] = [];
            }
            if (!isset($seating_data[$room][$row])) {
                $seating_data[$room][$row] = ['LEFT' => null, 'RIGHT' => null];
            }
            
            $seating_data[$room][$row][$pos] = $seat;
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visual Seating Plan</title>

    <link rel ="stylesheet" href="style.css">
    <script src ="script.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1rem 2rem; }
        .navbar h1 { font-size: 1.5rem; }
        .container { display: flex; min-height: calc(100vh - 70px); }
        .sidebar { width: 250px; background: white; padding: 2rem 0; box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
        .sidebar a { display: block; padding: 1rem 2rem; color: #333; text-decoration: none; transition: all 0.3s; }
        .sidebar a:hover, .sidebar a.active { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .main-content { flex: 1; padding: 2rem; background: #fafafa; }
        .card { background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 1.5rem; }
        .card h2 { margin-bottom: 1rem; color: #333; }
        
        .alert { padding: 1rem; border-radius: 5px; margin-bottom: 1rem; font-weight: 500; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        
        .form-grid { display: grid; grid-template-columns: 1fr auto; gap: 1rem; align-items: end; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 0.5rem; color: #666; font-weight: 500; }
        .form-group select { padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; }
        .btn { padding: 0.75rem 1.5rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 5px; cursor: pointer; }
        
        .info-box {
            background: #e3f2fd;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid #1976d2;
        }
        .info-box strong { color: #1976d2; }
        
        /* Visual Classroom Layout */
        .classroom-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .room-title {
            text-align: center;
            font-size: 1.5rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 1rem;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
            border-radius: 8px;
        }
        
        .teacher-desk {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-radius: 10px;
            font-weight: bold;
            font-size: 1.2rem;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .classroom-rows {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .row-container {
            display: grid;
            grid-template-columns: 50px 1fr 60px 1fr 50px;
            gap: 1rem;
            align-items: center;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .row-label {
            font-weight: bold;
            color: #667eea;
            text-align: center;
            font-size: 0.9rem;
        }
        
        .aisle {
            text-align: center;
            font-size: 1.5rem;
            color: #ccc;
            font-weight: bold;
        }
        
        .desk {
            background: white;
            border: 3px solid #e0e0e0;
            border-radius: 10px;
            padding: 0.75rem;
            min-height: 80px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            transition: all 0.3s;
            position: relative;
        }
        
        .desk:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        .desk.occupied {
            border-color: #667eea;
            background: linear-gradient(135deg, #ffffff 0%, #f0f4ff 100%);
        }
        
        .desk.empty {
            background: #f5f5f5;
            border-style: dashed;
            border-color: #ddd;
            opacity: 0.5;
        }
        
        .student-info {
            text-align: center;
        }
        
        .student-name {
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .student-roll {
            font-size: 0.75rem;
            color: #666;
            margin-bottom: 0.25rem;
        }
        
        .course-tag {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-top: 0.25rem;
        }
        
        .dept-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            padding: 0.15rem 0.4rem;
            border-radius: 8px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        .dept-CS { background: #e3f2fd; color: #1976d2; }
        .dept-EC { background: #f3e5f5; color: #7b1fa2; }
        .dept-ME { background: #e8f5e9; color: #388e3c; }
        .dept-CE { background: #fff3e0; color: #f57c00; }
        
        .course-CS201, .course-CS301, .course-CS302 { background: #e3f2fd; color: #1565c0; }
        .course-EC202, .course-EC301 { background: #f3e5f5; color: #6a1b9a; }
        .course-ME301 { background: #e8f5e9; color: #2e7d32; }
        .course-CE301 { background: #fff3e0; color: #e65100; }
        
        .legend {
            display: flex;
            gap: 2rem;
            justify-content: center;
            flex-wrap: wrap;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-top: 2rem;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .legend-box {
            width: 30px;
            height: 30px;
            border-radius: 5px;
            border: 2px solid;
        }
        
        .print-btn {
            background: #28a745;
            float: right;
        }
        
        @media print {
            .navbar, .sidebar, .no-print { display: none !important; }
            .main-content { padding: 0; }
            .classroom-container { break-inside: avoid; page-break-inside: avoid; }
        }
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
            <a href="students.php">Students</a>
            <a href="courses.php"> Courses</a>
            <a href="student_course_enrollment.php"> Course Enrollment</a>
            <a href="sessions.php"> Exam Sessions</a>
            <a href="exams.php"> Exams</a>
            <a href="enroll_students.php"> Enroll Students</a>
            <a href="visual_seating.php" class="active"> Visual Seating Plan</a>
        </div>
        
        <div class="main-content">
            <h2 style="margin-bottom: 1.5rem; color: #333;">Visual Seating Arrangement</h2>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <div class="card no-print">
                <h2> Generate Seating Plan</h2>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Select Exam Session</label>
                            <select name="session_id" required>
                                <option value="">Choose a session</option>
                                <?php 
                                $sessions->data_seek(0);
                                while ($session = $sessions->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $session['id']; ?>">
                                    <?php echo htmlspecialchars($session['session_name'] . ' - ' . date('M d, Y', strtotime($session['session_date']))); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <button type="submit" name="generate_seating" class="btn">🎲 Generate Plan</button>
                    </div>
                </form>
            </div>
            
            <div class="card no-print">
                <h2> View Seating Plan</h2>
                <form method="GET">
                    <div class="form-group">
                        <select name="session_id" onchange="this.form.submit()">
                            <option value="">Select a session to view</option>
                            <?php 
                            $sessions->data_seek(0);
                            while ($session = $sessions->fetch_assoc()): 
                            ?>
                            <option value="<?php echo $session['id']; ?>" <?php echo ($session['id'] == $selected_session) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($session['session_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </form>
            </div>
            
            <?php if ($session_info && count($seating_data) > 0): ?>
                <div class="no-print" style="text-align: right; margin-bottom: 1rem;">
                    <button onclick="window.print()" class="btn print-btn">🖨️ Print Seating Plan</button>
                </div>
                
                <?php foreach ($seating_data as $room_num => $rows): ?>
                <div class="classroom-container">
                    <div class="room-title">
                        🏛️ EXAMINATION HALL - Room <?php echo $room_num; ?>
                    </div>
                    
                    <div class="teacher-desk">
                        👨‍🏫 INVIGILATOR / TEACHER DESK
                    </div>
                    
                    <div class="classroom-rows">
                        <?php 
                        ksort($rows);
                        foreach ($rows as $row_num => $seats): 
                        ?>
                        <div class="row-container">
                            <div class="row-label">Row<br><?php echo $row_num; ?></div>
                            
                            <!-- LEFT SEAT -->
                            <?php if ($seats['LEFT']): 
                                $student = $seats['LEFT'];
                            ?>
                            <div class="desk occupied">
                                <span class="dept-badge dept-<?php echo $student['dept_code']; ?>">
                                    <?php echo $student['dept_code']; ?>
                                </span>
                                <div class="student-info">
                                    <div class="student-name"><?php echo htmlspecialchars($student['student_name']); ?></div>
                                    <div class="student-roll"><?php echo htmlspecialchars($student['roll_number']); ?></div>
                                    <span class="course-tag course-<?php echo $student['course_code']; ?>">
                                        <?php echo htmlspecialchars($student['course_code']); ?>
                                    </span>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="desk empty">
                                <div class="student-info" style="color: #999;">Empty Seat</div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- AISLE -->
                            <div class="aisle">🚶</div>
                            
                            <!-- RIGHT SEAT -->
                            <?php if ($seats['RIGHT']): 
                                $student = $seats['RIGHT'];
                            ?>
                            <div class="desk occupied">
                                <span class="dept-badge dept-<?php echo $student['dept_code']; ?>">
                                    <?php echo $student['dept_code']; ?>
                                </span>
                                <div class="student-info">
                                    <div class="student-name"><?php echo htmlspecialchars($student['student_name']); ?></div>
                                    <div class="student-roll"><?php echo htmlspecialchars($student['roll_number']); ?></div>
                                    <span class="course-tag course-<?php echo $student['course_code']; ?>">
                                        <?php echo htmlspecialchars($student['course_code']); ?>
                                    </span>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="desk empty">
                                <div class="student-info" style="color: #999;">Empty Seat</div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="row-label">Row<br><?php echo $row_num; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="legend">
                        <div class="legend-item">
                            <div class="legend-box dept-CS"></div>
                            <span>Computer Science</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-box dept-EC"></div>
                            <span>Electronics</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-box dept-ME"></div>
                            <span>Mechanical</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-box dept-CE"></div>
                            <span>Civil</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php elseif ($selected_session): ?>
                <div class="card">
                    <p style="text-align: center; padding: 2rem; color: #666;">
                        ⚠️ No seating arrangement generated yet.<br><br>
                        Click "Generate Plan" to create a visual seating arrangement.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>