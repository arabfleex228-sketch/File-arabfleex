<?php
$upload_dir = "../uploads/";
$message = '';
$message_type = '';

// جلب كافة البوسترات من قاعدة البيانات للمقارنة
$db_posters = [];
$res_m = $conn->query("SELECT poster FROM movies");
while($row = $res_m->fetch_assoc()) { if(!empty($row['poster'])) $db_posters[] = str_replace('uploads/', '', $row['poster']); }
$res_s = $conn->query("SELECT poster FROM series");
while($row = $res_s->fetch_assoc()) { if(!empty($row['poster'])) $db_posters[] = str_replace('uploads/', '', $row['poster']); }

// عمليات الحذف
if (isset($_POST['delete_file'])) {
    $file_to_delete = $_POST['file_name'];
    if (file_exists($upload_dir . $file_to_delete)) {
        unlink($upload_dir . $file_to_delete);
        $message = "تم حذف الملف بنجاح.";
        $message_type = "success";
    }
}

if (isset($_POST['delete_orphans'])) {
    $files = array_diff(scandir($upload_dir), array('.', '..'));
    $count = 0;
    foreach ($files as $file) {
        if (!in_array($file, $db_posters)) {
            unlink($upload_dir . $file);
            $count++;
        }
    }
    $message = "تم حذف $count ملفاً غير مرتبطة بالبيانات.";
    $message_type = "success";
}

// قراءة المجلد
$all_files = array_diff(scandir($upload_dir), array('.', '..'));
?>

<div class="section-header">
    <h1 class="section-title">إدارة ملفات الصور (Uploads)</h1>
    <form method="POST" onsubmit="return confirm('سيتم حذف كافة الصور غير المستخدمة، هل أنت متأكد؟');">
        <button type="submit" name="delete_orphans" class="btn btn-danger">
            <i class="fas fa-broom"></i> تنظيف المجلد (حذف اليتامى)
        </button>
    </form>
</div>

<?php if (!empty($message)): ?>
    <div class="mb-6 p-4 rounded-md text-center font-bold <?php echo $message_type == 'success' ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="bg-background-light p-6 rounded-lg border border-border-color">
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
        <?php foreach ($all_files as $file): 
            $is_orphan = !in_array($file, $db_posters);
        ?>
        <div class="relative bg-background-dark p-2 rounded border <?php echo $is_orphan ? 'border-red-500/50' : 'border-border-color'; ?>">
            <img src="../uploads/<?php echo $file; ?>" class="w-full aspect-square object-cover rounded mb-2">
            <div class="text-[10px] text-gray-500 truncate mb-2"><?php echo $file; ?></div>
            
            <div class="flex justify-between items-center">
                <?php if($is_orphan): ?>
                    <span class="text-[10px] text-red-500 font-bold">غير مستخدم</span>
                <?php else: ?>
                    <span class="text-[10px] text-green-500 font-bold">نشط</span>
                <?php endif; ?>
                
                <form method="POST" onsubmit="return confirm('حذف الصورة؟');">
                    <input type="hidden" name="file_name" value="<?php echo $file; ?>">
                    <button type="submit" name="delete_file" class="text-red-400 hover:text-red-600 transition-colors">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>