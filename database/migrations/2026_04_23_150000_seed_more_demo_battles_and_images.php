<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Slugs from the earlier demo seed (2026_04_22_130000_seed_home_page_demo_data)
     * that should also receive picsum images in this pass.
     *
     * @var list<string>
     */
    private const PREVIOUS_DEMO_SLUGS = [
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
        'athlete-showdown',
    ];

    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $now = now();

        $catId = DB::table('categories')->pluck('id', 'slug')->all();

        foreach ($this->newActiveBattles() as $b) {
            DB::table('battles')->updateOrInsert(
                ['slug' => $b['slug']],
                [
                    'title' => $b['title'],
                    'description' => $b['description'] ?? null,
                    'side_a_label' => $b['a'],
                    'side_b_label' => $b['b'],
                    'side_a_subtitle' => $b['a_sub'] ?? null,
                    'side_b_subtitle' => $b['b_sub'] ?? null,
                    'side_a_image' => $this->picsum($b['slug'], 'a'),
                    'side_b_image' => $this->picsum($b['slug'], 'b'),
                    'status' => 'active',
                    'opens_at' => $now->copy()->addHours($b['opens']),
                    'closes_at' => $now->copy()->addHours($b['closes']),
                    'winning_side' => null,
                    'total_pool' => $b['pool'],
                    'settled_at' => null,
                    'category_id' => isset($b['category']) ? ($catId[$b['category']] ?? null) : null,
                    'is_sponsored' => false,
                    'sponsor_handle' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        foreach ($this->sponsoredBattles() as $b) {
            DB::table('battles')->updateOrInsert(
                ['slug' => $b['slug']],
                [
                    'title' => $b['title'],
                    'description' => $b['description'] ?? null,
                    'side_a_label' => $b['a'],
                    'side_b_label' => $b['b'],
                    'side_a_subtitle' => $b['a_sub'] ?? null,
                    'side_b_subtitle' => $b['b_sub'] ?? null,
                    'side_a_image' => $this->picsum($b['slug'], 'a'),
                    'side_b_image' => $this->picsum($b['slug'], 'b'),
                    'status' => 'active',
                    'opens_at' => $now->copy()->addHours($b['opens']),
                    'closes_at' => $now->copy()->addHours($b['closes']),
                    'winning_side' => null,
                    'total_pool' => $b['pool'],
                    'settled_at' => null,
                    'category_id' => isset($b['category']) ? ($catId[$b['category']] ?? null) : null,
                    'is_sponsored' => true,
                    'sponsor_handle' => $b['sponsor'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        foreach (self::PREVIOUS_DEMO_SLUGS as $slug) {
            DB::table('battles')
                ->where('slug', $slug)
                ->update([
                    'side_a_image' => $this->picsum($slug, 'a'),
                    'side_b_image' => $this->picsum($slug, 'b'),
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        $slugs = array_merge(
            array_column($this->newActiveBattles(), 'slug'),
            array_column($this->sponsoredBattles(), 'slug'),
        );

        $battleIds = DB::table('battles')->whereIn('slug', $slugs)->pluck('id')->all();

        if (! empty($battleIds)) {
            DB::table('votes')->whereIn('battle_id', $battleIds)->delete();
        }

        DB::table('battles')->whereIn('slug', $slugs)->delete();

        DB::table('battles')
            ->whereIn('slug', self::PREVIOUS_DEMO_SLUGS)
            ->update([
                'side_a_image' => null,
                'side_b_image' => null,
            ]);
    }

    private function picsum(string $slug, string $side): string
    {
        return "https://picsum.photos/seed/{$slug}-{$side}/400/400";
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function newActiveBattles(): array
    {
        return [
            // Sports
            [
                'slug' => 'tennis-vs-football',
                'title' => 'Tennis vs Football',
                'description' => 'Which sport owns the weekends?',
                'a' => 'Tennis', 'b' => 'Football',
                'category' => 'sports',
                'pool' => 7200, 'opens' => -30, 'closes' => 40,
            ],
            [
                'slug' => 'nba-vs-nfl',
                'title' => 'NBA vs NFL',
                'a' => 'NBA', 'b' => 'NFL',
                'category' => 'sports',
                'pool' => 15300, 'opens' => -18, 'closes' => 28,
            ],
            [
                'slug' => 'nadal-vs-federer',
                'title' => 'Nadal vs Federer',
                'description' => 'Clay king or grass maestro?',
                'a' => 'Nadal', 'b' => 'Federer',
                'category' => 'sports',
                'pool' => 9400, 'opens' => -22, 'closes' => 16,
            ],
            [
                'slug' => 'olympics-vs-world-cup',
                'title' => 'Olympics vs World Cup',
                'a' => 'Olympics', 'b' => 'World Cup',
                'category' => 'sports',
                'pool' => 6100, 'opens' => -10, 'closes' => 54,
            ],
            [
                'slug' => 'mma-vs-boxing',
                'title' => 'MMA vs Boxing',
                'description' => 'The combat-sport classic.',
                'a' => 'MMA', 'b' => 'Boxing',
                'category' => 'sports',
                'pool' => 12800, 'opens' => -14, 'closes' => 22,
            ],

            // Memes
            [
                'slug' => 'doge-vs-pepe',
                'title' => 'Doge vs Pepe',
                'a' => 'Doge', 'b' => 'Pepe',
                'category' => 'memes',
                'pool' => 5400, 'opens' => -9, 'closes' => 12,
            ],
            [
                'slug' => 'morning-person-vs-night-owl',
                'title' => 'Morning Person vs Night Owl',
                'a' => 'Morning Person', 'b' => 'Night Owl',
                'category' => 'memes',
                'pool' => 3900, 'opens' => -5, 'closes' => 30,
            ],
            [
                'slug' => 'coffee-vs-tea',
                'title' => 'Coffee vs Tea',
                'description' => 'Pick your poison.',
                'a' => 'Coffee', 'b' => 'Tea',
                'category' => 'memes',
                'pool' => 7100, 'opens' => -16, 'closes' => 18,
            ],
            [
                'slug' => 'stonks-vs-doomers',
                'title' => 'Stonks vs Doomers',
                'a' => 'Stonks', 'b' => 'Doomers',
                'category' => 'memes',
                'pool' => 2650, 'opens' => -3, 'closes' => 44,
            ],
            [
                'slug' => 'rickroll-vs-nyan-cat',
                'title' => 'Rickroll vs Nyan Cat',
                'a' => 'Rickroll', 'b' => 'Nyan Cat',
                'category' => 'memes',
                'pool' => 4200, 'opens' => -7, 'closes' => 26,
            ],

            // Movies
            [
                'slug' => 'inception-vs-interstellar',
                'title' => 'Inception vs Interstellar',
                'description' => 'Nolan head-to-head.',
                'a' => 'Inception', 'b' => 'Interstellar',
                'category' => 'movies',
                'pool' => 10800, 'opens' => -20, 'closes' => 24,
            ],
            [
                'slug' => 'matrix-vs-blade-runner',
                'title' => 'The Matrix vs Blade Runner',
                'a' => 'The Matrix', 'b' => 'Blade Runner',
                'category' => 'movies',
                'pool' => 8400, 'opens' => -26, 'closes' => 32,
            ],
            [
                'slug' => 'alien-vs-predator',
                'title' => 'Alien vs Predator',
                'a' => 'Alien', 'b' => 'Predator',
                'category' => 'movies',
                'pool' => 5900, 'opens' => -11, 'closes' => 14,
            ],
            [
                'slug' => 'pulp-fiction-vs-fight-club',
                'title' => 'Pulp Fiction vs Fight Club',
                'a' => 'Pulp Fiction', 'b' => 'Fight Club',
                'category' => 'movies',
                'pool' => 7700, 'opens' => -13, 'closes' => 36,
            ],
            [
                'slug' => 'parasite-vs-oldboy',
                'title' => 'Parasite vs Oldboy',
                'description' => 'Korean cinema showdown.',
                'a' => 'Parasite', 'b' => 'Oldboy',
                'category' => 'movies',
                'pool' => 3300, 'opens' => -6, 'closes' => 50,
            ],

            // Superheroes
            [
                'slug' => 'spiderman-vs-superman',
                'title' => 'Spider-Man vs Superman',
                'a' => 'Spider-Man', 'b' => 'Superman',
                'category' => 'superheroes',
                'pool' => 14200, 'opens' => -28, 'closes' => 20,
            ],
            [
                'slug' => 'wonder-woman-vs-captain-marvel',
                'title' => 'Wonder Woman vs Captain Marvel',
                'a' => 'Wonder Woman', 'b' => 'Captain Marvel',
                'category' => 'superheroes',
                'pool' => 6800, 'opens' => -15, 'closes' => 38,
            ],
            [
                'slug' => 'thor-vs-thanos',
                'title' => 'Thor vs Thanos',
                'description' => 'Hammer time or snap time?',
                'a' => 'Thor', 'b' => 'Thanos',
                'category' => 'superheroes',
                'pool' => 9900, 'opens' => -21, 'closes' => 10,
            ],
            [
                'slug' => 'x-men-vs-avengers',
                'title' => 'X-Men vs Avengers',
                'a' => 'X-Men', 'b' => 'Avengers',
                'category' => 'superheroes',
                'pool' => 11700, 'opens' => -17, 'closes' => 46,
            ],
            [
                'slug' => 'deadpool-vs-wolverine',
                'title' => 'Deadpool vs Wolverine',
                'a' => 'Deadpool', 'b' => 'Wolverine',
                'category' => 'superheroes',
                'pool' => 8150, 'opens' => -9, 'closes' => 8,
            ],

            // TV Shows
            [
                'slug' => 'friends-vs-seinfeld',
                'title' => 'Friends vs Seinfeld',
                'a' => 'Friends', 'b' => 'Seinfeld',
                'category' => 'tv-shows',
                'pool' => 6300, 'opens' => -19, 'closes' => 34,
            ],
            [
                'slug' => 'the-office-vs-parks-and-rec',
                'title' => 'The Office vs Parks and Rec',
                'a' => 'The Office', 'b' => 'Parks and Rec',
                'category' => 'tv-shows',
                'pool' => 7450, 'opens' => -12, 'closes' => 16,
            ],
            [
                'slug' => 'stranger-things-vs-dark',
                'title' => 'Stranger Things vs Dark',
                'description' => 'Sci-fi mystery heavyweights.',
                'a' => 'Stranger Things', 'b' => 'Dark',
                'category' => 'tv-shows',
                'pool' => 4980, 'opens' => -8, 'closes' => 28,
            ],
            [
                'slug' => 'succession-vs-mad-men',
                'title' => 'Succession vs Mad Men',
                'a' => 'Succession', 'b' => 'Mad Men',
                'category' => 'tv-shows',
                'pool' => 3200, 'opens' => -5, 'closes' => 52,
            ],
            [
                'slug' => 'dexter-vs-mindhunter',
                'title' => 'Dexter vs Mindhunter',
                'a' => 'Dexter', 'b' => 'Mindhunter',
                'category' => 'tv-shows',
                'pool' => 2800, 'opens' => -4, 'closes' => 42,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sponsoredBattles(): array
    {
        return [
            [
                'slug' => 'nike-vs-adidas',
                'title' => 'Nike vs Adidas',
                'description' => 'Streetwear loyalty test.',
                'a' => 'Nike', 'b' => 'Adidas',
                'a_sub' => 'Just do it', 'b_sub' => 'Impossible is nothing',
                'category' => 'sports',
                'sponsor' => '@brandshop',
                'pool' => 28700, 'opens' => -36, 'closes' => 18,
            ],
            [
                'slug' => 'iphone-vs-android',
                'title' => 'iPhone vs Android',
                'description' => 'The forever tribal war.',
                'a' => 'iPhone', 'b' => 'Android',
                'category' => 'memes',
                'sponsor' => '@gadgetpro',
                'pool' => 33500, 'opens' => -40, 'closes' => 24,
            ],
            [
                'slug' => 'netflix-vs-hbo',
                'title' => 'Netflix vs HBO',
                'description' => 'Prestige TV showdown.',
                'a' => 'Netflix', 'b' => 'HBO',
                'category' => 'tv-shows',
                'sponsor' => '@streamhub',
                'pool' => 21200, 'opens' => -30, 'closes' => 12,
            ],
        ];
    }
};
