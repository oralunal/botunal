<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class WikiSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->call('dbd:sync-wiki', ['--tier' => 'all']);
    }
}
