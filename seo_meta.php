<?php
// تحديد رابط الموقع الأساسي ديناميكياً بدون معاملات إضافية
if (!isset($base_url)) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
    $base_url = $protocol . $_SERVER['HTTP_HOST'];
}

// تنظيف الـ URI للحصول على رابط Canonical نظيف بدون برامترات
$clean_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$canonical_url = $base_url . $clean_path;

$seo_title = "عرب فليكس | مشاهدة أحدث الأفلام والمسلسلات";
$seo_desc = "استمتع بمشاهدة أحدث الأفلام العربية والأجنبية والمسلسلات الحصرية بجودة عالية على عرب فليكس.";
$seo_img = $base_url . "/logo.png";
$schema_code = "";

// 1. فحص إذا كان الرابط لفيلم
if (strpos($clean_path, '/details/movies/') !== false) {
    $parts = explode('/', trim($clean_path, '/'));
    if (isset($parts[2])) {
        $movie_id = intval(substr($parts[2], 1));
        
        $stmt = $conn->prepare("SELECT title, description, poster, created_at FROM movies WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $movie_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($seo_m = $res->fetch_assoc()) {
                $seo_title = "مشاهدة فيلم " . htmlspecialchars($seo_m['title'], ENT_QUOTES, 'UTF-8') . " - عرب فليكس";
                $seo_desc = mb_substr(strip_tags($seo_m['description']), 0, 160) . "...";
                $seo_img = (strpos($seo_m['poster'], 'http') === 0) ? $seo_m['poster'] : $base_url . "/uploads/" . $seo_m['poster'];
                
                $schema_code = json_encode([
                    "@context" => "https://schema.org",
                    "@type" => "Movie",
                    "name" => $seo_m['title'],
                    "description" => strip_tags($seo_desc),
                    "image" => $seo_img,
                    "dateCreated" => $seo_m['created_at']
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $stmt->close();
        }
    }
} 
// 2. فحص إذا كان الرابط لمسلسل
elseif (strpos($clean_path, '/details/series/') !== false) {
    $parts = explode('/', trim($clean_path, '/'));
    if (isset($parts[2])) {
        $series_id = intval(substr($parts[2], 1));
        
        $stmt = $conn->prepare("SELECT title, description, poster FROM series WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $series_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($seo_s = $res->fetch_assoc()) {
                $seo_title = "مسلسل " . htmlspecialchars($seo_s['title'], ENT_QUOTES, 'UTF-8') . " - عرب فليكس";
                $seo_desc = mb_substr(strip_tags($seo_s['description']), 0, 160) . "...";
                $seo_img = (strpos($seo_s['poster'], 'http') === 0) ? $seo_s['poster'] : $base_url . "/uploads/" . $seo_s['poster'];
                
                $schema_code = json_encode([
                    "@context" => "https://schema.org",
                    "@type" => "TVSeries",
                    "name" => $seo_s['title'],
                    "description" => strip_tags($seo_desc),
                    "image" => $seo_img
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $stmt->close();
        }
    }
}
?>

<title><?php echo $seo_title; ?></title>
<meta name="description" content="<?php echo htmlspecialchars($seo_desc, ENT_QUOTES, 'UTF-8'); ?>">
<link rel="canonical" href="<?php echo htmlspecialchars($canonical_url, ENT_QUOTES, 'UTF-8'); ?>">

<!-- Open Graph / Facebook -->
<meta property="og:title" content="<?php echo $seo_title; ?>">
<meta property="og:description" content="<?php echo htmlspecialchars($seo_desc, ENT_QUOTES, 'UTF-8'); ?>">
<meta property="og:image" content="<?php echo htmlspecialchars($seo_img, ENT_QUOTES, 'UTF-8'); ?>">
<meta property="og:url" content="<?php echo htmlspecialchars($canonical_url, ENT_QUOTES, 'UTF-8'); ?>">
<meta property="og:type" content="website">

<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo $seo_title; ?>">
<meta name="twitter:description" content="<?php echo htmlspecialchars($seo_desc, ENT_QUOTES, 'UTF-8'); ?>">
<meta name="twitter:image" content="<?php echo htmlspecialchars($seo_img, ENT_QUOTES, 'UTF-8'); ?>">

<!-- Schema.org Data -->
<?php if (!empty($schema_code)): ?>
<script type="application/ld+json">
<?php echo $schema_code; ?>
</script>
<?php endif; ?>