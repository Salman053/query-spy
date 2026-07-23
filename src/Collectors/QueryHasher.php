<?php

declare(strict_types=1);

namespace EloquentQuerySpy\EloquentQuerySpy\Collectors;

class QueryHasher
{
    public function hash(string $sql): string
    {
        $normalized = $this->normalize($sql);

        return md5($normalized);
    }

    public function normalize(string $sql): string
    {
        $sql = preg_replace('/\s+/', ' ', $sql);

        $sql = preg_replace('/`([^`]+)`/', '$1', $sql);

        $sql = preg_replace('/\bin\s*\([^)]+\)/i', 'in (?)', $sql);

        $sql = trim($sql);

        return $sql;
    }

    public function extractTable(string $sql): ?string
    {
        if (preg_match('/\b(?:from|into|update|table|join)\s+[`"]?(\w+)[`"]?\b/i', $sql, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
