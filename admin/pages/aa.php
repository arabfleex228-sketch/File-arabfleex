<?php
/**
 * Arabfleex Link Hunter V29.3 (Egydead + Laroza + Ahwak + Q-Drama Fix)
 * تحديث قوي لدعم تصميم لاروزا الجديد (SeasonsBox) + القائمة المنسدلة للموبايل
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
    
    // جلب جميع المسلسلات
    $series = $pdo->query("SELECT id, title FROM series ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    $db_status = true;
} catch (Exception $e) {
    $db_error = "خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage();
}

function hunt_content($url, $referer = "", $post_data = null) {
    $url = preg_replace_callback('/[^\x21-\x7f]/', function($match) { return rawurlencode($match[0]); }, $url);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_ENCODING, ""); 
    
    if ($post_data) {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        $headers = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Accept: application/json, text/javascript, */*; q=0.01',
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With: XMLHttpRequest',
            'Referer: ' . ($referer ?: 'https://google.com/')
        ];
    } else {
        $headers = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: ar,en-US;q=0.9,en;q=0.8',
            'Referer: ' . ($referer ?: 'https://google.com/')
        ];
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $res = curl_exec($ch); curl_close($ch);
    return $res;
}

function get_domain($url) {
    $host = parse_url($url, PHP_URL_HOST); return $host ? str_ireplace('www.', '', $host) : 'Embed/Direct';
}

function detect_season_number($text) {
    $text = str_replace(['١','٢','٣','٤','٥','٦','٧','٨','٩','٠'], ['1','2','3','4','5','6','7','8','9','0'], $text);
    if (preg_match('/(?:جزء|موسم|الموسم|الجزء|season|part)[\s-]*([0-9]+)/iu', $text, $m)) return (int)$m[1];
    if (preg_match('/(?:جزء|موسم|الموسم|الجزء)[\s-]*(اول|أول|ثاني|ثالث|رابع|خامس|سادس|سابع|ثامن|تاسع|عاشر)/iu', $text, $m)) {
        $map = ['اول'=>1, 'أول'=>1, 'ثاني'=>2, 'ثالث'=>3, 'رابع'=>4, 'خامس'=>5, 'سادس'=>6, 'سابع'=>7, 'ثامن'=>8, 'تاسع'=>9, 'عاشر'=>10];
        return $map[trim($m[1])];
    }
    if (preg_match('/\b([0-9]+)\b/', $text, $m)) return (int)$m[1];
    return 1;
}

function parse_final_servers($html, $base_domain = "", $source_url = "") {
    $results = ['s1' => '', 's2' => '', 's3' => '', 's4' => ''];
    $all_links = [];
    if (preg_match('/var\s+servers\s*=\s*\[(.*?)\];/is', $html, $js_array_match)) {
        $array_content = str_replace(['\/', '\"'], ['/', '"'], $js_array_match[1]);
        if (preg_match_all('/src=["\']([^"\']+)["\']/i', $array_content, $src_matches)) {
             foreach($src_matches[1] as $lnk) { $all_links[] = stripslashes($lnk); }
        }
    }
    $is_laroza = (stripos($base_domain, 'laroza') !== false || stripos($source_url, 'laroza') !== false);
    preg_match_all('/data-embed-url=["\']([^"\']+)["\']/i', $html, $matches1);
    preg_match_all('/<iframe[^>]+src=["\']([^"\']+)["\']/i', $html, $matches2);
    preg_match_all('/(?:src|data-src|data-link|href)=["\']([^"\']+)["\']/i', $html, $matches3);
    preg_match_all('/<li[^>]+data-link=["\']([^"\']+)["\']/i', $html, $matches4);

    $all_links = array_merge($all_links, $matches1[1], $matches2[1], $matches3[1], $matches4[1]);
    $all_links = array_unique($all_links);

    if ($is_laroza) {
        $preferred = ['vidmoly' => '', 'vidoba' => '', 'okprime' => '', 'vk' => '']; $extra = [];
        foreach ($all_links as $link) {
            $link = trim(htmlspecialchars_decode($link));
            if (strpos($link, '//') === 0) $link = 'https:' . $link;
            if (!preg_match('/^https?:\/\//i', $link)) continue;
            if (preg_match('/\.(jpg|png|gif|css|js|svg|ico|woff|ttf)(\?.*)?$/i', $link)) continue;
            if (preg_match('/(facebook|twitter|instagram|youtube|google|tiktok|fontawesome|bootstrap|jquery)/i', $link)) continue;
            if (preg_match('/(watch\.php|video\.php|episode-|downloads?\.php|see\.php\?vid=)/i', $link)) continue; 

            if (stripos($link, 'vidmoly') !== false && empty($preferred['vidmoly'])) { $preferred['vidmoly'] = $link; continue; }
            if (stripos($link, 'vidoba') !== false && empty($preferred['vidoba'])) { $preferred['vidoba'] = $link; continue; }
            if (stripos($link, 'okprime') !== false && empty($preferred['okprime'])) { $preferred['okprime'] = $link; continue; }
            if (stripos($link, 'vk.com') !== false && empty($preferred['vk'])) { $preferred['vk'] = $link; continue; }
            if (preg_match('/(embed|\/e\/|player|video|iframe|\/v\/|film77|stream|play|1vid|vidspeeds|hlswish|listeamed|uqload)/i', $link)) { $extra[] = $link; }
        }
        $results['s1'] = $preferred['vidmoly']; $results['s2'] = $preferred['vidoba'];
        $results['s3'] = $preferred['okprime']; $results['s4'] = $preferred['vk'];
        $extra = array_values(array_unique($extra));
        foreach (['s1', 's2', 's3', 's4'] as $slot) {
            if (empty($results[$slot]) && !empty($extra)) $results[$slot] = array_shift($extra);
        }
        $valid_servers = array_filter([$results['s1'], $results['s2'], $results['s3'], $results['s4']]);
        if (!empty($valid_servers)) {
            $first_valid = reset($valid_servers);
            foreach (['s1', 's2', 's3', 's4'] as $slot) { if (empty($results[$slot])) $results[$slot] = $first_valid; }
        }
    } else {
        $valid_links = [];
        foreach ($all_links as $link) {
            $link = trim(htmlspecialchars_decode($link));
            if (strpos($link, '//') === 0) $link = 'https:' . $link;
            if (!preg_match('/^https?:\/\//i', $link)) continue;
            if (preg_match('/\.(jpg|png|gif|css|js|svg|ico|woff|ttf)(\?.*)?$/i', $link)) continue;
            if (preg_match('/(facebook|twitter|instagram|youtube|google|tiktok|fontawesome|bootstrap|jquery)/i', $link)) continue;
            if (preg_match('/(watch\.php|video\.php|episode-|downloads?\.php|see\.php)/i', $link) && stripos($link, 'player') === false && stripos($link, 'play.php') === false) continue; 
            if (preg_match('/(embed|\/e\/|player|video|iframe|\/v\/|film77|stream|play|1vid|vidspeeds|hlswish|listeamed|uqload|vidmoly|vidoba|okprime|vk\.com|ok\.ru|dood|mixdrop|hgcloud|morencius|dsvplay|playmogo|streamhg|mirrorace|koramaup|1fichier|vidshare|vidoza|liiivideo|abyssplayer|bysevepoin)/i', $link)) { $valid_links[] = $link; }
        }
        $valid_links = array_values(array_unique($valid_links));
        $results['s1'] = $valid_links[0] ?? ''; $results['s2'] = $valid_links[1] ?? '';
        $results['s3'] = $valid_links[2] ?? ''; $results['s4'] = $valid_links[3] ?? '';
        $first_valid = reset($valid_links);
        if ($first_valid) {
            for ($i = 1; $i <= 4; $i++) { if (empty($results['s' . $i])) $results['s' . $i] = $first_valid; }
        }
    }
    return $results;
}

function parse_download_servers($html, $base_domain) {
    $results = ['d1' => '', 'd2' => '']; $links = [];
    if (preg_match_all('/class=["\'][^"\']*(?:special-btn|download-btn|download-icon)[^"\']*["\'][^>]*href=["\']([^"\']+)["\']/i', $html, $matches_qd)) { $links = array_merge($links, $matches_qd[1]); }
    if (preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(?:.*?)class=["\'][^"\']*(?:special-btn|download-btn|download-icon)[^"\']*["\']/is', $html, $matches_qd2)) { $links = array_merge($links, $matches_qd2[1]); }
    if (preg_match('/<ul[^>]*class=["\'][^"\']*(?:downloadlist|donwload-servers-list|download-servers)[^"\']*["\'][^>]*>(.*?)<\/ul>/is', $html, $ul_block)) {
        preg_match_all('/href=["\']([^"\']+)["\']/i', $ul_block[1], $matches); $links = array_merge($links, $matches[1]);
    } else {
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\']/i', $html, $matches); $links = array_merge($links, $matches[1]);
    }
    $links = array_unique($links);
    $base_host = parse_url($base_domain, PHP_URL_HOST); if ($base_host) $base_host = str_ireplace('www.', '', $base_host);
    
    $external_servers = [];
    foreach ($links as $link) {
        $link = trim($link);
        if (empty($link) || $link == '#' || stripos($link, 'javascript:') === 0) continue;
        if (preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|eot)(\?.*)?$/i', $link)) continue;
        $full_link = $link;
        if (strpos($link, 'http') !== 0) {
             if(strpos($link, '//') === 0) $full_link = 'https:' . $link;
             else $full_link = rtrim($base_domain, '/') . '/' . ltrim($link, '/');
        }
        $link_host = parse_url($full_link, PHP_URL_HOST); if (!$link_host) continue;
        $link_host = str_ireplace('www.', '', $link_host);
        if (preg_match('/\.(mp4|mkv|avi)($|\?)/i', $full_link) || stripos($full_link, 'direct') !== false) continue; 
        if ($base_host && stripos($link_host, $base_host) !== false) continue;
        if (stripos($link_host, 'laroza') !== false || stripos($link_host, 'egydead') !== false || stripos($link_host, 'q-drama') !== false) continue; 
        
        $cdn_blacklist = '/(facebook\.com|twitter\.com|instagram\.com|tiktok\.com|t\.me|telegram|whatsapp|youtube\.com|google\.com|apple\.com|login|register|#|netdna|bootstrapcdn|cloudflare|googleapis|gstatic|unpkg|jsdelivr|vk\.com|fontawesome|fonts\.|mail\.ru|reviewrate\.net)/i';
        if (preg_match($cdn_blacklist, $full_link)) continue;
        $external_servers[] = $full_link;
    }
    $external_servers = array_values(array_unique($external_servers));
    if (isset($external_servers[0])) $results['d1'] = $external_servers[0];
    if (isset($external_servers[1])) $results['d2'] = $external_servers[1];
    if (empty($results['d2']) && !empty($results['d1'])) $results['d2'] = $results['d1'];
    if (empty($results['d1']) && !empty($results['d2'])) $results['d1'] = $results['d2'];
    return $results;
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>صياد الروابط المتعدد</title>
    <!-- استدعاء مكتبات التصميم والبحث الذكي -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Cairo', sans-serif; background-color: #050505; color: #fff; padding: 20px; }
        /* تنسيقات لوحة التحكم للحقول والأزرار */
        .hunter-input, .hunter-select { 
            width: 100%; padding: 0.75rem 1rem; background-color: #131313; border: 1px solid #2a2a2a; 
            border-radius: 0.5rem; color: #fff; transition: all 0.3s ease; outline: none; 
        }
        .hunter-input:focus, .hunter-select:focus { border-color: #DAA520; box-shadow: 0 0 0 2px rgba(218, 165, 32, 0.2); }
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

        .series-item:first-child .delete-row-btn { display: none; }
        
        /* شريط التمرير للكونسول */
        #consoleOutput::-webkit-scrollbar { width: 6px; }
        #consoleOutput::-webkit-scrollbar-track { background: #0a0a0a; }
        #consoleOutput::-webkit-scrollbar-thumb { background: #333; border-radius: 10px; }
        #consoleOutput::-webkit-scrollbar-thumb:hover { background: #555; }
    </style>
</head>
<body>

<div class="max-w-7xl mx-auto">
    <div class="section-header flex flex-col md:flex-row justify-between items-center gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-black text-white"><i class="fas fa-spider text-[#DAA520] ml-2"></i> صياد الروابط المتعدد (V29.3)</h1>
            <p class="text-gray-400 text-sm mt-1">تحديث شامل لتصميم لاروزا الجديد وتجاوز أي أعطال.</p>
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
        <!-- لوحة التحكم في السحب -->
        <div class="bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl relative overflow-hidden">
            <form method="POST" id="hunterForm" class="space-y-6 relative z-10" onsubmit="startLoadingUI()">
                
                <div id="series-list" class="space-y-4">
                    <div class="series-item p-4 rounded-xl relative bg-[#131313] border border-[#2a2a2a]">
                        <button type="button" class="delete-row-btn absolute top-2 left-2 text-red-500 hover:text-red-400 transition-colors z-20" onclick="this.parentElement.remove()">
                            <i class="fas fa-times-circle text-lg"></i>
                        </button>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="hunter-label">اختر المسلسل المستهدف:</label>
                                <select name="s_id[]" class="hunter-select series-select" required <?=$db_status ? '' : 'disabled'?>>
                                    <option value="">-- ابحث باسم المسلسل --</option>
                                    <?php foreach($series as $s): ?>
                                        <option value="<?=$s['id']?>"><?=htmlspecialchars($s['title'])?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="hunter-label">رابط صفحة الموسم المصدر:</label>
                                <input type="url" name="main_url[]" class="hunter-input" placeholder="مثال: https://laroza.com/series/xxx/" required <?=$db_status ? '' : 'disabled'?>>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="button" onclick="addSeriesRow()" class="text-sm font-bold text-[#DAA520] hover:text-yellow-400 flex items-center gap-2 transition-colors" <?=$db_status ? '' : 'disabled'?>>
                    <i class="fas fa-plus-circle"></i> إضافة مسلسل آخر للقائمة
                </button>
                <hr class="border-[#1F1F1F]">

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
                        <label class="hunter-label">عدد الحلقات الأقصى (لكل مسلسل):</label>
                        <input type="number" name="max_episodes" class="hunter-input" value="500" min="1" max="1000" required <?=$db_status ? '' : 'disabled'?>>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="hunter-label">الدومين الأساسي (سيتم التعرف عليه تلقائياً):</label>
                        <input type="text" name="base_domain" class="hunter-input" value="https://laroza.com" required <?=$db_status ? '' : 'disabled'?>>
                    </div>
                    <div>
                        <label class="hunter-label">تأخير بين كل حلقة:</label>
                        <select name="delay" class="hunter-select" <?=$db_status ? '' : 'disabled'?>>
                            <option value="0.2">سريع (0.2 ثانية)</option>
                            <option value="0.5" selected>متوسط (0.5 ثانية)</option>
                            <option value="1.0">بطيء وأمن (1 ثانية)</option>
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
                    <i class="fas fa-rocket" id="btnIcon"></i> <span id="btnText">بدء عملية السحب الجماعي</span>
                </button>
                
                <div id="progressContainer" class="hidden mt-4 p-4 rounded-xl bg-[#131313] border border-[#2a2a2a]">
                    <div class="flex justify-between items-center mb-2">
                        <span id="progressStatusText" class="text-sm font-bold text-gray-300">جاري الإطلاق...</span>
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
                    <i class="fas fa-satellite-dish text-5xl mb-4"></i><span class="font-bold tracking-wide">النظام جاهز بانتظار إطلاق السحب المتعدد...</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function initSelect2() {
        $('.series-select').select2({
            placeholder: "-- ابحث باسم المسلسل --", dir: "rtl", width: '100%',
            language: { noResults: () => "لا يوجد مسلسل بهذا الاسم" }
        });
    }

    $(document).ready(function() { initSelect2(); });

    function addSeriesRow() {
        $('.series-select').select2('destroy');
        const list = document.getElementById('series-list');
        const template = list.firstElementChild.cloneNode(true);
        template.querySelector('select').value = '';
        template.querySelector('input').value = '';
        template.querySelector('.delete-row-btn').style.display = 'block';
        list.appendChild(template);
        initSelect2();
    }

    function startLoadingUI() {
        document.getElementById('submitBtn').classList.add('hidden');
        document.getElementById('progressContainer').classList.remove('hidden');
        const consoleStatus = document.getElementById('consoleStatus');
        consoleStatus.className = 'text-[10px] font-bold px-3 py-1 rounded bg-blue-500/10 text-blue-400 border border-blue-500/20 animate-pulse';
        consoleStatus.innerHTML = 'الروبوت يعمل حالياً...';
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
        document.getElementById('progressStatusText').innerText = 'اكتملت جميع العمليات بنجاح!';
        document.getElementById('progressStatusText').classList.replace('text-gray-300', 'text-emerald-400');
        document.getElementById('consoleStatus').className = 'text-[10px] font-bold px-3 py-1 rounded bg-green-500/10 text-green-400 border border-green-500/20';
        document.getElementById('consoleStatus').innerHTML = 'اكتمل السحب بالكامل';
        setTimeout(() => {
            document.getElementById('submitBtn').classList.remove('hidden');
            document.getElementById('btnText').innerText = 'إجراء سحب جديد';
            document.getElementById('btnIcon').className = 'fas fa-redo';
        }, 1000);
    }
</script>

<iframe name="hidden_iframe" style="display:none;"></iframe>

<?php
if (isset($_POST['start_hunt']) && $db_status) {
    function push_to_browser($js_code) { echo "<script>$js_code</script>"; echo str_repeat(' ', 4096); @ob_flush(); @flush(); }

    $s_ids = isset($_POST['s_id']) ? (is_array($_POST['s_id']) ? $_POST['s_id'] : [$_POST['s_id']]) : [];
    $main_urls = isset($_POST['main_url']) ? (is_array($_POST['main_url']) ? $_POST['main_url'] : [$_POST['main_url']]) : [];
    
    $custom_domain = trim($_POST['base_domain']); $base_domain = rtrim($custom_domain, '/');
    $delay_seconds = floatval($_POST['delay']); $sleep_micro = $delay_seconds * 1000000;
    $max_episodes = intval($_POST['max_episodes']); $action_mode = $_POST['action_mode'] ?? 'all'; 
    $prevent_recent = isset($_POST['prevent_recent']);

    // فحص بنية جدول الحلقات لاختيار الأعمدة الصحيحة
    $title_col = ''; $date_col_found = '';
    try {
        $stmt_cols = $pdo->query("SHOW COLUMNS FROM episodes"); $table_columns = $stmt_cols->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('title', $table_columns)) $title_col = 'title'; elseif (in_array('name', $table_columns)) $title_col = 'name';
        if (in_array('date', $table_columns)) $date_col_found = 'date'; elseif (in_array('created_at', $table_columns)) $date_col_found = 'created_at';
        elseif (in_array('added_date', $table_columns)) $date_col_found = 'added_date'; elseif (in_array('added', $table_columns)) $date_col_found = 'added';
    } catch (Exception $e) {}

    $total_series_count = count($s_ids);
    push_to_browser("startLoadingUI();");
    push_to_browser("addLogToConsole('info', '🚀 بدء تشغيل محرك السحب المتعدد الموحد (V29.3)...');");

    for ($i = 0; $i < $total_series_count; $i++) {
        $s_id = intval($s_ids[$i]); $main_url = trim($main_urls[$i]); $original_input_url = $main_url;
        if ($s_id <= 0 || empty($main_url)) continue;

        // استخراج الدومين المصدر تلقائياً من الرابط لتجنب أخطاء الإدخال
        $current_base_domain = $base_domain;
        $parsed_url_info = parse_url($main_url);
        if (!empty($parsed_url_info['scheme']) && !empty($parsed_url_info['host'])) {
            $current_base_domain = $parsed_url_info['scheme'] . '://' . $parsed_url_info['host'];
        }

        $target_db_title = "مسلسل غير معروف";
        try {
            $stmt_title = $pdo->prepare("SELECT title FROM series WHERE id = ?"); $stmt_title->execute([$s_id]);
            $row = $stmt_title->fetch(PDO::FETCH_ASSOC); if ($row) $target_db_title = $row['title'];
        } catch (Exception $e) {}

        $target_season = detect_season_number($target_db_title); $current_series_order = $i + 1;
        push_to_browser("addLogToConsole('info', '=========================================');");
        push_to_browser("addLogToConsole('info', '🎬 جاري معالجة: [$target_db_title] (الهدف: الموسم $target_season).');");
        push_to_browser("updateProgressBar(0, 100, 'فحص الروابط: $target_db_title', 'المسلسل $current_series_order من $total_series_count');");

        $list_page = hunt_content($original_input_url, $current_base_domain);
        $list_page = preg_replace('/<div[^>]*class=["\'][^"\']*search__res__container[^"\']*["\'][^>]*>.*?<\/div>/is', '', $list_page);
        $list_page = preg_replace('/<section[^>]*class=["\'][^"\']*mobile__sarch[^"\']*["\'][^>]*>.*?<\/section>/is', '', $list_page);
        $list_page = preg_replace('/<section[^>]*class=["\'][^"\']*related_section[^"\']*["\'][^>]*>.*?<\/section>/is', '', $list_page);
        $list_page = preg_replace('/<div[^>]*class=["\'][^"\']*related__blocks[^"\']*["\'][^>]*>.*?<\/div>/is', '', $list_page);

        $html_to_parse = $list_page; $isolated = false;
        $is_single_episode = preg_match('/(video\.php|episode-|play\.php|player\.php|watch\.php|\/episode\/)/i', $original_input_url);
        
        if ($is_single_episode) {
            $path_query = ''; if (preg_match('/(?:vid=|id=|episode-|\/episode\/|\/ep\/)([^&"\']+)/i', $original_input_url, $mq)) { $path_query = $mq[1]; }
            if (preg_match_all('/<div[^>]*class=["\'][^"\']*(?:tabcontent|tab-content)[^"\']*["\'][^>]*>(.*?)<\/div>/is', $list_page, $blocks)) {
                foreach ($blocks[0] as $block) {
                    if (($path_query !== '' && stripos($block, $path_query) !== false) || stripos($block, 'active') !== false) { $html_to_parse = $block; $isolated = true; break; }
                }
            }
        }

        if (!$isolated && preg_match('/<div[^>]*class=["\']?AiredEPS["\']?[^>]*>(.*?)<\/div>/is', $list_page, $aired_match)) {
            if (preg_match('/<div[^>]*class=["\'][^"\']*tab-content\s*season-content\s*active[^"\']*["\'][^>]*>.*?<div[^>]*class=["\']?AiredEPS["\']?[^>]*>(.*?)<\/div>.*?<\/div>/is', $list_page, $active_tab_match)) {
                $html_to_parse = $active_tab_match[1];
            } else { $html_to_parse = $aired_match[1]; }
            $isolated = true; push_to_browser("addLogToConsole('info', 'تم العثور على الحلقات بنجاح في حاوية AiredEPS (Q-Drama).');");
        }

        if (!$isolated) {
            $start_pos = false;
            // إضافة حاويات (Classes) الخاصة بتصميمات لاروزا المتعددة بما فيها التصميم الجديد SeasonsBox
            $possible_classes = [
                'class="SeasonsBox"', "class='SeasonsBox'", // التصميم الجديد للاروزا
                'class="SeasonsEpisodesMain"', "class='SeasonsEpisodesMain'",
                'class="SeasonsEpisodes"', "class='SeasonsEpisodes'",
                'class="EpsList"', "class='EpsList'", 
                'class="episodes-list"', "class='episodes-list'", 
                'class="episodes__list"', 'id="episodes_list"',
                'class="episods"', "class='episods'",
                'class="box-episodes"', "class='box-episodes'",
                'class="list-episodes"', "class='list-episodes'",
                'class="EpisodesList"', "class='EpisodesList'",
                'class="epList"', "class='epList'",
                'class="Episodes"', "class='Episodes'",
                'class="all-episodes"', "class='all-episodes'",
                'class="episodes"', "class='episodes'"
            ];
            foreach ($possible_classes as $cls) {
                $pos = stripos($list_page, $cls);
                if ($pos !== false) {
                    $start_pos = strrpos(substr($list_page, 0, $pos), '<div');
                    if ($start_pos === false) $start_pos = $pos; break;
                }
            }
            if ($start_pos !== false) {
                $end_pos = false;
                $bounds = ['class="singleBottomArea"', 'class="related-posts"', 'class="footer"', 'id="footer"', 'class="clearfix"'];
                foreach ($bounds as $b) {
                    $ep = stripos($list_page, $b, $start_pos);
                    if ($ep !== false) { if ($end_pos === false || $ep < $end_pos) { $end_pos = $ep; } }
                }
                if ($end_pos === false) $end_pos = $start_pos + 15000; 
                $html_to_parse = substr($list_page, $start_pos, $end_pos - $start_pos); $isolated = true;
                push_to_browser("addLogToConsole('info', 'تم عزل قسم الحلقات بنجاح للبحث.');");
            }
        }

        $final_episode_list = [];
        preg_match_all('/<a\s+([^>]+)>(.*?)<\/a>/is', $html_to_parse, $a_tags);
        $max_ep_num = 0; $last_ep_link = null;

        if (!empty($a_tags[1])) {
            foreach ($a_tags[1] as $idx => $attrs) {
                $inner_html = $a_tags[2][$idx];
                if (preg_match('/href=["\']([^"\']+)["\']/i', $attrs, $href_match)) {
                    $href = trim($href_match[1]);
                    $decoded_href = urldecode($href); // فك التشفير للغة العربية
                    $inner_text = trim(strip_tags($inner_html));

                    // التحقق من الرابط مع دعم الكلمات العربية
                    $is_ep_link = preg_match('/(watch\.php\?|video\.php\?|episode-|\/episode\/|\/watch\/|\/ep\/|\?episode=|\/video\/|حلقه|حلقة|ep-|\/series\/|\/video-)/iu', $decoded_href) 
                                  || preg_match('/(?:حلقه|حلقة|الحلقة|الحلقه)\s*\d+/iu', $inner_text)
                                  || (stripos($attrs, 'video.php?vid=') !== false); // دعم مباشر لروابط لاروزا الجديدة

                    if ($is_ep_link) {
                        $ep_num = 0; 
                        if (preg_match('/(?:حلقه|حلقة|الحلقة|الحلقه)\s*(\d+)/iu', $inner_text, $m)) { $ep_num = (int)$m[1]; } 
                        elseif (preg_match('/(?:حلقه|حلقة|الحلقة|الحلقه)(?:-|_|\s)*(\d+)/iu', $decoded_href, $m)) { $ep_num = (int)$m[1]; } 
                        elseif (preg_match('/(?:ep|episode)(?:-|_|\s)*(\d+)/iu', $decoded_href, $m)) { $ep_num = (int)$m[1]; } 
                        elseif (preg_match('/<em[^>]*>\s*(\d+)\s*<\/em>/i', $inner_html, $m)) { $ep_num = (int)$m[1]; } // استخراج من <em> في التصميم الجديد
                        elseif (preg_match('/[-_](\d+)(?:[-_\/]|$)/u', $decoded_href, $m)) {
                            preg_match_all('/[-_](\d+)(?=[-_\/]|$)/u', rtrim($decoded_href, '/'), $all_nums);
                            $ep_num = !empty($all_nums[1]) ? (int)end($all_nums[1]) : 0;
                        } elseif (preg_match('/\b(\d+)\b/', $inner_text, $m)) { $ep_num = (int)$m[1]; }
                        
                        if ($ep_num > 0) {
                            if ($ep_num > $max_ep_num) $max_ep_num = $ep_num;
                            
                            // إصلاح الروابط النسبية
                            if (strpos($href, 'http') === 0) {
                                $full_link = $href;
                            } elseif (strpos($href, './') === 0) {
                                $full_link = rtrim($current_base_domain, '/') . substr($href, 1);
                            } else {
                                $full_link = rtrim($current_base_domain, '/') . '/' . ltrim($href, '/');
                            }
                            
                            if (strpos($href, '?') === 0) { $full_link = rtrim($current_base_domain, '/') . (stripos($current_base_domain, 'ahwak') !== false || stripos($current_base_domain, 'q-drama') !== false ? '/watch.php' : '/video.php') . $href; }
                            $final_episode_list[$ep_num] = $full_link;
                        }
                    }
                }
            }
        }
        ksort($final_episode_list);
        
        // ميزة احتياطية: سحب الحلقات من القائمة المنسدلة (Mobile Select) في حال فشل الصناديق
        if (empty($final_episode_list)) {
            if (preg_match_all('/<option[^>]*value=["\']([^"\']+)["\'][^>]*>(.*?)<\/option>/is', $list_page, $opt_tags)) {
                foreach ($opt_tags[1] as $idx => $val) {
                    $val = trim($val);
                    if ($val == 'select-ep' || empty($val)) continue;
                    $inner_text = trim(strip_tags($opt_tags[2][$idx]));
                    if (preg_match('/(watch\.php\?|video\.php\?|episode-|\/episode\/|\/watch\/|\/ep\/|\?episode=|\/video\/|حلقه|حلقة|ep-|\/series\/|\/video-)/iu', urldecode($val))) {
                        $ep_num = 0;
                        if (preg_match('/(?:حلقه|حلقة|الحلقة|الحلقه)\s*(\d+)/iu', $inner_text, $m)) { $ep_num = (int)$m[1]; }
                        elseif (preg_match('/\b(\d+)\b/', $inner_text, $m)) { $ep_num = (int)$m[1]; }
                        if ($ep_num > 0) {
                            if ($ep_num > $max_ep_num) $max_ep_num = $ep_num;
                            $full_link = (strpos($val, 'http') === 0) ? $val : rtrim($current_base_domain, '/') . '/' . ltrim($val, '/');
                            $final_episode_list[$ep_num] = $full_link;
                        }
                    }
                }
                ksort($final_episode_list);
                if(!empty($final_episode_list)) { push_to_browser("addLogToConsole('info', 'تم استخدام القائمة الاحتياطية (Mobile Dropdown) للوصول للروابط.');"); }
            }
        }

        if (empty($final_episode_list)) { push_to_browser("addLogToConsole('error', '❌ لا توجد حلقات للمسلسل $target_db_title، سيتم التخطي.');"); continue; }

        $total_found = count($final_episode_list); $actual_target = min($max_episodes, $total_found); 
        push_to_browser("addLogToConsole('success', '✅ تم حصر ($total_found) حلقات للمسلسل الحالي بدقة. سيتم سحب ($actual_target)...');");
        $processed_count = 0;

        foreach ($final_episode_list as $real_ep_num => $path) {
            if ($processed_count >= $actual_target) break;
            $ep_index = $real_ep_num;
            try {
                $q = $pdo->prepare("SELECT id FROM episodes WHERE series_id = ? AND episode_number = ?"); $q->execute([$s_id, $ep_index]);
                $is_exists = ($q->rowCount() > 0);

                if ($is_exists && $action_mode === 'insert_only') {
                    $processed_count++; push_to_browser("updateProgressBar($processed_count, $actual_target, 'تحديث السيرفرات...', 'المسلسل $current_series_order من $total_series_count | تم: $processed_count / $actual_target');"); 
                    usleep($sleep_micro); continue;
                }
                if (!$is_exists && $action_mode === 'update_only') {
                    $processed_count++; push_to_browser("updateProgressBar($processed_count, $actual_target, 'تحديث السيرفرات...', 'المسلسل $current_series_order من $total_series_count | تم: $processed_count / $actual_target');"); 
                    usleep($sleep_micro); continue;
                }

                $ep_title = ($processed_count == $actual_target - 1 && $actual_target > 1) ? "الأخيرة" : "الحلقة " . $ep_index;
                $ep_url = (strpos($path, 'http') === 0) ? $path : rtrim($current_base_domain, '/') . '/' . ltrim($path, '/');
                $video_page = hunt_content($ep_url, $main_url);
                
                $egydead_watch_html = ""; $is_egydead = (stripos($current_base_domain, 'egydead') !== false || stripos($ep_url, 'egydead') !== false);
                if ($is_egydead && stripos($ep_url, '/episode/') !== false) {
                    $watch_url = rtrim(strtok($ep_url, '?'), '/') . '/?view=watch'; $egydead_watch_html = hunt_content($watch_url, $ep_url);
                }
                $see_url = "";
                if (stripos($ep_url, 'watch.php') !== false && stripos($ep_url, 'vid=') !== false) { $see_url = str_ireplace('watch.php', 'see.php', $ep_url); }
                $see_page_html = ""; if (!empty($see_url)) { $see_page_html = hunt_content($see_url, $ep_url); }
                
                $play_page_html = "";
                if (preg_match('/(?:src|data-src|href)=["\']([^"\']*(?:play\.php|player\.php)[^"\']*)["\']/i', $video_page, $play_match)) {
                    $play_url = $play_match[1]; if(strpos($play_url, 'http') !== 0) $play_url = rtrim($current_base_domain, '/') . '/' . ltrim($play_url, '/');
                    $play_page_html = hunt_content($play_url, $ep_url);
                } 

                $combined_watch_pages = $egydead_watch_html . " " . $see_page_html . " " . $play_page_html . " " . $video_page;
                $servers = parse_final_servers($combined_watch_pages, $current_base_domain, $ep_url);
                $dl_servers = ['d1' => '', 'd2' => ''];
                $watch_page_for_dl = $is_egydead ? $egydead_watch_html : $video_page;
                $dl_servers_main = parse_download_servers(!empty($watch_page_for_dl) ? $watch_page_for_dl : $video_page, $current_base_domain);
                
                if (!empty($dl_servers_main['d1'])) {
                    $dl_servers = $dl_servers_main;
                } else {
                    $dl_url_target = str_replace(['video.php', 'episode-', 'watch.php'], ['download.php', 'download-', 'download.php'], $ep_url);
                    if ($dl_url_target !== $ep_url) {
                        $dl_page = hunt_content($dl_url_target, $ep_url); $dl_servers_fallback = parse_download_servers($dl_page, $current_base_domain);
                        if (!empty($dl_servers_fallback['d1'])) { $dl_servers = $dl_servers_fallback; }
                    }
                }

                $dl_servers['d1'] = trim((string)$dl_servers['d1']); $dl_servers['d2'] = trim((string)$dl_servers['d2']);
                if ($dl_servers['d1'] === '' && $dl_servers['d2'] !== '') $dl_servers['d1'] = $dl_servers['d2'];
                if ($dl_servers['d2'] === '' && $dl_servers['d1'] !== '') $dl_servers['d2'] = $dl_servers['d1'];

                if (!empty($servers['s1']) || !empty($servers['s2']) || !empty($servers['s3']) || !empty($servers['s4'])) {
                    $dl_text = !empty($dl_servers['d1']) ? get_domain($dl_servers['d1']) : 'لا يوجد تحميل';
                    $servers_text = addslashes("📺: " . get_domain($servers['s1']) . " | 📥: " . $dl_text);

                    if ($is_exists) {
                        $up_sql = "UPDATE episodes SET watch_link = :s1, watch_link_2 = :s2, watch_link_3 = :s3, watch_link_4 = :s4, download_link = :d1, download_link_2 = :d2 ";
                        $update_params = [':s1' => $servers['s1'], ':s2' => $servers['s2'], ':s3' => $servers['s3'], ':s4' => $servers['s4'], ':d1' => $dl_servers['d1'], ':d2' => $dl_servers['d2'], ':sid' => $s_id, ':enum' => $ep_index];
                        if ($prevent_recent && $date_col_found !== '') $up_sql .= ", $date_col_found = '2022-01-01 12:00:00' ";
                        if ($title_col !== '') { $up_sql .= ", $title_col = :title "; $update_params[':title'] = $ep_title; }
                        $up_sql .= " WHERE series_id = :sid AND episode_number = :enum";
                        $pdo->prepare($up_sql)->execute($update_params);
                        push_to_browser("addLogToConsole('warning', '♻️ تحديث الحلقة $ep_index ➜ $servers_text');");
                    } else {
                        $insert_fields = ['series_id', 'episode_number', 'watch_link', 'watch_link_2', 'watch_link_3', 'watch_link_4', 'download_link', 'download_link_2'];
                        $insert_values = [$s_id, $ep_index, $servers['s1'], $servers['s2'], $servers['s3'], $servers['s4'], $dl_servers['d1'], $dl_servers['d2']];
                        if ($title_col !== '') { $insert_fields[] = $title_col; $insert_values[] = $ep_title; }
                        if ($prevent_recent && $date_col_found !== '') { $insert_fields[] = $date_col_found; $insert_values[] = '2022-01-01 12:00:00'; }
                        $placeholders = implode(',', array_fill(0, count($insert_fields), '?'));
                        $fields_str = implode(',', $insert_fields);
                        $pdo->prepare("INSERT INTO episodes ($fields_str) VALUES ($placeholders)")->execute($insert_values);
                        push_to_browser("addLogToConsole('success', '✨ إنشاء حلقة $ep_index ➜ $servers_text');");
                    }
                } else { push_to_browser("addLogToConsole('error', 'الحلقة ($ep_index): فشل السحب أو لم يتم إيجاد سيرفرات مشاهدة.');"); }
            } catch (Exception $e) { $err = addslashes($e->getMessage()); push_to_browser("addLogToConsole('error', 'خطأ داتا بيز حلقة ($ep_index): $err');"); }
            
            $processed_count++;
            push_to_browser("updateProgressBar($processed_count, $actual_target, 'جاري السحب...', 'المسلسل $current_series_order من $total_series_count | تم: $processed_count / $actual_target');"); 
            usleep($sleep_micro + rand(10000, 50000));
        }
    } 
    push_to_browser("finishProcess();");
}
?>
</body>
</html>