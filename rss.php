<?php
require_once 'admin/db_config.php';
$conn->set_charset("utf8mb4");

// تم تثبيت الدومين الجديد هنا ليكون الأساسي لمحركات البحث
$base_url = "https://arabfleex.live";

function slugify_php($text) {
    $text = trim($text ?? '');
    $text = preg_replace('/\s+/', '-', $text); 
    $text = preg_replace('/[\/\\\?%\*:\|"<>.]/', '', $text); 
    $text = preg_replace('/--+/', '-', $text); 
    return rawurlencode($text);
}

header("Content-Type: application/rss+xml; charset=utf-8");

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
echo '  <channel>' . "\n";
echo '    <title>أحدث الإضافات - عرب فليكس</title>' . "\n";
echo '    <link>' . htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8') . '</link>' . "\n";
echo '    <description>آخر الأفلام والمسلسلات المضافة إلى عرب فليكس</description>' . "\n";
echo '    <language>ar</language>' . "\n";
echo '    <atom:link href="' . htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8') . '/rss.php" rel="self" type="application/rss+xml" />' . "\n";

$items_query = $conn->query("
    (SELECT id, title, description, created_at, 'movies' as type FROM movies ORDER BY created_at DESC LIMIT 15)
    UNION ALL
    (SELECT id, title, description, created_at, 'series' as type FROM series ORDER BY created_at DESC LIMIT 15)
    ORDER BY created_at DESC LIMIT 20
");

if ($items_query && $items_query->num_rows > 0) {
    while ($row = $items_query->fetch_assoc()) {
        $type_slug = $row['type'];
        $id_prefix = ($type_slug === 'movies') ? 'm' : 's';
        $slug = slugify_php($row['title']);
        $link = $base_url . "/details/" . $type_slug . "/" . $id_prefix . $row['id'] . "/" . $slug;
        
        $pubDate = date(DATE_RSS, strtotime($row['created_at']));
        $clean_desc = mb_substr(strip_tags($row['description']), 0, 250);

        echo "    <item>\n";
        echo "      <title><![CDATA[" . $row['title'] . "]]></title>\n";
        echo "      <link>$link</link>\n";
        echo "      <guid isPermaLink=\"true\">$link</guid>\n";
        echo "      <pubDate>$pubDate</pubDate>\n";
        echo "      <description><![CDATA[" . $clean_desc . "...]]></description>\n";
        echo "    </item>\n";
    }
}

$conn->close();
echo '  </channel>' . "\n";
echo '</rss>' . "\n";
?>