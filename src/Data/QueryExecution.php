<?php

declare(strict_types=1);

namespace EloquentQuerySpy\EloquentQuerySpy\Data;

class QueryExecution
{
    /**
     * @param  list<mixed>  $bindings
     */
    public function __construct(
        public readonly string $sql,
        public readonly array $bindings,
        public readonly float $time,
        public readonly string $connection,
        public readonly ?string $file = null,
        public readonly ?int $line = null,
        public readonly ?string $caller = null,
        public readonly ?string $hash = null,
        public readonly ?float $memoryBefore = null,
        public readonly ?float $memoryAfter = null,
        public readonly ?int $occurrence = null,
    ) {}

    public function memoryUsage(): ?float
    {
        if ($this->memoryBefore === null || $this->memoryAfter === null) {
            return null;
        }

        return $this->memoryAfter - $this->memoryBefore;
    }

    public function isSlow(int $thresholdMs = 100): bool
    {
        return $this->time > $thresholdMs;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sql' => $this->sql,
            'bindings' => $this->bindings,
            'time' => $this->time,
            'connection' => $this->connection,
            'file' => $this->file,
            'line' => $this->line,
            'caller' => $this->caller,
            'hash' => $this->hash,
            'memory_before' => $this->memoryBefore,
            'memory_after' => $this->memoryAfter,
            'occurrence' => $this->occurrence,
        ];
    }
}
