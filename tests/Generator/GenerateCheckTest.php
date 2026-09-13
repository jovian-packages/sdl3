<?php

declare(strict_types=1);

/*
| The generator's own invariants: the tree matches its output, the output is
| idempotent, and every generated body is exactly one extension call.
|
| The last one is the projection law. It is the line between this package and
| venusian-sdl3, and it is only a rule if something checks it.
*/

use Jovian\Bindings\Sdl3\Generator\EnumFamily;
use Jovian\Bindings\Sdl3\Generator\HeaderIndex;

function packageRoot(): string
{
    return dirname(__DIR__, 2);
}

function projectionSources(): array
{
    $root = packageRoot() . '/src';
    $out = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.php')
            && !str_contains($file->getPathname(), '/Enums/')) {
            $out[] = $file->getPathname();
        }
    }
    sort($out);

    return $out;
}

it('emits a body that is exactly one extension call', function (): void {
    $checked = 0;
    foreach (projectionSources() as $file) {
        $text = file_get_contents($file);
        if (!preg_match('/use\s+Sdl3\\\\[A-Za-z0-9_\\\\]+\s+as\s+(Ext[A-Za-z0-9_]+)\s*;/', $text, $alias)) {
            continue;
        }
        foreach (explode('public static function ', $text) as $index => $chunk) {
            if ($index === 0) {
                continue;
            }
            $body = explode("\n    }", $chunk)[0];
            $name = (preg_match('/^(\w+)/', $chunk, $m) ? $m[1] : '?');
            $calls = preg_match_all('/' . preg_quote($alias[1], '/') . '::/', $body);
            expect($calls)->toBe(1, basename($file) . '::' . $name . ' makes ' . $calls . ' extension calls');
            $checked++;
        }
    }
    expect($checked)->toBeGreaterThan(700);
});

it('keeps the checked-in tree identical to the generator output', function (): void {
    sdl3RequireExtension();

    $php = PHP_BINARY;
    $script = packageRoot() . '/scripts/generate.php';
    exec(escapeshellarg($php) . ' ' . escapeshellarg($script) . ' --check 2>&1', $output, $status);

    expect($status)->toBe(0, "generate.php --check reported drift:\n" . implode("\n", $output));
    expect(implode("\n", $output))->toContain('GEN_OK');
    expect(implode("\n", $output))->toContain('written=0');
    expect(implode("\n", $output))->toContain('stale=0');
});

it('strips a family prefix back to an underscore boundary', function (): void {
    expect(EnumFamily::commonPrefix(['SDL_SCALEMODE_NEAREST', 'SDL_SCALEMODE_LINEAR']))
        ->toBe('SDL_SCALEMODE_');

    // One intruder must not drag the prefix back to SDL_ — that is the bug
    // SDL_WINDOWPOS_UNDEFINED_MASK caused among the SDL_WINDOW_ flags.
    expect(EnumFamily::commonPrefix(['SDL_WINDOW_HIDDEN', 'SDL_WINDOWPOS_UNDEFINED_MASK']))
        ->toBe('SDL_');
});

it('keeps a family to its majority prefix', function (): void {
    $run = [
        'SDL_WINDOW_FULLSCREEN' => '1',
        'SDL_WINDOW_HIDDEN' => '8',
        'SDL_WINDOWPOS_UNDEFINED_MASK' => '0x1FFF0000u',
    ];
    $kept = HeaderIndex::majorityFamily($run);

    expect(array_keys($kept))->toBe(['SDL_WINDOW_FULLSCREEN', 'SDL_WINDOW_HIDDEN']);
});

it('evaluates the C constant expressions SDL writes', function (): void {
    expect(HeaderIndex::evaluate('0x00000020u', []))->toBe(32);
    expect(HeaderIndex::evaluate('SDL_UINT64_C(0x0000000000000008)', []))->toBe(8);
    expect(HeaderIndex::evaluate('(1u << 4)', []))->toBe(16);
    expect(HeaderIndex::evaluate('SDL_KNOWN | 2', ['SDL_KNOWN' => 1]))->toBe(3);
    // Anything it cannot evaluate must say so rather than guess.
    expect(HeaderIndex::evaluate('SDL_DEFINE_PIXELFOURCC(a, b, c, d)', []))->toBeNull();
});

it('splits a C declarator into name and type', function (): void {
    expect(HeaderIndex::splitDeclarator('const char *title'))->toBe(['title', 'char*']);
    expect(HeaderIndex::splitDeclarator('SDL_WindowFlags flags'))->toBe(['flags', 'SDL_WindowFlags']);
    expect(HeaderIndex::splitDeclarator('SDL_Window *window'))->toBe(['window', 'SDL_Window*']);
});

it('matches a parameter name across the extension re-casing', function (): void {
    expect(HeaderIndex::foldName('display_id'))->toBe(HeaderIndex::foldName('displayID'));
    expect(HeaderIndex::foldName('min_type'))->toBe(HeaderIndex::foldName('minType'));
    expect(HeaderIndex::foldName('window'))->not->toBe(HeaderIndex::foldName('renderer'));
});
