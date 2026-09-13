---
type: HowTo
title: How the projection is generated — reflection joined to the SDL3 headers
description: >-
  The generator's input is the installed extension read by Reflection, joined
  by name against the SDL3 C headers to recover the enum and flag types the
  extension flattens to int. What each gate proves, and what it does not.
tags: [generator, reflection, headers, gates, sdl3]
status: draft
generated:
  by: claude-fable-5
  at: 2026-09-13T00:00:00Z
---

# Generating the projection

```bash
export HERD_PHP_84_INI_SCAN_DIR=$(zsh -ic 'echo $HERD_PHP_84_INI_SCAN_DIR')
php scripts/generate.php            # write
php scripts/generate.php --check    # fail if anything would change
```

Current output:

```
ext=0.7.0 classes=29 methods=716 joined=692 unjoined=24 enums=39 (bitmask=6)
cases=836 enumParams=76 enumReturns=26 files=68 written=0 stale=0
```

## Two inputs, and why

**The installed extension, via Reflection.** There is no annotated C source for
ext-sdl3 the way `ext-metal` carries `@zep` annotations in `src/*.h`, and no
`ide/` stub directory on this machine. `ReflectionExtension('sdl3')` is the
only description of the extension that exists, which has a hard consequence:
`scripts/generate.php` cannot run without ext-sdl3 loaded, and it exits 1
saying so rather than emitting a half-surface.

**The SDL3 C headers** (`/opt/homebrew/include/SDL3`, overridable with
`JOVIAN_SDL3_HEADERS`). Reflection gives back `int` for every SDL enum, flag,
handle and plain integer alike — the type information is gone. The headers are
where it still exists. The header version must match what the extension links:
`SDL_version.h` says 3.4.4 and `SDL_GetVersion()` returns `3004004`.

## The join is by name

`HeaderIndex` parses every `extern SDL_DECLSPEC … SDLCALL SDL_Name(…);`
prototype into a map of parameter *name* → C type. `Pipeline` then walks each
reflected method, resolves its C function, and looks up each parameter by name.

Position would be wrong. The extension collapses SDL's output pointers into
array returns, so arities do not correspond:

```c
void SDL_GetWindowPosition(SDL_Window *window, int *x, int *y);
```
```php
SDLGetWindowPosition(int $window): array
```

Names survive that transformation. They are re-cased on the way —
`displayID` becomes `display_id` — so `HeaderIndex::foldName()` compares with
case and underscores removed.

**Method names** need candidates rather than a single spelling, because the
extension flattens SDL's subsystem underscores: `SDLGLCreateContext` is
`SDL_GL_CreateContext`, and `SDLRand` is lower-case `SDL_rand`.
`ExtMethod::cFunctionCandidates()` generates the alternatives and the first one
the header index knows wins.

## The two exception tables

Both are small, both are code so they stay visible in review.

`scripts/Generator/param-aliases.php` — parameters the extension genuinely
renamed, where folding cannot reach. One entry earns its place:
`SDL_CreateWindowAndRenderer`'s `window_flags` is `flags` in PHP, and without
the alias it would project as a bare `int` and lose the `SDLWindowFlags`
family. Renames between two parameters that are both plain `int` are invisible
in the output and are not carried as code.

`scripts/Generator/extra-enums.php` — families that earn an enum despite not
being reachable. One entry: `SDL_EventType`. The extension reads the event
union apart in C and returns a plain array, so the event's `type` reaches PHP
as an int that no prototype mentions. Reachability misses it entirely, and
without it the event surface cannot be used at all.

## The 24 unjoined methods

692 of 716 methods matched an SDL3 prototype. The remaining 24 are extension
glue with no C counterpart and are projected untyped, which is correct — there
is no C declaration to be faithful to. They fall into three groups:

* **Event readers** (16): `SDLReadEvent`, `SDLReadMouseButtonEvent`,
  `SDLReadKeyboardEvent`, `SDLFreeEvent` and siblings. These exist because
  `SDL_Event` is a union that cannot cross into PHP; the extension decomposes
  it in C.
* **Surface and transfer-buffer helpers** (6): `SDLReadSurfacePixels`,
  `SDLWriteSurfacePixels`, `SDLWriteSurfacePixelAt`,
  `SDLSurfaceToBlackAndWhite`, `SDLGPU::readFromGPUTransferBuffer`,
  `SDLGPU::writeToGPUTransferBuffer`. Note the last two are the only methods in
  the whole extension that are not named `SDL*`.
* **Two odd ones**: `SDLExitProcess` and `SDLUpdateTextureFromSurface`, which
  have no `extern SDL_DECLSPEC` prototype in the 3.4.4 headers.

Four further non-public helpers (`buildSurfaceArray`, `packSurfaceFromPtr`,
`buildPaletteArray`, `packPaletteFromPtr`) are excluded because they are not
public. That is the 720 → 716 difference if you count methods yourself.

## What each gate proves

| Gate | Proves | Does **not** prove |
|---|---|---|
| `verify-generate` | the tree is the generator's output; a second run is a no-op | that the output is *correct*, only that it is stable |
| `verify-parity` | every public ext method is projected once, arities match, each body makes exactly one ext call | that the forwarded arguments are semantically right |
| `verify-style` | house rules: strict_types, final all-static classes, int-backed UPPERCASE enum cases, no class constants, no `$this` | anything about behaviour |
| `verify-enum-values` | every emitted case equals the value the **C compiler** reads for the C constant the generator named | that the *right* family was chosen for a parameter |
| `verify-ext-control` | the live suites actually ran rather than skipping green | anything when the extension is absent — it fails instead of passing |

`verify-enum-values` is the one worth understanding. The generator resolves
SDL's constant expressions with a PHP evaluator, and a parser can be
confidently wrong. So the gate does not re-read the headers with the same code:
it writes a C program that prints every constant the package emits *by the C
name the generator recorded in the case's trailing comment*, compiles it
against the real headers, and diffs. A wrong value fails the diff; an invented
name fails to compile. 836 of 836 agree.

## Adding a gate

Give it a positive control. Break the thing it checks, watch it fail, restore.
Every gate in this package was built that way, and one of them —
`verify-enum-values` — caught a real bug during its own construction (see
[enums-and-constants.md](/enums-and-constants.md), the `SDL_oldnames.h` trap).
