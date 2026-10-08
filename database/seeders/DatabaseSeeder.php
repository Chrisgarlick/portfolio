<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the development database.
     *
     * The live site's content from its last build, plus the two repositioned
     * collections (projects and services) that have no live content yet.
     * Model events stay on: the observer renders rich text and records slugs
     * on save, and without it the pages would have no HTML to show.
     */
    public function run(): void
    {
        $this->call([
            LiveBuildSeeder::class,
            ProjectSeeder::class,
            ServiceSeeder::class,
        ]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
