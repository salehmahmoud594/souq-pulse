# SouqPulse 📊

**SouqPulse** is a secure, high-performance, and fully integrated Analytics & Purchase Funnel Dashboard plugin for WordPress and WooCommerce. It directly queries native WooCommerce order data (supporting both HPOS `wc_orders` and legacy `posts`) alongside visitor traffic metrics from the **WP Statistics** plugin, rendering an integrated Google-Analytics-style overview directly within a single unified dashboard tab in the WordPress admin area.

---

## 🌟 Key Features

* **Unified Performance Dashboard (6 KPI Cards):**
  * **Sales:** Total revenue (including tax & shipping, minus refunds) for the selected timeframe with comparison to the previous period.
  * **Orders:** Number of successful orders with growth tracking.
  * **Average Order Value (AOV):** Automatically computed average shopping cart size.
  * **Sessions:** Unique visitor sessions (integrated with WP Statistics tables).
  * **Bounce Rate:** The percentage of single-page visits with smart coloration (lower bounce rate = green/positive).
  * **Conversion Rate:** Calculated real-time store conversion rate (Orders / Sessions).

* **Advanced Interactive Charts (ApexCharts):**
  * **Sales & Orders Timeline:** An area chart showing daily net sales trends and orders.
  * **Purchase Funnel Tracking:** Tracks customer journey through 6 stages (View Session → Add to Cart → Begin Checkout → Add Shipping Info → Add Payment Info → Purchase) with drop-off percentages at each stage (GA4-style).
  * **Geographical Sales distribution:** A donut chart showing sales distribution across Egyptian Governorates (`country = 'EG'`), automatically grouping long-tail data under "Other Governorates".
  * **Real-time Visitors Count:** A live sparkline and heartbeat counter showing visitors active in the last 5 minutes.

* **Detailed Tables & Reports:**
  * **Top 5 Best-Selling Products:** Ranked by net revenue using index-optimized `UNION ALL` queries for itemized returns.
  * **Top 5 Store Customers:** Ranked by net spending with customer cohort metrics (average CLV, repeat customer counts, one-time customer counts).
  * **Inventory Health Card:** Real-time visibility into out-of-stock count, low-stock count, and total units available.

* **Sleek & Integrated User Experience (UX):**
  * Fully responsive interface styled with clean modern layout typography.
  * Shimmering animated CSS loading skeletons to prevent screen flashing during AJAX requests.
  * Instant date range select (Last 7 Days, Last 30 Days, Last 90 Days, Last 6 Months, Last 12 Months, All Time, and Custom Ranges) and comparison toggle without page refresh.
  * Unified Settings panel nested directly inside the dashboard page as a secondary tab.

---

## 🛠️ Project Structure & OOP Architecture

The plugin is built following strict Object-Oriented Programming (OOP) principles and Separation of Concerns:

```text
souq-pulse/
├── souq-pulse.php             # Main plugin bootstrapper and translation loader
├── uninstall.php              # Secure database cleanup upon deletion
├── README.md                  # Project documentation (this file)
├── includes/
│   ├── class-souqpulse.php    # Core component loader
│   ├── class-souqpulse-db.php # Database queries, SQL wrappers & caching layer
│   ├── class-souqpulse-admin.php # Dashboard UI renderer & settings manager
│   ├── class-souqpulse-ajax.php # Secured AJAX request controller
│   └── class-souqpulse-tracker.php # Session events logger & heartbeat tracker
└── assets/
    ├── css/
    │   └── admin-rtl.css      # Custom stylesheet, skeletons & tabs layout
    └── js/
        ├── admin.js           # Dashboard UI scripting & ApexCharts renderer
        └── tracker.js         # Frontend tracker & storefront heartbeat script
```

---

## 🔒 Security & Performance Compliance

This plugin was built and audited against strict `wp-guard` and `woo-guard` coding standards:

1. **Native Orders Data Layer:** Eliminates dependency on WooCommerce Analytics background imports (`wc_order_stats`). Queries HPOS (`wc_orders`) and Legacy (`posts`) directly for 100% real-time data accuracy.
2. **Accounting-Grade Refund Deductions:** Refunds are linked to parent order dates, ensuring net sales accurately reflect true revenue without cross-month distortion.
3. **Fixed-Point Precision (`CAST AS DECIMAL`):** Explicitly casts string meta values to `DECIMAL(26,8)` to eliminate floating-point rounding errors across legacy stores and order itemmeta.
4. **SQL Injection Prevention:** All SQL queries are prepared and secured using `$wpdb->prepare`.
5. **AJAX Nonce Verification:** Every admin AJAX endpoint is protected via CSRF Nonces and capability checks (`manage_woocommerce`).
6. **HPOS Compatibility:** Fully declared and 100% compatible with WooCommerce's High-Performance Order Storage (HPOS).
7. **Transient Caching:** Heavy statistical reports are cached as transients for **15 minutes** using locale-aware cache keys.

---

## 📋 Changelog

### Version 1.1.0
* **Feature:** Replaced WooCommerce Analytics table dependencies with direct, native WooCommerce order queries (HPOS & Legacy).
* **Feature:** Accounting-grade Net Sales calculation with refund attribution linked to parent order date.
* **Feature:** Index-optimized `UNION ALL` product revenue queries for fast execution.
* **Enhancement:** Added `CAST(meta_value AS DECIMAL(26,8))` for fixed-point decimal precision across string meta fields.
* **Enhancement:** Strict first-order-ever validation (`MIN(date_created)`) filtering valid order statuses for new customer counts.
* **UX:** Updated Sales KPI card UI with explicit tax, shipping, and refund definition tooltips.
* **Performance:** Updated transient cache expiration cycle to 15 minutes.

---

## 🚀 Installation & Setup

1. Verify that **WooCommerce** and **WP Statistics** are installed and active on your WordPress site.
2. Upload the `souq-pulse` directory to `/wp-content/plugins/`.
3. Activate **SouqPulse** from the WordPress admin plugins dashboard.
4. Navigate to **WooCommerce** → **Souq Pulse** to explore the dashboard.

---

## 👨‍💻 Developer & Author

* **Developed by:** Saleh Mahmoud
* **GitHub Profile:** [@salehmahmoud594](https://github.com/salehmahmoud594)
* **Repository URL:** [github.com/salehmahmoud594/souq-pulse](https://github.com/salehmahmoud594/souq-pulse)

---

## 📄 License

This project is licensed under the GPL v2 or later license.
