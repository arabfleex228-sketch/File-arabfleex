<?php
// إنشاء رابط الإدارة الحالي ديناميكياً لاستخدامه في الزر السحري
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$base_admin_url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . "?page=daily_episodes";

// كود الجافاسكريبت المشفر (يفتح في نفس الصفحة لتخطي حظر المتصفح) مع دعم 4 سيرفرات ورابط التحميل
$bookmarklet_js = "javascript:(function(){var html=document.documentElement.innerHTML;var arabseedLinks=[];var larozaLinks=[];var isArabseed=false;var match;var arabseedRegex=/data-link=[\"']([^\"']+)[\"']/g;while((match=arabseedRegex.exec(html))!==null){isArabseed=true;var raw=match[1];var b64=raw;var param=raw.match(/(?:url=|id=)([^&]+)/);if(param)b64=param[1];try{var dec=atob(b64);if(dec.includes('http'))arabseedLinks.push(dec);}catch(e){}}if(isArabseed){var s1=\"\",s2=\"\",downLink=\"\";arabseedLinks.forEach(l=>{var lower=l.toLowerCase();if(lower.includes('vidmoly'))s1=l;if(lower.includes('vidara'))s2=l;if(lower.includes('vidoba'))downLink=l;});if(!downLink){var rawVidoba=html.match(/https?:\/\/[^\s\"'<>]*(?:vidoba)[^\s\"'<>]*/i);if(rawVidoba)downLink=rawVidoba[0];}if(!s1&&arabseedLinks.length>0)s1=arabseedLinks[0];if(!s2&&arabseedLinks.length>1)s2=arabseedLinks.find(l=>l!==s1)||'';if(s1||s2){var fUrl='".$base_admin_url."&s1='+encodeURIComponent(s1)+'&s2='+encodeURIComponent(s2);if(downLink)fUrl+='&down='+encodeURIComponent(downLink);window.location.href=fUrl;}else{alert('لم يتم العثور على سيرفرات مشاهدة في هذه الصفحة!');}}else{var larozaRegex=/data-embed-url=[\"']([^\"']+)[\"']/g;while((match=larozaRegex.exec(html))!==null)larozaLinks.push(match[1]);if(larozaLinks.length===0){var iframes=/<iframe[^>]+src=[\"']([^\"']+)[\"']/ig;while((match=iframes.exec(html))!==null){var src=match[1];if(!src.match(/(facebook|twitter|google|ads|captcha)/i)){if(src.startsWith('//'))src='https:'+src;larozaLinks.push(src);}}}larozaLinks=[...new Set(larozaLinks)];var getPriority=function(url){var u=url.toLowerCase();if(u.includes('vidmoly'))return 1;if(u.includes('vidoba'))return 2;if(u.includes('okprime'))return 3;if(u.includes('ramadan-series.site'))return 4;return 5;};larozaLinks.sort(function(a,b){return getPriority(a)-getPriority(b);});var dLink=\"\";var vMatch=larozaLinks.find(l=>l.toLowerCase().includes('vidoba'));if(vMatch){dLink=vMatch;}else{var rawVidoba=html.match(/https?:\/\/[^\s\"'<>]*(?:vidoba)[^\s\"'<>]*/i);if(rawVidoba)dLink=rawVidoba[0];}if(larozaLinks.length>0){var finalUrl='".$base_admin_url."&s1='+encodeURIComponent(larozaLinks[0]||'')+'&s2='+encodeURIComponent(larozaLinks[1]||'')+'&s3='+encodeURIComponent(larozaLinks[2]||'')+'&s4='+encodeURIComponent(larozaLinks[3]||'');if(dLink!=='')finalUrl+='&down='+encodeURIComponent(dLink);window.location.href=finalUrl;}else{alert('لم يتم العثور على سيرفرات مشاهدة في هذه الصفحة!');}}})();";

// جلب مسلسلات رمضان 2026 وأعلى رقم حلقة لكل مسلسل
$series_query = $conn->query("
    SELECT s.id, s.title, IFNULL(MAX(e.episode_number), 0) as max_ep 
    FROM series s 
    LEFT JOIN episodes e ON s.id = e.series_id 
    WHERE s.ramadan_year = 2026 
    GROUP BY s.id 
    ORDER BY s.id DESC
");

$options_html = '<option value="">-- اختر المسلسل/البرنامج --</option>';
$series_max_eps = []; 

if ($series_query && $series_query->num_rows > 0) {
    while ($row = $series_query->fetch_assoc()) {
        $options_html .= '<option value="' . $row['id'] . '">' . htmlspecialchars($row['title']) . '</option>';
        $series_max_eps[$row['id']] = (int)$row['max_ep'];
    }
} else {
    $options_html .= '<option value="" disabled>لا يوجد مسلسلات مضافة لرمضان 2026</option>';
}

$max_eps_json = json_encode($series_max_eps);

function processVideoLink($link) {
    $link = trim($link);
    if (empty($link)) return '';
    
    if (stripos($link, '<iframe') !== false) {
        preg_match('/src=["\']([^"\']+)["\']/i', $link, $matches);
        if(isset($matches[1])) $link = $matches[1];
    }

    if (filter_var($link, FILTER_VALIDATE_URL) || strpos($link, '//') === 0) {
        if (strpos($link, '//') === 0) $link = 'https:' . $link;
        if (stripos($link, 'vidoba.org/') !== false && stripos($link, 'embed-') === false) {
            $link = str_ireplace('vidoba.org/', 'vidoba.org/embed-', $link);
        }
        return '<iframe src="' . $link . '" width="100%" height="400" frameborder="0" scrolling="no" allowfullscreen></iframe>';
    }
    return '';
}

if (isset($_POST['submit'])) {
    $series_ids = $_POST['series_id'];
    $episode_nums = $_POST['episode_num'];
    $ep_titles = $_POST['ep_title']; 
    $iframes = $_POST['iframe'];
    $iframes2 = $_POST['iframe2']; 
    $iframes3 = $_POST['iframe3']; 
    $iframes4 = $_POST['iframe4']; 
    $download_links = $_POST['download_link']; // جلب روابط التحميل
    
    $success_count = 0;
    $error_msg = "";

    for ($i = 0; $i < count($series_ids); $i++) {
        $s_id = intval($series_ids[$i]);
        $e_num = intval($episode_nums[$i]);
        
        $iframe_final = processVideoLink($iframes[$i]);
        $iframe2_final = processVideoLink($iframes2[$i] ?? '');
        $iframe3_final = processVideoLink($iframes3[$i] ?? '');
        $iframe4_final = processVideoLink($iframes4[$i] ?? '');
        $dl_final = isset($download_links[$i]) ? trim($download_links[$i]) : ''; // تنظيف رابط التحميل
        
        $title_final = !empty(trim($ep_titles[$i])) ? trim($ep_titles[$i]) : "الحلقة " . $e_num;

        if ($s_id > 0 && $e_num > 0 && (!empty($iframe_final) || !empty($iframe2_final) || !empty($iframe3_final) || !empty($dl_final))) {
            $check_stmt = $conn->prepare("SELECT id FROM episodes WHERE series_id = ? AND episode_number = ?");
            if (!$check_stmt) {
                $error_msg = "❌ خطأ في قاعدة البيانات: " . $conn->error;
                break;
            }
            $check_stmt->bind_param("ii", $s_id, $e_num);
            $check_stmt->execute();
            $result = $check_stmt->get_result();

            if ($result->num_rows > 0) {
                // تحديث الحلقة مع إضافة رابط التحميل
                $update_stmt = $conn->prepare("UPDATE episodes SET title=?, watch_link=?, watch_link_2=?, watch_link_3=?, watch_link_4=?, download_link=? WHERE series_id=? AND episode_number=?");
                $update_stmt->bind_param("ssssssii", $title_final, $iframe_final, $iframe2_final, $iframe3_final, $iframe4_final, $dl_final, $s_id, $e_num);
                if ($update_stmt->execute()) $success_count++;
            } else {
                // إدراج الحلقة مع رابط التحميل
                $insert_stmt = $conn->prepare("INSERT INTO episodes (series_id, title, watch_link, watch_link_2, watch_link_3, watch_link_4, download_link, episode_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $insert_stmt->bind_param("issssssi", $s_id, $title_final, $iframe_final, $iframe2_final, $iframe3_final, $iframe4_final, $dl_final, $e_num);
                if ($insert_stmt->execute()) $success_count++;
            }
        }
    }
    
    if (!empty($error_msg)) {
        $message = "<div style='background:#f8d7da; color:#721c24; padding:15px; border-radius:5px; margin-bottom:20px; font-weight:bold; text-align:center;'>$error_msg</div>";
    } elseif ($success_count > 0) {
        $message = "<div style='background:#d4edda; color:#155724; padding:15px; border-radius:5px; margin-bottom:20px; font-weight:bold; text-align:center;'>✅ تم بنجاح! تم حفظ الحلقة.</div>";
    } else {
        $message = "<div style='background:#fff3cd; color:#856404; padding:15px; border-radius:5px; margin-bottom:20px; font-weight:bold; text-align:center;'>⚠️ لم تقم بإدخال أي بيانات صالحة ليتم حفظها!</div>";
    }
}
?>

<style>
    .daily-container { background: #1e293b; padding: 24px; border-radius: 12px; width: 100%; border: 1px solid #334155; box-sizing: border-box; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3); }
    
    /* التصميم الأصلي المريح */
    .daily-row { display: flex; flex-direction: column; gap: 12px; margin-bottom: 15px; background: #0f172a; padding: 15px; border-radius: 8px; border: 1px solid #334155; transition: 0.3s;}
    .daily-row:focus-within { border-color: #fbbf24; box-shadow: 0 0 10px rgba(251, 191, 36, 0.2); }
    .daily-row-top, .daily-row-bottom { display: flex; gap: 12px; flex-wrap: wrap; width: 100%; }
    
    .daily-row select { flex: 2; min-width: 150px; padding: 12px; border-radius: 6px; background: #fff; color: #000; font-weight: bold; cursor: pointer; border: none; outline: none; }
    .daily-row input[type="number"] { flex: 0.5; min-width: 70px; padding: 12px; border-radius: 6px; text-align: center; color:#000; font-weight: bold; background: #f1f5f9; border: none; outline: none;}
    .daily-row input[type="text"].guest-input { flex: 1.5; min-width: 120px; padding: 12px; border-radius: 6px; color:#000; font-weight: bold; background: #fffbeb; border: 1px solid #f59e0b; outline: none;}
    .daily-row input[type="text"].link-input { flex: 1; min-width: 150px; padding: 12px; border-radius: 6px; color:#fff; font-family: monospace; direction: ltr; background: #1e293b; border: 1px solid #475569; outline: none; transition: 0.2s;}
    .daily-row input[type="text"].link-input:focus { border-color: #fbbf24; background: #000;}
    
    /* أزرار الإجراءات بالأسفل */
    .action-buttons { display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap; }
    .btn-preview-bottom { flex: 1; min-width: 150px; background: #475569; color: #fff; padding: 14px 20px; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; font-weight: 900; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px; }
    .btn-preview-bottom:hover { background: #64748b; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(71, 85, 105, 0.3); color: #fbbf24; }
    
    .btn-submit-daily { flex: 2; min-width: 200px; background: #fbbf24; color: #0f172a; padding: 14px 20px; border: none; border-radius: 8px; font-size: 18px; cursor: pointer; font-weight: 900; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px; }
    .btn-submit-daily:hover { background: #fcd34d; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(251, 191, 36, 0.3);}
</style>

<div class="daily-container">
    
    <div class="bg-gradient-to-l from-blue-900/40 to-transparent p-5 rounded-xl border border-blue-500/30 mb-8 shadow-lg">
        <h3 class="text-white font-black text-xl mb-3 flex items-center gap-2">
            <i class="fas fa-tablet-screen-button text-yellow-400"></i> تفعيل الزر السحري للتابلت والموبايل
        </h3>
        <p class="text-gray-300 text-sm leading-relaxed mb-4">
            1. اضغط على زر <strong>"نسخ الكود"</strong> بالأسفل.<br>
            2. أضف هذه الصفحة للمفضلة (Bookmark) واضغط على تعديل (Edit).<br>
            3. في خانة الرابط (URL) في المفضلة، <strong>اكتب بيدك كلمة <code>javascript:</code></strong> أولاً.<br>
            4. ثم الصق الكود الذي نسخته مباشرة بعد النقطتين.
        </p>
        
        <div class="flex flex-col md:flex-row gap-2 relative">
            <input type="text" id="bookmarkletCode" readonly value="<?php echo htmlspecialchars($bookmarklet_js); ?>" class="flex-1 bg-background-dark border border-border-color text-gray-500 text-xs p-3 rounded-lg outline-none" dir="ltr">
            <button type="button" onclick="copyBookmarkletCode()" class="bg-blue-600 hover:bg-blue-500 text-white font-bold px-6 py-3 rounded-lg shadow-lg whitespace-nowrap">
                <i class="fas fa-copy"></i> نسخ الكود
            </button>
        </div>
        <div id="copySuccess" class="hidden text-green-400 font-bold text-sm mt-2"><i class="fas fa-check-circle"></i> تم نسخ الكود بنجاح! اذهب للمفضلة الآن والصقه.</div>
    </div>

    <div class="flex items-center gap-3 mb-2 mt-4">
        <i class="fas fa-bolt text-3xl text-yellow-400"></i>
        <h2 class="text-white text-2xl font-black">الإضافة السريعة لحلقات اليوم</h2>
    </div>
    
    <?php if(!empty($message)) echo $message; ?>

    <form method="POST" action="">
        <div id="form-rows">
            <div class="daily-row">
                <div class="daily-row-top">
                    <select name="series_id[]" class="series-select" required>
                        <?php echo $options_html; ?>
                    </select>
                    <input type="number" name="episode_num[]" class="ep-input" placeholder="الحلقة" required>
                    <input type="text" name="ep_title[]" class="guest-input" placeholder="اسم الضيف (اختياري)">
                </div>
                <div class="daily-row-bottom">
                    <input type="text" name="iframe[]" class="link-input link-1" placeholder="سيرفر 1...">
                    <input type="text" name="iframe2[]" class="link-input link-2" placeholder="سيرفر 2...">
                    <input type="text" name="iframe3[]" class="link-input link-3" placeholder="سيرفر 3...">
                    <input type="text" name="iframe4[]" class="link-input link-4" placeholder="سيرفر 4..." style="border-bottom: 2px solid #3b82f6;">
                </div>
                <div style="width: 100%; margin-top: 5px;">
                    <input type="text" name="download_link[]" class="link-input" placeholder="رابط التحميل المباشر (اختياري)..." style="width: 100%; border-bottom: 2px solid #10b981;">
                </div>
            </div>
        </div>
        
        <div class="action-buttons">
            <button type="button" class="btn-preview-bottom" onclick="openPreview()">
                <i class="fas fa-eye"></i> معاينة السيرفرات
            </button>
            <button type="submit" name="submit" class="btn-submit-daily">
                <i class="fas fa-save"></i> حفظ ونشر الحلقة فوراً
            </button>
        </div>
    </form>
</div>

<div id="previewModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15, 23, 42, 0.9); z-index:9999; justify-content:center; align-items:center;">
    <div style="background:#1e293b; padding:20px; border-radius:12px; width:90%; max-width:800px; border:1px solid #334155; box-shadow:0 15px 30px rgba(0,0,0,0.5);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom:1px solid #334155; padding-bottom:10px;">
            <h3 class="text-white text-xl font-bold"><i class="fas fa-eye text-yellow-400 ml-2"></i> معاينة سيرفرات الحلقة</h3>
            <button onclick="closePreview()" style="background:#ef4444; border:none; color:white; padding:8px 15px; cursor:pointer; border-radius:6px; font-weight:bold;">إغلاق <i class="fas fa-times"></i></button>
        </div>
        <div id="previewContainer" style="display:flex; flex-direction:column; gap:15px; max-height:70vh; overflow-y:auto; padding-left:10px;">
            </div>
    </div>
</div>

<script>
    function copyBookmarkletCode() {
        const copyText = document.getElementById("bookmarkletCode");
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        document.execCommand("copy");
        
        document.getElementById('copySuccess').classList.remove('hidden');
        setTimeout(() => { document.getElementById('copySuccess').classList.add('hidden'); }, 4000);
    }

    const seriesMaxEps = <?php echo $max_eps_json; ?>;

    document.addEventListener('change', function(e) {
        if (e.target && e.target.classList.contains('series-select')) {
            const selectEle = e.target;
            const seriesId = selectEle.value;
            const row = selectEle.closest('.daily-row');
            const epInput = row.querySelector('.ep-input');
            const guestInput = row.querySelector('.guest-input');

            if (seriesId && seriesMaxEps[seriesId] !== undefined) {
                const nextEp = seriesMaxEps[seriesId] + 1;
                epInput.value = nextEp;
                guestInput.focus();
            } else {
                epInput.value = '';
            }
        }
    });

    function cleanInput(inputEle) {
        let pastedData = inputEle.value;
        if (pastedData.toLowerCase().includes('<iframe')) {
            const match = pastedData.match(/src=["']([^"']+)["']/i);
            if (match && match[1]) {
                inputEle.value = match[1]; 
            }
        }
        inputEle.style.backgroundColor = '#064e3b';
        setTimeout(() => inputEle.style.backgroundColor = '', 500);
    }

    window.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        const s1 = urlParams.get('s1'); const s2 = urlParams.get('s2');
        const s3 = urlParams.get('s3'); const s4 = urlParams.get('s4');
        const down = urlParams.get('down'); // استقبال رابط التحميل

        if (s1 || s2 || s3 || s4 || down) {
            const firstRow = document.querySelector('.daily-row');
            if (firstRow) {
                if (s1) { const l1 = firstRow.querySelector('.link-1'); l1.value = s1; cleanInput(l1); }
                if (s2) { const l2 = firstRow.querySelector('.link-2'); l2.value = s2; cleanInput(l2); }
                if (s3) { const l3 = firstRow.querySelector('.link-3'); l3.value = s3; cleanInput(l3); }
                if (s4) { const l4 = firstRow.querySelector('.link-4'); l4.value = s4; cleanInput(l4); }
                
                // ملء خانة التحميل لو موجود رابط
                if (down) { 
                    const downInput = firstRow.querySelector('input[name="download_link[]"]'); 
                    if(downInput) { downInput.value = down; cleanInput(downInput); } 
                }
                
                firstRow.querySelector('.series-select').focus();
                window.history.replaceState({}, document.title, window.location.pathname + "?page=daily_episodes");
            }
        }
    });

    document.addEventListener('paste', function(e) {
        if (e.target && e.target.tagName === 'INPUT' && e.target.classList.contains('link-input')) {
            const inputEle = e.target;
            const row = inputEle.closest('.daily-row');

            setTimeout(() => {
                cleanInput(inputEle);
                if (inputEle.classList.contains('link-1')) {
                    row.querySelector('.link-2')?.focus();
                } 
                else if (inputEle.classList.contains('link-2')) {
                    row.querySelector('.link-3')?.focus();
                }
                else if (inputEle.classList.contains('link-3')) {
                    row.querySelector('.link-4')?.focus();
                }
            }, 10);
        }
    });

    // دوال المعاينة
    function openPreview() {
        const row = document.querySelector('.daily-row');
        const links = [
            row.querySelector('.link-1').value,
            row.querySelector('.link-2').value,
            row.querySelector('.link-3').value,
            row.querySelector('.link-4').value
        ].filter(l => l.trim() !== '');

        const container = document.getElementById('previewContainer');
        container.innerHTML = '';

        if (links.length === 0) {
            container.innerHTML = '<div style="padding:20px; text-align:center; color:#f87171; background:#450a0a; border-radius:8px;">لا توجد روابط مضافة لمعاينتها!</div>';
        } else {
            links.forEach((link, idx) => {
                let url = link;
                if(link.includes('<iframe')) {
                    const match = link.match(/src=["']([^"']+)["']/i);
                    if(match) url = match[1];
                }
                if (url.startsWith('//')) url = 'https:' + url;
                
                container.innerHTML += `
                    <div style="background:#0f172a; border:1px solid #334155; padding:15px; border-radius:8px;">
                        <h4 style="margin:0 0 10px 0; color:#fbbf24; font-weight:bold;">سيرفر ${idx + 1}</h4>
                        <div style="margin-bottom:10px; font-size:12px; direction:ltr; word-break:break-all;">
                            <a href="${url}" target="_blank" style="color:#60a5fa; text-decoration:none;">${url}</a>
                        </div>
                        <iframe src="${url}" width="100%" height="300" frameborder="0" allowfullscreen style="border-radius:6px; background:#000;"></iframe>
                    </div>
                `;
            });
        }
        document.getElementById('previewModal').style.display = 'flex';
    }

    function closePreview() {
        document.getElementById('previewModal').style.display = 'none';
        document.getElementById('previewContainer').innerHTML = ''; 
    }
</script>