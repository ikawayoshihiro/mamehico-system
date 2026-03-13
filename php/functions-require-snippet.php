<?php
/**
 * functions.php への追記用スニペット
 * テーマの functions.php の末尾にこの内容をコピーして貼り付けてください。
 *
 * 読み込み順序は依存関係に従い固定です：
 *   1. コア（REST API / Stripe / Firebase / shortcode）
 *   2. 銀座①（admin_menu登録・共通スタイル）
 *   3. 銀座②（カレンダー）← ①の mamehico_ginza_admin_styles() に依存
 *   4. 銀座③（メール・予約一覧）← ①の mamehico_ginza_admin_styles() に依存
 *   5. ヨシノ①（admin_menu登録・共通CSS・Firebase config）
 *   6. ヨシノ②（カレンダー）← ①の yoshino_firebase_config() / yoshino_common_css() に依存
 */

// ---- MAMEHICO 予約システム ----
require get_stylesheet_directory() . '/php/mamehico-core.php';
require get_stylesheet_directory() . '/php/ginza-01-menu-styles.php';
require get_stylesheet_directory() . '/php/ginza-02-calendar.php';
require get_stylesheet_directory() . '/php/ginza-03-mail-list.php';
require get_stylesheet_directory() . '/php/yoshino-01-menu-common.php';
require get_stylesheet_directory() . '/php/yoshino-02-calendar.php';
// ---- ここまで ----
