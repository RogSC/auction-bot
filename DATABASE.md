# Модель данных

## Назначение

Этот документ описывает целевую PostgreSQL-схему MVP. Он является источником для миграций и должен обновляться одновременно с изменением структуры данных. Архитектурные правила находятся в `ARCHITECTURE.md`, а пользовательские Telegram-сценарии — в `TELEGRAM_FLOWS.md`.

## Общие правила

- Первичные ключи — `bigint` identity.
- Даты — `timestamptz` и хранятся в UTC.
- Деньги — `bigint` с суффиксом `_cents`; валюта MVP фиксирована как USD.
- Статусы — PHP enum + `varchar` в БД с `check`-ограничением.
- Критичные строки не удаляются физически. Отмена хранится статусом, причиной и временем.
- Все FK используют `restrict`, кроме явно безопасных зависимостей. Нельзя удалить участника, лот или аукцион с историей торгов.

## Сущности и связи

```text
users ──< bids >── auctions >── artworks
  │                    │
  │                    └──< purchase_offers
  └──< notifications

admins ──< audit_logs
auctions ──< audit_logs
processed_telegram_updates
telegram_messages
outbox_messages
settings
```

## Основные таблицы

### `users`

Участник, идентифицируемый Telegram.

| Поле | Тип | Правило |
| --- | --- | --- |
| `id` | bigint | PK |
| `telegram_id` | bigint | unique, никогда не показывается пользователям |
| `bidder_code` | varchar(32) | unique, например `BIDDER-000124` |
| `first_name`, `last_name`, `username` | varchar, nullable | технические данные Telegram, не публичны |
| `accepted_terms_version` | varchar(32), nullable | версия принятых условий |
| `accepted_terms_at` | timestamptz, nullable | дата принятия |
| `created_at`, `updated_at` | timestamptz | Laravel timestamps |

Индексы: unique `telegram_id`, unique `bidder_code`.

### `admins`

Отдельная модель для доступа к Filament. Не смешивается с Telegram-участниками.

| Поле | Тип | Правило |
| --- | --- | --- |
| `id` | bigint | PK |
| `name` | varchar(255) | обязательное |
| `email` | varchar(255) | unique |
| `password` | varchar(255) | Laravel hash |
| `is_active` | boolean | default true |
| `last_login_at` | timestamptz, nullable | аудит доступа |
| `remember_token`, timestamps | стандартные | |

### `artworks`

Карточка цифровой работы. Оригинальный файл не сохраняется.

| Поле | Тип | Правило |
| --- | --- | --- |
| `id` | bigint | PK |
| `title` | varchar(255) | обязательное |
| `description` | text | обязательное |
| `preview_disk` | varchar(64) | filesystem disk |
| `preview_path` | varchar(1024) | путь к preview |
| `created_by_admin_id` | bigint | FK `admins` |
| timestamps | timestamptz | |

### `auctions`

Агрегат и единственная точка синхронизации конкурентных ставок.

| Поле | Тип | Правило |
| --- | --- | --- |
| `id` | bigint | PK |
| `artwork_id` | bigint | FK `artworks`, unique среди незавершённых продаж одной работы |
| `status` | varchar(32) | `draft`, `scheduled`, `active`, `awaiting_payment`, `paid`, `completed`, `cancelled`, `no_sale` |
| `start_price_cents` | bigint | > 0 |
| `bid_increment_cents` | bigint | > 0 |
| `current_price_cents` | bigint | начинается со `start_price_cents` |
| `current_leader_id` | bigint, nullable | FK `users` |
| `starts_at`, `ends_at` | timestamptz | `ends_at > starts_at` |
| `extension_threshold_seconds` | integer | default 120, >= 0 |
| `extension_duration_seconds` | integer | default 120, >= 0 |
| `payment_due_at` | timestamptz, nullable | создаётся при финализации |
| `auction_winner_id`, `buyer_id` | bigint, nullable | отдельные FK `users` |
| `winning_bid_id`, `accepted_bid_id` | bigint, nullable | отдельные FK `bids` |
| `version` | integer | optimistic version, default 1 |
| `cancelled_at`, `cancelled_by_admin_id`, `cancellation_reason` | nullable | аудит отмены |
| timestamps | timestamptz | |

Индексы: `(status, starts_at)`, `(status, ends_at)`, `current_leader_id`, `auction_winner_id`, `buyer_id`. Ставка блокирует строку аукциона через `FOR UPDATE`; `version` используется для обнаружения устаревшего представления Telegram.

### `bids`

Неизменяемая история ставок. Админ-отмена меняет статус, но не удаляет строку.

| Поле | Тип | Правило |
| --- | --- | --- |
| `id` | bigint | PK |
| `auction_id`, `user_id` | bigint | обязательные FK |
| `amount_cents` | bigint | > 0, сумма, фактически принятая сервером |
| `status` | varchar(32) | `active`, `outbid`, `winning`, `cancelled`, `disqualified` |
| `placed_at` | timestamptz | фиксируется сервером |
| `cancelled_at`, `cancelled_by_admin_id`, `cancellation_reason` | nullable | причина корректировки |
| timestamps | timestamptz | |

Индексы: `(auction_id, status, amount_cents desc, id desc)`, `(user_id, placed_at desc)`. Частичный unique-индекс гарантирует не более одной ставки со статусом `winning` на аукцион.

### `purchase_offers`

История предложений купить работу после неоплаты или отмены сделки.

| Поле | Тип | Правило |
| --- | --- | --- |
| `id` | bigint | PK |
| `auction_id` | bigint | FK |
| `bid_id` | bigint | FK; сумма берётся из этой ставки |
| `offered_to_user_id` | bigint | FK |
| `amount_cents` | bigint | snapshot суммы ставки |
| `status` | varchar(32) | `pending`, `accepted`, `expired`, `declined`, `cancelled` |
| `offered_at`, `expires_at`, `accepted_at` | timestamptz | |
| `created_by_admin_id` | bigint | FK `admins` |
| timestamps | timestamptz | |

Индекс: `(auction_id, status)`. Частичный unique-индекс допускает одно `pending`-предложение на аукцион.

## Технические и аудиторские таблицы

| Таблица | Назначение | Ключевые ограничения |
| --- | --- | --- |
| `processed_telegram_updates` | дедупликация webhook update | unique `update_id` |
| `telegram_messages` | история исходящих сообщений | unique idempotency key, `chat_id`, тип, payload, отправленное сообщение |
| `notifications` | модель Laravel notification | индекс `(notifiable_type, notifiable_id, read_at)` |
| `outbox_messages` | события, надёжно записанные в транзакции | unique idempotency key, `processed_at` |
| `audit_logs` | неизменяемый аудит админ-действий | actor, object type/id, action, before/after JSON, reason |
| `settings` | изменяемые системные настройки | unique `key`, JSON `value` |

`processed_telegram_updates` сначала получает запись с уникальным `update_id`. Если вставка конфликтует, webhook завершает обработку без повторного запуска команды.

## Инварианты транзакций

1. `auctions.current_price_cents` и `current_leader_id` изменяются только вместе со ставкой или пересчётом после её отмены.
2. При финализации `winning_bid_id` принадлежит тому же аукциону, что и строка `auctions`.
3. `accepted_bid_id` определяет сумму покупателя и может отличаться от `winning_bid_id`.
4. Предложение второму участнику сохраняет его собственную сумму в `purchase_offers.amount_cents`.
5. Событие добавляется в `outbox_messages` в той же транзакции, что и изменение бизнес-состояния.

## План миграций

1. Базовые Laravel-таблицы, `users`, `admins`, `settings`.
2. `artworks`, `auctions` и ограничения состояний.
3. `bids`, индексы конкурентного доступа и связь с аукционами.
4. `purchase_offers`, `audit_logs`, `outbox_messages`.
5. Telegram-таблицы и хранилище сообщений/уведомлений.

Каждая миграция должна иметь обратимый `down()`, внешние ключи и индексы из этого документа. Изменение суммы или статуса исторической записи допускается только через явно документированную бизнес-операцию.
