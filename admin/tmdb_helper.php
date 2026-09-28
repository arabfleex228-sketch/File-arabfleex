<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$api_key = '084faf5aeaa8b2f5fb6a9c237101e491'; 

function fetch_tmdb_data($url) {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => ["accept: application/json"],
    ]);
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    if ($err) return null;
    return json_decode($response, true);
}

function clean_sql($conn, $string) {
    if (isset($conn) && $conn) return $conn->real_escape_string($string);
    return addslashes($string);
}

$search_results = null;
$item_details = null;
$final_sql_query = '';

if (isset($_GET['query']) && !empty($_GET['query'])) {
    $query = urlencode($_GET['query']);
    $type = $_GET['type']; 
    $search_url = "https://api.themoviedb.org/3/search/{$type}?api_key={$api_key}&query={$query}&language=ar-EG";
    $initial_search = fetch_tmdb_data($search_url);

    if ($type == 'tv' && $initial_search && !empty($initial_search['results'])) {
        $expanded_results = [];
        $top_shows = array_slice($initial_search['results'], 0, 3);
        
        foreach ($top_shows as $show) {
            $show['is_main'] = true;
            $expanded_results[] = $show;
            
            $show_details = fetch_tmdb_data("https://api.themoviedb.org/3/tv/{$show['id']}?api_key={$api_key}&language=ar-EG");
            
            if ($show_details && isset($show_details['seasons']) && count($show_details['seasons']) > 0) {
                foreach ($show_details['seasons'] as $season) {
                    if ($season['season_number'] == 0) continue; 
                    
                    $season_item = $show;
                    $season_item['is_main'] = false; 
                    $season_item['name'] = $show['name'] . ' (' . $season['name'] . ')'; 
                    $season_item['original_name'] = ($show['original_name'] ?? '') . ' S' . $season['season_number'];
                    $season_item['first_air_date'] = $season['air_date'] ?? $show['first_air_date'];
                    $season_item['poster_path'] = $season['poster_path'] ?? $show['poster_path'];
                    $season_item['overview'] = !empty($season['overview']) ? $season['overview'] : $show['overview'];
                    $season_item['season_number'] = $season['season_number']; 
                    
                    $expanded_results[] = $season_item;
                }
            }
        }
        $search_results = ['results' => $expanded_results];
    } else {
        $search_results = $initial_search; 
    }
}

if (isset($_GET['fetch_id']) && !empty($_GET['fetch_id'])) {
    $id = $_GET['fetch_id'];
    $type = $_GET['type']; 
    $season_number = $_GET['season'] ?? null; 

    // تمت إضافة include_video_language=ar,en,null لضمان جلب الفيديوهات بأي لغة كانت
    $details_url = "https://api.themoviedb.org/3/{$type}/{$id}?api_key={$api_key}&language=ar-EG&append_to_response=videos,credits&include_video_language=ar,en,null";
    $details = fetch_tmdb_data($details_url);

    if ($details) {
        $item_details = [];
        $poster_base_url = "https://image.tmdb.org/t/p/w500";
        $trailer_link = '';

        if (isset($details['videos']['results'])) {
            $trailer = null;
            foreach($details['videos']['results'] as $v) {
                if($v['type'] == 'Trailer' && $v['site'] == 'YouTube') { $trailer = $v; break; }
            }
            if (!$trailer) {
                foreach($details['videos']['results'] as $v) {
                    if($v['site'] == 'YouTube') { $trailer = $v; break; }
                }
            }
            if($trailer) $trailer_link = "https://www.youtube.com/watch?v=" . $trailer['key'];
        }

        $cast_data = [];
        if (isset($details['credits']['cast'])) {
            $count = 0;
            foreach ($details['credits']['cast'] as $actor) {
                if ($count >= 8) break; 
                if (!empty($actor['profile_path'])) { 
                    $cast_data[] = [
                        'name' => $actor['name'],
                        'character' => $actor['character'],
                        'image' => "https://image.tmdb.org/t/p/w185" . $actor['profile_path']
                    ];
                    $count++;
                }
            }
        }
        $cast_json = !empty($cast_data) ? json_encode($cast_data, JSON_UNESCAPED_UNICODE) : '';

        $genres = [];
        if (isset($details['genres'])) {
            foreach ($details['genres'] as $genre) $genres[] = $genre['name'];
        }
        $item_details['genre'] = implode('، ', $genres);
        $item_details['cast'] = $cast_data;
        $item_details['rating'] = round($details['vote_average'], 1);
        $item_details['trailer'] = $trailer_link;

        if ($type == 'movie') {
            $item_details['title'] = $details['title'];
            $item_details['description'] = $details['overview'];
            $item_details['poster'] = $poster_base_url . $details['poster_path'];
            $item_details['year'] = substr($details['release_date'] ?? '', 0, 4);
            $item_details['quality'] = '1080p';
        } elseif ($type == 'tv') {
            $item_details['title'] = $details['name'];
            $item_details['description'] = $details['overview'];
            $item_details['poster'] = $poster_base_url . $details['poster_path'];
            $item_details['year'] = substr($details['first_air_date'] ?? '', 0, 4);

            if ($season_number !== null) {
                // تمت إضافة include_video_language هنا أيضاً للموسم
                $season_details = fetch_tmdb_data("https://api.themoviedb.org/3/tv/{$id}/season/{$season_number}?api_key={$api_key}&language=ar-EG&append_to_response=videos&include_video_language=ar,en,null");
                
                if ($season_details) {
                    $item_details['title'] = $details['name'] . ' (' . $season_details['name'] . ')';
                    if (!empty($season_details['overview'])) $item_details['description'] = $season_details['overview'];
                    if (!empty($season_details['poster_path'])) $item_details['poster'] = $poster_base_url . $season_details['poster_path'];
                    if (!empty($season_details['air_date'])) $item_details['year'] = substr($season_details['air_date'], 0, 4);
                    
                    if (isset($season_details['videos']['results']) && count($season_details['videos']['results']) > 0) {
                        $season_trailer = null;
                        foreach($season_details['videos']['results'] as $v) {
                            if($v['type'] == 'Trailer' && $v['site'] == 'YouTube') { $season_trailer = $v; break; }
                        }
                        if (!$season_trailer) {
                            foreach($season_details['videos']['results'] as $v) {
                                if($v['site'] == 'YouTube') { $season_trailer = $v; break; }
                            }
                        }
                        if ($season_trailer) {
                            $item_details['trailer'] = "https://www.youtube.com/watch?v=" . $season_trailer['key'];
                        }
                    }
                }
            }
        }

        @include_once 'db_config.php';
        
        $title = clean_sql($conn ?? null, $item_details['title']);
        $description = clean_sql($conn ?? null, $item_details['description']);
        $poster = clean_sql($conn ?? null, $item_details['poster']);
        $year = clean_sql($conn ?? null, $item_details['year']);
        $genre = clean_sql($conn ?? null, $item_details['genre']);
        $trailer = clean_sql($conn ?? null, $item_details['trailer']);
        $cast_db = clean_sql($conn ?? null, $cast_json);
        $rating = floatval($item_details['rating']);
        
        if($type == 'movie') {
            $quality = clean_sql($conn ?? null, $item_details['quality']);
            $final_sql_query = "INSERT INTO `movies` (title, description, cast_data, poster, year, genre, rating, quality, is_recent, watch_link, trailer_link) VALUES ('{$title}', '{$description}', '{$cast_db}', '{$poster}', '{$year}', '{$genre}', {$rating}, '{$quality}', 1, '#', '{$trailer}');";
        } else {
            $final_sql_query = "INSERT INTO `series` (title, description, cast_data, poster, year, genre, rating, is_recent, ramadan_year, trailer_link) VALUES ('{$title}', '{$description}', '{$cast_db}', '{$poster}', '{$year}', '{$genre}', {$rating}, 1, 0, '{$trailer}');";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>مساعد جلب البيانات من TMDb</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; background-color: #f4f4f4; }
        .container { max-width: 900px; margin: 2rem auto; padding: 2rem; background-color: #fff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        input[type="text"], select { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 8px; }
        button { background-color: #3498db; color: white; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.3s;}
        button:hover { background-color: #2980b9; }
        .result-list a { display: block; padding: 1rem; border: 1px solid #eee; border-radius: 8px; margin-top: 0.75rem; text-decoration: none; color: #333; transition: 0.2s; }
        .result-list a:hover { background-color: #f8fafc; border-color: #cbd5e1; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .details-grid { display: grid; grid-template-columns: 200px 1fr; gap: 1.5rem; margin-top: 2rem; }
        textarea { width: 100%; height: 150px; padding: 0.5rem; border: 1px solid #ddd; border-radius: 8px; direction: ltr; text-align: left; }
        .badge-main { background-color: #e0e7ff; color: #1e40af; padding: 0.1rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: bold; border: 1px solid #c7d2fe; }
        .badge-season { background-color: #fef3c7; color: #92400e; padding: 0.1rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: bold; border: 1px solid #fde68a; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-3xl font-bold mb-6 text-center text-blue-800">مساعد جلب البيانات من TMDb</h1>
        <form method="GET" action="tmdb_helper.php" class="mb-8 bg-gray-50 p-6 rounded-lg border border-gray-200">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block mb-2 font-bold text-gray-700">اسم الفيلم أو المسلسل:</label>
                    <input type="text" name="query" placeholder="مثال: أبو العروسة" value="<?php echo isset($_GET['query']) ? htmlspecialchars($_GET['query']) : ''; ?>" required>
                </div>
                <div>
                    <label class="block mb-2 font-bold text-gray-700">النوع:</label>
                    <select name="type">
                        <option value="tv" <?php echo (isset($_GET['type']) && $_GET['type'] == 'tv') ? 'selected' : ''; ?>>مسلسل</option>
                        <option value="movie" <?php echo (isset($_GET['type']) && $_GET['type'] == 'movie') ? 'selected' : ''; ?>>فيلم</option>
                    </select>
                </div>
                <div class="self-end"><button type="submit" class="w-full">🔍 ابحث الآن</button></div>
            </div>
        </form>

        <?php if ($search_results && isset($search_results['results'])): ?>
            <div class="result-list">
                <h2 class="text-2xl font-bold mb-4 border-b pb-2">نتائج البحث (<?php echo count($search_results['results']); ?> نتيجة):</h2>
                <?php if (count($search_results['results']) == 0): ?>
                    <p class="text-red-500 font-bold">لم يتم العثور على نتائج. جرب كتابة الاسم بشكل مختلف.</p>
                <?php endif; ?>
                
                <?php foreach ($search_results['results'] as $item): ?>
                    <?php
                        $type = $_GET['type']; $id = $item['id'];
                        $title = ($type == 'movie') ? $item['title'] : $item['name'];
                        $original_title = ($type == 'movie') ? ($item['original_title'] ?? '') : ($item['original_name'] ?? '');
                        $year = ($type == 'movie') ? (isset($item['release_date']) ? substr($item['release_date'], 0, 4) : 'N/A') : (isset($item['first_air_date']) ? substr($item['first_air_date'], 0, 4) : 'N/A');
                        $poster_path = $item['poster_path'] ? "https://image.tmdb.org/t/p/w92" . $item['poster_path'] : 'https://placehold.co/92x138?text=No+Image';
                        $overview = isset($item['overview']) && !empty($item['overview']) ? mb_substr($item['overview'], 0, 150, 'UTF-8') . '...' : 'لا توجد قصة متاحة لهذا العمل.';
                        
                        $season_param = (isset($item['season_number']) && isset($item['is_main']) && !$item['is_main']) ? "&season={$item['season_number']}" : "";
                        $is_main_badge = (isset($item['is_main']) && $item['is_main']) ? '<span class="badge-main mr-2">المسلسل كامل</span>' : ((isset($item['is_main'])) ? '<span class="badge-season mr-2">موسم مخصص</span>' : '');
                    ?>
                    <a href="tmdb_helper.php?fetch_id=<?php echo $id; ?>&type=<?php echo $type; ?><?php echo $season_param; ?>">
                        <div class="flex items-start gap-4 <?php echo (isset($item['is_main']) && !$item['is_main']) ? 'ml-8 border-r-4 border-r-blue-400 pr-4' : ''; ?>">
                            <img src="<?php echo $poster_path; ?>" class="rounded min-w-[92px] shadow">
                            <div class="flex-1">
                                <h3 class="text-xl font-bold text-blue-700 mb-1 flex items-center flex-wrap">
                                    <?php echo $title; ?> 
                                    <?php echo $is_main_badge; ?>
                                </h3>
                                <?php if($original_title && $original_title != $title): ?>
                                    <span class="text-sm text-gray-400 font-normal ltr inline-block" dir="ltr">(<?php echo $original_title; ?>)</span>
                                <?php endif; ?>
                                <div class="flex gap-3 mb-2 mt-2 text-sm font-bold">
                                    <span class="bg-gray-200 text-gray-800 px-2 py-1 rounded">السنة: <?php echo $year; ?></span>
                                    <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded">التقييم: ⭐ <?php echo round($item['vote_average'], 1); ?></span>
                                </div>
                                <p class="text-gray-600 text-sm leading-relaxed"><?php echo htmlspecialchars($overview); ?></p>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($item_details): ?>
            <div class="bg-green-50 border border-green-200 p-4 rounded-lg mb-6">
                <h2 class="text-2xl font-bold text-green-700">✅ تم جلب البيانات بنجاح!</h2>
            </div>
            <div class="details-grid bg-white border border-gray-200 p-6 rounded-lg">
                <div><img src="<?php echo $item_details['poster']; ?>" alt="Poster" class="rounded shadow-lg w-full"></div>
                <div>
                    <h3 class="text-3xl font-bold text-gray-800 mb-3"><?php echo $item_details['title']; ?></h3>
                    <div class="flex gap-4 mb-4">
                        <p class="text-gray-700 bg-gray-100 px-3 py-1 rounded font-bold">📅 السنة: <?php echo $item_details['year']; ?></p>
                        <p class="text-gray-700 bg-gray-100 px-3 py-1 rounded font-bold">🎬 التريلر: <?php echo $item_details['trailer'] ? "✅ متوفر" : "❌ غير متوفر"; ?></p>
                    </div>
                    <p class="text-gray-700 mb-4 bg-blue-50 p-4 rounded-lg border border-blue-100 leading-relaxed"><strong>الوصف:</strong><br> <?php echo $item_details['description']; ?></p>
                    <?php if(!empty($item_details['cast'])): ?>
                    <div class="mt-4">
                        <strong class="block mb-3 text-lg border-b pb-2">أبرز الممثلين:</strong>
                        <div class="flex gap-2 flex-wrap">
                            <?php foreach($item_details['cast'] as $actor): ?>
                                <span class="bg-indigo-50 text-indigo-700 text-sm font-bold px-3 py-1.5 rounded-full border border-indigo-200 flex items-center gap-2">
                                    <img src="<?php echo $actor['image']; ?>" class="w-6 h-6 rounded-full object-cover">
                                    <?php echo $actor['name']; ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="mt-8 bg-gray-800 p-6 rounded-lg text-white">
                <h3 class="text-xl font-bold mb-3 text-yellow-400"><i class="fas fa-code"></i> كود SQL جاهز للنسخ:</h3>
                <textarea readonly onclick="this.select()" class="bg-gray-900 border-gray-700 text-green-400 font-mono text-left w-full h-32 p-4 rounded outline-none focus:border-yellow-400 transition"><?php echo $final_sql_query; ?></textarea>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>