<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سحب VK (رفع + مشاهدة)</title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; text-align: center; margin-top: 30px; background: #f4f7fa; color: #333;}
        .container { background: white; width: 90%; max-width: 800px; margin: auto; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        input[type="text"] { width: 80%; padding: 12px; margin-bottom: 15px; border: 2px solid #ccc; border-radius: 8px; font-size: 16px; outline: none;}
        input[type="text"]:focus { border-color: #0077FF; }
        button { padding: 12px 25px; background: #0077FF; color: white; border: none; cursor: pointer; font-size: 16px; border-radius: 8px; font-weight: bold; transition: 0.3s;}
        button:hover { background: #005ce6; }
        .result { margin-top: 20px; padding: 20px; background: #e7f3fe; border: 1px solid #cce5ff; text-align: left; direction: ltr; border-radius: 8px;}
        .link-box { width: 100%; height: 50px; padding: 10px; border: 1px solid #ccc; border-radius: 5px; margin-bottom: 10px; box-sizing: border-box; font-family: monospace; direction: ltr; text-align: left; font-size: 12px;}
        .iframe-box { height: 80px; border-color: #ff0000; }
        .quality-label { display: inline-block; background: #0077FF; color: white; padding: 4px 8px; border-radius: 4px; font-size: 13px; margin-bottom: 5px; font-weight: bold;}
        .info-box { background: #e2e3e5; color: #383d41; padding: 10px; border-radius: 5px; text-align: right; direction: rtl; margin-bottom: 15px; font-size: 13px; border: 1px solid #d6d8db;}
        iframe { width: 100%; height: 400px; border: none; border-radius: 8px; margin-top: 15px; background: #000; }
    </style>
</head>
<body>

<div class="container">
    <h2 style="color: #0077FF;">سحب VK المطور 🚀</h2>
    <form method="POST">
        <input type="text" name="url" placeholder="ضع رابط فيديو VK هنا..." required>
        <br>
        <button type="submit">استخراج الروابط والمشغل</button>
    </form>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['url'])) {
        $url = trim($_POST['url']);
        
        if (preg_match('/video(-?\d+)_(\d+)/', $url, $matches)) {
            $oid = $matches[1];
            $vid = $matches[2];
            
            $al_video_url = "https://vk.com/al_video.php?act=show";
            $post_fields = http_build_query([
                'act' => 'show',
                'al' => '1',
                'module' => 'videoplayer',
                'video' => "{$oid}_{$vid}"
            ]);

            // cURL خفيف جداً
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $al_video_url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $post_fields,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_ENCODING => "", 
                CURLOPT_HTTPHEADER => [
                    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)",
                    "Content-Type: application/x-www-form-urlencoded",
                    "X-Requested-With: XMLHttpRequest",
                    "Referer: " . $url
                ],
            ]);

            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);

            if ($err) {
                echo "<div class='result' style='color:red; text-align:right; direction:rtl;'>خطأ في الاتصال بالخادم: $err</div>";
            } else {
                $response = mb_convert_encoding($response, 'UTF-8', 'Windows-1251');
                $clean_data = str_replace('\\/', '/', $response);

                $mp4_links = [];
                $embed_hash = '';

                // استخراج الهاش السري لإنشاء كود Iframe يعمل 100%
                if (preg_match('/"hash2"\s*:\s*"([a-zA-Z0-9_]+)"/i', $response, $hash_match) || preg_match('/"hash"\s*:\s*"([a-zA-Z0-9_]+)"/i', $response, $hash_match)) {
                    $embed_hash = $hash_match[1];
                }

                // استخراج MP4 للرفع التلقائي
                if (preg_match_all('/"url(144|240|360|480|720|1080|1440|2160)"\s*:\s*"([^"]+)"/i', $response, $matches)) {
                    for ($i = 0; $i < count($matches[1]); $i++) {
                        $quality = $matches[1][$i];
                        $link = str_replace('\\/', '/', $matches[2][$i]);
                        if (filter_var($link, FILTER_VALIDATE_URL)) {
                            $mp4_links[$quality] = $link;
                        }
                    }
                }

                if (!empty($mp4_links) || $embed_hash) {
                    echo "<div class='result' style='text-align:center;'>";
                    
                    // 1. عرض المشغل الرسمي (للمشاهدة في الموقع)
                    if ($embed_hash) {
                        $iframe_src = "https://vk.com/video_ext.php?oid={$oid}&id={$vid}&hash={$embed_hash}&hd=2";
                        $iframe_code = "<iframe src=\"{$iframe_src}\" width=\"100%\" height=\"400\" allow=\"autoplay; encrypted-media; fullscreen; picture-in-picture;\" frameborder=\"0\" allowfullscreen></iframe>";
                        
                        echo "<h3 style='color:#FF0000; text-align:right; direction:rtl;'>▶️ كود المشغل (للمشاهدة على موقعك):</h3>";
                        echo "<div class='info-box'>هذا الكود (Iframe) مضمون 100% للعمل للمشاهدين على موقعك بدون أي مشاكل في الآي بي.</div>";
                        echo $iframe_code;
                        
                        echo "<div style='text-align:left; margin-top: 15px;'>";
                        echo "<span class='quality-label' style='background:#FF0000;'>كود Iframe للنسخ</span>";
                        echo "<textarea class='link-box iframe-box' onclick='this.select()' readonly>" . htmlspecialchars($iframe_code) . "</textarea>";
                        echo "</div>";
                    }

                    // 2. عرض روابط MP4 (للرفع)
                    krsort($mp4_links);
                    if (!empty($mp4_links)) {
                        echo "<hr style='border: 0; border-top: 1px solid #ccc; margin: 20px 0;'>";
                        echo "<h3 style='color:#0077FF; text-align:right; direction:rtl;'>⬇️ روابط MP4 (للرفع الخارجي Remote Upload):</h3>";
                        echo "<div class='info-box'>استخدم هذه الروابط <b>للرفع التلقائي</b> على سيرفرات المشاهدة (مثل Doodstream وغيرها). لا تحاول فتحها في متصفحك لأنها محمية للعمل على السيرفرات فقط.</div>";
                        foreach ($mp4_links as $q => $link) {
                            echo "<div style='text-align:left;'>";
                            echo "<span class='quality-label'>$q p</span>";
                            echo "<textarea class='link-box' onclick='this.select()' readonly>$link</textarea>";
                            echo "</div>";
                        }
                    }
                    echo "</div>";

                } else {
                     echo "<div class='result' style='color:red; text-align:right; direction:rtl;'><b>الفيديو محذوف أو الجروب خاص جداً.</b></div>";
                }
            }
        } else {
            echo "<div class='result' style='color:red; text-align:right; direction:rtl;'>رابط غير صحيح.</div>";
        }
    }
    ?>
</div>

</body>
</html>