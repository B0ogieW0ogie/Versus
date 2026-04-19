# Versus MVP (V1) — план реализации

## Context

Репозиторий `/Users/mrcloud/code/versus/` пустой (только `README.md` с визиткой проекта).
Нужно поднять с нуля MVP off-chain платформы «баттлов»: админ создаёт баттл A vs B,
пользователи ставят виртуальные токены на сторону, пишут комментарии, делятся
реферальными ссылками. При закрытии баттла пул распределяется по формуле из README
(88% победителям / 5% проект / 3% burn / 4% reward pool).

Решения пользователя:
- **Стек:** Laravel + Blade + Livewire 3 + Alpine.js + Tailwind
- **Scope V1:** Core (battles, voting, pool, settlement, payouts) + комментарии + реферальные ссылки. Скрытое голосование и анти-кит формула — **не в V1** (закладываем архитектурный задел, но `weight = amount`, пул открыт).
- **Баттлы создаёт только админ** (через админ-панель Filament).
- **Auth:** email+пароль через Laravel Breeze (Blade stack).

Явно **НЕ в V1:** on-chain интеграция, стриминг, скрытое голосование, анти-кит формула, модерация пользовательских баттлов, мобильное приложение.

---

## Технологический стек

| Компонент | Выбор | Причина |
|---|---|---|
| Инфра | Docker Compose (nginx + php-fpm + postgres + workspace) | Паттерн из `/PhpstormProjects/biggeek`, адаптирован под Laravel |
| Backend | Laravel 12 (PHP 8.3) | Последняя стабильная |
| БД | PostgreSQL 16 в контейнере | Транзакции, точные денежные типы (`numeric`) |
| Frontend | Blade + Livewire 3 + Alpine.js | По запросу пользователя, быстрый цикл разработки |
| CSS | Tailwind CSS 3 | Дефолт с Breeze |
| Auth | Laravel Breeze (Blade) | Стандарт, минимум настройки |
| Admin | Filament 3 | Готовые CRUD-ресурсы, экономит недели |
| Очереди | `database` driver в V1 | Без Redis для простоты |
| Тесты | Pest 3 (или PHPUnit 11) | Pest удобнее для feature-тестов |
| Качество | Laravel Pint + Larastan (level 6) | Стиль + статический анализ |

---

## Доменная модель

### Сущности (таблицы)

1. **`users`** (расширяем дефолтную Breeze):
   - `id, name, email, email_verified_at, password`
   - `balance` (`numeric(20,2)`) — виртуальные токены
   - `referral_code` (unique, 8 символов base32)
   - `referred_by_id` (FK `users.id`, nullable)
   - `is_admin` (bool, default false) — для V1 достаточно флага, без `spatie/permission`
   - `timestamps`

2. **`battles`**:
   - `id, slug (unique), title, description`
   - `side_a_label, side_b_label`
   - `side_a_image, side_b_image` (nullable, storage path)
   - `status` enum: `draft | active | closed | settled`
   - `opens_at, closes_at` (timestamps)
   - `winning_side` enum `A|B` nullable
   - `total_pool` (`numeric(20,2)`, денормализованная сумма — кэш)
   - `created_by_id` (FK `users.id`)
   - `settled_at` nullable
   - `timestamps`

3. **`votes`**:
   - `id, user_id, battle_id, side (A|B), amount, weight`
   - `referrer_id` (FK `users.id`, nullable — кто привёл голосующего)
   - `payout` nullable (заполняется при settle)
   - `timestamps`
   - **Unique:** `(user_id, battle_id)` — один голос на баттл в V1 (упрощает математику; в V2 можно снять).

4. **`comments`**:
   - `id, user_id, battle_id, body, side (A|B nullable), timestamps`
   - Плоские, без вложенности в V1.

5. **`transactions`** — аудит всех движений токенов:
   - `id, user_id nullable, type, amount (signed), balance_after nullable, battle_id nullable, meta jsonb, created_at`
   - `type` enum: `signup_bonus | vote_stake | vote_payout | referral_reward | project_fee | burn | reward_pool_credit | reward_pool_debit | admin_grant`
   - `user_id` nullable чтобы писать системные счета (project/burn/insurance) с `null user` + уникальным `type`.

### Системные счета

Вместо отдельных моделей `SystemLedger` — используем `transactions` с `user_id = null` и
соответствующим `type`. Баланс системы = `SUM(amount) WHERE user_id IS NULL AND type = ?`.
Это проще и даёт готовый аудит.

---

## Экономика и формулы (V1)

Даны на уровне `config/versus.php`, чтобы проценты не размазывались по коду:

```php
return [
    'signup_bonus' => 1000,
    'distribution' => [
        'winners'     => 0.88,
        'project'     => 0.05,
        'burn'        => 0.03,
        'reward_pool' => 0.04,
    ],
    'referral' => [
        // % от выигрыша реферала, идущий рефереру (из reward pool)
        'winner_cut' => 0.10,
    ],
];
```

**Settlement алгоритм** (в `App\Actions\Battles\SettleBattleAction`, всё в одной DB-транзакции):

1. Определить победившую сторону: сторона с бóльшим `SUM(weight)` среди `votes`.
2. `pool = SUM(amount)` всех голосов.
3. Начислить системные ставки:
   - `project_fee`: записать `transaction(user=null, type=project_fee, amount = +pool*0.05)`
   - `burn`: `transaction(user=null, type=burn, amount = +pool*0.03)` (токены «исчезают»)
   - `reward_pool_credit`: `+pool*0.04`
4. Для каждого голоса за победившую сторону:
   - `share = weight / SUM(winning_weights)`
   - `payout = pool * 0.88 * share`
   - Прибавить на `users.balance`, записать `vote_payout` transaction, заполнить `votes.payout`.
   - Если `referrer_id` не null и рефер не тот же юзер:
     - `ref_reward = payout * referral.winner_cut`
     - Дебет из reward pool (`reward_pool_debit` на `-ref_reward` с `user_id=null`)
     - Кредит рефереру: balance += ref_reward + transaction `referral_reward`
5. Проигравшие — стейк уже был списан при голосовании, доп. действий нет.
6. `battle.status = settled, winning_side, settled_at, total_pool = pool`.

**Точность:** все деньги — `numeric(20,2)`. В PHP держим через строки/`BCMath` или используем
`brick/money`. В V1 достаточно `numeric` в БД + round half-up до 2 знаков; остаток
(floating residue от копеек) кредитуем в reward pool.

---

## Структура кода

Основные файлы/директории, которые появятся:

```
app/
  Actions/
    Battles/
      CastVoteAction.php          # транзакционно списывает amount, создаёт Vote+Transaction
      SettleBattleAction.php      # алгоритм settle выше
  Console/Commands/
    SettleDueBattlesCommand.php   # artisan battles:settle-due
  Filament/Resources/
    BattleResource.php            # CRUD для админа
    UserResource.php              # только просмотр + ручной admin_grant
    TransactionResource.php       # read-only
  Livewire/
    BattleIndex.php               # страница списка активных баттлов
    BattleShow.php                # страница одного баттла (sides, пул, кнопка голосовать)
    VoteForm.php                  # форма ставки (amount + side)
    CommentThread.php             # список + форма комментария
    ReferralPanel.php             # профиль: своя ссылка + статы
  Models/
    Battle.php
    Comment.php
    Transaction.php
    User.php           # расширяем (balance, referral_code, is_admin)
    Vote.php
  Providers/
    AppServiceProvider.php        # Gate::define('admin', ...)
config/
  versus.php                      # экономика (проценты, бонус, реферальный %)
database/
  migrations/                     # users_add_versus_fields, battles, votes, comments, transactions
  seeders/
    DatabaseSeeder.php            # админ + 1-2 демо-баттла
routes/
  web.php                         # публичные страницы + профиль
  console.php                     # schedule: battles:settle-due каждую минуту
resources/views/
  livewire/*                      # шаблоны компонентов
  layouts/app.blade.php           # общий layout
tests/
  Feature/
    RegistrationTest.php          # bonus, referral attribution
    VoteTest.php                  # стейк, баланс, уникальность голоса
    SettlementTest.php            # 88/5/3/4, пропорциональные выплаты
    ReferralPayoutTest.php        # реферер получает только если реферал выиграл
```

---

## Поэтапный план реализации

### Этап 0 — Инициализация ✅ ГОТОВО

- [x] Docker-инфра по образцу biggeek: `docker-compose.yml` + `.docker/{php,nginx,workspace}/Dockerfile` + конфиги
- [x] nginx (80→host 8080) → php-fpm (PHP 8.3 + pdo_pgsql, bcmath, redis, gd, zip, intl) → postgres:16-alpine → workspace (CLI: composer, node 22, npm)
- [x] `composer create-project laravel/laravel` внутри workspace-контейнера, файлы смонтированы в `./`
- [x] `.env` настроен на pgsql (host=`postgres`, db=`versus`, user=`versus`)
- [x] `php artisan migrate` прошёл на Postgres
- [x] `Makefile` c шорткатами: `make up`, `make art CMD="..."`, `make test`, `make ws`
- [ ] Установить: `laravel/breeze`, `livewire/livewire`, `filament/filament`, `pestphp/pest`, `larastan/larastan`, `laravel/pint`
- [ ] `php artisan breeze:install blade` (с Alpine, Tailwind)
- [ ] `config/versus.php` с экономикой
- [ ] Git init + первый коммит скелета

**Как работаем дальше:** все команды выполняем через контейнер workspace — либо `make art CMD="..."`, либо `make ws` и уже внутри контейнера. Хост-машине PHP/Composer/Node не нужны.

### Этап 1 — Пользователи и аутентификация ✅ ГОТОВО

- [x] Миграция `users_add_versus_fields`: `balance`, `referral_code`, `referred_by_id`, `is_admin`
- [x] В `RegisteredUserController` (Breeze): генерация `referral_code`, начисление signup bonus через `Transaction`
- [x] Middleware `CaptureReferralCode` — сохраняет `?ref=CODE` из query в cookie на 30 дней; при регистрации подтягивает `referred_by_id`
- [x] `Gate::define('admin', fn($u) => $u->is_admin)` + middleware `can:admin` для Filament
- [x] Feature test: `RegistrationTest` (бонус начислен, реф привязан)

### Этап 2 — Доменные модели и админ-панель ✅ ГОТОВО

- [x] Миграции `battles`, `votes`, `comments`, `transactions` (+ индексы `(battle_id, side)` на `votes`, `(user_id, battle_id)` unique)
- [x] Модели + relations + factories
- [x] Установить Filament 5, создать `BattleResource` (CRUD со статусами, расписанием, картинками), `UserResource` (list + action «выдать токены» — создаёт `admin_grant` транзакцию), `TransactionResource` (read-only список)
- [x] Сидер: админ-юзер + 2 демо-баттла (Pepsi vs Coca-Cola, Vim vs Emacs)

### Этап 3 — Публичный UX ✅ ГОТОВО

- [x] Layout с хедером (баланс, реф-ссылка, вход/выход)
- [x] `BattleIndex` Livewire: активные + последние 10 завершённых баттлов
- [x] `BattleShow` Livewire: стороны A/B с картинками, `total_pool`, суммы/голоса по сторонам, отображение `closes_at`
- [x] Форма голоса внутри `BattleShow`: инпут amount + radio выбор стороны → `CastVoteAction`
  - Валидация: `status=active`, `now < closes_at`, `amount <= user.balance`, голоса у этого юзера в этом баттле ещё нет
- [x] Форма комментария внутри `BattleShow`: body + опциональная сторона
- [x] Страница `/referrals` (`ReferralPanel`): копируемая ссылка `/?ref=XXXXXXXX`, список приведённых, сумма `referral_reward` транзакций

### Этап 4 — Settlement ✅ ГОТОВО

- [x] `SettleBattleAction` — инвокабельный класс, всё в `DB::transaction` с `lockForUpdate`
- [x] `SettleDueBattlesCommand` — находит `status=active AND closes_at <= now()`, переводит в `closed`, затем выполняет settle
- [x] `routes/console.php`: `Schedule::command('battles:settle-due')->everyMinute()->withoutOverlapping()`
- [x] Кнопка «Settle now» в Filament (EditBattle Action) — только для Active/Closed
- [x] Feature тесты (Pest):
  - `SettlementTest::winner_side_gets_88_percent_proportionally`
  - `SettlementTest::project_burn_reward_pool_receive_correct_shares`
  - `SettlementTest::losers_dont_get_payout`
  - `SettlementTest::tie_refunds_all_stakes`
  - `SettlementTest::empty_pool_settles_without_error`
  - `SettlementTest::command_flips_active_to_closed_then_settles`
  - `ReferralPayoutTest::referrer_gets_cut_from_reward_pool_when_referee_wins`
  - `ReferralPayoutTest::referrer_gets_nothing_when_referee_loses`
  - `ReferralPayoutTest::ref_reward_capped_at_current_reward_pool`
  - `ReferralPayoutTest::self_referral_pays_no_bonus`

### Этап 5 — Полировка и запуск ✅ ГОТОВО

- [x] `Makefile` шорткаты: `make test`, `make stan`, `make pint`, `make fresh`, `make ws`, `make art`
- [x] `README.md` расширен секцией «Dev setup (MVP)» с Docker-инструкциями
- [x] В README зафиксировано «Что НЕ входит в V1» (скрытое голосование, анти-кит, on-chain, стриминг, модерация, mobile)
- [x] Pint / Larastan level 6 — зелёные

**Замечание по инициализации:** `composer create-project` ожидает пустую директорию — текущая такой и является (в ней только `README.md`, его нужно будет временно перенести или использовать `composer create-project laravel/laravel .` если Composer позволяет на не полностью пустой директории; иначе поднять во временной папке и переместить, сохранив `README.md`).

---

## Верификация (end-to-end smoke test)

1. `composer install && npm install && npm run build`
2. `cp .env.example .env && php artisan key:generate`
3. Создать PostgreSQL БД `versus`, прописать креды в `.env`
4. `php artisan migrate --seed` — должны появиться админ + 2 демо-баттла
5. `php artisan serve` + `php artisan schedule:work` в отдельном терминале
6. Зарегистрировать **User A** через `/register` — проверить баланс = 1000 (Signup bonus transaction)
7. Скопировать реф-ссылку User A из профиля, открыть в приватном окне, зарегистрировать **User B** — проверить `users.referred_by_id` у B указывает на A
8. Войти как админ (`admin@versus.test` / seed password) → Filament → создать баттл с `closes_at = now + 3 min`, статус `active`
9. User A голосует за сторону A на 200 токенов; User B (через реферальную куку) голосует за сторону B на 300 токенов → проверить балансы (800 / 700), `total_pool` = 500
10. Оба оставляют по комментарию
11. Дождаться авто-settle (или запустить `php artisan battles:settle-due`) — проверить:
    - `battles.status = settled`, `winning_side = B`
    - User B баланс = 700 + 500·0.88 = 1140 (вся сторона B = только он)
    - User A баланс = 800 (проиграл)
    - Реферальный бонус: User A получил `B.payout * 0.10 = 44` из reward pool → баланс A = 844
    - Системные `transactions`: `project_fee=+25`, `burn=+15`, `reward_pool_credit=+20`, `reward_pool_debit=-44` (баланс reward pool = −24, т.е. в минусе — **это bug-ловушка, см. ниже**)
12. Запустить `php artisan test` — все feature-тесты зелёные
13. `./vendor/bin/pint --test && ./vendor/bin/phpstan analyse` — без ошибок

**Важный edge-case, проверить в тестах:** если `referral.winner_cut * выплата > текущий reward pool` — ограничить `ref_reward = min(ref_reward, reward_pool_balance)`, чтобы пул не ушёл в минус. Иначе система должна дотировать рефералку из project fee или из 88% (но последнее ломает пропорциональность победителям). Правило для V1: «платим сколько есть в reward pool, остаток не выплачивается». Отразить в тесте.

---

## Критические файлы, которые нужно создать (сводка)

- [config/versus.php](../config/versus.php) — экономика, центральный источник правды
- [app/Actions/Battles/CastVoteAction.php](../app/Actions/Battles/CastVoteAction.php)
- [app/Actions/Battles/SettleBattleAction.php](../app/Actions/Battles/SettleBattleAction.php) — самый нагруженный файл, 100% покрытие тестами
- [app/Console/Commands/SettleDueBattlesCommand.php](../app/Console/Commands/SettleDueBattlesCommand.php)
- [app/Livewire/BattleShow.php](../app/Livewire/BattleShow.php) + `VoteForm.php` + `CommentThread.php`
- [app/Filament/Resources/BattleResource.php](../app/Filament/Resources/BattleResource.php)
- Миграции в [database/migrations/](../database/migrations/)
- Тесты в [tests/Feature/](../tests/Feature/) (особенно `SettlementTest`, `ReferralPayoutTest`)

## Ориентировочная трудоёмкость

~4–5 рабочих дней для одного разработчика до состояния «рабочий локальный MVP с тестами».
Деплой на staging (Forge/Laravel Cloud) — ещё 0.5 дня сверху.
