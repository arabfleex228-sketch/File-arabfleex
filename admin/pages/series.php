<?php
// --- معالجة طلبات الأجاكس للتعديل السريع (Toggle) ---
if (isset($_GET['ajax_action']) && $_GET['ajax_action'] == 'toggle') {
    header('Content-Type: application/json');
    $id = intval($_POST['id']);
    $col = $_POST['column'];
    $allowed_cols = ['is_recent', 'continue_after_ramadan', 'is_published'];
    if (in_array($col, $allowed_cols)) {
        $conn->query("UPDATE series SET $col = NOT $col WHERE id = $id");
        echo json_encode(['success' => true]);
    } else { echo json_encode(['success' => false]); }
    exit;
}

// --- تحديث قاعدة البيانات تلقائياً ---
// إصلاح: إضافة عمود تاريخ الإصدار الدقيق للترتيب الذكي
$check_release = $conn->query("SHOW COLUMNS FROM series LIKE 'release_date'");
if ($check_release && $check_release->num_rows == 0) {
    $conn->query("ALTER TABLE series ADD COLUMN release_date DATE DEFAULT NULL AFTER year");
}

$check_col = $conn->query("SHOW COLUMNS FROM series LIKE 'is_published'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE series ADD COLUMN is_published TINYINT(1) DEFAULT 1 AFTER continue_after_ramadan");
}
$check_cat = $conn->query("SHOW COLUMNS FROM series LIKE 'category'");
if ($check_cat && $check_cat->num_rows == 0) {
    $conn->query("ALTER TABLE series ADD COLUMN category VARCHAR(50) DEFAULT 'series' AFTER is_published");
}
$check_cast = $conn->query("SHOW COLUMNS FROM series LIKE 'cast_data'");
if ($check_cast && $check_cast->num_rows == 0) {
    $conn->query("ALTER TABLE series ADD COLUMN cast_data LONGTEXT DEFAULT NULL AFTER category");
}

$current_category = isset($_GET['category']) ? $_GET['category'] : 'series';

$page_title   = 'إدارة المسلسلات العربية';
$add_btn_text = 'إضافة مسلسل جديد';
if ($current_category == 'tv_show')  { $page_title = 'إدارة البرامج التلفزيونية'; $add_btn_text = 'إضافة برنامج جديد'; }
elseif ($current_category == 'turkish') { $page_title = 'إدارة المسلسلات التركية'; $add_btn_text = 'إضافة مسلسل تركي جديد'; }
elseif ($current_category == 'foreign')  { $page_title = 'إدارة المسلسلات الأجنبية'; $add_btn_text = 'إضافة مسلسل أجنبي جديد'; }
elseif ($current_category == 'indian')  { $page_title = 'إدارة المسلسلات الهندية'; $add_btn_text = 'إضافة مسلسل هندي جديد'; }
elseif ($current_category == 'wrestling') { $page_title = 'إدارة المصارعة الحرة'; $add_btn_text = 'إضافة عرض مصارعة جديد'; }

$tmdb_api_key = '084faf5aeaa8b2f5fb6a9c237101e491';
?>
<script src="https://cdn.plyr.io/3.7.8/plyr.js"></script>
<link rel="stylesheet" href="https://cdn.plyr.io/3.7.8/plyr.css" />
<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
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
    .badge-main   { background-color: #e0e7ff; color: #1e40af; padding: 0.1rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: bold; border: 1px solid #c7d2fe; }
    .badge-season { background-color: #fef3c7; color: #92400e; padding: 0.1rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: bold; border: 1px solid #fde68a; }
    /* أزرار مصدر البيانات */
    .src-btn { padding: 0.4rem 1rem; border-radius: 8px; font-weight: 700; font-size: 0.85rem; cursor: pointer; border: 2px solid #334155; background: #0f172a; color: #94a3b8; transition: all 0.2s; font-family: inherit; }
    .src-btn:hover { border-color: #6366f1; color: #a5b4fc; }
    .src-btn.src-active-tmdb { border-color: #3b82f6; background: rgba(59,130,246,0.15); color: #60a5fa; }
    .src-btn.src-active-ec   { border-color: #f59e0b; background: rgba(245,158,11,0.15); color: #fbbf24; }
</style>
<?php
function handle_upload_series($file) {
    if ($file['error'] !== UPLOAD_ERR_OK) return [false, "خطأ في رفع الملف."];
    $target_dir = "../uploads/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
    $file_name = time() . '_series_' . basename($file["name"]);
    $target_file = $target_dir . $file_name;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    if(getimagesize($file["tmp_name"]) === false) return [false, "الملف المرفوع ليس صورة."];
    if(!in_array($imageFileType, ["jpg", "png", "jpeg", "webp"])) return [false, "امتداد غير مسموح."];
    if (move_uploaded_file($file["tmp_name"], $target_file)) return [true, "uploads/" . $file_name];
    return [false, "حدث خطأ أثناء رفع الصورة."];
}

$message = ''; $message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_series'])) {
        $id = $_POST['series_id'];
        $stmt = $conn->prepare("SELECT poster FROM series WHERE id = ?");
        $stmt->bind_param("i", $id); $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        if ($result && !empty($result['poster']) && !filter_var($result['poster'], FILTER_VALIDATE_URL) && file_exists("../".$result['poster'])) unlink("../".$result['poster']);
        $stmt->close();
        $stmt = $conn->prepare("DELETE FROM series WHERE id = ?");
        $stmt->bind_param("i", $id);
        if($stmt->execute()){ $message = 'تم الحذف بنجاح.'; $message_type = 'success'; }
        $stmt->close();
    }

    if (isset($_POST['save_series'])) {
        $id = $_POST['series_id']; 
        $title = $_POST['title']; 
        $year = $_POST['year'];
        $release_date = !empty($_POST['release_date']) ? $_POST['release_date'] : NULL;
        $genre = $_POST['genre']; 
        $description = $_POST['description']; 
        $rating = $_POST['rating'];
        $trailer_link = $_POST['trailer_link']; 
        $is_recent = isset($_POST['is_recent']) ? 1 : 0;
        $continue_after_ramadan = isset($_POST['continue_after_ramadan']) ? 1 : 0;
        $is_published = isset($_POST['is_published']) ? 1 : 0;
        $category = $_POST['category']; 
        $cast_data = $_POST['cast_data'] ?? ''; 
        $ramadan_year = !empty($_POST['ramadan_year']) ? (int)$_POST['ramadan_year'] : NULL;
        $poster_path = $_POST['current_poster']; 
        $poster_url_tmdb = $_POST['poster_url_tmdb'] ?? ''; 

        if (isset($_FILES['poster']) && $_FILES['poster']['size'] > 0) {
            list($success, $new_p) = handle_upload_series($_FILES['poster']);
            if ($success) {
                if (!empty($poster_path) && !filter_var($poster_path, FILTER_VALIDATE_URL) && file_exists("../".$poster_path)) unlink("../".$poster_path);
                $poster_path = $new_p;
            } else { $message = $new_p; $message_type = 'error'; }
        } elseif (!empty($poster_url_tmdb)) { $poster_path = $poster_url_tmdb; }
        
        if(empty($message)){
            if (empty($id)) { 
                $stmt = $conn->prepare("INSERT INTO series (title, year, release_date, genre, poster, description, rating, trailer_link, is_recent, ramadan_year, continue_after_ramadan, is_published, category, cast_data) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sissssdsisiiss", $title, $year, $release_date, $genre, $poster_path, $description, $rating, $trailer_link, $is_recent, $ramadan_year, $continue_after_ramadan, $is_published, $category, $cast_data);
            } else { 
                $stmt = $conn->prepare("UPDATE series SET title=?, year=?, release_date=?, genre=?, poster=?, description=?, rating=?, trailer_link=?, is_recent=?, ramadan_year=?, continue_after_ramadan=?, is_published=?, category=?, cast_data=? WHERE id=?");
                $stmt->bind_param("sissssdsisiissi", $title, $year, $release_date, $genre, $poster_path, $description, $rating, $trailer_link, $is_recent, $ramadan_year, $continue_after_ramadan, $is_published, $category, $cast_data, $id);
            }
            if($stmt->execute()){ $message = 'تم الحفظ بنجاح.'; $message_type = 'success'; $current_category = $category; }
            $stmt->close();
        }
    }
}

$action = $_GET['action'] ?? 'view';
$series_data = null;
if ($action == 'edit' && isset($_GET['id'])) {
    $stmt = $conn->prepare("SELECT * FROM series WHERE id = ?");
    $stmt->bind_param("i", $_GET['id']); $stmt->execute();
    $series_data = $stmt->get_result()->fetch_assoc(); $stmt->close();
}
?>

<div class="section-header">
    <h1 class="section-title text-3xl font-black">
        <?php if($current_category == 'tv_show') echo '<i class="fas fa-microphone-alt text-purple-400 ml-2"></i>'; ?>
        <?php if($current_category == 'turkish') echo '<i class="fas fa-star-and-crescent text-red-400 ml-2"></i>'; ?>
        <?php if($current_category == 'foreign') echo '<i class="fas fa-globe-americas text-teal-400 ml-2"></i>'; ?>
        <?php if($current_category == 'indian') echo '<i class="fas fa-tv text-orange-500 ml-2"></i>'; ?>
        <?php if($current_category == 'series')  echo '<i class="fas fa-tv text-blue-400 ml-2"></i>'; ?>
        <?php if($current_category == 'wrestling')  echo '<i class="fas fa-hand-rock text-orange-400 ml-2"></i>'; ?>
        <?php echo $page_title; ?>
    </h1>
    <a href="index.php?page=series&category=<?php echo $current_category; ?>&action=add" class="btn btn-primary"><i class="fas fa-plus"></i> <?php echo $add_btn_text; ?></a>
</div>

<?php if (!empty($message)): ?>
    <div class="mb-4 p-4 rounded-md text-center font-bold <?php echo $message_type == 'success' ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<?php if ($action == 'add' || $action == 'edit'): ?>
    <!-- ===== قسم جلب البيانات (TMDb + السينما كوم) ===== -->
    <div class="bg-background-light p-6 rounded-lg border border-border-color mb-6 shadow-lg">
        <h2 class="text-xl font-bold mb-3"><i class="fas fa-magic text-accent-primary"></i> جلب البيانات</h2>

        <!-- أزرار اختيار المصدر -->
        <div class="flex gap-2 mb-4">
            <button type="button" id="src-tmdb" class="src-btn src-active-tmdb" onclick="setSource('tmdb')">🎬 TMDb</button>
            <button type="button" id="src-ec"   class="src-btn"                 onclick="setSource('elcinema')">🎥 السينما كوم</button>
        </div>

        <div class="flex flex-col md:flex-row gap-4">
            <input class="form-input flex-grow" type="text" id="tmdb_search_query" placeholder="ابحث عن المسلسل أو العرض بالاسم أو بـ ID...">
            <button type="button" id="tmdb_search_btn" class="btn btn-primary px-8"><i class="fas fa-search"></i> بحث</button>
        </div>
        <div id="tmdb-results" class="mt-4 max-h-64 overflow-y-auto"></div>
    </div>

<div class="bg-background-light p-6 rounded-lg border border-border-color shadow-xl">
    <form method="POST" action="index.php?page=series&category=<?php echo $current_category; ?>" enctype="multipart/form-data">
        <input type="hidden" name="series_id" value="<?php echo $series_data['id'] ?? ''; ?>">
        <input type="hidden" name="current_poster" value="<?php echo $series_data['poster'] ?? ''; ?>">
        <input type="hidden" name="cast_data" id="cast_data" value='<?php echo htmlspecialchars($series_data['cast_data'] ?? '', ENT_QUOTES, 'UTF-8'); ?>'>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div><label class="form-label">العنوان</label><input class="form-input" type="text" name="title" id="title" value="<?php echo $series_data['title'] ?? ''; ?>" required></div>
            
            <div>
                <label class="form-label text-brand-gold"><i class="fas fa-folder-open ml-1"></i> تصنيف القسم</label>
                <select name="category" class="form-select border-brand-gold">
                    <option value="series"  <?php echo (($series_data['category'] ?? $current_category) == 'series')  ? 'selected' : ''; ?>>مسلسل عربي</option>
                    <option value="foreign" <?php echo (($series_data['category'] ?? $current_category) == 'foreign') ? 'selected' : ''; ?>>مسلسل أجنبي</option>
                    <option value="indian" <?php echo (($series_data['category'] ?? $current_category) == 'indian') ? 'selected' : ''; ?>>مسلسل هندي</option>
                    <option value="turkish" <?php echo (($series_data['category'] ?? $current_category) == 'turkish') ? 'selected' : ''; ?>>مسلسل تركي</option>
                    <option value="tv_show" <?php echo (($series_data['category'] ?? $current_category) == 'tv_show') ? 'selected' : ''; ?>>برنامج تلفزيوني</option>
                    <option value="wrestling" <?php echo (($series_data['category'] ?? $current_category) == 'wrestling') ? 'selected' : ''; ?>>مصارعة حرة</option>
                </select>
            </div>

            <div><label class="form-label">سنة الإنتاج</label><input class="form-input" type="number" name="year" id="year" value="<?php echo $series_data['year'] ?? date('Y'); ?>"></div>
            
            <!-- حقل تاريخ الإصدار الدقيق للترتيب الذكي -->
            <div><label class="form-label text-green-400 font-bold bg-green-900/30 p-2 rounded block">تاريخ الإصدار الدقيق (يوم-شهر-سنة)</label><input class="form-input border-green-500" type="date" name="release_date" id="release_date" value="<?php echo $series_data['release_date'] ?? ''; ?>"></div>

            <div><label class="form-label">النوع</label><input class="form-input" type="text" name="genre" id="genre" value="<?php echo $series_data['genre'] ?? ''; ?>"></div>
            <div><label class="form-label">التقييم</label><input class="form-input" type="text" name="rating" id="rating" value="<?php echo $series_data['rating'] ?? '7.0'; ?>"></div>
            <div><label class="form-label">سنة العرض في رمضان</label><input class="form-input" type="number" name="ramadan_year" id="ramadan_year" value="<?php echo htmlspecialchars($series_data['ramadan_year'] ?? ''); ?>"></div>
            
            <div class="md:col-span-2"><label class="form-label">الوصف</label><textarea class="form-textarea" name="description" id="description" rows="4"><?php echo $series_data['description'] ?? ''; ?></textarea></div>

            <div class="md:col-span-2"> 
                <label class="form-label">رابط الإعلان (Trailer)</label>
                <div class="flex gap-4">
                    <input class="form-input flex-grow" type="text" name="trailer_link" id="trailer_link" value="<?php echo $series_data['trailer_link'] ?? ''; ?>" placeholder="رابط مباشر أو كود تضمين (YouTube)...">
                    <button type="button" id="preview-btn-series" class="btn btn-secondary"><i class="fas fa-eye"></i> معاينة</button>
                </div>
            </div>

            <div id="player-preview-container-series" class="md:col-span-2 mt-4" style="display: none;">
                <div id="player-error-series" class="player-preview-error"></div>
                <div id="player-preview-series" class="aspect-video bg-black rounded-lg overflow-hidden border border-brand-gold"></div>
            </div>
            
            <div><label class="form-label">رابط البوستر (TMDb / السينما كوم)</label><input class="form-input" type="text" name="poster_url_tmdb" id="poster_url_tmdb" value="<?php echo (!empty($series_data['poster']) && filter_var($series_data['poster'], FILTER_VALIDATE_URL)) ? $series_data['poster'] : ''; ?>"></div>
            <div><label class="form-label">رفع بوستر من الجهاز</label><input class="form-input !p-2" type="file" name="poster" id="poster" accept="image/*"></div>
            <div class="md:col-span-2"><img src="<?php echo (!empty($series_data['poster'])) ? (filter_var($series_data['poster'], FILTER_VALIDATE_URL) ? $series_data['poster'] : '../'.$series_data['poster']) : 'https://placehold.co/150x225?text=Poster'; ?>" id="poster_preview" class="w-32 h-auto rounded-md shadow-lg border border-border-color"></div>
            
            <div class="md:col-span-2 flex flex-wrap items-center gap-6 bg-[#0f172a] p-4 rounded-xl border border-[#1e293b]">
                <div class="flex items-center gap-3">
                    <input type="checkbox" name="is_recent" id="is_recent" class="w-5 h-5 accent-brand-gold" value="1" <?php echo (isset($series_data['is_recent']) && $series_data['is_recent'] == 1) ? 'checked' : ''; ?>>
                    <label class="font-bold cursor-pointer" for="is_recent">أضيف حديثاً</label>
                </div>
                <div class="flex items-center gap-3 border-r border-[#1e293b] px-6">
                    <input type="checkbox" name="continue_after_ramadan" id="continue_after_ramadan" class="w-5 h-5 accent-amber-500" value="1" <?php echo ($series_data['continue_after_ramadan'] ?? 0) ? 'checked' : ''; ?>>
                    <label class="font-bold text-amber-500 cursor-pointer" for="continue_after_ramadan">يستكمل عرضه</label>
                </div>
                <div class="flex items-center gap-3 border-r border-[#1e293b] pr-6">
                    <input type="checkbox" name="is_published" id="is_published" class="w-5 h-5 accent-blue-500" value="1" <?php echo (!isset($series_data['is_published']) || $series_data['is_published'] == 1) ? 'checked' : ''; ?>>
                    <label class="font-bold text-blue-400 cursor-pointer" for="is_published">منشور للزوار 👁️</label>
                </div>
            </div>
        </div>
        <div class="mt-8 flex justify-end gap-4"><a href="index.php?page=series&category=<?php echo $current_category; ?>" class="btn btn-secondary px-8">إلغاء</a><button type="submit" name="save_series" class="btn btn-primary px-12">حفظ</button></div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const apiKey      = '<?php echo $tmdb_api_key; ?>';
        const sBtn        = document.getElementById('tmdb_search_btn');
        const searchInput = document.getElementById('tmdb_search_query');
        const resDiv      = document.getElementById('tmdb-results');

        // ========= إدارة مصدر البيانات =========
        let currentSource = 'tmdb';

        window.setSource = function(src) {
            currentSource = src;
            document.getElementById('src-tmdb').className = 'src-btn' + (src === 'tmdb'     ? ' src-active-tmdb' : '');
            document.getElementById('src-ec').className   = 'src-btn' + (src === 'elcinema' ? ' src-active-ec'   : '');
            searchInput.placeholder = src === 'tmdb'
                ? 'ابحث عن المسلسل أو العرض بالاسم أو بـ ID...'
                : 'ابحث باللغة العربية في السينما كوم...';
            resDiv.innerHTML = '';
        };

        // ========= زر البحث =========
        if(sBtn) {
            sBtn.addEventListener('click', async () => {
                const q = searchInput.value.trim();
                if (!q) return;
                if (currentSource === 'elcinema') { await searchElcinema(q); }
                else { await searchTMDb(q); }
            });
            searchInput.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); sBtn.click(); } });
        }

        // ========= TMDb بحث (الآن يدعم البحث بالـ ID مباشرة) =========
        async function searchTMDb(q) {
            resDiv.innerHTML = '<p class="text-xs text-center text-brand-gold font-bold">جاري البحث واستخراج المواسم...</p>';
            
            let rawResults = [];

            // فحص إذا كان المدخل عبارة عن رقم (TMDb ID)
            if (/^\d+$/.test(q)) {
                try {
                    const r = await fetch(`https://api.themoviedb.org/3/tv/${q}?api_key=${apiKey}&language=ar-EG`);
                    const d = await r.json();
                    if (d.id) rawResults = [d];
                } catch(e) { console.error(e); }
            } else {
                try {
                    const r = await fetch(`https://api.themoviedb.org/3/search/tv?api_key=${apiKey}&query=${encodeURIComponent(q)}&language=ar-EG`);
                    const d = await r.json();
                    if(d.results) rawResults = d.results.slice(0, 3);
                } catch(e) { console.error(e); }
            }

            if(rawResults.length === 0) {
                resDiv.innerHTML = '<p class="text-xs text-red-500 text-center font-bold">لم يتم العثور على نتائج.</p>';
                return;
            }

            let expandedResults = [];
            for (const show of rawResults) {
                expandedResults.push({ id: show.id, season_number: null, is_main: true, display_name: show.name || show.title, original_name: show.original_name, first_air_date: show.first_air_date, poster_path: show.poster_path, vote_average: show.vote_average });
                
                // جلب التفاصيل الكاملة للمسلسل من أجل استخراج المواسم حتى ولو كان البحث عبر ID
                const showDetailsRes = await fetch(`https://api.themoviedb.org/3/tv/${show.id}?api_key=${apiKey}&language=ar-EG`);
                const showDetails = await showDetailsRes.json();
                if (showDetails.seasons) {
                    showDetails.seasons.forEach(season => {
                        if (season.season_number === 0) return;
                        expandedResults.push({ id: show.id, season_number: season.season_number, is_main: false, display_name: `${show.name || showDetails.name} (${season.name})`, original_name: `${show.original_name || ''} S${season.season_number}`, first_air_date: season.air_date || show.first_air_date, poster_path: season.poster_path || show.poster_path, vote_average: show.vote_average });
                    });
                }
            }

            resDiv.innerHTML = '';
            expandedResults.forEach(s => {
                const item = document.createElement('div');
                item.className = `flex items-start gap-4 p-3 hover:bg-white/10 cursor-pointer rounded-lg border-b border-gray-800 transition-colors mb-2 ${s.is_main ? 'bg-slate-800/50' : 'ml-8 border-r-4 border-r-blue-400 pr-4'}`;
                const poster = s.poster_path ? `https://image.tmdb.org/t/p/w92${s.poster_path}` : 'https://placehold.co/92x138?text=No+Image';
                const year   = s.first_air_date ? s.first_air_date.split('-')[0] : 'N/A';
                const badge  = s.is_main ? '<span class="badge-main mr-2">المسلسل كامل</span>' : '<span class="badge-season mr-2">موسم مخصص</span>';
                item.innerHTML = `
                    <img src="${poster}" class="w-12 h-auto rounded shadow-lg">
                    <div class="flex-1">
                        <h4 class="text-sm font-bold text-brand-gold mb-1 flex items-center flex-wrap">${s.display_name} ${badge}</h4>
                        <div class="text-xs font-bold mt-2 flex gap-2">
                            <span class="bg-gray-800 text-gray-300 px-2 py-1 rounded">سنة: ${year}</span>
                            <span class="bg-yellow-500/20 text-yellow-500 px-2 py-1 rounded">⭐ ${s.vote_average ? s.vote_average.toFixed(1) : 'N/A'}</span>
                            <span class="bg-blue-500/20 text-blue-400 px-2 py-1 rounded">TMDb</span>
                        </div>
                    </div>
                `;
                item.onclick = () => fillTMDb(s.id, s.season_number);
                resDiv.appendChild(item);
            });
        }

        // ========= TMDb ملء النموذج =========
        async function fillTMDb(showId, seasonNumber) {
            resDiv.innerHTML = '<p class="text-xs text-green-400 text-center font-bold">جاري جلب البيانات...</p>';
            const r = await fetch(`https://api.themoviedb.org/3/tv/${showId}?api_key=${apiKey}&language=ar-EG&append_to_response=videos,credits&include_video_language=ar,en,null`);
            const d = await r.json();
            
            let finalTitle = d.name, finalYear = d.first_air_date?.split('-')[0], finalOverview = d.overview, finalPosterPath = d.poster_path, finalTrailerKey = '';
            let finalDate = d.first_air_date; // جلب التاريخ الكامل للجديد

            if (d.videos && d.videos.results) {
                let t = d.videos.results.find(v => v.type === 'Trailer' && v.site === 'YouTube');
                if (!t) t = d.videos.results.find(v => v.site === 'YouTube');
                if (t) finalTrailerKey = t.key;
            }

            if (seasonNumber !== null) {
                const sRes  = await fetch(`https://api.themoviedb.org/3/tv/${showId}/season/${seasonNumber}?api_key=${apiKey}&language=ar-EG&append_to_response=videos&include_video_language=ar,en,null`);
                const sData = await sRes.json();
                finalTitle = `${d.name} (${sData.name})`;
                if (sData.air_date) {
                    finalYear = sData.air_date.split('-')[0];
                    finalDate = sData.air_date; 
                }
                if (sData.overview)     finalOverview    = sData.overview;
                if (sData.poster_path)  finalPosterPath  = sData.poster_path;
                if (sData.videos && sData.videos.results && sData.videos.results.length > 0) {
                    let st = sData.videos.results.find(v => v.type === 'Trailer' && v.site === 'YouTube');
                    if (!st) st = sData.videos.results.find(v => v.site === 'YouTube');
                    if (st) finalTrailerKey = st.key;
                }
            }

            document.getElementById('title').value       = finalTitle;
            document.getElementById('year').value        = finalYear;
            document.getElementById('release_date').value = finalDate || ''; // إضافة التاريخ كامل
            document.getElementById('rating').value      = d.vote_average.toFixed(1);
            document.getElementById('description').value = finalOverview;
            document.getElementById('genre').value       = d.genres.map(g => g.name).join('، ');
            document.getElementById('trailer_link').value = finalTrailerKey ? `https://www.youtube.com/watch?v=${finalTrailerKey}` : '';
            if (finalPosterPath) {
                const p = `https://image.tmdb.org/t/p/w500${finalPosterPath}`;
                document.getElementById('poster_url_tmdb').value = p;
                document.getElementById('poster_preview').src    = p;
            }
            if (d.credits && d.credits.cast) {
                const castArray = d.credits.cast.slice(0, 10).map(actor => ({
                    name: actor.name, character: actor.character,
                    image: actor.profile_path ? `https://image.tmdb.org/t/p/w185${actor.profile_path}` : `https://ui-avatars.com/api/?name=${encodeURIComponent(actor.name)}&background=262626&color=DAA520&size=200`
                }));
                document.getElementById('cast_data').value = JSON.stringify(castArray);
            } else { document.getElementById('cast_data').value = ''; }
            resDiv.innerHTML = '<div class="text-green-500 font-bold p-2 text-center">✅ تم جلب البيانات من TMDb!</div>';
        }

        // ========= السينما كوم بحث =========
        async function searchElcinema(q) {
            resDiv.innerHTML = '<p class="text-xs text-center text-yellow-400 font-bold">جاري البحث في السينما كوم...</p>';
            try {
                const r = await fetch(`pages/elcinema_api.php?action=search&q=${encodeURIComponent(q)}`);
                const d = await r.json();
                resDiv.innerHTML = '';
                if (!d.results || d.results.length === 0) {
                    resDiv.innerHTML = '<p class="text-xs text-red-500 text-center font-bold">لم يتم العثور على نتائج في السينما كوم.</p>';
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
                    resDiv.appendChild(el);
                });
            } catch(e) {
                resDiv.innerHTML = '<p class="text-xs text-red-500 text-center">تأكد من وجود ملف elcinema_api.php في نفس المجلد</p>';
            }
        }

        // ========= السينما كوم ملء النموذج =========
        async function fillElcinema(id) {
            resDiv.innerHTML = '<p class="text-xs text-center text-yellow-400 font-bold">جاري جلب البيانات من السينما كوم...</p>';
            try {
                const r = await fetch(`pages/elcinema_api.php?action=details&id=${id}`);
                const d = await r.json();
                if (d.error) { resDiv.innerHTML = `<p class="text-xs text-red-500 text-center">خطأ: ${d.error}</p>`; return; }
                if (d.title)       document.getElementById('title').value        = d.title;
                if (d.year) {
                    document.getElementById('year').value = d.year;
                    document.getElementById('release_date').value = d.year + '-01-01'; // السينما كوم
                }
                if (d.rating)      document.getElementById('rating').value       = d.rating;
                if (d.description) document.getElementById('description').value  = d.description;
                if (d.genre)       document.getElementById('genre').value        = d.genre;
                if (d.trailer)     document.getElementById('trailer_link').value = d.trailer;
                if (d.poster) {
                    document.getElementById('poster_url_tmdb').value = d.poster;
                    document.getElementById('poster_preview').src    = d.poster;
                }
                if (d.cast && d.cast.length > 0) {
                    document.getElementById('cast_data').value = JSON.stringify(d.cast);
                }
                resDiv.innerHTML = '<div class="text-yellow-400 font-bold p-2 text-center">✅ تم جلب البيانات من السينما كوم!</div>';
            } catch(e) {
                resDiv.innerHTML = '<p class="text-xs text-red-500 text-center">خطأ في الاتصال</p>';
            }
        }

        // ========= معاينة التريلر =========
        let hlsPlayer_series; let plyrPlayer_series;
        function destroyPlayers_series() {
            if (hlsPlayer_series) { hlsPlayer_series.destroy(); hlsPlayer_series = null; }
            if (plyrPlayer_series) { plyrPlayer_series.destroy(); plyrPlayer_series = null; }
        }
        function previewPlayer_series(watchUrl, containerId) {
            destroyPlayers_series();
            const container = document.getElementById(containerId);
            const errorDiv  = document.getElementById(containerId.replace('player-preview-', 'player-error-'));
            container.innerHTML = ''; if (errorDiv) errorDiv.style.display = 'none';
            if (!watchUrl.trim()) return;
            let url = watchUrl.trim();
            if (url.toLowerCase().includes('<iframe')) { const m = url.match(/src=["']([^"']+)["']/i); if (m && m[1]) url = m[1]; }
            if (url.includes('.m3u8') || url.toLowerCase().split('?')[0].endsWith('.mp4')) {
                const v = document.createElement('video'); v.controls = true; v.autoplay = true; v.style.width='100%'; v.style.height='100%'; container.appendChild(v);
                if (Hls.isSupported() && url.includes('.m3u8')) { hlsPlayer_series = new Hls(); hlsPlayer_series.loadSource(url); hlsPlayer_series.attachMedia(v); } else v.src = url;
                plyrPlayer_series = new Plyr(v, { autoplay: true });
            } else {
                const f = document.createElement('iframe'); f.src = url; f.style.width='100%'; f.style.height='100%'; f.frameBorder="0"; f.allowFullscreen=true; f.allow = "autoplay; fullscreen; picture-in-picture"; container.appendChild(f);
            }
        }
        const previewBtn = document.getElementById('preview-btn-series');
        if (previewBtn) {
            previewBtn.addEventListener('click', () => {
                const link = document.getElementById('trailer_link').value;
                document.getElementById('player-preview-container-series').style.display = 'block';
                previewPlayer_series(link, 'player-preview-series');
            });
        }
    });
</script>

<?php else: ?>
    <?php
    $limit = 50;
    $p = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
    $offset = ($p - 1) * $limit;
    $search = isset($_GET['search']) ? $conn->real_escape_string(trim($_GET['search'])) : '';
    $filter = isset($_GET['filter']) ? $conn->real_escape_string($_GET['filter']) : 'all';
    $filter_year = isset($_GET['filter_year']) ? $conn->real_escape_string(trim($_GET['filter_year'])) : '';
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'latest_added';
    $cat_safe = $conn->real_escape_string($current_category);
    $where_sql = "category = '$cat_safe'";
    if (!empty($search)) { $where_sql .= " AND (title LIKE '%$search%' OR year LIKE '%$search%')"; }
    if ($filter == '2025' || $filter == '2026') { $where_sql .= " AND ramadan_year = '$filter'"; }
    elseif ($filter == 'normal') { $where_sql .= " AND (ramadan_year IS NULL OR ramadan_year = '')"; }
    if (!empty($filter_year)) { $where_sql .= " AND year = '$filter_year'"; }
    
    // استخدام الترتيب الذكي COALESCE للجمع بين القديم والجديد
    $order_sql = "COALESCE(s.release_date, s.created_at) DESC";
    if ($sort == 'oldest_added') { $order_sql = "COALESCE(s.release_date, s.created_at) ASC"; }
    elseif ($sort == 'newest_year') { $order_sql = "s.year DESC, COALESCE(s.release_date, s.created_at) DESC"; }
    elseif ($sort == 'oldest_year') { $order_sql = "s.year ASC, COALESCE(s.release_date, s.created_at) ASC"; }
    
    $count_res  = $conn->query("SELECT COUNT(id) as total FROM series WHERE $where_sql");
    $total_rows = $count_res->fetch_assoc()['total'];
    $total_pages = ceil($total_rows / $limit);
    $stmt_list = $conn->query("SELECT s.*, (SELECT COUNT(id) FROM episodes WHERE series_id = s.id) as ep_count FROM series s WHERE $where_sql ORDER BY $order_sql LIMIT $offset, $limit");
    $result = $stmt_list;
    ?>

    <div class="search-container">
        <input type="text" id="seriesTableSearch" class="table-search-input" placeholder="🔍 ابحث بالاسم..." value="<?php echo htmlspecialchars($search); ?>">
        <input type="number" id="seriesTableYear" class="table-filter-select w-full md:w-auto" placeholder="سنة معينة (مثال: 2024)..." value="<?php echo htmlspecialchars($filter_year); ?>">
        <select id="seriesTableSort" class="table-filter-select w-full md:w-auto">
            <option value="latest_added" <?php echo $sort == 'latest_added' ? 'selected' : ''; ?>>الأحدث (ذكي)</option>
            <option value="newest_year"  <?php echo $sort == 'newest_year'  ? 'selected' : ''; ?>>الأحدث إنتاجاً (سنة الصدور)</option>
            <option value="oldest_year"  <?php echo $sort == 'oldest_year'  ? 'selected' : ''; ?>>الأقدم إنتاجاً (سنة الصدور)</option>
            <option value="oldest_added" <?php echo $sort == 'oldest_added' ? 'selected' : ''; ?>>الأقدم إضافة</option>
        </select>
        <select id="seriesTableFilter" class="table-filter-select w-full md:w-auto">
            <option value="all"    <?php echo $filter == 'all'    ? 'selected' : ''; ?>>الكل</option>
            <option value="2025"   <?php echo $filter == '2025'   ? 'selected' : ''; ?>>رمضان 2025</option>
            <option value="2026"   <?php echo $filter == '2026'   ? 'selected' : ''; ?>>رمضان 2026</option>
            <option value="normal" <?php echo $filter == 'normal' ? 'selected' : ''; ?>>بدون سنة رمضان</option>
        </select>
    </div>
    
    <div class="overflow-x-auto" id="tableWrapper">
        <table class="content-table" id="seriesTable">
            <thead><tr><th>البوستر</th><th>العنوان</th><th>الحلقات/العروض</th><th>إعدادات سريعة</th><th>إجراءات</th></tr></thead>
            <tbody>
            <?php if ($result->num_rows == 0): ?>
                <tr><td colspan="5" class="text-center py-10 text-gray-500 font-bold text-lg">لا يوجد نتائج مطابقة.</td></tr>
            <?php else:
            while($row = $result->fetch_assoc()):
            ?>
                <tr class="series-row" data-ramadan="<?php echo !empty($row['ramadan_year']) ? $row['ramadan_year'] : 'none'; ?>">
                    <td>
                        <div style="position:relative;">
                            <img src="<?php echo (filter_var($row['poster'], FILTER_VALIDATE_URL)) ? $row['poster'] : '../'.$row['poster']; ?>" class="table-poster">
                            <?php if(isset($row['is_published']) && $row['is_published'] == 0): ?>
                                <span style="position:absolute; top:0; right:0; background:rgba(239, 68, 68, 0.9); color:white; font-size:10px; padding:2px 4px; border-radius:3px; font-weight:bold;">مخفي</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="font-bold series-title">
                        <div class="mb-1 text-[15px]"><?php echo htmlspecialchars($row['title']); ?></div>
                        <span class="text-[10px] text-gray-500 series-year">إنتاج: <?php echo htmlspecialchars($row['year']); ?></span>
                        <span class="text-[10px] golden-text ml-2"><i class="fas fa-star"></i> <?php echo htmlspecialchars($row['rating']); ?></span>
                        <?php if(!empty($row['ramadan_year'])): ?>
                            <span class="text-[10px] bg-purple-500/20 text-purple-400 px-1 rounded ml-2">رمضان <?php echo $row['ramadan_year']; ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="bg-[#1e293b] border border-[#3b82f6] text-[#3b82f6] font-black px-3 py-1 rounded-full"><?php echo $row['ep_count']; ?></span>
                    </td>
                    <td>
                        <div class="flex flex-col gap-2">
                            <label class="flex items-center gap-2 text-[10px] font-bold text-gray-400">
                                <div class="toggle-switch"><input type="checkbox" onchange="toggleSeriesStatus(<?php echo $row['id']; ?>, 'is_recent')" <?php echo $row['is_recent'] ? 'checked' : ''; ?>><span class="toggle-slider"></span></div>حديثاً
                            </label>
                            <label class="flex items-center gap-2 text-[10px] font-bold text-amber-500">
                                <div class="toggle-switch"><input type="checkbox" onchange="toggleSeriesStatus(<?php echo $row['id']; ?>, 'continue_after_ramadan')" <?php echo $row['continue_after_ramadan'] ? 'checked' : ''; ?>><span class="toggle-slider"></span></div>يستكمل
                            </label>
                            <label class="flex items-center gap-2 text-[10px] font-bold text-blue-400">
                                <div class="toggle-switch"><input type="checkbox" onchange="toggleSeriesStatus(<?php echo $row['id']; ?>, 'is_published')" <?php echo (!isset($row['is_published']) || $row['is_published'] == 1) ? 'checked' : ''; ?>><span class="toggle-slider"></span></div>منشور
                            </label>
                        </div>
                    </td>
                    <td>
                        <div class="flex flex-wrap gap-2">
                            <a href="index.php?page=episodes&series_id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm" title="الحلقات / العروض"><i class="fas fa-list"></i></a>
                            <a href="index.php?page=series&category=<?php echo $current_category; ?>&action=edit&id=<?php echo $row['id']; ?>" class="btn btn-secondary btn-sm" title="تعديل"><i class="fas fa-edit"></i></a>
                            <form method="POST" onsubmit="return confirm('هل أنت متأكد من الحذف؟');"><input type="hidden" name="series_id" value="<?php echo $row['id']; ?>"><button type="submit" name="delete_series" class="btn btn-danger btn-sm" title="حذف"><i class="fas fa-trash"></i></button></form>
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
        const sInp  = document.getElementById('seriesTableSearch');
        const yInp  = document.getElementById('seriesTableYear');
        const sSort = document.getElementById('seriesTableSort');
        const fSel  = document.getElementById('seriesTableFilter');
        const tbody = document.querySelector('#seriesTable tbody');
        const currentCat = '<?php echo $current_category; ?>';

        function restoreFilters() {
            if(!sInp || !yInp || !sSort || !fSel) return false;
            let changed = false;
            const savedSearch = localStorage.getItem(`series_search_${currentCat}`);
            const savedYear   = localStorage.getItem(`series_year_${currentCat}`);
            const savedSort   = localStorage.getItem(`series_sort_${currentCat}`);
            const savedFilter = localStorage.getItem(`series_filter_${currentCat}`);
            if(savedSearch !== null && savedSearch !== '') { sInp.value = savedSearch; changed = true; }
            if(savedYear   !== null && savedYear   !== '') { yInp.value = savedYear;   changed = true; }
            if(savedSort   !== null && savedSort   !== 'latest_added') { sSort.value = savedSort; changed = true; }
            if(savedFilter !== null && savedFilter !== 'all')          { fSel.value  = savedFilter; changed = true; }
            return changed;
        }

        function saveFilters() {
            if(sInp)  localStorage.setItem(`series_search_${currentCat}`, sInp.value);
            if(yInp)  localStorage.setItem(`series_year_${currentCat}`,   yInp.value);
            if(sSort) localStorage.setItem(`series_sort_${currentCat}`,   sSort.value);
            if(fSel)  localStorage.setItem(`series_filter_${currentCat}`, fSel.value);
        }
        
        function loadTableData(page = 1) {
            saveFilters();
            const searchVal = sInp ? sInp.value : '';
            const filterVal = fSel ? fSel.value : 'all';
            const yearVal   = yInp ? yInp.value : '';
            const sortVal   = sSort ? sSort.value : 'latest_added';
            const url = `index.php?page=series&category=${currentCat}&search=${encodeURIComponent(searchVal)}&filter=${encodeURIComponent(filterVal)}&filter_year=${encodeURIComponent(yearVal)}&sort=${encodeURIComponent(sortVal)}&p=${page}`;
            tbody.style.opacity = '0.5';
            fetch(url).then(r => r.text()).then(html => {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                tbody.innerHTML = doc.querySelector('#seriesTable tbody').innerHTML;
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
        if(fSel)  { fSel.addEventListener('change',  () => loadTableData(1)); }

        async function toggleSeriesStatus(id, column) {
            const formData = new FormData();
            formData.append('id', id); formData.append('column', column);
            try {
                const response = await fetch('index.php?page=series&ajax_action=toggle', { method: 'POST', body: formData });
                const result   = await response.json();
                if (!result.success) { alert('حدث خطأ أثناء التحديث.'); loadTableData(); }
                else if(column === 'is_published') { loadTableData(); }
            } catch (e) { console.error('Error toggling status', e); }
        }
    </script>
<?php endif; ?>