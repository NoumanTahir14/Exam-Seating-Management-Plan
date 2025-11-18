🎓 Exam Seating Plan Management System

A PHP + MySQL based web application for generating exam hall seating arrangements, ensuring that students from different courses, batches, and departments sit together.

📌 Features

✔ Department Management
✔ Course Management
✔ Student Registration
✔ Student Course Enrollment
✔ Exam Creation
✔ Auto Seating Plan Generator
✔ Visual Seating Display
✔ Clean UI With CSS

📁 Project Structure
EXAM-SEATING-PLAN/
│
├── config.php
├── index.php
│
├── departments.php
├── courses.php
├── students.php
├── enroll_students.php
├── student_course_enrollment.php
│
├── exams.php
├── sessions.php
├── visual_seating.php
│
├── script.js
├── style.css
│
└── README.md
🚀 How to Use the System
1. Add Department

Go to departments.php → Add multiple departments.

2. Add Courses

Go to courses.php → Link courses to departments.

3. Add Students

Fill student info in students.php.

4. Enroll Students in Courses

Use enroll_students.php.

5. Create Exams

Use exams.php to define exam schedule.

6. Generate Seating Plan

Open:visual_seating.php

The system will:

Fetch eligible students

Mix departments

Arrange seating row by row

Show final seating visually

🧪 Technologies Used

PHP
MySQL
HTML
CSS
JavaScript
