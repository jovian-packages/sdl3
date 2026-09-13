# Agent guidelines — jovian/sdl3

## Knowledge Bundle (OKF)

This package ships an Open Knowledge Format bundle at [`.okf/`](.okf/) (excluded from the Composer dist via `.gitattributes` `export-ignore`). Before changing code or advising on this package: read [`.okf/index.md`](.okf/index.md) first, open only the concepts the task needs, prefer `status: stable` over `draft`. When you learn something durable, update the affected concept(s) and append [`.okf/log.md`](.okf/log.md); new or changed concepts stay `status: draft` until a human verifies them.

## Where this package sits

`ext-sdl3` (1:1 binding + unavoidable glue) → **`jovian/sdl3`** (enums + typed projection) → `venusian-sdl3` (composition) → Surface (cross-platform abstraction). Never reach up the stack, and never build a cross-platform abstraction here — that is Surface's job.

Version pairing: **jovian/sdl3 0.8.0 projects ext-sdl3 0.7.0**, which links SDL **3.4.4**. The package version and the extension version deliberately differ; the composer constraint is `ext-sdl3: ^0.7.0`.

## Rules

1. **Projection, not composition.** A method is legitimate only if it is exactly one extension call with the same arguments in the same order. The test: *can it be written as one extension call?* If not, it belongs in `venusian-sdl3`. Both `tests/Generator/GenerateCheckTest.php` and `verify-parity.mjs` enforce this: every one of the 716 generated bodies must contain exactly one `Ext*::` call.

2. **Reflection is the specification.** Unlike ext-metal there is no annotated C source and no `ide/` stubs on this machine — the installed extension is the only description of itself that exists. `scripts/generate.php` therefore *requires* ext-sdl3 to be loaded, and `verify-parity.mjs` fails rather than skips without it. If PHP cannot see the extension, export the Herd scan dir first:

   ```bash
   export HERD_PHP_84_INI_SCAN_DIR=$(zsh -ic 'echo $HERD_PHP_84_INI_SCAN_DIR')
   ```

3. **Never hand-edit generated code.** *Everything* under `src/` is generated — there is no hand-written counterpart to jovian/metal's `Runtime/` here, because there is nothing to hand-write (see rule 4). Edit `scripts/Generator/**`, then regenerate.

4. **There is no Runtime, and that is the correct projection.** ext-sdl3 has no Bridge, no registry, no object lifetimes, no retain/release. Every one of its 716 methods is `public static`, and every handle is a **raw native pointer widened to a PHP `int`** (`SDL_Window *` reaches PHP as `4620105136`). So the projection is static classes forwarding to static classes, and the caller owns every lifetime by calling the matching `SDLDestroy*`. Do not invent an object wrapper here; that is `venusian-sdl3`'s job.

5. **The join is by name, never by position.** `HeaderIndex::paramType()` matches a PHP parameter to its C declaration by name, folding case and underscores (`display_id` finds `displayID`). Position is unusable because the extension collapses SDL's output pointers into array returns: `SDL_GetWindowPosition(win, *x, *y)` arrives as `SDLGetWindowPosition(int $window): array`, and every index after the first is shifted. Names the extension genuinely renamed live in `scripts/Generator/param-aliases.php`.

6. **`typedef enum` is `Enum|int`; a bitmask family stays `int`,** for parameters and returns alike, because PHP enums cannot be OR'd. This is jovian/metal's NS_ENUM / NS_OPTIONS split under SDL's spelling. The six bitmask families are `SDLInitFlags`, `SDLWindowFlags`, `SDLKeymod`, `SDLBlendMode`, `SDLGPUShaderFormat`, `SDLGPUTextureUsageFlags`. Pass `SDLInitFlags::VIDEO->value | SDLInitFlags::EVENTS->value`.

7. **Enum parameters coerce, they never convert a return.** A parameter declared `SDLPixelFormat|int` forwards `$x instanceof \BackedEnum ? $x->value : $x`. A return declared `SDLScaleMode|int` returns the extension's raw int untouched — `Enum::from()` on a value SDL invented would throw, and throwing is behaviour this layer is not allowed to add.

8. **Optional parameters forward a trimmed argument list.** ext-sdl3 declares 110 parameters optional but reports no default through reflection (a Zephir arginfo trait). Rather than guess one, the 72 affected methods forward `...array_slice([...], 0, func_num_args())`, so an omitted argument stays omitted and the extension's own default applies. The defaults in the signature exist only to make the declaration legal. This is still exactly one extension call. Read [`.okf/optional-params.md`](.okf/optional-params.md) before touching it.

9. **Enums are int-backed with FULLY UPPERCASE cases, and every case names the C constant it came from** in a trailing comment. No class constants anywhere. Prefer `is_null($x)` over `$x === null`. Gates: `verify-style.mjs`, `verify-enum-values.mjs`.

10. **Constants are mined from the SDL3 headers, not invented.** ext-sdl3 0.7.0 exposes **zero** PHP-visible constants and zero functions — 29 classes and nothing else — so `/opt/homebrew/include/SDL3` is the authority. A family earns an enum by being *reachable*: it is the C type of a parameter or return on a projected method. The one documented exception is `SDL_EventType`, in `scripts/Generator/extra-enums.php`, because the extension reads the event union apart in C and no prototype mentions the type.

11. **Mind `SDL_oldnames.h`.** It `#define`s every SDL2 spelling to a deliberately undeclared identifier (`SDL_QUIT` → `SDL_QUIT_renamed_SDL_EVENT_QUIT`). Any C probe against these headers must `#define SDL_DISABLE_OLD_NAMES 1` first, or `#if defined(...)` will happily select a name that does not compile.

12. **The extension's array returns are not uniformly shaped, and the projection does not fix that.** `SDLGetWindowSize` returns a list `[w, h]`; `SDLGetRenderDrawColor` returns a map `['r'=>…]`; `SDLCreateSurface` and `SDLCreateTexture` return the whole struct as an array with a `ptr` key that later calls consume as the `int` handle. Mirror it, document it, and let `venusian-sdl3` normalise. See [`.okf/quirks.md`](.okf/quirks.md).

## Verification

```bash
php scripts/generate.php --check              # GEN_OK, written=0 stale=0
node scripts/gates/verify-generate.mjs        # GEN_OK (output + idempotence)
node scripts/gates/verify-parity.mjs          # PARITY_OK        716 = 716
node scripts/gates/verify-style.mjs           # STYLE_OK         68 files
node scripts/gates/verify-enum-values.mjs     # ENUM_VALUES_OK   836 cases vs cc
node scripts/gates/verify-ext-control.mjs     # EXT_CONTROL_CONSISTENT
vendor/bin/pest                               # 24 passed
php examples/proof_window_typed.php           # PROOF_WINDOW_TYPED_OK
```

`verify-enum-values.mjs` needs a C compiler and the SDL3 headers; it writes a C program that prints every constant the package emits, compiles it against the real headers, and diffs. The generator's own PHP expression evaluator is deliberately *not* what checks it.

Every extension-dependent test skips when `ext-sdl3` is absent, so a green `pest` run without the extension proves nothing. That is what `verify-ext-control.mjs` exists to catch — a skipped test reporting success.

When you add a gate, give it a positive control: deliberately break the thing it checks, watch it fail, then restore. A gate that has never failed is not yet evidence.

## Out of scope this pass

`sdl3image` and `sdl3ttf` are loaded on this machine and are **not** projected. They are separate extensions and want separate packages (`jovian/sdl3-image`, `jovian/sdl3-ttf`) built the same way — the generator here is parameterised on `ExtSurface::EXTENSION` and a header root, so most of it should carry over.
