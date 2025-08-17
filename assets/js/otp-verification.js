jQuery(document).ready(function($) {
    $('#phoneForm').submit(function(e) {
        e.preventDefault();
        var phone = $('#phone').val();

        $.ajax({
            url: otpAjax.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'otp_generate_code',
                phone: phone,
                nonce: otpAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    $('#modalPhone').val(phone);
                    $('#codeModal').modal('show');
                } else {
                    alert(response.data.message);
                }
            },
            error: function(xhr, status, error) {
                console.error(xhr.responseText, status, error);
                alert(' دو خطا در ارسال درخواست دوباره تلاش کنید.');
            }
        });
    });

    // ارسال کد برای تأیید و لاگین
    $('#codeForm').submit(function(e) {
        e.preventDefault();
        var code = $('#code').val();
        var phone = $('#modalPhone').val();

        $.ajax({
            url: otpAjax.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'otp_verify_code',
                phone: phone,
                code: code,
                nonce: otpAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    $('#codeModal').modal('hide');
                    window.location.href = otpAjax.editAddressUrl;
                } else {
                    alert(response.data.message);
                }
            },
            error: function(xhr, status, error) {
                console.error(xhr.responseText, status, error);
                alert(' دو خطا در ارسال درخواست دوباره تلاش کنید.');
            }
        });
    });

});
