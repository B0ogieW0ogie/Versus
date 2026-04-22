<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /** @var list<string> */
    private const USER_EMAILS = [
        'alice@demo.test',
        'bob@demo.test',
        'carol@demo.test',
        'degen99@demo.test',
        'moviebuff@demo.test',
        'catperson@demo.test',
        'luckyx@demo.test',
        'pixel@demo.test',
    ];

    /** @var list<string> */
    private const BATTLE_SLUGS = [
        'messi-vs-ronaldo',
        'marvel-vs-dc',
        'star-wars-vs-star-trek',
        'cats-vs-dogs',
        'breaking-bad-vs-sopranos',
        'iron-man-vs-batman',
        'pizza-vs-sushi',
        'lotr-vs-got',
        'summer-vs-winter',
        'beatles-vs-stones',
        'python-vs-js',
        'godfather-vs-citizen-kane',
    ];

    public function up(): void
    {
        // Skip in tests only — real DBs (local + prod) should receive demo data.
        if (app()->environment('testing')) {
            return;
        }

        $now = now();

        foreach ($this->categories() as $c) {
            DB::table('categories')->updateOrInsert(
                ['slug' => $c['slug']],
                $c + ['created_at' => $now, 'updated_at' => $now],
            );
        }
        $catId = DB::table('categories')->pluck('id', 'slug')->all();

        foreach ($this->users() as $u) {
            DB::table('users')->updateOrInsert(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => Hash::make('password'),
                    'balance' => $u['balance'],
                    'referral_code' => $u['code'],
                    'is_admin' => false,
                    'email_verified_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
        $userId = DB::table('users')
            ->whereIn('email', self::USER_EMAILS)
            ->pluck('id', 'email')
            ->all();

        foreach ($this->activeBattles() as $b) {
            DB::table('battles')->updateOrInsert(
                ['slug' => $b['slug']],
                [
                    'title' => $b['title'],
                    'description' => $b['description'] ?? null,
                    'side_a_label' => $b['a'],
                    'side_b_label' => $b['b'],
                    'side_a_subtitle' => $b['a_sub'] ?? null,
                    'side_b_subtitle' => $b['b_sub'] ?? null,
                    'side_a_image' => null,
                    'side_b_image' => null,
                    'status' => 'active',
                    'opens_at' => $now->copy()->addHours($b['opens']),
                    'closes_at' => $now->copy()->addHours($b['closes']),
                    'winning_side' => null,
                    'total_pool' => $b['pool'],
                    'settled_at' => null,
                    'category_id' => isset($b['category']) ? ($catId[$b['category']] ?? null) : null,
                    'is_featured' => ! empty($b['featured']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        foreach ($this->settledBattles() as $b) {
            DB::table('battles')->updateOrInsert(
                ['slug' => $b['slug']],
                [
                    'title' => $b['title'],
                    'description' => null,
                    'side_a_label' => $b['a'],
                    'side_b_label' => $b['b'],
                    'side_a_subtitle' => null,
                    'side_b_subtitle' => null,
                    'side_a_image' => null,
                    'side_b_image' => null,
                    'status' => 'settled',
                    'opens_at' => $now->copy()->subDays($b['settled_days_ago'] + 7),
                    'closes_at' => $now->copy()->subDays($b['settled_days_ago']),
                    'winning_side' => $b['winning_side'],
                    'total_pool' => $b['pool'],
                    'settled_at' => $now->copy()->subDays($b['settled_days_ago']),
                    'category_id' => isset($b['category']) ? ($catId[$b['category']] ?? null) : null,
                    'is_featured' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $demoBattleIds = DB::table('battles')
            ->whereIn('slug', self::BATTLE_SLUGS)
            ->pluck('id', 'slug')
            ->all();

        // Reset demo votes for idempotency across re-runs.
        DB::table('votes')
            ->whereIn('battle_id', array_values($demoBattleIds))
            ->whereIn('user_id', array_values($userId))
            ->delete();

        foreach ($this->settledBattles() as $b) {
            $bid = $demoBattleIds[$b['slug']] ?? null;
            if ($bid === null) {
                continue;
            }
            foreach ($b['payouts'] as $p) {
                if (! isset($userId[$p['email']])) {
                    continue;
                }
                DB::table('votes')->insert([
                    'user_id' => $userId[$p['email']],
                    'battle_id' => $bid,
                    'side' => $p['side'],
                    'amount' => $p['amount'],
                    'weight' => $p['amount'],
                    'referrer_id' => null,
                    'payout' => $p['payout'],
                    'created_at' => $now->copy()->subDays($b['settled_days_ago'] + 1),
                    'updated_at' => $now->copy()->subDays($b['settled_days_ago']),
                ]);
            }
        }

        foreach ($this->activeVotes() as $v) {
            $bid = $demoBattleIds[$v['slug']] ?? null;
            if ($bid === null || ! isset($userId[$v['email']])) {
                continue;
            }
            DB::table('votes')->insert([
                'user_id' => $userId[$v['email']],
                'battle_id' => $bid,
                'side' => $v['side'],
                'amount' => $v['amount'],
                'weight' => $v['amount'],
                'referrer_id' => null,
                'payout' => null,
                'created_at' => $now->copy()->subHours(6),
                'updated_at' => $now->copy()->subHours(6),
            ]);
        }
    }

    public function down(): void
    {
        $userIds = DB::table('users')->whereIn('email', self::USER_EMAILS)->pluck('id')->all();
        $battleIds = DB::table('battles')->whereIn('slug', self::BATTLE_SLUGS)->pluck('id')->all();

        if (! empty($battleIds)) {
            DB::table('votes')->whereIn('battle_id', $battleIds)->delete();
        }
        if (! empty($userIds)) {
            DB::table('votes')->whereIn('user_id', $userIds)->delete();
        }

        DB::table('battles')->whereIn('slug', self::BATTLE_SLUGS)->delete();
        DB::table('users')->whereIn('email', self::USER_EMAILS)->delete();
    }

    /**
     * @return list<array{slug: string, name_en: string, name_ru: string, sort_order: int}>
     */
    private function categories(): array
    {
        return [
            ['slug' => 'sports', 'name_en' => 'Sports', 'name_ru' => 'Спорт', 'sort_order' => 10],
            ['slug' => 'memes', 'name_en' => 'Memes', 'name_ru' => 'Мемы', 'sort_order' => 20],
            ['slug' => 'movies', 'name_en' => 'Movies', 'name_ru' => 'Фильмы', 'sort_order' => 30],
            ['slug' => 'superheroes', 'name_en' => 'Superheroes', 'name_ru' => 'Супергерои', 'sort_order' => 40],
            ['slug' => 'tv-shows', 'name_en' => 'TV Shows', 'name_ru' => 'Сериалы', 'sort_order' => 50],
        ];
    }

    /**
     * @return list<array{email: string, name: string, balance: float, code: string}>
     */
    private function users(): array
    {
        return [
            ['email' => 'alice@demo.test', 'name' => 'alice_pro', 'balance' => 12450.00, 'code' => 'DEMOALICE'],
            ['email' => 'bob@demo.test', 'name' => 'bearfan', 'balance' => 8900.00, 'code' => 'DEMOBOB'],
            ['email' => 'carol@demo.test', 'name' => 'carol', 'balance' => 7320.00, 'code' => 'DEMOCAROL'],
            ['email' => 'degen99@demo.test', 'name' => 'degen99', 'balance' => 4210.00, 'code' => 'DEMODEGEN'],
            ['email' => 'moviebuff@demo.test', 'name' => 'movie_nerd', 'balance' => 3100.00, 'code' => 'DEMOMOVIE'],
            ['email' => 'catperson@demo.test', 'name' => 'catperson', 'balance' => 2650.00, 'code' => 'DEMOCATS'],
            ['email' => 'luckyx@demo.test', 'name' => 'luckyx', 'balance' => 1480.00, 'code' => 'DEMOLUCKY'],
            ['email' => 'pixel@demo.test', 'name' => 'pixel', 'balance' => 520.00, 'code' => 'DEMOPIXEL'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function activeBattles(): array
    {
        return [
            [
                'slug' => 'messi-vs-ronaldo',
                'title' => 'Messi vs Ronaldo',
                'description' => 'The eternal GOAT debate — settle it before the next World Cup.',
                'a' => 'Messi', 'b' => 'Ronaldo',
                'a_sub' => 'La Pulga', 'b_sub' => 'CR7',
                'category' => 'sports', 'featured' => true,
                'pool' => 45000, 'opens' => -48, 'closes' => 8,
            ],
            [
                'slug' => 'marvel-vs-dc',
                'title' => 'Marvel vs DC',
                'description' => 'Which cinematic universe wins in 2026?',
                'a' => 'Marvel', 'b' => 'DC',
                'a_sub' => 'Avengers', 'b_sub' => 'Justice League',
                'category' => 'superheroes',
                'pool' => 32400, 'opens' => -24, 'closes' => 36,
            ],
            [
                'slug' => 'star-wars-vs-star-trek',
                'title' => 'Star Wars vs Star Trek',
                'description' => 'Force or Federation — pick your franchise.',
                'a' => 'Star Wars', 'b' => 'Star Trek',
                'category' => 'movies',
                'pool' => 22100, 'opens' => -36, 'closes' => 20,
            ],
            [
                'slug' => 'cats-vs-dogs',
                'title' => 'Cats vs Dogs',
                'description' => 'The internet favorite argument.',
                'a' => 'Cats', 'b' => 'Dogs',
                'category' => 'memes',
                'pool' => 18500, 'opens' => -12, 'closes' => 1,
            ],
            [
                'slug' => 'breaking-bad-vs-sopranos',
                'title' => 'Breaking Bad vs The Sopranos',
                'description' => 'Greatest TV drama of all time?',
                'a' => 'Breaking Bad', 'b' => 'The Sopranos',
                'category' => 'tv-shows',
                'pool' => 14800, 'opens' => -72, 'closes' => 60,
            ],
            [
                'slug' => 'iron-man-vs-batman',
                'title' => 'Iron Man vs Batman',
                'a' => 'Iron Man', 'b' => 'Batman',
                'category' => 'superheroes',
                'pool' => 11300, 'opens' => -20, 'closes' => 14,
            ],
            [
                'slug' => 'pizza-vs-sushi',
                'title' => 'Pizza vs Sushi',
                'a' => 'Pizza', 'b' => 'Sushi',
                'category' => 'memes',
                'pool' => 8750, 'opens' => -8, 'closes' => 10,
            ],
            [
                'slug' => 'lotr-vs-got',
                'title' => 'Lord of the Rings vs Game of Thrones',
                'a' => 'LOTR', 'b' => 'GoT',
                'category' => 'tv-shows',
                'pool' => 6420, 'opens' => -6, 'closes' => 72,
            ],
            [
                'slug' => 'summer-vs-winter',
                'title' => 'Summer vs Winter',
                'description' => 'Best season — no compromises.',
                'a' => 'Summer', 'b' => 'Winter',
                'category' => null,
                'pool' => 4280, 'opens' => -4, 'closes' => 48,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function settledBattles(): array
    {
        return [
            [
                'slug' => 'beatles-vs-stones',
                'title' => 'Beatles vs Rolling Stones',
                'a' => 'Beatles', 'b' => 'Rolling Stones',
                'category' => null,
                'pool' => 9500,
                'winning_side' => 'A',
                'settled_days_ago' => 3,
                'payouts' => [
                    ['email' => 'alice@demo.test', 'side' => 'A', 'amount' => 600, 'payout' => 4180.0],
                    ['email' => 'carol@demo.test', 'side' => 'A', 'amount' => 300, 'payout' => 2090.0],
                    ['email' => 'bob@demo.test', 'side' => 'A', 'amount' => 300, 'payout' => 2090.0],
                    ['email' => 'degen99@demo.test', 'side' => 'B', 'amount' => 500, 'payout' => null],
                ],
            ],
            [
                'slug' => 'python-vs-js',
                'title' => 'Python vs JavaScript',
                'a' => 'Python', 'b' => 'JavaScript',
                'category' => null,
                'pool' => 6700,
                'winning_side' => 'B',
                'settled_days_ago' => 7,
                'payouts' => [
                    ['email' => 'bob@demo.test', 'side' => 'B', 'amount' => 400, 'payout' => 2950.0],
                    ['email' => 'moviebuff@demo.test', 'side' => 'B', 'amount' => 250, 'payout' => 1840.0],
                    ['email' => 'luckyx@demo.test', 'side' => 'B', 'amount' => 150, 'payout' => 1100.0],
                    ['email' => 'carol@demo.test', 'side' => 'A', 'amount' => 300, 'payout' => null],
                ],
            ],
            [
                'slug' => 'godfather-vs-citizen-kane',
                'title' => 'The Godfather vs Citizen Kane',
                'a' => 'The Godfather', 'b' => 'Citizen Kane',
                'category' => 'movies',
                'pool' => 11800,
                'winning_side' => 'A',
                'settled_days_ago' => 14,
                'payouts' => [
                    ['email' => 'alice@demo.test', 'side' => 'A', 'amount' => 800, 'payout' => 5880.0],
                    ['email' => 'moviebuff@demo.test', 'side' => 'A', 'amount' => 400, 'payout' => 2940.0],
                    ['email' => 'pixel@demo.test', 'side' => 'A', 'amount' => 220, 'payout' => 1620.0],
                    ['email' => 'catperson@demo.test', 'side' => 'B', 'amount' => 300, 'payout' => null],
                ],
            ],
        ];
    }

    /**
     * @return list<array{slug: string, email: string, side: string, amount: int}>
     */
    private function activeVotes(): array
    {
        return [
            ['slug' => 'messi-vs-ronaldo', 'email' => 'alice@demo.test', 'side' => 'A', 'amount' => 500],
            ['slug' => 'messi-vs-ronaldo', 'email' => 'bob@demo.test', 'side' => 'B', 'amount' => 400],
            ['slug' => 'cats-vs-dogs', 'email' => 'alice@demo.test', 'side' => 'B', 'amount' => 200],
            ['slug' => 'cats-vs-dogs', 'email' => 'catperson@demo.test', 'side' => 'A', 'amount' => 350],
            ['slug' => 'marvel-vs-dc', 'email' => 'degen99@demo.test', 'side' => 'A', 'amount' => 600],
            ['slug' => 'star-wars-vs-star-trek', 'email' => 'moviebuff@demo.test', 'side' => 'A', 'amount' => 250],
            ['slug' => 'pizza-vs-sushi', 'email' => 'luckyx@demo.test', 'side' => 'A', 'amount' => 100],
        ];
    }
};
