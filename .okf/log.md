# Change log

## 2026-09-14 (later — version relabel)

Package version, `ext-sdl3` require, and every "0.7.1 wave" / "0.8.1"
label relabeled to 0.8.0: neither jovian/sdl3 0.8.1 nor ext-sdl3 0.7.1
was ever published, so this wave lands as jovian/sdl3 0.8.0 against
ext-sdl3 0.8.0 — the two now coincide. `composer.json` (`version`,
`ext-sdl3` require), `AGENTS.md`, `.okf/**`, `scripts/Generator/HeaderIndex.php`'s
comment, and `examples/proof_window_typed.php`'s header/echo updated.
`src/**` regenerated against the rebuilt ext-sdl3 0.8.0 (every generated
doc comment embeds the installed extension version). True 0.7.0 history
is left alone.

## 2026-09-14 (task 12b — SDL_GPU createinfo enum families)

Added during execution by controller ruling: Task 16 (`venusian-sdl3`'s
SDL_GPU engine) had been planned to invent its own `Enums\Gpu\*` copies of
SDL_GPU C constants. C constants belong to the projection, not the consumer —
so this wave adds the sixteen missing families to
`scripts/Generator/extra-enums.php` instead, and Task 16 is amended to import
the generated enums verbatim.

* **Why reachability misses them**: every `SDL_CreateGPU*`/`SDL_BeginGPU*`
  prototype ext-sdl3 projects takes its createinfo struct as one opaque
  `array` — the extension reads the struct apart in C, the same way it reads
  `SDL_Event` apart for `SDL_EventType`. No projected parameter or return is
  ever declared with `SDL_GPUPrimitiveType` etc. as its C type, so the miner's
  default reachability rule cannot find them.

* **Sixteen families added**, chosen by grepping Task 16's createinfo arrays
  for every member it sets as a raw int: `SDL_GPUPrimitiveType`,
  `SDL_GPULoadOp`, `SDL_GPUStoreOp`, `SDL_GPUVertexElementFormat`,
  `SDL_GPUVertexInputRate`, `SDL_GPUShaderStage`, `SDL_GPUFillMode`,
  `SDL_GPUCullMode`, `SDL_GPUFrontFace`, `SDL_GPUBlendOp`,
  `SDL_GPUBlendFactor`, `SDL_GPUFilter`, `SDL_GPUSamplerMipmapMode`,
  `SDL_GPUSamplerAddressMode`, `SDL_GPUTransferBufferUsage`, and the bitmask
  `SDL_GPUBufferUsageFlags`. Case naming used the generator's existing
  prefix-stripping rule unmodified — no special-casing needed. Deliberately
  left unmined: `SDL_GPUCompareOp`, `SDL_GPUStencilOp` and
  `SDL_GPUColorComponentFlags` — real SDL_GPU families, but nothing in Task
  16's createinfo arrays sets them as a raw int (colour write mask and
  depth/stencil compare stay at SDL's default).

* **Regeneration**: `ext=0.8.0 classes=31 methods=727 joined=702 unjoined=25
  enums=55 (bitmask=7) cases=925 enumParams=76 enumReturns=26 files=86
  written=16 stale=0 GEN_OK` — 39→55 enums, 836→925 cases (+89), 70→86 files,
  bitmask families 6→7 (`SDLGPUBufferUsageFlags` joins). `verify-generate`,
  `verify-parity` (727=727), `verify-style` (86 files), `verify-enum-values`
  (925 cases compiled against `/opt/homebrew/include/SDL3`), and
  `verify-ext-control` all green; no generator logic changed, so no gate
  needed a fix.

* **TDD**: `tests/Enums/EnumShapeTest.php` extended first with a spot check
  pinning `SDLGPUPrimitiveType::TRIANGLELIST` = 0, `SDLGPUBlendFactor::SRC_ALPHA`
  = 7 and `SDLGPUBufferUsageFlags::VERTEX` = 1 against the header values
  (RED — classes not found). `extra-enums.php` extended and
  `php scripts/generate.php` run (GREEN, no other change needed).

* **Tests**: 29 Pest tests (was 28), 6530 assertions.

* **AGENTS.md rule 10** now names the seventeen `extra-enums.php` exception
  families (`SDL_EventType` plus the sixteen SDL_GPU ones) instead of just
  `SDL_EventType`.

## 2026-09-14 (0.8.0 — Metal, Vulkan, window events)

Regenerated over ext-sdl3 0.8.0 (Task 12 of the Stage plan). Package now
requires `ext-sdl3 ^0.8.0`. jovian/venusian-sdl3's stage host and GPU engine
need this.

* **New surface**: `Video\SDLMetal` (3 methods), `Video\SDLVulkan` (7),
  `Events\SDLWindowEvents::SDLReadWindowEvent` (1). 11 new methods, 2 new
  classes. `ext=0.8.0 classes=31 methods=727 joined=702 unjoined=25 enums=39
  (bitmask=6) cases=836 enumParams=76 enumReturns=26 files=70 GEN_OK` — exactly
  the brief's expected counts (727/31/702/25).

* **Two generator bugs found and fixed, neither hand-patched in `src/`:**
  1. `HeaderIndex` excluded `SDL_vulkan.h` alongside the vendored
     `SDL_opengl*`/`SDL_egl*` headers on "not SDL's own surface" reasoning.
     Wrong — it's a thin 287-line SDL wrapper, same shape as `SDL_metal.h`,
     and it holds the `SDL_Vulkan_*` prototypes the join needs. Narrowed the
     skip to the two vendored prefixes.
  2. `ExtMethod::cFunctionCandidates()` only knew the `GL`/`EGL` subsystem
     underscore-flattening (`SDLGLCreateContext` → `SDL_GL_CreateContext`).
     Added `Metal` and `Vulkan` to the same list, so `SDLMetalCreateView` →
     `SDL_Metal_CreateView` and `SDLVulkanLoadLibrary` → `SDL_Vulkan_LoadLibrary`
     resolve. Before both fixes: `joined=692 unjoined=35` — none of the 11 new
     methods joined; the ten new methods weren't merely unjoined, they landed
     in the *wrong* bucket (silently indistinguishable from real extension
     glue) until the count came up short of the wave's total.

* **The known null-default risk did not materialize.** `SDLVulkanLoadLibrary`'s
  `path` parameter reflects `isOptional() === true` and throws on
  `getDefaultValue()` (`Internal error: Failed to retrieve the default
  value`), same as the ext task flagged. `ExtSurface::parameter()` never calls
  `getDefaultValue()` — it only reads `isOptional()` and `getType()`, neither
  of which throws — so the generator was already immune. Emitted signature:
  `SDLVulkanLoadLibrary(mixed $path = null): bool`, matching the existing
  "untyped optional forwards trimmed" rule (`.okf/optional-params.md`); no
  generator change needed for this part.

* **Optional parameters**: 110 → 111 (the new `path`), 72 → 73 methods
  affected. 79 of 111 are untyped (was 78); the split and the trimmed-forward
  mechanism are unchanged.

* **New quirks, mirrored not smoothed** (`.okf/quirks.md`): `SDLVulkan` and
  `SDLMetal` handles are pointer-bits like everything else; `SDLVulkanCreateSurface`
  returns `0` on failure like any other creator; `SDLVulkanLoadLibrary` needs
  `SDL_Init(VIDEO)` first (SDL's own precondition, confirmed by a real failing
  call: `"Video subsystem has not been initialized"`); `SDLMetalCreateView`
  alone breaks the "SDL never throws" convention — the extension raises
  `\RuntimeException` on a null view instead of returning `0`, and the
  projection mirrors that exactly (one ext call, whatever it does).
  `Events\SDLWindowEvents` is no longer one of the empty classes — six now,
  not seven.

* **TDD**: `tests/Feature/WaveTest.php` written first (RED — `SDLMetal` class
  not found, 4 failing). Generator regenerated (GREEN after the two fixes
  above). One test in the file needed a real fix, not a generator fix: the
  brief's literal "names the Vulkan instance extensions" test called
  `SDLVulkanLoadLibrary()` without `SDL_Init(VIDEO)` first, which fails for
  real reasons on any machine (see quirk above) — added the
  `sdl3InitVideo()`/`sdl3QuitVideo()` pairing the rest of the suite already
  uses (`tests/Pest.php`).

* **Gates**: all six green — `verify-generate`, `verify-parity` (727=727),
  `verify-style` (70 files), `verify-enum-values` (836 cases, unchanged —
  no new enum families reachable), `verify-ext-control`.

* **Tests**: 28 Pest tests (was 24), 6342 assertions, green with ext-sdl3
  0.8.0 and SDL 3.4.4 on the same M1 / metal backend.

* **Proof**: `examples/proof_window_typed.php` re-run clean, version strings
  bumped to 0.8.0/0.8.0 in its banner (hand-written example, not generated).

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
