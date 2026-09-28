<?php
require_once 'db_config.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// جلب صلاحية المدير الحالي وحفظها في الجلسة (لإخفاء/إظهار القوائم)
if (!isset($_SESSION['admin_role'])) {
    $admin_id = $_SESSION['admin_id'];
    $role_query = $conn->query("SELECT role FROM admins WHERE id = $admin_id LIMIT 1");
    if ($role_query && $role_query->num_rows > 0) {
        $_SESSION['admin_role'] = $role_query->fetch_assoc()['role'];
    } else {
        $_SESSION['admin_role'] = 'admin'; // افتراضي
    }
}

// --- معالج النسخ الاحتياطي ---
if (isset($_GET['action']) && $_GET['action'] == 'download_backup' && $_SESSION['admin_role'] === 'super_admin') {
    if(function_exists('logAdminAction')) { 
        logAdminAction($conn, 'تصدير بيانات', 'قام بتحميل نسخة احتياطية لقاعدة البيانات'); 
    }
    
    $tables = array();
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) { $tables[] = $row[0]; }
    
    $return = "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    foreach ($tables as $table) {
        $result = $conn->query("SELECT * FROM $table");
        $num_fields = $result->field_count;
        $row2 = $conn->query("SHOW CREATE TABLE $table")->fetch_row();
        $return .= "\n\n" . $row2[1] . ";\n\n";
        
        while ($row = $result->fetch_row()) {
            $return .= "INSERT INTO $table VALUES(";
            for ($j = 0; $j < $num_fields; $j++) {
                $row[$j] = addslashes($row[$j] ?? '');
                $row[$j] = str_replace("\n", "\\n", $row[$j]);
                if (isset($row[$j])) { $return .= '"' . $row[$j] . '"'; } else { $return .= '""'; }
                if ($j < ($num_fields - 1)) { $return .= ','; }
            }
            $return .= ");\n";
        }
        $return .= "\n\n\n";
    }
    $return .= "SET FOREIGN_KEY_CHECKS=1;";
    
    $filename = 'arabfleex_backup_' . date('Y-m-d_H-i') . '.sql';
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename=' . $filename);
    echo $return;
    exit();
}

// تحديد الصفحة الحالية
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
// تحديد القسم الحالي (للمسلسلات والبرامج والأفلام)
$current_category = isset($_GET['category']) ? $_GET['category'] : '';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم | <?php echo $_SERVER['HTTP_HOST']; ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        /* --- الألوان السينمائية --- */
        :root {
            --bg-main: #050505;           
            --bg-sidebar: rgba(15, 23, 42, 0.75); 
            --bg-card: rgba(30, 41, 59, 0.7);    
            --brand-gold: #f5c518;        
            --brand-gold-hover: #ffdd4d;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --border-color: rgba(255, 255, 255, 0.08); 
            --accent-glow: rgba(245, 197, 24, 0.15);
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: radial-gradient(circle at top right, #0f172a 0%, var(--bg-main) 100%);
            background-attachment: fixed;
            color: var(--text-primary);
            min-height: 100vh;
        }

        .golden-text { color: var(--brand-gold); text-shadow: 0 0 15px rgba(245, 197, 24, 0.4); }

        /* --- تصميم القائمة الجانبية --- */
        .sidebar-glass {
            background-color: var(--bg-sidebar);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-left: 1px solid var(--border-color);
            box-shadow: -5px 0 30px rgba(0,0,0,0.6);
        }

        .nav-group-title {
            color: #64748b; font-size: 0.8rem; font-weight: 900;
            padding: 0 1.2rem; margin-top: 1.5rem; margin-bottom: 0.5rem;
            letter-spacing: 0.05em; text-transform: uppercase;
        }

        .nav-link {
            display: flex; align-items: center; padding: 0.85rem 1.2rem;
            margin: 0.2rem 0.8rem; border-radius: 0.75rem; font-weight: 600;
            color: var(--text-secondary); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative; overflow: hidden; z-index: 1;
        }

        .nav-link::before {
            content: ''; position: absolute; top: 0; right: 0; bottom: 0; left: 0;
            background: linear-gradient(90deg, rgba(245, 197, 24, 0.1) 0%, transparent 100%);
            z-index: -1; opacity: 0; transition: opacity 0.3s ease; border-radius: 0.75rem;
        }

        .nav-link::after {
            content: ''; position: absolute; top: 15%; right: 0; bottom: 15%;
            width: 4px; background-color: var(--brand-gold); border-radius: 5px;
            transform: scaleY(0); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .nav-link:hover { color: var(--text-primary); transform: translateX(-4px); }
        .nav-link:hover::before { opacity: 1; }
        .nav-link:hover::after { transform: scaleY(1); }

        .nav-link.active {
            color: #000; background-color: var(--brand-gold);
            box-shadow: 0 4px 20px var(--accent-glow); font-weight: 800;
        }
        .nav-link.active i { color: #000 !important; }
        .nav-link.active::before, .nav-link.active::after { display: none; }

        .logout-link { color: #ef4444; margin-top: auto; }
        .logout-link:hover { background-color: rgba(239, 68, 68, 0.1); color: #fca5a5; }
        .logout-link::before { background: linear-gradient(90deg, rgba(239, 68, 68, 0.1) 0%, transparent 100%); }
        .logout-link::after { background-color: #ef4444; }

        /* --- الجداول وتنسيقات عرض المحتوى --- */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-bottom: 1rem;
            border-radius: 1rem;
            width: 100%;
        }

        .content-table {
            width: 100%;
            min-width: 800px;
            border-collapse: separate;
            border-spacing: 0;
            background-color: var(--bg-card);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border-color);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);
            border-radius: 1rem;
            overflow: hidden;
        }
        
        .content-table th, .content-table td {
            padding: 1.25rem 1rem;
            text-align: right;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }
        
        .content-table thead th {
            background-color: rgba(0,0,0,0.6);
            font-weight: 900;
            color: var(--brand-gold);
            font-size: 0.95rem;
            white-space: nowrap;
        }
        
        .content-table tbody tr { transition: background-color 0.2s ease; }
        .content-table tbody tr:hover { background-color: rgba(255, 255, 255, 0.08); }

        .table-poster {
            width: 60px !important;
            height: 85px !important;
            object-fit: cover !important;
            border-radius: 0.5rem;
            box-shadow: 0 4px 10px rgba(0,0,0,0.5);
            border: 1px solid rgba(255,255,255,0.1);
            display: block;
        }

        .btn {
            padding: 0.6rem 1.25rem; border-radius: 0.5rem; font-weight: 700;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer;
            border: none; display: inline-flex; align-items: center; gap: 0.5rem;
        }
        .btn-primary { background-color: var(--brand-gold); color: #0f172a; }
        .btn-primary:hover { background-color: #fcd34d; transform: translateY(-2px); box-shadow: 0 10px 15px -3px var(--accent-glow); }
        .btn-secondary { background-color: #334155; color: var(--text-primary); border: 1px solid var(--border-color); }
        .btn-secondary:hover { background-color: #475569; }
        .btn-danger { background-color: rgba(239, 68, 68, 0.8); color: white; }
        .btn-danger:hover { background-color: #ef4444; box-shadow: 0 10px 15px -3px rgba(239,68,68,0.3);}
        .btn-sm { padding: 0.4rem 0.8rem; font-size: 0.8rem; }

        .form-input, .form-textarea, .form-select {
            width: 100%; background-color: rgba(0, 0, 0, 0.4); border: 1px solid var(--border-color);
            border-radius: 0.75rem; padding: 0.85rem 1.15rem; color: var(--text-primary);
            transition: all 0.3s ease;
        }
        .form-input:focus, .form-textarea:focus, .form-select:focus {
            outline: none; border-color: var(--brand-gold); box-shadow: 0 0 0 4px var(--accent-glow); background-color: rgba(0, 0, 0, 0.6);
        }

        .form-label { font-weight: 700; margin-bottom: 0.6rem; display: block; color: var(--text-secondary); font-size: 0.9rem; }

        .section-header {
            display: flex; flex-direction: column; gap: 1rem; margin-bottom: 2rem;
            padding-bottom: 1.25rem; border-bottom: 1px solid var(--border-color);
        }
        @media (min-width: 768px) {
            .section-header { flex-direction: row; justify-content: space-between; align-items: center; }
        }
        .section-title { font-size: 1.85rem; font-weight: 900; letter-spacing: -0.025em; text-shadow: 0 2px 4px rgba(0,0,0,0.5);}

        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); }
        ::-webkit-scrollbar-thumb { background: #475569; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--brand-gold); }

        .sidebar-scroll::-webkit-scrollbar { width: 4px; }

        #mobile-overlay { display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.8); z-index: 40; backdrop-filter: blur(5px); }
        
        .nav-link i { transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .nav-link:hover i { transform: scale(1.15) rotate(-5deg); }

        /* --- تنسيقات إضافية مسترجعة للمكونات --- */
        .ad-card { background-color: rgba(26, 29, 36, 0.8); backdrop-filter: blur(10px); border: 1px solid var(--border-color); border-radius: 1rem; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5); }
        .input-dark { background-color: rgba(11, 13, 16, 0.6); border: 1px solid #374151; color: #e5e7eb; transition: all 0.3s ease; }
        .input-dark:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2); }
        .label-title { color: #9ca3af; font-size: 0.9rem; font-weight: 700; margin-bottom: 0.5rem; display: block; }
        
        /* Toggle Switch CSS */
        .toggle-checkbox:checked { right: 0; border-color: #10b981; }
        .toggle-checkbox:checked + .toggle-label { background-color: #10b981; }
        
        .glass-panel {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 1rem;
        }
    </style>
</head>
<body class="text-text-primary">

<!-- هيدر الموبايل -->
<div class="lg:hidden flex items-center justify-between p-4 sidebar-glass sticky top-0 z-50 border-b border-border-color shadow-lg">
    <div>
        <a href="index.php" class="font-black text-2xl tracking-wider">
            <span class="golden-text">عرب</span> <span class="text-white">فليكس</span>
        </a>
        <p class="text-xs text-gray-400 mt-0.5">الدومين النشط: <span class="text-[#DAA520] font-bold"><?php echo $_SERVER['HTTP_HOST']; ?></span></p>
    </div>
    <button id="toggleSidebar" class="text-brand-gold text-2xl focus:outline-none p-2 bg-white/5 rounded-lg border border-white/10 shadow-sm">
        <i class="fas fa-bars"></i>
    </button>
</div>

<div id="mobile-overlay" onclick="toggleSidebarMenu()"></div>

<div class="flex min-h-screen relative overflow-hidden">
    
    <!-- القائمة الجانبية -->
    <aside id="sidebar" class="sidebar-glass fixed inset-y-0 right-0 z-50 w-72 flex flex-col pt-6 pb-0 shrink-0 transform translate-x-full lg:translate-x-0 lg:static lg:inset-0 h-screen">
        
        <div class="text-center mb-8 hidden lg:block px-4 relative">
            <a href="index.php" class="font-black text-3xl tracking-wider block hover:scale-105 transition-transform">
                <span class="golden-text">عرب</span> <span class="text-white">فليكس</span>
            </a>
            <div class="text-[10px] text-brand-gold mt-2 font-black tracking-widest uppercase opacity-90 bg-white/5 inline-block px-3 py-1 rounded-full border border-white/10">
                <?php echo ($_SESSION['admin_role'] === 'super_admin') ? '<i class="fas fa-chess-king mr-1"></i> Director\'s Board' : '<i class="fas fa-shield-alt mr-1"></i> Moderator Board'; ?>
            </div>
            <p class="text-xs text-gray-400 mt-3 font-semibold">مرحباً: <span class="text-white font-bold"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span></p>
        </div>
        
        <button class="lg:hidden absolute top-4 left-4 text-text-secondary hover:text-white bg-white/5 w-8 h-8 rounded-full flex items-center justify-center border border-white/10" onclick="toggleSidebarMenu()">
            <i class="fas fa-times text-sm"></i>
        </button>

        <nav class="flex-1 overflow-y-auto sidebar-scroll pb-4 px-2">
            
            <div class="nav-group-title">الرئيسية</div>
            <a href="index.php?page=dashboard" class="nav-link <?php if($page == 'dashboard') echo 'active'; ?>">
                <i class="fas fa-layer-group ml-3 w-5 text-center"></i> نظرة عامة
            </a>
            
            <div class="nav-group-title">إدارة المحتوى</div>
            
            <!-- إضافة الأفلام -->
            <a href="index.php?page=movies&category=movie" class="nav-link <?php if($page == 'movies' && ($current_category == 'movie' || empty($current_category))) echo 'active'; ?>">
                <i class="fas fa-film ml-3 w-5 text-center text-blue-300"></i> الأفلام العربية
            </a>
            <a href="index.php?page=movies&category=foreign_movie" class="nav-link <?php if($page == 'movies' && $current_category == 'foreign_movie') echo 'active'; ?>">
                <i class="fas fa-film ml-3 w-5 text-center text-emerald-300"></i> الأفلام الأجنبية
            </a>
            <a href="index.php?page=movies&category=indian_movie" class="nav-link <?php if($page == 'movies' && $current_category == 'indian_movie') echo 'active'; ?>">
                <i class="fas fa-film ml-3 w-5 text-center text-orange-400"></i> الأفلام الهندية
            </a>
            
            <!-- الأقسام المدمجة للمسلسلات -->
            <a href="index.php?page=series&category=series" class="nav-link <?php if($page == 'series' && $current_category == 'series') echo 'active'; ?>">
                <i class="fas fa-tv ml-3 w-5 text-center text-blue-400"></i> المسلسلات العربية
            </a>
            <a href="index.php?page=series&category=foreign" class="nav-link <?php if($page == 'series' && $current_category == 'foreign') echo 'active'; ?>">
                <i class="fas fa-globe-americas ml-3 w-5 text-center text-teal-400"></i> المسلسلات الأجنبية
            </a>
            <a href="index.php?page=series&category=turkish" class="nav-link <?php if($page == 'series' && $current_category == 'turkish') echo 'active'; ?>">
                <i class="fas fa-star-and-crescent ml-3 w-5 text-center text-red-400"></i> المسلسلات التركية
            </a>
            <a href="index.php?page=series&category=indian" class="nav-link <?php if($page == 'series' && $current_category == 'indian') echo 'active'; ?>">
                <i class="fas fa-tv ml-3 w-5 text-center text-orange-500"></i> المسلسلات الهندية
            </a>
            <a href="index.php?page=series&category=tv_show" class="nav-link <?php if($page == 'series' && $current_category == 'tv_show') echo 'active'; ?>">
                <i class="fas fa-microphone-alt ml-3 w-5 text-center text-purple-400"></i> البرامج التلفزيونية
            </a>
            <a href="index.php?page=series&category=wrestling" class="nav-link <?php if($page == 'series' && $current_category == 'wrestling') echo 'active'; ?>">
                <i class="fas fa-hand-rock ml-3 w-5 text-center text-orange-400"></i> المصارعة الحرة
            </a>
            
            <a href="index.php?page=add_movies" class="nav-link <?php if($page == 'add_movies') echo 'active'; ?>">
                <i class="fas fa-cloud-upload-alt ml-3 w-5 text-center text-emerald-400"></i> جلب أفلام (سيرفرات)
            </a>
            
            <!-- زر الإضافة السريعة للأفلام (الجماعي الذكي) -->
            <a href="index.php?page=movies_bulk_add" class="nav-link <?php if($page == 'movies_bulk_add') echo 'active'; ?>">
                <i class="fas fa-bolt ml-3 w-5 text-center text-[#DAA520]" style="filter: drop-shadow(0 0 5px rgba(218,165,32,0.5));"></i> الإضافة السريعة للأفلام
            </a>
            
            <!-- زر المولد الذكي للمسلسلات (الجديد) -->
            <a href="index.php?page=series_bulk_add" class="nav-link <?php if($page == 'series_bulk_add') echo 'active'; ?>">
                <i class="fas fa-magic ml-3 w-5 text-center text-emerald-400" style="filter: drop-shadow(0 0 5px rgba(52,211,153,0.5));"></i> المولد الذكي للمسلسلات
            </a>

            <a href="index.php?page=add_episodes" class="nav-link <?php if($page == 'add_episodes') echo 'active'; ?>">
                <i class="fas fa-plus-square ml-3 w-5 text-center text-indigo-400"></i> إضافة حلقات سريع
            </a>
            <a href="index.php?page=daily_episodes" class="nav-link <?php if($page == 'daily_episodes') echo 'active'; ?>">
                <i class="fas fa-bolt ml-3 w-5 text-center text-yellow-400"></i> حلقات اليوم السريعة
            </a>
            <a href="index.php?page=latest_episodes" class="nav-link <?php if($page == 'latest_episodes') echo 'active'; ?>">
                <i class="fas fa-clock ml-3 w-5 text-center text-pink-400"></i> أحدث الحلقات المضافة
            </a>
            
            <!-- ====== قسم استخراج السيرفرات ====== -->
            <a href="index.php?page=af" class="nav-link <?php if($page == 'af') echo 'active'; ?>">
                <i class="fas fa-server ml-3 w-5 text-center text-emerald-500"></i> استخراج سيرفرات التحميل
            </a>
            <a href="index.php?page=link" class="nav-link <?php if($page == 'link') echo 'active'; ?>">
                <i class="fas fa-play-circle ml-3 w-5 text-center text-rose-500"></i> استخراج سيرفرات المشاهدة
            </a>
            <a href="index.php?page=aa" class="nav-link <?php if($page == 'aa') echo 'active'; ?>">
                <i class="fas fa-spider ml-3 w-5 text-center text-yellow-500" style="filter: drop-shadow(0 0 5px rgba(234,179,8,0.5));"></i> صياد الروابط (Hunter)
            </a>
            <a href="index.php?page=bb" class="nav-link <?php if($page == 'bb') echo 'active'; ?>">
                <i class="fas fa-file-invoice ml-3 w-5 text-center text-blue-400" style="filter: drop-shadow(0 0 5px rgba(96,165,250,0.5));"></i> صياد الروابط (ملف BB)
            </a>

            <!-- ====== قسم الـ API الخارجي ====== -->
            <div class="nav-group-title">تخزين الفيديو (API)</div>
            <a href="index.php?page=streamruby" class="nav-link <?php if($page == 'streamruby') echo 'active'; ?>">
                <i class="fas fa-cloud-upload-alt ml-3 w-5 text-center text-rose-500" style="filter: drop-shadow(0 0 5px rgba(244,63,94,0.5));"></i> إدارة StreamRuby
            </a>

            <div class="nav-group-title">الرادارات والإحصائيات</div>
            
            <a href="index.php?page=movies_monitor" class="nav-link <?php if($page == 'movies_monitor') echo 'active'; ?>">
                <i class="fas fa-satellite-dish ml-3 w-5 text-center text-orange-500" style="filter: drop-shadow(0 0 5px rgba(249,115,22,0.5));"></i> رادار المتابعة الشخصي
            </a>
            <a href="index.php?page=general_radar" class="nav-link <?php if($page == 'general_radar') echo 'active'; ?>">
                <i class="fas fa-tower-broadcast ml-3 w-5 text-center text-cyan-400" style="filter: drop-shadow(0 0 5px rgba(34,211,238,0.5));"></i> الرادار الشامل
            </a>
            <a href="index.php?page=ramadan_radar" class="nav-link <?php if($page == 'ramadan_radar') echo 'active'; ?>">
                <i class="fas fa-moon ml-3 w-5 text-center text-purple-400"></i> رادار رمضان 2026
            </a>

            <!-- ========================================== -->
            <!-- قائمة الإدارة العليا تظهر فقط للمدير العام -->
            <!-- ========================================== -->
            <?php if ($_SESSION['admin_role'] === 'super_admin'): ?>
                <div class="nav-group-title text-red-400 flex items-center gap-2"><i class="fas fa-lock text-xs"></i> الإدارة العليا (للمالك)</div>
                <a href="index.php?page=users" class="nav-link <?php if($page == 'users') echo 'active'; ?>">
                    <i class="fas fa-users ml-3 w-5 text-center text-blue-400"></i> إدارة المستخدمين و VIP
                </a>
                <a href="index.php?page=admins" class="nav-link <?php if($page == 'admins') echo 'active'; ?>">
                    <i class="fas fa-user-shield ml-3 w-5 text-center text-red-500"></i> إدارة المديرين
                </a>
                <a href="index.php?page=logs" class="nav-link <?php if($page == 'logs') echo 'active'; ?>">
                    <i class="fas fa-history ml-3 w-5 text-center text-orange-400"></i> سجل نشاطات النظام
                </a>
            <?php endif; ?>

            <div class="nav-group-title">النظام والأدوات</div>
            
            <a href="index.php?page=ads" class="nav-link <?php if($page == 'ads') echo 'active'; ?>">
                <i class="fas fa-ad ml-3 w-5 text-center text-blue-500"></i> الإعلانات
            </a>
            <a href="index.php?page=uploads" class="nav-link <?php if($page == 'uploads') echo 'active'; ?>">
                <i class="fas fa-folder-open ml-3 w-5 text-center text-orange-300"></i> إدارة الملفات
            </a>
            <a href="index.php?page=broken_links" class="nav-link <?php if($page == 'broken_links') echo 'active'; ?>">
                <i class="fas fa-link-slash ml-3 w-5 text-center text-red-400"></i> الروابط المعطلة
            </a>
            <a href="index.php?page=requests" class="nav-link <?php if($page == 'requests') echo 'active'; ?>">
                <i class="fas fa-envelope-open-text ml-3 w-5 text-center text-teal-300"></i> الطلبات والشكاوي
            </a>
            <a href="index.php?page=seo_settings" class="nav-link <?php if($page == 'seo_settings') echo 'active'; ?>">
                <i class="fas fa-search-dollar ml-3 w-5 text-center text-green-400"></i> إعدادات SEO
            </a>
            <a href="index.php?page=settings" class="nav-link <?php if($page == 'settings') echo 'active'; ?>">
                <i class="fas fa-sliders-h ml-3 w-5 text-center text-gray-400"></i> الإعدادات العامة
            </a>
            
        </nav>

        <div class="p-4 border-t border-border-color mt-auto bg-black bg-opacity-40 backdrop-blur-md">
            <a href="logout.php" class="nav-link logout-link !m-0 !px-4 !py-3">
                <i class="fas fa-power-off ml-3 w-5 text-center"></i> إنهاء الجلسة
            </a>
        </div>
    </aside>

    <!-- منطقة المحتوى -->
    <main class="flex-1 p-4 md:p-8 lg:p-10 w-full h-screen overflow-y-auto overflow-x-hidden relative z-10 custom-scrollbar">
        <?php
        // المصفوفة المسموح بها لتضمين الصفحات (تم إضافة series_bulk_add)
        $allowed_pages = [
            'dashboard', 'movies', 'series', 'episodes', 'daily_episodes', 
            'ramadan_radar', 'general_radar', 'requests', 'settings', 
            'uploads', 'broken_links', 'seo_settings', 'add_episodes', 
            'add_movies', 'movies_bulk_add', 'series_bulk_add', 'latest_episodes', 'ads', 'af', 'link', 
            'movies_monitor', 'streamruby', 'aa', 'bb', 'admins', 'logs', 'users'
        ];
        
        if (in_array($page, $allowed_pages)) {
            // حماية إضافية للملفات الحساسة
            if (($page == 'admins' || $page == 'logs' || $page == 'users') && $_SESSION['admin_role'] !== 'super_admin') {
                echo "
                <div class='flex flex-col items-center justify-center h-full'>
                    <div class='bg-red-500/10 backdrop-blur-md p-10 rounded-2xl border border-red-500/30 text-center shadow-2xl max-w-md w-full'>
                        <i class='fas fa-shield-alt text-6xl text-red-500 mb-6 drop-shadow-[0_0_15px_rgba(239,68,68,0.5)]'></i>
                        <h2 class='text-2xl font-black text-white mb-2'>صلاحيات غير كافية</h2>
                        <p class='text-red-300 font-semibold'>عذراً، ليس لديك صلاحية للوصول إلى هذه الصفحة. هذه الصفحة مخصصة للإدارة العليا فقط.</p>
                        <a href='index.php' class='mt-8 inline-block btn btn-secondary w-full justify-center'>العودة للرئيسية</a>
                    </div>
                </div>";
            } else {
                $page_file = "pages/{$page}.php";
                if (file_exists($page_file)) {
                    // تضمين محتوى الصفحة المطلوبة هنا
                    include $page_file;
                } else {
                    echo "
                    <div class='flex flex-col items-center justify-center h-full'>
                        <div class='glass-panel p-10 rounded-2xl text-center shadow-2xl max-w-lg w-full'>
                            <i class='fas fa-tools text-6xl text-gray-500 mb-6'></i>
                            <h2 class='text-2xl font-black text-white mb-2'>الصفحة قيد الإنشاء</h2>
                            <p class='text-gray-400 font-semibold mb-6'>جاري تجهيز هذه الصفحة. لتفعيلها، الرجاء إنشاء الملف التالي:</p>
                            <code class='block bg-black/50 p-4 rounded-xl border border-white/10 text-brand-gold font-mono text-left dir-ltr'>pages/{$page}.php</code>
                        </div>
                    </div>";
                }
            }
        } else {
            // صفحة افتراضية إذا كان الطلب غير صالح
            if (file_exists("pages/dashboard.php")) {
                include "pages/dashboard.php";
            } else {
                echo "<h1 class='text-2xl font-bold'>مرحباً بك في لوحة التحكم</h1><p class='mt-4'>يرجى إنشاء مجلد pages/ وإضافة ملف dashboard.php لتظهر لوحة المعلومات.</p>";
            }
        }
        ?>
    </main>
</div>

<script>
    // التحكم في القائمة الجانبية للموبايل
    function toggleSidebarMenu() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobile-overlay');
        
        if (sidebar.classList.contains('translate-x-full')) {
            sidebar.classList.remove('translate-x-full');
            sidebar.classList.add('translate-x-0');
            overlay.style.display = 'block';
            document.body.style.overflow = 'hidden'; // منع التمرير في الخلفية
        } else {
            sidebar.classList.add('translate-x-full');
            sidebar.classList.remove('translate-x-0');
            overlay.style.display = 'none';
            document.body.style.overflow = ''; 
        }
    }

    document.getElementById('toggleSidebar').addEventListener('click', toggleSidebarMenu);
</script>

</body>
</html>