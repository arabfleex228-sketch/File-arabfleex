<?php
// تأمين التوقيت ليتوافق مع مصر حتى يقلب اليوم الرمضاني في منتصف الليل بدقة
date_default_timezone_set('Africa/Cairo');

// تحديث قاعدة البيانات للتأكد من وجود حقول المشاهدات (بطريقة متوافقة مع السيرفر)
$check_series = $conn->query("SHOW COLUMNS FROM series LIKE 'views'");
if($check_series && $check_series->num_rows == 0) {
    $conn->query("ALTER TABLE series ADD COLUMN views INT DEFAULT 0");
}

$check_episodes = $conn->query("SHOW COLUMNS FROM episodes LIKE 'views'");
if($check_episodes && $check_episodes->num_rows == 0) {
    $conn->query("ALTER TABLE episodes ADD COLUMN views INT DEFAULT 0");
}

// حساب اليوم الرمضاني تلقائياً (أول يوم رمضان هو 18 فبراير 2026)
$start_date = '2026-02-18'; 
$today_obj = new DateTime(); 
$today_obj->setTime(0,0,0);

$ramadan_start_obj = new DateTime($start_date); 
$ramadan_start_obj->setTime(0,0,0);

$interval = $ramadan_start_obj->diff($today_obj);

$ramadan_day = 0; 
$is_ramadan_started = false;

if ($today_obj >= $ramadan_start_obj) {
    $is_ramadan_started = true;
    $ramadan_day = $interval->days; 
    if ($ramadan_day <= 0) $ramadan_day = 1; 
    if ($ramadan_day > 30) $ramadan_day = 30; // توقف العداد عند 30
}

$current_domain = $_SERVER['HTTP_HOST'];
$is_old_domain = ($current_domain === 'arabfleex.xo.je');

// 1. إحصائيات الموسم الأساسية
$kpi_query = $conn->query("
    SELECT 
        COUNT(s.id) as total_series,
        (SELECT COUNT(e.id) FROM episodes e JOIN series s2 ON e.series_id = s2.id WHERE s2.ramadan_year = 2026) as total_episodes
    FROM series s WHERE s.ramadan_year = 2026
");
$kpi = $kpi_query->fetch_assoc();

if ($is_old_domain) {
    $tv_query = $conn->query("SELECT IFNULL(SUM(e.views), 0) as total_episodes_views FROM episodes e JOIN series s3 ON e.series_id = s3.id WHERE s3.ramadan_year = 2026");
    $total_ramadan_views = $tv_query->fetch_assoc()['total_episodes_views'] ?? 0;
} else {
    $tv_query = $conn->query("SELECT COUNT(v.id) as total_episodes_views FROM views_log v JOIN episodes e ON v.content_id = e.id AND v.content_type = 'episode' JOIN series s3 ON e.series_id = s3.id WHERE s3.ramadan_year = 2026 AND v.domain_name = '$current_domain'");
    $total_ramadan_views = $tv_query->fetch_assoc()['total_episodes_views'] ?? 0;
}

$total_episodes = $kpi['total_episodes'];

// 2. تحليل التصنيفات (صراع الأنواع)
$genres_data = [];
if ($is_old_domain) {
    $genres_res = $conn->query("SELECT genre, views FROM series WHERE ramadan_year = 2026");
    while($r = $genres_res->fetch_assoc()) {
        $g_list = explode('،', $r['genre']);
        foreach($g_list as $g) {
            $g = trim($g);
            if(!empty($g)) { $genres_data[$g] = ($genres_data[$g] ?? 0) + $r['views']; }
        }
    }
} else {
    $genres_res = $conn->query("
        SELECT s.genre FROM views_log v 
        JOIN series s ON v.content_id = s.id AND v.content_type = 'series' 
        WHERE s.ramadan_year = 2026 AND v.domain_name = '$current_domain' AND s.genre != ''
    ");
    while($r = $genres_res->fetch_assoc()) {
        $g_list = explode('،', $r['genre']);
        foreach($g_list as $g) {
            $g = trim($g);
            if(!empty($g)) { $genres_data[$g] = ($genres_data[$g] ?? 0) + 1; }
        }
    }
}
arsort($genres_data);
$top_genres = array_slice($genres_data, 0, 5);

// 3. صحة السيرفرات وجلب الحلقات المعطوبة
$health_res = $conn->query("
    SELECT 
        SUM(CASE WHEN (IF(watch_link!='',1,0) + IF(watch_link_2!='',1,0) + IF(watch_link_3!='',1,0) + IF(watch_link_4!='',1,0)) >= 3 THEN 1 ELSE 0 END) as multi_servers,
        SUM(CASE WHEN (IF(watch_link!='',1,0) + IF(watch_link_2!='',1,0) + IF(watch_link_3!='',1,0) + IF(watch_link_4!='',1,0)) BETWEEN 1 AND 2 THEN 1 ELSE 0 END) as few_servers,
        SUM(CASE WHEN (IF(watch_link!='',1,0) + IF(watch_link_2!='',1,0) + IF(watch_link_3!='',1,0) + IF(watch_link_4!='',1,0)) = 0 THEN 1 ELSE 0 END) as zero_servers
    FROM episodes WHERE series_id IN (SELECT id FROM series WHERE ramadan_year = 2026)
")->fetch_assoc();

$multi_servers = $health_res['multi_servers'] ?? 0;
$few_servers = $health_res['few_servers'] ?? 0;
$zero_servers = $health_res['zero_servers'] ?? 0;

$multi_servers_pct = $total_episodes > 0 ? round(($multi_servers / $total_episodes) * 100) : 0;
$few_servers_pct = $total_episodes > 0 ? round(($few_servers / $total_episodes) * 100) : 0;
$zero_servers_pct = $total_episodes > 0 ? round(($zero_servers / $total_episodes) * 100) : 0;

$dead_links = $conn->query("
    SELECT e.id, e.episode_number, s.title as series_title 
    FROM episodes e JOIN series s ON e.series_id = s.id 
    WHERE s.ramadan_year = 2026 
    AND (IF(e.watch_link!='',1,0) + IF(e.watch_link_2!='',1,0) + IF(e.watch_link_3!='',1,0) + IF(e.watch_link_4!='',1,0)) = 0
    ORDER BY e.id DESC LIMIT 5
");

// 4. رادار الحلقات المفقودة
$missing_episodes_data = [];
$series_eps_res = $conn->query("
    SELECT s.title, GROUP_CONCAT(e.episode_number ORDER BY CAST(e.episode_number AS UNSIGNED) ASC) as eps 
    FROM series s 
    JOIN episodes e ON s.id = e.series_id 
    WHERE s.ramadan_year = 2026 
    GROUP BY s.id
");

if($series_eps_res && $series_eps_res->num_rows > 0) {
    while($row = $series_eps_res->fetch_assoc()) {
        if(empty($row['eps'])) continue;
        
        $eps_array = array_map('intval', explode(',', $row['eps']));
        $max_ep = max($eps_array);
        $missing = [];
        
        for($i = 1; $i <= $max_ep; $i++) {
            if(!in_array($i, $eps_array)) {
                $missing[] = $i;
            }
        }
        
        if(!empty($missing)) {
            $missing_episodes_data[$row['title']] = $missing;
        }
    }
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="section-header flex flex-col md:flex-row justify-between items-center gap-4 mb-8">
    <div>
        <h1 class="text-3xl font-black text-[#DAA520]"><i class="fas fa-radar ml-2"></i> رادار رمضان 2026</h1>
        <p class="text-gray-400 text-sm mt-1">مركز تحليل البيانات، التريندات، والمساعد الذكي لإدارة المحتوى</p>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl relative overflow-hidden group">
        <div class="absolute -left-4 -top-4 text-gray-800 text-7xl group-hover:scale-110 transition-transform"><i class="fas fa-calendar-day"></i></div>
        <h3 class="text-gray-400 font-bold mb-1 relative z-10">اليوم الرمضاني</h3>
        <p class="text-4xl font-black text-white relative z-10"><?php echo $is_ramadan_started ? $ramadan_day : 'لم يبدأ'; ?> <span class="text-sm text-gray-500 font-normal">/ 30</span></p>
    </div>
    
    <div class="bg-[#0F0F0F] p-6 rounded-2xl border border-blue-500/30 shadow-[0_0_20px_rgba(59,130,246,0.05)] relative overflow-hidden group">
        <div class="absolute -left-4 -top-4 text-blue-500/10 text-7xl group-hover:scale-110 transition-transform"><i class="fas fa-eye"></i></div>
        <h3 class="text-gray-400 font-bold mb-1 relative z-10">إجمالي المشاهدات</h3>
        <p class="text-4xl font-black text-blue-400 relative z-10"><?php echo number_format($total_ramadan_views); ?></p>
    </div>
    
    <div class="bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl relative overflow-hidden group">
        <div class="absolute -left-4 -top-4 text-purple-500/10 text-7xl group-hover:scale-110 transition-transform"><i class="fas fa-tv"></i></div>
        <h3 class="text-gray-400 font-bold mb-1 relative z-10">مسلسلات الموسم</h3>
        <p class="text-4xl font-black text-purple-400 relative z-10"><?php echo $kpi['total_series']; ?></p>
    </div>

    <div class="bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl relative overflow-hidden group">
        <div class="absolute -left-4 -top-4 text-green-500/10 text-7xl group-hover:scale-110 transition-transform"><i class="fas fa-list-ol"></i></div>
        <h3 class="text-gray-400 font-bold mb-1 relative z-10">الحلقات المرفوعة</h3>
        <p class="text-4xl font-black text-green-400 relative z-10"><?php echo number_format($total_episodes); ?></p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-8">
    <div class="lg:col-span-8 bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-black text-white"><i class="fas fa-fire-alt ml-2 text-red-500"></i> تريند الحلقات (التوب 10)</h2>
            <span class="bg-red-500/10 text-red-500 text-xs font-bold px-3 py-1 rounded-full animate-pulse">الأكثر تفاعلاً</span>
        </div>
        
        <div class="overflow-y-auto max-h-[400px] pr-2 custom-scrollbar">
            <table class="w-full text-right">
                <thead class="sticky top-0 bg-[#0F0F0F] z-10 border-b border-[#1F1F1F]">
                    <tr class="text-gray-400 text-xs">
                        <th class="pb-3 w-10 text-center">#</th>
                        <th class="pb-3">المسلسل والحلقة</th>
                        <th class="pb-3 text-center">المشاهدات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $view_field_ep = $is_old_domain ? "e.views" : "(SELECT COUNT(v.id) FROM views_log v WHERE v.content_type = 'episode' AND v.content_id = e.id AND v.domain_name = '$current_domain')";
                    $top_episodes = $conn->query("
                        SELECT e.id, e.title as ep_title, $view_field_ep as local_views, e.episode_number, s.title as series_title, s.poster 
                        FROM episodes e JOIN series s ON e.series_id = s.id 
                        WHERE s.ramadan_year = 2026 
                        HAVING local_views > 0
                        ORDER BY local_views DESC LIMIT 10
                    ");
                    $rank = 1;
                    if($top_episodes && $top_episodes->num_rows > 0):
                        while($ep = $top_episodes->fetch_assoc()):
                            $is_top_3 = $rank <= 3;
                    ?>
                    <tr class="border-b border-[#1F1F1F]/50 hover:bg-[#1a1a1a] transition-colors">
                        <td class="py-3 text-center">
                            <span class="font-black text-lg <?php echo $is_top_3 ? 'text-[#DAA520]' : 'text-gray-600'; ?>"><?php echo $rank; ?></span>
                        </td>
                        <td class="py-3">
                            <div class="flex items-center gap-3">
                                <img src="<?php echo strpos($ep['poster'], 'http') === 0 ? $ep['poster'] : '../'.$ep['poster']; ?>" class="w-10 h-10 rounded-lg object-cover shadow-md" onerror="this.src='https://placehold.co/40x40'">
                                <div>
                                    <div class="font-bold text-sm text-gray-200"><?php echo htmlspecialchars($ep['series_title']); ?></div>
                                    <div class="text-xs text-gray-500">الحلقة <?php echo $ep['episode_number']; ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 text-center">
                            <span class="bg-blue-500/10 text-blue-400 font-black px-3 py-1 rounded-full text-xs">
                                <?php echo number_format($ep['local_views']); ?> <i class="fas fa-eye ml-1 text-[10px]"></i>
                            </span>
                        </td>
                    </tr>
                    <?php $rank++; endwhile; else: ?>
                        <tr><td colspan="3" class="text-center py-8 text-gray-500 text-sm">لا توجد بيانات مشاهدات حتى الآن.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="lg:col-span-4 bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl flex flex-col relative overflow-hidden group">
        <div class="absolute top-0 right-0 w-32 h-32 bg-yellow-500/5 rounded-full blur-3xl -z-10 pointer-events-none transition-all duration-700 group-hover:bg-yellow-500/10"></div>

        <div class="flex justify-between items-center mb-6 relative z-10">
            <h2 class="text-xl font-black text-white"><i class="fas fa-trophy ml-2 text-yellow-500 animate-pulse"></i> أبطال الموسم (توب 10)</h2>
            <span class="text-[10px] font-bold text-green-500 bg-green-500/10 border border-green-500/20 px-2 py-1 rounded-md flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-ping"></span> مباشر
            </span>
        </div>
        
        <div class="flex-1 overflow-y-auto max-h-[400px] pr-2 custom-scrollbar space-y-3 relative z-10">
            <?php
            $view_field_s = $is_old_domain ? "views" : "(SELECT COUNT(v.id) FROM views_log v WHERE v.content_type = 'series' AND v.content_id = series.id AND v.domain_name = '$current_domain')";
            $top_series = $conn->query("
                SELECT title, $view_field_s as local_views, poster 
                FROM series 
                WHERE ramadan_year = 2026 
                HAVING local_views > 0
                ORDER BY local_views DESC LIMIT 10
            ");
            $s_rank = 1;
            if($top_series && $top_series->num_rows > 0):
                while($ts = $top_series->fetch_assoc()):
                    if ($s_rank == 1) { 
                        $rank_style = 'bg-yellow-500/20 text-yellow-500 border-yellow-500/40 shadow-[0_0_10px_rgba(234,179,8,0.2)]'; 
                        $icon = '<i class="fas fa-crown"></i>'; 
                        $line_color = 'from-yellow-400 to-yellow-600';
                    } elseif ($s_rank == 2) { 
                        $rank_style = 'bg-gray-300/20 text-gray-300 border-gray-300/40'; 
                        $icon = $s_rank; 
                        $line_color = 'from-gray-300 to-gray-500';
                    } elseif ($s_rank == 3) { 
                        $rank_style = 'bg-orange-700/30 text-orange-400 border-orange-700/50'; 
                        $icon = $s_rank; 
                        $line_color = 'from-orange-400 to-orange-700';
                    } else { 
                        $rank_style = 'bg-white/5 text-gray-500 border-white/10'; 
                        $icon = $s_rank; 
                        $line_color = 'from-transparent to-transparent';
                    }
            ?>
            <div class="flex items-center gap-3 bg-[#131313] p-2.5 rounded-xl border border-[#2a2a2a] relative overflow-hidden hover:border-[#DAA520] transition-all duration-300 hover:shadow-[0_5px_15px_rgba(218,165,32,0.1)] hover:-translate-y-0.5 cursor-default">
                
                <div class="absolute right-0 top-0 bottom-0 w-1 bg-gradient-to-b <?php echo $line_color; ?>"></div>
                
                <div class="w-8 h-8 rounded-lg flex items-center justify-center font-black text-sm shrink-0 border <?php echo $rank_style; ?>">
                    <?php echo $icon; ?>
                </div>
                
                <div class="relative w-12 h-16 shrink-0 rounded-md overflow-hidden shadow-lg">
                    <img src="<?php echo strpos($ts['poster'], 'http') === 0 ? $ts['poster'] : '../'.$ts['poster']; ?>" alt="<?php echo htmlspecialchars($ts['title']); ?>" class="w-full h-full object-cover transition-transform duration-500 hover:scale-110" onerror="this.src='https://placehold.co/48x64/1a1a1a/FFF?text=صورة'">
                </div>
                
                <div class="flex-1 min-w-0 pr-1">
                    <div class="font-bold text-sm text-gray-100 truncate hover:text-[#DAA520] transition-colors" title="<?php echo htmlspecialchars($ts['title']); ?>">
                        <?php echo htmlspecialchars($ts['title']); ?>
                    </div>
                    <div class="text-[11px] font-bold mt-1.5">
                        <span class="inline-flex items-center gap-1 bg-blue-500/10 border border-blue-500/20 text-blue-400 px-2 py-0.5 rounded">
                            <i class="fas fa-eye text-[10px]"></i> <?php echo number_format($ts['local_views']); ?>
                        </span>
                    </div>
                </div>
                
                <?php if($s_rank == 1): ?>
                    <i class="fas fa-fire text-red-500 text-xl ml-2 animate-bounce drop-shadow-[0_0_8px_rgba(239,68,68,0.6)]" title="التريند الأول"></i>
                <?php endif; ?>
                
            </div>
            <?php $s_rank++; endwhile; else: ?>
                <div class="flex flex-col items-center justify-center h-full text-gray-600 py-12 bg-[#131313] rounded-xl border border-[#1F1F1F] border-dashed">
                    <i class="fas fa-film text-4xl mb-3 opacity-30"></i>
                    <span class="text-sm font-bold">لا توجد مسلسلات مضافة للموسم</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
    <div class="bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl">
        <h2 class="text-lg font-black text-white mb-4"><i class="fas fa-chart-pie ml-2 text-purple-400"></i> صراع التصنيفات</h2>
        <div class="relative h-[220px] w-full">
            <?php if(empty($top_genres)): ?>
                <div class="absolute inset-0 flex items-center justify-center text-gray-500 text-sm">لا توجد مشاهدات كافية.</div>
            <?php else: ?>
                <canvas id="genresChart"></canvas>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl flex flex-col">
        <h2 class="text-lg font-black text-white mb-6"><i class="fas fa-server ml-2 text-blue-400"></i> تقرير جودة الروابط</h2>
        
        <div class="space-y-5 mb-6">
            <div>
                <div class="flex justify-between text-xs font-bold mb-1">
                    <span class="text-green-400"><i class="fas fa-check-double ml-1"></i> (3-4 سيرفرات) ممتاز</span>
                    <span class="text-gray-400"><?php echo $multi_servers; ?> حلقة</span>
                </div>
                <div class="w-full bg-[#1F1F1F] rounded-full h-2">
                    <div class="bg-green-500 h-2 rounded-full shadow-[0_0_10px_rgba(34,197,94,0.5)]" style="width: <?php echo $multi_servers_pct; ?>%"></div>
                </div>
            </div>

            <div>
                <div class="flex justify-between text-xs font-bold mb-1">
                    <span class="text-yellow-400"><i class="fas fa-check ml-1"></i> (1-2 سيرفرات) جيد</span>
                    <span class="text-gray-400"><?php echo $few_servers; ?> حلقة</span>
                </div>
                <div class="w-full bg-[#1F1F1F] rounded-full h-2">
                    <div class="bg-yellow-500 h-2 rounded-full" style="width: <?php echo $few_servers_pct; ?>%"></div>
                </div>
            </div>

            <div>
                <div class="flex justify-between text-xs font-bold mb-1">
                    <span class="text-red-500"><i class="fas fa-times ml-1"></i> بدون روابط (خطر)</span>
                    <span class="text-gray-400"><?php echo $zero_servers; ?> حلقة</span>
                </div>
                <div class="w-full bg-[#1F1F1F] rounded-full h-2">
                    <div class="bg-red-500 h-2 rounded-full <?php echo $zero_servers > 0 ? 'animate-pulse' : ''; ?>" style="width: <?php echo $zero_servers_pct; ?>%"></div>
                </div>
            </div>
        </div>

        <?php if($dead_links && $dead_links->num_rows > 0): ?>
        <div class="mt-auto bg-red-500/5 border border-red-500/20 rounded-xl p-3">
            <h4 class="text-xs font-bold text-red-400 mb-2"><i class="fas fa-exclamation-triangle ml-1"></i> حلقات تحتاج إضافة روابط فوراً:</h4>
            <div class="space-y-2 max-h-[100px] overflow-y-auto custom-scrollbar pr-1">
                <?php while($dl = $dead_links->fetch_assoc()): ?>
                <div class="flex justify-between items-center text-[11px] bg-[#0F0F0F] p-2 rounded border border-[#1F1F1F]">
                    <span class="text-gray-300 font-bold"><?php echo htmlspecialchars($dl['series_title']); ?> - ح<?php echo $dl['episode_number']; ?></span>
                    <a href="edit_episode.php?id=<?php echo $dl['id']; ?>" class="text-red-400 hover:text-white transition-colors underline">تعديل</a>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl flex flex-col">
        <?php if(!empty($missing_episodes_data)): ?>
            <h2 class="text-lg font-black text-white mb-4"><i class="fas fa-exclamation-circle ml-2 text-orange-500"></i> رادار الحلقات المفقودة</h2>
            <div class="space-y-3 flex-1 overflow-y-auto max-h-[220px] custom-scrollbar pr-2">
                <?php foreach($missing_episodes_data as $s_title => $m_eps): ?>
                <div class="bg-orange-500/10 border border-orange-500/30 p-3 rounded-xl">
                    <div class="font-bold text-sm text-gray-200 mb-1"><?php echo htmlspecialchars($s_title); ?></div>
                    <div class="text-xs text-orange-400 font-bold">
                        <i class="fas fa-search ml-1"></i> حلقات ناقصة: <?php echo implode('، ', $m_eps); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <p class="text-[10px] text-gray-500 mt-4 text-center">النظام اكتشف فجوات في تسلسل حلقات هذه المسلسلات.</p>
        <?php else: ?>
            <h2 class="text-lg font-black text-white mb-4"><i class="fas fa-arrow-down ml-2 text-red-500"></i> المسلسلات الأقل تفاعلاً</h2>
            <div class="space-y-3 flex-1">
                <?php
                $view_field_s_lowest = $is_old_domain ? "views" : "(SELECT COUNT(v.id) FROM views_log v WHERE v.content_type = 'series' AND v.content_id = series.id AND v.domain_name = '$current_domain')";
                $lowest_series = $conn->query("SELECT title, $view_field_s_lowest as local_views FROM series WHERE ramadan_year = 2026 ORDER BY local_views ASC LIMIT 4");
                if($lowest_series && $lowest_series->num_rows > 0):
                    while($ls = $lowest_series->fetch_assoc()):
                ?>
                <div class="flex justify-between items-center bg-[#1a1a1a] p-3 rounded-lg border border-[#333]">
                    <span class="font-bold text-sm text-gray-300 truncate w-2/3"><?php echo htmlspecialchars($ls['title']); ?></span>
                    <span class="bg-red-500/10 text-red-400 text-xs font-black px-2 py-1 rounded"><?php echo number_format($ls['local_views']); ?> <i class="fas fa-eye"></i></span>
                </div>
                <?php endwhile; else: ?>
                    <div class="text-sm text-gray-500 text-center py-4">لا توجد بيانات.</div>
                <?php endif; ?>
            </div>
            <p class="text-[10px] text-gray-500 mt-4 text-center">مفيش أي حلقات مفقودة. تسلسل الحلقات سليم 100%.</p>
        <?php endif; ?>
    </div>
</div>

<style>
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #333; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #555; }
</style>

<?php if(!empty($top_genres)): ?>
<script>
    const ctxPie = document.getElementById('genresChart').getContext('2d');
    new Chart(ctxPie, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_keys($top_genres)); ?>,
            datasets: [{
                data: <?php echo json_encode(array_values($top_genres)); ?>,
                backgroundColor: ['#DAA520', '#3b82f6', '#a855f7', '#ef4444', '#10b981'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { position: 'right', labels: { color: '#bbb', font: { family: 'Cairo', size: 12 } } }
            }
        }
    });
</script>
<?php endif; ?>