---
okf_version: "0.2"
---

# jovian/sdl3 — knowledge bundle

SDL3 typed in PHP: `ext-sdl3` 0.8.0 projected 1:1 into typed classes, plus the
enums the extension exposes none of. Read this index first, then open only the
concepts the task needs.

- [projection-rule.md](/projection-rule.md) — the rule that bounds this
  package: one method = one extension call. Why there is no `Runtime/` here
  when jovian/metal has one, and where composition goes instead.
- [generation.md](/generation.md) — reflection as the specification, the
  name-based join against the SDL3 headers, the alias and extras tables, and
  what each gate does and does not prove.
- [enums-and-constants.md](/enums-and-constants.md) — 55 header-mined
  families, reachability, why bitmasks stay `int`, the aliases PHP forces the
  miner to drop, the `SDL_oldnames.h` trap, and the 16 SDL_GPU createinfo
  families named only in `extra-enums.php`.
- [optional-params.md](/optional-params.md) — 110 parameters the extension
  calls optional without reporting a default, and the trimmed argument list
  that forwards them without guessing.
- [quirks.md](/quirks.md) — what ext-sdl3 does that looks wrong and is simply
  true: pointer-as-int handles, inconsistent array shapes, `SDLRenderClear`
  returning void, and the 25 glue methods with no SDL3 counterpart.

## Fast facts

| | |
|---|---|
| Version | 0.8.0, PHP `^8.4`, requires `ext-sdl3` `^0.8.0` (links SDL 3.4.4) |
| Namespace | `Jovian\Bindings\Sdl3\` |
| Generated | 31 classes / 727 methods / 55 enums / 925 cases — **all of `src/`** |
| Hand-written | none in `src/`; the extension has no glue to hand-project |
| Typing | 76 enum parameters, 26 enum returns, 7 bitmask families left as `int` |
| Joined | 702 of 727 methods matched an SDL3 prototype; 25 are extension glue |
| Proof | `examples/proof_window_typed.php` → `PROOF_WINDOW_TYPED_OK` |

## Layering

`ext-sdl3` (1:1 binding + unavoidable glue) → **`jovian/sdl3`** (enums + typed
projection) → `venusian-sdl3` (composition) → Surface (cross-platform
abstraction). Never reach up the stack.

## Out of scope

`sdl3image` and `sdl3ttf` load alongside ext-sdl3 on the build machine and are
not projected here. They want their own packages built the same way.
