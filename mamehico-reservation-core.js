// MAMEHICO 予約システム 共通エンジン v2.2.20
// 銀座ランチ（ginza）・ヨシノ系（yoshino）両対応
//
// 更新履歴
// v2.2.20 - 2026-03-15 startStripeCheckoutでyoshino hasFoods時のfood/coin計算を修正（空line_itemsエラー解消）、food_quantityパラメータ追加
// v2.2.19 - 2026-03-12 food_box_selectionsをsend-confirmation/create-checkoutに追加（{food_box_summary}対応）
// v2.2.18 - 2026-03-12 mealCountsをFirestoreのfoodsキーで動的初期化・boxCountを動的計算に変更（A/AB/AC/ABCハードコード除去）
// v2.2.15 - 2026-03-12 loadEventConfig: start_date/end_dateをローカル時間でパース（UTC解釈バグ修正）
// v2.2.14 - 2026-03-12 renderStep2Yoshino: 「飲食セット」セクションラベルを削除
// v2.2.13 - 2026-03-11 リダイレクトURLからvenue/nameを除去（Xserverブロック対策）・sessionStorageで受け渡し
// v2.2.12 - 2026-03-11 venueForDate/venueForStep3: venues配列・文字列両対応
// v2.2.11 - 2026-03-11 saveDirectReservation: yoshino成功ページにname・venueを追加
// v2.2.10 - 2026-03-11 renderStep3: yoshino確認画面に会場行を追加
// v2.2.9 - 2026-03-11 renderStep2Yoshino: hasFoods=falseのとき飲食セットUIを非表示・
//                      人数入力UIを表示。renderStep3/saveDirectReservation/startStripeCheckout:
//                      hasFoods=falseのとき入場料（price×count）で金額計算。
// v2.2.8 - 2026-03-11 venue送信追加・yoshino food文字列修正（mealCountsから生成）
// v2.2.7 - 2026-03-09 renderStep2冒頭に入力値退避処理追加
// v2.2.6 - 2026-03-09 日付別タイトル（titles）優先取得・完了画面に演目名表示
// v2.2.5 - 2026-03-09 hasFoods/hasCoins判定修正・slotDocId修正・titles/venues表示対応
// v2.2.2 - 2026-03-09 yoshino: 飲食セット必須バリデーションを撤廃
// v2.2.1 - 2026-03-09 yoshino対応・銀座・ヨシノ共通エンジン化
// v2.0.0 - 銀座ランチ専用版（旧）
(function() {
    'use strict';

    var cfg = window.MAMEHICO_CONFIG;
    if (!cfg) return;

    var MODE = cfg.mode || 'ginza';
    var DAYS_JP = ['\u65e5','\u6708','\u706b','\u6c34','\u6728','\u91d1','\u571f'];

    var state = {
        step: 1,
        currentYear: 0, currentMonth: 0,