# MAMEHICO システム作業ルール

## 作業前に必ずやること

- Firestoreの実データを直接確認する（仕様書の値を信じない）
- 作業対象ファイルとバージョンを宣言する
- スコープ外の問題を見つけたら報告して止まる

## バージョン管理

- JSとPHPコアスニペットのバージョンは必ず同時に上げる
- 変更のたびにchangelogに日付と内容を追記する
- enqueueバージョンはJSバージョンと一致させる

## ファイル構成

- mamehico-reservation-core.js（現在 v2.2.19）
- mamehico-snippet-core.php（現在 v2.2.19）
- yoshino-admin-2.php（現在 v2.3.1）
- スニペットは5本構成、本数を変えない

## Firestore

- プロジェクトID: mamehico-schedule
- foods のキーは動的取得、JSにハードコードしない
- yoshino_events のIDはevent_id固定（yoshino/hajimete/omoshiro）
