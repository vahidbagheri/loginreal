jQuery(document).ready(function ($) {
    let timerInterval;

    // متن‌ها از دیکشنری
    const t = otpAjax.i18n || {};
    const isRTL = !!otpAjax.is_rtl;

    // جهت تیتر/پاراگراف‌ها طبق زبان، ولی OTP همیشه LTR
    if (isRTL) {
        $('#otpModal .modal-content').attr('dir', 'rtl');
    } else {
        $('#otpModal .modal-content').attr('dir', 'ltr');
    }

    // ارسال شماره
    $('#sendCodeBtn').on('click', function () {
        const phone = $('#phone').val().trim();

        $.post({
            url: otpAjax.ajaxurl,
            data: {
                action: 'otp_generate_code',
                phone: phone,
                nonce: otpAjax.nonce
            },
            beforeSend: function () {
                // 👇 نمایش لودینگ
                $('#stepPhone').hide();
                $('#otpLoader').show();
            },
            success: function (response) {
                $('#otpLoader').hide(); // 👇 مخفی کردن لودینگ
                if (response.success) {
                    $('#showPhone').text(phone);
                    $('#stepCode').show();
                    setTimeout(() => $('.otp-field').first().focus(), 50);
                    startTimer();
                } else {
                    $('#stepPhone').show(); // بازگشت به مرحله شماره در صورت خطا
                    alert(response.data?.message || t.saveError || 'خطا رخ داد');
                }
            },
            error: function () {
                $('#otpLoader').hide();
                $('#stepPhone').show(); // بازگشت به مرحله شماره در صورت خطا
                alert('خطا در ارتباط با سرور، دوباره تلاش کنید.');
            },
            dataType: 'json'
        });
    });

    // ویرایش شماره تلفن
    $('#editPhone').on('click', function () {
        const currentPhone = $('#showPhone').text().trim();
        $('#phone').val(currentPhone); // پر کردن فیلد با شماره فعلی
        $('#stepCode').hide();
        $('#stepPhone').show();
        $('.otp-field').val(''); // پاک کردن فیلدهای OTP
        $('#otpError').hide(); // مخفی کردن پیام خطا
        clearInterval(timerInterval); // توقف تایمر
        $('#resendCode').prop('disabled', true).text(t.resend || 'Resend'); // ریست دکمه ارسال مجدد
        $('#phone').focus(); // فوکوس روی فیلد شماره
    });

    // حرکت بین فیلدها + ارسال خودکار بعد از رقم آخر
    const otpInputs = document.querySelectorAll('.otp-field');
    otpInputs.forEach((input, index) => {
        input.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 1); // فقط رقم
            if (this.value.length === 1) {
                if (index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus();
                } else {
                    $('#verifyBtn').trigger('click');
                }
            }
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace' && this.value === '' && index > 0) {
                otpInputs[index - 1].focus();
            }
            if (e.key.toLowerCase() === 'enter') {
                $('#verifyBtn').trigger('click');
            }
        });
        // انتخاب سریع هنگام فوکوس
        input.addEventListener('focus', function () { this.select(); });
    });

    // دکمه تأیید
    $('#verifyBtn').on('click', function () {
        let code = '';
        $('.otp-field').each(function () { code += $(this).val(); });
        const phone = $('#showPhone').text().trim();

        $.post(otpAjax.ajaxurl, {
            action: 'otp_verify_code',
            phone: phone,
            code: code,
            nonce: otpAjax.nonce
        }, function (response) {
            if (response.success) {
                $('#otpError').hide();
                $('#otpModal').modal('hide');
                window.location.href = otpAjax.editAddressUrl;
            } else {
                $('#otpError').text('❌ ' + (response.data?.message || t.invalidCode || 'Invalid code')).show();
                $('.otp-field').val('');
                $('.otp-field').first().focus();
            }
        }, 'json');
    });

    // تایمر ارسال مجدد
    function startTimer() {
        let time = 120;
        $('#resendCode').prop('disabled', true);
        clearInterval(timerInterval);
        timerInterval = setInterval(function () {
            $('#timer').text(time);
            if (time <= 0) {
                clearInterval(timerInterval);
                $('#resendCode').prop('disabled', false).text(t.resend || 'Resend');
            }
            time--;
        }, 1000);
    }

    // ارسال مجدد
    $('#resendCode').on('click', function () {
        $('#sendCodeBtn').trigger('click');
    });
});