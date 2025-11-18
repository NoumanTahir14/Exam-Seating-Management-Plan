<?php
// index.php - Updated Dashboard
include 'config.php';

$conn = getConnection();

// Get statistics
$totalStudents = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
$totalDepartments = $conn->query("SELECT COUNT(*) as count FROM departments")->fetch_assoc()['count'];
$totalCourses = $conn->query("SELECT COUNT(*) as count FROM courses")->fetch_assoc()['count'];
$totalExams = $conn->query("SELECT COUNT(*) as count FROM exams")->fetch_assoc()['count'];
$totalSessions = $conn->query("SELECT COUNT(*) as count FROM exam_sessions")->fetch_assoc()['count'];

// Get upcoming exam sessions
$upcomingSessions = $conn->query("
    SELECT es.*, 
    GROUP_CONCAT(DISTINCT c.code ORDER BY c.code SEPARATOR ', ') as course_codes,
    COUNT(DISTINCT se.exam_id) as exam_count,
    COUNT(DISTINCT sa.student_id) as student_count
    FROM exam_sessions es
    LEFT JOIN session_exams se ON es.id = se.session_id
    LEFT JOIN exams e ON se.exam_id = e.id
    LEFT JOIN courses c ON e.course_id = c.id
    LEFT JOIN seating_arrangements sa ON es.id = sa.session_id
    WHERE es.session_date >= CURDATE()
    GROUP BY es.id
    ORDER BY es.session_date, es.start_time
    LIMIT 5
");

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Seating Management - Dashboard</title>
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card:nth-child(1) { border-left-color: #667eea; }
        .stat-card:nth-child(2) { border-left-color: #f093fb; }
        .stat-card:nth-child(3) { border-left-color: #4facfe; }
        .stat-card:nth-child(4) { border-left-color: #43e97b; }
        .stat-card:nth-child(5) { border-left-color: #fa709a; }
        
        .stat-card h3 {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .stat-card .number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #333;
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
            margin-right: 0.25rem;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-primary {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>📚 Exam Seating Management System</h1>
    </div>
    
    <div class="container">
        <div class="sidebar">
            <a href="index.php"class="active"> Dashboard</a>
            <a href="departments.php"> Departments/Batches</a>
            <a href="students.php"> Students</a>
            <a href="courses.php"> Courses</a>
            <a href="student_course_enrollment.php"> Course Enrollment</a>
            <a href="sessions.php"> Exam Sessions</a>
            <a href="exams.php"> Exams</a>
            <a href="enroll_students.php"> Enroll Students</a>
            <a href="visual_seating.php" > Visual Seating Plan</a>
        </div>
        
        <div class="main-content">
            <h2 style="margin-bottom: 1.5rem; color: #333;">Dashboard Overview</h2>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Students</h3>
                    <div class="number"><?php echo $totalStudents; ?></div>
                </div>
                
                <div class="stat-card">
                    <h3>Departments</h3>
                    <div class="number"><?php echo $totalDepartments; ?></div>
                </div>
                
                <div class="stat-card">
                    <h3>Courses</h3>
                    <div class="number"><?php echo $totalCourses; ?></div>
                </div>
                
                <div class="stat-card">
                    <h3>Total Exams</h3>
                    <div class="number"><?php echo $totalExams; ?></div>
                </div>
                
                <div class="stat-card">
                    <h3>Exam Sessions</h3>
                    <div class="number"><?php echo $totalSessions; ?></div>
                </div>
            </div>
            
            <div class="card">
                <h2>Upcoming Exam Sessions</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Session Name</th>
                            <th>Courses</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Exams</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($upcomingSessions->num_rows > 0): ?>
                            <?php while ($session = $upcomingSessions->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($session['session_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($session['course_codes']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($session['session_date'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($session['start_time'])); ?> - <?php echo date('h:i A', strtotime($session['end_time'])); ?></td>
                                <td><span class="badge badge-primary"><?php echo $session['exam_count']; ?> Exams</span></td>
                                <td>
                                    <?php if ($session['student_count'] > 0): ?>
                                        <span class="badge badge-success">✓ <?php echo $session['student_count']; ?> Seated</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">⚠ Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: #666;">No upcoming sessions scheduled</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>