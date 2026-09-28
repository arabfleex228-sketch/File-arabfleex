<?php
// --- تفعيل عرض الأخطاء ---
ini_set('display_errors', 0); // كتم الأخطاء لمنعها من إفساد الـ JSON
error_reporting(E_ALL);

// --- ملف الاتصال بقاعدة البيانات (مرة واحدة هنا تكفي) ---
require_once 'admin/db_config.php';
$conn->set_charset("utf8");

// تحديد رابط الموقع ديناميكياً ليعمل على أي دومين فوراً
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
$base_url = $protocol . $_SERVER['HTTP_HOST'];

// ==========================================
// --- نظام الصيانة (Maintenance Mode) المباشر ---
// ==========================================
$is_maintenance = false;
$maintenance_msg = "الموقع تحت الصيانة حالياً لتقديم تجربة أفضل، سنعود قريباً.";

$check_m = $conn->query("SELECT * FROM site_settings WHERE id = 1 LIMIT 1");
if ($check_m && $row_m = $check_m->fetch_assoc()) {
    if ($row_m['maintenance_mode'] == 1) {
        $is_maintenance = true;
        $maintenance_msg = !empty($row_m['maintenance_msg']) ? htmlspecialchars($row_m['maintenance_msg']) : $maintenance_msg;
    }
}

if ($is_maintenance && empty($_SESSION['admin_id'])) {
    ?>
    <!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>تحت الصيانة - عرب فليكس</title>
        <link rel="icon" type="image/png" href="favicon.png">
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <style>
            body { font-family: 'Cairo', sans-serif; background-color: #050505; color: #f5f5f5; }
            .golden-text { background: linear-gradient(to right, #DAA520, #FCD34D); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        </style>
    </head>
    <body class="flex flex-col items-center justify-center min-h-screen bg-[#050505] p-4 relative overflow-hidden">
        <!-- خلفية ضبابية تجميلية -->
        <div class="absolute inset-0 z-0 opacity-20 pointer-events-none">
            <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-[#DAA520] rounded-full mix-blend-screen filter blur-[120px]"></div>
            <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-red-900 rounded-full mix-blend-screen filter blur-[120px]"></div>
        </div>

        <div class="relative z-10 max-w-lg w-full bg-white/5 backdrop-blur-2xl border border-white/10 rounded-3xl p-8 sm:p-12 text-center shadow-2xl">
            <div class="inline-block p-5 rounded-full bg-amber-500/10 mb-6 border border-amber-500/20">
                <i class="fas fa-tools text-5xl text-[#DAA520] animate-bounce"></i>
            </div>
            
            <h1 class="text-4xl font-black mb-2 tracking-wider"><span class="golden-text">عرب</span> فليكس</h1>
            <h2 class="text-xl font-bold text-gray-300 mb-6">نحن نقوم ببعض التحديثات!</h2>
            
            <div class="bg-black/50 border border-white/5 rounded-xl p-4 mb-8">
                <p class="text-gray-400 font-semibold leading-relaxed text-sm sm:text-base">
                    <?php echo $maintenance_msg; ?>
                </p>
            </div>
            
            <div class="w-16 h-1.5 bg-gradient-to-r from-transparent via-[#DAA520] to-transparent mx-auto rounded-full mb-8"></div>
            
            <p class="text-xs font-bold text-gray-500 mb-4">تابعنا لمعرفة وقت العودة:</p>
            <div class="flex justify-center gap-5">
                <a href="https://t.me/+nJKDP7wVXqo4Njk0" target="_blank" class="w-12 h-12 rounded-full bg-black/50 border border-white/10 flex items-center justify-center text-gray-400 hover:text-[#DAA520] hover:border-[#DAA520] hover:scale-110 transition-all duration-300 shadow-lg"><i class="fab fa-telegram text-xl"></i></a>
                <a href="https://www.facebook.com/groups/1316846693161456/" target="_blank" class="w-12 h-12 rounded-full bg-black/50 border border-white/10 flex items-center justify-center text-gray-400 hover:text-[#DAA520] hover:border-[#DAA520] hover:scale-110 transition-all duration-300 shadow-lg"><i class="fab fa-facebook-f text-xl"></i></a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// إنشاء/تحديث جدول الطلبات لإضافة الأعمدة الجديدة
$create_table_sql = "CREATE TABLE IF NOT EXISTS `requests` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `ticket_id` varchar(20) DEFAULT NULL,
    `type` varchar(50) NOT NULL,
    `name` varchar(255) NOT NULL,
    `email` varchar(255) NOT NULL,
    `work_name` varchar(255) DEFAULT NULL,
    `work_link` text DEFAULT NULL,
    `description` text DEFAULT NULL,
    `status` varchar(50) DEFAULT 'new',
    `admin_reply` text DEFAULT NULL,
    `is_read` tinyint(1) DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_ticket` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
$conn->query($create_table_sql);

// تحديث صامت للجدول القديم إن وجد
$check_req_col = $conn->query("SHOW COLUMNS FROM requests LIKE 'status'");
if ($check_req_col && $check_req_col->num_rows == 0) {
    $conn->query("ALTER TABLE requests ADD COLUMN status VARCHAR(50) DEFAULT 'new' AFTER description");
}
$check_req_ticket = $conn->query("SHOW COLUMNS FROM requests LIKE 'ticket_id'");
if ($check_req_ticket && $check_req_ticket->num_rows == 0) {
    $conn->query("ALTER TABLE requests ADD COLUMN ticket_id VARCHAR(20) UNIQUE AFTER id");
    $conn->query("ALTER TABLE requests ADD COLUMN admin_reply TEXT AFTER status");
}

// --- AJAX: نظام إرسال الطلبات ---
if (isset($_GET['ajax_action']) && $_GET['ajax_action'] == 'submit_request') {
    ob_start(); 
    
    // إنشاء رقم تتبع فريد
    $ticket_id = 'REQ-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
    // إضافة اسم الدومين
    $host = $_SERVER['HTTP_HOST']; 

    $type = isset($_POST['type']) ? $conn->real_escape_string($_POST['type']) : 'request';
    if ($type === 'feedback') {
        $name = isset($_POST['fb_name']) ? $conn->real_escape_string($_POST['fb_name']) : '';
        $email = isset($_POST['fb_email']) ? $conn->real_escape_string($_POST['fb_email']) : '';
        $description = isset($_POST['description']) ? $conn->real_escape_string($_POST['description']) : '';
        $work_name = ''; $work_link = '';
    } else {
        $name = isset($_POST['name']) ? $conn->real_escape_string($_POST['name']) : '';
        $email = isset($_POST['email']) ? $conn->real_escape_string($_POST['email']) : '';
        $work_name = isset($_POST['work_name']) ? $conn->real_escape_string($_POST['work_name']) : '';
        $work_link = isset($_POST['work_link']) ? $conn->real_escape_string($_POST['work_link']) : '';
        $description = '';
    }

    $sql = "INSERT INTO requests (ticket_id, type, name, email, work_name, work_link, description, status, domain_name) 
            VALUES ('$ticket_id', '$type', '$name', '$email', '$work_name', '$work_link', '$description', 'new', '$host')";

    $result = $conn->query($sql);
    $error_msg = $conn->error;

    ob_end_clean(); 
    header('Content-Type: application/json; charset=utf-8');

    if ($result === TRUE) {
        echo json_encode([
            'success' => true, 
            'ticket_id' => $ticket_id,
            'msg' => "تم إرسال طلبك بنجاح! رقم التتبع الخاص بك هو: <span class='text-[var(--brand-gold)] font-black text-lg tracking-widest'>$ticket_id</span><br><span class='text-xs mt-2 block'>يرجى الاحتفاظ بهذا الرقم لمتابعة حالة طلبك.</span>"
        ]);
    } else {
        echo json_encode(['success' => false, 'msg' => 'عذراً، حدث خطأ أثناء الحفظ: ' . $error_msg]);
    }
    exit;
}

// --- AJAX: نظام تتبع الطلبات ---
if (isset($_GET['ajax_action']) && $_GET['ajax_action'] == 'track_request') {
    ob_start();
    $ticket = isset($_POST['ticket_id']) ? trim($conn->real_escape_string($_POST['ticket_id'])) : '';
    
    $response = ['success' => false, 'html' => ''];
    if(!empty($ticket)) {
        $res = $conn->query("SELECT * FROM requests WHERE ticket_id = '$ticket' LIMIT 1");
        if($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            
            $statusStr = ''; $statusIcon = ''; $statusColor = '';
            switch($row['status']) {
                case 'new': $statusStr = 'تم استلام الطلب وبانتظار المراجعة'; $statusIcon = 'fa-inbox'; $statusColor = 'text-blue-400 bg-blue-500/10 border-blue-500/20'; break;
                case 'processing': $statusStr = 'جاري العمل على توفير طلبك'; $statusIcon = 'fa-spinner fa-spin'; $statusColor = 'text-orange-400 bg-orange-500/10 border-orange-500/20'; break;
                case 'completed': $statusStr = 'تم الانتهاء / متوفر الآن'; $statusIcon = 'fa-check-circle'; $statusColor = 'text-green-400 bg-green-500/10 border-green-500/20'; break;
                case 'rejected': $statusStr = 'عذراً، لا يمكن توفير الطلب حالياً'; $statusIcon = 'fa-times-circle'; $statusColor = 'text-red-400 bg-red-500/10 border-red-500/20'; break;
            }

            $workTitle = $row['type'] == 'request' ? "<div class='text-xs text-gray-500 mb-1'>العمل المطلوب:</div><div class='font-bold text-white'>".htmlspecialchars($row['work_name'])."</div>" : "<div class='text-xs text-gray-500 mb-1'>نوع الطلب:</div><div class='font-bold text-white'>اقتراح / شكوى</div>";

            $adminNotes = '';
            if(!empty($row['admin_reply'])) {
                $reply_text = htmlspecialchars($row['admin_reply']);
                // تحويل الروابط لنصوص قابلة للضغط
                $reply_text = preg_replace('/(https?:\/\/[^\s]+)/', '<a href="$1" target="_blank" class="text-[var(--brand-gold)] hover:underline break-all">$1</a>', $reply_text);
                
                $adminNotes = "
                <div class='mt-5 p-4 bg-white/5 border border-white/10 rounded-xl relative'>
                    <div class='absolute -top-3 right-4 bg-black px-2 text-xs font-bold text-[var(--brand-gold)]'>رد الإدارة</div>
                    <p class='text-sm text-gray-300 leading-relaxed mt-1'>$reply_text</p>
                </div>";
            }

            $date = date('Y/m/d h:i A', strtotime($row['created_at']));

            $response['success'] = true;
            $response['html'] = "
                <div class='bg-black/40 border border-white/10 rounded-2xl p-5 shadow-inner'>
                    <div class='flex flex-wrap items-start justify-between gap-4 border-b border-white/5 pb-4 mb-4'>
                        <div>$workTitle</div>
                        <div class='text-left'>
                            <div class='text-xs text-gray-500 mb-1'>رقم التتبع:</div>
                            <div class='font-mono font-bold text-white tracking-wider'>{$row['ticket_id']}</div>
                        </div>
                    </div>
                    
                    <div class='mb-4'>
                        <div class='text-xs text-gray-500 mb-2'>حالة الطلب الحالية:</div>
                        <div class='inline-flex items-center gap-2 px-4 py-2 rounded-lg border font-bold text-sm $statusColor shadow-lg'>
                            <i class='fas $statusIcon'></i> $statusStr
                        </div>
                    </div>
                    
                    $adminNotes

                    <div class='mt-5 text-[10px] text-gray-500 text-left border-t border-white/5 pt-3'>
                        تاريخ الطلب: $date
                    </div>
                </div>
            ";
        } else {
            $response['html'] = "<div class='p-4 bg-red-500/10 border border-red-500/20 text-red-400 text-center rounded-xl font-bold'><i class='fas fa-exclamation-triangle mr-2'></i> رقم التتبع غير صحيح أو غير موجود.</div>";
        }
    }
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// ==========================================
// باقي أكواد السيرفر الخاصة بالصفحة
// ==========================================
$check_ep_date = $conn->query("SHOW COLUMNS FROM episodes LIKE 'created_at'");
if ($check_ep_date && $check_ep_date->num_rows == 0) {
    $conn->query("ALTER TABLE episodes ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
}

if (isset($_GET['ajax_action']) && $_GET['ajax_action'] == 'heartbeat') { header('Content-Type: application/json'); echo json_encode(['success' => true]); exit; }
if (isset($_GET['ajax_action']) && $_GET['ajax_action'] == 'log_search') {
    $keyword = isset($_POST['keyword']) ? trim($conn->real_escape_string($_POST['keyword'])) : '';
    $host = $_SERVER['HTTP_HOST'];
    if(!empty($keyword)) { $conn->query("INSERT INTO search_logs (keyword, search_count, domain_name) VALUES ('$keyword', 1, '$host') ON DUPLICATE KEY UPDATE search_count = search_count + 1, last_searched = CURRENT_TIMESTAMP"); } exit;
}
if (isset($_GET['ajax_action']) && $_GET['ajax_action'] == 'log_demographics') {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    $session_id = session_id(); $country = isset($_POST['country']) ? $conn->real_escape_string($_POST['country']) : 'غير معروف'; $device = isset($_POST['device']) ? $conn->real_escape_string($_POST['device']) : 'Desktop';
    $conn->query("UPDATE visitor_log SET country = '$country', device_type = '$device' WHERE session_id = '$session_id' AND (country IS NULL OR country = 'غير معروف' OR country = '')"); exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS `views_log` ( `id` int(11) NOT NULL AUTO_INCREMENT, `content_type` varchar(50) NOT NULL, `content_id` int(11) NOT NULL, `viewed_at` timestamp NOT NULL DEFAULT current_timestamp(), PRIMARY KEY (`id`), KEY `idx_time` (`viewed_at`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

if (isset($_GET['ajax_action']) && $_GET['ajax_action'] == 'reg_movie_view') {
    ob_start(); if (session_status() === PHP_SESSION_NONE) { session_start(); }
    $movie_id = isset($_POST['movie_id']) ? intval($_POST['movie_id']) : 0;
    $host = $_SERVER['HTTP_HOST'];
    if ($movie_id > 0) {
        if (!isset($_SESSION['viewed_movies'])) { $_SESSION['viewed_movies'] = []; }
        if (!in_array($movie_id, $_SESSION['viewed_movies'])) {
            $conn->query("UPDATE movies SET views = views + 1 WHERE id = $movie_id");
            $conn->query("INSERT INTO views_log (content_type, content_id, domain_name) VALUES ('movie', $movie_id, '$host')");
            $_SESSION['viewed_movies'][] = $movie_id; $response = ['success' => true];
        } else { $response = ['success' => true]; }
    } else { $response = ['success' => false]; }
    ob_end_clean(); header('Content-Type: application/json'); echo json_encode($response); exit;
}

if (isset($_GET['ajax_action']) && $_GET['ajax_action'] == 'reg_view') {
    ob_start(); if (session_status() === PHP_SESSION_NONE) { session_start(); }
    $ep_id = isset($_POST['episode_id']) ? intval($_POST['episode_id']) : 0;
    $host = $_SERVER['HTTP_HOST'];
    if ($ep_id > 0) {
        if (!isset($_SESSION['viewed_eps'])) { $_SESSION['viewed_eps'] = []; }
        if (!in_array($ep_id, $_SESSION['viewed_eps'])) {
            $series_res = $conn->query("SELECT series_id FROM episodes WHERE id = $ep_id LIMIT 1");
            if ($series_res && $series_res->num_rows > 0) {
                $s_id = $series_res->fetch_assoc()['series_id'];
                $conn->query("UPDATE episodes SET views = views + 1 WHERE id = $ep_id");
                $conn->query("UPDATE series SET views = views + 1 WHERE id = $s_id");
                $conn->query("INSERT INTO views_log (content_type, content_id, domain_name) VALUES ('series', $s_id, '$host')");
                $conn->query("INSERT INTO views_log (content_type, content_id, domain_name) VALUES ('episode', $ep_id, '$host')");
                $_SESSION['viewed_eps'][] = $ep_id; $response = ['success' => true];
            } else { $response = ['success' => false]; }
        } else { $response = ['success' => true]; }
    } else { $response = ['success' => false]; }
    ob_end_clean(); header('Content-Type: application/json'); echo json_encode($response); exit;
}

if (isset($_GET['ajax_action']) && $_GET['ajax_action'] == 'get_details') {
    ob_start(); $type = isset($_GET['type']) ? $_GET['type'] : ''; $id = isset($_GET['id']) ? intval($_GET['id']) : 0; $response = ['success' => false, 'data' => null];
    if ($id > 0) {
        if ($type === 'movies') {
            $res = $conn->query("SELECT * FROM movies WHERE id = $id LIMIT 1");
            if ($res && $res->num_rows > 0) { $response['success'] = true; $response['data'] = $res->fetch_assoc(); }
        } elseif ($type === 'series' || $type === 'wrestling' || $type === 'tvshows') {
            $res = $conn->query("SELECT * FROM series WHERE id = $id LIMIT 1");
            if ($res && $res->num_rows > 0) {
                $series_data = $res->fetch_assoc();
                $ep_res = $conn->query("SELECT id, title, watch_link, watch_link_2, watch_link_3, watch_link_4, episode_number FROM episodes WHERE series_id = $id AND (is_published = 1 OR is_published IS NULL) ORDER BY episode_number ASC");
                $episodes = [];
                if ($ep_res) { while ($ep = $ep_res->fetch_assoc()) { $episodes[] = $ep; } }
                $series_data['episodes'] = $episodes; $response['success'] = true; $response['data'] = $series_data;
            }
        }
    }
    ob_end_clean(); header('Content-Type: application/json; charset=utf-8'); echo json_encode($response); exit;
}

function php_slugify($text) { 
    $text = trim($text ?? '');
    $text = preg_replace('/\s+/', '-', $text);
    $text = preg_replace('/[\/\\\?%\*:\|"<>.]/', '', $text);
    $text = preg_replace('/--+/', '-', $text);
    return $text; 
}

$seo_settings = []; $seo_res = $conn->query("SELECT * FROM seo_settings");
if($seo_res) { while($row = $seo_res->fetch_assoc()) { $seo_settings[$row['page_key']] = $row; } }

// التعديل هنا: الترتيب بناءً على الإصدار الدقيق إن وجد، وإلا تاريخ الإضافة
$movies_result = $conn->query("SELECT id, title, poster, year, rating, quality, genre, category, is_recent, release_date, created_at, LEFT(description, 160) AS description FROM movies WHERE is_published = 1 OR is_published IS NULL ORDER BY COALESCE(release_date, created_at) DESC, id DESC");
$movies = []; $arabic_movies = []; $foreign_movies = []; $indian_movies = [];
while ($row = $movies_result->fetch_assoc()) {
    $row['id_prefix'] = 'm' . $row['id']; $movies[] = $row;
    if (isset($row['category']) && $row['category'] == 'foreign_movie') { $foreign_movies[] = $row; } 
    elseif (isset($row['category']) && $row['category'] == 'indian_movie') { $indian_movies[] = $row; } 
    else { $arabic_movies[] = $row; }
}

// التعديل هنا: الترتيب بناءً على الإصدار الدقيق إن وجد، وإلا تاريخ الإضافة
$series_result = $conn->query("SELECT id, title, poster, year, rating, genre, category, is_recent, ramadan_year, continue_after_ramadan, views, release_date, created_at, LEFT(description, 160) AS description FROM series WHERE is_published = 1 OR is_published IS NULL ORDER BY COALESCE(release_date, created_at) DESC, id DESC");
$series_list = []; $arabic_series = []; $turkish_series = []; $foreign_series = []; $indian_series = []; $tv_shows = []; $wrestling_shows = [];
while ($row = $series_result->fetch_assoc()) { $row['id_prefix'] = 's' . $row['id']; $series_list[$row['id']] = $row; }
$series = array_values($series_list);

foreach ($series as $s) {
    $cat = isset($s['category']) ? $s['category'] : 'series';
    if ($cat == 'turkish') { $turkish_series[] = $s; } 
    elseif ($cat == 'tv_show') { $tv_shows[] = $s; } 
    elseif ($cat == 'foreign') { $foreign_series[] = $s; } 
    elseif ($cat == 'indian') { $indian_series[] = $s; } 
    elseif ($cat == 'wrestling') { $wrestling_shows[] = $s; } 
    else { $arabic_series[] = $s; }
}

$ramadan_series = []; foreach ($series as $s) { if (isset($s['ramadan_year']) && ($s['ramadan_year'] == '2026' || $s['ramadan_year'] == 2026)) { $ramadan_series[] = $s; } }
$top_ramadan_series = $ramadan_series; usort($top_ramadan_series, function($a, $b) { $viewsA = isset($a['views']) ? (int)$a['views'] : 0; $viewsB = isset($b['views']) ? (int)$b['views'] : 0; return $viewsB - $viewsA; }); $top_ramadan_series = array_slice($top_ramadan_series, 0, 3);

// التعديل هنا لترتيب شريط "أضيف حديثاً" و "الهيرو"
$hero_items = []; $hero_result = $conn->query("(SELECT id, title, 'movies' as type, poster, year, genre, rating, quality, release_date, created_at, NULL as ramadan_year, 0 as continue_after_ramadan, 0 as second_half_ramadan FROM movies WHERE is_recent = 1 AND (is_published = 1 OR is_published IS NULL)) UNION ALL (SELECT id, title, 'series' as type, poster, year, genre, rating, NULL as quality, release_date, created_at, ramadan_year, continue_after_ramadan, second_half_ramadan FROM series WHERE is_recent = 1 AND (is_published = 1 OR is_published IS NULL)) ORDER BY COALESCE(release_date, created_at) DESC, id DESC LIMIT 20");
if ($hero_result && $hero_result->num_rows > 0) { while($row = $hero_result->fetch_assoc()){ $hero_items[] = $row; } }

$recent_items = []; $recent_result = $conn->query("(SELECT id, title, 'movies' as type, poster, year, genre, rating, quality, release_date, created_at, NULL as ramadan_year, 0 as continue_after_ramadan, 0 as second_half_ramadan FROM movies WHERE is_recent = 1 AND (is_published = 1 OR is_published IS NULL)) UNION ALL (SELECT id, title, 'series' as type, poster, year, genre, rating, NULL as quality, release_date, created_at, ramadan_year, continue_after_ramadan, second_half_ramadan FROM series WHERE is_recent = 1 AND (ramadan_year IS NULL OR ramadan_year != 2026) AND (is_published = 1 OR is_published IS NULL)) ORDER BY COALESCE(release_date, created_at) DESC, id DESC LIMIT 15");
if ($recent_result && $recent_result->num_rows > 0) { while($row = $recent_result->fetch_assoc()){ $recent_items[] = $row; } }

$latest_episodes = []; 
$ep_query = "SELECT e.id as ep_id, e.title as ep_title, e.episode_number, s.id as series_id, s.title as series_title, s.poster, s.rating FROM episodes e JOIN series s ON e.series_id = s.id WHERE (s.is_published = 1 OR s.is_published IS NULL) AND (e.is_published = 1 OR e.is_published IS NULL) AND (e.show_in_latest = 1 OR e.show_in_latest IS NULL) ORDER BY e.created_at DESC LIMIT 30";
$ep_res = $conn->query($ep_query); if ($ep_res) { while($row = $ep_res->fetch_assoc()) { $latest_episodes[] = $row; } }

$uri = $_SERVER['REQUEST_URI'];
$page_title = $seo_settings['home']['title'] ?? "عرب فليكس مشاهدة أحدث الأفلام والمسلسلات";
$page_desc = $seo_settings['home']['description'] ?? "استمتع بمشاهدة الأفلام والمسلسلات الحصرية ومسلسلات رمضان 2026 بجودة عالية علي موقع عرب فليكس Arab Fleex";
$page_keywords = $seo_settings['home']['keywords'] ?? "افلام, مسلسلات, رمضان 2026, مشاهدة مباشرة, عرب فليكس, Arab Fleex, مسلسلات عربية, افلام اجنبية";
$page_image = $base_url . "/favicon.png"; // تعديل ديناميكي لرمز الفايكون
$schema_json = ''; // متغير لحفظ كود Schema.org

if (strpos($uri, '/details/') !== false) {
    $parts = explode('/', trim($uri, '/'));
    if (isset($parts[1]) && isset($parts[2])) {
        $type = $parts[1]; $id_pref = $parts[2]; $id_num = substr($id_pref, 1); $item = null;
        if ($type == 'movies') { foreach($movies as $m) if($m['id'] == $id_num) { $item = $m; break; } } 
        else { foreach($series as $s) if($s['id'] == $id_num) { $item = $s; break; } }
        
        if ($item) {
            $item_label = "مسلسل "; if ($type == 'movies') { $item_label = "فيلم "; } elseif (isset($item['category']) && $item['category'] == 'tv_show') { $item_label = "برنامج "; } elseif (isset($item['category']) && $item['category'] == 'wrestling') { $item_label = "عرض "; }
            $page_title = "مشاهدة " . $item_label . $item['title'] . " - عرب فليكس";
            $page_desc = "مشاهدة وتحميل " . $item['title'] . " بجودة عالية. " . mb_substr(strip_tags($item['description'] ?? ''), 0, 150) . "...";
            $page_image = $item['poster'];
            
            $schema_type = ($type == 'movies') ? 'Movie' : 'TVSeries';
            $schema_data = [
                "@context" => "https://schema.org",
                "@type" => $schema_type,
                "name" => $item['title'],
                "image" => $item['poster'],
                "description" => strip_tags($item['description'] ?? ''),
                "dateCreated" => $item['year'] ?? ''
            ];
            $schema_json = '<script type="application/ld+json">' . json_encode($schema_data, JSON_UNESCAPED_UNICODE) . '</script>';
        }
    }
} elseif (strpos($uri, '/ramadan/') !== false) {
    $year = explode('/', $uri)[2] ?? '2026';
    $page_title = "مسلسلات رمضان $year عرب فليكس Arab Fleex";
    $page_desc = "تابع حصرياً أقوى مسلسلات رمضان لعام $year على موقع عرب فليكس Arab Fleex بجودة عالية وبدون إعلانات.";
} elseif (strpos($uri, '/all-movies') !== false) { $page_title = $seo_settings['movies']['title'] ?? "أفلام عرب فليكس شاهد أحدث الأفلام بجودة عالية"; $page_desc = $seo_settings['movies']['description'] ?? "استمتع بمشاهدة الأفلام مع أقوي مكتبة ضخمة من أحدث الأفلام العربية علي موقع عرب فليكس Arab Fleex";
} elseif (strpos($uri, '/all-foreign-movies') !== false) { $page_title = "الأفلام الأجنبية - عرب فليكس شاهد أحدث الأفلام"; $page_desc = "استمتع بمشاهدة وتحميل أحدث الأفلام الأجنبية المترجمة بجودة عالية.";
} elseif (strpos($uri, '/all-indian-movies') !== false) { $page_title = "الأفلام الهندية - عرب فليكس شاهد أحدث الأفلام"; $page_desc = "استمتع بمشاهدة وتحميل أحدث وأقوى الأفلام الهندية المترجمة بجودة عالية.";
} elseif (strpos($uri, '/all-series') !== false) { $page_title = $seo_settings['series']['title'] ?? "مسلسلات عرب فليكس تابع أقوى و أحدث المسلسلات علي عرب فليكس"; $page_desc = $seo_settings['series']['description'] ?? "استمتع بمشاهد أحدث المسلسلات و مسلسلات رمضان 2026 والحصريات بجودة عالية علي موقع عرب فليكس Arab Fleex";
} elseif (strpos($uri, '/all-turkish') !== false) { $page_title = "المسلسلات التركية - عرب فليكس شاهد أحدث الحلقات"; $page_desc = "استمتع بمشاهدة أحدث وأقوى المسلسلات التركية مترجمة ومدبلجة بجودة عالية.";
} elseif (strpos($uri, '/all-foreign') !== false) { $page_title = "المسلسلات الأجنبية - عرب فليكس"; $page_desc = "استمتع بمشاهدة أحدث وأقوى المسلسلات الأجنبية مترجمة بجودة عالية.";
} elseif (strpos($uri, '/all-indian-series') !== false) { $page_title = "المسلسلات الهندية - عرب فليكس"; $page_desc = "استمتع بمشاهدة أحدث وأقوى المسلسلات الهندية المترجمة والمدبلجة بجودة عالية.";
} elseif (strpos($uri, '/all-tvshows') !== false) { $page_title = "البرامج التلفزيونية - عرب فليكس"; $page_desc = "تابع أحدث برامج المقالب والبرامج الحوارية والترفيهية بجودة عالية."; 
} elseif (strpos($uri, '/all-wrestling') !== false) { $page_title = "المصارعة الحرة - عرب فليكس"; $page_desc = "شاهد أحدث عروض ومهرجانات المصارعة الحرة WWE و AEW بجودة عالية."; }

$db_data = [ 
    'movies' => $movies, 'arabic_movies' => $arabic_movies, 'foreign_movies' => $foreign_movies, 'indian_movies' => $indian_movies,
    'series' => $series, 'arabic_series' => $arabic_series, 'turkish_series' => $turkish_series, 'foreign_series' => $foreign_series, 'indian_series' => $indian_series, 'tv_shows' => $tv_shows, 'wrestling' => $wrestling_shows,
    'hero' => $hero_items, 'recent' => $recent_items, 'latest_episodes' => $latest_episodes,
    'ramadan' => $ramadan_series, 'top_ramadan' => $top_ramadan_series, 'seo' => $seo_settings
];

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <base href="/"> 
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    
    <link rel="icon" type="image/png" href="favicon.png">

    <?php if (isset($ad_settings) && $ad_settings['status'] == 1): ?>
        <?php if (!empty($ad_settings['popunder_code'])) echo $ad_settings['popunder_code']; ?>
        <?php if (!empty($ad_settings['social_bar'])) echo $ad_settings['social_bar']; ?>
    <?php endif; ?>
    
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" as="style">
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" as="style">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <?php if (!empty($schema_json)) echo $schema_json . "\n"; ?>
    <style>
        :root { --background-dark: #050505; --background-light: #111111; --brand-gold: #DAA520; --brand-gold-light: #FCD34D; --brand-red: #E50914; --text-primary: #F3F4F6; --border-color: rgba(255, 255, 255, 0.08); }
        @keyframes slideInUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        html { scroll-behavior: smooth; }
        body { font-family: 'Cairo', sans-serif; background-color: var(--background-dark); color: var(--text-primary); overflow-x: hidden; -webkit-tap-highlight-color: transparent; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; background-image: radial-gradient(circle at top center, rgba(218, 165, 32, 0.03) 0%, transparent 50%); }
        .card-container, .swiper-slide, a { -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none; -webkit-touch-callout: none; }
        p, h1, h2, h3, span { -webkit-user-select: auto; -moz-user-select: auto; -ms-user-select: auto; user-select: auto; }
        ::-webkit-scrollbar { width: 6px; } ::-webkit-scrollbar-track { background: var(--background-dark); } ::-webkit-scrollbar-thumb { background: rgba(218, 165, 32, 0.3); border-radius: 10px; } ::-webkit-scrollbar-thumb:hover { background: var(--brand-gold); }
        .cast-scrollbar::-webkit-scrollbar { height: 4px; } .cast-scrollbar::-webkit-scrollbar-track { background: transparent; } .cast-scrollbar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 10px; } .cast-scrollbar::-webkit-scrollbar-thumb:hover { background: var(--brand-gold); }
        
        .page { display: none; opacity: 0; transition: opacity 0.4s ease-in-out; will-change: opacity; } .page.page-active { display: block; opacity: 1; }
        .golden-text { background: linear-gradient(to right, var(--brand-gold), var(--brand-gold-light)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .card-container { animation: slideInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; opacity: 0; display: block;}
        .card-play-icon { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) scale(0.5); opacity: 0; transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); font-size: 3.5rem; color: rgba(255,255,255,0.9); text-shadow: 0 0 20px rgba(0,0,0,0.8); z-index: 10; pointer-events: none;} .card-container:hover .card-play-icon { transform: translate(-50%, -50%) scale(1); opacity: 1; }
        .section-title { display: flex; align-items: center; gap: 0.75rem; font-size: 1.5rem; sm:font-size: 1.75rem; font-weight: 900; margin-bottom: 2rem; padding-bottom: 0.75rem; position: relative; color: white; } .section-title i { font-size: 1.5rem; background: linear-gradient(to right, var(--brand-gold), #fff); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        #hero-section { position: relative; width: 100%; height: 60vh; min-height: 450px; max-height: 600px; } #hero-section::after { content:''; position:absolute; bottom:0; left:0; right:0; height:150px; background:linear-gradient(to top, var(--background-dark) 0%, transparent 100%); z-index:20; pointer-events:none;}
        .hero-slider .swiper-slide { position: relative; overflow: hidden; background-position: center; background-size: cover; width: 75%; border-radius: 1.5rem; transition: transform 0.5s ease; opacity:0.5; transform: scale(0.9);} .hero-slider .swiper-slide-active { opacity:1; transform: scale(1); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.7); border: 1px solid rgba(255,255,255,0.1);} @media (min-width: 768px) { .hero-slider .swiper-slide { width: 50%; } } @media (min-width: 1024px) { .hero-slider .swiper-slide { width: 40%; } }
        .hero-slider .swiper-slide::before { content: ''; position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,0.95) 0%, rgba(0,0,0,0.2) 50%, rgba(0,0,0,0) 100%); z-index: 5; } .slider-content { position: absolute; bottom: 0; right: 0; left: 0; z-index: 10; padding: 2.5rem 2rem; text-align: center; }
        .swiper-pagination-bullet { background: rgba(255, 255, 255, 0.3); width: 8px; height: 8px; transition: all 0.3s;} .swiper-pagination-bullet-active { background: var(--brand-gold); width: 24px; border-radius: 4px; }
        .swiper-button-next, .swiper-button-prev { color: white; background-color: rgba(255,255,255,0.1); width: 44px; height: 44px; border-radius: 50%; backdrop-filter: blur(10px); transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.05);} .swiper-button-next:hover, .swiper-button-prev:hover { background-color: var(--brand-gold); color: black; transform: scale(1.1);} .swiper-button-next::after, .swiper-button-prev::after { font-size: 1rem; font-weight: 900; }
        .content-slider { padding: 15px 0 25px 0; overflow: hidden; margin: -15px 0; } .content-slider .swiper-slide { width: 30%; transition: transform 0.3s; height: auto; } @media (min-width: 480px) { .content-slider .swiper-slide { width: 25%; } } @media (min-width: 640px) { .content-slider .swiper-slide { width: 22%; } } @media (min-width: 768px) { .content-slider .swiper-slide { width: 18%; } } @media (min-width: 1024px) { .content-slider .swiper-slide { width: 15%; } } @media (min-width: 1280px) { .content-slider .swiper-slide { width: 13%; } } .content-slider .swiper-slide:hover { z-index: 10; }
        .content-next, .content-prev { color: white; background: rgba(0,0,0,0.6); backdrop-filter: blur(5px); width: 40px; height: 40px; border-radius: 50%; top: 40%; transform: translateY(-50%); border: 1px solid rgba(255,255,255,0.1); } .content-next:hover, .content-prev:hover { background: var(--brand-gold); color: black; border-color:transparent;} .content-next::after, .content-prev::after { font-size: 1.2rem; font-weight: 900; }
        .view-more-btn { display: block; text-align: center; margin: 3rem auto 0; padding: 0.8rem 2.5rem; background: rgba(255,255,255,0.03); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); border-radius: 99px; font-weight: bold; transition: all 0.3s ease; width: fit-content; color: #ccc;} .view-more-btn:hover { background: var(--brand-gold); color: black; border-color: var(--brand-gold); transform: translateY(-3px); box-shadow: 0 10px 20px rgba(218, 165, 32, 0.2); }
        .pagination-controls { display: flex; justify-content: center; align-items: center; gap: 1rem; margin-top: 3rem; } .pagination-btn { padding: 0.6rem 1.8rem; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 99px; font-weight: bold; transition: all 0.3s ease; } .pagination-btn:not(.disabled):hover { background: var(--brand-gold); color: black; border-color: var(--brand-gold); }
        .modal { transition: opacity 0.3s ease, visibility 0.3s ease; } .modal-content { transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); } .modal.invisible .modal-content { transform: scale(0.9); }
        .tab-btn { background-color: rgba(255,255,255,0.02); border-bottom: 2px solid transparent; transition: all 0.3s ease; color: #888;} .tab-btn.active { color: var(--brand-gold); border-bottom-color: var(--brand-gold); background-color: rgba(218, 165, 32, 0.05); }
        #bottom-nav { background: rgba(10, 10, 10, 0.85); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.08); bottom: 0; left: 0; right: 0; border-radius: 1.5rem 1.5rem 0 0; border-bottom: none; padding-bottom: calc(0.2rem + env(safe-area-inset-bottom)); box-shadow: 0 -10px 40px rgba(0,0,0,0.6); z-index: 60;} .bottom-nav-active { color: var(--brand-gold) !important; transform: translateY(-2px);} .bottom-nav-active svg { filter: drop-shadow(0 2px 8px rgba(218,165,32,0.6)); } .bottom-nav-link svg, .bottom-nav-link i { transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .ep-badge-glow { box-shadow: 0 0 20px rgba(218,165,32,0.3), inset 0 0 10px rgba(255,255,255,0.1); }
        #live-search-results::-webkit-scrollbar { width: 4px; } #live-search-results::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 8px; }
        .glass-header { background: rgba(5, 5, 5, 0.7); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); border-bottom: 1px solid rgba(255,255,255,0.05); } .glass-panel { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .ad-container { text-align: center; margin: 20px auto; max-width: 100%; overflow: hidden; display: flex; justify-content: center; }
    </style>
    <?php include 'seo_meta.php'; ?>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-M3H32TLKDR"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-M3H32TLKDR');
</script>
</head>
<body class="antialiased flex flex-col min-h-screen">
    
    <div id="prayer-banner" class="bg-gradient-to-r from-amber-900/90 via-black to-amber-900/90 text-[var(--brand-gold)] border-b border-[var(--brand-gold)]/20 py-2.5 px-4 text-xs sm:text-sm font-bold z-50 relative flex justify-between items-center shadow-lg transition-all duration-300 backdrop-blur-sm">
        <div class="flex-1 flex justify-center items-center gap-2">
            <i class="fas fa-mosque text-base animate-pulse"></i>
            <span>أخي الكريم، لا تدع مشاهدة الأفلام والمسلسلات تلهيك عن ذكر الله وعن أداء الصلاة في وقتها.</span>
        </div>
        <button onclick="closePrayerBanner()" class="text-gray-400 hover:text-white transition-colors px-2 ml-2 focus:outline-none bg-white/5 rounded-full w-6 h-6 flex items-center justify-center">
            <i class="fas fa-times text-sm"></i>
        </button>
    </div>

    <!-- قائمة الهامبرجر الجانبية (Sidebar) -->
    <div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[190] opacity-0 invisible transition-all duration-300"></div>
    <div id="main-sidebar" class="fixed inset-y-0 right-0 w-72 bg-[#0a0a0a]/95 backdrop-blur-2xl border-l border-white/10 shadow-2xl z-[200] transform translate-x-full transition-transform duration-300 overflow-y-auto">
        <div class="p-6 flex flex-col h-full relative">
            <div class="flex justify-between items-center mb-10 pb-4 border-b border-white/10">
                <a href="/" class="flex items-center gap-2 group">
                    <span class="font-black text-2xl tracking-wider"><span class="golden-text">عرب</span> <span class="text-white">فليكس</span></span>
                </a>
                <button onclick="toggleSidebar()" class="text-gray-400 hover:text-white text-3xl focus:outline-none bg-white/5 w-10 h-10 rounded-full flex justify-center items-center"><i class="fas fa-times"></i></button>
            </div>
            
            <div class="flex flex-col gap-3 font-bold text-lg">
                <a href="/" class="sidebar-link flex items-center gap-4 px-4 py-3 rounded-xl hover:bg-white/10 hover:text-[var(--brand-gold)] text-gray-300 transition-colors"><i class="fas fa-home w-6 text-center"></i> الرئيسية</a>
                <a href="/#movies-section" class="sidebar-link flex items-center gap-4 px-4 py-3 rounded-xl hover:bg-white/10 hover:text-[var(--brand-gold)] text-gray-300 transition-colors"><i class="fas fa-film w-6 text-center"></i> أفلام عربية</a>
                <a href="/#foreign-movies-section" class="sidebar-link flex items-center gap-4 px-4 py-3 rounded-xl hover:bg-white/10 hover:text-[var(--brand-gold)] text-gray-300 transition-colors"><i class="fas fa-film w-6 text-center text-emerald-400"></i> أفلام أجنبية</a>
                <a href="/#indian-movies-section" class="sidebar-link flex items-center gap-4 px-4 py-3 rounded-xl hover:bg-white/10 hover:text-[var(--brand-gold)] text-gray-300 transition-colors"><i class="fas fa-film w-6 text-center text-orange-500"></i> أفلام هندية</a>
                
                <a href="/#series-section" class="sidebar-link flex items-center gap-4 px-4 py-3 rounded-xl hover:bg-white/10 hover:text-[var(--brand-gold)] text-gray-300 transition-colors mt-2"><i class="fas fa-tv w-6 text-center"></i> مسلسلات عربية</a>
                <a href="/#foreign-section" class="sidebar-link flex items-center gap-4 px-4 py-3 rounded-xl hover:bg-white/10 hover:text-[var(--brand-gold)] text-gray-300 transition-colors"><i class="fas fa-globe-americas w-6 text-center text-teal-400"></i> مسلسلات أجنبية</a>
                <a href="/#turkish-section" class="sidebar-link flex items-center gap-4 px-4 py-3 rounded-xl hover:bg-white/10 hover:text-[var(--brand-gold)] text-gray-300 transition-colors"><i class="fas fa-star-and-crescent w-6 text-center text-red-400"></i> مسلسلات تركية</a>
                <a href="/#indian-series-section" class="sidebar-link flex items-center gap-4 px-4 py-3 rounded-xl hover:bg-white/10 hover:text-[var(--brand-gold)] text-gray-300 transition-colors"><i class="fas fa-tv w-6 text-center text-orange-500"></i> مسلسلات هندية</a>
                
                <a href="/#tvshows-section" class="sidebar-link flex items-center gap-4 px-4 py-3 rounded-xl hover:bg-white/10 hover:text-[var(--brand-gold)] text-gray-300 transition-colors mt-2"><i class="fas fa-microphone-alt w-6 text-center text-purple-400"></i> برامج تلفزيونية</a>
                <a href="/#wrestling-section" class="sidebar-link flex items-center gap-4 px-4 py-3 rounded-xl hover:bg-white/10 hover:text-[var(--brand-gold)] text-gray-300 transition-colors"><i class="fas fa-hand-rock w-6 text-center text-orange-400"></i> مصارعة حرة</a>
                <a href="/history" class="sidebar-link flex items-center gap-4 px-4 py-3 rounded-xl hover:bg-white/10 hover:text-[var(--brand-gold)] text-gray-300 transition-colors mt-2"><i class="fas fa-history w-6 text-center text-blue-400"></i> سجل المشاهدة</a>
                
                <div class="h-px w-full bg-white/10 my-2"></div>
                
                <!-- قائمة مسلسلات رمضان المنسدلة -->
                <div>
                    <button onclick="toggleSidebarDropdown('ramadan-dropdown')" class="w-full flex items-center justify-between px-4 py-3 rounded-xl bg-gradient-to-r from-[var(--brand-gold)]/10 to-transparent border border-[var(--brand-gold)]/20 text-[var(--brand-gold)] transition-colors focus:outline-none">
                        <div class="flex items-center gap-4"><i class="fas fa-moon w-6 text-center"></i> مسلسلات رمضان</div>
                        <i id="ramadan-dropdown-icon" class="fas fa-chevron-down text-sm transition-transform duration-300"></i>
                    </button>
                    <div id="ramadan-dropdown" class="hidden flex-col gap-2 pl-12 pr-4 pt-2">
                        <a href="/ramadan/2026" class="sidebar-link block py-2 text-gray-300 hover:text-[var(--brand-gold)] transition-colors font-bold text-sm">رمضان 2026</a>
                        <a href="/ramadan/2025" class="sidebar-link block py-2 text-gray-300 hover:text-[var(--brand-gold)] transition-colors font-bold text-sm">رمضان 2025</a>
                    </div>
                </div>

                <a href="/requests" class="sidebar-link flex items-center gap-4 px-4 py-3 rounded-xl hover:bg-white/10 hover:text-[var(--brand-gold)] text-gray-300 transition-colors mt-2"><i class="fas fa-envelope-open-text w-6 text-center text-yellow-500"></i> الطلبات والشكاوي</a>
            </div>
            
            <div class="mt-auto pt-6 text-center text-gray-500 text-sm">
                &copy; <?php echo date("Y"); ?> عرب فليكس
            </div>
        </div>
    </div>

    <header class="glass-header sticky top-0 z-40 transition-all duration-300">
        <nav class="container mx-auto px-4 lg:px-8">
            <div class="flex items-center justify-between h-20 w-full gap-4">
                
                <div class="flex items-center gap-3 sm:gap-4 lg:gap-6">
                    <!-- زر القائمة الجانبية (همبورجر) -->
                    <button onclick="toggleSidebar()" class="text-gray-300 hover:text-[var(--brand-gold)] transition-colors focus:outline-none p-2 bg-white/5 rounded-xl border border-white/10 flex items-center justify-center shadow-lg">
                        <i class="fas fa-bars text-xl sm:text-2xl"></i>
                    </button>
                    
                    <div class="flex-shrink-0">
                        <a href="/" class="flex items-center gap-2 lg:gap-3 group">
                            <span class="font-black text-xl md:text-2xl tracking-wider group-hover:scale-105 transition-transform"><span class="golden-text">عرب</span> <span class="text-white">فليكس</span></span>
                        </a>
                    </div>
                </div>
                
                <div class="flex-shrink-0 relative z-50">
                    <form id="search-form" class="relative group">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <i class="fas fa-search text-gray-400 text-sm group-focus-within:text-[var(--brand-gold)] transition-colors"></i>
                        </div>
                        <input id="search-bar" type="search" placeholder="ابحث..." class="bg-black/40 border border-white/10 text-white rounded-full py-2 pr-4 pl-10 focus:outline-none focus:ring-1 focus:ring-[var(--brand-gold)] focus:border-[var(--brand-gold)] focus:bg-black/60 w-32 sm:w-36 md:w-44 lg:w-56 xl:w-64 transition-all duration-300 text-sm md:text-base font-bold shadow-inner placeholder-gray-500 leading-normal">
                        
                        <div id="live-search-results" class="absolute top-full left-0 mt-4 w-[280px] sm:w-[350px] md:w-[400px] lg:w-[450px] max-h-[60vh] bg-[#050505] rounded-2xl shadow-[0_10px_30px_rgba(0,0,0,0.9)] overflow-y-auto hidden z-[100] border border-white/10">
                        </div>
                    </form>
                </div>
                
            </div>
        </nav>
    </header>

    <nav id="bottom-nav" class="md:hidden fixed z-[60]">
        <div class="flex justify-around items-center h-[65px] relative px-2">
            <a href="/#movies-section" data-path="/#movies-section" class="bottom-nav-link flex flex-col items-center justify-center gap-1 text-gray-400 hover:text-white transition-colors w-1/5 h-full">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"></rect><line x1="7" y1="2" x2="7" y2="22"></line><line x1="17" y1="2" x2="17" y2="22"></line><line x1="2" y1="12" x2="22" y2="12"></line><line x1="2" y1="7" x2="7" y2="7"></line><line x1="2" y1="17" x2="7" y2="17"></line><line x1="17" y1="17" x2="22" y2="17"></line><line x1="17" y1="7" x2="22" y2="7"></line></svg>
                <span class="text-[10px] font-bold">أفلام</span>
            </a>
            
            <a href="/#series-section" data-path="/#series-section" class="bottom-nav-link flex flex-col items-center justify-center gap-1 text-gray-400 hover:text-white transition-colors w-1/5 h-full">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="15" rx="2" ry="2"></rect><polyline points="17 2 12 7 7 2"></polyline></svg>
                <span class="text-[10px] font-bold">مسلسلات</span>
            </a>

            <a href="/" data-path="/" class="bottom-nav-link flex flex-col items-center justify-center text-gray-400 hover:text-[var(--brand-gold)] transition-colors focus:outline-none w-1/5 h-full relative -top-3">
                <div class="bg-gradient-to-tr from-[var(--brand-gold)] to-yellow-300 w-12 h-12 rounded-full flex items-center justify-center shadow-[0_5px_15px_rgba(218,165,32,0.4)] text-black border-4 border-[#050505] transition-transform hover:scale-105">
                   <i class="fas fa-home text-lg"></i>
                </div>
                <span class="text-[10px] font-bold mt-1 text-[var(--brand-gold)]">الرئيسية</span>
            </a>
            
            <button id="mobile-ramadan-trigger" class="bottom-nav-link flex flex-col items-center justify-center gap-1 text-gray-400 hover:text-white transition-colors w-1/5 h-full focus:outline-none">
                <i class="fas fa-moon text-[20px]"></i>
                <span class="text-[10px] font-bold mt-0.5">رمضان</span>
            </button>

            <a href="/requests" data-path="/requests" class="bottom-nav-link flex flex-col items-center justify-center gap-1 text-gray-400 hover:text-white transition-colors w-1/5 h-full">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                <span class="text-[10px] font-bold">الطلبات</span>
            </a>
        </div>
    </nav>

    <main id="main-content-wrapper" class="relative flex-grow pb-[100px] md:pb-10">
        <div id="home-page" class="page">
             <?php if (isset($ad_settings) && $ad_settings['status'] == 1 && !empty($ad_settings['top_banner'])): ?>
                 <div class="ad-container top-ad mb-4"><?php echo $ad_settings['top_banner']; ?></div>
             <?php endif; ?>

             <section id="hero-section">
                 <div class="swiper hero-slider h-full w-full pt-6">
                     <div class="swiper-wrapper"></div>
                     <div class="swiper-pagination"></div>
                     <div class="swiper-button-prev hidden sm:flex"></div>
                     <div class="swiper-button-next hidden sm:flex"></div>
                 </div>
             </section>

            <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-8 relative z-30">
                <section id="latest-episodes-section" class="mb-14 relative group hidden">
                    <h2 class="section-title text-xl sm:text-2xl"><i class="fa-solid fa-bolt"></i>أحدث الإضافات</h2>
                    <div class="swiper content-slider" id="latest-episodes-swiper"><div class="swiper-wrapper" id="latest-episodes-slider"></div><div class="swiper-button-next content-next opacity-0 group-hover:opacity-100 transition-opacity hidden md:flex"></div><div class="swiper-button-prev content-prev opacity-0 group-hover:opacity-100 transition-opacity hidden md:flex"></div></div>
                </section>

                <?php if (isset($ad_settings) && $ad_settings['status'] == 1 && !empty($ad_settings['native_banner'])): ?>
                    <div class="ad-container native-ad mb-14 glass-panel p-4 rounded-2xl"><?php echo $ad_settings['native_banner']; ?></div>
                <?php endif; ?>

                <section id="recent-section" class="mb-14 relative group hidden">
                    <h2 class="section-title text-xl sm:text-2xl"><i class="fa-solid fa-wand-magic-sparkles"></i>أضيف حديثاً</h2>
                    <div class="swiper content-slider" id="recent-swiper"><div class="swiper-wrapper" id="recent-slider"></div><div class="swiper-button-next content-next opacity-0 group-hover:opacity-100 transition-opacity hidden md:flex"></div><div class="swiper-button-prev content-prev opacity-0 group-hover:opacity-100 transition-opacity hidden md:flex"></div></div>
                </section>
                
                <section id="movies-section" class="mb-16"><h2 class="section-title text-xl sm:text-2xl"><i class="fa-solid fa-film"></i>أفلام عرب فليكس</h2><div id="movies-grid" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-6 gap-3 sm:gap-5"></div><a href="/all-movies/1" class="view-more-btn text-sm sm:text-base">عرض المزيد <i class="fas fa-arrow-left mr-2 text-xs"></i></a></section>
                <section id="foreign-movies-section" class="mb-16 hidden"><h2 class="section-title text-xl sm:text-2xl"><i class="fa-solid fa-film !text-emerald-400 !bg-none" style="-webkit-text-fill-color: #34d399;"></i>الأفلام الأجنبية</h2><div id="foreign-movies-grid" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-6 gap-3 sm:gap-5"></div><a href="/all-foreign-movies/1" class="view-more-btn text-sm sm:text-base">عرض المزيد <i class="fas fa-arrow-left mr-2 text-xs"></i></a></section>
                <section id="indian-movies-section" class="mb-16 hidden"><h2 class="section-title text-xl sm:text-2xl"><i class="fa-solid fa-film !text-orange-500 !bg-none" style="-webkit-text-fill-color: #f97316;"></i>الأفلام الهندية</h2><div id="indian-movies-grid" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-6 gap-3 sm:gap-5"></div><a href="/all-indian-movies/1" class="view-more-btn text-sm sm:text-base">عرض المزيد <i class="fas fa-arrow-left mr-2 text-xs"></i></a></section>
                
                <section id="series-section" class="mb-16"><h2 class="section-title text-xl sm:text-2xl"><i class="fa-solid fa-tv"></i>مسلسلات عرب فليكس</h2><div id="series-grid" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-6 gap-3 sm:gap-5"></div><a href="/all-series/1" class="view-more-btn text-sm sm:text-base">عرض المزيد <i class="fas fa-arrow-left mr-2 text-xs"></i></a></section>
                <section id="foreign-section" class="mb-16 hidden"><h2 class="section-title text-xl sm:text-2xl"><i class="fa-solid fa-globe-americas !text-teal-400 !bg-none" style="-webkit-text-fill-color: #2dd4bf;"></i>المسلسلات الأجنبية</h2><div id="foreign-grid" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-6 gap-3 sm:gap-5"></div><a href="/all-foreign/1" class="view-more-btn text-sm sm:text-base">عرض المزيد <i class="fas fa-arrow-left mr-2 text-xs"></i></a></section>
                <section id="turkish-section" class="mb-16 hidden"><h2 class="section-title text-xl sm:text-2xl"><i class="fa-solid fa-star-and-crescent !text-red-500 !bg-none" style="-webkit-text-fill-color: #ef4444;"></i>المسلسلات التركية</h2><div id="turkish-grid" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-6 gap-3 sm:gap-5"></div><a href="/all-turkish/1" class="view-more-btn text-sm sm:text-base">عرض المزيد <i class="fas fa-arrow-left mr-2 text-xs"></i></a></section>
                <section id="indian-series-section" class="mb-16 hidden"><h2 class="section-title text-xl sm:text-2xl"><i class="fa-solid fa-tv !text-orange-500 !bg-none" style="-webkit-text-fill-color: #f97316;"></i>المسلسلات الهندية</h2><div id="indian-series-grid" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-6 gap-3 sm:gap-5"></div><a href="/all-indian-series/1" class="view-more-btn text-sm sm:text-base">عرض المزيد <i class="fas fa-arrow-left mr-2 text-xs"></i></a></section>
                
                <section id="tvshows-section" class="mb-16 hidden"><h2 class="section-title text-xl sm:text-2xl"><i class="fa-solid fa-microphone-alt !text-purple-400 !bg-none" style="-webkit-text-fill-color: #a78bfa;"></i>البرامج التلفزيونية</h2><div id="tvshows-grid" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-6 gap-3 sm:gap-5"></div><a href="/all-tvshows/1" class="view-more-btn text-sm sm:text-base">عرض المزيد <i class="fas fa-arrow-left mr-2 text-xs"></i></a></section>
                <section id="wrestling-section" class="mb-16 hidden"><h2 class="section-title text-xl sm:text-2xl"><i class="fa-solid fa-hand-rock !text-orange-400 !bg-none" style="-webkit-text-fill-color: #fb923c;"></i>المصارعة الحرة</h2><div id="wrestling-grid" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-6 gap-3 sm:gap-5"></div><a href="/all-wrestling/1" class="view-more-btn text-sm sm:text-base">عرض المزيد <i class="fas fa-arrow-left mr-2 text-xs"></i></a></section>
            </div>

            <?php if (isset($ad_settings) && $ad_settings['status'] == 1 && !empty($ad_settings['bottom_banner'])): ?>
                <div class="ad-container bottom-ad mb-8"><?php echo $ad_settings['bottom_banner']; ?></div>
            <?php endif; ?>

        </div>
        
        <div id="details-page" class="page <?php echo (strpos($uri, '/details/') !== false) ? 'page-active' : ''; ?>">
            <?php if (strpos($uri, '/details/') !== false && isset($item)): ?>
                <!-- هذا المحتوى مخفي للمستخدمين بسبب الـ JS ولكنه مرئي لمحركات البحث للفهرسة السريعة -->
                <div class="container mx-auto px-4 py-8 pointer-events-none absolute opacity-0 z-[-1]">
                    <h1 class="text-4xl text-white font-black mb-4"><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                    <img src="<?php echo htmlspecialchars($item['poster'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?>">
                    <p class="text-gray-300"><?php echo htmlspecialchars($item['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="text-white mt-4">سنة الإنتاج: <?php echo htmlspecialchars($item['year'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <div id="requests-page" class="page"></div>
        <div id="all-items-page" class="page"></div>
        <div id="search-results-page" class="page"></div>
        <div id="history-page" class="page"></div> 
    </main>

    <footer class="bg-black/40 border-t border-white/5 mt-12 pb-[110px] md:pb-8 relative z-50 backdrop-blur-lg">
        <div class="container mx-auto py-10 px-4 sm:px-6 lg:px-8 text-gray-400">
            <div class="flex flex-col items-center justify-center gap-6 text-center">
                
                <a href="/" class="flex items-center justify-center gap-3 group">
                    <span class="font-black text-3xl md:text-4xl tracking-wider group-hover:scale-105 transition-transform"><span class="golden-text">عرب</span> <span class="text-white">فليكس</span></span>
                </a>
                
                <div class="flex flex-wrap justify-center items-center gap-4 sm:gap-6 font-bold text-sm md:text-base">
                    <!-- التعديل هنا: تحويل سياسة الاستخدام إلى DMCA مع أيقونة -->
                    <a href="#" onclick="openModal('policy-modal'); return false;" class="hover:text-[var(--brand-gold)] transition-colors flex items-center gap-1.5"><i class="fas fa-shield-alt text-lg"></i> DMCA</a>
                    <a href="#" onclick="openModal('faq-modal'); return false;" class="hover:text-[var(--brand-gold)] transition-colors">الأسئلة الشائعة</a>
                    <a href="https://www.facebook.com/groups/1316846693161456/?ref=share&mibextid=NSMWBT" target="_blank" class="hover:text-[#1877F2] transition-colors">مجتمع فيسبوك</a>
                </div>
                
                <div class="flex justify-center items-center gap-4 mt-2">
                    <a href="https://www.facebook.com/groups/1316846693161456/?ref=share&mibextid=NSMWBT" target="_blank" rel="noopener noreferrer" aria-label="Facebook" class="w-12 h-12 rounded-full bg-white/5 flex items-center justify-center text-xl text-gray-400 hover:bg-[#1877F2] hover:text-white transition-all duration-300 hover:scale-110"><i class="fab fa-facebook-f"></i></a>
                </div>
                
                <div class="text-sm font-bold text-gray-500 mt-2">
                    <p>&copy; <?php echo date("Y"); ?> عرب فليكس Arab Fleex. جميع الحقوق محفوظة.</p>
                </div>
            </div>
        </div>
    </footer>

    <div id="mobile-ramadan-modal" class="fixed inset-0 z-[100] flex flex-col items-center justify-center bg-black/90 backdrop-blur-md opacity-0 invisible transition-all duration-300 md:hidden">
        <button id="close-ramadan-modal" class="absolute top-6 right-6 text-gray-400 hover:text-white text-4xl p-2 focus:outline-none bg-white/5 rounded-full w-12 h-12 flex items-center justify-center">&times;</button>
        <div class="ramadan-modal-content flex flex-col items-center w-full max-w-sm px-6 transform scale-90 opacity-0 transition-all duration-400 delay-100">
            <div class="flex flex-col items-center mb-10"><div class="w-20 h-20 bg-gradient-to-tr from-[var(--brand-gold)] to-yellow-300 rounded-full flex items-center justify-center shadow-[0_0_30px_rgba(218,165,32,0.4)] mb-4"><i class="fas fa-moon text-4xl text-black"></i></div><h2 class="text-white text-3xl font-black tracking-wide">رمضان</h2></div>
            <div class="flex flex-col gap-4 w-full"><a href="/ramadan/2026" class="glass-panel text-white text-center py-4 rounded-2xl font-bold text-lg hover:bg-[var(--brand-gold)] hover:text-black hover:border-transparent transition-all shadow-lg">مسلسلات رمضان 2026</a><a href="/ramadan/2025" class="glass-panel text-white text-center py-4 rounded-2xl font-bold text-lg hover:bg-[var(--brand-gold)] hover:text-black hover:border-transparent transition-all shadow-lg">مسلسلات رمضان 2025</a></div>
        </div>
    </div>

    <!-- التعديل هنا: تحديث نافذة DMCA بالكامل لتدعم اللغتين والتبديل بينهما -->
    <div id="policy-modal" class="modal fixed inset-0 z-[110] flex items-center justify-center bg-black/80 backdrop-blur-sm invisible opacity-0">
        <div class="modal-content glass-panel rounded-3xl shadow-2xl w-11/12 max-w-2xl p-6 sm:p-8 relative transform scale-95 border border-white/10 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6 border-b border-white/5 pb-4">
                <button onclick="closeModal('policy-modal')" class="text-gray-400 hover:text-white text-3xl focus:outline-none w-10 h-10 rounded-full bg-white/5 flex justify-center items-center">&times;</button>
                <button id="dmca-lang-btn" onclick="toggleDMCALanguage()" class="bg-[#0ba3e6] hover:bg-[#088bbb] text-white px-4 py-2 rounded-full text-sm font-bold transition-colors shadow-lg">Switch to English <i class="fas fa-language ml-1"></i></button>
            </div>
            
            <!-- القسم العربي -->
            <div id="dmca-ar" class="block space-y-5">
                <h2 class="text-2xl font-black mb-6 text-[var(--brand-gold)] text-center">قانون الألفية الجديدة لحقوق طبع ونشر المواد الرقمية (DMCA)</h2>
                <p class="text-sm sm:text-base text-gray-300 leading-relaxed font-semibold">يوفر قانون (DMCA) حماية لمقدمي الخدمة عبر الإنترنت من المسؤولية عن انتهاك حقوق الطبع والنشر، بشرط إزالة المحتوى المخالف فور إخطار مالك الحقوق.</p>
        
                <div class="glass-panel p-5 rounded-xl border-r-4 border-r-[var(--brand-gold)] bg-black/30 shadow-inner">
                    <p class="text-sm sm:text-base text-gray-300 leading-relaxed font-semibold text-justify">
                        <strong class="text-white text-lg">إخلاء مسؤولية:</strong> موقع <strong class="text-white">عرب فليكس</strong> لا يستضيف أي محتوى مرئي على خوادمه. نحن نوفر روابط أو تضمين (Embed) لمحتوى مرفوع على مواقع طرف ثالث. المسؤولية القانونية تقع على عاتق من قام برفع الملفات الأصلية.
                    </p>
                </div>
        
                <p class="text-sm sm:text-base text-gray-300 leading-relaxed font-semibold">لتقديم شكوى رسمية، يرجى مراسلتنا عبر: 
                    <!-- رابط داخلي يوجه لصفحة الطلبات والشكاوي لتجنب البريد الإلكتروني -->
                    <a href="/requests" onclick="closeModal('policy-modal'); setTimeout(()=>document.getElementById('tab-feedback')?.click(), 500);" class="text-[var(--brand-gold)] hover:underline font-bold">صفحة الشكاوي والطلبات</a>
                </p>
        
                <div class="mt-6">
                    <h3 class="text-lg font-black text-white mb-4">المعلومات المطلوبة في الإشعار:</h3>
                    <ul class="space-y-3 text-sm sm:text-base text-gray-300 font-semibold">
                        <li class="flex items-start gap-3"><i class="fas fa-check text-green-500 mt-1"></i> <span>توقيع المالك أو من ينوب عنه قانونياً.</span></li>
                        <li class="flex items-start gap-3"><i class="fas fa-check text-green-500 mt-1"></i> <span>تحديد العمل المحمي بحقوق النشر الذي تم انتهاكه.</span></li>
                        <li class="flex items-start gap-3"><i class="fas fa-check text-green-500 mt-1"></i> <span>رابط (URL) المادة المخالفة على موقعنا.</span></li>
                        <li class="flex items-start gap-3"><i class="fas fa-check text-green-500 mt-1"></i> <span>معلومات الاتصال المباشرة بك.</span></li>
                        <li class="flex items-start gap-3"><i class="fas fa-check text-green-500 mt-1"></i> <span>بيان "حسن نية" يؤكد أن الاستخدام غير مرخص.</span></li>
                        <li class="flex items-start gap-3"><i class="fas fa-check text-green-500 mt-1"></i> <span>إقرار بصحة البيانات تحت طائلة عقوبة الحنث باليمين.</span></li>
                    </ul>
                </div>
            </div>

            <!-- القسم الإنجليزي -->
            <div id="dmca-en" class="hidden space-y-5" dir="ltr" style="text-align: left;">
                <h2 class="text-2xl font-black mb-6 text-[var(--brand-gold)] text-center">Digital Millennium Copyright Act (DMCA)</h2>
                <p class="text-sm sm:text-base text-gray-300 leading-relaxed font-semibold">The DMCA provides protection to internet service providers from liability for copyright infringement, provided that the infringing content is removed upon notification by the rights owner.</p>
        
                <div class="glass-panel p-5 rounded-xl border-l-4 border-l-[var(--brand-gold)] bg-black/30 shadow-inner">
                    <p class="text-sm sm:text-base text-gray-300 leading-relaxed font-semibold text-justify">
                        <strong class="text-white text-lg">Disclaimer:</strong> <strong class="text-white">Arab Fleex</strong> does not host any visual content on its servers. We only provide links or embed content uploaded to third-party sites. Legal responsibility lies with those who uploaded the original files.
                    </p>
                </div>
        
                <p class="text-sm sm:text-base text-gray-300 leading-relaxed font-semibold">To submit a formal complaint, please contact us via: 
                    <a href="/requests" onclick="closeModal('policy-modal'); setTimeout(()=>document.getElementById('tab-feedback')?.click(), 500);" class="text-[var(--brand-gold)] hover:underline font-bold">Requests & Complaints Page</a>
                </p>
        
                <div class="mt-6">
                    <h3 class="text-lg font-black text-white mb-4">Information required in the notice:</h3>
                    <ul class="space-y-3 text-sm sm:text-base text-gray-300 font-semibold">
                        <li class="flex items-start gap-3"><i class="fas fa-check text-green-500 mt-1"></i> <span>Signature of the owner or authorized representative.</span></li>
                        <li class="flex items-start gap-3"><i class="fas fa-check text-green-500 mt-1"></i> <span>Identification of the copyrighted work claimed to have been infringed.</span></li>
                        <li class="flex items-start gap-3"><i class="fas fa-check text-green-500 mt-1"></i> <span>The URL of the infringing material on our site.</span></li>
                        <li class="flex items-start gap-3"><i class="fas fa-check text-green-500 mt-1"></i> <span>Your direct contact information.</span></li>
                        <li class="flex items-start gap-3"><i class="fas fa-check text-green-500 mt-1"></i> <span>A "good faith" statement confirming that the use is unauthorized.</span></li>
                        <li class="flex items-start gap-3"><i class="fas fa-check text-green-500 mt-1"></i> <span>A statement that the information is accurate under penalty of perjury.</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <div id="faq-modal" class="modal fixed inset-0 z-[110] flex items-center justify-center bg-black/80 backdrop-blur-sm invisible opacity-0"><div class="modal-content glass-panel rounded-2xl shadow-2xl w-11/12 max-w-2xl p-6 sm:p-8 relative transform scale-95 border border-white/10 max-h-[85vh] overflow-y-auto"><button onclick="closeModal('faq-modal')" class="absolute top-4 left-4 text-gray-400 hover:text-white text-3xl">&times;</button><h2 class="text-2xl font-black mb-6 flex items-center gap-2"><i class="fas fa-question-circle golden-text"></i> الأسئلة الشائعة</h2><div class="space-y-4"><div class="bg-black/30 p-4 rounded-xl border border-white/5"><p class="font-bold text-white mb-2 text-lg">هل الموقع مجاني؟</p><p class="text-sm text-gray-400 leading-relaxed">نعم، الموقع مجاني بالكامل ولا يطلب منك دفع أي رسوم لمشاهدة الأفلام والمسلسلات.</p></div><div class="bg-black/30 p-4 rounded-xl border border-white/5"><p class="font-bold text-white mb-2 text-lg">هل أحتاج إلى إنشاء حساب للمشاهدة؟</p><p class="text-sm text-gray-400 leading-relaxed">لا، يمكنك تصفح الموقع ومشاهدة كل المحتوى المتوفر مباشرة دون الحاجة لإنشاء حساب أو تسجيل دخول.</p></div><div class="bg-black/30 p-4 rounded-xl border border-white/5"><p class="font-bold text-white mb-2 text-lg">ماذا أفعل إذا كان هناك فيلم أو حلقة لا تعمل؟</p><p class="text-sm text-gray-400 leading-relaxed">نحاول دائماً تحديث الروابط باستمرار، ولكن في حال واجهت مشكلة في التشغيل، يمكنك استخدام صفحة "الطلبات والشكاوي" لإبلاغنا، وسنقوم بإصلاح الخلل أو توفير رابط بديل في أسرع وقت.</p></div><div class="bg-black/30 p-4 rounded-xl border border-white/5"><p class="font-bold text-white mb-2 text-lg">هل يمكنني طلب فيلم أو مسلسل غير موجود؟</p><p class="text-sm text-gray-400 leading-relaxed">بالتأكيد! يمكنك طلب أي عمل غير متوفر عبر التوجه إلى قسم "الطلبات" الموجود في القائمة، وسيقوم فريقنا بتوفيره وإضافته للموقع فور توفره.</p></div></div></div></div>
    <div id="trailer-modal" class="modal fixed inset-0 z-[120] flex items-center justify-center bg-black/95 backdrop-blur-xl invisible opacity-0 transition-all duration-300"><div class="modal-content w-full max-w-5xl p-4 relative"><button onclick="closeTrailerModal()" class="absolute -top-12 left-4 text-white/50 hover:text-white text-5xl transition-colors">&times;</button><div class="aspect-video w-full bg-black rounded-2xl overflow-hidden shadow-2xl border border-white/10 relative"><iframe id="trailer-iframe" class="absolute inset-0 w-full h-full" src="" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div></div></div>

    <script>
        window.addEventListener('error', function(e) {
            console.error("Global JS Error Caught! Forcing Homepage visible...", e);
            document.querySelectorAll('.page').forEach(p => p.classList.remove('page-active'));
            const hp = document.getElementById('home-page');
            if (hp) hp.classList.add('page-active');
        });

        const db = <?php echo json_encode($db_data, JSON_UNESCAPED_UNICODE); ?>;
        
        let homePage, detailsPage, requestsPage, allItemsPage, searchResultsPage, historyPage;
        let activePageElement = null;
        let heroSwiper = null, recentSwiper = null, latestEpisodesSwiper = null;

        // --- وظائف القائمة الجانبية (Sidebar) ---
        window.toggleSidebar = function() {
            const sidebar = document.getElementById('main-sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            if (sidebar.classList.contains('translate-x-full')) {
                sidebar.classList.remove('translate-x-full');
                overlay.classList.remove('opacity-0', 'invisible');
                document.body.style.overflow = 'hidden';
            } else {
                sidebar.classList.add('translate-x-full');
                overlay.classList.add('opacity-0', 'invisible');
                document.body.style.overflow = '';
            }
        };

        window.toggleSidebarDropdown = function(id) {
            const el = document.getElementById(id);
            const icon = document.getElementById(id + '-icon');
            if (el.classList.contains('hidden')) {
                el.classList.remove('hidden');
                el.classList.add('flex');
                if(icon) icon.classList.add('rotate-180');
            } else {
                el.classList.add('hidden');
                el.classList.remove('flex');
                if(icon) icon.classList.remove('rotate-180');
            }
        };

        document.querySelectorAll('.sidebar-link').forEach(link => {
            link.addEventListener('click', () => { toggleSidebar(); });
        });

        // دالة تبديل لغة الـ DMCA
        window.toggleDMCALanguage = function() {
            const ar = document.getElementById('dmca-ar');
            const en = document.getElementById('dmca-en');
            const btn = document.getElementById('dmca-lang-btn');
            if(ar.classList.contains('hidden')) {
                // تفعيل العربية
                ar.classList.remove('hidden');
                en.classList.add('hidden');
                btn.innerHTML = 'Switch to English <i class="fas fa-language ml-1"></i>';
            } else {
                // تفعيل الإنجليزية
                ar.classList.add('hidden');
                en.classList.remove('hidden');
                btn.innerHTML = '<i class="fas fa-language mr-1"></i> التبديل للعربية';
            }
        };

        // --- دالة إضافة العمل إلى سجل المشاهدة محلياً مع رقم الحلقة ---
        window.addToLocalHistory = function(id_prefix, id, title, poster, type, year, rating, quality, last_episode) {
            try {
                let history = JSON.parse(localStorage.getItem('arabfleex_history') || '[]');
                history = history.filter(h => h.id_prefix !== id_prefix); // إزالة النسخة القديمة إذا وجدت
                history.unshift({
                    id_prefix: id_prefix,
                    id: id,
                    title: title,
                    poster: poster,
                    type: type,
                    year: year,
                    rating: rating,
                    quality: quality,
                    last_episode: last_episode || null, // حفظ رقم أو اسم الحلقة
                    timestamp: Date.now()
                });
                if (history.length > 50) history.pop(); // الاحتفاظ بآخر 50 عنصر فقط
                localStorage.setItem('arabfleex_history', JSON.stringify(history));
            } catch(e) { console.error("Could not save to history", e); }
        };

        function logVisitorDemographics() {
            if (!sessionStorage.getItem('demo_logged')) {
                const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
                const deviceType = isMobile ? 'Mobile' : 'Desktop';
                fetch('https://ipapi.co/json/').then(res => res.json()).then(data => {
                    const fd = new FormData(); fd.append('country', data.country_name || 'غير معروف'); fd.append('device', deviceType);
                    fetch('/index.php?ajax_action=log_demographics', { method: 'POST', body: fd }); sessionStorage.setItem('demo_logged', 'true');
                }).catch(() => {
                    const fd = new FormData(); fd.append('device', deviceType); fetch('/index.php?ajax_action=log_demographics', { method: 'POST', body: fd }); sessionStorage.setItem('demo_logged', 'true');
                });
            }
        }

        function closePrayerBanner() { const b = document.getElementById('prayer-banner'); if (b) { b.style.opacity = '0'; b.style.transform = 'translateY(-100%)'; setTimeout(() => b.style.display = 'none', 300); localStorage.setItem('prayerBannerClosed', 'true'); } }
        
        function updateSEOMetaTags(title, desc, urlPath, image = null) {
            document.title = title;
            const currentOrigin = window.location.origin; // لجلب الدومين النشط أوتوماتيكياً
            
            if(document.getElementById('meta-title')) document.getElementById('meta-title').innerText = title;
            if(document.getElementById('meta-desc')) document.getElementById('meta-desc').setAttribute('content', desc);
            if(document.getElementById('meta-canonical')) document.getElementById('meta-canonical').setAttribute('href', currentOrigin + urlPath);
            if(document.getElementById('og-title')) document.getElementById('og-title').setAttribute('content', title);
            if(document.getElementById('og-desc')) document.getElementById('og-desc').setAttribute('content', desc);
            if(document.getElementById('og-url')) document.getElementById('og-url').setAttribute('content', currentOrigin + urlPath);
            if(image && document.getElementById('og-image')) document.getElementById('og-image').setAttribute('content', image);
        }

        function recordEpInteraction(episodeId) { if (!episodeId) return; const fd = new FormData(); fd.append('episode_id', episodeId); fetch(`/index.php?ajax_action=reg_view&cb=${Date.now()}`, { method: 'POST', body: fd }).catch(e => console.error(e)); }
        function recordMovieInteraction(movieId) { if (!movieId) return; const fd = new FormData(); fd.append('movie_id', movieId); fetch(`/index.php?ajax_action=reg_movie_view&cb=${Date.now()}`, { method: 'POST', body: fd }).catch(e => console.error(e)); }
        function cleanUrl(url) { if (!url) return ''; let str = url.trim(); if (str.toLowerCase().includes('<iframe') || str.toLowerCase().includes('<div')) { const match = str.match(/src=["']([^"']+)["']/i); if (match && match[1]) return match[1]; } if (!str.startsWith('http') && !str.startsWith('//') && str.includes('.')) { str = 'https://' + str; } return str; }
        function escapeHTML(str) { if (!str) return ''; return str.replace(/[&<>"']/g, function(m) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]; }); }
        
        function openTrailer(url) { const m = document.getElementById('trailer-modal'), i = document.getElementById('trailer-iframe'); if (!m || !i) return; let embedUrl = cleanUrl(url); const ytMatch = embedUrl.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i); if (ytMatch && ytMatch[1]) { embedUrl = `https://www.youtube.com/embed/${ytMatch[1]}`; } i.src = embedUrl; m.classList.remove('invisible', 'opacity-0'); document.body.style.overflow = 'hidden'; }
        function closeTrailerModal() { const m = document.getElementById('trailer-modal'), i = document.getElementById('trailer-iframe'); if (i) i.src = ''; if (m) m.classList.add('invisible', 'opacity-0'); document.body.style.overflow = ''; }
        function slugify(text) { if (!text) return 'item'; return text.toString().trim().replace(/\s+/g, '-').replace(/[/\\?%*:|"<>.]/g, '').replace(/--+/g, '-'); }

        function isSearchMatch(title, term) {
            if (!title || !term) return false;
            const tLower = title.toLowerCase(), sLower = term.toLowerCase();
            if (tLower.includes(sLower)) return true;
            const prefixes = /^(مسلسل|فيلم|فلم|برنامج|عرض|انمي|أنمي|موسم)\s+/gi;
            const cTitle = tLower.replace(prefixes, '').trim(), cTerm = sLower.replace(prefixes, '').trim();
            if (!cTitle || !cTerm) return false;
            if (cTitle.includes(cTerm) || cTerm.includes(cTitle)) return true;
            const searchWords = cTerm.split(/\s+/);
            if (searchWords.length > 0) { return searchWords.every(word => cTitle.includes(word)); }
            return false;
        }

        function transitionToPage(newPageElement) {
            if (!newPageElement) return;
            document.querySelectorAll('.page').forEach(p => { if(p !== newPageElement) p.classList.remove('page-active'); });
            const showNewPage = () => {
                window.scrollTo({ top: 0, behavior: 'instant' });
                document.getElementById('main-content-wrapper').style.minHeight = '';
                void newPageElement.offsetWidth;
                requestAnimationFrame(() => {
                    newPageElement.classList.add('page-active'); activePageElement = newPageElement;
                    try { if (newPageElement === homePage) { if (heroSwiper) heroSwiper.update(); if (recentSwiper) recentSwiper.update(); if (latestEpisodesSwiper) latestEpisodesSwiper.update(); } } catch(e){}
                });
            };
            if (activePageElement && activePageElement !== newPageElement) {
                document.getElementById('main-content-wrapper').style.minHeight = document.getElementById('main-content-wrapper').offsetHeight + 'px';
                activePageElement.classList.remove('page-active'); setTimeout(showNewPage, 50); 
            } else { showNewPage(); }
        }

        function openMobileRamadanModal(e) { if(e) e.preventDefault(); const m = document.getElementById('mobile-ramadan-modal'), b = document.getElementById('mobile-ramadan-trigger'); if(m) { m.classList.remove('invisible', 'opacity-0'); m.querySelector('.ramadan-modal-content').classList.remove('scale-90', 'opacity-0'); document.body.style.overflow = 'hidden'; if(b) b.classList.add('bottom-nav-active'); } }
        function closeMobileRamadanModal() { const m = document.getElementById('mobile-ramadan-modal'), b = document.getElementById('mobile-ramadan-trigger'); if(m) { m.classList.add('invisible', 'opacity-0'); m.querySelector('.ramadan-modal-content').classList.add('scale-90', 'opacity-0'); document.body.style.overflow = ''; if(b && !window.location.pathname.includes('/ramadan/')) { b.classList.remove('bottom-nav-active'); } } }

        document.addEventListener('click', (e) => {
            const mModal = document.getElementById('mobile-ramadan-modal'); if(mModal && e.target === mModal) closeMobileRamadanModal();
        });
        
        function createCard(item, type, delay) {
            const actualType = type || item.type || 'series';
            let routingType = 'series';
            if (actualType === 'movies') routingType = 'movies';
            else if (item.category === 'wrestling' || actualType === 'wrestling') routingType = 'wrestling';
            else if (item.category === 'tv_show' || actualType === 'tvshows') routingType = 'tvshows';
            
            const id_prefix = (actualType === 'movies') ? 'm' : 's';
            const full_id = item.id_prefix || (id_prefix + item.id), slug = slugify(item.title);
            const ramadanNotice = (actualType === 'series' && item.continue_after_ramadan == 1) ? `<div class="absolute bottom-2 right-2 bg-gradient-to-r from-red-600 to-red-800 text-white text-[8px] sm:text-[10px] font-black px-2 py-0.5 rounded shadow-lg z-10 animate-pulse">يستكمل بعد رمضان</div>` : '';
            const lastWatchedBadge = item.last_episode ? `<div class="absolute bottom-[4.5rem] left-2 right-2 text-center z-20"><span class="inline-block bg-gradient-to-r from-[var(--brand-gold)] to-yellow-500 text-black text-[10px] sm:text-xs font-black px-2 py-0.5 rounded-md shadow-[0_5px_10px_rgba(0,0,0,0.8)] border border-black/20 truncate max-w-full">${item.last_episode}</span></div>` : '';
            
            return `<a href="/details/${routingType}/${full_id}/${slug}" class="card-container group relative block" style="animation-delay: ${delay}ms">
                <div class="relative rounded-xl overflow-hidden aspect-[2/3] bg-gray-900 shadow-lg transform transition-all duration-500 group-hover:-translate-y-2 group-hover:shadow-2xl group-hover:shadow-[var(--brand-gold)]/20">
                    <img src="${item.poster}" alt="${item.title}" loading="lazy" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                    <div class="absolute inset-0 bg-gradient-to-t from-[#050505] via-black/20 to-transparent opacity-80 group-hover:opacity-90 transition-opacity duration-300"></div>
                    <i class="card-play-icon fa-solid fa-circle-play"></i>
                    ${item.quality ? `<div class="absolute top-2 right-2 px-2 py-0.5 bg-black/60 backdrop-blur-md border border-white/10 text-[var(--brand-gold)] text-[9px] sm:text-[10px] font-black rounded-full shadow-lg">${item.quality}</div>` : ''}
                    <div class="absolute top-2 left-2 bg-black/60 backdrop-blur-md border border-white/10 text-white text-[9px] sm:text-[10px] font-bold px-2 py-0.5 rounded-full flex items-center gap-1 shadow-lg"><i class="fas fa-star text-[var(--brand-gold)] text-[10px]"></i> ${item.rating || 'N/A'}</div>
                    ${ramadanNotice}
                    ${lastWatchedBadge}
                    <div class="absolute bottom-0 left-0 right-0 p-3 transform translate-y-2 group-hover:translate-y-0 transition-transform duration-300">
                        <h3 class="font-black text-[12px] sm:text-sm text-white truncate text-shadow-md group-hover:text-[var(--brand-gold)] transition-colors" title="${item.title}">${item.title}</h3>
                        <p class="text-[10px] sm:text-xs text-gray-400 font-bold opacity-0 group-hover:opacity-100 transition-opacity duration-300 mt-1">${item.year || ''}</p>
                    </div></div></a>`;
        }

        function createTopCard(item, type, delay, rank) {
            const actualType = type || item.type || 'series';
            let routingType = 'series';
            if (actualType === 'movies') routingType = 'movies';
            else if (item.category === 'wrestling' || actualType === 'wrestling') routingType = 'wrestling';
            else if (item.category === 'tv_show' || actualType === 'tvshows') routingType = 'tvshows';

            const id_prefix = (actualType === 'movies') ? 'm' : 's', full_id = item.id_prefix || (id_prefix + item.id), slug = slugify(item.title);
            let rankBadge = ''; if(rank === 1) rankBadge = 'from-yellow-400 to-yellow-600 text-black shadow-[0_0_20px_rgba(234,179,8,0.5)]'; if(rank === 2) rankBadge = 'from-gray-300 to-gray-500 text-black shadow-[0_0_20px_rgba(209,213,219,0.4)]'; if(rank === 3) rankBadge = 'from-amber-700 to-amber-900 text-white shadow-[0_0_20px_rgba(180,83,9,0.4)]';
            return `<a href="/details/${routingType}/${full_id}/${slug}" class="card-container group relative block" style="animation-delay: ${delay}ms">
                <div class="relative rounded-2xl overflow-hidden aspect-[2/3] bg-gray-900 border border-white/10 shadow-lg transform transition-all duration-500 group-hover:scale-105 group-hover:border-[var(--brand-gold)] group-hover:shadow-[0_0_30px_rgba(218,165,32,0.3)]">
                    <img src="${item.poster}" alt="${item.title}" loading="lazy" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                    <div class="absolute inset-0 bg-gradient-to-t from-[#050505] via-black/30 to-transparent opacity-90 group-hover:opacity-100 transition-opacity"></div>
                    <i class="card-play-icon fa-solid fa-circle-play text-[var(--brand-gold)] !text-shadow-none"></i>
                    <div class="absolute top-0 right-0 bg-gradient-to-b ${rankBadge} px-3 py-2 rounded-bl-2xl z-20 flex flex-col items-center justify-center font-black">
                        <span class="text-[8px] sm:text-[10px] leading-none mb-0.5 opacity-80">TOP</span><span class="text-xl sm:text-2xl leading-none drop-shadow-md">${rank}</span>
                    </div>
                    <div class="absolute bottom-0 left-0 right-0 p-3 text-center"><h3 class="font-black text-sm sm:text-base text-white truncate text-shadow-md group-hover:text-[var(--brand-gold)] transition-colors" title="${item.title}">${item.title}</h3></div>
                </div></a>`;
        }

        function createEpisodeCard(item, delay) {
            const full_id = 's' + item.series_id, slug = slugify(item.series_title);
            let guestBadge = '', ribbonBadge = ''; 
            if (item.ep_title && item.ep_title !== `الحلقة ${item.episode_number}`) {
                if (item.ep_title.includes('الأخيرة') || item.ep_title.includes('اخيرة')) { ribbonBadge = `<div class="absolute top-3 -left-8 w-28 bg-gradient-to-r from-red-600 to-red-800 text-white text-center font-black py-1 text-[9px] transform -rotate-45 shadow-lg z-40">الأخيرة</div>`; } 
                else {
                    let iconHtml = ''; if (item.ep_title.includes('ضيف') || item.ep_title.includes('برنامج') || item.ep_title.includes('لقاء') || item.ep_title.includes('عرض')) { iconHtml = '<i class="fas fa-microphone-alt text-[8px] ml-1"></i>'; }
                    guestBadge = `<div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-[#050505] via-black/80 to-transparent pt-10 pb-2 px-2 z-20 text-center flex items-end justify-center"><span class="inline-block bg-black/60 backdrop-blur-md border border-white/10 text-[var(--brand-gold)] text-[9px] sm:text-[11px] font-black px-2 py-1 rounded-lg w-full truncate shadow-lg" title="${item.ep_title}">${iconHtml}${item.ep_title}</span></div>`;
                }
            }
            return `<a href="/details/series/${full_id}/${slug}" class="card-container group relative block" style="animation-delay: ${delay}ms">
                <div class="relative rounded-xl overflow-hidden aspect-[2/3] bg-gray-900 shadow-lg transform transition-all duration-500 group-hover:-translate-y-2 group-hover:shadow-[0_15px_30px_rgba(218,165,32,0.2)]">
                    <img src="${item.poster}" alt="${item.series_title}" loading="lazy" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                    <div class="absolute inset-0 bg-gradient-to-t from-[#050505] via-black/20 to-transparent opacity-80 group-hover:opacity-90 transition-opacity z-10"></div>
                    <i class="card-play-icon fa-solid fa-circle-play text-white z-30 drop-shadow-[0_0_15px_rgba(0,0,0,0.8)]"></i>
                    <div class="absolute top-2 right-2 px-2 py-1 bg-gradient-to-r from-[var(--brand-gold)] to-yellow-500 text-black text-[10px] sm:text-xs font-black rounded-lg shadow-lg z-20">حلقة ${item.episode_number}</div>
                    ${ribbonBadge}${guestBadge}
                    <div class="absolute bottom-0 left-0 right-0 p-3 z-10 text-center ${guestBadge ? 'hidden' : ''}"><h3 class="font-black text-[12px] sm:text-sm text-white truncate text-shadow-md group-hover:text-[var(--brand-gold)] transition-colors" title="${item.series_title}">${item.series_title}</h3></div>
                </div></a>`;
        }

        async function _renderDetailsPage(id, type) {
            const actualType = (type === 'wrestling' || type === 'tvshows') ? 'series' : type;
            const basicInfo = (actualType === 'movies' ? db.movies : db.series).find(item => item.id_prefix === id);
            if (!basicInfo) { history.replaceState(null, null, '/'); router(); return; }
            const slug = slugify(basicInfo.title); const newPath = `/details/${type}/${id}/${slug}`;
            if (window.location.pathname.split('/').slice(0, 5).join('/') !== newPath) { history.replaceState(null, null, newPath); }
            
            const safeTitle = (basicInfo.title || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');

            detailsPage.innerHTML = `
            <div class="min-h-screen relative">
                <div class="absolute inset-0 h-[80vh] w-full z-0 pointer-events-none"><img src="${basicInfo.poster}" alt="" class="w-full h-full object-cover opacity-30 filter blur-md transform scale-105"><div class="absolute inset-0 bg-gradient-to-t from-[var(--background-dark)] via-[var(--background-dark)]/90 to-transparent opacity-100"></div></div>
                <div class="container mx-auto px-4 sm:px-6 lg:px-8 pt-6 pb-20 relative z-10">
                    <button onclick="history.back()" class="mb-10 group flex items-center gap-3 text-gray-400 hover:text-white transition-colors w-fit"><div class="w-10 h-10 rounded-full glass-panel flex items-center justify-center group-hover:bg-white/10 transition-all"><i class="fas fa-arrow-right"></i></div><span class="font-bold">رجوع</span></button>
                    <div class="flex flex-col md:flex-row gap-6 md:gap-10 lg:gap-16 items-start w-full animate-pulse">
                        <div class="w-2/3 sm:w-1/2 md:w-1/3 lg:w-1/4 xl:w-1/5 max-w-[320px] flex-shrink-0 group relative mx-auto md:mx-0"><div class="rounded-2xl bg-white/5 aspect-[2/3] w-full border border-white/10 shadow-[0_30px_60px_rgba(0,0,0,0.5)]"></div></div>
                        <div class="flex-1 min-w-0 pt-2 md:pt-8 text-center md:text-right w-full"><div class="h-10 sm:h-14 bg-white/5 rounded-2xl w-3/4 mb-6 mx-auto md:mx-0"></div><div class="flex flex-wrap items-center justify-center md:justify-start gap-4 mb-8"><div class="h-6 bg-white/5 rounded-lg w-16"></div><div class="h-6 bg-white/5 rounded-lg w-16"></div><div class="h-6 bg-white/5 rounded-lg w-20"></div></div><div class="space-y-4 max-w-3xl mx-auto md:mx-0 mb-8"><div class="h-4 bg-white/5 rounded-lg w-full"></div><div class="h-4 bg-white/5 rounded-lg w-5/6 mx-auto md:mx-0"></div><div class="h-4 bg-white/5 rounded-lg w-4/6 mx-auto md:mx-0"></div></div><div class="flex flex-wrap gap-4 items-center mt-10 justify-center md:justify-start"><div class="h-14 bg-white/5 rounded-xl w-48"></div><div class="h-14 bg-white/5 rounded-xl w-40"></div></div></div>
                    </div>
                </div>
            </div>`;
            transitionToPage(detailsPage);

            const realId = id.substring(1);
            try {
                const response = await fetch(`/index.php?ajax_action=get_details&type=${actualType}&id=${realId}`); const result = await response.json();
                if(!result.success) throw new Error("بيانات غير متوفرة");
                const content = result.data; 
                let typeName = actualType === 'movies' ? 'فيلم' : (content.category === 'tv_show' ? 'برنامج' : (content.category === 'wrestling' ? 'عرض' : 'مسلسل'));
                const seoTitle = `مشاهدة ${typeName} ${content.title} - عرب فليكس`;
                const seoDesc = `مشاهدة وتحميل ${content.title} بجودة عالية. ` + (content.description ? content.description.substring(0, 100) : '');
                updateSEOMetaTags(seoTitle, seoDesc, newPath, content.poster);

                let seasonsHTML = '';
                if (actualType === 'series') {
                    let currentTitle = content.title.replace(/^(مسلسل|برنامج|عرض|انمي|أنمي)\s+/i, '').trim(); let baseWord = currentTitle.split(' ')[0];
                    if (baseWord.length >= 3) {
                        const relatedSeasons = db.series.filter(s => { let sTitle = s.title.replace(/^(مسلسل|برنامج|عرض|انمي|أنمي)\s+/i, '').trim(); return sTitle.startsWith(baseWord); });
                        if (relatedSeasons.length > 1) {
                            
                            relatedSeasons.sort((a, b) => {
                                const getSeasonNumber = (title) => {
                                    const t = title.toLowerCase();
                                    const match = t.match(/(?:موسم|الموسم|season|part|جزء|s)[\s_:-]*(\d+)/);
                                    if (match) return parseInt(match[1], 10);
                                    const endNum = t.match(/\s(\d+)$/);
                                    if (endNum) return parseInt(endNum[1], 10);
                                    return 0; 
                                };

                                const numA = getSeasonNumber(a.title);
                                const numB = getSeasonNumber(b.title);

                                if (numA !== numB) {
                                    return numA - numB;
                                }
                                return a.title.localeCompare(b.title, ['en', 'ar'], { numeric: true, sensitivity: 'base' });
                            });

                            let buttons = relatedSeasons.map(season => {
                                const s_full_id = 's' + season.id, s_slug = slugify(season.title);
                                const isActive = (season.id == content.id) ? 'bg-[var(--brand-gold)] text-black shadow-[0_0_15px_rgba(218,165,32,0.4)] pointer-events-none' : 'glass-panel text-white hover:bg-white/10 hover:text-[var(--brand-gold)]';
                                let btnText = season.title.replace(/^(مسلسل|برنامج|عرض|انمي|أنمي)\s+/i, '');
                                return `<a href="/details/series/${s_full_id}/${s_slug}" class="px-5 py-2.5 rounded-xl font-bold text-sm transition-all duration-300 ${isActive}">${btnText}</a>`;
                            }).join('');
                            seasonsHTML = `<div class="mb-8"><h3 class="text-lg font-bold text-gray-400 mb-3 flex items-center gap-2">مواسم أخرى</h3><div class="flex flex-wrap gap-2">${buttons}</div></div>`;
                        }
                    }
                }

                let castHTML = '';
                if (content.cast_data && content.cast_data !== 'null' && content.cast_data !== '') {
                    try {
                        const castData = JSON.parse(content.cast_data);
                        if (castData.length > 0) {
                            const actorsHTML = castData.map(actor => `
                                <div class="flex-shrink-0 w-24 flex flex-col items-center text-center group cursor-pointer">
                                    <div class="w-20 h-20 rounded-full overflow-hidden border border-white/10 group-hover:border-[var(--brand-gold)] group-hover:shadow-[0_0_15px_rgba(218,165,32,0.3)] transition-all duration-300 mb-2"><img src="${actor.image}" alt="${actor.name}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500 bg-gray-800"></div>
                                    <span class="text-[13px] font-bold text-gray-200 group-hover:text-white transition-colors line-clamp-1 w-full">${actor.name}</span><span class="text-[11px] text-gray-500 line-clamp-1 w-full mt-0.5">${actor.character}</span>
                                </div>`).join('');
                            castHTML = `<div class="mt-10 mb-8 w-full"><h3 class="text-xl font-black text-white mb-4 flex items-center gap-2">طاقم العمل</h3><div class="flex gap-4 overflow-x-auto pb-4 cast-scrollbar px-1 w-full">${actorsHTML}</div></div>`;
                        }
                    } catch (e) {}
                }

                let watchSectionHTML = '';
                const trailerBtn = (content.trailer_link && content.trailer_link !== '#') ? `<button onclick="openTrailer('${content.trailer_link}')" class="flex items-center justify-center gap-2 glass-panel hover:bg-white/10 text-white font-bold py-3 sm:py-4 px-6 rounded-xl transition-all duration-300 hover:-translate-y-1 w-full sm:w-auto"><i class="fas fa-play text-xs text-[var(--brand-gold)]"></i> الإعلان الترويجي</button>` : '';

                if (actualType === 'series' && content.episodes && content.episodes.length > 0) {
                    const reversedEpisodes = [...content.episodes].reverse();
                    watchSectionHTML = `
                        <div class="flex flex-col sm:flex-row flex-wrap gap-4 items-center justify-center md:justify-start mb-10">${trailerBtn}</div>
                        <div class="glass-panel rounded-3xl p-5 sm:p-8 mb-8 relative overflow-hidden">
                            <div class="absolute -top-20 -right-20 w-40 h-40 bg-[var(--brand-gold)] opacity-10 blur-[80px] rounded-full pointer-events-none"></div>
                            <div class="flex items-center justify-between mb-6 border-b border-white/5 pb-4"><h3 class="text-2xl font-black text-white flex items-center gap-3">الحلقات</h3><span class="bg-white/10 px-3 py-1 rounded-lg text-sm font-bold text-gray-300">${content.episodes.length} حلقة</span></div>
                            <div id="episodes-list" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 max-h-[400px] overflow-y-auto pr-2">
                                ${reversedEpisodes.map((ep, index) => {
                                    const currentLink = `play.php?ep_id=${ep.id}`; const isLatest = index === 0;
                                    const activeClass = isLatest ? 'bg-gradient-to-tr from-[var(--brand-gold)] to-yellow-500 text-black border-transparent shadow-[0_5px_15px_rgba(218,165,32,0.3)] hover:-translate-y-1 relative overflow-hidden' : 'bg-black/40 border-white/5 text-gray-300 hover:bg-white/10 hover:border-white/20 hover:text-white'; 
                                    const latestBadge = isLatest ? `<div class="absolute top-0 right-0 bg-red-600 text-white text-[9px] font-black px-2 py-0.5 rounded-bl-lg z-10 w-full text-center">أحدث إضافة</div>` : '';
                                    let displayHTML = '';
                                    
                                    const epNumText = `الحلقة ${ep.episode_number}`;
                                    const epLabelSafe = (ep.title && ep.title !== epNumText) ? epNumText + ' - ' + ep.title.replace(/'/g, "\\'") : epNumText;
                                    const historyCall = `addToLocalHistory('${basicInfo.id_prefix}', '${basicInfo.id}', '${safeTitle}', '${basicInfo.poster}', '${type}', '${basicInfo.year}', '${basicInfo.rating}', '${basicInfo.quality}', '${epLabelSafe}')`;

                                    if (ep.title && ep.title !== epNumText) { displayHTML = `${latestBadge}<div class="flex flex-col items-center justify-center h-full w-full ${isLatest ? 'pt-4' : 'pt-1'}"><span class="text-[10px] ${isLatest ? 'text-black/60' : 'opacity-60'} mb-1 font-bold">الحلقة ${ep.episode_number}</span><span class="text-[10px] sm:text-[11px] font-black text-center w-full px-1 truncate" title="${ep.title}">${ep.title}</span></div>`; } 
                                    else { displayHTML = `${latestBadge}<div class="flex items-center justify-center h-full w-full ${isLatest ? 'pt-2' : ''}"><span class="text-base font-black text-center">الحلقة ${ep.episode_number}</span></div>`; }
                                    
                                    return `<a href="${currentLink}" target="_blank" onclick="recordEpInteraction(${ep.id}); ${historyCall}" class="flex flex-col items-center justify-center min-h-[80px] px-2 border rounded-xl transition-all duration-300 group ${activeClass}">${!isLatest ? `<i class="fa-solid fa-play mb-2 text-sm opacity-50 group-hover:opacity-100 group-hover:text-[var(--brand-gold)] transition-all"></i>` : `<i class="fa-solid fa-play absolute -left-2 -bottom-3 text-5xl text-black/10 transform -rotate-12"></i>`}${displayHTML}</a>`;
                                }).join('')}
                            </div>
                        </div>`;
                } else if (actualType === 'series') { watchSectionHTML = `<div class="flex flex-col sm:flex-row flex-wrap gap-4 items-center justify-center md:justify-start mt-8">${trailerBtn ? trailerBtn : '<p class="text-gray-500 bg-white/5 p-4 rounded-xl border border-white/5 inline-block font-bold">لا توجد حلقات متوفرة حالياً.</p>'}</div>`; } 
                else if (actualType === 'movies') {
                    const hasValidLink = content.watch_link && content.watch_link !== '#';
                    if (hasValidLink) { 
                        const link = `play.php?movie_id=${content.id}`; 
                        const movieHistoryCall = `addToLocalHistory('${basicInfo.id_prefix}', '${basicInfo.id}', '${safeTitle}', '${basicInfo.poster}', '${type}', '${basicInfo.year}', '${basicInfo.rating}', '${basicInfo.quality}', null)`;
                        watchSectionHTML = `<div class="flex flex-col sm:flex-row flex-wrap gap-4 items-center justify-center md:justify-start mt-8"><a href="${link}" target="_blank" onclick="recordMovieInteraction(${content.id}); ${movieHistoryCall}" class="flex items-center justify-center gap-3 bg-[var(--brand-red)] text-white font-black py-3 sm:py-4 px-6 sm:px-10 rounded-xl transition-all duration-300 hover:bg-red-700 hover:-translate-y-1 shadow-[0_10px_20px_rgba(229,9,20,0.3)] relative overflow-hidden group w-full sm:w-auto"><i class="fa-solid fa-play text-xl"></i> <span class="text-base sm:text-lg">مشاهدة وتحميل الفيلم</span></a>${trailerBtn}</div>`; 
                    } else { 
                        watchSectionHTML = `<div class="flex flex-col sm:flex-row flex-wrap gap-4 items-center justify-center md:justify-start mt-8"><p class="text-gray-400 font-bold glass-panel p-4 rounded-xl w-full sm:w-auto text-center">روابط المشاهدة غير متوفرة حالياً.</p>${trailerBtn}</div>`; 
                    }
                }

                const currentUrl = window.location.href;
                const shareSection = `
                    <div class="flex items-center gap-3 mt-10">
                        <span class="text-sm font-bold text-gray-500 mr-2">مشاركة:</span>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(currentUrl)}" target="_blank" class="w-10 h-10 rounded-full glass-panel flex items-center justify-center hover:bg-[#1877F2] hover:border-transparent hover:text-white transition-all hover:-translate-y-1 text-gray-400"><i class="fab fa-facebook-f"></i></a>
                        <a href="https://twitter.com/intent/tweet?url=${encodeURIComponent(currentUrl)}&text=${encodeURIComponent('شاهد ' + content.title + ' على عرب فليكس')}" target="_blank" class="w-10 h-10 rounded-full glass-panel flex items-center justify-center hover:bg-[#1DA1F2] hover:border-transparent hover:text-white transition-all hover:-translate-y-1 text-gray-400"><i class="fab fa-twitter"></i></a>
                        <a href="https://api.whatsapp.com/send?text=${encodeURIComponent('شاهد ' + content.title + ' بجودة عالية على عرب فليكس: ' + currentUrl)}" target="_blank" class="w-10 h-10 rounded-full glass-panel flex items-center justify-center hover:bg-[#25D366] hover:border-transparent hover:text-white transition-all hover:-translate-y-1 text-gray-400"><i class="fab fa-whatsapp text-lg"></i></a>
                        <button onclick="navigator.clipboard.writeText('${currentUrl}'); this.innerHTML='<i class=\\\'fas fa-check text-green-400\\\'></i>'; setTimeout(()=>this.innerHTML='<i class=\\\'fas fa-link\\\'></i>', 2000);" class="w-10 h-10 rounded-full glass-panel flex items-center justify-center hover:bg-white/20 hover:text-white transition-all hover:-translate-y-1 text-gray-400"><i class="fas fa-link"></i></button>
                    </div>`;

                let relatedHTML = '';
                if (actualType === 'movies') {
                    let sourceList = db.movies;
                    if (content.category === 'foreign_movie') { sourceList = db.foreign_movies || db.movies; } 
                    else if (content.category === 'indian_movie') { sourceList = db.indian_movies || db.movies; } 
                    else { sourceList = db.arabic_movies || db.movies; }
                    const otherMovies = sourceList.filter(m => m.id !== content.id); 
                    const randomRelated = otherMovies.sort(() => 0.5 - Math.random()).slice(0, 6);
                    if (randomRelated.length > 0) { 
                        relatedHTML = `<div class="mt-20 relative z-10"><h3 class="text-2xl font-black text-white mb-6">أعمال مشابهة</h3><div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-6 gap-3 sm:gap-5">${randomRelated.map((item, i) => createCard(item, 'movies', i * 40)).join('')}</div></div>`; 
                    }
                } else if (actualType === 'series') {
                    let sourceList = db.series;
                    if (content.category === 'turkish') sourceList = db.turkish_series || db.series;
                    else if (content.category === 'foreign') sourceList = db.foreign_series || db.series;
                    else if (content.category === 'indian') sourceList = db.indian_series || db.series;
                    else if (content.category === 'tv_show') sourceList = db.tv_shows || db.series;
                    else if (content.category === 'wrestling') sourceList = db.wrestling || db.series;
                    else sourceList = db.arabic_series || db.series;
                    
                    let currentTitle = content.title.replace(/^(مسلسل|برنامج|عرض|انمي|أنمي)\s+/i, '').trim(); 
                    let baseWord = currentTitle.split(' ')[0];
                    const otherItems = sourceList.filter(s => { let sTitle = s.title.replace(/^(مسلسل|برنامج|عرض|انمي|أنمي)\s+/i, '').trim(); return s.id !== content.id && (!baseWord || !sTitle.startsWith(baseWord)); });
                    
                    const randomRelated = otherItems.sort(() => 0.5 - Math.random()).slice(0, 6);
                    if (randomRelated.length > 0) { 
                        relatedHTML = `<div class="mt-20 relative z-10"><h3 class="text-2xl font-black text-white mb-6">أعمال مشابهة</h3><div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-6 gap-3 sm:gap-5">${randomRelated.map((item, i) => createCard(item, 'series', i * 40)).join('')}</div></div>`; 
                    }
                }

                detailsPage.innerHTML = `
                <div class="min-h-screen relative transition-opacity duration-300">
                    <div class="absolute inset-0 h-[80vh] w-full z-0 pointer-events-none"><img src="${content.poster}" alt="" class="w-full h-full object-cover opacity-30 filter blur-md transform scale-105"><div class="absolute inset-0 bg-gradient-to-t from-[var(--background-dark)] via-[var(--background-dark)]/90 to-transparent opacity-100"></div></div>
                    <div class="container mx-auto px-4 sm:px-6 lg:px-8 pt-6 pb-20 relative z-10">
                        <button onclick="history.back()" class="mb-10 group flex items-center gap-3 text-gray-400 hover:text-white transition-colors w-fit"><div class="w-10 h-10 rounded-full glass-panel flex items-center justify-center group-hover:bg-white/10 transition-all"><i class="fas fa-arrow-right"></i></div><span class="font-bold">رجوع</span></button>
                        <div class="flex flex-col md:flex-row gap-6 md:gap-10 lg:gap-16 items-start w-full">
                            <div class="w-3/4 sm:w-1/2 md:w-1/3 lg:w-1/4 xl:w-1/5 max-w-[320px] flex-shrink-0 group relative mx-auto md:mx-0">
                                <div class="rounded-2xl overflow-hidden shadow-[0_30px_60px_rgba(0,0,0,0.8)] border border-white/10 relative z-10"><img src="${content.poster}" alt="${content.title}" class="w-full object-cover aspect-[2/3]">${content.quality ? `<div class="absolute top-3 right-3 px-3 py-1 bg-black/60 backdrop-blur-md border border-white/10 text-[var(--brand-gold)] text-xs font-black rounded-full">${content.quality}</div>` : ''}</div>
                                <div class="absolute inset-0 bg-[var(--brand-gold)] opacity-0 blur-[60px] group-hover:opacity-20 transition-opacity duration-700 z-0"></div>
                            </div>
                            <div class="flex-1 min-w-0 w-full pt-2 md:pt-6 lg:pt-8 text-center md:text-right">
                                <h1 class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-black mb-4 sm:mb-6 text-white text-shadow-lg leading-tight tracking-tight">${content.title}</h1>
                                <div class="flex flex-wrap items-center justify-center md:justify-start gap-3 sm:gap-4 mb-6 sm:mb-8">${content.rating ? `<div class="inline-flex items-center bg-gradient-to-r from-[var(--brand-gold)] to-yellow-600 rounded-xl px-4 py-1.5 shadow-[0_4px_15px_rgba(218,165,32,0.3)] border border-[var(--brand-gold)]/40 text-black gap-1.5 font-black text-sm sm:text-base" dir="ltr"><i class="fas fa-star text-xs sm:text-sm pb-0.5"></i><span>${content.rating}</span><span class="text-xs opacity-80 font-bold">/ 10</span></div><span class="hidden sm:inline-block w-1 h-1 rounded-full bg-gray-600"></span>` : ''}<span class="text-gray-300 font-bold text-sm bg-white/5 px-3 py-1 rounded-lg border border-white/5">${content.year}</span>${content.genre ? `<span class="hidden sm:inline-block w-1 h-1 rounded-full bg-gray-600"></span><span class="text-gray-300 font-bold text-sm bg-white/5 px-3 py-1 rounded-lg border border-white/5">${content.genre}</span>` : ''}</div>
                                <div class="mb-8 w-full">
                                    <h3 class="text-lg sm:text-xl font-black text-white mb-4 flex items-center justify-center md:justify-start gap-2">
                                        <i class="fas fa-align-right text-[var(--brand-gold)]"></i> القصة
                                    </h3>
                                    <div class="glass-panel p-4 sm:p-6 rounded-2xl border-r-4 border-r-[var(--brand-gold)] relative overflow-hidden group hover:border-r-[var(--brand-gold-light)] transition-colors">
                                        <div class="absolute -left-6 -top-6 text-[var(--brand-gold)] opacity-5 transform -rotate-12 group-hover:scale-110 transition-transform duration-500 pointer-events-none">
                                            <i class="fas fa-quote-left text-9xl"></i>
                                        </div>
                                        <p class="text-sm sm:text-base lg:text-lg text-gray-300 leading-relaxed font-semibold relative z-10 text-justify">
                                            ${content.description || 'لا يوجد وصف متاح حالياً لهذا العمل.'}
                                        </p>
                                    </div>
                                </div>
                                ${seasonsHTML}${watchSectionHTML}${castHTML}${shareSection}
                            </div>
                        </div>
                        ${relatedHTML}
                    </div>
                </div>`;
            } catch (error) { detailsPage.innerHTML = `<div class="container mx-auto px-4 py-20 text-center"><h2 class="text-2xl text-red-500 font-bold mb-4">حدث خطأ أثناء جلب التفاصيل</h2><button onclick="history.back()" class="bg-white/10 px-6 py-2 rounded-xl text-white">العودة للخلف</button></div>`; }
        }

        function _renderHistoryPage() {
            updateSEOMetaTags("سجل المشاهدة - عرب فليكس", "سجل مشاهداتك السابقة على عرب فليكس", "/history");
            let history = JSON.parse(localStorage.getItem('arabfleex_history') || '[]');
            
            let contentHTML = `<div class="container mx-auto px-4 sm:px-6 lg:px-8 py-12 min-h-screen" style="animation: fadeIn 0.7s ease-out;">
                <div class="flex flex-col sm:flex-row justify-between items-center mb-10 pb-4 border-b border-white/5 gap-4">
                    <h1 class="text-2xl sm:text-3xl font-black text-white flex items-center gap-3"><i class="fas fa-history text-blue-400"></i> سجل المشاهدة</h1>
                    ${history.length > 0 ? `<button onclick="clearHistory()" class="text-red-400 hover:text-white bg-red-500/10 hover:bg-red-500 transition-colors px-4 py-2 rounded-xl text-sm font-bold flex items-center gap-2"><i class="fas fa-trash-alt"></i> مسح السجل</button>` : ''}
                </div>`;

            if (history.length > 0) {
                contentHTML += `<div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-6 gap-3 sm:gap-5 relative z-20">
                    ${history.map((item, i) => createCard(item, item.type, i * 40)).join('')}
                </div>`;
            } else {
                contentHTML += `<div class="text-center text-gray-500 mt-20 relative z-20 glass-panel p-10 rounded-3xl max-w-md mx-auto shadow-2xl border border-white/5">
                    <div class="w-24 h-24 rounded-full bg-white/5 flex items-center justify-center mx-auto mb-6"><i class="fas fa-clock fa-3x opacity-50"></i></div>
                    <h2 class="text-xl font-black text-white mb-2">السجل فارغ!</h2>
                    <p class="text-base font-bold text-gray-400 mb-8">لم تقم بمشاهدة أي فيلم أو مسلسل بعد.</p>
                    <a href="/" class="inline-block bg-[var(--brand-gold)] text-black px-8 py-3 rounded-xl font-black shadow-[0_10px_20px_rgba(218,165,32,0.2)] hover:-translate-y-1 transition-all">تصفح المحتوى الآن</a>
                </div>`;
            }
            contentHTML += `</div>`;
            historyPage.innerHTML = contentHTML;
            transitionToPage(historyPage);
        }

        window.clearHistory = function() {
            if(confirm('هل أنت متأكد من رغبتك في مسح سجل المشاهدة بالكامل؟')) {
                localStorage.removeItem('arabfleex_history');
                _renderHistoryPage();
            }
        };

        function _renderRequestsPage() {
            updateSEOMetaTags("الطلبات - عرب فليكس", "أرسل طلبك لمسلسل أو فيلم، وتتبع حالة طلبك", "/requests");
            
            requestsPage.innerHTML = `
            <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-12" style="animation: fadeIn 0.7s ease-out;">
                <div class="max-w-2xl mx-auto">
                    <h1 class="text-3xl font-black text-white mb-8 text-center"><span class="golden-text">تواصل</span> معنا</h1>
                    <div class="glass-panel p-6 sm:p-10 rounded-3xl shadow-2xl relative overflow-hidden">
                        <div id="request-form-message" class="hidden mb-6 p-4 rounded-xl text-center font-bold text-sm leading-relaxed"></div>
                        
                        <div class="flex p-1 bg-black/40 rounded-xl mb-8 border border-white/5 relative z-10">
                            <button type="button" id="tab-request" class="flex-1 py-3 px-2 sm:px-4 font-bold rounded-lg transition-all text-xs sm:text-sm active bg-white/10 text-white shadow-md" data-tab="request">طلب عمل</button>
                            <button type="button" id="tab-feedback" class="flex-1 py-3 px-2 sm:px-4 font-bold rounded-lg transition-all text-xs sm:text-sm text-gray-400 hover:text-white" data-tab="feedback">شكوى/اقتراح</button>
                            <button type="button" id="tab-track" class="flex-1 py-3 px-2 sm:px-4 font-bold rounded-lg transition-all text-xs sm:text-sm text-gray-400 hover:text-[var(--brand-gold)]" data-tab="track"><i class="fas fa-search mr-1 hidden sm:inline"></i> تتبع طلبك</button>
                        </div>
                        
                        <form id="request-form" novalidate>
                            <div class="space-y-5">
                                <div id="request-fields" class="form-fields space-y-5">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                        <div><input type="text" name="name" id="req-name" placeholder="الاسم" class="w-full bg-black/40 border border-white/10 rounded-xl py-3.5 px-4 font-semibold text-white focus:outline-none focus:border-[var(--brand-gold)] focus:bg-black/60 transition-all placeholder-gray-500" required></div>
                                        <div><input type="email" name="email" id="req-email" placeholder="البريد الإلكتروني" class="w-full bg-black/40 border border-white/10 rounded-xl py-3.5 px-4 font-semibold text-white focus:outline-none focus:border-[var(--brand-gold)] focus:bg-black/60 transition-all placeholder-gray-500" required></div>
                                    </div>
                                    <div>
                                        <input type="text" name="work_name" id="work-name" placeholder="اسم الفيلم أو المسلسل المطلوب" class="w-full bg-black/40 border border-white/10 rounded-xl py-3.5 px-4 font-semibold text-white focus:outline-none focus:border-[var(--brand-gold)] focus:bg-black/60 transition-all placeholder-gray-500" required>
                                        <div id="smart-check-result" class="hidden mt-3"></div>
                                    </div>
                                    <div><input type="url" name="work_link" id="work-link" placeholder="رابط العمل (مثال: IMDB) - اختياري" class="w-full bg-black/40 border border-white/10 rounded-xl py-3.5 px-4 font-semibold text-white focus:outline-none focus:border-[var(--brand-gold)] focus:bg-black/60 transition-all placeholder-gray-500"></div>
                                </div>
                                
                                <div id="feedback-fields" class="form-fields space-y-5 hidden">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                        <div><input type="text" name="fb_name" id="fb-name" placeholder="الاسم" class="w-full bg-black/40 border border-white/10 rounded-xl py-3.5 px-4 font-semibold text-white focus:outline-none focus:border-[var(--brand-gold)] focus:bg-black/60 transition-all placeholder-gray-500"></div>
                                        <div><input type="email" name="fb_email" id="fb-email" placeholder="البريد الإلكتروني" class="w-full bg-black/40 border border-white/10 rounded-xl py-3.5 px-4 font-semibold text-white focus:outline-none focus:border-[var(--brand-gold)] focus:bg-black/60 transition-all placeholder-gray-500"></div>
                                    </div>
                                    <div><textarea id="description" name="description" rows="4" placeholder="اكتب تفاصيل الاقتراح أو الشكوى..." class="w-full bg-black/40 border border-white/10 rounded-xl py-3.5 px-4 font-semibold text-white focus:outline-none focus:border-[var(--brand-gold)] focus:bg-black/60 transition-all placeholder-gray-500 resize-none"></textarea></div>
                                </div>
                            </div>
                            <div class="mt-8" id="submit-btn-container">
                                <button type="submit" id="submit-btn" class="w-full bg-white text-black font-black py-4 px-6 rounded-xl hover:bg-gray-200 transition-all hover:-translate-y-1 text-lg relative shadow-lg">
                                    <span id="btn-text">إرسال الطلب</span><i id="btn-spinner" class="fas fa-spinner fa-spin hidden absolute left-1/2 top-1/2 transform -translate-x-1/2 -translate-y-1/2 text-xl"></i>
                                </button>
                            </div>
                        </form>

                        <div id="track-fields" class="hidden text-center">
                            <h3 class="text-xl font-bold text-white mb-2">تتبع حالة طلبك</h3>
                            <p class="text-sm text-gray-400 mb-6">أدخل رقم التتبع الخاص بك لمعرفة حالة الطلب ورد الإدارة</p>
                            <form id="track-form" class="flex flex-col sm:flex-row gap-3">
                                <input type="text" id="track-ticket-id" placeholder="مثال: REQ-XXXXXX" class="flex-grow bg-black/40 border border-white/10 rounded-xl py-3 px-4 font-mono font-bold text-white focus:outline-none focus:border-[var(--brand-gold)] focus:bg-black/60 text-center sm:text-right" required>
                                <button type="submit" id="track-btn" class="bg-[var(--brand-gold)] text-black font-black py-3 px-8 rounded-xl hover:bg-yellow-500 transition-all shadow-lg whitespace-nowrap">
                                    <span id="track-btn-text">تتبع</span><i id="track-btn-spinner" class="fas fa-spinner fa-spin hidden"></i>
                                </button>
                            </form>
                            <div id="track-result" class="mt-8 hidden"></div>
                        </div>

                    </div>
                </div>
            </div>`;
            
            transitionToPage(requestsPage); 

            setTimeout(() => {
                const tabs = {
                    request: document.getElementById('tab-request'),
                    feedback: document.getElementById('tab-feedback'),
                    track: document.getElementById('tab-track')
                };
                const fields = {
                    request: document.getElementById('request-fields'),
                    feedback: document.getElementById('feedback-fields'),
                    track: document.getElementById('track-fields')
                };
                const requestForm = document.getElementById('request-form');
                const trackForm = document.getElementById('track-form');
                const formMessage = document.getElementById('request-form-message');
                const submitBtnContainer = document.getElementById('submit-btn-container');
                const smartCheckDiv = document.getElementById('smart-check-result');
                const workNameInput = document.getElementById('work-name');

                const switchTab = (activeKey) => {
                    formMessage.classList.add('hidden');
                    Object.keys(tabs).forEach(key => {
                        if (key === activeKey) {
                            tabs[key].classList.add('bg-white/10', 'text-white', 'shadow-md');
                            tabs[key].classList.remove('text-gray-400', 'hover:text-white', 'hover:text-[var(--brand-gold)]');
                            fields[key].classList.remove('hidden');
                        } else {
                            tabs[key].classList.remove('bg-white/10', 'text-white', 'shadow-md');
                            if(key === 'track') tabs[key].classList.add('text-gray-400', 'hover:text-[var(--brand-gold)]');
                            else tabs[key].classList.add('text-gray-400', 'hover:text-white');
                            fields[key].classList.add('hidden');
                        }
                    });
                    
                    if (activeKey === 'track') {
                        requestForm.classList.add('hidden');
                    } else {
                        requestForm.classList.remove('hidden');
                    }
                };

                if (tabs.request) tabs.request.addEventListener('click', () => switchTab('request'));
                if (tabs.feedback) tabs.feedback.addEventListener('click', () => switchTab('feedback'));
                if (tabs.track) tabs.track.addEventListener('click', () => switchTab('track'));

                if (workNameInput && smartCheckDiv) {
                    workNameInput.addEventListener('input', (e) => {
                        const term = e.target.value.trim().toLowerCase();
                        if (term.length < 2) { smartCheckDiv.classList.add('hidden'); return; }
                        let allItems = [];
                        if(db.movies) allItems = allItems.concat(db.movies.map(m => ({...m, itemType: 'movies'})));
                        if(db.series) allItems = allItems.concat(db.series.map(s => ({...s, itemType: 'series'})));
                        const foundItem = allItems.find(item => isSearchMatch(item.title, term));
                        if (foundItem) {
                            const id_prefix = foundItem.itemType === 'movies' ? 'm' : 's', full_id = foundItem.id_prefix || (id_prefix + foundItem.id), slug = slugify(foundItem.title), link = `/details/${foundItem.itemType}/${full_id}/${slug}`;
                            smartCheckDiv.innerHTML = `<div class="bg-green-500/10 border border-green-500/20 text-green-400 p-3 rounded-xl flex flex-col sm:flex-row items-center justify-between text-sm shadow-inner gap-3"><div class="flex items-center gap-2"><i class="fas fa-check-circle animate-pulse"></i><span>موجود بالفعل! <strong>${foundItem.title}</strong></span></div><a href="${link}" class="bg-green-500 text-black px-3 py-1.5 rounded-lg font-bold hover:bg-green-400 transition-colors shadow-lg whitespace-nowrap">شاهد الآن</a></div>`;
                            smartCheckDiv.classList.remove('hidden');
                        } else { smartCheckDiv.classList.add('hidden'); }
                    });
                }

                if (requestForm) {
                    requestForm.addEventListener('submit', (e) => {
                        e.preventDefault(); 
                        const submitBtn = document.getElementById('submit-btn'), btnText = document.getElementById('btn-text'), btnSpinner = document.getElementById('btn-spinner');
                        submitBtn.disabled = true; btnText.classList.add('opacity-0'); btnSpinner.classList.remove('hidden');
                        
                        const isFeedback = !fields.feedback.classList.contains('hidden');
                        const requestType = isFeedback ? 'feedback' : 'request';
                        const formData = new FormData(requestForm); formData.append('type', requestType);

                        fetch('?ajax_action=submit_request', { method: 'POST', body: formData })
                        .then(res => res.json())
                        .then(data => {
                            formMessage.innerHTML = data.msg;
                            formMessage.className = data.success ? 'mb-6 p-5 rounded-xl text-center font-bold bg-green-500/10 text-green-400 border border-green-500/20 shadow-lg' : 'mb-6 p-4 rounded-xl text-center font-bold bg-red-500/10 text-red-400 border border-red-500/20';
                            if (data.success) { requestForm.reset(); if(smartCheckDiv) smartCheckDiv.classList.add('hidden'); }
                            formMessage.classList.remove('hidden');
                        })
                        .catch(err => {
                            formMessage.textContent = 'حدث خطأ في الاتصال بالخادم.';
                            formMessage.className = 'mb-6 p-4 rounded-xl text-center font-bold bg-red-500/10 text-red-400 border border-red-500/20';
                            formMessage.classList.remove('hidden');
                        })
                        .finally(() => { submitBtn.disabled = false; btnText.classList.remove('opacity-0'); btnSpinner.classList.add('hidden'); });
                    });
                }

                if (trackForm) {
                    trackForm.addEventListener('submit', (e) => {
                        e.preventDefault();
                        const tBtn = document.getElementById('track-btn'), tText = document.getElementById('track-btn-text'), tSpinner = document.getElementById('track-btn-spinner');
                        const resDiv = document.getElementById('track-result');
                        
                        tBtn.disabled = true; tText.classList.add('hidden'); tSpinner.classList.remove('hidden');
                        resDiv.classList.add('hidden');

                        const fd = new FormData(); fd.append('ticket_id', document.getElementById('track-ticket-id').value);
                        
                        fetch('?ajax_action=track_request', { method: 'POST', body: fd })
                        .then(res => res.json())
                        .then(data => {
                            resDiv.innerHTML = data.html;
                            resDiv.classList.remove('hidden');
                        })
                        .catch(() => {
                            resDiv.innerHTML = "<div class='text-red-500 font-bold'>حدث خطأ. يرجى المحاولة لاحقاً.</div>";
                            resDiv.classList.remove('hidden');
                        })
                        .finally(() => { tBtn.disabled = false; tText.classList.remove('hidden'); tSpinner.classList.add('hidden'); });
                    });
                }
            }, 50);
        }

        function openModal(id) { const m = document.getElementById(id); if (m) { m.classList.remove('invisible','opacity-0'); m.querySelector('.modal-content').classList.remove('scale-95'); } }
        function closeModal(id) { const m = document.getElementById(id); if (m) { m.classList.add('invisible','opacity-0'); m.querySelector('.modal-content').classList.add('scale-95'); } }
        
        let currentRenderedGridCount = 0; 

        document.addEventListener('DOMContentLoaded', () => {
            homePage = document.getElementById('home-page'); detailsPage = document.getElementById('details-page'); requestsPage = document.getElementById('requests-page'); allItemsPage = document.getElementById('all-items-page'); searchResultsPage = document.getElementById('search-results-page'); historyPage = document.getElementById('history-page');
            function renderSliderContent(data, sliderId, type, count) { const slider = document.getElementById(sliderId); if (!slider) return; slider.innerHTML = data.slice(0, count).map((item, i) => `<div class="swiper-slide">${createCard(item, type, i * 20)}</div>`).join(''); }
            function renderEpisodesSlider(data, sliderId) { const slider = document.getElementById(sliderId); if (!slider) return; slider.innerHTML = data.map((item, i) => `<div class="swiper-slide">${createEpisodeCard(item, i * 20)}</div>`).join(''); }
            function renderGridContent(data, gridId, type, count) { const grid = document.getElementById(gridId); if (!grid) return; let itemsToRender = data; if (type === 'series' || type === 'arabic_series') { itemsToRender = data.filter(item => !item.ramadan_year || item.ramadan_year == 0); } const routingType = (type.includes('movies')) ? 'movies' : 'series'; grid.innerHTML = itemsToRender.slice(0, count).map((item, i) => createCard(item, routingType, i * 40)).join(''); }
            
            function renderHomePageContent() {
                let homeGridCount = 24; 
                if (window.innerWidth < 640) {
                    homeGridCount = 12; 
                } else if (window.innerWidth >= 640 && window.innerWidth < 1024) {
                    homeGridCount = 16; 
                }

                currentRenderedGridCount = homeGridCount;

                updateSEOMetaTags(db.seo['home']?.title || "عرب فليكس مشاهدة أحدث الأفلام والمسلسلات", db.seo['home']?.description || "استمتع بمشاهدة الأفلام والمسلسلات الحصرية.", "/");
                
                if (db.latest_episodes && db.latest_episodes.length > 0) {
                    renderEpisodesSlider(db.latest_episodes, 'latest-episodes-slider'); document.getElementById('latest-episodes-section').classList.remove('hidden');
                    if (!latestEpisodesSwiper) { try { latestEpisodesSwiper = new Swiper('#latest-episodes-swiper', { observer: true, observeParents: true, slidesPerView: 'auto', spaceBetween: 16, rtl: true, grabCursor: true, autoplay: { delay: 3500, disableOnInteraction: true }, navigation: { nextEl: '#latest-episodes-section .content-next', prevEl: '#latest-episodes-section .content-prev' }, breakpoints: { 640: { spaceBetween: 20 }, 1024: { spaceBetween: 24 } } }); } catch(e){} } else { latestEpisodesSwiper.update(); }
                }
                if (db.recent && db.recent.length > 0) { 
                    renderSliderContent(db.recent, 'recent-slider', null, 15); document.getElementById('recent-section').classList.remove('hidden');
                    if (!recentSwiper) { try { recentSwiper = new Swiper('#recent-swiper', { observer: true, observeParents: true, slidesPerView: 'auto', spaceBetween: 16, rtl: true, grabCursor: true, autoplay: { delay: 4500, disableOnInteraction: true }, navigation: { nextEl: '#recent-section .content-next', prevEl: '#recent-section .content-prev' }, breakpoints: { 640: { spaceBetween: 20 }, 1024: { spaceBetween: 24 } } }); } catch(e){} } else { recentSwiper.update(); }
                }
                
                renderGridContent(db.arabic_movies, 'movies-grid', 'arabic_movies', homeGridCount);
                if (db.foreign_movies && db.foreign_movies.length > 0) { document.getElementById('foreign-movies-section').classList.remove('hidden'); renderGridContent(db.foreign_movies, 'foreign-movies-grid', 'foreign_movies', homeGridCount); }
                if (db.indian_movies && db.indian_movies.length > 0) { document.getElementById('indian-movies-section').classList.remove('hidden'); renderGridContent(db.indian_movies, 'indian-movies-grid', 'indian_movies', homeGridCount); }
                
                renderGridContent(db.arabic_series, 'series-grid', 'series', homeGridCount);
                if (db.foreign_series && db.foreign_series.length > 0) { document.getElementById('foreign-section').classList.remove('hidden'); renderGridContent(db.foreign_series, 'foreign-grid', 'foreign_series', homeGridCount); }
                if (db.turkish_series && db.turkish_series.length > 0) { document.getElementById('turkish-section').classList.remove('hidden'); renderGridContent(db.turkish_series, 'turkish-grid', 'turkish_series', homeGridCount); }
                if (db.indian_series && db.indian_series.length > 0) { document.getElementById('indian-series-section').classList.remove('hidden'); renderGridContent(db.indian_series, 'indian-series-grid', 'indian_series', homeGridCount); }
                if (db.tv_shows && db.tv_shows.length > 0) { document.getElementById('tvshows-section').classList.remove('hidden'); renderGridContent(db.tv_shows, 'tvshows-grid', 'tv_shows', homeGridCount); }
                if (db.wrestling && db.wrestling.length > 0) { document.getElementById('wrestling-section').classList.remove('hidden'); renderGridContent(db.wrestling, 'wrestling-grid', 'wrestling', homeGridCount); }
            }

            function _renderAllItemsPage(type, page) {
                let seoType = 'series', pageTitle = '';
                if (type === 'movies' || type === 'arabic_movies') { seoType = 'movies'; pageTitle = 'أفلام عرب فليكس'; type = 'arabic_movies'; } 
                else if (type === 'foreign_movies') { seoType = 'movies'; pageTitle = 'الأفلام الأجنبية'; } 
                else if (type === 'indian_movies') { seoType = 'movies'; pageTitle = 'الأفلام الهندية'; } 
                else if (type === 'series' || type === 'arabic_series') { pageTitle = 'مسلسلات عربية'; type = 'arabic_series'; } 
                else if (type === 'turkish_series') { pageTitle = 'المسلسلات التركية'; } 
                else if (type === 'foreign_series') { pageTitle = 'المسلسلات الأجنبية'; } 
                else if (type === 'indian_series') { pageTitle = 'المسلسلات الهندية'; } 
                else if (type === 'tv_shows') { pageTitle = 'البرامج التلفزيونية'; } 
                else if (type === 'wrestling') { pageTitle = 'المصارعة الحرة'; }

                let urlPathType = type; 
                if (type === 'arabic_movies') urlPathType = 'movies'; 
                if (type === 'foreign_movies') urlPathType = 'foreign-movies'; 
                if (type === 'indian_movies') urlPathType = 'indian-movies'; 
                if (type === 'arabic_series') urlPathType = 'series'; 
                if (type === 'turkish_series') urlPathType = 'turkish'; 
                if (type === 'foreign_series') urlPathType = 'foreign'; 
                if (type === 'indian_series') urlPathType = 'indian-series'; 
                if (type === 'tv_shows') urlPathType = 'tvshows'; 
                if (type === 'wrestling') urlPathType = 'wrestling';

                updateSEOMetaTags(db.seo[seoType]?.title || pageTitle, db.seo[seoType]?.description || "", `/all-${urlPathType}/${page}`);
                const itemsPerPage = 24; let allItems = db[type] || []; if (type === 'arabic_series') { allItems = db.arabic_series.filter(item => !item.ramadan_year || item.ramadan_year == 0); }
                const totalPages = Math.ceil(allItems.length / itemsPerPage), pageNum = parseInt(page, 10) || 1, itemsToShow = allItems.slice((pageNum - 1) * itemsPerPage, ((pageNum - 1) * itemsPerPage) + itemsPerPage), routingType = (type.includes('movies')) ? 'movies' : 'series';
                let contentHTML = `<div class="container mx-auto px-4 sm:px-6 lg:px-8 py-12 min-h-screen" style="animation: fadeIn 0.7s ease-out;"><h1 class="text-3xl font-black text-white mb-10 pb-4 border-b border-white/5">${pageTitle}</h1><div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-6 gap-3 sm:gap-5">${itemsToShow.map((item, i) => createCard(item, routingType, i * 40)).join('')}</div>`;
                if (totalPages > 1) { contentHTML += `<div class="pagination-controls"><a href="/all-${urlPathType}/${pageNum - 1}" class="pagination-btn ${pageNum <= 1 ? 'disabled pointer-events-none opacity-50' : ''}">السابق</a><span class="font-bold text-gray-400">صفحة ${pageNum} من ${totalPages}</span><a href="/all-${urlPathType}/${pageNum + 1}" class="pagination-btn ${pageNum >= totalPages ? 'disabled pointer-events-none opacity-50' : ''}">التالي</a></div>`; }
                contentHTML += `</div>`; allItemsPage.innerHTML = contentHTML; transitionToPage(allItemsPage);
            }
            function _renderRamadanPage(year) {
                updateSEOMetaTags(`مسلسلات رمضان ${year} - عرب فليكس`, `شاهد أحدث مسلسلات رمضان ${year} بجودة عالية`, `/ramadan/${year}`);
                const ramadanSeries = db.series.filter(item => parseInt(item.ramadan_year, 10) === parseInt(year, 10));
                let contentHTML = `<div class="container mx-auto px-4 sm:px-6 lg:px-8 py-12 min-h-screen relative" style="animation: fadeIn 0.7s ease-out;"><h1 class="text-3xl font-black text-white mb-10 flex items-center gap-3"><i class="fas fa-moon golden-text"></i> مسلسلات رمضان ${year}</h1>`;
                if (year == '2026' && db.top_ramadan && db.top_ramadan.length > 0) { contentHTML += `<div class="mb-14 relative z-20 glass-panel rounded-3xl p-5 sm:p-8"><h2 class="text-2xl font-black text-white flex items-center gap-3 mb-8"><i class="fa-solid fa-trophy text-[var(--brand-gold)]"></i> التوب 3 الأعلى مشاهدة</h2><div class="grid grid-cols-3 gap-3 sm:gap-5 max-w-4xl mx-auto">${db.top_ramadan.map((item, i) => createTopCard(item, 'series', i * 40, i + 1)).join('')}</div></div><h2 class="text-2xl font-bold text-white mb-6">كل مسلسلات رمضان 2026</h2>`; }
                if (ramadanSeries.length > 0) { contentHTML += `<div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-6 gap-3 sm:gap-5 relative z-20">${ramadanSeries.map((item, i) => createCard(item, 'series', i * 40)).join('')}</div>`; } else { contentHTML += `<div class="text-center text-gray-500 mt-20 relative z-20 glass-panel p-10 rounded-2xl max-w-md mx-auto"><i class="fas fa-calendar-times fa-4x mb-6 opacity-50"></i><p class="text-lg font-bold">لا توجد مسلسلات مضافة لهذا الموسم بعد.</p></div>`; }
                contentHTML += `</div>`; allItemsPage.innerHTML = contentHTML; transitionToPage(allItemsPage);
            }
            function _renderSearchResultsPage(term, page) {
                const itemsPerPage = 24, cleanTerm = escapeHTML(decodeURIComponent(term)), searchLower = decodeURIComponent(term).toLowerCase(); updateSEOMetaTags(`نتائج البحث عن: ${cleanTerm}`, `نتائج البحث عن ${cleanTerm} في عرب فليكس`, `/search/${term}/${page}`);
                const allContent = [...db.movies.map(m => ({...m, type: 'movies'})), ...db.series.map(s => ({...s, type: 'series'}))], searchResults = allContent.filter(item => isSearchMatch(item.title, searchLower)), totalPages = Math.ceil(searchResults.length / itemsPerPage), pageNum = parseInt(page, 10) || 1, itemsToShow = searchResults.slice((pageNum - 1) * itemsPerPage, ((pageNum - 1) * itemsPerPage) + itemsPerPage);
                let contentHTML = `<div class="container mx-auto px-4 sm:px-6 lg:px-8 py-12 min-h-screen" style="animation: fadeIn 0.7s ease-out;"><h1 class="text-2xl sm:text-3xl font-black text-white mb-10 pb-4 border-b border-white/5">نتائج البحث عن: <span class="golden-text">"${cleanTerm}"</span></h1>`;
                if (searchResults.length > 0) { contentHTML += `<div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-6 gap-3 sm:gap-5">${itemsToShow.map((item, i) => createCard(item, item.type, i * 40)).join('')}</div>`; if (totalPages > 1) contentHTML += `<div class="pagination-controls"><a href="/search/${term}/${pageNum - 1}" class="pagination-btn ${pageNum <= 1 ? 'disabled pointer-events-none opacity-50' : ''}">السابق</a><span class="font-bold text-gray-400">صفحة ${pageNum} من ${totalPages}</span><a href="/search/${term}/${pageNum + 1}" class="pagination-btn ${pageNum >= totalPages ? 'disabled pointer-events-none opacity-50' : ''}">التالي</a></div>`; } else contentHTML += `<div class="text-center text-gray-500 mt-20 glass-panel p-10 rounded-2xl max-w-md mx-auto"><i class="fas fa-search fa-4x mb-6 opacity-50"></i><p class="text-lg font-bold">لا توجد نتائج بحث تطابق "${cleanTerm}".</p></div>`;
                contentHTML += `</div>`; searchResultsPage.innerHTML = contentHTML; transitionToPage(searchResultsPage);
            }
            function initHeroSlider() {
                const sliderContent = (db.hero || []).slice(0, 20), swiperWrapper = document.querySelector('.hero-slider .swiper-wrapper'); if (!swiperWrapper) return; 
                if (heroSwiper && typeof heroSwiper.destroy === 'function') heroSwiper.destroy(true, true);
                if (sliderContent.length === 0) { swiperWrapper.innerHTML = ''; document.getElementById('hero-section').style.display = 'none'; return; }
                document.getElementById('hero-section').style.display = 'block'; swiperWrapper.innerHTML = sliderContent.map(item => { const id_p = item.type === 'movies' ? 'm' : 's', full_id = id_p + item.id, slug = slugify(item.title), href = `/details/${item.type}/${full_id}/${slug}`; return `<a href="${href}" class="swiper-slide" style="background-image:url(${item.poster})"><div class="slider-content"><div><h2 class="text-2xl sm:text-4xl font-black text-white text-shadow-lg mb-2">${item.title}</h2><p class="text-sm font-bold text-gray-300 drop-shadow-md bg-black/40 backdrop-blur-sm inline-block px-3 py-1 rounded-full border border-white/10">${item.year} <span class="mx-1">•</span> ${item.type === 'movies' ? 'فيلم' : 'مسلسل'}</p></div></div></a>`; }).join('');
                try { heroSwiper = new Swiper('.hero-slider', { observer: true, observeParents: true, effect: 'coverflow', coverflowEffect: { rotate: 0, stretch: 0, depth: 100, modifier: 2.5, slideShadows: false }, grabCursor: true, centeredSlides: true, slidesPerView: 'auto', loop: sliderContent.length > 2, rtl: true, autoplay: { delay: 4000, disableOnInteraction: false }, pagination: { el: '.swiper-pagination', clickable: true }, navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' } }); } catch(e) {}
            }
            function updateBottomNavActiveState(path) {
                document.querySelectorAll('.bottom-nav-link').forEach(link => { link.classList.remove('bottom-nav-active', 'text-white'); if (link.getAttribute('data-path') === path || (path === '/' && link.getAttribute('data-path') === '/')) { link.classList.add('bottom-nav-active'); } });
                closeMobileRamadanModal();
            }
            
            function router() {
                try {
                    let path = window.location.pathname, hash = window.location.hash; const fullPath = path + hash; updateBottomNavActiveState(fullPath || path);
                    if (path.startsWith('/details/')) { const parts = path.split('/'); _renderDetailsPage(parts[3], parts[2]); } 
                    else if (path.startsWith('/all-movies/')) { _renderAllItemsPage('arabic_movies', path.split('/')[2]); } 
                    else if (path.startsWith('/all-foreign-movies/')) { _renderAllItemsPage('foreign_movies', path.split('/')[2]); } 
                    else if (path.startsWith('/all-indian-movies/')) { _renderAllItemsPage('indian_movies', path.split('/')[2]); } 
                    else if (path.startsWith('/all-series/')) { _renderAllItemsPage('arabic_series', path.split('/')[2]); } 
                    else if (path.startsWith('/all-turkish/')) { _renderAllItemsPage('turkish_series', path.split('/')[2]); } 
                    else if (path.startsWith('/all-foreign/')) { _renderAllItemsPage('foreign_series', path.split('/')[2]); } 
                    else if (path.startsWith('/all-indian-series/')) { _renderAllItemsPage('indian_series', path.split('/')[2]); } 
                    else if (path.startsWith('/all-tvshows/')) { _renderAllItemsPage('tv_shows', path.split('/')[2]); } 
                    else if (path.startsWith('/all-wrestling/')) { _renderAllItemsPage('wrestling', path.split('/')[2]); } 
                    else if (path.startsWith('/ramadan/')) { _renderRamadanPage(path.split('/')[2]); } 
                    else if (path.startsWith('/search/')) { _renderSearchResultsPage(path.split('/')[2], path.split('/')[3]); } 
                    else if (path === '/requests') { _renderRequestsPage(); } 
                    else if (path === '/history') { _renderHistoryPage(); } 
                    else { renderHomePageContent(); initHeroSlider(); transitionToPage(homePage); if (hash) { let tId = hash.substring(1), el = document.getElementById(tId); if (el) setTimeout(() => el.scrollIntoView({ behavior: 'smooth', block: 'start' }), 500); } else { window.scrollTo({ top: 0, behavior: 'instant' }); } }
                } catch (error) { if (homePage) { document.querySelectorAll('.page').forEach(p => p.classList.remove('page-active')); homePage.classList.add('page-active'); } }
            }
            
            router();
            
            try {
                logVisitorDemographics();
                if (localStorage.getItem('prayerBannerClosed') === 'true') { const pBanner = document.getElementById('prayer-banner'); if (pBanner) pBanner.style.display = 'none'; }
                const mrt = document.getElementById('mobile-ramadan-trigger'); if(mrt) mrt.addEventListener('click', openMobileRamadanModal);
                const crmb = document.getElementById('close-ramadan-modal'); if(crmb) crmb.addEventListener('click', closeMobileRamadanModal);

                const sForm = document.getElementById('search-form'); 
                if(sForm) {
                    sForm.addEventListener('submit', (e) => { 
                        e.preventDefault(); const term = document.getElementById('search-bar').value.trim(); 
                        if(term) { const fd = new FormData(); fd.append('keyword', term); fetch('/index.php?ajax_action=log_search', { method: 'POST', body: fd }).catch(e => console.error(e)); history.pushState(null, null, `/search/${encodeURIComponent(term)}/1`); router(); document.getElementById('live-search-results').classList.add('hidden'); document.getElementById('search-bar').blur(); } 
                    });
                }
                window.addEventListener('popstate', router);
                document.body.addEventListener('click', (e) => {
                    let link = e.target.closest('a');
                    if (link && document.getElementById('mobile-ramadan-modal') && document.getElementById('mobile-ramadan-modal').contains(link)) { closeMobileRamadanModal(); }
                    if (link && link.href && link.host === window.location.host && !link.getAttribute('target')) {
                        if (link.pathname === window.location.pathname && link.hash) { e.preventDefault(); document.getElementById(link.hash.substring(1))?.scrollIntoView({ behavior: 'smooth', block: 'start' }); updateBottomNavActiveState(link.pathname + link.hash); } 
                        else if (link.pathname !== window.location.pathname || link.hash) { e.preventDefault(); history.pushState(null, '', link.href); router(); }
                    }
                });
                
                const sInput = document.getElementById('search-bar'), lrBox = document.getElementById('live-search-results');
                if(sInput && lrBox) {
                    sInput.addEventListener('input', function(e) {
                        const term = this.value.trim().toLowerCase();
                        if(term.length < 2) { lrBox.classList.add('hidden'); return; }
                        let allItems = []; if(db.movies) allItems = allItems.concat(db.movies.map(m => ({...m, itemType: 'movies'}))); if(db.series) allItems = allItems.concat(db.series.map(s => ({...s, itemType: 'series'})));
                        const filtered = allItems.filter(item => isSearchMatch(item.title, term)).slice(0, 8);
                        if(filtered.length > 0) {
                            let resultsHTML = filtered.map(item => {
                                const id_prefix = item.itemType === 'movies' ? 'm' : 's', full_id = item.id_prefix || (id_prefix + full_id), slug = slugify(item.title);
                                let typeText = 'مسلسل'; 
                                if (item.itemType === 'movies' && item.category === 'foreign_movie') typeText = 'فيلم أجنبي'; 
                                else if (item.itemType === 'movies' && item.category === 'indian_movie') typeText = 'فيلم هندي'; 
                                else if (item.itemType === 'movies') typeText = 'فيلم'; 
                                else if (item.category === 'turkish') typeText = 'مسلسل تركي'; 
                                else if (item.category === 'foreign') typeText = 'مسلسل أجنبي'; 
                                else if (item.category === 'indian') typeText = 'مسلسل هندي'; 
                                else if (item.category === 'tv_show') typeText = 'برنامج'; 
                                else if (item.category === 'wrestling') typeText = 'مصارعة حرة';
                                return `<a href="/details/${item.itemType}/${full_id}/${slug}" class="flex items-center gap-3 p-3 hover:bg-white/5 transition-colors border-b border-white/5 last:border-b-0 group"><div class="flex-shrink-0 relative w-12 h-16 sm:w-14 sm:h-20 rounded-lg overflow-hidden shadow-md group-hover:shadow-[0_0_15px_rgba(218,165,32,0.3)] transition-all"><img src="${item.poster}" class="w-full h-full object-cover"></div><div class="flex flex-col justify-center w-full overflow-hidden"><span class="text-sm font-black text-gray-200 group-hover:text-[var(--brand-gold)] truncate transition-colors mb-1.5">${item.title}</span><div class="flex items-center gap-2"><span class="text-[10px] font-bold bg-white/10 text-gray-300 px-2 py-0.5 rounded-md">${typeText} ${item.year ? ' - '+item.year : ''}</span>${item.rating ? `<span class="text-[10px] font-bold text-[var(--brand-gold)] flex items-center gap-1"><i class="fas fa-star text-[8px]"></i>${item.rating}</span>` : ''}</div></div></a>`;
                            }).join('');
                            resultsHTML += `<button type="button" onclick="document.getElementById('search-form').dispatchEvent(new Event('submit', {cancelable: true, bubbles: true}))" class="w-full text-center p-3 text-sm font-bold text-gray-400 hover:text-white hover:bg-white/5 transition-colors border-t border-white/5 bg-black/40">عرض كل النتائج لـ "${term}" <i class="fas fa-arrow-left ml-2 text-[10px]"></i></button>`;
                            lrBox.innerHTML = resultsHTML; lrBox.classList.remove('hidden');
                        } else {
                            lrBox.innerHTML = `<div class="p-8 text-center flex flex-col items-center gap-3"><i class="fas fa-search text-gray-600 text-3xl mb-2"></i><span class="text-sm text-gray-400 font-bold">لا توجد نتائج لـ "${term}"</span></div>`; lrBox.classList.remove('hidden');
                        }
                    });
                    document.addEventListener('click', function(e) { if(!sInput.contains(e.target) && !lrBox.contains(e.target)) { lrBox.classList.add('hidden'); } });
                    sInput.addEventListener('focus', function() { if(this.value.trim().length >= 2) { lrBox.classList.remove('hidden'); } });
                }
                
                let lastWindowWidth = window.innerWidth;
                window.addEventListener('resize', function() {
                   if (window.innerWidth !== lastWindowWidth) {
                       lastWindowWidth = window.innerWidth;
                       if (document.getElementById('home-page') && document.getElementById('home-page').classList.contains('page-active')) {
                           let requiredGridCount = 24; 
                           if (window.innerWidth < 640) requiredGridCount = 12; 
                           else if (window.innerWidth >= 640 && window.innerWidth < 1024) requiredGridCount = 16;
                           if (requiredGridCount !== currentRenderedGridCount) { renderHomePageContent(); }
                       }
                   }
                });

                setInterval(() => { fetch('/index.php?ajax_action=heartbeat').catch(() => {}); }, 60000); 
            } catch(e) { console.error("Non-critical init error:", e); }
        });
    </script>
</body>
</html>