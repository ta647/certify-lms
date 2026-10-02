# Certify LMS

マルチ資格対応の資格学習プラットフォームです。受講生は資格ごとの教材で学習し、演習問題・模擬試験で理解度を確かめながら、コーチの面談サポートを受けて資格取得を目指せます。

> プロジェクト構造・ドメインモデル・コードの読み進め方は [ONBOARDING.md](./ONBOARDING.md) を参照してください。

## 主な機能

| ロール | 機能 |
|---|---|
| 受講生（student） | 教材閲覧 / 演習問題・苦手分野ドリル / 模擬試験（分野別ヒートマップ・合格可能性スコア）/ 面談予約 / チャット / 学習時間・進捗・ストリーク管理 / 修了証の受領 |
| コーチ（coach） | 教材・演習問題・模試の管理 / 担当受講生の進捗フォロー / 面談対応・面談メモ / チャット |
| 管理者（admin） | ユーザー招待・管理 / 資格・資格分類マスタ管理 / 資格へのコーチ割当 / 面談回数の付与 / 全体ダッシュボード |

## 動作環境

- Docker Desktop / Docker Compose
- 開発環境は Laravel Sail で構築します（PHP コンテナ・MySQL・Mailpit・phpMyAdmin を起動）

## 環境構築手順

### 1. リポジトリの clone

```bash
git clone <このリポジトリの URL>
cd <リポジトリ名>
```

### 2. 環境変数ファイルの作成

```bash
cp .env.example .env
```

`.env.example` は Sail 向けに設定済みのため、コピーするだけでローカル開発を始められます（外部サービス連携のキーは後述）。

### 3. 依存パッケージのインストール（初回のみ）

`vendor/` がまだ無いため、初回のみ Docker 経由で Composer を実行します。

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs
```

### 4. Sail エイリアスの設定（推奨）

```bash
alias sail='./vendor/bin/sail'
```

以降のコマンドはこのエイリアス前提で記載します（未設定の場合は `./vendor/bin/sail` に読み替えてください）。

### 5. コンテナの起動

```bash
sail up -d
```

### 6. アプリケーションの初期化

```bash
sail artisan key:generate
sail artisan storage:link
sail artisan migrate:fresh --seed
```

`storage:link` は教材画像・プロフィール画像の配信に必要です。`migrate:fresh --seed` でテーブル作成とデモデータ投入が行われます（いつでも再実行してデータを初期状態に戻せます）。

### 7. フロントエンドのビルド

```bash
sail npm install
sail npm run build
```

Blade / CSS / JS を編集しながら開発する場合は、`build` の代わりに `sail npm run dev` を起動したままにしてください（Vite のホットリロードが効きます）。

### 8. 動作確認

http://localhost:8000 にアクセスし、下記の[ログインアカウント](#ログインアカウント)でログインできればセットアップ完了です。

## 開発環境 URL

| 用途 | URL |
|---|---|
| アプリケーション | http://localhost:8000 |
| phpMyAdmin（DB 確認） | http://localhost:8080 |
| Mailpit（メール確認） | http://localhost:8025 |

アプリケーションが送信するメール（招待メールなど）はすべて Mailpit に届きます。実際のメールは送信されません。

## ログインアカウント

`migrate:fresh --seed` 後、以下の固定アカウントが使えます（パスワードはすべて `password`）。

| ロール | メールアドレス | 備考 |
|---|---|---|
| 管理者 | admin@certify-lms.test | 全機能にアクセス可能 |
| コーチ | coach@certify-lms.test | IT 系資格の担当 |
| コーチ | coach2@certify-lms.test | ビジネス系資格の担当 |
| 受講生 | student@certify-lms.test | 受講中の資格・学習履歴・面談などのデモデータ付き |

このほか、ライフサイクル（招待中 / 受講中 / 卒業 / 退会）を網羅したデモユーザーが投入されます。

> 本サービスは**招待制**です。公開の会員登録画面はありません。新規ユーザーを作るには、管理者でログイン → ユーザー管理から招待 → Mailpit で招待メールの URL を開く → オンボーディング登録、という流れになります。

## 通知・メールのキュー処理

チャット・Q&A 返信・面談の予約／キャンセル／リマインダー・管理者お知らせの通知、および招待メールの送信は、DB ベースのキュー(`jobs` テーブル)に積まれ、専用の worker コンテナ(`queue-worker`)が非同期に処理します。`sail up -d` で他のコンテナと一緒に自動起動するため、通常は何もしなくても送信されます。

```bash
sail logs queue-worker -f          # worker の処理状況を確認
```

一時的な送信失敗(メールサーバの不調など)は 30 秒後 → 5 分後の 2 回まで自動リトライし、それでも失敗した場合は `failed_jobs` テーブルに記録されます(配信自体は失われません)。

```bash
sail artisan queue:failed          # 失敗した送信の一覧
sail artisan queue:retry all       # 失敗した送信をすべて再投入
sail artisan queue:retry {id}      # 指定した1件だけ再投入
```

`.env.example` は `QUEUE_CONNECTION=database` が既定です。既存の `.env` で `QUEUE_CONNECTION=sync` のままになっている場合は `database` に書き換えてください(書き換えないと worker が何も処理しません)。

## テスト

```bash
sail artisan test                              # 全テスト実行
sail artisan test --filter=Xxx                # クラス名・メソッド名で絞り込み
sail artisan test --group=external-api        # 外部API(Google/Gemini/Stripe)連携のテストのみ実行
sail artisan test --exclude-group=external-api # それ以外のテストのみ実行
```

## コード整形

Laravel Pint を使用しています。コミット前に実行してください。

```bash
sail bin pint --dirty    # 変更ファイルのみ整形
sail bin pint --test     # 整形漏れの確認（CI 相当のチェック）
```

## 使用技術

- PHP 8.5 / Laravel 10
- MySQL 8.4
- Laravel Fortify（認証）/ Laravel Sanctum（API 認証）
- Blade + Tailwind CSS + Vite（JavaScript は素の JS、フレームワーク不使用）
- PHPUnit / Laravel Pint
- league/commonmark（教材本文の Markdown レンダリング）
- Pusher（チャットのリアルタイム配信）
- mPDF（修了証の PDF 生成）
- Stripe（追加面談パックの決済）
- Google Calendar API（コーチの面談カレンダー連携）
- Gemini API（受講生向け AI 相談）
- Docker（Laravel Sail）

## 環境変数

`.env.example` をコピーするだけで、すべての機能がローカルで動作します（メールは Mailpit に配信されます）。

- `PUSHER_*` — チャットのリアルタイム配信に使用します。有効にする場合は Pusher のキーを取得して設定し、`BROADCAST_DRIVER=pusher` に変更してください。未設定（既定の `BROADCAST_DRIVER=log`）でもメッセージの送受信自体は動作し、相手画面へのリアルタイム反映のみ行われません
- `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` — コーチの面談設定タブから Google カレンダーと連携する機能に使用します。未設定でも他の機能には影響しません（連携ボタンを押した際にエラーになるのみ）。設定する場合は以下の手順で OAuth クライアントを作成してください。
  1. [Google Cloud Console](https://console.cloud.google.com/) でプロジェクトを作成（または既存のものを利用）
  2. 「API とサービス」→「ライブラリ」から **Google Calendar API** を有効化
  3. 「API とサービス」→「認証情報」→「認証情報を作成」→「OAuth クライアント ID」を選択し、アプリケーションの種類は「ウェブ アプリケーション」を選択
  4. 「承認済みのリダイレクト URI」に `{APP_URL}/settings/google-calendar/callback`（ローカルでは `http://localhost:8000/settings/google-calendar/callback`）を追加
  5. 発行された クライアント ID / クライアントシークレット を `.env` の `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` に設定
- `AI_CHAT_ENABLED` / `GEMINI_API_KEY` 等 — 受講生向けAI相談機能(Gemini連携)に使用します。既定は無効(`AI_CHAT_ENABLED=false`)で、関連ルート・ウィジェットとも表示されません。有効にする場合は以下を設定してください。
  1. [Google AI Studio](https://aistudio.google.com/app/apikey) でGemini APIキーを発行
  2. `.env` の `GEMINI_API_KEY` に設定し、`AI_CHAT_ENABLED=true` に変更
  3. 任意で `AI_CHAT_DAILY_MESSAGE_LIMIT`(1受講生あたりの1日の送信上限、既定50)・`AI_CHAT_AUTO_TITLE`(会話タイトルの自動生成、既定true)・`AI_CHAT_GEMINI_MODEL`(既定`gemini-2.5-flash`)を調整
- `STRIPE_KEY` / `STRIPE_SECRET` / `STRIPE_WEBHOOK_SECRET` — 追加面談パックの購入(Stripe連携)に使用します。未設定でも他の機能には影響しません(購入ボタンを押した際にエラーになるのみ)。
  1. [Stripeダッシュボード](https://dashboard.stripe.com/test/apikeys)(テストモード)で公開可能キー/シークレットキーを取得し、`.env` の `STRIPE_KEY` / `STRIPE_SECRET` に設定
  2. ローカルでWebhookを受信するには [Stripe CLI](https://stripe.com/docs/stripe-cli) を導入し、`stripe listen --forward-to localhost:8000/webhooks/stripe` を実行(表示される `whsec_...` を `STRIPE_WEBHOOK_SECRET` に設定)
  3. 決済はStripeのテストカード(例: `4242 4242 4242 4242`)で試せます
- `ADMIN_DASHBOARD_CACHE_TTL` — 管理者ダッシュボードの全体KPI・資格別修了率の集計をキャッシュする秒数(既定300秒)。受講状態が変わった場合は即時無効化されるため、通常は変更不要です。

新しい環境変数やセットアップ手順を追加した場合は、`.env.example` と本 README に追記し、チームの誰でも環境を再現できる状態を保ってください。
