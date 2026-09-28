<?php
/**
 * سكريبت جلب بيانات السينما.كوم (Web Scraping) لموقع عرب فليكس
 * يقوم بجلب بيانات الأفلام/المسلسلات والتعليقات الخاصة بها
 */

class ArabFlex_ElCinema_Scraper {
    
    // دالة لجلب محتوى الصفحة باستخدام cURL
    public function fetch_html($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        // إضافة User-Agent حقيقي لتجنب الحظر
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: ar,en-US;q=0.7,en;q=0.3',
        ]);
        
        $html = curl_exec($ch);
        curl_close($ch);
        return $html;
    }

    // دالة لاستخراج البيانات من كود الـ HTML
    public function parse_work_data($html) {
        // تجاهل أخطاء الـ HTML غير الصالحة (بسبب HTML5)
        libxml_use_internal_errors(true);
        
        $dom = new DOMDocument();
        // إضافة دعم ترميز UTF-8 للنصوص العربية
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        
        $xpath = new DOMXPath($dom);
        $data = [];

        // 1. استخراج الاسم العربي
        $title_ar_nodes = $xpath->query("//div[contains(@class, 'panel jumbo')]/h1/span[@dir='rtl']");
        $data['title_ar'] = $title_ar_nodes->length > 0 ? trim($title_ar_nodes->item(0)->nodeValue) : '';

        // 2. استخراج الاسم الإنجليزي
        $title_en_nodes = $xpath->query("//div[contains(@class, 'panel jumbo')]/h1/span[@dir='ltr']");
        $data['title_en'] = $title_en_nodes->length > 0 ? trim($title_en_nodes->item(0)->nodeValue) : '';

        // 3. استخراج سنة الإصدار
        $year_nodes = $xpath->query("//div[contains(@class, 'panel jumbo')]/h1/span[@class='left']");
        if ($year_nodes->length > 0) {
            preg_match('/\d{4}/', $year_nodes->item(0)->nodeValue, $matches);
            $data['year'] = $matches[0] ?? '';
        }

        // 4. استخراج البوستر (صورة الغلاف)
        $poster_nodes = $xpath->query("//div[contains(@class, 'intro-box')]//ul[contains(@class, 'button-group-vertical')]/li/a/img/@src");
        $data['poster'] = $poster_nodes->length > 0 ? $poster_nodes->item(0)->nodeValue : '';

        // 5. استخراج القصة (الملخص)
        $synopsis_nodes = $xpath->query("//p[preceding-sibling::strong[contains(text(), 'ملخص القصة:')]]");
        if ($synopsis_nodes->length > 0) {
            // تنظيف النص من كلمة "...اقرأ المزيد"
            $synopsis = $synopsis_nodes->item(0)->nodeValue;
            $synopsis = str_replace('...اقرأ المزيد', ' ', $synopsis);
            $data['synopsis'] = trim(preg_replace('/\s+/', ' ', $synopsis));
        }

        // 6. استخراج التصنيف (رومانسي، أكشن، إلخ)
        $genres = [];
        $genre_nodes = $xpath->query("//ul[@id='jump-here-genre']/li/a");
        foreach ($genre_nodes as $node) {
            $genres[] = trim($node->nodeValue);
        }
        $data['genres'] = $genres;

        // 7. استخراج طاقم العمل (الممثلين)
        $cast = [];
        $cast_nodes = $xpath->query("//div[h3/a[contains(@href, '/cast')]]/following-sibling::div//ul[contains(@class, 'description')]/li/a");
        foreach ($cast_nodes as $node) {
            $actor_name = trim($node->nodeValue);
            if (!empty($actor_name) && !in_array($actor_name, ['المزيد'])) {
                $cast[] = $actor_name;
            }
        }
        $data['cast'] = $cast;

        return $data;
    }

    // دالة لجلب التعليقات باستخدام AJAX بناءً على طلب الـ cURL الذي أرسلته
    public function fetch_comments($work_id) {
        $url = "https://elcinema.com/ajaxable/more_comments?type=work&id={$work_id}&is_new=0";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        // استخدام نفس الـ Headers الموجودة في الـ cURL لتجنب الحظر
        $headers = [
            'Accept: text/html, */*; q=0.01',
            'Accept-Language: ar-EG',
            'Cache-Control: no-cache',
            'Connection: keep-alive',
            'Pragma: no-cache',
            'Referer: https://elcinema.com/work/' . $work_id . '/',
            'Sec-Fetch-Dest: empty',
            'Sec-Fetch-Mode: cors',
            'Sec-Fetch-Site: same-origin',
            'User-Agent: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Mobile Safari/537.36',
            'X-Requested-With: XMLHttpRequest'
        ];
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        
        if(curl_errno($ch)){
            return ['error' => curl_error($ch)];
        }
        
        curl_close($ch);
        
        // إرجاع كود الـ HTML الخاص بالتعليقات لتقوم بعرضه في موقعك
        return $response;
    }
}

// ==========================================
// تجربة السكريبت (Test the script)
// ==========================================

$scraper = new ArabFlex_ElCinema_Scraper();

// 1. إذا أردت جلب البيانات من رابط مباشر (قم بإزالة التعليق لتفعيلها):
// $url = "https://elcinema.com/work/2096254/";
// $html = $scraper->fetch_html($url);

// 2. استخدام كود الـ HTML الذي قمت أنت بنسخه (للتجربة حالياً):
$html = file_get_contents('php://input'); // سيقرأ الـ HTML إذا تم إرساله كـ POST، أو يمكنك وضع الـ HTML في ملف وقراءته

// استخراج البيانات
$movie_data = $scraper->parse_work_data($html);

echo "<h3>بيانات العمل:</h3>";
echo "<pre>";
print_r($movie_data);
echo "</pre>";

// 3. جلب التعليقات للمسلسل رقم 2096254 (أو 2098504 كما في طلب الـ Network)
$work_id = "2096254"; // معرف المسلسل "ورد على فل وياسمين"
$comments_html = $scraper->fetch_comments($work_id);

echo "<h3>التعليقات المجلوبة عبر AJAX:</h3>";
echo "<div style='border: 1px solid #ccc; padding: 10px; max-height: 400px; overflow-y: scroll;'>";
echo $comments_html; // سيتم طباعة الـ HTML الخاص بالتعليقات مباشرة
echo "</div>";

?>