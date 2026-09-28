<?php
require_once 'admin/db_config.php';
$conn->set_charset("utf8mb4");

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
$base_url = $protocol . $_SERVER['HTTP_HOST'];

function getSEOPreview($path, $conn) {
    $title = "عرب فليكس - Arab Fleex | مشاهدة أحدث الأفلام والمسلسلات";
    $desc = "استمتع بمشاهدة أحدث الأفلام العربية والأجنبية، والمسلسلات الحصرية بجودة عالية على عرب فليكس.";

    if (strpos($path, '/details/movies/') !== false) {
        $parts = explode('/', trim($path, '/'));
        $id = intval(substr($parts[2], 1));
        $res = $conn->query("SELECT title, description FROM movies WHERE id = $id LIMIT 1");
        if ($res && $row = $res->fetch_assoc()) {
            $title = "مشاهدة فيلم " . $row['title'] . " - عرب فليكس";
            $desc = "مشاهدة وتحميل " . $row['title'] . " بجودة عالية. " . mb_substr(strip_tags($row['description']), 0, 150) . "...";
        }
    }

    return ['title' => $title, 'desc' => $desc];
}

$test_routes = [
    'الرئيسية' => '/',
    'قسم الأفلام' => '/all-movies/1',
];

$first_movie = $conn->query("SELECT id, title FROM movies LIMIT 1")->fetch_assoc();
if ($first_movie) {
    $test_routes['صفحة فيلم: ' . $first_movie['title']] = '/details/movies/m' . $first_movie['id'] . '/slug';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>فاحص الـ SEO - عرب فليكس</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Cairo', sans-serif; background: #1a1a1a; color: white; }</style>
</head>
<body class="p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold mb-8 text-amber-500 border-b pb-4">تقرير معاينة SEO للموقع</h1>
        
        <div class="space-y-6">
            <?php foreach ($test_routes as $name => $url): 
                $seo = getSEOPreview($url, $conn);
            ?>
            <div class="bg-zinc-800 p-6 rounded-lg border border-zinc-700 shadow-xl">
                <h2 class="text-xl font-bold mb-4 text-blue-400"><?php echo htmlspecialchars($name); ?> <span class="text-sm font-normal text-zinc-500">(<?php echo htmlspecialchars($url); ?>)</span></h2>
                
                <div class="space-y-3">
                    <div>
                        <span class="text-zinc-400 block text-sm">العنوان (Meta Title):</span>
                        <div class="text-green-400 font-bold"><?php echo htmlspecialchars($seo['title']); ?></div>
                    </div>
                    <div>
                        <span class="text-zinc-400 block text-sm">الوصف (Meta Description):</span>
                        <div class="text-white bg-black/30 p-2 rounded mt-1 border-r-4 border-amber-500"><?php echo htmlspecialchars($seo['desc']); ?></div>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-zinc-700">
                    <span class="text-zinc-500 text-xs">شكل النتيجة في جوجل:</span>
                    <div class="bg-white text-black p-4 rounded mt-2 shadow-inner">
                        <div class="text-[#1a0dab] text-xl hover:underline cursor-pointer truncate"><?php echo htmlspecialchars($seo['title']); ?></div>
                        <div class="text-[#006621] text-sm truncate"><?php echo $base_url . htmlspecialchars($url); ?></div>
                        <div class="text-[#545454] text-sm mt-1 line-clamp-2"><?php echo htmlspecialchars($seo['desc']); ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>