
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

$request = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// --- STUDENT ENDPOINTS ---

if ($request === 'student_dashboard') {
    $student_id = $_GET['student_id'];
    $query = "SELECT s.*, u.first_name, u.last_name, u.email 
              FROM students s 
              JOIN users u ON s.user_id = u.id 
              WHERE s.id = $student_id";
    $result = mysqli_query($conn, $query);
    echo json_encode(mysqli_fetch_assoc($result));
}

if ($request === 'enrollments') {
    $student_id = $_GET['student_id'];
    $query = "SELECT e.*, c.course_code, c.course_name, c.units, 
                     cs.section_name, cs.schedule, cs.room,
                     g.final_average, g.grade_letter
              FROM enrollments e
              JOIN class_sections cs ON e.class_section_id = cs.id
              JOIN courses c ON cs.course_id = c.id
              LEFT JOIN grades g ON e.id = g.enrollment_id
              WHERE e.student_id = $student_id";
    $result = mysqli_query($conn, $query);
    $enrollments = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $enrollments[] = $row;
    }
    echo json_encode($enrollments);
}

if ($request === 'available_courses') {
    $query = "SELECT DISTINCT c.*, cs.id as section_id, cs.section_name, cs.schedule, cs.room, cs.capacity
              FROM courses c
              JOIN class_sections cs ON c.id = cs.course_id";
    $result = mysqli_query($conn, $query);
    $courses = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $courses[] = $row;
    }
    echo json_encode($courses);
}

// --- ADMIN ENDPOINTS ---

if ($request === 'all_students') {
    $query = "SELECT s.*, u.first_name, u.last_name, u.email 
              FROM students s 
              JOIN users u ON s.user_id = u.id";
    $result = mysqli_query($conn, $query);
    $students = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $students[] = $row;
    }
    echo json_encode($students);
}

if ($request === 'all_courses') {
    $query = "SELECT * FROM courses ORDER BY course_code";
    $result = mysqli_query($conn, $query);
    $courses = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $courses[] = $row;
    }
    echo json_encode($courses);
}

if ($request === 'all_faculty') {
    $query = "SELECT f.*, u.first_name, u.last_name, u.email 
              FROM faculty f 
              JOIN users u ON f.user_id = u.id";
    $result = mysqli_query($conn, $query);
    $faculty = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $faculty[] = $row;
    }
    echo json_encode($faculty);
}

if ($request === 'class_sections') {
    $query = "SELECT cs.*, c.course_code, c.course_name, u.first_name, u.last_name
              FROM class_sections cs
              JOIN courses c ON cs.course_id = c.id
              LEFT JOIN users u ON cs.faculty_id = u.id";
    $result = mysqli_query($conn, $query);
    $sections = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $sections[] = $row;
    }
    echo json_encode($sections);
}

if ($request === 'audit_logs') {
    $query = "SELECT al.*, u.first_name, u.last_name 
              FROM audit_logs al 
              LEFT JOIN users u ON al.user_id = u.id 
              ORDER BY al.created_at DESC LIMIT 100";
    $result = mysqli_query($conn, $query);
    $logs = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $logs[] = $row;
    }
    echo json_encode($logs);
}

// POST endpoints
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if ($request === 'enroll_course') {
        $student_id = $data['student_id'];
        $section_id = $data['section_id'];
        $query = "INSERT INTO enrollments (student_id, class_section_id) 
                  VALUES ($student_id, $section_id)";
        if (mysqli_query($conn, $query)) {
            echo json_encode(['success' => true, 'message' => 'Enrolled successfully']);
        }
    }
    
    if ($request === 'submit_grades') {
        $enrollment_id = $data['enrollment_id'];
        $midterm = $data['midterm_grade'];
        $final = $data['final_grade'];
        $average = ($midterm + $final) / 2;
        $grade_letter = $average >= 90 ? 'A' : ($average >= 80 ? 'B' : ($average >= 70 ? 'C' : 'F'));
        
        $query = "INSERT INTO grades (enrollment_id, midterm_grade, final_grade, final_average, grade_letter) 
                  VALUES ($enrollment_id, $midterm, $final, $average, '$grade_letter')
                  ON DUPLICATE KEY UPDATE 
                  midterm_grade = $midterm, final_grade = $final, final_average = $average, grade_letter = '$grade_letter'";
        mysqli_query($conn, $query);
        echo json_encode(['success' => true, 'grade_letter' => $grade_letter]);
    }
}

mysqli_close($conn);
?>