<?php
if (!isset($_GET['series_id']) || !is_numeric($_GET['series_id'])) exit("معرف غير صحيح.");
$series_id = intval($_GET['series_id']);

// تأكد من وجود الأعمدة الجديدة في قاعدة البيانات (حالة الظهور)
$check_cols = $conn->query("SHOW COLUMNS FROM episodes LIKE 'is_published'");
if ($check_cols->num_rows == 0) {
    $conn->query("ALTER TABLE episodes ADD COLUMN is_published TINYINT(1) DEFAULT 1, ADD COLUMN show_in_latest TINYINT(1) DEFAULT 1");
}

if (isset($_GET['ajax_action'])) {
    if ($_GET['ajax_action'] == 'check_duplicate') {
        header('Content-Type: application/json');
        $num = intval($_GET['num']);
        $check = $conn->query("SELECT id FROM episodes WHERE series_id = $series_id AND episode_number = $num");
        echo json_encode(['exists' => $check->num_rows > 0]);
        exit;
    }
    if ($_GET['ajax_action'] == 'reorder') {
        header('Content-Type: application/json');
        $order = $_POST['order'] ?? []; 
        foreach ($order as $item) {
            $id = intval($item['id']);
            $num = intval($item['num']);
            $conn->query("UPDATE episodes SET episode_number = $num WHERE id = $id AND series_id = $series_id");
        }
        echo json_encode(['success' => true]);
        exit;
    }
    // نظام الحفظ السريع للأزرار الجديدة (بدون إعادة تحميل) مع حل مشكلة الخطأ الوهمي
    if ($_GET['ajax_action'] == 'toggle_status') {
        ob_clean(); // مسح أي مخرجات سابقة لمنع أخطاء الـ JSON
        header('Content-Type: application/json');
        $ep_id = intval($_POST['ep_id']);
        $field = $_POST['field']; 
        $value = intval($_POST['value']);
        
        if (in_array($field, ['is_published', 'show_in_latest'])) {
            $conn->query("UPDATE episodes SET $field = $value WHERE id = $ep_id AND series_id = $series_id");
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Invalid field']);
        }
        exit;
    }
}

$message = ''; $message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. تحديث حلقة
    if (isset($_POST['update_episode'])) {
        $id = intval($_POST['episode_id']); 
        $link1 = trim($_POST['watch_link']);
        $link2 = trim($_POST['watch_link_2']);
        $link3 = trim($_POST['watch_link_3']);
        $link4 = trim($_POST['watch_link_4']);
        $download_link = trim($_POST['download_link'] ?? '');
        $download_link_2 = trim($_POST['download_link_2'] ?? '');
        $num = intval($_POST['episode_number']); 
        $title = trim($_POST['title']) ?: "الحلقة " . $num;
        $is_pub = isset($_POST['is_published']) ? 1 : 0;
        $show_lat = isset($_POST['show_in_latest']) ? 1 : 0;
        
        $stmt = $conn->prepare("UPDATE episodes SET title=?, watch_link=?, watch_link_2=?, watch_link_3=?, watch_link_4=?, download_link=?, download_link_2=?, episode_number=?, is_published=?, show_in_latest=? WHERE id=? AND series_id=?");
        $stmt->bind_param("sssssssiiiii", $title, $link1, $link2, $link3, $link4, $download_link, $download_link_2, $num, $is_pub, $show_lat, $id, $series_id);
        if ($stmt->execute()) { 
            $message = 'تم تحديث الحلقة بنجاح.'; $message_type = 'success'; 
            echo "<script>setTimeout(() => { window.location.href='index.php?page=episodes&series_id=$series_id'; }, 1000);</script>";
        }
    }

    // 2. إضافة حلقة
    if (isset($_POST['add_episode'])) {
        $link1 = trim($_POST['watch_link']); 
        $link2 = trim($_POST['watch_link_2']);
        $link3 = trim($_POST['watch_link_3']);
        $link4 = trim($_POST['watch_link_4']);
        $download_link = trim($_POST['download_link'] ?? '');
        $download_link_2 = trim($_POST['download_link_2'] ?? '');
        $num = intval($_POST['episode_number']);
        $title = trim($_POST['title']) ?: "الحلقة " . $num;
        $is_pub = isset($_POST['is_published']) ? 1 : 0;
        $show_lat = isset($_POST['show_in_latest']) ? 1 : 0;
        
        $check = $conn->query("SELECT id FROM episodes WHERE series_id = $series_id AND episode_number = $num");
        if ($check->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE episodes SET title=?, watch_link=?, watch_link_2=?, watch_link_3=?, watch_link_4=?, download_link=?, download_link_2=?, is_published=?, show_in_latest=? WHERE series_id=? AND episode_number=?");
            $stmt->bind_param("sssssssiiii", $title, $link1, $link2, $link3, $link4, $download_link, $download_link_2, $is_pub, $show_lat, $series_id, $num);
            if ($stmt->execute()) { 
                $message = 'تم تحديث روابط الحلقة الموجودة مسبقاً.'; $message_type = 'success'; 
                echo "<script>setTimeout(() => { window.location.href='index.php?page=episodes&series_id=$series_id'; }, 1000);</script>";
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO episodes (series_id, title, watch_link, watch_link_2, watch_link_3, watch_link_4, download_link, download_link_2, episode_number, is_published, show_in_latest) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssssssiii", $series_id, $title, $link1, $link2, $link3, $link4, $download_link, $download_link_2, $num, $is_pub, $show_lat);
            if ($stmt->execute()) { 
                $new_ep_id = $conn->insert_id;
                if (isset($_POST['silent_add']) && $new_ep_id > 0) {
                    $conn->query("UPDATE episodes SET created_at = DATE_SUB(NOW(), INTERVAL 2 MONTH) WHERE id = $new_ep_id");
                }
                $message = 'تمت إضافة الحلقة بنجاح.'; $message_type = 'success'; 
                echo "<script>setTimeout(() => { window.location.href='index.php?page=episodes&series_id=$series_id'; }, 1000);</script>";
            }
        }
    }

    // 3. حذف حلقة واحدة
    if (isset($_POST['delete_episode'])) {
        $id = intval($_POST['episode_id']); 
        $conn->query("DELETE FROM episodes WHERE id = $id AND series_id = $series_id");
        $message = 'تم حذف الحلقة بنجاح.'; $message_type = 'success';
        echo "<script>setTimeout(() => { window.location.href='index.php?page=episodes&series_id=$series_id'; }, 1000);</script>";
    }

    // 4. حذف جماعي
    if (isset($_POST['delete_bulk']) && isset($_POST['episode_ids'])) {
        $ids = array_map('intval', $_POST['episode_ids']);
        if (!empty($ids)) {
            $ids_string = implode(',', $ids);
            $conn->query("DELETE FROM episodes WHERE series_id = $series_id AND id IN ($ids_string)");
            $message = 'تم حذف الحلقات المختارة بنجاح.'; $message_type = 'success';
            echo "<script>setTimeout(() => { window.location.href='index.php?page=episodes&series_id=$series_id'; }, 1000);</script>";
        }
    }

    // 5. إضافة جماعية (محدثة لتدعم وضع الأرقام فوق الروابط ونسخ الرابط لسيرفر 1 والتحميل 1)
    if (isset($_POST['add_bulk_episodes'])) {
        // تنظيف النص من أي فواصل أسطر غريبة
        $raw_text = str_replace("\r", "", $_POST['bulk_links']);
        $lines = explode("\n", $raw_text); 
        $start = intval($_POST['start_number']); 
        $count = 0;
        
        // تجهيز الاستعلام لإدخال الرابط في watch_link و download_link معاً
        $stmt = $conn->prepare("INSERT INTO episodes (series_id, title, watch_link, download_link, episode_number) VALUES (?, ?, ?, ?, ?)");
        
        $current_ep_num = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue; // تجاهل الأسطر الفارغة

            // إذا كان السطر عبارة عن رقم الحلقة (طوله صغير ومكون من أرقام فقط)
            if (is_numeric($line) && strlen($line) <= 5) {
                $current_ep_num = intval($line);
                continue; // انتقل للسطر التالي الذي يحتوي على الرابط
            }

            // --- التعديل الذكي لحل مشكلة ظهور رقم الحلقة قبل الجودة ---
            // فحص إذا كان السطر يبدأ برقم ثم مسافة ثم الرابط المتعدد
            // مثال: "8 360|https..."
            if (preg_match('/^(\d+)\s+(.+)$/', $line, $matches)) {
                $n = intval($matches[1]); // استخراج رقم الحلقة (مثال: 8)
                $url = trim($matches[2]); // استخراج الرابط الصافي النظيف بدون الرقم (مثال: 360|https...)
            } else {
                // إذا كان السطر يحتوي على الرابط فقط بدون رقم الحلقة في بدايته
                $n = ($current_ep_num !== null) ? $current_ep_num : ($start + $count); 
                $url = $line;
            }

            $t = "الحلقة " . $n; 

            // تنفيذ الإدخال: وضع الرابط النظيف ($url) في خانة السيرفر الأول وخانة التحميل الأولى
            $stmt->bind_param("isssi", $series_id, $t, $url, $url, $n); 
            
            if($stmt->execute()) {
                $new_ep_id = $conn->insert_id;
                if (isset($_POST['silent_add_bulk']) && $new_ep_id > 0) {
                    $conn->query("UPDATE episodes SET created_at = DATE_SUB(NOW(), INTERVAL 2 MONTH) WHERE id = $new_ep_id");
                }
                $count++; 
            }
            
            // تصفير رقم الحلقة لتجهيز قراءة الحلقة التالية
            $current_ep_num = null;
        }
        $message = "تمت إضافة $count حلقة بنجاح."; $message_type = 'success';
        echo "<script>setTimeout(() => { window.location.href='index.php?page=episodes&series_id=$series_id'; }, 1000);</script>";
    }
}

$series_q = $conn->query("SELECT * FROM series WHERE id = $series_id LIMIT 1");
$series = $series_q->fetch_assoc();

$stats_q = $conn->query("SELECT COUNT(id) as eps_count, IFNULL(SUM(views), 0) as eps_views FROM episodes WHERE series_id = $series_id")->fetch_assoc();
$top_ep = $conn->query("SELECT episode_number, title, views FROM episodes WHERE series_id = $series_id ORDER BY views DESC LIMIT 1")->fetch_assoc();

$chart_labels = [];
$chart_data = [];
$ep_chart_q = $conn->query("SELECT episode_number, views FROM episodes WHERE series_id = $series_id ORDER BY episode_number ASC");
while($r = $ep_chart_q->fetch_assoc()) {
    $chart_labels[] = "حلقة " . $r['episode_number'];
    $chart_data[] = $r['views'];
}

$action = $_GET['action'] ?? 'view';
?>

<script src="https://cdn.plyr.io/3.7.8/plyr.js"></script>
<link rel="stylesheet" href="https://cdn.plyr.io/3.7.8/plyr.css" />
<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .player-preview-error { display: none; background: #dc2626; color: white; padding: 10px; border-radius: 5px; margin-bottom: 10px; text-align: center; }
    .server-label { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-weight: bold; color: #DAA520; }
    .form-textarea { background: #1a1a1a; border: 1px solid #333; color: white; width: 100%; border-radius: 8px; padding: 10px; outline: none; transition: 0.3s; }
    .form-textarea:focus { border-color: #DAA520; }
    .checkbox-custom { width: 18px; height: 18px; cursor: pointer; accent-color: #DAA520; }
    
    .smart-extractor-box {
        background: linear-gradient(145deg, #111827, #0F0F0F);
        border: 1px dashed #3b82f6; border-radius: 16px; padding: 20px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5); transition: all 0.3s ease;
    }
    .smart-extractor-box:focus-within { border-color: #DAA520; box-shadow: 0 0 20px rgba(218,165,32,0.1); }
    
    .guest-name-input { background-color: #fffbeb !important; border-color: #f59e0b !important; color: #000 !important; font-weight: bold !important; }
    .guest-name-input::placeholder { color: #854d0e; }
    
    .drag-handle { cursor: grab; color: #6b7280; font-size: 1.2rem; padding: 0.5rem; }
    .drag-handle:active { cursor: grabbing; }
    .sortable-ghost { opacity: 0.4; background-color: #1e293b; }

    /* تصميم أزرار الإخفاء والظهور (الجديد والأنيق) */
    .toggle-switch-wrapper { display: flex; align-items: center; cursor: pointer; user-select: none; }
    .toggle-switch { width: 44px; height: 24px; background-color: #374151; border-radius: 9999px; position: relative; transition: background-color 0.3s ease; box-shadow: inset 0 2px 4px rgba(0,0,0,0.3); }
    .toggle-switch::after { content: ''; position: absolute; top: 2px; right: 2px; width: 20px; height: 20px; background-color: white; border-radius: 50%; transition: transform 0.3s cubic-bezier(0.4, 0.0, 0.2, 1); box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
    .toggle-input:checked + .toggle-switch.pub { background-color: #10B981; }
    .toggle-input:checked + .toggle-switch.latest { background-color: #F59E0B; }
    .toggle-input:checked + .toggle-switch::after { transform: translateX(-20px); }
    .toggle-label { margin-right: 10px; font-size: 13px; font-weight: bold; color: #D1D5DB; transition: color 0.3s ease; }
    .toggle-switch-wrapper:hover .toggle-label { color: white; }
    
    /* تصميم توست الإشعارات */
    .custom-toast { position: fixed; bottom: 20px; right: 20px; background: #3b82f6; color: white; padding: 12px 24px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); z-index: 9999; font-weight: bold; transform: translateY(100px); opacity: 0; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    .custom-toast.show { transform: translateY(0); opacity: 1; }
    .custom-toast.error { background: #ef4444; }
</style>

<div class="flex items-center justify-between mb-8">
    <div class="flex items-center gap-6">
        <?php 
        $back_link = ($action == 'edit') ? "index.php?page=episodes&series_id=$series_id" : "index.php?page=series&category=" . ($series['category'] ?? 'series'); 
        ?>
        <a href="<?php echo $back_link; ?>" class="bg-[#0F0F0F] w-12 h-12 flex items-center justify-center rounded-xl border border-[#1F1F1F] text-[#DAA520] hover:bg-[#1A1A1A] transition-all shadow-lg"><i class="fas fa-arrow-right"></i></a>
        <div>
            <div class="text-xs text-gray-500 mb-1">الرئيسية <i class="fas fa-chevron-left text-[8px] mx-1"></i> إدارة الحلقات والمشاهدات</div>
            <h1 class="text-2xl font-black text-white">تفاصيل: <span class="text-[#DAA520]"><?php echo htmlspecialchars($series['title']); ?></span></h1>
        </div>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="mb-6 p-4 rounded-xl text-center font-bold shadow-lg <?php echo $message_type == 'error' ? 'bg-red-500/10 text-red-500 border-red-500/20' : 'bg-green-500/10 text-green-500 border-green-500/20'; ?> border">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<?php if ($action == 'view'): ?>
<div class="grid grid-cols-1 xl:grid-cols-12 gap-8 mb-10">
    <div class="xl:col-span-7 bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl relative overflow-hidden flex flex-col md:flex-row gap-6">
        <div class="absolute -right-20 -top-20 w-64 h-64 bg-[#DAA520] opacity-5 rounded-full blur-[80px] pointer-events-none"></div>
        
        <div class="flex-shrink-0 relative z-10 w-32 h-48 mx-auto md:mx-0">
            <img src="<?php echo (filter_var($series['poster'], FILTER_VALIDATE_URL)) ? $series['poster'] : '../'.$series['poster']; ?>" class="w-full h-full object-cover rounded-xl shadow-lg border border-white/10" onerror="this.src='https://placehold.co/300x450?text=Poster'">
        </div>
        
        <div class="flex-1 w-full relative z-10">
            <div class="flex items-center gap-3 mb-2">
                <h2 class="text-2xl font-black text-[#DAA520]"><?php echo htmlspecialchars($series['title']); ?></h2>
                <span class="bg-white/10 text-gray-300 text-xs px-2 py-1 rounded font-bold"><?php echo htmlspecialchars($series['year']); ?></span>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-6">
                <div class="bg-[#151515] p-4 rounded-xl border border-[#222]">
                    <div class="text-xs text-gray-500 font-bold mb-1"><i class="fas fa-eye text-blue-400 ml-1"></i> مشاهدات المسلسل</div>
                    <div class="text-xl font-black text-white"><?php echo number_format($series['views'] ?? 0); ?></div>
                </div>
                
                <div class="bg-[#151515] p-4 rounded-xl border border-[#222]">
                    <div class="text-xs text-gray-500 font-bold mb-1"><i class="fas fa-play-circle text-emerald-400 ml-1"></i> مشاهدات الحلقات</div>
                    <div class="text-xl font-black text-white"><?php echo number_format($stats_q['eps_views']); ?></div>
                </div>

                <div class="bg-[#151515] p-4 rounded-xl border border-[#222]">
                    <div class="text-xs text-gray-500 font-bold mb-1"><i class="fas fa-fire-alt text-red-500 ml-1"></i> أعلى حلقة</div>
                    <div class="text-sm font-black text-white mt-1">حلقة <?php echo $top_ep['episode_number'] ?? '-'; ?> <span class="text-[10px] text-gray-500 mr-1">(<?php echo number_format($top_ep['views'] ?? 0); ?>)</span></div>
                </div>

                <div class="bg-[#151515] p-4 rounded-xl border border-[#222]">
                    <div class="text-xs text-gray-500 font-bold mb-1"><i class="fas fa-list-ol text-purple-400 ml-1"></i> عدد الحلقات</div>
                    <div class="text-xl font-black text-white"><?php echo $stats_q['eps_count']; ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="xl:col-span-5 bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl flex flex-col">
        <h3 class="text-lg font-black text-white mb-4"><i class="fas fa-chart-bar text-blue-400 ml-2"></i> تحليل الأداء</h3>
        <div class="flex-1 relative min-h-[200px] w-full">
            <?php if(empty($chart_data)): ?>
                <div class="absolute inset-0 flex items-center justify-center text-gray-500 text-sm font-bold">لا توجد حلقات لعرض تحليلها.</div>
            <?php else: ?>
                <canvas id="epViewsChart"></canvas>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if(!empty($chart_data)): ?>
<script>
    const ctxEp = document.getElementById('epViewsChart').getContext('2d');
    new Chart(ctxEp, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                label: 'عدد المشاهدات',
                data: <?php echo json_encode($chart_data); ?>,
                backgroundColor: 'rgba(218, 165, 32, 0.8)',
                borderColor: '#DAA520',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { titleFont: { family: 'Cairo' }, bodyFont: { family: 'Cairo' } } },
            scales: { y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#888' } }, x: { grid: { display: false }, ticks: { color: '#888', font: { family: 'Cairo', size: 10 } } } }
        }
    });
</script>
<?php endif; ?>
<?php endif; ?>

<?php if ($action == 'edit' && isset($_GET['id'])): 
    $ep = $conn->query("SELECT * FROM episodes WHERE id = ".intval($_GET['id']))->fetch_assoc();
?>
    <div class="bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl">
        <h2 class="text-xl font-bold mb-8">تعديل الحلقة</h2>
        
        <div class="smart-extractor-box mb-8">
            <h3 class="text-lg font-bold mb-2 text-[#3b82f6]"><i class="fas fa-bolt ml-2"></i>تحديث السيرفرات تلقائياً</h3>
            <p class="text-xs text-gray-400 mb-4">انسخ كود المصدر <kbd class="bg-gray-800 px-1 rounded">Ctrl+U</kbd> لموقع المشاهدة والصقه هنا لتحديث الروابط فوراً.</p>
            <div class="flex flex-col md:flex-row gap-2">
                <input type="text" id="edit_html_paste" placeholder="الصق كود HTML هنا..." class="form-input flex-1 w-full !py-3">
                <button type="button" onclick="extractServersInstant('edit_html_paste', 'watch_link_1', 'watch_link_2', 'watch_link_3', 'watch_link_4')" class="btn bg-[#3b82f6] hover:bg-blue-600 text-white font-bold px-6 py-3 w-full md:w-auto">
                    <i class="fas fa-magic"></i> استخراج
                </button>
            </div>
        </div>

        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <input type="hidden" name="episode_id" value="<?php echo $ep['id']; ?>">
            
            <div class="md:col-span-2 flex flex-col sm:flex-row gap-6 bg-[#151515] p-5 rounded-xl border border-[#222]">
                <label class="toggle-switch-wrapper">
                    <input type="checkbox" name="is_published" value="1" class="toggle-input sr-only" <?php echo (!isset($ep['is_published']) || $ep['is_published'] == 1) ? 'checked' : ''; ?>>
                    <div class="toggle-switch pub"></div>
                    <span class="toggle-label">نشر الحلقة (إظهار عام)</span>
                </label>
                
                <div class="hidden sm:block w-px h-8 bg-gray-700"></div>

                <label class="toggle-switch-wrapper">
                    <input type="checkbox" name="show_in_latest" value="1" class="toggle-input sr-only" <?php echo (!isset($ep['show_in_latest']) || $ep['show_in_latest'] == 1) ? 'checked' : ''; ?>>
                    <div class="toggle-switch latest"></div>
                    <span class="toggle-label">إظهار في أحدث الإضافات</span>
                </label>
            </div>

            <div>
                <label class="block text-sm text-gray-500 mb-2">رقم الحلقة</label>
                <input type="number" name="episode_number" value="<?php echo $ep['episode_number']; ?>" class="form-input w-full" required>
            </div>
            <div>
                <label class="block text-sm text-amber-500 font-bold mb-2">اسم الضيف / عنوان الحلقة</label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($ep['title']); ?>" placeholder="مثال: ضيف الحلقة محمد صلاح" class="form-input guest-name-input w-full">
            </div>
            
            <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <div class="server-label text-green-400"><i class="fas fa-server"></i> سيرفر المشاهدة 1</div>
                    <textarea name="watch_link" id="watch_link_1" class="form-textarea" rows="2" required><?php echo $ep['watch_link']; ?></textarea>
                    <button type="button" onclick="previewSvr(1)" class="btn btn-secondary btn-sm mt-2 w-full"><i class="fas fa-eye"></i> معاينة 1</button>
                </div>
                <div>
                    <div class="server-label text-blue-400"><i class="fas fa-server"></i> سيرفر المشاهدة 2</div>
                    <textarea name="watch_link_2" id="watch_link_2" class="form-textarea" rows="2"><?php echo $ep['watch_link_2']; ?></textarea>
                    <button type="button" onclick="previewSvr(2)" class="btn btn-secondary btn-sm mt-2 w-full"><i class="fas fa-eye"></i> معاينة 2</button>
                </div>
                <div>
                    <div class="server-label text-purple-400"><i class="fas fa-server"></i> سيرفر المشاهدة 3</div>
                    <textarea name="watch_link_3" id="watch_link_3" class="form-textarea" rows="2"><?php echo $ep['watch_link_3']; ?></textarea>
                    <button type="button" onclick="previewSvr(3)" class="btn btn-secondary btn-sm mt-2 w-full"><i class="fas fa-eye"></i> معاينة 3</button>
                </div>
                <div>
                    <div class="server-label text-amber-500"><i class="fas fa-server"></i> سيرفر المشاهدة 4</div>
                    <textarea name="watch_link_4" id="watch_link_4" class="form-textarea" rows="2"><?php echo $ep['watch_link_4']; ?></textarea>
                    <button type="button" onclick="previewSvr(4)" class="btn btn-secondary btn-sm mt-2 w-full"><i class="fas fa-eye"></i> معاينة 4</button>
                </div>
            </div>

            <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
                <div>
                    <div class="server-label text-emerald-400"><i class="fas fa-download"></i> رابط التحميل 1</div>
                    <input type="text" name="download_link" value="<?php echo $ep['download_link'] ?? ''; ?>" class="form-input w-full" placeholder="رابط التحميل الأول...">
                </div>
                <div>
                    <div class="server-label text-cyan-400"><i class="fas fa-download"></i> رابط التحميل 2</div>
                    <input type="text" name="download_link_2" value="<?php echo $ep['download_link_2'] ?? ''; ?>" class="form-input w-full" placeholder="رابط التحميل الثاني...">
                </div>
            </div>

            <div id="preview-area" class="md:col-span-2 mt-4" style="display: none;">
                <div class="player-preview-error" id="preview-error"></div>
                <div id="player-render" class="aspect-video bg-black rounded-lg overflow-hidden border border-[#DAA520]"></div>
            </div>

            <div class="md:col-span-2 flex flex-col md:flex-row justify-end gap-4 border-t border-[#1F1F1F] pt-8">
                <a href="index.php?page=episodes&series_id=<?php echo $series_id; ?>" class="btn btn-secondary px-10 text-center w-full md:w-auto">إلغاء</a>
                <button type="submit" name="update_episode" class="btn btn-primary px-16 w-full md:w-auto">تحديث الحلقة</button>
            </div>
        </form>
    </div>

<?php else: ?>
    <!-- قسم المستخرج الذكي وإضافة حلقة -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-12 items-start">
        
        <div class="lg:col-span-5 lg:sticky lg:top-4 lg:z-40 lg:self-start">
            <div class="smart-extractor-box">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 rounded-full bg-blue-500/20 flex items-center justify-center text-[#3b82f6] text-xl"><i class="fas fa-bolt"></i></div>
                    <h2 class="text-xl font-bold text-[#3b82f6]">المستخرج الذكي</h2>
                </div>
                <p class="text-xs text-gray-400 mb-4 pl-14">انسخ كود الصفحة بالكامل (Ctrl+U) والصقه هنا لتعبئة الروابط الأربعة.</p>
                <textarea id="smart_html_paste" placeholder="الصق الكود هنا..." class="form-textarea w-full !h-24 mb-3" rows="3"></textarea>
                <button type="button" onclick="extractServersInstant('smart_html_paste', 'add_link_1', 'add_link_2', 'add_link_3', 'add_link_4')" class="btn bg-[#3b82f6] hover:bg-blue-600 text-white font-bold w-full shadow-lg">
                    <i class="fas fa-magic ml-2"></i> استخراج وتعبئة تلقائية
                </button>
            </div>
            
            <div class="bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl mt-6">
                <h2 class="text-md font-bold mb-4 text-gray-400"><i class="fas fa-list-ol ml-2"></i>إضافة متعددة (روابط سريعة)</h2>
                <form method="POST" class="space-y-4">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                        <input type="number" name="start_number" value="1" class="form-input w-full sm:flex-1" placeholder="رقم البداية">
                        <label class="flex items-center justify-center gap-2 cursor-pointer bg-gray-800 px-3 py-2 w-full sm:w-auto rounded-lg whitespace-nowrap border border-gray-700 hover:border-brand-gold transition-colors">
                            <input type="checkbox" name="silent_add_bulk" value="1" class="w-4 h-4 accent-amber-500">
                            <span class="text-xs font-bold text-gray-300">🤫 صامتة</span>
                        </label>
                    </div>
                    <textarea name="bulk_links" placeholder="يمكنك وضع رقم الحلقة ثم مسافة ثم الرابط متعدد الجودات... (مثال: 1 360|http...)" class="form-textarea w-full" rows="6" required></textarea>
                    <button type="submit" name="add_bulk_episodes" class="btn btn-secondary w-full py-2 text-sm">إضافة الكل</button>
                </form>
            </div>
        </div>

        <div class="lg:col-span-7 bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl">
            <h2 class="text-xl font-bold mb-6 text-[#DAA520]"><i class="fas fa-plus-circle ml-2"></i> إضافة حلقة جديدة</h2>
            <form method="POST" class="space-y-4">
                
                <div class="flex flex-col sm:flex-row gap-6 bg-[#151515] p-4 rounded-xl border border-[#222] mb-4">
                    <label class="toggle-switch-wrapper">
                        <input type="checkbox" name="is_published" value="1" class="toggle-input sr-only" checked>
                        <div class="toggle-switch pub"></div>
                        <span class="toggle-label">نشر الحلقة</span>
                    </label>
                    
                    <div class="hidden sm:block w-px h-6 bg-gray-700"></div>

                    <label class="toggle-switch-wrapper">
                        <input type="checkbox" name="show_in_latest" value="1" class="toggle-input sr-only" checked>
                        <div class="toggle-switch latest"></div>
                        <span class="toggle-label">إظهار بأحدث الإضافات</span>
                    </label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="w-full">
                        <input type="number" name="episode_number" id="episode_number_input" placeholder="رقم الحلقة" class="form-input w-full" required>
                        <div id="duplicate-warning" class="hidden text-xs text-red-500 mt-1 font-bold animate-pulse"><i class="fas fa-exclamation-triangle"></i> هذه الحلقة مسجلة! سيتم استبدال الروابط.</div>
                    </div>
                    <div class="w-full">
                        <input type="text" name="title" placeholder="اسم الضيف لبرامج رامز (اختياري)" class="form-input guest-name-input w-full">
                    </div>
                </div>
                
                <div class="bg-blue-900/10 border border-blue-500/20 p-3 rounded-lg mt-2">
                    <label class="flex items-center gap-2 cursor-pointer w-fit group">
                        <input type="checkbox" name="silent_add" value="1" class="w-5 h-5 accent-blue-500">
                        <span class="text-sm font-bold text-gray-300 group-hover:text-blue-400 transition-colors">🤫 إضافة صامتة (حفظ بتاريخ قديم)</span>
                    </label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs text-green-400 font-bold block mb-1">سيرفر 1 (أساسي)</label>
                        <textarea name="watch_link" id="add_link_1" placeholder="رابط سيرفر 1..." class="form-textarea w-full" rows="2" required></textarea>
                    </div>
                    <div>
                        <label class="text-xs text-blue-400 font-bold block mb-1">سيرفر 2</label>
                        <textarea name="watch_link_2" id="add_link_2" placeholder="رابط سيرفر 2..." class="form-textarea w-full" rows="2"></textarea>
                    </div>
                    <div>
                        <label class="text-xs text-purple-400 font-bold block mb-1">سيرفر 3</label>
                        <textarea name="watch_link_3" id="add_link_3" placeholder="رابط سيرفر 3..." class="form-textarea w-full" rows="2"></textarea>
                    </div>
                    <div>
                        <label class="text-xs text-amber-500 font-bold block mb-1">سيرفر 4</label>
                        <textarea name="watch_link_4" id="add_link_4" placeholder="رابط سيرفر 4..." class="form-textarea w-full" rows="2"></textarea>
                    </div>
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 mb-2">
                    <button type="button" onclick="previewAdd(1)" class="btn btn-secondary border-blue-500/30 hover:bg-blue-500/10 text-xs py-2 w-full">معاينة 1</button>
                    <button type="button" onclick="previewAdd(2)" class="btn btn-secondary border-blue-500/30 hover:bg-blue-500/10 text-xs py-2 w-full">معاينة 2</button>
                    <button type="button" onclick="previewAdd(3)" class="btn btn-secondary border-blue-500/30 hover:bg-blue-500/10 text-xs py-2 w-full">معاينة 3</button>
                    <button type="button" onclick="previewAdd(4)" class="btn btn-secondary border-blue-500/30 hover:bg-blue-500/10 text-xs py-2 w-full">معاينة 4</button>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="text-xs text-emerald-400 font-bold block mb-1"><i class="fas fa-download"></i> رابط التحميل 1</label>
                        <input type="text" name="download_link" id="add_download_link" placeholder="رابط التحميل الأول..." class="form-input w-full">
                    </div>
                    <div>
                        <label class="text-xs text-cyan-400 font-bold block mb-1"><i class="fas fa-download"></i> رابط التحميل 2</label>
                        <input type="text" name="download_link_2" id="add_download_link_2" placeholder="رابط التحميل الثاني..." class="form-input w-full">
                    </div>
                </div>

                <div id="preview-area-add" style="display:none;" class="mt-4">
                    <div id="player-render-add" class="aspect-video bg-black rounded-lg overflow-hidden border border-[#DAA520]"></div>
                </div>
                <button type="submit" name="add_episode" class="btn btn-primary w-full py-4 text-lg mt-6 shadow-[0_0_15px_rgba(218,165,32,0.3)]">
                    <i class="fas fa-save ml-2"></i> حفظ الحلقة
                </button>
            </form>
        </div>

    </div>

    <div class="bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl">
        <form method="POST" id="bulk-delete-form">
            <div class="mb-4 flex flex-col md:flex-row gap-4 justify-between md:items-center">
                <h2 class="text-xl font-bold text-white"><i class="fas fa-list ml-2"></i> قائمة الحلقات <span class="text-xs text-gray-500 font-normal">(الترتيب بالسحب والإفلات)</span></h2>
                <button type="button" onclick="smartConfirm(this, 'bulk-delete-form')" class="btn bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-bold transition-all w-full md:w-auto">
                    <i class="fas fa-trash-alt ml-1"></i> حذف المحدد
                </button>
            </div>

            <div class="table-responsive w-full overflow-x-auto">
                <table class="content-table w-full min-w-[700px]" id="episodes-table">
                    <thead>
                        <tr>
                            <th width="30"></th>
                            <th width="40"><input type="checkbox" id="select-all-checkbox" class="checkbox-custom"></th>
                            <th width="100">الحلقة</th>
                            <th>العنوان / الضيف</th>
                            <th class="text-center">المشاهدات</th>
                            <th class="text-center">حالة السيرفرات</th>
                            <th class="text-center">حالة الظهور</th>
                            <th class="text-center">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="sortable-episodes">
                        <?php
                        $res = $conn->query("SELECT * FROM episodes WHERE series_id = $series_id ORDER BY episode_number ASC");
                        while($row = $res->fetch_assoc()):
                            
                            // *** التعديل تم هنا (إضافة ?? '') لمنع الخطأ عند وجود قيمة فارغة ***
                            $links_count = (!empty(trim($row['watch_link'] ?? '')) ? 1 : 0) +
                                           (!empty(trim($row['watch_link_2'] ?? '')) ? 1 : 0) +
                                           (!empty(trim($row['watch_link_3'] ?? '')) ? 1 : 0) +
                                           (!empty(trim($row['watch_link_4'] ?? '')) ? 1 : 0);
                            
                            if ($links_count >= 3) {
                                $status_badge = '<span class="bg-green-500/10 text-green-500 border border-green-500/20 px-3 py-1 rounded-full text-[11px] font-bold"><i class="fas fa-check-double ml-1"></i> ممتاز ('.$links_count.')</span>';
                            } elseif ($links_count >= 1) {
                                $status_badge = '<span class="bg-yellow-500/10 text-yellow-500 border border-yellow-500/20 px-3 py-1 rounded-full text-[11px] font-bold"><i class="fas fa-check ml-1"></i> جيد ('.$links_count.')</span>';
                            } else {
                                $status_badge = '<span class="bg-red-500/10 text-red-500 border border-red-500/20 px-3 py-1 rounded-full text-[11px] font-bold"><i class="fas fa-times ml-1"></i> بدون روابط</span>';
                            }

                            // جلب حالة أزرار الظهور
                            $is_published = (!isset($row['is_published']) || $row['is_published'] == 1);
                            $show_in_latest = (!isset($row['show_in_latest']) || $row['show_in_latest'] == 1);
                        ?>
                        <tr data-id="<?php echo $row['id']; ?>" class="episode-row group">
                            <td class="text-center"><i class="fas fa-bars drag-handle opacity-50 group-hover:opacity-100 transition-opacity"></i></td>
                            <td class="text-center"><input type="checkbox" name="episode_ids[]" value="<?php echo $row['id']; ?>" class="episode-checkbox checkbox-custom"></td>
                            <td class="font-black text-xl text-[#DAA520] ep-number-cell text-center"><?php echo $row['episode_number']; ?></td>
                            <td class="font-bold text-gray-200"><?php echo htmlspecialchars($row['title']); ?></td>
                            <td class="text-center">
                                <span class="bg-blue-500/10 text-blue-400 font-bold px-3 py-1 rounded-full text-xs">
                                    <?php echo number_format($row['views'] ?? 0); ?> <i class="fas fa-eye ml-1"></i>
                                </span>
                            </td>
                            <td class="text-center"><?php echo $status_badge; ?></td>
                            
                            <td class="text-center">
                                <div class="flex flex-col gap-3 items-center justify-center">
                                    <label class="toggle-switch-wrapper" title="إظهار/إخفاء الحلقة بالكامل">
                                      <input type="checkbox" class="toggle-input sr-only" onchange="toggleEpStatus(<?php echo $row['id']; ?>, 'is_published', this)" <?php echo $is_published ? 'checked' : ''; ?>>
                                      <div class="toggle-switch pub" style="width: 36px; height: 20px;"></div>
                                      <span class="toggle-label" style="font-size: 11px; min-width: 65px; text-align: right; margin-right: 8px;">ظهور عام</span>
                                    </label>
                                    
                                    <label class="toggle-switch-wrapper" title="إظهار/إخفاء من أحدث الإضافات">
                                      <input type="checkbox" class="toggle-input sr-only" onchange="toggleEpStatus(<?php echo $row['id']; ?>, 'show_in_latest', this)" <?php echo $show_in_latest ? 'checked' : ''; ?>>
                                      <div class="toggle-switch latest" style="width: 36px; height: 20px;"></div>
                                      <span class="toggle-label" style="font-size: 11px; min-width: 65px; text-align: right; margin-right: 8px;">أحدث الإضافات</span>
                                    </label>
                                </div>
                            </td>

                            <td>
                                <div class="flex justify-center gap-2">
                                    <a href="index.php?page=episodes&series_id=<?php echo $series_id; ?>&action=edit&id=<?php echo $row['id']; ?>" class="btn btn-secondary px-3 py-1 text-sm text-blue-400"><i class="fas fa-edit"></i></a>
                                    <button type="button" onclick="smartConfirm(this, 'delete_single_<?php echo $row['id']; ?>', true)" class="btn btn-secondary px-3 py-1 text-sm text-red-500"><i class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <input type="hidden" name="delete_bulk" value="1">
        </form>

        <?php 
        $res->data_seek(0);
        while($row = $res->fetch_assoc()): 
        ?>
        <form id="delete_single_<?php echo $row['id']; ?>" method="POST" style="display:none;">
            <input type="hidden" name="episode_id" value="<?php echo $row['id']; ?>">
            <input type="hidden" name="delete_episode" value="1">
        </form>
        <?php endwhile; ?>
    </div>
<?php endif; ?>

<script>
// وظيفة الإشعارات المخصصة بدلاً من Alert
function showCustomToast(msg, isError = false) {
    const toast = document.createElement('div');
    toast.className = `custom-toast ${isError ? 'error' : ''}`;
    toast.innerHTML = `<i class="fas ${isError ? 'fa-exclamation-circle' : 'fa-check-circle'} ml-2"></i> ${msg}`;
    document.body.appendChild(toast);
    
    setTimeout(() => { toast.classList.add('show'); }, 100);
    setTimeout(() => { 
        toast.classList.remove('show'); 
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// وظيفة التأكيد المخصصة بدلاً من Confirm
function smartConfirm(btn, formId, isIconOnly = false) {
    if (btn.dataset.confirmed === 'yes') {
        document.getElementById(formId).submit();
    } else {
        btn.dataset.confirmed = 'yes';
        const originalContent = btn.innerHTML;
        const originalClass = btn.className;
        
        btn.innerHTML = isIconOnly ? '<i class="fas fa-exclamation-triangle"></i>' : '<i class="fas fa-exclamation-triangle ml-1"></i> تأكيد؟';
        btn.classList.add('bg-red-800');
        
        setTimeout(() => {
            if (btn.dataset.confirmed === 'yes') {
                btn.dataset.confirmed = 'no';
                btn.innerHTML = originalContent;
                btn.className = originalClass;
            }
        }, 3000);
    }
}

function toggleEpStatus(epId, field, checkboxElement) {
    const value = checkboxElement.checked ? 1 : 0;
    const formData = new FormData();
    formData.append('ep_id', epId);
    formData.append('field', field);
    formData.append('value', value);

    const originalOpacity = checkboxElement.nextElementSibling.style.opacity;
    checkboxElement.nextElementSibling.style.opacity = '0.5';

    fetch('index.php?page=episodes&series_id=<?php echo $series_id; ?>&ajax_action=toggle_status', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.json();
    })
    .then(data => {
        checkboxElement.nextElementSibling.style.opacity = originalOpacity;
        if(!data.success) {
            console.error('Server error:', data.msg);
            showCustomToast('حدث خطأ أثناء حفظ الإعدادات', true);
            checkboxElement.checked = !checkboxElement.checked;
        }
    })
    .catch(error => {
        checkboxElement.nextElementSibling.style.opacity = originalOpacity;
        console.error('Error:', error);
        showCustomToast('حدث خطأ في الاتصال. تأكد من جودة الإنترنت.', true);
        checkboxElement.checked = !checkboxElement.checked;
    });
}

const epInput = document.getElementById('episode_number_input');
if (epInput) {
    let timeout = null;
    epInput.addEventListener('input', function() {
        clearTimeout(timeout);
        const warning = document.getElementById('duplicate-warning');
        const num = this.value;
        if (!num) { warning.classList.add('hidden'); return; }
        
        timeout = setTimeout(async () => {
            const res = await fetch(`index.php?page=episodes&series_id=<?php echo $series_id; ?>&ajax_action=check_duplicate&num=${num}`);
            const data = await res.json();
            if (data.exists) {
                warning.classList.remove('hidden');
                epInput.classList.add('border-red-500');
            } else {
                warning.classList.add('hidden');
                epInput.classList.remove('border-red-500');
            }
        }, 300);
    });
}

const sortableList = document.getElementById('sortable-episodes');
if (sortableList) {
    new Sortable(sortableList, {
        handle: '.drag-handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        onEnd: function () {
            const rows = document.querySelectorAll('.episode-row');
            let orderData = [];
            
            rows.forEach((row, index) => {
                let newNum = index + 1; 
                row.querySelector('.ep-number-cell').innerText = newNum;
                orderData.push({ id: row.getAttribute('data-id'), num: newNum });
            });

            const formData = new FormData();
            orderData.forEach((item, i) => {
                formData.append(`order[${i}][id]`, item.id);
                formData.append(`order[${i}][num]`, item.num);
            });

            fetch('index.php?page=episodes&series_id=<?php echo $series_id; ?>&ajax_action=reorder', {
                method: 'POST', body: formData
            }).catch(e => console.error("Error reordering:", e));
        }
    });
}

function extractServersInstant(inputId, t1, t2, t3, t4) {
    const htmlText = document.getElementById(inputId).value;
    if(!htmlText.trim()) { showCustomToast("يرجى لصق كود الـ HTML أولاً.", true); return; }

    let extractedLinks = [];
    let match;

    const arabseedRegex = /data-link=["']([^"']+)["']/g;
    while ((match = arabseedRegex.exec(htmlText)) !== null) {
        let rawStr = match[1]; let base64Code = rawStr;
        if(rawStr.startsWith('link:')) base64Code = rawStr.substring(5);
        try {
            let decodedUrl = atob(base64Code);
            if(decodedUrl.startsWith('http')) extractedLinks.push(decodedUrl);
        } catch(e) {}
    }

    if(extractedLinks.length === 0) {
        const iframeRegex = /<iframe[^>]+src=["']([^"']+)["']/g;
        while ((match = iframeRegex.exec(htmlText)) !== null) {
            extractedLinks.push(match[1]);
        }
    }

    if (extractedLinks.length > 0) {
        extractedLinks = [...new Set(extractedLinks)];
        const fields = [t1, t2, t3, t4];
        let assignedCount = 0;
        
        for (let i = 0; i < Math.min(extractedLinks.length, 4); i++) {
            const field = document.getElementById(fields[i]);
            if (field) { field.value = extractedLinks[i]; assignedCount++; }
        }
        
        showCustomToast("تم استخراج وتعبئة " + assignedCount + " سيرفر/سيرفرات بنجاح!");
        document.getElementById(inputId).value = '';
    } else {
        showCustomToast("لم يتم العثور على أي سيرفرات مشاهدة (iframe أو data-link).", true);
    }
}

const selectAllCheck = document.getElementById('select-all-checkbox');
if(selectAllCheck) {
    selectAllCheck.addEventListener('change', function(e) {
        const checkboxes = document.querySelectorAll('.episode-checkbox');
        checkboxes.forEach(cb => cb.checked = e.target.checked);
    });
}

let playerInstance = null;
function setupPlayer(videoSrc, containerId, errorId) {
    const renderArea = document.getElementById(containerId);
    const errorDiv = document.getElementById(errorId);
    
    if(playerInstance) { playerInstance.destroy(); playerInstance = null; }
    renderArea.innerHTML = ''; errorDiv.style.display = 'none';

    if (!videoSrc) {
        errorDiv.textContent = 'الرابط فارغ!'; errorDiv.style.display = 'block';
        return;
    }

    try {
        if(videoSrc.includes('youtube.com') || videoSrc.includes('youtu.be')) {
            renderArea.innerHTML = `<div class="plyr__video-embed" id="player"><iframe src="${videoSrc}?origin=https://plyr.io&amp;iv_load_policy=3&amp;modestbranding=1&amp;playsinline=1&amp;showinfo=0&amp;rel=0&amp;enablejsapi=1" allowfullscreen allowtransparency allow="autoplay"></iframe></div>`;
            playerInstance = new Plyr('#player', { controls: ['play-large', 'play', 'progress', 'current-time', 'mute', 'volume', 'fullscreen'] });
        }
        else if (videoSrc.endsWith('.mp4') || videoSrc.endsWith('.webm') || videoSrc.endsWith('.ogg')) {
            renderArea.innerHTML = `<video id="player" playsinline controls data-poster=""><source src="${videoSrc}" type="video/mp4" /></video>`;
            playerInstance = new Plyr('#player', { controls: ['play-large', 'play', 'progress', 'current-time', 'mute', 'volume', 'fullscreen'] });
        }
        else if (videoSrc.endsWith('.m3u8')) {
            renderArea.innerHTML = `<video id="player" playsinline controls></video>`;
            const video = document.getElementById('player');
            if (Hls.isSupported()) {
                const hls = new Hls(); hls.loadSource(videoSrc); hls.attachMedia(video);
                hls.on(Hls.Events.MANIFEST_PARSED, function() {
                    playerInstance = new Plyr(video, { controls: ['play-large', 'play', 'progress', 'current-time', 'mute', 'volume', 'settings', 'fullscreen'] });
                });
                hls.on(Hls.Events.ERROR, function(event, data) {
                    if (data.fatal) { errorDiv.textContent = 'خطأ في تشغيل HLS'; errorDiv.style.display = 'block'; }
                });
            } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                video.src = videoSrc;
                playerInstance = new Plyr(video);
            }
        }
        else if(videoSrc.includes('|')) {
            const qualities = videoSrc.split(',');
            let sourcesHTML = '';
            qualities.forEach(q => {
                const parts = q.split('|');
                if(parts.length === 2) sourcesHTML += `<source src="${parts[1]}" type="video/mp4" size="${parts[0]}">`;
            });
            renderArea.innerHTML = `<video id="player" playsinline controls>${sourcesHTML}</video>`;
            playerInstance = new Plyr('#player', { controls: ['play-large', 'play', 'progress', 'current-time', 'mute', 'volume', 'settings', 'fullscreen'], settings: ['quality', 'speed'], quality: { default: qualities[0].split('|')[0], options: qualities.map(q => q.split('|')[0]) } });
        }
        else {
            renderArea.innerHTML = `<iframe src="${videoSrc}" class="w-full h-full border-0" allowfullscreen allow="autoplay"></iframe>`;
        }
    } catch(e) {
        errorDiv.textContent = 'حدث خطأ في المشغل: ' + e.message;
        errorDiv.style.display = 'block';
    }
}

function previewSvr(num) {
    document.getElementById('preview-area').style.display = 'block';
    const link = document.getElementById('watch_link_' + num).value;
    setupPlayer(link, 'player-render', 'preview-error');
}

function previewAdd(num) {
    document.getElementById('preview-area-add').style.display = 'block';
    const link = document.getElementById('add_link_' + num).value;
    setupPlayer(link, 'player-render-add', 'preview-error'); 
}
</script>