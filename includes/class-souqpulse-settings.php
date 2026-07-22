<?php
/**
 * صفحة إعدادات SouqPulse — اختيار اللغة والإعدادات العامة
 *
 * @package SouqPulse
 */

if (!defined('ABSPATH')) {
    exit;
}

class SouqPulse_Settings
{

    /**
     * مفاتيح خيارات قاعدة البيانات
     */
    const OPTION_LANGUAGE = 'souqpulse_language';

    /**
     * اللغات المدعومة
     */
    private static $supported_locales = array(
        'auto' => 'Automatic (Follow Site)',
        'ar' => 'العربية',
        'en_US' => 'English',
    );

    /**
     * تسجيل الصفحة والإعدادات
     */
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_settings_assets'));
    }

    /**
     * إضافة صفحة الإعدادات كـ submenu تحت SouqPulse
     */
    public function add_settings_page()
    {
        add_submenu_page(
            'woocommerce',
            __('SouqPulse Settings', 'souq-pulse'),
            __('SouqPulse — Settings', 'souq-pulse'),
            'manage_woocommerce',
            'souqpulse-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * تسجيل الإعدادات في Settings API
     */
    public function register_settings()
    {
        register_setting(
            'souqpulse_settings_group',
            self::OPTION_LANGUAGE,
            array(
                'sanitize_callback' => array($this, 'sanitize_language'),
                'default' => 'auto',
            )
        );

        add_settings_section(
            'souqpulse_general_section',
            __('Display Settings', 'souq-pulse'),
            '__return_false',
            'souqpulse-settings'
        );

        add_settings_field(
            self::OPTION_LANGUAGE,
            __('Dashboard Language', 'souq-pulse'),
            array($this, 'render_language_field'),
            'souqpulse-settings',
            'souqpulse_general_section'
        );
    }

    /**
     * تعقيم قيمة اختيار اللغة المدخلة
     */
    public function sanitize_language($value)
    {
        $value = sanitize_key(wp_unslash($value));
        return array_key_exists($value, self::$supported_locales) ? $value : 'auto';
    }

    /**
     * رسم حقل اختيار اللغة
     */
    public function render_language_field()
    {
        $current = get_option(self::OPTION_LANGUAGE, 'auto');
        ?>
        <fieldset>
            <?php foreach (self::$supported_locales as $key => $label): ?>
                <label style="display:block; margin-bottom:8px; font-size:14px;">
                    <input type="radio" name="<?php echo esc_attr(self::OPTION_LANGUAGE); ?>" value="<?php echo esc_attr($key); ?>"
                        <?php checked($current, $key); ?> style="margin-left:6px;" />
                    <?php echo esc_html($label); ?>
                    <?php if ('auto' === $key): ?>
                        <span style="color:#888; font-size:12px;">
                            &mdash; <?php esc_html_e('Uses the WordPress site language', 'souq-pulse'); ?>
                        </span>
                    <?php endif; ?>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <p class="description">
            <?php esc_html_e('Choose the display language for the SouqPulse analytics dashboard. Requires a page refresh to take effect.', 'souq-pulse'); ?>
        </p>
        <?php
    }

    /**
     * تحميل أصول CSS الخاصة بصفحة الإعدادات فقط
     */
    public function enqueue_settings_assets($hook)
    {
        if ('woocommerce_page_souqpulse-settings' !== $hook) {
            return;
        }
        // يمكن إضافة CSS/JS مخصص لاحقاً
    }

    /**
     * رسم صفحة الإعدادات كاملة
     */
    public function render_settings_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'souq-pulse'));
        }
        ?>
        <div class="wrap souqpulse-settings-wrap"
            style="max-width:680px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">

            <!-- Header Banner -->
            <div
                style="background: linear-gradient(135deg, #6366f1, #10b981); border-radius: 12px; padding: 28px 32px; margin: 20px 0 28px; color: #fff; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="margin: 0 0 6px; font-size: 22px; color: #fff;">
                        📊 <?php esc_html_e('SouqPulse Settings', 'souq-pulse'); ?>
                    </h1>
                    <p style="margin: 0; opacity: 0.85; font-size: 13px;">
                        <?php esc_html_e('Manage your analytics dashboard preferences.', 'souq-pulse'); ?>
                    </p>
                </div>
                <div style="font-size: 50px; opacity: 0.3;">⚙️</div>
            </div>

            <!-- Settings Form -->
            <div style="background:#fff; border-radius:10px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); padding: 28px 32px;">

                <?php if (isset($_GET['settings-updated'])): ?>
                    <div class="notice notice-success is-dismissible" style="margin-bottom:20px;">
                        <p><strong><?php esc_html_e('Settings saved successfully.', 'souq-pulse'); ?></strong></p>
                    </div>
                <?php endif; ?>

                <form method="post" action="options.php">
                    <?php
                    settings_fields('souqpulse_settings_group');
                    do_settings_sections('souqpulse-settings');
                    ?>

                    <div
                        style="background: #f8faff; border: 1px solid #e2e8f0; border-radius:8px; padding: 20px 24px; margin-bottom: 24px;">
                        <h3 style="margin-top:0; color:#334155; font-size:15px;">
                            🌐 <?php esc_html_e('Dashboard Language', 'souq-pulse'); ?>
                        </h3>
                        <?php $this->render_language_field(); ?>
                    </div>

                    <!-- Info Box -->
                    <div
                        style="background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:14px 18px; margin-bottom:24px; font-size:13px; color:#92400e; display:flex; gap:10px; align-items:flex-start;">
                        <span style="font-size:18px; line-height:1;">💡</span>
                        <div>
                            <strong><?php esc_html_e('How automatic language detection works:', 'souq-pulse'); ?></strong><br>
                            <?php esc_html_e('In Automatic mode, SouqPulse follows the WordPress site language set in Settings → General. If an Arabic locale (e.g., ar_EG) is active and a translation file is present, the dashboard will display in Arabic with RTL layout automatically.', 'souq-pulse'); ?>
                        </div>
                    </div>

                    <?php submit_button(__('Save Settings', 'souq-pulse'), 'primary', 'submit', true, array('style' => 'background:#6366f1; border-color:#6366f1; font-size:14px; padding:8px 24px; height:auto;')); ?>
                </form>
            </div>

            <!-- Footer -->
            <p style="text-align:center; color:#94a3b8; font-size:12px; margin-top:20px;">
                SouqPulse v<?php echo esc_html(SOUQPULSE_VERSION); ?> &mdash;
                <?php esc_html_e('Developed by', 'souq-pulse'); ?>
                <a href="https://github.com/salehmahmoud594" target="_blank" rel="noopener noreferrer"
                    style="color:#6366f1; text-decoration:none;">Saleh Mahmoud</a>
            </p>
        </div>
        <?php
    }
}
