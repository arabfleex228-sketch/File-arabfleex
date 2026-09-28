<?php
require_once 'admin/db_config.php';

// تم تثبيت الدومين الجديد هنا ليكون الأساسي لمحركات البحث
$base_url = "https://arabfleex.live";

function slugify_php($text) {
    $text = trim($text ?? '');
    $text = preg_replace('/\s+/', '-', $text);
    $text = preg_replace('/[\/\\\?%\*:\|"<>.]/', '', $text);
    $text = preg_replace('/--+/', '-', $text);
    return rawurlencode($text);
}

header('Content-Type: application/xml; charset=utf-8');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

$last_mod_query = $conn->query("
    (SELECT created_at FROM movies ORDER BY created_at DESC LIMIT 1)
    UNION ALL
    (SELECT created_at FROM series ORDER BY created_at DESC LIMIT 1)
    ORDER BY created_at DESC LIMIT 1
");
$row_date = $last_mod_query ? $last_mod_query->fetch_assoc() : null;
$last_mod_date = $row_date['created_at'] ?? date('c');
$last_mod = date('c', strtotime($last_mod_date));

// الرئيسية
echo '  <url>' . "\n";
echo '    <loc>' . $base_url . '/</loc>' . "\n";
echo '    <lastmod>' . $last_mod . '</lastmod>' . "\n";
echo '    <priority>1.0</priority>' . "\n";
echo '    <changefreq>daily</changefreq>' . "\n";
echo '  </url>' . "\n";

// الأفلام
$movies = $conn->query("SELECT id, title, created_at FROM movies ORDER BY created_at DESC");
if ($movies && $movies->num_rows > 0) {
    while ($row = $movies->fetch_assoc()) {
        $slug = slugify_php($row['title']);
        $url = $base_url . '/details/movies/m' . $row['id'] . '/' . $slug;
        $lastmod = date('c', strtotime($row['created_at'] ?? 'now'));

        echo '  <url>' . "\n";
        echo '    <loc>' . $url . '</loc>' . "\n";
        echo '    <lastmod>' . $lastmod . '</lastmod>' . "\n";
        echo '    <priority>0.8</priority>' . "\n";
        echo '  </url>' . "\n";
    }
}

// المسلسلات
$series = $conn->query("SELECT id, title, created_at FROM series ORDER BY created_at DESC");
if ($series && $series->num_rows > 0) {
    while ($row = $series->fetch_assoc()) {
        $slug = slugify_php($row['title']);
        $url = $base_url . '/details/series/s' . $row['id'] . '/' . $slug;
        $lastmod = date('c', strtotime($row['created_at'] ?? 'now'));

        echo '  <url>' . "\n";
        echo '    <loc>' . $url . '</loc>' . "\n";
        echo '    <lastmod>' . $lastmod . '</lastmod>' . "\n";
        echo '    <priority>0.8</priority>' . "\n";
        echo '  </url>' . "\n";
    }
}

$conn->close();
echo '</urlset>';
?>