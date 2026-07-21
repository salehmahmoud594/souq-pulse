<?php
/**
 * Plugin Name: SouqPulse (نبض السوق)
 * Plugin URI: https://github.com/salehmahmoud594/souq-pulse
 * Description: An integrated and smart analytics dashboard for WooCommerce, merging real-time visitor traffic from WP Statistics with advanced KPIs in a fully localized Arabic RTL interface.
 * Version: 1.1.0
 * Author: Saleh Mahmoud
 * Author URI: https://github.com/salehmahmoud594
 * Text Domain: souq-pulse
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC prefers at least: 6.0
 */

// منع الوصول المباشر
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// تعريف الثوابت الأساسية
define( 'SOUQPULSE_VERSION', '1.1.0' );
define( 'SOUQPULSE_PATH', plugin_dir_path( __FILE__ ) );
define( 'SOUQPULSE_URL', plugin_dir_url( __FILE__ ) );
define( 'SOUQPULSE_BASENAME', plugin_basename( __FILE__ ) );

/**
 * تصفية محدد اللغة للمستند بناءً على إعدادات البلجن
 */
add_filter( 'plugin_locale', 'souqpulse_override_plugin_locale', 10, 2 );

function souqpulse_override_plugin_locale( $locale, $domain ) {
    if ( 'souq-pulse' === $domain ) {
        $forced_locale = get_option( 'souqpulse_language', 'auto' );
        if ( 'auto' !== $forced_locale ) {
            return $forced_locale;
        }
    }
    return $locale;
}

/**
 * تحميل ملفات الترجمة مع دعم الاختيار اليدوي للغة
 * Priority 5 — يجب أن يُحمَّل قبل plugins_loaded (10)
 */
add_action( 'init', 'souqpulse_load_textdomain', 5 );

function souqpulse_load_textdomain() {
    // فحص وتجميع ملف اللغة العربية إذا تطلب الأمر
    $po_file = SOUQPULSE_PATH . 'languages/souq-pulse-ar.po';
    $mo_file = SOUQPULSE_PATH . 'languages/souq-pulse-ar.mo';
    if ( file_exists( $po_file ) && ( ! file_exists( $mo_file ) || filemtime( $po_file ) > filemtime( $mo_file ) ) ) {
        souqpulse_compile_po_to_mo( $po_file, $mo_file );
    }

    load_plugin_textdomain(
        'souq-pulse',
        false,
        dirname( SOUQPULSE_BASENAME ) . '/languages'
    );
}

/**
 * تجميع ملف PO إلى MO برمجياً بشكل سريع وموفر للأداء
 */
function souqpulse_compile_po_to_mo( $po_file, $mo_file ) {
    if ( ! file_exists( $po_file ) ) {
        return false;
    }

    $po_content = file_get_contents( $po_file );
    if ( ! $po_content ) {
        return false;
    }

    $lines = explode( "\n", $po_content );
    $entries = array();
    $current_id = null;
    $current_str = null;
    $in_id = false;
    $in_str = false;

    foreach ( $lines as $line ) {
        $line = trim( $line );
        if ( empty( $line ) || strpos( $line, '#' ) === 0 ) {
            if ( $current_id !== null && $current_str !== null ) {
                $entries[ $current_id ] = $current_str;
                $current_id = null;
                $current_str = null;
            }
            $in_id = false;
            $in_str = false;
            continue;
        }

        if ( preg_match( '/^msgid\s+"(.*)"$/', $line, $m ) ) {
            $current_id = $m[1];
            $in_id = true;
            $in_str = false;
        } elseif ( preg_match( '/^msgstr\s+"(.*)"$/', $line, $m ) ) {
            $current_str = $m[1];
            $in_id = false;
            $in_str = true;
        } elseif ( strpos( $line, '"' ) === 0 ) {
            $val = substr( $line, 1, -1 );
            if ( $in_id ) {
                $current_id .= $val;
            } elseif ( $in_str ) {
                $current_str .= $val;
            }
        }
    }

    if ( $current_id !== null && $current_str !== null ) {
        $entries[ $current_id ] = $current_str;
    }

    foreach ( $entries as $k => $v ) {
        if ( $k === '' || empty( $v ) ) {
            unset( $entries[ $k ] );
        }
    }

    ksort( $entries );
    $count = count( $entries );
    if ( $count <= 0 ) {
        return false;
    }

    $orig_table_offset = 28;
    $trans_table_offset = $orig_table_offset + ( $count * 8 );
    $strings_offset = $trans_table_offset + ( $count * 8 );

    $orig_table = '';
    $trans_table = '';
    $strings = '';
    $current_strings_offset = $strings_offset;

    foreach ( $entries as $orig => $trans ) {
        $orig = stripcslashes( $orig );
        $len = strlen( $orig );
        $orig_table .= pack( 'L2', $len, $current_strings_offset );
        $strings .= $orig . "\x00";
        $current_strings_offset += $len + 1;
    }

    foreach ( $entries as $orig => $trans ) {
        $trans = stripcslashes( $trans );
        $len = strlen( $trans );
        $trans_table .= pack( 'L2', $len, $current_strings_offset );
        $strings .= $trans . "\x00";
        $current_strings_offset += $len + 1;
    }

    $mo_data = pack( 'I*', 0x950412de, 0, $count, $orig_table_offset, $trans_table_offset, 0, 0 );
    $mo_data .= $orig_table . $trans_table . $strings;

    return file_put_contents( $mo_file, $mo_data ) !== false;
}


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
    $message = __( 'إضافة <strong>Souq Pulse (SouqPulse)</strong> تتطلب تفعيل إضافة <strong>WooCommerce</strong> للعمل. تم تعطيل الإضافة تلقائياً.', 'souq-pulse' );

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
