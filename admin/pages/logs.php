<?php
// حماية الصفحة للمدير العام فقط
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'super_admin') {
    die("صلاحيات غير كافية");
}

// مسح السجل (اختياري)
if (isset($_GET['clear']) && $_GET['clear'] == '1') {
    $conn->query("TRUNCATE TABLE admin_logs");
    header("Location: index.php?page=logs");
    exit();
}

// جلب النشاطات مع ربطها بجدول المديرين لجلب اسم المدير
$query = "
    SELECT l.*, a.username 
    FROM admin_logs l 
    LEFT JOIN admins a ON l.admin_id = a.id 
    ORDER BY l.created_at DESC 
    LIMIT 100
";
$logs = $conn->query($query);
?>

<div class="section-header flex justify-between items-center">
    <div>
        <h2 class="section-title text-brand-gold"><i class="fas fa-history ml-2"></i> سجل نشاطات النظام</h2>
        <p class="text-text-secondary mt-2">يعرض آخر 100 حركة تمت داخل لوحة التحكم بواسطة المديرين.</p>
    </div>
    
    <a href="index.php?page=logs&clear=1" class="btn btn-danger" onclick="return confirm('هل أنت متأكد من مسح جميع السجلات؟ لا يمكن التراجع عن هذا.');">
        <i class="fas fa-trash"></i> تفريغ السجل
    </a>
</div>

<div class="table-responsive">
    <table class="content-table">
        <thead>
            <tr>
                <th>التاريخ والوقت</th>
                <th>المدير</th>
                <th>نوع الحركة</th>
                <th>التفاصيل</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($logs && $logs->num_rows > 0): ?>
                <?php while ($log = $logs->fetch_assoc()): ?>
                <tr>
                    <td dir="ltr" class="text-left text-sm text-gray-400">
                        <?php echo date('Y-m-d h:i A', strtotime($log['created_at'])); ?>
                    </td>
                    <td class="font-bold text-white">
                        <i class="fas fa-user text-brand-gold ml-1"></i> 
                        <?php echo htmlspecialchars($log['username'] ?? 'مدير محذوف'); ?>
                    </td>
                    <td>
                        <span class="bg-gray-800 text-gray-300 px-3 py-1 rounded border border-gray-700 text-xs font-bold">
                            <?php echo htmlspecialchars($log['action_type']); ?>
                        </span>
                    </td>
                    <td class="text-gray-300 text-sm w-1/2">
                        <?php echo htmlspecialchars($log['details']); ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" class="text-center text-gray-500 py-8">لا توجد نشاطات مسجلة حتى الآن.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>