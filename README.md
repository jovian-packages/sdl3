# jovian/sdl3

Typed PHP projection of [`ext-sdl3`](https://github.com/php-io-extensions/sdl3).

**jovian/sdl3 0.8.0 projects ext-sdl3 0.8.0**, which links SDL **3.4.4**. The
Composer constraint is `ext-sdl3: ^0.8.0`.

```
ext-sdl3  →  jovian/sdl3  →  venusian-sdl3  →  Surface
1:1 binding  enums +         composition       cross-platform
+ glue       typed surface
```

## What it is

`ext-sdl3` binds SDL3 one-to-one but exposes **no PHP-visible constants at
all** — 29 classes, zero functions, zero class constants — and hands back a
bare `int` for every enum, flag and handle alike. This package adds the two
things that are missing and nothing else:

* **39 enum families / 836 cases**, mined from the SDL3 C headers, each case
  annotated with the C constant it came from and verified against a C compiler.
* **Types on the surface**: 76 parameters and 26 returns that were `int` now
  say which family they belong to.

Everything else is a faithful 1:1 forward. One PHP method, one extension call,
same arguments in the same order. No behaviour is added — no exceptions, no
reshaped returns, no invented defaults.

Input for `venusian-sdl3`'s `input.sdl3` engine comes through here as-is:
keyboard, mouse and gamepad calls, event reads, and
`SDLEvents::SDLWaitEventTimeout(int $ms): ?array` (`['ptr', 'event_type']`
or null), which lets a stage that owns the macOS event pump wait for input.

## Install

```bash
composer require jovian/sdl3
```

Requires PHP 8.4+ and the `sdl3` extension. Under Laravel Herd, PHP needs to be
told where to find it:

```bash
export HERD_PHP_84_INI_SCAN_DIR=$(zsh -ic 'echo $HERD_PHP_84_INI_SCAN_DIR')
php --ri sdl3        # should report 0.8.0
```

## Use

```php
use Jovian\Bindings\Sdl3\SDL;
use Jovian\Bindings\Sdl3\SDLError;
use Jovian\Bindings\Sdl3\Video\SDLVideo;
use Jovian\Bindings\Sdl3\Render\SDLRender;
use Jovian\Bindings\Sdl3\Enums\SDLInitFlags;
use Jovian\Bindings\Sdl3\Enums\SDLWindowFlags;

if (!SDL::SDLInit(SDLInitFlags::VIDEO->value)) {
    throw new RuntimeException(SDLError::SDLGetError());
}

$window = SDLVideo::SDLCreateWindow('hello', 640, 480, SDLWindowFlags::RESIZABLE->value);
$renderer = SDLRender::SDLCreateRenderer($window);

SDLRender::SDLSetRenderDrawColor($renderer, 12, 34, 56, 255);
SDLRender::SDLRenderClear($renderer);
SDLRender::SDLRenderPresent($renderer);

SDLRender::SDLDestroyRenderer($renderer);
SDLVideo::SDLDestroyWindow($window);
SDL::SDLQuit();
```

Three things in that snippet are worth knowing.

**Bitmask families take `->value`.** PHP enum cases cannot be OR'd, so
parameters that take a bitmask stay `int` and you pass the case's value:
`SDLInitFlags::VIDEO->value | SDLInitFlags::EVENTS->value`. True enumerations
are different — those parameters accept either form:

```php
SDLRender::SDLCreateTexture($r, SDLPixelFormat::RGBA8888, SDLTextureAccess::TARGET, 16, 16);
SDLRender::SDLCreateTexture($r, SDLPixelFormat::RGBA8888->value, 2, 16, 16);  // same call
```

**Handles are raw pointers as PHP ints**, and you own them. A failed creator
returns `0`, not `null`. Nothing releases anything for you.

**Failure is SDL's convention, not PHP's.** `false` / `0` / `''` plus a message
in `SDLError::SDLGetError()`. This package never throws — turning that into
exceptions is `venusian-sdl3`'s job. Note that `SDL_GetError()` is not cleared
on success, so call `SDLError::SDLClearError()` first if you intend to read it.

## Surprises worth reading about

ext-sdl3's array returns are not uniformly shaped — `SDLGetWindowSize` gives a
list, `SDLGetRenderDrawColor` a keyed map, and `SDLCreateTexture` the whole
struct with the handle under a `ptr` key. `SDLRenderClear` returns `void` where
SDL returns `bool`. These are mirrored, not corrected. See
[`.okf/quirks.md`](.okf/quirks.md).

## Verify

```bash
php scripts/generate.php --check              # GEN_OK, written=0 stale=0
node scripts/gates/verify-generate.mjs        # GEN_OK
node scripts/gates/verify-parity.mjs          # PARITY_OK        716 = 716
node scripts/gates/verify-style.mjs           # STYLE_OK         68 files
node scripts/gates/verify-enum-values.mjs     # ENUM_VALUES_OK   836 cases vs cc
node scripts/gates/verify-ext-control.mjs     # EXT_CONTROL_CONSISTENT
vendor/bin/pest                               # 24 passed
php examples/proof_window_typed.php           # PROOF_WINDOW_TYPED_OK
```

The proof opens a small window, presents four frames, and byte-checks 76,800
pixels read back through `SDL_RenderReadPixels` against the colour each frame
was cleared to. All four match exactly.

## Regenerating

**Everything under `src/` is generated.** Do not hand-edit it — change
`scripts/Generator/**` and re-run `php scripts/generate.php`. The generator
reads the installed extension by Reflection, so ext-sdl3 must be loaded; it
exits with a message rather than emitting a partial surface. SDL3 headers are
found at `/opt/homebrew/include/SDL3` or wherever `JOVIAN_SDL3_HEADERS` points.

## Not included

`sdl3image` and `sdl3ttf` are separate extensions and want separate packages.

## Further reading

Agent and contributor guidance is in [`AGENTS.md`](AGENTS.md); the knowledge
bundle is at [`.okf/`](.okf/), starting with [`.okf/index.md`](.okf/index.md).

## Licence

MIT.
