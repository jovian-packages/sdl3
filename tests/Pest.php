<?php

declare(strict_types=1);

/*
| Pest bootstrap for jovian/sdl3.
|
| Every extension-dependent test skips when ext-sdl3 is absent, so a green run
| on a machine without the extension proves almost nothing. That is what
| scripts/gates/verify-ext-control.mjs exists to catch: it fails if a suite
| skipped while the extension was in fact loaded.
|
| The video tests need a display server. On a headless box SDL_Init(VIDEO)
| fails and those tests skip with SDL's own reason rather than pretending.
*/

use Jovian\Bindings\Sdl3\Enums\SDLInitFlags;
use Jovian\Bindings\Sdl3\SDL;
use Jovian\Bindings\Sdl3\SDLError;
use Jovian\Bindings\Sdl3\Render\SDLRender;
use Jovian\Bindings\Sdl3\Video\SDLVideo;

function sdl3ExtensionLoaded(): bool
{
    return extension_loaded('sdl3');
}

function sdl3RequireExtension(): void
{
    if (!sdl3ExtensionLoaded()) {
        test()->skip('ext-sdl3 is not loaded');
    }
}

/**
 * Bring the video subsystem up, or skip with the reason SDL gave.
 *
 * SDL_Init is refcounted, so calling this from several tests in one process is
 * fine as long as each one pairs it with sdl3QuitVideo().
 */
function sdl3InitVideo(): void
{
    sdl3RequireExtension();
    SDLError::SDLClearError();
    if (!SDL::SDLInit(SDLInitFlags::VIDEO->value)) {
        test()->skip('SDL_Init(VIDEO) failed: ' . SDLError::SDLGetError());
    }
}

function sdl3QuitVideo(): void
{
    SDL::SDLQuitSubSystem(SDLInitFlags::VIDEO->value);
}

/**
 * A hidden window and a renderer, torn down by the caller.
 *
 * Hidden keeps the suite from flashing windows across the desktop on every
 * run; examples/proof_window_typed.php is the one place that shows one.
 *
 * @return array{0: int, 1: int} window handle, renderer handle
 */
function sdl3WindowAndRenderer(int $width = 64, int $height = 48): array
{
    $window = SDLVideo::SDLCreateWindow('jovian-sdl3-test', $width, $height, \Jovian\Bindings\Sdl3\Enums\SDLWindowFlags::HIDDEN->value);
    if ($window === 0) {
        test()->skip('SDL_CreateWindow failed: ' . SDLError::SDLGetError());
    }
    $renderer = SDLRender::SDLCreateRenderer($window);
    if ($renderer === 0) {
        SDLVideo::SDLDestroyWindow($window);
        test()->skip('SDL_CreateRenderer failed: ' . SDLError::SDLGetError());
    }

    return [$window, $renderer];
}

function sdl3Destroy(int $window, int $renderer): void
{
    SDLRender::SDLDestroyRenderer($renderer);
    SDLVideo::SDLDestroyWindow($window);
}

/** The ARGB8888 pixel SDL_RenderReadPixels hands back for one RGBA colour. */
function sdl3PackArgb(int $r, int $g, int $b, int $a = 255): int
{
    return ($a << 24) | ($r << 16) | ($g << 8) | $b;
}
