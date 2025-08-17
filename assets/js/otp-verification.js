jQuery(document).ready(function ($) {
    let timerInterval;

    // ارسال شماره
    $('#sendCodeBtn').click(function () {
        var phone = $('#phone').val();

        $.post(otpAjax.ajaxurl, {
            action: 'otp_generate_code',
            phone: phone,
            nonce: otpAjax.nonce
        }, function (response) {
            if (response.success) {
                $('#showPhone').text(phone);
                $('#stepPhone').hide();
                $('#stepCode').show();
                $('#modalPhone').val(phone);
                $('.otp-field').first().focus();
                startTimer();
            } else {
                alert(response.data.message);
            }
        }, 'json');
    });

    // مدیریت فیلدهای OTP
    $(document).on('input', '.otp-field', function () {
        if (this.value.length === 1) {
            $(this).next('.otp-field').focus();
        }
    });



    // تأیید کد
  $('#verifyBtn').click(function() {
        let code = '';
        $('.otp-field').each(function() {
            code += $(this).val();
        });
        var phone = $('#showPhone').text();

        $.post(otpAjax.ajaxurl, {
            action: 'otp_verify_code',
            phone: phone,
            code: code,
            nonce: otpAjax.nonce
        }, function(response) {
            if (response.success) {
                $('#otpError').hide(); // پنهان کردن خطا اگر قبلاً بوده
                $('#otpModal').modal('hide');
                window.location.href = otpAjax.editAddressUrl;
            } else {
                // 👇 نمایش پیام خطا
                $('#otpError').text('❌ ' + response.data.message).show();

                // خالی کردن همه خونه‌ها
                $('.otp-field').val('');
                $('.otp-field').first().focus();
            }
        }, 'json');
    });

    // تایمر ارسال مجدد
    function startTimer() {
        let time = 120;
        $('#resendCode').prop('disabled', true);
        timerInterval = setInterval(function () {
            $('#timer').text(time);
            if (time <= 0) {
                clearInterval(timerInterval);
                $('#resendCode').prop('disabled', false).text('ارسال مجدد');
            }
            time--;
        }, 1000);
    }

    // ارسال مجدد
    $('#resendCode').click(function () {
        $('#sendCodeBtn').click();
    });


    const otpInputs = document.querySelectorAll(".otp-field");

    otpInputs.forEach((input, index) => {
        // حرکت به بعدی
        input.addEventListener("input", function (e) {
            if (this.value.length === 1) {
                if (index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus();
                } else {
                    // 👇 وقتی آخرین خونه پر شد → اتومات verify
                    $('#verifyBtn').click();
                }
            }
        });

        // برگشت با بک‌اسپیس
        input.addEventListener("keydown", function (e) {
            if (e.key === "Backspace" && this.value === "" && index > 0) {
                otpInputs[index - 1].focus();
            }
        });
    });
});
