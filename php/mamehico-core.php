<?php
/**
 * MAMEHICO 予約システム コアスニペット v2.2.19
 * 銀座ランチ・ヨシノ系 共通
 *
 * 更新履歴
 * v2.2.19 - 2026-03-12 food_box_selectionsをsend-confirmation/create-checkout/yoshino完了ページに追加（{food_box_summary}対応）
 * v2.2.18 - 2026-03-12 デフォルトメールテンプレートを正式版に更新（venue/coin_summary/公演名追加）
 * v2.2.17 - 2026-03-12 yoshinoテンプレートをカテゴリー共通化・空値行削除
 * v2.2.16 - 2026-03-12 enqueueバージョンを2.2.16に更新
 * v2.2.13 - 2026-03-11 yoshino成功ページのvenue/nameをsessionStorageで受け渡し（Xserverブロック対策）
 * v2.2.12 - 2026-03-11 venues配列・文字列両対応（JSのみ）
 * v2.2.11 - 2026-03-11 yoshino成功ページに会場・氏名を追加
 * v2.2.8  - 2026-03-11 $venue追加・{venue}変数追加
 * v2.2.5  - 2026-03-09 enqueueバージョン修正・yoshino完了ページslotDocId修正
 * v2.2.1  - 2026-03-09 yoshino対応、銀座・ヨシノ共通エンジン化
 * v2.0.0  - 銀座ランチ専用版（旧）
 */

// ============================================================
// JS / CSS エンキュー
// ============================================================
add_action('wp_enqueue_scripts', function() {
    $dir = get_stylesheet_directory_uri();
    wp_enqueue_style( 'mamehico-reservation', $dir . '/mamehico-reservation.css', [], '2.2.19');
    wp_enqueue_script('mamehico-reservation', $dir . '/mamehico-reservation-core.js', [], '2.2.19', true);
});

// ============================================================
// REST API 登録
// ============================================================
add_action('rest_api_init', function() {
    register_rest_route('mamehico/v1', '/create-checkout', [
        'methods' => 'POST', 'callback' => 'mamehico_create_checkout', 'permission_callback' => '__return_true',
    ]);
    register_rest_route('mamehico/v1', '/verify-session', [
        'methods' => 'GET', 'callback' => 'mamehico_verify_session', 'permission_callback' => '__return_true',
    ]);
    register_rest_route('mamehico/v1', '/send-confirmation', [
        'methods' => 'POST', 'callback' => 'mamehico_send_confirmation', 'permission_callback' => '__return_true',
    ]);
});

// ============================================================
// Stripe Checkout セッション作成
// ============================================================
function mamehico_create_checkout(WP_REST_Request $request) {
    $stripe_secret = get_option('mamehico_stripe_secret');
    if (!$stripe_secret) return new WP_Error('no_key', 'Stripeキーが設定されていません', ['status' => 500]);

    $mode        = sanitize_text_field($request->get_param('mode') ?? 'ginza');
    $event_id    = sanitize_text_field($request->get_param('event_id') ?? 'ginza-lunch');
    $event_title = sanitize_text_field($request->get_param('event_title') ?? '');
    $date        = sanitize_text_field($request->get_param('date'));
    $slot        = sanitize_text_field($request->get_param('slot'));
    $slot_end    = sanitize_text_field($request->get_param('slot_end') ?? '');
    $count       = intval($request->get_param('count'));
    $name        = sanitize_text_field($request->get_param('name'));
    $email       = sanitize_email($request->get_param('email'));
    $phone       = sanitize_text_field($request->get_param('phone') ?? '');
    $seat_price  = intval($request->get_param('seat_price') ?? 0);
    $coin        = intval($request->get_param('coin') ?? 0);
    $food_label  = sanitize_text_field($request->get_param('food_label') ?? 'なし');
    $food_price  = intval($request->get_param('food_price') ?? 0);
    $food_box_selections_json = sanitize_text_field($request->get_param('food_box_selections') ?? '[]');

    if (!$date || !$slot || !$count || !$name || !$email)
        return new WP_Error('missing_params', '必須項目が不足しています', ['status' => 400]);

    if ($mode === 'yoshino') {
        $success_url = home_url('/yoshino-success/?session_id={CHECKOUT_SESSION_ID}');
        $cancel_url  = home_url('/' . $event_id . '/');
    } else {
        $success_url = home_url('/ginza-lunch-success/?session_id={CHECKOUT_SESSION_ID}');
        $cancel_url  = home_url('/ginza-lunch/');
    }

    $line_items = [];
    $idx = 0;

    if ($seat_price > 0) {
        $line_items['line_items[' . $idx . '][price_data][currency]']                  = 'jpy';
        $line_items['line_items[' . $idx . '][price_data][product_data][name]']        = $event_title . ' ' . $date . ' ' . $slot . ' ' . $count . '名';
        $line_items['line_items[' . $idx . '][price_data][unit_amount]']               = $seat_price;
        $line_items['line_items[' . $idx . '][quantity]']                              = $count;
        $idx++;
    }

    if ($coin > 0) {
        $line_items['line_items[' . $idx . '][price_data][currency]']                  = 'jpy';
        $line_items['line_items[' . $idx . '][price_data][product_data][name]']        = 'おうえんコイン';
        $line_items['line_items[' . $idx . '][price_data][unit_amount]']               = $coin;
        $line_items['line_items[' . $idx . '][quantity]']                              = 1;
        $idx++;
    }

    if ($food_price > 0 && $food_label !== 'なし') {
        $line_items['line_items[' . $idx . '][price_data][currency]']                  = 'jpy';
        $line_items['line_items[' . $idx . '][price_data][product_data][name]']        = $food_label;
        $line_items['line_items[' . $idx . '][price_data][unit_amount]']               = $food_price;
        $line_items['line_items[' . $idx . '][quantity]']                              = $count;
        $idx++;
    }

    if (empty($line_items))
        return new WP_Error('no_items', '決済するアイテムがありません', ['status' => 400]);

    $body = array_merge($line_items, [
        'mode'           => 'payment',
        'customer_email' => $email,
        'success_url'    => $success_url,
        'cancel_url'     => $cancel_url,
        'metadata[mode]'                   => $mode,
        'metadata[event_id]'               => $event_id,
        'metadata[event_title]'            => $event_title,
        'metadata[date]'                   => $date,
        'metadata[slot]'                   => $slot,
        'metadata[slot_end]'               => $slot_end,
        'metadata[count]'                  => (string) $count,
        'metadata[name]'                   => $name,
        'metadata[email]'                  => $email,
        'metadata[phone]'                  => $phone,
        'metadata[coin]'                   => (string) $coin,
        'metadata[food_label]'             => $food_label,
        'metadata[food_price]'             => (string) $food_price,
        'metadata[food_box_selections]'    => $food_box_selections_json,
    ]);

    $response = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', [
        'timeout' => 30,
        'headers' => ['Authorization' => 'Bearer ' . $stripe_secret, 'Content-Type' => 'application/x-www-form-urlencoded'],
        'body' => http_build_query($body),
    ]);

    if (is_wp_error($response)) return new WP_Error('stripe_error', $response->get_error_message(), ['status' => 500]);
    $result = json_decode(wp_remote_retrieve_body($response), true);
    if (isset($result['error'])) return new WP_Error('stripe_error', $result['error']['message'], ['status' => 400]);

    return rest_ensure_response(['url' => $result['url'], 'session_id' => $result['id']]);
}

// ============================================================
// Stripe セッション確認
// ============================================================
function mamehico_verify_session(WP_REST_Request $request) {
    $stripe_secret = get_option('mamehico_stripe_secret');
    $session_id = sanitize_text_field($request->get_param('session_id'));
    if (!$session_id) return new WP_Error('missing_session', 'Session IDが必要です', ['status' => 400]);

    $response = wp_remote_get('https://api.stripe.com/v1/checkout/sessions/' . $session_id, [
        'timeout' => 30,
        'headers' => ['Authorization' => 'Bearer ' . $stripe_secret],
    ]);
    if (is_wp_error($response)) return new WP_Error('stripe_error', $response->get_error_message(), ['status' => 500]);

    $session = json_decode(wp_remote_retrieve_body($response), true);
    if (isset($session['error'])) return new WP_Error('stripe_error', $session['error']['message'], ['status' => 400]);
    if ($session['payment_status'] !== 'paid') return new WP_Error('not_paid', '決済が完了していません', ['status' => 400]);

    return rest_ensure_response([
        'status'   => 'paid',
        'metadata' => $session['metadata'],
        'email'    => $session['customer_email'] ?? $session['metadata']['email'] ?? '',
    ]);
}

// ============================================================
// メール送信
// ============================================================
function mamehico_send_confirmation(WP_REST_Request $request) {
    $mode        = sanitize_text_field($request->get_param('mode') ?? 'ginza');
    $event_id    = sanitize_text_field($request->get_param('event_id') ?? 'ginza-lunch');
    $event_title = sanitize_text_field($request->get_param('event_title') ?? '');
    $name        = sanitize_text_field($request->get_param('name'));
    $email       = sanitize_email($request->get_param('email'));
    $phone       = sanitize_text_field($request->get_param('phone') ?? '');
    $date        = sanitize_text_field($request->get_param('date'));
    $slot        = sanitize_text_field($request->get_param('slot'));
    $count       = intval($request->get_param('count'));
    $payment     = sanitize_text_field($request->get_param('payment'));
    $total       = intval($request->get_param('total'));
    $tax         = intval($request->get_param('tax'));
    $grand       = intval($request->get_param('grand'));
    $coin        = intval($request->get_param('coin') ?? 0);
    $food        = sanitize_text_field($request->get_param('food') ?? 'なし');
    $venue       = sanitize_text_field($request->get_param('venue') ?? '');

    $food_box_selections_raw = $request->get_param('food_box_selections');
    if (is_string($food_box_selections_raw)) {
        $food_box_arr = json_decode($food_box_selections_raw, true);
        if (!is_array($food_box_arr)) $food_box_arr = [];
    } elseif (is_array($food_box_selections_raw)) {
        $food_box_arr = array_map('sanitize_text_field', $food_box_selections_raw);
    } else {
        $food_box_arr = [];
    }

    $food_box_summary = 'なし';
    if (!empty($food_box_arr)) {
        $counts = [];
        foreach ($food_box_arr as $label) {
            $label = sanitize_text_field($label);
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }
        $parts = [];
        foreach ($counts as $label => $n) {
            $parts[] = $label . '×' . $n;
        }
        $food_box_summary = implode('、', $parts);
    }

    if (!$name || !$email || !$date || !$slot)
        return new WP_Error('missing_params', '必須項目が不足しています', ['status' => 400]);

    $payment_label = $payment === 'cash' ? '店頭でお支払い' : ($payment === 'free' ? '無料' : 'クレジットカード');
    $date_label = date('Y年n月j日', strtotime($date));

    $vars = [
        '{name}'             => $name,
        '{email}'            => $email,
        '{phone}'            => $phone ?: 'なし',
        '{title}'            => $event_title,
        '{venue}'            => $venue,
        '{date}'             => $date_label,
        '{slot}'             => $slot,
        '{count}'            => $count,
        '{payment}'          => $payment_label,
        '{subtotal}'         => number_format($total),
        '{tax}'              => number_format($tax),
        '{grand_total}'      => number_format($grand),
        '{coin}'             => $coin > 0 ? '¥' . number_format($coin) : 'なし',
        '{food}'             => $food,
        '{meal_summary}'     => $food,
        '{coin_summary}'     => $coin > 0 ? '¥' . number_format($coin) : 'なし',
        '{food_box_summary}' => $food_box_summary,
    ];

    $admin_email = 'info@mamehico.com';
    $headers = [
        'Content-Type: text/plain; charset=UTF-8',
        'From: MAMEHICO予約システム <noreply@mamehico.com>',
    ];

    if ($mode === 'yoshino') {
        $pfx = 'ymail_' . $event_id;
        $c_subject_tpl = get_option($pfx . '_cs') ?: get_option('ymail_yoshino_cs', mamehico_default_subject($mode, $event_id, 'customer'));
        $c_body_tpl    = get_option($pfx . '_cb') ?: get_option('ymail_yoshino_cb', mamehico_default_body($mode, $event_id, 'customer'));
        $a_subject_tpl = get_option($pfx . '_as') ?: get_option('ymail_yoshino_as', mamehico_default_subject($mode, $event_id, 'admin'));
        $a_body_tpl    = get_option($pfx . '_ab') ?: get_option('ymail_yoshino_ab', mamehico_default_body($mode, $event_id, 'admin'));
        $c_subject = mamehico_apply_vars($c_subject_tpl, $vars);
        $c_body    = mamehico_remove_empty_lines(mamehico_apply_vars($c_body_tpl, $vars));
        $a_subject = mamehico_apply_vars($a_subject_tpl, $vars);
        $a_body    = mamehico_remove_empty_lines(mamehico_apply_vars($a_body_tpl, $vars));
    } else {
        $prefix = 'mamehico';
        $c_subject = mamehico_apply_vars(get_option($prefix . '_mail_customer_subject', mamehico_default_subject($mode, $event_id, 'customer')), $vars);
        $c_body    = mamehico_apply_vars(get_option($prefix . '_mail_customer_body',    mamehico_default_body($mode, $event_id, 'customer')), $vars);
        $a_subject = mamehico_apply_vars(get_option($prefix . '_mail_admin_subject',    mamehico_default_subject($mode, $event_id, 'admin')), $vars);
        $a_body    = mamehico_apply_vars(get_option($prefix . '_mail_admin_body',       mamehico_default_body($mode, $event_id, 'admin')), $vars);
    }

    wp_mail($email, $c_subject, $c_body, $headers);
    wp_mail($admin_email, $a_subject, $a_body, $headers);

    return rest_ensure_response(['sent' => true]);
}

function mamehico_apply_vars($template, $vars) {
    return str_replace(array_keys($vars), array_values($vars), $template);
}

function mamehico_remove_empty_lines($text) {
    $lines = explode("\n", $text);
    $filtered = array_filter($lines, function($line) {
        $trimmed = trim($line);
        if (preg_match('/[:\uff1a]\s*なし\s*$/', $trimmed)) return false;
        if (preg_match('/[:\uff1a]\s*$/', $trimmed)) return false;
        return true;
    });
    return implode("\n", $filtered);
}

function mamehico_default_subject($mode, $event_id, $type) {
    if ($mode === 'ginza') {
        return $type === 'customer'
            ? 'ご予約が確定いたしました（MAMEHICO 銀座）'
            : '【予約入りました】{date} {slot}　{name} 様 {count}名';
    }
    return $type === 'customer'
        ? '【ご予約完了】{title} {date} {slot}'
        : '【予約】{title} {date} {slot} {name}様 {count}名';
}

function mamehico_default_body($mode, $event_id, $type) {
    if ($mode === 'ginza' && $type === 'customer') {
        return '{name} 様

このたびはMAMEHICO 銀座ランチをご予約いただき、ありがとうございます。
以下の内容でご予約が確定いたしました。

■ 日付　　{date}
■ 時間帯　{slot}
■ 人数　　{count} 名
■ お支払い　{payment}
■ 小計　　¥{subtotal}（税別）
■ 消費税　¥{tax}
■ 合計　　¥{grand_total}

ご来店をお待ちしております。

──────────────────────
MAMEHICO GINZA
TEL: 03-6263-0820
──────────────────────
キャンセルはお電話にてお問い合わせください。';
    }
    if ($mode === 'ginza' && $type === 'admin') {
        return '銀座ランチ　新規予約が入りました。

━━━━━━━━━━━━━━━━━━
【日付】　{date}
【時間帯】{slot}
【人数】　{count} 名
【支払い】{payment}
【合計】　¥{grand_total}
━━━━━━━━━━━━━━━━━━
【お名前】{name}
【メール】{email}
【電話】　{phone}
━━━━━━━━━━━━━━━━━━';
    }
    if ($type === 'customer') {
        return '{name} 様

このたびは {title} のご予約をいただき、ありがとうございます。
以下の内容でお席を確保いたしました。

■ 公演　　{title}
■ 日付　　{date}
■ 会場　　{venue}
■ 時間帯　{slot}
■ 人数　　{count} 名
■ 食事　　{meal_summary}
■ お弁当　{food_box_summary}
■ コイン　{coin_summary}
■ お支払い　{payment}
■ 合計　　¥{grand_total}（税込）

当日は {name} 様にお会いできるのを楽しみにお待ちしております。
どうぞお気をつけてお越しください。

──────────────────────
MAMEHICO
──────────────────────
キャンセル・変更はお電話か info@mamehico.com までご連絡ください。';
    }
    return '【新規予約】{title}

━━━━━━━━━━━━━━━━━━
【タイトル】{title}
【日付】　{date}
【会場】　{venue}
【時間】　{slot}
【人数】　{count} 名
【食事】　{meal_summary}
【お弁当】{food_box_summary}
【コイン】{coin_summary}
【支払い】{payment}
【合計】　¥{grand_total}
━━━━━━━━━━━━━━━━━━
【氏名】　{name}
【メール】{email}
【電話】　{phone}
━━━━━━━━━━━━━━━━━━';
}

// ============================================================
// Firebase設定（共通）
// ============================================================
function mamehico_firebase_config() {
    return [
        'apiKey'            => 'AIzaSyDnDy4ipNbMbzXd2yurCgVwKjyEkp3FCZE',
        'authDomain'        => 'mamehico-schedule.firebaseapp.com',
        'projectId'         => 'mamehico-schedule',
        'storageBucket'     => 'mamehico-schedule.firebasestorage.app',
        'messagingSenderId' => '992467894050',
        'appId'             => '1:992467894050:web:88b676e753523dac572f7b',
    ];
}

function mamehico_config_script($mode, $event_id = '') {
    $api_base = esc_js(home_url('/wp-json/mamehico/v1'));
    $fb = json_encode(mamehico_firebase_config());
    $event_id_js = esc_js($event_id);
    return '<script>
window.MAMEHICO_CONFIG = {
    mode: "' . $mode . '",
    eventId: "' . $event_id_js . '",
    apiBase: "' . $api_base . '",
    firebase: ' . $fb . '
};
</script>';
}

// ============================================================
// モーダル共通HTML
// ============================================================
function mamehico_modal_html($trigger_id) {
    return '
<style>
@keyframes mamehico-fadein{from{opacity:0}to{opacity:1}}
@keyframes mamehico-slideup{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
#mamehico-modal-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.55);z-index:99999;align-items:flex-start;justify-content:center;padding:0 16px 40px;box-sizing:border-box;overflow-y:auto;animation:mamehico-fadein .2s ease}
#mamehico-modal-inner{background:#fff;border-radius:4px;width:100%;max-width:500px;position:relative;margin:72px auto 40px;animation:mamehico-slideup .25s ease}
#mamehico-modal-inner #mamehico-reservation-root{padding-top:80px}
#mamehico-modal-inner .res-title{margin-top:0}
#mamehico-modal-close{position:absolute;top:12px;right:14px;background:transparent;border:none;font-size:1.5rem;cursor:pointer;color:#888;z-index:2;line-height:1;width:36px;height:36px;display:flex;align-items:center;justify-content:center}
#mamehico-modal-close:hover{color:#333}
@media(max-width:600px){#mamehico-modal-overlay{padding:0 10px 30px}#mamehico-modal-inner{margin-top:60px}}
</style>
<div id="mamehico-modal-overlay" onclick="if(event.target===this)this.style.display=\'none\'">\n  <div id="mamehico-modal-inner">\n    <button id="mamehico-modal-close" onclick="document.getElementById(\'mamehico-modal-overlay\').style.display=\'none\'">✕</button>\n    <div id="mamehico-reservation-root"></div>\n  </div>\n</div>\n<script>\ndocument.addEventListener("DOMContentLoaded",function(){\n    var btns=document.querySelectorAll("#' . $trigger_id . ',.' . $trigger_id . '");\n    btns.forEach(function(btn){\n        btn.addEventListener("click",function(e){\n            e.preventDefault();\n            var o=document.getElementById("mamehico-modal-overlay");\n            o.style.display="flex";o.scrollTop=0;\n        });\n    });\n});\n</script>';
}

// ============================================================
// 完了ページ共通スタイル
// ============================================================
function mamehico_success_styles() {
    return '<style>
#mamehico-success-root{font-family:\'Zen Kaku Gothic New\',sans-serif;background:#fff;color:#2E303E;border-radius:2px;padding:48px 40px;max-width:500px;margin:0 auto}
#mamehico-success-root *{box-sizing:border-box}
.success-icon{text-align:center;font-size:1.6rem;color:#2E303E;margin-bottom:12px;font-weight:300;letter-spacing:.1em}
.success-title{text-align:center;font-size:1.1rem;letter-spacing:.2em;color:#2E303E;margin-bottom:6px;font-weight:500}
.success-sub{text-align:center;font-size:.85rem;color:#888882;margin-bottom:36px;letter-spacing:.08em}
.success-detail{background:#f7f6f3;border:1px solid #d8d5cf;border-radius:2px;padding:20px 22px;margin-bottom:28px}
.success-row{display:flex;justify-content:space-between;font-size:.9rem;padding:7px 0;color:#888882;border-bottom:1px solid #e8e5df}
.success-row:last-child{border-bottom:none}
.success-row .val{color:#2E303E;font-weight:500}
.success-note{font-size:.8rem;color:#888882;text-align:center;line-height:1.9;margin-bottom:24px}
.success-btn{display:block;width:100%;background:#2E303E;border:1px solid #2E303E;border-radius:2px;padding:16px;color:#fff;font-size:.95rem;letter-spacing:.14em;cursor:pointer;font-family:inherit;font-weight:500;text-align:center;text-decoration:none;transition:opacity .15s}
.success-btn:hover{opacity:.75;color:#fff}
.error-state{text-align:center;color:#c0392b;font-size:.9rem;padding:40px 20px}
@media(max-width:600px){#mamehico-success-root{padding:32px 20px}}
</style>';
}

// ============================================================
// ショートコード: 銀座ランチ モーダル
// ============================================================
add_shortcode('mamehico_reservation_modal', function() {
    return mamehico_config_script('ginza', 'ginza-lunch') . mamehico_modal_html('mamehico-open-modal');
});

// ============================================================
// ショートコード: 銀座ランチ 完了ページ
// ============================================================
add_shortcode('mamehico_reservation_success', function() {
    $api_base = home_url('/wp-json/mamehico/v1');
    $fb = json_encode(mamehico_firebase_config());
    add_action('wp_footer', function() use ($api_base, $fb) {
        echo mamehico_success_styles();
        echo '<script>window.__MAME_API=' . json_encode($api_base) . ';window.__MAME_FB=' . $fb . ';</script>';
        echo '<script type="module">
import{initializeApp,getApps}from"https://www.gstatic.com/firebasejs/11.4.0/firebase-app.js";
import{getFirestore,doc,setDoc,updateDoc,increment,getDoc}from"https://www.gstatic.com/firebasejs/11.4.0/firebase-firestore.js";
const apiBase=window.__MAME_API,fbCfg=window.__MAME_FB;
const root=document.getElementById("mamehico-success-root");
const DJ=["日","月","火","水","木","金","土"];
const SL={"11:30":{end:"13:30",key:"1130"},"12:30":{end:"14:30",key:"1230"},"13:30":{end:"15:30",key:"1330"},"14:30":{end:"16:30",key:"1430"}};
function rows(a){return a.map(r=>\'<div class="success-row"><span>\'+r[0]+\'</span><span class="val">\'+r[1]+\'</span></div>\').join("");}
async function init(){
  const p=new URLSearchParams(location.search),type=p.get("type"),sid=p.get("session_id");
  if(type==="cash"||type==="free"){
    const date=p.get("date")||"",slot=p.get("slot")||"",count=parseInt(p.get("count")||"1");
    const[y,m,d]=date.split("-"),dateObj=new Date(+y,+m-1,+d),si=SL[slot],total=6000*count;
    root.innerHTML=\'<div class="success-icon">✓</div><div class="success-title">ご予約ありがとうございます</div><div class="success-sub">確認メールをお送りしました</div><div class="success-detail">\'+rows([
      ["日付",+m+"月"+ +d+"日（"+DJ[dateObj.getDay()]+"）"],
      ["時間",slot+" — "+(si?si.end:"")+"（120分）"],
      ["人数",count+"名"],["お支払い","店頭でお支払い"],
      ["小計（税別）","¥"+total.toLocaleString()],
      ["消費税（10%）","¥"+Math.floor(total*.1).toLocaleString()],
      ["合計","¥"+Math.floor(total*1.1).toLocaleString()]
    ])+\'</div><p class="success-note">当日は予約時間の5分前までにお越しください。<br>キャンセルはお電話にてお願いいたします。<br>TEL: 03-6263-0820</p>\';
    return;
  }
  if(!sid){root.innerHTML=\'<div class="error-state">不正なアクセスです。</div>\';return;}
  const pk="ginza_processed_"+sid;
  try{
    const res=await fetch(apiBase+"/verify-session?session_id="+sid),data=await res.json();
    if(!res.ok){root.innerHTML=\'<div class="error-state">\'+( data.message||"決済の確認に失敗しました。")+\'</div>\';return;}
    const{metadata:meta,email}=data,{date,slot,count,name}=meta;
    const si=SL[slot];
    if(!localStorage.getItem(pk)){
      const app=getApps().find(a=>a.name==="mamehico-res")||initializeApp(fbCfg,"mamehico-res");
      const db=getFirestore(app);
      const sk=si?si.key:slot.replace(":",""),sdid=date+"_"+sk;
      await setDoc(doc(db,"ginza_reservations",sid),{
        date,slot,slot_end:si?si.end:"",count:+count,name,email,
        phone:meta.phone||"",stripe_session_id:sid,
        payment_method:"card",status:"confirmed",event_id:"ginza-lunch",
        coin:0,food_label:"なし",food_price:0,
        created_at:new Date().toISOString()
      });
      const ss=await getDoc(doc(db,"ginza_slots",sdid));
      if(ss.exists())await updateDoc(doc(db,"ginza_slots",sdid),{booked:increment(+count)});
      else await setDoc(doc(db,"ginza_slots",sdid),{booked:+count,capacity:10,date,slot});
      localStorage.setItem(pk,"1");
      const total=6000*+count;
      await fetch(apiBase+"/send-confirmation",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({
        mode:"ginza",event_id:"ginza-lunch",event_title:"銀座ランチ",name,email,phone:meta.phone||"",date,slot,count:+count,
        payment:"card",total,tax:Math.floor(total*.1),grand:Math.floor(total*1.1),coin:0,food:"なし"
      })});
    }
    const[y,m,d]=date.split("-"),dateObj=new Date(+y,+m-1,+d),total=6000*+count;
    root.innerHTML=\'<div class="success-icon">✓</div><div class="success-title">ご予約ありがとうございます</div><div class="success-sub">確認メールを \'+email+\' にお送りしました</div><div class="success-detail">\'+rows([
      ["日付",+m+"月"+ +d+"日（"+DJ[dateObj.getDay()]+"）"],
      ["時間",slot+" — "+(si?si.end:"")+"（120分）"],
      ["人数",count+"名"],["お名前",name+" 様"],["お支払い","クレジットカード"],
      ["消費税（10%）","¥"+Math.floor(total*.1).toLocaleString()],
      ["合計","¥"+Math.floor(total*1.1).toLocaleString()]
    ])+\'</div><p class="success-note">当日は予約時間の5分前までにお越しください。<br>キャンセルはお電話にてお願いいたします。<br>TEL: 03-6263-0820</p>\';
  }catch(e){root.innerHTML=\'<div class="error-state">エラーが発生しました: \'+e.message+\'</div>\';}
}
init();
</script>';
    });
    return '<div id="mamehico-success-root"><p style="text-align:center;padding:40px;color:#888">確認中...</p></div>';
});

// ============================================================
// ショートコード: ヨシノ モーダル
// [yoshino_reservation_modal event_id="yoshino"]
// ============================================================
add_shortcode('yoshino_reservation_modal', function($atts) {
    $atts = shortcode_atts(['event_id' => ''], $atts);
    $event_id = sanitize_key($atts['event_id']);
    if (!$event_id) return '<p style="color:red">event_id が指定されていません</p>';
    $trigger_id = 'yoshino-open-modal-' . $event_id;
    return mamehico_config_script('yoshino', $event_id) . mamehico_modal_html($trigger_id);
});

// ============================================================
// ショートコード: ヨシノ 完了ページ
// ============================================================
add_shortcode('yoshino_reservation_success', function() {
    $api_base = home_url('/wp-json/mamehico/v1');
    $fb = json_encode(mamehico_firebase_config());
    add_action('wp_footer', function() use ($api_base, $fb) {
        echo mamehico_success_styles();
        echo '<script>window.__MAME_API=' . json_encode($api_base) . ';window.__MAME_FB=' . $fb . ';</script>';
        echo '<script type="module">
import{initializeApp,getApps}from"https://www.gstatic.com/firebasejs/11.4.0/firebase-app.js";
import{getFirestore,doc,setDoc,updateDoc,increment,getDoc}from"https://www.gstatic.com/firebasejs/11.4.0/firebase-firestore.js";
const apiBase=window.__MAME_API,fbCfg=window.__MAME_FB;
const root=document.getElementById("mamehico-success-root");
const DJ=["日","月","火","水","木","金","土"];
function rows(a){return a.map(r=>\'<div class="success-row"><span>\'+r[0]+\'</span><span class="val">\'+r[1]+\'</span></div>\').join("");}
async function init(){
  const p=new URLSearchParams(location.search);
  const type=p.get("type"),sid=p.get("session_id"),eventId=p.get("event_id")||"yoshino";
  if(type==="free"||type==="cash"){
    const date=p.get("date")||"",slot=p.get("slot")||"",slotEnd=p.get("slot_end")||"",count=parseInt(p.get("count")||"1");
    const name=(()=>{try{return sessionStorage.getItem("yoshino_success_name")||"";}catch(e){return "";}})();
    const venue=(()=>{try{return sessionStorage.getItem("yoshino_success_venue")||"";}catch(e){return "";}})();
    const[y,m,d]=date.split("-"),dateObj=new Date(+y,+m-1,+d);
    const detailRows=[["日付",+m+"月"+ +d+"日（"+DJ[dateObj.getDay()]+"）"],["時間",slot+(slotEnd?" — "+slotEnd:"")]];
    if(venue)detailRows.push(["会場",venue]);
    if(name)detailRows.push(["お名前",name+" 様"]);
    detailRows.push(["人数",count+"名"],["入場",type==="free"?"無料（当日おうえんコイン購入可）":"店頭でお支払い"]);
    root.innerHTML=\'<div class="success-icon">✓</div><div class="success-title">ご予約ありがとうございます</div><div class="success-sub">確認メールをお送りしました</div><div class="success-detail">\'+rows(detailRows)+\'</div><p class="success-note">開演時間までにお越しください。<br>キャンセルはお電話にてお願いいたします。<br>TEL: 03-6263-0820</p>\';
    return;
  }
  if(!sid){root.innerHTML=\'<div class="error-state">不正なアクセスです。</div>\';return;}
  const pk="yoshino_processed_"+sid;
  try{
    const res=await fetch(apiBase+"/verify-session?session_id="+sid),data=await res.json();
    if(!res.ok){root.innerHTML=\'<div class="error-state">\'+( data.message||"決済の確認に失敗しました。")+\'</div>\';return;}
    const{metadata:meta,email}=data;
    const{date,slot,slot_end,count,name,event_id,coin,food_label,food_price,event_title}=meta;
    const foodBoxSelections=(()=>{try{return JSON.parse(meta.food_box_selections||"[]");}catch(e){return[];}})();
    if(!localStorage.getItem(pk)){
      const app=getApps().find(a=>a.name==="mamehico-res")||initializeApp(fbCfg,"mamehico-res");
      const db=getFirestore(app);
      const slotKey=slot.replace(":",""),sdid=date+"_"+slotKey;
      await setDoc(doc(db,"yoshino_reservations",sid),{
        date,slot,slot_end:slot_end||"",count:+count,name,email,
        phone:meta.phone||"",stripe_session_id:sid,
        payment_method:"card",status:"confirmed",event_id:event_id||"yoshino",
        event_title:event_title||"",
        coin:+(coin||0),food_label:food_label||"なし",food_price:+(food_price||0),
        food_box_selections:foodBoxSelections,
        created_at:new Date().toISOString()
      });
      const capacity=45;
      const ss=await getDoc(doc(db,"yoshino_slots",sdid));
      if(ss.exists())await updateDoc(doc(db,"yoshino_slots",sdid),{booked:increment(+count)});
      else await setDoc(doc(db,"yoshino_slots",sdid),{booked:+count,capacity,date,slot,event_id:event_id||"yoshino"});
      localStorage.setItem(pk,"1");
      const seatPrice=+(meta.seat_price||0),coinAmt=+(coin||0),foodAmt=+(food_price||0);
      const total=seatPrice*+count+coinAmt+(foodAmt*+count);
      await fetch(apiBase+"/send-confirmation",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({
        mode:"yoshino",event_id:event_id||"yoshino",event_title:event_title||"",
        name,email,phone:meta.phone||"",date,slot,count:+count,
        payment:"card",total,tax:Math.floor(total*.1),grand:Math.floor(total*1.1),
        coin:coinAmt,food:food_label||"なし",
        food_box_selections:foodBoxSelections
      })});
    }
    const[y,m,d]=date.split("-"),dateObj=new Date(+y,+m-1,+d);
    const coinAmt=+(coin||0),foodAmt=+(food_price||0),seatPrice=+(meta.seat_price||0);
    const total=seatPrice*+count+coinAmt+(foodAmt*+count);
    const detailRows=[
      ["日付",+m+"月"+ +d+"日（"+DJ[dateObj.getDay()]+"）"],
      ["時間",slot+(slot_end?" — "+slot_end:"")],
      ["人数",count+"名"],["お名前",name+" 様"],["お支払い","クレジットカード"]
    ];
    if(coinAmt>0)detailRows.push(["おうえんコイン","¥"+coinAmt.toLocaleString()]);
    if(foodAmt>0)detailRows.push([food_label,"¥"+(foodAmt*+count).toLocaleString()]);
    if(total>0){
      detailRows.push(["消費税（10%）","¥"+Math.floor(total*.1).toLocaleString()]);
      detailRows.push(["合計","¥"+Math.floor(total*1.1).toLocaleString()]);
    }else{
      detailRows.push(["入場","無料"]);
    }
    root.innerHTML=\'<div class="success-icon">✓</div><div class="success-title">ご予約ありがとうございます</div><div class="success-sub">確認メールを \'+email+\' にお送りしました</div><div class="success-detail">\'+rows(detailRows)+\'</div><p class="success-note">開演時間までにお越しください。<br>キャンセルはお電話にてお願いいたします。<br>TEL: 03-6263-0820</p>\';
  }catch(e){root.innerHTML=\'<div class="error-state">エラーが発生しました: \'+e.message+\'</div>\';}
}
init();
</script>';
    });
    return '<div id="mamehico-success-root"><p style="text-align:center;padding:40px;color:#888">確認中...</p></div>';
});
