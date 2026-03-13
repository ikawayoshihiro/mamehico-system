<?php
// ヨシノ管理 ① メニュー登録・共通関数

if (!defined('YOSHINO_EID')) define('YOSHINO_EID', 'yoshino');

add_action('admin_menu', function() {
    add_menu_page('ヨシノ管理','ヨシノ管理','manage_options','yoshino-calendar','yoshino_calendar_page','dashicons-tickets-alt',32);
    add_submenu_page('yoshino-calendar','公演日カレンダー','公演日カレンダー','manage_options','yoshino-calendar','yoshino_calendar_page');
    add_submenu_page('yoshino-calendar','メールテンプレート','メールテンプレート','manage_options','yoshino-mail','yoshino_mail_page');
    add_submenu_page('yoshino-calendar','予約一覧','予約一覧','manage_options','yoshino-reservations','yoshino_reservations_page');
    add_submenu_page('yoshino-calendar','基本設定','基本設定','manage_options','yoshino-settings','yoshino_settings_page');
});

if (!function_exists('yoshino_firebase_config')) {
    function yoshino_firebase_config() {
        return json_encode(array(
            'apiKey'            => 'AIzaSyDnDy4ipNbMbzXd2yurCgVwKjyEkp3FCZE',
            'authDomain'        => 'mamehico-schedule.firebaseapp.com',
            'projectId'         => 'mamehico-schedule',
            'storageBucket'     => 'mamehico-schedule.firebasestorage.app',
            'messagingSenderId' => '992467894050',
            'appId'             => '1:992467894050:web:88b676e753523dac572f7b',
        ));
    }
}

if (!function_exists('yoshino_common_css')) {
    function yoshino_common_css() {
        echo '<style>
.ye-wrap{max-width:960px;margin:32px 0;font-family:-apple-system,BlinkMacSystemFont,"Helvetica Neue",sans-serif}
.ye-wrap h1{font-size:20px;font-weight:500;color:#2E303E;border:none;margin-bottom:8px}
.ye-btn{border:1px solid #d8d5cf;border-radius:3px;padding:6px 14px;font-size:12px;cursor:pointer;font-family:inherit;background:#fff;color:#2E303E;text-decoration:none;display:inline-block}
.ye-btn:hover{background:#2E303E;color:#fff;border-color:#2E303E}
.ye-btn.primary{background:#2E303E;color:#fff;border-color:#2E303E}
.ye-btn.primary:hover{opacity:.75}
.ye-field{margin-bottom:16px}
.ye-field label{display:block;font-size:11px;letter-spacing:.1em;color:#999;margin-bottom:6px;font-weight:500}
.ye-field input,.ye-field textarea{width:100%;border:1px solid #ddd;border-radius:3px;padding:9px 12px;font-size:13px;font-family:inherit;color:#2E303E;box-sizing:border-box}
.ye-field input:focus,.ye-field textarea:focus{border-color:#2E303E;outline:none}
.ye-grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
</style>';
    }
}
