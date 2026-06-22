<?php
/**
 * Database seed script.
 *
 * Usage:
 *   cd backend
 *   php database/seed.php
 *
 * Loads .env (if vlucas/phpdotenv is installed), connects via PDO, inserts
 * demo users (with PHP-bcrypt-hashed passwords) + sample events + sample
 * forum posts + sample feedback + sample notifications + sample calendar.
 *
 * Safe to re-run: existing demo users are skipped based on email.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

if (class_exists(\Dotenv\Dotenv::class) && file_exists(__DIR__ . '/../.env')) {
    \Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->load();
}

function env(string $key, string $default = ''): string {
    $v = getenv($key);
    return $v === false ? $default : $v;
}

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    env('DB_HOST', '127.0.0.1'),
    env('DB_PORT', '3306'),
    env('DB_NAME', 'unievent_db'),
    env('DB_CHARSET', 'utf8mb4')
);

try {
    $pdo = new PDO($dsn, env('DB_USER', 'root'), env('DB_PASS', ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

echo "Connected to database. Seeding...\n";

// ─── Demo users ──────────────────────────────────────────────────────────────
$users = [
    ['Campus Organizer', 'organizer@unievents.test', 'organizer123', 'organizer', 'CO', 'bg-purple-500', '', 'Student Affairs'],
    ['Demo Student',     'student@unievents.test',  'student123',    'student',   'DS', 'bg-blue-500',   'A23CS0001', 'Computer Science'],
    ['Loai AlQadasi',    'loai@unievents.test',     'loai123',       'student',   'LA', 'bg-emerald-500','A23EC9010', 'Computer Engineering'],
    ['Admin User',       'admin@unievents.test',    'admin123',      'admin',     'AU', 'bg-rose-500',   '', 'Administration'],
];

$userIds = [];
$check = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
$insert = $pdo->prepare(
    'INSERT INTO users (name, email, password, role, avatar, avatar_color, student_id, department) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);

foreach ($users as $u) {
    $check->execute([$u[1]]);
    $existing = $check->fetch();
    if ($existing) {
        $userIds[$u[1]] = (int)$existing['user_id'];
        echo "  - User {$u[1]} already exists (id={$userIds[$u[1]]}), skipping.\n";
        continue;
    }
    $hash = password_hash($u[2], PASSWORD_BCRYPT, ['cost' => 10]);
    $insert->execute([$u[0], $u[1], $hash, $u[3], $u[4], $u[5], $u[6], $u[7]]);
    $userIds[$u[1]] = (int)$pdo->lastInsertId();
    echo "  + Created user {$u[1]} (id={$userIds[$u[1]]}).\n";
}

$organizerId = $userIds['organizer@unievents.test'];
$studentId   = $userIds['student@unievents.test'];
$loaiId      = $userIds['loai@unievents.test'];

// ─── Events ─────────────────────────────────────────────────────────────────
$events = [
    ['UTM Tech Innovation Summit 2026', 'A full-day summit showcasing the latest innovations in AI, cloud computing, and cybersecurity. Industry leaders from top tech companies will share insights on emerging trends and career opportunities.', 'Technology', '2026-07-15', '9:00 AM', '5:00 PM', 'UTM Main Hall', 500, 478, 'RM 25'],
    ['Career Fair 2026', 'Connect with over 50 top employers from various industries. Bring your resume and prepare for on-the-spot interviews. Professional headshot service available.', 'Career', '2026-07-20', '10:00 AM', '4:00 PM', 'UTM Sports Complex', 800, 765, 'Free'],
    ['AI & Machine Learning Workshop', 'Hands-on workshop covering neural networks, deep learning, and practical ML applications. Laptops required. Certificate of participation provided.', 'Academic', '2026-07-25', '2:00 PM', '6:00 PM', 'Computer Lab B2-305', 50, 32, 'RM 15'],
    ['Inter-Faculty Sports Tournament', 'Annual sports competition featuring football, basketball, badminton, and volleyball. All faculties welcome to participate.', 'Sports', '2026-08-01', '8:00 AM', '6:00 PM', 'UTM Sports Arena', 300, 280, 'Free'],
    ['Digital Art Exhibition', 'Showcasing student artwork in digital painting, 3D modeling, and interactive media. Live art demonstrations and creative workshops.', 'Arts', '2026-08-05', '11:00 AM', '7:00 PM', 'UTM Art Gallery', 200, 185, 'Free Entry'],
    ['Campus Music Festival', 'Live performances by student bands, solo artists, and special guest performers. Food trucks and merchandise stalls available.', 'Entertainment', '2026-08-10', '6:00 PM', '11:00 PM', 'UTM Open Stage', 600, 540, 'RM 30'],
];

$eventIds = [];
$checkE = $pdo->prepare('SELECT event_id FROM events WHERE title = ?');
$insertE = $pdo->prepare(
    'INSERT INTO events (organizer_id, title, description, category, event_date, start_time, end_time, venue, capacity, available_seats, price, image_url, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "", "open")'
);

foreach ($events as $e) {
    $checkE->execute([$e[0]]);
    $existing = $checkE->fetch();
    if ($existing) {
        $eventIds[$e[0]] = (int)$existing['event_id'];
        continue;
    }
    $insertE->execute([$organizerId, $e[0], $e[1], $e[2], $e[3], $e[4], $e[5], $e[6], $e[7], $e[8], $e[9]]);
    $eventIds[$e[0]] = (int)$pdo->lastInsertId();
    echo "  + Created event: {$e[0]}\n";
}

// ─── Forum posts ────────────────────────────────────────────────────────────
$posts = [
    [$studentId, $eventIds['UTM Tech Innovation Summit 2026'] ?? 1, 'What to expect at the Tech Summit?', 'I am planning to attend the UTM Tech Innovation Summit this July. Has anyone been to previous editions? What topics are usually covered and should I prepare anything specific before attending?', 'Demo Student'],
    [$loaiId,    $eventIds['Career Fair 2026'] ?? 2, 'Career Fair tips and resume advice', 'The Career Fair is coming up soon. I wanted to start a thread where we can share tips on how to prepare, what to bring, and how to make a great impression with recruiters. Please share your experiences!', 'Loai AlQadasi'],
];

$checkP = $pdo->prepare('SELECT post_id FROM forum_posts WHERE title = ?');
$insertP = $pdo->prepare('INSERT INTO forum_posts (user_id, event_id, title, content, author) VALUES (?, ?, ?, ?, ?)');

foreach ($posts as $p) {
    $checkP->execute([$p[2]]);
    if ($checkP->fetch()) continue;
    $insertP->execute($p);
}

// ─── Comments ───────────────────────────────────────────────────────────────
// (Skip if existing)
$pdo->query('SELECT 1 FROM comments LIMIT 1')->fetch();
if ($pdo->query('SELECT COUNT(*) as c FROM comments')->fetch()['c'] == 0) {
    $firstPostId = (int)$pdo->query('SELECT post_id FROM forum_posts ORDER BY post_id ASC LIMIT 1')->fetch()['post_id'];
    $secondPostId = (int)$pdo->query('SELECT post_id FROM forum_posts ORDER BY post_id ASC LIMIT 1 OFFSET 1')->fetch()['post_id'];
    $cStmt = $pdo->prepare('INSERT INTO comments (post_id, user_id, comment_text, author) VALUES (?, ?, ?, ?)');
    $cStmt->execute([$firstPostId, $loaiId, 'I went last year and it was amazing! The keynote on cloud computing was particularly insightful.', 'Loai AlQadasi']);
    if ($secondPostId) {
        $cStmt->execute([$secondPostId, $studentId, 'Great initiative! I would add that you should research the companies attending beforehand.', 'Demo Student']);
    }
}

// ─── Feedback ───────────────────────────────────────────────────────────────
$checkF = $pdo->prepare('SELECT feedback_id FROM feedback WHERE user_id = ? AND event_id = ?');
$insertF = $pdo->prepare('INSERT INTO feedback (user_id, event_id, rating, review, author) VALUES (?, ?, ?, ?, ?)');
$feedback = [
    [$studentId, $eventIds['UTM Tech Innovation Summit 2026'] ?? 1, 5, 'The Tech Summit last year was incredibly well-organized. Looking forward to this year even more!', 'Demo Student'],
    [$loaiId,    $eventIds['Career Fair 2026'] ?? 2, 4, 'The Career Fair was very helpful. I got two interview callbacks from the companies I spoke with.', 'Loai AlQadasi'],
];
foreach ($feedback as $f) {
    $checkF->execute([$f[0], $f[1]]);
    if ($checkF->fetch()) continue;
    $insertF->execute($f);
}

// ─── Notifications ──────────────────────────────────────────────────────────
$checkN = $pdo->prepare('SELECT notification_id FROM notifications WHERE user_id = ? AND title = ?');
$insertN = $pdo->prepare('INSERT INTO notifications (user_id, title, message, notification_type, is_read) VALUES (?, ?, ?, ?, ?)');
$notifs = [
    [$studentId, 'Registration Confirmed', 'Your registration for the Tech Innovation Summit has been confirmed!', 'success', 0],
    [$studentId, 'Event Reminder', 'The Career Fair starts in 3 days. Remember to bring your resume!', 'warning', 0],
    [$loaiId, 'Welcome to UniEvents', 'Your account has been created successfully. Explore upcoming events and book your tickets!', 'info', 1],
];
foreach ($notifs as $n) {
    $checkN->execute([$n[0], $n[1]]);
    if ($checkN->fetch()) continue;
    $insertN->execute($n);
}

// ─── Calendar events ────────────────────────────────────────────────────────
$checkC = $pdo->prepare('SELECT calendar_id FROM calendar_events WHERE user_id = ? AND event_id = ?');
$insertC = $pdo->prepare('INSERT INTO calendar_events (user_id, event_id, title, calendar_date, start_time, end_time, venue) VALUES (?, ?, ?, ?, ?, ?, ?)');
$cal = [
    [$studentId, $eventIds['UTM Tech Innovation Summit 2026'] ?? 1, 'UTM Tech Innovation Summit 2026', '2026-07-15', '9:00 AM', '5:00 PM', 'UTM Main Hall'],
    [$studentId, $eventIds['Career Fair 2026'] ?? 2, 'Career Fair 2026', '2026-07-20', '10:00 AM', '4:00 PM', 'UTM Sports Complex'],
];
foreach ($cal as $c) {
    $checkC->execute([$c[0], $c[1]]);
    if ($checkC->fetch()) continue;
    $insertC->execute($c);
}

echo "\n✓ Seed complete.\n";
echo "Demo login accounts:\n";
foreach ($users as $u) {
    echo "  {$u[3]}: {$u[1]} / {$u[2]}\n";
}
