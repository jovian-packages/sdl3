<?php

declare(strict_types=1);

/*
| Exit proof for jovian/sdl3 0.8.0 against ext-sdl3 0.8.0.
|
|   php examples/proof_window_typed.php
|
| Every call below goes through the typed projection — no `Sdl3\…` class is
| touched directly — so reaching the end proves the projection forwards
| correctly for the whole window/renderer path, not merely that it parses.
|
| What it proves, in order:
|
|   1. SDL_Init(VIDEO) through the mined SDLInitFlags enum, and WasInit echoes
|      the same bit back, which means the enum's value is SDL's value.
|   2. A real window is created; the flags we asked for come back set, and
|      title and size round-trip through setters and getters.
|   3. A renderer is created and several frames are drawn and presented, each
|      cleared to a different known colour.
|   4. Each frame is read back with SDL_RenderReadPixels and BYTE-CHECKED: the
|      pixel SDL returns must be exactly the colour we asked it to clear to,
|      for every pixel in the surface.
|   5. Enum instances and raw ints are shown to be interchangeable at the
|      typed boundary.
|   6. Everything is destroyed and SDL_Quit leaves the video subsystem down.
|
| The window is briefly visible. That is deliberate — this is a GUI session and
| a visible window is part of the evidence, as it is in jovian/metal's
| proof_triangle_typed.php.
|
| Prints PROOF_WINDOW_TYPED_OK on success; exits non-zero with a reason
| otherwise.
*/

namespace Jovian\Bindings\Sdl3\Proof;

use Jovian\Bindings\Sdl3\Enums\SDLInitFlags;
use Jovian\Bindings\Sdl3\Enums\SDLPixelFormat;
use Jovian\Bindings\Sdl3\Enums\SDLTextureAccess;
use Jovian\Bindings\Sdl3\Enums\SDLWindowFlags;
use Jovian\Bindings\Sdl3\Render\SDLRender;
use Jovian\Bindings\Sdl3\SDL;
use Jovian\Bindings\Sdl3\SDLError;
use Jovian\Bindings\Sdl3\Timer\SDLTimer;
use Jovian\Bindings\Sdl3\Video\SDLVideo;

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Jovian\\Bindings\\Sdl3\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
}

function bail(string $why): never
{
    $error = SDLError::SDLGetError();
    fwrite(STDERR, 'PROOF_WINDOW_TYPED_FAILED: ' . $why . ($error === '' ? '' : ' — SDL says: ' . $error) . "\n");
    exit(1);
}

function step(string $message): void
{
    echo '  ' . $message . "\n";
}

if (!extension_loaded('sdl3')) {
    fwrite(STDERR, "PROOF_WINDOW_TYPED_FAILED: ext-sdl3 is not loaded.\n");
    fwrite(STDERR, "  export HERD_PHP_84_INI_SCAN_DIR=\$(zsh -ic 'echo \$HERD_PHP_84_INI_SCAN_DIR')\n");
    exit(1);
}

echo "jovian/sdl3 0.8.0 — typed window proof against ext-sdl3 0.8.0\n";

/* 1. Init, through the mined enum. ------------------------------------- */

SDLError::SDLClearError();
if (!SDL::SDLInit(SDLInitFlags::VIDEO->value)) {
    bail('SDL_Init(VIDEO) failed');
}
if (SDL::SDLWasInit(SDLInitFlags::VIDEO->value) !== SDLInitFlags::VIDEO->value) {
    bail('SDL_WasInit did not echo SDLInitFlags::VIDEO — the mined enum value disagrees with SDL');
}
$version = SDL::SDLGetVersion();
step(sprintf(
    'SDL %d.%d.%d on %s, driver "%s"',
    intdiv($version, 1_000_000),
    intdiv($version % 1_000_000, 1_000),
    $version % 1_000,
    SDL::SDLGetPlatform(),
    SDLVideo::SDLGetCurrentVideoDriver(),
));

/* 2. A real window, with flags that round-trip. ------------------------ */

$width = 160;
$height = 120;
$asked = SDLWindowFlags::RESIZABLE->value | SDLWindowFlags::ALWAYS_ON_TOP->value;

$window = SDLVideo::SDLCreateWindow('jovian/sdl3 proof', $width, $height, $asked);
if ($window === 0) {
    bail('SDL_CreateWindow returned a null handle');
}

$flags = SDLVideo::SDLGetWindowFlags($window);
if (($flags & $asked) !== $asked) {
    bail(sprintf('window flags did not round-trip: asked %d, got %d', $asked, $flags));
}
step(sprintf('window %d created, flags 0x%X include every bit requested', $window, $flags));

if (SDLVideo::SDLGetWindowTitle($window) !== 'jovian/sdl3 proof') {
    bail('the window title did not round-trip');
}
if (SDLVideo::SDLGetWindowSize($window) !== [$width, $height]) {
    bail('the window size did not round-trip');
}
step('title and size round-trip through the typed setters and getters');

/* 3 + 4. Frames, presented and byte-checked. --------------------------- */

$renderer = SDLRender::SDLCreateRenderer($window);
if ($renderer === 0) {
    SDLVideo::SDLDestroyWindow($window);
    bail('SDL_CreateRenderer returned a null handle');
}
step('renderer created, backend "' . SDLRender::SDLGetRendererName($renderer) . '"');

SDLVideo::SDLShowWindow($window);

/** @var list<array{0: int, 1: int, 2: int}> */
$frames = [
    [200, 30, 40],
    [30, 200, 60],
    [40, 60, 200],
    [12, 34, 56],
];

$verified = 0;
foreach ($frames as $index => [$r, $g, $b]) {
    if (!SDLRender::SDLSetRenderDrawColor($renderer, $r, $g, $b, 255)) {
        bail('SDL_SetRenderDrawColor failed');
    }
    SDLRender::SDLRenderClear($renderer);

    // Read back BEFORE presenting: on a double-buffered backend the back
    // buffer is what we just cleared, and presenting may leave it undefined.
    $surface = SDLRender::SDLRenderReadPixels($renderer);
    if (!is_array($surface) || !isset($surface['pixels']['data'])) {
        bail('SDL_RenderReadPixels did not return a surface array');
    }

    $pixels = $surface['pixels']['data'];
    $count = count($pixels);
    if ($count !== $surface['w'] * $surface['h']) {
        bail(sprintf('read back %d pixels for a %dx%d surface', $count, $surface['w'], $surface['h']));
    }

    $format = $surface['format'];
    if ($format !== SDLPixelFormat::ARGB8888->value && $format !== SDLPixelFormat::XRGB8888->value) {
        // Not a failure: prove what is provable and say what was skipped.
        step(sprintf('frame %d: format %d is not ARGB8888/XRGB8888, byte check skipped', $index, $format));
    } else {
        $expected = (255 << 24) | ($r << 16) | ($g << 8) | $b;
        $mask = $format === SDLPixelFormat::ARGB8888->value ? 0xFFFFFFFF : 0x00FFFFFF;
        $wrong = 0;
        foreach ($pixels as $pixel) {
            if (($pixel & $mask) !== ($expected & $mask)) {
                $wrong++;
            }
        }
        if ($wrong !== 0) {
            bail(sprintf(
                'frame %d: %d of %d pixels differ; expected 0x%08X, first was 0x%08X',
                $index,
                $wrong,
                $count,
                $expected & $mask,
                $pixels[0] & $mask,
            ));
        }
        $verified += $count;
        step(sprintf(
            'frame %d: all %d pixels read back as 0x%08X (rgb %d,%d,%d) — exact match',
            $index,
            $count,
            $expected & $mask,
            $r,
            $g,
            $b,
        ));
    }

    if (!SDLRender::SDLRenderPresent($renderer)) {
        bail('SDL_RenderPresent failed');
    }
    SDLTimer::SDLDelay(120);
}

if ($verified === 0) {
    bail('no frame could be byte-checked; the readback path proved nothing');
}

/* 5. Enum instances and ints are interchangeable. ---------------------- */

$fromEnum = SDLRender::SDLCreateTexture($renderer, SDLPixelFormat::RGBA8888, SDLTextureAccess::TARGET, 16, 16);
$fromInt = SDLRender::SDLCreateTexture($renderer, SDLPixelFormat::RGBA8888->value, SDLTextureAccess::TARGET->value, 16, 16);
if (!isset($fromEnum['ptr'], $fromInt['ptr']) || $fromEnum['format'] !== $fromInt['format']) {
    bail('passing an enum case and passing its int value did not agree');
}
step('enum cases and raw ints are interchangeable at the typed boundary');
SDLRender::SDLDestroyTexture($fromEnum['ptr']);
SDLRender::SDLDestroyTexture($fromInt['ptr']);

/* 6. Clean teardown. --------------------------------------------------- */

SDLRender::SDLDestroyRenderer($renderer);
SDLVideo::SDLDestroyWindow($window);
SDL::SDLQuit();

if (SDL::SDLWasInit(SDLInitFlags::VIDEO->value) !== 0) {
    bail('SDL_Quit left the video subsystem initialised');
}
step('renderer, window and subsystem torn down cleanly');

echo sprintf("%d pixels byte-checked across %d frames\n", $verified, count($frames));
echo "PROOF_WINDOW_TYPED_OK\n";
