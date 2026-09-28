<?php
// إظهار الأخطاء مؤقتاً لاكتشاف أي مشكلة (يمكنك إزالتها لاحقاً)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// تأمين الصفحة ومنع الوصول المباشر
if (!isset($_SESSION['admin_id'])) {
    die("Access Denied");
}

$msg = '';
$msg_type = '';

// --- 1. الإصلاح الجذري: التأكد من إنشاء الجداول وإضافة الأعمدة الناقصة إجبارياً ---

// التأكد من إنشاء الجدول الأساسي
$conn->query("CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// الأعمدة المطلوبة لجدول المستخدمين
$user_columns = [
    "username" => "VARCHAR(100) NOT NULL",
    "email" => "VARCHAR(100) NOT NULL",
    "password_hash" => "VARCHAR(255) NOT NULL",
    "is_vip" => "TINYINT(1) DEFAULT 0",
    "vip_expires_at" => "DATETIME NULL",
    "created_at" => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
];

foreach ($user_columns as $col => $type) {
    $check = $conn->query("SHOW COLUMNS FROM users LIKE '$col'");
    if ($check && $check->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN $col $type");
    }
}

// التأكد من جدول أكواد الـ VIP
$conn->query("CREATE TABLE IF NOT EXISTS vip_codes (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$vip_columns = [
    "code" => "VARCHAR(50) NOT NULL",
    "duration_days" => "INT NOT NULL DEFAULT 30",
    "is_used" => "TINYINT(1) DEFAULT 0",
    "used_by_user_id" => "INT NULL",
    "used_at" => "DATETIME NULL",
    "created_at" => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
];

foreach ($vip_columns as $col => $type) {
    $check = $conn->query("SHOW COLUMNS FROM vip_codes LIKE '$col'");
    if ($check && $check->num_rows == 0) {
        $conn->query("ALTER TABLE vip_codes ADD COLUMN $col $type");
    }
}


// --- 2. معالجة الطلبات (POST) ---
// توليد أكواد جديدة
if (isset($_POST['generate_codes'])) {
    $amount = (int)$_POST['codes_amount'];
    $duration = (int)$_POST['vip_duration'];
    
    if ($amount > 0 && $duration > 0) {
        $generated = 0;
        $stmt = $conn->prepare("INSERT INTO vip_codes (code, duration_days) VALUES (?, ?)");
        
        for ($i = 0; $i < $amount; $i++) {
            // توليد كود عشوائي قوي
            $randomString = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 12));
            $code = 'VIP-' . substr($randomString, 0, 4) . '-' . substr($randomString, 4, 4) . '-' . substr($randomString, 8, 4);
            
            $stmt->bind_param("si", $code, $duration);
            if($stmt->execute()) {
                $generated++;
            }
        }
        $stmt->close();
        $msg = "تم توليد $generated كود بنجاح بمدة $duration يوم.";
        $msg_type = "success";
    } else {
        $msg = "الرجاء إدخال أرقام صحيحة.";
        $msg_type = "error";
    }
}

// حذف كود
if (isset($_POST['delete_code'])) {
    $id = (int)$_POST['code_id'];
    $conn->query("DELETE FROM vip_codes WHERE id = $id");
    $msg = "تم حذف الكود بنجاح.";
    $msg_type = "success";
}

// حذف مستخدم
if (isset($_POST['delete_user'])) {
    $id = (int)$_POST['user_id'];
    $conn->query("DELETE FROM users WHERE id = $id");
    $msg = "تم حذف المستخدم نهائياً من النظام.";
    $msg_type = "success";
}


// --- 3. جلب الإحصائيات بأمان ---
$q_u = $conn->query("SELECT COUNT(*) as c FROM users");
$users_count = $q_u ? $q_u->fetch_assoc()['c'] : 0;

$q_v = $conn->query("SELECT COUNT(*) as c FROM users WHERE is_vip = 1 AND vip_expires_at > NOW()");
$vip_count = $q_v ? $q_v->fetch_assoc()['c'] : 0;

$q_c = $conn->query("SELECT COUNT(*) as c FROM vip_codes");
$codes_count = $q_c ? $q_c->fetch_assoc()['c'] : 0;

$q_uc = $conn->query("SELECT COUNT(*) as c FROM vip_codes WHERE is_used = 1");
$used_codes = $q_uc ? $q_uc->fetch_assoc()['c'] : 0;
?>

<div class="section-header">
    <h1 class="text-3xl font-black flex items-center gap-3"><i class="fas fa-users text-blue-400"></i> إدارة الأعضاء والـ VIP</h1>
</div>

<?php if (!empty($msg)): ?>
    <div class="mb-6 p-4 rounded-xl text-center font-bold border <?php echo $msg_type === 'success' ? 'bg-green-500/10 text-green-400 border-green-500/20' : 'bg-red-500/10 text-red-400 border-red-500/20'; ?>">
        <?php echo $msg; ?>
    </div>
<?php endif; ?>

<!-- الإحصائيات السريعة -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
    <div class="glass-panel p-5 rounded-2xl flex items-center gap-4 border border-blue-500/20 shadow-lg">
        <div class="w-12 h-12 rounded-full bg-blue-500/10 flex items-center justify-center text-blue-400 text-xl"><i class="fas fa-users"></i></div>
        <div><div class="text-gray-400 text-xs sm:text-sm font-bold">إجمالي الأعضاء</div><div class="text-2xl font-black text-white"><?php echo number_format($users_count); ?></div></div>
    </div>
    <div class="glass-panel p-5 rounded-2xl flex items-center gap-4 border border-yellow-500/20 shadow-lg">
        <div class="w-12 h-12 rounded-full bg-yellow-500/10 flex items-center justify-center text-yellow-400 text-xl"><i class="fas fa-crown"></i></div>
        <div><div class="text-gray-400 text-xs sm:text-sm font-bold">أعضاء VIP (نشط)</div><div class="text-2xl font-black text-[var(--brand-gold)]"><?php echo number_format($vip_count); ?></div></div>
    </div>
    <div class="glass-panel p-5 rounded-2xl flex items-center gap-4 border border-emerald-500/20 shadow-lg">
        <div class="w-12 h-12 rounded-full bg-emerald-500/10 flex items-center justify-center text-emerald-400 text-xl"><i class="fas fa-barcode"></i></div>
        <div><div class="text-gray-400 text-xs sm:text-sm font-bold">إجمالي الأكواد</div><div class="text-2xl font-black text-white"><?php echo number_format($codes_count); ?></div></div>
    </div>
    <div class="glass-panel p-5 rounded-2xl flex items-center gap-4 border border-rose-500/20 shadow-lg">
        <div class="w-12 h-12 rounded-full bg-rose-500/10 flex items-center justify-center text-rose-400 text-xl"><i class="fas fa-check-circle"></i></div>
        <div><div class="text-gray-400 text-xs sm:text-sm font-bold">أكواد مستخدمة</div><div class="text-2xl font-black text-white"><?php echo number_format($used_codes); ?></div></div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
    
    <!-- قسم الأكواد والتوليد (يأخذ مساحة 1 عمود) -->
    <div class="xl:col-span-1 space-y-8">
        
        <!-- صندوق توليد الأكواد -->
        <div class="glass-panel p-6 rounded-2xl shadow-xl border border-white/10 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-32 h-32 bg-[var(--brand-gold)] rounded-full mix-blend-screen filter blur-[60px] opacity-20 pointer-events-none"></div>
            <h2 class="text-xl font-black text-white mb-6 flex items-center gap-2"><i class="fas fa-magic text-[var(--brand-gold)]"></i> توليد أكواد VIP</h2>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label class="form-label text-gray-300">حدد الباقة (المدة)</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="cursor-pointer relative">
                            <input type="radio" name="vip_duration" value="60" class="peer sr-only" checked>
                            <div class="p-3 text-center rounded-xl bg-black/40 border border-white/10 text-sm font-bold text-gray-400 peer-checked:bg-[var(--brand-gold)]/10 peer-checked:text-[var(--brand-gold)] peer-checked:border-[var(--brand-gold)]/50 transition-all">
                                60 يوم (شهرين)
                            </div>
                        </label>
                        <label class="cursor-pointer relative">
                            <input type="radio" name="vip_duration" value="365" class="peer sr-only">
                            <div class="p-3 text-center rounded-xl bg-black/40 border border-white/10 text-sm font-bold text-gray-400 peer-checked:bg-[var(--brand-gold)]/10 peer-checked:text-[var(--brand-gold)] peer-checked:border-[var(--brand-gold)]/50 transition-all">
                                365 يوم (سنة)
                            </div>
                        </label>
                    </div>
                </div>
                
                <div>
                    <label class="form-label text-gray-300">العدد المطلوب توليده</label>
                    <input type="number" name="codes_amount" min="1" max="100" value="1" class="form-input text-center font-bold" required>
                </div>
                
                <button type="submit" name="generate_codes" class="w-full btn btn-primary justify-center shadow-lg"><i class="fas fa-plus-circle"></i> إنشاء الأكواد الآن</button>
            </form>
        </div>
        
        <!-- قائمة الأكواد -->
        <div class="glass-panel p-6 rounded-2xl shadow-xl border border-white/10">
            <h2 class="text-xl font-black text-white mb-6 flex items-center gap-2"><i class="fas fa-list text-gray-400"></i> أحدث الأكواد</h2>
            <div class="overflow-y-auto max-h-[500px] custom-scrollbar pr-2 space-y-3">
                <?php
                $codes_query = $conn->query("SELECT v.*, u.username FROM vip_codes v LEFT JOIN users u ON v.used_by_user_id = u.id ORDER BY v.created_at DESC LIMIT 50");
                if ($codes_query && $codes_query->num_rows > 0):
                    while ($code = $codes_query->fetch_assoc()):
                        $is_used = isset($code['is_used']) && $code['is_used'] == 1;
                ?>
                    <div class="p-4 rounded-xl border <?php echo $is_used ? 'bg-red-500/5 border-red-500/10' : 'bg-green-500/5 border-green-500/20'; ?> flex flex-col gap-2 relative group">
                        <div class="flex justify-between items-start">
                            <div class="font-mono font-bold tracking-wider text-sm <?php echo $is_used ? 'text-gray-500 line-through' : 'text-[var(--brand-gold)]'; ?>"><?php echo htmlspecialchars($code['code'] ?? ''); ?></div>
                            <span class="text-xs font-bold px-2 py-0.5 rounded <?php echo $is_used ? 'bg-red-500/20 text-red-400' : 'bg-green-500/20 text-green-400'; ?>"><?php echo $is_used ? 'مُستخدم' : 'جديد'; ?></span>
                        </div>
                        <div class="text-[10px] text-gray-400 flex justify-between items-center mt-1">
                            <span>المدة: <b class="text-white"><?php echo htmlspecialchars($code['duration_days'] ?? ''); ?> يوم</b></span>
                            <?php if($is_used): ?>
                                <span class="text-red-300">استخدمه: <b><?php echo htmlspecialchars($code['username'] ?? 'مجهول'); ?></b></span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- زر النسخ والحذف -->
                        <div class="absolute top-2 left-2 opacity-0 group-hover:opacity-100 transition-opacity flex gap-2">
                            <?php if(!$is_used): ?>
                            <button type="button" onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($code['code'] ?? ''); ?>'); alert('تم نسخ الكود!');" class="w-7 h-7 rounded bg-blue-500/20 text-blue-400 hover:bg-blue-500 hover:text-white flex items-center justify-center transition-colors"><i class="fas fa-copy text-xs"></i></button>
                            <?php endif; ?>
                            <form method="POST" onsubmit="return confirm('هل أنت متأكد من حذف الكود؟');" class="inline">
                                <input type="hidden" name="code_id" value="<?php echo htmlspecialchars($code['id'] ?? ''); ?>">
                                <button type="submit" name="delete_code" class="w-7 h-7 rounded bg-red-500/20 text-red-400 hover:bg-red-500 hover:text-white flex items-center justify-center transition-colors"><i class="fas fa-trash text-xs"></i></button>
                            </form>
                        </div>
                    </div>
                <?php 
                    endwhile;
                else: 
                ?>
                    <div class="text-center py-10 text-gray-500 text-sm font-bold">لا توجد أكواد مولدة حالياً.</div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- قسم قائمة الأعضاء (يأخذ مساحة 2 عمود) -->
    <div class="xl:col-span-2">
        <div class="glass-panel p-6 rounded-2xl shadow-xl border border-white/10 h-full">
            <h2 class="text-xl font-black text-white mb-6 flex items-center gap-2"><i class="fas fa-users text-blue-400"></i> قائمة الأعضاء</h2>
            
            <div class="table-responsive">
                <table class="content-table w-full">
                    <thead>
                        <tr>
                            <th>الاسم</th>
                            <th>البريد الإلكتروني</th>
                            <th class="text-center">حالة الحساب</th>
                            <th class="text-center">تاريخ الانتهاء</th>
                            <th class="text-center">إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $users_query = $conn->query("SELECT * FROM users ORDER BY id DESC LIMIT 100");
                        if ($users_query && $users_query->num_rows > 0):
                            while ($user = $users_query->fetch_assoc()):
                                $is_active_vip = (isset($user['is_vip']) && $user['is_vip'] == 1 && strtotime($user['vip_expires_at']) > time());
                                $uname = htmlspecialchars($user['username'] ?? 'User');
                        ?>
                            <tr>
                                <td class="font-bold text-white flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center font-black text-black <?php echo $is_active_vip ? 'bg-gradient-to-tr from-[var(--brand-gold)] to-yellow-300' : 'bg-gray-600 text-white'; ?>">
                                        <?php echo mb_substr($uname, 0, 1, "UTF-8"); ?>
                                    </div>
                                    <?php echo $uname; ?>
                                </td>
                                <td class="text-sm text-gray-400"><?php echo htmlspecialchars($user['email'] ?? ''); ?></td>
                                <td class="text-center">
                                    <?php if ($is_active_vip): ?>
                                        <span class="inline-block bg-[var(--brand-gold)]/10 text-[var(--brand-gold)] border border-[var(--brand-gold)]/30 px-3 py-1 rounded-full text-xs font-black shadow-lg"><i class="fas fa-crown"></i> VIP</span>
                                    <?php elseif (isset($user['is_vip']) && $user['is_vip'] == 1): ?>
                                        <span class="inline-block bg-red-500/10 text-red-400 border border-red-500/20 px-3 py-1 rounded-full text-xs font-bold">منتهي</span>
                                    <?php else: ?>
                                        <span class="inline-block bg-white/5 text-gray-400 px-3 py-1 rounded-full text-xs font-bold">مجاني</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center text-xs font-mono text-gray-400">
                                    <?php echo (isset($user['is_vip']) && $user['is_vip'] == 1 && !empty($user['vip_expires_at'])) ? date('Y-m-d', strtotime($user['vip_expires_at'])) : '-'; ?>
                                </td>
                                <td class="text-center">
                                    <form method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذا المستخدم نهائياً؟ ستضيع بياناته واشتراكه.');">
                                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id'] ?? ''); ?>">
                                        <button type="submit" name="delete_user" class="btn btn-danger btn-sm !p-2 rounded-lg" title="حذف المستخدم"><i class="fas fa-user-times"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                            <tr><td colspan="5" class="text-center py-10 text-gray-500">لا يوجد مستخدمين مسجلين بعد.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
        </div>
    </div>

</div>