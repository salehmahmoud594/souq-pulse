<?php
/**
 * معالج طلبات AJAX للوحة التحكم
 *
 * @package SouqPulse
 */

if (!defined('ABSPATH')) {
    exit;
}

class SouqPulse_AJAX
{

    /**
     * تهيئة الخطافات وتسجيل إجراءات AJAX
     */
    public function __construct()
    {
        add_action('wp_ajax_souqpulse_get_dashboard_data', array($this, 'get_dashboard_data'));
        add_action('wp_ajax_souqpulse_get_realtime_count', array($this, 'get_realtime_count'));
    }

    /**
     * جلب بيانات لوحة التحكم وإرجاعها بصيغة JSON
     */
    public function get_dashboard_data()
    {
        // 1. التحقق من Nonce للأمان وحماية CSRF
        check_ajax_referer('souqpulse_admin_nonce', 'security');

        // 2. التحقق من صلاحيات المستخدم الحالي
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'ليس لديك صلاحية للوصول إلى هذه البيانات.'), 403);
        }

        // 3. استقبال وتطهير معاملات الطلب
        $range = isset($_POST['range']) ? sanitize_key(wp_unslash($_POST['range'])) : '30days';
        $compare = isset($_POST['compare']) && wp_unslash($_POST['compare']) === 'true';

        // حساب التواريخ بناءً على المدخلات وتوقيت المتجر المحلي
        $end_time = current_time('timestamp');
        $end_date = date('Y-m-d H:i:s', $end_time);

        if ('7days' === $range) {
            $start_date = date('Y-m-d H:i:s', strtotime('-6 days 00:00:00', $end_time));
        } elseif ('30days' === $range) {
            $start_date = date('Y-m-d H:i:s', strtotime('-29 days 00:00:00', $end_time));
        } elseif ('90days' === $range) {
            $start_date = date('Y-m-d H:i:s', strtotime('-89 days 00:00:00', $end_time));
        } elseif ('6months' === $range) {
            $start_date = date('Y-m-d H:i:s', strtotime('-179 days 00:00:00', $end_time));
        } elseif ('12months' === $range) {
            $start_date = date('Y-m-d H:i:s', strtotime('-364 days 00:00:00', $end_time));
        } elseif ('alltime' === $range) {
            $start_date = '2000-01-01 00:00:00';
            $compare = false; // لا توجد فترة سابقة لمقارنتها بكل الوقت
        } elseif ('custom' === $range) {
            $raw_start = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
            $raw_end = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';

            $start_date = !empty($raw_start) ? $raw_start . ' 00:00:00' : date('Y-m-d H:i:s', strtotime('-29 days 00:00:00', $end_time));
            $end_date = !empty($raw_end) ? $raw_end . ' 23:59:59' : date('Y-m-d H:i:s', $end_time);
        } else {
            $start_date = date('Y-m-d H:i:s', strtotime('-29 days 00:00:00', $end_time));
        }

        // 4. استرجاع البيانات من طبقة قاعدة البيانات
        $analytics_data = SouqPulse_DB::get_sales_analytics($start_date, $end_date, $compare);

        if (is_wp_error($analytics_data)) {
            wp_send_json_error($analytics_data->get_error_message());
        }

        // 4.5. جلب عدد المستخدمين النشطين بالوقت الفعلي (يتجاوز كاش الـ 15 دقيقة للتحديث اللحظي)
        $analytics_data['realtime_active_users'] = SouqPulse_Tracker::get_active_sessions_count();

        // 5. إرجاع النتيجة بنجاح
        wp_send_json_success($analytics_data);
    }

    /**
     * جلب عدد الزوار النشطين الآن فقط بصيغة خفيفة جداً لتحديث الكارت دورياً
     */
    public function get_realtime_count()
    {
        // التحقق من المعرف الأمني والترخيص
        check_ajax_referer('souqpulse_admin_nonce', 'security');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'غير مصرح.'), 403);
        }

        $count = SouqPulse_Tracker::get_active_sessions_count();

        wp_send_json_success(array('count' => $count));
    }
}
