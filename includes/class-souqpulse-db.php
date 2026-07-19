<?php
/**
 * فئة التعامل مع قاعدة البيانات والكاش
 *
 * @package SouqPulse
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SouqPulse_DB {

    /**
     * دالة التفعيل لإعداد الجداول المخصصة
     */
    public static function activate() {
        // سيتم إنشاء جدول مسار العميل (Funnel Events) في المرحلة الخامسة
    }

    /**
     * جلب كافة بيانات المبيعات والعملاء للفترة المحددة
     *
     * @param string $start_date تاريخ البدء (Y-m-d H:i:s)
     * @param string $end_date تاريخ الانتهاء (Y-m-d H:i:s)
     * @param bool   $compare    هل يجب حساب مقارنة بالفترة السابقة؟
     * @return array
     */
    public static function get_sales_analytics( $start_date, $end_date, $compare = true ) {
        global $wpdb;

        // تنظيف مدخلات التواريخ لتجنب أي مشاكل أمنية
        $start_date = sanitize_text_field( $start_date );
        $end_date   = sanitize_text_field( $end_date );

        // مفتاح الكاش الفريد للفترة والخيارات المحددة
        $cache_key = 'souqpulse_sales_' . md5( $start_date . '_' . $end_date . '_' . ($compare ? '1' : '0') );
        $cached_data = get_transient( $cache_key );

        if ( false !== $cached_data ) {
            return $cached_data;
        }

        // حساب تواريخ الفترة السابقة للمقارنة
        $start = new DateTime( $start_date );
        $end   = new DateTime( $end_date );
        $diff  = $start->diff( $end );
        $days  = $diff->days + 1;

        $prev_start_obj = clone $start;
        $prev_start_obj->modify( "-{$days} days" );
        $prev_end_obj = clone $end;
        $prev_end_obj->modify( "-{$days} days" );

        $prev_start_date = $prev_start_obj->format( 'Y-m-d H:i:s' );
        $prev_end_date   = $prev_end_obj->format( 'Y-m-d H:i:s' );

        $results = array(
            'current' => array(
                'sales'         => 0,
                'orders'        => 0,
                'aov'           => 0,
                'new_customers' => 0,
                'sessions'      => 0,
                'bounce_rate'   => 0,
                'avg_duration'  => 0,
            ),
            'previous' => array(
                'sales'         => 0,
                'orders'        => 0,
                'aov'           => 0,
                'new_customers' => 0,
                'sessions'      => 0,
                'bounce_rate'   => 0,
                'avg_duration'  => 0,
            ),
            'timeline'      => array(),
            'top_products'  => array(),
            'top_customers' => array(),
        );

        // 1. استعلام الملخص للفترة الحالية
        $summary_query = $wpdb->prepare(
            "SELECT SUM(total_sales) as total_sales, COUNT(order_id) as order_count 
             FROM {$wpdb->prefix}wc_order_stats 
             WHERE date_created >= %s AND date_created <= %s 
               AND status IN ('completed', 'processing', 'on-hold')",
            $start_date,
            $end_date
        );
        $summary = $wpdb->get_row( $summary_query );

        if ( $summary ) {
            $results['current']['sales']  = (float) $summary->total_sales;
            $results['current']['orders'] = (int) $summary->order_count;
            $results['current']['aov']    = $results['current']['orders'] > 0 ? ($results['current']['sales'] / $results['current']['orders']) : 0;
        }

        // 2. استعلام عدد العملاء الجدد للفترة الحالية (returning_customer = 0)
        $new_cust_query = $wpdb->prepare(
            "SELECT COUNT(DISTINCT customer_id) as new_cust 
             FROM {$wpdb->prefix}wc_order_stats 
             WHERE date_created >= %s AND date_created <= %s 
               AND status IN ('completed', 'processing', 'on-hold') 
               AND returning_customer = 0",
            $start_date,
            $end_date
        );
        $results['current']['new_customers'] = (int) $wpdb->get_var( $new_cust_query );

        // 3. حساب الفترة السابقة للمقارنة
        if ( $compare ) {
            $prev_summary_query = $wpdb->prepare(
                "SELECT SUM(total_sales) as total_sales, COUNT(order_id) as order_count 
                 FROM {$wpdb->prefix}wc_order_stats 
                 WHERE date_created >= %s AND date_created <= %s 
                   AND status IN ('completed', 'processing', 'on-hold')",
                $prev_start_date,
                $prev_end_date
            );
            $prev_summary = $wpdb->get_row( $prev_summary_query );

            if ( $prev_summary ) {
                $results['previous']['sales']  = (float) $prev_summary->total_sales;
                $results['previous']['orders'] = (int) $prev_summary->order_count;
                $results['previous']['aov']    = $results['previous']['orders'] > 0 ? ($results['previous']['sales'] / $results['previous']['orders']) : 0;
            }

            $prev_new_cust_query = $wpdb->prepare(
                "SELECT COUNT(DISTINCT customer_id) as new_cust 
                 FROM {$wpdb->prefix}wc_order_stats 
                 WHERE date_created >= %s AND date_created <= %s 
                   AND status IN ('completed', 'processing', 'on-hold') 
                   AND returning_customer = 0",
                $prev_start_date,
                $prev_end_date
            );
            $results['previous']['new_customers'] = (int) $wpdb->get_var( $prev_new_cust_query );
        }

        // 3.5. استعلام بيانات الزيارات من WP Statistics إن وجدت الجداول
        $visitor_table = $wpdb->prefix . 'statistics_visitor';
        $stats_active = (bool) $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $visitor_table ) );

        if ( $stats_active ) {
            // الاستعلام للفترة الحالية
            $curr_stats_query = $wpdb->prepare(
                "SELECT 
                    COUNT(ID) as sessions,
                    COUNT(CASE WHEN hits <= 1 THEN 1 END) as bounced_count,
                    AVG(hits) as avg_hits
                 FROM {$visitor_table}
                 WHERE last_visit >= %s AND last_visit <= %s",
                $start_date,
                $end_date
            );
            $curr_stats = $wpdb->get_row( $curr_stats_query );

            if ( $curr_stats ) {
                $results['current']['sessions']    = (int) $curr_stats->sessions;
                $results['current']['bounce_rate'] = $results['current']['sessions'] > 0 ? ( ( (int) $curr_stats->bounced_count / $results['current']['sessions'] ) * 100 ) : 0;
                $results['current']['avg_duration'] = round( (float) $curr_stats->avg_hits * 45 ); // بالثواني
            }

            // الاستعلام للفترة السابقة
            if ( $compare ) {
                $prev_stats_query = $wpdb->prepare(
                    "SELECT 
                        COUNT(ID) as sessions,
                        COUNT(CASE WHEN hits <= 1 THEN 1 END) as bounced_count,
                        AVG(hits) as avg_hits
                     FROM {$visitor_table}
                     WHERE last_visit >= %s AND last_visit <= %s",
                    $prev_start_date,
                    $prev_end_date
                );
                $prev_stats = $wpdb->get_row( $prev_stats_query );

                if ( $prev_stats ) {
                    $results['previous']['sessions']    = (int) $prev_stats->sessions;
                    $results['previous']['bounce_rate'] = $results['previous']['sessions'] > 0 ? ( ( (int) $prev_stats->bounced_count / $results['previous']['sessions'] ) * 100 ) : 0;
                    $results['previous']['avg_duration'] = round( (float) $prev_stats->avg_hits * 45 ); // بالثواني
                }
            }
        }

        // 4. استعلام الخط البياني الزمني للمبيعات والطلبات يومياً
        $timeline_query = $wpdb->prepare(
            "SELECT DATE(date_created) as day, SUM(total_sales) as sales, COUNT(order_id) as orders 
             FROM {$wpdb->prefix}wc_order_stats 
             WHERE date_created >= %s AND date_created <= %s 
               AND status IN ('completed', 'processing', 'on-hold') 
             GROUP BY DATE(date_created) 
             ORDER BY day ASC",
            $start_date,
            $end_date
        );
        $timeline_rows = $wpdb->get_results( $timeline_query );
        
        foreach ( $timeline_rows as $row ) {
            $results['timeline'][] = array(
                'day'    => $row->day,
                'sales'  => (float) $row->sales,
                'orders' => (int) $row->orders,
            );
        }

        // 5. استعلام أعلى 5 منتجات مبيعاً
        $products_query = $wpdb->prepare(
            "SELECT p.product_id, post.post_title as name, SUM(p.product_net_revenue) as revenue 
             FROM {$wpdb->prefix}wc_order_product_lookup p 
             LEFT JOIN {$wpdb->posts} post ON p.product_id = post.ID 
             WHERE p.date_created >= %s AND p.date_created <= %s 
             GROUP BY p.product_id, post.post_title 
             ORDER BY revenue DESC 
             LIMIT 5",
            $start_date,
            $end_date
        );
        $product_rows = $wpdb->get_results( $products_query );

        foreach ( $product_rows as $row ) {
            $results['top_products'][] = array(
                'id'      => (int) $row->product_id,
                'name'    => $row->name ? $row->name : __( 'منتج غير معروف', 'souq-pulse' ),
                'revenue' => (float) $row->revenue,
            );
        }

        // 6. استعلام أعلى 5 عملاء حسب قيمة المشتريات
        $customers_query = $wpdb->prepare(
            "SELECT o.customer_id, c.first_name, c.last_name, c.email, 
                    COUNT(o.order_id) as order_count, SUM(o.total_sales) as total_spend 
             FROM {$wpdb->prefix}wc_order_stats o 
             LEFT JOIN {$wpdb->prefix}wc_customer_lookup c ON o.customer_id = c.customer_id 
             WHERE o.date_created >= %s AND o.date_created <= %s 
               AND o.status IN ('completed', 'processing', 'on-hold') 
             GROUP BY o.customer_id, c.first_name, c.last_name, c.email 
             ORDER BY total_spend DESC 
             LIMIT 5",
            $start_date,
            $end_date
        );
        $customer_rows = $wpdb->get_results( $customers_query );

        foreach ( $customer_rows as $row ) {
            $name = trim( $row->first_name . ' ' . $row->last_name );
            if ( empty( $name ) ) {
                $name = $row->email ? $row->email : __( 'عميل زائر', 'souq-pulse' );
            }
            $results['top_customers'][] = array(
                'customer_id' => (int) $row->customer_id,
                'name'        => $name,
                'orders'      => (int) $row->order_count,
                'total_spend' => (float) $row->total_spend,
            );
        }

        // حفظ النتائج في كاش المؤقتات لمدة 15 دقيقة
        set_transient( $cache_key, $results, 15 * MINUTE_IN_SECONDS );

        return $results;
    }
}
