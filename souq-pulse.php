<?php
/**
 * Plugin Name: SouqPulse (نبض السوق)
 * Description: لوحة تحليلات متكاملة لمتجر WooCommerce مدمجة مع إحصائيات الزوار من WP Statistics بواجهة عربية كاملة RTL.
 * Version: 1.0.0
 * Author: WordPress Developer
 * Text Domain: souq-pulse
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 */

// منع الوصول المباشر
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// تعريف الثوابت الأساسية
define( 'SOUQPULSE_VERSION', '1.0.0' );
define( 'SOUQPULSE_PATH', plugin_dir_path( __FILE__ ) );
define( 'SOUQPULSE_URL', plugin_dir_url( __FILE__ ) );
define( 'SOUQPULSE_BASENAME', plugin_basename( __FILE__ ) );

/**
 * التحقق من وجود WooCommerce وتفعيله
 */
add_action( 'plugins_loaded', 'souqpulse_init_dependency_check' );

function souqpulse_init_dependency_check() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        // إضافة تنبيه للمشرف
        add_action( 'admin_notices', 'souqpulse_woocommerce_missing_notice' );
        // تعطيل الإضافة تلقائياً لتجنب أي مشاكل
        deactivate_plugins( SOUQPULSE_BASENAME );
        if ( isset( $_GET['activate'] ) ) {
            unset( $_GET['activate'] );
        }
        return;
    }

    // تضمين الفئة الرئيسية للإضافة وتشغيلها
    require_once SOUQPULSE_PATH . 'includes/class-souqpulse.php';
    SouqPulse::get_instance();
}

/**
 * رسالة تنبيه عند غياب WooCommerce
 */
function souqpulse_woocommerce_missing_notice() {
    $class = 'notice notice-error is-dismissible';
    $message = __( 'إضافة <strong>نبض السوق (SouqPulse)</strong> تتطلب تفعيل إضافة <strong>WooCommerce</strong> للعمل. تم تعطيل الإضافة تلقائياً.', 'souq-pulse' );

    printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), wp_kses_post( $message ) );
}

/**
 * الإعلان عن التوافق مع جداول الطلبات عالية الأداء (HPOS) في WooCommerce
 */
add_action( 'before_woocommerce_init', 'souqpulse_declare_hpos_compatibility' );

function souqpulse_declare_hpos_compatibility() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', SOUQPULSE_BASENAME, true );
    }
}

/**
 * ربط خطافات التفعيل وإلغاء التفعيل
 */
register_activation_hook( __FILE__, 'souqpulse_activate_plugin' );
register_deactivation_hook( __FILE__, 'souqpulse_deactivate_plugin' );

function souqpulse_activate_plugin() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }
    
    // إعداد قاعدة البيانات في مراحل لاحقة
    require_once SOUQPULSE_PATH . 'includes/class-souqpulse-db.php';
    SouqPulse_DB::activate();
}

function souqpulse_deactivate_plugin() {
    // أي عمليات تنظيف خفيفة عند التعطيل (بدون مسح البيانات)
}
