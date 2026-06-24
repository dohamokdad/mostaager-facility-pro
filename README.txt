Mostaager Facility Pro - UI Skeleton

This package contains the UI skeleton for Mostaager dashboards: building, owner, agent and rent.

Installation:
1. Upload the folder into wp-content/plugins/mostaager-facility-pro
2. Activate the plugin
3. Ensure permalinks saved once

Notes:
- This is a stable UI and DB helper skeleton. Financial engine and full WooCommerce/Telr integrations come in next releases.
- Added WordPress REST API endpoints under `wp-json/ms/v1/` for facilities, invoices, and expense creation.
- Added the `[ms_dashboard]` shortcode and a Gutenberg dynamic block `mostaager-facility-pro/ms-dashboard`.
- Introduced `Mostaager_DB` as a lightweight wrapper for plugin database access.
- Added PHPUnit test scaffolding for shortcode registration and DB wrapper loading.
## الملخص

إضافة **Mostaager Facility Pro** هي نظام إدارة مرافق مبانٍ مدمج مع قالب Houzez على WordPress. الإصدار الجديد من الإضافة أُعيد هيكلته معمارياً بشكل أنظف، لكنه فقد 15 وظيفة أساسية كانت موجودة في الإصدار القديم. هذا الـ Epic يهدف إلى استعادة هذه الوظائف المفقودة داخل البنية الجديدة، وإصلاح مشاكل التنقل بين التبويبات في جميع لوحات التحكم، وبناء نظام متكامل لإدارة الصيانة وتوزيع الفواتير يخدم رئيس اتحاد الملاك والملاك والمستأجرين والوسطاء.

---

## السياق والمشكلة

### من المتأثر؟


| الدور                 | المشكلة الحالية                                                                 |
| --------------------- | ------------------------------------------------------------------------------- |
| **رئيس اتحاد الملاك** | لا يستطيع إنشاء طلبات صيانة، ولا يرى فواتير المبنى، ولا يتلقى إشعارات عند الدفع |
| **المالك**            | لوحة التحكم لا تعرض عقاراته (عدد = 0)، والتبويبات لا تعمل                       |
| **المستأجر**          | لا يستطيع التنقل بين تبويبات لوحته                                              |
| **الوسيط**            | زر إضافة عقار لا يعمل، عقاراته لا تظهر، لا توجد مناقشات                         |


### أين المشكلة في المنتج؟

- **لوحات التحكم الأربع**: `rent-dashboard`, `owner-dashboard`, `agent-dashboard`, `building-dashboard`
- **الإضافة**: `mostaager-facility-pro` (الإصدار الجديد)
- **التكامل**: Houzez theme + WooCommerce + Telr

### الوضع الحالي

الإصدار الجديد من الإضافة يحتوي على بنية معمارية نظيفة (`core/`, `app/Dashboards/`, `ms_*` prefix) لكنه يفتقر إلى:

1. **التبويبات لا تعمل** — تعارض بين `dashboard.js` و `dashboard-tabs.js`
2. **عقارات المالك = 0** — الكود يبحث في `ms_units` الفارغ بدلاً من Houzez posts
3. **عقارات الوسيط لا تظهر** — نفس المشكلة
4. **نظام الصيانة غائب كلياً** — لا نموذج إضافة، لا توزيع فواتير، لا محفظة مبنى
5. **الإشعارات غائبة** — لا يعلم رئيس اتحاد الملاك بالمدفوعات
6. **المناقشات غائبة** — لا تواصل بين الملاك والمستأجرين

---

## نطاق العمل

### ما هو داخل النطاق

- إصلاح التبويبات في جميع اللوحات الأربع
- ربط عقارات المالك والوسيط بـ Houzez posts
- استعادة CPTs: `building`, `expenses`, `invoices`, `discussions`, `transfers`
- بناء نظام الصيانة الكامل لرئيس اتحاد الملاك
- توزيع الفواتير على الملاك والمستأجرين + إشعار الوسيط
- محفظة المبنى مع progress bar
- نظام الإشعارات الداخلية
- نظام المناقشات
- Admin pages لإدارة الفواتير والتحويلات
- تكامل Telr/WooCommerce للدفع

### ما هو خارج النطاق

- تغيير قالب Houzez أو إعداداته
- بناء تطبيق موبايل
- نظام تقارير متقدم

---

## القرارات التقنية المتفق عليها


| القرار             | القيمة                                          |
| ------------------ | ----------------------------------------------- |
| **العملة**         | جنيه مصري (EGP) — ثابتة                         |
| **prefix الدوال**  | `ms_` موحد                                      |
| **CPTs**           | تُعاد تسجيلها (بيانات موجودة فيها)              |
| **تسجيل العقارات** | Houzez فقط (`post_type = property`)             |
| **المباني**        | `post_type = building` (من الإضافة القديمة)     |
| **توزيع الفاتورة** | مالك (دائماً) + مستأجر (إن وُجد) + إشعار للوسيط |


