<?php

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('users')->insert(['name' => 'Alice', 'email' => 'alice@test.com', 'password' => Hash::make('password'), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('users')->insert(['name' => 'Bob', 'email' => 'bob@test.com', 'password' => Hash::make('password'), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('users')->insert(['name' => 'Charlie', 'email' => 'charlie@test.com', 'password' => Hash::make('password'), 'created_at' => now(), 'updated_at' => now()]);

        DB::table('posts')->insert(['user_id' => 1, 'title' => 'Alice Post 1', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('posts')->insert(['user_id' => 1, 'title' => 'Alice Post 2', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('posts')->insert(['user_id' => 2, 'title' => 'Bob Post 1', 'created_at' => now(), 'updated_at' => now()]);

        DB::table('comments')->insert(['post_id' => 1, 'body' => 'Great post!', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('comments')->insert(['post_id' => 1, 'body' => 'Thanks!', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('comments')->insert(['post_id' => 3, 'body' => 'Nice!', 'created_at' => now(), 'updated_at' => now()]);
    }
}
