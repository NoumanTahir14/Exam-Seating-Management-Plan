<?php
// enroll_students.php - Enroll students in exams
include 'config.php';

$conn = getConnection();

// Enroll students in exam
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_students'])) {
    $exam_id = intval($_POST['exam_id']);
    
    if (isset($_POST['student_ids']) && is_array($_POST['student_ids'])) {
        $success_count = 0;
        foreach ($_POST['student_ids'] as $student_id) {
            $student_id = intval($student_id);
            $result = $conn->query("INSERT IGNORE INTO exam_enrollments (exam_id, student_id) 
                                    VALUES ($exam_id, $student_id)");
            if ($result) {
                $success_count++;
            }
        }
           $success_message = "✅ Enrolled $success_count students successfully!";
    }
}

// Remove enrollment
if (isset($_GET['remove']) && isset($_GET['enrollment_id'])) {
    $enrollment_id = intval($_GET['enrollment_id']);
    $conn->query("DELETE FROM exam_enrollments WHERE id = $enrollment_id");
    header("Location: enroll_students.php");
    exit;
}

// Get all exams
$exams = $conn->query("
    SELECT e.*, c.name as course_name, c.code as course_code, d.name as dept_name
    FROM exams e
    JOIN courses c ON e.course_id = c.id
    JOIN departments d ON c.department_id = d.id
    ORDER BY e.exam_date DESC
");

// Get selected exam details if any
$selected_exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$selected_exam = null;
$enrolled_students = null;
$available_students = null;

if ($selected_exam_id > 0) {
    // Get exam details
    $selected_exam = $conn->query("
        SELECT e.*, c.name as course_name, c.code as course_code, c.department_id
        FROM exams e
        JOIN courses c ON e.course_id = c.id
        WHERE e.id = $selected_exam_id
    ")->fetch_assoc();
    
    if ($selected_exam) {
        $course_dept_id = $selected_exam['department_id'];
        
        // Get already enrolled students
        $enrolled_students = $conn->query("
            SELECT ee.id as enrollment_id, s.*, b.name as batch_name, d.name as dept_name, d.code as dept_code
            FROM exam_enrollments ee
            JOIN students s ON ee.student_id = s.id
            JOIN batches b ON s.batch_id = b.id
            JOIN departments d ON s.department_id = d.id
            WHERE ee.exam_id = $selected_exam_id
            ORDER BY s.name
        ");
        
        // Get available students (from same department, not enrolled)
        $available_students = $conn->query("
            SELECT s.*, b.name as batch_name, d.name as dept_name, d.code as dept_code
            FROM students s
            JOIN batches b ON s.batch_id = b.id
            JOIN departments d ON s.department_id = d.id
            WHERE s.department_id = $course_dept_id
            AND s.id NOT IN (SELECT student_id FROM exam_enrollments WHERE exam_id = $selected_exam_id)
            ORDER BY s.name
        ");
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enroll Students in Exams</title>

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
        .form-group { display: flex; flex-direction: column; margin-bottom: 1rem; }
        .form-group label { margin-bottom: 0.5rem; color: #666; font-weight: 500; }
        .form-group select { padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem; }
        .btn { padding: 0.75rem 1.5rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4); }
        .btn-danger { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .btn-small { padding: 0.5rem 1rem; font-size: 0.85rem; }
        .alert { padding: 1rem; border-radius: 5px; margin-bottom: 1rem; font-weight: 500; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; color: #333; }
        .badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.85rem; font-weight: 500; }
        .dept-cs { background: #e3f2fd; color: #1976d2; }
        .dept-ec { background: #f3e5f5; color: #7b1fa2; }
        .dept-me { background: #e8f5e9; color: #388e3c; }
        .dept-ce { background: #fff3e0; color: #f57c00; }
        .student-selection {
            border: 1px solid #ddd;
            padding: 1rem;
            border-radius: 5px;
            max-height: 400px;
            overflow-y: auto;
            background: #f8f9fa;
        }
        .student-checkbox {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            background: white;
            border-radius: 5px;
            border: 1px solid #eee;
        }
        .student-checkbox:hover {
            background: #f0f7ff;
        }
        .student-checkbox input {
            margin-right: 1rem;
            width: 20px;
            height: 20px;
        }
        .student-info {
            flex: 1;
        }
        .student-name {
            font-weight: 600;
            color: #333;
        }
        .student-details {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.25rem;
        }
        .select-all {
            padding: 0.75rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 1rem;
            width: 100%;
        }
        .exam-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid #667eea;
        }
        .exam-info h3 {
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        .stats {
            display: flex;
            gap: 2rem;
            margin-top: 0.5rem;
        }
        .stat-item {
            font-size: 0.9rem;
        }
        .stat-item strong {
            color: #667eea;
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
            <a href="students.php"> Students</a>
            <a href="courses.php"> Courses</a>
            <a href="student_course_enrollment.php"> Course Enrollment</a>
            <a href="sessions.php"> Exam Sessions</a>
            <a href="exams.php">Exams</a>
            <a href="enroll_students.php"class="active"> Enroll Students</a>
            <a href="visual_seating.php" > Visual Seating Plan</a>
        </div>
        
        <div class="main-content">
            <h2 style="margin-bottom: 1.5rem; color: #333;">Enroll Students in Exams</h2>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <div class="card">
                <h2>Select Exam</h2>
                <form method="GET">
                    <div class="form-group">
                        <label>Choose an Exam</label>
                        <select name="exam_id" onchange="this.form.submit()" required>
                            <option value="">-- Select an exam --</option>
                            <?php 
                            $exams->data_seek(0);
                            while ($exam = $exams->fetch_assoc()): 
                            ?>
                            <option value="<?php echo $exam['id']; ?>" <?php echo ($exam['id'] == $selected_exam_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($exam['name'] . ' - ' . $exam['course_name'] . ' (' . $exam['course_code'] . ') - ' . date('M d, Y', strtotime($exam['exam_date']))); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </form>
            </div>
            
            <?php if ($selected_exam): ?>
                <div class="exam-info">
                    <h3>📝 <?php echo htmlspecialchars($selected_exam['name']); ?></h3>
                    <p><strong>Course:</strong> <?php echo htmlspecialchars($selected_exam['course_name'] . ' (' . $selected_exam['course_code'] . ')'); ?></p>
                    <div class="stats">
                        <div class="stat-item">
                            <strong><?php echo $enrolled_students->num_rows; ?></strong> students enrolled
                        </div>
                        <div class="stat-item">
                            <strong><?php echo $available_students->num_rows; ?></strong> students available
                        </div>
                    </div>
                </div>
                
                <?php if ($available_students->num_rows > 0): ?>
                <div class="card">
                    <h2>Enroll Students</h2>
                    <form method="POST">
                        <input type="hidden" name="exam_id" value="<?php echo $selected_exam_id; ?>">
                        
                        <button type="button" class="select-all" onclick="toggleSelectAll()">
                            ✓ Select All / Deselect All
                        </button>
                        
                        <div class="student-selection">
                            <?php while ($student = $available_students->fetch_assoc()): 
                                $dept_class = 'dept-cs';
                                if (strpos($student['dept_code'], 'EC') !== false) $dept_class = 'dept-ec';
                                else if (strpos($student['dept_code'], 'ME') !== false) $dept_class = 'dept-me';
                                else if (strpos($student['dept_code'], 'CE') !== false) $dept_class = 'dept-ce';
                            ?>
                            <div class="student-checkbox">
                                <input type="checkbox" name="student_ids[]" value="<?php echo $student['id']; ?>" class="student-check">
                                <div class="student-info">
                                    <div class="student-name"><?php echo htmlspecialchars($student['name']); ?></div>
                                    <div class="student-details">
                                        🎓 <?php echo htmlspecialchars($student['roll_number']); ?> | 
                                        📚 <?php echo htmlspecialchars($student['batch_name']); ?> | 
                                        <span class="badge <?php echo $dept_class; ?>"><?php echo htmlspecialchars($student['dept_code']); ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                        
                        <button type="submit" name="enroll_students" class="btn" style="margin-top: 1rem;">
                            Enroll Selected Students
                        </button>
                    </form>
                </div>
                <?php endif; ?>
                
                <?php if ($enrolled_students->num_rows > 0): ?>
                <div class="card">
                    <h2>Currently Enrolled Students</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Roll Number</th>
                                <th>Student Name</th>
                                <th>Department</th>
                                <th>Batch</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($student = $enrolled_students->fetch_assoc()): 
                                $dept_class = 'dept-cs';
                                if (strpos($student['dept_code'], 'EC') !== false) $dept_class = 'dept-ec';
                                else if (strpos($student['dept_code'], 'ME') !== false) $dept_class = 'dept-me';
                                else if (strpos($student['dept_code'], 'CE') !== false) $dept_class = 'dept-ce';
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($student['roll_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($student['dept_name']); ?>
                                    <span class="badge <?php echo $dept_class; ?>"><?php echo htmlspecialchars($student['dept_code']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($student['batch_name']); ?></td>
                                <td>
                                    <a href="?remove=1&enrollment_id=<?php echo $student['enrollment_id']; ?>" 
                                       class="btn btn-danger btn-small" 
                                       onclick="return confirm('Remove this student from the exam?')">
                                        Remove
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function toggleSelectAll() {
            const checkboxes = document.querySelectorAll('.student-check');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = !allChecked;
            });
        }
    </script>
</body>

</html>
