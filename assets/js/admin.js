/**
 * SouqPulse (Souq Pulse) - Admin Script
 * Handles dashboard charts initialization, AJAX requests, and UI interactions
 */

(function($) {
    'use strict';
    
    // تعريف متغير الترجمة
    var i18n = (typeof souqpulseAdminData !== 'undefined' && souqpulseAdminData.i18n) ? souqpulseAdminData.i18n : {};

    // المتغيرات العامة لحفظ مثيلات الرسومات البيانية
    var salesChart;
    var funnelChart;
    var geoChart;
    var sparklineChart;
    var revShareChart;
    var paymentChart;
    var heatmapChart;
    var kpiSparklines = {
        sales: null,
        orders: null,
        aov: null,
        sessions: null,
        bounce: null,
        conversion: null
    };

    $(document).ready(function() {
        // تهيئة الرسوم البيانية كقوالب فارغة أولاً
        initChartsShell();
        
        // جلب البيانات الفعلية للفترة الافتراضية (آخر 30 يوم)
        fetchDashboardData();

        // ربط أحداث تغيير المدخلات والتبويبات
        bindEvents();
        initTabs();

        // تحديث عداد الزوار النشطين الآن كل 20 ثانية عبر الـ AJAX الخفيف
        setInterval(fetchRealtimeCount, 20000);
    });

    /**
     * تهيئة التبويبات العلوية ولوحة التحكم
     */
    function initTabs() {
        $('.souqpulse-tab-btn').on('click', function(e) {
            e.preventDefault();
            var target = $(this).data('target');
            $('.souqpulse-tab-btn').removeClass('active');
            $(this).addClass('active');
            $('.souqpulse-tab-content').removeClass('active').hide();
            $(target).addClass('active').show();
        });
    }

    /**
     * ربط أحداث النطاق الزمني والمقارنة
     */
    /**
     * ربط أحداث النطاق الزمني والمقارنة
     */
    function bindEvents() {
        $('#souqpulse-date-range').on('change', function() {
            var range = $(this).val();
            
            // إظهار وإخفاء حقول التاريخ المخصص
            if (range === 'custom') {
                $('#souqpulse-custom-dates').css('display', 'inline-flex');
            } else {
                $('#souqpulse-custom-dates').css('display', 'none');
            }

            // تعطيل المقارنة عند اختيار i18n.all_time
            if (range === 'alltime') {
                $('#souqpulse-compare-toggle').prop('checked', false).prop('disabled', true);
                $('.comparison-toggle').css('opacity', '0.5');
            } else {
                $('#souqpulse-compare-toggle').prop('disabled', false);
                $('.comparison-toggle').css('opacity', '1');
            }

            if (range !== 'custom') {
                fetchDashboardData();
            }
        });

        $('#souqpulse-compare-toggle').on('change', function() {
            fetchDashboardData();
        });

        // جلب البيانات عند إدخال كلا التاريخين المخصصين
        $('#souqpulse-start-date, #souqpulse-end-date').on('change', function() {
            var start = $('#souqpulse-start-date').val();
            var end = $('#souqpulse-end-date').val();
            if (start && end) {
                fetchDashboardData();
            }
        });

        // تبديل عرض التوزيع الجغرافي بين جميع الدول ومحافظات مصر
        $(document).on('click', '.souqpulse-geo-btn', function(e) {
            e.preventDefault();
            var tab = $(this).data('geo-tab');
            $('.souqpulse-geo-btn').removeClass('active');
            $(this).addClass('active');

            if (tab === 'countries') {
                $('#souqpulse-geo-countries-container').show();
                $('#souqpulse-geo-egypt-container').hide();
            } else {
                $('#souqpulse-geo-countries-container').hide();
                $('#souqpulse-geo-egypt-container').show();
            }
        });
    }

    /**
     * عرض الهياكل العظمية المؤقتة (Skeletons) أثناء تحميل البيانات
     */
    function showLoadingSkeletons() {
        $('.kpi-value').addClass('souqpulse-skeleton skeleton-value').text('');
        $('.kpi-change').css('opacity', '0.5');
        $('.souqpulse-chart-placeholder, #souqpulse-sales-timeline-chart, #souqpulse-funnel-chart, #souqpulse-geo-chart').addClass('souqpulse-skeleton');
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
        $('.souqpulse-chart-placeholder, #souqpulse-sales-timeline-chart, #souqpulse-funnel-chart, #souqpulse-geo-chart').removeClass('souqpulse-skeleton');
    }

    /**
     * جلب بيانات لوحة التحكم عبر AJAX
     */
    function fetchDashboardData() {
        showLoadingSkeletons();

        var range = $('#souqpulse-date-range').val();
        var compare = $('#souqpulse-compare-toggle').is(':checked');
        var startDate = $('#souqpulse-start-date').val();
        var endDate = $('#souqpulse-end-date').val();

        // تجهيز بيانات الطلب
        var postData = {
            action: 'souqpulse_get_dashboard_data',
            security: souqpulseAdminData.nonce,
            range: range,
            compare: compare ? 'true' : 'false',
            start_date: startDate,
            end_date: endDate
        };

        // إرسال طلب AJAX
        $.post(souqpulseAdminData.ajax_url, postData, function(response) {
            hideLoadingSkeletons();

            if (response.success) {
                updateDashboardUI(response.data, compare);
            } else {
                console.error(i18n.failed_fetching_data, response.data);
                // إظهار رسالة خطأ للمستخدم
                $('.kpi-value').text('' + souqpulseAdminData.currency_symbol + ' 0.00');
            }
        }).fail(function(xhr, status, error) {
            hideLoadingSkeletons();
            console.error(i18n.error_connecting_server, error);
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
        
        // تحديث إحصائيات الزوار من WP Statistics والوقت الفعلي
        $('#kpi-sessions .kpi-value').text(current.sessions.toLocaleString());
        $('#kpi-bounce-rate .kpi-value').text(current.bounce_rate.toFixed(2) + '%');
        var durationLabel = (souqpulseAdminData.i18n && souqpulseAdminData.i18n.avg_duration_label) ? souqpulseAdminData.i18n.avg_duration_label : i18n.avg_duration_label;
        $('#sessions-duration-meta').text(durationLabel + formatDuration(current.avg_duration));
        $('.realtime-value').text(data.realtime_active_users || 0);
        updateRealtimeSparkline(data.realtime_active_users || 0);

        // تحديث معدل التحويل الحقيقي للمتجر
        $('#kpi-conversion .kpi-value').text(current.conversion_rate.toFixed(2) + '%');

        // تحديث إحصائيات سلوك العملاء الكلية (CLV و Cohorts)
        $('#cust-avg-clv').text(formatCurrency(data.customer_metrics.avg_clv));
        $('#cust-repeat-count').text(data.customer_metrics.repeat_customers.toLocaleString());
        $('#cust-onetime-count').text(data.customer_metrics.onetime_customers.toLocaleString());

        // تحديث التغييرات ونسب المقارنة
        if (compareEnabled) {
            updateKPIChange('#kpi-sales', current.sales, previous.sales, false);
            updateKPIChange('#kpi-orders', current.orders, previous.orders, false);
            updateKPIChange('#kpi-aov', current.aov, previous.aov, false);
            updateKPIChange('#kpi-sessions', current.sessions, previous.sessions, false);
            updateKPIChange('#kpi-bounce-rate', current.bounce_rate, previous.bounce_rate, true); // الارتداد الأقل أفضل
            updateKPIChange('#kpi-conversion', current.conversion_rate, previous.conversion_rate, false);
        } else {
            $('.kpi-change').css('display', 'none');
        }

        // 2. تحديث جدول أعلى المنتجات مبيعاً
        var productsHtml = '';
        if (data.top_products && data.top_products.length > 0) {
            var maxRevenue = data.top_products[0].revenue;
            data.top_products.forEach(function(item) {
                var percent = maxRevenue > 0 ? (item.revenue / maxRevenue) * 100 : 0;
                var thumb = item.thumbnail ? '<img src="' + escHtml(item.thumbnail) + '" alt="" style="width:28px; height:28px; border-radius:4px; object-fit:cover; margin-left:8px; vertical-align:middle;" />' : '<span style="display:inline-block; width:28px; height:28px; border-radius:4px; background:#e2e8f0; color:#64748b; text-align:center; line-height:28px; margin-left:8px; font-size:12px;">📦</span>';
                var qtyBadge = item.quantity > 0 ? ' <span class="badge" style="font-size:10px; color:#475569; background:#f1f5f9; padding:2px 6px; border-radius:10px; margin-right:4px;">(' + item.quantity.toLocaleString() + ' ' + i18n.piece_word + ')</span>' : '';
                var priceTag = item.price ? '<br><span class="product-price-tag">' + escHtml(String(item.price)) + '</span>' : '';

                productsHtml += '<tr>' +
                    '<td>' + thumb + '<strong style="vertical-align:middle;">' + escHtml(item.name) + '</strong>' + qtyBadge + priceTag + '</td>' +
                    '<td><strong>' + formatCurrency(item.revenue) + '</strong></td>' +
                    '<td>' +
                    '  <div class="progress-bar-container">' +
                    '    <div class="progress-bar-fill" style="width: ' + percent + '%"></div>' +
                    '  </div>' +
                    '</td>' +
                    '</tr>';
            });
        } else {
            productsHtml = '<tr><td colspan="3">' + renderEmptyState(i18n.no_sales_recorded, i18n.try_another_date_range, '📦') + '</td></tr>';
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
            customersHtml = '<tr><td colspan="3">' + renderEmptyState(i18n.no_customer_data, i18n.try_wider_date_range, '👥') + '</td></tr>';
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
                { name: i18n.sales_revenue_label, data: sales },
                { name: i18n.order_count, data: orders }
            ]);
            salesChart.updateOptions({
                xaxis: { categories: days }
            });
        } else {
            // رسم فارغ عند انعدام البيانات
            salesChart.updateSeries([
                { name: i18n.sales_revenue_label, data: [0] },
                { name: i18n.order_count, data: [0] }
            ]);
            salesChart.updateOptions({
                xaxis: { categories: ['-'] }
            });
        }

        // 4.5. تحديث الرسم البياني لمسار الشراء (Funnel)
        if (data.funnel && data.funnel.length > 0) {
            var funnelCounts = [];
            var funnelLabels = [];

            data.funnel.forEach(function(item) {
                funnelCounts.push(item.count);
                funnelLabels.push(item.label);
            });

            funnelChart.updateSeries([{
                name: i18n.funnel_visits,
                data: funnelCounts
            }]);

            funnelChart.updateOptions({
                xaxis: {
                    categories: funnelLabels
                },
                dataLabels: {
                    formatter: function (val, opt) {
                        var step = data.funnel[opt.dataPointIndex];
                        var text = step.label + ': ' + val.toLocaleString() + ' (' + step.pct_of_total + '%)';
                        if (step.type !== 'view_session' && step.drop_off > 0) {
                            text += ' | ' + i18n.dropoff + ': ' + step.drop_off + '%';
                        }
                        return text;
                    }
                },
                tooltip: {
                    y: {
                        formatter: function(val, opt) {
                            var step = data.funnel[opt.seriesIndex]; // fallback or general
                            // Better yet, just return formatted visitors count
                            return val.toLocaleString() + ' ' + i18n.visit_word;
                        }
                    }
                }
            });
        }

        // 4.6. تحديث بيانات كارت حالة المخزون (Inventory)
        if (data.inventory) {
            $('#inv-total-units').text(data.inventory.total_units.toLocaleString());
            $('#inv-low-stock').text(data.inventory.low_stock.toLocaleString());
            $('#inv-out-of-stock').text(data.inventory.out_of_stock.toLocaleString());
        }

        // 4.7. تحديث مخطط توزيع المحافظات المصرية (Donut Chart)
        if (data.geo && data.geo.length > 0) {
            var geoLabels = [];
            var geoSeries = [];

            // عرض أعلى 4 محافظات ودمج البقية في i18n.other_governorates لمنع تشتت الرسم
            var maxStates = 4;
            var topGeo = data.geo.slice(0, maxStates);
            var otherSalesSum = 0;

            topGeo.forEach(function(item) {
                geoLabels.push(item.name);
                geoSeries.push(parseFloat(item.sales));
            });

            if (data.geo.length > maxStates) {
                var remaining = data.geo.slice(maxStates);
                remaining.forEach(function(item) {
                    otherSalesSum += parseFloat(item.sales);
                });
                if (otherSalesSum > 0) {
                    geoLabels.push(i18n.other_governorates);
                    geoSeries.push(otherSalesSum);
                }
            }

            geoChart.updateSeries(geoSeries);
            geoChart.updateOptions({
                labels: geoLabels,
                tooltip: {
                    y: {
                        formatter: function(val) {
                            return formatCurrency(val);
                        }
                    }
                }
            });
        } else {
            // رسم فارغ في حال انعدام البيانات
            geoChart.updateSeries([]);
            geoChart.updateOptions({
                labels: []
            });
        }

        // 4.8. تحديث ويدجت نسبة إيراد العملاء الراجعين
        if (data.customer_revenue_share) {
            var retPct = data.customer_revenue_share.returning_pct || 0;
            var newPct = data.customer_revenue_share.new_pct || 0;
            
            if (revShareChart) {
                revShareChart.updateSeries([data.customer_revenue_share.returning_revenue, data.customer_revenue_share.new_revenue]);
            }
            
            if (retPct > 0 || newPct > 0) {
                $('#rev-share-slogan').html('⚡ <strong>' + retPct + '%</strong> ' + i18n.revenue_from_loyal_customers);
            } else {
                $('#rev-share-slogan').text(i18n.no_sales_recorded_desc);
            }
        }

        // 4.9. تحديث تحليلات وسائل الدفع ومخاطر الـ COD
        if (data.payment_methods && data.payment_methods.length > 0) {
            var payTitles = [];
            var payRevenues = [];
            var payTableHtml = '';

            data.payment_methods.forEach(function(pm) {
                payTitles.push(pm.title);
                payRevenues.push(pm.revenue);

                var isCod = pm.code.toLowerCase().indexOf('cod') !== -1 || pm.title.indexOf(i18n.cod) !== -1;
                var badgeStyle = pm.refund_rate > 15 ? 'background:#ef4444; color:#fff;' : (pm.refund_rate > 5 ? 'background:#f59e0b; color:#fff;' : 'background:#10b981; color:#fff;');
                var codHighlight = isCod ? ' style="background: #fffbebf5;"' : '';

                payTableHtml += '<tr' + codHighlight + '>' +
                    '<td><strong>' + escHtml(pm.title) + '</strong>' + (isCod ? ' <span class="badge" style="background:#f59e0b; color:#fff; font-size:10px; padding:2px 6px; border-radius:4px; margin-right:4px;">COD</span>' : '') + '</td>' +
                    '<td class="text-center">' + pm.orders.toLocaleString() + '</td>' +
                    '<td><strong>' + formatCurrency(pm.revenue) + '</strong></td>' +
                    '<td><span class="badge" style="font-size:11px; padding:2px 8px; border-radius:12px; ' + badgeStyle + '">' + pm.refund_rate + '% (' + pm.refunds + ')</span></td>' +
                    '</tr>';
            });

            if (paymentChart) {
                paymentChart.updateSeries([{ name: i18n.revenue_label_prefix + ' (' + souqpulseAdminData.currency_symbol + ')', data: payRevenues }]);
                paymentChart.updateOptions({ xaxis: { categories: payTitles } });
            }
            $('#table-payment-methods tbody').html(payTableHtml);
        } else {
            if (paymentChart) {
                paymentChart.updateSeries([{ name: i18n.revenue_label_prefix + ' (' + souqpulseAdminData.currency_symbol + ')', data: [0] }]);
                paymentChart.updateOptions({ xaxis: { categories: ['-'] } });
            }
            $('#table-payment-methods tbody').html('<tr><td colspan="4">' + renderEmptyState(i18n.no_payment_methods, i18n.no_payment_methods_desc, '💳') + '</td></tr>');
        }

        // 4.10. تحديث الخريطة الحرارية لساعات وأيام الشراء (Order Heatmap)
        if (data.order_heatmap && data.order_heatmap.length > 0 && heatmapChart) {
            heatmapChart.updateSeries(data.order_heatmap);
        }

        // 4.11. تحديث بطاقات تقسيم العملاء RFM
        if (data.rfm_segments) {
            var rfmHtml = '';
            $.each(data.rfm_segments, function(key, seg) {
                var safeColor = /^#[0-9a-fA-F]{3,6}$/.test(seg.color) ? seg.color : '#6366f1';
                rfmHtml += '<div class="rfm-card" style="border-top-color:' + safeColor + ';">' +
                    '<span class="rfm-icon">' + (seg.icon || '👤') + '</span>' +
                    '<span class="rfm-label">' + escHtml(seg.label) + '</span>' +
                    '<span class="rfm-count" style="color:' + safeColor + ';">' + (seg.count || 0).toLocaleString() + ' <span class="rfm-unit">' + i18n.customer_word + '</span></span>' +
                    '<span class="rfm-pct">' + (seg.pct || 0) + '% ' + i18n.from_revenue + '</span>' +
                    '</div>';
            });
            $('#rfm-segment-container').html(rfmHtml);
        }

        // 4.12. تحديث جدول المنتجات الأكثر شراءً معاً (Product Affinity)
        if (data.product_affinity && data.product_affinity.length > 0) {
            var affinityHtml = '';
            data.product_affinity.forEach(function(pair) {
                affinityHtml += '<tr>' +
                    '<td><span style="color:#1e293b; font-weight:600;">' + escHtml(pair.product_a_name) + '</span> <span style="color:#94a3b8; font-size:12px; margin:0 4px;">➕</span> <span style="color:#1e293b; font-weight:600;">' + escHtml(pair.product_b_name) + '</span></td>' +
                    '<td style="text-align:center;"><span class="badge" style="background:#f1f5f9; color:#475569; padding:3px 8px; border-radius:12px; font-weight:700;">' + pair.pair_count.toLocaleString() + ' ' + i18n.time_word + '</span></td>' +
                    '</tr>';
            });
            $('#table-product-affinity tbody').html(affinityHtml);
        } else {
            $('#table-product-affinity tbody').html('<tr><td colspan="2">' + renderEmptyState(i18n.no_pairs_sold, i18n.no_pairs_sold_desc, '🛍️') + '</td></tr>');
        }

        // 4.13. تحديث الـ KPI Sparklines الـ 6 بالبيانات اليومية
        if (data.kpi_sparklines) {
            if (kpiSparklines.sales && data.kpi_sparklines.sales) kpiSparklines.sales.updateSeries([{ data: data.kpi_sparklines.sales }]);
            if (kpiSparklines.orders && data.kpi_sparklines.orders) kpiSparklines.orders.updateSeries([{ data: data.kpi_sparklines.orders }]);
            if (kpiSparklines.aov && data.kpi_sparklines.aov) kpiSparklines.aov.updateSeries([{ data: data.kpi_sparklines.aov }]);
            if (kpiSparklines.sessions && data.kpi_sparklines.sessions) kpiSparklines.sessions.updateSeries([{ data: data.kpi_sparklines.sessions }]);
            if (kpiSparklines.bounce && data.kpi_sparklines.bounce) kpiSparklines.bounce.updateSeries([{ data: data.kpi_sparklines.bounce }]);
            if (kpiSparklines.conversion && data.kpi_sparklines.conversion) kpiSparklines.conversion.updateSeries([{ data: data.kpi_sparklines.conversion }]);
        }

        // 4.14. تحديث التوزيع الجغرافي وخريطة مصر ومصفوفة احتفاظ العملاء (Cohort Retention)
        renderGeoCountriesTable(data.geo_countries);
        renderGeoEgyptTable(data.geo);
        renderEgyptMap(data.geo);
        renderCohortRetentionTable(data.cohort_retention);
    }

    /**
     * تحديث نسبة المقارنة للفترة السابقة مع مراعاة المقارنات المعاكسة
     */
    function updateKPIChange(selector, current, previous, lowerIsBetter) {
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

        if (percent > 0.01) {
            arrow = '↑ ';
            statusClass = lowerIsBetter ? 'negative' : 'positive';
        } else if (percent < -0.01) {
            arrow = '↓ ';
            statusClass = lowerIsBetter ? 'positive' : 'negative';
            percent = Math.abs(percent);
        }

        $changeEl.removeClass('positive negative neutral').addClass(statusClass);
        $changeEl.html(arrow + percent.toFixed(1) + '% <span class="change-label">' + i18n.vs_previous_period + '</span>');
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
                { name: i18n.sales_revenue_label, data: [0] },
                { name: i18n.order_count, data: [0] }
            ],
            xaxis: {
                categories: ['-'],
                labels: { style: { colors: '#64748b', fontSize: '12px' } }
            },
            yaxis: [{
                title: { text: i18n.sales_revenue_label, style: { color: '#6366f1' } },
                labels: {
                    style: { colors: '#64748b' },
                    formatter: function(val) { return souqpulseAdminData.currency_symbol + ' ' + Math.round(val).toLocaleString(); }
                }
            }, {
                opposite: true,
                title: { text: i18n.orders_plural, style: { color: '#10b981' } },
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
            series: [{ name: i18n.visits_plural, data: [1500, 800, 480, 360, 310, 220] }],
            xaxis: {
                categories: [i18n.funnel_visit, i18n.funnel_add_to_cart, i18n.funnel_begin_checkout, i18n.shipping_info, i18n.payment_info, i18n.funnel_completed_purchase],
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
            labels: [i18n.cairo, i18n.alexandria, i18n.giza, i18n.qalyubia, i18n.other],
            legend: { position: 'bottom', horizontalAlign: 'center', labels: { colors: '#64748b' } },
            dataLabels: {
                enabled: true,
                formatter: function (val) { return Math.round(val) + "%"; }
            }
        };
        geoChart = new ApexCharts(document.querySelector("#souqpulse-geo-chart"), geoOptions);
        geoChart.render();

        // 5. مخطط مساهمة إيرادات العملاء الراجعين مقابل الجدد
        var revShareOptions = {
            chart: {
                type: 'donut',
                height: 90,
                width: 110,
                fontFamily: 'Tajawal, sans-serif',
                sparkline: { enabled: true },
                rtl: true
            },
            colors: ['#6366f1', '#3b82f6'],
            series: [0, 0],
            labels: [i18n.returning_customers, i18n.rfm_new],
            tooltip: {
                y: {
                    formatter: function(val) { return formatCurrency(val); }
                }
            }
        };
        revShareChart = new ApexCharts(document.querySelector("#souqpulse-rev-share-chart"), revShareOptions);
        revShareChart.render();

        // 6. مخطط تحليل وسائل الدفع
        var paymentOptions = {
            chart: {
                type: 'bar',
                height: 200,
                fontFamily: 'Tajawal, sans-serif',
                toolbar: { show: false },
                rtl: true
            },
            plotOptions: {
                bar: {
                    borderRadius: 4,
                    horizontal: true,
                    barHeight: '50%',
                    distributed: true
                }
            },
            colors: ['#10b981', '#f59e0b', '#3b82f6', '#8b5cf6', '#ef4444'],
            series: [{ name: i18n.revenue_label_prefix + ' (' + souqpulseAdminData.currency_symbol + ')', data: [0] }],
            xaxis: {
                categories: ['-'],
                labels: { style: { colors: '#64748b' } }
            },
            legend: { show: false }
        };
        paymentChart = new ApexCharts(document.querySelector("#souqpulse-payment-chart"), paymentOptions);
        paymentChart.render();

        // 7. الخريطة الحرارية لساعات وأيام الشراء (Peak Order Heatmap)
        var emptyHeatmapSeries = [
            { name: i18n.saturday, data: Array(24).fill(0) },
            { name: i18n.sunday, data: Array(24).fill(0) },
            { name: i18n.monday, data: Array(24).fill(0) },
            { name: i18n.tuesday, data: Array(24).fill(0) },
            { name: i18n.wednesday, data: Array(24).fill(0) },
            { name: i18n.thursday, data: Array(24).fill(0) },
            { name: i18n.friday, data: Array(24).fill(0) }
        ];

        var heatmapOptions = {
            chart: {
                type: 'heatmap',
                height: 280,
                fontFamily: 'Tajawal, sans-serif',
                toolbar: { show: false },
                rtl: true
            },
            colors: ['#6366f1'],
            dataLabels: { enabled: false },
            series: emptyHeatmapSeries,
            xaxis: {
                categories: ['12am', '1am', '2am', '3am', '4am', '5am', '6am', '7am', '8am', '9am', '10am', '11am', '12pm', '1pm', '2pm', '3pm', '4pm', '5pm', '6pm', '7pm', '8pm', '9pm', '10pm', '11pm'],
                labels: { style: { colors: '#64748b', fontSize: '10px' } }
            },
            yaxis: {
                labels: { style: { colors: '#475569', fontWeight: 500 } }
            },
            grid: { padding: { right: 10, left: 10 } }
        };
        heatmapChart = new ApexCharts(document.querySelector("#souqpulse-heatmap-chart"), heatmapOptions);
        heatmapChart.render();

        // 8. مخطط المنحنى الصغير للزوار النشطين الآن (Real-time Sparkline)
        var sparklineOptions = {
            chart: {
                type: 'area',
                height: 80,
                fontFamily: 'Tajawal, sans-serif',
                sparkline: { enabled: true }
            },
            colors: ['#10b981'],
            stroke: { curve: 'smooth', width: 2 },
            fill: {
                type: 'gradient',
                gradient: { shadeIntensity: 1, opacityFrom: 0.15, opacityTo: 0.01 }
            },
            series: [{ name: i18n.active_users, data: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0] }],
            tooltip: { enabled: false }
        };
        var sparklineEl = document.querySelector('#souqpulse-realtime-chart');
        if (sparklineEl) {
            sparklineChart = new ApexCharts(sparklineEl, sparklineOptions);
            sparklineChart.render();
        }

        // 9. تهيئة الـ KPI Sparklines — 6 مخططات مصغرة داخل كروت الأداء
        var kpiSparkConfigs = [
            { key: 'sales',      id: '#sparkline-sales',       color: '#6366f1' },
            { key: 'orders',     id: '#sparkline-orders',      color: '#10b981' },
            { key: 'aov',        id: '#sparkline-aov',         color: '#6366f1' },
            { key: 'sessions',   id: '#sparkline-sessions',    color: '#3b82f6' },
            { key: 'bounce',     id: '#sparkline-bounce',      color: '#ef4444' },
            { key: 'conversion', id: '#sparkline-conversion',  color: '#10b981' }
        ];
        kpiSparkConfigs.forEach(function(cfg) {
            var spEl = document.querySelector(cfg.id);
            if (!spEl) return;
            kpiSparklines[cfg.key] = new ApexCharts(spEl, {
                chart: {
                    type: 'area',
                    height: 50,
                    sparkline: { enabled: true },
                    animations: { enabled: false }
                },
                colors: [cfg.color],
                stroke: { curve: 'smooth', width: 2 },
                fill: {
                    type: 'gradient',
                    gradient: { shadeIntensity: 1, opacityFrom: 0.25, opacityTo: 0.01 }
                },
                series: [{ name: cfg.key, data: [0, 0, 0, 0, 0, 0, 0] }],
                tooltip: { enabled: false }
            });
            kpiSparklines[cfg.key].render();
        });
    }

    // تمت إزالة دالة البيانات التجريبية نظراً لأن كل كروت التقارير أصبحت حقيقية 100%

    /**
     * جلب عدد الزوار النشطين الآن فقط لتحديث كارت الوقت الفعلي دورياً
     */
    function fetchRealtimeCount() {
        var postData = {
            action: 'souqpulse_get_realtime_count',
            security: souqpulseAdminData.nonce
        };

        $.post(souqpulseAdminData.ajax_url, postData, function(response) {
            if (response.success) {
                $('.realtime-value').text(response.data.count);
                updateRealtimeSparkline(response.data.count);
            }
        });
    }

    /**
     * تحديث خط الزوار النشطين لتبدو الواجهة متفاعلة وحية
     */
    function updateRealtimeSparkline(currentCount) {
        if (!sparklineChart || !sparklineChart.w) return;
        var currentData = sparklineChart.w.config.series[0].data;
        currentData.shift(); // إزالة أقدم نقطة
        currentData.push(currentCount); // إضافة القيمة الجديدة
        
        sparklineChart.updateSeries([{
            name: i18n.active_users,
            data: currentData
        }]);
    }

    /**
     * تنسيق مدة الزيارة بالدقائق والثواني
     */
    function formatDuration(seconds) {
        if (!seconds || seconds <= 0) return '0 ' + i18n.second_word;
        var m = Math.floor(seconds / 60);
        var s = seconds % 60;
        if (m > 0) {
            return m + ' ' + i18n.min_abbr + ' ' + s + ' ' + i18n.sec_abbr;
        }
        return s + ' ' + i18n.second_word;
    }

    /**
     * تنسيق العملة المحلية
     */
    function formatCurrency(val) {
        return souqpulseAdminData.currency_symbol + ' ' + parseFloat(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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

    /**
     * بناء عنصر الحالات الفارغة (Empty State)
     */
    function renderEmptyState(title, subtitle, icon) {
        icon = icon || '📊';
        title = title || i18n.no_data_recorded;
        subtitle = subtitle || i18n.try_another_date_range;
        return '<div class="souqpulse-empty-state">' +
               '  <div class="empty-icon">' + icon + '</div>' +
               '  <h5 class="empty-title">' + escHtml(title) + '</h5>' +
               '  <p class="empty-subtitle">' + escHtml(subtitle) + '</p>' +
               '</div>';
    }

    /**
     * رسم وتلوين خريطة مصر التفاعلية SVG بناءً على كثافة المبيعات (Choropleth Scale)
     */
    function renderEgyptMap(geoData) {
        var $container = $('#souqpulse-egypt-map-container');
        if (!$container.length) return;

        // خريطة أسماء وأكواد ومسارات محافظات مصر المعتمدة
        var govPaths = {
            'EG-C':   { name: i18n.cairo, d: 'M460,250 L480,240 L500,260 L480,280 L460,270 Z' },
            'EG-ALX': { name: i18n.alexandria, d: 'M340,160 L380,150 L390,170 L350,180 Z' },
            'EG-GZ':  { name: i18n.giza, d: 'M420,260 L460,270 L430,340 L390,320 Z' },
            'EG-QAL': { name: i18n.qalyubia, d: 'M460,230 L480,220 L490,240 L470,245 Z' },
            'EG-DK':  { name: i18n.dakahlia, d: 'M470,180 L500,170 L510,200 L480,205 Z' },
            'EG-SHR': { name: i18n.sharqia, d: 'M490,200 L530,190 L540,220 L500,230 Z' },
            'EG-GH':  { name: i18n.gharbia, d: 'M440,190 L470,185 L475,215 L445,215 Z' },
            'EG-KB':  { name: i18n.monufia, d: 'M430,215 L465,215 L460,240 L430,235 Z' },
            'EG-BH':  { name: i18n.beheira, d: 'M380,170 L430,185 L430,225 L370,210 Z' },
            'EG-KSH': { name: i18n.kafr_el_sheikh, d: 'M430,160 L475,160 L470,185 L430,185 Z' },
            'EG-DA':  { name: i18n.damietta, d: 'M500,165 L525,165 L520,185 L495,185 Z' },
            'EG-PTS': { name: i18n.port_said, d: 'M530,175 L560,175 L555,195 L530,195 Z' },
            'EG-IS':  { name: i18n.ismailia, d: 'M530,200 L570,200 L565,230 L530,225 Z' },
            'EG-SUZ': { name: i18n.suez, d: 'M530,235 L580,240 L570,270 L525,260 Z' },
            'EG-NS':  { name: i18n.north_sinai, d: 'M580,180 L670,170 L650,230 L575,210 Z' },
            'EG-SS':  { name: i18n.south_sinai, d: 'M580,240 L650,235 L620,330 L575,290 Z' },
            'EG-FYM': { name: i18n.faiyum, d: 'M400,310 L440,305 L435,340 L395,335 Z' },
            'EG-BNS': { name: i18n.beni_suef, d: 'M435,340 L480,335 L475,375 L425,370 Z' },
            'EG-MN':  { name: i18n.minya, d: 'M425,375 L485,375 L480,440 L415,435 Z' },
            'EG-AST': { name: i18n.asyut, d: 'M420,440 L495,440 L490,500 L410,495 Z' },
            'EG-SHG': { name: i18n.sohag, d: 'M425,505 L510,505 L505,560 L420,555 Z' },
            'EG-QNA': { name: i18n.qena, d: 'M490,565 L570,555 L560,620 L480,615 Z' },
            'EG-LX':  { name: i18n.luxor, d: 'M490,620 L550,620 L545,660 L485,655 Z' },
            'EG-ASW': { name: i18n.aswan, d: 'M480,660 L590,660 L580,770 L465,770 Z' },
            'EG-WAD': { name: i18n.new_valley, d: 'M150,330 L410,330 L460,770 L150,770 Z' },
            'EG-MS':  { name: i18n.matrouh, d: 'M150,140 L360,140 L390,320 L150,320 Z' },
            'EG-BA':  { name: i18n.red_sea, d: 'M550,270 L600,270 L650,770 L570,770 Z' }
        };

        var maxSales = 0;
        var geoMap = {};
        if (geoData && geoData.length) {
            geoData.forEach(function(item) {
                if (item.code) {
                    geoMap[item.code] = item;
                    if (item.sales > maxSales) maxSales = item.sales;
                }
            });
        }

        var svgHtml = '<svg class="souqpulse-egypt-svg" viewBox="100 120 600 670" xmlns="http://www.w3.org/2000/svg">';
        
        $.each(govPaths, function(code, info) {
            var item = geoMap[code];
            var sales = item ? item.sales : 0;
            var orders = item ? item.orders : 0;
            var ratio = maxSales > 0 ? (sales / maxSales) : 0;

            var fillColor = '#cbd5e1';
            if (ratio > 0.75) fillColor = '#4338ca';
            else if (ratio > 0.4) fillColor = '#6366f1';
            else if (ratio > 0.15) fillColor = '#818cf8';
            else if (ratio > 0) fillColor = '#c7d2fe';

            var tooltipTitle = escHtml(info.name) + ': ' + formatCurrency(sales) + ' (' + orders + ' ' + i18n.order_word + ')';

            svgHtml += '<path d="' + info.d + '" class="souqpulse-gov-path" fill="' + fillColor + '" data-code="' + code + '" data-name="' + escHtml(info.name) + '">' +
                       '<title>' + tooltipTitle + '</title>' +
                       '</path>';
        });

        svgHtml += '</svg>';
        $container.html(svgHtml);
    }

    /**
     * رسم جدول التوزيع الجغرافي للدول العالمية مع أعلام الدول وأشرطة التقدم
     */
    function renderGeoCountriesTable(countriesData) {
        var $tbody = $('#table-geo-countries tbody');
        if (!$tbody.length) return;

        if (!countriesData || countriesData.length === 0) {
            $tbody.html('<tr><td colspan="3">' + renderEmptyState(i18n.no_geo_data, i18n.no_geo_data_desc, '🌐') + '</td></tr>');
            return;
        }

        var html = '';
        countriesData.forEach(function(item) {
            html += '<tr>' +
                '<td><span style="font-size:16px; margin-left:6px;">' + item.flag + '</span> <strong>' + escHtml(item.name) + '</strong></td>' +
                '<td style="text-align:center;"><span class="badge" style="background:#f1f5f9; color:#475569; padding:2px 8px; border-radius:12px; font-weight:700;">' + item.orders.toLocaleString() + '</span></td>' +
                '<td>' +
                    '<div class="geo-progress-wrapper">' +
                        '<div style="display:flex; justify-content:space-between; font-size:12px;">' +
                            '<strong style="color:#1e293b;">' + formatCurrency(item.sales) + '</strong>' +
                            '<span style="color:#64748b;">' + item.percentage + '%</span>' +
                        '</div>' +
                        '<div class="geo-progress-bar-bg">' +
                            '<div class="geo-progress-bar-fill" style="width:' + item.percentage + '%;"></div>' +
                        '</div>' +
                    '</div>' +
                '</td>' +
            '</tr>';
        });

        $tbody.html(html);
    }

    /**
     * رسم جدول التوزيع الجغرافي لمحافظات مصر
     */
    function renderGeoEgyptTable(geoData) {
        var $tbody = $('#table-geo-egypt tbody');
        if (!$tbody.length) return;

        if (!geoData || geoData.length === 0) {
            $tbody.html('<tr><td colspan="3">' + renderEmptyState(i18n.no_egypt_sales, i18n.no_egypt_sales_desc, '🇪🇬') + '</td></tr>');
            return;
        }

        var html = '';
        geoData.forEach(function(item) {
            html += '<tr>' +
                '<td><strong>' + escHtml(item.name) + '</strong></td>' +
                '<td style="text-align:center;">' + item.orders.toLocaleString() + '</td>' +
                '<td><strong>' + formatCurrency(item.sales) + '</strong></td>' +
            '</tr>';
        });

        $tbody.html(html);
    }

    /**
     * رسم جدول مصفوفة احتفاظ العملاء شهرياً مع التلوين الحراري (Cohort Retention Heatmap)
     */
    function renderCohortRetentionTable(cohortData) {
        var $tbody = $('#table-cohort-retention tbody');
        if (!$tbody.length) return;

        if (!cohortData || !cohortData.cohorts || cohortData.cohorts.length === 0) {
            $tbody.html('<tr><td colspan="8">' + renderEmptyState(i18n.no_cohort_data, i18n.no_cohort_data_desc, '📅') + '</td></tr>');
            return;
        }

        var html = '';
        cohortData.cohorts.forEach(function(row) {
            html += '<tr>' +
                '<td style="text-align: right; font-weight: 700; color: #1e293b;">' + escHtml(row.cohort_name) + '</td>' +
                '<td><strong style="color: #6366f1;">' + row.total_size.toLocaleString() + '</strong></td>';

            for (var i = 0; i < 6; i++) {
                var pct = row.retention[i];
                if (pct === null || pct === undefined) {
                    html += '<td style="color: #cbd5e1;">-</td>';
                } else {
                    var bg = 'rgba(99, 102, 241, ' + Math.max(0.12, (pct / 100)) + ')';
                    var color = pct > 40 ? '#1e1b4b' : '#334155';
                    html += '<td><div class="cohort-cell" style="background: ' + bg + '; color: ' + color + ';">' + pct + '%</div></td>';
                }
            }
            html += '</tr>';
        });

        $tbody.html(html);
    }

})(jQuery);
