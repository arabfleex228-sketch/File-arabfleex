<?php
// التأكد من وجود الاتصال بقاعدة البيانات
if (!isset($conn)) { require_once '../db_config.php'; }
if (session_status() == PHP_SESSION_NONE) { session_start(); }

$message = '';
$message_type = '';

// جلب الإعدادات الحالية من قاعدة البيانات
$ad_settings = ['status' => 0, 'popunder_code' => '', 'native_banner' => '', 'social_bar' => '', 'direct_link' => '', 'top_banner' => '', 'bottom_banner' => '', 'api_key' => ''];
$ads_res = $conn->query("SELECT * FROM ads_settings WHERE id = 1 LIMIT 1");
if ($ads_res && $row = $ads_res->fetch_assoc()) {
    $ad_settings = array_merge($ad_settings, $row);
}

// حفظ الإعدادات عند الضغط على زر التحديث
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ads'])) {
    $status = isset($_POST['status']) ? intval($_POST['status']) : 0;
    $popunder_code = trim($_POST['popunder_code']);
    $native_banner = trim($_POST['native_banner']);
    $social_bar = trim($_POST['social_bar']);
    $direct_link = trim($_POST['direct_link']);
    $top_banner = trim($_POST['top_banner']);
    $bottom_banner = trim($_POST['bottom_banner']);
    $api_key = trim($_POST['api_key']); 

    // تحديث قاعدة البيانات
    $stmt = $conn->prepare("UPDATE ads_settings SET status=?, popunder_code=?, native_banner=?, social_bar=?, direct_link=?, top_banner=?, bottom_banner=?, api_key=? WHERE id=1");
    $stmt->bind_param("isssssss", $status, $popunder_code, $native_banner, $social_bar, $direct_link, $top_banner, $bottom_banner, $api_key);
    
    if ($stmt->execute()) {
        $message = "تم حفظ إعدادات الإعلانات بنجاح!";
        $message_type = "success";
        
        $ad_settings['status'] = $status;
        $ad_settings['popunder_code'] = $popunder_code;
        $ad_settings['native_banner'] = $native_banner;
        $ad_settings['social_bar'] = $social_bar;
        $ad_settings['direct_link'] = $direct_link;
        $ad_settings['top_banner'] = $top_banner;
        $ad_settings['bottom_banner'] = $bottom_banner;
        $ad_settings['api_key'] = $api_key;
    } else {
        $message = "حدث خطأ أثناء التحديث: " . $conn->error;
        $message_type = "error";
    }
    $stmt->close();
}

// ------------------------------------------------------------------
// جلب إحصائيات Adsterra (أرباح الشهر + إحصائيات اليوم)
// ------------------------------------------------------------------
$stats_today = ['revenue' => 0.00, 'clicks' => 0, 'impressions' => 0];
$monthly_revenue = 0.00;
$api_error_message = '';

if (!empty($ad_settings['api_key'])) {
    
    $today = gmdate('Y-m-d'); 
    $first_day_of_month = gmdate('Y-m-01'); // أول يوم في الشهر الحالي
    
    $headers = [
        "X-API-Key: " . $ad_settings['api_key'],
        "Accept: application/json"
    ];

    // ==========================================
    // 1. جلب إحصائيات اليوم
    // ==========================================
    $url_today = "https://api3.adsterratools.com/publisher/stats.json?start_date={$today}&finish_date={$today}&group_by=date";
    $ch_today = curl_init();
    curl_setopt($ch_today, CURLOPT_URL, $url_today);
    curl_setopt($ch_today, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_today, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch_today, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch_today, CURLOPT_SSL_VERIFYPEER, false);

    $res_today = curl_exec($ch_today);
    $code_today = curl_getinfo($ch_today, CURLINFO_HTTP_CODE);

    if (!curl_errno($ch_today) && $code_today == 200) {
        $data_today = json_decode($res_today, true);
        if (isset($data_today['items']) && is_array($data_today['items'])) {
            foreach ($data_today['items'] as $item) {
                $stats_today['revenue'] += floatval($item['revenue'] ?? 0);
                $stats_today['clicks'] += intval($item['clicks'] ?? 0);
                $stats_today['impressions'] += intval($item['impressions'] ?? 0);
            }
        }
    } elseif ($code_today == 401 || $code_today == 403) {
        $api_error_message = "خطأ في التوثيق: الرمز المميز (X-API-Key) غير صحيح.";
    } elseif ($code_today == 422) {
        $api_error_message = "خطأ 422: البيانات المرسلة غير متطابقة مع متطلبات Adsterra.";
    } else {
        $api_error_message = "تعذر الاتصال بخوادم Adsterra.";
    }
    curl_close($ch_today);

    // ==========================================
    // 2. جلب أرباح الشهر الحالي
    // ==========================================
    if (empty($api_error_message)) {
        $url_month = "https://api3.adsterratools.com/publisher/stats.json?start_date={$first_day_of_month}&finish_date={$today}&group_by=date";
        $ch_month = curl_init();
        curl_setopt($ch_month, CURLOPT_URL, $url_month);
        curl_setopt($ch_month, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_month, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch_month, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch_month, CURLOPT_SSL_VERIFYPEER, false);

        $res_month = curl_exec($ch_month);
        if (!curl_errno($ch_month) && curl_getinfo($ch_month, CURLINFO_HTTP_CODE) == 200) {
            $data_month = json_decode($res_month, true);
            if (isset($data_month['items']) && is_array($data_month['items'])) {
                foreach ($data_month['items'] as $item) {
                    $monthly_revenue += floatval($item['revenue'] ?? 0);
                }
            }
        }
        curl_close($ch_month);
    }

} else {
    $api_error_message = "يرجى إدخال مفتاح الربط (X-API-Key) لعرض إحصائياتك.";
}
?>

<div class="section-header flex justify-between items-center mb-6">
    <h1 class="section-title"><i class="fas fa-ad text-[var(--brand-gold)] ml-2"></i>إدارة الإعلانات والإحصائيات</h1>
</div>

<?php if (!empty($message)): ?>
    <div class="mb-6 p-4 rounded-lg flex items-center gap-3 font-bold <?php echo $message_type === 'success' ? 'bg-green-500/10 text-green-400 border border-green-500/30' : 'bg-red-500/10 text-red-400 border border-red-500/30'; ?>">
        <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> text-xl"></i>
        <span><?php echo $message; ?></span>
    </div>
<?php endif; ?>

<div class="panel-box max-w-5xl mx-auto">
    
    <!-- قسم إحصائيات Adsterra -->
    <div class="mb-8 border-b border-[var(--border-color)] pb-6">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 rounded-full bg-[#18C99B]/10 border border-[#18C99B]/30 flex items-center justify-center text-xl text-[#18C99B] shadow-[0_0_15px_rgba(24,201,155,0.15)]">
                <i class="fas fa-chart-line"></i>
            </div>
            <div>
                <h2 class="text-xl font-black text-white">إحصائيات Adsterra</h2>
                <p class="text-gray-400 text-xs mt-1">يتم جلب أرباحك وإحصائياتك مباشرة من حسابك في Adsterra.</p>
            </div>
        </div>

        <?php if (!empty($api_error_message)): ?>
            <div class="bg-red-500/10 border border-red-500/30 rounded-xl p-4 text-center">
                <p class="text-red-400 text-sm font-bold"><i class="fas fa-info-circle ml-1"></i> <?php echo $api_error_message; ?></p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                
                <!-- أرباح الشهر (البديل الذكي للرصيد الكلي) -->
                <div class="bg-black/40 border border-[var(--brand-gold)]/30 rounded-xl p-5 relative overflow-hidden flex flex-col items-center justify-center text-center shadow-[0_0_15px_rgba(218,165,32,0.1)]">
                    <div class="absolute -right-4 -top-4 text-[var(--brand-gold)] opacity-5 text-6xl"><i class="fas fa-calendar-check"></i></div>
                    <span class="text-gray-400 text-xs font-bold mb-2">أرباح الشهر (This Month)</span>
                    <span class="text-3xl font-black text-[var(--brand-gold)]">$<?php echo number_format($monthly_revenue, 2); ?></span>
                </div>

                <!-- الأرباح اليومية -->
                <div class="bg-black/40 border border-[#18C99B]/30 rounded-xl p-5 relative overflow-hidden flex flex-col items-center justify-center text-center">
                    <div class="absolute -right-4 -top-4 text-[#18C99B] opacity-5 text-6xl"><i class="fas fa-dollar-sign"></i></div>
                    <span class="text-gray-400 text-xs font-bold mb-2">أرباح اليوم (Today)</span>
                    <span class="text-3xl font-black text-[#18C99B]">$<?php echo number_format($stats_today['revenue'], 2); ?></span>
                </div>
                
                <!-- المشاهدات -->
                <div class="bg-black/40 border border-blue-500/30 rounded-xl p-5 relative overflow-hidden flex flex-col items-center justify-center text-center">
                    <div class="absolute -right-4 -top-4 text-blue-500 opacity-5 text-6xl"><i class="fas fa-eye"></i></div>
                    <span class="text-gray-400 text-xs font-bold mb-2">مشاهدات اليوم (Impressions)</span>
                    <span class="text-3xl font-black text-blue-400"><?php echo number_format($stats_today['impressions']); ?></span>
                </div>

                <!-- النقرات -->
                <div class="bg-black/40 border border-purple-500/30 rounded-xl p-5 relative overflow-hidden flex flex-col items-center justify-center text-center">
                    <div class="absolute -right-4 -top-4 text-purple-500 opacity-5 text-6xl"><i class="fas fa-hand-pointer"></i></div>
                    <span class="text-gray-400 text-xs font-bold mb-2">نقرات اليوم (Clicks)</span>
                    <span class="text-3xl font-black text-purple-400"><?php echo number_format($stats_today['clicks']); ?></span>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- قسم الأكواد والإعدادات -->
    <div class="flex items-center gap-3 mb-8 border-b border-[var(--border-color)] pb-4">
        <div class="w-10 h-10 rounded-full bg-black/40 border border-[var(--border-color)] flex items-center justify-center text-xl text-[var(--brand-gold)] shadow-[0_0_15px_rgba(218,165,32,0.15)]">
            <i class="fas fa-code"></i>
        </div>
        <div>
            <h2 class="text-xl font-black text-white">إعدادات الأكواد والربط</h2>
            <p class="text-gray-400 text-xs mt-1">ضع الأكواد الإعلانية ومفتاح الربط الخاص بك هنا.</p>
        </div>
    </div>

    <form method="POST" action="">
        
        <!-- إعداد الـ API -->
        <div class="mb-6 bg-black/20 p-5 rounded-xl border border-[var(--border-color)] hover:border-[#18C99B] transition-colors relative">
            <label class="flex items-center gap-2 text-[#18C99B] font-black text-sm mb-3">
                <i class="fas fa-key"></i> مفتاح الربط (X-API-Key)
            </label>
            <input type="text" name="api_key" value="<?php echo htmlspecialchars($ad_settings['api_key']); ?>" class="w-full bg-black/60 border border-[var(--border-color)] focus:border-[#18C99B] rounded-lg p-3 text-sm font-mono text-left text-gray-300 outline-none transition-all shadow-inner" dir="ltr" placeholder="ex: a1b2c3d4e5f6...">
            <p class="text-[10px] text-gray-500 mt-2"><i class="fas fa-info-circle ml-1"></i> ضع الرمز المميز (Token) المنسوخ من حسابك هنا.</p>
        </div>

        <!-- زر تفعيل وإيقاف الإعلانات بالكامل -->
        <div class="mb-8 bg-black/30 border border-[var(--border-color)] rounded-xl p-5 relative overflow-hidden">
            <h3 class="font-bold text-sm text-gray-300 text-center mb-4">الحالة العامة للإعلانات في الموقع</h3>
            <div class="flex justify-center items-center gap-4">
                <label class="cursor-pointer flex items-center gap-2 px-8 py-2.5 rounded-full transition-all border <?php echo $ad_settings['status'] == 1 ? 'bg-[var(--brand-gold)] border-[var(--brand-gold)] text-black shadow-[0_0_15px_rgba(218,165,32,0.4)]' : 'bg-black/50 border-[var(--border-color)] text-gray-400 hover:text-white'; ?>">
                    <input type="radio" name="status" value="1" <?php echo $ad_settings['status'] == 1 ? 'checked' : ''; ?> class="hidden status-radio">
                    <i class="fas fa-check-circle"></i> <span class="font-black">مفعل</span>
                </label>
                
                <label class="cursor-pointer flex items-center gap-2 px-8 py-2.5 rounded-full transition-all border <?php echo $ad_settings['status'] == 0 ? 'bg-red-500 border-red-500 text-white shadow-[0_0_15px_rgba(239,68,68,0.4)]' : 'bg-black/50 border-[var(--border-color)] text-gray-400 hover:text-white'; ?>">
                    <input type="radio" name="status" value="0" <?php echo $ad_settings['status'] == 0 ? 'checked' : ''; ?> class="hidden status-radio">
                    <i class="fas fa-times-circle"></i> <span class="font-black">متوقف</span>
                </label>
            </div>
        </div>

        <div class="space-y-6">
            
            <!-- Popunder Code -->
            <div class="bg-black/20 p-4 rounded-xl border border-[var(--border-color)] hover:border-[var(--brand-gold)] transition-colors">
                <label class="flex items-center gap-2 text-[var(--brand-gold)] font-black text-sm mb-3">
                    <i class="fas fa-external-link-alt"></i> Popunder Code (النافذة المنبثقة)
                </label>
                <textarea name="popunder_code" rows="3" class="w-full bg-black/60 border border-[var(--border-color)] focus:border-[var(--brand-gold)] rounded-lg p-3 text-xs font-mono text-left text-gray-300 outline-none transition-all shadow-inner" dir="ltr" placeholder="<script type='text/javascript' src='...'></script>"><?php echo htmlspecialchars($ad_settings['popunder_code']); ?></textarea>
            </div>

            <!-- Direct Link -->
            <div class="bg-black/20 p-4 rounded-xl border border-[var(--border-color)] hover:border-green-400 transition-colors">
                <label class="flex items-center gap-2 text-green-400 font-bold text-sm mb-3">
                    <i class="fas fa-link"></i> Direct Link (الرابط المباشر)
                </label>
                <textarea name="direct_link" rows="2" class="w-full bg-black/60 border border-[var(--border-color)] focus:border-green-400 rounded-lg p-3 text-xs font-mono text-left text-gray-300 outline-none transition-all shadow-inner" dir="ltr" placeholder="https://..."><?php echo htmlspecialchars($ad_settings['direct_link']); ?></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Native Banner Code -->
                <div class="bg-black/20 p-4 rounded-xl border border-[var(--border-color)] hover:border-blue-400 transition-colors">
                    <label class="flex items-center gap-2 text-blue-400 font-bold text-sm mb-3">
                        <i class="fas fa-image"></i> Native Banner
                    </label>
                    <textarea name="native_banner" rows="3" class="w-full bg-black/60 border border-[var(--border-color)] focus:border-blue-400 rounded-lg p-3 text-xs font-mono text-left text-gray-300 outline-none transition-all shadow-inner" dir="ltr" placeholder="كود إعلانات المقالات..."><?php echo htmlspecialchars($ad_settings['native_banner']); ?></textarea>
                </div>

                <!-- Social Bar Code -->
                <div class="bg-black/20 p-4 rounded-xl border border-[var(--border-color)] hover:border-pink-400 transition-colors">
                    <label class="flex items-center gap-2 text-pink-400 font-bold text-sm mb-3">
                        <i class="fas fa-comment-dots"></i> Social Bar
                    </label>
                    <textarea name="social_bar" rows="3" class="w-full bg-black/60 border border-[var(--border-color)] focus:border-pink-400 rounded-lg p-3 text-xs font-mono text-left text-gray-300 outline-none transition-all shadow-inner" dir="ltr" placeholder="كود إشعارات السوشيال..."><?php echo htmlspecialchars($ad_settings['social_bar']); ?></textarea>
                </div>

                <!-- Top Banner -->
                <div class="bg-black/20 p-4 rounded-xl border border-[var(--border-color)] hover:border-gray-300 transition-colors">
                    <label class="flex items-center gap-2 text-gray-300 font-bold text-sm mb-3">
                        <i class="fas fa-rectangle-wide"></i> Top Banner (728x90)
                    </label>
                    <textarea name="top_banner" rows="3" class="w-full bg-black/60 border border-[var(--border-color)] focus:border-gray-400 rounded-lg p-3 text-xs font-mono text-left text-gray-300 outline-none transition-all shadow-inner" dir="ltr" placeholder="كود البانر العلوي..."><?php echo htmlspecialchars($ad_settings['top_banner']); ?></textarea>
                </div>

                <!-- Bottom Banner -->
                <div class="bg-black/20 p-4 rounded-xl border border-[var(--border-color)] hover:border-gray-300 transition-colors">
                    <label class="flex items-center gap-2 text-gray-300 font-bold text-sm mb-3">
                        <i class="fas fa-window-minimize"></i> Bottom Banner (300x250)
                    </label>
                    <textarea name="bottom_banner" rows="3" class="w-full bg-black/60 border border-[var(--border-color)] focus:border-gray-400 rounded-lg p-3 text-xs font-mono text-left text-gray-300 outline-none transition-all shadow-inner" dir="ltr" placeholder="كود البانر السفلي..."><?php echo htmlspecialchars($ad_settings['bottom_banner']); ?></textarea>
                </div>
            </div>

        </div>

        <!-- زر الحفظ -->
        <div class="mt-8 pt-6 border-t border-[var(--border-color)]">
            <button type="submit" name="update_ads" class="w-full bg-[var(--brand-gold)] hover:bg-yellow-500 text-black font-black py-3.5 rounded-lg transition-all shadow-[0_5px_20px_rgba(218,165,32,0.2)] transform hover:-translate-y-1 flex items-center justify-center gap-2 text-lg">
                <i class="fas fa-save"></i> حفظ التعديلات 
            </button>
        </div>

    </form>
</div>

<script>
    const labels = document.querySelectorAll('label:has(.status-radio)');
    const radios = document.querySelectorAll('.status-radio');

    radios.forEach(radio => {
        radio.addEventListener('change', function() {
            labels.forEach(l => {
                l.className = 'cursor-pointer flex items-center gap-2 px-8 py-2.5 rounded-full transition-all border bg-black/50 border-[var(--border-color)] text-gray-400 hover:text-white';
            });
            
            if(this.value === '1') {
                this.parentElement.className = 'cursor-pointer flex items-center gap-2 px-8 py-2.5 rounded-full transition-all border bg-[var(--brand-gold)] border-[var(--brand-gold)] text-black shadow-[0_0_15px_rgba(218,165,32,0.4)]';
            } else {
                this.parentElement.className = 'cursor-pointer flex items-center gap-2 px-8 py-2.5 rounded-full transition-all border bg-red-500 border-red-500 text-white shadow-[0_0_15px_rgba(239,68,68,0.4)]';
            }
        });
    });
</script>