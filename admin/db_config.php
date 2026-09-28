<?php
// بدء الجلسة في البداية فوراً لضمان عمل تسجيل الدخول (Login) بشكل سليم
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// إعدادات الاتصال الجديدة (AwardSpace)
$servername = "fdb1029.awardspace.net";
$username = "4617465_arabfleex";
$password = "alifalah9090";
$dbname = "4617465_arabfleex";

$conn = new mysqli($servername,$username, $password,$dbname);
if ($conn->connect_error) { die(); } // صمت تام عند الخطأ
$conn->set_charset("utf8mb4");
date_default_timezone_set('Africa/Cairo');

// --- 1. إنشاء جداول الحماية والـ SEO ---
$conn->query("CREATE TABLE IF NOT EXISTS blocked_ips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(50) NOT NULL UNIQUE,
    reason VARCHAR(255),
    blocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS auto_seo_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL UNIQUE,
    title_template VARCHAR(255) NOT NULL,
    desc_template TEXT NOT NULL,
    keywords_template TEXT NOT NULL
)");

// --- 2. تحديثات قاعدة البيانات التلقائية (بما فيها نظام المديرين وسجل النشاطات) ---
try {
    // =========================================================================
    // --- الإضافات الجديدة الخاصة بنظام الأعضاء والـ VIP (بدون حذف أي كود قديم) ---
    // =========================================================================
    
    // إنشاء جدول الأعضاء (المجانيين والـ VIP)
    $conn->query("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        is_vip TINYINT(1) DEFAULT 0,
        vip_expires_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // إنشاء جدول أكواد التفعيل للـ VIP
    $conn->query("CREATE TABLE IF NOT EXISTS vip_codes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) NOT NULL UNIQUE,
        duration_days INT NOT NULL DEFAULT 30,
        is_used TINYINT(1) DEFAULT 0,
        used_by_user_id INT NULL,
        used_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // تحديث جدول الطلبات لربطه برقم المستخدم (عشان نميز طلبات الـ VIP)
    $check_req_user =$conn->query("SHOW COLUMNS FROM requests LIKE 'user_id'");
    if ($check_req_user && $check_req_user->num_rows == 0) {$conn->query("ALTER TABLE requests ADD COLUMN user_id INT NULL AFTER id");
    }
    // =========================================================================

    // تحديثات الأفلام والمسلسلات القديمة
    $check1 =$conn->query("SHOW COLUMNS FROM movies LIKE 'watch_link_3'");
    if ($check1 && $check1->num_rows == 0) {$conn->query("ALTER TABLE movies ADD COLUMN watch_link_3 TEXT"); }
    $check2 =$conn->query("SHOW COLUMNS FROM movies LIKE 'watch_link_4'");
    if ($check2 && $check2->num_rows == 0) {$conn->query("ALTER TABLE movies ADD COLUMN watch_link_4 TEXT"); }
    $check3 =$conn->query("SHOW COLUMNS FROM episodes LIKE 'watch_link_3'");
    if ($check3 && $check3->num_rows == 0) {$conn->query("ALTER TABLE episodes ADD COLUMN watch_link_3 TEXT"); }
    $check4 =$conn->query("SHOW COLUMNS FROM episodes LIKE 'watch_link_4'");
    if ($check4 && $check4->num_rows == 0) {$conn->query("ALTER TABLE episodes ADD COLUMN watch_link_4 TEXT"); }
    
    $check_session =$conn->query("SHOW COLUMNS FROM visitor_log LIKE 'session_id'");
    if ($check_session && $check_session->num_rows == 0) {$conn->query("ALTER TABLE visitor_log ADD COLUMN session_id VARCHAR(100) AFTER ip_address");
        $conn->query("ALTER TABLE visitor_log DROP INDEX ip_address"); 
        $conn->query("ALTER TABLE visitor_log DROP INDEX unique_visit"); 
        $conn->query("ALTER TABLE visitor_log ADD UNIQUE KEY unique_session_visit (session_id, visit_date)");
    }

    $check_category =$conn->query("SHOW COLUMNS FROM series LIKE 'category'");
    if ($check_category && $check_category->num_rows == 0) {$conn->query("ALTER TABLE series ADD COLUMN category VARCHAR(50) DEFAULT 'series' AFTER is_recent");
    }

    $check_cast_m =$conn->query("SHOW COLUMNS FROM movies LIKE 'cast_data'");
    if ($check_cast_m && $check_cast_m->num_rows == 0) {$conn->query("ALTER TABLE movies ADD COLUMN cast_data TEXT AFTER description");
    }
    $check_cast_s =$conn->query("SHOW COLUMNS FROM series LIKE 'cast_data'");
    if ($check_cast_s && $check_cast_s->num_rows == 0) {$conn->query("ALTER TABLE series ADD COLUMN cast_data TEXT AFTER description");
    }

    // --- فصل الدومينات (التحديث الجديد) ---
    $old_domain = 'arabfleex.xo.je';
    
    // 1. إضافة الأعمدة
    $conn->query("ALTER TABLE visitor_log ADD COLUMN IF NOT EXISTS domain_name VARCHAR(100) NULL AFTER session_id");
    $conn->query("ALTER TABLE views_log ADD COLUMN IF NOT EXISTS domain_name VARCHAR(100) NULL AFTER content_id");
    $conn->query("ALTER TABLE requests ADD COLUMN IF NOT EXISTS domain_name VARCHAR(100) NULL AFTER status");
    $conn->query("ALTER TABLE search_logs ADD COLUMN IF NOT EXISTS domain_name VARCHAR(100) NULL AFTER keyword");

    // 2. تحديث السجلات القديمة لتنسب إلى الدومين القديم (تلقائياً)
    $conn->query("UPDATE visitor_log SET domain_name = '$old_domain' WHERE domain_name IS NULL OR domain_name = ''");
    $conn->query("UPDATE views_log SET domain_name = '$old_domain' WHERE domain_name IS NULL OR domain_name = ''");
    $conn->query("UPDATE requests SET domain_name = '$old_domain' WHERE domain_name IS NULL OR domain_name = ''");
    $conn->query("UPDATE search_logs SET domain_name = '$old_domain' WHERE domain_name IS NULL OR domain_name = ''");

    // 3. تعديل مفاتيح البحث والزيارات لتكون فريدة بناءً على الكلمة + الدومين معاً
    $check_search_idx =$conn->query("SHOW INDEX FROM search_logs WHERE Key_name = 'keyword'");
    if ($check_search_idx && $check_search_idx->num_rows > 0) {$conn->query("ALTER TABLE search_logs DROP INDEX keyword");
        $conn->query("ALTER TABLE search_logs ADD UNIQUE KEY unique_keyword_domain (keyword, domain_name)");
    }
    
    $check_vis_idx =$conn->query("SHOW INDEX FROM visitor_log WHERE Key_name = 'unique_session_visit'");
    if ($check_vis_idx && $check_vis_idx->num_rows > 0) {$idx_cols = [];
        while($row =$check_vis_idx->fetch_assoc()) { $idx_cols[] =$row['Column_name']; }
        if (!in_array('domain_name', $idx_cols)) {$conn->query("ALTER TABLE visitor_log DROP INDEX unique_session_visit");
            $conn->query("ALTER TABLE visitor_log ADD UNIQUE KEY unique_session_domain_visit (session_id, domain_name, visit_date)");
        }
    }
    // ------------------------------------

    // إضافة عمود الصلاحية لجدول المديرين (super_admin أو admin)
    $check_role =$conn->query("SHOW COLUMNS FROM admins LIKE 'role'");
    if ($check_role && $check_role->num_rows == 0) {$conn->query("ALTER TABLE admins ADD COLUMN role VARCHAR(20) DEFAULT 'admin' AFTER password");
        $conn->query("UPDATE admins SET role = 'super_admin' ORDER BY id ASC LIMIT 1");
    }

    $conn->query("CREATE TABLE IF NOT EXISTS admin_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        action_type VARCHAR(50) NOT NULL,
        details TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
    )");

    // ⭐ إضافة حقل تاريخ الإصدار الدقيق (تم إزالة كود التحديث التلقائي للقديم بناءً على طلبك) ⭐
    $check_rd_m =$conn->query("SHOW COLUMNS FROM movies LIKE 'release_date'");
    if ($check_rd_m && $check_rd_m->num_rows == 0) {$conn->query("ALTER TABLE movies ADD COLUMN release_date DATE DEFAULT NULL AFTER year");
    }
    $check_rd_s =$conn->query("SHOW COLUMNS FROM series LIKE 'release_date'");
    if ($check_rd_s && $check_rd_s->num_rows == 0) {$conn->query("ALTER TABLE series ADD COLUMN release_date DATE DEFAULT NULL AFTER year");
    }

} catch (Exception $e) { }

// دالة جاهزة لتسجيل نشاطات المديرين
function logAdminAction($conn, $action_type,$details) {
    if (isset($_SESSION['admin_id'])) {
        $admin_id =$_SESSION['admin_id'];
        $stmt =$conn->prepare("INSERT INTO admin_logs (admin_id, action_type, details) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iss", $admin_id, $action_type,$details);
            $stmt->execute();$stmt->close();
        }
    }
}

// --- 3. إنشاء جدول الإعلانات (Adsterra) تلقائياً ---
$conn->query("CREATE TABLE IF NOT EXISTS `ads_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `status` tinyint(1) NOT NULL DEFAULT '0',
    `api_token` varchar(255) DEFAULT NULL,
    `popunder_code` text,
    `native_banner` text,
    `social_bar` text,
    `direct_link` text,
    `bottom_banner` text,
    `top_banner` text,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$check_ads =$conn->query("SELECT id FROM ads_settings WHERE id = 1");
if ($check_ads && $check_ads->num_rows == 0) {$conn->query("INSERT INTO ads_settings (id, status) VALUES (1, 0)");
}

$ad_settings = ['status' => 0, 'popunder_code' => '', 'native_banner' => '', 'social_bar' => '', 'direct_link' => '', 'top_banner' => '', 'bottom_banner' => ''];
$ads_res =$conn->query("SELECT * FROM ads_settings WHERE id = 1 LIMIT 1");
if ($ads_res && $row =$ads_res->fetch_assoc()) {
    $ad_settings =$row;
}

// --- 4. جلب الـ IP الحقيقي والتحقق من الحظر ---
$user_ip =$_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) { 
    $user_ip =$_SERVER['HTTP_CF_CONNECTING_IP'];
} elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $user_ip =$_SERVER['HTTP_CLIENT_IP'];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {$user_ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]; }$user_ip = trim($conn->real_escape_string($user_ip));

$block_check =$conn->prepare("SELECT id FROM blocked_ips WHERE ip_address = ?");
if ($block_check) {$block_check->bind_param("s", $user_ip);$block_check->execute();
    $block_result =$block_check->get_result();
    if ($block_result->num_rows > 0) {
        die("<h1>Access Denied / تم حظر دخولك للموقع</h1>"); 
    }
    $block_check->close();
}

// --- 5. نظام الزيارات القوي (بصمة الجهاز المدمجة) ---
$is_admin = isset($_SESSION['admin_id']);

if (!$is_admin) {
    $ua =$_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $is_bot = preg_match('~(googlebot\vert{}bingbot\vert{}applebot\vert{}slurp\vert{}baiduspider\vert{}bot)~i',$ua);

    if (!$is_bot) {
        $today = date('Y-m-d');$stable_fingerprint = md5($user_ip .$ua); 
        $current_domain =$_SERVER['HTTP_HOST']; // قراءة الدومين الحالي
        
        $stmt =$conn->prepare("INSERT INTO visitor_log (ip_address, session_id, domain_name, visit_date, last_activity) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE last_activity = NOW()");
        if ($stmt) {$stmt->bind_param("ssss", $user_ip,$stable_fingerprint, $current_domain,$today);
            $stmt->execute();$stmt->close();
        }
    }
}
?>