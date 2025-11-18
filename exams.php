<?php
include 'config.php';

$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_exam'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $course_id = intval($_POST['course_id']);
    $exam_date = $_POST['exam_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    
    $result = $conn->query("INSERT INTO exams (name, course_id, exam_date, start_time, end_time) 
                  VALUES ('$name', $course_id, '$exam_date', '$start_time', '$end_time')");
    
    if ($result) {
        $exam_id = $conn->insert_id;
        
        // AUTO-ENROLL: Enroll all students who are taking this course
        $conn->query("
            INSERT INTO exam_enrollments (exam_id, student_id)
            SELECT $exam_id, student_id 
            FROM course_enrollments 
            WHERE course_id = $course_id
        ");
    }
    
    header("Location: exams.php");
    exit;
}

$courses = $conn->query("SELECT c.*, d.name as dept_name FROM courses c JOIN departments d ON c.department_id = d.id ORDER BY c.name");

$exams = $conn->query("
    SELECT e.*, c.name as course_name, c.code as course_code, d.name as dept_name,
    COUNT(DISTINCT ee.student_id) as student_count
    FROM exams e
    JOIN courses c ON e.course_id = c.id
    JOIN departments d ON c.department_id = d.id
    LEFT JOIN exam_enrollments ee ON e.id = ee.exam_id
    GROUP BY e.id
    ORDER BY e.exam_date DESC, e.start_time
");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exams</title>

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
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 0.5rem; color: #666; font-weight: 500; }
        .form-group input, .form-group select { padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem; }
        .btn { padding: 0.75rem 1.5rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; color: #333; }
        .badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.85rem; font-weight: 500; background: #d4edda; color: #155724; }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>📚 Exam Seating Management System</h1>
    </div>
    
    <div class="container">
        <div class="sidebar">
            <a href="index.php">Dashboard</a>
            <a href="departments.php"> Departments/Batches</a>
            <a href="students.php"> Students</a>
            <a href="courses.php"> Courses</a>
            <a href="student_course_enrollment.php"> Course Enrollment</a>
            <a href="sessions.php"> Exam Sessions</a>
            <a href="exams.php" class="active"> Exams</a>
            <a href="enroll_students.php"> Enroll Students</a>
            <a href="visual_seating.php"> Visual Seating Plan</a>
        </div>
        
        <div class="main-content">
            <h2 style="margin-bottom: 1.5rem; color: #333;">Exam Management</h2>
            
            <div class="card">
                <h2>Schedule New Exam</h2>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Exam Name</label>
                            <input type="text" name="name" placeholder="e.g., Mid Semester" required>
                        </div>
                        <div class="form-group">
                            <label>Course</label>
                            <select name="course_id" required>
                                <option value="">Select Course</option>
                                <?php 
                                $courses->data_seek(0);
                                while ($course = $courses->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['code'] . ' - ' . $course['name']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Exam Date</label>
                            <input type="date" name="exam_date" required>
                        </div>
                        <div class="form-group">
                            <label>Start Time</label>
                            <input type="time" name="start_time" required>
                        </div>
                        <div class="form-group">
                            <label>End Time</label>
                            <input type="time" name="end_time" required>
                        </div>
                    </div>
                    <button type="submit" name="add_exam" class="btn">Schedule Exam</button>
                </form>
            </div>
            
            <div class="card">
                <h2>All Scheduled Exams</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Exam Name</th>
                            <th>Course</th>
                            <th>Department</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Students Enrolled</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($exam = $exams->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($exam['name']); ?></td>
                            <td><?php echo htmlspecialchars($exam['course_name']); ?> (<?php echo htmlspecialchars($exam['course_code']); ?>)</td>
                            <td><?php echo htmlspecialchars($exam['dept_name']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($exam['exam_date'])); ?></td>
                            <td><?php echo date('h:i A', strtotime($exam['start_time'])); ?> - <?php echo date('h:i A', strtotime($exam['end_time'])); ?></td>
                            <td><span class="badge"><?php echo $exam['student_count']; ?> Students</span></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>