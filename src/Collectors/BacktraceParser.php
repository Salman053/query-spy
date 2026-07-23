<?php

declare(strict_types=1);

namespace EloquentQuerySpy\EloquentQuerySpy\Collectors;

class BacktraceParser
{
    /** @var list<string> */
    private const array PACKAGE_CLASS_PREFIXES = [
        'EloquentQuerySpy\\EloquentQuerySpy\\',
    ];

    /** @var list<string> */
    private const array SKIP_CLASS_PREFIXES = [
        'Illuminate\\Database\\',
        'Illuminate\\Events\\',
        'Pest\\',
        'PHPUnit\\',
    ];

    /**
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
        $file = (string) ($frame['file'] ?? '');
        $class = (string) ($frame['class'] ?? '');

        foreach (self::PACKAGE_CLASS_PREFIXES as $prefix) {
            if (str_starts_with($class, $prefix)) {
                return true;
            }
        }

        foreach (self::SKIP_CLASS_PREFIXES as $prefix) {
            if (str_starts_with($class, $prefix)) {
                return true;
            }
        }

        if ($file === '') {
            return false;
        }

        $normalizedFile = str_replace('\\', '/', $file);
        $normalizedProject = str_replace('\\', '/', $projectPath);

        if (str_contains($normalizedFile, '/vendor/')) {
            return true;
        }

        if (
            ! str_starts_with($normalizedFile, $normalizedProject)
            || (strlen($normalizedFile) > strlen($normalizedProject) && $normalizedFile[strlen($normalizedProject)] !== '/')
        ) {
            return true;
        }

        $spyDir = str_replace('\\', '/', __DIR__);

        if (str_starts_with($normalizedFile, $spyDir)) {
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
        $normalizedPath = str_replace('\\', '/', $path);
        $normalizedProject = str_replace('\\', '/', $projectPath);

        if (str_starts_with($normalizedPath, $normalizedProject)) {
            $relative = substr($normalizedPath, strlen($normalizedProject));
            $relative = ltrim($relative, '/\\');

            $vendorPos = strpos($relative, 'vendor/');

            if ($vendorPos !== false) {
                return substr($relative, $vendorPos + 7);
            }

            return $relative;
        }

        return $normalizedPath;
    }

    private function detectProjectPath(): string
    {
        if (function_exists('base_path')) {
            $base = base_path();

            return str_replace('\\', '/', $base);
        }

        $base = __DIR__;
        $parts = explode(DIRECTORY_SEPARATOR, $base);

        $vendorIndex = null;
        foreach ($parts as $i => $part) {
            if ($part === 'vendor') {
                $vendorIndex = $i;

                break;
            }
        }

        if ($vendorIndex !== null) {
            return implode(DIRECTORY_SEPARATOR, array_slice($parts, 0, $vendorIndex));
        }

        for ($i = 0; $i < 5; $i++) {
            $base = dirname($base);
        }

        return str_replace('\\', '/', $base);
    }
}
