# مشروع استمارة التعاقد وإدارة العملاء | مجموعة العمار للتطوير العقاري
### Shores Only • Coastal Projects Management System

نظام متكامل وفاخر لإدارة واستيفاء بيانات عقود الشاليهات والوحدات الساحلية لمجموعة العمار، مصمم بواجهة عصرية مريحة للعين مع لمسات ذهبية أنيقة وإطار ذهبي يبرز اللوجو، مع دعم الربط التلقائي بموظفي المبيعات (Sales Attribution)، لوحة تحكم إدارية متكاملة، ونظام REST API كامل.

---

## 🌟 مميزات النظام (Key Features)

1. **واجهة فاخرة ومريحة للعين (Eye-Friendly Luxury Theme):**
   - تصميم بألوان كحلية زيتونية مهدئة للعين (Slate Navy `#121c2b`) لا تسبب أي إجهاد بصري.
   - إطار ذهبي مميز ومشع حول اللوجو يبرز هوية مجموعة العمار (`border-2 border-amber-400`).
   - تلوين العناوين والكلام الهام بجريدينت ذهبي أنيق.
2. **تسلسل الحقول المعتمد:**
   - كود الوحدة $\rightarrow$ مكان التعاقد (قائمة منسدلة) $\rightarrow$ فترة السداد (قائمة منسدلة) $\rightarrow$ سداد الـ 5% $\rightarrow$ رفع المستندات $\rightarrow$ الملاحظات والطلبات الخاصة.
3. **خانة الملاحظات بعدد حروف محدد:**
   - حد أقصى **250 حرفاً** مع عداد لحظي وديناميكي (`0 / 250 حرف`) وتنبيه بصري عند الاقتراب من الحد.
4. **تتبع موظفي المبيعات (Sales Consultant Tracking):**
   - روابط مخصصة لكل موظف عبر الرابط (Slug: `?slug=ahmed-xxx`).
   - إظهار شارة المستشار العقاري المعتمد تلقائياً في الاستمارة وإسناد العميل له.
5. **لوحة تحكم المسؤول (Admin Dashboard - `view_clients.php`):**
   - استعراض جميع العقود والعملاء، البحث اللحظي، الفلترة حسب السيلز أو الفرع.
   - تعديل بيانات العملاء والملاحظات، معاينة وتنزيل المستندات، وتصدير كامل لملفات Excel/CSV.
6. **إدارة فريق المبيعات (`manage_sales.php`):**
   - إضافة مسؤولي المبيعات، توليد الروابط المخصصة تلقائياً، تفعيل وتعطيل الروابط، ونسخ الروابط بنقرة واحدة.

---

## 🚀 كيفية الرفع والنشر (Deployment Guide)

### 1️⃣ الخطوة الأولى: رفع الكود على GitHub
تم إنشاء وتجهيز المستودع رسمياً على حسابك:
- **المستودع:** `https://github.com/Nada690/form-for-elamar`

---

### 2️⃣ الخطوة الثانية: رفع الفرونت إند على Vercel (Frontend)
الفرونت إند مجهز كـ Static Web App فائق السرعة عبر `index.html` و `vercel.json`:
1. افتح موقع [Vercel](https://vercel.com) وسجل الدخول بحساب GitHub.
2. اضغط على **Add New...** ثم **Project**.
3. اختر مستودع `Nada690/form-for-elamar` واضغط **Import**.
4. في إعدادات المشروع:
   - **Framework Preset:** اتركها `Other` (أو Static).
   - **Root Directory:** اتركها `./`
5. اضغط **Deploy**! ستحصل فوراً على رابط الفرونت إند (مثال: `https://form-for-elamar.vercel.app`).
6. لربط الفرونت إند بالباك إند: ضع رابط الباك إند داخل ملف `config.js`:
   ```javascript
   window.BACKEND_URL = "https://your-backend.railway.app";
   ```

---

### 3️⃣ الخطوة الثالثة: رفع الباك إند على Railway أو Render (Backend)

#### الخيار أ: عبر Railway (موصى به - أسهل وأسرع)
1. افتح موقع [Railway](https://railway.app) وسجل الدخول بحساب GitHub.
2. اضغط **New Project** $\rightarrow$ **Deploy from GitHub repo**.
3. اختر المستودع `Nada690/form-for-elamar`.
4. أضف قاعدة بيانات MySQL بنقرة واحدة: اضغط **+ New** $\rightarrow$ **Database** $\rightarrow$ **Add MySQL**.
5. سيقوم Railway تلقائياً بتوليد متغيرات البيئة (`MYSQLHOST`, `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLDATABASE`) وسيتصل بها الباك إند تلقائياً عبر `db.php` دون أي تعديل يدوي!
6. بعد اكتمال البناء، اذهب إلى إعدادات الخدمة (Settings) $\rightarrow$ **Networking** $\rightarrow$ **Generate Domain** لتحصل على رابط الباك إند.
7. لتجهيز الجداول لأول مرة: افتح في المتصفح:
   `https://your-backend.railway.app/setup_db.php`

---

#### الخيار ب: عبر Render
1. افتح موقع [Render](https://render.com) وسجل الدخول بحساب GitHub.
2. اضغط **New +** $\rightarrow$ **Web Service**.
3. اربط مستودع `Nada690/form-for-elamar`.
4. الإعدادات:
   - **Environment:** `Docker` (سيستخدم `Dockerfile` المرفق تلقائياً).
   - **Plan:** Free.
5. أضف قاعدة بيانات MySQL مجانية (عبر Render أو Aiven أو PlanetScale أو TiDB Cloud).
6. أضف متغيرات البيئة في تبويب **Environment**:
   - `DB_HOST`: عنوان سيرفر قاعدة البيانات
   - `DB_USER`: اسم المستخدم
   - `DB_PASSWORD`: كلمة المرور
   - `DB_NAME`: `elamar_db`
7. اضغط **Create Web Service**.
8. لتجهيز الجداول: افتح: `https://your-backend.onrender.com/setup_db.php`.

---

## 🔑 بيانات الدخول الافتراضية للوحة التحكم (Credentials)

- **رابط لوحة المسؤول:** `view_clients.php`
- **اسم المستخدم:** `admin`
- **كلمة المرور:** `admin123`
- **رابط إدارة السيلز:** `manage_sales.php`

---

## 📁 هيكلية المشروع (Project Structure)

```text
├── index.html          # واجهة الاستمارة الرسمية للفرونت إند (Vercel)
├── config.js           # إعداد رابط الباك إند للفرونت إند
├── form.php            # استمارة التعاقد بنسخة PHP للباك إند
├── submit.php          # معالج إرسال واستقبال البيانات والمرفقات (POST API + CORS)
├── db.php              # موصل قاعدة البيانات PDO يدعم السيرفرات السحابية ومحلياً
├── setup_db.php        # سكريبت تثبيت وبناء الجداول والمسؤول الافتراضي
├── view_clients.php    # لوحة قيادة المسؤول والبحث وتصدير العقود وتعديل الملاحظات
├── manage_sales.php    # نظام إدارة موظفي المبيعات والروابط المخصصة
├── check_auth.php      # فحص جلسة المسؤول
├── logo.png            # شعار مجموعة العمار للتطوير العقاري
├── api/                # واجهة RESTful API كاملة
├── Dockerfile          # ملف بناء الحاوية السحابية لـ Render و Railway
├── railway.json        # إعدادات النشر على Railway
├── render.yaml         # إعدادات النشر على Render
├── vercel.json         # إعدادات النشر على Vercel
└── README.md           # دليل التشغيل والتوثيق
```
