# SouqPulse 📊

**SouqPulse** is a secure, high-performance, and fully integrated Analytics & Purchase Funnel Dashboard plugin for WordPress and WooCommerce. It directly queries native WooCommerce order data (supporting both HPOS `wc_orders` and legacy `posts`) alongside visitor traffic metrics from the **WP Statistics** plugin, rendering an integrated Google-Analytics-style overview in a native Arabic RTL interface directly within the WordPress admin area.

---

## 🌟 Key Features

* **Unified Performance Dashboard (6 KPI Cards with Sparklines):**
  * **Sales:** Total revenue (including tax & shipping, minus refunds) for the selected timeframe with comparison to the previous period.
  * **Orders:** Number of successful orders with growth tracking.
  * **Average Order Value (AOV):** Automatically computed average shopping cart size.
  * **Sessions:** Unique visitor sessions (integrated with WP Statistics tables).
  * **Bounce Rate:** The percentage of single-page visits with smart coloration (lower bounce rate = green/positive).
  * **Conversion Rate:** Calculated real-time store conversion rate (Orders / Sessions).

* **Advanced Interactive Analytics & Charts (ApexCharts):**
  * **Sales & Orders Timeline:** An area chart showing daily net sales trends and order volumes.
  * **Purchase Funnel Tracking:** Customer journey through 6 stages (Visit → View Product → Add to Cart → Begin Checkout → Add Info → Purchase) with drop-off percentages.
  * **Geographical Sales Distribution:** Multi-tab geographic analytics covering global countries and an interactive distribution for Egyptian Governorates.
  * **Peak Order Heatmap:** 24x7 matrix highlighting peak buying hours and days to optimize ad campaigns.
  * **RFM Customer Segmentation:** Interactive customer categorization (Champions, Loyal, At Risk, Hibernating, Lost, etc.).
  * **Cohort Retention Matrix:** Monthly customer retention heatmap tracking repeat purchase behavior over time.
  * **Product Affinity (Frequently Bought Together):** Analyzes pair purchasing patterns to reveal bundle opportunities.
  * **Payment Methods & COD Risk Analysis:** Breakdown of payment options with return rate metrics.
  * **Real-time Visitors Heartbeat:** Live sparkline counter tracking active sessions in the last 5 minutes.

* **Detailed Tables & Reports:**
  * **Top 5 Best-Selling Products:** Ranked by net revenue using index-optimized queries.
  * **Top 5 Store Customers:** Ranked by net spending with CLV and repeat purchase counts.
  * **Inventory Health Card:** Real-time visibility into out-of-stock count, low-stock count, and total units available.

* **Sleek Native Arabic RTL Interface:**
  * Native Arabic RTL layout out-of-the-box.
  * Shimmering animated CSS loading skeletons to prevent screen flashing during AJAX requests.
  * Instant date range filtering (Last 7 Days, Last 30 Days, Last 90 Days, Last 6 Months, Last 12 Months, All Time, and Custom Ranges) with comparison toggle without page refresh.

---

## 🛠️ Project Structure & OOP Architecture

The plugin is built following strict Object-Oriented Programming (OOP) principles and Separation of Concerns:

```text
souq-pulse/
├── souq-pulse.php             # Main plugin bootstrapper & textdomain loader
├── uninstall.php              # Secure database cleanup upon deletion
├── README.md                  # Project documentation
├── check-i18n.sh              # Shell script for translation completeness checks
├── includes/
│   ├── class-souqpulse.php    # Core component loader
│   ├── class-souqpulse-db.php # Database queries, SQL wrappers & caching layer
│   ├── class-souqpulse-admin.php # Dashboard UI renderer
│   ├── class-souqpulse-ajax.php # Secured AJAX request controller
│   └── class-souqpulse-tracker.php # Session events logger & heartbeat tracker
├── languages/
│   ├── souq-pulse.pot         # Translation template
│   ├── souq-pulse-ar.po       # Arabic translations source
│   └── souq-pulse-ar.mo       # Compiled binary translation file
├── scripts/
│   └── check_i18n.php         # Cross-platform CLI translation verifier
└── assets/
    ├── css/
    │   └── admin-rtl.css      # Custom RTL stylesheet & skeleton animations
    └── js/
        ├── admin.js           # Dashboard UI scripting & ApexCharts renderer
        └── tracker.js         # Storefront event tracking & heartbeat script
```

---

## 🔒 Security & Performance Compliance

This plugin was built and audited against strict `wp-guard` and `woo-guard` coding standards:

1. **Native Orders Data Layer:** Queries HPOS (`wc_orders`) and Legacy (`posts`) directly for 100% real-time data accuracy without external dependencies.
2. **Accounting-Grade Refund Deductions:** Refunds are linked to parent order dates, ensuring net sales accurately reflect true revenue.
3. **Fixed-Point Precision (`CAST AS DECIMAL`):** Explicitly casts string meta values to `DECIMAL(26,8)` to eliminate floating-point rounding errors.
4. **SQL Injection Prevention:** All SQL queries are prepared using `$wpdb->prepare`.
5. **AJAX Nonce & Capability Checks:** Every AJAX endpoint is protected via CSRF Nonces and capability verification (`manage_woocommerce`).
6. **HPOS Compatibility:** Fully declared and 100% compatible with WooCommerce High-Performance Order Storage (HPOS).
7. **Transient Caching:** Heavy statistical reports are cached for 15 minutes using locale-aware keys.

---

## 📋 Changelog

### Version 1.2.0
* **Feature:** Integrated RFM Interactive Customer Segmentation analysis.
* **Feature:** Added Monthly Cohort Customer Retention Heatmap.
* **Feature:** Added Frequently Bought Together (Product Affinity) bundle engine.
* **Feature:** Added Payment Methods Distribution & COD Risk analytics.
* **Feature:** Added Peak Order Times Heatmap (24x7 days & hours matrix).
* **Refactoring:** Streamlined interface to native Arabic RTL without external settings dependency.
* **Localization:** 100% complete Gettext translation coverage (256/256 strings extracted and compiled).
* **DevOps:** Added automated `check_i18n.php` verification script.

### Version 1.1.0
* **Feature:** Native WooCommerce order queries (HPOS & Legacy support).
* **Feature:** Accounting-grade Net Sales calculation with parent-order refund attribution.
* **UX:** Updated KPI card UI with tax, shipping, and refund tooltips.
* **Performance:** Optimized transient caching cycle.

---

## 🚀 Installation & Setup

1. Verify that **WooCommerce** and **WP Statistics** are installed and active.
2. Upload the `souq-pulse` directory to `/wp-content/plugins/`.
3. Activate **SouqPulse** from the WordPress admin plugins dashboard.
4. Navigate to **WooCommerce** → **Souq Pulse** to view the dashboard.

---

## 👨‍💻 Developer & Author

* **Developed by:** Saleh Mahmoud
* **GitHub Profile:** [@salehmahmoud594](https://github.com/salehmahmoud594)
* **Repository URL:** [github.com/salehmahmoud594/souq-pulse](https://github.com/salehmahmoud594/souq-pulse)

---

## 📄 License

This project is licensed under the GPL v2 or later license.
