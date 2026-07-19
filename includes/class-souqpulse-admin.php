<?php
/**
 * متحكم لوحة التحكم الإدارية (Admin Controller)
 *
 * @package SouqPulse
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SouqPulse_Admin {

    /**
     * تهيئة الخطافات (Hooks)
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_submenu_page' ), 99 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * تسجيل صفحة لوحة تحليلات "Souq Pulse" كصفحة فرعية تحت WooCommerce
     */
    public function register_submenu_page() {
        add_submenu_page(
            'woocommerce',                                // الصفحة الأب
            __( 'Souq Pulse — التحليلات والمؤشرات', 'souq-pulse' ), // عنوان الصفحة في المتصفح
            __( 'Souq Pulse', 'souq-pulse' ),                    // اسم القائمة
            'manage_woocommerce',                         // الصلاحية المطلوبة
            'souqpulse-dashboard',                        // المعرف الفريد للصفحة
            array( $this, 'render_dashboard_page' )       // الدالة المسؤولة عن عرض الصفحة
        );
    }

    /**
     * تحميل ملفات التنسيق والأكواد البرمجية للوحة التحكم فقط
     */
    public function enqueue_assets( $hook ) {
        // التأكد من تحميل الملفات في صفحة إحصائيات Souq Pulse فقط
        if ( 'woocommerce_page_souqpulse-dashboard' !== $hook ) {
            return;
        }

        // تضمين مكتبة ApexCharts لعرض الرسومات البيانية
        wp_enqueue_script( 'apexcharts', 'https://cdn.jsdelivr.net/npm/apexcharts', array(), '3.42.0', true );

        // تضمين خطوط Google Fonts (Outfit / Tajawal)
        wp_enqueue_style( 'souqpulse-fonts', 'https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap', array(), SOUQPULSE_VERSION );

        // ملف التنسيقات الخاص بواجهة RTL
        wp_enqueue_style( 'souqpulse-admin-css', SOUQPULSE_URL . 'assets/css/admin-rtl.css', array(), SOUQPULSE_VERSION );

        // ملف الجافاسكريبت للوحة التحكم
        wp_enqueue_script( 'souqpulse-admin-js', SOUQPULSE_URL . 'assets/js/admin.js', array( 'jquery', 'apexcharts' ), SOUQPULSE_VERSION, true );

        // إرسال بيانات AJAX والـ nonces للجافاسكريبت
        wp_localize_script( 'souqpulse-admin-js', 'souqpulseAdminData', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'souqpulse_admin_nonce' ),
        ) );
    }

    /**
     * عرض محتويات لوحة تحليلات "Souq Pulse"
     */
    public function render_dashboard_page() {
        ?>
        <div class="wrap souqpulse-dashboard-wrapper" dir="rtl">
            <!-- الهيدر العلوي للوحة التحكم -->
            <div class="souqpulse-header">
                <div class="souqpulse-header-title">
                    <h1><?php esc_html_e( 'Souq Pulse', 'souq-pulse' ); ?> <span class="badge"><?php esc_html_e( 'بيتا', 'souq-pulse' ); ?></span></h1>
                    <p class="description"><?php esc_html_e( 'نظرة شاملة ولحظية على أداء مبيعات متجرك وسلوك الزوار في مكان واحد.', 'souq-pulse' ); ?></p>
                </div>
                <div class="souqpulse-header-actions">
                    <!-- مفتاح اختيار النطاق الزمني -->
                    <div class="date-picker-container">
                        <select id="souqpulse-date-range" class="souqpulse-select">
                            <option value="7days"><?php esc_html_e( 'آخر 7 أيام', 'souq-pulse' ); ?></option>
                            <option value="30days" selected><?php esc_html_e( 'آخر 30 يوم', 'souq-pulse' ); ?></option>
                            <option value="custom"><?php esc_html_e( 'نطاق مخصص', 'souq-pulse' ); ?></option>
                        </select>
                        <div class="comparison-toggle">
                            <label class="switch">
                                <input type="checkbox" id="souqpulse-compare-toggle" checked>
                                <span class="slider round"></span>
                            </label>
                            <span class="compare-label"><?php esc_html_e( 'مقارنة بالفترة السابقة', 'souq-pulse' ); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- شبكة كروت الأداء الرئيسية (KPIs) -->
            <div class="souqpulse-kpi-grid">
                <!-- كارت المبيعات الإجمالية -->
                <div class="souqpulse-card kpi-card" id="kpi-sales">
                    <div class="card-header">
                        <span class="card-title"><?php esc_html_e( 'إجمالي المبيعات', 'souq-pulse' ); ?></span>
                        <span class="card-icon"><span class="dashicons dashicons-chart-area"></span></span>
                    </div>
                    <div class="card-body">
                        <h2 class="kpi-value"><?php echo esc_html( sprintf( __( 'ج.م %s', 'souq-pulse' ), '0.00' ) ); ?></h2>
                        <span class="kpi-change positive">↑ 0% <span class="change-label"><?php esc_html_e( 'vs الفترة السابقة', 'souq-pulse' ); ?></span></span>
                    </div>
                </div>

                <!-- كارت الطلبات -->
                <div class="souqpulse-card kpi-card" id="kpi-orders">
                    <div class="card-header">
                        <span class="card-title"><?php esc_html_e( 'عدد الطلبات', 'souq-pulse' ); ?></span>
                        <span class="card-icon"><span class="dashicons dashicons-cart"></span></span>
                    </div>
                    <div class="card-body">
                        <h2 class="kpi-value">0</h2>
                        <span class="kpi-change positive">↑ 0% <span class="change-label"><?php esc_html_e( 'vs الفترة السابقة', 'souq-pulse' ); ?></span></span>
                    </div>
                </div>

                <!-- كارت متوسط قيمة الطلب (AOV) -->
                <div class="souqpulse-card kpi-card" id="kpi-aov">
                    <div class="card-header">
                        <span class="card-title"><?php esc_html_e( 'متوسط قيمة الطلب (AOV)', 'souq-pulse' ); ?></span>
                        <span class="card-icon"><span class="dashicons dashicons-calculator"></span></span>
                    </div>
                    <div class="card-body">
                        <h2 class="kpi-value"><?php echo esc_html( sprintf( __( 'ج.م %s', 'souq-pulse' ), '0.00' ) ); ?></h2>
                        <span class="kpi-change negative">↓ 0% <span class="change-label"><?php esc_html_e( 'vs الفترة السابقة', 'souq-pulse' ); ?></span></span>
                    </div>
                </div>

                <!-- كارت الجلسات (Sessions) -->
                <div class="souqpulse-card kpi-card" id="kpi-sessions">
                    <div class="card-header">
                        <span class="card-title"><?php esc_html_e( 'عدد الزيارات (الجلسات)', 'souq-pulse' ); ?></span>
                        <span class="card-icon"><span class="dashicons dashicons-groups"></span></span>
                    </div>
                    <div class="card-body">
                        <h2 class="kpi-value">0</h2>
                        <span class="kpi-change positive">↑ 0% <span class="change-label"><?php esc_html_e( 'vs الفترة السابقة', 'souq-pulse' ); ?></span></span>
                        <span class="kpi-meta-text" id="sessions-duration-meta"><?php esc_html_e( 'متوسط مدة الزيارة: 0 ثانية', 'souq-pulse' ); ?></span>
                    </div>
                </div>

                <!-- كارت معدل الارتداد (Bounce Rate) -->
                <div class="souqpulse-card kpi-card" id="kpi-bounce-rate">
                    <div class="card-header">
                        <span class="card-title"><?php esc_html_e( 'معدل الارتداد', 'souq-pulse' ); ?></span>
                        <span class="card-icon"><span class="dashicons dashicons-controls-repeat"></span></span>
                    </div>
                    <div class="card-body">
                        <h2 class="kpi-value">0.00%</h2>
                        <span class="kpi-change neutral">0% <span class="change-label"><?php esc_html_e( 'vs الفترة السابقة', 'souq-pulse' ); ?></span></span>
                    </div>
                </div>

                <!-- كارت معدل التحويل (Conversion Rate) -->
                <div class="souqpulse-card kpi-card" id="kpi-conversion">
                    <div class="card-header">
                        <span class="card-title"><?php esc_html_e( 'معدل تحويل المتجر', 'souq-pulse' ); ?></span>
                        <span class="card-icon"><span class="dashicons dashicons-awards"></span></span>
                    </div>
                    <div class="card-body">
                        <h2 class="kpi-value">0.00%</h2>
                        <span class="kpi-change positive">↑ 0% <span class="change-label"><?php esc_html_e( 'vs الفترة السابقة', 'souq-pulse' ); ?></span></span>
                    </div>
                </div>
            </div>

            <!-- قسم الرسومات البيانية الرئيسية (المبيعات والـ Funnel) -->
            <div class="souqpulse-charts-grid">
                <!-- مخطط المبيعات عبر الوقت -->
                <div class="souqpulse-card chart-card flex-2">
                    <div class="card-header">
                        <span class="card-title"><?php esc_html_e( 'إجمالي المبيعات وعدد الطلبات بمرور الوقت', 'souq-pulse' ); ?></span>
                    </div>
                    <div class="card-body">
                        <div id="souqpulse-sales-timeline-chart" class="souqpulse-chart-placeholder"></div>
                    </div>
                </div>

                <!-- كارت الزوار في الوقت الفعلي (Real-time) -->
                <div class="souqpulse-card chart-card flex-1" id="kpi-realtime">
                    <div class="card-header">
                        <span class="card-title"><?php esc_html_e( 'الزوار النشطون الآن', 'souq-pulse' ); ?></span>
                        <span class="realtime-pulse"></span>
                    </div>
                    <div class="card-body realtime-body">
                        <h1 class="realtime-value">0</h1>
                        <p class="realtime-sub"><?php esc_html_e( 'زائر نشط خلال آخر 5 دقائق', 'souq-pulse' ); ?></p>
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
                        <span class="card-title"><?php esc_html_e( 'مسار تحويل العميل (Purchase Funnel)', 'souq-pulse' ); ?></span>
                    </div>
                    <div class="card-body">
                        <div id="souqpulse-funnel-chart" class="souqpulse-chart-placeholder"></div>
                    </div>
                </div>

                <!-- كارت حالة المخزون -->
                <div class="souqpulse-card details-card flex-1">
                    <div class="card-header">
                        <span class="card-title"><?php esc_html_e( 'حالة المخزون (Inventory)', 'souq-pulse' ); ?></span>
                    </div>
                    <div class="card-body inventory-body">
                        <div class="inventory-status-item">
                            <span class="status-dot green"></span>
                            <span class="status-label"><?php esc_html_e( 'إجمالي الوحدات المتاحة:', 'souq-pulse' ); ?></span>
                            <strong class="status-value" id="inv-total-units">0</strong>
                        </div>
                        <div class="inventory-status-item">
                            <span class="status-dot yellow"></span>
                            <span class="status-label"><?php esc_html_e( 'منتجات منخفضة المخزون:', 'souq-pulse' ); ?></span>
                            <strong class="status-value" id="inv-low-stock">0</strong>
                        </div>
                        <div class="inventory-status-item">
                            <span class="status-dot red"></span>
                            <span class="status-label"><?php esc_html_e( 'منتجات نفدت من المخزون:', 'souq-pulse' ); ?></span>
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
                        <span class="card-title"><?php esc_html_e( 'أعلى 5 منتجات مبيعًا', 'souq-pulse' ); ?></span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="souqpulse-table" id="table-top-products">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'المنتج', 'souq-pulse' ); ?></th>
                                        <th style="width: 150px;"><?php esc_html_e( 'المبيعات', 'souq-pulse' ); ?></th>
                                        <th style="width: 120px;"><?php esc_html_e( 'التقدم', 'souq-pulse' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted"><?php esc_html_e( 'جاري تحميل البيانات...', 'souq-pulse' ); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- أعلى العملاء -->
                <div class="souqpulse-card table-card">
                    <div class="card-header">
                        <span class="card-title"><?php esc_html_e( 'أعلى 5 عملاء للمتجر', 'souq-pulse' ); ?></span>
                    </div>
                    <div class="card-body">
                        <!-- ملخص سلوك العملاء والمجموعات -->
                        <div class="customer-stats-summary">
                            <div>
                                <span><?php esc_html_e( 'متوسط القيمة العمرية (CLV):', 'souq-pulse' ); ?></span>
                                <strong id="cust-avg-clv">ج.م 0.00</strong>
                            </div>
                            <div>
                                <span><?php esc_html_e( 'عملاء مكررون:', 'souq-pulse' ); ?></span>
                                <strong id="cust-repeat-count">0</strong>
                            </div>
                            <div>
                                <span><?php esc_html_e( 'عملاء لمرة واحدة:', 'souq-pulse' ); ?></span>
                                <strong id="cust-onetime-count">0</strong>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="souqpulse-table" id="table-top-customers">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'العميل', 'souq-pulse' ); ?></th>
                                        <th><?php esc_html_e( 'الطلبات', 'souq-pulse' ); ?></th>
                                        <th><?php esc_html_e( 'إجمالي الشراء', 'souq-pulse' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted"><?php esc_html_e( 'جاري تحميل البيانات...', 'souq-pulse' ); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- التوزيع الجغرافي للطلبات حسب المحافظة -->
                <div class="souqpulse-card table-card">
                    <div class="card-header">
                        <span class="card-title"><?php esc_html_e( 'توزيع الطلبات جغرافياً (المحافظات)', 'souq-pulse' ); ?></span>
                    </div>
                    <div class="card-body">
                        <div id="souqpulse-geo-chart" class="souqpulse-chart-placeholder"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
