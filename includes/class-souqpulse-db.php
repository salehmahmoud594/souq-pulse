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
     * الخاصية الإستاتيكية لتخزين حالة تفعيل HPOS
     *
     * @var bool|null
     */
    private static $is_hpos_enabled = null;

    /**
     * التحقق مما إذا كانت جداول HPOS المخصصة مفعّلة في WooCommerce
     *
     * @return bool
     */
    private static function is_hpos_enabled() {
        if ( null === self::$is_hpos_enabled ) {
            self::$is_hpos_enabled = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
                && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        }
        return self::$is_hpos_enabled;
    }

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

        // مفتاح الكاش الفريد للفترة والخيارات المحددة شامل اختيار اللغة
        $cache_key   = 'souqpulse_sales_' . md5( $start_date . '_' . $end_date . '_' . ( $compare ? '1' : '0' ) . '_' . get_locale() );
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

        $is_hpos = self::is_hpos_enabled();
        $statuses = array( 'wc-completed', 'wc-processing', 'wc-on-hold', 'wc-refunded' );
        $statuses_in = "'" . implode( "', '", array_map( 'esc_sql', $statuses ) ) . "'";

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

        // 1. استعلام إجمالي المبيعات والمرتجعات للفترة الحالية
        if ( $is_hpos ) {
            $gross_query = $wpdb->prepare(
                "SELECT SUM(total_amount) as gross_sales, COUNT(id) as order_count
                 FROM {$wpdb->prefix}wc_orders
                 WHERE type = 'shop_order'
                   AND status IN ({$statuses_in})
                   AND date_created_gmt >= %s AND date_created_gmt <= %s",
                $start_date,
                $end_date
            );
            $gross_row = $wpdb->get_row( $gross_query );

            $refund_query = $wpdb->prepare(
                "SELECT SUM(ABS(r.total_amount)) as total_refunds
                 FROM {$wpdb->prefix}wc_orders r
                 JOIN {$wpdb->prefix}wc_orders o ON r.parent_order_id = o.id
                 WHERE r.type = 'shop_order_refund'
                   AND o.type = 'shop_order'
                   AND o.status IN ({$statuses_in})
                   AND o.date_created_gmt >= %s AND o.date_created_gmt <= %s",
                $start_date,
                $end_date
            );
            $total_refunds = (float) $wpdb->get_var( $refund_query );
        } else {
            $gross_query = $wpdb->prepare(
                "SELECT SUM(CAST(pm.meta_value AS DECIMAL(26,8))) as gross_sales, COUNT(p.ID) as order_count
                 FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_order_total'
                 WHERE p.post_type = 'shop_order'
                   AND p.post_status IN ({$statuses_in})
                   AND p.post_date >= %s AND p.post_date <= %s",
                $start_date,
                $end_date
            );
            $gross_row = $wpdb->get_row( $gross_query );

            $refund_query = $wpdb->prepare(
                "SELECT SUM(CAST(ABS(CAST(pm.meta_value AS DECIMAL(26,8))) AS DECIMAL(26,8))) as total_refunds
                 FROM {$wpdb->posts} r
                 JOIN {$wpdb->posts} o ON r.post_parent = o.ID
                 LEFT JOIN {$wpdb->postmeta} pm ON r.ID = pm.post_id AND pm.meta_key = '_order_total'
                 WHERE r.post_type = 'shop_order_refund'
                   AND o.post_type = 'shop_order'
                   AND o.post_status IN ({$statuses_in})
                   AND o.post_date >= %s AND o.post_date <= %s",
                $start_date,
                $end_date
            );
            $total_refunds = (float) $wpdb->get_var( $refund_query );
        }

        if ( $gross_row ) {
            $gross_sales = (float) $gross_row->gross_sales;
            $net_sales   = max( 0, $gross_sales - $total_refunds );

            $results['current']['sales']  = $net_sales;
            $results['current']['orders'] = (int) $gross_row->order_count;
            $results['current']['aov']    = $results['current']['orders'] > 0 ? ( $net_sales / $results['current']['orders'] ) : 0;
        }

        // 2. استعلام عدد العملاء الجدد للفترة الحالية
        if ( $is_hpos ) {
            $new_cust_query = $wpdb->prepare(
                "SELECT COUNT(DISTINCT customer_identifier) FROM (
                    SELECT 
                        CASE WHEN customer_id > 0 THEN CAST(customer_id AS CHAR) ELSE billing_email END as customer_identifier,
                        MIN(date_created_gmt) as first_order_date
                    FROM {$wpdb->prefix}wc_orders
                    WHERE type = 'shop_order'
                      AND status IN ({$statuses_in})
                    GROUP BY customer_identifier
                ) as customer_first_orders
                WHERE first_order_date >= %s AND first_order_date <= %s",
                $start_date,
                $end_date
            );
        } else {
            $new_cust_query = $wpdb->prepare(
                "SELECT COUNT(DISTINCT customer_identifier) FROM (
                    SELECT 
                        CASE 
                            WHEN pm_cust.meta_value IS NOT NULL AND CAST(pm_cust.meta_value AS UNSIGNED) > 0 
                            THEN pm_cust.meta_value 
                            ELSE pm_email.meta_value 
                        END as customer_identifier,
                        MIN(p.post_date) as first_order_date
                    FROM {$wpdb->posts} p
                    LEFT JOIN {$wpdb->postmeta} pm_cust ON p.ID = pm_cust.post_id AND pm_cust.meta_key = '_customer_user'
                    LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_billing_email'
                    WHERE p.post_type = 'shop_order'
                      AND p.post_status IN ({$statuses_in})
                    GROUP BY customer_identifier
                ) as customer_first_orders
                WHERE first_order_date >= %s AND first_order_date <= %s",
                $start_date,
                $end_date
            );
        }
        $results['current']['new_customers'] = (int) $wpdb->get_var( $new_cust_query );

        // 3. حساب الفترة السابقة للمقارنة
        if ( $compare ) {
            if ( $is_hpos ) {
                $prev_gross_query = $wpdb->prepare(
                    "SELECT SUM(total_amount) as gross_sales, COUNT(id) as order_count
                     FROM {$wpdb->prefix}wc_orders
                     WHERE type = 'shop_order'
                       AND status IN ({$statuses_in})
                       AND date_created_gmt >= %s AND date_created_gmt <= %s",
                    $prev_start_date,
                    $prev_end_date
                );
                $prev_gross_row = $wpdb->get_row( $prev_gross_query );

                $prev_refund_query = $wpdb->prepare(
                    "SELECT SUM(ABS(r.total_amount)) as total_refunds
                     FROM {$wpdb->prefix}wc_orders r
                     JOIN {$wpdb->prefix}wc_orders o ON r.parent_order_id = o.id
                     WHERE r.type = 'shop_order_refund'
                       AND o.type = 'shop_order'
                       AND o.status IN ({$statuses_in})
                       AND o.date_created_gmt >= %s AND o.date_created_gmt <= %s",
                    $prev_start_date,
                    $prev_end_date
                );
                $prev_total_refunds = (float) $wpdb->get_var( $prev_refund_query );
            } else {
                $prev_gross_query = $wpdb->prepare(
                    "SELECT SUM(CAST(pm.meta_value AS DECIMAL(26,8))) as gross_sales, COUNT(p.ID) as order_count
                     FROM {$wpdb->posts} p
                     LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_order_total'
                     WHERE p.post_type = 'shop_order'
                       AND p.post_status IN ({$statuses_in})
                       AND p.post_date >= %s AND p.post_date <= %s",
                    $prev_start_date,
                    $prev_end_date
                );
                $prev_gross_row = $wpdb->get_row( $prev_gross_query );

                $prev_refund_query = $wpdb->prepare(
                    "SELECT SUM(CAST(ABS(CAST(pm.meta_value AS DECIMAL(26,8))) AS DECIMAL(26,8))) as total_refunds
                     FROM {$wpdb->posts} r
                     JOIN {$wpdb->posts} o ON r.post_parent = o.ID
                     LEFT JOIN {$wpdb->postmeta} pm ON r.ID = pm.post_id AND pm.meta_key = '_order_total'
                     WHERE r.post_type = 'shop_order_refund'
                       AND o.post_type = 'shop_order'
                       AND o.post_status IN ({$statuses_in})
                       AND o.post_date >= %s AND o.post_date <= %s",
                    $prev_start_date,
                    $prev_end_date
                );
                $prev_total_refunds = (float) $wpdb->get_var( $prev_refund_query );
            }

            if ( $prev_gross_row ) {
                $prev_gross_sales = (float) $prev_gross_row->gross_sales;
                $prev_net_sales   = max( 0, $prev_gross_sales - $prev_total_refunds );

                $results['previous']['sales']  = $prev_net_sales;
                $results['previous']['orders'] = (int) $prev_gross_row->order_count;
                $results['previous']['aov']    = $results['previous']['orders'] > 0 ? ( $prev_net_sales / $results['previous']['orders'] ) : 0;
            }

            if ( $is_hpos ) {
                $prev_new_cust_query = $wpdb->prepare(
                    "SELECT COUNT(DISTINCT customer_identifier) FROM (
                        SELECT 
                            CASE WHEN customer_id > 0 THEN CAST(customer_id AS CHAR) ELSE billing_email END as customer_identifier,
                            MIN(date_created_gmt) as first_order_date
                        FROM {$wpdb->prefix}wc_orders
                        WHERE type = 'shop_order'
                          AND status IN ({$statuses_in})
                        GROUP BY customer_identifier
                    ) as customer_first_orders
                    WHERE first_order_date >= %s AND first_order_date <= %s",
                    $prev_start_date,
                    $prev_end_date
                );
            } else {
                $prev_new_cust_query = $wpdb->prepare(
                    "SELECT COUNT(DISTINCT customer_identifier) FROM (
                        SELECT 
                            CASE 
                                WHEN pm_cust.meta_value IS NOT NULL AND CAST(pm_cust.meta_value AS UNSIGNED) > 0 
                                THEN pm_cust.meta_value 
                                ELSE pm_email.meta_value 
                            END as customer_identifier,
                            MIN(p.post_date) as first_order_date
                        FROM {$wpdb->posts} p
                        LEFT JOIN {$wpdb->postmeta} pm_cust ON p.ID = pm_cust.post_id AND pm_cust.meta_key = '_customer_user'
                        LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_billing_email'
                        WHERE p.post_type = 'shop_order'
                          AND p.post_status IN ({$statuses_in})
                        GROUP BY customer_identifier
                    ) as customer_first_orders
                    WHERE first_order_date >= %s AND first_order_date <= %s",
                    $prev_start_date,
                    $prev_end_date
                );
            }
            $results['previous']['new_customers'] = (int) $wpdb->get_var( $prev_new_cust_query );
        }

        // 3.5. استعلام بيانات الزيارات من WP Statistics إن وجدت الجداول
        $visitor_table = $wpdb->prefix . 'statistics_visitor';
        $stats_active = (bool) $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $visitor_table ) );

        if ( $stats_active ) {
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
                $results['current']['sessions']     = (int) $curr_stats->sessions;
                $results['current']['bounce_rate']  = $results['current']['sessions'] > 0 ? ( ( (int) $curr_stats->bounced_count / $results['current']['sessions'] ) * 100 ) : 0;
                $results['current']['avg_duration'] = round( (float) $curr_stats->avg_hits * 45 );
            }

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
                    $results['previous']['sessions']     = (int) $prev_stats->sessions;
                    $results['previous']['bounce_rate']  = $results['previous']['sessions'] > 0 ? ( ( (int) $prev_stats->bounced_count / $results['previous']['sessions'] ) * 100 ) : 0;
                    $results['previous']['avg_duration'] = round( (float) $prev_stats->avg_hits * 45 );
                }
            }
        }

        // 4. استعلام الخط البياني الزمني للمبيعات والطلبات يومياً
        if ( $is_hpos ) {
            $timeline_gross = $wpdb->prepare(
                "SELECT DATE(date_created_gmt) as day, SUM(total_amount) as sales, COUNT(id) as orders
                 FROM {$wpdb->prefix}wc_orders
                 WHERE type = 'shop_order'
                   AND status IN ({$statuses_in})
                   AND date_created_gmt >= %s AND date_created_gmt <= %s
                 GROUP BY DATE(date_created_gmt)
                 ORDER BY day ASC",
                $start_date,
                $end_date
            );
            $gross_rows = $wpdb->get_results( $timeline_gross );

            $timeline_refunds = $wpdb->prepare(
                "SELECT DATE(o.date_created_gmt) as day, SUM(ABS(r.total_amount)) as refund_sales
                 FROM {$wpdb->prefix}wc_orders r
                 JOIN {$wpdb->prefix}wc_orders o ON r.parent_order_id = o.id
                 WHERE r.type = 'shop_order_refund'
                   AND o.type = 'shop_order'
                   AND o.status IN ({$statuses_in})
                   AND o.date_created_gmt >= %s AND o.date_created_gmt <= %s
                 GROUP BY DATE(o.date_created_gmt)",
                $start_date,
                $end_date
            );
            $refund_rows = $wpdb->get_results( $timeline_refunds );
        } else {
            $timeline_gross = $wpdb->prepare(
                "SELECT DATE(p.post_date) as day, SUM(CAST(pm.meta_value AS DECIMAL(26,8))) as sales, COUNT(p.ID) as orders
                 FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_order_total'
                 WHERE p.post_type = 'shop_order'
                   AND p.post_status IN ({$statuses_in})
                   AND p.post_date >= %s AND p.post_date <= %s
                 GROUP BY DATE(p.post_date)
                 ORDER BY day ASC",
                $start_date,
                $end_date
            );
            $gross_rows = $wpdb->get_results( $timeline_gross );

            $timeline_refunds = $wpdb->prepare(
                "SELECT DATE(o.post_date) as day, SUM(CAST(ABS(CAST(pm.meta_value AS DECIMAL(26,8))) AS DECIMAL(26,8))) as refund_sales
                 FROM {$wpdb->posts} r
                 JOIN {$wpdb->posts} o ON r.post_parent = o.ID
                 LEFT JOIN {$wpdb->postmeta} pm ON r.ID = pm.post_id AND pm.meta_key = '_order_total'
                 WHERE r.post_type = 'shop_order_refund'
                   AND o.post_type = 'shop_order'
                   AND o.post_status IN ({$statuses_in})
                   AND o.post_date >= %s AND o.post_date <= %s
                 GROUP BY DATE(o.post_date)",
                $start_date,
                $end_date
            );
            $refund_rows = $wpdb->get_results( $timeline_refunds );
        }

        $refunds_by_day = array();
        foreach ( $refund_rows as $rf ) {
            $refunds_by_day[ $rf->day ] = (float) $rf->refund_sales;
        }

        foreach ( $gross_rows as $row ) {
            $day_refund = isset( $refunds_by_day[ $row->day ] ) ? $refunds_by_day[ $row->day ] : 0;
            $net_day_sales = max( 0, (float) $row->sales - $day_refund );

            $results['timeline'][] = array(
                'day'    => $row->day,
                'sales'  => $net_day_sales,
                'orders' => (int) $row->orders,
            );
        }

        // 5. استعلام أعلى 5 منتجات مبيعاً
        if ( $is_hpos ) {
            $products_query = $wpdb->prepare(
                "SELECT i_meta.meta_value as product_id, post.post_title as name, SUM(CAST(tot_meta.meta_value AS DECIMAL(26,8))) as revenue
                 FROM {$wpdb->prefix}woocommerce_order_items items
                 JOIN {$wpdb->prefix}woocommerce_order_itemmeta i_meta ON items.order_item_id = i_meta.order_item_id AND i_meta.meta_key = '_product_id'
                 JOIN {$wpdb->prefix}woocommerce_order_itemmeta tot_meta ON items.order_item_id = tot_meta.order_item_id AND tot_meta.meta_key = '_line_total'
                 LEFT JOIN {$wpdb->posts} post ON CAST(i_meta.meta_value AS UNSIGNED) = post.ID
                 WHERE items.order_item_type = 'line_item'
                   AND items.order_id IN (
                       SELECT id FROM {$wpdb->prefix}wc_orders
                       WHERE type = 'shop_order'
                         AND status IN ({$statuses_in})
                         AND date_created_gmt >= %s AND date_created_gmt <= %s
                       UNION ALL
                       SELECT r.id FROM {$wpdb->prefix}wc_orders r
                       JOIN {$wpdb->prefix}wc_orders o ON r.parent_order_id = o.id
                       WHERE r.type = 'shop_order_refund'
                         AND o.type = 'shop_order'
                         AND o.status IN ({$statuses_in})
                         AND o.date_created_gmt >= %s AND o.date_created_gmt <= %s
                   )
                 GROUP BY i_meta.meta_value, post.post_title
                 ORDER BY revenue DESC
                 LIMIT 5",
                $start_date,
                $end_date,
                $start_date,
                $end_date
            );
        } else {
            $products_query = $wpdb->prepare(
                "SELECT i_meta.meta_value as product_id, post.post_title as name, SUM(CAST(tot_meta.meta_value AS DECIMAL(26,8))) as revenue
                 FROM {$wpdb->prefix}woocommerce_order_items items
                 JOIN {$wpdb->prefix}woocommerce_order_itemmeta i_meta ON items.order_item_id = i_meta.order_item_id AND i_meta.meta_key = '_product_id'
                 JOIN {$wpdb->prefix}woocommerce_order_itemmeta tot_meta ON items.order_item_id = tot_meta.order_item_id AND tot_meta.meta_key = '_line_total'
                 LEFT JOIN {$wpdb->posts} post ON CAST(i_meta.meta_value AS UNSIGNED) = post.ID
                 WHERE items.order_item_type = 'line_item'
                   AND items.order_id IN (
                       SELECT ID FROM {$wpdb->posts}
                       WHERE post_type = 'shop_order'
                         AND post_status IN ({$statuses_in})
                         AND post_date >= %s AND post_date <= %s
                       UNION ALL
                       SELECT r.ID FROM {$wpdb->posts} r
                       JOIN {$wpdb->posts} o ON r.post_parent = o.ID
                       WHERE r.post_type = 'shop_order_refund'
                         AND o.post_type = 'shop_order'
                         AND o.post_status IN ({$statuses_in})
                         AND o.post_date >= %s AND o.post_date <= %s
                   )
                 GROUP BY i_meta.meta_value, post.post_title
                 ORDER BY revenue DESC
                 LIMIT 5",
                $start_date,
                $end_date,
                $start_date,
                $end_date
            );
        }
        $product_rows = $wpdb->get_results( $products_query );

        foreach ( $product_rows as $row ) {
            $results['top_products'][] = array(
                'id'      => (int) $row->product_id,
                'name'    => $row->name ? $row->name : __( 'منتج غير معروف', 'souq-pulse' ),
                'revenue' => (float) $row->revenue,
            );
        }

        // 6. استعلام أعلى 5 عملاء حسب صافي قيمة الشراء
        if ( $is_hpos ) {
            $customers_query = $wpdb->prepare(
                "SELECT 
                    cust_id,
                    email,
                    COUNT(order_id) as order_count,
                    SUM(gross_amount - refund_amount) as total_spend
                 FROM (
                    SELECT 
                        o.id as order_id,
                        o.customer_id as cust_id,
                        o.billing_email as email,
                        o.total_amount as gross_amount,
                        COALESCE(SUM(ABS(r.total_amount)), 0) as refund_amount
                    FROM {$wpdb->prefix}wc_orders o
                    LEFT JOIN {$wpdb->prefix}wc_orders r ON r.parent_order_id = o.id AND r.type = 'shop_order_refund'
                    WHERE o.type = 'shop_order'
                      AND o.status IN ({$statuses_in})
                      AND o.date_created_gmt >= %s AND o.date_created_gmt <= %s
                    GROUP BY o.id, o.customer_id, o.billing_email, o.total_amount
                 ) as order_totals
                 GROUP BY cust_id, email
                 ORDER BY total_spend DESC
                 LIMIT 5",
                $start_date,
                $end_date
            );
        } else {
            $customers_query = $wpdb->prepare(
                "SELECT 
                    cust_id,
                    email,
                    COUNT(order_id) as order_count,
                    SUM(gross_amount - refund_amount) as total_spend
                 FROM (
                    SELECT 
                        o.ID as order_id,
                        COALESCE(pm_cust.meta_value, 0) as cust_id,
                        pm_email.meta_value as email,
                        CAST(pm_tot.meta_value AS DECIMAL(26,8)) as gross_amount,
                        COALESCE(SUM(CAST(ABS(CAST(pm_ref.meta_value AS DECIMAL(26,8))) AS DECIMAL(26,8))), 0) as refund_amount
                    FROM {$wpdb->posts} o
                    LEFT JOIN {$wpdb->postmeta} pm_cust ON o.ID = pm_cust.post_id AND pm_cust.meta_key = '_customer_user'
                    LEFT JOIN {$wpdb->postmeta} pm_email ON o.ID = pm_email.post_id AND pm_email.meta_key = '_billing_email'
                    LEFT JOIN {$wpdb->postmeta} pm_tot ON o.ID = pm_tot.post_id AND pm_tot.meta_key = '_order_total'
                    LEFT JOIN {$wpdb->posts} r ON r.post_parent = o.ID AND r.post_type = 'shop_order_refund'
                    LEFT JOIN {$wpdb->postmeta} pm_ref ON r.ID = pm_ref.post_id AND pm_ref.meta_key = '_order_total'
                    WHERE o.post_type = 'shop_order'
                      AND o.post_status IN ({$statuses_in})
                      AND o.post_date >= %s AND o.post_date <= %s
                    GROUP BY o.ID, pm_cust.meta_value, pm_email.meta_value, pm_tot.meta_value
                 ) as order_totals
                 GROUP BY cust_id, email
                 ORDER BY total_spend DESC
                 LIMIT 5",
                $start_date,
                $end_date
            );
        }
        $customer_rows = $wpdb->get_results( $customers_query );

        foreach ( $customer_rows as $row ) {
            $customer_name = '';
            if ( ! empty( $row->cust_id ) && (int) $row->cust_id > 0 ) {
                $user_info = get_userdata( (int) $row->cust_id );
                if ( $user_info ) {
                    $customer_name = trim( $user_info->first_name . ' ' . $user_info->last_name );
                }
            }
            if ( empty( $customer_name ) ) {
                $customer_name = $row->email ? $row->email : __( 'عميل زائر', 'souq-pulse' );
            }

            $results['top_customers'][] = array(
                'customer_id' => (int) $row->cust_id,
                'name'        => $customer_name,
                'orders'      => (int) $row->order_count,
                'total_spend' => (float) $row->total_spend,
            );
        }

        // حساب معدل التحويل
        $results['current']['conversion_rate'] = $results['current']['sessions'] > 0 ? ( ( $results['current']['orders'] / $results['current']['sessions'] ) * 100 ) : 0;
        if ( $compare ) {
            $results['previous']['conversion_rate'] = $results['previous']['sessions'] > 0 ? ( ( $results['previous']['orders'] / $results['previous']['sessions'] ) * 100 ) : 0;
        }

        // 6.5. حساب متوسط القيمة العمرية للعميل (CLV) والعملاء المكررين
        if ( $is_hpos ) {
            $clv_cohort_query = "SELECT 
                AVG(net_spend) as avg_clv,
                COUNT(CASE WHEN valid_orders > 1 THEN 1 END) as repeat_count,
                COUNT(CASE WHEN valid_orders = 1 THEN 1 END) as onetime_count
            FROM (
                SELECT 
                    CASE WHEN o.customer_id > 0 THEN CAST(o.customer_id AS CHAR) ELSE o.billing_email END as cust_id,
                    COUNT(DISTINCT o.id) as valid_orders,
                    SUM(o.total_amount - COALESCE(r.refund_sum, 0)) as net_spend
                FROM {$wpdb->prefix}wc_orders o
                LEFT JOIN (
                    SELECT parent_order_id, SUM(ABS(total_amount)) as refund_sum
                    FROM {$wpdb->prefix}wc_orders
                    WHERE type = 'shop_order_refund'
                    GROUP BY parent_order_id
                ) r ON o.id = r.parent_order_id
                WHERE o.type = 'shop_order'
                  AND o.status IN ({$statuses_in})
                GROUP BY cust_id
            ) as customer_summaries";
        } else {
            $clv_cohort_query = "SELECT 
                AVG(net_spend) as avg_clv,
                COUNT(CASE WHEN valid_orders > 1 THEN 1 END) as repeat_count,
                COUNT(CASE WHEN valid_orders = 1 THEN 1 END) as onetime_count
            FROM (
                SELECT 
                    CASE 
                        WHEN pm_cust.meta_value IS NOT NULL AND CAST(pm_cust.meta_value AS UNSIGNED) > 0 
                        THEN pm_cust.meta_value 
                        ELSE pm_email.meta_value 
                    END as cust_id,
                    COUNT(DISTINCT o.ID) as valid_orders,
                    SUM(CAST(pm_tot.meta_value AS DECIMAL(26,8)) - COALESCE(r.refund_sum, 0)) as net_spend
                FROM {$wpdb->posts} o
                LEFT JOIN {$wpdb->postmeta} pm_cust ON o.ID = pm_cust.post_id AND pm_cust.meta_key = '_customer_user'
                LEFT JOIN {$wpdb->postmeta} pm_email ON o.ID = pm_email.post_id AND pm_email.meta_key = '_billing_email'
                LEFT JOIN {$wpdb->postmeta} pm_tot ON o.ID = pm_tot.post_id AND pm_tot.meta_key = '_order_total'
                LEFT JOIN (
                    SELECT ref.post_parent, SUM(CAST(ABS(CAST(pm_ref.meta_value AS DECIMAL(26,8))) AS DECIMAL(26,8))) as refund_sum
                    FROM {$wpdb->posts} ref
                    LEFT JOIN {$wpdb->postmeta} pm_ref ON ref.ID = pm_ref.post_id AND pm_ref.meta_key = '_order_total'
                    WHERE ref.post_type = 'shop_order_refund'
                    GROUP BY ref.post_parent
                ) r ON o.ID = r.post_parent
                WHERE o.post_type = 'shop_order'
                  AND o.post_status IN ({$statuses_in})
                GROUP BY cust_id
            ) as customer_summaries";
        }
        $cohorts = $wpdb->get_row( $clv_cohort_query );

        $results['customer_metrics'] = array(
            'avg_clv'           => $cohorts ? (float) $cohorts->avg_clv : 0,
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
            $total_sessions = 1;
        }

        $prev_count = 0;
        $index = 0;

        foreach ( $steps as $type => $label ) {
            $count = $funnel_counts[ $type ];
            
            $pct_of_total = ( $count / $total_sessions ) * 100;

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

        // 9. استعلام المؤشرات الجغرافية للمحافظات المصرية مع فلتر صريح country = 'EG'
        if ( $is_hpos ) {
            $geo_query = $wpdb->prepare(
                "SELECT a.state, COUNT(DISTINCT o.id) as order_count, SUM(o.total_amount - COALESCE(r.refund_sum, 0)) as total_sales
                 FROM {$wpdb->prefix}wc_orders o
                 JOIN {$wpdb->prefix}wc_order_addresses a ON o.id = a.order_id AND a.address_type = 'billing'
                 LEFT JOIN (
                     SELECT parent_order_id, SUM(ABS(total_amount)) as refund_sum
                     FROM {$wpdb->prefix}wc_orders
                     WHERE type = 'shop_order_refund'
                     GROUP BY parent_order_id
                 ) r ON o.id = r.parent_order_id
                 WHERE o.type = 'shop_order'
                   AND o.status IN ({$statuses_in})
                   AND a.country = 'EG'
                   AND o.date_created_gmt >= %s AND o.date_created_gmt <= %s
                 GROUP BY a.state
                 ORDER BY total_sales DESC",
                $start_date,
                $end_date
            );
        } else {
            $geo_query = $wpdb->prepare(
                "SELECT pm_state.meta_value as state, COUNT(DISTINCT o.ID) as order_count, SUM(CAST(pm_tot.meta_value AS DECIMAL(26,8)) - COALESCE(r.refund_sum, 0)) as total_sales
                 FROM {$wpdb->posts} o
                 JOIN {$wpdb->postmeta} pm_country ON o.ID = pm_country.post_id AND pm_country.meta_key = '_billing_country' AND pm_country.meta_value = 'EG'
                 LEFT JOIN {$wpdb->postmeta} pm_state ON o.ID = pm_state.post_id AND pm_state.meta_key = '_billing_state'
                 LEFT JOIN {$wpdb->postmeta} pm_tot ON o.ID = pm_tot.post_id AND pm_tot.meta_key = '_order_total'
                 LEFT JOIN (
                     SELECT ref.post_parent, SUM(CAST(ABS(CAST(pm_ref.meta_value AS DECIMAL(26,8))) AS DECIMAL(26,8))) as refund_sum
                     FROM {$wpdb->posts} ref
                     LEFT JOIN {$wpdb->postmeta} pm_ref ON ref.ID = pm_ref.post_id AND pm_ref.meta_key = '_order_total'
                     WHERE ref.post_type = 'shop_order_refund'
                     GROUP BY ref.post_parent
                 ) r ON o.ID = r.post_parent
                 WHERE o.post_type = 'shop_order'
                   AND o.post_status IN ({$statuses_in})
                   AND o.post_date >= %s AND o.post_date <= %s
                 GROUP BY pm_state.meta_value
                 ORDER BY total_sales DESC",
                $start_date,
                $end_date
            );
        }
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
            $state_code = strtoupper( (string) $row->state );
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
