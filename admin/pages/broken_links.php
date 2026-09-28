<?php
// معالج طلبات AJAX
if (isset($_GET['action']) && $_GET['action'] == 'ajax_check') {
    // فتح الـ Output Buffer لمنع أي رسائل خطأ من تخريب الـ JSON
    ob_start();
    
    $url = $_POST['url'] ?? '';
    $url = trim($url);
    
    // استخراج الرابط النظيف من كود الـ iframe أو الـ embed إن وجد
    if (preg_match('/src=["\']([^"\']+)["\']/i', $url, $matches)) {
        $url = trim($matches[1]);
    }

    // التحقق من أن الرابط يبدأ بـ http أو https
    if(empty($url) || $url == '#' || !preg_match('/^https?:\/\//i', $url)){
         ob_end_clean();
         echo json_encode(['status' => 'broken', 'message' => 'رابط مفقود أو غير صالح']);
         exit;
    }

    // فحص متطور ومحمي باستخدام cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true); 
    curl_setopt($ch, CURLOPT_NOBODY, true); // فحص الهيدر فقط للسرعة
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3); // تتبع التحويلات
    curl_setopt($ch, CURLOPT_TIMEOUT, 8); 
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // العديد من سيرفرات الفيديو ترفض فحص HEAD وتعطي خطأ 405 أو 400
    // في هذه الحالة نغير وضع الفحص إلى GET ولكن نحمل أول 2048 بايت فقط لتسريع الفحص
    if ($httpCode >= 400 || $httpCode == 0) {
        curl_setopt($ch, CURLOPT_NOBODY, false); 
        curl_setopt($ch, CURLOPT_RANGE, '0-2048'); 
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    }
    
    curl_close($ch);

    ob_end_clean(); // مسح أي تحذيرات PHP للحفاظ على نظافة الـ JSON

    // إذا كان السيرفر يرفض الفحص الآلي (يعطي 403 أو 405) فهو غالباً يعمل ولكنه محمي نعتبره يعمل.
    if (($httpCode >= 200 && $httpCode < 400) || $httpCode == 403 || $httpCode == 405 || $httpCode == 401) {
        echo json_encode(['status' => 'active', 'message' => 'يعمل']);
    } else {
        echo json_encode(['status' => 'broken', 'message' => "معطل (كود: $httpCode)"]);
    }
    exit;
}

$view_all = isset($_GET['view']) && $_GET['view'] == 'all'; 
?>

<div class="section-header">
    <h1 class="section-title text-3xl font-black text-white">
        <i class="fas fa-tools text-red-500 mr-2"></i> مركز فحص السيرفرات والصيانة
    </h1>
    <div class="flex flex-wrap gap-2 mt-2 md:mt-0">
         <button id="scan-all-btn" onclick="startAutomatedScan()" class="btn bg-purple-600 hover:bg-purple-700 text-white font-bold btn-sm shadow-lg">
             <i class="fas fa-robot ml-1"></i> فحص تلقائي لجميع سيرفرات الصفحة
         </button>
         <a href="index.php?page=broken_links&view=all" class="btn btn-secondary btn-sm"><i class="fas fa-eye"></i> عرض كل السيرفرات</a>
         <a href="index.php?page=broken_links" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> تصفية المشاكل فقط</a>
    </div>
</div>

<div class="mb-4 text-xs text-gray-400 bg-blue-900/20 border border-blue-500/30 p-3 rounded-lg">
    <i class="fas fa-info-circle text-blue-400 ml-1"></i> تم برمجة هذا الفاحص الذكي ليدعم السيرفرات المباشرة، وروابط التضمين (Embed/Iframe)، وتخطي حماية المواقع لضمان نتيجة فحص دقيقة.
</div>

<!-- شريط التقدم للفحص التلقائي -->
<div id="scan-progress-container" class="hidden mb-6 bg-[#0f172a] p-4 rounded-xl border border-purple-500/50 shadow-lg">
    <div class="flex justify-between items-center mb-3">
        <h3 class="font-bold text-purple-400 flex items-center gap-2">
            <i class="fas fa-circle-notch fa-spin"></i> <span id="scan-status-text">جاري فحص السيرفرات تلقائياً...</span>
        </h3>
        <span class="font-black text-lg text-white" id="scan-counter">0 / 0</span>
    </div>
    <div class="w-full bg-gray-800 rounded-full h-3 overflow-hidden border border-gray-700">
        <div id="scan-progress-bar" class="bg-gradient-to-r from-purple-600 to-pink-500 h-full rounded-full transition-all duration-300 shadow-[0_0_10px_rgba(168,85,247,0.5)]" style="width: 0%"></div>
    </div>
</div>

<div class="space-y-10">
    <!-- الأفلام -->
    <div class="bg-[#0f172a] p-6 rounded-2xl border border-[#1e293b] shadow-xl">
        <h2 class="text-xl font-bold mb-6 text-red-400 border-b border-[#1e293b] pb-3">
            <i class="fas fa-film ml-2"></i> أفلام بها مشاكل أو سيرفرات معطلة
        </h2>
        <div class="table-responsive">
            <table class="content-table w-full">
                <thead><tr><th class="text-right">الفيلم</th><th class="text-right">سيرفر 1</th><th>فحص السيرفر</th><th>إجراء</th></tr></thead>
                <tbody>
                <?php
                $movies = $conn->query("SELECT id, title, watch_link, poster, description FROM movies ORDER BY created_at DESC");
                $m_count = 0;
                while($m = $movies->fetch_assoc()):
                    $issues = [];
                    
                    // تنظيف الرابط للفحص
                    $clean_link = trim($m['watch_link']);
                    if (preg_match('/src=["\']([^"\']+)["\']/i', $clean_link, $matches)) {
                        $clean_link = trim($matches[1]);
                    }

                    if (empty($clean_link) || $clean_link == '#' || !preg_match('/^https?:\/\//i', $clean_link)) $issues[] = "رابط مفقود أو غير صالح";
                    if (empty($m['poster'])) $issues[] = "بوستر مفقود";
                    if (strlen($m['description'] ?? '') < 20) $issues[] = "وصف ناقص";
                    
                    if (!empty($issues) || $view_all): $m_count++;
                ?>
                    <tr class="hover:bg-white/5 transition-colors">
                        <td class="font-bold text-sm text-gray-200">
                            <?php echo htmlspecialchars($m['title']); ?>
                            <div class="mt-1">
                                <?php foreach($issues as $i){ echo "<span class='bg-red-500/10 text-red-400 px-2 py-0.5 rounded text-[10px] ml-1 border border-red-500/20'>$i</span>"; } ?>
                            </div>
                        </td>
                        <td class="text-left text-[10px] text-gray-500 truncate max-w-[200px]" dir="ltr">
                            <?php echo htmlspecialchars($m['watch_link']); ?>
                        </td>
                        <td id="m-stat-<?php echo $m['id']; ?>" class="text-center">
                            <?php if(!empty($clean_link) && preg_match('/^https?:\/\//i', $clean_link)): ?>
                                <button data-scan="true" data-type="movie" data-id="<?php echo $m['id']; ?>" data-url="<?php echo htmlspecialchars($m['watch_link']); ?>" onclick='checkLink("movie", <?php echo $m["id"]; ?>, <?php echo json_encode($m["watch_link"]); ?>)' class="btn bg-[#1e293b] hover:bg-[#334155] border border-[#334155] text-white btn-sm py-1 px-4 text-xs font-bold rounded-lg shadow-sm">
                                    <i class="fas fa-stethoscope ml-1"></i> فحص
                                </button>
                            <?php else: ?>
                                <span class="text-gray-500 text-xs">لا يوجد سيرفر صالح</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <a href="index.php?page=movies&action=edit&id=<?php echo $m['id']; ?>" class="btn bg-blue-500/10 text-blue-400 border border-blue-500/20 hover:bg-blue-500 hover:text-white btn-sm text-xs rounded-lg transition-colors">إصلاح</a>
                        </td>
                    </tr>
                <?php endif; endwhile; 
                if($m_count == 0) echo "<tr><td colspan='4' class='text-center py-8 text-green-500 font-bold bg-green-500/5'><i class='fas fa-check-circle text-2xl block mb-2 opacity-50'></i> لا توجد مشاكل في سيرفرات الأفلام.</td></tr>";
                ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- الحلقات -->
    <div class="bg-[#0f172a] p-6 rounded-2xl border border-[#1e293b] shadow-xl">
        <h2 class="text-xl font-bold mb-6 text-amber-400 border-b border-[#1e293b] pb-3">
            <i class="fas fa-list-ol ml-2"></i> حلقات مسلسلات سيرفراتها معطلة
        </h2>
        <div class="table-responsive">
            <table class="content-table w-full">
                <thead><tr><th class="text-right">المسلسل - الحلقة</th><th class="text-right">سيرفر 1</th><th>فحص السيرفر</th><th>إجراء</th></tr></thead>
                <tbody>
                <?php
                $episodes = $conn->query("SELECT e.id, e.series_id, e.title as ep_title, s.title as s_title, e.watch_link FROM episodes e JOIN series s ON e.series_id = s.id ORDER BY e.id DESC");
                $e_count = 0;
                while($e = $episodes->fetch_assoc()):
                    
                    // استخراج الرابط سواء كان Embed أو Iframe أو مباشر
                    $clean_link = trim($e['watch_link']);
                    if (preg_match('/src=["\']([^"\']+)["\']/i', $clean_link, $matches)) {
                        $clean_link = trim($matches[1]);
                    }
                    
                    $is_broken = empty($clean_link) || $clean_link == '#' || !preg_match('/^https?:\/\//i', $clean_link);
                    
                    if ($is_broken || $view_all): $e_count++;
                ?>
                    <tr class="hover:bg-white/5 transition-colors">
                        <td class="text-sm">
                            <span class="text-gray-400 text-[10px] bg-gray-800 px-2 py-0.5 rounded-md inline-block mb-1"><?php echo $e['s_title']; ?></span><br>
                            <strong class="text-gray-200"><?php echo $e['ep_title']; ?></strong>
                            <?php if($is_broken): ?> <div class="mt-1"><span class='bg-red-500/10 text-red-400 px-2 py-0.5 rounded text-[10px] border border-red-500/20'>سيرفر مفقود أو غير صالح</span></div> <?php endif; ?>
                        </td>
                        <td class="text-left text-[10px] text-gray-500 truncate max-w-[200px]" dir="ltr">
                            <?php echo htmlspecialchars($e['watch_link']); ?>
                        </td>
                        <td id="e-stat-<?php echo $e['id']; ?>" class="text-center">
                            <?php if(!$is_broken): ?>
                                <button data-scan="true" data-type="ep" data-id="<?php echo $e['id']; ?>" data-url="<?php echo htmlspecialchars($e['watch_link']); ?>" onclick='checkLink("ep", <?php echo $e["id"]; ?>, <?php echo json_encode($e["watch_link"]); ?>)' class="btn bg-[#1e293b] hover:bg-[#334155] border border-[#334155] text-white btn-sm py-1 px-4 text-xs font-bold rounded-lg shadow-sm">
                                    <i class="fas fa-stethoscope ml-1"></i> فحص
                                </button>
                            <?php else: ?>
                                <span class="text-gray-500 text-xs">لا يوجد سيرفر صالح</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <a href="index.php?page=episodes&series_id=<?php echo $e['series_id']; ?>&action=edit&id=<?php echo $e['id']; ?>" class="btn bg-blue-500/10 text-blue-400 border border-blue-500/20 hover:bg-blue-500 hover:text-white btn-sm text-xs rounded-lg transition-colors">إصلاح</a>
                        </td>
                    </tr>
                <?php endif; endwhile; 
                if($e_count == 0) echo "<tr><td colspan='4' class='text-center py-8 text-green-500 font-bold bg-green-500/5'><i class='fas fa-check-circle text-2xl block mb-2 opacity-50'></i> كل الحلقات سيرفراتها جاهزة ومكتملة.</td></tr>";
                ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// دالة مساعدة ذكية جداً لاستخراج الرابط مهما كان نوعه
function extractSrcFromIframe(urlData) {
    if (!urlData || typeof urlData !== 'string') return null;
    let str = urlData.trim();
    
    // البحث عن أي src="" داخل النص (سواء كان iframe, embed, فيديو، الخ)
    const match = str.match(/src=["']([^"']+)["']/i);
    if (match && match[1]) {
        return match[1].trim();
    }
    
    // إذا لم يكن هناك src، نعيد النص كما هو على اعتبار أنه رابط مباشر
    return str; 
}

// دالة الفحص الرئيسية
async function checkLink(type, id, urlData) {
    const target = (type === 'movie' ? 'm-stat-' : 'e-stat-') + id;
    const cell = document.getElementById(target);
    cell.innerHTML = '<span class="text-blue-400 text-xs font-bold animate-pulse"><i class="fas fa-spinner fa-spin ml-1"></i> جاري...</span>';
    
    let cleanUrl = extractSrcFromIframe(urlData);
    
    // التحقق النهائي قبل الإرسال للسيرفر
    if (!cleanUrl || cleanUrl === '#' || !cleanUrl.toLowerCase().startsWith('http')) {
         cell.innerHTML = '<span class="text-red-500 bg-red-500/10 px-2 py-1 rounded text-xs font-bold border border-red-500/20">❌ غير صالح</span>';
         return 'broken';
    }

    try {
        const formData = new FormData(); 
        formData.append('url', cleanUrl);
        
        const res = await fetch('index.php?page=broken_links&action=ajax_check', { 
            method: 'POST', body: formData 
        });
        
        if (!res.ok) throw new Error("Server Error");
        
        const data = await res.json();
        
        if (data.status === 'active') {
            cell.innerHTML = '<span class="text-green-500 bg-green-500/10 px-2 py-1 rounded text-xs font-bold border border-green-500/20">✅ يعمل بكفاءة</span>';
        } else {
            cell.innerHTML = '<span class="text-red-500 bg-red-500/10 px-2 py-1 rounded text-xs font-bold border border-red-500/20">❌ ' + data.message + '</span>';
        }
        return data.status;

    } catch (e) { 
        console.error(e);
        cell.innerHTML = '<span class="text-amber-500 bg-amber-500/10 px-2 py-1 rounded text-xs font-bold border border-amber-500/20">⚠️ فشل الاتصال بالسيرفر</span>'; 
        return 'error';
    }
}

// نظام الفحص التلقائي الشامل
async function startAutomatedScan() {
    const buttons = document.querySelectorAll('button[data-scan="true"]');
    const total = buttons.length;
    
    if (total === 0) {
        alert("عذراً، لا توجد أي سيرفرات لفحصها في هذه القائمة.");
        return;
    }

    const container = document.getElementById('scan-progress-container');
    container.classList.remove('hidden');
    container.style.opacity = '0';
    setTimeout(() => container.style.opacity = '1', 10);

    const btn = document.getElementById('scan-all-btn');
    btn.disabled = true;
    btn.classList.add('opacity-50', 'cursor-not-allowed');
    
    let completed = 0;
    let brokenCount = 0;

    for (let i = 0; i < buttons.length; i++) {
        const button = buttons[i];
        const type = button.getAttribute('data-type');
        const id = button.getAttribute('data-id');
        const url = button.getAttribute('data-url');
        
        // تظليل الصف الحالي
        const row = document.getElementById((type === 'movie' ? 'm-stat-' : 'e-stat-') + id).closest('tr');
        row.style.backgroundColor = 'rgba(59, 130, 246, 0.1)';
        
        const status = await checkLink(type, id, url);
        
        row.style.backgroundColor = ''; // إزالة التظليل
        
        if (status === 'broken' || status === 'error') {
            brokenCount++;
        }

        completed++;
        const percent = Math.round((completed / total) * 100);
        document.getElementById('scan-progress-bar').style.width = percent + '%';
        document.getElementById('scan-counter').innerText = completed + ' / ' + total;
        
        // انتظار بسيط جداً لعدم خنق المتصفح أو السيرفر
        await new Promise(r => setTimeout(r, 400));
    }

    const statusText = document.getElementById('scan-status-text');
    statusText.innerHTML = '<i class="fas fa-check-circle text-green-500"></i> تم الانتهاء من فحص جميع السيرفرات!';
    document.getElementById('scan-progress-bar').classList.replace('from-purple-600', 'from-green-500');
    document.getElementById('scan-progress-bar').classList.replace('to-pink-500', 'to-emerald-400');
    
    setTimeout(() => {
        if (brokenCount > 0) {
            alert(`⚠️ اكتمل الفحص! تم العثور على ${brokenCount} سيرفرات معطلة أو لا تستجيب.`);
        } else {
            alert("✅ ممتاز! جميع السيرفرات تعمل بكفاءة.");
        }
        btn.disabled = false;
        btn.classList.remove('opacity-50', 'cursor-not-allowed');
    }, 1000);
}
</script>