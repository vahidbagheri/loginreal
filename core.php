<?php
/*
Plugin Name: OTP Verification for WooCommerce
Plugin URI: https://example.com/otp-verification
Description: A simple OTP verification system with modal and AJAX for WordPress, integrated with WooCommerce. Auto creates user if not exists, logs in, and redirects to edit billing address after login. Adds custom billing fields.
Version: 1.2
Author: vahid bagheri
Author URI: https://example.com
License: GPL2
*/

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

// چک کردن وجود WooCommerce
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return; // اگر WooCommerce فعال نبود، پلاگین کار نکنه
}


// تعریف ثابت‌ها
define('OTP_VERIFICATION_VERSION', '1.2');
define('OTP_VERIFICATION_PATH', plugin_dir_path(__FILE__));
define('OTP_VERIFICATION_URL', plugin_dir_url(__FILE__));


function otp_verification_install() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'verification_codes';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        phone_number varchar(15) NOT NULL,
        verification_code varchar(6) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        expires_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY phone_number (phone_number)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'otp_verification_install');


function otp_verification_uninstall() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'verification_codes';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}
register_uninstall_hook(__FILE__, 'otp_verification_uninstall');


function otp_verification_enqueue_assets() {
   
    wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css');
    wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js', array('jquery'), null, true);


    wp_enqueue_script('jquery');


    wp_enqueue_script('otp-verification-js', OTP_VERIFICATION_URL . 'assets/js/otp-verification.js', array('jquery'), OTP_VERIFICATION_VERSION, true);


    wp_localize_script('otp-verification-js', 'otpAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('otp_verification_nonce'),
        'editAddressUrl' => wc_get_account_endpoint_url('edit-address') 
    ));
}
add_action('wp_enqueue_scripts', 'otp_verification_enqueue_assets');




function otp_verification_shortcode() {
    ob_start(); ?>
    
    <!-- دکمه ورود/ثبت‌نام -->
    <div class="text-center my-4">
        <button type="button" class="btn btn-primary btn-lg rounded-pill shadow" data-bs-toggle="modal" data-bs-target="#otpModal">
            ورود / ثبت‌نام
        </button>
    </div>

    <!-- مودال OTP -->
    <div class="modal fade" id="otpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 shadow-lg">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold">📱 ورود یا ثبت‌نام</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                </div>
                <div class="modal-body">

                    <!-- مرحله ۱: دریافت شماره -->
                    <div id="stepPhone">
                        <p class="text-muted">شماره موبایل خود را وارد کنید:</p>
                        <input type="text" id="phone" class="form-control rounded-pill mb-3" placeholder="مثال: 09123456789">
                        <button id="sendCodeBtn" class="btn btn-primary w-100 rounded-pill">ارسال کد تأیید</button>
                    </div>

                    <!-- مرحله ۲: وارد کردن کد -->
                    <div id="stepCode" style="display:none;">
                        <p class="text-muted">کدی که به <span id="showPhone"></span> ارسال شد را وارد کنید:</p>
                        <div class="d-flex justify-content-center gap-2 mb-3 otp-inputs">
                            <?php for ($i=1; $i<=6; $i++): ?>
                                <input type="text" maxlength="1" class="form-control text-center fs-4 otp-field" style="width:45px;">
                            <?php endfor; ?>
                        </div>
                        <!-- پیام خطا -->
<div id="otpError" class="text-danger text-center mb-2" style="display:none;"></div>
                        <button id="verifyBtn" class="btn btn-success w-100 rounded-pill">تأیید و ورود</button>

                        <div class="mt-3 text-center">
                            <small class="text-muted">کد نرسیده؟</small><br>
                            <button type="button" id="resendCode" class="btn btn-link p-0" disabled>ارسال مجدد (<span id="timer">120</span> ثانیه)</button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <style>
        .otp-inputs .otp-field {
            border-radius: 12px;
            height: 55px;
            font-weight: bold;
        }
        .otp-inputs .otp-field:focus {
            border: 2px solid #0d6efd;
            box-shadow: 0 0 6px rgba(13,110,253,0.4);
        }
        .otp-field {
    width: 50px;
    height: 60px;
    font-size: 24px;
    text-align: center;
    direction: ltr;   /* 👈 اجباری چپ به راست */
    unicode-bidi: plaintext; /* 👈 جلوگيری از قاطی شدن با RTL */
    border: 2px solid #ddd;
    border-radius: 8px;
    font-weight: bold;
    transition: all 0.2s ease;
}
.otp-field:focus {
    border-color: #28a745;
    box-shadow: 0 0 6px rgba(40,167,69,0.4);
    outline: none;
}
.otp-inputs {
    direction: ltr;   /* 👈 همیشه چپ به راست */
    display: flex;
    justify-content: center;
    gap: 10px;
}

.otp-field {
    width: 50px;
    height: 60px;
    font-size: 24px;
    text-align: center;
    border: 2px solid #ddd;
    border-radius: 8px;
    font-weight: bold;
    transition: all 0.2s ease;
}
.otp-field:focus {
    border-color: #28a745;
    box-shadow: 0 0 6px rgba(40,167,69,0.4);
    outline: none;
}

    </style>

    <?php return ob_get_clean();
}
add_shortcode('otp_verification', 'otp_verification_shortcode');




function otp_generate_code() {
    check_ajax_referer('otp_verification_nonce', 'nonce');

    global $wpdb;
    $table_name = $wpdb->prefix . 'verification_codes';

    $phone = sanitize_text_field($_POST['phone']);

    $code = sprintf("%06d", rand(0, 999999));

    $expires_at = date('Y-m-d H:i:s', strtotime('+2 minutes'));

    $wpdb->delete($table_name, array('phone_number' => $phone));

    $inserted = $wpdb->insert($table_name, array(
        'phone_number' => $phone,
        'verification_code' => $code,
        'expires_at' => $expires_at
    ));
    
    if ($inserted) {
        $message = "کد تایید شما: $code";
        voiceweb_send_sms($message, $phone);

        wp_send_json_success(array('message' => 'کد تأیید ارسال شد!'));
    } else {
        wp_send_json_error(array('message' => 'خطا در ذخیره کد.'));
    }
}
add_action('wp_ajax_otp_generate_code', 'otp_generate_code');
add_action('wp_ajax_nopriv_otp_generate_code', 'otp_generate_code');

function otp_verify_code() {
    check_ajax_referer('otp_verification_nonce', 'nonce');

    global $wpdb;
    $table_name = $wpdb->prefix . 'verification_codes';

    $phone = sanitize_text_field($_POST['phone']);
    $code = sanitize_text_field($_POST['code']);
    $current_time = date('Y-m-d H:i:s');

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE phone_number = %s AND verification_code = %s AND expires_at > %s",
        $phone, $code, $current_time
    ));

    if ($row) {
        $users = get_users(array(
            'search' => 'user_' . $phone,
            'search_columns' => array('user_login'),
            'number' => 1
        ));

      if (!empty($users)) {
            $user = $users[0];
            wp_clear_auth_cookie();
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID, true);
            do_action('wp_login', $user->user_login, $user);

      } else {
            $username = 'user_' . preg_replace('/[^0-9]/', '', $phone);
            $email = $username . '@example.com';
            $password = wp_generate_password();

            $user_id = wp_insert_user(array(
                'user_login' => $username,
                'user_pass' => $password,
                'user_email' => $email,
                'role' => 'customer'
            ));

            if (!is_wp_error($user_id)) {
               

                $user = get_user_by('id', $user_id);
                wp_clear_auth_cookie();
                wp_set_current_user($user->ID);
                add_filter('auth_cookie_expiration',function ($expiration){
                    return $expiration = 60*60*72;
                },1);
                wp_set_auth_cookie($user->ID, true);
                do_action('wp_login', $user->user_login, $user);
            } else {
                wp_send_json_error(array('message' => 'خطا در ساخت کاربر: ' . $user_id->get_error_message()));
                return;
            }
        }

        $wpdb->delete($table_name, array('phone_number' => $phone));

        wp_send_json_success(array('message' => 'لاگین موفق!'));
    } else {
        wp_send_json_error(array('message' => 'کد نامعتبر یا منقضی شده.'));
    }
}
add_action('wp_ajax_otp_verify_code', 'otp_verify_code');
add_action('wp_ajax_nopriv_otp_verify_code', 'otp_verify_code');


function voiceweb_send_sms($message, $mobile_number, $for_customer = false) {
    // endpoint
    $url = 'https://mehrafraz.com:443/fullrest/api/Send';

    // data
    $data = [
        'Smsbody'   => $message,
        'Mobiles'   => [$mobile_number],
        'Id'        => '0',
        'UserName'  => 'citynet',
        'Password'  => 'Citynet#8847',
        'DomainName'=> 'agency'
    ];

    // JSON encode
    $jsonData = json_encode($data);

    // headers
    $headers = [
        'Content-Type: application/json',
    ];

    // curl session
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}



// function check_wp_login_cookies() {
//     $has_logged_in = false;
//     $has_auth = false;

//     foreach ($_COOKIE as $name => $value) {
//         if (strpos($name, 'wordpress_logged_in_') === 0) {
//             $has_logged_in = true;
//         }
//         if (strpos($name, 'wordpress_sec_') === 0 || strpos($name, 'wordpress_') === 0) {
//             $has_auth = true;
//         }
//     }

//     // اگر یکی بود و اون یکی نبود ⇒ حالت ناقص
//     if ( ($has_logged_in && !$has_auth) || (!$has_logged_in && $has_auth) ) {
//         wp_clear_auth_cookie(); // همه کوکی‌های سشن لاگین پاک میشه
//         wp_safe_redirect(home_url()); // ریدایرکت به صفحه اصلی
//         exit;
//     }
// }
// add_action('init', 'check_wp_login_cookies');


