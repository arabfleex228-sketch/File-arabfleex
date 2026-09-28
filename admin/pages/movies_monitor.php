<?php
/**
 * رادار المتابعة الشخصي - عرب فليكس
 * مبني بالكامل على الهندسة العكسية لأكواد HTML الخاصة بعرب سيد ولاروزا
 */

$state_file = __DIR__ . '/movies_state.json';
$arabseed_base = "https://asd.pics"; 
$arabseed_target = $arabseed_base . "/recently/"; 

// معالجة الدومين الخاص بلاروزا
if (isset($_POST['update_laroza_url'])) {
    $input_url = rtrim(trim($_POST['new_laroza_url']), '/');
    $input_url = str_replace(['/newvideos1.php', '/home.24'], '', $input_url);
    $_SESSION['laroza_url'] = $input_url;
}
$laroza_base = isset($_SESSION['laroza_url']) ? $_SESSION['laroza_url'] : "https://larozaa.website";
$laroza_target = $laroza_base . "/newvideos1.php"; 

$debug_messages = [];

// معالجة زر الفرمتة
if (isset($_POST['reset_data'])) {
    if (file_exists($state_file)) {
        unlink($state_file);
    }
    $debug_messages[] = "<span class='text-red-400'>[فرمتة]</span> تم مسح الذاكرة بالكامل. الأرشيف الآن نظيف.";
    $current_data = [];
    $previous_data = [];
} else {
    $previous_data = [];
    if (file_exists($state_file)) {
        $file_content = file_get_contents($state_file);
        if ($file_content) {
            $previous_data = json_decode($file_content, true) ?: [];
        }
    }
    $current_data = $previous_data;
}

// دالة الجلب
function fetchHTML_Advanced($url, &$debug_messages, $site_name) {
    $ch = curl_init();
    $headers = [
        "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
        "Accept-Language: ar,en-US;q=0.9,en;q=0.8",
        "Cache-Control: no-cache",
        "Connection: keep-alive",
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36",
        "Upgrade-Insecure-Requests: 1"
    ];

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_REFERER, 'https://www.google.com/');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_ENCODING, ""); 
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $html = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpcode == 200) {
        return $html;
    } else {
        $debug_messages[] = "<span class='text-red-500'>[خطأ]</span> فشل الاتصال بـ ($site_name) - كود: $httpcode";
        return false;
    }
}

// القناص: يستخرج بناءً على الكلاسات الأصلية للمواقع
function extractSniperCards($html, $site_name, &$debug_messages, $base_url) {
    $results = [];
    if (!$html) return $results;

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    $xpath = new DOMXPath($dom);

    // فحص حماية كلاود فلير
    $pageTitle = "";
    $titleNodes = $xpath->query('//title');
    if ($titleNodes->length > 0) $pageTitle = trim($titleNodes->item(0)->nodeValue);
    
    if (stripos($pageTitle, 'Just a moment') !== false || stripos($pageTitle, 'Cloudflare') !== false) {
        $debug_messages[] = "<span class='text-red-500'>[حماية نشطة]</span> صفحة ($site_name) تطلب التحقق، لا يمكن سحب الإضافات.";
        return $results;
    }

    $elements = null;

    // استهداف الكلاسات الصحيحة المأخوذة من أكواد HTML اللي المطور بعتها
    if ($site_name == 'عرب سيد') {
        $elements = $xpath->query('//a[contains(@class, "episode__item") or contains(@class, "movie__block")] | //li[contains(@class, "box__")]//a');
    } elseif ($site_name == 'لاروزا') {
        $elements = $xpath->query('//div[contains(@class, "pm-video-thumb")]//a | //div[contains(@class, "video-item")]//a | //div[contains(@class, "box")]//a');
    }

    if ($elements && $elements->length > 0) {
        $count = 0;
        foreach ($elements as $link) {
            if ($count >= 30) break; // سحب أحدث 30 فقط

            $href = $link->getAttribute('href');
            if (empty($href) || $href == '#' || $href == '/') continue;

            // جلب العنوان (أولاً من خاصية title لأن عرب سيد بيستخدمها)
            $title = trim($link->getAttribute('title'));
            
            // ثانياً من الصورة
            if (empty($title)) {
                $img = $xpath->query('.//img', $link)->item(0);
                if ($img) {
                    $title = trim($img->getAttribute('alt') ?: $img->getAttribute('title'));
                }
            }

            // ثالثاً من النص الداخلي
            if (empty($title)) {
                $title = trim($link->textContent);
            }

            $title = preg_replace('/\s+/', ' ', $title);
            
            // فلترة بسيطة لمنع الروابط الخاطئة
            if (empty($title) || mb_strlen($title, 'UTF-8') < 4) continue;
            if (stripos($href, 'category') !== false || stripos($href, 'genre') !== false) continue;
            
            // تحويل الروابط النسبية إلى مطلقة (عشان لاروزا)
            if (strpos($href, 'http') !== 0) {
                $parsed_base = parse_url($base_url);
                $scheme_host = $parsed_base['scheme'] . '://' . $parsed_base['host'];
                $href = rtrim($scheme_host, '/') . '/' . ltrim($href, '/');
            }

            if (!isset($results[$href])) {
                $results[$href] = ['title' => $title, 'link' => $href, 'site' => $site_name, 'time' => time()];
                $count++;
            }
        }
        
        if ($count > 0) {
            $debug_messages[] = "<span class='text-green-400'>[نجاح]</span> تم قنص أحدث $count حلقة/فيلم من ($site_name).";
        } else {
            $debug_messages[] = "<span class='text-yellow-400'>[تحذير]</span> وجدنا الأكواد في ($site_name) لكن العناوين فارغة.";
        }
    } else {
        $debug_messages[] = "<span class='text-red-400'>[فشل]</span> لم نعثر على كلاسات الأفلام في ($site_name).";
    }

    return $results;
}

$new_notifications = [];

// معالجة زر الفحص
if (isset($_POST['check_now'])) {
    
    $debug_messages[] = "<span class='text-blue-300'>[جاري الفحص]</span> فحص صفحة: <span dir='ltr'>$arabseed_target</span>";
    $arabseed_html = fetchHTML_Advanced($arabseed_target, $debug_messages, 'عرب سيد');
    $arabseed_items = extractSniperCards($arabseed_html, 'عرب سيد', $debug_messages, $arabseed_base);

    $debug_messages[] = "<span class='text-blue-300'>[جاري الفحص]</span> فحص صفحة: <span dir='ltr'>$laroza_target</span>";
    $laroza_html = fetchHTML_Advanced($laroza_target, $debug_messages, 'لاروزا');
    $laroza_items = extractSniperCards($laroza_html, 'لاروزا', $debug_messages, $laroza_base);

    $fetched_data = array_merge($arabseed_items, $laroza_items);

    foreach ($fetched_data as $link => $item) {
        if (!isset($previous_data[$link])) {
            $new_notifications[] = $item; 
        }
        $current_data[$link] = $item; 
    }

    if (!empty($fetched_data)) {
        $merged_data = array_merge($current_data, $previous_data);
        $merged_data = array_slice($merged_data, 0, 150, true);
        file_put_contents($state_file, json_encode($merged_data));
        $previous_data = $merged_data;
    }
}

?>

<div class="section-header">
    <div>
        <h1 class="section-title text-brand-gold"><i class="fas fa-satellite-dish mr-2"></i> رادار المتابعة الشخصي</h1>
        <p class="text-text-secondary mt-2">يعتمد على القناص البرمجي لقراءة الكلاسات الأصلية للمواقع وسحب أحدث الإضافات بدقة.</p>
    </div>
</div>

<div style="background-color: var(--bg-card); border: 1px solid var(--border-color);" class="p-6 rounded-xl mb-6 shadow-lg backdrop-blur-md">
    <form method="POST" action="index.php?page=movies_monitor" class="flex flex-col md:flex-row gap-4 items-end">
        
        <div class="flex-1 w-full">
            <label class="form-label text-brand-gold"><i class="fas fa-link mr-1"></i> دومين لاروزا الحالي (الرئيسي فقط):</label>
            <input type="url" name="new_laroza_url" value="<?php echo htmlspecialchars($laroza_base); ?>" class="form-input" dir="ltr" placeholder="https://larozaa.website" required>
            <p class="text-xs text-text-secondary mt-2">الرادار يفحص: <span class="text-red-400" dir="ltr">/recently/</span> لعرب سيد | و <span class="text-pink-400" dir="ltr">/newvideos1.php</span> للاروزا</p>
        </div>
        
        <div class="flex flex-wrap gap-2 w-full md:w-auto">
            <button type="submit" name="update_laroza_url" class="btn btn-secondary flex-1 md:flex-none justify-center">
                <i class="fas fa-save"></i> حفظ الدومين
            </button>
            
            <button type="submit" name="check_now" class="btn btn-primary flex-1 md:flex-none justify-center">
                <i class="fas fa-sync-alt"></i> تحديث الرادار
            </button>
            
            <button type="submit" name="reset_data" class="btn btn-danger flex-1 md:flex-none justify-center" onclick="return confirm('هل أنت متأكد أنك تريد مسح كل الروابط المحفوظة والبدء من جديد؟');">
                <i class="fas fa-trash-alt"></i> فرمتة الذاكرة
            </button>
        </div>

    </form>
</div>

<?php if (!empty($debug_messages)): ?>
<div class="mb-6 p-4 rounded-xl border border-gray-600 bg-gray-900/80">
    <h3 class="text-sm font-bold text-gray-300 mb-2 border-b border-gray-700 pb-2"><i class="fas fa-terminal"></i> تقرير القناص المباشر:</h3>
    <ul class="text-xs space-y-2 font-mono text-gray-300" dir="rtl">
        <?php foreach($debug_messages as $msg): ?>
            <li><?php echo $msg; ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if (isset($_POST['check_now']) && !empty($new_notifications)): ?>
    <div class="bg-emerald-900/30 border border-emerald-500/50 p-6 rounded-xl shadow-lg mb-8">
        <h2 class="text-xl font-bold text-emerald-400 mb-5 flex items-center gap-2">
            <i class="fas fa-bolt text-2xl animate-pulse text-yellow-500"></i> حصرياً! إضافات نزلت حالا (<?php echo count($new_notifications); ?>)
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            <?php foreach ($new_notifications as $note): ?>
                <div style="background-color: rgba(0,0,0,0.4); border: 1px solid rgba(255, 255, 255, 0.1);" class="p-3 rounded-lg flex flex-col justify-between transition hover:border-brand-gold h-full">
                    <div class="mb-3">
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded text-white <?php echo $note['site'] == 'عرب سيد' ? 'bg-red-600/80' : 'bg-pink-600/80'; ?>">
                            <?php echo htmlspecialchars($note['site']); ?>
                        </span>
                        <div class="mt-2 font-bold text-sm text-gray-200 line-clamp-2" title="<?php echo htmlspecialchars($note['title']); ?>">
                            <?php echo htmlspecialchars($note['title']); ?>
                        </div>
                    </div>
                    <a href="<?php echo htmlspecialchars($note['link']); ?>" target="_blank" class="btn btn-sm btn-primary text-xs w-full justify-center">
                        <i class="fas fa-play"></i> مشاهدة
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="mb-2 flex justify-between items-center mt-4">
    <h2 class="text-xl font-bold text-white mb-4"><i class="fas fa-list-ul text-text-secondary mr-2"></i> قائمة (أُضيف حديثاً) المجمعة</h2>
    <span class="text-xs text-gray-500 bg-gray-800 px-3 py-1 rounded-full border border-gray-700">إجمالي الأرشيف: <?php echo count($current_data); ?></span>
</div>

<?php if (empty($current_data)): ?>
    <div style="background-color: var(--bg-card); border: 1px solid var(--border-color);" class="p-8 rounded-xl text-center text-text-secondary">
        <i class="fas fa-tv text-4xl mb-3 opacity-50 block"></i>
        القائمة فارغة حالياً. اضغط على "تحديث الرادار".
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="content-table">
            <thead>
                <tr>
                    <th scope="col" style="width: 15%;">المصدر</th>
                    <th scope="col">الاسم (حلقة / فيلم)</th>
                    <th scope="col" style="width: 20%;">تاريخ الاكتشاف</th>
                    <th scope="col" style="width: 15%; text-align: center;">إجراء</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                uasort($current_data, function($a, $b) { return $b['time'] - $a['time']; });
                $count = 0;
                foreach ($current_data as $link => $item): 
                    if ($count++ > 50) break; 
                ?>
                    <tr>
                        <td>
                            <span class="px-2 py-1 rounded text-[11px] font-bold text-white <?php echo $item['site'] == 'عرب سيد' ? 'bg-red-600/80' : 'bg-pink-600/80'; ?>">
                                <?php echo htmlspecialchars($item['site']); ?>
                            </span>
                        </td>
                        <td class="font-bold text-sm text-gray-200">
                            <?php echo htmlspecialchars($item['title']); ?>
                        </td>
                        <td dir="ltr" class="text-text-secondary text-xs">
                            <i class="far fa-clock mr-1"></i> <?php echo date('m-d h:i A', $item['time']); ?>
                        </td>
                        <td style="text-align: center;">
                            <a href="<?php echo htmlspecialchars($item['link']); ?>" target="_blank" class="btn btn-sm btn-secondary hover:text-brand-gold text-xs">
                                فتح الرابط <i class="fas fa-external-link-alt ml-1"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<style>
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;  
        overflow: hidden;
    }
</style>