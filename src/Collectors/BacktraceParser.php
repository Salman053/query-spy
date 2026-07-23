<?php

declare(strict_types=1);

namespace EloquentQuerySpy\EloquentQuerySpy\Collectors;

class BacktraceParser
{
    private const string VENDOR_WIN = 'vendor\\';

    private const string VENDOR_UNIX = 'vendor/';

    /**
     * Parse a debug_backtrace array to find the application caller.
     *
     * @param  list<array{file?: string, line?: int, class?: string, function?: string, type?: string}>  $backtrace
     * @return array{0: string|null, 1: int|null, 2: string|null}
     */
    public function parse(array $backtrace, ?string $projectPath = null): array
    {
        $projectPath ??= $this->detectProjectPath();

        $found = null;

        foreach ($backtrace as $frame) {
            if ($this->isInternalFrame($frame, $projectPath)) {
                continue;
            }

            $found = $frame;

            break;
        }

        if ($found === null && isset($backtrace[0])) {
            $found = $backtrace[0];
        }

        if ($found === null) {
            return [null, null, null];
        }

        $file = $found['file'] ?? null;
        $line = $found['line'] ?? null;
        $caller = $this->formatCaller($found);

        if ($file !== null) {
            $file = $this->normalizePath($file, $projectPath);
        }

        return [$file, $line, $caller];
    }

    /**
     * @param  array{file?: string, class?: string}  $frame
     */
    private function isInternalFrame(array $frame, string $projectPath): bool
    {
        $file = $frame['file'] ?? '';

        if ($file === '') {
            return false;
        }

        $normalizedFile = str_replace('\\', '/', $file);
        $normalizedProject = str_replace('\\', '/', $projectPath);

        if (! str_starts_with($normalizedFile, $normalizedProject)) {
            return true;
        }

        if (str_contains($normalizedFile, self::VENDOR_WIN) || str_contains($normalizedFile, self::VENDOR_UNIX)) {
            return true;
        }

        return false;
    }

    /**
     * @param  array{class?: string, type?: string, function?: string}  $frame
     */
    private function formatCaller(array $frame): ?string
    {
        $class = $frame['class'] ?? null;
        $type = $frame['type'] ?? null;
        $function = $frame['function'] ?? null;

        if ($class === null && $function === null) {
            return null;
        }

        if ($class !== null) {
            $lastBackslash = strrpos($class, '\\');
            $shortClass = $lastBackslash !== false
                ? substr($class, $lastBackslash + 1)
                : $class;

            return $shortClass.($type ?? '::').($function ?? '');
        }

        return $function;
    }

    private function normalizePath(string $path, string $projectPath): string
    {
        $path = str_replace('\\', '/', $path);
        $projectPath = str_replace('\\', '/', $projectPath);

        if (str_starts_with($path, $projectPath)) {
            return substr($path, strlen($projectPath) + 1);
        }

        return $path;
    }

    private function detectProjectPath(): string
    {
        $base = __DIR__;

        for ($i = 0; $i < 5; $i++) {
            $base = dirname($base);
        }

        return str_replace('\\', '/', $base);
    }
}
