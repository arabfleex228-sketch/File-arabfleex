<?php
$message = '';
$message_type = '';

// --- إضافة v40: منطق التنظيف الشامل وتحسين القاعدة ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deep_clean'])) {
    $steps_taken = [];
    
    // 1. تنظيف الطلبات المقروءة والقديمة (أقدم من شهرين)
    $two_months_ago = date('Y-m-d', strtotime('-2 month'));
    $stmt1 = $conn->prepare("DELETE FROM requests WHERE is_read = 1 AND created_at < ?");
    $stmt1->bind_param("s", $two_months_ago);
    $stmt1->execute();
    $steps_taken[] = "تم حذف " . $stmt1->affected_rows . " من الطلبات القديمة المقروءة.";
    $stmt1->close();

    // 2. حذف الحلقات اليتيمة (التي ليس لها مسلسل)
    $stmt2 = $conn->query("DELETE FROM episodes WHERE series_id NOT IN (SELECT id FROM series)");
    $steps_taken[] = "تم حذف " . $conn->affected_rows . " من الحلقات اليتيمة.";

    // 3. تحسين كافة الجداول (Optimize)
    $tables_res = $conn->query("SHOW TABLES");
    while($table = $tables_res->fetch_array()) {
        $conn->query("OPTIMIZE TABLE " . $table[0]);
    }
    $steps_taken[] = "تم تحسين هيكلة جميع جداول قاعدة البيانات.";

    $message = implode("<br>", $steps_taken);
    $message_type = 'success';
}

// المنطق الأصلي لتنظيف السجلات وتغيير كلمة المرور
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clean_logs'])) {
    $one_month_ago = date('Y-m-d', strtotime('-1 month'));
    $stmt = $conn->prepare("DELETE FROM visitor_log WHERE visit_date < ?");
    $stmt->bind_param("s", $one_month_ago);
    if ($stmt->execute()) {
        $message = "تم تنظيف " . $stmt->affected_rows . " سجل زيارات قديم.";
        $message_type = 'success';
    }
    $stmt->close();
}

// كود تغيير كلمة المرور 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $admin_id = $_SESSION['admin_id'];

    if ($new_password !== $confirm_password) {
        $message = 'كلمة المرور الجديدة غير متطابقة.';
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("SELECT password FROM admins WHERE id = ?");
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($result && password_verify($current_password, $result['password'])) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
            $update_stmt->bind_param("si", $hashed_password, $admin_id);
            if ($update_stmt->execute()) {
                $message = 'تم تغيير كلمة المرور بنجاح.';
                $message_type = 'success';
            }
            $update_stmt->close();
        } else {
            $message = 'كلمة المرور الحالية غير صحيحة.';
            $message_type = 'error';
        }
    }
}

// ==========================================
// --- تحديث إعدادات وضع الصيانة ---
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_maintenance'])) {
    $m_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
    $m_msg = trim($_POST['maintenance_msg']);
    
    // التأكد من وجود الجدول وإنشاؤه إن لم يوجد لتفادي الأخطاء
    $conn->query("CREATE TABLE IF NOT EXISTS `site_settings` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `maintenance_mode` tinyint(1) DEFAULT 0,
        `maintenance_msg` text DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $check = $conn->query("SELECT id FROM site_settings WHERE id = 1");
    if ($check && $check->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO site_settings (id, maintenance_mode, maintenance_msg) VALUES (1, ?, ?)");
        $stmt->bind_param("is", $m_mode, $m_msg);
    } else {
        $stmt = $conn->prepare("UPDATE site_settings SET maintenance_mode = ?, maintenance_msg = ? WHERE id = 1");
        $stmt->bind_param("is", $m_mode, $m_msg);
    }
    
    if ($stmt->execute()) {
        $message = 'تم تحديث إعدادات وضع الصيانة بنجاح.';
        $message_type = 'success';
    } else {
        $message = 'حدث خطأ أثناء تحديث وضع الصيانة.';
        $message_type = 'error';
    }
    $stmt->close();
}

// جلب بيانات الصيانة الحالية لعرضها في النموذج
$current_m_mode = 0;
$current_m_msg = "الموقع تحت الصيانة حالياً لتقديم تجربة أفضل، سنعود قريباً.";

// التأكد من وجود الجدول قبل الاستعلام المبدئي لتجنب أي أخطاء إذا كانت هذه أول مرة يتم فتح الصفحة فيها
$conn->query("CREATE TABLE IF NOT EXISTS `site_settings` (`id` int(11) NOT NULL AUTO_INCREMENT, `maintenance_mode` tinyint(1) DEFAULT 0, `maintenance_msg` text DEFAULT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$m_res = $conn->query("SELECT maintenance_mode, maintenance_msg FROM site_settings WHERE id = 1");
if ($m_res && $row = $m_res->fetch_assoc()) {
    $current_m_mode = $row['maintenance_mode'];
    if (!empty($row['maintenance_msg'])) {
        $current_m_msg = $row['maintenance_msg'];
    }
}
?>

<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-black text-white"><i class="fas fa-cog text-[#DAA520] ml-2"></i> الإعدادات وصيانة النظام</h1>
        <p class="text-gray-400 text-sm mt-1">إدارة حسابك وأدوات الحفاظ على أداء وسرعة المنصة</p>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="mb-6 p-4 rounded-xl text-center font-bold <?php echo $message_type == 'success' ? 'bg-green-500/10 text-green-500 border border-green-500/20' : 'bg-red-500/10 text-red-500 border border-red-500/20'; ?> shadow-lg">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<!-- قسم وضع الصيانة -->
<div class="bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl relative overflow-hidden mb-8 max-w-6xl mx-auto">
    <div class="absolute -left-6 -top-6 text-yellow-500/5 text-8xl pointer-events-none"><i class="fas fa-tools"></i></div>
    
    <h2 class="text-xl font-black mb-6 text-white relative z-10"><i class="fas fa-tools ml-2 text-[#DAA520]"></i>وضع الصيانة (Maintenance Mode)</h2>
    
    <form method="POST" class="relative z-10">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
            <div class="p-5 bg-[#131313] rounded-xl border border-[#2a2a2a] h-full flex flex-col justify-center">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="maintenance_mode" value="1" class="sr-only peer" <?php echo $current_m_mode ? 'checked' : ''; ?>>
                    <div class="w-14 h-7 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-[#DAA520]"></div>
                    <div class="mr-4">
                        <span class="block text-lg font-bold text-white mb-1">تفعيل وضع الصيانة</span>
                        <span class="text-xs text-gray-500 font-bold leading-tight block">عند التفعيل، سيتم إغلاق الموقع عن الزوار وعرض رسالة الصيانة.</span>
                    </div>
                </label>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-400 mb-2">رسالة الصيانة التي ستظهر للزوار</label>
                <textarea name="maintenance_msg" rows="3" class="w-full bg-[#131313] border border-[#2a2a2a] rounded-lg px-4 py-3 text-white focus:outline-none focus:border-[#DAA520] transition-colors resize-none" placeholder="اكتب رسالة الصيانة هنا..."><?php echo htmlspecialchars($current_m_msg); ?></textarea>
            </div>
        </div>
        <div class="mt-6 flex justify-end border-t border-[#1F1F1F] pt-4">
            <button type="submit" name="update_maintenance" class="bg-yellow-500/10 hover:bg-[#DAA520] text-[#DAA520] hover:text-black border border-[#DAA520]/50 hover:border-[#DAA520] font-bold py-2.5 px-8 rounded-lg transition-all shadow-lg">
                <i class="fas fa-save ml-1"></i> حفظ إعدادات الصيانة
            </button>
        </div>
    </form>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 max-w-6xl mx-auto">
    
    <!-- قسم تغيير كلمة المرور -->
    <div class="bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl relative overflow-hidden">
        <div class="absolute -left-6 -top-6 text-blue-500/5 text-8xl pointer-events-none"><i class="fas fa-shield-alt"></i></div>
        
        <h2 class="text-xl font-black mb-6 text-white relative z-10"><i class="fas fa-key ml-2 text-blue-500"></i>تغيير كلمة المرور</h2>
        <form method="POST" class="relative z-10">
            <div class="space-y-5">
                <div>
                    <label class="block text-sm font-bold text-gray-400 mb-2">كلمة المرور الحالية</label>
                    <input class="w-full bg-[#131313] border border-[#2a2a2a] rounded-lg px-4 py-3 text-white focus:outline-none focus:border-blue-500 transition-colors" type="password" name="current_password" required>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-400 mb-2">كلمة المرور الجديدة</label>
                    <input class="w-full bg-[#131313] border border-[#2a2a2a] rounded-lg px-4 py-3 text-white focus:outline-none focus:border-[#DAA520] transition-colors" type="password" name="new_password" required>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-400 mb-2">تأكيد كلمة المرور الجديدة</label>
                    <input class="w-full bg-[#131313] border border-[#2a2a2a] rounded-lg px-4 py-3 text-white focus:outline-none focus:border-[#DAA520] transition-colors" type="password" name="confirm_password" required>
                </div>
            </div>
            <button type="submit" name="change_password" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg mt-8 transition-colors shadow-lg">
                <i class="fas fa-save ml-1"></i> حفظ التغييرات
            </button>
        </form>
    </div>

    <!-- قسم أدوات الصيانة -->
    <div class="bg-[#0F0F0F] p-6 rounded-2xl border border-[#1F1F1F] shadow-xl relative overflow-hidden">
        <div class="absolute -left-6 -top-6 text-red-500/5 text-8xl pointer-events-none"><i class="fas fa-server"></i></div>
        
        <h2 class="text-xl font-black mb-6 text-white relative z-10"><i class="fas fa-database ml-2 text-[#DAA520]"></i>أدوات الصيانة</h2>
        
        <div class="space-y-6 relative z-10">
            <!-- التنظيف الشامل -->
            <div class="p-5 bg-[#131313] rounded-xl border border-red-500/20 hover:border-red-500/40 transition-colors group">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 rounded-full bg-red-500/10 flex items-center justify-center text-red-500 group-hover:scale-110 transition-transform">
                        <i class="fas fa-fire"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-red-400 text-lg">التنظيف الشامل (Deep Clean)</h3>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mb-4 font-bold pr-14">يقوم بحذف الحلقات اليتيمة، الطلبات القديمة جداً، ويعمل تحسين (Optimize) لجداول القاعدة لضمان أقصى سرعة.</p>
                <form method="POST" onsubmit="return confirm('هل أنت متأكد من إجراء عملية الصيانة الشاملة وقص البيانات القديمة؟');">
                    <button type="submit" name="deep_clean" class="w-full bg-red-500/20 hover:bg-red-600 text-red-400 hover:text-white border border-red-500/30 hover:border-red-600 font-bold py-2 rounded-lg transition-all">
                        <i class="fas fa-sparkles ml-1"></i> ابدأ التنظيف
                    </button>
                </form>
            </div>

            <!-- تفريغ السجلات -->
            <div class="p-5 bg-[#131313] rounded-xl border border-[#2a2a2a] hover:border-gray-500/40 transition-colors group">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 rounded-full bg-gray-500/10 flex items-center justify-center text-gray-400 group-hover:scale-110 transition-transform">
                        <i class="fas fa-broom"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-300 text-lg">تفريغ سجل الزوار</h3>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mb-4 font-bold pr-14">حذف سجلات الزيارات (Logs) التي مر عليها أكثر من شهر لتقليل حجم القاعدة وتسريع اللوحة.</p>
                <form method="POST">
                    <button type="submit" name="clean_logs" class="w-full bg-[#1a1a1a] hover:bg-gray-700 text-gray-300 font-bold py-2 border border-[#333] hover:border-gray-500 rounded-lg transition-all">
                        <i class="fas fa-trash-alt ml-1"></i> تنظيف السجلات
                    </button>
                </form>
            </div>

            <!-- النسخ الاحتياطي -->
            <div class="p-5 bg-[#131313] rounded-xl border border-[#2a2a2a] hover:border-blue-500/40 transition-colors group">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 rounded-full bg-blue-500/10 flex items-center justify-center text-blue-400 group-hover:scale-110 transition-transform">
                        <i class="fas fa-download"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-300 text-lg">النسخ الاحتياطي</h3>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mb-4 font-bold pr-14">تحميل نسخة احتياطية كاملة بصيغة SQL للحفاظ على بيانات مسلسلاتك وحلقاتك.</p>
                <a href="index.php?action=download_backup" class="block text-center w-full bg-blue-500/20 hover:bg-blue-600 text-blue-400 hover:text-white border border-blue-500/30 font-bold py-2 rounded-lg transition-all">
                    <i class="fas fa-cloud-download-alt ml-1"></i> تحميل نسخة SQL
                </a>
            </div>

        </div>
    </div>
</div>