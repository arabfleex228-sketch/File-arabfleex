<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// =====================================================
// مساعد جلب البيانات من السينما كوم
// يتوافق مع قاعدة بيانات arabfleex
// =====================================================

@include_once 'db_config.php';

$debug_mode = isset($_GET['debug']) && $_GET['debug'] == '1';

// =====================================================
// دالة cURL مع دعم الكوكيز الكامل
// =====================================================
function fetch_with_cookies($url, &$cookie_jar = [], $referer = 'https://elcinema.com/', $is_ajax = false) {
    if (!function_exists('curl_init')) return [null, 'curl غير متاح'];

    // بناء نص الكوكيز
    $cookie_str = '';
    if (!empty($cookie_jar)) {
        $parts = [];
        foreach ($cookie_jar as $k => $v) $parts[] = "$k=$v";
        $cookie_str = implode('; ', $parts);
    }

    $ch = curl_init();
    $headers = [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language: ar-EG,ar;q=0.9,en-US;q=0.8,en;q=0.7',
        'Accept-Encoding: gzip, deflate, br',
        'Cache-Control: no-cache',
        'Pragma: no-cache',
        'Connection: keep-alive',
        'Upgrade-Insecure-Requests: 1',
        'Sec-Ch-Ua: "Chromium";v="127", "Not)A;Brand";v="99"',
        'Sec-Ch-Ua-Mobile: ?1',
        'Sec-Ch-Ua-Platform: "Android"',
        'Sec-Fetch-Site: same-origin',
        'Sec-Fetch-Mode: navigate',
        'Sec-Fetch-User: ?1',
        'Sec-Fetch-Dest: document',
    ];
    if ($is_ajax) {
        $headers[] = 'X-Requested-With: XMLHttpRequest';
        $headers[0] = 'Accept: text/html, */*; q=0.01';
        $headers[12] = 'Sec-Fetch-Mode: cors';
        $headers[14] = 'Sec-Fetch-Dest: empty';
    }

    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_ENCODING       => '',
        CURLOPT_HEADER         => true,   // نريد الهيدرز لاستخراج الكوكيز
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Mobile Safari/537.36',
        CURLOPT_REFERER        => $referer,
    ]);
    if (!empty($cookie_str)) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookie_str);
    }

    $response = curl_exec($ch);
    $err      = curl_error($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hdr_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    if ($err)             return [null, "cURL: $err"];
    if ($httpcode == 403) return [null, "محظور (403) - حماية Cloudflare"];
    if ($httpcode == 503) return [null, "Cloudflare يمنع (503)"];
    if ($httpcode != 200) return [null, "HTTP $httpcode"];

    // استخراج الهيدرز والـ body
    $raw_headers = substr($response, 0, $hdr_size);
    $body        = substr($response, $hdr_size);

    // استخراج Set-Cookie وتحديث جرة الكوكيز
    preg_match_all('/^Set-Cookie:\s*([^;]+)/mi', $raw_headers, $ck_matches);
    foreach ($ck_matches[1] as $ck) {
        $ck = trim($ck);
        if (strpos($ck, '=') !== false) {
            [$k, $v] = explode('=', $ck, 2);
            $cookie_jar[trim($k)] = trim($v);
        }
    }

    if (empty($body)) return [null, "الاستجابة فارغة"];
    return [$body, null];
}

// =====================================================
// الخطوة الأولى: جلب session cookie من الهوم بيدج
// =====================================================
function get_session($debug = false) {
    $cookies = [];
    // زيارة الصفحة الرئيسية لأخذ الكوكي
    [$html, $err] = fetch_with_cookies('https://elcinema.com/', $cookies, 'https://elcinema.com/');
    if ($debug) {
        echo "<div style='background:#111;color:#0f0;padding:1rem;margin:1rem 0;border-radius:8px;font-family:monospace;font-size:12px'>";
        echo "<b>Homepage fetch:</b> " . ($err ? "❌ $err" : "✅ ".strlen($html)." bytes") . "<br>";
        echo "<b>Cookies obtained:</b> " . implode(', ', array_keys($cookies)) . "</div>";
    }
    return $cookies;
}

// =====================================================
// جلب صفحة بعد الحصول على السيشن
// =====================================================
function smart_fetch($url, $debug = false, $referer = 'https://elcinema.com/', $is_ajax = false) {
    $cookies = [];

    // الخطوة 1: جلب الهوم بيدج للسيشن
    [$home_html, $home_err] = fetch_with_cookies('https://elcinema.com/', $cookies, 'https://elcinema.com/');

    if ($debug) {
        echo "<div style='background:#111;color:#0f0;padding:1rem;margin:.5rem 0;border-radius:8px;font-family:monospace;font-size:12px;direction:ltr'>";
        echo "[Step 1] Homepage: " . ($home_err ? "❌ $home_err" : "✅ ".strlen($home_html ?? '')." bytes") . "<br>";
        echo "[Cookies] " . json_encode(array_keys($cookies)) . "</div>";
    }

    if (empty($cookies) && !$home_html) {
        return [null, $home_err ?? 'فشل جلب الكوكيز'];
    }

    // الخطوة 2: جلب الـ URL المطلوب بالكوكيز
    [$html, $err] = fetch_with_cookies($url, $cookies, $referer, $is_ajax);

    if ($debug) {
        echo "<div style='background:#111;color:#4af;padding:1rem;margin:.5rem 0;border-radius:8px;font-family:monospace;font-size:12px;direction:ltr'>";
        echo "[Step 2] $url<br>";
        echo ($err ? "❌ $err" : "✅ ".strlen($html ?? '')." bytes") . "</div>";
        if ($html) {
            echo "<div style='background:#000;color:#888;padding:1rem;margin:.5rem 0;border-radius:8px;font-family:monospace;font-size:11px;direction:ltr'>";
            echo "<b>HTML Preview (500 chars):</b><br>" . htmlspecialchars(substr($html, 0, 500)) . "</div>";
        }
    }

    return [$html, $err];
}

// =====================================================
// تحليل نتائج البحث
// =====================================================
function parse_search($html, $type_filter = 'all') {
    $results = [];
    $seen    = [];

    // استخراج روابط /work/ID
    preg_match_all(
        '#<a\s[^>]*href="(?:https?://(?:www\.)?elcinema\.com)?(/work/(\d+)/[^"]*)"[^>]*>(.*?)</a>#si',
        $html, $m, PREG_SET_ORDER
    );

    foreach ($m as $match) {
        $work_id = $match[2];
        $title   = trim(preg_replace('/\s+/', ' ', strip_tags($match[3])));
        if (isset($seen[$work_id]) || mb_strlen($title, 'UTF-8') < 2) continue;
        $seen[$work_id] = true;

        $pos   = strpos($html, '/work/' . $work_id . '/');
        $chunk = $pos !== false ? substr($html, max(0, $pos - 800), 1800) : '';

        $poster = '';
        if (preg_match('/(?:data-src|src)="(https:\/\/[^"]+\.(?:jpg|jpeg|png|webp)[^"]*)"/i', $chunk, $pm))
            $poster = $pm[1];

        $year = '';
        if (preg_match('/\b(19[5-9]\d|20[0-3]\d)\b/', $chunk, $ym)) $year = $ym[0];

        $rating = '0';
        if (preg_match('/(?:rate|rating|score)[^>]{0,50}>([\d.]+)/is', $chunk, $rm)) $rating = $rm[1];

        $kind = 'movie';
        if (preg_match('/مسلسل|series|حلقات|موسم/i', $chunk)) $kind = 'tv';

        if ($type_filter === 'movie' && $kind !== 'movie') continue;
        if ($type_filter === 'tv'    && $kind !== 'tv')    continue;

        $results[] = ['id'=>$work_id,'title'=>$title,'poster'=>$poster,'year'=>$year,'rating'=>$rating,'type'=>$kind];
        if (count($results) >= 20) break;
    }

    // Fallback
    if (empty($results)) {
        preg_match_all('#/work/(\d+)/#', $html, $fm);
        foreach (array_unique($fm[1] ?? []) as $wid) {
            if (!isset($seen[$wid])) {
                $results[] = ['id'=>$wid,'title'=>'عمل رقم '.$wid,'poster'=>'','year'=>'','rating'=>'0','type'=>'movie'];
                $seen[$wid] = true;
            }
            if (count($results) >= 10) break;
        }
    }
    return $results;
}

// =====================================================
// تحليل صفحة تفاصيل عمل
// =====================================================
function parse_details($html) {
    if (empty($html)) return null;
    $d = [];

    // العنوان
    preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $tm);
    $d['title'] = trim($tm[1] ?? '');
    if (empty($d['title'])) { preg_match('/<title>([^<|]+)/i', $html, $t2); $d['title'] = trim($t2[1] ?? ''); }
    if (empty($d['title'])) { preg_match('/<h1[^>]*>([^<]+)/i', $html, $t3); $d['title'] = trim(strip_tags($t3[1] ?? '')); }

    // البوستر
    preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $pm);
    $d['poster'] = $pm[1] ?? '';
    if (empty($d['poster'])) {
        preg_match('/(?:data-src|src)="(https?:\/\/[^"]+\.(?:jpg|jpeg|png|webp)[^"]*)"/i', $html, $pm2);
        $d['poster'] = $pm2[1] ?? '';
    }

    // الوصف
    preg_match('/<meta[^>]+(?:name=["\']description["\']|property=["\']og:description["\'])[^>]+content=["\']([^"\']+)["\']/i', $html, $dm);
    $d['description'] = trim($dm[1] ?? '');
    if (empty($d['description'])) {
        preg_match('/"description"\s*:\s*"([^"]{20,})"/i', $html, $dm2);
        $d['description'] = trim($dm2[1] ?? '');
    }

    // السنة
    preg_match_all('/\b(19[5-9]\d|20[0-3]\d)\b/', $html, $ym);
    $years = array_unique($ym[1] ?? []); sort($years);
    $d['year'] = $years[0] ?? '';

    // التصنيف
    $genres = [];
    preg_match_all('/"genre"\s*:\s*"([^"]{2,20})"/i', $html, $gm);
    $genres = $gm[1] ?? [];
    if (empty($genres)) {
        preg_match_all('/<a[^>]+href="[^"]*(?:genre|tag|category)[^"]*"[^>]*>([^<]{2,20})<\/a>/i', $html, $gm2);
        $genres = $gm2[1] ?? [];
    }
    $d['genre'] = implode('، ', array_slice(array_unique(array_filter($genres)), 0, 5));

    // التقييم
    $d['rating'] = 0;
    foreach (['/"ratingValue"\s*:\s*"?([\d.]+)"?/i', '/itemprop="ratingValue"[^>]*>\s*([\d.]+)/i', '/(?:rate|score)[^>]{0,50}>([\d.]+)/is'] as $rp) {
        if (preg_match($rp, $html, $rm) && ($rv = floatval($rm[1])) > 0 && $rv <= 10) { $d['rating'] = round($rv, 1); break; }
    }

    // التريلر
    $d['trailer'] = '';
    preg_match_all('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([\w\-]{11})/', $html, $yt);
    if (!empty($yt[1][0])) $d['trailer'] = 'https://www.youtube.com/watch?v=' . $yt[1][0];

    // الممثلون
    $cast = []; $seen_a = [];
    preg_match_all('/"actor"\s*:\s*\{"@type"\s*:\s*"Person","name"\s*:\s*"([^"]+)"(?:,"url":"[^"]*")?(?:,"image":"([^"]*)")?/i', $html, $am, PREG_SET_ORDER);
    foreach ($am as $a) {
        $n = trim($a[1]);
        if (!isset($seen_a[$n])) { $seen_a[$n]=true; $cast[] = ['name'=>$n,'character'=>'','image'=>$a[2]??'']; }
    }
    if (empty($cast)) {
        preg_match_all('/<a[^>]+href="[^"]*\/person\/\d+[^"]*"[^>]*>\s*([^<]{2,40})\s*<\/a>/i', $html, $am2);
        foreach (($am2[1]??[]) as $n) {
            $n = trim($n);
            if (!isset($seen_a[$n]) && !empty($n) && !preg_match('/\d/', $n)) { $seen_a[$n]=true; $cast[] = ['name'=>$n,'character'=>'','image'=>'']; }
        }
    }
    $d['cast'] = array_slice($cast, 0, 8);
    $d['type'] = preg_match('/مسلسل|series|حلقات|موسم/i', $html) ? 'tv' : 'movie';

    return $d;
}

// =====================================================
// بناء SQL
// =====================================================
function build_sql($conn, $d) {
    $e = fn($s) => (!empty($conn) && !($conn instanceof mysqli && $conn->connect_errno)) ? $conn->real_escape_string($s) : addslashes($s);

    $t  = $e($d['title']);         $de = $e($d['description']);
    $p  = $e($d['poster']);        $y  = $e($d['year']);
    $g  = $e($d['genre']);         $tr = $e($d['trailer']);
    $ra = floatval($d['rating']);
    $cj = !empty($d['cast']) ? $e(json_encode($d['cast'], JSON_UNESCAPED_UNICODE)) : '';

    return [
        "INSERT INTO `movies` (title, description, cast_data, poster, year, genre, rating, quality, is_recent, is_published, watch_link, watch_link_2, watch_link_3, watch_link_4, download_link, download_link_2, trailer_link, category) VALUES ('{$t}', '{$de}', '{$cj}', '{$p}', '{$y}', '{$g}', {$ra}, '1080p', 1, 1, '#', '', '', '', '', '', '{$tr}', 'movie');",
        "INSERT INTO `series` (title, description, cast_data, poster, year, genre, rating, is_recent, is_published, ramadan_year, continue_after_ramadan, trailer_link, category) VALUES ('{$t}', '{$de}', '{$cj}', '{$p}', '{$y}', '{$g}', {$ra}, 1, 1, 0, 0, '{$tr}', 'series');"
    ];
}

// =====================================================
// معالجة الطلبات
// =====================================================
$search_results = null;
$item_details   = null;
$sql_movie = $sql_series = '';
$error_msg  = '';

// بحث
if (!empty($_GET['query'])) {
    $q    = trim($_GET['query']);
    $type = $_GET['type'] ?? 'all';
    $surl = 'https://elcinema.com/search/?q=' . urlencode($q);
    [$html, $err] = smart_fetch($surl, $debug_mode, 'https://elcinema.com/', false);
    if ($html) {
        $search_results = parse_search($html, $type);
    } else {
        $error_msg = $err ?? 'فشل الاتصال';
    }
}

// ID مباشر
if (!empty($_GET['direct_id'])) {
    $_GET['fetch_id'] = intval($_GET['direct_id']);
}

// جلب تفاصيل
if (!empty($_GET['fetch_id'])) {
    $wid  = intval($_GET['fetch_id']);
    $wurl = "https://elcinema.com/work/{$wid}/";
    [$html, $err] = smart_fetch($wurl, $debug_mode, 'https://elcinema.com/search/', false);
    if ($html) {
        $item_details = parse_details($html);
        if ($item_details) [$sql_movie, $sql_series] = build_sql($conn ?? null, $item_details);
    } else {
        $error_msg = $err ?? 'فشل جلب الصفحة';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>مساعد السينما كوم - ArabFleex</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
<style>
body{font-family:'Cairo',sans-serif;background:linear-gradient(135deg,#0f172a 0%,#1e1b4b 100%);min-height:100vh;}
.card{background:#1e293b;border:1px solid #334155;border-radius:12px;}
.ri{display:block;padding:1rem;border:1px solid #334155;border-radius:10px;margin-top:.75rem;text-decoration:none;color:#e2e8f0;transition:all .2s;background:#0f172a;}
.ri:hover{background:#1e293b;border-color:#6366f1;box-shadow:0 0 0 2px rgba(99,102,241,.2);}
.sql-box{background:#0a0a1a;border:1px solid #334155;border-radius:8px;font-family:'Courier New',monospace;color:#4ade80;direction:ltr;text-align:left;width:100%;padding:1rem;min-height:80px;resize:vertical;outline:none;font-size:.78rem;}
.sql-box:focus{border-color:#fbbf24;}
.btn{padding:.65rem 1.5rem;border-radius:8px;font-weight:700;cursor:pointer;transition:all .2s;border:none;font-family:'Cairo',sans-serif;}
.btn-p{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;}
.btn-p:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(99,102,241,.4);}
.btn-g{background:linear-gradient(135deg,#059669,#10b981);color:white;}
.btn-g:hover{transform:translateY(-1px);}
.cp{background:#059669;color:white;padding:.4rem 1rem;border-radius:6px;cursor:pointer;font-size:.85rem;font-family:'Cairo',sans-serif;border:none;font-weight:700;transition:all .2s;}
.cp:hover{background:#047857;}
.badge{padding:.2rem .6rem;border-radius:20px;font-size:.75rem;font-weight:700;}
.b-m{background:rgba(99,102,241,.2);color:#818cf8;border:1px solid rgba(99,102,241,.3);}
.b-t{background:rgba(245,158,11,.2);color:#fbbf24;border:1px solid rgba(245,158,11,.3);}
input,select{background:#0f172a;border:1px solid #334155;border-radius:8px;color:#e2e8f0;padding:.75rem 1rem;width:100%;outline:none;font-family:'Cairo',sans-serif;}
input:focus,select:focus{border-color:#6366f1;}
label{color:#94a3b8;font-weight:600;font-size:.9rem;}
.tab{padding:.5rem 1.2rem;border-radius:8px;cursor:pointer;font-weight:700;font-size:.9rem;border:2px solid transparent;transition:all .2s;}
.tab.on{background:#6366f1;color:white;border-color:#6366f1;}
.tab:not(.on){background:#0f172a;color:#94a3b8;border-color:#334155;}
.tab:not(.on):hover{border-color:#6366f1;color:#a5b4fc;}
</style>
</head>
<body class="p-4 md:p-8">
<div class="max-w-4xl mx-auto">

<div class="text-center mb-8">
    <h1 class="text-3xl md:text-4xl font-black text-white mb-1">🎬 مساعد السينما كوم</h1>
    <p class="text-slate-400 text-sm">ابحث أو أدخل ID مباشرة — بيانات جاهزة لـ ArabFleex</p>
</div>

<!-- Tabs -->
<div class="flex gap-2 mb-6">
    <div class="tab <?php echo empty($_GET['direct_id'])?'on':''; ?>" onclick="showTab('s')">🔍 بحث بالاسم</div>
    <div class="tab <?php echo !empty($_GET['direct_id'])?'on':''; ?>" onclick="showTab('d')">🎯 ID مباشر</div>
    <?php if($debug_mode): ?><a href="?<?php echo http_build_query(array_diff_key($_GET,['debug'=>''])); ?>" class="tab" style="color:#f59e0b;border-color:#f59e0b">🔧 أغلق التشخيص</a><?php endif; ?>
</div>

<!-- تاب البحث -->
<div id="ts" class="<?php echo !empty($_GET['direct_id'])?'hidden':''; ?>">
<div class="card p-6 mb-6">
<form method="GET" class="flex flex-col md:flex-row gap-4">
    <?php if($debug_mode): ?><input type="hidden" name="debug" value="1"><?php endif; ?>
    <div class="flex-1"><label class="block mb-1">اسم الفيلم أو المسلسل</label>
        <input type="text" name="query" placeholder="مثال: ولاد رزق، بابا المجال..." value="<?php echo htmlspecialchars($_GET['query']??''); ?>" autocomplete="off"></div>
    <div class="md:w-44"><label class="block mb-1">النوع</label>
        <select name="type">
            <option value="all"   <?php echo(($_GET['type']??'')=='all'  )?'selected':'';?>>الكل</option>
            <option value="movie" <?php echo(($_GET['type']??'')=='movie')?'selected':'';?>>أفلام فقط</option>
            <option value="tv"    <?php echo(($_GET['type']??'')=='tv'   )?'selected':'';?>>مسلسلات فقط</option>
        </select></div>
    <div class="md:self-end"><button type="submit" class="btn btn-p w-full">🔍 ابحث</button></div>
</form>
</div></div>

<!-- تاب ID مباشر -->
<div id="td" class="<?php echo empty($_GET['direct_id'])?'hidden':''; ?>">
<div class="card p-6 mb-6">
    <p class="text-slate-300 text-sm mb-1">افتح elcinema.com، دخل على الفيلم أو المسلسل، وانسخ الرقم من الرابط:</p>
    <p class="text-yellow-400 font-mono text-sm mb-4" dir="ltr">elcinema.com/work/<span class="text-green-400 font-black">2098504</span>/</p>
    <form method="GET" class="flex flex-col md:flex-row gap-4">
        <?php if($debug_mode): ?><input type="hidden" name="debug" value="1"><?php endif; ?>
        <div class="flex-1"><label class="block mb-1">رقم العمل (ID)</label>
            <input type="number" name="direct_id" placeholder="مثال: 2098504" value="<?php echo htmlspecialchars($_GET['direct_id']??''); ?>"></div>
        <div class="md:self-end"><button type="submit" class="btn btn-g w-full">🎯 جلب البيانات</button></div>
    </form>
</div></div>

<!-- خطأ -->
<?php if ($error_msg): ?>
<div class="card p-5 mb-6" style="border-color:#7f1d1d;background:rgba(127,29,29,.15);">
    <p class="text-red-400 font-bold text-sm">⚠️ <?php echo htmlspecialchars($error_msg); ?></p>
    <div class="flex gap-3 mt-3 flex-wrap">
        <a href="?<?php echo http_build_query(array_merge($_GET,['debug'=>'1'])); ?>" class="text-yellow-400 underline text-sm">🔧 تشغيل التشخيص</a>
        <span class="text-slate-600 text-sm">|</span>
        <span class="text-slate-400 text-sm">أو جرب <strong>تاب ID مباشر</strong> بدل البحث</span>
    </div>
</div>
<?php endif; ?>

<!-- نتائج البحث -->
<?php if ($search_results !== null): ?>
<div class="card p-6 mb-6">
    <h2 class="text-xl font-bold text-white mb-4 border-b border-slate-700 pb-3">
        نتائج البحث <span class="text-slate-400 font-normal text-base">(<?php echo count($search_results); ?>)</span>
    </h2>
    <?php if (empty($search_results)): ?>
        <div class="text-center py-8">
            <p class="text-red-400 font-bold text-lg mb-2">⚠️ لم يتم العثور على نتائج</p>
            <p class="text-slate-400 text-sm">جرب تاب "ID مباشر" وأدخل رقم العمل من الموقع مباشرة</p>
        </div>
    <?php else: ?>
        <?php foreach($search_results as $item): ?>
        <a class="ri" href="?fetch_id=<?php echo $item['id'];?>&bq=<?php echo urlencode($_GET['query']??'');?>&bt=<?php echo urlencode($_GET['type']??'all');?><?php echo $debug_mode?'&debug=1':'';?>">
            <div class="flex items-start gap-4">
                <?php if(!empty($item['poster'])): ?>
                    <img src="<?php echo htmlspecialchars($item['poster']);?>" alt="" class="rounded-lg w-14 h-20 object-cover flex-shrink-0" onerror="this.style.display='none'">
                <?php else: ?>
                    <div class="w-14 h-20 bg-slate-700 rounded-lg flex items-center justify-center flex-shrink-0">🎬</div>
                <?php endif; ?>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap mb-1">
                        <h3 class="text-white font-bold"><?php echo htmlspecialchars($item['title']);?></h3>
                        <span class="badge <?php echo $item['type']==='tv'?'b-t':'b-m';?>"><?php echo $item['type']==='tv'?'📺 مسلسل':'🎬 فيلم';?></span>
                    </div>
                    <div class="flex gap-3 text-sm">
                        <?php if(!empty($item['year'])): ?><span class="text-slate-400">📅 <?php echo $item['year'];?></span><?php endif; ?>
                        <?php if(!empty($item['rating'])&&$item['rating']!='0'): ?><span class="text-yellow-400">⭐ <?php echo $item['rating'];?></span><?php endif; ?>
                        <span class="text-slate-600 text-xs">ID: <?php echo $item['id'];?></span>
                    </div>
                </div>
                <span class="text-indigo-400 text-sm font-bold self-center flex-shrink-0">جلب ←</span>
            </div>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- تفاصيل العمل -->
<?php if ($item_details): ?>
<div class="card p-6 mb-6">
    <div class="flex items-center gap-2 mb-5">
        <span class="text-green-400 text-2xl">✅</span>
        <h2 class="text-xl font-bold text-white">تم جلب البيانات بنجاح!</h2>
    </div>
    <div class="flex flex-col md:flex-row gap-6 mb-6">
        <div class="md:w-44 flex-shrink-0">
            <img src="<?php echo htmlspecialchars($item_details['poster']??'');?>" alt="poster"
                 class="rounded-xl shadow-xl w-full object-cover"
                 onerror="this.src='https://placehold.co/176x264?text=No+Image'">
        </div>
        <div class="flex-1">
            <h3 class="text-2xl font-black text-white mb-3"><?php echo htmlspecialchars($item_details['title']);?></h3>
            <div class="grid grid-cols-3 gap-2 mb-4">
                <div class="bg-slate-800 rounded-lg p-2 text-center">
                    <div class="text-slate-400 text-xs mb-1">السنة</div>
                    <div class="text-white font-bold text-sm"><?php echo htmlspecialchars($item_details['year']??'—');?></div>
                </div>
                <div class="bg-slate-800 rounded-lg p-2 text-center">
                    <div class="text-slate-400 text-xs mb-1">التقييم</div>
                    <div class="text-yellow-400 font-bold text-sm">⭐ <?php echo $item_details['rating'];?></div>
                </div>
                <div class="bg-slate-800 rounded-lg p-2 text-center">
                    <div class="text-slate-400 text-xs mb-1">تريلر</div>
                    <div class="font-bold text-sm <?php echo !empty($item_details['trailer'])?'text-green-400':'text-red-400';?>">
                        <?php echo !empty($item_details['trailer'])?'✅ موجود':'❌ لا';?>
                    </div>
                </div>
            </div>
            <?php if(!empty($item_details['genre'])): ?>
                <p class="text-sm mb-3"><span class="text-slate-400">التصنيف:</span> <span class="text-indigo-300 font-bold"><?php echo htmlspecialchars($item_details['genre']);?></span></p>
            <?php endif; ?>
            <?php if(!empty($item_details['description'])): ?>
                <div class="bg-slate-800 rounded-lg p-3">
                    <p class="text-slate-300 text-sm leading-relaxed"><?php echo htmlspecialchars(mb_substr($item_details['description'],0,350,'UTF-8'));?>...</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if(!empty($item_details['cast'])): ?>
    <div class="mb-5">
        <p class="text-white font-bold mb-2 text-sm border-b border-slate-700 pb-2">🎭 الممثلون</p>
        <div class="flex flex-wrap gap-2">
            <?php foreach($item_details['cast'] as $a): ?>
                <span class="bg-slate-800 px-3 py-1 rounded-full border border-slate-700 text-slate-300 text-sm"><?php echo htmlspecialchars($a['name']);?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="space-y-4">
        <p class="text-yellow-400 font-bold">💾 كود SQL — متوافق 100% مع جداول movies و series</p>
        <div>
            <div class="flex items-center justify-between mb-1">
                <label class="text-blue-400 font-bold text-sm">🎬 جدول الأفلام (movies)</label>
                <button class="cp" onclick="cp('sm',this)">نسخ</button>
            </div>
            <textarea id="sm" class="sql-box" readonly onclick="this.select()"><?php echo htmlspecialchars($sql_movie);?></textarea>
        </div>
        <div>
            <div class="flex items-center justify-between mb-1">
                <label class="text-amber-400 font-bold text-sm">📺 جدول المسلسلات (series)</label>
                <button class="cp" onclick="cp('ss',this)">نسخ</button>
            </div>
            <textarea id="ss" class="sql-box" readonly onclick="this.select()"><?php echo htmlspecialchars($sql_series);?></textarea>
        </div>
    </div>

    <div class="mt-5 flex gap-3 justify-center flex-wrap">
        <?php if(!empty($_GET['bq'])): ?>
            <a href="?query=<?php echo urlencode($_GET['bq']);?>&type=<?php echo urlencode($_GET['bt']??'all');?><?php echo $debug_mode?'&debug=1':'';?>" class="btn btn-p">← العودة للنتائج</a>
        <?php else: ?>
            <a href="elcinema_helper.php" class="btn btn-p">← بحث جديد</a>
        <?php endif; ?>
        <?php if(!$debug_mode): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET,['debug'=>'1']));?>" class="btn" style="background:#374151;color:#9ca3af;">🔧 تشخيص</a>
        <?php endif; ?>
    </div>
</div>
<?php elseif(!empty($_GET['fetch_id'])&&!$error_msg): ?>
<div class="card p-6 mb-6 text-center">
    <p class="text-red-400 font-bold text-lg">⚠️ فشل جلب البيانات من صفحة العمل</p>
    <p class="text-slate-400 mt-2 text-sm">الاستضافة ممكن تكون مسدودة من الوصول لـ elcinema.com</p>
    <a href="?<?php echo http_build_query(array_merge($_GET,['debug'=>'1']));?>" class="text-yellow-400 underline text-sm mt-3 inline-block">🔧 شغّل التشخيص لمعرفة السبب</a>
</div>
<?php endif; ?>

<!-- الشاشة الافتراضية -->
<?php if(!$search_results&&!$item_details&&!$error_msg&&!$debug_mode): ?>
<div class="card p-6">
    <h3 class="text-white font-bold text-lg mb-4">📖 طريقة الاستخدام</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-slate-900 rounded-xl p-4">
            <p class="text-indigo-400 font-bold mb-3">🔍 طريقة 1: بحث بالاسم</p>
            <ol class="space-y-2 text-slate-300 text-sm">
                <li>1. اكتب اسم الفيلم أو المسلسل</li>
                <li>2. اختر النوع واضغط ابحث</li>
                <li>3. اضغط على النتيجة</li>
                <li>4. انسخ SQL وألصقه في phpMyAdmin</li>
            </ol>
        </div>
        <div class="bg-slate-900 rounded-xl p-4">
            <p class="text-green-400 font-bold mb-3">🎯 طريقة 2: ID مباشر (الأضمن)</p>
            <ol class="space-y-2 text-slate-300 text-sm">
                <li>1. افتح elcinema.com في المتصفح</li>
                <li>2. ابحث وافتح صفحة العمل</li>
                <li>3. انسخ الرقم من الرابط</li>
                <li>4. الصقه في "ID مباشر" واضغط جلب</li>
            </ol>
            <p class="text-yellow-400 text-xs mt-2 font-mono" dir="ltr">elcinema.com/work/<span class="text-green-400 font-bold">2098504</span>/</p>
        </div>
    </div>
    <div class="mt-4 p-3 rounded-lg bg-blue-500/10 border border-blue-500/30">
        <p class="text-blue-400 text-sm">💡 الملف يجلب session cookie تلقائياً من موقع السينما كوم قبل كل طلب — متوافق مع InfinityFree</p>
    </div>
    <div class="text-center mt-3">
        <a href="?debug=1&direct_id=2098504" class="text-slate-500 text-xs underline">اختبار التشخيص بـ ID تجريبي</a>
    </div>
</div>
<?php endif; ?>

</div>
<script>
function cp(id,btn){const el=document.getElementById(id);el.select();document.execCommand('copy');const o=btn.textContent;btn.textContent='✅ تم';btn.style.background='#065f46';setTimeout(()=>{btn.textContent=o;btn.style.background='';},2000);}
function showTab(t){document.getElementById('ts').classList.toggle('hidden',t!=='s');document.getElementById('td').classList.toggle('hidden',t!=='d');document.querySelectorAll('.tab').forEach((el,i)=>{el.classList.toggle('on',(i===0&&t==='s')||(i===1&&t==='d'));});}
</script>
</body>
</html>
