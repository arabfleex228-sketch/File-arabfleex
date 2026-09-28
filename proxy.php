<?php
// إخفاء الأخطاء
error_reporting(0);

// استلام الرابط المطلوب تخطي حظره
$url = isset($_GET['url']) ? urldecode($_GET['url']) : '';

if(empty($url)) {
    die('<div style="text-align:center; padding:20px; color:white; background:black;">لم يتم تحديد رابط.</div>');
}

// زيادة أمان: التأكد أننا نستخدم البروكسي فقط مع المواقع التي تحتاج ذلك (يمكنك إضافة مواقع أخرى هنا مستقبلاً)
if(strpos($url, 'sh.ramadan-series.site') === false) {
    // إذا لم يكن من الموقع المحظور، قم بتوجيه الزائر للرابط مباشرة
    header("Location: " . $url);
    exit;
}

// بدء الاتصال السري لجلب المشغل
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

// الخدعة الرئيسية: إيهام الموقع أن الزيارة قادمة من داخله
$parsed_url = parse_url($url);
$referer = $parsed_url['scheme'] . '://' . $parsed_url['host'] . '/';
curl_setopt($ch, CURLOPT_REFERER, $referer);

// استخدام متصفح الزائر لعدم كشفنا
curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);

$html_content = curl_exec($ch);
curl_close($ch);

// إصلاح الروابط الداخلية للمشغل لتعمل داخل موقعك
$html_content = preg_replace('/href="\//', 'href="' . $referer, $html_content);
$html_content = preg_replace('/src="\//', 'src="' . $referer, $html_content);

// طباعة المشغل
echo $html_content;
?>