<?php
// --- 1. إعدادات ---
require_once 'admin/db_config.php';
$conn->set_charset("utf8"); // ضروري جداً للعربي

// تحديد رابط الموقع ديناميكياً
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
$base_url = $protocol . $_SERVER['HTTP_HOST'];

// --- 2. دالة لإنشاء الـ Slug (مطابقة 100% للي في sitemap) ---
function slugify_php($text) {
    $text = trim($text ?? '');
    $text = preg_replace('/\s+/', '-', $text); 
    $text = preg_replace('/[\/\\\?%\*:\|"<>.]/', '', $text); 
    $text = preg_replace('/--+/', '-', $text); 
    return $text;
}

// --- 3. إرسال الهيدر الصحيح ---
header("Content-Type: application/atom+xml; charset=utf-8");

// --- 4. (احترافي) جلب تاريخ آخر تحديث للموقع ---
$last_mod_query = $conn->query("
    (SELECT created_at FROM movies ORDER BY created_at DESC LIMIT 1)
    UNION ALL
    (SELECT created_at FROM series ORDER BY created_at DESC LIMIT 1)
    ORDER BY created_at DESC LIMIT 1
");
$last_mod_date = $last_mod_query->fetch_assoc()['created_at'] ?? date('c');
$last_mod = date(DATE_ATOM, strtotime($last_mod_date));


// --- 5. بداية ملف الـ XML ---
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<feed xmlns="http://www.w3.org/2005/Atom">' . "\n";
echo '  <title>أحدث الإضافات - عرب فليكس</title>' . "\n";
echo '  <subtitle>آخر الأفلام والمسلسلات المضافة إلى عرب فليكس</subtitle>' . "\n";
echo '  <link href="' . htmlspecialchars($base_url) . '" />' . "\n";
echo '  <link href="' . htmlspecialchars($base_url) . '/atom.php" rel="self" />' . "\n";
echo '  <id>' . htmlspecialchars($base_url) . '/</id>' . "\n";
echo '  <updated>' . $last_mod . '</updated>' . "\n";
echo '  <author><name>عرب فليكس</name></author>' . "\n";


// --- 6. جلب آخر 20 عمل (أفلام ومسلسلات) ---
// --- (تعديل) تم حذف "البرامج" من الكود ---
$items_query = $conn->query("
    (SELECT id, title, description, created_at, 'movies' as type FROM movies ORDER BY created_at DESC LIMIT 15)
    UNION ALL
    (SELECT id, title, description, created_at, 'series' as type FROM series ORDER BY created_at DESC LIMIT 15)
    ORDER BY created_at DESC LIMIT 20
");

if ($items_query && $items_query->num_rows > 0) {
    while ($row = $items_query->fetch_assoc()) {
        
        // تجهيز الرابط الصحيح بناءً على النوع
        $type_slug = $row['type']; // 'movies' or 'series'
        $id_prefix = ($type_slug === 'movies') ? 'm' : 's';
        $slug = slugify_php($row['title']);
        $link = htmlspecialchars($base_url . "/details/" . $type_slug . "/" . $id_prefix . $row['id'] . "/" . $slug);
        
        // تجهيز التاريخ والوصف
        $updated = date(DATE_ATOM, strtotime($row['created_at']));
        $summary = htmlspecialchars(mb_substr($row['description'], 0, 250) . '...'); // وصف مختصر

        echo "  <entry>\n";
        echo "    <title>" . htmlspecialchars($row['title']) . "</title>\n";
        echo "    <link href=\"$link\" />\n";
        echo "    <id>$link</id>\n";
        echo "    <updated>$updated</updated>\n";
        echo "    <summary>$summary</summary>\n";
        echo "  </entry>\n";
    }
}

$conn->close();

// --- 7. إغلاق ملف الـ XML ---
echo '</feed>' . "\n";
?>