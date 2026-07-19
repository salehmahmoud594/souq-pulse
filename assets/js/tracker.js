/**
 * سكريبت تتبع أحداث الفرونت إند - نبض السوق
 * يقوم بمراقبة تفاعل المستخدم في صفحة إتمام الطلب (الـ checkout) وإرسال الأحداث عبر AJAX
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // إرسال نبضة نشاط أولية فور التحميل
        sendHeartbeat();

        // إرسال نبضة نشاط دورية كل 25 ثانية
        setInterval(sendHeartbeat, 25000);

        // التحقق من أننا في صفحة إتمام الطلب لتنشيط التتبع الفرعي
        if ( $('form.checkout').length > 0 ) {
            bindCheckoutEvents();
        }
    });

    /**
     * إرسال نبضة نشاط لتسجيل أن المستخدم نشط حالياً
     */
    function sendHeartbeat() {
        $.post(souqpulseTrackerData.ajax_url, {
            action: 'souqpulse_heartbeat',
            security: souqpulseTrackerData.nonce
        });
    }

    /**
     * ربط أحداث تغيير الشحن وطرق الدفع في صفحة Checkout
     */
    function bindCheckoutEvents() {
        // 1. تتبع اختيار طريقة الشحن
        $('form.checkout').on('change', 'input[name^="shipping_method"]', function() {
            trackClientEvent('add_shipping_info');
        });

        // 2. تتبع اختيار طريقة الدفع
        $('form.checkout').on('change', 'input[name="payment_method"]', function() {
            trackClientEvent('add_payment_info');
        });

        // التتبع عند الكتابة أو الانتقال بين الحقول (في حال تم تفعيلها كبديل)
        // نقوم بإرسال الحدث الأول فوراً عند توفر عناصر الشحن/الدفع المحملة افتراضياً
        setTimeout(function() {
            if ( $('input[name^="shipping_method"]:checked').length > 0 ) {
                trackClientEvent('add_shipping_info');
            }
            if ( $('input[name="payment_method"]:checked').length > 0 ) {
                trackClientEvent('add_payment_info');
            }
        }, 1000);
    }

    /**
     * إرسال طلب AJAX لتسجيل الحدث في السيرفر
     */
    function trackClientEvent(eventType) {
        // تفادي إرسال نفس الحدث أكثر من مرة في نفس تحميل الصفحة بالمتصفح
        if ( window['souqpulse_tracked_' + eventType] ) {
            return;
        }
        window['souqpulse_tracked_' + eventType] = true;

        var data = {
            action: 'souqpulse_track_event',
            security: souqpulseTrackerData.nonce,
            event_type: eventType
        };

        $.post(souqpulseTrackerData.ajax_url, data, function(response) {
            if (response.success) {
                console.log('SouqPulse Tracked: ' + eventType);
            }
        });
    }

})(jQuery);
