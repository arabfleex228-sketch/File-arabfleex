<?php
/**
 * أداة استخراج السيرفرات V4.0 (إصدار قاهر التشفير - عرب سيد والمواقع المعقدة)
 */

$extracted_servers = [];
$total_links = 0;
$error_msg = '';
$seen_urls = []; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['html_code'])) {
    $html_content = $_POST['html_code'];

    if (empty(trim($html_content))) {
        $error_msg = "الرجاء لصق كود HTML أولاً.";
    } else {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        @$dom->loadHTML(mb_convert_encoding($html_content, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        // ==========================================
        // 1. محرك فك التشفير المتقدم (لحل مشكلة عرب سيد)
        // ==========================================
        $smartDecode = function($string) {
            $string = trim($string);
            // إذا كان النص قصير جداً لا داعي لفحصه
            if (empty($string) || strlen($string) < 15) return false;

            // تنظيف السلسلة من الشوائب التي توضع للتمويه
            $clean_string = str_replace(['"', "'", ' ', '\\', '\/'], ['', '', '', '', '/'], $string);

            $possibilities = [
                $clean_string, 
                @base64_decode($clean_string), // Base64 العادي
                @base64_decode(strrev($clean_string)), // Base64 معكوس (خدعة عرب سيد الشهيرة)
                strrev(@base64_decode($clean_string)), // فك ثم عكس
                @base64_decode(str_rot13($clean_string)), // ROT13 (خدعة الإزاحة التي تشبه Base23)
                str_rot13(@base64_decode($clean_string)), 
                @urldecode(@base64_decode($clean_string))
            ];

            foreach ($possibilities as $decoded) {
                if (is_string($decoded) && filter_var($decoded, FILTER_VALIDATE_URL)) {
                    return $decoded;
                }
                if (is_string($decoded) && strpos($decoded, '//') === 0 && filter_var('https:' . $decoded, FILTER_VALIDATE_URL)) {
                    return 'https:' . $decoded;
                }
            }
            return false;
        };

        // ==========================================
        // 2. فلتر التنظيف الصارم لمنع الزبالة (كابتشا/اعلانات)
        // ==========================================
        $addServer = function($category, $name, $url) use (&$extracted_servers, &$total_links, &$seen_urls) {
            if (strpos($url, '//') === 0) { $url = 'https:' . $url; }
            $url = trim($url);

            // منع امتدادات الملفات غير المرغوبة
            $blacklist_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'css', 'js', 'svg', 'woff', 'ttf', 'ico'];
            $path = parse_url($url, PHP_URL_PATH) ?? '';
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($ext, $blacklist_ext)) return;

            // منع دومينات الإعلانات والكابتشا والتتبع
            $blacklist_domains = ['google', 'facebook', 'twitter', 'analytics', 'recaptcha', 'doubleclick', 'googletagmanager', 'fontawesome', 'w3.org'];
            $domain = isset(parse_url($url)['host']) ? strtolower(parse_url($url)['host']) : '';
            foreach ($blacklist_domains as $bad) {
                if (strpos($domain, $bad) !== false) return;
            }

            // منع الكلمات الدلالية في الرابط (لحل مشكلة securimage وغيرها)
            $blacklist_keywords = ['securimage', 'captcha', 'blank.html', 'about:blank', 'advert', 'banner', 'pixel', 'tracking', 'chat', 'javascript:'];
            $url_lower = strtolower($url);
            foreach ($blacklist_keywords as $bad_word) {
                if (strpos($url_lower, $bad_word) !== false) return;
            }

            // الإضافة النهائية
            if (filter_var($url, FILTER_VALIDATE_URL) && !in_array($url, $seen_urls)) {
                $clean_domain = str_ireplace('www.', '', $domain);
                $extracted_servers[$category][] = [
                    'name' => !empty($name) ? mb_substr(trim(strip_tags($name)), 0, 30, 'UTF-8') : 'سيرفر مشاهدة',
                    'domain' => $clean_domain ?: 'سيرفر',
                    'url' => $url
                ];
                $seen_urls[] = $url;
                $total_links++;
            }
        };

        // ==========================================
        // 3. استخراج الإطارات والمشغلات الصريحة
        // ==========================================
        $media_tags = $xpath->query('//iframe | //video | //source');
        foreach ($media_tags as $tag) {
            $src = $tag->getAttribute('src') ?: $tag->getAttribute('data-src');
            $decoded_src = $smartDecode($src) ?: $src;
            if (!empty($decoded_src)) {
                $name = $tag->getAttribute('title') ?: 'إطار / مشغل مباشر';
                $addServer('سيرفرات ومشغلات مباشرة', $name, $decoded_src);
            }
        }

        // ==========================================
        // 4. قاهر عرب سيد (المرور على كل خصائص Data)
        // ==========================================
        // الكود هنا لا يبحث عن اسم خاصية معينة، بل يمر على كل عناصر الـ HTML
        // ويبحث داخل أي خاصية تبدأ بـ "data-"، مما يكسر أي تغيير في الأسماء من عرب سيد
        $allElements = $dom->getElementsByTagName('*');
        foreach ($allElements as $elem) {
            if ($elem->hasAttributes()) {
                foreach ($elem->attributes as $attr) {
                    if (strpos($attr->nodeName, 'data-') === 0) {
                        $val = $attr->nodeValue;
                        $decoded_src = $smartDecode($val);
                        
                        if ($decoded_src) {
                            $name = $elem->textContent ? trim(strip_tags($elem->textContent)) : '';
                            if (empty($name) || is_numeric($name)) {
                                $name = 'زر سيرفر (' . str_replace('data-', '', $attr->nodeName) . ')';
                            }
                            $addServer('سيرفرات مشفرة في الأزرار', $name, $decoded_src);
                        }
                    }
                }
            }
        }

        // ==========================================
        // 5. الفحص الخام (Raw HTML Scan) - الملاذ الأخير
        // ==========================================
        // يبحث عن أي نص عشوائي في الصفحة (في الجافاسكربت أو الـ HTML) يبدو كأنه Base64
        // ويحاول فكه.. هذا يضرب تشفير عرب سيد في مقتل لو تم إخفاء الرابط في سكربت
        preg_match_all('/["\'>=]([a-zA-Z0-9+\/]{30,}={0,2})["\'<]/', $html_content, $raw_b64);
        if (!empty($raw_b64[1])) {
            foreach ($raw_b64[1] as $match) {
                $decoded = $smartDecode($match);
                if ($decoded) {
                    $addServer('استخراج خام (فك تشفير عميق)', 'سيرفر مخفي بشدة', $decoded);
                }
            }
        }
        
        // البحث عن روابط m3u8 و mp4 صريحة عائمة في الكود
        preg_match_all('/(https?:\/\/[^"\'\s<>]+?\.(?:m3u8|mp4|mkv|webm))/i', $html_content, $direct_matches);
        if (!empty($direct_matches[1])) {
            foreach ($direct_matches[1] as $match) {
                $addServer('روابط ميديا مسربة', 'رابط وسائط', $match);
            }
        }
    }
}
?>

<!-- هيدر الصفحة -->
<div class="section-header">
    <div>
        <h2 class="section-title">
            <i class="fas fa-radar golden-text ml-2"></i> مستخرج سيرفرات المشاهدة الذكي
        </h2>
        <p class="text-text-secondary mt-2 text-sm font-semibold">يقوم بكسر حماية المواقع المعقدة (عرب سيد، ماي سيما) باستخراج الإطارات، فك الـ Base64 العكسي، والفحص الشامل.</p>
    </div>
</div>

<!-- عرض الأخطاء إن وجدت -->
<?php if (!empty($error_msg)): ?>
    <div class="bg-red-500 bg-opacity-20 border border-red-500 text-red-200 px-4 py-3 rounded-lg mb-6 flex items-center shadow-lg">
        <i class="fas fa-exclamation-triangle ml-3 text-xl"></i>
        <span class="font-bold"><?php echo $error_msg; ?></span>
    </div>
<?php endif; ?>

<!-- صندوق إدخال الكود -->
<div class="mb-8" style="background-color: var(--bg-card); backdrop-filter: blur(10px); border: 1px solid var(--border-color); border-radius: 1rem; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);">
    <form method="POST" action="">
        <label class="form-label text-brand-gold mb-3 flex items-center">
            <i class="fas fa-code ml-2"></i> كود المصدر (HTML) لصفحة المشاهدة
        </label>
        <textarea name="html_code" rows="7" class="form-textarea mb-4 text-left font-mono text-sm" dir="ltr" placeholder="<!-- 
الصق كود الـ HTML لموقع عرب سيد أو أي موقع هنا...
هذا الإصدار يبحث داخل (كل) الخصائص المخفية في الكود،
ويقوم بتجربة أكثر من 6 طرق لفك التشفير تلقائياً!
-->"></textarea>
        
        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary text-lg px-8 py-3">
                <i class="fas fa-unlock-alt ml-2"></i> استخراج وفك التشفير
            </button>
        </div>
    </form>
</div>

<!-- قسم عرض النتائج -->
<?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error_msg)): ?>
    <div style="background-color: var(--bg-card); backdrop-filter: blur(10px); border: 1px solid var(--border-color); border-radius: 1rem; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);">
        
        <div class="flex items-center justify-between mb-6 border-b border-gray-700 pb-4">
            <h3 class="text-xl font-bold text-white flex items-center">
                <i class="fas fa-check-circle text-green-400 ml-2"></i> السيرفرات المستخرجة
            </h3>
            <span class="bg-yellow-500 bg-opacity-20 text-brand-gold px-4 py-1.5 rounded-full text-sm font-bold border border-yellow-500 border-opacity-30">
                إجمالي: <?php echo $total_links; ?> رابط
            </span>
        </div>

        <?php if (!empty($extracted_servers)): ?>
            
            <div class="space-y-6">
                <!-- حلقة لعرض الروابط -->
                <?php foreach ($extracted_servers as $category => $servers): ?>
                    <div class="bg-black bg-opacity-40 rounded-xl border border-gray-800 p-4">
                        
                        <h4 class="text-lg font-black golden-text mb-4 flex items-center">
                            <i class="fas fa-folder-open ml-2 text-gray-400"></i>
                            <?php echo htmlspecialchars($category); ?>
                        </h4>
                        
                        <div class="grid grid-cols-1 gap-3">
                            <?php foreach ($servers as $index => $server): ?>
                                <?php $unique_id = "server_" . md5($category . $index . $server['url']); ?>
                                
                                <div class="bg-gray-800 bg-opacity-50 border border-gray-700 hover:border-brand-gold hover:bg-gray-800 transition-all rounded-lg p-3 flex flex-col md:flex-row items-start md:items-center justify-between group">
                                    
                                    <div class="flex-1 w-full md:w-auto mb-3 md:mb-0 md:pl-4">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="bg-gray-900 text-gray-300 px-2 py-0.5 rounded text-xs font-bold border border-gray-700 uppercase tracking-wider">
                                                <?php echo htmlspecialchars($server['domain']); ?>
                                            </span>
                                            <span class="text-white font-bold text-sm">
                                                <?php echo htmlspecialchars($server['name'] == '' ? 'سيرفر مشاهدة' : $server['name']); ?>
                                            </span>
                                        </div>
                                        <input type="text" readonly value="<?php echo htmlspecialchars($server['url']); ?>" class="form-input text-xs py-1.5 font-mono text-gray-400 focus:text-white w-full" dir="ltr" id="<?php echo $unique_id; ?>">
                                    </div>

                                    <div class="flex gap-2 w-full md:w-auto mt-2 md:mt-0 justify-end">
                                        <button onclick="copyLink('<?php echo $unique_id; ?>', this)" class="btn btn-secondary btn-sm flex-1 md:flex-none justify-center">
                                            <i class="fas fa-copy"></i> نسخ
                                        </button>
                                        <a href="<?php echo htmlspecialchars($server['url']); ?>" target="_blank" class="btn btn-primary btn-sm flex items-center justify-center" title="تشغيل / معاينة">
                                            <i class="fas fa-play m-0"></i>
                                        </a>
                                    </div>

                                </div>
                            <?php endforeach; ?>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <div class="text-center py-10">
                <i class="fas fa-eye-slash text-6xl text-gray-600 mb-4 block"></i>
                <h3 class="text-xl text-text-secondary font-bold mb-2">لم نتمكن من إيجاد أي سيرفرات!</h3>
                <p class="text-gray-500 text-sm">إذا كان الموقع هو عرب سيد، حاول فتح الأداة للمطورين (F12) ونسخ الـ HTML من هناك بعد تحميل الصفحة بالكامل.</p>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
    function copyLink(elementId, btnElement) {
        var copyText = document.getElementById(elementId);
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        document.execCommand("copy");
        
        var originalHTML = btnElement.innerHTML;
        var originalClass = btnElement.className;
        
        btnElement.innerHTML = '<i class="fas fa-check text-black"></i> تم النسخ';
        btnElement.className = "btn btn-primary btn-sm flex-1 md:flex-none justify-center";
        
        setTimeout(function() {
            btnElement.innerHTML = originalHTML;
            btnElement.className = originalClass;
        }, 1500);
    }
</script>