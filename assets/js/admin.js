/**
 * SouqPulse (نبض السوق) - Admin Script
 * Handles dashboard charts initialization and UI interactions
 */

(function($) {
    'use strict';

    // تهيئة اللوحة عند تحميل الصفحة بالكامل
    $(document).ready(function() {
        initMockCharts();
        bindEvents();
    });

    /**
     * ربط الأحداث وعناصر التحكم
     */
    function bindEvents() {
        // تغيير النطاق الزمني
        $('#souqpulse-date-range').on('change', function() {
            var range = $(this).val();
            console.log('تم تغيير النطاق الزمني إلى: ' + range);
            // سيتم ربط طلب AJAX هنا في المراحل اللاحقة
        });

        // زر تفعيل المقارنة
        $('#souqpulse-compare-toggle').on('change', function() {
            var compare = $(this).is(':checked');
            console.log('مقارنة الفترة السابقة: ' + compare);
            // سيتم ربط منطق التحديث هنا لاحقاً
        });
    }

    /**
     * تهيئة رسومات بيانية توضيحية للمرحلة الأولى
     */
    function initMockCharts() {
        // 1. مخطط المبيعات والطلبات بمرور الوقت
        var salesOptions = {
            chart: {
                type: 'area',
                height: 280,
                fontFamily: 'Tajawal, sans-serif',
                toolbar: { show: false },
                rtl: true
            },
            colors: ['#6366f1', '#10b981'],
            stroke: {
                curve: 'smooth',
                width: [3, 2]
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.35,
                    opacityTo: 0.05,
                    stops: [0, 90, 100]
                }
            },
            series: [{
                name: 'المبيعات (ج.م)',
                data: [12000, 15000, 11000, 18000, 22000, 25000, 31000]
            }, {
                name: 'عدد الطلبات',
                data: [45, 52, 38, 65, 74, 82, 95]
            }],
            xaxis: {
                categories: ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'],
                labels: {
                    style: { colors: '#64748b', fontSize: '12px' }
                }
            },
            yaxis: [{
                title: { text: 'المبيعات (ج.م)', style: { color: '#6366f1' } },
                labels: {
                    style: { colors: '#64748b' },
                    formatter: function(val) { return 'ج.م ' + val.toLocaleString(); }
                }
            }, {
                opposite: true,
                title: { text: 'الطلبات', style: { color: '#10b981' } },
                labels: {
                    style: { colors: '#64748b' }
                }
            }],
            tooltip: {
                shared: true,
                intersect: false,
                theme: 'light'
            },
            grid: {
                borderColor: '#e2e8f0',
                strokeDashArray: 4
            },
            legend: {
                position: 'top',
                horizontalAlign: 'right'
            }
        };
        var salesChart = new ApexCharts(document.querySelector("#souqpulse-sales-timeline-chart"), salesOptions);
        salesChart.render();

        // 2. مخطط مسار تحويل العميل (Purchase Funnel)
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
                    dataLabels: {
                        position: 'inside'
                    }
                }
            },
            colors: ['#3b82f6', '#6366f1', '#8b5cf6', '#ec4899', '#f43f5e', '#10b981'],
            dataLabels: {
                enabled: true,
                textAnchor: 'middle',
                style: {
                    colors: ['#fff'],
                    fontWeight: 700
                },
                formatter: function (val, opt) {
                    return opt.w.globals.labels[opt.dataPointIndex] + ": " + val.toLocaleString() + " زيارة";
                },
                offsetX: 0
            },
            series: [{
                name: 'الزيارات',
                data: [1500, 800, 480, 360, 310, 220]
            }],
            xaxis: {
                categories: ['زيارة الموقع', 'إضافة للسلة', 'بدء الدفع', 'معلومات الشحن', 'معلومات الدفع', 'عملية الشراء'],
                labels: {
                    style: { colors: '#64748b' }
                }
            },
            yaxis: {
                labels: { show: false }
            },
            grid: {
                borderColor: '#e2e8f0',
                strokeDashArray: 4
            },
            legend: { show: false },
            tooltip: {
                theme: 'light',
                y: {
                    formatter: function(val) { return val + " زائر"; }
                }
            }
        };
        var funnelChart = new ApexCharts(document.querySelector("#souqpulse-funnel-chart"), funnelOptions);
        funnelChart.render();

        // 3. مخطط توزيع الطلبات جغرافياً (المحافظات)
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
            legend: {
                position: 'bottom',
                horizontalAlign: 'center',
                labels: { colors: '#64748b' }
            },
            dataLabels: {
                enabled: true,
                formatter: function (val) {
                    return Math.round(val) + "%";
                }
            },
            tooltip: {
                theme: 'light',
                y: {
                    formatter: function(val) { return val + " طلب"; }
                }
            }
        };
        var geoChart = new ApexCharts(document.querySelector("#souqpulse-geo-chart"), geoOptions);
        geoChart.render();

        // 4. مخطط الزوار في الوقت الفعلي (Real-time Sparkline)
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
            series: [{
                name: 'النشطون',
                data: [12, 14, 18, 15, 16, 22, 19, 25, 23, 27, 31, 28]
            }],
            tooltip: {
                fixed: { enabled: false },
                x: { show: false },
                y: {
                    title: {
                        formatter: function () { return 'زائر نشط:'; }
                    }
                },
                marker: { show: false }
            }
        };
        var sparklineChart = new ApexCharts(document.querySelector("#souqpulse-realtime-chart"), sparklineOptions);
        sparklineChart.render();

        // تعبئة بيانات الجداول التوضيحية
        fillMockTables();
    }

    /**
     * تعبئة جداول المنتجات والعملاء ببيانات تجريبية للمرحلة الأولى
     */
    function fillMockTables() {
        // أعلى 5 منتجات
        var topProducts = [
            { name: 'قميص كلاسيك أبيض كوتون', sales: 15400, percent: 85 },
            { name: 'بنطال جينز سليم فيت أزرق', sales: 12100, percent: 70 },
            { name: 'حذاء كاجوال جلد طبيعي أسود', sales: 9800, percent: 55 },
            { name: 'تيشيرت رياضي سريع الجفاف سادة', sales: 7400, percent: 40 },
            { name: 'محفظة جلدية ذكية لحماية البطاقات', sales: 4200, percent: 25 }
        ];

        var productsHtml = '';
        topProducts.forEach(function(item) {
            productsHtml += '<tr>' +
                '<td>' + item.name + '</td>' +
                '<td><strong>ج.م ' + item.sales.toLocaleString() + '</strong></td>' +
                '<td>' +
                '  <div class="progress-bar-container">' +
                '    <div class="progress-bar-fill" style="width: ' + item.percent + '%"></div>' +
                '  </div>' +
                '</td>' +
                '</tr>';
        });
        $('#table-top-products tbody').html(productsHtml);

        // أعلى 5 عملاء
        var topCustomers = [
            { name: 'أحمد محمد علي', orders: 12, total: 8400 },
            { name: 'سارة عبدالرحمن محمود', orders: 9, total: 6900 },
            { name: 'خالد عمر الشريف', orders: 8, total: 5400 },
            { name: 'فاطمة الزهراء حسن', orders: 6, total: 4800 },
            { name: 'محمود سعيد إبراهيم', orders: 5, total: 3900 }
        ];

        var customersHtml = '';
        topCustomers.forEach(function(item) {
            customersHtml += '<tr>' +
                '<td>' + item.name + '</td>' +
                '<td class="text-center">' + item.orders + '</td>' +
                '<td><strong>ج.م ' + item.total.toLocaleString() + '</strong></td>' +
                '</tr>';
        });
        $('#table-top-customers tbody').html(customersHtml);

        // تعبئة كروت الـ KPI والقيم الافتراضية
        $('#kpi-sales .kpi-value').text('ج.م 54,900.00');
        $('#kpi-sales .kpi-change').html('↑ 12.5% <span class="change-label">vs الفترة السابقة</span>');

        $('#kpi-orders .kpi-value').text('40');
        $('#kpi-orders .kpi-change').html('↑ 8.3% <span class="change-label">vs الفترة السابقة</span>');

        $('#kpi-aov .kpi-value').text('ج.م 1,372.50');
        $('#kpi-aov .kpi-change').html('↑ 3.8% <span class="change-label">vs الفترة السابقة</span>');

        $('#kpi-sessions .kpi-value').text('1,500');
        $('#kpi-sessions .kpi-change').html('↑ 15.2% <span class="change-label">vs الفترة السابقة</span>');

        $('#kpi-bounce-rate .kpi-value').text('42.30%');
        $('#kpi-bounce-rate .kpi-change').removeClass('positive').addClass('negative').html('↓ 2.1% <span class="change-label">vs الفترة السابقة</span>');

        $('#kpi-conversion .kpi-value').text('2.67%');
        $('#kpi-conversion .kpi-change').html('↑ 0.45% <span class="change-label">vs الفترة السابقة</span>');

        $('.realtime-value').text('28');

        $('#inv-total-units').text('1,420');
        $('#inv-low-stock').text('8');
        $('#inv-out-of-stock').text('3');
    }

})(jQuery);
