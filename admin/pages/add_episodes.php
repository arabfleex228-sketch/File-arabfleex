<?php
// إنشاء رابط الإدارة الحالي ديناميكياً
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
// سحب مسار اللوحة الحالي بالكامل (سواء كان الدومين القديم، الجديد، أو أي دومين مستقبلي)
$admin_base_path = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];

// كود الجافاسكريبت للزر السحري (تم تعديله ليعمل ديناميكياً مع أي دومين)
$bookmarklet_js = "javascript:(function(){var html=document.documentElement.innerHTML;var pTitle=document.title;var pUrl=window.location.href.toLowerCase();var isMovie=(pUrl.includes('movie')||pUrl.includes('film')||pTitle.includes('%D9%81%D9%8A%D9%84%D9%85')||pTitle.includes('فيلم'));var baseUrl=isMovie?'" . $admin_base_path . "?page=add_movies':'" . $admin_base_path . "?page=add_episodes';var arab=[];var laroza=[];var isArab=false;var match;var aRgx=/data-link=[\"']([^\"']+)[\"']/g;while((match=aRgx.exec(html))!==null){isArab=true;var raw=match[1];var b64=raw;var param=raw.match(/(?:url=|id=)([^&]+)/);if(param)b64=param[1];try{var dec=atob(b64);if(dec.includes('http'))arab.push(dec);}catch(e){}}if(!isArab){var lRgx=/data-embed-url=[\"']([^\"']+)[\"']/g;while((match=lRgx.exec(html))!==null)laroza.push(match[1]);if(laroza.length===0){var iframes=/<iframe[^>]+src=[\"']([^\"']+)[\"']/ig;while((match=iframes.exec(html))!==null){var src=match[1];if(!src.match(/(facebook|twitter|google|ads|captcha)/i)){if(src.startsWith('//'))src='https:'+src;laroza.push(src);}}}laroza=[...new Set(laroza)];}function getLinkFromList(list,keyword){return list.find(l=>l.toLowerCase().includes(keyword))||\"\";}var s1=\"\",s2=\"\",s3=\"\",s4=\"\",down1=\"\";var sourceList=isArab?arab:laroza;if(isArab){s1=getLinkFromList(sourceList,'vidmoly')||sourceList[0]||\"\";s2=getLinkFromList(sourceList,'vidara')||(sourceList.length>1?sourceList[1]:\"\");down1=getLinkFromList(sourceList,'vidoba');}else{var getPri=function(u){var ul=u.toLowerCase();if(ul.includes('vidmoly'))return 1;if(ul.includes('vidoba'))return 2;if(ul.includes('okprime'))return 3;return 4;};sourceList.sort((a,b)=>getPri(a)-getPri(b));s1=sourceList[0]||\"\";s2=sourceList[1]||\"\";s3=sourceList[2]||\"\";s4=sourceList[3]||\"\";down1=getLinkFromList(sourceList,'vidoba');}if(!down1){var rv=html.match(/https?:\/\/[^\s\"'<>]*(?:vidoba)[^\s\"'<>]*/i);if(rv)down1=rv[0];}if(down1)down1=down1.replace(/\/embed-/i,'/');function go(d2){if(sourceList.length===0){alert('لم يتم العثور على سيرفرات مشاهدة!');return;}var fUrl=baseUrl+'&s1='+encodeURIComponent(s1)+'&s2='+encodeURIComponent(s2);if(!isArab){fUrl+='&s3='+encodeURIComponent(s3)+'&s4='+encodeURIComponent(s4);}if(down1)fUrl+='&down='+encodeURIComponent(down1);if(d2)fUrl+='&down2='+encodeURIComponent(d2);window.location.href=fUrl;}var dlBtnHref=\"\";var aTags=document.getElementsByTagName('a');for(var i=0;i<aTags.length;i++){if(aTags[i].textContent.includes('سيرفرات التحميل')||aTags[i].innerHTML.includes('سيرفرات التحميل')){dlBtnHref=aTags[i].href;break;}}if(dlBtnHref&&dlBtnHref.startsWith('http')){var xhr=new XMLHttpRequest();xhr.open('GET',dlBtnHref,true);xhr.timeout=3000;xhr.onload=function(){var foundD2=\"\";if(xhr.status===200){var div=document.createElement('div');div.innerHTML=xhr.responseText;var lks=div.getElementsByTagName('a');for(var j=0;j<lks.length;j++){var h=lks[j].href,t=lks[j].textContent;if(h&&h.startsWith('http')&&!h.includes(window.location.hostname)&&(t.includes('مباشر')||t.includes('تحميل')||h.includes('vidoba'))){foundD2=h;break;}}}go(foundD2||dlBtnHref);};xhr.onerror=function(){go(dlBtnHref);};xhr.ontimeout=function(){go(dlBtnHref);};try{xhr.send();}catch(e){go(dlBtnHref);}}else{go(\"\");}})();";

// جلب المسلسلات العادية
$series_query = $conn->query("
    SELECT s.id, s.title, IFNULL(MAX(e.episode_number), 0) as max_ep 
    FROM series s 
    LEFT JOIN episodes e ON s.id = e.series_id 
    WHERE s.ramadan_year IS NULL OR s.ramadan_year != 2026 
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
    $options_html .= '<option value="" disabled>لا توجد مسلسلات مضافة حالياً</option>';
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
        return $link;
    }
    return $link;
}

if (isset($_POST['submit'])) {
    $series_ids = $_POST['series_id'];
    $episode_nums = $_POST['episode_num'];
    $ep_titles = $_POST['ep_title']; 
    $iframes = $_POST['iframe'];
    $iframes2 = $_POST['iframe2']; 
    $iframes3 = $_POST['iframe3']; 
    $iframes4 = $_POST['iframe4']; 
    $download_links = $_POST['download_link'] ?? [];
    $download_links_2 = $_POST['download_link_2'] ?? [];
    
    $silent_add = isset($_POST['silent_add']) ? true : false; // التحقق من زر الإضافة الصامتة
    
    $success_count = 0;
    $error_msg = "";

    for ($i = 0; $i < count($series_ids); $i++) {
        $s_id = intval($series_ids[$i]);
        $e_num = intval($episode_nums[$i]);
        
        $iframe_final = processVideoLink($iframes[$i]);
        $iframe2_final = processVideoLink($iframes2[$i] ?? '');
        $iframe3_final = processVideoLink($iframes3[$i] ?? '');
        $iframe4_final = processVideoLink($iframes4[$i] ?? '');
        $dl_final = isset($download_links[$i]) ? trim($download_links[$i]) : '';
        $dl2_final = isset($download_links_2[$i]) ? trim($download_links_2[$i]) : '';
        
        $title_final = !empty(trim($ep_titles[$i])) ? trim($ep_titles[$i]) : "الحلقة " . $e_num;

        if ($s_id > 0 && $e_num > 0 && (!empty($iframe_final) || !empty($iframe2_final) || !empty($iframe3_final) || !empty($dl_final) || !empty($dl2_final))) {
            $check_stmt = $conn->prepare("SELECT id FROM episodes WHERE series_id = ? AND episode_number = ?");
            if (!$check_stmt) {
                $error_msg = "❌ خطأ في قاعدة البيانات: " . $conn->error;
                break;
            }
            $check_stmt->bind_param("ii", $s_id, $e_num);
            $check_stmt->execute();
            $result = $check_stmt->get_result();

            if ($result->num_rows > 0) {
                if ($silent_add) {
                    $update_stmt = $conn->prepare("UPDATE episodes SET title=?, watch_link=?, watch_link_2=?, watch_link_3=?, watch_link_4=?, download_link=?, download_link_2=?, created_at=DATE_SUB(NOW(), INTERVAL 2 MONTH) WHERE series_id=? AND episode_number=?");
                } else {
                    $update_stmt = $conn->prepare("UPDATE episodes SET title=?, watch_link=?, watch_link_2=?, watch_link_3=?, watch_link_4=?, download_link=?, download_link_2=? WHERE series_id=? AND episode_number=?");
                }
                $update_stmt->bind_param("sssssssii", $title_final, $iframe_final, $iframe2_final, $iframe3_final, $iframe4_final, $dl_final, $dl2_final, $s_id, $e_num);
                if ($update_stmt->execute()) $success_count++;
            } else {
                if ($silent_add) {
                    $insert_stmt = $conn->prepare("INSERT INTO episodes (series_id, title, watch_link, watch_link_2, watch_link_3, watch_link_4, download_link, download_link_2, episode_number, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL 2 MONTH))");
                } else {
                    $insert_stmt = $conn->prepare("INSERT INTO episodes (series_id, title, watch_link, watch_link_2, watch_link_3, watch_link_4, download_link, download_link_2, episode_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                }
                $insert_stmt->bind_param("isssssssi", $s_id, $title_final, $iframe_final, $iframe2_final, $iframe3_final, $iframe4_final, $dl_final, $dl2_final, $e_num);
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
    .daily-row { display: flex; flex-direction: column; gap: 12px; margin-bottom: 15px; background: #0f172a; padding: 15px; border-radius: 8px; border: 1px solid #334155; transition: 0.3s;}
    .daily-row:focus-within { border-color: #3b82f6; box-shadow: 0 0 10px rgba(59, 130, 246, 0.2); }
    .daily-row-top, .daily-row-bottom { display: flex; gap: 12px; flex-wrap: wrap; width: 100%; }
    .daily-row select { flex: 2; min-width: 150px; padding: 12px; border-radius: 6px; background: #fff; color: #000; font-weight: bold; cursor: pointer; border: none; outline: none; }
    .daily-row input[type="number"] { flex: 0.5; min-width: 70px; padding: 12px; border-radius: 6px; text-align: center; color:#000; font-weight: bold; background: #f1f5f9; border: none; outline: none;}
    .daily-row input[type="text"].guest-input { flex: 1.5; min-width: 120px; padding: 12px; border-radius: 6px; color:#000; font-weight: bold; background: #eff6ff; border: 1px solid #3b82f6; outline: none;}
    .daily-row input[type="text"].link-input { flex: 1; min-width: 150px; padding: 12px; border-radius: 6px; color:#fff; font-family: monospace; direction: ltr; background: #1e293b; border: 1px solid #475569; outline: none; transition: 0.2s;}
    .daily-row input[type="text"].link-input:focus { border-color: #3b82f6; background: #000;}
    .action-buttons { display: flex; gap: 10px; flex-wrap: wrap; }
    .btn-preview-bottom { flex: 1; min-width: 150px; background: #475569; color: #fff; padding: 14px 20px; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; font-weight: 900; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px; }
    .btn-preview-bottom:hover { background: #64748b; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(71, 85, 105, 0.3); color: #93c5fd; }
    .btn-submit-daily { flex: 2; min-width: 200px; background: #3b82f6; color: #fff; padding: 14px 20px; border: none; border-radius: 8px; font-size: 18px; cursor: pointer; font-weight: 900; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px; }
    .btn-submit-daily:hover { background: #2563eb; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(59, 130, 246, 0.3);}
</style>

<div class="daily-container">
    <div class="bg-gradient-to-l from-blue-900/40 to-transparent p-5 rounded-xl border border-blue-500/30 mb-8 shadow-lg">
        <h3 class="text-white font-black text-xl mb-3 flex items-center gap-2">
            <i class="fas fa-tablet-screen-button text-blue-400"></i> الزر السحري للمسلسلات العادية
        </h3>
        <p class="text-gray-300 text-sm leading-relaxed mb-4">
            انسخ الكود وأضفه للمفضلة مع كلمة <code>javascript:</code> لاستخدامه في جلب سيرفرات المسلسلات العادية كروابط مباشرة ومجهزة بروابط التحميل.
        </p>
        
        <div class="flex flex-col md:flex-row gap-2 relative">
            <input type="text" id="bookmarkletCode" readonly value="<?php echo htmlspecialchars($bookmarklet_js); ?>" class="flex-1 bg-background-dark border border-border-color text-gray-500 text-xs p-3 rounded-lg outline-none" dir="ltr">
            <button type="button" onclick="copyBookmarkletCode()" class="bg-blue-600 hover:bg-blue-500 text-white font-bold px-6 py-3 rounded-lg shadow-lg whitespace-nowrap">
                <i class="fas fa-copy"></i> نسخ الكود
            </button>
        </div>
        <div id="copySuccess" class="hidden text-green-400 font-bold text-sm mt-2"><i class="fas fa-check-circle"></i> تم نسخ الكود بنجاح! قم بتحديث المفضلة لديك.</div>
    </div>

    <div class="flex items-center gap-3 mb-2 mt-4">
        <i class="fas fa-tv text-3xl text-blue-500"></i>
        <h2 class="text-white text-2xl font-black">الإضافة السريعة للمسلسلات (بدون Iframe)</h2>
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
                    <input type="text" name="ep_title[]" class="guest-input" placeholder="عنوان فرعي (اختياري)">
                </div>
                <div class="daily-row-bottom">
                    <input type="text" name="iframe[]" class="link-input link-1" placeholder="سيرفر 1...">
                    <input type="text" name="iframe2[]" class="link-input link-2" placeholder="سيرفر 2...">
                    <input type="text" name="iframe3[]" class="link-input link-3" placeholder="سيرفر 3...">
                    <input type="text" name="iframe4[]" class="link-input link-4" placeholder="سيرفر 4..." style="border-bottom: 2px solid #3b82f6;">
                </div>
                <div style="width: 100%; margin-top: 5px; display: flex; gap: 10px;">
                    <input type="text" name="download_link[]" class="link-input" placeholder="رابط التحميل 1 (اختياري)..." style="width: 50%; border-bottom: 2px solid #10b981;">
                    <input type="text" name="download_link_2[]" class="link-input" placeholder="رابط التحميل 2 (مباشر أو صفحة التحميل)..." style="width: 50%; border-bottom: 2px solid #06b6d4;">
                </div>
            </div>
        </div>
        
        <!-- خيار الإضافة الصامتة -->
        <div class="bg-slate-800/80 border border-blue-500/30 p-3 rounded-lg mt-2 mb-4 flex justify-between items-center transition-colors hover:bg-slate-800">
            <label class="flex items-center gap-3 cursor-pointer group w-full">
                <input type="checkbox" name="silent_add" value="1" class="w-5 h-5 accent-blue-500 rounded cursor-pointer">
                <span class="font-bold text-gray-300 group-hover:text-blue-400 transition-colors">🤫 إضافة صامتة (لن تظهر الحلقة للزوار في "أحدث الإضافات")</span>
            </label>
        </div>
        
        <div class="action-buttons">
            <button type="button" class="btn-preview-bottom" onclick="openPreview()">
                <i class="fas fa-eye"></i> معاينة الروابط
            </button>
            <button type="submit" name="submit" class="btn-submit-daily">
                <i class="fas fa-save"></i> حفظ الحلقة
            </button>
        </div>
    </form>
</div>

<div id="previewModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15, 23, 42, 0.9); z-index:9999; justify-content:center; align-items:center;">
    <div style="background:#1e293b; padding:20px; border-radius:12px; width:90%; max-width:800px; border:1px solid #334155; box-shadow:0 15px 30px rgba(0,0,0,0.5);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom:1px solid #334155; padding-bottom:10px;">
            <h3 class="text-white text-xl font-bold"><i class="fas fa-eye text-blue-400 ml-2"></i> معاينة سيرفرات الحلقة</h3>
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
        const down = urlParams.get('down');
        const down2 = urlParams.get('down2'); 

        if (s1 || s2 || s3 || s4 || down || down2) {
            const firstRow = document.querySelector('.daily-row');
            if (firstRow) {
                if (s1) { const l1 = firstRow.querySelector('.link-1'); l1.value = s1; cleanInput(l1); }
                if (s2) { const l2 = firstRow.querySelector('.link-2'); l2.value = s2; cleanInput(l2); }
                if (s3) { const l3 = firstRow.querySelector('.link-3'); l3.value = s3; cleanInput(l3); }
                if (s4) { const l4 = firstRow.querySelector('.link-4'); l4.value = s4; cleanInput(l4); }
                
                if (down) { 
                    const downInput = firstRow.querySelector('input[name="download_link[]"]'); 
                    if(downInput) { downInput.value = down; cleanInput(downInput); } 
                }

                if (down2) { 
                    const down2Input = firstRow.querySelector('input[name="download_link_2[]"]'); 
                    if(down2Input) { down2Input.value = down2; cleanInput(down2Input); } 
                }
                
                firstRow.querySelector('.series-select').focus();
                window.history.replaceState({}, document.title, window.location.pathname + "?page=add_episodes");
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
                        <h4 style="margin:0 0 10px 0; color:#3b82f6; font-weight:bold;">سيرفر ${idx + 1}</h4>
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