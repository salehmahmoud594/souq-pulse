<?php
/**
 * نظام تتبع مسار العميل (Funnel Tracker)
 *
 * @package SouqPulse
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SouqPulse_Tracker {

    /**
     * تهيئة الخطافات وتسجيل إجراءات التتبع
     */
    public function __construct() {
        // إعداد الجلسة (Cookie) عند تهيئة الموقع
        add_action( 'init', array( $this, 'init_session' ) );

        // تحميل كود التتبع في الواجهة الأمامية للمتجر
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracker_script' ) );

        // تتبع أحداث صفحات الموقع الأساسية (زيارة الموقع / بدء الدفع)
        add_action( 'template_redirect', array( $this, 'track_page_views' ) );

        // تتبع إضافة المنتجات للسلة (Server-side)
        add_action( 'woocommerce_add_to_cart', array( $this, 'track_add_to_cart_event' ) );

        // تتبع إتمام عملية الشراء (Server-side)
        add_action( 'woocommerce_thankyou', array( $this, 'track_purchase_event' ) );

        // نقاط استقبال أحداث AJAX للفرونت إند (معلومات الشحن والدفع)
        add_action( 'wp_ajax_nopriv_souqpulse_track_event', array( $this, 'ajax_track_event' ) );
        add_action( 'wp_ajax_souqpulse_track_event', array( $this, 'ajax_track_event' ) );
    }

    /**
     * تهيئة معرف الجلسة (Session ID) وحفظه في ملف تعريف الارتباط
     */
    public function init_session() {
        if ( headers_sent() ) {
            return;
        }

        if ( ! isset( $_COOKIE['souqpulse_session_id'] ) ) {
            $session_id = wp_generate_uuid4();
            // وضع ملف تعريف ارتباط ينتهي بإغلاق المتصفح (Session-only)
            setcookie( 'souqpulse_session_id', $session_id, 0, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
            $_COOKIE['souqpulse_session_id'] = $session_id;
        }
    }

    /**
     * الحصول على معرف الجلسة الحالي بأمان
     */
    public static function get_current_session_id() {
        if ( isset( $_COOKIE['souqpulse_session_id'] ) ) {
            return sanitize_key( wp_unslash( $_COOKIE['souqpulse_session_id'] ) );
        }
        return '';
    }

    /**
     * تسجيل حدث في قاعدة البيانات
     */
    public static function log_event( $event_type ) {
        global $wpdb;
        $session_id = self::get_current_session_id();
        if ( empty( $session_id ) ) {
            return;
        }

        $table_name = $wpdb->prefix . 'souqpulse_funnel_events';
        $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );

        if ( ! $table_exists ) {
            return;
        }

        // منع تكرار نفس الحدث في نفس الجلسة (للحصول على نسب تسرب دقيقة)
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE session_id = %s AND event_type = %s",
            $session_id,
            $event_type
        ) );

        if ( ! $exists ) {
            $wpdb->insert(
                $table_name,
                array(
                    'session_id' => $session_id,
                    'event_type' => sanitize_key( $event_type ),
                    'created_at' => current_time( 'mysql' )
                ),
                array( '%s', '%s', '%s' )
            );
        }
    }

    /**
     * تحميل سكريبت التتبع في الواجهة الأمامية للموقع
     */
    public function enqueue_tracker_script() {
        // عدم تتبع المشرفين داخل لوحة التحكم
        if ( is_admin() ) {
            return;
        }

        wp_enqueue_script( 'souqpulse-tracker-js', SOUQPULSE_URL . 'assets/js/tracker.js', array( 'jquery' ), SOUQPULSE_VERSION, true );

        wp_localize_script( 'souqpulse-tracker-js', 'souqpulseTrackerData', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'souqpulse_tracker_nonce' ),
        ) );
    }

    /**
     * تتبع أحداث تحميل الصفحات (view_session & begin_checkout)
     */
    public function track_page_views() {
        if ( is_admin() ) {
            return;
        }

        // 1. حدث الدخول الأول للموقع
        self::log_event( 'view_session' );

        // 2. حدث بدء الدفع (صفحة الـ Checkout)
        if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page() ) {
            self::log_event( 'begin_checkout' );
        }
    }

    /**
     * تتبع إضافة منتج للسلة
     */
    public function track_add_to_cart_event() {
        self::log_event( 'add_to_cart' );
    }

    /**
     * تتبع إتمام عملية الشراء (صفحة شكرًا لك)
     */
    public function track_purchase_event( $order_id ) {
        self::log_event( 'purchase' );
    }

    /**
     * معالج استقبال أحداث AJAX من الفرونت إند
     */
    public function ajax_track_event() {
        // التحقق من المعرف الأمني للطلب
        check_ajax_referer( 'souqpulse_tracker_nonce', 'security' );

        $allowed_events = array( 'add_to_cart', 'add_shipping_info', 'add_payment_info' );
        $event_type     = isset( $_POST['event_type'] ) ? sanitize_key( wp_unslash( $_POST['event_type'] ) ) : '';

        if ( in_array( $event_type, $allowed_events, true ) ) {
            self::log_event( $event_type );
            wp_send_json_success();
        }

        wp_send_json_error( array( 'message' => __( 'حدث غير مدعوم التتبع.', 'souq-pulse' ) ), 400 );
    }
}
