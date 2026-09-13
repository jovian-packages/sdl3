# Change log

## 2026-09-13 (0.8.0 — the package)

Built from scratch against ext-sdl3 0.7.0, as roadmap step 10 of the Venusian
Surface GPU program, pulled forward because the extension is finished.
jovian/metal is the pattern; everything below is either inherited from it or a
deviation ext-sdl3's shape forced.

* **Scaffold**: `jovian/sdl3`, namespace `Jovian\Bindings\Sdl3\`, requiring
  `ext-sdl3 ^0.7.0` and PHP `^8.4`. Local git on `0.8.x`, no remotes. MIT.
  Package 0.8.0 projects extension 0.7.0, which links SDL 3.4.4.

* **No Runtime, deliberately.** ext-sdl3 has no Bridge, no registry, no object
  lifetimes: 29 classes, 716 methods, *every one* `public static`, every handle
  a raw native pointer widened to a PHP `int`. The faithful projection of that
  is all-static classes with no hand-written core, so `src/` is 100% generated
  — the sharpest difference from jovian/metal.

* **Generator**: `scripts/generate.php` reads the INSTALLED extension by
  Reflection (there is no annotated source and no `ide/` stubs) and joins it
  against the SDL3 headers at `/opt/homebrew/include/SDL3` to recover the enum
  and flag types the extension flattens to `int`. The join is by C function
  name then **parameter name**, never position — the extension collapses SDL's
  output pointers into array returns, so arities do not correspond but names
  survive, modulo re-casing (`displayID` → `display_id`).
  `ext=0.7.0 classes=29 methods=716 joined=692 unjoined=24 enums=39
  (bitmask=6) cases=836 enumParams=76 enumReturns=26 files=68 GEN_OK`.

* **Projection**: 29 classes / 716 methods / 39 enums / 836 enum cases.
  76 enum parameters, 26 enum returns, 6 bitmask families left as `int`.
  692 methods joined an SDL3 prototype; the 24 that did not are extension glue
  (16 event readers, 6 surface/transfer-buffer helpers, 2 odd ones) and are
  projected untyped, which is correct — there is no C declaration to match.

* **Optional parameters**: ext-sdl3 declares 110 parameters optional without
  reporting a default (a Zephir arginfo trait). Probing established that for
  untyped ones omitting is identical to passing `null`, and that for the 32
  typed ones the default is not observable at all. Rather than guess, the 72
  affected methods forward `...array_slice([...], 0, func_num_args())`, so an
  omitted argument stays omitted. Still exactly one extension call.

* **Gates**: five oracles, all green, each with a watched positive control.
  `verify-parity` (716 = 716, arities, one ext call per body),
  `verify-generate` (output + idempotence), `verify-style`,
  `verify-enum-values` (836 cases compiled against the real headers),
  `verify-ext-control` (the live suites did not skip their way to green).

* **`verify-enum-values` caught a real trap while being written.**
  `SDL_oldnames.h` `#define`s every SDL2 spelling to a deliberately undeclared
  identifier (`SDL_QUIT` → `SDL_QUIT_renamed_SDL_EVENT_QUIT`), so a probe using
  `#if defined(...)` to pick among candidate spellings selects a name that does
  not compile. Fixed twice over: the probe now sets `SDL_DISABLE_OLD_NAMES`,
  and the generator records each case's C name in the emitted source so the
  gate never guesses a name in the first place.

* **`majorityFamily` fixed a miner bug.** `SDL_WINDOWPOS_UNDEFINED_MASK` sits
  in the same unbroken `#define` run as the `SDL_WINDOW_*` flags without being
  one of them, which dragged the family's common prefix back to `SDL_` and
  produced cases named `WINDOW_HIDDEN` instead of `HIDDEN`. The run is now
  grouped by its first two tokens and the largest group kept.

* **Tests**: 24 Pest tests, 6246 assertions, green on an Apple M1 with SDL
  3.4.4 and the `metal` render backend.

* **Proof**: `examples/proof_window_typed.php` drives the whole path through
  the typed surface — init through the mined `SDLInitFlags`, a real visible
  window whose flags/title/size round-trip, a renderer, four presented frames —
  and **byte-checks 76,800 pixels** read back with `SDL_RenderReadPixels`
  against the colour each frame was cleared to. All four frames are an exact
  match (`0xFFC81E28`, `0xFF1EC83C`, `0xFF283CC8`, `0xFF0C2238`). Readback
  through the 0.7.0 surface is fully available; no fallback was needed. Prints
  `PROOF_WINDOW_TYPED_OK`.

* **Deviations from jovian/metal, all forced by ext-sdl3's shape**: no
  `Runtime/` and no `Values/`; no boxing of returns, because there are no
  objects to box; parameters and returns are `int` handles alike rather than
  int-in/object-out; and the optional-parameter trimming above, which metal
  never needed because ext-metal reports its defaults.

* **Out of scope**: `sdl3image` and `sdl3ttf` are loaded on this machine and
  are not projected. They want their own packages, built the same way — the
  generator is parameterised on `ExtSurface::EXTENSION` and a header root, so
  most of it should carry over.
