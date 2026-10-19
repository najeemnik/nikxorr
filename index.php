<?php
/**
 * NiKXor - Root Entry Redirector for Apache / XAMPP / Host
 */
header('Location: nikxor/index.php');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="0;url=nikxor/index.php">
    <title>در حال انتقال به NiKXor...</title>
</head>
<body style="background:#0f172a;color:#fff;font-family:sans-serif;text-align:center;padding-top:50px;">
    <h3>در حال انتقال به سیستم حسابداری NiKXor...</h3>
    <p><a href="nikxor/index.php" style="color:#fbbf24;">کلیک کنید اگر به صورت خودکار منتقل نشدید</a></p>
    <script>window.location.replace("nikxor/index.php");</script>
</body>
</html>
