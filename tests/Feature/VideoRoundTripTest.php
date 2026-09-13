<?php

declare(strict_types=1);

/*
| Real SDL behaviour through the typed surface.
|
| Everything here calls the projection, never the extension directly, so a
| green run is evidence the projection forwards correctly — including the
| enum-to-int coercion and the trimmed argument list for optional parameters.
*/

use Jovian\Bindings\Sdl3\Enums\SDLInitFlags;
use Jovian\Bindings\Sdl3\Enums\SDLPixelFormat;
use Jovian\Bindings\Sdl3\Enums\SDLScaleMode;
use Jovian\Bindings\Sdl3\Enums\SDLTextureAccess;
use Jovian\Bindings\Sdl3\Enums\SDLWindowFlags;
use Jovian\Bindings\Sdl3\Render\SDLRender;
use Jovian\Bindings\Sdl3\SDL;
use Jovian\Bindings\Sdl3\SDLError;
use Jovian\Bindings\Sdl3\Timer\SDLTimer;
use Jovian\Bindings\Sdl3\Video\SDLVideo;

it('reports the linked SDL version and platform', function (): void {
    sdl3RequireExtension();

    // SDL_VERSIONNUM packs major*1e6 + minor*1e3 + micro.
    $version = SDL::SDLGetVersion();
    expect($version)->toBeGreaterThanOrEqual(3_000_000);
    expect(intdiv($version, 1_000_000))->toBe(3);
    expect(SDL::SDLGetPlatform())->not->toBeEmpty();
    expect(SDL::SDLGetRevision())->toBeString();
});

it('round-trips init and quit of the video subsystem', function (): void {
    sdl3RequireExtension();

    expect(SDL::SDLWasInit(SDLInitFlags::VIDEO->value))->toBe(0);

    SDLError::SDLClearError();
    if (!SDL::SDLInit(SDLInitFlags::VIDEO->value)) {
        test()->skip('SDL_Init(VIDEO) failed: ' . SDLError::SDLGetError());
    }

    expect(SDL::SDLWasInit(SDLInitFlags::VIDEO->value))->toBe(SDLInitFlags::VIDEO->value);
    expect(SDLVideo::SDLGetCurrentVideoDriver())->not->toBeEmpty();
    expect(SDLVideo::SDLGetNumVideoDrivers())->toBeGreaterThan(0);

    SDL::SDLQuitSubSystem(SDLInitFlags::VIDEO->value);
    expect(SDL::SDLWasInit(SDLInitFlags::VIDEO->value))->toBe(0);
});

it('round-trips window flags, title and size', function (): void {
    sdl3InitVideo();

    $window = SDLVideo::SDLCreateWindow('jovian-sdl3', 200, 150, SDLWindowFlags::HIDDEN->value);
    if ($window === 0) {
        sdl3QuitVideo();
        test()->skip('SDL_CreateWindow failed: ' . SDLError::SDLGetError());
    }

    // The flag we asked for comes back; SDL adds its own on top, so test the
    // bit rather than the whole word.
    expect(SDLVideo::SDLGetWindowFlags($window) & SDLWindowFlags::HIDDEN->value)
        ->toBe(SDLWindowFlags::HIDDEN->value);

    expect(SDLVideo::SDLGetWindowTitle($window))->toBe('jovian-sdl3');
    expect(SDLVideo::SDLSetWindowTitle($window, 'renamed'))->toBeTrue();
    expect(SDLVideo::SDLGetWindowTitle($window))->toBe('renamed');

    expect(SDLVideo::SDLGetWindowSize($window))->toBe([200, 150]);
    expect(SDLVideo::SDLSetWindowSize($window, 120, 90))->toBeTrue();
    expect(SDLVideo::SDLGetWindowSize($window))->toBe([120, 90]);

    expect(SDLVideo::SDLGetWindowID($window))->toBeGreaterThan(0);

    SDLVideo::SDLDestroyWindow($window);
    sdl3QuitVideo();
});

it('clears a renderer to a known colour and reads the pixels back', function (): void {
    sdl3InitVideo();
    [$window, $renderer] = sdl3WindowAndRenderer(32, 24);

    expect(SDLRender::SDLGetRendererName($renderer))->not->toBeEmpty();
    expect(SDLRender::SDLSetRenderDrawColor($renderer, 12, 34, 56, 255))->toBeTrue();

    // ext-sdl3 is not consistent about the shape of its array returns:
    // SDL_GetWindowSize comes back as a list, SDL_GetRenderDrawColor as a
    // keyed map. The projection mirrors the extension rather than smoothing
    // this over — normalising it is venusian-sdl3's job. See .okf/quirks.md.
    expect(SDLRender::SDLGetRenderDrawColor($renderer))->toBe(['r' => 12, 'g' => 34, 'b' => 56, 'a' => 255]);

    SDLRender::SDLRenderClear($renderer);

    // The optional rect is omitted here, which exercises the trimmed argument
    // list: the projection must forward one argument, not two.
    $surface = SDLRender::SDLRenderReadPixels($renderer);

    expect($surface)->toBeArray()->toHaveKeys(['w', 'h', 'format', 'pixels']);
    expect($surface['w'])->toBe(32);
    expect($surface['h'])->toBe(24);

    $pixels = $surface['pixels']['data'];
    expect($pixels)->toHaveCount(32 * 24);

    // SDL hands back ARGB8888 on this platform; assert against the format it
    // reported rather than assuming.
    if ($surface['format'] === SDLPixelFormat::ARGB8888->value) {
        expect($pixels[0])->toBe(sdl3PackArgb(12, 34, 56));
        expect(array_unique($pixels))->toHaveCount(1);
    }

    sdl3Destroy($window, $renderer);
    sdl3QuitVideo();
});

it('accepts enum instances and raw ints interchangeably', function (): void {
    sdl3InitVideo();
    [$window, $renderer] = sdl3WindowAndRenderer();

    // Same call, once with the enum case and once with its int value.
    $fromEnum = SDLRender::SDLCreateTexture($renderer, SDLPixelFormat::RGBA8888, SDLTextureAccess::TARGET, 8, 8);
    $fromInt = SDLRender::SDLCreateTexture($renderer, SDLPixelFormat::RGBA8888->value, SDLTextureAccess::TARGET->value, 8, 8);

    expect($fromEnum)->toBeArray()->toHaveKey('ptr');
    expect($fromInt)->toBeArray()->toHaveKey('ptr');
    expect($fromEnum['format'])->toBe($fromInt['format']);
    expect($fromEnum['w'])->toBe(8)->and($fromEnum['h'])->toBe(8);

    expect(SDLRender::SDLSetDefaultTextureScaleMode($renderer, SDLScaleMode::NEAREST))->toBeTrue();
    expect(SDLRender::SDLSetDefaultTextureScaleMode($renderer, SDLScaleMode::NEAREST->value))->toBeTrue();

    SDLRender::SDLDestroyTexture($fromEnum['ptr']);
    SDLRender::SDLDestroyTexture($fromInt['ptr']);
    sdl3Destroy($window, $renderer);
    sdl3QuitVideo();
});

it('sets and clears the error string', function (): void {
    sdl3RequireExtension();

    SDLError::SDLClearError();
    expect(SDLError::SDLGetError())->toBe('');
    SDLError::SDLSetError('jovian-sdl3 probe');
    expect(SDLError::SDLGetError())->toBe('jovian-sdl3 probe');
    SDLError::SDLClearError();
    expect(SDLError::SDLGetError())->toBe('');
});

it('advances the millisecond clock', function (): void {
    sdl3RequireExtension();

    $before = SDLTimer::SDLGetTicks();
    SDLTimer::SDLDelay(12);
    $after = SDLTimer::SDLGetTicks();

    expect($after)->toBeGreaterThanOrEqual($before + 5);
});
