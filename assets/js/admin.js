/**
 * SouqPulse (Souq Pulse) - Admin Script
 * Handles dashboard charts initialization, AJAX requests, and UI interactions
 */

(function($) {
    'use strict';

    // المتغيرات العامة لحفظ مثيلات الرسومات البيانية
    var salesChart;
    var funnelChart;
    var geoChart;
    var sparklineChart;

    $(document).ready(function() {
        // تهيئة الرسوم البيانية كقوالب فارغة أولاً
        initChartsShell();
        
        // جلب البيانات الفعلية للفترة الافتراضية (آخر 30 يوم)
        fetchDashboardData();

        // ربط أحداث تغيير المدخلات
        bindEvents();
    });

    /**
     * ربط أحداث النطاق الزمني والمقارنة
     */
    function bindEvents() {
        $('#souqpulse-date-range, #souqpulse-compare-toggle').on('change', function() {
            fetchDashboardData();
        });
    }

    /**
     * عرض الهياكل العظمية المؤقتة (Skeletons) أثناء تحميل البيانات
     */
    function showLoadingSkeletons() {
        $('.kpi-value').addClass('souqpulse-skeleton skeleton-value').text('');
        $('.kpi-change').css('opacity', '0.5');
        $('.souqpulse-table tbody').html(
            '<tr><td colspan="3" class="text-center"><div class="souqpulse-skeleton skeleton-text" style="width:60%; margin:0 auto;"></div></td></tr>' +
            '<tr><td colspan="3" class="text-center"><div class="souqpulse-skeleton skeleton-text" style="width:50%; margin:0 auto;"></div></td></tr>'
        );
    }

    /**
     * إخفاء الهياكل العظمية بعد اكتمال التحميل
     */
    function hideLoadingSkeletons() {
        $('.kpi-value').removeClass('souqpulse-skeleton skeleton-value');
        $('.kpi-change').css('opacity', '1');
    }

    /**
     * جلب بيانات لوحة التحكم عبر AJAX
     */
    function fetchDashboardData() {
        showLoadingSkeletons();

        var range = $('#souqpulse-date-range').val();
        var compare = $('#souqpulse-compare-toggle').is(':checked');

        // تجهيز بيانات الطلب
        var postData = {
            action: 'souqpulse_get_dashboard_data',
            security: souqpulseAdminData.nonce,
            range: range,
            compare: compare ? 'true' : 'false'
        };

        // إرسال طلب AJAX
        $.post(souqpulseAdminData.ajax_url, postData, function(response) {
            hideLoadingSkeletons();

            if (response.success) {
                updateDashboardUI(response.data, compare);
            } else {
                console.error('فشل جلب بيانات Souq Pulse:', response.data);
                // إظهار رسالة خطأ للمستخدم
                $('.kpi-value').text('ج.م 0.00');
            }
        }).fail(function(xhr, status, error) {
            hideLoadingSkeletons();
            console.error('خطأ في الاتصال بالخادم:', error);
        });
    }

    /**
     * تحديث عناصر واجهة المستخدم بالبيانات الحقيقية
     */
    function updateDashboardUI(data, compareEnabled) {
        var current = data.current;
        var previous = data.previous;

        // 1. تحديث قيم الـ KPIs مفرقة التنسيق
        $('#kpi-sales .kpi-value').text(formatCurrency(current.sales));
        $('#kpi-orders .kpi-value').text(current.orders.toLocaleString());
        $('#kpi-aov .kpi-value').text(formatCurrency(current.aov));
        
        // تحديث إحصائيات الزوار من WP Statistics
        $('#kpi-sessions .kpi-value').text(current.sessions.toLocaleString());
        $('#kpi-bounce-rate .kpi-value').text(current.bounce_rate.toFixed(2) + '%');
        $('#sessions-duration-meta').text('متوسط مدة الزيارة: ' + formatDuration(current.avg_duration));

        // تحديث معدل التحويل الحقيقي للمتجر
        $('#kpi-conversion .kpi-value').text(current.conversion_rate.toFixed(2) + '%');

        // تحديث إحصائيات سلوك العملاء الكلية (CLV و Cohorts)
        $('#cust-avg-clv').text(formatCurrency(data.customer_metrics.avg_clv));
        $('#cust-repeat-count').text(data.customer_metrics.repeat_customers.toLocaleString());
        $('#cust-onetime-count').text(data.customer_metrics.onetime_customers.toLocaleString());

        // تحديث التغييرات ونسب المقارنة
        if (compareEnabled) {
            updateKPIChange('#kpi-sales', current.sales, previous.sales);
            updateKPIChange('#kpi-orders', current.orders, previous.orders);
            updateKPIChange('#kpi-aov', current.aov, previous.aov);
            updateKPIChange('#kpi-sessions', current.sessions, previous.sessions);
            updateKPIChange('#kpi-bounce-rate', previous.bounce_rate, current.bounce_rate);
            updateKPIChange('#kpi-conversion', current.conversion_rate, previous.conversion_rate);
        } else {
            $('.kpi-change').css('display', 'none');
        }

        // 2. تحديث جدول أعلى المنتجات مبيعاً
        var productsHtml = '';
        if (data.top_products && data.top_products.length > 0) {
            var maxRevenue = data.top_products[0].revenue;
            data.top_products.forEach(function(item) {
                var percent = maxRevenue > 0 ? (item.revenue / maxRevenue) * 100 : 0;
                productsHtml += '<tr>' +
                    '<td>' + escHtml(item.name) + '</td>' +
                    '<td><strong>' + formatCurrency(item.revenue) + '</strong></td>' +
                    '<td>' +
                    '  <div class="progress-bar-container">' +
                    '    <div class="progress-bar-fill" style="width: ' + percent + '%"></div>' +
                    '  </div>' +
                    '</td>' +
                    '</tr>';
            });
        } else {
            productsHtml = '<tr><td colspan="3" class="text-center text-muted">لا توجد مبيعات في هذه الفترة.</td></tr>';
        }
        $('#table-top-products tbody').html(productsHtml);

        // 3. تحديث جدول أعلى العملاء
        var customersHtml = '';
        if (data.top_customers && data.top_customers.length > 0) {
            data.top_customers.forEach(function(item) {
                customersHtml += '<tr>' +
                    '<td>' + escHtml(item.name) + '</td>' +
                    '<td class="text-center">' + item.orders + '</td>' +
                    '<td><strong>' + formatCurrency(item.total_spend) + '</strong></td>' +
                    '</tr>';
            });
        } else {
            customersHtml = '<tr><td colspan="3" class="text-center text-muted">لا توجد بيانات عملاء لهذه الفترة.</td></tr>';
        }
        $('#table-top-customers tbody').html(customersHtml);

        // 4. تحديث الرسم البياني للمبيعات والطلبات عبر الوقت
        if (data.timeline && data.timeline.length > 0) {
            var days = [];
            var sales = [];
            var orders = [];

            data.timeline.forEach(function(item) {
                // تنسيق تاريخ اليوم بشكل مبسط
                var date = new Date(item.day);
                var formattedDay = date.toLocaleDateString('ar-EG', { day: 'numeric', month: 'short' });
                days.push(formattedDay);
                sales.push(item.sales);
                orders.push(item.orders);
            });

            salesChart.updateSeries([
                { name: 'المبيعات (ج.م)', data: sales },
                { name: 'عدد الطلبات', data: orders }
            ]);
            salesChart.updateOptions({
                xaxis: { categories: days }
            });
        } else {
            // رسم فارغ عند انعدام البيانات
            salesChart.updateSeries([
                { name: 'المبيعات (ج.م)', data: [] },
                { name: 'عدد الطلبات', data: [] }
            ]);
        }

        // 5. دمج وتحديث كروت المراحل اللاحقة بقيم تجريبية مؤقتاً لتفادي الفراغات
        updateRemainingMockData(current.orders, current.sessions);
    }

    /**
     * تحديث نسبة المقارنة للفترة السابقة
     */
    function updateKPIChange(selector, current, previous) {
        var $changeEl = $(selector + ' .kpi-change');
        $changeEl.css('display', 'inline-flex');

        var diff = current - previous;
        var percent = 0;

        if (previous > 0) {
            percent = (diff / previous) * 100;
        } else if (current > 0) {
            percent = 100;
        }

        var arrow = '';
        var statusClass = 'neutral';

        if (percent > 0) {
            arrow = '↑ ';
            statusClass = 'positive';
        } else if (percent < 0) {
            arrow = '↓ ';
            statusClass = 'negative';
            percent = Math.abs(percent);
        }

        $changeEl.removeClass('positive negative neutral').addClass(statusClass);
        $changeEl.html(arrow + percent.toFixed(1) + '% <span class="change-label">vs الفترة السابقة</span>');
    }

    /**
     * تهيئة المخططات كقوالب وهياكل فارغة
     */
    function initChartsShell() {
        // 1. مخطط المبيعات عبر الوقت
        var salesOptions = {
            chart: {
                type: 'area',
                height: 280,
                fontFamily: 'Tajawal, sans-serif',
                toolbar: { show: false },
                rtl: true
            },
            colors: ['#6366f1', '#10b981'],
            stroke: { curve: 'smooth', width: [3, 2] },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.35,
                    opacityTo: 0.05,
                    stops: [0, 90, 100]
                }
            },
            series: [
                { name: 'المبيعات (ج.م)', data: [] },
                { name: 'عدد الطلبات', data: [] }
            ],
            xaxis: {
                categories: [],
                labels: { style: { colors: '#64748b', fontSize: '12px' } }
            },
            yaxis: [{
                title: { text: 'المبيعات (ج.م)', style: { color: '#6366f1' } },
                labels: {
                    style: { colors: '#64748b' },
                    formatter: function(val) { return 'ج.م ' + Math.round(val).toLocaleString(); }
                }
            }, {
                opposite: true,
                title: { text: 'الطلبات', style: { color: '#10b981' } },
                labels: { style: { colors: '#64748b' } }
            }],
            grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
            legend: { position: 'top', horizontalAlign: 'right' }
        };
        salesChart = new ApexCharts(document.querySelector("#souqpulse-sales-timeline-chart"), salesOptions);
        salesChart.render();

        // 2. مخطط مسار تحويل العميل (Funnel)
        var funnelOptions = {
            chart: {
                type: 'bar',
                height: 280,
                fontFamily: 'Tajawal, sans-serif',
                toolbar: { show: false },
                rtl: true
            },
            plotOptions: {
                bar: {
                    borderRadius: 4,
                    horizontal: true,
                    barHeight: '65%',
                    distributed: true,
                    dataLabels: { position: 'inside' }
                }
            },
            colors: ['#3b82f6', '#6366f1', '#8b5cf6', '#ec4899', '#f43f5e', '#10b981'],
            dataLabels: {
                enabled: true,
                style: { colors: ['#fff'], fontWeight: 700 },
                formatter: function (val, opt) {
                    return opt.w.globals.labels[opt.dataPointIndex] + ": " + val.toLocaleString();
                }
            },
            series: [{ name: 'الزيارات', data: [1500, 800, 480, 360, 310, 220] }],
            xaxis: {
                categories: ['زيارة الموقع', 'إضافة للسلة', 'بدء الدفع', 'معلومات الشحن', 'معلومات الدفع', 'عملية الشراء'],
                labels: { style: { colors: '#64748b' } }
            },
            yaxis: { labels: { show: false } },
            legend: { show: false }
        };
        funnelChart = new ApexCharts(document.querySelector("#souqpulse-funnel-chart"), funnelOptions);
        funnelChart.render();

        // 3. مخطط توزيع المحافظات
        var geoOptions = {
            chart: {
                type: 'donut',
                height: 280,
                fontFamily: 'Tajawal, sans-serif',
                rtl: true
            },
            colors: ['#6366f1', '#10b981', '#f59e0b', '#3b82f6', '#ef4444'],
            series: [45, 25, 15, 10, 5],
            labels: ['القاهرة', 'الإسكندرية', 'الجيزة', 'القليوبية', 'أخرى'],
            legend: { position: 'bottom', horizontalAlign: 'center', labels: { colors: '#64748b' } },
            dataLabels: {
                enabled: true,
                formatter: function (val) { return Math.round(val) + "%"; }
            }
        };
        geoChart = new ApexCharts(document.querySelector("#souqpulse-geo-chart"), geoOptions);
        geoChart.render();

        // 4. مخطط الزوار بالوقت الفعلي
        var sparklineOptions = {
            chart: {
                type: 'area',
                height: 80,
                sparkline: { enabled: true },
                fontFamily: 'Tajawal, sans-serif',
                rtl: true
            },
            stroke: { curve: 'smooth', width: 2 },
            fill: { opacity: 0.15 },
            colors: ['#10b981'],
            series: [{ name: 'النشطون', data: [12, 14, 18, 15, 16, 22, 19, 25, 23, 27, 31, 28] }]
        };
        sparklineChart = new ApexCharts(document.querySelector("#souqpulse-realtime-chart"), sparklineOptions);
        sparklineChart.render();
    }

    /**
     * دالة لتحديث باقي البيانات التجريبية للمراحل القادمة
     */
    function updateRemainingMockData(realOrdersCount, realSessionsCount) {
        $('.realtime-value').text('18');
        $('#inv-total-units').text('850');
        $('#inv-low-stock').text('4');
        $('#inv-out-of-stock').text('2');
    }

    /**
     * تنسيق مدة الزيارة بالدقائق والثواني
     */
    function formatDuration(seconds) {
        if (!seconds || seconds <= 0) return '0 ثانية';
        var m = Math.floor(seconds / 60);
        var s = seconds % 60;
        if (m > 0) {
            return m + ' د ' + s + ' ث';
        }
        return s + ' ثانية';
    }

    /**
     * تنسيق العملة المحلية
     */
    function formatCurrency(val) {
        return 'ج.م ' + parseFloat(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    /**
     * تنظيف نصوص HTML لزيادة الأمان
     */
    function escHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;')
                  .replace(/</g, '&lt;')
                  .replace(/>/g, '&gt;')
                  .replace(/"/g, '&quot;')
                  .replace(/'/g, '&#039;');
    }

})(jQuery);
