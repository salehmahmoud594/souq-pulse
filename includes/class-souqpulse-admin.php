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
            'نبض السوق — التحليلات والمؤشرات', // عنوان الصفحة في المتصفح
            'نبض السوق',                    // اسم القائمة
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
                'total_sales' => 'المبيعات الإجمالية',
                'order_count' => 'عدد الطلبات',
                'aov' => 'متوسط قيمة الطلب',
                'sessions' => 'الزوار والجلسات',
                'bounce_rate' => 'معدل الارتداد',
                'conversion_rate' => 'معدل التحويل',
                'no_change' => 'بدون تغيير',
                'sales' => 'المبيعات',
                'orders' => 'الطلبات',
                'active_visitor_5m' => 'زائر نشط خلال آخر 5 دقائق',
                'no_data_for_period' => 'لا توجد بيانات لهذه الفترة.',
                'no_products_sold' => 'لا توجد منتجات مباعة',
                'no_products_sold_desc' => 'لم يتم بيع أي منتجات خلال الفترة المحددة.',
                'order_word' => 'طلب',
                'piece_word' => 'قطعة',
                'no_pairs_sold' => 'لا توجد منتجات مشتركة',
                'no_pairs_sold_desc' => 'لم يتم شراء منتجين معاً في أي طلب.',
                'time_word' => 'مرة',
                'no_geo_data' => 'لا توجد بيانات جغرافية',
                'no_geo_data_desc' => 'لم يتم تسجيل أي طلبات في دول معروفة.',
                'no_egypt_sales' => 'لا توجد مبيعات في مصر',
                'no_egypt_sales_desc' => 'لم يتم تسجيل طلبات من داخل مصر.',
                'no_cohort_data' => 'لا توجد بيانات احتفاظ',
                'no_cohort_data_desc' => 'يتطلب طلبات في أكثر من شهر لحساب الاحتفاظ.',
                'no_payment_methods' => 'لا توجد وسائل دفع',
                'no_payment_methods_desc' => 'لم يتم تسجيل طلبات بوسائل دفع في هذه الفترة.',
                'no_sales_recorded' => 'لا توجد مبيعات',
                'no_sales_recorded_desc' => 'لا توجد مبيعات مسجلة في هذه الفترة.',
                'no_data_recorded' => 'لا توجد بيانات مسجلة',
                'try_another_date_range' => 'جرّب اختيار نطاق زمني آخر للاطلاع على الإحصائيات.',
                'no_customer_data' => 'لا توجد بيانات للعملاء',
                'try_wider_date_range' => 'جرّب اختيار نطاق زمني أوسع لعرض بيانات العملاء.',
                'january' => 'يناير',
                'february' => 'فبراير',
                'march' => 'مارس',
                'april' => 'أبريل',
                'may' => 'مايو',
                'june' => 'يونيو',
                'july' => 'يوليو',
                'august' => 'أغسطس',
                'september' => 'سبتمبر',
                'october' => 'أكتوبر',
                'november' => 'نوفمبر',
                'december' => 'ديسمبر',
                'sunday' => 'الأحد',
                'monday' => 'الإثنين',
                'tuesday' => 'الثلاثاء',
                'wednesday' => 'الأربعاء',
                'thursday' => 'الخميس',
                'friday' => 'الجمعة',
                'saturday' => 'السبت',
                'rfm_champions' => 'أبطال الشراء',
                'rfm_loyal' => 'عملاء مخلصون',
                'rfm_potential' => 'فرصة سانحة',
                'rfm_new' => 'عملاء جدد',
                'rfm_need_attention' => 'يحتاجون اهتمام',
                'rfm_at_risk' => 'معرضون للخطر',
                'rfm_cant_lose' => 'لا يمكن خسارتهم',
                'rfm_hibernating' => 'عملاء نائمون',
                'rfm_lost' => 'عملاء ضائعون',
                'returning_customers' => 'عملاء راجعون',
                'visitor' => 'زائر',
                'session' => 'جلسة',
                'product' => 'منتج',
                'order' => 'طلب',
                'revenue' => 'الإيرادات',
                'hour_label' => 'الساعة',
                'day_label' => 'اليوم',
                'date_label' => 'التاريخ',
                'orders_plural' => 'الطلبات',
                'visits_plural' => 'الزيارات',
                'all_time' => 'كل الوقت',
                'active_users' => 'النشطون',
                'payment_info' => 'معلومات الدفع',
                'shipping_info' => 'معلومات الشحن',
                'cod' => 'عند الاستلام',
                'other' => 'أخرى',
                'funnel_visit' => 'زيارة الموقع',
                'funnel_view_product' => 'مشاهدة منتج',
                'funnel_add_to_cart' => 'إضافة للسلة',
                'funnel_checkout' => 'بداية الدفع',
                'funnel_purchase' => 'عملية الشراء',
                'funnel_begin_checkout' => 'بدء الدفع',
                'funnel_completed_purchase' => 'عملية الشراء',
                'funnel_visits' => 'زيارات مسار التحويل',
                'cairo' => 'القاهرة',
                'alexandria' => 'الإسكندرية',
                'giza' => 'الجيزة',
                'qalyubia' => 'القليوبية',
                'dakahlia' => 'الدقهلية',
                'beheira' => 'البحيرة',
                'faiyum' => 'الفيوم',
                'gharbia' => 'الغربية',
                'monufia' => 'المنوفية',
                'ismailia' => 'الإسماعلية',
                'suez' => 'السويس',
                'port_said' => 'بورسعيد',
                'aswan' => 'أسوان',
                'asyut' => 'أسيوط',
                'beni_suef' => 'بني سويف',
                'damietta' => 'دمياط',
                'kafr_el_sheikh' => 'كفر الشيخ',
                'luxor' => 'الأقصر',
                'minya' => 'المنيا',
                'matrouh' => 'مطروح',
                'north_sinai' => 'شمال سيناء',
                'south_sinai' => 'جنوب سيناء',
                'sohag' => 'سوهاج',
                'sharqia' => 'الشرقية',
                'new_valley' => 'الوادي الجديد',
                'red_sea' => 'البحر الأحمر',
                'qena' => 'قنا',
                'other_governorates' => 'محافظات أخرى',
                'error_connecting_server' => 'خطأ في الاتصال بالخادم:',
                'failed_fetching_data' => 'فشل جلب بيانات نبض السوق:',
                'vs_previous_period' => 'vs الفترة السابقة',
                'customer_word' => 'عميل',
                'from_revenue' => '% من الإيرادات',
                'revenue_from_loyal_customers' => 'من الإيرادات من عملاء مخلصين!',
                'dropoff' => 'تسرب',
                'avg_duration_label' => 'متوسط مدة الزيارة: ',
                'visit_word' => 'زيارة الموقع',
                'second_word' => 'ثانية',
                'sec_abbr' => 'ث',
                'min_abbr' => 'د',
                'revenue_label_prefix' => 'الإيرادات',
                'sales_revenue_label' => 'إيرادات المبيعات',
            ),
        ));
    }

    /**
     * عرض محتويات لوحة تحليلات "Souq Pulse"
     */
    public function render_dashboard_page()
    {
        $currency_symbol = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : 'ج.م';
        ?>
        <div class="wrap souqpulse-dashboard-wrapper" dir="rtl">
            <!-- الهيدر العلوي للوحة التحكم -->
            <div class="souqpulse-header-section" style="margin-bottom: 24px;">
                <div class="souqpulse-header"
                    style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
                    <div class="souqpulse-header-title">
                        <h1 style="display: flex; align-items: center; gap: 10px; margin: 0; font-size: 22px; font-weight: 700; color: #1e293b;">
                            ⚡ <?php echo 'نبض السوق'; ?>
                            <span class="badge"
                                style="background: var(--souqpulse-primary); color: #fff; font-size: 11px; padding: 3px 8px; border-radius: 12px; font-weight: normal;"><?php echo 'تجريبي'; ?></span>
                        </h1>
                        <p class="description" style="margin: 4px 0 0; color: #64748b; font-size: 13px;">
                            <?php echo 'نظرة شاملة ولحظية على أداء مبيعات متجرك وسلوك الزوار في مكان واحد.'; ?>
                        </p>
                    </div>
                    <div class="souqpulse-header-actions">
                        <!-- مفتاح اختيار النطاق الزمني -->
                        <div class="date-picker-container">
                            <select id="souqpulse-date-range" class="souqpulse-select">
                                <option value="7days"><?php echo 'آخر 7 أيام'; ?></option>
                                <option value="30days" selected><?php echo 'آخر 30 يوم'; ?></option>
                                <option value="90days"><?php echo 'آخر 90 يوم'; ?></option>
                                <option value="6months"><?php echo 'آخر 6 شهور'; ?></option>
                                <option value="12months"><?php echo 'آخر 12 شهر'; ?></option>
                                <option value="alltime"><?php echo 'كل الوقت'; ?></option>
                                <option value="custom"><?php echo 'فترة مخصصة'; ?></option>
                            </select>

                            <!-- حقول مخصصة للنطاق الزمني تظهر عند الحاجة -->
                            <div id="souqpulse-custom-dates" class="custom-dates-inputs"
                                style="display:none; align-items:center; gap:8px;">
                                <input type="date" id="souqpulse-start-date" class="souqpulse-input-date"
                                    placeholder="<?php echo 'من تاريخ'; ?>">
                                <span><?php echo 'إلى'; ?></span>
                                <input type="date" id="souqpulse-end-date" class="souqpulse-input-date"
                                    placeholder="<?php echo 'إلى تاريخ'; ?>">
                            </div>

                            <div class="comparison-toggle">
                                <label class="switch">
                                    <input type="checkbox" id="souqpulse-compare-toggle" checked>
                                    <span class="slider round"></span>
                                </label>
                                <span
                                    class="compare-label"><?php echo 'مقارنة بالفترة السابقة'; ?></span>
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
                                title="<?php echo 'صافي الإيرادات (شاملاً الضرائب والشحن ومخصوماً منه المرتجعات)'; ?>"><?php echo 'المبيعات الإجمالية'; ?></span>
                            <span class="card-icon"><span class="dashicons dashicons-chart-area"></span></span>
                        </div>
                        <div class="card-body">
                            <h2 class="kpi-value"><?php echo esc_html(sprintf('%s ' . $currency_symbol, '0.00')); ?>
                            </h2>
                            <span class="kpi-change positive">↑ 0% <span
                                    class="change-label"><?php echo 'vs الفترة السابقة'; ?></span></span>
                            <div class="kpi-sparkline" id="sparkline-sales"></div>
                        </div>
                    </div>

                    <!-- كارت الطلبات -->
                    <div class="souqpulse-card kpi-card" id="kpi-orders">
                        <div class="card-header">
                            <span class="card-title"><?php echo 'عدد الطلبات'; ?></span>
                            <span class="card-icon"><span class="dashicons dashicons-cart"></span></span>
                        </div>
                        <div class="card-body">
                            <h2 class="kpi-value">0</h2>
                            <span class="kpi-change positive">↑ 0% <span
                                    class="change-label"><?php echo 'vs الفترة السابقة'; ?></span></span>
                            <div class="kpi-sparkline" id="sparkline-orders"></div>
                        </div>
                    </div>

                    <!-- كارت متوسط قيمة الطلب (AOV) -->
                    <div class="souqpulse-card kpi-card" id="kpi-aov">
                        <div class="card-header">
                            <span class="card-title"><?php echo 'متوسط قيمة الطلب'; ?></span>
                            <span class="card-icon"><span class="dashicons dashicons-calculator"></span></span>
                        </div>
                        <div class="card-body">
                            <h2 class="kpi-value"><?php echo esc_html(sprintf('%s ' . $currency_symbol, '0.00')); ?>
                            </h2>
                            <span class="kpi-change negative">↓ 0% <span
                                    class="change-label"><?php echo 'vs الفترة السابقة'; ?></span></span>
                            <div class="kpi-sparkline" id="sparkline-aov"></div>
                        </div>
                    </div>

                    <!-- كارت الجلسات (Sessions) -->
                    <div class="souqpulse-card kpi-card" id="kpi-sessions">
                        <div class="card-header">
                            <span class="card-title"><?php echo 'عدد الزيارات (الجلسات)'; ?></span>
                            <span class="card-icon"><span class="dashicons dashicons-admin-users"></span></span>
                        </div>
                        <div class="card-body">
                            <h2 class="kpi-value">0</h2>
                            <span class="kpi-change positive">↑ 0% <span
                                    class="change-label"><?php echo 'vs الفترة السابقة'; ?></span></span>
                            <span class="kpi-meta-text"
                                id="sessions-duration-meta"><?php echo 'متوسط مدة الزيارة: 0 ثانية'; ?></span>
                            <div class="kpi-sparkline" id="sparkline-sessions"></div>
                        </div>
                    </div>

                    <!-- كارت معدل الارتداد (Bounce Rate) -->
                    <div class="souqpulse-card kpi-card" id="kpi-bounce-rate">
                        <div class="card-header">
                            <span class="card-title"><?php echo 'معدل الارتداد'; ?></span>
                            <span class="card-icon"><span class="dashicons dashicons-controls-repeat"></span></span>
                        </div>
                        <div class="card-body">
                            <h2 class="kpi-value">0.00%</h2>
                            <span class="kpi-change neutral">0% <span
                                    class="change-label"><?php echo 'vs الفترة السابقة'; ?></span></span>
                            <div class="kpi-sparkline" id="sparkline-bounce"></div>
                        </div>
                    </div>

                    <!-- كارت معدل التحويل (Conversion Rate) -->
                    <div class="souqpulse-card kpi-card" id="kpi-conversion">
                        <div class="card-header">
                            <span class="card-title"><?php echo 'معدل التحويل للمتجر'; ?></span>
                            <span class="card-icon"><span class="dashicons dashicons-awards"></span></span>
                        </div>
                        <div class="card-body">
                            <h2 class="kpi-value">0.00%</h2>
                            <span class="kpi-change positive">↑ 0% <span
                                    class="change-label"><?php echo 'vs الفترة السابقة'; ?></span></span>
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
                                class="card-title"><?php echo 'إجمالي المبيعات وعدد الطلبات عبر الوقت'; ?></span>
                        </div>
                        <div class="card-body">
                            <div id="souqpulse-sales-timeline-chart" class="souqpulse-chart-placeholder"></div>
                        </div>
                    </div>

                    <!-- كارت الزوار في الوقت الفعلي (Real-time) -->
                    <div class="souqpulse-card chart-card flex-1" id="kpi-realtime">
                        <div class="card-header">
                            <span class="card-title"><?php echo 'الزوار النشطون الآن'; ?></span>
                            <span class="realtime-pulse"></span>
                        </div>
                        <div class="card-body realtime-body">
                            <h1 class="realtime-value">0</h1>
                            <p class="realtime-sub"><?php echo 'زائر نشط خلال آخر 5 دقائق'; ?></p>
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
                                class="card-title"><?php echo 'مسار تحويل العميل (Purchase Funnel)'; ?></span>
                        </div>
                        <div class="card-body">
                            <div id="souqpulse-funnel-chart" class="souqpulse-chart-placeholder"></div>
                        </div>
                    </div>

                    <!-- كارت حالة المخزون -->
                    <div class="souqpulse-card details-card flex-1">
                        <div class="card-header">
                            <span class="card-title"><?php echo 'حالة المخزون'; ?></span>
                        </div>
                        <div class="card-body inventory-body">
                            <div class="inventory-status-item">
                                <span class="status-dot green"></span>
                                <span
                                    class="status-label"><?php echo 'إجمالي القطع المتوفرة:'; ?></span>
                                <strong class="status-value" id="inv-total-units">0</strong>
                            </div>
                            <div class="inventory-status-item">
                                <span class="status-dot yellow"></span>
                                <span class="status-label"><?php echo 'منتجات منخفضة المخزون:'; ?></span>
                                <strong class="status-value" id="inv-low-stock">0</strong>
                            </div>
                            <div class="inventory-status-item">
                                <span class="status-dot red"></span>
                                <span
                                    class="status-label"><?php echo 'منتجات نفذت من المخزون:'; ?></span>
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
                            <span class="card-title"><?php echo 'أعلى 5 منتجات مبيعاً'; ?></span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="souqpulse-table" id="table-top-products">
                                    <thead>
                                        <tr>
                                            <th><?php echo 'منتج'; ?></th>
                                            <th style="width: 150px;"><?php echo 'المبيعات'; ?></th>
                                            <th style="width: 120px;"><?php echo 'التقدم'; ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="3" class="text-center text-muted">
                                                <?php echo 'جاري تحميل البيانات...'; ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- أعلى العملاء ونسبة الإيراد -->
                    <div class="souqpulse-card table-card">
                        <div class="card-header">
                            <span class="card-title"><?php echo 'أعلى 5 عملاء ومصدر الإيرادات'; ?></span>
                        </div>
                        <div class="card-body">
                            <!-- ملخص سلوك العملاء والمجموعات -->
                            <div class="customer-stats-summary">
                                <div>
                                    <span><?php echo 'متوسط القيمة الدائمة للعميل (CLV):'; ?></span>
                                    <strong id="cust-avg-clv">ج.م 0.00</strong>
                                </div>
                                <div>
                                    <span><?php echo 'عملاء متكررون:'; ?></span>
                                    <strong id="cust-repeat-count">0</strong>
                                </div>
                                <div>
                                    <span><?php echo 'عملاء لمرة واحدة:'; ?></span>
                                    <strong id="cust-onetime-count">0</strong>
                                </div>
                            </div>

                            <!-- نسبة إيراد العملاء الراجعين -->
                            <div class="returning-rev-widget"
                                style="margin: 15px 0; padding: 12px; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1; display: flex; align-items: center; justify-content: space-between; gap: 12px;">
                                <div style="flex: 1;">
                                    <h4 style="margin: 0 0 4px; font-size: 13px; color: #334155; font-weight: 700;">
                                        <?php echo 'نسبة الإيرادات حسب شريحة العملاء'; ?></h4>
                                    <p id="rev-share-slogan" style="margin: 0; font-size: 11px; color: #64748b;">
                                        <?php echo 'جاري تحليل مساهمة العملاء...'; ?></p>
                                </div>
                                <div id="souqpulse-rev-share-chart" style="min-width: 110px; height: 90px;"></div>
                            </div>

                            <div class="table-responsive">
                                <table class="souqpulse-table" id="table-top-customers">
                                    <thead>
                                        <tr>
                                            <th><?php echo 'عميل'; ?></th>
                                            <th><?php echo 'الطلبات'; ?></th>
                                            <th><?php echo 'إجمالي الشراء'; ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="3" class="text-center text-muted">
                                                <?php echo 'جاري تحميل البيانات...'; ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- التوزيع الجغرافي للمبيعات والطلبات -->
                    <div class="souqpulse-card table-card">
                        <div class="card-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                            <span class="card-title"><?php echo 'التوزيع الجغرافي للمبيعات والطلبات'; ?></span>
                            <div class="souqpulse-geo-toggles">
                                <button class="souqpulse-geo-btn active" data-geo-tab="countries">🌐 <?php echo 'كل الدول'; ?></button>
                                <button class="souqpulse-geo-btn" data-geo-tab="egypt">🇪🇬 <?php echo 'محافظات مصر'; ?></button>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- عرض جدول الدول العالمية (الافتراضي) -->
                            <div id="souqpulse-geo-countries-container">
                                <div class="table-responsive">
                                    <table class="souqpulse-table" id="table-geo-countries">
                                        <thead>
                                            <tr>
                                                <th><?php echo 'الدولة'; ?></th>
                                                <th style="width: 70px; text-align: center;"><?php echo 'الطلبات'; ?></th>
                                                <th style="min-width: 140px;"><?php echo 'الإيرادات ونسبة المساهمة'; ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td colspan="3" class="text-center text-muted"><?php echo 'جاري تحميل التوزيع الجغرافي...'; ?></td>
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
                                                <th><?php echo 'المحافظة'; ?></th>
                                                <th style="width: 70px; text-align: center;"><?php echo 'الطلبات'; ?></th>
                                                <th><?php echo 'المبيعات'; ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td colspan="3" class="text-center text-muted"><?php echo 'جاري التحميل...'; ?></td>
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
                                class="card-title"><?php echo 'تحليل وسائل الدفع ومخاطر الدفع عند الاستلام'; ?></span>
                        </div>
                        <div class="card-body">
                            <div id="souqpulse-payment-chart" style="min-height: 200px;"></div>
                            <div class="table-responsive" style="margin-top: 15px;">
                                <table class="souqpulse-table" id="table-payment-methods">
                                    <thead>
                                        <tr>
                                            <th><?php echo 'وسيلة الدفع'; ?></th>
                                            <th><?php echo 'الطلبات'; ?></th>
                                            <th><?php echo 'الإيرادات'; ?></th>
                                            <th><?php echo 'معدل الاسترجاع'; ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">
                                                <?php echo 'جاري تحميل البيانات...'; ?></td>
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
                                class="card-title"><?php echo 'أوقات ذروة الشراء (الأيام والساعات - بتوقيت مصر)'; ?></span>
                        </div>
                        <div class="card-body">
                            <div id="souqpulse-heatmap-chart" style="min-height: 280px;"></div>
                            <p style="margin: 8px 0 0; font-size: 11px; color: #64748b; text-align: center;">
                                💡
                                <?php echo 'المربعات الأغمق تمثل أوقات الشراء المكثفة للعملاء — يُنصح بها لتوقيت الحملات الإعلانية.'; ?>
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
                                class="card-title"><?php echo 'RFM — تقسيم العملاء التفاعلي'; ?></span>
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
                                class="card-title"><?php echo 'غالباً تُشترى معاً (ترابط المنتجات)'; ?></span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="souqpulse-table" id="table-product-affinity">
                                    <thead>
                                        <tr>
                                            <th><?php echo 'حزمة المنتجات'; ?></th>
                                            <th style="width: 90px; text-align: center;">
                                                <?php echo 'التكرار'; ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="2" class="text-center text-muted">
                                                <?php echo 'جاري تحليل ترابط المنتجات...'; ?></td>
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
                                class="card-title"><?php echo 'مصفوفة احتفاظ العملاء شهرياً (Cohort Retention Heatmap)'; ?></span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="souqpulse-table" id="table-cohort-retention"
                                    style="font-size: 12px; text-align: center;">
                                    <thead>
                                        <tr>
                                            <th style="text-align: right; min-width: 120px;">
                                                <?php echo 'الاحتفاظ الشهري'; ?></th>
                                            <th style="width: 80px;"><?php echo 'العدد'; ?></th>
                                            <th><?php echo 'الشهر 0'; ?></th>
                                            <th><?php echo 'الشهر +1'; ?></th>
                                            <th><?php echo 'الشهر +2'; ?></th>
                                            <th><?php echo 'الشهر +3'; ?></th>
                                            <th><?php echo 'الشهر +4'; ?></th>
                                            <th><?php echo 'الشهر +5'; ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">
                                                <?php echo 'جاري تحليل مصفوفة الاحتفاظ...'; ?>
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
