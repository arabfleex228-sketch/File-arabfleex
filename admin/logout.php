<?php
session_start();

// إلغاء جميع متغيرات الجلسة
$_SESSION = array();

// تدمير الجلسة
session_destroy();

// توجيه المستخدم إلى صفحة تسجيل الدخول
header("Location: login.php");
exit();
?>