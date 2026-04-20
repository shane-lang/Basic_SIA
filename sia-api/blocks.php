<?php
// =============================================================================
// blocks.php — Class Block / Section Management
//
// FEATURE: Assign students to class blocks (e.g. BSIT-1A, BSIT-1B).
//   • A block belongs to a program + year_level + semester + school_year.
//   • Each block has a max_capacity (default 40).
//   • When a block is full, the next block letter is auto-generated (A→B→C…).
//   • Students see their own block on the dashboard.
//   • Registrar can assign, reassign, and view blocks.
//   • All enrolled subjects in a block share the same course_sections row
//     (same schedule/room/faculty) — that mapping is stored in block_course_sections.
//
// NEW TABLES (run migrate_blocks.php or execute migrate_blocks.sql):
//   class_blocks            — one row per block (BSIT-1A, BSIT-1B, …)
//   block_course_sections   — maps a block to a course_section for each subject
//
// NEW COLUMN:
//   students.block_id INT DEFAULT NULL → FK → class_blocks.id
//
// ENDPOINTS (all via GET/POST ?action=XXX):
//
//   GET  get_blocks               — list all blocks (filter: program, year_level, semester)
//   GET  get_block_detail         — single block with enrolled students + subjects
//   GET  get_student_block        — return the block assigned to a student
//   POST assign_block             — assign (or reassign) a student to a block
//   POST auto_assign_block        — auto-assign student to existing block with space,
//                                   or create a new block if all are full
//   POST create_block             — manually create a new block
//   POST update_block             — rename / change capacity
//   POST assign_block_section     — link a course_section to a block for a subject
//
// USAGE IN registrar.php — add to the GET switch:
//   case 'get_blocks':          getBlocks($conn);          exit();
//   case 'get_block_detail':    getBlockDetail($conn);     exit();
//   case 'get_student_block':   getStudentBlock($conn);    exit();
//
// USAGE IN registrar.php — add to the POST switch:
//   case 'assign_block':        assignBlock($conn, $data);          exit();
//   case 'auto_assign_block':   autoAssignBlock($conn, $data);      exit();
//   case 'create_block':        createBlock($conn, $data);          exit();
//   case 'update_block':        updateBlock($conn, $data);          exit();
//   case 'assign_block_section':assignBlockSection($conn, $data);   exit();
//
// =============================================================================

// ── Only run the HTTP router when blocks.php is the entry point ──────────────
// When registrar.php does require_once __DIR__.'/blocks.php', this block is
// skipped so only the function definitions below are loaded — no duplicate
// output, no double-auth, no ob_start() level clash.
if (basename($_SERVER['SCRIPT_FILENAME']) === 'blocks.php') {

ob_start();
set_exception_handler(function (Throwable $e) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    $o = $_SERVER['HTTP_ORIGIN'] ?? '*';
    header("Access-Control-Allow-Origin: $o", true);
    header('Access-Control-Allow-Credentials: true', true);
    header('Content-Type: application/json', true);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
});

require_once __DIR__ . '/config.php';
applyCors();
require_once __DIR__ . '/auth_middleware.php';
require_once __DIR__ . '/audit_helper.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// All block endpoints require at least registrar or admin role.
// get_student_block is also accessible by students (own block only).
$authUser = requireAuth($conn); // any authenticated role
$GLOBALS['authUser'] = $authUser;

// ── Ensure tables exist ───────────────────────────────────────────────────────
ensureBlockTables($conn);

switch ($method) {
    case 'GET':
        switch ($action) {
            case 'get_blocks':         getBlocks($conn);         break;
            case 'get_block_detail':   getBlockDetail($conn);    break;
            case 'get_student_block':  getStudentBlock($conn);   break;
            default:
                echo json_encode(['success' => false, 'message' => "Unknown GET action: $action"]);
        }
        break;

    case 'POST':
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true) ?? [];
        switch ($action) {
            case 'assign_block':         assignBlock($conn, $data);        break;
            case 'auto_assign_block':    autoAssignBlock($conn, $data);    break;
            case 'create_block':         createBlock($conn, $data);        break;
            case 'update_block':         updateBlock($conn, $data);        break;
            case 'assign_block_section': assignBlockSection($conn, $data); break;
            default:
                echo json_encode(['success' => false, 'message' => "Unknown POST action: $action"]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

} // end standalone router guard

// =============================================================================
// SCHEMA HELPERS
// =============================================================================

function ensureBlockTables(mysqli $conn): void
{
    // ── class_blocks ─────────────────────────────────────────────────────────
    $conn->query("
        CREATE TABLE IF NOT EXISTS class_blocks (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            block_code   VARCHAR(30)  NOT NULL COMMENT 'e.g. BSIT-1A',
            program      VARCHAR(150) NOT NULL,
            year_level   VARCHAR(30)  NOT NULL COMMENT '1st Year, 2nd Year, Grade 11…',
            semester     VARCHAR(100) NOT NULL COMMENT '1st Semester, AY 2026-2027',
            school_year  VARCHAR(20)  NOT NULL COMMENT '2026-2027',
            max_capacity INT          NOT NULL DEFAULT 40,
            is_active    TINYINT(1)   NOT NULL DEFAULT 1,
            created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_block (program, year_level, semester, block_code),
            INDEX idx_program_year (program, year_level, semester)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // ── block_course_sections — maps each subject in a block to a section ────
    // Allows "Block BSIT-1A takes CC101 in Section A (Room 101, MWF 7-9am)"
    $conn->query("
        CREATE TABLE IF NOT EXISTS block_course_sections (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            block_id         INT NOT NULL,
            course_id        INT NOT NULL,
            course_section_id INT DEFAULT NULL COMMENT 'FK → course_sections.id',
            created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_block_course (block_id, course_id),
            INDEX idx_block (block_id),
            INDEX idx_course (course_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // ── Add block_id to students if missing ──────────────────────────────────
    $colCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'block_id'");
    if ($colCheck && $colCheck->num_rows === 0) {
        $conn->query("ALTER TABLE students ADD COLUMN block_id INT DEFAULT NULL COMMENT 'FK → class_blocks.id'");
        $conn->query("ALTER TABLE students ADD INDEX idx_block_id (block_id)");
    }
}

// =============================================================================
// HELPER — Generate next block letter suffix (A→B→C…→Z→AA→AB…)
// =============================================================================
function nextBlockLetter(string $lastLetter): string
{
    if ($lastLetter === '') return 'A';

    $letters = str_split($lastLetter);
    $i = count($letters) - 1;

    while ($i >= 0) {
        if ($letters[$i] < 'Z') {
            $letters[$i] = chr(ord($letters[$i]) + 1);
            return implode('', $letters);
        }
        $letters[$i] = 'A';
        $i--;
    }
    // Overflow: AA after Z, AAA after ZZ, etc.
    return 'A' . implode('', $letters);
}

// =============================================================================
// HELPER — Build block_code from program code + year number + letter
// Example: program="Bachelor of Science in Information Technology", year="1st Year", letter="A"
//   → "BSIT-1A"
// =============================================================================
function buildBlockCode(mysqli $conn, string $program, string $yearLevel, string $letter): string
{
    // Resolve program code
    $esc  = $conn->real_escape_string($program);
    $pRes = $conn->query("SELECT code FROM programs WHERE name='$esc' OR code='$esc' LIMIT 1");
    $code = ($pRes && $r = $pRes->fetch_assoc()) ? $r['code'] : strtoupper(substr(preg_replace('/[^A-Z]/i', '', $program), 0, 6));

    // Year number: "1st Year" → 1, "Grade 11" → 11, "2nd Year" → 2
    if (preg_match('/(\d+)/', $yearLevel, $m)) {
        $num = (int)$m[1];
    } else {
        $num = 1;
    }

    return "$code-{$num}{$letter}";
}

// =============================================================================
// GET BLOCKS
// GET ?action=get_blocks[&program=BSIT][&year_level=1st+Year][&semester=…]
// Returns list of blocks with current enrollment count.
// =============================================================================
function getBlocks(mysqli $conn): void
{
    $program    = trim($_GET['program']    ?? '');
    $yearLevel  = trim($_GET['year_level'] ?? '');
    $semester   = trim($_GET['semester']   ?? '');

    $where = 'WHERE b.is_active = 1';
    $params = [];
    $types  = '';

    if ($program !== '') {
        // Match both full name and code
        $where .= " AND (b.program = ? OR b.program IN (SELECT name FROM programs WHERE code = ? LIMIT 1))";
        $params[] = $program;
        $params[] = $program;
        $types   .= 'ss';
    }
    if ($yearLevel !== '') {
        $where .= ' AND b.year_level = ?';
        $params[] = $yearLevel;
        $types   .= 's';
    }
    if ($semester !== '') {
        // Match term portion only (ignore AY suffix for flexibility)
        $termOnly = '';
        if (preg_match('/^(1st Semester|2nd Semester|Summer|Midyear)/i', $semester, $sm)) {
            $termOnly = $sm[1];
        } else {
            $termOnly = $semester;
        }
        $where .= ' AND b.semester LIKE ?';
        $params[] = $termOnly . '%';
        $types   .= 's';
    }

    $sql = "
        SELECT b.*,
               COUNT(s.id) AS enrolled_count
        FROM class_blocks b
        LEFT JOIN students s ON s.block_id = b.id
             AND s.enrollment_status NOT IN ('Graduated','Dropped','Inactive')
             AND s.block_id IS NOT NULL
        $where
        GROUP BY b.id
        ORDER BY b.program, b.year_level, b.block_code
    ";

    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res   = $stmt->get_result();
    $blocks = [];

    while ($row = $res->fetch_assoc()) {
        $blocks[] = [
            'id'            => (int)$row['id'],
            'blockCode'     => $row['block_code'],
            'program'       => $row['program'],
            'yearLevel'     => $row['year_level'],
            'semester'      => $row['semester'],
            'schoolYear'    => $row['school_year'],
            'maxCapacity'   => (int)$row['max_capacity'],
            'enrolledCount' => (int)$row['enrolled_count'],
            'availableSeats'=> max(0, (int)$row['max_capacity'] - (int)$row['enrolled_count']),
            'isFull'        => (int)$row['enrolled_count'] >= (int)$row['max_capacity'],
            'isActive'      => (bool)$row['is_active'],
        ];
    }
    $stmt->close();

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success' => true, 'blocks' => $blocks, 'count' => count($blocks)]);
}

// =============================================================================
// GET BLOCK DETAIL
// GET ?action=get_block_detail&block_id=X
// Returns one block with its students and subject-section mappings.
// =============================================================================
function getBlockDetail(mysqli $conn): void
{
    $blockId = (int)($_GET['block_id'] ?? 0);
    if (!$blockId) {
        echo json_encode(['success' => false, 'message' => 'block_id required']);
        return;
    }

    // Block info
    $bStmt = $conn->prepare("SELECT * FROM class_blocks WHERE id = ? LIMIT 1");
    $bStmt->bind_param('i', $blockId);
    $bStmt->execute();
    $block = $bStmt->get_result()->fetch_assoc();
    $bStmt->close();

    if (!$block) {
        echo json_encode(['success' => false, 'message' => 'Block not found']);
        return;
    }

    // Students in block
    // FIX: Changed JOIN → LEFT JOIN on users so students with a missing/
    //      mismatched user_id still appear instead of being silently excluded.
    $sStmt = $conn->prepare("
        SELECT s.id, s.student_number, s.first_name, s.last_name,
               s.year_level, s.semester, s.enrollment_status,
               s.approval_status, s.payment_status,
               COALESCE(u.email, '') AS email
        FROM students s
        LEFT JOIN users u ON u.id = s.user_id
        WHERE s.block_id = ?
          AND s.enrollment_status NOT IN ('Graduated','Dropped','Inactive')
        ORDER BY s.last_name, s.first_name
    ");
    $sStmt->bind_param('i', $blockId);
    $sStmt->execute();
    $sRes     = $sStmt->get_result();
    $students = [];
    while ($r = $sRes->fetch_assoc()) {
        $students[] = [
            'id'               => (int)$r['id'],
            'studentNumber'    => $r['student_number'],
            'firstName'        => $r['first_name'],
            'lastName'         => $r['last_name'],
            'fullName'         => trim($r['first_name'] . ' ' . $r['last_name']),
            'yearLevel'        => $r['year_level'],
            'semester'         => $r['semester'],
            'enrollmentStatus' => $r['enrollment_status'],
            'approvalStatus'   => $r['approval_status'],
            'paymentStatus'    => $r['payment_status'],
            'email'            => $r['email'],
        ];
    }
    $sStmt->close();

    // Subject-section mappings for this block
    $csStmt = $conn->prepare("
        SELECT bcs.course_id, c.code, c.name, c.credits,
               bcs.course_section_id,
               cs.section_code, cs.day, cs.time_start, cs.time_end,
               r.room_name,
               TRIM(CONCAT(COALESCE(f.first_name,''), ' ', COALESCE(f.last_name,''))) AS instructor
        FROM block_course_sections bcs
        JOIN courses c ON c.id = bcs.course_id
        LEFT JOIN course_sections cs ON cs.id = bcs.course_section_id
        LEFT JOIN faculty f ON f.user_id = cs.faculty_id
        LEFT JOIN rooms r ON r.id = cs.room_id
        WHERE bcs.block_id = ?
        ORDER BY c.code
    ");
    $csStmt->bind_param('i', $blockId);
    $csStmt->execute();
    $csRes    = $csStmt->get_result();
    $subjects = [];
    while ($r = $csRes->fetch_assoc()) {
        $subjects[] = [
            'courseId'        => (int)$r['course_id'],
            'code'            => $r['code'],
            'name'            => $r['name'],
            'credits'         => (int)$r['credits'],
            'courseSectionId' => $r['course_section_id'] ? (int)$r['course_section_id'] : null,
            'sectionCode'     => $r['section_code'] ?? null,
            'day'             => $r['day'] ?? null,
            'time'            => ($r['time_start'] && $r['time_end'])
                                    ? $r['time_start'] . ' - ' . $r['time_end']
                                    : null,
            'room'            => $r['room_name'] ?? null,
            'instructor'      => trim($r['instructor'] ?? ''),
        ];
    }
    $csStmt->close();

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success' => true,
        'block'   => [
            'id'            => (int)$block['id'],
            'blockCode'     => $block['block_code'],
            'program'       => $block['program'],
            'yearLevel'     => $block['year_level'],
            'semester'      => $block['semester'],
            'schoolYear'    => $block['school_year'],
            'maxCapacity'   => (int)$block['max_capacity'],
            'enrolledCount' => count($students),
            'availableSeats'=> max(0, (int)$block['max_capacity'] - count($students)),
            'isFull'        => count($students) >= (int)$block['max_capacity'],
            'isActive'      => (bool)$block['is_active'],
        ],
        'students' => $students,
        'subjects' => $subjects,
    ]);
}

// =============================================================================
// GET STUDENT BLOCK
// GET ?action=get_student_block&student_id=X  (or user_id=X)
// Returns the block assigned to a student, or null if not yet assigned.
// Accessible by the student themselves or by staff.
// =============================================================================
function getStudentBlock(mysqli $conn): void
{
    $authUser = $GLOBALS['authUser'];

    // Resolve student_id
    $studentId = (int)($_GET['student_id'] ?? 0);
    if (!$studentId) {
        $userId = (int)($_GET['user_id'] ?? $authUser['user_id'] ?? 0);
        if ($userId) {
            $r = $conn->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
            $r->bind_param('i', $userId);
            $r->execute();
            $row = $r->get_result()->fetch_assoc();
            $r->close();
            $studentId = $row ? (int)$row['id'] : 0;
        }
    }

    // Students can only see their own block
    if ($authUser['role'] === 'student') {
        $own = $conn->prepare("SELECT id FROM students WHERE id = ? AND user_id = ? LIMIT 1");
        $own->bind_param('ii', $studentId, $authUser['user_id']);
        $own->execute();
        if ($own->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            return;
        }
        $own->close();
    }

    if (!$studentId) {
        echo json_encode(['success' => false, 'message' => 'student_id required']);
        return;
    }

    $stmt = $conn->prepare("
        SELECT s.block_id, b.block_code, b.program, b.year_level,
               b.semester, b.school_year, b.max_capacity,
               COUNT(s2.id) AS enrolled_count
        FROM students s
        LEFT JOIN class_blocks b ON b.id = s.block_id
        LEFT JOIN students s2 ON s2.block_id = b.id
             AND s2.enrollment_status NOT IN ('Graduated','Dropped','Inactive')
        WHERE s.id = ?
        GROUP BY s.id, b.id
        LIMIT 1
    ");
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || !$row['block_id']) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode([
            'success'      => true,
            'block'        => null,
            'message'      => 'No block assigned yet. Please wait for the Registrar to assign your class block.',
        ]);
        return;
    }

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success' => true,
        'block'   => [
            'id'            => (int)$row['block_id'],
            'blockCode'     => $row['block_code'],
            'program'       => $row['program'],
            'yearLevel'     => $row['year_level'],
            'semester'      => $row['semester'],
            'schoolYear'    => $row['school_year'],
            'maxCapacity'   => (int)$row['max_capacity'],
            'enrolledCount' => (int)$row['enrolled_count'],
        ],
    ]);
}

// =============================================================================
// CREATE BLOCK
// POST { program, year_level, semester, school_year, max_capacity? }
// Manually create a new block with the next available letter.
// =============================================================================
function createBlock(mysqli $conn, array $data): void
{
    $authUser = $GLOBALS['authUser'];
    if (!in_array($authUser['role'], ['registrar', 'admin'], true)) {
        echo json_encode(['success' => false, 'message' => 'Access denied. Registrar or Admin only.']);
        return;
    }

    $program     = trim($data['program']     ?? '');
    $yearLevel   = trim($data['year_level']  ?? '');
    $semester    = trim($data['semester']    ?? '');
    $schoolYear  = trim($data['school_year'] ?? '');
    $maxCapacity = max(1, (int)($data['max_capacity'] ?? 40));

    foreach (['program', 'year_level', 'semester', 'school_year'] as $f) {
        if ($$f === '') {
            echo json_encode(['success' => false, 'message' => "Field '$f' is required"]);
            return;
        }
    }

    // Find the last block letter for this program+year+semester
    $esc = $conn->real_escape_string($program);
    $ylE = $conn->real_escape_string($yearLevel);
    $smE = $conn->real_escape_string($semester);
    $lastRes = $conn->query("
        SELECT block_code FROM class_blocks
        WHERE program = '$esc' AND year_level = '$ylE' AND semester LIKE '" . $conn->real_escape_string(explode(',', $semester)[0]) . "%'
        ORDER BY block_code DESC LIMIT 1
    ");
    $lastCode   = $lastRes ? ($lastRes->fetch_assoc()['block_code'] ?? '') : '';
    $lastLetter = $lastCode ? substr($lastCode, strrpos($lastCode, '-') + 2) : '';
    $nextLetter = nextBlockLetter($lastLetter);

    $blockCode = buildBlockCode($conn, $program, $yearLevel, $nextLetter);

    // Also derive school_year from semester label if not provided
    if ($schoolYear === '' && preg_match('/AY\s*(\d{4}-\d{4})/i', $semester, $m)) {
        $schoolYear = $m[1];
    }

    $stmt = $conn->prepare("
        INSERT INTO class_blocks (block_code, program, year_level, semester, school_year, max_capacity)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE max_capacity = VALUES(max_capacity), is_active = 1
    ");
    $stmt->bind_param('sssssi', $blockCode, $program, $yearLevel, $semester, $schoolYear, $maxCapacity);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $newId = $conn->insert_id ?: (int)$conn->query("SELECT id FROM class_blocks WHERE block_code='$blockCode' AND program='$esc' LIMIT 1")->fetch_assoc()['id'];
        logAuditShared($conn, $authUser, 'CREATE_BLOCK', 'class_blocks', $newId,
            "Block $blockCode created for $program $yearLevel ($semester)");
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => true, 'message' => "Block $blockCode created.", 'block_id' => $newId, 'block_code' => $blockCode]);
    } else {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(['success' => false, 'message' => 'Failed to create block: ' . $conn->error]);
    }
    $stmt->close();
}

// =============================================================================
// UPDATE BLOCK
// POST { block_id, max_capacity?, is_active? }
// =============================================================================
function updateBlock(mysqli $conn, array $data): void
{
    $authUser = $GLOBALS['authUser'];
    if (!in_array($authUser['role'], ['registrar', 'admin'], true)) {
        echo json_encode(['success' => false, 'message' => 'Access denied.']);
        return;
    }

    $blockId     = (int)($data['block_id']    ?? 0);
    $maxCapacity = isset($data['max_capacity']) ? max(1, (int)$data['max_capacity']) : null;
    $isActive    = isset($data['is_active'])    ? ((bool)$data['is_active'] ? 1 : 0) : null;

    if (!$blockId) {
        echo json_encode(['success' => false, 'message' => 'block_id required']);
        return;
    }

    $parts  = [];
    $params = [];
    $types  = '';

    if ($maxCapacity !== null) {
        // Prevent reducing below current enrollment count
        $cntRes = $conn->prepare("SELECT COUNT(*) AS c FROM students WHERE block_id = ? AND enrollment_status NOT IN ('Graduated','Dropped','Inactive')");
        $cntRes->bind_param('i', $blockId);
        $cntRes->execute();
        $currentCount = (int)$cntRes->get_result()->fetch_assoc()['c'];
        $cntRes->close();
        if ($maxCapacity < $currentCount) {
            echo json_encode(['success' => false, 'message' => "Cannot set capacity to $maxCapacity — block currently has $currentCount students enrolled."]);
            return;
        }
        $parts[]  = 'max_capacity = ?';
        $params[] = $maxCapacity;
        $types   .= 'i';
    }
    if ($isActive !== null) {
        $parts[]  = 'is_active = ?';
        $params[] = $isActive;
        $types   .= 'i';
    }

    if (empty($parts)) {
        echo json_encode(['success' => false, 'message' => 'Nothing to update']);
        return;
    }

    $params[] = $blockId;
    $types   .= 'i';

    $stmt = $conn->prepare("UPDATE class_blocks SET " . implode(', ', $parts) . " WHERE id = ?");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success' => true, 'message' => 'Block updated.']);
}

// =============================================================================
// ASSIGN BLOCK
// POST { student_id, block_id }
// Manually assign (or reassign) a student to a specific block.
// Registrar/Admin only.
// =============================================================================
function assignBlock(mysqli $conn, array $data): void
{
    $authUser = $GLOBALS['authUser'];
    if (!in_array($authUser['role'], ['registrar', 'admin'], true)) {
        echo json_encode(['success' => false, 'message' => 'Access denied.']);
        return;
    }

    $studentId = (int)($data['student_id'] ?? 0);
    $blockId   = (int)($data['block_id']   ?? 0);

    if (!$studentId || !$blockId) {
        echo json_encode(['success' => false, 'message' => 'student_id and block_id required']);
        return;
    }

    // Verify block exists and is active
    $bStmt = $conn->prepare("SELECT * FROM class_blocks WHERE id = ? AND is_active = 1 LIMIT 1");
    $bStmt->bind_param('i', $blockId);
    $bStmt->execute();
    $block = $bStmt->get_result()->fetch_assoc();
    $bStmt->close();

    if (!$block) {
        echo json_encode(['success' => false, 'message' => 'Block not found or inactive']);
        return;
    }

    // Check capacity (excluding the student being reassigned, in case they're already in this block)
    $capStmt = $conn->prepare("
        SELECT COUNT(*) AS c FROM students
        WHERE block_id = ? AND id != ?
          AND enrollment_status NOT IN ('Graduated','Dropped','Inactive')
    ");
    $capStmt->bind_param('ii', $blockId, $studentId);
    $capStmt->execute();
    $currentCount = (int)$capStmt->get_result()->fetch_assoc()['c'];
    $capStmt->close();

    if ($currentCount >= (int)$block['max_capacity']) {
        echo json_encode([
            'success' => false,
            'message' => "Block {$block['block_code']} is full ({$currentCount}/{$block['max_capacity']} students).",
            'isFull'  => true,
        ]);
        return;
    }

    // Assign
    $upd = $conn->prepare("UPDATE students SET block_id = ? WHERE id = ?");
    $upd->bind_param('ii', $blockId, $studentId);
    $upd->execute();
    $upd->close();

    // Auto-link the block's course_sections to the student's enrollments
    _syncBlockSectionsToStudent($conn, $studentId, $blockId);

    logAuditShared($conn, $authUser, 'ASSIGN_BLOCK', 'students', $studentId,
        "Student $studentId assigned to block {$block['block_code']} (ID $blockId)");

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success'   => true,
        'message'   => "Student assigned to block {$block['block_code']}.",
        'blockCode' => $block['block_code'],
        'blockId'   => $blockId,
    ]);
}

// =============================================================================
// AUTO-ASSIGN BLOCK
// POST { student_id }
//   — Finds the first block for the student's program+year+semester with space.
//   — If none exists, creates the first block (letter A).
//   — If all existing blocks are full, creates the next block (A→B→C…).
// Safe to call on every enrollment approval; idempotent if already assigned.
// =============================================================================
function autoAssignBlock(mysqli $conn, array $data): void
{
    $authUser = $GLOBALS['authUser'];
    if (!in_array($authUser['role'], ['registrar', 'admin'], true)) {
        echo json_encode(['success' => false, 'message' => 'Access denied.']);
        return;
    }

    $studentId = (int)($data['student_id'] ?? 0);
    if (!$studentId) {
        echo json_encode(['success' => false, 'message' => 'student_id required']);
        return;
    }

    // Load student info
    $sStmt = $conn->prepare("SELECT program, year_level, semester, block_id FROM students WHERE id = ? LIMIT 1");
    $sStmt->bind_param('i', $studentId);
    $sStmt->execute();
    $student = $sStmt->get_result()->fetch_assoc();
    $sStmt->close();

    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        return;
    }

    // Already has a block → idempotent success
    if (!empty($student['block_id'])) {
        $bRes = $conn->prepare("SELECT block_code FROM class_blocks WHERE id = ? LIMIT 1");
        $bRes->bind_param('i', $student['block_id']);
        $bRes->execute();
        $bRow = $bRes->get_result()->fetch_assoc();
        $bRes->close();
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode([
            'success'   => true,
            'message'   => 'Student already has a block assigned.',
            'blockCode' => $bRow['block_code'] ?? '',
            'blockId'   => (int)$student['block_id'],
            'isNew'     => false,
        ]);
        return;
    }

    $program   = $student['program'];
    $yearLevel = $student['year_level'];
    $semester  = $student['semester'];

    // Extract school_year from semester label (e.g. "1st Semester, AY 2026-2027" → "2026-2027")
    $schoolYear = '';
    if (preg_match('/AY\s*(\d{4}-\d{4})/i', $semester, $ayM)) {
        $schoolYear = $ayM[1];
    } else {
        $y = (int)date('Y');
        $schoolYear = "$y-" . ($y + 1);
    }

    // Semester term (strip AY suffix for LIKE matching)
    $semTerm = $semester;
    if (preg_match('/^(1st Semester|2nd Semester|Summer|Midyear)/i', $semester, $sm)) {
        $semTerm = $sm[1];
    }

    // Find blocks for this program+year+semester ordered by block_code
    $esc = $conn->real_escape_string($program);
    $ylE = $conn->real_escape_string($yearLevel);
    $smE = $conn->real_escape_string($semTerm);

    $blocksRes = $conn->query("
        SELECT b.id, b.block_code, b.max_capacity,
               COUNT(s.id) AS enrolled_count
        FROM class_blocks b
        LEFT JOIN students s ON s.block_id = b.id
             AND s.enrollment_status NOT IN ('Graduated','Dropped','Inactive')
        WHERE b.program = '$esc'
          AND b.year_level = '$ylE'
          AND b.semester LIKE '$smE%'
          AND b.is_active = 1
        GROUP BY b.id
        ORDER BY b.block_code ASC
    ");

    $targetBlockId   = null;
    $targetBlockCode = '';
    $isNewBlock      = false;
    $lastLetter      = '';

    if ($blocksRes) {
        while ($b = $blocksRes->fetch_assoc()) {
            $lastLetter = substr($b['block_code'], strrpos($b['block_code'], '-') + 2);
            if ((int)$b['enrolled_count'] < (int)$b['max_capacity']) {
                $targetBlockId   = (int)$b['id'];
                $targetBlockCode = $b['block_code'];
                break;
            }
        }
    }

    // No block with space found — create a new one
    if (!$targetBlockId) {
        $nextLetter      = nextBlockLetter($lastLetter); // '' → 'A', 'A' → 'B', etc.
        $targetBlockCode = buildBlockCode($conn, $program, $yearLevel, $nextLetter);
        $maxCap          = (int)($data['max_capacity'] ?? 40);

        $ins = $conn->prepare("
            INSERT INTO class_blocks (block_code, program, year_level, semester, school_year, max_capacity)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $ins->bind_param('sssssi', $targetBlockCode, $program, $yearLevel, $semester, $schoolYear, $maxCap);
        $ins->execute();
        $targetBlockId = $ins->insert_id;
        $ins->close();
        $isNewBlock = true;

        logAuditShared($conn, $authUser, 'CREATE_BLOCK', 'class_blocks', $targetBlockId,
            "Auto-created block $targetBlockCode for $program $yearLevel ($semester)");
    }

    // Assign student
    $upd = $conn->prepare("UPDATE students SET block_id = ? WHERE id = ?");
    $upd->bind_param('ii', $targetBlockId, $studentId);
    $upd->execute();
    $upd->close();

    _syncBlockSectionsToStudent($conn, $studentId, $targetBlockId);

    logAuditShared($conn, $authUser, 'AUTO_ASSIGN_BLOCK', 'students', $studentId,
        "Student $studentId auto-assigned to block $targetBlockCode (ID $targetBlockId)" . ($isNewBlock ? ' [new block]' : ''));

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success'   => true,
        'message'   => "Student assigned to block $targetBlockCode." . ($isNewBlock ? ' (New block created.)' : ''),
        'blockCode' => $targetBlockCode,
        'blockId'   => $targetBlockId,
        'isNew'     => $isNewBlock,
    ]);
}

// =============================================================================
// ASSIGN BLOCK SECTION
// POST { block_id, course_id, course_section_id }
// Link a course_sections row to a block for a specific subject.
// After linking, all students in the block see the same schedule for that subject.
// =============================================================================
function assignBlockSection(mysqli $conn, array $data): void
{
    $authUser = $GLOBALS['authUser'];
    if (!in_array($authUser['role'], ['registrar', 'admin'], true)) {
        echo json_encode(['success' => false, 'message' => 'Access denied.']);
        return;
    }

    $blockId         = (int)($data['block_id']          ?? 0);
    $courseId        = (int)($data['course_id']         ?? 0);
    $courseSectionId = (int)($data['course_section_id'] ?? 0);

    if (!$blockId || !$courseId) {
        echo json_encode(['success' => false, 'message' => 'block_id and course_id required']);
        return;
    }

    $csId = $courseSectionId ?: null;

    $stmt = $conn->prepare("
        INSERT INTO block_course_sections (block_id, course_id, course_section_id)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE course_section_id = VALUES(course_section_id)
    ");
    $stmt->bind_param('iii', $blockId, $courseId, $csId);
    $stmt->execute();
    $stmt->close();

    logAuditShared($conn, $authUser, 'ASSIGN_BLOCK_SECTION', 'class_blocks', $blockId,
        "Block $blockId — course $courseId linked to section $courseSectionId");

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success' => true, 'message' => 'Section assigned to block subject.']);
}

// =============================================================================
// PRIVATE — Sync block course_sections to a student's enrollments.
// When a student is assigned to a block that already has section mappings,
// their enrollment rows inherit the same course_section (schedule/room/faculty).
// This is informational only — the actual scheduling is in course_sections.
// =============================================================================
function _syncBlockSectionsToStudent(mysqli $conn, int $studentId, int $blockId): void
{
    // Get all course-section mappings for this block
    $res = $conn->prepare("SELECT course_id, course_section_id FROM block_course_sections WHERE block_id = ?");
    $res->bind_param('i', $blockId);
    $res->execute();
    $mappings = $res->get_result()->fetch_all(MYSQLI_ASSOC);
    $res->close();

    // For each mapping, if the student is enrolled in that course, nothing extra is needed
    // in the DB (course_sections is already joined in getSchedule/getEnrollments).
    // This function is a placeholder for future expansion (e.g. storing section_id on enrollments).
    // Currently course_sections is joined via course_id, so all students see the active section.
    // No-op for now — hook point for future direct assignment.
    unset($mappings); // suppress unused variable
}

// =============================================================================
// COMPATIBILITY SHIM — if called from registrar.php via require_once,
// the functions above are available directly. The HTTP router at the top
// of this file will NOT run (basename check not needed — registrar.php
// require_once's this file only for the function definitions, not the router).
// =============================================================================