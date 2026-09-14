<?php

declare(strict_types=1);

/*
| The mined enums, checked for the shape the house rules ask for and for
| agreement with the values SDL actually uses at runtime.
|
| The C-compiler check lives in scripts/gates/verify-enum-values.mjs; this file
| covers what a PHP process can see on its own.
*/

use Jovian\Bindings\Sdl3\Enums\SDLGPUBlendFactor;
use Jovian\Bindings\Sdl3\Enums\SDLGPUBufferUsageFlags;
use Jovian\Bindings\Sdl3\Enums\SDLGPUPrimitiveType;
use Jovian\Bindings\Sdl3\Enums\SDLInitFlags;
use Jovian\Bindings\Sdl3\Enums\SDLPixelFormat;
use Jovian\Bindings\Sdl3\Enums\SDLScaleMode;
use Jovian\Bindings\Sdl3\Enums\SDLWindowFlags;
use Jovian\Bindings\Sdl3\SDL;

function enumFiles(): array
{
    $dir = dirname(__DIR__, 2) . '/src/Enums';

    return glob($dir . '/*.php') ?: [];
}

it('emits enums at all', function (): void {
    expect(enumFiles())->not->toBeEmpty();
});

it('declares every enum int-backed with unique, fully uppercase cases', function (): void {
    foreach (enumFiles() as $file) {
        $class = 'Jovian\\Bindings\\Sdl3\\Enums\\' . basename($file, '.php');
        expect(enum_exists($class))->toBeTrue($class);

        $reflection = new ReflectionEnum($class);
        expect((string) $reflection->getBackingType())->toBe('int', $class);

        $cases = $class::cases();
        expect($cases)->not->toBeEmpty($class);

        $values = array_map(fn ($c): int => $c->value, $cases);
        expect($values)->toEqual(array_unique($values), $class . ' has a duplicate backing value');

        foreach ($cases as $case) {
            expect($case->name)->toBe(strtoupper($case->name), $class . '::' . $case->name);
        }
    }
});

it('annotates every case with the C constant it was mined from', function (): void {
    foreach (enumFiles() as $file) {
        $text = file_get_contents($file);
        $declared = preg_match_all('/^\s*case\s+\w+\s*=/m', $text);
        $annotated = preg_match_all('/^\s*case\s+\w+\s*=\s*-?\d+;\s*\/\/\s*SDL_\w+$/m', $text);
        expect($annotated)->toBe($declared, basename($file));
    }
});

it('carries no class constants, because constants live in enums', function (): void {
    $files = glob(dirname(__DIR__, 2) . '/src/*/*.php') ?: [];
    foreach ([...$files, ...(glob(dirname(__DIR__, 2) . '/src/*.php') ?: [])] as $file) {
        expect(preg_match('/^\s*(?:public |private |protected )?const\s+\w+/m', file_get_contents($file)))
            ->toBe(0, basename($file) . ' declares a class constant');
    }
});

it('agrees with the values SDL reports at runtime', function (): void {
    sdl3RequireExtension();

    // SDL_WasInit echoes back the flag word it was given, so the enum's value
    // for VIDEO has to be the same bit SDL uses internally.
    expect(SDL::SDLInit(SDLInitFlags::VIDEO->value))->toBeTrue();
    expect(SDL::SDLWasInit(SDLInitFlags::VIDEO->value))->toBe(SDLInitFlags::VIDEO->value);
    SDL::SDLQuitSubSystem(SDLInitFlags::VIDEO->value);

    // SDL_GetPixelFormatDetails decodes the format word; a wrong enum value
    // would decode to different bit counts or fail outright.
    $details = SDL::SDLGetPixelFormatDetails(SDLPixelFormat::RGBA8888->value);
    expect($details)->toBeArray();
    expect($details['bits_per_pixel'] ?? null)->toBe(32);
});

it('keeps bitmask families OR-able', function (): void {
    // The point of leaving bitmask parameters as int: cases combine by value.
    $combined = SDLInitFlags::VIDEO->value | SDLInitFlags::EVENTS->value;
    expect($combined)->toBe(SDLInitFlags::VIDEO->value + SDLInitFlags::EVENTS->value);
    expect($combined & SDLInitFlags::VIDEO->value)->toBe(SDLInitFlags::VIDEO->value);

    $flags = SDLWindowFlags::HIDDEN->value | SDLWindowFlags::BORDERLESS->value;
    expect($flags & SDLWindowFlags::BORDERLESS->value)->toBe(SDLWindowFlags::BORDERLESS->value);
});

it('mines the families the surface actually needs', function (): void {
    // A spot check that reachability picked up the obvious ones rather than
    // silently emitting nothing.
    expect(SDLScaleMode::NEAREST->value)->toBe(0);
    expect(SDLPixelFormat::tryFrom(SDLPixelFormat::ARGB8888->value))->not->toBeNull();
    expect(count(SDLWindowFlags::cases()))->toBeGreaterThan(10);
});

it('projects the SDL_GPU createinfo families extra-enums.php names, with the header values', function (): void {
    // These C members live inside createinfo arrays the extension reads
    // apart in C — no prototype names them, so extra-enums.php lists the
    // families explicitly (the same mechanism as SDL_EventType). venusian-sdl3
    // (task 16) imports these verbatim instead of inventing its own copies.
    expect(SDLGPUPrimitiveType::TRIANGLELIST->value)->toBe(0);       // SDL_GPU_PRIMITIVETYPE_TRIANGLELIST
    expect(SDLGPUBlendFactor::SRC_ALPHA->value)->toBe(7);            // SDL_GPU_BLENDFACTOR_SRC_ALPHA
    expect(SDLGPUBufferUsageFlags::VERTEX->value)->toBe(1);          // SDL_GPU_BUFFERUSAGE_VERTEX
});
