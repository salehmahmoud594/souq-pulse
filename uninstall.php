<?php
/**
 * ملف إلغاء تثبيت إضافة SouqPulse (نبض السوق)
 *
 * يتم تشغيل هذا الملف تلقائياً عند قيام المستخدم بحذف الإضافة من لوحة التحكم.
 * يقوم بمسح جداول قاعدة البيانات المخصصة للبلجن فقط، ولا يلمس أي جداول خاصة بـ WooCommerce أو WP Statistics.
 */

// إذا لم يتم استدعاء الملف بواسطة ووردبريس، اخرج فوراً
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// أسماء الجداول المخصصة للإضافة فقط
$table_funnel = $wpdb->prefix . 'souqpulse_funnel_events';

// مسح الجداول الخاصة بنا فقط
$wpdb->query( "DROP TABLE IF EXISTS {$table_funnel}" );

// مسح أي خيارات أو كاش (transients) خاص بنا من قاعدة البيانات
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_souqpulse_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_souqpulse_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'souqpulse_%'" );
