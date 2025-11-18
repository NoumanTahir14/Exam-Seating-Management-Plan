<?php
// students.php - Student Management
include 'config.php';

$conn = getConnection();

// Add new student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $roll_number = $conn->real_escape_string($_POST['roll_number']);
    $batch_id = intval($_POST['batch_id']);
    $department_id = intval($_POST['department_id']);
    
    $result = $conn->query("INSERT INTO students (name, roll_number, batch_id, department_id) 
                            VALUES ('$name', '$roll_number', $batch_id, $department_id)");
    
    if ($result) {
        $success_message = "✅ Student added successfully!";
    } else {
        $error_message = "❌ Error: " . $conn->error;
    }
}

// Bulk import students
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_import'])) {
    $batch_id = intval($_POST['bulk_batch_id']);
    $department_id = intval($_POST['bulk_department_id']);
    $students_data = $_POST['students_bulk'];
    
    $lines = explode("\n", $students_data);
    $imported = 0;
    $errors = 0;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        $parts = explode(',', $line);
        if (count($parts) >= 2) {
            $name = $conn->real_escape_string(trim($parts[0]));
            $roll = $conn->real_escape_string(trim($parts[1]));
            
            $result = $conn->query("INSERT INTO students (name, roll_number, batch_id, department_id) 
                                    VALUES ('$name', '$roll', $batch_id, $department_id)");
            
            if ($result) {
                $imported++;
            } else {
                $errors++;
            }
        }
    }
    
    $success_message = "✅ Imported $imported students successfully!" . ($errors > 0 ? " ($errors errors)" : "");
}

// Delete student
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $conn->query("DELETE FROM students WHERE id = $id");
    header("Location: students.php");
    exit;
}

// Get filter parameters
$filter_department = isset($_GET['filter_dept']) ? intval($_GET['filter_dept']) : 0;
$filter_batch = isset($_GET['filter_batch']) ? intval($_GET['filter_batch']) : 0;
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query with filters
$where_clauses = [];
if ($filter_department > 0) {
    $where_clauses[] = "s.department_id = $filter_department";
}
if ($filter_batch > 0) {
    $where_clauses[] = "s.batch_id = $filter_batch";
}
if (!empty($search)) {
    $search_safe = $conn->real_escape_string($search);
    $where_clauses[] = "(s.name LIKE '%$search_safe%' OR s.roll_number LIKE '%$search_safe%')";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Get students with filters
$students = $conn->query("
    SELECT s.*, b.name as batch_name, b.year as batch_year, 
    d.name as dept_name, d.code as dept_code
    FROM students s
    JOIN batches b ON s.batch_id = b.id
    JOIN departments d ON s.department_id = d.id
    $where_sql
    ORDER BY d.name, b.year DESC, s.name
");

// Get departments for dropdown
$departments = $conn->query("SELECT * FROM departments ORDER BY name");

// Get batches for dropdown
$batches = $conn->query("SELECT b.*, d.name as dept_name FROM batches b JOIN departments d ON b.department_id = d.id ORDER BY d.name, b.year DESC");

// Get statistics
$total_students = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management</title>

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
        
        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #eee;
        }
        .tab {
            padding: 0.75rem 1.5rem;
            background: transparent;
            border: none;
            cursor: pointer;
            color: #666;
            font-size: 1rem;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 0.5rem; color: #666; font-weight: 500; }
        .form-group input, .form-group select, .form-group textarea { padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem; font-family: inherit; }
        .form-group textarea { min-height: 150px; resize: vertical; }
        
        .btn { padding: 0.75rem 1.5rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; transition: all 0.3s; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4); }
        .btn-danger { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .btn-small { padding: 0.5rem 1rem; font-size: 0.85rem; }
        
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            font-weight: 500;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .filter-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            align-items: end;
        }
        .filter-bar .form-group {
            min-width: 200px;
            margin-bottom: 0;
        }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; color: #333; position: sticky; top: 0; }
        
        .badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.85rem; font-weight: 500; }
        .badge-dept { margin-left: 0.5rem; }
        .dept-cs { background: #e3f2fd; color: #1976d2; }
        .dept-ec { background: #f3e5f5; color: #7b1fa2; }
        .dept-me { background: #e8f5e9; color: #388e3c; }
        .dept-ce { background: #fff3e0; color: #f57c00; }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .stat-summary {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            gap: 2rem;
        }
        .stat-item {
            display: flex;
            flex-direction: column;
        }
        .stat-item .label {
            color: #666;
            font-size: 0.85rem;
        }
        .stat-item .value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .info-box {
            background: #e3f2fd;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid #1976d2;
        }
        .info-box h4 {
            color: #1976d2;
            margin-bottom: 0.5rem;
        }
        .info-box p {
            color: #555;
            font-size: 0.9rem;
            line-height: 1.6;
        }
        
        .table-container {
            max-height: 600px;
            overflow-y: auto;
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
            <a href="students.php"class="active"> Students</a>
            <a href="courses.php"> Courses</a>
            <a href="student_course_enrollment.php"> Course Enrollment</a>
            <a href="sessions.php"> Exam Sessions</a>
            <a href="exams.php"> Exams</a>
            <a href="enroll_students.php"> Enroll Students</a>
            <a href="visual_seating.php" > Visual Seating Plan</a>
        </div>
        
        <div class="main-content">
            <h2 style="margin-bottom: 1.5rem; color: #333;">Student Management</h2>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <div class="stat-summary">
                <div class="stat-item">
                    <span class="label">Total Students</span>
                    <span class="value"><?php echo $total_students; ?></span>
                </div>
                <div class="stat-item">
                    <span class="label">Showing</span>
                    <span class="value"><?php echo $students->num_rows; ?></span>
                </div>
            </div>
            
            <div class="card">
                <div class="tabs">
                    <button class="tab active" onclick="switchTab('add-single')">➕ Add Single Student</button>
                    <button class="tab" onclick="switchTab('bulk-import')">📋 Bulk Import</button>
                </div>
                
                <!-- Single Student Form -->
                <div id="add-single" class="tab-content active">
                    <h2>Add New Student</h2>
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Student Name *</label>
                                <input type="text" name="name" placeholder="e.g., John Doe" required>
                            </div>
                            <div class="form-group">
                                <label>Roll Number *</label>
                                <input type="text" name="roll_number" placeholder="e.g., CS2024001" required>
                            </div>
                            <div class="form-group">
                                <label>Department *</label>
                                <select name="department_id" required id="dept_select_single">
                                    <option value="">Select Department</option>
                                    <?php 
                                    $departments->data_seek(0);
                                    while ($dept = $departments->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $dept['id']; ?>">
                                        <?php echo htmlspecialchars($dept['name']); ?> (<?php echo htmlspecialchars($dept['code']); ?>)
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Batch *</label>
                                <select name="batch_id" required id="batch_select_single">
                                    <option value="">Select Batch</option>
                                    <?php 
                                    $batches->data_seek(0);
                                    while ($batch = $batches->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $batch['id']; ?>" data-dept="<?php echo $batch['department_id']; ?>">
                                        <?php echo htmlspecialchars($batch['name'] . ' - ' . $batch['dept_name']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" name="add_student" class="btn">Add Student</button>
                    </form>
                </div>
                
                <!-- Bulk Import Form -->
                <div id="bulk-import" class="tab-content">
                    <h2>Bulk Import Students</h2>
                    
                    <div class="info-box">
                        <h4>📝 How to use Bulk Import:</h4>
                        <p>
                            1. Select the department and batch for all students<br>
                            2. Enter student data in the format: <strong>Name, Roll Number</strong> (one per line)<br>
                            3. Click "Import Students"
                        </p>
                        <p style="margin-top: 0.5rem;">
                            <strong>Example:</strong><br>
                            John Doe, CS2024001<br>
                            Jane Smith, CS2024002<br>
                            Bob Wilson, CS2024003
                        </p>
                    </div>
                    
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Department *</label>
                                <select name="bulk_department_id" required id="dept_select_bulk">
                                    <option value="">Select Department</option>
                                    <?php 
                                    $departments->data_seek(0);
                                    while ($dept = $departments->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $dept['id']; ?>">
                                        <?php echo htmlspecialchars($dept['name']); ?> (<?php echo htmlspecialchars($dept['code']); ?>)
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Batch *</label>
                                <select name="bulk_batch_id" required id="batch_select_bulk">
                                    <option value="">Select Batch</option>
                                    <?php 
                                    $batches->data_seek(0);
                                    while ($batch = $batches->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $batch['id']; ?>" data-dept="<?php echo $batch['department_id']; ?>">
                                        <?php echo htmlspecialchars($batch['name'] . ' - ' . $batch['dept_name']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Student Data (Name, Roll Number - one per line) *</label>
                            <textarea name="students_bulk" placeholder="John Doe, CS2024001&#10;Jane Smith, CS2024002&#10;Bob Wilson, CS2024003" required></textarea>
                        </div>
                        
                        <button type="submit" name="bulk_import" class="btn">Import Students</button>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <h2>All Students</h2>
                
                <!-- Filter Bar -->
                <form method="GET" class="filter-bar">
                    <div class="form-group">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Name or Roll Number" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group">
                        <label>Filter by Department</label>
                        <select name="filter_dept">
                            <option value="0">All Departments</option>
                            <?php 
                            $departments->data_seek(0);
                            while ($dept = $departments->fetch_assoc()): 
                            ?>
                            <option value="<?php echo $dept['id']; ?>" <?php echo ($filter_department == $dept['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Filter by Batch</label>
                        <select name="filter_batch">
                            <option value="0">All Batches</option>
                            <?php 
                            $batches->data_seek(0);
                            while ($batch = $batches->fetch_assoc()): 
                            ?>
                            <option value="<?php echo $batch['id']; ?>" <?php echo ($filter_batch == $batch['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($batch['name'] . ' - ' . $batch['dept_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn">Apply Filters</button>
                    <a href="students.php" class="btn" style="text-decoration: none; display: inline-block;">Clear</a>
                </form>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Roll Number</th>
                                <th>Student Name</th>
                                <th>Department</th>
                                <th>Batch</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($students->num_rows > 0): ?>
                                <?php while ($student = $students->fetch_assoc()): 
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
                                        <span class="badge badge-dept <?php echo $dept_class; ?>">
                                            <?php echo htmlspecialchars($student['dept_code']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($student['batch_name']); ?> (<?php echo $student['batch_year']; ?>)</td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?delete=1&id=<?php echo $student['id']; ?>" 
                                               class="btn btn-danger btn-small" 
                                               onclick="return confirm('Are you sure you want to delete this student?')">
                                                🗑️ Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #666; padding: 2rem;">
                                        No students found. Add some students using the form above.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function switchTab(tabId) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabId).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }
        
        // Filter batches based on selected department (Single Student Form)
        document.getElementById('dept_select_single').addEventListener('change', function() {
            const selectedDept = this.value;
            const batchSelect = document.getElementById('batch_select_single');
            const options = batchSelect.querySelectorAll('option');
            
            options.forEach(option => {
                if (option.value === '') {
                    option.style.display = 'block';
                    return;
                }
                
                const optionDept = option.getAttribute('data-dept');
                if (selectedDept === '' || optionDept === selectedDept) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });
            
            batchSelect.value = '';
        });
        
        // Filter batches based on selected department (Bulk Import Form)
        document.getElementById('dept_select_bulk').addEventListener('change', function() {
            const selectedDept = this.value;
            const batchSelect = document.getElementById('batch_select_bulk');
            const options = batchSelect.querySelectorAll('option');
            
            options.forEach(option => {
                if (option.value === '') {
                    option.style.display = 'block';
                    return;
                }
                
                const optionDept = option.getAttribute('data-dept');
                if (selectedDept === '' || optionDept === selectedDept) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });
            
            batchSelect.value = '';
        });
    </script>
</body>
</html>