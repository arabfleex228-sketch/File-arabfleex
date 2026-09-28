<?php
// حماية الصفحة للمدير العام فقط
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'super_admin') {
    die("صلاحيات غير كافية");
}

$message = '';
$error = '';

// معالجة إضافة مدير جديد
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_admin') {
    $new_username = trim($_POST['username']);
    $new_password = $_POST['password'];
    $role = $_POST['role']; // 'admin' أو 'super_admin'

    if (!empty($new_username) && !empty($new_password)) {
        // التحقق من عدم وجود الاسم مسبقاً
        $check = $conn->prepare("SELECT id FROM admins WHERE username = ?");
        $check->bind_param("s", $new_username);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "اسم المستخدم موجود مسبقاً!";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO admins (username, password, role) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $new_username, $hashed_password, $role);
            if ($stmt->execute()) {
                $message = "تمت إضافة المدير بنجاح.";
                logAdminAction($conn, 'إضافة مدير', "قام بإضافة حساب مدير جديد باسم: $new_username بصلاحية: $role");
            } else {
                $error = "حدث خطأ أثناء الإضافة.";
            }
        }
    } else {
        $error = "يرجى تعبئة جميع الحقول.";
    }
}

// معالجة حذف مدير
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    // منع المدير من حذف نفسه
    if ($del_id === $_SESSION['admin_id']) {
        $error = "لا يمكنك حذف حسابك الخاص!";
    } else {
        // جلب اسم المدير قبل الحذف لتسجيله في السجل
        $get_name = $conn->query("SELECT username, role FROM admins WHERE id = $del_id");
        if ($get_name->num_rows > 0) {
            $del_user = $get_name->fetch_assoc();
            
            // منع حذف المدير العام الأساسي (ID=1) للحماية
            if ($del_id == 1 || ($del_user['role'] == 'super_admin' && $_SESSION['admin_id'] != 1)) {
                $error = "غير مسموح بحذف هذا الحساب.";
            } else {
                $conn->query("DELETE FROM admins WHERE id = $del_id");
                $message = "تم حذف الحساب بنجاح.";
                logAdminAction($conn, 'حذف مدير', "قام بحذف حساب المدير: " . $del_user['username']);
            }
        }
    }
}

// جلب قائمة المديرين
$admins = $conn->query("SELECT id, username, role FROM admins ORDER BY id ASC");
?>

<div class="section-header">
    <h2 class="section-title text-brand-gold"><i class="fas fa-users-cog ml-2"></i> إدارة فريق العمل</h2>
    <p class="text-text-secondary mt-2">من هنا يمكنك إضافة مديري محتوى وتحديد صلاحياتهم.</p>
</div>

<?php if ($message): ?>
    <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl mb-6 font-bold text-center">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-xl mb-6 font-bold text-center">
        <?php echo $error; ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- فورم الإضافة -->
    <div class="lg:col-span-1 bg-bg-card p-6 rounded-2xl border border-border-color shadow-xl h-fit">
        <h3 class="text-xl font-bold mb-4 text-white"><i class="fas fa-plus-circle ml-2 text-brand-gold"></i> إضافة مدير جديد</h3>
        <form method="POST" action="index.php?page=admins">
            <input type="hidden" name="action" value="add_admin">
            
            <div class="mb-4">
                <label class="form-label">اسم المستخدم (للدخول)</label>
                <input type="text" name="username" class="form-input" required autocomplete="off">
            </div>
            
            <div class="mb-4">
                <label class="form-label">كلمة المرور</label>
                <input type="password" name="password" class="form-input" required autocomplete="new-password">
            </div>
            
            <div class="mb-6">
                <label class="form-label">مستوى الصلاحيات</label>
                <select name="role" class="form-select">
                    <option value="admin">مدير محتوى (لا يرى الإدارة العليا)</option>
                    <option value="super_admin">مدير عام (صلاحيات كاملة)</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary w-full justify-center">
                <i class="fas fa-save"></i> إنشاء الحساب
            </button>
        </form>
    </div>

    <!-- جدول المديرين -->
    <div class="lg:col-span-2">
        <div class="table-responsive">
            <table class="content-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>اسم المستخدم</th>
                        <th>الصلاحية</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($admin = $admins->fetch_assoc()): ?>
                    <tr>
                        <td class="font-bold text-gray-400">#<?php echo $admin['id']; ?></td>
                        <td class="font-bold text-white"><?php echo htmlspecialchars($admin['username']); ?></td>
                        <td>
                            <?php if ($admin['role'] === 'super_admin'): ?>
                                <span class="bg-red-500/20 text-red-400 px-3 py-1 rounded-full text-xs font-bold border border-red-500/20">مدير عام</span>
                            <?php else: ?>
                                <span class="bg-blue-500/20 text-blue-400 px-3 py-1 rounded-full text-xs font-bold border border-blue-500/20">مدير محتوى</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($admin['id'] !== $_SESSION['admin_id'] && $admin['id'] != 1): ?>
                                <a href="index.php?page=admins&delete_id=<?php echo $admin['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('هل أنت متأكد من حذف هذا المدير؟');">
                                    <i class="fas fa-trash-alt"></i> حذف
                                </a>
                            <?php else: ?>
                                <span class="text-gray-500 text-sm">لا يمكن الحذف</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>