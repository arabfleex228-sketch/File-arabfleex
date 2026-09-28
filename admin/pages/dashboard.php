<?php
// ==========================================
// 1. التحديث التلقائي للجداول (إضافة أعمدة الدول والأجهزة وتأكيد جدول التريند اللحظي)
// ==========================================
$conn->query("CREATE TABLE IF NOT EXISTS admin_tasks (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    task_text VARCHAR(255) NOT NULL,
    is_completed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS search_logs (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    keyword VARCHAR(255) NOT NULL UNIQUE,
    search_count INT(11) DEFAULT 1,
    last_searched TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("CREATE TABLE IF NOT EXISTS `views_log` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `content_type` varchar(50) NOT NULL,
    `content_id` int(11) NOT NULL,
    `viewed_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_time` (`viewed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// إضافة أعمدة الدول والأجهزة إلى جدول visitor_log بطريقة متوافقة (بدون IF NOT EXISTS)
$check_country = $conn->query("SHOW COLUMNS FROM visitor_log LIKE 'country'");
if($check_country && $check_country->num_rows == 0) {
    $conn->query("ALTER TABLE visitor_log ADD COLUMN country VARCHAR(100) DEFAULT 'غير معروف'");
}

$check_device = $conn->query("SHOW COLUMNS FROM visitor_log LIKE 'device_type'");
if($check_device && $check_device->num_rows == 0) {
    $conn->query("ALTER TABLE visitor_log ADD COLUMN device_type VARCHAR(50) DEFAULT 'Desktop'");
}

// ==========================================
// 2. معالجة طلبات قائمة المهام (إضافة، تحديد، حذف)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['task_action'])) {
    if ($_POST['task_action'] == 'add' && !empty($_POST['task_text'])) {
        $text = $conn->real_escape_string(trim($_POST['task_text']));
        $conn->query("INSERT INTO admin_tasks (task_text) VALUES ('$text')");
    } elseif ($_POST['task_action'] == 'toggle' && isset($_POST['task_id'])) {
        $id = (int)$_POST['task_id'];
        $status = (int)$_POST['status'];
        $conn->query("UPDATE admin_tasks SET is_completed = $status WHERE id = $id");
    } elseif ($_POST['task_action'] == 'delete' && isset($_POST['task_id'])) {
        $id = (int)$_POST['task_id'];
        $conn->query("DELETE FROM admin_tasks WHERE id = $id");
    }
    echo "<script>window.location.href = 'index.php?page=dashboard';</script>";
    exit;
}

// جلب المهام
$tasks_result = $conn->query("SELECT * FROM admin_tasks ORDER BY is_completed ASC, created_at DESC");

// تحديد الدومين الحالي
$current_domain = $_SERVER['HTTP_HOST'];

// جلب الكلمات البحثية (مفلترة بالدومين الحالي)
$top_searches_result = $conn->query("
    SELECT keyword, search_count 
    FROM search_logs 
    WHERE TRIM(keyword) != '' 
    AND LENGTH(TRIM(keyword)) > 2
    AND (LENGTH(keyword) - LENGTH(REPLACE(keyword, ' ', ''))) < 3 
    AND domain_name = '$current_domain'
    ORDER BY search_count DESC, last_searched DESC 
    LIMIT 10
");

// ==========================================
// 3. جلب الإحصائيات الأساسية 
// ==========================================
$movies_count = $conn->query("SELECT COUNT(id) as count FROM movies")->fetch_assoc()['count'] ?? 0;
$series_count = $conn->query("SELECT COUNT(id) as count FROM series")->fetch_assoc()['count'] ?? 0;
$requests_count = $conn->query("SELECT COUNT(id) as count FROM requests WHERE domain_name = '$current_domain'")->fetch_assoc()['count'] ?? 0;

$today = date('Y-m-d');
$unique_visits = $conn->query("SELECT COUNT(DISTINCT session_id) as count FROM visitor_log WHERE visit_date = '$today' AND domain_name = '$current_domain'")->fetch_assoc()['count'] ?? 0;
$online_now = $conn->query("SELECT COUNT(DISTINCT session_id) as count FROM visitor_log WHERE last_activity > DATE_SUB(NOW(), INTERVAL 3 MINUTE) AND domain_name = '$current_domain'")->fetch_assoc()['count'] ?? 0;

$unread_requests = $conn->query("SELECT COUNT(id) as count FROM requests WHERE is_read = 0 AND domain_name = '$current_domain'")->fetch_assoc()['count'] ?? 0;
$total_logs = $conn->query("SELECT COUNT(id) as count FROM visitor_log WHERE domain_name = '$current_domain'")->fetch_assoc()['count'] ?? 0;

// ==========================================
// الإحصائيات التراكمية (الحقيقية منذ الإنشاء) مفلترة بالدومين
// ==========================================

// 1. إجمالي الزوار
$all_time_visitors = 0;
$atv_res = $conn->query("SELECT COUNT(DISTINCT session_id) as total_visitors FROM visitor_log WHERE domain_name = '$current_domain'");
if ($atv_res && $row = $atv_res->fetch_assoc()) {
    $all_time_visitors = (int)$row['total_visitors'];
}

// 2. إجمالي المشاهدات الحقيقية (منفصلة لكل دومين)
$all_time_views = 0;
$atvw_res = $conn->query("SELECT COUNT(id) as total_views FROM views_log WHERE domain_name = '$current_domain'");
if ($atvw_res && $row = $atvw_res->fetch_assoc()) {
    $all_time_views = (int)$row['total_views'];
}
// ==========================================

$top_rated_movie_query = $conn->query("SELECT title FROM movies ORDER BY rating DESC LIMIT 1");
$top_rated_movie = ($top_rated_movie_query && $top_rated_movie_query->num_rows > 0) ? $top_rated_movie_query->fetch_assoc()['title'] : 'لا يوجد';

// ==========================================
// 4. تجهيز بيانات الرسوم البيانية الأساسية
// ==========================================
$chart_labels = [];
$visits_chart_data = [];
$movies_added_data = [];
$series_added_data = [];
$episodes_added_data = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('d/m', strtotime($date));
    
    // الزيارات مفلترة بالدومين
    $res = $conn->query("SELECT COUNT(DISTINCT session_id) as count FROM visitor_log WHERE visit_date = '$date' AND domain_name = '$current_domain'")->fetch_assoc();
    $visits_chart_data[] = $res['count'] ?? 0;

    // المحتوى مشترك بين الدومينين
    $m_query = $conn->query("SELECT COUNT(id) as count FROM movies WHERE DATE(created_at) = '$date'");
    $movies_added_data[] = $m_query ? $m_query->fetch_assoc()['count'] : 0;

    $s_query = $conn->query("SELECT COUNT(id) as count FROM series WHERE DATE(created_at) = '$date'");
    $series_added_data[] = $s_query ? $s_query->fetch_assoc()['count'] : 0;

    $e_query = $conn->query("SELECT COUNT(id) as count FROM episodes WHERE DATE(created_at) = '$date'");
    $episodes_added_data[] = $e_query ? $e_query->fetch_assoc()['count'] : 0;
}

$detailed_visits_query = $conn->query("SELECT visit_date, COUNT(DISTINCT session_id) as visits_count FROM visitor_log WHERE domain_name = '$current_domain' GROUP BY visit_date ORDER BY visit_date DESC LIMIT 30");
$visits_data = []; $max_visits = 1; 
if ($detailed_visits_query) {
    while ($row = $detailed_visits_query->fetch_assoc()) {
        $visits_data[] = $row;
        if ($row['visits_count'] > $max_visits) { $max_visits = $row['visits_count']; }
    }
}
$arabic_days = ['Sunday' => 'الأحد', 'Monday' => 'الإثنين', 'Tuesday' => 'الثلاثاء', 'Wednesday' => 'الأربعاء', 'Thursday' => 'الخميس', 'Friday' => 'الجمعة', 'Saturday' => 'السبت'];

// ==========================================
// 5. جلب بيانات الميزات الجديدة (التريند، الأجهزة، الدول) مفلترة بالدومين
// ==========================================

// أ) التريند اللحظي
$trending_query = $conn->query("
    SELECT v.content_id, v.content_type, COUNT(*) as recent_views, 
           COALESCE(m.title, s.title) as title, COALESCE(m.poster, s.poster) as poster
    FROM views_log v
    LEFT JOIN movies m ON v.content_type = 'movie' AND v.content_id = m.id
    LEFT JOIN series s ON v.content_type = 'series' AND v.content_id = s.id
    WHERE v.viewed_at >= NOW() - INTERVAL 24 HOUR AND v.domain_name = '$current_domain'
    GROUP BY v.content_id, v.content_type, title, poster
    HAVING title IS NOT NULL AND title != ''
    ORDER BY recent_views DESC
    LIMIT 6
");

// ب) إحصائيات الأجهزة
$devices_query = $conn->query("
    SELECT device_type, COUNT(*) as count 
    FROM visitor_log 
    WHERE device_type IS NOT NULL AND device_type != '' AND domain_name = '$current_domain'
    GROUP BY device_type
");
$device_counts = ['موبايل' => 0, 'كمبيوتر' => 0, 'تابلت' => 0];
if($devices_query) {
    while($row = $devices_query->fetch_assoc()){
        $type = strtolower($row['device_type']);
        if (strpos($type, 'mobile') !== false) $device_counts['موبايل'] += $row['count'];
        elseif (strpos($type, 'tablet') !== false) $device_counts['تابلت'] += $row['count'];
        else $device_counts['كمبيوتر'] += $row['count'];
    }
}
$device_labels = ['موبايل', 'كمبيوتر', 'تابلت'];
$device_data = [$device_counts['موبايل'], $device_counts['كمبيوتر'], $device_counts['تابلت']];

// ج) إحصائيات الدول
$countries_query = $conn->query("
    SELECT country, COUNT(*) as count 
    FROM visitor_log 
    WHERE country IS NOT NULL AND country != 'غير معروف' AND country != '' AND domain_name = '$current_domain'
    GROUP BY country 
    ORDER BY count DESC 
    LIMIT 5
");
$country_labels = []; $country_data = [];
if($countries_query) {
    while($row = $countries_query->fetch_assoc()){
        $country_labels[] = $row['country'];
        $country_data[] = $row['count'];
    }
}
?>

<style>
    .stat-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden; background-color: var(--bg-card); backdrop-filter: blur(10px); }
    .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.4); border-color: var(--brand-gold); }
    .stat-card::after { content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: linear-gradient(45deg, transparent, rgba(245, 197, 24, 0.05), transparent); transform: rotate(45deg); transition: 0.5s; }
    .stat-card:hover::after { left: 100%; }
    
    .live-indicator { display: inline-flex; align-items: center; gap: 8px; background: rgba(16, 185, 129, 0.1); padding: 5px 12px; border-radius: 20px; border: 1px solid rgba(16, 185, 129, 0.2); }
    .pulse-dot { width: 8px; height: 8px; background-color: #10b981; border-radius: 50%; position: relative; }
    .pulse-dot::before { content: ''; position: absolute; width: 100%; height: 100%; background-color: #10b981; border-radius: 50%; animation: pulse-ring 1.5s cubic-bezier(0.455, 0.03, 0.515, 0.955) infinite; }
    @keyframes pulse-ring { 0% { transform: scale(0.33); opacity: 1; } 80%, 100% { transform: scale(3); opacity: 0; } }

    .task-item { transition: all 0.2s; border: 1px solid transparent; }
    .task-item:hover { background-color: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.1); }
    .task-checkbox { width: 18px; height: 18px; accent-color: var(--brand-gold); cursor: pointer; }
    .task-text.completed { text-decoration: line-through; color: #64748b; opacity: 0.7; }
    .task-add-input { background: rgba(0,0,0,0.4); border: 1px solid var(--border-color); color: white; }
    .task-add-input:focus { border-color: var(--brand-gold); outline: none; box-shadow: 0 0 0 2px rgba(245, 197, 24, 0.2); }

    .panel-box { background-color: var(--bg-card); padding: 1.5rem; border-radius: 1rem; border: 1px solid var(--border-color); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.3); }

    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #333; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #555; }

    .trend-badge { background: linear-gradient(45deg, #ef4444, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-weight: 900; }
    
    @media (max-width: 768px) {
        .section-header { flex-direction: column; gap: 15px; align-items: center !important; text-align: center; }
        .canvas-container { max-height: 250px !important; }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="section-header flex justify-between items-center mb-6">
    <h1 class="section-title">لوحة التحكم الرئيسية</h1>
    <div class="flex items-center gap-4">
        <a href="index.php?page=general_radar" class="btn bg-cyan-900/40 text-cyan-400 border border-cyan-500/30 hover:bg-cyan-500 hover:text-white transition-all btn-sm shadow-lg">
            <i class="fas fa-radar fa-spin-pulse"></i> الرادار الشامل
        </a>
        
        <div class="live-indicator" title="متصلون الآن يتصفحون الموقع أو يشاهدون الحلقات">
            <div class="pulse-dot"></div>
            <span class="text-xs font-bold text-green-400">نشط الآن: <?php echo $online_now; ?></span>
        </div>
    </div>
</div>

<?php if ($unread_requests > 0): ?>
    <div class="mb-8 p-4 bg-amber-500/10 border-r-4 border-amber-500 rounded-lg flex items-center justify-between animate-pulse shadow-lg">
        <div class="flex items-center gap-3">
            <i class="fas fa-bell text-amber-500 text-xl"></i>
            <span class="font-bold text-amber-200">يوجد لديك (<?php echo $unread_requests; ?>) طلبات جديدة لم تقرأ.</span>
        </div>
        <a href="index.php?page=requests" class="text-xs bg-amber-500 text-black px-4 py-2 rounded-lg font-bold hover:bg-amber-400 transition-colors">عرض الطلبات</a>
    </div>
<?php endif; ?>

<!-- 1. البطاقات الإحصائية -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="stat-card p-6 rounded-lg border border-border-color">
        <i class="fas fa-users mb-3 block text-3xl text-brand-gold"></i>
        <div>
            <div class="text-3xl font-black"><?php echo number_format($unique_visits); ?></div>
            <div class="text-text-secondary text-sm font-bold mt-1">زيارات اليوم (فريدة)</div>
        </div>
    </div>
    <div class="stat-card p-6 rounded-lg border border-border-color">
        <i class="fas fa-film mb-3 block text-3xl text-blue-400"></i>
        <div>
            <div class="text-3xl font-black"><?php echo number_format($movies_count); ?></div>
            <div class="text-text-secondary text-sm font-bold mt-1">إجمالي الأفلام</div>
        </div>
    </div>
    <div class="stat-card p-6 rounded-lg border border-border-color">
        <i class="fas fa-tv mb-3 block text-3xl text-purple-400"></i>
        <div>
            <div class="text-3xl font-black"><?php echo number_format($series_count); ?></div>
            <div class="text-text-secondary text-sm font-bold mt-1">إجمالي المسلسلات</div>
        </div>
    </div>
    <div class="stat-card p-6 rounded-lg border border-border-color">
        <i class="fas fa-envelope-open-text mb-3 block text-3xl text-red-400"></i>
        <div>
            <div class="text-3xl font-black"><?php echo number_format($requests_count); ?></div>
            <div class="text-text-secondary text-sm font-bold mt-1">إجمالي الطلبات</div>
        </div>
    </div>
</div>

<!-- 2. الميزات الجديدة: التريند اللحظي، الأجهزة، والدول -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
    
    <!-- قائمة التريند اللحظي -->
    <div class="panel-box flex flex-col h-full">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-black text-white"><i class="fas fa-fire text-red-500 ml-2 animate-pulse"></i>تريند آخر 24 ساعة</h2>
            <span class="text-xs bg-red-500/20 text-red-400 border border-red-500/30 px-2 py-1 rounded font-bold">الآن</span>
        </div>
        
        <div class="overflow-y-auto pr-2 custom-scrollbar flex-1" style="max-height: 280px;">
            <?php if ($trending_query && $trending_query->num_rows > 0): ?>
                <ul class="space-y-3">
                    <?php 
                    $rank = 1; 
                    while($trend = $trending_query->fetch_assoc()): 
                        $poster_src = $trend['poster'];
                        if (!empty($poster_src) && strpos($poster_src, 'http') !== 0 && strpos($poster_src, '/') !== 0) {
                            $poster_src = '../' . $poster_src;
                        }
                    ?>
                        <li class="flex items-center gap-3 p-2 bg-black/30 rounded-xl hover:bg-black/50 transition-colors border border-transparent hover:border-gray-700 group">
                            <span class="text-xl font-black <?php echo $rank <= 3 ? 'trend-badge' : 'text-gray-500'; ?> w-6 text-center shrink-0">#<?php echo $rank; ?></span>
                            <img src="<?php echo htmlspecialchars($poster_src); ?>" onerror="this.src='../favicon.png'" class="w-10 h-10 rounded object-cover shadow-lg border border-gray-700">
                            <div class="flex-1 overflow-hidden">
                                <h4 class="text-sm font-bold text-gray-200 truncate group-hover:text-brand-gold transition-colors" title="<?php echo htmlspecialchars($trend['title']); ?>"><?php echo htmlspecialchars($trend['title']); ?></h4>
                                <span class="text-[10px] text-gray-400 bg-gray-800 px-1.5 py-0.5 rounded mt-1 inline-block"><?php echo $trend['content_type'] == 'movie' ? 'فيلم' : 'مسلسل'; ?></span>
                            </div>
                            <div class="text-center shrink-0 ml-2">
                                <span class="block text-xs font-black text-brand-gold"><?php echo number_format($trend['recent_views']); ?></span>
                                <span class="text-[9px] text-gray-500">مشاهدة</span>
                            </div>
                        </li>
                    <?php $rank++; endwhile; ?>
                </ul>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500 text-sm">لا يوجد نشاط مشاهدة مسجل في آخر 24 ساعة.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- رسم بياني: الأجهزة -->
    <div class="panel-box">
        <h2 class="text-lg font-black mb-4 text-white"><i class="fas fa-mobile-alt text-blue-400 ml-2"></i>أنواع الأجهزة المستخدمة</h2>
        <div class="canvas-container flex justify-center items-center" style="height: 220px;">
            <canvas id="devicesChart"></canvas>
        </div>
    </div>

    <!-- رسم بياني: أكثر الدول متابعة -->
    <div class="panel-box">
        <h2 class="text-lg font-black mb-4 text-white"><i class="fas fa-globe-africa text-green-400 ml-2"></i>أكثر الدول متابعة</h2>
        <div class="canvas-container" style="height: 220px;">
            <?php if(!empty($country_data)): ?>
                <canvas id="countriesChart"></canvas>
            <?php else: ?>
                <div class="flex h-full items-center justify-center text-gray-500 text-sm font-bold">لم يتم تسجيل دول الزوار بعد.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 3. الرسوم البيانية القديمة -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
    <div class="panel-box">
        <h2 class="text-lg font-black mb-4 text-white"><i class="fas fa-chart-line text-brand-gold ml-2"></i>نشاط الزيارات الأسبوعي</h2>
        <div class="canvas-container" style="height: 250px;">
            <canvas id="visitsChart"></canvas>
        </div>
    </div>

    <div class="panel-box">
        <h2 class="text-lg font-black mb-4 text-white"><i class="fas fa-cloud-upload-alt text-brand-gold ml-2"></i>معدل إضافة المحتوى الأسبوعي</h2>
        <div class="canvas-container" style="height: 250px;">
            <canvas id="contentChart"></canvas>
        </div>
    </div>
</div>

<!-- 4. قائمة المهام ومركز البيانات -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
    
    <!-- قائمة المهام -->
    <div class="lg:col-span-2 panel-box flex flex-col h-full">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-black text-white"><i class="fas fa-tasks text-pink-400 ml-2"></i>قائمة مهام الإدارة</h2>
            <span class="text-xs bg-gray-800 text-gray-400 px-2 py-1 rounded">خاص بك فقط</span>
        </div>
        
        <form method="POST" action="index.php?page=dashboard" class="flex gap-2 mb-4">
            <input type="hidden" name="task_action" value="add">
            <input type="text" name="task_text" required placeholder="ما الذي تريد تذكره لاحقاً؟" class="task-add-input flex-1 rounded-lg px-4 py-2 text-sm">
            <button type="submit" class="btn btn-primary !py-2"><i class="fas fa-plus"></i> حفظ</button>
        </form>

        <div class="overflow-y-auto pr-2 custom-scrollbar" style="max-height: 220px;">
            <?php if ($tasks_result && $tasks_result->num_rows > 0): ?>
                <ul class="space-y-2">
                    <?php while($task = $tasks_result->fetch_assoc()): ?>
                        <li class="task-item flex items-center justify-between p-3 bg-black/30 rounded-lg">
                            <form method="POST" action="index.php?page=dashboard" class="flex items-center gap-3 flex-1">
                                <input type="hidden" name="task_action" value="toggle">
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                <input type="hidden" name="status" value="<?php echo $task['is_completed'] ? '0' : '1'; ?>">
                                <input type="checkbox" class="task-checkbox" onchange="this.form.submit()" <?php echo $task['is_completed'] ? 'checked' : ''; ?>>
                                <span class="task-text font-semibold text-sm <?php echo $task['is_completed'] ? 'completed' : 'text-white'; ?>">
                                    <?php echo htmlspecialchars($task['task_text']); ?>
                                </span>
                            </form>
                            
                            <form method="POST" action="index.php?page=dashboard">
                                <input type="hidden" name="task_action" value="delete">
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                <button type="submit" class="text-red-500 hover:text-red-400 p-2" title="حذف المهمة">
                                    <i class="fas fa-times"></i>
                                </button>
                            </form>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500 text-sm">
                    <i class="fas fa-clipboard-check text-3xl mb-2 opacity-50 block"></i>
                    لا توجد مهام حالياً، أنت مسيطر على الوضع!
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- مركز البيانات -->
    <div class="panel-box h-full flex flex-col justify-between">
        <h2 class="text-lg font-black mb-4 text-white"><i class="fas fa-info-circle text-blue-400 ml-2"></i>مركز البيانات</h2>
        <div class="space-y-3">
            <div class="p-3 bg-black/40 rounded-lg border border-border-color flex justify-between items-center group">
                <span class="text-sm font-bold text-gray-400">الطلبات الجديدة:</span>
                <span class="font-black text-lg <?php echo $unread_requests > 0 ? 'text-red-500' : 'text-green-500'; ?>"><?php echo $unread_requests; ?></span>
            </div>
            
            <div class="p-3 bg-black/40 rounded-lg border border-border-color flex justify-between items-center">
                <span class="text-sm font-bold text-gray-400">سجلات الزوار الحالية:</span>
                <span class="font-black text-lg text-blue-400"><?php echo number_format($total_logs); ?></span>
            </div>
            
            <div class="p-3 bg-gradient-to-r from-purple-900/30 to-black rounded-lg border border-purple-500/30 flex justify-between items-center shadow-lg transform transition hover:scale-[1.02]">
                <span class="text-[13px] font-bold text-gray-300"><i class="fas fa-globe ml-1 text-purple-400"></i> إجمالي الزوار (منذ الإنشاء):</span>
                <span class="font-black text-xl text-purple-400 drop-shadow-[0_0_5px_rgba(168,85,247,0.5)]"><?php echo number_format($all_time_visitors); ?></span>
            </div>
            
            <div class="p-3 bg-gradient-to-r from-pink-900/30 to-black rounded-lg border border-pink-500/30 flex justify-between items-center shadow-lg transform transition hover:scale-[1.02]">
                <span class="text-[13px] font-bold text-gray-300"><i class="fas fa-eye ml-1 text-pink-400"></i> إجمالي المشاهدات (منذ الإنشاء):</span>
                <span class="font-black text-xl text-pink-400 drop-shadow-[0_0_5px_rgba(236,72,153,0.5)]"><?php echo number_format($all_time_views); ?></span>
            </div>
            
            <div class="p-3 bg-black/40 rounded-lg border border-border-color">
                <span class="text-xs font-bold text-gray-400 block mb-1">الفيلم الأعلى تقييماً:</span>
                <span class="font-bold text-brand-gold truncate block text-sm"><?php echo htmlspecialchars($top_rated_movie); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- 5. جدول الزيارات ورادار الكلمات البحثية -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
    
    <!-- جدول تفاصيل الزيارات -->
    <div class="lg:col-span-2 panel-box h-full flex flex-col">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-lg font-black text-white"><i class="fas fa-calendar-alt text-green-400 ml-2"></i>تفاصيل الزيارات (آخر 30 يوم)</h2>
        </div>
        
        <div class="table-responsive flex-1 overflow-y-auto pr-2 custom-scrollbar" style="max-height: 350px;">
            <table class="content-table w-full">
                <thead class="sticky top-0 z-10 bg-[var(--bg-card)]">
                    <tr>
                        <th width="20%">التاريخ</th>
                        <th width="15%">اليوم</th>
                        <th width="20%">عدد الزيارات</th>
                        <th width="45%">مؤشر الكثافة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($visits_data)): ?>
                        <tr><td colspan="4" class="text-center py-8 text-gray-500">لا توجد بيانات متاحة.</td></tr>
                    <?php else: ?>
                        <?php foreach ($visits_data as $v): 
                            $date_obj = new DateTime($v['visit_date']);
                            $day_name = $arabic_days[$date_obj->format('l')];
                            $is_today = ($v['visit_date'] == $today);
                            
                            $percent = ($max_visits > 0) ? ($v['visits_count'] / $max_visits) * 100 : 0;
                            if ($percent >= 80) $bar_color = 'bg-green-500 shadow-[0_0_10px_rgba(34,197,94,0.4)]';
                            elseif ($percent >= 40) $bar_color = 'bg-blue-500';
                            else $bar_color = 'bg-purple-500';
                        ?>
                        <tr class="<?php echo $is_today ? 'bg-brand-gold/5' : ''; ?>">
                            <td class="font-bold text-gray-300 text-sm">
                                <?php echo $date_obj->format('Y/m/d'); ?>
                                <?php if ($is_today): ?>
                                    <span class="ml-2 text-[10px] bg-brand-gold text-black px-2 py-0.5 rounded-md">اليوم</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-gray-400 text-xs font-bold"><?php echo $day_name; ?></td>
                            <td class="font-black text-lg <?php echo $is_today ? 'text-brand-gold' : 'text-white'; ?>">
                                <?php echo number_format($v['visits_count']); ?>
                            </td>
                            <td>
                                <div class="flex items-center gap-3 w-full">
                                    <div class="w-full bg-black/50 rounded-full h-2 overflow-hidden flex-1 border border-border-color">
                                        <div class="h-full rounded-full <?php echo $bar_color; ?>" style="width: <?php echo $percent; ?>%"></div>
                                    </div>
                                    <span class="text-[11px] font-bold text-gray-400 w-8 text-left"><?php echo round($percent); ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- رادار الكلمات البحثية -->
    <div class="lg:col-span-1 panel-box h-full flex flex-col">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-lg font-black text-white"><i class="fas fa-search text-brand-gold ml-2"></i>رادار الكلمات البحثية</h2>
            <span class="text-[10px] bg-brand-gold/20 text-brand-gold px-2 py-1 rounded font-bold border border-brand-gold/30">التوب 10</span>
        </div>
        
        <div class="flex-1 overflow-y-auto pr-2 custom-scrollbar" style="max-height: 350px;">
            <?php if ($top_searches_result && $top_searches_result->num_rows > 0): ?>
                <ul class="space-y-3">
                    <?php 
                    $search_rank = 1;
                    while($s = $top_searches_result->fetch_assoc()): 
                        if($search_rank == 1) $badge_color = 'bg-red-500 text-white shadow-[0_0_10px_rgba(239,68,68,0.5)]';
                        elseif($search_rank == 2) $badge_color = 'bg-gray-300 text-black shadow-[0_0_10px_rgba(209,213,219,0.5)]';
                        elseif($search_rank == 3) $badge_color = 'bg-amber-700 text-white shadow-[0_0_10px_rgba(180,83,9,0.5)]';
                        else $badge_color = 'bg-white/10 text-gray-400 border border-white/10';
                    ?>
                        <li class="flex items-center justify-between p-3 bg-[#131313] border border-[#2a2a2a] rounded-xl hover:border-brand-gold hover:shadow-[0_5px_15px_rgba(218,165,32,0.1)] transition-all group">
                            <div class="flex items-center gap-3">
                                <span class="flex items-center justify-center w-7 h-7 rounded-lg text-sm font-black <?php echo $badge_color; ?>"><?php echo $search_rank; ?></span>
                                <span class="font-bold text-sm text-gray-200 group-hover:text-brand-gold transition-colors truncate max-w-[120px]" title="<?php echo htmlspecialchars($s['keyword']); ?>"><?php echo htmlspecialchars($s['keyword']); ?></span>
                            </div>
                            <div class="text-xs font-bold bg-blue-500/10 border border-blue-500/20 text-blue-400 px-2 py-1.5 rounded-md flex items-center gap-1 shrink-0">
                                <i class="fas fa-search text-[10px]"></i> <?php echo number_format($s['search_count']); ?>
                            </div>
                        </li>
                    <?php $search_rank++; endwhile; ?>
                </ul>
            <?php else: ?>
                <div class="text-center py-12 text-gray-500 flex flex-col items-center justify-center h-full">
                    <i class="fas fa-search-minus text-4xl mb-3 opacity-20 block"></i>
                    <span class="text-sm font-bold">لم يتم تسجيل أي عمليات بحث مطابقة حتى الآن.</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
Chart.defaults.color = '#94a3b8';
Chart.defaults.font.family = "'Cairo', sans-serif";

// --- شارت الزيارات ---
const visitsCtx = document.getElementById('visitsChart').getContext('2d');
new Chart(visitsCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($chart_labels); ?>,
        datasets: [{
            label: 'الزيارات الفريدة',
            data: <?php echo json_encode($visits_chart_data); ?>,
            borderColor: '#f5c518',
            backgroundColor: 'rgba(245, 197, 24, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#f5c518',
            pointRadius: 4,
            pointHoverRadius: 6
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' } },
            x: { grid: { display: false } }
        }
    }
});

// --- شارت المحتوى ---
const contentCtx = document.getElementById('contentChart').getContext('2d');
new Chart(contentCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($chart_labels); ?>,
        datasets: [
            {
                label: 'أفلام مضافة',
                data: <?php echo json_encode($movies_added_data); ?>,
                backgroundColor: '#3b82f6',
                borderRadius: 4
            },
            {
                label: 'مسلسلات مضافة',
                data: <?php echo json_encode($series_added_data); ?>,
                backgroundColor: '#f59e0b',
                borderRadius: 4
            },
            {
                label: 'حلقات مضافة',
                data: <?php echo json_encode($episodes_added_data); ?>,
                backgroundColor: '#ec4899',
                borderRadius: 4
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { 
            legend: { display: true, position: 'top', labels: { color: '#f8fafc', font: { family: 'Cairo', weight: 'bold' } } } 
        },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { stepSize: 1 } },
            x: { grid: { display: false }, stacked: false }
        }
    }
});

// --- شارت الأجهزة ---
if (document.getElementById('devicesChart')) {
    const devicesCtx = document.getElementById('devicesChart').getContext('2d');
    new Chart(devicesCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($device_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($device_data); ?>,
                backgroundColor: ['#3b82f6', '#10b981', '#f5c518'],
                borderColor: 'transparent',
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { position: 'bottom', labels: { color: '#f8fafc', font: { family: 'Cairo', weight: 'bold' } } }
            }
        }
    });
}

// --- شارت الدول ---
if (document.getElementById('countriesChart')) {
    const countriesCtx = document.getElementById('countriesChart').getContext('2d');
    new Chart(countriesCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($country_labels); ?>,
            datasets: [{
                label: 'عدد الزوار',
                data: <?php echo json_encode($country_data); ?>,
                backgroundColor: 'rgba(16, 185, 129, 0.8)',
                borderColor: '#10b981',
                borderWidth: 1,
                borderRadius: 6
            }]
        },
        options: {
            indexAxis: 'y', 
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { precision: 0 } },
                y: { grid: { display: false }, ticks: { font: { family: 'Cairo', weight: 'bold', size: 11 } } }
            }
        }
    });
}
</script>