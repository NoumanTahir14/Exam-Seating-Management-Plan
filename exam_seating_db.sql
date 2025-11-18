DROP DATABASE IF EXISTS exam_seating_db;
CREATE DATABASE exam_seating_db;
USE exam_seating_db;

-- Departments
CREATE TABLE departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Batches
CREATE TABLE batches (
    id INT PRIMARY KEY AUTO_INCREMENT,
    department_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    year INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);

-- Students
CREATE TABLE students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    roll_number VARCHAR(50) NOT NULL UNIQUE,
    batch_id INT NOT NULL,
    department_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);

-- Courses
CREATE TABLE courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    department_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    credits INT DEFAULT 3,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);

-- Exams
CREATE TABLE exams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    exam_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    course_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- Exam Sessions
CREATE TABLE exam_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_name VARCHAR(200) NOT NULL,
    session_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    total_rooms INT DEFAULT 1,
    seats_per_room INT DEFAULT 30,
    rows_per_room INT DEFAULT 15,
    seats_per_row INT DEFAULT 2,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Link exams to sessions
CREATE TABLE session_exams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id INT NOT NULL,
    exam_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES exam_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    UNIQUE KEY unique_session_exam (session_id, exam_id)
);

-- Student enrollment in exams
CREATE TABLE exam_enrollments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    exam_id INT NOT NULL,
    student_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (exam_id, student_id)
);

-- Seating arrangements (FIXED)
CREATE TABLE seating_arrangements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id INT NOT NULL,
    student_id INT NOT NULL,
    exam_id INT NOT NULL,
    room_number INT NOT NULL,
    seat_row INT NOT NULL,
    seat_position VARCHAR(10) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES exam_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    UNIQUE KEY unique_seat (session_id, room_number, seat_row, seat_position)
);

-- Insert sample data
INSERT INTO departments (name, code) VALUES 
('Computer Science', 'CS'),
('Electronics Engineering', 'EC'),
('Mechanical Engineering', 'ME'),
('Civil Engineering', 'CE');

INSERT INTO batches (department_id, name, year) VALUES 
(1, '2024 Batch', 2024),
(1, '2023 Batch', 2023),
(2, '2024 Batch', 2024),
(2, '2023 Batch', 2023),
(3, '2024 Batch', 2024),
(4, '2024 Batch', 2024);

INSERT INTO courses (department_id, name, code, credits) VALUES 
(1, 'Data Structures', 'CS201', 4),
(1, 'Database Management Systems', 'CS301', 3),
(1, 'Operating Systems', 'CS302', 4),
(2, 'Digital Electronics', 'EC202', 4),
(2, 'Signals and Systems', 'EC301', 3),
(3, 'Thermodynamics', 'ME301', 3),
(4, 'Structural Analysis', 'CE301', 4);

INSERT INTO students (name, roll_number, batch_id, department_id) VALUES 
('Ahmed Ali', 'CS2024001', 1, 1),
('Fatima Khan', 'CS2024002', 1, 1),
('Hassan Ahmed', 'CS2024003', 1, 1),
('Ayesha Malik', 'CS2024004', 1, 1),
('Bilal Shah', 'CS2024005', 1, 1),
('Zainab Hussain', 'CS2023001', 2, 1),
('Usman Tariq', 'CS2023002', 2, 1),
('Mariam Siddiqui', 'CS2023003', 2, 1),
('Ali Raza', 'EC2024001', 3, 2),
('Sana Rehman', 'EC2024002', 3, 2),
('Hamza Iqbal', 'EC2024003', 3, 2),
('Nida Batool', 'EC2024004', 3, 2),
('Imran Yousuf', 'EC2023001', 4, 2),
('Hira Nasir', 'EC2023002', 4, 2),
('Asad Farooq', 'ME2024001', 5, 3),
('Amna Aslam', 'ME2024002', 5, 3),
('Kamran Javed', 'ME2024003', 5, 3),
('Sara Baig', 'CE2024001', 6, 4),
('Faisal Haider', 'CE2024002', 6, 4),
('Rabia Noor', 'CE2024003', 6, 4);

INSERT INTO exams (name, exam_date, start_time, end_time, course_id) VALUES 
('Mid Semester - Data Structures', '2025-12-01', '10:00:00', '13:00:00', 1),
('Mid Semester - Digital Electronics', '2025-12-01', '10:00:00', '13:00:00', 4),
('Mid Semester - Database Systems', '2025-12-01', '10:00:00', '13:00:00', 2),
('Mid Semester - Thermodynamics', '2025-12-01', '10:00:00', '13:00:00', 6);

INSERT INTO exam_sessions (session_name, session_date, start_time, end_time, total_rooms, seats_per_room, rows_per_room, seats_per_row) 
VALUES ('Morning Session - December 1', '2025-12-01', '10:00:00', '13:00:00', 2, 30, 15, 2);

INSERT INTO session_exams (session_id, exam_id) VALUES 
(1, 1), (1, 2), (1, 3), (1, 4);

INSERT INTO exam_enrollments (exam_id, student_id) VALUES 
(1, 1), (1, 2), (1, 3), (1, 4), (1, 5), (1, 6), (1, 7), (1, 8),
(2, 9), (2, 10), (2, 11), (2, 12), (2, 13), (2, 14),
(3, 1), (3, 2), (3, 6), (3, 7),
(4, 15), (4, 16), (4, 17);