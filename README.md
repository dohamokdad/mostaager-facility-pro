# mostaager-facility-pro
Plugin to manage building facilities
الإضافة المرفوعة Mostaager Facility PRO v15.0.0 الخاصة بموقع ejar-egy.com من خلال الكود المصدري الموجود داخل الملف ZIP، ولم أقم بفحص الموقع الحي أو قواعد البيانات الفعلية.

نظرة عامة على الإضافة

الإضافة عبارة عن نظام إدارة عقارات ومرافق متكامل مبني فوق قالب/منصة Houzez ويحتوي على:

لوحات التحكم الرئيسية
لوحة المالك
[owner_dashboard_v4]
متابعة العقارات
الفواتير
المحفظة
طلبات الصيانة
الإشعارات
لوحة المستأجر
[rent_dashboard_v4]
الإيجارات
الفواتير
سجل الدفع
الإشعارات
لوحة مدير المبنى
[manager_dashboard_v4]
إدارة المباني
المصاريف
الصيانة
الوحدات
التقارير
لوحة الوسيط
[agent_dashboard_v4]
العقارات
العملاء المحتملين
الصيانة
الربط مع Houzez
أدوار المستخدمين الموجودة

وجدت الأدوار التالية:

Role	الوصف
owner	مالك
tenant	مستأجر
agent	وسيط
building_manager	مدير مبنى
mostaager_manager	مدير النظام
mostaager_supervisor	مشرف

جميعها تعتمد على Capability رئيسية:

ms_view_dashboard
قاعدة البيانات

الإضافة تنشئ تقريباً 23 جدولاً مخصصاً.

أهم الجداول:

Table
ms_buildings
ms_units
ms_unit_tenants
ms_invoices
ms_maintenance_requests
ms_maintenance_comments
ms_maintenance_invoices
ms_building_wallet
ms_user_wallet
ms_wallet_transactions
ms_notifications
ms_property_reviews
ms_utility_bills

هذا يدل أن النظام لا يعتمد بالكامل على Posts بل يستخدم جداول مخصصة للأداء.

REST API

وجدت API داخلي:

/ms/v1/facilities
/ms/v1/invoices
/ms/v1/expenses

مع صلاحيات مختلفة حسب الدور.

نقاط القوة
1. هيكلية جيدة نسبياً

يوجد فصل بين:

Dashboards
APIs
Database Layer
Integrations
Roles
2. دعم الفواتير

يوجد:

class-invoice-pdf.php
class-wc-invoice-bridge.php
rent-invoices.php

مما يعني:

إنشاء فواتير
PDF
ربط مع WooCommerce
3. الصيانة

النظام متقدم نسبياً:

ms_maintenance_requests
ms_maintenance_comments
ms_maintenance_timeline
ms_maintenance_attachments

أي يوجد Workflow كامل للصيانة.

4. المحافظ المالية

يوجد:

ms_building_wallet
ms_user_wallet
ms_wallet_transactions

وهذا جيد لإدارة رسوم المباني.
