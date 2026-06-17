
CREATE DATABASE IF NOT EXISTS markmetrics;
USE markmetrics;

-- 1. Departments Table
CREATE TABLE departments (
    department_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Semesters Table (NEW - Normalized)
CREATE TABLE semesters (
    semester_id INT AUTO_INCREMENT PRIMARY KEY,
    semester_name VARCHAR(50) NOT NULL, -- e.g., 'Spring', 'Summer', 'Fall'
    academic_year VARCHAR(20) NOT NULL, -- e.g., '2024', '2025'
    display_name VARCHAR(100) GENERATED ALWAYS AS (CONCAT(semester_name, ' ', academic_year)) STORED,
    UNIQUE (semester_name, academic_year)
);

-- 3. Grading Scale Table (NEW - Normalized)
CREATE TABLE grading_scale (
    grade VARCHAR(5) PRIMARY KEY,
    min_score DECIMAL(5,2) NOT NULL,
    max_score DECIMAL(5,2) NOT NULL,
    points DECIMAL(3,2) NOT NULL
);

-- 4. Programs Table
CREATE TABLE programs (
    program_id INT AUTO_INCREMENT PRIMARY KEY,
    program_code VARCHAR(20) UNIQUE NOT NULL, -- Standardized: DEPT-DEGREE
    name VARCHAR(255) NOT NULL,
    department_id INT NOT NULL,
    total_credits DECIMAL(5,2) NOT NULL,
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE CASCADE
);

-- 5. Users Table
CREATE TABLE users (
    id VARCHAR(50) PRIMARY KEY, 
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'parent', 'teacher', 'admin') NOT NULL,
    department_id INT, 
    status ENUM('Active', 'Inactive', 'Pending') DEFAULT 'Pending', 
    profile_picture_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE SET NULL
);

-- 6. Students Table
CREATE TABLE students (
    student_id VARCHAR(50) PRIMARY KEY,
    program_id INT, 
    date_of_birth DATE,
    mothers_name VARCHAR(255) DEFAULT 'Unknown',
    academic_year VARCHAR(50), 
    current_academic_year VARCHAR(50), 
    enrollment_term VARCHAR(50), -- Could be normalized further but left for readability
    parent_id VARCHAR(50), 
    total_credits_earned DECIMAL(5,2) DEFAULT 0,
    cumulative_gpa DECIMAL(3,2) DEFAULT 0.00,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE SET NULL
);

-- 7. Teachers Table
CREATE TABLE teachers (
    teacher_id VARCHAR(50) PRIMARY KEY,
    position VARCHAR(100), 
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 8. Courses Table
CREATE TABLE courses (
    course_code VARCHAR(20) PRIMARY KEY, -- Standardized: DEPT-NUMBER
    course_name VARCHAR(255) NOT NULL,
    department_id INT NOT NULL, 
    credits DECIMAL(3,1) NOT NULL, 
    teacher_id VARCHAR(50),
    semester_id INT, -- Normalized link to semesters
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES semesters(semester_id) ON DELETE SET NULL
);

-- 9. Enrollments Table
CREATE TABLE enrollments (
    enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    course_code VARCHAR(20) NOT NULL,
    semester_id INT NOT NULL,
    midterm_score DECIMAL(5,2),
    final_score DECIMAL(5,2),
    ct_score DECIMAL(5,2),
    assignment_score DECIMAL(5,2),
    total_score DECIMAL(5,2) GENERATED ALWAYS AS (COALESCE(midterm_score, 0) + COALESCE(final_score, 0) + COALESCE(ct_score, 0) + COALESCE(assignment_score, 0)) STORED,
    -- Grade and points could be derived, but kept for historical snapshot (Standard 3NF would move these to a View)
    grade VARCHAR(5), 
    points DECIMAL(3,2), 
    status ENUM('Completed', 'Ongoing', 'Dropped') DEFAULT 'Ongoing',
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (course_code) REFERENCES courses(course_code) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES semesters(semester_id),
    UNIQUE KEY unique_enrollment (student_id, course_code, semester_id)
);

-- 10. Audit Logs Table
CREATE TABLE audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) NOT NULL, 
    action_type VARCHAR(100) NOT NULL, 
    description TEXT NOT NULL,
    target_entity_type VARCHAR(50), 
    target_entity_id VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 11. Grade Correction Requests Table
CREATE TABLE grade_correction_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    course_code VARCHAR(20) NOT NULL,
    current_grade VARCHAR(5),
    new_grade VARCHAR(5) NOT NULL,
    justification TEXT NOT NULL,
    evidence_file_url VARCHAR(500),
    requested_by VARCHAR(50) NOT NULL, 
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    resolved_by VARCHAR(50), 
    FOREIGN KEY (student_id) REFERENCES students(student_id),
    FOREIGN KEY (course_code) REFERENCES courses(course_code),
    FOREIGN KEY (requested_by) REFERENCES teachers(teacher_id),
    FOREIGN KEY (resolved_by) REFERENCES users(id)
);

-- 12. Attendance Table
CREATE TABLE attendance (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    course_code VARCHAR(20) NOT NULL,
    date DATE NOT NULL,
    status ENUM('Present', 'Absent', 'Excused') NOT NULL,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (course_code) REFERENCES courses(course_code) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (student_id, course_code, date)
);

-- 13. Upcoming Tasks Table
CREATE TABLE upcoming_tasks (
    task_id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(20) NOT NULL,
    title VARCHAR(255) NOT NULL, 
    task_type ENUM('Exam', 'Assignment', 'Presentation') NOT NULL,
    due_date DATETIME NOT NULL,
    FOREIGN KEY (course_code) REFERENCES courses(course_code) ON DELETE CASCADE
);

-- 14. Student Finances Table (NEW)
CREATE TABLE student_finances (
    finance_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    total_billed DECIMAL(10,2) DEFAULT 0,
    paid_amount DECIMAL(10,2) DEFAULT 0,
    waived_amount DECIMAL(10,2) DEFAULT 0,
    balance DECIMAL(10,2) GENERATED ALWAYS AS (total_billed - paid_amount - waived_amount) STORED,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_finance (student_id)
);

-- 15. Class Schedules Table (NEW)
CREATE TABLE class_schedules (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(20) NOT NULL,
    day_of_week ENUM('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room_number VARCHAR(20),
    FOREIGN KEY (course_code) REFERENCES courses(course_code) ON DELETE CASCADE
);

-- ==========================================
-- DATA INSERTION (Normalized & Standardized)
-- ==========================================

-- Populate Departments
INSERT INTO departments (name) VALUES 
('Computer Science and Engineering'),
('Electrical and Electronics Engineering'),
('Business Administration'),
('Mathematics'),
('Physics');

-- Populate Semesters (Normalized)
INSERT INTO semesters (semester_name, academic_year) VALUES 
('Spring', '2024'),
('Spring', '2025'),
('Summer', '2025'),
('Fall', '2025'),
('Spring', '2026');

-- Populate Grading Scale (Normalized)
INSERT INTO grading_scale (grade, min_score, max_score, points) VALUES 
('A',  90.00, 100.00, 4.00),
('A-', 86.00,  89.99, 3.67),
('B+', 82.00,  85.99, 3.33),
('B',  78.00,  81.99, 3.00),
('B-', 74.00,  77.99, 2.67),
('C+', 70.00,  73.99, 2.33),
('C',  66.00,  69.99, 2.00),
('C-', 62.00,  65.99, 1.67),
('D+', 58.00,  61.99, 1.33),
('D',  55.00,  57.99, 1.00),
('F',  0.00,   54.99, 0.00);

-- Populate Programs (Standardized Codes)
INSERT INTO programs (program_code, name, department_id, total_credits) VALUES 
('CSE-BSC', 'B.Sc in Computer Science & Engineering', 1, 138.00),
('BBA-BBA', 'Bachelor of Business Administration', 3, 120.00),
('EEE-BSC', 'B.Sc in Electrical & Electronics', 2, 140.00);

-- Populate Users
INSERT INTO users (id, name, email, password_hash, role, department_id, status) VALUES 
('ADM-001', 'Admin User', 'admin@markmetrics.edu', 'hashed_pwd_123', 'admin', NULL, 'Active'),
('TCH-101', 'Prof. Mahi Bhai', 'mahi@markmetrics.edu', 'hashed_pwd_123', 'teacher', 1, 'Active'),
('TCH-102', 'Dr. Aris Thorne', 'aris@markmetrics.edu', 'hashed_pwd_123', 'teacher', 1, 'Active'),
('TCH-103', 'Prof. Elena Vance', 'elena@markmetrics.edu', 'hashed_pwd_123', 'teacher', 4, 'Active'),
('TCH-104', 'Tanvir Ahmed', 'tanvir@markmetrics.edu', 'hashed_pwd_123', 'teacher', 1, 'Active'),
('TCH-105', 'Dr. Sarah Connor', 'sarah@markmetrics.edu', 'hashed_pwd_123', 'teacher', 1, 'Active'),
('TCH-106', 'Prof. James Gordon', 'james@markmetrics.edu', 'hashed_pwd_123', 'teacher', 1, 'Active'),
('PAR-901', 'Shekh Rahman', 'shekh@email.com', 'hashed_pwd_123', 'parent', NULL, 'Active'),
('PAR-902', 'Robert T. Miller', 'robert@email.com', 'hashed_pwd_123', 'parent', NULL, 'Active'),
('21-44502-1', 'Julian V. Sterling', 'julian@student.edu', '123', 'student', 1, 'Active'),
('0112330784', 'Romoan K.', 'mrahman2330784@bscse.uiu.ac.bd', '123', 'student', 1, 'Active'),
('0112330682', 'Hukna Rafi', 'mmursalin2330682@bscse.uiu.ac.bd', '123', 'student', 1, 'Active'),
('0112330791', 'Lombu Maruf', 'billah@student.edu', '123', 'student', 1, 'Active'),
('0112330811', 'Moda Emon', 'khalad@student.edu', '123', 'student', 1, 'Active');

-- Populate Teachers
INSERT INTO teachers (teacher_id, position) VALUES 
('TCH-101', 'Senior Faculty'),
('TCH-102', 'Associate Professor'),
('TCH-103', 'Professor'),
('TCH-104', 'Lecturer'),
('TCH-105', 'Associate Professor'),
('TCH-106', 'Senior Lecturer');

-- Populate Students
INSERT INTO students (student_id, program_id, date_of_birth, academic_year, current_academic_year, enrollment_term, parent_id, total_credits_earned, cumulative_gpa) VALUES 
('0112330784', 1, '2004-09-14', '2023-2024', '2025-2026', 'Spring 2024', 'PAR-901', 67.00, 3.82),
('21-44502-1', 1, '2003-05-20', '2023-2024', '2025-2026', 'Spring 2024', 'PAR-902', 148.00, 3.94),
('0112330682', 1, '2004-11-05', '2023-2024', '2025-2026', 'Spring 2024', NULL, 45.00, 3.10),
('0112330791', 1, '2005-01-15', '2023-2024', '2025-2026', 'Spring 2024', NULL, 30.00, 3.45),
('0112330811', 1, '2004-08-22', '2023-2024', '2025-2026', 'Spring 2024', NULL, 60.00, 2.95);

-- Populate Courses (Standardized Codes & Semester IDs)
INSERT INTO courses (course_code, course_name, department_id, credits, teacher_id, semester_id) VALUES 
('CSE-4165', 'Advanced Algorithms', 1, 3.0, 'TCH-101', 1),
('CSE-4402', 'Theory of Computation', 1, 3.0, 'TCH-104', 1),
('CSE-2216', 'Structure Programming Language', 1, 3.0, 'TCH-102', 1),
('EEE-2265', 'Electric Circuit', 2, 3.0, NULL, 1),
('MAT-1199', 'Fundamental Calculus', 4, 3.0, 'TCH-103', 1),
('CSE-4401', 'Database Systems', 1, 3.0, 'TCH-104', 3),
('ENG-1101', 'Technical Writing', 1, 3.0, 'TCH-103', 3),
('CSE-3301', 'Data Structures', 1, 3.0, 'TCH-101', 2),
('MAT-2201', 'Linear Algebra', 4, 3.0, 'TCH-103', 2),
('CSE-4411', 'Artificial Intelligence', 1, 3.0, 'TCH-101', 4),
('CSE-5501', 'Advanced Distributed Systems', 1, 3.0, 'TCH-105', 5),
('CSE-5508', 'Ethical Hacking & Pen Testing', 1, 3.0, 'TCH-106', 5),
('CSE-4450', 'Big Data Engineering', 1, 3.0, 'TCH-101', 5);

-- Populate Enrollments (Standardized Codes & Semester IDs)
INSERT INTO enrollments (student_id, course_code, semester_id, midterm_score, final_score, grade, points, status) VALUES 
('0112330784', 'CSE-4165', 1, 38.00, 56.00, 'A+', 4.00, 'Completed'),
('0112330682', 'CSE-4165', 1, NULL, 48.00, 'INC', 0.00, 'Ongoing'),
('0112330791', 'CSE-4165', 1, 32.00, 42.00, 'B-', 2.67, 'Completed'),
('0112330811', 'CSE-4165', 1, 28.00, 45.00, 'C+', 2.33, 'Completed'),
('21-44502-1', 'CSE-4401', 3, 40.00, 54.00, 'A+', 4.00, 'Completed'),
('21-44502-1', 'ENG-1101', 3, 38.00, 50.00, 'A-', 3.67, 'Completed'),
('21-44502-1', 'CSE-3301', 2, 39.00, 55.00, 'A+', 4.00, 'Completed'),
('21-44502-1', 'MAT-2201', 2, 35.00, 48.00, 'B+', 3.33, 'Completed'),
('21-44502-1', 'CSE-4411', 4, 39.00, 54.00, 'A+', 4.00, 'Completed'),

-- Spring 2025
('0112330784', 'CSE-3301', 2, 37.00, 52.00, 'A', 3.75, 'Completed'),
('0112330784', 'MAT-2201', 2, 34.00, 46.00, 'B', 3.00, 'Completed'),
('0112330682', 'CSE-3301', 2, 32.00, 44.00, 'B-', 2.67, 'Completed'),
('0112330682', 'MAT-2201', 2, 30.00, 40.00, 'C+', 2.33, 'Completed'),
('0112330791', 'CSE-3301', 2, 35.00, 48.00, 'B+', 3.33, 'Completed'),
('0112330791', 'MAT-2201', 2, 38.00, 50.00, 'A-', 3.67, 'Completed'),
('0112330811', 'CSE-3301', 2, 28.00, 38.00, 'C', 2.00, 'Completed'),
('0112330811', 'MAT-2201', 2, 25.00, 35.00, 'D+', 1.33, 'Completed'),

-- Summer 2025
('0112330784', 'CSE-4401', 3, 39.00, 55.00, 'A+', 4.00, 'Completed'),
('0112330784', 'ENG-1101', 3, 36.00, 49.00, 'A-', 3.67, 'Completed'),
('0112330682', 'CSE-4401', 3, 31.00, 42.00, 'B', 3.00, 'Completed'),
('0112330682', 'ENG-1101', 3, 33.00, 45.00, 'B+', 3.33, 'Completed'),
('0112330791', 'CSE-4401', 3, 34.00, 47.00, 'A-', 3.67, 'Completed'),
('0112330791', 'ENG-1101', 3, 35.00, 48.00, 'B+', 3.33, 'Completed'),
('0112330811', 'CSE-4401', 3, 29.00, 40.00, 'C+', 2.33, 'Completed'),
('0112330811', 'ENG-1101', 3, 27.00, 37.00, 'C', 2.00, 'Completed'),

-- Fall 2025
('0112330784', 'CSE-4411', 4, 38.00, 53.00, 'A+', 4.00, 'Completed'),
('0112330682', 'CSE-4411', 4, 30.00, 43.00, 'B', 3.00, 'Completed'),
('0112330791', 'CSE-4411', 4, 36.00, 49.00, 'A-', 3.67, 'Completed'),
('0112330811', 'CSE-4411', 4, 26.00, 39.00, 'C+', 2.33, 'Completed'),

-- Ongoing (Spring 2026)
('21-44502-1', 'CSE-5501', 5, NULL, NULL, '--', NULL, 'Ongoing'),
('21-44502-1', 'CSE-5508', 5, NULL, NULL, '--', NULL, 'Ongoing'),
('21-44502-1', 'CSE-4450', 5, NULL, NULL, '--', NULL, 'Ongoing'),
('0112330784', 'CSE-5501', 5, NULL, NULL, '--', NULL, 'Ongoing'),
('0112330784', 'CSE-5508', 5, NULL, NULL, '--', NULL, 'Ongoing'),
('0112330682', 'CSE-5501', 5, NULL, NULL, '--', NULL, 'Ongoing'),
('0112330682', 'CSE-5508', 5, NULL, NULL, '--', NULL, 'Ongoing'),
('0112330791', 'CSE-5501', 5, NULL, NULL, '--', NULL, 'Ongoing'),
('0112330791', 'CSE-5508', 5, NULL, NULL, '--', NULL, 'Ongoing'),
('0112330811', 'CSE-5501', 5, NULL, NULL, '--', NULL, 'Ongoing'),
('0112330811', 'CSE-5508', 5, NULL, NULL, '--', NULL, 'Ongoing');

-- Audit Logs
INSERT INTO audit_logs (user_id, action_type, description, target_entity_type, target_entity_id) VALUES 
('TCH-101', 'Mark Updated', 'Prof. Mahi Bhai updated grade for Romoan K. - Final Exam(+5)', 'enrollments', '1'),
('ADM-001', 'System Configuration', 'System Admin updated Policy Schema for 2024', 'system', NULL),
('ADM-001', 'Bulk Action', 'Registrar Office initialed bulk Transcript Lock', 'system', NULL),
('TCH-101', 'New Grade Entry', 'Prof. Mahi updated final result for SEC F', 'enrollments', NULL);

-- Grade Correction Requests
INSERT INTO grade_correction_requests (student_id, course_code, current_grade, new_grade, justification, requested_by, status) VALUES 
('21-44502-1', 'CSE-4402', 'B+', 'A-', 'Re-evaluation of final project component.', 'TCH-102', 'Pending'),
('0112330784', 'CSE-2216', 'D', 'C', 'Typographical error in initial entry.', 'TCH-103', 'Approved'),
('0112330811', 'MAT-1199', 'F', 'D', 'Late submission of medical certificate accepted.', 'TCH-102', 'Rejected');

-- Upcoming Tasks
INSERT INTO upcoming_tasks (course_code, title, task_type, due_date) VALUES 
('CSE-4165', 'Advanced Algorithms Final Presentation', 'Presentation', '2026-05-22 09:00:00'),
('CSE-4402', 'Systems Architecture Mid-term Exam', 'Exam', '2026-05-25 14:30:00'),
('CSE-2216', 'Ethical Hacking Lab Submission', 'Assignment', '2026-05-28 23:59:59');
