<?php
/**
 * Arabfleex Link Hunter V29.0 (TXT Direct Edition)
 */

@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
@ini_set('implicit_flush', true);
@ob_implicit_flush(true);
while (ob_get_level() > 0) { @ob_end_flush(); }

$db_host = 'sql211.infinityfree.com';
$db_name = 'if0_42294413_arabfleex'; 
$db_user = 'if0_42294413';      
$db_pass = 'bpAc6hDFtP2XfX'; 

$series = [];
$db_status = false;
$db_error = "";

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET SESSION sql_mode = ''"); 
    
    // تم إزالة LIMIT 100 لجلب جميع المسلسلات
    $series = $pdo->query("SELECT id, title FROM series ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    $db_status = true;
} catch (Exception $e) {
    $db_error = "خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage();
}

function get_domain($url) {
    if(empty($url)) return 'غير متوفر';
    $host = parse_url($url, PHP_URL_HOST);
    return $host ? str_ireplace('www.', '', $host) : 'Direct';
}
?>

<!-- استدعاء مكتبات البحث الذكي -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
    /* تنسيقات لوحة التحكم للحقول والأزرار */
    .hunter-input, .hunter-select, .hunter-file { 
        width: 100%; padding: 0.75rem 1rem; background-color: #131313; border: 1px solid #2a2a2a; 
        border-radius: 0.5rem; color: #fff; transition: all 0.3s ease; outline: none; 
    }
    .hunter-file { padding: 0.5rem; cursor: pointer; }
    .hunter-file::file-selector-button { background-color: rgba(255,255,255,0.05); border: 1px solid #2a2a2a; color: #DAA520; padding: 0.5rem 1rem; border-radius: 0.5rem; margin-left: 1rem; cursor: pointer; transition: all 0.2s; font-family: 'Cairo', sans-serif; font-weight: bold; }
    .hunter-file::file-selector-button:hover { background-color: #1a1a1a; }
    
    .hunter-input:focus, .hunter-select:focus, .hunter-file:focus { border-color: #DAA520; box-shadow: 0 0 0 2px rgba(218, 165, 32, 0.2); }
    .hunter-label { display: block; font-size: 0.875rem; font-weight: 700; margin-bottom: 0.5rem; color: #9ca3af; }
    
    /* تنسيقات مكتبة Select2 لتطابق لوحة التحكم بالكامل */
    .select2-container--default .select2-selection--single {
        background-color: #131313 !important; border: 1px solid #2a2a2a !important;
        border-radius: 0.5rem !important; height: 3rem !important; display: flex; align-items: center;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #fff !important; padding-left: 1rem !important; padding-right: 2rem !important; font-weight: 600;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 100% !important; right: 10px !important; }
    .select2-dropdown { background-color: #0F0F0F !important; border: 1px solid #DAA520 !important; color: #fff !important; overflow: hidden; z-index: 9999; }
    .select2-search--dropdown .select2-search__field {
        background-color: #131313 !important; border: 1px solid #2a2a2a !important; color: #fff !important;
        border-radius: 0.5rem; padding: 0.5rem 1rem; font-family: 'Cairo', sans-serif; outline: none;
    }
    .select2-search--dropdown .select2-search__field:focus { border-color: #DAA520 !important; }
    .select2-results__option { padding: 0.75rem 1rem !important; border-bottom: 1px solid rgba(255,255,255,0.05); font-family: 'Cairo', sans-serif; }
    .select2-container--default .select2-results__option--highlighted[aria-selected] { background-color: rgba(218, 165, 32, 0.2) !important; color: #DAA520 !important; }
    .select2-container--default .select2-results__option[aria-selected=true] { background-color: rgba(218, 165, 32, 0.1) !important; color: #DAA520 !important; }
</style>

<div class="section-header flex flex-col md:flex-row justify-between items-center gap-4 mb-8">
    <div>
        <h1 class="text-3xl font-black text-white"><i class="fas fa-file-alt text-[#DAA520] ml-2"></i> رافع الحلقات السريع (نسخة TXT)</h1>
        <p class="text-gray-400 text-sm mt-1">مصمم خصيصاً لقراءة ملفات النصوص وحقنها مباشرة بقاعدة البيانات.</p>
    </div>
</div>

<?php if($db_status): ?>
    <div class="mb-6 p-4 rounded-xl flex items-center gap-3 font-bold text-sm bg-green-500/10 border border-green-500/20 text-green-500 shadow-lg">
        <i class="fas fa-check-circle text-lg"></i> <span>متصل بنجاح - تم جلب <?=count($series)?> مسلسل للقائمة</span>
    </div>
<?php else: ?>
    <div class="mb-6 p-4 rounded-xl flex items-center gap-3 font-bold text-sm bg-red-500/10 border border-red-500/20 text-red-500 shadow-lg">
        <i class="fas fa-exclamation-triangle text-lg"></i> <span><?=$db_error?></span>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">
    <div class="bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl relative overflow-hidden">
        <form method="POST" id="hunterForm" enctype="multipart/form-data" class="space-y-6 relative z-10" onsubmit="return handleFormSubmit(event)">
            
            <div class="p-4 rounded-xl bg-[#131313] border border-[#DAA520]">
                <label class="hunter-label text-[#DAA520]"><i class="fas fa-file-upload ml-1"></i> رفع ملف الروابط المجهزة (TXT)</label>
                <p class="text-xs text-gray-400 mb-3">اختر المسلسل، ثم ارفع ملف .txt الذي يحتوي على الروابط بالصيغة المتفق عليها.</p>
                <input type="file" name="txt_file" id="txtFileInput" accept=".txt" class="hunter-file" required <?=$db_status ? '' : 'disabled'?>>
            </div>

            <div>
                <label class="hunter-label">اختر المسلسل المستهدف:</label>
                <select name="s_id" id="primary_s_id" class="hunter-select series-select" required <?=$db_status ? '' : 'disabled'?>>
                    <option value="">-- ابحث باسم المسلسل --</option>
                    <?php foreach($series as $s): ?>
                        <option value="<?=$s['id']?>"><?=htmlspecialchars($s['title'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="hunter-label">نظام العمل:</label>
                    <select name="action_mode" class="hunter-select" <?=$db_status ? '' : 'disabled'?>>
                        <option value="all">شامل (تحديث القديم وإنشاء الجديد)</option>
                        <option value="update_only">تحديث الحلقات المضافة مسبقاً فقط</option>
                        <option value="insert_only">إضافة الحلقات الجديدة فقط</option>
                    </select>
                </div>
                <div>
                    <label class="hunter-label">تأخير بين كل حلقة:</label>
                    <select name="delay" class="hunter-select" <?=$db_status ? '' : 'disabled'?>>
                        <option value="0.05" selected>صاروخي (0.05 ثانية)</option>
                        <option value="0.2">سريع (0.2 ثانية)</option>
                        <option value="0.5">متوسط (0.5 ثانية)</option>
                    </select>
                </div>
            </div>

            <div class="mt-4">
                <label class="flex items-start gap-4 p-4 rounded-xl cursor-pointer transition-colors bg-[#131313] border border-[#2a2a2a] hover:border-[#DAA520]">
                    <input type="checkbox" name="prevent_recent" class="mt-1 w-5 h-5 rounded cursor-pointer accent-[#DAA520]" checked <?=$db_status ? '' : 'disabled'?>>
                    <div>
                        <span class="block text-sm font-bold text-gray-200">تفعيل وضع التخفي (لمنع الظهور في الأحدث)</span>
                        <span class="block text-xs mt-1 text-gray-500">يعطي الحلقات تاريخ قديم (2022) لمنع ظهورها نهائياً بالرئيسية.</span>
                    </div>
                </label>
            </div>

            <button type="submit" name="start_hunt" id="submitBtn" class="w-full bg-[#DAA520] hover:bg-yellow-500 text-black font-bold py-3 px-4 rounded-lg mt-4 transition-colors shadow-lg flex items-center justify-center gap-2" <?=$db_status ? '' : 'disabled'?>>
                <i class="fas fa-rocket" id="btnIcon"></i> <span id="btnText">بدء معالجة الملف</span>
            </button>
            
            <div id="progressContainer" class="hidden mt-4 p-4 rounded-xl bg-[#131313] border border-[#2a2a2a]">
                <div class="flex justify-between items-center mb-2">
                    <span id="progressStatusText" class="text-sm font-bold text-gray-300">جاري المعالجة...</span>
                    <span id="progressPercentage" class="text-sm font-black text-[#DAA520]">0%</span>
                </div>
                <div class="w-full rounded-full h-3 overflow-hidden bg-[#1F1F1F] border border-[#2a2a2a]">
                    <div id="progressBar" class="h-full rounded-full transition-all duration-300 relative overflow-hidden bg-[#DAA520]" style="width: 0%;">
                        <div class="absolute inset-0 bg-white/20 animate-pulse"></div>
                    </div>
                </div>
                <div class="text-center mt-2 text-xs text-gray-500 font-mono" id="progressCounters">بانتظار التحميل...</div>
            </div>
        </form>
    </div>

    <!-- كونسول العمليات -->
    <div class="bg-[#0F0F0F] rounded-2xl border border-[#1F1F1F] shadow-xl flex flex-col h-[650px] lg:h-auto overflow-hidden">
        <div class="px-5 py-4 flex justify-between items-center bg-[#131313] border-b border-[#1F1F1F]">
            <span class="text-sm font-bold text-gray-300"><i class="fas fa-terminal ml-2 text-[#DAA520]"></i> سجل العمليات المباشر</span>
            <span id="consoleStatus" class="text-[10px] font-bold px-3 py-1 rounded bg-[#1a1a1a] text-gray-500 border border-[#2a2a2a]">جاهز للعمل...</span>
        </div>
        
        <div class="flex-1 overflow-y-auto p-5 space-y-3 bg-[#0a0a0a]" id="consoleOutput" style="font-family: monospace; font-size: 0.85rem; line-height: 1.6;">
            <div id="emptyConsole" class="h-full flex flex-col items-center justify-center opacity-40 text-gray-500">
                <i class="fas fa-file-invoice text-5xl mb-4"></i><span class="font-bold tracking-wide">النظام بانتظار رفع ملف الـ TXT...</span>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('.series-select').select2({
            placeholder: "-- ابحث باسم المسلسل --", dir: "rtl", width: '100%',
            language: { noResults: () => "لا يوجد مسلسل بهذا الاسم" }
        });
    });

    function handleFormSubmit(event) {
        const fileInput = document.getElementById('txtFileInput');
        const s_id = document.getElementById('primary_s_id').value;
        if (!fileInput.files.length || s_id === '') {
            alert("يرجى اختيار المسلسل ورفع ملف TXT!");
            event.preventDefault(); return false;
        }
        startLoadingUI(); return true;
    }

    function startLoadingUI() {
        document.getElementById('submitBtn').classList.add('hidden');
        document.getElementById('progressContainer').classList.remove('hidden');
        const consoleStatus = document.getElementById('consoleStatus');
        consoleStatus.className = 'text-[10px] font-bold px-3 py-1 rounded bg-blue-500/10 text-blue-400 border border-blue-500/20 animate-pulse';
        consoleStatus.innerHTML = 'جاري حقن الحلقات...';
        const empty = document.getElementById('emptyConsole');
        if(empty) empty.style.display = 'none';
    }

    function addLogToConsole(type, message) {
        const time = new Date().toLocaleTimeString('ar-EG', { hour12: false });
        let colorStyle, icon;
        switch(type) {
            case 'info': colorStyle = 'color: #60a5fa; background: rgba(59, 130, 246, 0.05); border-right: 2px solid #3b82f6;'; icon = 'fa-info-circle'; break;
            case 'success': colorStyle = 'color: #34d399; background: rgba(16, 185, 129, 0.08); border-right: 2px solid #10b981; font-weight: bold;'; icon = 'fa-check-circle'; break;
            case 'error': colorStyle = 'color: #f87171; background: rgba(239, 68, 68, 0.05); border-right: 2px solid #ef4444;'; icon = 'fa-exclamation-triangle'; break;
            case 'warning': colorStyle = 'color: #f59e0b; background: rgba(245, 158, 11, 0.05); border-right: 2px solid #f59e0b;'; icon = 'fa-sync-alt'; break;
        }
        const html = `<div class="flex items-start gap-3 p-3 rounded-l-lg mb-2" style="${colorStyle}">
                        <span class="shrink-0 mt-0.5 opacity-60 text-xs">[${time}]</span><span class="shrink-0 mt-0.5"><i class="fas ${icon}"></i></span><span class="flex-1">${message}</span>
                      </div>`;
        const consoleDiv = document.getElementById("consoleOutput");
        consoleDiv.insertAdjacentHTML('beforeend', html);
        consoleDiv.scrollTop = consoleDiv.scrollHeight;
    }

    function updateProgressBar(current, total, statusText = '', subText = '') {
        const percent = total > 0 ? Math.round((current / total) * 100) : 0;
        document.getElementById('progressBar').style.width = percent + '%';
        document.getElementById('progressPercentage').innerText = percent + '%';
        if(subText !== '') document.getElementById('progressCounters').innerText = subText;
        if(statusText !== '') document.getElementById('progressStatusText').innerText = statusText;
    }

    function finishProcess() {
        document.getElementById('progressBar').style.width = '100%';
        document.getElementById('progressPercentage').innerText = '100%';
        document.getElementById('progressStatusText').innerText = 'اكتملت عملية المعالجة والحقن!';
        document.getElementById('progressStatusText').classList.replace('text-gray-300', 'text-emerald-400');
        document.getElementById('consoleStatus').className = 'text-[10px] font-bold px-3 py-1 rounded bg-green-500/10 text-green-400 border border-green-500/20';
        document.getElementById('consoleStatus').innerHTML = 'اكتملت العملية';
        setTimeout(() => {
            document.getElementById('submitBtn').classList.remove('hidden');
            document.getElementById('btnText').innerText = 'رفع ملف آخر';
            document.getElementById('btnIcon').className = 'fas fa-redo';
        }, 1000);
    }
</script>

<iframe name="hidden_iframe" style="display:none;"></iframe>

<?php
if (isset($_POST['start_hunt']) && $db_status) {
    function push_to_browser($js_code) { echo "<script>$js_code</script>"; echo str_repeat(' ', 4096); @ob_flush(); @flush(); }

    $s_id = intval($_POST['s_id']);
    $action_mode = $_POST['action_mode'] ?? 'all'; 
    $prevent_recent = isset($_POST['prevent_recent']);
    $delay_seconds = floatval($_POST['delay']);
    $sleep_micro = $delay_seconds * 1000000;

    push_to_browser("startLoadingUI();");
    
    if (!isset($_FILES['txt_file']) || $_FILES['txt_file']['error'] !== UPLOAD_ERR_OK) {
        push_to_browser("addLogToConsole('error', '❌ لم يتم رفع الملف بشكل صحيح.');"); push_to_browser("finishProcess();"); exit;
    }

    $file_tmp = $_FILES['txt_file']['tmp_name']; $content = file_get_contents($file_tmp);
    $blocks = preg_split('/-{10,}/', $content); $parsed_data = [];
    
    foreach ($blocks as $block) {
        $block = trim($block); if (empty($block)) continue;
        
        $ep_num = 0; $servers = ['s1' => '', 's2' => '', 's3' => '', 's4' => '']; $dls = ['d1' => '', 'd2' => ''];
        if (preg_match('/رقم الحلقة:.*?\s+(\d+)\b/iu', $block, $m)) { $ep_num = (int)$m[1]; }
        if ($ep_num === 0) continue; 
        
        if (preg_match('/سيرفر ١:\s*(https?:\/\/[^\s]+)/iu', $block, $m)) $servers['s1'] = trim($m[1]);
        if (preg_match('/سيرفر ٢:\s*(https?:\/\/[^\s]+)/iu', $block, $m)) $servers['s2'] = trim($m[1]);
        if (preg_match('/سيرفر ٣:\s*(https?:\/\/[^\s]+)/iu', $block, $m)) $servers['s3'] = trim($m[1]);
        if (preg_match('/سيرفر ٤:\s*(https?:\/\/[^\s]+)/iu', $block, $m)) { $servers['s4'] = trim($m[1]); }
        if (empty($servers['s4']) && !empty($servers['s1'])) { $servers['s4'] = $servers['s1']; }

        if (preg_match('/سيرفر تحميل ١:\s*(https?:\/\/[^\s]+)/iu', $block, $m)) $dls['d1'] = trim($m[1]);
        if (preg_match('/سيرفر تحميل ٢:\s*(https?:\/\/[^\s]+)/iu', $block, $m)) $dls['d2'] = trim($m[1]);
        
        if (!empty($servers['s1']) || !empty($dls['d1'])) { $parsed_data[$ep_num] = ['watch' => $servers, 'download' => $dls]; }
    }

    ksort($parsed_data); $total_episodes = count($parsed_data);
    if ($total_episodes === 0) {
        push_to_browser("addLogToConsole('error', '❌ لم يتم العثور على أي حلقات صالحة في ملف הـ TXT! تأكد من مطابقة الصيغة.');");
        push_to_browser("finishProcess();"); exit;
    }

    push_to_browser("addLogToConsole('info', '✅ تم قراءة وتجهيز ($total_episodes) حلقة بنجاح من الملف.');");

    $title_col = ''; $date_col_found = '';
    try {
        $stmt_cols = $pdo->query("SHOW COLUMNS FROM episodes"); $table_columns = $stmt_cols->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('title', $table_columns)) $title_col = 'title'; elseif (in_array('name', $table_columns)) $title_col = 'name';
        if (in_array('date', $table_columns)) $date_col_found = 'date'; elseif (in_array('created_at', $table_columns)) $date_col_found = 'created_at';
        elseif (in_array('added_date', $table_columns)) $date_col_found = 'added_date'; elseif (in_array('added', $table_columns)) $date_col_found = 'added';
    } catch (Exception $e) {}

    $processed_count = 0;

    foreach ($parsed_data as $ep_index => $links) {
        try {
            $q = $pdo->prepare("SELECT id FROM episodes WHERE series_id = ? AND episode_number = ?"); $q->execute([$s_id, $ep_index]);
            $is_exists = ($q->rowCount() > 0);

            if ($is_exists && $action_mode === 'insert_only') {
                $processed_count++; push_to_browser("updateProgressBar($processed_count, $total_episodes, 'تخطي الموجود...', 'تم: $processed_count / $total_episodes');"); 
                continue;
            }
            if (!$is_exists && $action_mode === 'update_only') {
                $processed_count++; push_to_browser("updateProgressBar($processed_count, $total_episodes, 'تخطي غير الموجود...', 'تم: $processed_count / $total_episodes');"); 
                continue;
            }

            $ep_title = ($ep_index == $total_episodes && $total_episodes > 1) ? "الأخيرة" : "الحلقة " . $ep_index;
            $s1 = $links['watch']['s1']; $s2 = $links['watch']['s2']; $s3 = $links['watch']['s3']; $s4 = $links['watch']['s4'];
            $d1 = $links['download']['d1']; $d2 = $links['download']['d2'];
            
            $w_domains = implode(', ', array_filter([get_domain($s1), get_domain($s2), get_domain($s3), get_domain($s4)]));
            $d_domains = implode(', ', array_filter([get_domain($d1), get_domain($d2)]));
            $servers_text = addslashes("📺: $w_domains | 📥: $d_domains");

            if ($is_exists) {
                $up_sql = "UPDATE episodes SET watch_link = :s1, watch_link_2 = :s2, watch_link_3 = :s3, watch_link_4 = :s4, download_link = :d1, download_link_2 = :d2 ";
                $update_params = [':s1' => $s1, ':s2' => $s2, ':s3' => $s3, ':s4' => $s4, ':d1' => $d1, ':d2' => $d2, ':sid' => $s_id, ':enum' => $ep_index];
                if ($prevent_recent && $date_col_found !== '') $up_sql .= ", $date_col_found = '2022-01-01 12:00:00' ";
                if ($title_col !== '') { $up_sql .= ", $title_col = :title "; $update_params[':title'] = $ep_title; }
                $up_sql .= " WHERE series_id = :sid AND episode_number = :enum";
                $pdo->prepare($up_sql)->execute($update_params);
                push_to_browser("addLogToConsole('warning', '♻️ تحديث الحلقة $ep_index ➜ $servers_text');");
            } else {
                $insert_fields = ['series_id', 'episode_number', 'watch_link', 'watch_link_2', 'watch_link_3', 'watch_link_4', 'download_link', 'download_link_2'];
                $insert_values = [$s_id, $ep_index, $s1, $s2, $s3, $s4, $d1, $d2];
                if ($title_col !== '') { $insert_fields[] = $title_col; $insert_values[] = $ep_title; }
                if ($prevent_recent && $date_col_found !== '') { $insert_fields[] = $date_col_found; $insert_values[] = '2022-01-01 12:00:00'; }
                $placeholders = implode(',', array_fill(0, count($insert_fields), '?')); $fields_str = implode(',', $insert_fields);
                $pdo->prepare("INSERT INTO episodes ($fields_str) VALUES ($placeholders)")->execute($insert_values);
                push_to_browser("addLogToConsole('success', '✨ إنشاء حلقة $ep_index ➜ $servers_text');");
            }
        } catch (Exception $e) { $err = addslashes($e->getMessage()); push_to_browser("addLogToConsole('error', 'خطأ بقاعدة البيانات حلقة ($ep_index): $err');"); }
        $processed_count++; push_to_browser("updateProgressBar($processed_count, $total_episodes, 'جاري الحقن بقاعدة البيانات...', 'تم: $processed_count / $total_episodes');"); 
        usleep($sleep_micro); 
    }
    push_to_browser("finishProcess();");
}
?>