<?php
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // حذف الطلب
    if (isset($_POST['delete_request'])) {
        $id = intval($_POST['request_id']);
        $stmt = $conn->prepare("DELETE FROM requests WHERE id = ?");
        $stmt->bind_param("i", $id);
        if($stmt->execute()){ $message = 'تم حذف الطلب بنجاح.'; $message_type = 'success'; }
        $stmt->close();
    }
    // تحديث حالة الطلب ورد الإدارة
    if (isset($_POST['update_status'])) {
        $id = intval($_POST['request_id']);
        $new_status = $conn->real_escape_string($_POST['status_val']);
        $admin_reply = $conn->real_escape_string($_POST['admin_reply'] ?? '');
        
        $stmt = $conn->prepare("UPDATE requests SET status = ?, admin_reply = ?, is_read = 1 WHERE id = ?");
        $stmt->bind_param("ssi", $new_status, $admin_reply, $id);
        if($stmt->execute()){ $message = 'تم تحديث حالة الطلب والرد.'; $message_type = 'success'; }
        $stmt->close();
    }
    // ميزة مارك كـ مقروءة
    if (isset($_POST['mark_read'])) {
        $conn->query("UPDATE requests SET is_read = 1");
        $message = 'تم تحديد الكل كمقروء.'; $message_type = 'success';
    }
}

// التأكد من وجود عمود الرد في قاعدة البيانات
$check_reply_col = $conn->query("SHOW COLUMNS FROM requests LIKE 'admin_reply'");
if ($check_reply_col && $check_reply_col->num_rows == 0) {
    $conn->query("ALTER TABLE requests ADD COLUMN admin_reply TEXT AFTER status");
}
?>

<div class="flex flex-col md:flex-row justify-between items-center mb-10 gap-6">
    <h1 class="text-3xl font-black">الطلبات والشكاوي</h1>
    <form method="POST">
        <button type="submit" name="mark_read" class="btn btn-secondary text-sm">تحديد الكل كمقروء</button>
    </form>
</div>

<?php if (!empty($message)): ?>
    <div class="mb-6 p-4 rounded-xl text-center font-bold bg-green-500/10 text-green-500 border border-green-500/20"><?php echo $message; ?></div>
<?php endif; ?>

<div class="w-full overflow-x-auto shadow-2xl rounded-xl">
    <table class="content-table w-full min-w-[950px]">
        <thead>
            <tr>
                <th>التتبع / الحالة</th>
                <th>النوع</th>
                <th>الاسم / البريد</th>
                <th class="min-w-[250px]">التفاصيل</th>
                <th>التاريخ</th>
                <th>إجراء</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $current_domain = $_SERVER['HTTP_HOST'];
        
        // --- تعديل الاستعلام لربط الطلبات بجدول المستخدمين لمعرفة حالة الـ VIP ---
        $query = "SELECT r.*, u.is_vip, u.vip_expires_at 
                  FROM requests r 
                  LEFT JOIN users u ON r.user_id = u.id 
                  WHERE r.domain_name = '$current_domain' 
                  ORDER BY r.created_at DESC";
                  
        $result = $conn->query($query);
        
        if ($result && $result->num_rows > 0):
            while($row = $result->fetch_assoc()):
                $status = isset($row['status']) ? $row['status'] : 'new';
                $ticket_id = isset($row['ticket_id']) && !empty($row['ticket_id']) ? $row['ticket_id'] : 'لا يوجد';
                $admin_reply = isset($row['admin_reply']) ? $row['admin_reply'] : '';
                
                // --- فحص ما إذا كان المستخدم VIP ونشط ---
                $is_active_vip = false;
                if (!empty($row['is_vip']) && $row['is_vip'] == 1 && strtotime($row['vip_expires_at']) > time()) {
                    $is_active_vip = true;
                }

                // تخصيص لون الصف إذا كان غير مقروء أو إذا كان VIP
                $row_classes = $row['is_read'] == 0 ? 'bg-yellow-500/5 border-r-4 border-yellow-500' : 'border-r-4 border-transparent';
                if ($is_active_vip) {
                    $row_classes .= ' bg-gradient-to-l from-[var(--brand-gold)]/10 to-transparent';
                }
        ?>
            <tr class="<?php echo $row_classes; ?>">
                <td class="w-48">
                    <div class="mb-2 text-xs font-mono bg-black/30 inline-block px-2 py-1 rounded text-gray-400 border border-white/10">
                        <i class="fas fa-hashtag text-[var(--brand-gold)]"></i> <?php echo htmlspecialchars($ticket_id); ?>
                    </div>
                    <form method="POST" class="flex flex-col gap-2">
                        <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">
                        <input type="hidden" name="update_status" value="1">
                        
                        <?php
                            $status_bg = 'bg-blue-500/10 text-blue-400 border-blue-500/20';
                            if ($status == 'processing') $status_bg = 'bg-orange-500/10 text-orange-400 border-orange-500/20';
                            if ($status == 'completed') $status_bg = 'bg-green-500/10 text-green-400 border-green-500/20';
                            if ($status == 'rejected') $status_bg = 'bg-red-500/10 text-red-400 border-red-500/20';
                        ?>
                        
                        <select name="status_val" class="w-full text-xs font-bold py-1.5 px-2 rounded-lg border <?php echo $status_bg; ?> focus:outline-none appearance-none cursor-pointer">
                            <option value="new" <?php echo $status == 'new' ? 'selected' : ''; ?> class="bg-gray-900 text-blue-400">جديد <?php echo $row['is_read'] == 0 ? '🔴' : ''; ?></option>
                            <option value="processing" <?php echo $status == 'processing' ? 'selected' : ''; ?> class="bg-gray-900 text-orange-400">جاري العمل ⏳</option>
                            <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?> class="bg-gray-900 text-green-400">تم التوفير ✔️</option>
                            <option value="rejected" <?php echo $status == 'rejected' ? 'selected' : ''; ?> class="bg-gray-900 text-red-400">مرفوض ❌</option>
                        </select>
                        <input type="text" name="admin_reply" value="<?php echo htmlspecialchars($admin_reply); ?>" placeholder="أضف رابط العمل أو سبب الرفض هنا.." class="w-full bg-black/40 border border-white/10 rounded px-2 py-1 text-[10px] text-white focus:border-[var(--brand-gold)] focus:outline-none">
                        <button type="submit" class="w-full bg-white/5 hover:bg-white/10 text-gray-300 py-1 rounded text-xs transition-colors border border-white/5">حفظ التحديث</button>
                    </form>
                </td>
                <td>
                    <?php if(isset($row['type']) && $row['type'] == 'request'): ?>
                        <span class="bg-blue-500/10 text-blue-400 py-1 px-2 rounded-md text-xs font-bold whitespace-nowrap"><i class="fas fa-film ml-1"></i> طلب عمل</span>
                    <?php else: ?>
                        <span class="bg-yellow-500/10 text-yellow-400 py-1 px-2 rounded-md text-xs font-bold whitespace-nowrap"><i class="fas fa-comment-alt ml-1"></i> شكوى/اقتراح</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="font-bold text-white whitespace-nowrap flex items-center gap-2">
                        <?php echo !empty($row['name']) ? htmlspecialchars($row['name']) : '<span class="text-gray-600 italic font-normal">غير محدد</span>'; ?>
                        
                        <!-- إظهار تاج الـ VIP إذا كان المستخدم مشتركاً -->
                        <?php if ($is_active_vip): ?>
                            <span class="bg-gradient-to-r from-[var(--brand-gold)] to-yellow-600 text-black px-2 py-0.5 rounded text-[10px] font-black shadow-[0_0_10px_rgba(245,197,24,0.4)]" title="هذا الطلب من مشترك VIP، له أولوية التنفيذ"><i class="fas fa-crown"></i> VIP</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-xs text-gray-500 whitespace-nowrap mt-1">
                        <?php echo !empty($row['email']) ? htmlspecialchars($row['email']) : '<span class="text-gray-700">لا يوجد بريد</span>'; ?>
                    </div>
                </td>
                <td class="text-gray-400 text-sm min-w-[250px]">
                    <?php if(isset($row['type']) && $row['type'] == 'request'): ?>
                        <strong class="text-gray-300">العمل:</strong> 
                        <?php echo !empty($row['work_name']) ? htmlspecialchars($row['work_name']) : '<span class="text-gray-600 italic">اسم العمل غير متوفر</span>'; ?><br>
                        
                        <?php if(!empty($row['work_link'])): ?>
                            <strong class="text-gray-300">الرابط:</strong> <a href="<?php echo htmlspecialchars($row['work_link']); ?>" target="_blank" class="text-[var(--brand-gold)] hover:underline hover:text-white transition-colors mt-1 inline-block">اضغط للمعاينة <i class="fas fa-external-link-alt text-[10px]"></i></a>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if(!empty($row['description'])): ?>
                            <div class="p-2 bg-black/30 rounded-lg border border-white/5 text-xs whitespace-normal break-words leading-relaxed">
                                <?php echo nl2br(htmlspecialchars($row['description'])); ?>
                            </div>
                        <?php else: ?>
                            <div class="text-gray-600 italic text-xs py-2">لا يوجد تفاصيل أو نص مرفق.</div>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td class="text-gray-500 text-xs font-mono whitespace-nowrap">
                    <?php echo isset($row['created_at']) ? date('Y/m/d h:i A', strtotime($row['created_at'])) : 'غير معروف'; ?>
                </td>
                <td>
                    <form method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذا الطلب نهائياً؟');">
                        <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">
                        <button type="submit" name="delete_request" class="btn btn-secondary px-3 py-2 text-red-500 hover:bg-red-500/20 hover:text-red-400 transition-colors rounded-lg"><i class="fas fa-trash-alt"></i></button>
                    </form>
                </td>
            </tr>
        <?php endwhile; else: ?>
            <tr><td colspan="6" class="text-center py-10 text-gray-500">لا توجد طلبات حالياً على هذا الدومين.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>