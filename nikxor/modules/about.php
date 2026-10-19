<?php
/**
 * NiKXor Accounting - About the Application & Developer
 * معرفی سامانه هوشمند حسابی نیک‌زور و سازنده برنامه
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
auth_require();

$pageTitle = 'درباره سامانه حسابی NiKXor و سازنده';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 900px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px;">

    <!-- 1. Hero Card: معرفی سازنده برنامه -->
    <div class="card" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #ffffff; border: none; padding: 32px; border-radius: 16px; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.3);">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 24px;">
            <div style="display: flex; align-items: center; gap: 20px;">
                <div style="width: 84px; height: 84px; border-radius: 20px; background: linear-gradient(135deg, #0284c7, #38bdf8); display: flex; align-items: center; justify-content: center; font-size: 38px; color: #ffffff; box-shadow: 0 8px 16px rgba(2, 132, 199, 0.4); flex-shrink: 0;">
                    <i class="fa-solid fa-user-tie"></i>
                </div>
                <div>
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                        <span style="background: rgba(56, 189, 248, 0.2); color: #38bdf8; font-size: 11.5px; font-weight: 700; padding: 3px 10px; border-radius: 9999px; border: 1px solid rgba(56, 189, 248, 0.4);">
                            توسعه‌دهنده و طراح سیستم
                        </span>
                    </div>
                    <h2 style="font-size: 24px; font-weight: 800; margin: 0 0 6px 0; color: #ffffff; letter-spacing: -0.5px;">
                        احمد نجیم نیک (Ahmad Najeem Nik)
                    </h2>
                    <p style="font-size: 14px; color: #cbd5e1; margin: 0; line-height: 1.6;">
                        توسعه‌دهنده ارشد نرم‌افزارهای تجاری، سامانه‌های مالی یکپارچه و هوش مصنوعی
                    </p>
                </div>
            </div>

            <!-- Contact Buttons -->
            <div style="display: flex; flex-direction: column; gap: 10px; min-width: 220px;">
                <a href="tel:+93783788278" class="btn" style="background: #0284c7; color: #ffffff; justify-content: center; padding: 12px 18px; font-weight: 700; border-radius: 10px; text-decoration: none; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-phone"></i>
                    <span dir="ltr">+93 783 788 278</span>
                </a>
                <a href="https://wa.me/93783788278" target="_blank" class="btn" style="background: #10b981; color: #ffffff; justify-content: center; padding: 12px 18px; font-weight: 700; border-radius: 10px; text-decoration: none; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-brands fa-whatsapp" style="font-size: 18px;"></i>
                    <span>ارتباط در واتساپ</span>
                </a>
            </div>
        </div>
    </div>

    <!-- 2. درباره نرم‌افزار NiKXor -->
    <div class="card" style="padding: 28px;">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 18px;">
            <div style="width: 46px; height: 46px; border-radius: 12px; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 22px;">
                <i class="fa-solid fa-cubes"></i>
            </div>
            <div>
                <h3 style="font-size: 18px; font-weight: 800; margin: 0; color: #0f172a;">
                    درباره سامانه حسابداری NiKXor
                </h3>
                <span style="font-size: 12px; color: #64748b;">سیستم هوشمند، امن و مستقل مدیریت مالی و فروشگاهی</span>
            </div>
        </div>

        <p style="font-size: 14.5px; line-height: 1.8; color: #334155; margin-bottom: 20px;">
            نرم‌افزار <strong>NiKXor</strong> یک سامانه تخصصی و بومی برای مدیریت همه‌جانبه فروشگاه‌های موبایل، لوازم جانبی و کسب‌وکارهای بازرگانی در افغانستان است. این نرم‌افزار با هدف رفع پیچیدگی‌های محاسباتی، تفکیک دقیق سرمایه در گردش از مفاد خالص، و ثبت شفاف تمامی طلبات و معاملات نسیه طراحی گردیده است.
        </p>

        <!-- Feature Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px;">
            <div style="background: #f8fafc; padding: 16px; border-radius: 10px; border: 1px solid #e2e8f0;">
                <div style="color: #0284c7; font-size: 18px; margin-bottom: 8px;">
                    <i class="fa-solid fa-mobile-screen-button"></i>
                </div>
                <h4 style="font-size: 14px; font-weight: 700; margin: 0 0 6px 0; color: #0f172a;">رهگیری IMEI و سریال کالا</h4>
                <p style="font-size: 12.5px; color: #64748b; margin: 0; line-height: 1.6;">
                    ثبت شناسه اختصاصی برای تک‌تک گوشی‌ها با جلوگیری قطعی از ورود IMEI تکراری و صدور بل رسمی.
                </p>
            </div>

            <div style="background: #f8fafc; padding: 16px; border-radius: 10px; border: 1px solid #e2e8f0;">
                <div style="color: #10b981; font-size: 18px; margin-bottom: 8px;">
                    <i class="fa-solid fa-scale-balanced"></i>
                </div>
                <h4 style="font-size: 14px; font-weight: 700; margin: 0 0 6px 0; color: #0f172a;">تفکیک هوشمند سرمایه و مفاد</h4>
                <p style="font-size: 12.5px; color: #64748b; margin: 0; line-height: 1.6;">
                    کته‌گوری‌بندی خودکار اقساط و طلبات به سهم اصل سرمایه دکان و سهم مفاد خالص با قابلیت تنظیم دستی و هوشمند.
                </p>
            </div>

            <div style="background: #f8fafc; padding: 16px; border-radius: 10px; border: 1px solid #e2e8f0;">
                <div style="color: #6366f1; font-size: 18px; margin-bottom: 8px;">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                </div>
                <h4 style="font-size: 14px; font-weight: 700; margin: 0 0 6px 0; color: #0f172a;">مشاور هوش مصنوعی دکان</h4>
                <p style="font-size: 12.5px; color: #64748b; margin: 0; line-height: 1.6;">
                    تحلیل سودآوری اجناس، تخمین وضعیت طلبات مشکوک‌الوصول و ارائه راهکارهای افزایش فروش و حاشیه سود.
                </p>
            </div>

            <div style="background: #f8fafc; padding: 16px; border-radius: 10px; border: 1px solid #e2e8f0;">
                <div style="color: #f59e0b; font-size: 18px; margin-bottom: 8px;">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <h4 style="font-size: 14px; font-weight: 700; margin: 0 0 6px 0; color: #0f172a;">امنیت، بک‌آپ و عملکرد آفلاین</h4>
                <p style="font-size: 12.5px; color: #64748b; margin: 0; line-height: 1.6;">
                    پایگاه داده مستقل و امن با قابلیت تهیه نسخه پشتیبان دیتابیس در هر لحظه و عملکرد سریع بدون وابستگی به اینترنت.
                </p>
            </div>
        </div>
    </div>

    <!-- 3. اطلاعات لایسنس و پشتیبانی -->
    <div class="card" style="padding: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; background: #fdfefe; border: 1px solid #e2e8f0;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-certificate" style="color: #0284c7; font-size: 20px;"></i>
            <span style="font-size: 13.5px; color: #334155; font-weight: 600;">
                نسخه نرم‌افزار: <strong>NiKXor Pro v1.5</strong> — تمامی حقوق برای <strong>احمد نجیم نیک</strong> محفوظ است.
            </span>
        </div>
        <div style="font-size: 12.5px; color: #64748b;">
            پشتیبانی فنی و سفارشی‌سازی: <a href="tel:+93783788278" style="color: #0284c7; font-weight: 700; text-decoration: none;" dir="ltr">+93783788278</a>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
