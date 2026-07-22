<?php
/**
 * متحكم لوحة التحكم الإدارية (Admin Controller)
 *
 * @package SouqPulse
 */

if (!defined('ABSPATH')) {
    exit;
}

class SouqPulse_Admin
{

    /**
     * تهيئة الخطافات (Hooks)
     */
    public function __construct()
    {
        add_action('admin_menu', array($this, 'register_submenu_page'), 99);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * تسجيل صفحة لوحة تحليلات "Souq Pulse" كصفحة فرعية تحت WooCommerce
     */
    public function register_submenu_page()
    {
        add_submenu_page(
            'woocommerce',                                // الصفحة الأب
            __('Souq Pulse — Analytics and Indicators', 'souq-pulse'), // عنوان الصفحة في المتصفح
            __('Souq Pulse', 'souq-pulse'),                    // اسم القائمة
            'manage_woocommerce',                         // الصلاحية المطلوبة
            'souqpulse-dashboard',                        // المعرف الفريد للصفحة
            array($this, 'render_dashboard_page')       // الدالة المسؤولة عن عرض الصفحة
        );
    }

    /**
     * تحميل ملفات التنسيق والأكواد البرمجية للوحة التحكم فقط
     */
    public function enqueue_assets($hook)
    {
        // التأكد من تحميل الملفات في صفحة إحصائيات Souq Pulse فقط
        if ('woocommerce_page_souqpulse-dashboard' !== $hook) {
            return;
        }

        // تضمين مكتبة ApexCharts لعرض الرسومات البيانية
        wp_enqueue_script('apexcharts', 'https://cdn.jsdelivr.net/npm/apexcharts', array(), '3.42.0', true);

        // تضمين خطوط Google Fonts (Outfit / Tajawal)
        wp_enqueue_style('souqpulse-fonts', 'https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap', array(), SOUQPULSE_VERSION);

        // ملف التنسيقات الخاص بواجهة RTL
        wp_enqueue_style('souqpulse-admin-css', SOUQPULSE_URL . 'assets/css/admin-rtl.css', array(), SOUQPULSE_VERSION);

        // ملف الجافاسكريبت للوحة التحكم
        wp_enqueue_script('souqpulse-admin-js', SOUQPULSE_URL . 'assets/js/admin.js', array('jquery', 'apexcharts'), SOUQPULSE_VERSION, true);

        $currency_symbol = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : 'ج.م';

        // إرسال بيانات AJAX والـ nonces للجافاسكريبت
        wp_localize_script('souqpulse-admin-js', 'souqpulseAdminData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('souqpulse_admin_nonce'),
            'currency_symbol' => $currency_symbol,
            'i18n' => array(
                'total_sales' => __('Total Sales', 'souq-pulse'),
                'order_count' => __('Number of Orders', 'souq-pulse'),
                'aov' => __('Average Order Value', 'souq-pulse'),
                'sessions' => __('Visitors & Sessions', 'souq-pulse'),
                'bounce_rate' => __('Bounce Rate', 'souq-pulse'),
                'conversion_rate' => __('Conversion Rate', 'souq-pulse'),
                'no_change' => __('No Change', 'souq-pulse'),
                'sales' => __('Sales', 'souq-pulse'),
                'orders' => __('Orders', 'souq-pulse'),
                'active_visitor_5m' => __('Active visitor in last 5 mins', 'souq-pulse'),
                'no_data_for_period' => __('No data for this period.', 'souq-pulse'),
                'no_products_sold' => __('No products sold', 'souq-pulse'),
                'no_products_sold_desc' => __('No products were sold during the selected period.', 'souq-pulse'),
                'order_word' => __('Order', 'souq-pulse'),
                'piece_word' => __('Piece', 'souq-pulse'),
                'no_pairs_sold' => __('No pairs sold', 'souq-pulse'),
                'no_pairs_sold_desc' => __('No two products were bought together in one order.', 'souq-pulse'),
                'time_word' => __('Time(s)', 'souq-pulse'),
                'no_geo_data' => __('No Geographic Data', 'souq-pulse'),
                'no_geo_data_desc' => __('No orders were recorded in known countries.', 'souq-pulse'),
                'no_egypt_sales' => __('No Sales in Egypt', 'souq-pulse'),
                'no_egypt_sales_desc' => __('No orders were recorded inside Egypt.', 'souq-pulse'),
                'no_cohort_data' => __('No Cohort Data', 'souq-pulse'),
                'no_cohort_data_desc' => __('Requires orders in more than one month to calculate retention.', 'souq-pulse'),
                'no_payment_methods' => __('No Payment Methods', 'souq-pulse'),
                'no_payment_methods_desc' => __('No orders were recorded with payment methods in this period.', 'souq-pulse'),
                'no_sales_recorded' => __('No Sales', 'souq-pulse'),
                'no_sales_recorded_desc' => __('No sales recorded in this period.', 'souq-pulse'),
                'no_data_recorded' => __('No Data Recorded', 'souq-pulse'),
                'try_another_date_range' => __('Try selecting another date range to see statistics.', 'souq-pulse'),
                'no_customer_data' => __('No Customer Data', 'souq-pulse'),
                'try_wider_date_range' => __('Try selecting a wider date range to see customer data.', 'souq-pulse'),
                'january' => __('January', 'souq-pulse'),
                'february' => __('February', 'souq-pulse'),
                'march' => __('March', 'souq-pulse'),
                'april' => __('April', 'souq-pulse'),
                'may' => __('May', 'souq-pulse'),
                'june' => __('June', 'souq-pulse'),
                'july' => __('July', 'souq-pulse'),
                'august' => __('August', 'souq-pulse'),
                'september' => __('September', 'souq-pulse'),
                'october' => __('October', 'souq-pulse'),
                'november' => __('November', 'souq-pulse'),
                'december' => __('December', 'souq-pulse'),
                'sunday' => __('Sunday', 'souq-pulse'),
                'monday' => __('Monday', 'souq-pulse'),
                'tuesday' => __('Tuesday', 'souq-pulse'),
                'wednesday' => __('Wednesday', 'souq-pulse'),
                'thursday' => __('Thursday', 'souq-pulse'),
                'friday' => __('Friday', 'souq-pulse'),
                'saturday' => __('Saturday', 'souq-pulse'),
                'rfm_champions' => __('Champions', 'souq-pulse'),
                'rfm_loyal' => __('Loyal Customers', 'souq-pulse'),
                'rfm_potential' => __('Potential Loyalist', 'souq-pulse'),
                'rfm_new' => __('New Customers', 'souq-pulse'),
                'rfm_need_attention' => __('Need Attention', 'souq-pulse'),
                'rfm_at_risk' => __('At Risk', 'souq-pulse'),
                'rfm_cant_lose' => __('Can\'t Lose Them', 'souq-pulse'),
                'rfm_hibernating' => __('Hibernating', 'souq-pulse'),
                'rfm_lost' => __('Lost', 'souq-pulse'),
                'returning_customers' => __('Returning Customers', 'souq-pulse'),
                'visitor' => __('Visitor', 'souq-pulse'),
                'session' => __('Session', 'souq-pulse'),
                'product' => __('Product', 'souq-pulse'),
                'order' => __('Order', 'souq-pulse'),
                'revenue' => __('Revenue', 'souq-pulse'),
                'hour_label' => __('Hour', 'souq-pulse'),
                'day_label' => __('Day', 'souq-pulse'),
                'date_label' => __('Date', 'souq-pulse'),
                'orders_plural' => __('Orders', 'souq-pulse'),
                'visits_plural' => __('Visits', 'souq-pulse'),
                'all_time' => __('All Time', 'souq-pulse'),
                'active_users' => __('Active Users', 'souq-pulse'),
                'payment_info' => __('Payment Information', 'souq-pulse'),
                'shipping_info' => __('Shipping Information', 'souq-pulse'),
                'cod' => __('Cash on Delivery', 'souq-pulse'),
                'other' => __('Other', 'souq-pulse'),
                'funnel_visit' => __('Site Visit', 'souq-pulse'),
                'funnel_view_product' => __('View Product', 'souq-pulse'),
                'funnel_add_to_cart' => __('Add to Cart', 'souq-pulse'),
                'funnel_checkout' => __('Checkout', 'souq-pulse'),
                'funnel_purchase' => __('Completed Purchase', 'souq-pulse'),
                'funnel_begin_checkout' => __('Begin Checkout', 'souq-pulse'),
                'funnel_completed_purchase' => __('Purchase Process', 'souq-pulse'),
                'funnel_visits' => __('Funnel Visits', 'souq-pulse'),
                'cairo' => __('Cairo', 'souq-pulse'),
                'alexandria' => __('Alexandria', 'souq-pulse'),
                'giza' => __('Giza', 'souq-pulse'),
                'qalyubia' => __('Qalyubia', 'souq-pulse'),
                'dakahlia' => __('Dakahlia', 'souq-pulse'),
                'beheira' => __('Beheira', 'souq-pulse'),
                'faiyum' => __('Faiyum', 'souq-pulse'),
                'gharbia' => __('Gharbia', 'souq-pulse'),
                'monufia' => __('Monufia', 'souq-pulse'),
                'ismailia' => __('Ismailia', 'souq-pulse'),
                'suez' => __('Suez', 'souq-pulse'),
                'port_said' => __('Port Said', 'souq-pulse'),
                'aswan' => __('Aswan', 'souq-pulse'),
                'asyut' => __('Asyut', 'souq-pulse'),
                'beni_suef' => __('Beni Suef', 'souq-pulse'),
                'damietta' => __('Damietta', 'souq-pulse'),
                'kafr_el_sheikh' => __('Kafr el-Sheikh', 'souq-pulse'),
                'luxor' => __('Luxor', 'souq-pulse'),
                'minya' => __('Minya', 'souq-pulse'),
                'matrouh' => __('Matrouh', 'souq-pulse'),
                'north_sinai' => __('North Sinai', 'souq-pulse'),
                'south_sinai' => __('South Sinai', 'souq-pulse'),
                'sohag' => __('Sohag', 'souq-pulse'),
                'sharqia' => __('Sharqia', 'souq-pulse'),
                'new_valley' => __('New Valley', 'souq-pulse'),
                'red_sea' => __('Red Sea', 'souq-pulse'),
                'qena' => __('Qena', 'souq-pulse'),
                'other_governorates' => __('Other Governorates', 'souq-pulse'),
                'error_connecting_server' => __('Error connecting to server:', 'souq-pulse'),
                'failed_fetching_data' => __('Failed fetching Souq Pulse data:', 'souq-pulse'),
                'vs_previous_period' => __('vs Previous Period', 'souq-pulse'),
                'customer_word' => __('Customer', 'souq-pulse'),
                'from_revenue' => __('% of Revenue', 'souq-pulse'),
                'revenue_from_loyal_customers' => __('of Revenue from Returning Loyal Customers!', 'souq-pulse'),
                'dropoff' => __('Drop-off', 'souq-pulse'),
                'avg_duration_label' => __('Average Visit Duration: ', 'souq-pulse'),
                'visit_word' => __('Visit', 'souq-pulse'),
                'second_word' => __('Second', 'souq-pulse'),
                'sec_abbr' => __('s', 'souq-pulse'),
                'min_abbr' => __('m', 'souq-pulse'),
                'revenue_label_prefix' => __('Revenue', 'souq-pulse'),
                'sales_revenue_label' => __('Sales Revenue', 'souq-pulse'),
            ),
        ));
    }

    /**
     * عرض محتويات لوحة تحليلات "Souq Pulse"
     */
    public function render_dashboard_page()
    {
        ?>
        <div class="wrap souqpulse-dashboard-wrapper" dir="rtl">
            <!-- الهيدر العلوي للوحة التحكم -->
            <div class="souqpulse-header-section" style="margin-bottom: 24px;">
                <div class="souqpulse-header"
                    style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
                    <div class="souqpulse-header-title">
                        <h1 style="display: flex; align-items: center; gap: 10px; margin: 0; font-size: 22px; font-weight: 700; color: #1e293b;">
                            ⚡ <?php esc_html_e('Souq Pulse', 'souq-pulse'); ?>
                            <span class="badge"
                                style="background: var(--souqpulse-primary); color: #fff; font-size: 11px; padding: 3px 8px; border-radius: 12px; font-weight: normal;"><?php esc_html_e('Beta', 'souq-pulse'); ?></span>
                        </h1>
                        <p class="description" style="margin: 4px 0 0; color: #64748b; font-size: 13px;">
                            <?php esc_html_e('Comprehensive real-time overview of your store\'s sales performance and visitor behavior in one place.', 'souq-pulse'); ?>
                        </p>
                    </div>
                    <div class="souqpulse-header-actions">
                        <!-- مفتاح اختيار النطاق الزمني -->
                        <div class="date-picker-container">
                            <select id="souqpulse-date-range" class="souqpulse-select">
                                <option value="7days"><?php esc_html_e('Last 7 Days', 'souq-pulse'); ?></option>
                                <option value="30days" selected><?php esc_html_e('Last 30 Days', 'souq-pulse'); ?></option>
                                <option value="90days"><?php esc_html_e('Last 90 Days', 'souq-pulse'); ?></option>
                                <option value="6months"><?php esc_html_e('Last 6 Months', 'souq-pulse'); ?></option>
                                <option value="12months"><?php esc_html_e('Last 12 Months', 'souq-pulse'); ?></option>
                                <option value="alltime"><?php esc_html_e('All Time', 'souq-pulse'); ?></option>
                                <option value="custom"><?php esc_html_e('Custom Period', 'souq-pulse'); ?></option>
                            </select>

                            <!-- حقول مخصصة للنطاق الزمني تظهر عند الحاجة -->
                            <div id="souqpulse-custom-dates" class="custom-dates-inputs"
                                style="display:none; align-items:center; gap:8px;">
                                <input type="date" id="souqpulse-start-date" class="souqpulse-input-date"
                                    placeholder="<?php esc_attr_e('From Date', 'souq-pulse'); ?>">
                                <span><?php esc_html_e('To', 'souq-pulse'); ?></span>
                                <input type="date" id="souqpulse-end-date" class="souqpulse-input-date"
                                    placeholder="<?php esc_attr_e('To Date', 'souq-pulse'); ?>">
                            </div>

                            <div class="comparison-toggle">
                                <label class="switch">
                                    <input type="checkbox" id="souqpulse-compare-toggle" checked>
                                    <span class="slider round"></span>
                                </label>
                                <span
                                    class="compare-label"><?php esc_html_e('Compared to previous period', 'souq-pulse'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- تبويب التحليلات -->
            <div id="souqpulse-analytics-tab" class="souqpulse-tab-content active">
                <!-- شبكة كروت الأداء الرئيسية (KPIs) -->
                <div class="souqpulse-kpi-grid">
                    <!-- كارت المبيعات الإجمالية -->
                    <div class="souqpulse-card kpi-card" id="kpi-sales">
                        <div class="card-header">
                            <span class="card-title"
                                title="<?php esc_attr_e('Net Revenue (Including taxes and shipping, excluding refunds)', 'souq-pulse'); ?>"><?php esc_html_e('Total Sales', 'souq-pulse'); ?></span>
                            <span class="card-icon"><span class="dashicons dashicons-chart-area"></span></span>
                        </div>
                        <div class="card-body">
                            <h2 class="kpi-value"><?php echo esc_html(sprintf(__('%s EGP', 'souq-pulse'), '0.00')); ?>
                            </h2>
                            <span class="kpi-change positive">↑ 0% <span
                                    class="change-label"><?php esc_html_e('vs Previous Period', 'souq-pulse'); ?></span></span>
                            <div class="kpi-sparkline" id="sparkline-sales"></div>
                        </div>
                    </div>

                    <!-- كارت الطلبات -->
                    <div class="souqpulse-card kpi-card" id="kpi-orders">
                        <div class="card-header">
                            <span class="card-title"><?php esc_html_e('Number of Orders', 'souq-pulse'); ?></span>
                            <span class="card-icon"><span class="dashicons dashicons-cart"></span></span>
                        </div>
                        <div class="card-body">
                            <h2 class="kpi-value">0</h2>
                            <span class="kpi-change positive">↑ 0% <span
                                    class="change-label"><?php esc_html_e('vs Previous Period', 'souq-pulse'); ?></span></span>
                            <div class="kpi-sparkline" id="sparkline-orders"></div>
                        </div>
                    </div>

                    <!-- كارت متوسط قيمة الطلب (AOV) -->
                    <div class="souqpulse-card kpi-card" id="kpi-aov">
                        <div class="card-header">
                            <span class="card-title"><?php esc_html_e('Average Order Value (AOV)', 'souq-pulse'); ?></span>
                            <span class="card-icon"><span class="dashicons dashicons-calculator"></span></span>
                        </div>
                        <div class="card-body">
                            <h2 class="kpi-value"><?php echo esc_html(sprintf(__('%s EGP', 'souq-pulse'), '0.00')); ?>
                            </h2>
                            <span class="kpi-change negative">↓ 0% <span
                                    class="change-label"><?php esc_html_e('vs Previous Period', 'souq-pulse'); ?></span></span>
                            <div class="kpi-sparkline" id="sparkline-aov"></div>
                        </div>
                    </div>

                    <!-- كارت الجلسات (Sessions) -->
                    <div class="souqpulse-card kpi-card" id="kpi-sessions">
                        <div class="card-header">
                            <span class="card-title"><?php esc_html_e('Number of Visits (Sessions)', 'souq-pulse'); ?></span>
                            <span class="card-icon"><span class="dashicons dashicons-admin-users"></span></span>
                        </div>
                        <div class="card-body">
                            <h2 class="kpi-value">0</h2>
                            <span class="kpi-change positive">↑ 0% <span
                                    class="change-label"><?php esc_html_e('vs Previous Period', 'souq-pulse'); ?></span></span>
                            <span class="kpi-meta-text"
                                id="sessions-duration-meta"><?php esc_html_e('Average Visit Duration: 0 seconds', 'souq-pulse'); ?></span>
                            <div class="kpi-sparkline" id="sparkline-sessions"></div>
                        </div>
                    </div>

                    <!-- كارت معدل الارتداد (Bounce Rate) -->
                    <div class="souqpulse-card kpi-card" id="kpi-bounce-rate">
                        <div class="card-header">
                            <span class="card-title"><?php esc_html_e('Bounce Rate', 'souq-pulse'); ?></span>
                            <span class="card-icon"><span class="dashicons dashicons-controls-repeat"></span></span>
                        </div>
                        <div class="card-body">
                            <h2 class="kpi-value">0.00%</h2>
                            <span class="kpi-change neutral">0% <span
                                    class="change-label"><?php esc_html_e('vs Previous Period', 'souq-pulse'); ?></span></span>
                            <div class="kpi-sparkline" id="sparkline-bounce"></div>
                        </div>
                    </div>

                    <!-- كارت معدل التحويل (Conversion Rate) -->
                    <div class="souqpulse-card kpi-card" id="kpi-conversion">
                        <div class="card-header">
                            <span class="card-title"><?php esc_html_e('Store Conversion Rate', 'souq-pulse'); ?></span>
                            <span class="card-icon"><span class="dashicons dashicons-awards"></span></span>
                        </div>
                        <div class="card-body">
                            <h2 class="kpi-value">0.00%</h2>
                            <span class="kpi-change positive">↑ 0% <span
                                    class="change-label"><?php esc_html_e('vs Previous Period', 'souq-pulse'); ?></span></span>
                            <div class="kpi-sparkline" id="sparkline-conversion"></div>
                        </div>
                    </div>
                </div>

                <!-- قسم الرسومات البيانية الرئيسية (المبيعات والـ Funnel) -->
                <div class="souqpulse-charts-grid">
                    <!-- مخطط المبيعات عبر الوقت -->
                    <div class="souqpulse-card chart-card flex-2">
                        <div class="card-header">
                            <span
                                class="card-title"><?php esc_html_e('Total Sales and Number of Orders Over Time', 'souq-pulse'); ?></span>
                        </div>
                        <div class="card-body">
                            <div id="souqpulse-sales-timeline-chart" class="souqpulse-chart-placeholder"></div>
                        </div>
                    </div>

                    <!-- كارت الزوار في الوقت الفعلي (Real-time) -->
                    <div class="souqpulse-card chart-card flex-1" id="kpi-realtime">
                        <div class="card-header">
                            <span class="card-title"><?php esc_html_e('Active Visitors Now', 'souq-pulse'); ?></span>
                            <span class="realtime-pulse"></span>
                        </div>
                        <div class="card-body realtime-body">
                            <h1 class="realtime-value">0</h1>
                            <p class="realtime-sub"><?php esc_html_e('Active visitor in last 5 mins', 'souq-pulse'); ?></p>
                            <div class="realtime-sparkline">
                                <div id="souqpulse-realtime-chart"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- شبكة مسار العميل وجداول العملاء والمنتجات -->
                <div class="souqpulse-details-grid">
                    <!-- مخطط مسار الشراء (Funnel) -->
                    <div class="souqpulse-card details-card flex-2">
                        <div class="card-header">
                            <span
                                class="card-title"><?php esc_html_e('Purchase Funnel', 'souq-pulse'); ?></span>
                        </div>
                        <div class="card-body">
                            <div id="souqpulse-funnel-chart" class="souqpulse-chart-placeholder"></div>
                        </div>
                    </div>

                    <!-- كارت حالة المخزون -->
                    <div class="souqpulse-card details-card flex-1">
                        <div class="card-header">
                            <span class="card-title"><?php esc_html_e('Inventory Status', 'souq-pulse'); ?></span>
                        </div>
                        <div class="card-body inventory-body">
                            <div class="inventory-status-item">
                                <span class="status-dot green"></span>
                                <span
                                    class="status-label"><?php esc_html_e('Total Units Available:', 'souq-pulse'); ?></span>
                                <strong class="status-value" id="inv-total-units">0</strong>
                            </div>
                            <div class="inventory-status-item">
                                <span class="status-dot yellow"></span>
                                <span class="status-label"><?php esc_html_e('Low Stock Products:', 'souq-pulse'); ?></span>
                                <strong class="status-value" id="inv-low-stock">0</strong>
                            </div>
                            <div class="inventory-status-item">
                                <span class="status-dot red"></span>
                                <span
                                    class="status-label"><?php esc_html_e('Out of Stock Products:', 'souq-pulse'); ?></span>
                                <strong class="status-value" id="inv-out-of-stock">0</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- شبكة تفصيلية إضافية (أعلى المنتجات وأعلى العملاء والجغرافيا) -->
                <div class="souqpulse-tables-grid">
                    <!-- أعلى المنتجات مبيعًا -->
                    <div class="souqpulse-card table-card">
                        <div class="card-header">
                            <span class="card-title"><?php esc_html_e('Top 5 Selling Products', 'souq-pulse'); ?></span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="souqpulse-table" id="table-top-products">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Product', 'souq-pulse'); ?></th>
                                            <th style="width: 150px;"><?php esc_html_e('Sales', 'souq-pulse'); ?></th>
                                            <th style="width: 120px;"><?php esc_html_e('Progress', 'souq-pulse'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="3" class="text-center text-muted">
                                                <?php esc_html_e('Loading data...', 'souq-pulse'); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- أعلى العملاء ونسبة الإيراد -->
                    <div class="souqpulse-card table-card">
                        <div class="card-header">
                            <span class="card-title"><?php esc_html_e('Top 5 Customers & Revenue Source', 'souq-pulse'); ?></span>
                        </div>
                        <div class="card-body">
                            <!-- ملخص سلوك العملاء والمجموعات -->
                            <div class="customer-stats-summary">
                                <div>
                                    <span><?php esc_html_e('Average Customer Lifetime Value (CLV):', 'souq-pulse'); ?></span>
                                    <strong id="cust-avg-clv">ج.م 0.00</strong>
                                </div>
                                <div>
                                    <span><?php esc_html_e('Repeat Customers:', 'souq-pulse'); ?></span>
                                    <strong id="cust-repeat-count">0</strong>
                                </div>
                                <div>
                                    <span><?php esc_html_e('One-time Customers:', 'souq-pulse'); ?></span>
                                    <strong id="cust-onetime-count">0</strong>
                                </div>
                            </div>

                            <!-- نسبة إيراد العملاء الراجعين -->
                            <div class="returning-rev-widget"
                                style="margin: 15px 0; padding: 12px; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1; display: flex; align-items: center; justify-content: space-between; gap: 12px;">
                                <div style="flex: 1;">
                                    <h4 style="margin: 0 0 4px; font-size: 13px; color: #334155; font-weight: 700;">
                                        <?php esc_html_e('Revenue Contribution by Customer Segment', 'souq-pulse'); ?></h4>
                                    <p id="rev-share-slogan" style="margin: 0; font-size: 11px; color: #64748b;">
                                        <?php esc_html_e('Analyzing loyal customer contribution...', 'souq-pulse'); ?></p>
                                </div>
                                <div id="souqpulse-rev-share-chart" style="min-width: 110px; height: 90px;"></div>
                            </div>

                            <div class="table-responsive">
                                <table class="souqpulse-table" id="table-top-customers">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Customer', 'souq-pulse'); ?></th>
                                            <th><?php esc_html_e('Orders', 'souq-pulse'); ?></th>
                                            <th><?php esc_html_e('Total Purchase', 'souq-pulse'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="3" class="text-center text-muted">
                                                <?php esc_html_e('Loading data...', 'souq-pulse'); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- التوزيع الجغرافي للمبيعات والطلبات -->
                    <div class="souqpulse-card table-card">
                        <div class="card-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                            <span class="card-title"><?php esc_html_e('Geographic Distribution of Sales and Orders', 'souq-pulse'); ?></span>
                            <div class="souqpulse-geo-toggles">
                                <button class="souqpulse-geo-btn active" data-geo-tab="countries">🌐 <?php esc_html_e('All Countries', 'souq-pulse'); ?></button>
                                <button class="souqpulse-geo-btn" data-geo-tab="egypt">🇪🇬 <?php esc_html_e('Governorates of Egypt', 'souq-pulse'); ?></button>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- عرض جدول الدول العالمية (الافتراضي) -->
                            <div id="souqpulse-geo-countries-container">
                                <div class="table-responsive">
                                    <table class="souqpulse-table" id="table-geo-countries">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e('Country', 'souq-pulse'); ?></th>
                                                <th style="width: 70px; text-align: center;"><?php esc_html_e('Orders', 'souq-pulse'); ?></th>
                                                <th style="min-width: 140px;"><?php esc_html_e('Revenue & Contribution Percentage', 'souq-pulse'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td colspan="3" class="text-center text-muted"><?php esc_html_e('Loading geographic distribution...', 'souq-pulse'); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- عرض خريطة ومحافظات مصر (عند اختيار تبويب مصر) -->
                            <div id="souqpulse-geo-egypt-container" style="display: none;">
                                <div id="souqpulse-egypt-map-wrapper" style="margin-bottom: 15px; position: relative;">
                                    <div id="souqpulse-egypt-map-container"
                                        style="width: 100%; height: 210px; display: flex; align-items: center; justify-content: center; background: #fafafa; border-radius: 8px; border: 1px solid #f1f5f9; overflow: hidden;">
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="souqpulse-table" id="table-geo-egypt">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e('Governorate', 'souq-pulse'); ?></th>
                                                <th style="width: 70px; text-align: center;"><?php esc_html_e('Orders', 'souq-pulse'); ?></th>
                                                <th><?php esc_html_e('Sales', 'souq-pulse'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td colspan="3" class="text-center text-muted"><?php esc_html_e('Loading...', 'souq-pulse'); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- شبكة تحليلات وسائل الدفع وخريطة الذروة -->
                <div class="souqpulse-details-grid" style="margin-top: 24px;">
                    <!-- تحليل وسائل الدفع -->
                    <div class="souqpulse-card details-card flex-1">
                        <div class="card-header">
                            <span
                                class="card-title"><?php esc_html_e('Payment Methods Analysis & COD Risks', 'souq-pulse'); ?></span>
                        </div>
                        <div class="card-body">
                            <div id="souqpulse-payment-chart" style="min-height: 200px;"></div>
                            <div class="table-responsive" style="margin-top: 15px;">
                                <table class="souqpulse-table" id="table-payment-methods">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Payment Method', 'souq-pulse'); ?></th>
                                            <th><?php esc_html_e('Orders', 'souq-pulse'); ?></th>
                                            <th><?php esc_html_e('Revenue', 'souq-pulse'); ?></th>
                                            <th><?php esc_html_e('Return Rate', 'souq-pulse'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">
                                                <?php esc_html_e('Loading data...', 'souq-pulse'); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- الخريطة الحرارية لأوقات الطلبات -->
                    <div class="souqpulse-card details-card flex-2">
                        <div class="card-header">
                            <span
                                class="card-title"><?php esc_html_e('Peak Order Times (Days & Hours - Egypt Time)', 'souq-pulse'); ?></span>
                        </div>
                        <div class="card-body">
                            <div id="souqpulse-heatmap-chart" style="min-height: 280px;"></div>
                            <p style="margin: 8px 0 0; font-size: 11px; color: #64748b; text-align: center;">
                                💡
                                <?php esc_html_e('Darker squares represent intensive customer buying times — recommended for timing ad campaigns.', 'souq-pulse'); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- شبكة تحليلات RFM والمنتجات المترابطة -->
                <div class="souqpulse-details-grid" style="margin-top: 24px;">
                    <!-- تقسيم العملاء RFM -->
                    <div class="souqpulse-card details-card flex-2">
                        <div class="card-header">
                            <span
                                class="card-title"><?php esc_html_e('RFM — Interactive Customer Segmentation', 'souq-pulse'); ?></span>
                        </div>
                        <div class="card-body">
                            <div class="rfm-segment-grid" id="rfm-segment-container"
                                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; margin-bottom: 12px;">
                                <!-- يتم ملؤها ديناميكياً ببطاقات ملونة لكل شريحة -->
                            </div>
                        </div>
                    </div>

                    <!-- المنتجات الأكثر شراءً معاً (Product Affinity) -->
                    <div class="souqpulse-card details-card flex-1">
                        <div class="card-header">
                            <span
                                class="card-title"><?php esc_html_e('Frequently Bought Together (Product Affinity)', 'souq-pulse'); ?></span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="souqpulse-table" id="table-product-affinity">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Product Bundle', 'souq-pulse'); ?></th>
                                            <th style="width: 90px; text-align: center;">
                                                <?php esc_html_e('Frequency', 'souq-pulse'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="2" class="text-center text-muted">
                                                <?php esc_html_e('Analyzing product affinity...', 'souq-pulse'); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- كارت مصفوفة الاحتفاظ بالعملاء (Monthly Cohort Retention Heatmap) -->
                <div class="souqpulse-details-grid" style="margin-top: 24px;">
                    <div class="souqpulse-card details-card flex-1">
                        <div class="card-header">
                            <span
                                class="card-title"><?php esc_html_e('Monthly Customer Retention Matrix (Cohort Retention Heatmap)', 'souq-pulse'); ?></span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="souqpulse-table" id="table-cohort-retention"
                                    style="font-size: 12px; text-align: center;">
                                    <thead>
                                        <tr>
                                            <th style="text-align: right; min-width: 120px;">
                                                <?php esc_html_e('Monthly Cohort', 'souq-pulse'); ?></th>
                                            <th style="width: 80px;"><?php esc_html_e('Count', 'souq-pulse'); ?></th>
                                            <th><?php esc_html_e('Month 0', 'souq-pulse'); ?></th>
                                            <th><?php esc_html_e('Month +1', 'souq-pulse'); ?></th>
                                            <th><?php esc_html_e('Month +2', 'souq-pulse'); ?></th>
                                            <th><?php esc_html_e('Month +3', 'souq-pulse'); ?></th>
                                            <th><?php esc_html_e('Month +4', 'souq-pulse'); ?></th>
                                            <th><?php esc_html_e('Month +5', 'souq-pulse'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">
                                                <?php esc_html_e('Analyzing cohort retention matrix...', 'souq-pulse'); ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
