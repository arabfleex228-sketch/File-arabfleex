<?php
// --- معالجة طلبات الأجاكس للتعديل السريع (Toggle) ---
if (isset($_GET['ajax_action']) && $_GET['ajax_action'] == 'toggle') {
    header('Content-Type: application/json');
    $id = intval($_POST['id']);
    $col = $_POST['column'];
    $allowed_cols = ['is_recent', 'is_published'];
    
    if (in_array($col, $allowed_cols)) {
        $conn->query("UPDATE movies SET $col = NOT $col WHERE id = $id");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

// --- تحديث قاعدة البيانات تلقائياً ---
$check_release_m = $conn->query("SHOW COLUMNS FROM movies LIKE 'release_date'");
if ($check_release_m && $check_release_m->num_rows == 0) {
    $conn->query("ALTER TABLE movies ADD COLUMN release_date DATE DEFAULT NULL AFTER year");
}

$check_w2 = $conn->query("SHOW COLUMNS FROM movies LIKE 'watch_link_2'");
if ($check_w2 && $check_w2->num_rows == 0) {
    $conn->query("ALTER TABLE movies ADD COLUMN watch_link_2 VARCHAR(255) DEFAULT NULL AFTER watch_link");
    $conn->query("ALTER TABLE movies ADD COLUMN watch_link_3 VARCHAR(255) DEFAULT NULL AFTER watch_link_2");
    $conn->query("ALTER TABLE movies ADD COLUMN watch_link_4 VARCHAR(255) DEFAULT NULL AFTER watch_link_3");
    $conn->query("ALTER TABLE movies ADD COLUMN download_link VARCHAR(255) DEFAULT NULL AFTER watch_link_4");
    $conn->query("ALTER TABLE movies ADD COLUMN download_link_2 VARCHAR(255) DEFAULT NULL AFTER download_link");
}

$check_col = $conn->query("SHOW COLUMNS FROM movies LIKE 'is_published'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE movies ADD COLUMN is_published TINYINT(1) DEFAULT 1 AFTER is_recent");
}

$check_cast = $conn->query("SHOW COLUMNS FROM movies LIKE 'cast_data'");
if ($check_cast && $check_cast->num_rows == 0) {
    $conn->query("ALTER TABLE movies ADD COLUMN cast_data LONGTEXT DEFAULT NULL AFTER is_published");
}

$check_cat_m = $conn->query("SHOW COLUMNS FROM movies LIKE 'category'");
if ($check_cat_m && $check_cat_m->num_rows == 0) {
    $conn->query("ALTER TABLE movies ADD COLUMN category VARCHAR(50) DEFAULT 'movie' AFTER cast_data");
}

// إضافة عمود المشاهدات للأفلام لتشغيل الإحصائيات (تحديث جديد)
$check_views_m = $conn->query("SHOW COLUMNS FROM movies LIKE 'views'");
if ($check_views_m && $check_views_m->num_rows == 0) {
    $conn->query("ALTER TABLE movies ADD COLUMN views INT DEFAULT 0 AFTER category");
}

// تحديد تصنيف الأفلام الحالي
$allowed_movie_categories = ['movie', 'foreign_movie', 'indian_movie'];
$current_category = (isset($_GET['category']) && in_array($_GET['category'], $allowed_movie_categories)) ? $_GET['category'] : 'movie';

if ($current_category == 'foreign_movie') { 
    $page_title = 'إدارة الأفلام الأجنبية'; 
    $add_btn_text = 'إضافة فيلم أجنبي'; 
} elseif ($current_category == 'indian_movie') { 
    $page_title = 'إدارة الأفلام الهندية'; 
    $add_btn_text = 'إضافة فيلم هندي'; 
} else { 
    $page_title = 'إدارة الأفلام العربية'; 
    $add_btn_text = 'إضافة فيلم عربي'; 
}

$tmdb_api_key = '084faf5aeaa8b2f5fb6a9c237101e491';
?>
<script src="https://cdn.plyr.io/3.7.8/plyr.js"></script>
<link rel="stylesheet" href="https://cdn.plyr.io/3.7.8/plyr.css" />
<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .player-preview-error { display: none; background-color: rgba(220, 38, 38, 0.85); color: white; padding: 1rem; border-radius: 0.5rem; font-weight: 700; border: 2px solid #fff; text-align: center; margin-bottom: 10px; }
    .search-container { margin-bottom: 1.5rem; display: flex; gap: 1rem; flex-wrap: wrap; }
    .table-search-input { flex: 1; min-width: 250px; background: #1a0b2e; border: 1px solid #3b82f6; padding: 0.75rem 1rem; border-radius: 0.5rem; color: white; outline: none; }
    .table-filter-select { background: #1a0b2e; border: 1px solid #3b82f6; padding: 0.75rem 1rem; border-radius: 0.5rem; color: white; outline: none; cursor: pointer; min-width: 180px; }
    .toggle-switch { position: relative; display: inline-block; width: 36px; height: 20px; }
    .toggle-switch input { opacity: 0; width: 0; height: 0; }
    .toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ef4444; transition: .3s; border-radius: 20px; }
    .toggle-slider:before { position: absolute; content: ""; height: 14px; width: 14px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; }
    input:checked + .toggle-slider { background-color: #10b981; }
    input:checked + .toggle-slider:before { transform: translateX(16px); }
    /* أزرار مصدر البيانات */
    .src-btn { padding: 0.4rem 1rem; border-radius: 8px; font-weight: 700; font-size: 0.85rem; cursor: pointer; border: 2px solid #334155; background: #0f172a; color: #94a3b8; transition: all 0.2s; font-family: inherit; }
    .src-btn:hover { border-color: #6366f1; color: #a5b4fc; }
    .src-btn.src-active-tmdb { border-color: #3b82f6; background: rgba(59,130,246,0.15); color: #60a5fa; }
    .src-btn.src-active-ec { border-color: #f59e0b; background: rgba(245,158,11,0.15); color: #fbbf24; }
</style>
<?php
function handle_upload($file) {
    if ($file['error'] !== UPLOAD_ERR_OK) return [false, "خطأ في الرفع."];
    $target_dir = "../uploads/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
    $file_name = time() . '_movie_' . basename($file["name"]);
    $target_file = $target_dir . $file_name;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    if(getimagesize($file["tmp_name"]) === false) return [false, "ليس صورة."];
    if(!in_array($imageFileType, ["jpg", "jpeg", "png", "webp"])) return [false, "امتداد غير مسموح."];
    if (move_uploaded_file($file["tmp_name"], $target_file)) return [true, "uploads/" . $file_name];
    return [false, "خطأ في الحفظ."];
}

$message = ''; $message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_movie'])) {
        $id = $_POST['movie_id'];
        $stmt = $conn->prepare("SELECT poster FROM movies WHERE id = ?");
        $stmt->bind_param("i", $id); $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        if ($result && !empty($result['poster']) && !filter_var($result['poster'], FILTER_VALIDATE_URL) && file_exists("../".$result['poster'])) {
            unlink("../".$result['poster']);
        }
        $stmt->close();
        $stmt = $conn->prepare("DELETE FROM movies WHERE id = ?");
        $stmt->bind_param("i", $id);
        if($stmt->execute()){ $message = 'تم الحذف بنجاح.'; $message_type = 'success'; }
        $stmt->close();
    }

    if (isset($_POST['save_movie'])) {
        $id = $_POST['movie_id']; 
        $title = $_POST['title']; 
        $year = $_POST['year'];
        $release_date = !empty($_POST['release_date']) ? $_POST['release_date'] : NULL;
        $genre = $_POST['genre']; 
        $description = $_POST['description']; 
        $rating = $_POST['rating'];
        $watch_link = $_POST['watch_link']; 
        $watch_link_2 = $_POST['watch_link_2'];
        $watch_link_3 = $_POST['watch_link_3'];
        $watch_link_4 = $_POST['watch_link_4'];
        $download_link = $_POST['download_link'] ?? '';
        $download_link_2 = $_POST['download_link_2'] ?? '';
        $trailer_link = $_POST['trailer_link'];
        $quality = $_POST['quality']; 
        $cast_data = $_POST['cast_data'] ?? ''; 
        $category = $_POST['category'];
        
        $is_recent = isset($_POST['is_recent']) ? 1 : 0;
        $is_published = isset($_POST['is_published']) ? 1 : 0; 
        
        $poster_path = $_POST['current_poster']; 
        $poster_url_tmdb = $_POST['poster_url_tmdb'] ?? ''; 

        if (isset($_FILES['poster']) && $_FILES['poster']['size'] > 0) {
            list($success, $new_p) = handle_upload($_FILES['poster']);
            if ($success) {
                if (!empty($poster_path) && !filter_var($poster_path, FILTER_VALIDATE_URL) && file_exists("../".$poster_path)) unlink("../".$poster_path);
                $poster_path = $new_p;
            } else { $message = $new_p; $message_type = 'error'; }
        } elseif (!empty($poster_url_tmdb)) { $poster_path = $poster_url_tmdb; }
        
        if (empty($message)) {
            if (empty($id)) { 
                $stmt = $conn->prepare("INSERT INTO movies (title, year, release_date, genre, poster, description, rating, watch_link, watch_link_2, watch_link_3, watch_link_4, download_link, download_link_2, trailer_link, quality, is_recent, is_published, cast_data, category) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sissssdssssssssiiss", $title, $year, $release_date, $genre, $poster_path, $description, $rating, $watch_link, $watch_link_2, $watch_link_3, $watch_link_4, $download_link, $download_link_2, $trailer_link, $quality, $is_recent, $is_published, $cast_data, $category);
            } else { 
                $stmt = $conn->prepare("UPDATE movies SET title=?, year=?, release_date=?, genre=?, poster=?, description=?, rating=?, watch_link=?, watch_link_2=?, watch_link_3=?, watch_link_4=?, download_link=?, download_link_2=?, trailer_link=?, quality=?, is_recent=?, is_published=?, cast_data=?, category=? WHERE id=?");
                $stmt->bind_param("sissssdssssssssiissi", $title, $year, $release_date, $genre, $poster_path, $description, $rating, $watch_link, $watch_link_2, $watch_link_3, $watch_link_4, $download_link, $download_link_2, $trailer_link, $quality, $is_recent, $is_published, $cast_data, $category, $id);
            }
            if($stmt->execute()){ 
                $message = 'تم الحفظ بنجاح.'; $message_type = 'success'; 
                $current_category = $category;
            }
            $stmt->close();
        }
    }
}

$action = $_GET['action'] ?? 'view';
$movie_data = null;
if (($action == 'edit' || $action == 'add') && isset($_GET['id'])) {
    $stmt = $conn->prepare("SELECT * FROM movies WHERE id = ?");
    $stmt->bind_param("i", $_GET['id']); $stmt->execute();
    $movie_data = $stmt->get_result()->fetch_assoc(); $stmt->close();
}
?>

<div class="section-header">
    <h1 class="section-title">
        <?php 
        if($current_category == 'foreign_movie') echo '<i class="fas fa-film text-emerald-400 ml-2"></i>'; 
        elseif($current_category == 'indian_movie') echo '<i class="fas fa-film text-orange-500 ml-2"></i>'; 
        else echo '<i class="fas fa-film text-blue-400 ml-2"></i>'; 
        ?>
        <?php echo $page_title; ?>
    </h1>
    <a href="index.php?page=movies&category=<?php echo $current_category; ?>&action=add" class="btn btn-primary"><i class="fas fa-plus"></i> <?php echo $add_btn_text; ?></a>
</div>

<?php if ($message): ?>
    <div class="mb-4 p-4 rounded-md text-center font-bold <?php echo $message_type == 'success' ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<?php if ($action == 'add' || $action == 'edit'): ?>
    
    <?php if ($action == 'edit' && $movie_data): ?>
    <!-- ========================================== -->
    <!-- إحصائيات وتفاصيل الفيلم الفردية (تظهر فقط عند التعديل) -->
    <!-- ========================================== -->
    <div class="bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl relative overflow-hidden flex flex-col md:flex-row gap-6 mb-8 mt-4">
        <div class="absolute -right-20 -top-20 w-64 h-64 bg-blue-500 opacity-5 rounded-full blur-[80px] pointer-events-none"></div>
        
        <div class="flex-shrink-0 relative z-10 w-32 md:w-40 mx-auto md:mx-0">
            <img src="<?php echo (!empty($movie_data['poster']) && filter_var($movie_data['poster'], FILTER_VALIDATE_URL)) ? $movie_data['poster'] : '../'.($movie_data['poster'] ?? ''); ?>" class="w-full h-auto object-cover rounded-xl shadow-lg border border-white/10" onerror="this.src='https://placehold.co/300x450?text=Poster'">
        </div>
        
        <div class="flex-1 w-full relative z-10 flex flex-col justify-center">
            <div class="flex flex-wrap items-center gap-3 mb-2">
                <h2 class="text-2xl font-black text-white"><?php echo htmlspecialchars($movie_data['title']); ?></h2>
                <span class="bg-blue-500/20 text-blue-400 text-xs px-2 py-1 rounded font-bold"><?php echo htmlspecialchars($movie_data['year']); ?></span>
                <?php if(isset($movie_data['is_published']) && $movie_data['is_published'] == 0): ?>
                    <span class="bg-red-500/20 text-red-500 text-xs px-2 py-1 rounded font-bold"><i class="fas fa-eye-slash"></i> مخفي عن الزوار</span>
                <?php endif; ?>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
                <!-- إحصائية المشاهدات للفيلم الفردي -->
                <div class="bg-[#151515] p-4 rounded-xl border border-[#222] shadow-[0_0_15px_rgba(59,130,246,0.1)]">
                    <div class="text-xs text-gray-500 font-bold mb-1"><i class="fas fa-eye text-blue-400 ml-1"></i> المشاهدات</div>
                    <div class="text-xl font-black text-white"><?php echo number_format($movie_data['views'] ?? 0); ?> <span class="text-xs text-gray-500 font-normal">مشاهدة</span></div>
                </div>
                
                <div class="bg-[#151515] p-4 rounded-xl border border-[#222]">
                    <div class="text-xs text-gray-500 font-bold mb-1"><i class="fas fa-star text-yellow-400 ml-1"></i> التقييم</div>
                    <div class="text-xl font-black text-white"><?php echo htmlspecialchars($movie_data['rating'] ?? 'N/A'); ?></div>
                </div>

                <div class="bg-[#151515] p-4 rounded-xl border border-[#222]">
                    <div class="text-xs text-gray-500 font-bold mb-1"><i class="fas fa-film text-emerald-400 ml-1"></i> الجودة</div>
                    <div class="text-lg font-black text-white mt-1"><?php echo !empty($movie_data['quality']) ? htmlspecialchars($movie_data['quality']) : 'غير محدد'; ?></div>
                </div>

                <div class="bg-[#151515] p-4 rounded-xl border border-[#222]">
                    <div class="text-xs text-gray-500 font-bold mb-1"><i class="fas fa-folder-open text-purple-400 ml-1"></i> التصنيف</div>
                    <div class="text-sm font-black text-white mt-2"><?php echo htmlspecialchars($movie_data['genre'] ?? '-'); ?></div>
                </div>
            </div>
        </div>
    </div>
    <!-- ========================================== -->
    <?php endif; ?>

    <!-- ===== قسم جلب البيانات (TMDb + السينما كوم) ===== -->
    <div class="bg-background-light p-6 rounded-lg border border-border-color mb-6 shadow-lg">
        <h2 class="text-xl font-bold mb-3"><i class="fas fa-magic text-accent-primary"></i> جلب البيانات</h2>
        
        <!-- أزرار اختيار المصدر -->
        <div class="flex gap-2 mb-4">
            <button type="button" id="src-tmdb" class="src-btn src-active-tmdb" onclick="setSource('tmdb')">🎬 TMDb</button>
            <button type="button" id="src-ec" class="src-btn" onclick="setSource('elcinema')">🎥 السينما كوم</button>
        </div>

        <div class="flex flex-col md:flex-row gap-4">
            <input class="form-input flex-grow" type="text" id="tmdb_search_query" placeholder="ابحث عن فيلم بالاسم أو بـ ID...">
            <button type="button" id="tmdb_search_btn" class="btn btn-primary px-8"><i class="fas fa-search"></i> بحث</button>
        </div>
        <div id="tmdb-results" class="mt-4 max-h-64 overflow-y-auto"></div>
    </div>

    <div class="bg-background-light p-6 rounded-lg border border-border-color shadow-xl">
        <form method="POST" action="index.php?page=movies&category=<?php echo $current_category; ?>" enctype="multipart/form-data">
            <input type="hidden" name="movie_id" value="<?php echo $movie_data['id'] ?? ''; ?>">
            <input type="hidden" name="current_poster" value="<?php echo $movie_data['poster'] ?? ''; ?>">
            <input type="hidden" name="cast_data" id="cast_data" value='<?php echo htmlspecialchars($movie_data['cast_data'] ?? '', ENT_QUOTES, 'UTF-8'); ?>'>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="form-label">عنوان الفيلم</label>
                    <input class="form-input" type="text" name="title" id="title" value="<?php echo $movie_data['title'] ?? ''; ?>" required>
                </div>
                
                <div>
                    <label class="form-label text-brand-gold"><i class="fas fa-folder-open ml-1"></i> تصنيف الفيلم</label>
                    <select name="category" class="form-select border-brand-gold">
                        <option value="movie" <?php echo (($movie_data['category'] ?? $current_category) == 'movie') ? 'selected' : ''; ?>>فيلم عربي</option>
                        <option value="foreign_movie" <?php echo (($movie_data['category'] ?? $current_category) == 'foreign_movie') ? 'selected' : ''; ?>>فيلم أجنبي</option>
                        <option value="indian_movie" <?php echo (($movie_data['category'] ?? $current_category) == 'indian_movie') ? 'selected' : ''; ?>>فيلم هندي</option>
                    </select>
                </div>

                <div>
                    <label class="form-label">سنة الإنتاج</label>
                    <input class="form-input" type="number" name="year" id="year" value="<?php echo $movie_data['year'] ?? date('Y'); ?>">
                </div>

                <div>
                    <label class="form-label text-green-400 font-bold bg-green-900/30 p-2 rounded block">تاريخ الإصدار الدقيق (يوم-شهر-سنة)</label>
                    <input class="form-input border-green-500" type="date" name="release_date" id="release_date" value="<?php echo $movie_data['release_date'] ?? ''; ?>">
                </div>

                <div><label class="form-label">النوع</label><input class="form-input" type="text" name="genre" id="genre" value="<?php echo $movie_data['genre'] ?? ''; ?>"></div>
                <div><label class="form-label">التقييم</label><input class="form-input" type="text" name="rating" id="rating" value="<?php echo $movie_data['rating'] ?? '7.0'; ?>"></div>
                <div class="md:col-span-2"><label class="form-label">الوصف</label><textarea class="form-textarea" name="description" id="description" rows="4"><?php echo $movie_data['description'] ?? ''; ?></textarea></div>
                
                <div> 
                    <label class="form-label text-green-400">رابط المشاهدة (سيرفر 1)</label>
                    <textarea class="form-textarea" name="watch_link" id="watch_link_1" rows="2" placeholder="الرابط الأساسي..."><?php echo $movie_data['watch_link'] ?? ''; ?></textarea>
                    <button type="button" onclick="previewSvr(1)" class="btn btn-secondary btn-sm mt-2 w-full"><i class="fas fa-eye"></i> معاينة 1</button>
                </div>
                
                <div> 
                    <label class="form-label text-blue-400">رابط المشاهدة (سيرفر 2)</label>
                    <textarea class="form-textarea" name="watch_link_2" id="watch_link_2" rows="2" placeholder="الرابط الإضافي..."><?php echo $movie_data['watch_link_2'] ?? ''; ?></textarea>
                    <button type="button" onclick="previewSvr(2)" class="btn btn-secondary btn-sm mt-2 w-full"><i class="fas fa-eye"></i> معاينة 2</button>
                </div>

                <div> 
                    <label class="form-label text-purple-400">رابط المشاهدة (سيرفر 3)</label>
                    <textarea class="form-textarea" name="watch_link_3" id="watch_link_3" rows="2" placeholder="الرابط الإضافي..."><?php echo $movie_data['watch_link_3'] ?? ''; ?></textarea>
                    <button type="button" onclick="previewSvr(3)" class="btn btn-secondary btn-sm mt-2 w-full"><i class="fas fa-eye"></i> معاينة 3</button>
                </div>

                <div> 
                    <label class="form-label text-amber-500">رابط المشاهدة (سيرفر 4)</label>
                    <textarea class="form-textarea" name="watch_link_4" id="watch_link_4" rows="2" placeholder="الرابط الإضافي..."><?php echo $movie_data['watch_link_4'] ?? ''; ?></textarea>
                    <button type="button" onclick="previewSvr(4)" class="btn btn-secondary btn-sm mt-2 w-full"><i class="fas fa-eye"></i> معاينة 4</button>
                </div>

                <div> 
                    <label class="form-label text-emerald-400"><i class="fas fa-download"></i> رابط التحميل 1</label>
                    <input class="form-input" type="text" name="download_link" id="download_link" value="<?php echo $movie_data['download_link'] ?? ''; ?>" placeholder="رابط التحميل الأساسي...">
                </div>

                <div> 
                    <label class="form-label text-cyan-400"><i class="fas fa-download"></i> رابط التحميل 2</label>
                    <input class="form-input" type="text" name="download_link_2" id="download_link_2" value="<?php echo $movie_data['download_link_2'] ?? ''; ?>" placeholder="رابط التحميل الإضافي...">
                </div>

                <div class="md:col-span-2"> 
                    <label class="form-label">رابط الإعلان (Trailer)</label>
                    <div class="flex gap-2">
                        <input class="form-input flex-grow" type="text" name="trailer_link" id="trailer_link" value="<?php echo $movie_data['trailer_link'] ?? ''; ?>" placeholder="رابط يوتيوب أو فيديو مباشر...">
                        <button type="button" onclick="previewTrailer()" class="btn btn-secondary whitespace-nowrap"><i class="fas fa-eye"></i> معاينة الإعلان</button>
                    </div>
                </div>
                <div><label class="form-label">الجودة</label><input class="form-input" type="text" name="quality" id="quality" value="<?php echo $movie_data['quality'] ?? ''; ?>"></div>
                 
                <div id="player-preview-container-movie" class="md:col-span-2 mt-4" style="display: none;">
                    <div id="player-error-movie" class="player-preview-error"></div>
                    <div id="player-preview-movie" class="aspect-video bg-black rounded-lg overflow-hidden"></div>
                </div>
                 
                <div><label class="form-label text-accent-primary">رابط بوستر (TMDb / السينما كوم)</label><input class="form-input" type="text" name="poster_url_tmdb" id="poster_url_tmdb" value="<?php echo (!empty($movie_data['poster']) && filter_var($movie_data['poster'], FILTER_VALIDATE_URL)) ? $movie_data['poster'] : ''; ?>"></div>
                <div><label class="form-label">رفع بوستر محلي</label><input class="form-input !p-2" type="file" name="poster" id="poster" accept="image/*"></div>
                <div class="md:col-span-2"><img src="<?php echo (!empty($movie_data['poster'])) ? (filter_var($movie_data['poster'], FILTER_VALIDATE_URL) ? $movie_data['poster'] : '../'.$movie_data['poster']) : 'https://placehold.co/150x225?text=Poster'; ?>" id="poster_preview" class="w-32 h-auto rounded-md shadow-lg border border-border-color"></div>
                
                <div class="md:col-span-2 flex flex-wrap items-center gap-6 bg-[#0f172a] p-4 rounded-xl border border-[#1e293b]">
                    <div class="flex items-center gap-3 border-r border-[#1e293b] pr-6">
                        <input type="checkbox" name="is_recent" id="is_recent" class="w-5 h-5 accent-brand-gold" value="1" <?php echo (isset($movie_data['is_recent']) && $movie_data['is_recent'] == 1) ? 'checked' : ''; ?>>
                        <label class="font-bold cursor-pointer" for="is_recent">أضيف حديثاً</label>
                    </div>
                    <div class="flex items-center gap-3">
                        <input type="checkbox" name="is_published" id="is_published" class="w-5 h-5 accent-blue-500" value="1" <?php echo (!isset($movie_data['is_published']) || $movie_data['is_published'] == 1) ? 'checked' : ''; ?>>
                        <label class="font-bold text-blue-400 cursor-pointer" for="is_published">منشور (يظهر للزوار) 👁️</label>
                    </div>
                </div>
            </div>
            <div class="mt-8 flex justify-end gap-4"><a href="index.php?page=movies&category=<?php echo $current_category; ?>" class="btn btn-secondary px-8">إلغاء</a><button type="submit" name="save_movie" class="btn btn-primary px-12">حفظ الفيلم</button></div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const apiKey     = '<?php echo $tmdb_api_key; ?>';
            const searchBtn  = document.getElementById('tmdb_search_btn');
            const searchInput = document.getElementById('tmdb_search_query');
            const resultsDiv = document.getElementById('tmdb-results');
            
            // ========= إدارة مصدر البيانات =========
            let currentSource = 'tmdb';

            window.setSource = function(src) {
                currentSource = src;
                document.getElementById('src-tmdb').className = 'src-btn' + (src === 'tmdb' ? ' src-active-tmdb' : '');
                document.getElementById('src-ec').className   = 'src-btn' + (src === 'elcinema' ? ' src-active-ec' : '');
                searchInput.placeholder = src === 'tmdb' ? 'ابحث عن فيلم بالاسم أو بـ ID...' : 'ابحث باللغة العربية في السينما كوم...';
                resultsDiv.innerHTML = '';
            };

            // ========= زر البحث =========
            if (searchBtn) {
                searchBtn.addEventListener('click', async () => {
                    const q = searchInput.value.trim();
                    if (!q) return;
                    if (currentSource === 'elcinema') {
                        await searchElcinema(q);
                    } else {
                        await searchTMDb(q);
                    }
                });
                searchInput.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); searchBtn.click(); } });
            }

            // ========= TMDb بحث (الآن يدعم البحث بالـ ID مباشرة) =========
            async function searchTMDb(q) {
                resultsDiv.innerHTML = '<p class="text-xs text-center text-brand-gold font-bold">جاري البحث في TMDb...</p>';
                
                let results = [];
                
                // فحص إذا كان المدخل عبارة عن أرقام فقط (TMDb ID)
                if (/^\d+$/.test(q)) {
                    try {
                        const r = await fetch(`https://api.themoviedb.org/3/movie/${q}?api_key=${apiKey}&language=ar-EG`);
                        const d = await r.json();
                        if (d.id) {
                            results = [d]; // وضعه في مصفوفة ليتوافق مع طريقة العرض
                        }
                    } catch(e) { console.error(e); }
                } else {
                    try {
                        const r = await fetch(`https://api.themoviedb.org/3/search/movie?api_key=${apiKey}&query=${encodeURIComponent(q)}&language=ar-EG`);
                        const d = await r.json();
                        if(d.results) results = d.results.slice(0, 6);
                    } catch(e) { console.error(e); }
                }

                resultsDiv.innerHTML = '';
                
                if(results.length === 0) {
                    resultsDiv.innerHTML = '<p class="text-xs text-red-500 text-center font-bold">لم يتم العثور على نتائج.</p>';
                    return;
                }

                results.forEach(m => {
                    const item = document.createElement('div');
                    item.className = 'flex items-start gap-4 p-3 hover:bg-white/10 cursor-pointer rounded-lg border-b border-gray-800 transition-colors mb-2';
                    const poster = m.poster_path ? `https://image.tmdb.org/t/p/w92${m.poster_path}` : 'https://placehold.co/92x138?text=No+Image';
                    const year = m.release_date ? m.release_date.split('-')[0] : 'N/A';
                    const originalTitle = (m.original_title && m.original_title !== m.title) ? `<span class="block text-[10px] text-gray-500 ltr font-mono mt-1" dir="ltr">${m.original_title}</span>` : '';
                    item.innerHTML = `
                        <img src="${poster}" class="w-12 h-auto rounded shadow-lg">
                        <div class="flex-1">
                            <h4 class="text-sm font-bold text-brand-gold mb-0">${m.title}</h4>
                            ${originalTitle}
                            <div class="text-xs font-bold mt-2 flex gap-2">
                                <span class="bg-gray-800 text-gray-300 px-2 py-1 rounded">سنة: ${year}</span>
                                <span class="bg-yellow-500/20 text-yellow-500 px-2 py-1 rounded">⭐ ${m.vote_average ? m.vote_average.toFixed(1) : 'N/A'}</span>
                                <span class="bg-blue-500/20 text-blue-400 px-2 py-1 rounded">TMDb</span>
                            </div>
                        </div>
                    `;
                    item.onclick = () => fillTMDb(m.id);
                    resultsDiv.appendChild(item);
                });
            }
            
            // ========= TMDb ملء النموذج =========
            async function fillTMDb(id) {
                resultsDiv.innerHTML = '<p class="text-xs text-green-400 text-center font-bold">جاري جلب البيانات...</p>';
                const r = await fetch(`https://api.themoviedb.org/3/movie/${id}?api_key=${apiKey}&language=ar-EG&append_to_response=videos,credits`);
                const d = await r.json();
                document.getElementById('title').value       = d.title;
                document.getElementById('year').value        = d.release_date?.split('-')[0];
                document.getElementById('release_date').value = d.release_date || ''; // جلب التاريخ الكامل
                document.getElementById('rating').value      = d.vote_average.toFixed(1);
                document.getElementById('description').value = d.overview;
                document.getElementById('genre').value       = d.genres.map(g => g.name).join('، ');
                const tr = d.videos?.results?.find(v => v.type === 'Trailer');
                if (tr) document.getElementById('trailer_link').value = `https://www.youtube.com/watch?v=${tr.key}`;
                const p = `https://image.tmdb.org/t/p/w500${d.poster_path}`;
                document.getElementById('poster_url_tmdb').value = p;
                document.getElementById('poster_preview').src = p;
                if (d.credits && d.credits.cast) {
                    const castArray = d.credits.cast.slice(0, 10).map(actor => ({
                        name: actor.name, character: actor.character,
                        image: actor.profile_path ? `https://image.tmdb.org/t/p/w185${actor.profile_path}` : `https://ui-avatars.com/api/?name=${encodeURIComponent(actor.name)}&background=262626&color=DAA520&size=200`
                    }));
                    document.getElementById('cast_data').value = JSON.stringify(castArray);
                } else { document.getElementById('cast_data').value = ''; }
                resultsDiv.innerHTML = '<div class="text-green-500 font-bold p-2 text-center">✅ تم جلب البيانات من TMDb!</div>';
            }

            // ========= السينما كوم بحث =========
            async function searchElcinema(q) {
                resultsDiv.innerHTML = '<p class="text-xs text-center text-yellow-400 font-bold">جاري البحث في السينما كوم...</p>';
                try {
                    const r = await fetch(`pages/elcinema_api.php?action=search&q=${encodeURIComponent(q)}`);
                    const d = await r.json();
                    resultsDiv.innerHTML = '';
                    if (!d.results || d.results.length === 0) {
                        resultsDiv.innerHTML = '<p class="text-xs text-red-500 text-center font-bold">لم يتم العثور على نتائج في السينما كوم.</p>';
                        return;
                    }
                    d.results.forEach(item => {
                        const el = document.createElement('div');
                        el.className = 'flex items-start gap-4 p-3 hover:bg-white/10 cursor-pointer rounded-lg border-b border-gray-800 transition-colors mb-2';
                        el.innerHTML = `
                            <img src="${item.poster || 'https://placehold.co/92x138?text=No+Image'}" class="w-12 h-auto rounded shadow-lg" onerror="this.src='https://placehold.co/92x138?text=No+Image'">
                            <div class="flex-1">
                                <h4 class="text-sm font-bold text-yellow-400 mb-0">${item.title}</h4>
                                <div class="text-xs font-bold mt-2 flex gap-2 flex-wrap">
                                    ${item.year ? `<span class="bg-gray-800 text-gray-300 px-2 py-1 rounded">سنة: ${item.year}</span>` : ''}
                                    ${item.rating && item.rating !== '0' ? `<span class="bg-yellow-500/20 text-yellow-500 px-2 py-1 rounded">⭐ ${item.rating}</span>` : ''}
                                    <span class="bg-amber-500/20 text-amber-400 px-2 py-1 rounded">🎥 السينما كوم</span>
                                </div>
                            </div>
                        `;
                        el.onclick = () => fillElcinema(item.id);
                        resultsDiv.appendChild(el);
                    });
                } catch(e) {
                    resultsDiv.innerHTML = '<p class="text-xs text-red-500 text-center">تأكد من وجود ملف elcinema_api.php في نفس المجلد</p>';
                }
            }

            // ========= السينما كوم ملء النموذج =========
            async function fillElcinema(id) {
                resultsDiv.innerHTML = '<p class="text-xs text-center text-yellow-400 font-bold">جاري جلب البيانات من السينما كوم...</p>';
                try {
                    const r = await fetch(`pages/elcinema_api.php?action=details&id=${id}`);
                    const d = await r.json();
                    if (d.error) { resultsDiv.innerHTML = `<p class="text-xs text-red-500 text-center">خطأ: ${d.error}</p>`; return; }
                    if (d.title)       document.getElementById('title').value       = d.title;
                    if (d.year) {
                        document.getElementById('year').value = d.year;
                        document.getElementById('release_date').value = d.year + '-01-01'; // السينما كوم لا تعطي تاريخ كامل
                    }
                    if (d.rating)      document.getElementById('rating').value      = d.rating;
                    if (d.description) document.getElementById('description').value = d.description;
                    if (d.genre)       document.getElementById('genre').value       = d.genre;
                    if (d.trailer)     document.getElementById('trailer_link').value = d.trailer;
                    if (d.poster) {
                        document.getElementById('poster_url_tmdb').value = d.poster;
                        document.getElementById('poster_preview').src    = d.poster;
                    }
                    if (d.cast && d.cast.length > 0) {
                        document.getElementById('cast_data').value = JSON.stringify(d.cast);
                    }
                    resultsDiv.innerHTML = '<div class="text-yellow-400 font-bold p-2 text-center">✅ تم جلب البيانات من السينما كوم!</div>';
                } catch(e) {
                    resultsDiv.innerHTML = '<p class="text-xs text-red-500 text-center">خطأ في الاتصال</p>';
                }
            }
        });

        let hlsPlayer_movie; let plyrPlayer_movie;
        function destroyPlayers_movie() {
            if (hlsPlayer_movie) { hlsPlayer_movie.destroy(); hlsPlayer_movie = null; }
            if (plyrPlayer_movie) { plyrPlayer_movie.destroy(); plyrPlayer_movie = null; }
        }

        function previewPlayer_movie(watchUrl, containerId) {
            destroyPlayers_movie();
            const container = document.getElementById(containerId);
            const errorDiv = document.getElementById(containerId.replace('player-preview-', 'player-error-'));
            container.innerHTML = ''; if (errorDiv) errorDiv.style.display = 'none';
            if (!watchUrl.trim()) return;
            
            let url = watchUrl.trim();
            if (url.toLowerCase().includes('<iframe') || url.toLowerCase().includes('<div')) {
                const m = url.match(/src=["']([^"']+)["']/i);
                if (m && m[1]) url = m[1];
            }
            
            const ytMatch = url.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i);
            if (ytMatch && ytMatch[1]) { url = `https://www.youtube.com/embed/${ytMatch[1]}`; }
            
            if (url.includes('dailymotion.com/video/')) {
                const id = url.split('video/')[1]?.split('?')[0];
                if (id) url = `https://www.dailymotion.com/embed/video/${id}`;
            }

            if (url.includes('.m3u8') || url.toLowerCase().split('?')[0].endsWith('.mp4')) {
                const v = document.createElement('video'); v.controls = true; v.autoplay = true; 
                v.style.width='100%'; v.style.height='100%';
                container.appendChild(v);
                if (Hls.isSupported() && url.includes('.m3u8')) { hlsPlayer_movie = new Hls(); hlsPlayer_movie.loadSource(url); hlsPlayer_movie.attachMedia(v); } else v.src = url;
                plyrPlayer_movie = new Plyr(v, { autoplay: true });
            } else {
                const f = document.createElement('iframe'); 
                f.src = url; f.style.width='100%'; f.style.height='100%'; f.frameBorder="0"; 
                f.allowFullscreen=true; f.allow = "autoplay; fullscreen; picture-in-picture";
                container.appendChild(f);
            }
        }

        window.previewSvr = function(num) {
            const link = document.getElementById('watch_link_' + num).value;
            document.getElementById('player-preview-container-movie').style.display = 'block';
            previewPlayer_movie(link, 'player-preview-movie');
        }

        window.previewTrailer = function() {
            const link = document.getElementById('trailer_link').value;
            document.getElementById('player-preview-container-movie').style.display = 'block';
            previewPlayer_movie(link, 'player-preview-movie');
        }
    </script>

<?php else: ?>
    <?php
    $limit = 50;
    $p = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
    $offset = ($p - 1) * $limit;
    
    $search = isset($_GET['search']) ? $conn->real_escape_string(trim($_GET['search'])) : '';
    $filter_year = isset($_GET['filter_year']) ? $conn->real_escape_string(trim($_GET['filter_year'])) : '';
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'latest_added';
    
    $cat_safe = $conn->real_escape_string($current_category);
    $where_sql = "category = '$cat_safe'";
    
    if (!empty($search)) { $where_sql .= " AND (title LIKE '%$search%' OR year LIKE '%$search%')"; }
    if (!empty($filter_year)) { $where_sql .= " AND year = '$filter_year'"; }
    
    // الترتيب: جعل الافتراضي هو ID (الأحدث إضافة للقاعدة) وإضافة فلتر صريح لتاريخ الإصدار
    $order_sql = "id DESC"; 
    if ($sort == 'latest_release') { $order_sql = "COALESCE(release_date, created_at) DESC, id DESC"; }
    elseif ($sort == 'newest_year') { $order_sql = "year DESC, id DESC"; }
    elseif ($sort == 'oldest_year') { $order_sql = "year ASC, id ASC"; }
    elseif ($sort == 'oldest_added') { $order_sql = "id ASC"; }
    
    $count_res = $conn->query("SELECT COUNT(id) as total FROM movies WHERE $where_sql");
    $total_rows = $count_res->fetch_assoc()['total'];
    $total_pages = ceil($total_rows / $limit);
    
    $res = $conn->query("SELECT * FROM movies WHERE $where_sql ORDER BY $order_sql LIMIT $offset, $limit");
    
    // ==========================================
    // حساب الإحصائيات (الجديد المستوحى من كود الحلقات)
    // ==========================================
    $stats_q = $conn->query("SELECT COUNT(id) as movies_count, IFNULL(SUM(views), 0) as total_views FROM movies WHERE category = '$cat_safe'")->fetch_assoc();
    $top_movie = $conn->query("SELECT title, views FROM movies WHERE category = '$cat_safe' ORDER BY views DESC LIMIT 1")->fetch_assoc();
    
    // بيانات الرسم البياني
    $chart_labels = [];
    $chart_data = [];
    $movies_chart_q = $conn->query("SELECT title, views FROM movies WHERE category = '$cat_safe' ORDER BY views DESC LIMIT 10");
    while($r = $movies_chart_q->fetch_assoc()) {
        $title = mb_strlen($r['title']) > 15 ? mb_substr($r['title'], 0, 15) . '...' : $r['title'];
        $chart_labels[] = $title;
        $chart_data[] = $r['views'];
    }
    ?>

    <!-- ========================================== -->
    <!-- قسم الإحصائيات (الجديد المستوحى من الحلقات) -->
    <!-- ========================================== -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-8 mb-10 mt-6">
        <!-- الإحصائيات السريعة -->
        <div class="xl:col-span-5 bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl relative overflow-hidden flex flex-col justify-center">
            <div class="absolute -right-20 -top-20 w-64 h-64 bg-blue-500 opacity-5 rounded-full blur-[80px] pointer-events-none"></div>
            
            <h3 class="text-xl font-black text-white mb-6 relative z-10"><i class="fas fa-chart-pie text-blue-400 ml-2"></i> إحصائيات وتفاعل الزوار</h3>
            
            <div class="grid grid-cols-2 gap-4 relative z-10">
                <div class="bg-[#151515] p-4 rounded-xl border border-[#222]">
                    <div class="text-xs text-gray-500 font-bold mb-1"><i class="fas fa-eye text-blue-400 ml-1"></i> إجمالي المشاهدات</div>
                    <div class="text-xl font-black text-white"><?php echo number_format($stats_q['total_views']); ?></div>
                </div>
                
                <div class="bg-[#151515] p-4 rounded-xl border border-[#222]">
                    <div class="text-xs text-gray-500 font-bold mb-1"><i class="fas fa-film text-emerald-400 ml-1"></i> عدد الأفلام المرفوعة</div>
                    <div class="text-xl font-black text-white"><?php echo number_format($stats_q['movies_count']); ?></div>
                </div>

                <div class="bg-[#151515] p-4 rounded-xl border border-[#222] col-span-2">
                    <div class="text-xs text-gray-500 font-bold mb-1"><i class="fas fa-fire-alt text-red-500 ml-1"></i> الفيلم الأعلى تريند (مشاهدة)</div>
                    <div class="text-sm font-black text-white mt-1"><?php echo $top_movie ? htmlspecialchars($top_movie['title']) : '-'; ?> <span class="text-[10px] text-gray-500 mr-1">(<?php echo number_format($top_movie['views'] ?? 0); ?>)</span></div>
                </div>
            </div>
        </div>

        <!-- الرسم البياني للأفلام الأعلى مشاهدة -->
        <div class="xl:col-span-7 bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl flex flex-col">
            <h3 class="text-lg font-black text-white mb-4"><i class="fas fa-chart-bar text-amber-500 ml-2"></i> أعلى 10 أفلام مشاهدة في هذا القسم</h3>
            <div class="flex-1 relative min-h-[200px] w-full">
                <?php if(empty($chart_data) || array_sum($chart_data) == 0): ?>
                    <div class="absolute inset-0 flex items-center justify-center text-gray-500 text-sm font-bold">لا توجد مشاهدات كافية لعرض الرسم البياني.</div>
                <?php else: ?>
                    <canvas id="moviesViewsChart"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- كود تشغيل الرسم البياني -->
    <?php if(!empty($chart_data) && array_sum($chart_data) > 0): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctxMv = document.getElementById('moviesViewsChart');
            if (ctxMv) {
                new Chart(ctxMv.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($chart_labels); ?>,
                        datasets: [{
                            label: 'عدد المشاهدات',
                            data: <?php echo json_encode($chart_data); ?>,
                            backgroundColor: 'rgba(59, 130, 246, 0.8)',
                            borderColor: '#3b82f6',
                            borderWidth: 1,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: { titleFont: { family: 'Cairo' }, bodyFont: { family: 'Cairo' } }
                        },
                        scales: {
                            y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#888' } },
                            x: { grid: { display: false }, ticks: { color: '#888', font: { family: 'Cairo', size: 10 } } }
                        }
                    }
                });
            }
        });
    </script>
    <?php endif; ?>
    <!-- ========================================== -->

    <div class="search-container">
        <input type="text" id="movieTableSearch" class="table-search-input" placeholder="🔍 ابحث في الأفلام بالاسم..." value="<?php echo htmlspecialchars($search); ?>">
        <input type="number" id="movieTableYear" class="table-filter-select w-full md:w-auto" placeholder="سنة محددة (مثال: 2024)..." value="<?php echo htmlspecialchars($filter_year); ?>">
        <select id="movieTableSort" class="table-filter-select w-full md:w-auto">
            <option value="latest_added" <?php echo $sort == 'latest_added' ? 'selected' : ''; ?>>الأحدث إضافة للقاعدة (الافتراضي)</option>
            <option value="latest_release" <?php echo $sort == 'latest_release' ? 'selected' : ''; ?>>الأحدث صدوراً (تاريخ الإصدار)</option>
            <option value="newest_year"  <?php echo $sort == 'newest_year'  ? 'selected' : ''; ?>>الأحدث إنتاجاً (سنة الصدور)</option>
            <option value="oldest_year"  <?php echo $sort == 'oldest_year'  ? 'selected' : ''; ?>>الأقدم إنتاجاً</option>
            <option value="oldest_added" <?php echo $sort == 'oldest_added' ? 'selected' : ''; ?>>الأقدم إضافة للقاعدة</option>
        </select>
    </div>
    
    <div class="overflow-x-auto" id="tableWrapper">
        <table class="content-table" id="moviesTable">
            <thead>
                <tr>
                    <th>البوستر</th>
                    <th>العنوان</th>
                    <th>السنة</th>
                    <th class="text-center">المشاهدات</th>
                    <th>إعدادات سريعة</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($res->num_rows == 0): ?>
                <tr><td colspan="6" class="text-center py-10 text-gray-500 font-bold text-lg">لا يوجد نتائج مطابقة.</td></tr>
            <?php else: 
            while($row = $res->fetch_assoc()):
            ?>
                <tr>
                    <td>
                        <div style="position:relative;">
                            <img src="<?php echo (filter_var($row['poster'], FILTER_VALIDATE_URL)) ? $row['poster'] : '../'.$row['poster']; ?>" class="table-poster">
                            <?php if(isset($row['is_published']) && $row['is_published'] == 0): ?>
                                <span style="position:absolute; top:0; right:0; background:rgba(239, 68, 68, 0.9); color:white; font-size:10px; padding:2px 4px; border-radius:3px; font-weight:bold;">مخفي</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="font-bold movie-title">
                        <div class="mb-1 text-[15px]"><?php echo htmlspecialchars($row['title']); ?></div>
                        <span class="golden-text text-xs"><i class="fas fa-star"></i> <?php echo $row['rating']; ?></span>
                    </td>
                    <td class="movie-year font-bold text-gray-400"><?php echo htmlspecialchars($row['year']); ?></td>
                    <td class="text-center">
                        <span class="bg-blue-500/10 text-blue-400 font-bold px-3 py-1 rounded-full text-xs">
                            <?php echo number_format($row['views'] ?? 0); ?> <i class="fas fa-eye ml-1"></i>
                        </span>
                    </td>
                    <td>
                        <div class="flex flex-col gap-2">
                            <label class="flex items-center gap-2 text-[10px] font-bold text-gray-400">
                                <div class="toggle-switch">
                                    <input type="checkbox" onchange="toggleMovieStatus(<?php echo $row['id']; ?>, 'is_recent')" <?php echo $row['is_recent'] ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>حديثاً
                            </label>
                            <label class="flex items-center gap-2 text-[10px] font-bold text-blue-400">
                                <div class="toggle-switch">
                                    <input type="checkbox" onchange="toggleMovieStatus(<?php echo $row['id']; ?>, 'is_published')" <?php echo (!isset($row['is_published']) || $row['is_published'] == 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>منشور
                            </label>
                        </div>
                    </td>
                    <td>
                        <div class="flex flex-wrap gap-2">
                            <a href="index.php?page=movies&category=<?php echo $current_category; ?>&action=edit&id=<?php echo $row['id']; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></a>
                            <form method="POST" onsubmit="return confirm('حذف؟');"><input type="hidden" name="movie_id" value="<?php echo $row['id']; ?>"><button type="submit" name="delete_movie" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button></form>
                        </div>
                    </td>
                </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination-container mt-6 flex flex-wrap justify-center gap-2" id="paginationContainer">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <button onclick="loadTableData(<?php echo $i; ?>)" class="px-4 py-2 rounded font-bold transition <?php echo ($i == $p) ? 'bg-[#3b82f6] text-white shadow-lg' : 'bg-[#1a0b2e] border border-[#3b82f6] text-[#3b82f6] hover:bg-[#3b82f6] hover:text-white'; ?>">
                <?php echo $i; ?>
            </button>
        <?php endfor; ?>
    </div>
    <?php else: ?>
    <div id="paginationContainer"></div>
    <?php endif; ?>

    <script>
        let searchTimeout;
        const sInp = document.getElementById('movieTableSearch');
        const yInp = document.getElementById('movieTableYear');
        const sSort = document.getElementById('movieTableSort');
        const tbody = document.querySelector('#moviesTable tbody');
        const currentCat = '<?php echo $current_category; ?>';
        
        function restoreFilters() {
            if(!sInp || !yInp || !sSort) return false;
            let changed = false;
            const savedSearch = localStorage.getItem(`movies_search_${currentCat}`);
            const savedYear   = localStorage.getItem(`movies_year_${currentCat}`);
            const savedSort   = localStorage.getItem(`movies_sort_${currentCat}`);
            if(savedSearch !== null && savedSearch !== '') { sInp.value = savedSearch; changed = true; }
            if(savedYear !== null && savedYear !== '')     { yInp.value = savedYear; changed = true; }
            if(savedSort !== null && savedSort !== 'latest_added') { sSort.value = savedSort; changed = true; }
            return changed;
        }

        function saveFilters() {
            if(sInp)  localStorage.setItem(`movies_search_${currentCat}`, sInp.value);
            if(yInp)  localStorage.setItem(`movies_year_${currentCat}`,   yInp.value);
            if(sSort) localStorage.setItem(`movies_sort_${currentCat}`,   sSort.value);
        }
        
        function loadTableData(page = 1) {
            saveFilters();
            const searchVal = sInp ? sInp.value : '';
            const yearVal   = yInp ? yInp.value : '';
            const sortVal   = sSort ? sSort.value : 'latest_added';
            const url = `index.php?page=movies&category=${currentCat}&search=${encodeURIComponent(searchVal)}&filter_year=${encodeURIComponent(yearVal)}&sort=${encodeURIComponent(sortVal)}&p=${page}`;
            tbody.style.opacity = '0.5';
            fetch(url).then(r => r.text()).then(html => {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                tbody.innerHTML = doc.querySelector('#moviesTable tbody').innerHTML;
                tbody.style.opacity = '1';
                const newPag = doc.querySelector('#paginationContainer');
                const oldPag = document.getElementById('paginationContainer');
                if (newPag && oldPag) { oldPag.innerHTML = newPag.innerHTML; oldPag.className = newPag.className; }
            }).catch(err => { console.error(err); tbody.style.opacity = '1'; });
        }

        if (restoreFilters()) { loadTableData(1); }

        if(sInp)  { sInp.addEventListener('input',  () => { clearTimeout(searchTimeout); searchTimeout = setTimeout(() => loadTableData(1), 500); }); }
        if(yInp)  { yInp.addEventListener('input',  () => { clearTimeout(searchTimeout); searchTimeout = setTimeout(() => loadTableData(1), 500); }); }
        if(sSort) { sSort.addEventListener('change', () => loadTableData(1)); }
        
        async function toggleMovieStatus(id, column) {
            const formData = new FormData();
            formData.append('id', id); formData.append('column', column);
            try {
                const response = await fetch('index.php?page=movies&ajax_action=toggle', { method: 'POST', body: formData });
                const result = await response.json();
                if (!result.success) { alert('حدث خطأ أثناء التحديث.'); loadTableData(); }
                else if(column === 'is_published') { loadTableData(); }
            } catch (e) { console.error('Error toggling status', e); }
        }
    </script>
<?php endif; ?>