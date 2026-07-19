<?php
/**
 * الفئة الرئيسية لتشغيل الإضافة (Loader Class)
 *
 * @package SouqPulse
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SouqPulse {

    /**
     * نسخة واحدة من الفئة (Singleton Instance)
     *
     * @var SouqPulse
     */
    private static $instance = null;

    /**
     * الحصول على النسخة الفريدة من الفئة
     *
     * @return SouqPulse
     */
    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * المشيّد الخاص لمنع إنشاء كائنات جديدة خارج الفئة
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_components();
    }

    /**
     * تحميل الملفات المطلوبة
     */
    private function load_dependencies() {
        require_once SOUQPULSE_PATH . 'includes/class-souqpulse-db.php';
        require_once SOUQPULSE_PATH . 'includes/class-souqpulse-admin.php';
    }

    /**
     * تهيئة المكونات وتفعيل الخطافات
     */
    private function init_components() {
        // تهيئة وحدة التحكم الإدارية
        if ( is_admin() ) {
            new SouqPulse_Admin();
        }
    }
}
