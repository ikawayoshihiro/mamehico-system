<?php
// 銀座ランチ管理 ① メニュー登録・共通スタイル
// v3.0.2 - 2026-03-10

add_action('admin_menu', function() {
    add_menu_page(
        '銀座ランチ 管理',
        '銀座営業日',
        'manage_options',
        'mamehico-ginza-calendar',
        'mamehico_ginza_render_calendar',
        'dashicons-calendar-alt',
        30
    );
    add_submenu_page('mamehico-ginza-calendar', 'メールテンプレート', 'メールテンプレート', 'manage_options', 'mamehico-ginza-mail', 'mamehico_ginza_render_mail');
    add_submenu_page('mamehico-ginza-calendar', '予約一覧', '予約一覧', 'manage_options', 'mamehico-ginza-reservations', 'mamehico_ginza_render_reservations');
});

if (!function_exists('mamehico_ginza_admin_styles')):
function mamehico_ginza_admin_styles() {
    return '
<style>
.mm-admin-wrap { margin: 32px 0; font-family: -apple-system, BlinkMacSystemFont, "Helvetica Neue", sans-serif; }
.mm-admin-wrap h1 { font-size: 20px; font-weight: 500; color: #2E303E; border: none; margin-bottom: 6px; padding-bottom: 0; }
.mm-admin-wrap p.mm-desc { color: #888; font-size: 13px; margin-bottom: 28px; }
.mm-section { border: 1px solid #e5e3de; border-radius: 6px; overflow: hidden; margin-bottom: 32px; }
.mm-section-header { padding: 14px 20px; background: #2E303E; color: #fff; font-size: 13px; letter-spacing: .1em; font-weight: 500; }
.mm-section-header.light { background: #f8f8f7; color: #2E303E; border-bottom: 1px solid #e5e3de; }
.mm-field { padding: 20px 20px 0; }
.mm-field:last-child { padding-bottom: 20px; }
.mm-label { display: block; font-size: 11px; letter-spacing: .12em; color: #999; margin-bottom: 8px; font-weight: 500; text-transform: uppercase; }
.mm-input { width: 100%; border: 1px solid #ddd; border-radius: 3px; padding: 10px 14px; font-size: 14px; color: #2E303E; font-family: inherit; background: #fff; box-sizing: border-box; }
.mm-input:focus { border-color: #2E303E; outline: none; }
.mm-textarea { width: 100%; border: 1px solid #ddd; border-radius: 3px; padding: 12px 14px; font-size: 13px; color: #2E303E; font-family: "SF Mono","Fira Code",monospace; background: #fafaf9; line-height: 1.7; resize: vertical; box-sizing: border-box; }
.mm-textarea:focus { border-color: #2E303E; outline: none; background: #fff; }
.mm-save-btn { background: #2E303E; color: #fff; border: none; border-radius: 4px; padding: 12px 36px; font-size: 14px; cursor: pointer; font-family: inherit; font-weight: 500; transition: opacity .15s; }
.mm-save-btn:hover { opacity: .75; }
.mm-saved { display: inline-flex; align-items: center; gap: 6px; background: #f0faf4; border: 1px solid #a8d5b5; color: #2d7a4f; padding: 8px 16px; border-radius: 4px; font-size: 13px; margin-bottom: 20px; }
.mm-vars { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 28px; padding: 14px 18px; background: #f8f8f7; border: 1px solid #e5e3de; border-radius: 4px; }
.mm-vars span { font-size: 11px; color: #666; margin-right: 4px; }
.mm-var { font-size: 12px; background: #2E303E; color: #fff; padding: 3px 9px; border-radius: 3px; font-family: monospace; cursor: default; }
</style>';
}
endif;
