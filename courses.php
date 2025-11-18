<?php
// courses.php
include 'config.php';

$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_course'])) {
    $dept_id = intval($_POST['department_id']);
    $name = $conn->real_escape_string($_POST['name']);
    $code = $conn->real_escape_string($_POST['code']);
    $credits = intval($_POST['credits']);
    $conn->query("INSERT INTO courses (department_id, name, code, credits) VALUES ($dept_id, '$name', '$code', $credits)");
    header("Location: courses.php");
    exit;
}

$departments = $conn->query("SELECT * FROM departments ORDER BY name");
$courses = $conn->query("
    SELECT c.*, d.name as dept_name, d.code as dept_code
    FROM courses c
    JOIN departments d ON c.department_id = d.id
    ORDER BY d.name, c.name
");

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Courses</title>

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
            <a href="courses.php"class="active"> Courses</a>
            <a href="student_course_enrollment.php"> Course Enrollment</a>
            <a href="sessions.php"> Exam Sessions</a>
            <a href="exams.php"> Exams</a>
            <a href="enroll_students.php"> Enroll Students</a>
            <a href="visual_seating.php" > Visual Seating Plan</a>
        </div>
        
        <div class="main-content">
            <h2 style="margin-bottom: 1.5rem; color: #333;">Course Management</h2>
            
            <div class="card">
                <h2>Add New Course</h2>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Department</label>
                            <select name="department_id" required>
                                <option value="">Select Department</option>
                                <?php while ($dept = $departments->fetch_assoc()): ?>
                                <option value="<?php echo $dept['id']; ?>">
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Course Name</label>
                            <input type="text" name="name" required>
                        </div>
                        <div class="form-group">
                            <label>Course Code</label>
                            <input type="text" name="code" required>
                        </div>
                        <div class="form-group">
                            <label>Credits</label>
                            <input type="number" name="credits" min="1" max="10" value="3" required>
                        </div>
                    </div>
                    <button type="submit" name="add_course" class="btn">Add Course</button>
                </form>
            </div>
            
            <div class="card">
                <h2>All Courses</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Course Code</th>
                            <th>Course Name</th>
                            <th>Department</th>
                            <th>Credits</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($course = $courses->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($course['code']); ?></td>
                            <td><?php echo htmlspecialchars($course['name']); ?></td>
                            <td><?php echo htmlspecialchars($course['dept_name']); ?></td>
                            <td><?php echo $course['credits']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>