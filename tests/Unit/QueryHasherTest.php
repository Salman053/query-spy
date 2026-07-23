<?php

declare(strict_types=1);

use EloquentQuerySpy\EloquentQuerySpy\Collectors\QueryHasher;

it('normalizes whitespace in SQL', function () {
    $hasher = new QueryHasher;

    $sql1 = 'SELECT * FROM users WHERE id = ?';
    $sql2 = 'SELECT   *    FROM   users   WHERE   id   =   ?';

    expect($hasher->hash($sql1))->toBe($hasher->hash($sql2));
});

it('normalizes backtick-quoted identifiers', function () {
    $hasher = new QueryHasher;

    $sql1 = 'SELECT * FROM `users` WHERE `id` = ?';
    $sql2 = 'SELECT * FROM users WHERE id = ?';

    $n1 = $hasher->normalize($sql1);
    $n2 = $hasher->normalize($sql2);

    expect($n1)->toBe($n2);
});

it('normalizes IN clauses with varying placeholders', function () {
    $hasher = new QueryHasher;

    $sql1 = 'SELECT * FROM posts WHERE id IN (?, ?, ?)';
    $sql2 = 'SELECT * FROM posts WHERE id IN (?)';

    $n1 = $hasher->normalize($sql1);
    $n2 = $hasher->normalize($sql2);

    expect($n1)->toBe($n2);
});

it('extracts table name from SELECT', function () {
    $hasher = new QueryHasher;

    expect($hasher->extractTable('SELECT * FROM users'))->toBe('users');
});

it('extracts table name from INSERT', function () {
    $hasher = new QueryHasher;

    expect($hasher->extractTable('INSERT INTO logs (message) VALUES (?)'))->toBe('logs');
});

it('extracts table name from UPDATE', function () {
    $hasher = new QueryHasher;

    expect($hasher->extractTable('UPDATE posts SET title = ? WHERE id = ?'))->toBe('posts');
});

it('extracts table name from JOIN', function () {
    $hasher = new QueryHasher;

    expect($hasher->extractTable('SELECT * FROM users JOIN profiles ON users.id = profiles.user_id'))->toBe('users');
});

it('returns null for SELECT without FROM', function () {
    $hasher = new QueryHasher;

    expect($hasher->extractTable('SELECT 1'))->toBeNull();
});

it('produces consistent hashes for semantically identical queries', function () {
    $hasher = new QueryHasher;

    $hash1 = $hasher->hash('SELECT * FROM users WHERE status = ? AND type = ?');
    $hash2 = $hasher->hash('SELECT * FROM users WHERE status = ? AND type = ?');

    expect($hash1)->toBe($hash2);
});

it('produces different hashes for different queries', function () {
    $hasher = new QueryHasher;

    $hash1 = $hasher->hash('SELECT * FROM users');
    $hash2 = $hasher->hash('SELECT * FROM posts');

    expect($hash1)->not->toBe($hash2);
});
