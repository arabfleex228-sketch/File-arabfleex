<?php
// --- معالج الحذف المتعدد (تحديد مجموعة) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    if (!empty($_POST['episode_ids'])) {
        // تأمين المدخلات (تحويل جميع القيم إلى أرقام صحيحة لمنع الثغرات)
        $ids = array_map('intval', $_POST['episode_ids']);
        $ids_string = implode(',', $ids);
        
        $delete_query = "DELETE FROM episodes WHERE id IN ($ids_string)";
        
        if ($conn->query($delete_query)) {
            echo "<script>
                alert('تم حذف الحلقات المحددة بنجاح!');
                window.location.href = 'index.php?page=latest_episodes';
            </script>";
        } else {
            echo "<script>alert('حدث خطأ أثناء الحذف: " . addslashes($conn->error) . "');</script>";
        }
    } else {
        echo "<script>alert('الرجاء تحديد حلقة واحدة على الأقل للحذف.');</script>";
    }
}

// --- معالج الحذف الفردي (زر الحذف الصغير) ---
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $delete_query = "DELETE FROM episodes WHERE id = $delete_id";
    if ($conn->query($delete_query)) {
        echo "<script>
            window.location.href = 'index.php?page=latest_episodes';
        </script>";
    }
}
?>

<style>
    /* تحسينات إضافية لخطوط الجدول وخانات التحديد */
    .table-text-large {
        font-family: 'Cairo', sans-serif;
        font-size: 15px;
    }
    .series-name-text {
        font-size: 16px;
        font-weight: 800;
        color: #f8fafc;
        letter-spacing: 0.5px;
    }
    .custom-checkbox {
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: #ef4444; /* لون التحديد أحمر */
    }
    .action-buttons-container {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
</style>

<!-- بداية فورم الحذف المتعدد -->
<form method="POST" action="index.php?page=latest_episodes" id="bulkDeleteForm">
    <div class="section-header">
        <h1 class="section-title" style="font-family: 'Cairo', sans-serif;">
            <i class="fas fa-clock text-pink-400 ml-2"></i> أحدث الحلقات المضافة
        </h1>
        
        <div class="action-buttons-container">
            <!-- زر الحذف المتعدد -->
            <button type="submit" name="bulk_delete" class="btn btn-danger" onclick="return confirm('هل أنت متأكد من حذف جميع الحلقات التي قمت بتحديدها؟');">
                <i class="fas fa-trash-alt"></i> حذف المحدد
            </button>
            
            <!-- زر الإضافة -->
            <a href="index.php?page=add_episodes" class="btn btn-primary">
                <i class="fas fa-plus"></i> إضافة حلقة جديدة
            </a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="content-table table-text-large">
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;">
                        <!-- مربع تحديد الكل -->
                        <input type="checkbox" id="selectAll" class="custom-checkbox" title="تحديد الكل">
                    </th>
                    <th>المعرف (ID)</th>
                    <th>اسم المسلسل</th>
                    <th>الموسم</th>
                    <th>الحلقة</th>
                    <th>تاريخ الإضافة</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // استعلام لجلب أحدث 50 حلقة تمت إضافتها
                $query = "
                    SELECT e.*, s.title as series_title 
                    FROM episodes e 
                    LEFT JOIN series s ON e.series_id = s.id 
                    ORDER BY e.id DESC 
                    LIMIT 50
                ";
                
                $result = $conn->query($query);

                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $season_num = isset($row['season_number']) ? $row['season_number'] : (isset($row['season']) ? $row['season'] : '-');
                        $episode_num = isset($row['episode_number']) ? $row['episode_number'] : (isset($row['episode']) ? $row['episode'] : '-');
                        $date_added = isset($row['created_at']) ? date('Y-m-d H:i', strtotime($row['created_at'])) : 'غير محدد';
                        $series_name = !empty($row['series_title']) ? htmlspecialchars($row['series_title']) : 'مسلسل غير معروف';

                        echo "<tr class='hover:bg-gray-800/50 transition-colors'>";
                        
                        // مربع التحديد الخاص بالصف
                        echo "<td style='text-align: center;'>
                                <input type='checkbox' name='episode_ids[]' value='{$row['id']}' class='custom-checkbox row-checkbox'>
                              </td>";
                              
                        echo "<td><span class='text-text-secondary font-bold'>#{$row['id']}</span></td>";
                        echo "<td><span class='series-name-text'>{$series_name}</span></td>";
                        echo "<td><span class='bg-gray-800 border border-gray-600 px-3 py-1 rounded-md text-sm font-bold shadow-sm'>الموسم {$season_num}</span></td>";
                        echo "<td><span class='bg-brand-gold/10 text-brand-gold border border-brand-gold/30 px-3 py-1 rounded-md text-sm font-bold shadow-sm'>الحلقة {$episode_num}</span></td>";
                        echo "<td><span class='text-gray-400 text-sm font-semibold'>{$date_added}</span></td>";
                        
                        // أزرار الإجراءات الفردية
                        echo "<td style='display: flex; gap: 0.5rem;'>
                                <a href='index.php?page=episodes&series_id=" . (isset($row['series_id']) ? $row['series_id'] : '') . "' class='btn btn-sm btn-secondary' title='عرض حلقات المسلسل'>
                                    <i class='fas fa-eye'></i>
                                </a>
                                
                                <a href='index.php?page=latest_episodes&delete_id={$row['id']}' class='btn btn-sm btn-danger' onclick='return confirm(\"هل أنت متأكد من حذف هذه الحلقة؟\");' title='حذف هذه الحلقة فقط'>
                                    <i class='fas fa-trash'></i>
                                </a>
                              </td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='7' class='text-center py-8 text-lg text-text-secondary'><i class='fas fa-inbox text-3xl mb-3 block opacity-50'></i> لا توجد حلقات مضافة حالياً.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</form>

<script>
    // كود جافاسكريبت لتشغيل "تحديد الكل"
    document.addEventListener('DOMContentLoaded', function() {
        const selectAllCheckbox = document.getElementById('selectAll');
        const rowCheckboxes = document.querySelectorAll('.row-checkbox');

        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                // عند تغيير حالة "تحديد الكل"، قم بتطبيق نفس الحالة على جميع المربعات الأخرى
                rowCheckboxes.forEach(function(checkbox) {
                    checkbox.checked = selectAllCheckbox.checked;
                });
            });
        }

        // إذا تم إلغاء تحديد عنصر واحد، قم بإلغاء "تحديد الكل"
        rowCheckboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                if (!this.checked) {
                    selectAllCheckbox.checked = false;
                } else {
                    // التحقق مما إذا كانت جميع المربعات محددة الآن
                    const allChecked = Array.from(rowCheckboxes).every(cb => cb.checked);
                    selectAllCheckbox.checked = allChecked;
                }
            });
        });
    });
</script>