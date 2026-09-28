<?php
// ─────────────────────────────────────────────────────────
// ElCinema API Proxy — v5.0 (تخطي حماية Cloudflare للاستضافات الجديدة)
// ?action=search&q=اسم    |    ?action=details&id=2098504
// ─────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// ══════════════════════════════════════════════════════════
// cURL helper — دالة جلب متقدمة جداً لتخطي حظر الاستضافات
// ══════════════════════════════════════════════════════════
function ec_fetch(string $url, string $referer = 'https://elcinema.com/'): ?string {
    if (!function_exists('curl_init')) return null;
    
    // استخدام User-Agent لمتصفح كروم حديث جداً
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36';

    // الهيدرز المشتركة التي تجعل الطلب يبدو بشرياً 100%
    $common_headers = [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
        'Accept-Language: ar,en-US;q=0.9,en;q=0.8',
        'Cache-Control: max-age=0',
        'Sec-Ch-Ua: "Chromium";v="128", "Not;A=Brand";v="24", "Google Chrome";v="128"',
        'Sec-Ch-Ua-Mobile: ?0',
        'Sec-Ch-Ua-Platform: "Windows"',
        'Sec-Fetch-Dest: document',
        'Sec-Fetch-Mode: navigate',
        'Sec-Fetch-User: ?1',
        'Upgrade-Insecure-Requests: 1'
    ];

    // --- الخطوة 1: الدخول للصفحة الرئيسية لأخذ الجلسة (Session Cookie) ---
    $ch = curl_init();
    $h1 = $common_headers;
    $h1[] = 'Sec-Fetch-Site: none'; // لأننا قادمون من الخارج
    
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://elcinema.com/',
        CURLOPT_RETURNTRANSFER => true, 
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true, 
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, // إجبار استخدام IPv4 لتخطي حظر Cloudflare
        CURLOPT_ENCODING => '', 
        CURLOPT_HEADER => true,
        CURLOPT_USERAGENT => $ua,
        CURLOPT_HTTPHEADER => $h1,
    ]);
    $resp = curl_exec($ch);
    $hs   = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $cookies = [];
    if (preg_match_all('/^Set-Cookie:\s*([^;]+)/mi', substr($resp, 0, $hs), $ck)) {
        foreach ($ck[1] as $c) {
            [$k, $v] = array_pad(explode('=', trim($c), 2), 2, '');
            if ($k) $cookies[trim($k)] = trim($v);
        }
    }

    // --- الخطوة 2: طلب صفحة البحث أو التفاصيل الفعلية باستخدام الكوكيز ---
    $ch2 = curl_init();
    $h2 = $common_headers;
    $h2[] = 'Sec-Fetch-Site: same-origin'; // لأننا الآن نتصفح داخل الموقع
    
    curl_setopt_array($ch2, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true, 
        CURLOPT_TIMEOUT => 25,
        CURLOPT_FOLLOWLOCATION => true, 
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, // إجبار استخدام IPv4
        CURLOPT_ENCODING => '', 
        CURLOPT_HEADER => false,
        CURLOPT_USERAGENT => $ua, 
        CURLOPT_REFERER => $referer,
        CURLOPT_COOKIE => implode('; ', array_map(fn($k,$v)=>"$k=$v", array_keys($cookies), $cookies)),
        CURLOPT_HTTPHEADER => $h2,
    ]);
    
    $html = curl_exec($ch2);
    $code = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);
    
    return ($code === 200 && !empty($html)) ? $html : null;
}

// ══════════════════════════════════════════════════════════
// مساعدات
// ══════════════════════════════════════════════════════════
function xt(string $s): string {
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/', ' ', strip_tags($s)));
}

// ══════════════════════════════════════════════════════════
// SEARCH (جلب نتائج البحث)
// ══════════════════════════════════════════════════════════
if (($_GET['action'] ?? '') === 'search') {
    $q = trim($_GET['q'] ?? '');
    if (!$q) { echo json_encode(['results' => []]); exit; }

    $html = ec_fetch('https://elcinema.com/search/?q=' . urlencode($q));
    if (!$html) { echo json_encode(['results' => [], 'error' => 'connection failed or blocked by Cloudflare']); exit; }

    $results = [];
    
    // استخدام DOMDocument لضمان دقة الاستخراج 100%
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    $xpath = new DOMXPath($dom);

    // استخراج أرقام الأعمال
    preg_match_all('#href="(?:https?://(?:www\.)?elcinema\.com)?/work/(\d+)/#i', $html, $matches);
    $unique_ids = array_unique($matches[1]);

    foreach ($unique_ids as $wid) {
        $title = ''; $poster = ''; $year = ''; $rating = ''; $type = 'movie';

        // 1. جلب العنوان
        $titleNodes = $xpath->query("//a[contains(@href, '/work/$wid/') and not(img)]");
        foreach ($titleNodes as $tn) {
            $t = xt($tn->textContent);
            if (mb_strlen($t, 'UTF-8') >= 2) {
                $title = $t;
                break;
            }
        }
        if (!$title) continue; 

        // 2. جلب البوستر
        $imgNodes = $xpath->query("//a[contains(@href, '/work/$wid/')]/img");
        if ($imgNodes->length > 0) {
            $img = $imgNodes->item(0);
            $poster = $img->getAttribute('data-src');
            if (!$poster) $poster = $img->getAttribute('src');
        }

        // 3. جلب السنة والتقييم
        $container = $titleNodes->item(0);
        $levels = 0;
        while ($container && $levels < 4) {
            $container = $container->parentNode;
            $levels++;
        }

        if ($container) {
            $containerHtml = $dom->saveHTML($container);
            
            // استخراج السنة
            if (preg_match('#/release_year/(\d{4})/#', $containerHtml, $ym)) $year = $ym[1];
            elseif (preg_match('/\b(19[5-9]\d|20[0-3]\d)\b/', $containerHtml, $ym))  $year = $ym[1];

            // استخراج التقييم
            if (preg_match('/<div class="stars-orange[^"]*">[^<]*<i[^>]*><\/i><div>([\d.]+)<\/div>/si', $containerHtml, $rm))
                $rating = $rm[1];

            // تحديد النوع
            if (preg_match('/مسلسل|حلقات/u', $containerHtml)) $type = 'tv';
        }

        $results[] = [
            'id' => $wid, 
            'title' => $title, 
            'poster' => $poster, 
            'year' => $year, 
            'rating' => $rating, 
            'type' => $type
        ];
        
        if (count($results) >= 15) break;
    }

    echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ══════════════════════════════════════════════════════════
// DETAILS (جلب تفاصيل العمل)
// ══════════════════════════════════════════════════════════
if (($_GET['action'] ?? '') === 'details') {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) { echo json_encode(['error' => 'no id']); exit; }

    $html = ec_fetch("https://elcinema.com/work/$id/", 'https://elcinema.com/search/');
    if (!$html) { echo json_encode(['error' => 'failed to fetch']); exit; }

    $d = [];

    // ══ 1. العنوان العربي ════════════════════════════════
    $title = '';
    if (preg_match('/<input[^>]+id=["\']title-ar["\'][^>]+value=\s*([^\/>"]+)/i', $html, $m))
        $title = trim($m[1], " \t\n\r\0\x0B\"'");

    if (empty($title)) {
        if (preg_match('/<span[^>]+dir=["\']rtl["\'][^>]*>\s*([^<]{2,})\s*<\/span>/ui', $html, $m))
            $title = xt($m[1]);
    }
    $d['title'] = $title;

    // ══ 2. البوستر ════════════════════════════════════════
    $poster = '';
    if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m))
        $poster = $m[1];
    if (!empty($poster)) $poster = preg_replace('/_\d+x\d*_/', '_315x420_', $poster);
    $d['poster'] = $poster;

    // ══ 3. الوصف / القصة ════════════════════════════════
    $desc = '';
    if (preg_match("/<p[^>]*>((?:(?!<\/p>).)+?id=['\"]read-more['\"](?:(?!<\/p>).)+)<\/p>/si", $html, $m)) {
        $raw_p = $m[1];
        $raw_p = preg_replace("/<a[^>]+id=['\"]read-more['\"][^>]*>.*?<\/a>/si", '', $raw_p);
        $raw_p = preg_replace("/<span[^>]+class=['\"]hide['\"][^>]*>(.*?)<\/span>/si", '$1', $raw_p);
        $desc  = xt($raw_p);
    }
    if (mb_strlen($desc, 'UTF-8') < 20) {
        preg_match_all('/<p[^>]*>([\p{Arabic}][^<]{40,})<\/p>/u', $html, $pm);
        if (!empty($pm[1])) $desc = xt($pm[1][0]);
    }
    $d['description'] = $desc;

    // ══ 4. السنة ══════════════════════════════════════════
    $year = '';
    if (preg_match('#/index/work/release_year/(\d{4})/#', $html, $m)) $year = $m[1];
    if (empty($year) && preg_match('/\((\d{4})\)/', $html, $m)) $year = $m[1];
    $d['year'] = $year;

    // ══ 5. التصنيف / النوع ════════════════════════════════
    $genres = [];
    preg_match_all('#<a[^>]+href=["\'][^"\']*?/index/work/genre/[^"\']*["\'][^>]*>([^<]{2,30})</a>#iu', $html, $gm);
    foreach ($gm[1] as $g) { $g = xt($g); if ($g) $genres[] = $g; }
    $d['genre'] = implode('، ', array_slice(array_unique($genres), 0, 5));

    // ══ 6. التقييم ════════════════════════════════════════
    $rating = 0;
    if (preg_match('/<div class="stars-orange[^"]*">[^<]*<i[^>]*><\/i><div>([\d.]+)<\/div>/si', $html, $m))
        $rating = round(floatval($m[1]), 1);
    $d['rating'] = $rating;

    // ══ 7. التريلر ════════════════════════════════════════
    $trailer = '';
    if (preg_match('#href="(/work/\d+/video/(\d+))"#i', $html, $vm)) {
        $video_page = ec_fetch("https://elcinema.com{$vm[1]}", "https://elcinema.com/work/$id/");
        if ($video_page) {
            if (preg_match('#(?:youtube\.com/embed/|youtu\.be/)([\w\-]{11})#i', $video_page, $yt))
                $trailer = "https://www.youtube.com/watch?v={$yt[1]}";
        }
    }
    if (empty($trailer)) {
        if (preg_match('#(?:youtube\.com/embed/|youtu\.be/)([\w\-]{11})#i', $html, $yt))
            $trailer = "https://www.youtube.com/watch?v={$yt[1]}";
    }
    $d['trailer'] = $trailer;

    // ══ 8. طاقم العمل مع صور ══════════════════════════════
    $cast = []; $seen_cast = [];

    preg_match_all(
        '/<div class="thumbnail-wrapper">\s*'
        . '<a[^>]+href="\/person\/(\d+)\/[^"]*"[^>]*>\s*'
        . '<img[^>]+data-src="([^"]+)"[^>]*>\s*<\/a>\s*'
        . '<ul class="description">\s*'
        . '<li>\s*<a[^>]*>([^<]+)<\/a>\s*<\/li>\s*'
        . '(?:<li[^>]*>\s*([^<]*)\s*<\/li>)?/si',
        $html, $cm, PREG_SET_ORDER
    );

    foreach ($cm as $c) {
        $name      = xt($c[3]);
        $img       = trim($c[2]);
        $character = xt(trim($c[4] ?? '', " \t\n&nbsp;()"));
        if (!$name || isset($seen_cast[$name])) continue;
        $seen_cast[$name] = true;
        if (empty($img))
            $img = 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=1e293b&color=DAA520&size=200';
        $cast[] = ['name' => $name, 'character' => $character, 'image' => $img];
        if (count($cast) >= 10) break;
    }

    if (empty($cast)) {
        preg_match_all('/<div class="thumbnail-wrapper">(.*?)<\/div>/si', $html, $blocks);
        foreach ($blocks[1] as $blk) {
            $img = '';
            if (preg_match('/data-src="([^"]+)"/i', $blk, $im)) $img = $im[1];
            $name = '';
            if (preg_match('/<a[^>]+href="\/person\/[^"]+"[^>]*>([^<]+)<\/a>/iu', $blk, $nm)) $name = xt($nm[1]);
            $char = '';
            if (preg_match('/<li[^>]+class="subheader"[^>]*>([^<]*)<\/li>/si', $blk, $ch)) $char = xt(trim($ch[1], " ()&nbsp;"));
            if (!$name || isset($seen_cast[$name])) continue;
            $seen_cast[$name] = true;
            if (empty($img)) $img = 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=1e293b&color=DAA520&size=200';
            $cast[] = ['name' => $name, 'character' => $char, 'image' => $img];
            if (count($cast) >= 10) break;
        }
    }

    $d['cast'] = $cast;
    $d['type']  = preg_match('/مسلسل|حلقات/u', $html) ? 'tv' : 'movie';

    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode(['error' => 'unknown action']);