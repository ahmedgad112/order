# نظام إدارة الأدوار (Num System)

نظام طوابير يومي بالعربية (RTL) مبني على Laravel + Vue 3، يشمل:

- إصدار الدور من الموظف (اسم الطالب ورقم الطلب والنوع)
- شاشة عرض عامة مع تنبيه صوتي عند النداء
- متابعة التذكرة بالرقم القومي أو رقم الطلب
- لوحة موظف (دور جديد / نداء / إكمال / إلغاء / إعادة نداء)
- لوحة إدارة (فتح/إغلاق، تصفير اليوم، تقارير، مستخدمين، سجل التسجيلات)

## المتطلبات

- PHP 8.3+
- Composer
- Node.js 20+
- SQLite (افتراضي) أو MySQL

## التثبيت السريع

```bash
composer install
copy .env.example .env   # Windows
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
```

للتشغيل أثناء التطوير (سيرفر + Vite + Reverb):

```bash
composer run dev
```

أو يدوياً في نوافذ منفصلة:

```bash
php artisan serve
php artisan reverb:start
npm run dev
```

افتح: `http://127.0.0.1:8000`

## حسابات التجربة (بعد seed)

حسابات الموظفين لا تُنشأ تلقائياً. المدير هو المسؤول عن إنشاء الموظفين وإعطائهم الأدوار من لوحة الإدارة.

| الدور | البريد | كلمة المرور |
|------|--------|-------------|
| سوبر أدمن | `gad@gmail.com` | `Ahmedgad@2011` |
| مدير (إدارة الموظفين) | `manager@queue.local` | `password` |

## المسارات

| المسار | الوصف |
|--------|--------|
| `/` | التسجيل عند الموظف ومتابعة التذكرة |
| `/display` | شاشة العرض |
| `/track` | متابعة التذكرة |
| `/login` | تسجيل الدخول |
| `/teller` | لوحة الموظف |
| `/admin` | لوحة الإدارة |
| `/admin/registrations` | سجل التسجيلات وتسجيل الدخول |

## الريل تايم (Reverb)

تأكد من ضبط المتغيرات في `.env`:

```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

القناة العامة: `queue-channel`  
الأحداث: `TicketIssued`, `TicketCalled`, `TicketCompleted`, `QueueSystemUpdated`, `QueueDayReset`

## الاختبارات

```bash
php artisan test
```

## ملاحظات تشغيل

- التذاكر يومية؛ **تصفير اليوم** يحذف تذاكر اليوم الحالي.
- إغلاق النظام يمنع إصدار تذاكر جديدة ونداء التالي، مع السماح بإكمال التذكرة الحالية.
- واجهات إصدار/تتبع التذاكر محمية بحد معدل الطلبات (throttle).
