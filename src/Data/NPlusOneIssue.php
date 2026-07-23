<?php

declare(strict_types=1);

namespace EloquentQuerySpy\EloquentQuerySpy\Data;

class NPlusOneIssue
{
    /**
     * @param  list<QueryExecution>  $queries
     */
    public function __construct(
        public readonly string $sql,
        public readonly string $table,
        public readonly ?string $relation,
        public readonly int $count,
        public readonly string $file,
        public readonly int $line,
        public readonly string $suggestedWith,
        public readonly array $queries = [],
        public readonly ?QueryExecution $parentQuery = null,
    ) {}

    public function suggestedFix(): string
    {
        return $this->suggestedWith;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'table' => $this->table,
            'relation' => $this->relation,
            'count' => $this->count,
            'file' => $this->file,
            'line' => $this->line,
            'suggested_with' => $this->suggestedWith,
            'parent_sql' => $this->parentQuery?->sql,
            'parent_file' => $this->parentQuery?->file,
            'parent_line' => $this->parentQuery?->line,
        ];
    }
}
