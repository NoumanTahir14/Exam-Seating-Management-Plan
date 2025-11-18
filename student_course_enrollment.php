<?php
include 'config.php';

$conn = getConnection();

$success_message = '';
$error_message = '';

// Enroll student in courses
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_student'])) {
    $student_id = intval($_POST['student_id']);
    $course_ids = $_POST['course_ids'] ?? [];
    
    if (count($course_ids) > 0) {
        $enrolled_count = 0;
        foreach ($course_ids as $course_id) {
            $course_id = intval($course_id);
            $result = $conn->query("INSERT IGNORE INTO course_enrollments (student_id, course_id) 
                                    VALUES ($student_id, $course_id)");
            if ($result) {
                $enrolled_count++;
                
                // Auto-enroll in existing exams for this course
                $exams = $conn->query("SELECT id FROM exams WHERE course_id = $course_id");
                while ($exam = $exams->fetch_assoc()) {
                    $conn->query("INSERT IGNORE INTO exam_enrollments (exam_id, student_id) 
                                 VALUES ({$exam['id']}, $student_id)");
                }
            }
        }
        $success_message = "✅ Successfully enrolled student in $enrolled_count course(s)!";
    }
}

// Remove course enrollment
if (isset($_GET['remove']) && isset($_GET['enrollment_id'])) {
    $enrollment_id = intval($_GET['enrollment_id']);
    
    // Get course_id before deleting
    $enrollment = $conn->query("SELECT student_id, course_id FROM course_enrollments WHERE id = $enrollment_id")->fetch_assoc();
    
    if ($enrollment) {
        $conn->query("DELETE FROM course_enrollments WHERE id = $enrollment_id");
        
        // Also remove from related exams
        $conn->query("DELETE ee FROM exam_enrollments ee 
                     JOIN exams e ON ee.exam_id = e.id 
                     WHERE ee.student_id = {$enrollment['student_id']} 
                     AND e.course_id = {$enrollment['course_id']}");
        
        $success_message = "✅ Course enrollment removed successfully!";
    }
}

// Get all students
$students = $conn->query("
    SELECT s.*, d.name as dept_name, d.code as dept_code, b.name as batch_name
    FROM students s
    JOIN departments d ON s.department_id = d.id
    JOIN batches b ON s.batch_id = b.id
    ORDER BY s.name
");

// Get selected student's info if any
$selected_student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$selected_student = null;
$available_courses = null;
$enrolled_courses = null;

if ($selected_student_id > 0) {
    $selected_student = $conn->query("
        SELECT s.*, d.id as dept_id, d.name as dept_name, d.code as dept_code, b.name as batch_name
        FROM students s
        JOIN departments d ON s.department_id = d.id
        JOIN batches b ON s.batch_id = b.id
        WHERE s.id = $selected_student_id
    ")->fetch_assoc();
    
    if ($selected_student) {
        // Get available courses for this student's department
        $available_courses = $conn->query("
            SELECT c.* 
            FROM courses c
            WHERE c.department_id = {$selected_student['dept_id']}
            AND c.id NOT IN (
                SELECT course_id FROM course_enrollments WHERE student_id = $selected_student_id
            )
            ORDER BY c.name
        ");
        
        // Get enrolled courses
        $enrolled_courses = $conn->query("
            SELECT ce.id as enrollment_id, c.*, ce.enrollment_date,
            (SELECT COUNT(*) FROM exams WHERE course_id = c.id) as exam_count
            FROM course_enrollments ce
            JOIN courses c ON ce.course_id = c.id
            WHERE ce.student_id = $selected_student_id
            ORDER BY c.name
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
    <title>Student Course Enrollment</title>

    <link rel ="stylesheet" href="style.css">
    <script src ="script.js"></script>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar h1 {
            font-size: 1.5rem;
        }
        
        .container {
            display: flex;
            min-height: calc(100vh - 70px);
        }
        
        .sidebar {
            width: 250px;
            background: white;
            padding: 2rem 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar a {
            display: block;
            padding: 1rem 2rem;
            color: #333;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar a:hover, .sidebar a.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .main-content {
            flex: 1;
            padding: 2rem;
        }
        
        .card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        
        .card h2 {
            margin-bottom: 1rem;
            color: #333;
        }
        
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
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 0.5rem;
            color: #666;
            font-weight: 500;
        }
        
        .form-group select {
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .course-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .course-card {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 1.5rem;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .course-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }
        
        .course-card.selected {
            border-color: #667eea;
            background: #f0f4ff;
        }
        
        .course-header {
            display: flex;
            align-items: start;
            gap: 1rem;
        }
        
        .course-checkbox {
            width: 24px;
            height: 24px;
            cursor: pointer;
        }
        
        .course-code {
            font-size: 1.2rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .course-name {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .course-credits {
            color: #666;
            font-size: 0.9rem;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .badge-info {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .student-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #667eea;
        }
        
        .student-info h3 {
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .no-data {
            text-align: center;
            padding: 3rem;
            color: #999;
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
            <a href="student_course_enrollment.php" class="active"> Course Enrollment</a>
            <a href="sessions.php"> Exam Sessions</a>
            <a href="exams.php"> Exams</a>
            <a href="enroll_students.php"> Enroll Students</a>
            <a href="visual_seating.php"> Visual Seating Plan</a>
        </div>
        
        <div class="main-content">
            <h2 style="margin-bottom: 1.5rem; color: #333;">Student Course Enrollment</h2>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <div class="card">
                <h2>👨‍🎓 Select Student</h2>
                <form method="GET">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Choose Student</label>
                            <select name="student_id" onchange="this.form.submit()" required>
                                <option value="">-- Select a student --</option>
                                <?php 
                                $students->data_seek(0);
                                while ($student = $students->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $student['id']; ?>" <?php echo ($student['id'] == $selected_student_id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($student['name'] . ' (' . $student['roll_number'] . ') - ' . $student['dept_code']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            
            <?php if ($selected_student): ?>
                <div class="student-info">
                    <h3>📋 Student Information</h3>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($selected_student['name']); ?></p>
                    <p><strong>Roll Number:</strong> <?php echo htmlspecialchars($selected_student['roll_number']); ?></p>
                    <p><strong>Department:</strong> <?php echo htmlspecialchars($selected_student['dept_name']); ?> (<?php echo htmlspecialchars($selected_student['dept_code']); ?>)</p>
                    <p><strong>Batch:</strong> <?php echo htmlspecialchars($selected_student['batch_name']); ?></p>
                </div>
                
                <?php if ($available_courses->num_rows > 0): ?>
                <div class="card">
                    <h2>📖 Available Courses for Enrollment</h2>
                    <form method="POST">
                        <input type="hidden" name="student_id" value="<?php echo $selected_student_id; ?>">
                        
                        <button type="button" class="btn btn-success" onclick="toggleSelectAll()" style="margin-bottom: 1rem;">
                            ✓ Select All / Deselect All
                        </button>
                        
                        <div class="course-grid">
                            <?php while ($course = $available_courses->fetch_assoc()): ?>
                            <div class="course-card" onclick="toggleCard(this)">
                                <div class="course-header">
                                    <input type="checkbox" 
                                           name="course_ids[]" 
                                           value="<?php echo $course['id']; ?>"
                                           class="course-checkbox"
                                           onclick="event.stopPropagation();">
                                    <div>
                                        <div class="course-code"><?php echo htmlspecialchars($course['code']); ?></div>
                                        <div class="course-name"><?php echo htmlspecialchars($course['name']); ?></div>
                                        <div class="course-credits">📊 <?php echo $course['credits']; ?> Credits</div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                        
                        <button type="submit" name="enroll_student" class="btn">
                            ✅ Enroll in Selected Courses
                        </button>
                    </form>
                </div>
                <?php else: ?>
                <div class="card">
                    <div class="no-data">
                        ✅ Student is enrolled in all available courses for their department!
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="card">
                    <h2>📚 Currently Enrolled Courses</h2>
                    <?php if ($enrolled_courses->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Course Code</th>
                                    <th>Course Name</th>
                                    <th>Credits</th>
                                    <th>Exams</th>
                                    <th>Enrolled Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($course = $enrolled_courses->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($course['code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($course['name']); ?></td>
                                    <td><span class="badge badge-info"><?php echo $course['credits']; ?> Credits</span></td>
                                    <td><span class="badge badge-success"><?php echo $course['exam_count']; ?> Exam(s)</span></td>
                                    <td><?php echo date('M d, Y', strtotime($course['enrollment_date'])); ?></td>
                                    <td>
                                        <a href="?student_id=<?php echo $selected_student_id; ?>&remove=1&enrollment_id=<?php echo $course['enrollment_id']; ?>" 
                                           class="btn btn-danger btn-small"
                                           onclick="return confirm('Remove this course enrollment? Student will also be removed from related exams.')">
                                            🗑️ Remove
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-data">
                            📋 No courses enrolled yet
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function toggleCard(card) {
            const checkbox = card.querySelector('.course-checkbox');
            checkbox.checked = !checkbox.checked;
            card.classList.toggle('selected', checkbox.checked);
        }
        
        function toggleSelectAll() {
            const checkboxes = document.querySelectorAll('.course-checkbox');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = !allChecked;
                checkbox.closest('.course-card').classList.toggle('selected', !allChecked);
            });
        }
        
        // Initialize card states
        document.querySelectorAll('.course-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                this.closest('.course-card').classList.toggle('selected', this.checked);
            });
        });
    </script>
</body>
</html>