---
type: Concept
title: The 39 mined enum families, and why bitmasks stay int
description: >-
  ext-sdl3 exposes zero PHP-visible constants, so every family here is mined
  from the SDL3 headers. Reachability decides which, the enum/bitmask split
  decides how they are typed, and a C compiler decides whether the values are
  right.
tags: [enums, constants, bitmask, headers, sdl3]
status: draft
generated:
  by: claude-fable-5
  at: 2026-09-13T00:00:00Z
---

# Enums and constants

`php --ri sdl3` reports 29 classes and nothing else. Reflection confirms it:
**0 functions, 0 global constants, 0 class constants**. Every named value SDL
has — `SDL_INIT_VIDEO`, `SDL_PIXELFORMAT_ARGB8888`, `SDL_EVENT_QUIT` — is
absent from the extension's PHP surface. Under the house rule that constants
live in jovian, that makes this package their only home, and the SDL3 C headers
their only source.

Result: **39 families, 836 cases.**

## Which families get mined: reachability

A family earns an enum by being **reachable** — it is the C type of a parameter
or a return on a method this package projects. That keeps the surface bounded
to what is usable without boiling the SDK: SDL3 declares 95 `typedef enum`
families and 21 bitmask typedefs, of which 39 are reachable.

The largest are `SDLScancode` (249), `SDLEventType` (120),
`SDLGPUTextureFormat` (105) and `SDLPixelFormat` (65); the smallest is
`SDLGPUIndexElementSize` (2).

One documented exception, in `scripts/Generator/extra-enums.php`:
**`SDL_EventType`**. The extension reads `SDL_Event` apart in C and returns a
plain array, so an event's `type` reaches PHP as an int that no C prototype
mentions. Reachability misses it, and without it nothing can act on an event.

## How they are typed: the enum / bitmask split

SDL writes constants two ways, and the difference decides the projection:

**`typedef enum SDL_ScaleMode { … }`** — one member is the whole value. A
parameter of that type projects as `SDLScaleMode|int`, and the body forwards
`$x instanceof \BackedEnum ? $x->value : $x`. 76 parameters and 26 returns.

**`typedef Uint64 SDL_WindowFlags;` + a run of `#define`s** — members are bits
that callers OR together. PHP enum cases cannot be OR'd, so **these parameters
stay `int`**; the enum exists only to name the bits, and a `@param` line on the
method says so. Six families: `SDLInitFlags`, `SDLWindowFlags`, `SDLKeymod`,
`SDLBlendMode`, `SDLGPUShaderFormat`, `SDLGPUTextureUsageFlags`.

```php
SDL::SDLInit(SDLInitFlags::VIDEO->value | SDLInitFlags::EVENTS->value);
```

This is exactly jovian/metal's `NS_ENUM` is `Enum|int`, `NS_OPTIONS` stays
`int` rule, under SDL's spelling.

**Returns are never converted.** A return declared `SDLScaleMode|int` hands
back the extension's raw int. Calling `Enum::from()` on a value SDL invented
would throw, and this layer is not allowed to add a failure mode. The union
type documents the family; the caller opts in with `tryFrom()`.

## Case naming

`SDL_SCALEMODE_NEAREST` becomes `SDLScaleMode::NEAREST`: the family's longest
common prefix, cut back to an underscore boundary, is stripped and the rest
uppercased.

Two things complicate that, both handled:

**An intruder in the `#define` run.** The bitmask heuristic is "the defines
immediately following the typedef", which is nearly always right and was wrong
once: `SDL_WINDOWPOS_UNDEFINED_MASK` sits in the same unbroken run as the
`SDL_WINDOW_*` flags without being one. One intruder drags the common prefix
back to `SDL_` and leaves every case named `WINDOW_SOMETHING`.
`HeaderIndex::majorityFamily()` groups the run by its first two tokens and
keeps the largest group.

**Aliases.** SDL names the same value twice for convenience, and a PHP backed
enum cannot hold a duplicate value. First name wins; the alias is dropped and
listed in the emitted file's docblock. Thirteen cases across four families:

* `SDLPixelFormat` — the six endianness aliases (`ARGB32` is `BGRA8888` here,
  and so on). Use the explicit 8888 spellings.
* `SDLEventType` — `DISPLAY_FIRST`/`LAST` and `WINDOW_FIRST`/`LAST`, which are
  range markers rather than events.
* `SDLColorspace` — `RGB_DEFAULT`, `YUV_DEFAULT`.
* `SDLAudioFormat` — `SDL_AUDIO_S32`.

## Every case names its origin

Each emitted case carries the C constant it was mined from:

```php
case VIDEO = 32; // SDL_INIT_VIDEO
```

That is what a reader needs to check a value against SDL's documentation, and
it is what `scripts/gates/verify-enum-values.mjs` compiles. The generator says
which constant it read; the C compiler says what that constant is worth. All
836 agree.

## The `SDL_oldnames.h` trap

Worth knowing before writing any C probe against these headers.

`SDL_oldnames.h` maps every SDL2 spelling to a deliberately undeclared
identifier so that using one fails to compile:

```c
#define SDL_QUIT SDL_QUIT_renamed_SDL_EVENT_QUIT
```

These are `#define`s, so `#if defined(SDL_QUIT)` is **true**, and a probe that
guesses candidate spellings with `#if defined(...)` will happily select a name
that does not exist. The first version of `verify-enum-values.mjs` did exactly
this and failed to compile on `SDL_QUIT`, `SDL_WINDOW_SHOWN`,
`SDL_RENDER_TARGETS_RESET` and `SDL_RENDER_DEVICE_RESET`.

The fix is two-part and both halves matter: `#define SDL_DISABLE_OLD_NAMES 1`
before including `SDL.h`, and — better — stop guessing names at all, which is
why the generator now records each case's C name in the source.

## Extending

Do not hand-add a family. Make it reachable (type a parameter with it in the
extension, or in the header join) or, if it genuinely cannot be reached, add it
to `extra-enums.php` **with a comment saying why**, the way `SDL_EventType`
does. `SDL_Keycode` is deliberately *not* mined: its members are spelled
`SDLK_*` rather than `SDL_*`, it is a 300-member namespace rather than a flag
set, and nothing in the projected surface needs it named.
