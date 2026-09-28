<?php
$message = '';

// --- معالجة حفظ إعدادات الميتا تاجز ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_seo'])) {
    foreach ($_POST['seo'] as $key => $data) {
        $stmt = $conn->prepare("INSERT INTO seo_settings (page_key, title, description, keywords) 
                                VALUES (?, ?, ?, ?) 
                                ON DUPLICATE KEY UPDATE title=?, description=?, keywords=?");
        $stmt->bind_param("sssssss", $key, $data['title'], $data['description'], $data['keywords'], 
                          $data['title'], $data['description'], $data['keywords']);
        $stmt->execute();
    }
    $message = "تم حفظ إعدادات محركات البحث (SEO) بنجاح! 🚀";
}

// جلب البيانات الحالية
$seo_data = [];
$res = $conn->query("SELECT * FROM seo_settings");
while($row = $res->fetch_assoc()) { $seo_data[$row['page_key']] = $row; }

$sections = [
    'home' => ['label' => 'الصفحة الرئيسية', 'icon' => 'fa-home', 'url' => 'https://arabfleex.ct.ws/'],
    'movies' => ['label' => 'قسم الأفلام', 'icon' => 'fa-film', 'url' => 'https://arabfleex.ct.ws/all-movies/1'],
    'series' => ['label' => 'قسم المسلسلات', 'icon' => 'fa-tv', 'url' => 'https://arabfleex.ct.ws/all-series/1']
];
?>

<div class="section-header mb-6">
    <h1 class="section-title text-3xl font-black text-white flex items-center gap-3">
        <i class="fas fa-search-location text-[#DAA520]"></i> تحسين محركات البحث (SEO)
    </h1>
    <p class="text-gray-400 mt-2 text-sm">تحكم في كيفية ظهور موقعك على جوجل لتصدر نتائج البحث وجلب المزيد من الزوار.</p>
</div>

<?php if($message): ?>
    <div class="mb-8 p-4 bg-[#10b981]/10 border border-[#10b981]/30 text-[#10b981] rounded-xl text-center font-bold text-lg shadow-[0_0_15px_rgba(16,185,129,0.2)] animate-pulse">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<form method="POST" class="space-y-10">
    <?php foreach($sections as $key => $info): 
        $current = $seo_data[$key] ?? [];
        $title_val = htmlspecialchars($current['title'] ?? '', ENT_QUOTES);
        $desc_val = htmlspecialchars($current['description'] ?? '', ENT_QUOTES);
        $keys_val = htmlspecialchars($current['keywords'] ?? '', ENT_QUOTES);
    ?>
    
    <div class="bg-[#141414] rounded-2xl border border-[#262626] shadow-2xl overflow-hidden">
        <!-- هيدر القسم -->
        <div class="bg-[#1a1a1a] p-4 border-b border-[#262626] flex items-center justify-between">
            <h2 class="text-[#DAA520] font-bold text-xl flex items-center gap-2">
                <i class="fas <?php echo $info['icon']; ?>"></i> <?php echo $info['label']; ?>
            </h2>
            <span class="text-xs font-bold text-gray-500 bg-[#0A0A0A] px-3 py-1 rounded-full border border-[#262626]">
                تحديث السيو المباشر
            </span>
        </div>

        <div class="p-6 grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <!-- عمود إدخال البيانات -->
            <div class="space-y-5">
                <!-- العنوان -->
                <div>
                    <div class="flex justify-between items-end mb-2">
                        <label class="font-bold text-gray-300 block">عنوان الصفحة (Meta Title)</label>
                        <div class="flex items-center gap-2">
                            <span id="badge_title_<?php echo $key; ?>" class="text-[10px] px-2 py-0.5 rounded font-bold transition-colors">جاري الفحص</span>
                            <span class="text-xs font-bold text-gray-500 seo-counter" data-target="title_<?php echo $key; ?>" data-type="title" data-max="60">0 / 60</span>
                        </div>
                    </div>
                    <input type="text" id="title_<?php echo $key; ?>" name="seo[<?php echo $key; ?>][title]" 
                           class="w-full bg-[#0A0A0A] border border-[#333] text-white rounded-lg py-3 px-4 focus:outline-none focus:border-[#DAA520] focus:ring-1 focus:ring-[#DAA520] transition-colors seo-input-sync" 
                           data-sync-target="google-title-<?php echo $key; ?>"
                           value="<?php echo $title_val; ?>" placeholder="اكتب عنواناً جذاباً يتضمن أهم كلمة بحثية...">
                    <p class="text-[10px] text-gray-500 mt-1"><i class="fas fa-magic text-[#DAA520]"></i> نصيحة: الأفضل بين 30 إلى 60 حرف.</p>
                </div>
                
                <!-- الوصف -->
                <div>
                    <div class="flex justify-between items-end mb-2">
                        <label class="font-bold text-gray-300 block">وصف الصفحة (Meta Description)</label>
                        <div class="flex items-center gap-2">
                            <span id="badge_desc_<?php echo $key; ?>" class="text-[10px] px-2 py-0.5 rounded font-bold transition-colors">جاري الفحص</span>
                            <span class="text-xs font-bold text-gray-500 seo-counter" data-target="desc_<?php echo $key; ?>" data-type="desc" data-max="160">0 / 160</span>
                        </div>
                    </div>
                    <textarea id="desc_<?php echo $key; ?>" name="seo[<?php echo $key; ?>][description]" rows="3" 
                              class="w-full bg-[#0A0A0A] border border-[#333] text-white rounded-lg py-3 px-4 focus:outline-none focus:border-[#DAA520] focus:ring-1 focus:ring-[#DAA520] transition-colors seo-input-sync" 
                              data-sync-target="google-desc-<?php echo $key; ?>"
                              placeholder="وصف ملخص لما سيجده الزائر في هذه الصفحة..."><?php echo $desc_val; ?></textarea>
                    <p class="text-[10px] text-gray-500 mt-1"><i class="fas fa-magic text-[#DAA520]"></i> نصيحة: الأفضل بين 120 إلى 160 حرف لجذب الزائر.</p>
                </div>
                
                <!-- الكلمات المفتاحية -->
                <div>
                    <div class="flex justify-between items-end mb-2">
                        <label class="font-bold text-gray-300 block">الكلمات المفتاحية (Keywords)</label>
                        <span id="badge_keys_<?php echo $key; ?>" class="text-[10px] px-2 py-0.5 rounded font-bold transition-colors">جاري الفحص</span>
                    </div>
                    <input type="text" id="keys_<?php echo $key; ?>" name="seo[<?php echo $key; ?>][keywords]" 
                           class="w-full bg-[#0A0A0A] border border-[#333] text-white rounded-lg py-3 px-4 focus:outline-none focus:border-[#DAA520] focus:ring-1 focus:ring-[#DAA520] transition-colors seo-keys-checker" 
                           data-badge-target="badge_keys_<?php echo $key; ?>"
                           value="<?php echo $keys_val; ?>" placeholder="افلام, مسلسلات, حصري, جودة عالية (افصل بفاصلة)">
                    <p class="text-[10px] text-gray-500 mt-1"><i class="fas fa-magic text-[#DAA520]"></i> نصيحة: يفضل إضافة 3 إلى 8 كلمات مفتاحية مفصولة بفاصلة.</p>
                </div>
            </div>

            <!-- عمود المعاينة المباشرة (Google SERP Preview) -->
            <div class="bg-[#0A0A0A] border border-[#333] rounded-xl p-6 flex flex-col justify-center relative overflow-hidden group">
                <div class="absolute top-0 right-0 bg-[#333] text-gray-300 text-[10px] px-3 py-1 rounded-bl-lg font-bold z-10">
                    <i class="fab fa-google"></i> معاينة جوجل (Dark Mode)
                </div>
                
                <div class="mt-4" dir="ltr" style="font-family: Arial, sans-serif;">
                    <!-- URL -->
                    <div class="flex items-center gap-2 mb-1">
                        <div class="w-6 h-6 rounded-full bg-gray-800 flex items-center justify-center overflow-hidden">
                            <img src="https://arabfleex.ct.ws/favicon.png" class="w-4 h-4" alt="logo" onerror="this.style.display='none'">
                        </div>
                        <div>
                            <span class="text-[#dadce0] text-[13px] block">Arab Fleex</span>
                            <span class="text-[#bdc1c6] text-[12px] block leading-none"><?php echo $info['url']; ?></span>
                        </div>
                    </div>
                    <!-- Title -->
                    <h3 id="google-title-<?php echo $key; ?>" class="text-[#8ab4f8] text-[20px] hover:underline cursor-pointer truncate max-w-full leading-tight mb-1" style="direction: rtl; text-align: right;">
                        <?php echo $title_val ?: 'عنوان الصفحة سيظهر هنا'; ?>
                    </h3>
                    <!-- Description -->
                    <p id="google-desc-<?php echo $key; ?>" class="text-[#bdc1c6] text-[14px] leading-snug line-clamp-2" style="direction: rtl; text-align: right; word-break: break-word;">
                        <?php echo $desc_val ?: 'اكتب وصفاً للصفحة لتشاهد كيف سيظهر للزوار في محرك بحث جوجل. حاول أن تجعله مشوقاً.'; ?>
                    </p>
                </div>
            </div>

        </div>
    </div>
    <?php endforeach; ?>

    <!-- زر الحفظ العائم -->
    <div class="sticky bottom-6 z-50 flex justify-end mt-10">
        <button type="submit" name="save_seo" class="bg-gradient-to-r from-[#DAA520] to-yellow-600 hover:from-yellow-500 hover:to-yellow-400 text-black px-10 py-4 rounded-xl font-black text-xl shadow-[0_10px_30px_rgba(218,165,32,0.4)] flex items-center gap-3 transition-all hover:scale-105 hover:-translate-y-1">
            <i class="fas fa-save text-2xl"></i> حفظ جميع الإعدادات ونشرها
        </button>
    </div>
</form>

<!-- سكربت المعاينة المباشرة والمؤشر الذكي (تقييم السيو) -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // الألوان المعتمدة للمؤشر
    const colors = {
        bad: 'bg-red-500/20 text-red-500 border border-red-500/50',
        ok: 'bg-yellow-500/20 text-yellow-500 border border-yellow-500/50',
        good: 'bg-green-500/20 text-green-500 border border-green-500/50',
        empty: 'bg-gray-800 text-gray-400 border border-gray-700'
    };

    // 1. نظام المؤشر للعنوان والوصف (Traffic Light System)
    const counters = document.querySelectorAll('.seo-counter');
    counters.forEach(counter => {
        const inputId = counter.getAttribute('data-target');
        const type = counter.getAttribute('data-type');
        const input = document.getElementById(inputId);
        const maxChars = parseInt(counter.getAttribute('data-max'));
        const badge = document.getElementById('badge_' + inputId);
        
        if(input) {
            const updateIndicator = () => {
                const len = input.value.trim().length;
                counter.textContent = `${len} / ${maxChars}`;
                
                // تحديث الشارة (المؤشر)
                badge.className = `text-[10px] px-2 py-0.5 rounded font-bold transition-colors `;
                
                if (len === 0) {
                    badge.textContent = 'فارغ';
                    badge.className += colors.empty;
                } else if (type === 'title') {
                    if (len < 30 || len > 70) { badge.textContent = 'ضعيف'; badge.className += colors.bad; }
                    else if (len >= 30 && len <= 40) { badge.textContent = 'مقبول'; badge.className += colors.ok; }
                    else { badge.textContent = 'ممتاز'; badge.className += colors.good; }
                } else if (type === 'desc') {
                    if (len < 50 || len > 160) { badge.textContent = 'ضعيف'; badge.className += colors.bad; }
                    else if (len >= 50 && len <= 110) { badge.textContent = 'مقبول'; badge.className += colors.ok; }
                    else { badge.textContent = 'ممتاز'; badge.className += colors.good; }
                }
            };
            
            input.addEventListener('input', updateIndicator);
            updateIndicator(); // التشغيل الأولي
        }
    });

    // 2. نظام المؤشر للكلمات المفتاحية
    const keyCheckers = document.querySelectorAll('.seo-keys-checker');
    keyCheckers.forEach(input => {
        const badgeId = input.getAttribute('data-badge-target');
        const badge = document.getElementById(badgeId);
        
        if (input && badge) {
            const updateKeysIndicator = () => {
                const val = input.value.trim();
                badge.className = `text-[10px] px-2 py-0.5 rounded font-bold transition-colors `;
                
                if (val === '') {
                    badge.textContent = 'فارغ';
                    badge.className += colors.empty;
                    return;
                }
                
                // حساب عدد الكلمات المفصولة بفاصلة
                const keysCount = val.split(',').filter(item => item.trim() !== '').length;
                
                if (keysCount < 3) { badge.textContent = 'ضعيف (' + keysCount + ')'; badge.className += colors.bad; }
                else if (keysCount >= 3 && keysCount <= 5) { badge.textContent = 'جيد (' + keysCount + ')'; badge.className += colors.ok; }
                else { badge.textContent = 'ممتاز (' + keysCount + ')'; badge.className += colors.good; }
            };
            
            input.addEventListener('input', updateKeysIndicator);
            updateKeysIndicator();
        }
    });

    // 3. نظام التزامن المباشر مع معاينة جوجل (Live SERP Preview)
    const syncInputs = document.querySelectorAll('.seo-input-sync');
    syncInputs.forEach(input => {
        const targetId = input.getAttribute('data-sync-target');
        const targetElement = document.getElementById(targetId);
        
        const defaultText = targetId.includes('title') ? 'عنوان الصفحة سيظهر هنا' : 'اكتب وصفاً للصفحة لتشاهد كيف سيظهر للزوار في محرك بحث جوجل. حاول أن تجعله مشوقاً.';
        
        if(targetElement) {
            input.addEventListener('input', (e) => {
                const val = e.target.value.trim();
                targetElement.textContent = val !== '' ? val : defaultText;
                
                targetElement.style.opacity = '0.7';
                setTimeout(() => { targetElement.style.opacity = '1'; }, 150);
            });
        }
    });
});
</script>