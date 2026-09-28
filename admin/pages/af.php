<?php
/**
 * أداة استخراج وفك تشفير سيرفرات التحميل (مدمجة مع تصميم لوحة التحكم)
 */

$extracted_servers = [];
$total_links = 0;
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['html_code'])) {
    $html_content = $_POST['html_code'];

    if (empty(trim($html_content))) {
        $error_msg = "الرجاء لصق كود HTML أولاً.";
    } else {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        // تحميل الكود مع تجاهل أخطاء HTML
        @$dom->loadHTML(mb_convert_encoding($html_content, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        // 1. استهداف حاويات الجودة (مثل 1080p, 720p)
        $quality_tabs = $xpath->query("//div[contains(@class, 'tab__inner')]");

        if ($quality_tabs && $quality_tabs->length > 0) {
            foreach ($quality_tabs as $tab) {
                // استخراج الجودة
                $quality = $tab->getAttribute('data-quality'); 
                if (empty($quality)) $quality = "أخرى";

                // استهداف روابط التحميل داخل هذه الجودة
                $links = $xpath->query(".//a[contains(@class, 'download__item')]", $tab);
                
                foreach ($links as $link) {
                    $href = $link->getAttribute('href');
                    
                    // استخراج اسم السيرفر
                    $server_name_node = $xpath->query(".//h4", $link)->item(0);
                    $server_name = $server_name_node ? trim($server_name_node->textContent) : 'سيرفر غير معروف';

                    // استخراج كود Base64 الذي يأتي بعد /l/ وفك تشفيره
                    if (preg_match('/\/l\/([a-zA-Z0-9+\/=-]+)/', $href, $matches)) {
                        $base64_code = $matches[1];
                        $clean_url = base64_decode($base64_code); 

                        // التأكد من أن الناتج هو رابط صالح
                        if (filter_var($clean_url, FILTER_VALIDATE_URL)) {
                            $parsed = parse_url($clean_url);
                            $domain = isset($parsed['host']) ? str_ireplace('www.', '', strtolower($parsed['host'])) : 'unknown';

                            $extracted_servers[$quality][] = [
                                'name' => $server_name,
                                'domain' => $domain,
                                'url' => $clean_url
                            ];
                            $total_links++;
                        }
                    }
                }
            }
        } 
        
        // 2. خطة بديلة (Fallback) في حال لم تكن مقسمة بجودات، نبحث عن أي رابط مشفر
        if (empty($extracted_servers)) {
            preg_match_all('/href="[^"]*\/l\/([a-zA-Z0-9+\/=-]+)"/i', $html_content, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $b64) {
                    $clean_url = base64_decode($b64);
                    if (filter_var($clean_url, FILTER_VALIDATE_URL)) {
                        $parsed = parse_url($clean_url);
                        $domain = isset($parsed['host']) ? str_ireplace('www.', '', strtolower($parsed['host'])) : 'unknown';
                        
                        $extracted_servers['روابط مستخرجة'][] = [
                            'name' => 'سيرفر تحميل',
                            'domain' => $domain,
                            'url' => $clean_url
                        ];
                        $total_links++;
                    }
                }
            }
        }
    }
}
?>

<!-- هيدر الصفحة -->
<div class="section-header">
    <div>
        <h2 class="section-title">
            <i class="fas fa-server golden-text ml-2"></i> مستخرج سيرفرات التحميل
        </h2>
        <p class="text-text-secondary mt-2 text-sm font-semibold">استخراج وتفكيك الروابط المشفرة من مصادر خارجية وتصنيفها.</p>
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
            <i class="fas fa-code ml-2"></i> كود المصدر (HTML)
        </label>
        <textarea name="html_code" rows="7" class="form-textarea mb-4 text-left font-mono text-sm" dir="ltr" placeholder="<!-- 
1. اذهب لصفحة الفيلم في الموقع المستهدف
2. اضغط Ctrl+U لفتح كود المصدر
3. انسخ الكود بالكامل (Ctrl+A ثم Ctrl+C)
4. الصقه هنا واضغط استخراج 
-->"></textarea>
        
        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary text-lg px-8 py-3">
                <i class="fas fa-rocket ml-2"></i> استخراج وفك التشفير
            </button>
        </div>
    </form>
</div>

<!-- قسم عرض النتائج -->
<?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error_msg)): ?>
    <div style="background-color: var(--bg-card); backdrop-filter: blur(10px); border: 1px solid var(--border-color); border-radius: 1rem; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);">
        
        <div class="flex items-center justify-between mb-6 border-b border-gray-700 pb-4">
            <h3 class="text-xl font-bold text-white flex items-center">
                <i class="fas fa-check-circle text-green-400 ml-2"></i> السيرفرات الصافية المستخرجة
            </h3>
            <span class="bg-yellow-500 bg-opacity-20 text-brand-gold px-4 py-1.5 rounded-full text-sm font-bold border border-yellow-500 border-opacity-30">
                <?php echo $total_links; ?> رابط تم فكه
            </span>
        </div>

        <?php if (!empty($extracted_servers)): ?>
            
            <div class="space-y-6">
                <!-- حلقة لعرض الروابط مقسمة حسب الجودة -->
                <?php foreach ($extracted_servers as $quality => $servers): ?>
                    <div class="bg-black bg-opacity-40 rounded-xl border border-gray-800 p-4">
                        
                        <h4 class="text-lg font-black golden-text mb-4 flex items-center">
                            <i class="fas fa-film ml-2"></i>
                            جودة: <?php echo htmlspecialchars($quality); ?>
                            <?php if (is_numeric($quality)) echo 'p'; ?>
                        </h4>
                        
                        <div class="grid grid-cols-1 gap-3">
                            <?php foreach ($servers as $index => $server): ?>
                                <?php $unique_id = "link_" . md5($quality . $index . $server['url']); ?>
                                
                                <!-- كارت السيرفر الواحد -->
                                <div class="bg-gray-800 bg-opacity-50 border border-gray-700 hover:border-brand-gold hover:bg-gray-800 transition-all rounded-lg p-3 flex flex-col md:flex-row items-start md:items-center justify-between group">
                                    
                                    <div class="flex-1 w-full md:w-auto mb-3 md:mb-0 md:pl-4">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="bg-gray-900 text-gray-300 px-2 py-0.5 rounded text-xs font-bold border border-gray-700 uppercase tracking-wider">
                                                <?php echo htmlspecialchars($server['domain']); ?>
                                            </span>
                                            <span class="text-white font-bold text-sm">
                                                <?php echo htmlspecialchars($server['name']); ?>
                                            </span>
                                        </div>
                                        <input type="text" readonly value="<?php echo htmlspecialchars($server['url']); ?>" class="form-input text-xs py-1.5 font-mono text-gray-400 focus:text-white" dir="ltr" id="<?php echo $unique_id; ?>">
                                    </div>

                                    <div class="flex gap-2 w-full md:w-auto mt-2 md:mt-0 justify-end">
                                        <button onclick="copyLink('<?php echo $unique_id; ?>', this)" class="btn btn-secondary btn-sm flex-1 md:flex-none justify-center">
                                            <i class="fas fa-copy"></i> نسخ
                                        </button>
                                        <a href="<?php echo htmlspecialchars($server['url']); ?>" target="_blank" class="btn btn-primary btn-sm flex items-center justify-center" title="فتح الرابط للتجربة">
                                            <i class="fas fa-external-link-alt m-0"></i>
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
                <i class="fas fa-search-minus text-6xl text-gray-600 mb-4 block"></i>
                <h3 class="text-xl text-text-secondary font-bold mb-2">لم نجد روابط سيرفرات مخفية!</h3>
                <p class="text-gray-500 text-sm">تأكد من أن الكود الذي نسخته يحتوي بالفعل على أزرار تحميل.</p>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- سكربت النسخ متوافق مع تصميم اللوحة -->
<script>
    function copyLink(elementId, btnElement) {
        var copyText = document.getElementById(elementId);
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        document.execCommand("copy");
        
        var originalHTML = btnElement.innerHTML;
        var originalClass = btnElement.className;
        
        // تحويل الزر للشكل الناجح (ذهبي)
        btnElement.innerHTML = '<i class="fas fa-check text-black"></i> تم النسخ';
        btnElement.className = "btn btn-primary btn-sm flex-1 md:flex-none justify-center";
        
        setTimeout(function() {
            btnElement.innerHTML = originalHTML;
            btnElement.className = originalClass;
        }, 1500);
    }
</script>