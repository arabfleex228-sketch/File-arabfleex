<?php
// ==========================================
// ملف الإضافة السريعة والجماعية للأفلام (المولد الذكي)
// ==========================================

// معالجة طلب الحفظ الجماعي (AJAX POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_bulk_movies'])) {
    header('Content-Type: application/json');
    $movies_data = json_decode($_POST['movies_json'], true);
    
    if (empty($movies_data)) {
        echo json_encode(['success' => false, 'message' => 'لا توجد أفلام للحفظ.']);
        exit;
    }

    $inserted_count = 0;
    
    // تم تعديل الاستعلام ليأخذ قيمة is_recent من المستخدم بدل إجبارها على 1
    $stmt = $conn->prepare("INSERT INTO movies (title, year, release_date, genre, poster, description, rating, watch_link, download_link, trailer_link, quality, is_recent, is_published, cast_data, category) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)");
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات: ' . $conn->error]);
        exit;
    }

    foreach ($movies_data as $movie) {
        $title        = $movie['title'];
        $year         = !empty($movie['year']) ? $movie['year'] : date('Y');
        $release_date = !empty($movie['release_date']) ? $movie['release_date'] : NULL;
        $genre        = $movie['genre'];
        $poster       = $movie['poster'];
        $description  = $movie['description'];
        $rating       = $movie['rating'];
        $watch_link   = $movie['magic_link']; 
        $download_link= $movie['magic_link']; 
        $trailer_link = $movie['trailer_link'];
        
        // التعديل: أخذ الجودة وحالة "أضيف حديثا" من واجهة المستخدم
        $quality      = $movie['quality']; 
        $is_recent    = isset($movie['is_recent']) && $movie['is_recent'] == true ? 1 : 0; 
        
        $cast_data    = $movie['cast_data'];
        $category     = $movie['category'];

        // التعديل الهام هنا: 11 حرف s ثم حرف i ثم حرفين s ليتطابق مع البيانات وتُحفظ الجودة كـ نص
        $stmt->bind_param("sssssssssssiss", $title, $year, $release_date, $genre, $poster, $description, $rating, $watch_link, $download_link, $trailer_link, $quality, $is_recent, $cast_data, $category);
        
        if ($stmt->execute()) {
            $inserted_count++;
        }
    }
    
    $stmt->close();
    
    echo json_encode(['success' => true, 'message' => "تم حفظ $inserted_count فيلم بنجاح!"]);
    exit;
}

$tmdb_api_key = '084faf5aeaa8b2f5fb6a9c237101e491';
?>

<style>
    .smart-box { background: linear-gradient(145deg, #111827, #0F0F0F); border: 1px dashed #3b82f6; border-radius: 16px; transition: all 0.3s ease; }
    .smart-box:focus-within { border-color: #DAA520; box-shadow: 0 0 20px rgba(218,165,32,0.1); }
    .movie-queue-row { animation: slideIn 0.3s ease-out forwards; }
    @keyframes slideIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .custom-toast { position: fixed; bottom: 20px; right: 20px; background: #3b82f6; color: white; padding: 12px 24px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); z-index: 9999; font-weight: bold; transform: translateY(100px); opacity: 0; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    .custom-toast.show { transform: translateY(0); opacity: 1; }
    .custom-toast.error { background: #ef4444; }
    .custom-toast.success { background: #10B981; }
</style>

<div class="section-header flex justify-between items-center mb-6">
    <h1 class="section-title text-3xl font-black">
        <i class="fas fa-bolt text-[#DAA520] ml-2"></i> الإضافة السريعة للأفلام <span class="text-xs text-blue-400 bg-blue-900/30 px-2 py-1 rounded ml-2">الذكاء الآلي</span>
    </h1>
    <a href="index.php?page=movies" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-right ml-1"></i> العودة للأفلام</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
    
    <!-- القسم الأيمن: لوحة الإدخال والبحث -->
    <div class="lg:col-span-5 sticky top-4 z-40">
        <div class="bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl smart-box">
            <h2 class="text-xl font-bold mb-6 text-white"><i class="fas fa-magic text-blue-400 ml-2"></i> 1. جهز بيانات الفيلم</h2>
            
            <div class="grid grid-cols-2 gap-4 mb-5">
                <!-- اختيار الفئة -->
                <div>
                    <label class="form-label text-[#DAA520] font-bold"><i class="fas fa-folder-open ml-1"></i> فئة الفيلم</label>
                    <select id="movie_category" class="form-select border-[#DAA520] w-full">
                        <option value="foreign_movie">فيلم أجنبي</option>
                        <option value="movie">فيلم عربي</option>
                        <option value="indian_movie">فيلم هندي</option>
                    </select>
                </div>
                <!-- اختيار الجودة -->
                <div>
                    <label class="form-label text-blue-400 font-bold"><i class="fas fa-video ml-1"></i> جودة النسخة</label>
                    <select id="movie_quality" class="form-select border-blue-500 w-full">
                        <option value="WEB-DL">WEB-DL</option>
                        <option value="BluRay">BluRay</option>
                        <option value="HD">HD</option>
                        <option value="CAM">CAM</option>
                        <option value="متعددة">متعددة</option>
                    </select>
                </div>
            </div>

            <!-- خيار "أضيف حديثاً" (تم جعله غير مفعل افتراضياً) -->
            <div class="mb-5 bg-gray-800/50 p-3 rounded-lg border border-gray-700">
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" id="movie_is_recent" class="form-checkbox h-5 w-5 text-blue-600 rounded bg-gray-900 border-gray-600 ml-3">
                    <span class="text-gray-300 font-bold text-sm">عرض الفيلم في قسم "أضيف حديثاً" بالرئيسية</span>
                </label>
            </div>

            <!-- البحث في TMDb -->
            <div class="mb-5">
                <label class="form-label font-bold text-gray-300"><i class="fas fa-search ml-1"></i> ابحث عن الفيلم (TMDb)</label>
                <div class="flex gap-2">
                    <input type="text" id="tmdb_search_input" class="form-input flex-1" placeholder="اكتب اسم الفيلم هنا...">
                    <button type="button" id="tmdb_search_btn" class="btn btn-primary px-4"><i class="fas fa-search"></i></button>
                </div>
                <div id="tmdb_results" class="mt-3 max-h-48 overflow-y-auto rounded-lg bg-black/30 border border-gray-800 hidden p-2 custom-scrollbar"></div>
            </div>

            <!-- الفيلم المختار (للتأكيد البصري) -->
            <div id="selected_movie_preview" class="hidden mb-5 p-3 bg-blue-900/10 border border-blue-500/20 rounded-xl flex items-center gap-4">
                <img id="sel_poster" src="" class="w-12 h-16 object-cover rounded shadow">
                <div class="flex-1 overflow-hidden">
                    <h4 id="sel_title" class="font-black text-white text-sm truncate"></h4>
                    <span id="sel_year" class="text-xs font-bold bg-gray-800 text-gray-300 px-2 py-0.5 rounded mt-1 inline-block"></span>
                </div>
                <button type="button" onclick="clearSelectedMovie()" class="text-red-500 hover:text-red-400 p-2"><i class="fas fa-times"></i></button>
            </div>

            <!-- حقل اسم الفيلم (قابل للتعديل) -->
            <div class="mb-5">
                <label class="form-label font-bold text-white"><i class="fas fa-heading ml-1"></i> اسم الفيلم (يمكنك تعديله)</label>
                <input type="text" id="movie_title_input" class="form-input w-full font-bold text-[#DAA520]" placeholder="سيظهر اسم الفيلم هنا لتعديله...">
            </div>

            <!-- حقل قصة الفيلم (قابل للتعديل) -->
            <div class="mb-5">
                <label class="form-label font-bold text-[#DAA520]"><i class="fas fa-align-right ml-1"></i> قصة الفيلم (يمكنك تعديلها)</label>
                <textarea id="movie_description_input" class="form-input w-full h-24 resize-none" placeholder="اكتب أو عدل قصة الفيلم هنا..."></textarea>
            </div>

            <!-- الرابط السحري -->
            <div class="mb-6">
                <label class="form-label font-bold text-gray-300"><i class="fas fa-link ml-1"></i> رابط المشاهدة (سيتم توليد الجودات منه)</label>
                <input type="text" id="magic_url_input" class="form-input w-full" placeholder="مثال: .../film-2026-webdl-720p.mp4" dir="ltr">
                <p class="text-[10px] text-gray-500 mt-1">يجب أن يحتوي الرابط على إحدى الجودات (360, 480, 720, 1080) ليتم الاستبدال.</p>
            </div>

            <!-- زر الإضافة للقائمة -->
            <button type="button" onclick="addMovieToQueue()" class="btn bg-green-600 hover:bg-green-500 text-white font-black w-full py-3 shadow-[0_0_15px_rgba(22,163,74,0.3)]">
                <i class="fas fa-arrow-left ml-2"></i> إضافة للجدول المنتظر
            </button>
        </div>
    </div>

    <!-- القسم الأيسر: طابور الأفلام (القائمة) -->
    <div class="lg:col-span-7 bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl flex flex-col h-full min-h-[500px]">
        <div class="flex justify-between items-center mb-6 border-b border-gray-800 pb-4">
            <h2 class="text-xl font-bold text-white"><i class="fas fa-list-ol text-[#DAA520] ml-2"></i> قائمة الأفلام الجاهزة للرفع</h2>
            <span id="queue_counter" class="bg-blue-500/20 text-blue-400 px-3 py-1 rounded-full font-bold text-sm">0 فيلم</span>
        </div>

        <!-- الجدول -->
        <div class="flex-1 overflow-x-auto w-full">
            <table class="content-table w-full min-w-[500px]">
                <thead>
                    <tr>
                        <th width="60">البوستر</th>
                        <th>الفيلم والفئة</th>
                        <th>الخصائص</th>
                        <th width="50">إلغاء</th>
                    </tr>
                </thead>
                <tbody id="movies_queue_body">
                    <tr id="empty_queue_row">
                        <td colspan="4" class="text-center py-12 text-gray-500 font-bold">
                            <i class="fas fa-inbox text-4xl mb-3 block opacity-30"></i>
                            القائمة فارغة. ابحث عن فيلم وأضفه من اللوحة الجانبية.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- زر الحفظ النهائي -->
        <div class="mt-6 pt-4 border-t border-gray-800">
            <button type="button" id="save_all_btn" onclick="saveAllToDatabase()" class="btn btn-primary w-full py-4 text-lg hidden shadow-[0_0_20px_rgba(218,165,32,0.4)]">
                <i class="fas fa-cloud-upload-alt ml-2"></i> حفظ جميع الأفلام في قاعدة البيانات
            </button>
        </div>
    </div>
</div>

<script>
    const TMDb_API_KEY = '<?php echo $tmdb_api_key; ?>';
    let currentSelectedMovieData = null; // سيخزن بيانات الفيلم
    let moviesQueue = []; // سلة الأفلام الجاهزة للحفظ

    function showToast(msg, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `custom-toast ${type}`;
        toast.innerHTML = `<i class="fas ${type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'} ml-2"></i> ${msg}`;
        document.body.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 300); }, 3000);
    }

    // 1. نظام البحث في TMDb
    const searchBtn = document.getElementById('tmdb_search_btn');
    const searchInput = document.getElementById('tmdb_search_input');
    const resultsDiv = document.getElementById('tmdb_results');

    searchBtn.addEventListener('click', async () => {
        const q = searchInput.value.trim();
        if (!q) return;
        
        resultsDiv.innerHTML = '<div class="text-center text-gray-400 py-4"><i class="fas fa-spinner fa-spin"></i> جاري البحث...</div>';
        resultsDiv.classList.remove('hidden');

        try {
            let results = [];

            // أ) ميزة البحث الذكي برقم (TMDb ID) أو (IMDb ID) للوصول المباشر والدقيق
            if (/^\d+$/.test(q) || /^tt\d+$/.test(q)) {
                let searchUrl = '';
                if (/^tt\d+$/.test(q)) {
                    // بحث عبر رقم IMDb
                    searchUrl = `https://api.themoviedb.org/3/find/${q}?api_key=${TMDb_API_KEY}&external_source=imdb_id&language=ar-EG`;
                    const r = await fetch(searchUrl);
                    const d = await r.json();
                    if (d.movie_results && d.movie_results.length > 0) results = d.movie_results;
                } else {
                    // بحث عبر رقم TMDb
                    searchUrl = `https://api.themoviedb.org/3/movie/${q}?api_key=${TMDb_API_KEY}&language=ar-EG`;
                    const r = await fetch(searchUrl);
                    if (r.ok) {
                        const d = await r.json();
                        results = [d]; // تحويله لمصفوفة ليتم عرضه كالعادة
                    }
                }
            } else {
                // ب) البحث بالاسم (يبدأ بالعربي)
                const rAr = await fetch(`https://api.themoviedb.org/3/search/movie?api_key=${TMDb_API_KEY}&query=${encodeURIComponent(q)}&language=ar-EG&page=1`);
                const dAr = await rAr.json();
                results = dAr.results || [];

                // ج) إذا لم يجد بالعربي، يبحث بالإنجليزي كاحتياطي تلقائي
                if (results.length === 0) {
                    const rEn = await fetch(`https://api.themoviedb.org/3/search/movie?api_key=${TMDb_API_KEY}&query=${encodeURIComponent(q)}&language=en-US&page=1`);
                    const dEn = await rEn.json();
                    results = dEn.results || [];
                }
            }
            
            resultsDiv.innerHTML = '';
            if (!results || results.length === 0) {
                resultsDiv.innerHTML = '<div class="text-center text-red-400 py-4 font-bold text-sm">لم يتم العثور على نتائج.<br><span class="text-gray-400 text-xs">جرب البحث بالاسم الإنجليزي أو اكتب رقم (ID) الفيلم من موقع TMDb.</span></div>';
                return;
            }

            // عرض 10 نتائج بدلاً من 5 لإعطاء خيارات أكثر
            results.slice(0, 10).forEach(m => {
                const item = document.createElement('div');
                item.className = 'flex items-center gap-3 p-2 hover:bg-white/10 cursor-pointer rounded-lg border-b border-gray-800 transition-colors mb-1';
                const poster = m.poster_path ? `https://image.tmdb.org/t/p/w92${m.poster_path}` : 'https://placehold.co/92x138?text=No+Image';
                const year = m.release_date ? m.release_date.split('-')[0] : 'N/A';
                
                item.innerHTML = `
                    <img src="${poster}" class="w-10 h-14 rounded object-cover">
                    <div>
                        <div class="text-sm font-bold text-[#DAA520] truncate max-w-[200px]">${m.title}</div>
                        <div class="text-xs text-gray-400 mt-1">${year} | ⭐ ${m.vote_average ? m.vote_average.toFixed(1) : 'N/A'}</div>
                    </div>
                `;
                item.onclick = () => fetchMovieDetails(m.id);
                resultsDiv.appendChild(item);
            });
        } catch (e) {
            resultsDiv.innerHTML = '<div class="text-center text-red-400 py-4 font-bold text-sm">حدث خطأ في الاتصال.</div>';
        }
    });

    searchInput.addEventListener('keydown', e => { if (e.key === 'Enter') searchBtn.click(); });

    async function fetchMovieDetails(id) {
        resultsDiv.innerHTML = '<div class="text-center text-green-400 py-4"><i class="fas fa-spinner fa-spin"></i> جاري التجهيز...</div>';
        try {
            const r = await fetch(`https://api.themoviedb.org/3/movie/${id}?api_key=${TMDb_API_KEY}&language=ar-EG&append_to_response=videos,credits`);
            const d = await r.json();

            let trailerKey = '';
            if (d.videos && d.videos.results) {
                const tr = d.videos.results.find(v => v.type === 'Trailer');
                if (tr) trailerKey = `https://www.youtube.com/watch?v=${tr.key}`;
            }

            let castDataStr = '';
            if (d.credits && d.credits.cast) {
                const castArray = d.credits.cast.slice(0, 10).map(actor => ({
                    name: actor.name, character: actor.character,
                    image: actor.profile_path ? `https://image.tmdb.org/t/p/w185${actor.profile_path}` : `https://ui-avatars.com/api/?name=${encodeURIComponent(actor.name)}&background=262626&color=DAA520&size=200`
                }));
                castDataStr = JSON.stringify(castArray);
            }

            // استخراج الوصف، وإن كان فارغاً نتركه فارغاً ليكتبه المستخدم
            const finalDescription = (d.overview && d.overview.trim() !== '') ? d.overview : '';

            currentSelectedMovieData = {
                title: d.title,
                year: d.release_date ? d.release_date.split('-')[0] : '',
                release_date: d.release_date || '',
                rating: d.vote_average ? d.vote_average.toFixed(1) : '0',
                genre: d.genres ? d.genres.map(g => g.name).join('، ') : '',
                poster: d.poster_path ? `https://image.tmdb.org/t/p/w500${d.poster_path}` : '',
                trailer_link: trailerKey,
                cast_data: castDataStr
            };

            resultsDiv.classList.add('hidden');
            searchInput.value = '';
            
            document.getElementById('sel_poster').src = currentSelectedMovieData.poster || 'https://placehold.co/92x138?text=No+Image';
            document.getElementById('sel_title').innerText = currentSelectedMovieData.title;
            document.getElementById('sel_year').innerText = currentSelectedMovieData.year;
            
            // وضع القصة في مربع النص ليتمكن المستخدم من مراجعتها أو تعديلها
            document.getElementById('movie_description_input').value = finalDescription;
            
            // وضع اسم الفيلم في مربع النص ليتمكن المستخدم من مراجعته أو تعديله
            document.getElementById('movie_title_input').value = currentSelectedMovieData.title;

            document.getElementById('selected_movie_preview').classList.remove('hidden');
            document.getElementById('magic_url_input').focus();
            
            showToast('تم اختيار الفيلم بنجاح.');
        } catch(e) {
            showToast('حدث خطأ أثناء جلب التفاصيل.', 'error');
        }
    }

    function clearSelectedMovie() {
        currentSelectedMovieData = null;
        document.getElementById('selected_movie_preview').classList.add('hidden');
    }

    function generateMagicLinks(url) {
        const regex = /(360|480|720|1080)/g;
        let matches = [...url.matchAll(regex)];
        
        if (matches.length === 0) return null;
        
        const lastMatch = matches[matches.length - 1];
        const matchIndex = lastMatch.index;
        const matchLength = lastMatch[0].length;
        
        const stringBefore = url.substring(0, matchIndex);
        const stringAfter = url.substring(matchIndex + matchLength);
        
        const qualities = [360, 480, 720, 1080];
        const generatedLinks = [];
        
        qualities.forEach(q => {
            generatedLinks.push(`${q}|${stringBefore}${q}${stringAfter}`);
        });
        
        return generatedLinks.join(','); 
    }

    function addMovieToQueue() {
        if (!currentSelectedMovieData) {
            showToast('يرجى البحث عن فيلم واختياره أولاً.', 'error');
            return;
        }

        const rawUrl = document.getElementById('magic_url_input').value.trim();
        if (!rawUrl) {
            showToast('يرجى وضع رابط الفيلم لاستخراج الجودات.', 'error');
            return;
        }

        const magicLinkResult = generateMagicLinks(rawUrl);
        if (!magicLinkResult) {
            showToast('لم يتم العثور على جودة (360, 480, 720, 1080) في الرابط لاستبدالها.', 'error');
            return;
        }

        // سحب البيانات من حقول الواجهة الجديدة
        const categorySelect = document.getElementById('movie_category');
        const qualitySelect = document.getElementById('movie_quality'); // الجودة
        const isRecentCheckbox = document.getElementById('movie_is_recent'); // أضيف حديثا
        const customDescription = document.getElementById('movie_description_input').value.trim(); // القصة المعدلة
        const customTitle = document.getElementById('movie_title_input').value.trim(); // الاسم المعدل

        if (!customTitle) {
            showToast('يرجى إدخال اسم الفيلم.', 'error');
            return;
        }

        const movieToSave = {
            ...currentSelectedMovieData,
            title: customTitle, // استخدام الاسم المعدل
            description: customDescription || 'لا توجد قصة متوفرة لهذا الفيلم حالياً.', // استخدام القصة المكتوبة
            magic_link: magicLinkResult,
            category: categorySelect.value,
            category_text: categorySelect.options[categorySelect.selectedIndex].text,
            quality: qualitySelect.value, 
            is_recent: isRecentCheckbox.checked, 
            unique_id: Date.now() 
        };

        moviesQueue.push(movieToSave);
        renderQueueTable();
        
        clearSelectedMovie();
        document.getElementById('magic_url_input').value = '';
        document.getElementById('movie_description_input').value = ''; // تفريغ حقل القصة للفيلم القادم
        document.getElementById('movie_title_input').value = ''; // تفريغ حقل الاسم للفيلم القادم
        
        // إرجاع الإعدادات للوضع الافتراضي
        isRecentCheckbox.checked = false; 
        
        showToast('تم إدراج الفيلم في قائمة الحفظ بنجاح.');
    }

    function renderQueueTable() {
        const tbody = document.getElementById('movies_queue_body');
        const counter = document.getElementById('queue_counter');
        const saveBtn = document.getElementById('save_all_btn');
        
        counter.innerText = `${moviesQueue.length} فيلم`;

        if (moviesQueue.length === 0) {
            tbody.innerHTML = `
                <tr id="empty_queue_row">
                    <td colspan="4" class="text-center py-12 text-gray-500 font-bold">
                        <i class="fas fa-inbox text-4xl mb-3 block opacity-30"></i>
                        القائمة فارغة. ابحث عن فيلم وأضفه من اللوحة الجانبية.
                    </td>
                </tr>`;
            saveBtn.classList.add('hidden');
            return;
        }

        saveBtn.classList.remove('hidden');
        tbody.innerHTML = '';

        moviesQueue.forEach(movie => {
            const recentBadge = movie.is_recent 
                ? '<span class="text-[10px] bg-blue-500/20 text-blue-400 px-2 py-0.5 rounded mr-1">يظهر كحديث</span>' 
                : '';
                
            const tr = document.createElement('tr');
            tr.className = 'movie-queue-row group bg-[#151515] hover:bg-[#1a1a1a] transition-colors border-b border-[#222]';
            
            tr.innerHTML = `
                <td class="p-2"><img src="${movie.poster}" class="w-10 h-14 rounded object-cover shadow border border-gray-700"></td>
                <td class="font-bold text-white">
                    <div class="truncate text-sm mb-1">${movie.title} <span class="text-xs text-gray-500 font-normal">(${movie.year})</span></div>
                    <span class="text-[10px] bg-brand-gold/20 text-brand-gold px-2 py-0.5 rounded border border-brand-gold/30">${movie.category_text}</span>
                </td>
                <td>
                    <div class="flex flex-col gap-1 items-start">
                        <span class="text-[10px] bg-gray-700 text-white px-2 py-1 rounded font-bold">الجودة: ${movie.quality}</span>
                        <div class="flex">${recentBadge}</div>
                    </div>
                </td>
                <td>
                    <button type="button" onclick="removeFromQueue(${movie.unique_id})" class="text-red-500 hover:text-red-400 p-2 bg-red-500/10 rounded-lg transition-colors">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function removeFromQueue(uniqueId) {
        moviesQueue = moviesQueue.filter(m => m.unique_id !== uniqueId);
        renderQueueTable();
    }

    async function saveAllToDatabase() {
        if (moviesQueue.length === 0) return;

        const btn = document.getElementById('save_all_btn');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin ml-2"></i> جاري الحفظ...';
        btn.disabled = true;

        const formData = new FormData();
        formData.append('save_bulk_movies', '1');
        formData.append('movies_json', JSON.stringify(moviesQueue));

        try {
            const response = await fetch('index.php?page=movies_bulk_add', { 
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                showToast(result.message, 'success');
                moviesQueue = []; 
                renderQueueTable(); 
            } else {
                showToast(result.message, 'error');
            }
        } catch (e) {
            showToast('حدث خطأ في الاتصال بالسيرفر أثناء الحفظ.', 'error');
            console.error(e);
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }
</script>