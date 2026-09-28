<?php
// تحديث قاعدة البيانات للتأكد من وجود حقول المشاهدات (بطريقة متوافقة مع السيرفر)
$check_movies = $conn->query("SHOW COLUMNS FROM movies LIKE 'views'");
if($check_movies && $check_movies->num_rows == 0) {
    $conn->query("ALTER TABLE movies ADD COLUMN views INT DEFAULT 0");
}

$check_series = $conn->query("SHOW COLUMNS FROM series LIKE 'views'");
if($check_series && $check_series->num_rows == 0) {
    $conn->query("ALTER TABLE series ADD COLUMN views INT DEFAULT 0");
}

$check_episodes = $conn->query("SHOW COLUMNS FROM episodes LIKE 'views'");
if($check_episodes && $check_episodes->num_rows == 0) {
    $conn->query("ALTER TABLE episodes ADD COLUMN views INT DEFAULT 0");
}

// --- 1. إحصائيات الأرقام الرئيسية (KPIs) ---
$movies_kpi = $conn->query("SELECT COUNT(id) as total_movies, IFNULL(SUM(views), 0) as total_views FROM movies")->fetch_assoc();

// استبعاد مسلسلات رمضان من الحسبة
$series_kpi = $conn->query("
    SELECT COUNT(id) as total_series, IFNULL(SUM(views), 0) as total_views 
    FROM series WHERE ramadan_year IS NULL OR ramadan_year = 0
")->fetch_assoc();

$episodes_kpi = $conn->query("
    SELECT COUNT(e.id) as total_episodes, IFNULL(SUM(e.views), 0) as total_views 
    FROM episodes e JOIN series s ON e.series_id = s.id 
    WHERE s.ramadan_year IS NULL OR s.ramadan_year = 0
")->fetch_assoc();

// تحديد الدومين الحالي
$current_domain = $_SERVER['HTTP_HOST'];
$is_old_domain = ($current_domain === 'arabfleex.xo.je');

// إصلاح خطأ الحساب والفصل بين الدومين القديم والجديد
if ($is_old_domain) {
    $total_all_views = $movies_kpi['total_views'] + $series_kpi['total_views'];
} else {
    $total_all_views_res = $conn->query("SELECT COUNT(id) as c FROM views_log WHERE domain_name = '$current_domain'");
    $total_all_views = $total_all_views_res->fetch_assoc()['c'] ?? 0;
}

// --- 2. تحليل التصنيفات (الأنواع - Genres) الشائعة ---
$genres_data = [];
if ($is_old_domain) {
    $res_m = $conn->query("SELECT genre, views FROM movies WHERE genre != ''");
    while($row = $res_m->fetch_assoc()){
        $g_list = explode('،', $row['genre']); 
        foreach($g_list as $g) {
            $g = trim(str_replace(',', '', $g));
            if(!empty($g)) $genres_data[$g] = ($genres_data[$g] ?? 0) + $row['views'];
        }
    }

    $res_s = $conn->query("SELECT genre, views FROM series WHERE genre != '' AND (ramadan_year IS NULL OR ramadan_year = 0)");
    while($row = $res_s->fetch_assoc()){
        $g_list = explode('،', $row['genre']);
        foreach($g_list as $g) {
            $g = trim(str_replace(',', '', $g));
            if(!empty($g)) $genres_data[$g] = ($genres_data[$g] ?? 0) + $row['views'];
        }
    }
} else {
    $res_g = $conn->query("
        SELECT COALESCE(m.genre, s.genre) as genre
        FROM views_log v
        LEFT JOIN movies m ON v.content_type = 'movie' AND v.content_id = m.id
        LEFT JOIN series s ON v.content_type = 'series' AND v.content_id = s.id AND (s.ramadan_year IS NULL OR s.ramadan_year = 0)
        WHERE v.domain_name = '$current_domain' AND COALESCE(m.genre, s.genre) IS NOT NULL AND COALESCE(m.genre, s.genre) != ''
    ");
    while($row = $res_g->fetch_assoc()){
        $g_list = explode('،', $row['genre']); 
        foreach($g_list as $g) {
            $g = trim(str_replace(',', '', $g));
            if(!empty($g)) $genres_data[$g] = ($genres_data[$g] ?? 0) + 1;
        }
    }
}
arsort($genres_data);
$top_genres = array_slice($genres_data, 0, 5, true);

// --- 3. صحة السيرفرات (للحلقات العامة) ---
$health_res = $conn->query("
    SELECT 
        SUM(CASE WHEN (IF(watch_link!='',1,0) + IF(watch_link_2!='',1,0) + IF(watch_link_3!='',1,0) + IF(watch_link_4!='',1,0)) >= 3 THEN 1 ELSE 0 END) as multi_servers,
        SUM(CASE WHEN (IF(watch_link!='',1,0) + IF(watch_link_2!='',1,0) + IF(watch_link_3!='',1,0) + IF(watch_link_4!='',1,0)) BETWEEN 1 AND 2 THEN 1 ELSE 0 END) as few_servers,
        SUM(CASE WHEN (IF(watch_link!='',1,0) + IF(watch_link_2!='',1,0) + IF(watch_link_3!='',1,0) + IF(watch_link_4!='',1,0)) = 0 THEN 1 ELSE 0 END) as zero_servers
    FROM episodes WHERE series_id IN (SELECT id FROM series WHERE ramadan_year IS NULL OR ramadan_year = 0)
")->fetch_assoc();

$total_episodes = $episodes_kpi['total_episodes'];
$multi_servers = $health_res['multi_servers'] ?? 0;
$few_servers = $health_res['few_servers'] ?? 0;
$zero_servers = $health_res['zero_servers'] ?? 0;

$multi_servers_pct = $total_episodes > 0 ? round(($multi_servers / $total_episodes) * 100) : 0;
$few_servers_pct = $total_episodes > 0 ? round(($few_servers / $total_episodes) * 100) : 0;
$zero_servers_pct = $total_episodes > 0 ? round(($zero_servers / $total_episodes) * 100) : 0;

// استعلام الحلقات الميتة (0 سيرفرات) 
$dead_links = $conn->query("
    SELECT e.id, e.series_id, e.episode_number, s.title as series_title 
    FROM episodes e JOIN series s ON e.series_id = s.id 
    WHERE (s.ramadan_year IS NULL OR s.ramadan_year = 0) 
    AND (IF(e.watch_link!='',1,0) + IF(e.watch_link_2!='',1,0) + IF(e.watch_link_3!='',1,0) + IF(e.watch_link_4!='',1,0)) = 0
    ORDER BY e.id DESC LIMIT 5
");

// استعلام الحلقات التي تحتوي على 1-2 سيرفرات فقط (للتنبيه)
$few_links_episodes = $conn->query("
    SELECT e.id, e.series_id, e.episode_number, s.title as series_title,
           (IF(e.watch_link!='',1,0) + IF(e.watch_link_2!='',1,0) + IF(e.watch_link_3!='',1,0) + IF(e.watch_link_4!='',1,0)) as active_servers_count
    FROM episodes e JOIN series s ON e.series_id = s.id 
    WHERE (s.ramadan_year IS NULL OR s.ramadan_year = 0) 
    AND (IF(e.watch_link!='',1,0) + IF(e.watch_link_2!='',1,0) + IF(e.watch_link_3!='',1,0) + IF(e.watch_link_4!='',1,0)) BETWEEN 1 AND 2
    ORDER BY e.id DESC LIMIT 5
");

?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="section-header flex flex-col md:flex-row justify-between items-center gap-4 mb-8">
    <div>
        <h1 class="text-3xl font-black text-blue-500"><i class="fas fa-chart-line ml-2"></i> الرادار الشامل</h1>
        <p class="text-gray-400 text-sm mt-1">مراقبة حية لأداء الأفلام والمسلسلات العامة على عرب فليكس</p>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-[#0F0F0F] p-6 rounded-2xl border border-blue-500/30 shadow-[0_0_20px_rgba(59,130,246,0.05)] relative overflow-hidden group">
        <div class="absolute -left-4 -top-4 text-blue-500/10 text-7xl group-hover:scale-110 transition-transform"><i class="fas fa-eye"></i></div>
        <h3 class="text-gray-400 font-bold mb-1 relative z-10">إجمالي المشاهدات</h3>
        <p class="text-4xl font-black text-blue-400 relative z-10"><?php echo number_format($total_all_views); ?></p>
    </div>
    
    <div class="bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl relative overflow-hidden group">
        <div class="absolute -left-4 -top-4 text-emerald-500/10 text-7xl group-hover:scale-110 transition-transform"><i class="fas fa-film"></i></div>
        <h3 class="text-gray-400 font-bold mb-1 relative z-10">أفلام المنصة</h3>
        <p class="text-4xl font-black text-emerald-400 relative z-10"><?php echo number_format($movies_kpi['total_movies']); ?></p>
    </div>
    
    <div class="bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl relative overflow-hidden group">
        <div class="absolute -left-4 -top-4 text-purple-500/10 text-7xl group-hover:scale-110 transition-transform"><i class="fas fa-tv"></i></div>
        <h3 class="text-gray-400 font-bold mb-1 relative z-10">المسلسلات العادية</h3>
        <p class="text-4xl font-black text-purple-400 relative z-10"><?php echo number_format($series_kpi['total_series']); ?></p>
    </div>

    <div class="bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl relative overflow-hidden group">
        <div class="absolute -left-4 -top-4 text-orange-500/10 text-7xl group-hover:scale-110 transition-transform"><i class="fas fa-play-circle"></i></div>
        <h3 class="text-gray-400 font-bold mb-1 relative z-10">إجمالي الحلقات</h3>
        <p class="text-4xl font-black text-orange-400 relative z-10"><?php echo number_format($episodes_kpi['total_episodes']); ?></p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-8">
    <!-- تريند الأفلام -->
    <div class="lg:col-span-8 bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-black text-white"><i class="fas fa-film ml-2 text-emerald-500"></i> تريند الأفلام (التوب 10)</h2>
            <span class="bg-emerald-500/10 text-emerald-500 text-xs font-bold px-3 py-1 rounded-full animate-pulse">الأكثر تفاعلاً</span>
        </div>
        
        <div class="overflow-y-auto max-h-[400px] pr-2 custom-scrollbar">
            <table class="w-full text-right">
                <thead class="sticky top-0 bg-[#0F0F0F] z-10 border-b border-[#1F1F1F]">
                    <tr class="text-gray-400 text-xs">
                        <th class="pb-3 w-10 text-center">#</th>
                        <th class="pb-3">الفيلم</th>
                        <th class="pb-3 text-center">المشاهدات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $view_field_m = $is_old_domain ? "views" : "(SELECT COUNT(id) FROM views_log WHERE content_type = 'movie' AND content_id = movies.id AND domain_name = '$current_domain')";
                    $top_movies = $conn->query("SELECT id, title, poster, $view_field_m as local_views, year FROM movies HAVING local_views > 0 ORDER BY local_views DESC LIMIT 10");
                    $rank = 1;
                    if($top_movies && $top_movies->num_rows > 0):
                        while($m = $top_movies->fetch_assoc()):
                            $is_top_3 = $rank <= 3;
                    ?>
                    <tr class="border-b border-[#1F1F1F]/50 hover:bg-[#1a1a1a] transition-colors">
                        <td class="py-3 text-center">
                            <span class="font-black text-lg <?php echo $is_top_3 ? 'text-[#DAA520]' : 'text-gray-600'; ?>"><?php echo $rank; ?></span>
                        </td>
                        <td class="py-3">
                            <div class="flex items-center gap-3">
                                <img src="<?php echo strpos($m['poster'], 'http') === 0 ? $m['poster'] : '../'.$m['poster']; ?>" class="w-10 h-10 rounded-lg object-cover shadow-md" onerror="this.src='https://placehold.co/40x40'">
                                <div>
                                    <div class="font-bold text-sm text-gray-200"><?php echo htmlspecialchars($m['title']); ?></div>
                                    <div class="text-[10px] text-gray-500"><?php echo htmlspecialchars($m['year']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 text-center">
                            <span class="bg-emerald-500/10 text-emerald-400 font-black px-3 py-1 rounded-full text-xs">
                                <?php echo number_format($m['local_views']); ?> <i class="fas fa-eye ml-1 text-[10px]"></i>
                            </span>
                        </td>
                    </tr>
                    <?php $rank++; endwhile; else: ?>
                        <tr><td colspan="3" class="text-center py-8 text-gray-500 text-sm">لا توجد بيانات مشاهدات.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- نجوم المسلسلات -->
    <div class="lg:col-span-4 bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl flex flex-col relative overflow-hidden group">
        <div class="absolute top-0 right-0 w-32 h-32 bg-purple-500/5 rounded-full blur-3xl -z-10 pointer-events-none transition-all duration-700 group-hover:bg-purple-500/10"></div>

        <div class="flex justify-between items-center mb-6 relative z-10">
            <h2 class="text-xl font-black text-white"><i class="fas fa-crown ml-2 text-purple-500 animate-pulse"></i> نجوم المسلسلات</h2>
            <span class="text-[10px] font-bold text-purple-500 bg-purple-500/10 border border-purple-500/20 px-2 py-1 rounded-md flex items-center gap-1">
                توب 10
            </span>
        </div>
        
        <div class="flex-1 overflow-y-auto max-h-[400px] pr-2 custom-scrollbar space-y-3 relative z-10">
            <?php
            $view_field_s = $is_old_domain ? "views" : "(SELECT COUNT(id) FROM views_log WHERE content_type = 'series' AND content_id = series.id AND domain_name = '$current_domain')";
            $top_series = $conn->query("SELECT title, $view_field_s as local_views, poster FROM series WHERE ramadan_year IS NULL OR ramadan_year = 0 HAVING local_views > 0 ORDER BY local_views DESC LIMIT 10");
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
            <div class="flex items-center gap-3 bg-[#131313] p-2.5 rounded-xl border border-[#2a2a2a] relative overflow-hidden hover:border-purple-500 transition-all duration-300 hover:shadow-[0_5px_15px_rgba(168,85,247,0.1)] hover:-translate-y-0.5 cursor-default">
                
                <div class="absolute right-0 top-0 bottom-0 w-1 bg-gradient-to-b <?php echo $line_color; ?>"></div>
                
                <div class="w-8 h-8 rounded-lg flex items-center justify-center font-black text-sm shrink-0 border <?php echo $rank_style; ?>">
                    <?php echo $icon; ?>
                </div>
                
                <div class="relative w-12 h-16 shrink-0 rounded-md overflow-hidden shadow-lg">
                    <img src="<?php echo strpos($ts['poster'], 'http') === 0 ? $ts['poster'] : '../'.$ts['poster']; ?>" alt="<?php echo htmlspecialchars($ts['title']); ?>" class="w-full h-full object-cover transition-transform duration-500 hover:scale-110" onerror="this.src='https://placehold.co/48x64/1a1a1a/FFF?text=صورة'">
                </div>
                
                <div class="flex-1 min-w-0 pr-1">
                    <div class="font-bold text-sm text-gray-100 truncate hover:text-purple-400 transition-colors" title="<?php echo htmlspecialchars($ts['title']); ?>">
                        <?php echo htmlspecialchars($ts['title']); ?>
                    </div>
                    <div class="text-[11px] font-bold mt-1.5">
                        <span class="inline-flex items-center gap-1 bg-purple-500/10 border border-purple-500/20 text-purple-400 px-2 py-0.5 rounded">
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
                    <i class="fas fa-tv text-4xl mb-3 opacity-30"></i>
                    <span class="text-sm font-bold">لا توجد مسلسلات مضافة</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
    <!-- التصنيفات الشائعة -->
    <div class="bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl flex flex-col">
        <h2 class="text-lg font-black text-white mb-4"><i class="fas fa-chart-pie ml-2 text-blue-400"></i> أكثر التصنيفات مشاهدة</h2>
        <div class="relative flex-1 min-h-[220px] w-full flex justify-center items-center">
            <?php if(empty($top_genres)): ?>
                <div class="text-gray-500 text-sm">لا توجد مشاهدات كافية.</div>
            <?php else: ?>
                <canvas id="generalGenresChart"></canvas>
            <?php endif; ?>
        </div>
    </div>

    <!-- قسم جودة السيرفرات المحدث (مع التنبيهات وإصلاح الروابط) -->
    <div class="bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl flex flex-col h-full">
        <h2 class="text-lg font-black text-white mb-6"><i class="fas fa-server ml-2 text-blue-400"></i> جودة سيرفرات المسلسلات</h2>
        
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

        <div class="flex-1 overflow-y-auto custom-scrollbar pr-1 space-y-3" style="max-height: 200px;">
            <!-- صندوق الحلقات الميتة (بدون سيرفرات) -->
            <?php if($dead_links && $dead_links->num_rows > 0): ?>
            <div class="bg-red-500/5 border border-red-500/20 rounded-xl p-3">
                <h4 class="text-xs font-bold text-red-400 mb-2"><i class="fas fa-exclamation-triangle ml-1"></i> حلقات تحتاج روابط فوراً:</h4>
                <div class="space-y-2">
                    <?php while($dl = $dead_links->fetch_assoc()): ?>
                    <div class="flex justify-between items-center text-[11px] bg-[#0F0F0F] p-2 rounded border border-[#1F1F1F]">
                        <span class="text-gray-300 font-bold"><?php echo htmlspecialchars($dl['series_title']); ?> - ح<?php echo $dl['episode_number']; ?></span>
                        <a href="index.php?page=episodes&series_id=<?php echo $dl['series_id']; ?>&action=edit&id=<?php echo $dl['id']; ?>" class="text-red-400 hover:text-white transition-colors underline">تعديل</a>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- صندوق الحلقات التي تحتاج دعم (1-2 سيرفر) -->
            <?php if($few_links_episodes && $few_links_episodes->num_rows > 0): ?>
            <div class="bg-yellow-500/5 border border-yellow-500/20 rounded-xl p-3">
                <h4 class="text-xs font-bold text-yellow-400 mb-2"><i class="fas fa-info-circle ml-1"></i> حلقات تحتاج سيرفرات إضافية:</h4>
                <div class="space-y-2">
                    <?php while($fl = $few_links_episodes->fetch_assoc()): ?>
                    <div class="flex justify-between items-center text-[11px] bg-[#0F0F0F] p-2 rounded border border-[#1F1F1F]">
                        <div class="flex flex-col">
                            <span class="text-gray-300 font-bold"><?php echo htmlspecialchars($fl['series_title']); ?> - ح<?php echo $fl['episode_number']; ?></span>
                            <span class="text-[9px] text-gray-500">متوفر: <?php echo $fl['active_servers_count']; ?> سيرفر</span>
                        </div>
                        <a href="index.php?page=episodes&series_id=<?php echo $fl['series_id']; ?>&action=edit&id=<?php echo $fl['id']; ?>" class="text-yellow-500 hover:text-white transition-colors underline shrink-0">تزويد</a>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- الحلقات الأكثر تفاعلاً -->
    <div class="bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl flex flex-col relative overflow-hidden group">
        <div class="absolute top-0 left-0 w-32 h-32 bg-orange-500/5 rounded-full blur-3xl -z-10 pointer-events-none transition-all duration-700 group-hover:bg-orange-500/10"></div>
        
        <div class="flex justify-between items-center mb-6 relative z-10">
            <h2 class="text-lg font-black text-white"><i class="fas fa-fire-alt ml-2 text-orange-500 animate-pulse"></i> تريند الحلقات (التوب 20)</h2>
        </div>
        
        <div class="space-y-3 flex-1 overflow-y-auto max-h-[400px] custom-scrollbar pr-2 relative z-10">
            <?php
            $view_field_ep = $is_old_domain ? "e.views" : "(SELECT COUNT(v.id) FROM views_log v WHERE v.content_type = 'episode' AND v.content_id = e.id AND v.domain_name = '$current_domain')";
            $top_episodes = $conn->query("
                SELECT e.id, e.series_id, e.title as ep_title, $view_field_ep as local_views, e.episode_number, 
                       s.title as series_title, s.poster,
                       (IF(e.watch_link!='',1,0) + IF(e.watch_link_2!='',1,0) + IF(e.watch_link_3!='',1,0) + IF(e.watch_link_4!='',1,0)) as server_count
                FROM episodes e JOIN series s ON e.series_id = s.id 
                WHERE s.ramadan_year IS NULL OR s.ramadan_year = 0 
                HAVING local_views > 0
                ORDER BY local_views DESC LIMIT 20
            ");
            
            $episodes_data = [];
            $max_ep_views = 0;
            if($top_episodes && $top_episodes->num_rows > 0){
                while($ep = $top_episodes->fetch_assoc()){
                    $episodes_data[] = $ep;
                    if($ep['local_views'] > $max_ep_views) {
                        $max_ep_views = $ep['local_views'];
                    }
                }
            }

            if(!empty($episodes_data)):
                $ep_rank = 1;
                foreach($episodes_data as $ep):
                    $view_percentage = $max_ep_views > 0 ? round(($ep['local_views'] / $max_ep_views) * 100) : 0;
                    
                    if ($ep_rank == 1) { 
                        $badge = '<i class="fas fa-crown text-yellow-400 text-lg drop-shadow-[0_0_5px_rgba(250,204,21,0.8)]"></i>'; 
                    } elseif ($ep_rank == 2) { 
                        $badge = '<span class="text-gray-300 font-black text-lg drop-shadow-[0_0_5px_rgba(209,213,219,0.8)]">2</span>'; 
                    } elseif ($ep_rank == 3) { 
                        $badge = '<span class="text-orange-600 font-black text-lg drop-shadow-[0_0_5px_rgba(234,88,12,0.8)]">3</span>'; 
                    } else { 
                        $badge = '<span class="text-gray-600 font-bold text-sm">'.$ep_rank.'</span>'; 
                    }

                    $server_color = 'bg-red-500 shadow-[0_0_5px_rgba(239,68,68,0.8)]'; 
                    $server_title = 'بدون سيرفرات!';
                    if($ep['server_count'] >= 3) {
                        $server_color = 'bg-green-500 shadow-[0_0_5px_rgba(34,197,94,0.8)]';
                        $server_title = 'سيرفرات ممتازة';
                    } elseif ($ep['server_count'] > 0) {
                        $server_color = 'bg-yellow-500 shadow-[0_0_5px_rgba(234,179,8,0.8)]';
                        $server_title = 'سيرفرات قليلة';
                    }
            ?>
            
            <div class="flex items-center gap-3 bg-[#131313] p-2.5 rounded-xl border border-[#2a2a2a] relative overflow-hidden hover:border-orange-500/50 hover:shadow-[0_4px_12px_rgba(249,115,22,0.1)] transition-all duration-300 group">
                <div class="w-6 flex justify-center items-center shrink-0">
                    <?php echo $badge; ?>
                </div>

                <div class="relative w-11 h-11 shrink-0 rounded-lg overflow-hidden shadow-md">
                    <img src="<?php echo strpos($ep['poster'], 'http') === 0 ? $ep['poster'] : '../'.$ep['poster']; ?>" alt="صورة" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" onerror="this.src='https://placehold.co/44x44/1a1a1a/FFF?text=H'">
                    <div class="absolute top-1 right-1 w-2.5 h-2.5 rounded-full border border-[#131313] <?php echo $server_color; ?>" title="<?php echo $server_title; ?>"></div>
                </div>
                
                <div class="flex-1 min-w-0 flex flex-col justify-center">
                    <div class="flex justify-between items-start mb-1">
                        <div class="flex flex-col truncate pr-2">
                            <span class="font-bold text-[13px] text-gray-200 truncate" title="<?php echo htmlspecialchars($ep['series_title']); ?>">
                                <?php echo htmlspecialchars($ep['series_title']); ?>
                            </span>
                            <span class="text-[10px] text-orange-400 font-bold">الحلقة <?php echo $ep['episode_number']; ?></span>
                        </div>
                        
                        <div class="flex flex-col items-end shrink-0">
                            <span class="text-[11px] font-black text-white bg-[#1F1F1F] px-1.5 py-0.5 rounded">
                                <?php echo number_format($ep['local_views']); ?> <i class="fas fa-eye text-orange-500 ml-0.5 text-[9px]"></i>
                            </span>
                        </div>
                    </div>
                    
                    <div class="w-full bg-[#1a1a1a] rounded-full h-1 mt-1 overflow-hidden">
                        <div class="bg-gradient-to-l from-orange-400 to-orange-600 h-1 rounded-full transition-all duration-1000" style="width: <?php echo $view_percentage; ?>%"></div>
                    </div>
                </div>

                <a href="index.php?page=episodes&series_id=<?php echo $ep['series_id']; ?>&action=edit&id=<?php echo $ep['id']; ?>" class="absolute left-2 top-1/2 -translate-y-1/2 bg-blue-500/20 text-blue-400 hover:bg-blue-500 hover:text-white w-8 h-8 rounded-full flex items-center justify-center transition-all duration-300 opacity-0 group-hover:opacity-100 translate-x-4 group-hover:translate-x-0" title="تعديل الحلقة">
                    <i class="fas fa-pen text-[10px]"></i>
                </a>
            </div>
            <?php 
                $ep_rank++; 
                endforeach; 
            else: 
            ?>
                <div class="flex flex-col items-center justify-center h-full text-gray-600 py-10 bg-[#131313] rounded-xl border border-[#1F1F1F] border-dashed">
                    <i class="fas fa-fire-extinguisher text-3xl mb-3 opacity-30"></i>
                    <span class="text-sm font-bold">لا توجد تفاعلات بعد</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .custom-scrollbar::-webkit-scrollbar { width: 5px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #2a2a2a; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #444; }
</style>

<?php if(!empty($top_genres)): ?>
<script>
    const ctxGen = document.getElementById('generalGenresChart').getContext('2d');
    new Chart(ctxGen, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_keys($top_genres)); ?>,
            datasets: [{
                data: <?php echo json_encode(array_values($top_genres)); ?>,
                backgroundColor: ['#DAA520', '#3b82f6', '#a855f7', '#ef4444', '#10b981'],
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { 
                    display: true, 
                    position: 'bottom',
                    labels: { color: '#e5e7eb', font: { family: 'Cairo', size: 12, weight: 'bold' }, padding: 15 }
                },
                tooltip: {
                    backgroundColor: 'rgba(15, 15, 15, 0.95)',
                    titleColor: '#DAA520',
                    bodyColor: '#ffffff',
                    borderColor: '#DAA520',
                    borderWidth: 1,
                    titleFont: { family: 'Cairo', size: 13 },
                    bodyFont: { family: 'Cairo', size: 13, weight: 'bold' }
                }
            }
        }
    });
</script>
<?php endif; ?>