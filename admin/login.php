<?php
require_once 'db_config.php';

if (isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password_input = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, password FROM admins WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $admin = $result->fetch_assoc();
        
        // نظام فحص مزدوج (لضمان الدخول إذا كانت كلمة المرور مشفرة أو نص عادي)
        if (password_verify($password_input, $admin['password']) || $password_input === $admin['password']) {
            
            // تحديث كلمة المرور لتصبح مشفرة تلقائياً للأمان
            if ($password_input === $admin['password'] && !password_get_info($admin['password'])['algo']) {
                $new_hash = password_hash($password_input, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
                $update_stmt->bind_param("si", $new_hash, $admin['id']);
                $update_stmt->execute();
                $update_stmt->close();
            }

            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $username;
            header("Location: index.php");
            exit();
        } else {
            $error_message = "بيانات الدخول غير صحيحة.";
        }
    } else {
        $error_message = "بيانات الدخول غير صحيحة.";
    }
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - عرب فليكس</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; background-color: #050505; color: white; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">
    <div class="max-w-md w-full bg-[#0F0F0F] p-10 rounded-3xl border border-[#1F1F1F] shadow-2xl">
        <div class="text-center mb-10">
            <h1 class="text-4xl font-black mb-2"><span class="text-[#DAA520]">عرب</span> فليكس</h1>
            <p class="text-gray-500 font-bold">لوحة التحكم الإدارية</p>
            <p class="text-gray-500 font-bold" dir="ltr">
                <?php echo $_SERVER['HTTP_HOST']; ?>
            </p>
        </div>
        
        <?php if ($error_message): ?>
            <div class="bg-red-500/10 text-red-400 p-4 rounded-xl text-center mb-6 border border-red-500/20 font-bold">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <input type="text" name="username" placeholder="اسم المستخدم" required class="w-full p-4 rounded-xl bg-[#000] border border-[#1F1F1F] text-white outline-none focus:border-[#DAA520]">
            <input type="password" name="password" placeholder="كلمة المرور" required class="w-full p-4 rounded-xl bg-[#000] border border-[#1F1F1F] text-white outline-none focus:border-[#DAA520]">
            <button type="submit" class="w-full bg-[#DAA520] hover:bg-[#B8860B] text-black font-black p-4 rounded-xl transition-all shadow-lg shadow-[#DAA520]/10">دخول اللوحة</button>
        </form>
    </div>
</body>
</html>