<?php
// ==========================================
// ملف الإضافة السريعة للمسلسلات والمولد الذكي للحلقات
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_bulk_series'])) {
    header('Content-Type: application/json');
    $payload = json_decode($_POST['payload'], true);
    
    if (empty($payload) || empty($payload['series']) || empty($payload['episodes'])) {
        echo json_encode(['success' => false, 'message' => 'بيانات مفقودة. لا توجد حلقات للحفظ.']);
        exit;
    }

    $series = $payload['series'];
    $episodes = $payload['episodes'];
    $inserted_episodes_count = 0;
    $series_id = 0;

    // 1. التحقق مما إذا كان المسلسل موجوداً بالفعل (عن طريق الاسم)
    $stmt_check = $conn->prepare("SELECT id FROM series WHERE title = ?");
    $stmt_check->bind_param("s", $series['title']);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result();

    if ($res_check->num_rows > 0) {
        // المسلسل موجود، سنأخذ الـ ID الخاص به
        $series_id = $res_check->fetch_assoc()['id'];
    } else {
        // المسلسل غير موجود، سنقوم بإنشائه أولاً
        $is_recent = $series['is_recent'] ? 1 : 0;
        
        $stmt_insert = $conn->prepare("INSERT INTO series (title, year, release_date, genre, poster, description, rating, is_recent, is_published, cast_data, category) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)");
        
        $stmt_insert->bind_param(
            "sisssssiss", 
            $series['title'], 
            $series['year'], 
            $series['release_date'], 
            $series['genre'], 
            $series['poster'], 
            $series['description'], 
            $series['rating'], 
            $is_recent, 
            $series['cast_data'], 
            $series['category']
        );
        
        if ($stmt_insert->execute()) {
            $series_id = $conn->insert_id;
        } else {
            echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء حفظ بيانات المسلسل الأساسية.']);
            exit;
        }
    }

    // 2. إدراج أو تحديث الحلقات المرتبطة بهذا المسلسل
    $stmt_ep = $conn->prepare("INSERT INTO episodes (series_id, title, watch_link, download_link, episode_number, is_published, show_in_latest) VALUES (?, ?, ?, ?, ?, 1, 1)");

    foreach ($episodes as $ep) {
        $ep_num = intval($ep['number']);
        $magic_link = $ep['magic_link'];
        $ep_title = "الحلقة " . $ep_num;

        // التحقق مما إذا كانت الحلقة موجودة لنفس المسلسل لتحديثها أو إضافتها
        $chk_ep = $conn->query("SELECT id FROM episodes WHERE series_id = $series_id AND episode_number = $ep_num");
        
        if ($chk_ep->num_rows > 0) {
            // تحديث الحلقة الموجودة
            $stmt_upd = $conn->prepare("UPDATE episodes SET watch_link = ?, download_link = ? WHERE series_id = ? AND episode_number = ?");
            $stmt_upd->bind_param("ssii", $magic_link, $magic_link, $series_id, $ep_num);
            if($stmt_upd->execute()) $inserted_episodes_count++;
        } else {
            // إدراج حلقة جديدة
            $stmt_ep->bind_param("isssi", $series_id, $ep_title, $magic_link, $magic_link, $ep_num);
            if($stmt_ep->execute()) $inserted_episodes_count++;
        }
    }
    
    echo json_encode(['success' => true, 'message' => "تم حفظ المسلسل وتوليد $inserted_episodes_count حلقة بنجاح!"]);
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
        <i class="fas fa-bolt text-[#DAA520] ml-2"></i> المولد الذكي للمسلسلات والحلقات
    </h1>
    <a href="index.php?page=series" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-right ml-1"></i> العودة للمسلسلات</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
    
    <!-- القسم الأيمن: لوحة الإدخال والبحث -->
    <div class="lg:col-span-5 sticky top-4 z-40">
        <div class="bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl smart-box mb-6">
            <h2 class="text-xl font-bold mb-6 text-white"><i class="fas fa-tv text-blue-400 ml-2"></i> 1. جهز بيانات المسلسل</h2>
            
            <div class="mb-5">
                <label class="form-label text-[#DAA520] font-bold"><i class="fas fa-folder-open ml-1"></i> فئة المسلسل</label>
                <select id="series_category" class="form-select border-[#DAA520] w-full">
                    <option value="series">مسلسل عربي</option>
                    <option value="foreign">مسلسل أجنبي</option>
                    <option value="turkish">مسلسل تركي</option>
                    <option value="indian">مسلسل هندي</option>
                    <option value="tv_show">برنامج تلفزيوني</option>
                </select>
            </div>

            <!-- خيار "أضيف حديثاً" للمسلسل -->
            <div class="mb-5 bg-gray-800/50 p-3 rounded-lg border border-gray-700">
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" id="series_is_recent" class="form-checkbox h-5 w-5 text-blue-600 rounded bg-gray-900 border-gray-600 ml-3">
                    <span class="text-gray-300 font-bold text-sm">عرض المسلسل في قسم "أضيف حديثاً"</span>
                </label>
            </div>

            <!-- البحث في TMDb (TV) -->
            <div class="mb-5">
                <label class="form-label font-bold text-gray-300"><i class="fas fa-search ml-1"></i> ابحث عن المسلسل (TMDb)</label>
                <div class="flex gap-2">
                    <input type="text" id="tmdb_search_input" class="form-input flex-1" placeholder="اكتب اسم المسلسل أو الـ ID...">
                    <button type="button" id="tmdb_search_btn" class="btn btn-primary px-4"><i class="fas fa-search"></i></button>
                </div>
                <div id="tmdb_results" class="mt-3 max-h-48 overflow-y-auto rounded-lg bg-black/30 border border-gray-800 hidden p-2 custom-scrollbar"></div>
            </div>

            <!-- المسلسل المختار -->
            <div id="selected_series_preview" class="hidden mb-5 p-3 bg-blue-900/10 border border-blue-500/20 rounded-xl flex items-center gap-4">
                <img id="sel_poster" src="" class="w-12 h-16 object-cover rounded shadow">
                <div class="flex-1 overflow-hidden">
                    <h4 id="sel_title" class="font-black text-white text-sm truncate"></h4>
                    <span id="sel_year" class="text-xs font-bold bg-gray-800 text-gray-300 px-2 py-0.5 rounded mt-1 inline-block"></span>
                </div>
                <button type="button" onclick="clearSelectedSeries()" class="text-red-500 hover:text-red-400 p-2"><i class="fas fa-times"></i></button>
            </div>

            <!-- الاسم والوصف (قابلين للتعديل) -->
            <div class="mb-5">
                <label class="form-label font-bold text-white">اسم المسلسل (قابل للتعديل)</label>
                <input type="text" id="series_title_input" class="form-input w-full font-bold text-[#DAA520]">
            </div>
            <div class="mb-5">
                <label class="form-label font-bold text-[#DAA520]">قصة المسلسل (قابلة للتعديل)</label>
                <textarea id="series_description_input" class="form-input w-full h-20 resize-none"></textarea>
            </div>
        </div>

        <div class="bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl smart-box">
            <h2 class="text-xl font-bold mb-6 text-white"><i class="fas fa-magic text-emerald-400 ml-2"></i> 2. المولد السحري للحلقات</h2>
            
            <div class="grid grid-cols-2 gap-4 mb-5">
                <div>
                    <label class="form-label text-sm text-gray-300">من حلقة رقم</label>
                    <input type="number" id="ep_start" class="form-input w-full text-center font-bold text-lg" value="1" min="1">
                </div>
                <div>
                    <label class="form-label text-sm text-gray-300">إلى حلقة رقم</label>
                    <input type="number" id="ep_end" class="form-input w-full text-center font-bold text-lg" value="10" min="1">
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label font-bold text-gray-300">رابط إحدى الحلقات (سيتم تحليل النمط تلقائياً)</label>
                <input type="text" id="magic_url_input" oninput="analyzeLink()" class="form-input w-full" placeholder="مثال: .../S01-EP008-480p.mp4" dir="ltr">
            </div>

            <div id="template_preview_box" class="mb-6 bg-gray-900 p-3 rounded-lg border border-gray-700 hidden">
                <div class="text-xs text-emerald-400 font-bold mb-1"><i class="fas fa-check-circle ml-1"></i> شكل القالب بعد التحليل (يمكنك تصحيحه يدوياً):</div>
                <input type="text" id="url_template_input" class="form-input w-full text-xs text-gray-400" dir="ltr">
                <div class="text-[10px] text-gray-500 mt-2">يرمز <b>{EP}</b> لمكان رقم الحلقة، و <b>{Q}</b> لمكان الجودة.</div>
            </div>

            <button type="button" onclick="generateEpisodes()" class="btn bg-green-600 hover:bg-green-500 text-white font-black w-full py-3 shadow-[0_0_15px_rgba(22,163,74,0.3)]">
                <i class="fas fa-cogs ml-2"></i> توليد الحلقات وإضافتها للجدول
            </button>
        </div>
    </div>

    <!-- القسم الأيسر: طابور الحلقات -->
    <div class="lg:col-span-7 bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl flex flex-col h-full min-h-[600px]">
        <div class="flex justify-between items-center mb-6 border-b border-gray-800 pb-4">
            <h2 class="text-xl font-bold text-white"><i class="fas fa-list-ol text-[#DAA520] ml-2"></i> الحلقات الجاهزة للحفظ</h2>
            <span id="queue_counter" class="bg-blue-500/20 text-blue-400 px-3 py-1 rounded-full font-bold text-sm">0 حلقة</span>
        </div>

        <div class="flex-1 overflow-x-auto w-full">
            <table class="content-table w-full min-w-[500px]">
                <thead>
                    <tr>
                        <th width="80">رقم الحلقة</th>
                        <th>الحالة / الخصائص</th>
                        <th width="50">حذف</th>
                    </tr>
                </thead>
                <tbody id="episodes_queue_body">
                    <tr id="empty_queue_row">
                        <td colspan="3" class="text-center py-12 text-gray-500 font-bold">
                            <i class="fas fa-inbox text-4xl mb-3 block opacity-30"></i>
                            لم يتم توليد أي حلقات بعد.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-6 pt-4 border-t border-gray-800">
            <button type="button" id="save_all_btn" onclick="saveAllToDatabase()" class="btn btn-primary w-full py-4 text-lg hidden shadow-[0_0_20px_rgba(218,165,32,0.4)]">
                <i class="fas fa-cloud-upload-alt ml-2"></i> حفظ المسلسل والحلقات في قاعدة البيانات
            </button>
        </div>
    </div>
</div>

<script>
    const TMDb_API_KEY = '<?php echo $tmdb_api_key; ?>';
    let currentSeriesData = null; 
    let generatedEpisodesQueue = []; 
    let detectedPadding = 1;

    function showToast(msg, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `custom-toast ${type}`;
        toast.innerHTML = `<i class="fas ${type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'} ml-2"></i> ${msg}`;
        document.body.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 300); }, 3000);
    }

    // 1. نظام البحث المخصص للمسلسلات في TMDb
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
            // بحث ذكي برقم الـ ID (TMDb ID)
            if (/^\d+$/.test(q)) {
                const r = await fetch(`https://api.themoviedb.org/3/tv/${q}?api_key=${TMDb_API_KEY}&language=ar-EG`);
                if (r.ok) {
                    const d = await r.json();
                    results = [d]; 
                }
            } else {
                // بحث بالاسم
                const rAr = await fetch(`https://api.themoviedb.org/3/search/tv?api_key=${TMDb_API_KEY}&query=${encodeURIComponent(q)}&language=ar-EG&page=1`);
                const dAr = await rAr.json();
                results = dAr.results || [];
                
                if (results.length === 0) {
                    const rEn = await fetch(`https://api.themoviedb.org/3/search/tv?api_key=${TMDb_API_KEY}&query=${encodeURIComponent(q)}&language=en-US&page=1`);
                    const dEn = await rEn.json();
                    results = dEn.results || [];
                }
            }
            
            resultsDiv.innerHTML = '';
            if (!results || results.length === 0) {
                resultsDiv.innerHTML = '<div class="text-center text-red-400 py-4 font-bold text-sm">لم يتم العثور على المسلسل.</div>';
                return;
            }

            results.slice(0, 10).forEach(s => {
                const item = document.createElement('div');
                item.className = 'flex items-center gap-3 p-2 hover:bg-white/10 cursor-pointer rounded-lg border-b border-gray-800 transition-colors mb-1';
                const poster = s.poster_path ? `https://image.tmdb.org/t/p/w92${s.poster_path}` : 'https://placehold.co/92x138?text=No+Image';
                const year = s.first_air_date ? s.first_air_date.split('-')[0] : 'N/A';
                // TMDb يستخدم name للمسلسلات وليس title
                item.innerHTML = `
                    <img src="${poster}" class="w-10 h-14 rounded object-cover">
                    <div>
                        <div class="text-sm font-bold text-[#DAA520] truncate max-w-[200px]">${s.name || s.original_name}</div>
                        <div class="text-xs text-gray-400 mt-1">${year} | ⭐ ${s.vote_average ? s.vote_average.toFixed(1) : 'N/A'}</div>
                    </div>
                `;
                item.onclick = () => fetchSeriesDetails(s.id);
                resultsDiv.appendChild(item);
            });
        } catch (e) {
            resultsDiv.innerHTML = '<div class="text-center text-red-400 py-4 font-bold text-sm">حدث خطأ في الاتصال.</div>';
        }
    });
    searchInput.addEventListener('keydown', e => { if (e.key === 'Enter') searchBtn.click(); });

    async function fetchSeriesDetails(id) {
        resultsDiv.innerHTML = '<div class="text-center text-green-400 py-4"><i class="fas fa-spinner fa-spin"></i> جاري التجهيز...</div>';
        try {
            const r = await fetch(`https://api.themoviedb.org/3/tv/${id}?api_key=${TMDb_API_KEY}&language=ar-EG&append_to_response=credits`);
            const d = await r.json();

            let castDataStr = '';
            if (d.credits && d.credits.cast) {
                const castArray = d.credits.cast.slice(0, 10).map(actor => ({
                    name: actor.name, character: actor.character,
                    image: actor.profile_path ? `https://image.tmdb.org/t/p/w185${actor.profile_path}` : `https://ui-avatars.com/api/?name=${encodeURIComponent(actor.name)}&background=262626&color=DAA520&size=200`
                }));
                castDataStr = JSON.stringify(castArray);
            }

            const finalDesc = (d.overview && d.overview.trim() !== '') ? d.overview : 'لا توجد قصة متوفرة لهذا المسلسل حالياً.';

            currentSeriesData = {
                title: d.name,
                year: d.first_air_date ? d.first_air_date.split('-')[0] : '',
                release_date: d.first_air_date || '',
                rating: d.vote_average ? d.vote_average.toFixed(1) : '0',
                genre: d.genres ? d.genres.map(g => g.name).join('، ') : '',
                poster: d.poster_path ? `https://image.tmdb.org/t/p/w500${d.poster_path}` : '',
                cast_data: castDataStr
            };

            resultsDiv.classList.add('hidden');
            searchInput.value = '';
            
            document.getElementById('sel_poster').src = currentSeriesData.poster || 'https://placehold.co/92x138?text=No+Image';
            document.getElementById('sel_title').innerText = currentSeriesData.title;
            document.getElementById('sel_year').innerText = currentSeriesData.year;
            
            document.getElementById('series_title_input').value = currentSeriesData.title;
            document.getElementById('series_description_input').value = finalDesc;
            
            document.getElementById('selected_series_preview').classList.remove('hidden');
            showToast('تم اختيار المسلسل بنجاح.');
        } catch(e) {
            showToast('حدث خطأ أثناء جلب التفاصيل.', 'error');
        }
    }

    function clearSelectedSeries() {
        currentSeriesData = null;
        document.getElementById('selected_series_preview').classList.add('hidden');
        document.getElementById('series_title_input').value = '';
        document.getElementById('series_description_input').value = '';
    }

    // 2. تحليل الرابط السحري أوتوماتيكياً
    function analyzeLink() {
        const rawUrl = document.getElementById('magic_url_input').value.trim();
        const templateBox = document.getElementById('template_preview_box');
        const templateInput = document.getElementById('url_template_input');
        
        if (!rawUrl) {
            templateBox.classList.add('hidden');
            return;
        }

        let template = rawUrl;
        detectedPadding = 1; // الافتراضي
        
        // أ) البحث عن الجودة (أخر جودة موجودة في الرابط)
        const qRegex = /(360|480|720|1080)(?!.*\d)/;
        let qMatch = template.match(qRegex);
        if (qMatch) {
            let idx = template.lastIndexOf(qMatch[0]);
            template = template.substring(0, idx) + "{Q}" + template.substring(idx + qMatch[0].length);
        }

        // ب) البحث عن رقم الحلقة الذكي
        const epRegex = /(S\d+[-_]?E|S\d+[-_]?EP|E|EP|Episode[-_]?)(\d+)/i;
        let epMatch = template.match(epRegex);
        if (epMatch) {
            detectedPadding = epMatch[2].length; // معرفة كم صفر (مثلاً 008 يعني الطول 3)
            let prefix = epMatch[1];
            let targetToReplace = prefix + epMatch[2];
            let replacement = prefix + "{EP}";
            template = template.replace(targetToReplace, replacement);
        }

        templateInput.value = template;
        templateBox.classList.remove('hidden');
    }

    // 3. توليد الحلقات وإرسالها للجدول
    function generateEpisodes() {
        if (!currentSeriesData) {
            showToast('يرجى اختيار المسلسل أولاً من الخطوة 1.', 'error');
            return;
        }

        const template = document.getElementById('url_template_input').value.trim();
        if (!template || !template.includes('{Q}')) {
            showToast('لم يتم العثور على جودة في الرابط. تأكد من أن القالب يحتوي على {Q}.', 'error');
            return;
        }
        
        const startEp = parseInt(document.getElementById('ep_start').value);
        const endEp = parseInt(document.getElementById('ep_end').value);
        
        if (startEp > endEp || startEp < 1) {
            showToast('نطاق الحلقات غير صحيح.', 'error');
            return;
        }

        const qualitiesList = [360, 480, 720, 1080];
        let newEpisodes = [];

        for (let i = startEp; i <= endEp; i++) {
            // تنسيق رقم الحلقة بالأصفار (Padding) إذا كان الرابط الأصلي به أصفار
            let epNumStr = i.toString().padStart(detectedPadding, '0');
            
            // استبدال رقم الحلقة
            let epTemplate = template.includes('{EP}') ? template.replace('{EP}', epNumStr) : template;
            
            // توليد الروابط بالجودات المختلفة
            let generatedLinksArray = [];
            qualitiesList.forEach(q => {
                generatedLinksArray.push(`${q}|${epTemplate.replace('{Q}', q)}`);
            });
            
            let finalMagicLink = generatedLinksArray.join(',');

            newEpisodes.push({
                number: i,
                magic_link: finalMagicLink,
                unique_id: `ep_${Date.now()}_${i}`
            });
        }

        // إضافة الحلقات للطابور (بدون تكرار لو تم توليد نفس الحلقة مرتين عن طريق الخطأ)
        newEpisodes.forEach(newEp => {
            const exists = generatedEpisodesQueue.findIndex(e => e.number === newEp.number);
            if (exists >= 0) generatedEpisodesQueue[exists] = newEp; // استبدال إذا كانت موجودة
            else generatedEpisodesQueue.push(newEp);
        });

        // ترتيب الطابور حسب رقم الحلقة
        generatedEpisodesQueue.sort((a, b) => a.number - b.number);
        
        renderQueueTable();
        showToast(`تم توليد ${newEpisodes.length} حلقة بنجاح!`);
    }

    function renderQueueTable() {
        const tbody = document.getElementById('episodes_queue_body');
        const counter = document.getElementById('queue_counter');
        const saveBtn = document.getElementById('save_all_btn');
        
        counter.innerText = `${generatedEpisodesQueue.length} حلقة`;

        if (generatedEpisodesQueue.length === 0) {
            tbody.innerHTML = `
                <tr id="empty_queue_row">
                    <td colspan="3" class="text-center py-12 text-gray-500 font-bold">
                        <i class="fas fa-inbox text-4xl mb-3 block opacity-30"></i>
                        لم يتم توليد أي حلقات بعد.
                    </td>
                </tr>`;
            saveBtn.classList.add('hidden');
            return;
        }

        saveBtn.classList.remove('hidden');
        tbody.innerHTML = '';

        generatedEpisodesQueue.forEach(ep => {
            const tr = document.createElement('tr');
            tr.className = 'movie-queue-row bg-[#151515] hover:bg-[#1a1a1a] transition-colors border-b border-[#222]';
            
            tr.innerHTML = `
                <td class="text-center font-black text-2xl text-[#DAA520]">${ep.number}</td>
                <td>
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] bg-green-500/20 text-green-400 px-2 py-1 rounded font-bold border border-green-500/30">
                            <i class="fas fa-check-double mr-1"></i> تم توليد 4 جودات
                        </span>
                        <span class="text-[10px] bg-blue-500/20 text-blue-400 px-2 py-1 rounded font-bold">جاهزة للرفع</span>
                    </div>
                </td>
                <td class="text-center">
                    <button type="button" onclick="removeFromQueue('${ep.unique_id}')" class="text-red-500 hover:text-red-400 p-2 bg-red-500/10 rounded-lg transition-colors">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function removeFromQueue(uniqueId) {
        generatedEpisodesQueue = generatedEpisodesQueue.filter(e => e.unique_id !== uniqueId);
        renderQueueTable();
    }

    // 4. الحفظ النهائي في قاعدة البيانات (حفظ المسلسل + حلقاته)
    async function saveAllToDatabase() {
        if (generatedEpisodesQueue.length === 0 || !currentSeriesData) return;

        const customTitle = document.getElementById('series_title_input').value.trim();
        const customDesc = document.getElementById('series_description_input').value.trim();
        
        if (!customTitle) {
            showToast('اسم المسلسل لا يمكن أن يكون فارغاً.', 'error');
            return;
        }

        const btn = document.getElementById('save_all_btn');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin ml-2"></i> جاري حفظ المسلسل والحلقات...';
        btn.disabled = true;

        // تجهيز بيانات المسلسل النهائية
        const finalSeriesData = {
            ...currentSeriesData,
            title: customTitle,
            description: customDesc,
            category: document.getElementById('series_category').value,
            is_recent: document.getElementById('series_is_recent').checked
        };

        const payload = {
            series: finalSeriesData,
            episodes: generatedEpisodesQueue
        };

        const formData = new FormData();
        formData.append('save_bulk_series', '1');
        formData.append('payload', JSON.stringify(payload));

        try {
            const response = await fetch('index.php?page=series_bulk_add', { // تأكد من اسم الصفحة
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                showToast(result.message, 'success');
                generatedEpisodesQueue = [];
                renderQueueTable();
                
                // تفريغ إعدادات المسلسل للبدء من جديد
                clearSelectedSeries();
                document.getElementById('magic_url_input').value = '';
                document.getElementById('template_preview_box').classList.add('hidden');
                
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