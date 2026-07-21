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
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * تسجيل إعدادات لوحة التحكم
     */
    public function register_settings() {
        register_setting(
            'souqpulse_settings_group',
            'souqpulse_language',
            array(
                'sanitize_callback' => array( $this, 'sanitize_language' ),
                'default'           => 'auto',
            )
        );
    }

    /**
     * تطهير خيار اللغة
     */
    public function sanitize_language( $value ) {
        $value = sanitize_key( wp_unslash( $value ) );
        $supported = array( 'auto', 'ar', 'en' );
        return in_array( $value, $supported, true ) ? $value : 'auto';
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
            'i18n'     => array(
                'avg_duration_label' => __( 'متوسط مدة الزيارة: ', 'souq-pulse' ),
            ),
        ) );
    }

    /**
     * عرض محتويات لوحة تحليلات "Souq Pulse"
     */
    public function render_dashboard_page() {
        ?>
        <div class="wrap souqpulse-dashboard-wrapper" dir="rtl">
            <!-- الهيدر العلوي للوحة التحكم مع التبويبات -->
            <div class="souqpulse-header-section" style="margin-bottom: 24px;">
                <div class="souqpulse-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
                    <div class="souqpulse-header-title">
                        <h1 style="margin: 0; font-size: 24px; color: var(--souqpulse-dark); display: flex; align-items: center; gap: 8px;">
                            📊 <?php esc_html_e( 'Souq Pulse', 'souq-pulse' ); ?> 
                            <span class="badge" style="background: var(--souqpulse-primary); color: #fff; font-size: 11px; padding: 3px 8px; border-radius: 12px; font-weight: normal;"><?php esc_html_e( 'بيتا', 'souq-pulse' ); ?></span>
                        </h1>
                        <p class="description" style="margin: 4px 0 0; color: #64748b; font-size: 13px;"><?php esc_html_e( 'نظرة شاملة ولحظية على أداء مبيعات متجرك وسلوك الزوار في مكان واحد.', 'souq-pulse' ); ?></p>
                    </div>

                    <!-- التبويبات العلوية -->
                    <div class="souqpulse-nav-tabs">
                        <button class="souqpulse-tab-btn active" data-target="#souqpulse-analytics-tab">
                            📊 <?php esc_html_e( 'لوحة التحليلات', 'souq-pulse' ); ?>
                        </button>
                        <button class="souqpulse-tab-btn" data-target="#souqpulse-settings-tab">
                            ⚙️ <?php esc_html_e( 'الإعدادات', 'souq-pulse' ); ?>
                        </button>
                    </div>
                    
                    <div class="souqpulse-header-actions">
                        <!-- مفتاح اختيار النطاق الزمني -->
                        <div class="date-picker-container">
                            <select id="souqpulse-date-range" class="souqpulse-select">
                                <option value="7days"><?php esc_html_e( 'آخر 7 أيام', 'souq-pulse' ); ?></option>
                                <option value="30days" selected><?php esc_html_e( 'آخر 30 يوم', 'souq-pulse' ); ?></option>
                                <option value="90days"><?php esc_html_e( 'آخر 90 يوم', 'souq-pulse' ); ?></option>
                                <option value="6months"><?php esc_html_e( 'آخر 6 شهور', 'souq-pulse' ); ?></option>
                                <option value="12months"><?php esc_html_e( 'آخر 12 شهر', 'souq-pulse' ); ?></option>
                                <option value="alltime"><?php esc_html_e( 'كل الوقت', 'souq-pulse' ); ?></option>
                                <option value="custom"><?php esc_html_e( 'فترة مخصصة', 'souq-pulse' ); ?></option>
                            </select>

                            <!-- حقول مخصصة للنطاق الزمني تظهر عند الحاجة -->
                            <div id="souqpulse-custom-dates" class="custom-dates-inputs" style="display:none; align-items:center; gap:8px;">
                                <input type="date" id="souqpulse-start-date" class="souqpulse-input-date" placeholder="<?php esc_attr_e( 'من تاريخ', 'souq-pulse' ); ?>">
                                <span><?php esc_html_e( 'إلى', 'souq-pulse' ); ?></span>
                                <input type="date" id="souqpulse-end-date" class="souqpulse-input-date" placeholder="<?php esc_attr_e( 'إلى تاريخ', 'souq-pulse' ); ?>">
                            </div>

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
            </div>

            <!-- تبويب التحليلات -->
            <div id="souqpulse-analytics-tab" class="souqpulse-tab-content active">
                <!-- شبكة كروت الأداء الرئيسية (KPIs) -->
            <div class="souqpulse-kpi-grid">
                <!-- كارت المبيعات الإجمالية -->
                <div class="souqpulse-card kpi-card" id="kpi-sales">
                    <div class="card-header">
                        <span class="card-title" title="<?php esc_attr_e( 'صافي العوائد (شاملاً الضرائب والشحن ومخصوماً منه المرتجعات)', 'souq-pulse' ); ?>"><?php esc_html_e( 'إجمالي المبيعات', 'souq-pulse' ); ?></span>
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

                <!-- أعلى العملاء ونسبة الإيراد -->
                <div class="souqpulse-card table-card">
                    <div class="card-header">
                        <span class="card-title"><?php esc_html_e( 'أعلى 5 عملاء ومصدر الإيرادات', 'souq-pulse' ); ?></span>
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

                        <!-- نسبة إيراد العملاء الراجعين -->
                        <div class="returning-rev-widget" style="margin: 15px 0; padding: 12px; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1; display: flex; align-items: center; justify-content: space-between; gap: 12px;">
                            <div style="flex: 1;">
                                <h4 style="margin: 0 0 4px; font-size: 13px; color: #334155; font-weight: 700;"><?php esc_html_e( 'مساهمة الإيرادات حسب فئة العميل', 'souq-pulse' ); ?></h4>
                                <p id="rev-share-slogan" style="margin: 0; font-size: 11px; color: #64748b;"><?php esc_html_e( 'جاري تحليل مساهمة العملاء الأوفياء...', 'souq-pulse' ); ?></p>
                            </div>
                            <div id="souqpulse-rev-share-chart" style="min-width: 110px; height: 90px;"></div>
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

            <!-- شبكة تحليلات وسائل الدفع وخريطة الذروة -->
            <div class="souqpulse-details-grid" style="margin-top: 24px;">
                <!-- تحليل وسائل الدفع -->
                <div class="souqpulse-card details-card flex-1">
                    <div class="card-header">
                        <span class="card-title"><?php esc_html_e( 'تحليل وسائل الدفع ومخاطر الـ COD', 'souq-pulse' ); ?></span>
                    </div>
                    <div class="card-body">
                        <div id="souqpulse-payment-chart" style="min-height: 200px;"></div>
                        <div class="table-responsive" style="margin-top: 15px;">
                            <table class="souqpulse-table" id="table-payment-methods">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'وسيلة الدفع', 'souq-pulse' ); ?></th>
                                        <th><?php esc_html_e( 'الطلبات', 'souq-pulse' ); ?></th>
                                        <th><?php esc_html_e( 'الإيرادات', 'souq-pulse' ); ?></th>
                                        <th><?php esc_html_e( 'نسبة الإرجاع', 'souq-pulse' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted"><?php esc_html_e( 'جاري تحميل البيانات...', 'souq-pulse' ); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- الخريطة الحرارية لأوقات الطلبات -->
                <div class="souqpulse-card details-card flex-2">
                    <div class="card-header">
                        <span class="card-title"><?php esc_html_e( 'أوقات ذروة الطلبات (الأيام والساعات بتوقيت مصر)', 'souq-pulse' ); ?></span>
                    </div>
                    <div class="card-body">
                        <div id="souqpulse-heatmap-chart" style="min-height: 280px;"></div>
                        <p style="margin: 8px 0 0; font-size: 11px; color: #64748b; text-align: center;">
                            💡 <?php esc_html_e( 'المربعات الأغمق تمثل أوقات الشراء المكثف للعملاء — يوصى بها لتوقيت نشر الحملات الإعلانية.', 'souq-pulse' ); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        </div>

        <!-- تبويب الإعدادات -->
        <div id="souqpulse-settings-tab" class="souqpulse-tab-content" style="display:none; padding: 10px 0;">
            <div class="souqpulse-card" style="max-width: 600px; margin: 0 auto; padding: 30px; background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,0.05); border:1px solid var(--souqpulse-border);">
                <h2 style="margin-top:0; margin-bottom: 20px; font-size:18px; color:var(--souqpulse-dark); font-weight:700;">
                    🌐 <?php esc_html_e( 'إعدادات اللغة والترجمة', 'souq-pulse' ); ?>
                </h2>
                <form method="post" action="options.php">
                    <?php
                    settings_fields( 'souqpulse_settings_group' );
                    $current_lang = get_option( 'souqpulse_language', 'auto' );
                    ?>
                    
                    <div style="margin-bottom: 25px;">
                        <label style="display:block; margin-bottom: 10px; font-weight: 500; color: #475569; font-size: 14px;">
                            <?php esc_html_e( 'لغة لوحة التحكم:', 'souq-pulse' ); ?>
                        </label>
                        <select name="souqpulse_language" class="souqpulse-select" style="width: 100%; max-width: 320px; height: 40px; padding: 0 12px; font-size: 14px; border: 1px solid var(--souqpulse-border); border-radius: 6px; background-color:#fff; color:var(--souqpulse-dark); outline:none;">
                            <option value="auto" <?php selected( $current_lang, 'auto' ); ?>><?php esc_html_e( 'تلقائي (يتبع لغة الموقع)', 'souq-pulse' ); ?></option>
                            <option value="ar" <?php selected( $current_lang, 'ar' ); ?>><?php esc_html_e( 'العربية', 'souq-pulse' ); ?></option>
                            <option value="en" <?php selected( $current_lang, 'en' ); ?>><?php esc_html_e( 'English', 'souq-pulse' ); ?></option>
                        </select>
                        <p style="color: #64748b; font-size: 12px; margin-top: 8px; line-height: 1.5;">
                            <?php esc_html_e( 'اختر لغة عرض لوحة تحليلات نبض السوق. يتطلب حفظ الإعدادات لإعادة تحميل الكلمات المترجمة.', 'souq-pulse' ); ?>
                        </p>
                    </div>

                    <!-- صندوق إرشادات -->
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding: 16px; margin-bottom: 25px; font-size:13px; color:#475569; line-height: 1.6;">
                        <strong>💡 <?php esc_html_e( 'كيف يعمل الكشف التلقائي؟', 'souq-pulse' ); ?></strong><br>
                        <?php esc_html_e( 'في الوضع التلقائي، ستتبع لوحة التحكم لغة موقع ووردبريس المحددة في الإعدادات العامة للموقع. إذا كانت لغة الموقع هي العربية، سيتم عرض اللوحة بالكامل بواجهة RTL ومصطلحات معربة.', 'souq-pulse' ); ?>
                    </div>

                    <div style="border-top: 1px solid #e2e8f0; padding-top: 20px; display: flex; justify-content: flex-end;">
                        <button type="submit" class="souqpulse-btn-primary" style="background:var(--souqpulse-primary); border:none; color:#fff; padding:10px 24px; border-radius:6px; font-weight:500; cursor:pointer; font-size: 14px; font-family: inherit; transition: opacity 0.2s ease;">
                            <?php esc_html_e( 'حفظ الإعدادات', 'souq-pulse' ); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
    }
}
