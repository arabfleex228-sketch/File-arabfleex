<?php
// إخفاء التحذيرات المزعجة الخاصة بإصدارات PHP الحديثة عشان المشغل يبقى نضيف
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);

require_once 'admin/db_config.php';
// تحديث الترميز إلى utf8mb4 لدعم اللغة العربية بشكل كامل وصحيح بدون أخطاء
$conn->set_charset("utf8mb4");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تحديد رابط الموقع ديناميكياً بناءً على الدومين اللي الزائر فاتحه حالياً
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
$base_url = $protocol . $_SERVER['HTTP_HOST'];
$site_name = "عرب فليكس";

// تجهيز المتغيرات
$link1 = $link2 = $link3 = $link4 = '';
$download_link = ''; 
$download_link_2 = '';
$title = 'مشاهدة';
$next_play_link = '';
$next_title = '';
$show_logo = 1; // الافتراضي 1 (يعني إظهار اللوجو)

// قائمة روابط الـ Proxy الخاصة بك على Cloudflare Workers لفك الحماية وتوزيع الضغط
$cf_proxies = [
    "https://lingering-sun-46b4.mf828262.workers.dev/?url=",  // الـ Worker الأول (الأصلي)
    "https://jolly-term-f45d.afu6656gu.workers.dev/?url=",    // الـ Worker الثاني
    "https://shy-snow-52c3.alifalah9988044.workers.dev/?url=", // الـ Worker الثالث
    "https://young-glade-3a0e.sspw9f88.workers.dev/?url="     // الـ Worker الرابع
];

// اختيار بروكسي عشوائي لكل زائر (لتجنب الوصول لـ Limit الخاص بـ Cloudflare)
$cf_proxy_url = $cf_proxies[array_rand($cf_proxies)];

// متغيرات الـ SEO الافتراضية
$seo_desc = "مشاهدة أحدث الأفلام والمسلسلات المترجمة والعربية بجودة عالية على $site_name.";
$seo_keywords = "مشاهدة, تحميل, افلام, مسلسلات, مترجم, عربي, اون لاين, جودة عالية, HD";
$seo_image = $base_url . "/logo.png"; // مسار اللوجو الافتراضي الديناميكي
$seo_type = "video.movie";
$canonical_url = $base_url;
$schema_markup = '';
$item_category = '';

$ep_id = isset($_GET['ep_id']) ? intval($_GET['ep_id']) : 0;
$movie_id = isset($_GET['movie_id']) ? intval($_GET['movie_id']) : 0;
$current_server = isset($_GET['server']) ? (int)$_GET['server'] : 1;

if ($ep_id > 0) {
    $stmt = $conn->prepare("SELECT e.*, s.title as series_title, s.description as series_desc, s.poster as series_poster, s.category FROM episodes e JOIN series s ON e.series_id = s.id WHERE e.id = ? LIMIT 1");
    $stmt->bind_param("i", $ep_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $link1 = $row['watch_link'] ?? '';
        $link2 = $row['watch_link_2'] ?? '';
        $link3 = $row['watch_link_3'] ?? '';
        $link4 = $row['watch_link_4'] ?? '';
        $download_link = $row['download_link'] ?? ''; 
        $download_link_2 = $row['download_link_2'] ?? ''; 
        
        $show_logo = isset($row['show_logo']) ? (int)$row['show_logo'] : 1;
        
        $category = isset($row['category']) ? strtolower($row['category']) : '';
        $type_word = "مسلسل";
        $lang_word = ""; 
        $schema_lang = "ar";

        // تحديد نوع العمل بشكل دقيق
        if (in_array($category, ['wrestling'])) {
            $type_word = "عرض";
        } elseif (in_array($category, ['tv_show', 'shows'])) {
            $type_word = "برنامج";
        } elseif (in_array($category, ['foreign', 'english', 'usa'])) {
            $type_word = "مسلسل أجنبي";
            $lang_word = "مترجم";
            $schema_lang = "en";
        } elseif (in_array($category, ['arabic', 'egyptian', 'syrian', 'gulf'])) {
            $type_word = "مسلسل عربي";
        } elseif (in_array($category, ['turkish'])) {
            $type_word = "مسلسل تركي";
            $lang_word = "مترجم";
            $schema_lang = "tr";
        } elseif (in_array($category, ['indian'])) {
            $type_word = "مسلسل هندي";
            $lang_word = "مترجم";
            $schema_lang = "hi";
        } elseif (in_array($category, ['anime', 'cartoon'])) {
            $type_word = "انمي";
            $lang_word = "مترجم";
            $schema_lang = "ja";
        }
        
        $item_category = $type_word;

        // --- المنطق الذكي لقراءة الاسم وتنظيفه ---
        $raw_ep_title = trim($row['title'] ?? '');
        $raw_series_title = trim($row['series_title'] ?? '');
        $ep_num = $row['episode_number'];

        if (empty($raw_ep_title) || is_numeric($raw_ep_title) || $raw_ep_title == $ep_num) {
            $ep_title_text = "الحلقة " . $ep_num;
        } else {
            if (strpos($raw_ep_title, $raw_series_title) !== false) {
                $ep_title_text = trim(str_replace($raw_series_title, '', $raw_ep_title));
            } else {
                $ep_title_text = $raw_ep_title;
            }
        }
        
        if ($category === 'wrestling' && strpos($ep_title_text, 'الحلقة') !== false) {
            $ep_title_text = str_replace('الحلقة', 'الجزء', $ep_title_text); 
        }

        $clean_series_title = htmlspecialchars($raw_series_title, ENT_QUOTES, 'UTF-8');
        
        $raw_title = "$clean_series_title - $ep_title_text";
        $title = preg_replace('/\s+/', ' ', trim($raw_title));
        
        $clean_desc = mb_substr(strip_tags($row['series_desc']), 0, 150);
        $seo_desc = "شاهد وحمل $type_word $clean_series_title $ep_title_text $lang_word اون لاين بجودة عالية HD بدون إعلانات مزعجة. $clean_desc...";
        
        $seo_keywords = "مشاهدة, $clean_series_title, $ep_title_text, $type_word, $lang_word, $type_word $clean_series_title, تحميل, جودة عالية, HD, $site_name";

        if(!empty($row['series_poster'])) {
            $seo_image = (strpos($row['series_poster'], 'http') === 0) ? $row['series_poster'] : $base_url . "/uploads/" . $row['series_poster'];
        }
        $seo_type = "video.episode";
        $canonical_url = $base_url . "/play.php?ep_id=" . $ep_id;

        $schema_array = [
            "@context" => "https://schema.org",
            "@type" => "TVEpisode",
            "name" => $ep_title_text,
            "partOfSeries" => [
                "@type" => "TVSeries",
                "name" => $clean_series_title,
                "inLanguage" => $schema_lang,
                "genre" => $type_word
            ],
            "description" => strip_tags($seo_desc),
            "image" => $seo_image,
            "episodeNumber" => (string)$ep_num
        ];
        $schema_markup = json_encode($schema_array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        if (!isset($_SESSION['viewed_eps'])) $_SESSION['viewed_eps'] = [];
        if (!in_array($ep_id, $_SESSION['viewed_eps'])) {
            $s_id = (int)$row['series_id'];
            $conn->query("UPDATE episodes SET views = views + 1 WHERE id = $ep_id");
            $conn->query("UPDATE series SET views = views + 1 WHERE id = $s_id");
            $_SESSION['viewed_eps'][] = $ep_id;
        }

        $next_ep_num = $row['episode_number'] + 1;
        $next_stmt = $conn->query("SELECT id, title, episode_number FROM episodes WHERE series_id = {$row['series_id']} AND episode_number = $next_ep_num LIMIT 1");
        if ($next_stmt && $next_row = $next_stmt->fetch_assoc()) {
            $next_play_link = "play.php?ep_id=" . $next_row['id'];
            $raw_next_title = trim($next_row['title'] ?? '');
            
            if (empty($raw_next_title) || is_numeric($raw_next_title) || $raw_next_title == $next_row['episode_number']) {
                $next_title = "الحلقة " . $next_row['episode_number'];
            } else {
                if (strpos($raw_next_title, $raw_series_title) !== false) {
                    $next_title = trim(str_replace($raw_series_title, '', $raw_next_title));
                } else {
                    $next_title = $raw_next_title;
                }
            }
        }
    } else {
        die("<div style='background-color:#050505; min-height:100vh; display:flex; align-items:center; justify-content:center;'><h2 style='color:#ef4444; font-family: Cairo, sans-serif;'>عذراً، المحتوى غير موجود.</h2></div>");
    }

} elseif ($movie_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM movies WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $movie_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $link1 = $row['watch_link'] ?? '';
        $link2 = $row['watch_link_2'] ?? '';
        $link3 = $row['watch_link_3'] ?? '';
        $link4 = $row['watch_link_4'] ?? '';
        $download_link = $row['download_link'] ?? '';
        $download_link_2 = $row['download_link_2'] ?? '';
        
        $show_logo = isset($row['show_logo']) ? (int)$row['show_logo'] : 1;

        $category = isset($row['category']) ? strtolower($row['category']) : '';
        $movie_type = "فيلم";
        $lang_word = "";
        $schema_lang = "ar";

        if (in_array($category, ['foreign', 'english', 'action', 'horror', 'scifi', 'comedy_en'])) {
            $movie_type = "فيلم أجنبي";
            $lang_word = "مترجم";
            $schema_lang = "en";
        } elseif (in_array($category, ['arabic', 'egyptian', 'comedy_ar'])) {
            $movie_type = "فيلم عربي";
        } elseif (in_array($category, ['anime_movie'])) {
            $movie_type = "فيلم انمي";
            $lang_word = "مترجم";
            $schema_lang = "ja";
        } elseif (in_array($category, ['hindi', 'indian'])) {
            $movie_type = "فيلم هندي";
            $lang_word = "مترجم";
            $schema_lang = "hi";
        }
        
        $item_category = $movie_type;

        $raw_movie_title = trim($row['title'] ?? '');
        $clean_movie_name = trim(str_replace(['فيلم', 'الفيلم', 'فيلم '], '', $raw_movie_title));
        $clean_movie_title = htmlspecialchars($clean_movie_name, ENT_QUOTES, 'UTF-8');
        
        $title = preg_replace('/\s+/', ' ', trim($clean_movie_title));
        
        $clean_desc = mb_substr(strip_tags($row['description']), 0, 150);
        $seo_desc = "شاهد وحمل $movie_type $clean_movie_title $lang_word كامل اون لاين بجودة عالية HD. $clean_desc...";
        
        $seo_keywords = "مشاهدة, فيلم $clean_movie_title, $movie_type, $lang_word, كامل, تحميل, جودة عالية, ايجي بست, ماي سيما, $site_name";

        if(!empty($row['poster'])) {
            $seo_image = (strpos($row['poster'], 'http') === 0) ? $row['poster'] : $base_url . "/uploads/" . $row['poster'];
        }
        $canonical_url = $base_url . "/play.php?movie_id=" . $movie_id;

        $schema_array = [
            "@context" => "https://schema.org",
            "@type" => "Movie",
            "name" => $clean_movie_title,
            "description" => strip_tags($seo_desc),
            "image" => $seo_image,
            "inLanguage" => $schema_lang,
            "dateCreated" => $row['created_at']
        ];
        $schema_markup = json_encode($schema_array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

    } else {
         die("<div style='background-color:#050505; min-height:100vh; display:flex; align-items:center; justify-content:center;'><h2 style='color:#ef4444; font-family: Cairo, sans-serif;'>عذراً، الفيلم غير موجود.</h2></div>");
    }
} else {
     die("<div style='background-color:#050505; min-height:100vh; display:flex; align-items:center; justify-content:center;'><h2 style='color:#ef4444; font-family: Cairo, sans-serif;'>رابط غير صالح.</h2></div>");
}

$watch_link_raw = $link1;
if ($current_server == 4 && !empty(trim($link4 ?? ''))) $watch_link_raw = $link4;
elseif ($current_server == 3 && !empty(trim($link3 ?? ''))) $watch_link_raw = $link3;
elseif ($current_server == 2 && !empty(trim($link2 ?? ''))) $watch_link_raw = $link2;

$watch_link_raw = stripslashes(html_entity_decode(trim($watch_link_raw ?? ''), ENT_QUOTES, 'UTF-8'));
$watch_link = $watch_link_raw; 

if (!empty($watch_link_raw) && preg_match('/<(iframe|embed|object|div|script).*?src\s*=\s*(["\'])(.*?)\2/is', $watch_link_raw, $matches)) {
    $watch_link = $matches[3];
} elseif (!empty($watch_link_raw) && preg_match('/src\s*=\s*(["\'])(.*?)\1/is', $watch_link_raw, $matches)) {
    $watch_link = $matches[2];
}

$watch_link = trim($watch_link ?? '');

if (substr($watch_link, 0, 2) === '//') {
    $watch_link = 'https:' . $watch_link;
} elseif (!empty($watch_link) && !preg_match('/^https?:\/\//i', $watch_link) && strpos($watch_link, '.') !== false && strpos($watch_link, '/') !== 0) {
    $watch_link = 'https://' . $watch_link;
}

if (strpos($watch_link, 'streamlive.xo.je') !== false && strpos($watch_link, 'player.php') === false) {
    $watch_link = $base_url . "/player.php?url=" . urlencode($watch_link);
}

$is_multi_quality = false;
$multi_sources = [];
if (preg_match_all('/(\d{3,4})\s*[\|\*]\s*(https?:\/\/[^\s,<>]+)/i', $watch_link, $matches, PREG_SET_ORDER)) {
    foreach ($matches as $match) {
        $multi_sources[] = [
            'size' => (int)$match[1],
            'url' => trim($match[2] ?? '')
        ];
    }
    
    if (count($multi_sources) > 1) {
        $is_multi_quality = true;
        usort($multi_sources, function($a, $b) { return $b['size'] <=> $a['size']; });
    } elseif (count($multi_sources) == 1) {
        $watch_link = $multi_sources[0]['url'];
    }
}

$link_path = strtolower(strtok($watch_link, '?'));
$is_direct_link = false;
$is_youtube = false;
$youtube_id = '';

if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/\s]{11})%i', $watch_link, $match)) {
    $is_youtube = true;
    $youtube_id = $match[1];
} elseif (preg_match('!(?:dailymotion\.com/(?:video|hub)/([a-zA-Z0-9]+))|(?:dai\.ly/([a-zA-Z0-9]+))!i', $watch_link, $match)) {
    $dailymotion_id = !empty($match[2]) ? $match[2] : $match[1];
    $watch_link = "https://www.dailymotion.com/embed/video/" . $dailymotion_id . "?autoplay=1";
}

// --- معالجة الروابط وتمريرها عبر Cloudflare Proxy تلقائياً ---
if ($is_multi_quality) {
    $is_direct_link = true;
    foreach ($multi_sources as &$src) {
        if (strpos($src['url'], 'workers.dev') === false && (strpos($src['url'], 'shahidtv.net') !== false || strpos($src['url'], 'mhav1.com') !== false || strpos($src['url'], 'provegooott.com') !== false)) {
            $src['url'] = $cf_proxy_url . urlencode($src['url']);
        }
    }
    unset($src);
} elseif (!$is_youtube && strpos($watch_link, 'player.php') === false && (substr($link_path, -5) === '.m3u8' || substr($link_path, -4) === '.mp4' || substr($link_path, -4) === '.mkv' || strpos($watch_link, 'shahidtv.net') !== false || strpos($watch_link, 'mhav1.com') !== false || strpos($watch_link, 'provegooott.com') !== false || strpos($watch_link, 'googlevideo.com') !== false || strpos($watch_link, 'mdiaload.com') !== false)) {
    $is_direct_link = true;
    
    // فحص وتمرير روابط MP4 المحمية من خلال الـ Proxy الخاص بك للمشاهدة
    if (strpos($watch_link, 'workers.dev') === false && (strpos($watch_link, 'shahidtv.net') !== false || strpos($watch_link, 'mhav1.com') !== false || strpos($watch_link, 'provegooott.com') !== false || substr($link_path, -4) === '.mkv' || substr($link_path, -4) === '.mp4')) {
        $watch_link = $cf_proxy_url . urlencode($watch_link);
    }
}

if (!$is_direct_link && strpos($watch_link, 'sh.ramadan-series.site') !== false) {
    $watch_link = "proxy.php?url=" . urlencode($watch_link);
}

$base_server_url = ($ep_id > 0) ? "play.php?ep_id=$ep_id" : "play.php?movie_id=$movie_id";
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    
    <title>مشاهدة <?php echo htmlspecialchars($title); ?> - <?php echo $site_name; ?></title>

    <link rel="icon" type="image/png" href="<?php echo $base_url; ?>/favicon.png">
    <link rel="apple-touch-icon" href="<?php echo $base_url; ?>/favicon.png">

    <meta name="description" content="<?php echo $seo_desc; ?>">
    <meta name="keywords" content="<?php echo $seo_keywords; ?>">
    <link rel="canonical" href="<?php echo $canonical_url; ?>">
    <meta name="robots" content="index, follow">
    <meta name="theme-color" content="#030303">
    
    <meta property="og:title" content="<?php echo htmlspecialchars($title); ?>">
    <meta property="og:description" content="<?php echo $seo_desc; ?>">
    <meta property="og:image" content="<?php echo $seo_image; ?>">
    <meta property="og:url" content="<?php echo $canonical_url; ?>">
    <meta property="og:type" content="<?php echo $seo_type; ?>">
    <meta property="og:site_name" content="<?php echo $site_name; ?>">
    <meta property="og:locale" content="ar_AR">

    <?php if(!empty($schema_markup)): ?>
    <script type="application/ld+json">
        <?php echo $schema_markup; ?>
    </script>
    <?php endif; ?>

    <?php if (isset($ad_settings) && $ad_settings['status'] == 1): ?>
        <?php if (!empty($ad_settings['popunder_code'])) echo $ad_settings['popunder_code']; ?>
        <?php if (!empty($ad_settings['social_bar'])) echo $ad_settings['social_bar']; ?>
    <?php endif; ?>
    
    <script src="https://cdn.plyr.io/3.7.8/plyr.js"></script>
    <link rel="stylesheet" href="https://cdn.plyr.io/3.7.8/plyr.css" />
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { cairo: ['Cairo', 'sans-serif'] },
                    colors: {
                        dark: {
                            900: '#030303', 
                            800: '#0a0a0a',
                            700: '#141414',
                        },
                        brand: {
                            gold: '#facc15', 
                            primary: '#eab308' 
                        }
                    },
                    boxShadow: {
                        'neon': '0 0 40px -10px rgba(250, 204, 21, 0.3)',
                        'glass': '0 8px 32px 0 rgba(0, 0, 0, 0.37)'
                    }
                }
            }
        }
    </script>

    <style>
        ::-webkit-scrollbar { display: none; }
        body { 
            background-color: #030303; 
            font-family: 'Cairo', sans-serif;
            -webkit-tap-highlight-color: transparent;
            overflow-x: hidden;
        }

        .dynamic-bg {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            z-index: -2;
            background-image: url('<?php echo $seo_image; ?>');
            background-size: cover;
            background-position: center;
            filter: blur(80px);
            opacity: 0.35;
            transform: scale(1.1); 
        }
        
        .dynamic-overlay {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            z-index: -1;
            background: linear-gradient(to bottom, rgba(3,3,3,0.5) 0%, rgba(3,3,3,0.95) 100%);
        }

        :root {
            --plyr-color-main: #facc15;
            --plyr-video-control-color: #d4d4d8;
            --plyr-video-control-color-hover: #ffffff;
            --plyr-menu-background: rgba(15, 15, 15, 0.85);
            --plyr-menu-color: #ffffff;
            --plyr-menu-border-color: rgba(255, 255, 255, 0.05);
            --plyr-menu-shadow: 0 20px 40px rgba(0, 0, 0, 0.8);
            --plyr-font-family: 'Cairo', sans-serif;
            --plyr-range-thumb-height: 16px;
            --plyr-range-track-height: 6px;
            --plyr-tooltip-background: #facc15;
            --plyr-tooltip-color: #000;
        }

        .player-wrapper {
            position: relative; 
            width: 100%; 
            aspect-ratio: 16/9; 
            background: #000;
            z-index: 10;
        }

        .plyr, .embed-container { width: 100%; height: 100%; }
        .embed-container iframe { width: 100%; height: 100%; border: none; position: absolute; top: 0; left: 0; }

        .plyr__control--overlaid {
            background: rgba(0, 0, 0, 0.4) !important;
            backdrop-filter: blur(10px) !important;
            border: 2px solid rgba(250, 204, 21, 0.5) !important;
            color: #facc15 !important;
            padding: 26px !important;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
            box-shadow: 0 0 0 0 rgba(250, 204, 21, 0.4) !important;
            animation: pulse-gold 2s infinite;
        }
        
        @keyframes pulse-gold {
            0% { box-shadow: 0 0 0 0 rgba(250, 204, 21, 0.4); }
            70% { box-shadow: 0 0 0 25px rgba(250, 204, 21, 0); }
            100% { box-shadow: 0 0 0 0 rgba(250, 204, 21, 0); }
        }

        .plyr__control--overlaid:hover {
            background: #facc15 !important; color: #000 !important;
            transform: scale(1.1) !important;
            border-color: #facc15 !important;
            animation: none;
            box-shadow: 0 0 40px rgba(250, 204, 21, 0.6) !important;
        }
        
        .plyr--video .plyr__controls {
            padding: 60px 20px 20px !important;
            background: linear-gradient(to top, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0.5) 40%, transparent 100%) !important;
        }

        .plyr__menu__container { 
            direction: rtl; text-align: right; 
            border-radius: 16px; 
            backdrop-filter: blur(25px); 
            -webkit-backdrop-filter: blur(25px);
            padding: 10px; border: 1px solid rgba(255,255,255,0.1);
        }
        .plyr__menu__container button { padding: 10px 16px; border-radius: 10px; font-weight: 700; font-size: 14px; transition: 0.3s; }
        .plyr__menu__container .plyr__control[aria-checked="true"] { background: rgba(250, 204, 21, 0.15); color: #facc15; }
        .plyr__menu__container .plyr__control[aria-checked="true"]::before { background: #facc15 !important; }
        .plyr__menu__value { background: rgba(255,255,255,0.1); border-radius: 6px; padding: 2px 10px; font-size: 12px; color: #facc15; margin-right: auto !important; margin-left: 0 !important; }
        .plyr__menu__container [data-plyr="settings"]::after { margin-left: 0; margin-right: auto; transform: rotate(180deg); }
        .plyr__menu__container [data-plyr="settings"][aria-expanded="true"]::after { transform: rotate(90deg); }
        .plyr__menu__container .plyr__control--back { justify-content: flex-start !important; }
        .plyr__menu__container .plyr__control--back::before { margin-right: 0; margin-left: 10px; transform: rotate(180deg); }

        .player-brand-logo {
            position: absolute; top: 25px; left: 30px; z-index: 50;
            font-size: 1.6rem; font-weight: 900; letter-spacing: -0.5px;
            color: #fff; text-shadow: 0 4px 20px rgba(0,0,0,0.9);
            transition: opacity 0.5s ease; pointer-events: none; direction: ltr; user-select: none;
        }
        .player-brand-logo span { color: #facc15; }
        .logo-hidden { opacity: 0 !important; }

        .ep-next-card {
            position: absolute; bottom: 90px; right: 25px; z-index: 40;
            background: rgba(10, 10, 10, 0.85); backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 16px; padding: 12px 20px;
            display: flex; align-items: center; gap: 16px; text-decoration: none;
            box-shadow: 0 20px 40px rgba(0,0,0,0.8);
            opacity: 0; transform: translateX(30px) scale(0.95); pointer-events: none;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            max-width: 320px;
        }
        .ep-next-card::before {
            content: ''; position: absolute; top: 0; right: 0; width: 4px; height: 100%;
            background: #facc15; border-radius: 0 16px 16px 0;
        }
        .ep-next-card.active { opacity: 1; transform: translateX(0) scale(1); pointer-events: auto; }
        .ep-next-card:hover { background: rgba(20, 20, 20, 0.95); transform: translateY(-3px) scale(1.02); }
        
        .hide-scroll { -ms-overflow-style: none; scrollbar-width: none; }
        .hide-scroll::-webkit-scrollbar { display: none; }

        @media (min-width: 768px) {
            .player-outer { 
                border-radius: 24px; 
                overflow: hidden; 
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
                border: 1px solid rgba(255,255,255,0.05);
                margin-top: 2rem;
            }
        }
        
        @media (max-width: 767px) {
            .player-outer { width: 100vw; margin-left: calc(-50vw + 50%); margin-right: calc(-50vw + 50%); border-radius: 0; }
            .player-wrapper { aspect-ratio: auto; height: 35vh; min-height: 240px; }
            .plyr--video .plyr__controls { padding: 30px 10px 10px !important; }
            .player-brand-logo { top: 15px; left: 15px; font-size: 1.2rem; }
            .plyr__control--overlaid { padding: 20px !important; border-width: 1px !important; }
            .ep-next-card { bottom: 65px; right: 15px; padding: 10px 15px; border-radius: 12px; max-width: 250px; }
            .ep-next-card::before { border-radius: 0 12px 12px 0; }
            .glass-nav { padding-left: 1rem; padding-right: 1rem; }
            .title-area h1 { font-size: 1.3rem !important; }
        }
    </style>
</head>
<body class="text-zinc-200 antialiased">
    
    <div class="dynamic-bg"></div>
    <div class="dynamic-overlay"></div>

    <div class="sr-only">
        <h1><?php echo htmlspecialchars($title); ?></h1>
        <p><?php echo $seo_desc; ?></p>
        <strong>الكلمات الدليلية: <?php echo $seo_keywords; ?></strong>
    </div>

    <header class="fixed top-0 w-full z-50 transition-all duration-300">
        <div class="glass-nav mx-auto px-6 h-20 flex items-center justify-between bg-gradient-to-b from-black/80 to-transparent backdrop-blur-[2px]">
            <a href="/" class="flex-shrink-0 text-2xl md:text-3xl font-black tracking-tighter drop-shadow-lg hover:scale-105 transition-transform">
                <span class="text-brand-gold">عرب</span> <span class="text-white">فليكس</span>
            </a>

            <a href="javascript:window.close()" class="group flex items-center justify-center w-10 h-10 md:w-auto md:px-5 md:h-10 bg-white/5 hover:bg-white/10 backdrop-blur-md border border-white/10 text-white rounded-full font-bold text-sm transition-all duration-300 shadow-glass">
                <span class="hidden md:block ml-2 group-hover:text-red-400 transition-colors">إغلاق المشغل</span>
                <i class="fas fa-times group-hover:text-red-400 group-hover:rotate-90 transition-all duration-300 text-lg"></i>
            </a>
        </div>
    </header>

    <main class="w-full max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 md:pt-24 pb-12 flex flex-col gap-6 relative z-10">
        
        <?php if (empty($watch_link) || $watch_link == '#'): ?>
             <div class="mt-20 bg-dark-800/80 backdrop-blur-xl border border-white/5 rounded-3xl p-16 text-center shadow-2xl mx-4 md:mx-0">
                 <div class="w-24 h-24 bg-red-500/10 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-video-slash text-5xl text-red-500"></i>
                 </div>
                 <h3 class="text-2xl text-white font-black">عذراً، لا يوجد رابط مشاهدة متاح حالياً.</h3>
                 <p class="text-zinc-500 mt-2">يرجى المحاولة مرة أخرى لاحقاً أو إبلاغ الإدارة.</p>
             </div>
        <?php else: ?>
            
            <div class="title-area mt-4 md:mt-0 flex flex-col gap-2 order-2 md:order-1">
                <div class="flex items-center gap-3">
                    <?php if(!empty($item_category)): ?>
                    <span class="px-3 py-1 bg-brand-gold/20 border border-brand-gold/30 rounded-lg text-xs font-black text-brand-gold tracking-wide">
                        <?php echo htmlspecialchars($item_category); ?>
                    </span>
                    <?php endif; ?>
                    <span class="flex items-center gap-1 text-xs font-bold text-zinc-400 bg-white/5 px-2 py-1 rounded-lg">
                        <i class="fas fa-eye"></i> جودة عالية
                    </span>
                </div>
                <h1 class="text-2xl md:text-4xl font-black text-white leading-tight drop-shadow-md">
                    <?php echo htmlspecialchars($title); ?>
                </h1>
            </div>

            <div class="player-outer order-1 md:order-2 bg-black relative group">
                <div class="hidden md:block absolute -inset-0.5 bg-brand-gold/20 rounded-3xl blur-2xl opacity-0 group-hover:opacity-100 transition duration-1000 pointer-events-none"></div>
                
                <div class="player-wrapper">
                    <?php if ($show_logo == 1): ?>
                        <div id="playerWatermark" class="player-brand-logo">
                            Arab<span>Fleex</span>
                        </div>
                    <?php endif; ?>

                    <?php if ($is_youtube): ?>
                        <div id="player" data-plyr-provider="youtube" data-plyr-embed-id="<?php echo htmlspecialchars($youtube_id); ?>"></div>
                    <?php elseif ($is_direct_link): ?>
                        <?php 
                            $is_google_video = (strpos($watch_link, 'googlevideo.com') !== false); 
                            $needs_crossorigin = (!$is_google_video && substr($link_path, -5) === '.m3u8') ? 'crossorigin' : '';
                        ?>
                        <video id="player" controls autoplay preload="auto" playsinline referrerpolicy="no-referrer" <?php echo $needs_crossorigin; ?> data-poster="<?php echo htmlspecialchars($seo_image); ?>">
                            <?php if ($is_multi_quality): ?>
                                <?php foreach ($multi_sources as $src): ?>
                                    <source src="<?php echo htmlspecialchars($src['url']); ?>" type="video/mp4" size="<?php echo $src['size']; ?>">
                                <?php endforeach; ?>
                            <?php else: ?>
                                <source src="<?php echo htmlspecialchars($watch_link); ?>" type="video/mp4">
                            <?php endif; ?>
                        </video>
                    <?php else: ?>
                        <div class="embed-container">
                            <iframe src="<?php echo htmlspecialchars($watch_link); ?>" frameborder="0" scrolling="no" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen webkitallowfullscreen mozallowfullscreen></iframe>
                        </div>
                    <?php endif; ?>

                    <?php if ($next_play_link && ($is_youtube || $is_direct_link)): ?>
                    <a href="<?php echo $next_play_link; ?>" id="nextEpCard" class="ep-next-card">
                        <div class="flex-1 overflow-hidden">
                            <span class="block text-[10px] md:text-xs font-black text-brand-gold mb-1 uppercase tracking-wider">الحلقة التالية <span class="text-zinc-500 font-normal ml-1">تبدأ قريباً</span></span>
                            <div class="text-sm md:text-base font-bold text-white truncate"><?php echo htmlspecialchars($next_title); ?></div>
                        </div>
                        <div class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-white/10 flex items-center justify-center text-white shrink-0 shadow-inner">
                            <i class="fas fa-play ml-1 text-sm"></i>
                        </div>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="order-3 mt-2">
                <div class="bg-dark-800/60 backdrop-blur-xl border border-white/5 rounded-2xl p-4 md:p-6 shadow-glass">
                    
                    <div class="mb-4">
                        <h3 class="text-xs md:text-sm font-bold text-zinc-400 mb-3 uppercase tracking-wider flex items-center gap-2">
                            <i class="fas fa-satellite-dish text-brand-gold"></i> مصادر التشغيل
                        </h3>
                        <div class="flex overflow-x-auto hide-scroll gap-3 pb-2 snap-x">
                            <?php 
                            $servers = [
                                1 => ['link' => $link1],
                                2 => ['link' => $link2],
                                3 => ['link' => $link3],
                                4 => ['link' => $link4],
                            ];
                            
                            foreach ($servers as $num => $srv): 
                                if(!empty(trim($srv['link'] ?? '')) && trim($srv['link']) !== '#'): 
                                    $isActive = ($current_server == $num);
                                    
                                    if ($isActive) {
                                        $class = "snap-start shrink-0 flex items-center gap-2 px-5 py-3 rounded-xl font-bold text-sm bg-brand-gold/10 text-brand-gold border border-brand-gold/50 shadow-[0_0_15px_rgba(250,204,21,0.2)]";
                                        $iconClass = "text-brand-gold animate-pulse";
                                    } else {
                                        $class = "snap-start shrink-0 flex items-center gap-2 px-5 py-3 rounded-xl font-bold text-sm bg-white/5 text-zinc-300 border border-transparent hover:bg-white/10 hover:border-white/10 transition-all duration-300";
                                        $iconClass = "text-zinc-500";
                                    }
                            ?>
                                <a href="<?php echo $base_server_url; ?>&server=<?php echo $num; ?>" class="<?php echo $class; ?>">
                                    <i class="fas fa-play-circle <?php echo $iconClass; ?> text-lg"></i>
                                    <span><?php echo ($num == 1) ? 'سيرفر عرب فليكس' : 'سيرفر ' . $num; ?></span>
                                </a>
                            <?php 
                                endif; 
                            endforeach; 
                            ?>
                        </div>
                    </div>

                    <?php if((!empty(trim($download_link ?? '')) && trim($download_link) !== '#') || (!empty(trim($download_link_2 ?? '')) && trim($download_link_2) !== '#')): ?>
                    <div class="pt-4 border-t border-white/5">
                        <h3 class="text-xs md:text-sm font-bold text-zinc-400 mb-3 uppercase tracking-wider flex items-center gap-2">
                            <i class="fas fa-download text-emerald-400"></i> خيارات التحميل
                        </h3>
                        <div class="flex flex-wrap gap-3">
                            
                            <?php if(!empty(trim($download_link ?? '')) && trim($download_link) !== '#'): ?>
                                <?php
                                $d_link_trim = trim($download_link);
                                if (strpos($d_link_trim, '|') !== false) {
                                    $quality_links = explode(',', $d_link_trim);
                                    foreach ($quality_links as $q_link) {
                                        $q_parts = explode('|', $q_link);
                                        if (count($q_parts) == 2) {
                                            $quality_name = trim($q_parts[0]);
                                            $quality_url = trim($q_parts[1]);
                                            
                                            // تمرير الرابط عبر البروكسي أوتوماتيكياً للتحميل اليدوي
                                            if (strpos($quality_url, 'workers.dev') === false && (strpos($quality_url, 'shahidtv.net') !== false || strpos($quality_url, 'mhav1.com') !== false || preg_match('/\.mp4$|\.mkv$/i', strtok($quality_url, '?')))) {
                                                $quality_url = $cf_proxy_url . urlencode($quality_url);
                                            }
                                            
                                            // إضافة &dl=1 لإجبار التحميل
                                            if (strpos($quality_url, 'workers.dev') !== false && strpos($quality_url, '&dl=1') === false) {
                                                $quality_url .= '&dl=1';
                                            }
                                            
                                            ?>
                                            <a href="<?php echo htmlspecialchars($quality_url); ?>" target="_blank" class="flex-1 min-w-[120px] flex items-center justify-center gap-2 px-4 py-3 rounded-xl font-bold text-sm bg-brand-gold/10 text-brand-gold border border-brand-gold/30 hover:bg-brand-gold hover:text-black transition-all duration-300 shadow-[0_0_15px_rgba(250,204,21,0.15)]">
                                                <i class="fas fa-inbox"></i> تحميل <?php echo htmlspecialchars($quality_name); ?>
                                            </a>
                                            <?php
                                        }
                                    }
                                } else {
                                    // تمرير الرابط المفرد عبر البروكسي للتحميل اليدوي
                                    if (strpos($d_link_trim, 'workers.dev') === false && (strpos($d_link_trim, 'shahidtv.net') !== false || strpos($d_link_trim, 'mhav1.com') !== false || preg_match('/\.mp4$|\.mkv$/i', strtok($d_link_trim, '?')))) {
                                        $d_link_trim = $cf_proxy_url . urlencode($d_link_trim);
                                    }
                                    // إضافة &dl=1 لإجبار التحميل
                                    if (strpos($d_link_trim, 'workers.dev') !== false && strpos($d_link_trim, '&dl=1') === false) {
                                        $d_link_trim .= '&dl=1';
                                    }
                                    ?>
                                    <a href="<?php echo htmlspecialchars($d_link_trim); ?>" target="_blank" class="flex-1 min-w-[140px] flex items-center justify-center gap-2 px-5 py-3 rounded-xl font-bold text-sm bg-brand-gold/10 text-brand-gold border border-brand-gold/30 hover:bg-brand-gold hover:text-black transition-all duration-300 shadow-[0_0_15px_rgba(250,204,21,0.15)]">
                                        <i class="fas fa-star"></i> سيرفر تحميل عرب فليكس
                                    </a>
                                    <?php
                                }
                                ?>
                            <?php endif; ?>
                            
                            <?php if(!empty(trim($download_link_2 ?? '')) && trim($download_link_2) !== '#'): ?>
                                <?php 
                                    $d2 = trim($download_link_2);
                                    // تمرير الرابط الثاني عبر البروكسي
                                    if (strpos($d2, 'workers.dev') === false && (strpos($d2, 'shahidtv.net') !== false || strpos($d2, 'mhav1.com') !== false || preg_match('/\.mp4$|\.mkv$/i', strtok($d2, '?')))) {
                                        $d2 = $cf_proxy_url . urlencode($d2);
                                    }
                                    if (strpos($d2, 'workers.dev') !== false && strpos($d2, '&dl=1') === false) {
                                        $d2 .= '&dl=1';
                                    }
                                ?>
                                <a href="<?php echo htmlspecialchars($d2); ?>" target="_blank" class="flex-1 min-w-[140px] flex items-center justify-center gap-2 px-5 py-3 rounded-xl font-bold text-sm bg-blue-500/10 text-blue-400 border border-blue-500/20 hover:bg-blue-500 hover:text-white transition-all duration-300 shadow-sm">
                                    <i class="fas fa-cloud-download-alt"></i> سيرفر تحميل 2
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
            
        <?php endif; ?>
    </main>

    <script>
        let lastScroll = 0;
        window.addEventListener("scroll", () => {
            const header = document.querySelector("header");
            const currentScroll = window.pageYOffset;
            if (currentScroll <= 0) {
                header.classList.remove("-translate-y-full");
                header.classList.add("bg-gradient-to-b");
                return;
            }
            if (currentScroll > lastScroll && currentScroll > 50) {
                header.classList.add("-translate-y-full");
            } else {
                header.classList.remove("-translate-y-full");
                header.classList.remove("bg-gradient-to-b");
                header.classList.add("bg-black/90");
            }
            lastScroll = currentScroll;
        });

        function setupWatermarkLogic(playerInstance) {
            const wm = document.getElementById('playerWatermark');
            if (wm) {
                playerInstance.on('play', () => wm.classList.add('logo-hidden'));
                playerInstance.on('pause', () => wm.classList.remove('logo-hidden'));
                playerInstance.on('ended', () => wm.classList.remove('logo-hidden'));
            }
        }

        if (document.getElementById('player')) {
            const playerElement = document.getElementById('player');
            const watchUrl = "<?php echo $watch_link; ?>";
            const isM3U8 = watchUrl.toLowerCase().split('?')[0].endsWith('.m3u8');
            const isMultiQuality = <?php echo $is_multi_quality ? 'true' : 'false'; ?>;
            const isYouTube = <?php echo $is_youtube ? 'true' : 'false'; ?>;
            
            let availableQualities = [1080, 720, 480, 360]; 
            <?php if ($is_multi_quality): ?>
                availableQualities = [<?php echo implode(',', array_column($multi_sources, 'size')); ?>];
            <?php endif; ?>
            
            let activeSettings = ['speed']; 
            if (isMultiQuality || (isM3U8 && Hls.isSupported()) || isYouTube) {
                activeSettings = ['quality', 'speed']; 
            }
            
            const plyrOptions = {
                i18n: { 
                    restart: 'إعادة', rewind: 'رجوع', play: 'تشغيل', pause: 'إيقاف', 
                    fastForward: 'تقدم', seek: 'بحث', volume: 'الصوت', mute: 'كتم', unmute: 'إلغاء الكتم',
                    settings: 'الإعدادات', speed: 'السرعة', quality: 'الجودة', normal: 'عادي', pip: 'صورة مصغرة', download: 'تحميل', 
                    qualityLabel: { 0: 'تلقائي', 1080: '1080p', 720: '720p', 480: '480p', 360: '360p' }
                },
                controls: [
                    'play-large', 'rewind', 'play', 'fast-forward', 'progress', 'current-time', 'duration',
                    'mute', 'volume', 'settings', 'pip', 'fullscreen' 
                ],
                youtube: { noCookie: true, rel: 0, showinfo: 0, iv_load_policy: 3, modestbranding: 1, playsinline: 1 },
                settings: activeSettings, 
                speed: { selected: 1, options: [0.75, 1, 1.25, 1.5, 2] },
                quality: { default: <?php echo $is_multi_quality ? ($multi_sources[0]['size'] ?? 720) : 720; ?>, options: availableQualities, forced: true },
                autoplay: true,
                seekTime: 10
            };

            let player;

            if (Hls.isSupported() && isM3U8 && !isMultiQuality) {
                const hls = new Hls({ maxMaxBufferLength: 30, enableWorker: true });
                hls.loadSource(watchUrl);
                
                hls.on(Hls.Events.MANIFEST_PARSED, function () {
                    const hlsQualities = hls.levels.map(l => l.height);
                    hlsQualities.unshift(0); 
                    
                    plyrOptions.quality = { 
                        default: 0, 
                        options: hlsQualities, 
                        forced: true, 
                        onChange: (e) => {
                            if (e === 0) window.hls.currentLevel = -1;
                            else window.hls.levels.forEach((level, index) => { if (level.height === e) window.hls.currentLevel = index; });
                        }
                    };
                    
                    player = new Plyr(playerElement, plyrOptions);
                    setupNextEpisodeLogic(player);
                    setupWatermarkLogic(player);
                });
                
                hls.attachMedia(playerElement);
                window.hls = hls;

            } else if (isYouTube) {
                player = new Plyr(playerElement, plyrOptions);
                setupNextEpisodeLogic(player);
                setupWatermarkLogic(player);
            } else {
                if (!isMultiQuality) {
                    playerElement.src = watchUrl;
                }
                player = new Plyr(playerElement, plyrOptions);
                setupNextEpisodeLogic(player);
                setupWatermarkLogic(player);
            }

            function setupNextEpisodeLogic(playerInstance) {
                const nextCard = document.getElementById('nextEpCard');
                if (nextCard) {
                    playerInstance.on('timeupdate', () => {
                        const timeRemaining = playerInstance.duration - playerInstance.currentTime;
                        if (timeRemaining <= 45 && timeRemaining > 0) { nextCard.classList.add('active'); } 
                        else { nextCard.classList.remove('active'); }
                    });
                    playerInstance.on('ended', () => { window.location.href = nextCard.href; });
                }
            }
        }

        <?php if (!$is_direct_link && !$is_youtube && $show_logo == 1): ?>
        setTimeout(() => {
            const wm = document.getElementById('playerWatermark');
            if (wm) wm.classList.add('logo-hidden');
        }, 5000);
        <?php endif; ?>

        setInterval(() => { fetch('/index.php?ajax_action=heartbeat').catch(() => {}); }, 60000); 
    </script>
</body>
</html>