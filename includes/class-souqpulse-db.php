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
        global $wpdb;
        $table_name = $wpdb->prefix . 'souqpulse_funnel_events';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id varchar(64) NOT NULL,
            event_type varchar(32) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY event_type (event_type)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
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
                'sales'           => 0,
                'orders'          => 0,
                'aov'             => 0,
                'new_customers'   => 0,
                'sessions'        => 0,
                'bounce_rate'     => 0,
                'avg_duration'    => 0,
                'conversion_rate' => 0,
            ),
            'previous' => array(
                'sales'           => 0,
                'orders'          => 0,
                'aov'             => 0,
                'new_customers'   => 0,
                'sessions'        => 0,
                'bounce_rate'     => 0,
                'avg_duration'    => 0,
                'conversion_rate' => 0,
            ),
            'timeline'         => array(),
            'top_products'     => array(),
            'top_customers'    => array(),
            'customer_metrics' => array(
                'avg_clv'           => 0,
                'repeat_customers'  => 0,
                'onetime_customers' => 0,
            ),
            'inventory'        => array(
                'total_units'  => 0,
                'low_stock'    => 0,
                'out_of_stock' => 0,
            ),
            'geo'              => array(),
        );

        // 1. استعلام الملخص للفترة الحالية
        $summary_query = $wpdb->prepare(
            "SELECT SUM(total_sales) as total_sales, COUNT(order_id) as order_count 
             FROM {$wpdb->prefix}wc_order_stats 
             WHERE date_created >= %s AND date_created <= %s 
               AND status IN ('wc-completed', 'wc-processing', 'wc-on-hold', 'completed', 'processing', 'on-hold')",
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
               AND status IN ('wc-completed', 'wc-processing', 'wc-on-hold', 'completed', 'processing', 'on-hold') 
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
                   AND status IN ('wc-completed', 'wc-processing', 'wc-on-hold', 'completed', 'processing', 'on-hold')",
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
                   AND status IN ('wc-completed', 'wc-processing', 'wc-on-hold', 'completed', 'processing', 'on-hold') 
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
               AND status IN ('wc-completed', 'wc-processing', 'wc-on-hold', 'completed', 'processing', 'on-hold') 
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
               AND o.status IN ('wc-completed', 'wc-processing', 'wc-on-hold', 'completed', 'processing', 'on-hold') 
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

        // حساب معدل التحويل
        $results['current']['conversion_rate'] = $results['current']['sessions'] > 0 ? ( ( $results['current']['orders'] / $results['current']['sessions'] ) * 100 ) : 0;
        if ( $compare ) {
            $results['previous']['conversion_rate'] = $results['previous']['sessions'] > 0 ? ( ( $results['previous']['orders'] / $results['previous']['sessions'] ) * 100 ) : 0;
        }

        // حساب متوسط القيمة العمرية للعميل (CLV)
        $clv_query = "SELECT AVG(total_spend) FROM (
            SELECT SUM(total_sales) as total_spend
            FROM {$wpdb->prefix}wc_order_stats
            WHERE status IN ('wc-completed', 'wc-processing', 'wc-on-hold', 'completed', 'processing', 'on-hold')
              AND customer_id > 0
            GROUP BY customer_id
        ) as customer_spendings";
        $avg_clv = (float) $wpdb->get_var( $clv_query );

        // حساب العملاء المكررين والعملاء لمرة واحدة
        $cohort_query = "SELECT 
            COUNT(CASE WHEN order_count > 1 THEN 1 END) as repeat_count,
            COUNT(CASE WHEN order_count = 1 THEN 1 END) as onetime_count
        FROM (
            SELECT COUNT(order_id) as order_count
            FROM {$wpdb->prefix}wc_order_stats
            WHERE status IN ('wc-completed', 'wc-processing', 'wc-on-hold', 'completed', 'processing', 'on-hold')
              AND customer_id > 0
            GROUP BY customer_id
        ) as customer_orders";
        $cohorts = $wpdb->get_row( $cohort_query );

        $results['customer_metrics'] = array(
            'avg_clv'           => $avg_clv,
            'repeat_customers'  => $cohorts ? (int) $cohorts->repeat_count : 0,
            'onetime_customers' => $cohorts ? (int) $cohorts->onetime_count : 0,
        );

        // 7. استعلام مسار التحويل Funnel
        $funnel_table = $wpdb->prefix . 'souqpulse_funnel_events';
        $funnel_active = (bool) $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $funnel_table ) );

        $funnel_counts = array(
            'view_session'      => 0,
            'add_to_cart'       => 0,
            'begin_checkout'    => 0,
            'add_shipping_info' => 0,
            'add_payment_info'  => 0,
            'purchase'          => 0,
        );

        if ( $funnel_active ) {
            $funnel_query = $wpdb->prepare(
                "SELECT event_type, COUNT(DISTINCT session_id) as session_count 
                 FROM {$funnel_table} 
                 WHERE created_at >= %s AND created_at <= %s 
                 GROUP BY event_type",
                $start_date,
                $end_date
            );
            $funnel_rows = $wpdb->get_results( $funnel_query );

            foreach ( $funnel_rows as $row ) {
                if ( isset( $funnel_counts[ $row->event_type ] ) ) {
                    $funnel_counts[ $row->event_type ] = (int) $row->session_count;
                }
            }
        }

        // تحضير مصفوفة الـ Funnel بالترتيب وحساب النسب المئوية والتسرب
        $funnel_data = array();
        $steps = array(
            'view_session'      => __( 'زيارة الموقع', 'souq-pulse' ),
            'add_to_cart'       => __( 'إضافة للسلة', 'souq-pulse' ),
            'begin_checkout'    => __( 'بدء الدفع', 'souq-pulse' ),
            'add_shipping_info' => __( 'معلومات الشحن', 'souq-pulse' ),
            'add_payment_info'  => __( 'معلومات الدفع', 'souq-pulse' ),
            'purchase'          => __( 'الشراء', 'souq-pulse' ),
        );

        $total_sessions = $results['current']['sessions'] > 0 ? $results['current']['sessions'] : $funnel_counts['view_session'];
        if ( $total_sessions <= 0 ) {
            $total_sessions = 1; // تفادي القسمة على صفر
        }

        $prev_count = 0;
        $index = 0;

        foreach ( $steps as $type => $label ) {
            $count = $funnel_counts[ $type ];
            
            // النسبة من إجمالي الزيارات
            $pct_of_total = ( $count / $total_sessions ) * 100;

            // نسبة التسرب من الخطوة السابقة
            $drop_off = 0;
            if ( $index > 0 && $prev_count > 0 ) {
                $drop_off = ( ( $prev_count - $count ) / $prev_count ) * 100;
            }

            $funnel_data[] = array(
                'type'         => $type,
                'label'        => $label,
                'count'        => $count,
                'pct_of_total' => round( $pct_of_total, 1 ),
                'drop_off'     => round( $drop_off, 1 ),
            );

            $prev_count = $count;
            $index++;
        }

        $results['funnel'] = $funnel_data;

        // 8. استعلام بيانات المخزون (تحليلات المخزون)
        $low_stock_threshold = (int) get_option( 'woocommerce_notify_low_stock_amount', 2 );
        $lookup_table = $wpdb->prefix . 'wc_product_meta_lookup';
        
        $stock_stats = $wpdb->get_row( $wpdb->prepare(
            "SELECT 
                SUM(CASE WHEN stock_status = 'outofstock' THEN 1 ELSE 0 END) as out_of_stock_count,
                SUM(CASE WHEN stock_status = 'instock' AND stock_quantity IS NOT NULL AND stock_quantity <= %d THEN 1 ELSE 0 END) as low_stock_count,
                SUM(CASE WHEN stock_quantity IS NOT NULL AND stock_quantity > 0 THEN stock_quantity ELSE 0 END) as total_units
             FROM {$lookup_table}",
            $low_stock_threshold
        ) );

        $results['inventory'] = array(
            'total_units'  => $stock_stats ? (int) $stock_stats->total_units : 0,
            'low_stock'    => $stock_stats ? (int) $stock_stats->low_stock_count : 0,
            'out_of_stock' => $stock_stats ? (int) $stock_stats->out_of_stock_count : 0,
        );

        // 9. استعلام المؤشرات الجغرافية للمحافظات المصرية
        $geo_query = $wpdb->prepare(
            "SELECT c.state, COUNT(o.order_id) as order_count, SUM(o.total_sales) as total_sales
             FROM {$wpdb->prefix}wc_order_stats o
             JOIN {$wpdb->prefix}wc_customer_lookup c ON o.customer_id = c.customer_id
             WHERE o.status IN ('wc-completed', 'wc-processing', 'wc-on-hold', 'completed', 'processing', 'on-hold')
               AND c.country = 'EG'
               AND o.date_created >= %s AND o.date_created <= %s
             GROUP BY c.state
             ORDER BY total_sales DESC",
            $start_date,
            $end_date
        );
        $geo_rows = $wpdb->get_results( $geo_query );

        $eg_states = array(
            'C'   => __( 'القاهرة', 'souq-pulse' ),
            'KH'  => __( 'القاهرة', 'souq-pulse' ),
            'ALX' => __( 'الإسكندرية', 'souq-pulse' ),
            'GZ'  => __( 'الجيزة', 'souq-pulse' ),
            'QAL' => __( 'القليوبية', 'souq-pulse' ),
            'DK'  => __( 'الدقهلية', 'souq-pulse' ),
            'BH'  => __( 'البحيرة', 'souq-pulse' ),
            'FYM' => __( 'الفيوم', 'souq-pulse' ),
            'GH'  => __( 'الغربية', 'souq-pulse' ),
            'KB'  => __( 'المنوفية', 'souq-pulse' ),
            'IS'  => __( 'الإسماعيلية', 'souq-pulse' ),
            'SUZ' => __( 'السويس', 'souq-pulse' ),
            'PTS' => __( 'بورسعيد', 'souq-pulse' ),
            'ASW' => __( 'أسوان', 'souq-pulse' ),
            'AST' => __( 'أسيوط', 'souq-pulse' ),
            'BNS' => __( 'بني سويف', 'souq-pulse' ),
            'DA'  => __( 'دمياط', 'souq-pulse' ),
            'KSH' => __( 'كفر الشيخ', 'souq-pulse' ),
            'LX'  => __( 'الأقصر', 'souq-pulse' ),
            'MN'  => __( 'المنيا', 'souq-pulse' ),
            'MS'  => __( 'مطروح', 'souq-pulse' ),
            'NS'  => __( 'شمال سيناء', 'souq-pulse' ),
            'SHG' => __( 'سوهاج', 'souq-pulse' ),
            'SHR' => __( 'الشرقية', 'souq-pulse' ),
            'SS'  => __( 'جنوب سيناء', 'souq-pulse' ),
            'WAD' => __( 'الوادي الجديد', 'souq-pulse' ),
            'BA'  => __( 'البحر الأحمر', 'souq-pulse' ),
            'QNA' => __( 'قنا', 'souq-pulse' ),
        );

        $geo_data = array();
        $other_sales = 0;
        $other_orders = 0;

        foreach ( $geo_rows as $row ) {
            $state_code = strtoupper( $row->state );
            if ( isset( $eg_states[ $state_code ] ) ) {
                $state_name = $eg_states[ $state_code ];
                if ( isset( $geo_data[ $state_name ] ) ) {
                    $geo_data[ $state_name ]['sales']  += (float) $row->total_sales;
                    $geo_data[ $state_name ]['orders'] += (int) $row->order_count;
                } else {
                    $geo_data[ $state_name ] = array(
                        'name'   => $state_name,
                        'sales'  => (float) $row->total_sales,
                        'orders' => (int) $row->order_count,
                    );
                }
            } else {
                $other_sales  += (float) $row->total_sales;
                $other_orders += (int) $row->order_count;
            }
        }

        if ( $other_sales > 0 ) {
            $geo_data[ __( 'محافظات أخرى', 'souq-pulse' ) ] = array(
                'name'   => __( 'محافظات أخرى', 'souq-pulse' ),
                'sales'  => $other_sales,
                'orders' => $other_orders,
            );
        }

        // تحويلها لمصفوفة مفهرسة وترتيبها حسب المبيعات تنازلياً
        $geo_list = array_values( $geo_data );
        usort( $geo_list, function( $a, $b ) {
            return $b['sales'] <=> $a['sales'];
        } );

        $results['geo'] = $geo_list;

        // حفظ النتائج في كاش المؤقتات لمدة 15 دقيقة
        set_transient( $cache_key, $results, 15 * MINUTE_IN_SECONDS );

        return $results;
    }
}
