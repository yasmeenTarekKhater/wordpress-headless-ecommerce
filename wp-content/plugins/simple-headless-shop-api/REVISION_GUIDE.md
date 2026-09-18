# 📚 Simple Headless Shop API - Revision & Guide (دليل المراجعة والشرح)

هذا المستند يشرح بالتفصيل جميع الأجزاء والدوال (WordPress Functions & Hooks) التي تم استخدامها في إنشاء الـ Custom Plugin لمتجر Headless eCommerce متصل بتطبيق Front-end (مثل Nuxt.js).

---

## 1. 📂 Structure of the Plugin (هيكل الـ Plugin)

- **`simple-headless-shop-api.php`**: الملف الرئيسي للـ Plugin (Main entry point). يقوم بتسجيل الـ Hooks الخاصة بالـ Activation وتضمين بقية الملفات.
- **`includes/products-api.php`**: خاص بـ Endpoints المنتجات (عرض جميع المنتجات مع الفلترة والبحث، وعرض منتج فردي بناءً على الـ Slug).
- **`includes/auth-api.php`**: خاص بـ Endpoints التوثيق والتسجيل (Customer Registration & User Management).

---

## 2. 🛠️ WordPress Hooks & Core Setup (الإعدادات الأولية للـ Plugin)

### 🔒 Direct Access Guard (حماية الملفات من الوصول المباشر)
```php
if (!defined('ABSPATH')) {
    exit;
}
```
- **المفهوم**: ثابِت `ABSPATH` يتم تعريفه فقط عند تحميل بيئة ووردبريس.
- **الهدف**: حماية الملفات بحيث لو حاول أي شخص فتح ملف PHP مباشرة عبر المتصفح دون المرور بـ WordPress، يتم إيقاف التفيذ فوراً (`exit;`).

### ⚡ Plugin Activation Hook & Customer Role
```php
function simple_shop_activate() {
    add_role('customer', 'Customer', [
        'read' => true,
    ]);
}
register_activation_hook(__FILE__, 'simple_shop_activate');
```
- **`register_activation_hook()`**: يتم تشغيل هذه الدالة مرة واحدة فقط عند تفعيل الـ Plugin من لوحة تحكم ووردبريس.
- **`add_role()`**: تقوم بتسجيل دور جديد للمستخدمين في ووردبريس باسم `customer` لإعطائهم صلاحيات العميل.

---

## 3. 🌐 REST API Endpoints & Routes (إنشاء وإدارة الـ API)

### 📌 `add_action('rest_api_init', ...)`
- **المفهوم**: Hook أساسي في ووردبريس يتم استدعاؤه عند تجهيز الـ REST API. يتم تسجيل جميع الـ Routes بداخل هذا الـ Hook.

### 📌 `register_rest_route($namespace, $route, $args)`
تستخدم لتسجيل Endpoint جديد:
- **`$namespace`**: النطاق مثل `'shop/v1'`.
- **`$route`**: المسار مثل `'/products'` أو `'/auth/register'`.
- **`$args`**: مصفوفة تحتوي على:
  - `'methods'`: نوع الطلب (مثل `WP_REST_Server::READABLE` للـ GET، و `WP_REST_Server::CREATABLE` للـ POST).
  - `'callback'`: اسم الدالة التي تتعامل مع الطلب وتُرجع البيانات.
  - `'permission_callback'`: دالة للتحقق من الصلاحيات (استخدمنا `'__return_true'` لجعل الـ Endpoint عام ومتاح للجميع).
  - `'args'`: التحقق من الـ Inputs والـ Sanitization للبيانات القادمة في الـ Request.

---

## 4. 🛒 Products API Logic (منطق API المنتجات)

### 📦 List Products Endpoint (`GET /wp-json/shop/v1/products`)
تُستخدم لعمل Pagination, Search, Filtering, and Sorting:

#### 🔹 الدوال المستخدمة (WordPress Functions):
1. **`WP_Query($query_args)`**:
   - الدالة الرئيسية لاستعلام البيانات من قاعدة البيانات.
   - نمرر لها `post_type => 'product'`, `post_status => 'publish'`, `posts_per_page`, `paged`, `s` (للبحث)، `tax_query` (للفلترة حسب التصنيف)، و `meta_query` (للفلترة حسب السعر).
2. **`wp_get_post_terms($post_id, 'product_category')`**:
   - تجلب أقسام المنتج (Taxonomy terms).
3. **`get_field('field_name', $post_id)`**:
   - دالة من مكتبة Advanced Custom Fields (ACF) لجلب الـ Custom Fields مثل السعر `price` والكمية `stock` والـ SKU.
4. **`get_the_post_thumbnail_url($post_id, 'large')`**:
   - تجلب رابط صورة المنتج المميزة.
5. **`rest_ensure_response($data)`**:
   - تضمن أن الاستجابة مغلفة بشكل صح في صيغة `WP_REST_Response`.

---

### 🏷️ Single Product Endpoint (`GET /wp-json/shop/v1/products/{slug}`)
تستخدم لجلب تفاصيل منتج واحد باستخدام الـ Slug:

#### 🔹 الدوال المستخدمة:
1. **`get_page_by_path($slug, OBJECT, ['product'])`**:
   - تستهلك الـ Slug وتبحث عن منشور من نوع `product` يطابق هذا المسار.
2. **`WP_Error($code, $message, $data)`**:
   - في حال عدم وجود المنتج أو عدم نشره، تُرجع خطأ منظم مع كود حالة 404 (Not Found):
   ```php
   if (!$product || $product->post_status !== 'publish') {
       return new WP_Error('product_not_found', 'Product not found.', ['status' => 404]);
   }
   ```

---

## 5. 🔐 Auth & Customer Registration Logic (منطق تسجيل العملاء)

### 👤 Register Endpoint (`POST /wp-json/shop/v1/auth/register`)

تستقبل `name`, `email`, و `password` لإنشاء حساب عميل جديد.

#### 🔹 الدوال المستخدمة (WordPress Functions):

1. **`email_exists($email)`**:
   - تفحص ما إذا كان البريد الإلكتروني مستخدماً مسبقاً في قاعدة بيانات ووردبريس.
2. **`sanitize_user($username)` & `username_exists($username)`**:
   - تُستخدم لبناء `user_login` تلقائياً من جزء البريد الإلكتروني قبل علامة `@` والتأكد من عدم تكراره بإضافة عداد رقمي (مثل `john`, `john1`, `john2`).
3. **`wp_insert_user($user_data)`**:
   - الدالة المباشرة لإنشاء مستخدم جديد في ووردبريس. نحدد لها `user_login`, `user_email`, `user_pass`, `display_name`, و `role => 'customer'`.
4. **`is_wp_error($result)`**:
   - تتحقق مما إذا كانت النتيجة المحصلة من ووردبريس هي كائن خطأ.
5. **`get_userdata($user_id)`**:
   - تجلب بيانات المستخدم المنشأ لإرجاعها في الاستجابة (مثل ID، الاسم، والبريد).
6. **`new WP_REST_Response($data, 201)`**:
   - تُرجع استجابة بنجاح العملية مع HTTP Status Code `201 Created`.

---

## 🧼 Data Validation & Sanitization (التنقية والتحقق من صحة البيانات)

لضمان حماية الـ API، تم استخدام الدوال التالية داخل الـ `args` للـ Routes:

- **`sanitize_text_field($string)`**: إزالة النص الفائق أو الـ HTML الضار من النصوص (مثل الاسم).
- **`sanitize_email($email)`**: تنقية البريد الإلكتروني.
- **`sanitize_title($title)`**: تحويل الـ Slug إلى صيغة آمنة للروابط.
- **`is_email($email)`**: دالة ووردبريس للتأكد من أن النص يمثل بريد إلكتروني صحيح.

---

## 📌 Summary Table of WordPress Functions (ملخص سريع للدوال)

| الدالة (Function) | الوظيفة (Functionality) |
| :--- | :--- |
| **`register_rest_route()`** | تسجيل مسار جديد في الـ REST API |
| **`WP_Query()`** | الاستعلام عن المنتجات وإجراء الفلترة والبحث والترتيب |
| **`get_page_by_path()`** | البحث عن المنتج باستخدام الـ Slug |
| **`get_field()`** | جلب الحقول المخصصة (ACF) مثل السعر والمخزون |
| **`email_exists()`** | التحقق من وجود الإيميل مسبقاً في ووردبريس |
| **`username_exists()`** | التحقق من وجود اسم المستخدم قبل إنشائه |
| **`wp_insert_user()`** | إنشاء حساب مستخدم جديد في قاعدة البيانات |
| **`add_role()`** | إضافة صلاحية أو دور جديد (مثل Customer) |
| **`rest_ensure_response()`** | تحويل المخرجات إلى استجابة REST API قياسية |
| **`WP_Error()`** | إرجاع استجابة خطأ مع رمز HTTP معين (مثل 404 أو 409) |
