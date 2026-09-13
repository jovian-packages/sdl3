---
type: Reference
title: ext-sdl3 0.7.0 conventions the projection mirrors rather than smooths
description: >-
  Pointer-as-int handles, array returns that are sometimes lists and sometimes
  maps, creators that return a struct instead of a handle, and a few return
  types that disagree with SDL's own. All faithful, none fixed here.
tags: [quirks, conventions, handles, returns, sdl3]
status: draft
generated:
  by: claude-fable-5
  at: 2026-09-13T00:00:00Z
---

# Extension conventions, mirrored not smoothed

Everything on this page is something ext-sdl3 0.7.0 does that a caller will
find surprising. None of it is fixed here. Smoothing is `venusian-sdl3`'s job,
and a projection that quietly corrected its extension would be lying about what
it projects.

## Handles are raw pointers widened to PHP ints

`SDL_Window *` reaches PHP as `4620105136`. So does `SDL_Renderer *`,
`SDL_Texture *`, `SDL_Surface *`, every GPU object, and every audio stream.

Consequences worth stating:

* There is no type safety between handle kinds. Passing a renderer where a
  texture belongs is an `int` to an `int`, and SDL discovers it at runtime —
  usually as `false` plus an `SDL_GetError()` message, sometimes worse.
* PHP refcounting has no relationship to the native lifetime. The caller owns
  it and must call the matching `SDLDestroy*`. Nothing in this package or the
  extension will do it at shutdown.
* A failed creator returns `0`, not `null`.

`SDL_PropertiesID`, `SDL_DisplayID`, `SDL_WindowID`, `SDL_JoystickID` and
friends are genuine `Uint32` ID values rather than pointers, and are also
`int`. They are not interchangeable with pointer handles even though the type
says they are.

## Array returns are not uniformly shaped

The extension collapses SDL's output pointers into an array return, and it does
not do so consistently.

**Positional lists:**

```php
SDLVideo::SDLGetWindowSize($w);      // [160, 120]
SDLVideo::SDLGetWindowPosition($w);  // [x, y]
```

**Keyed maps:**

```php
SDLRender::SDLGetRenderDrawColor($r);  // ['r'=>12,'g'=>34,'b'=>56,'a'=>255]
```

There is no rule that predicts which. Check the call.

## Some creators return the whole struct, not a handle

`SDLCreateSurface`, `SDLCreateTexture`, `SDLCreateTextureFromSurface` and
`SDLRenderReadPixels` return an **array describing the object**, with the
handle under a `ptr` key:

```php
$texture = SDLRender::SDLCreateTexture($r, SDLPixelFormat::RGBA8888, SDLTextureAccess::TARGET, 16, 16);
// ['ptr' => 5225439472, 'format' => …, 'w' => 16, 'h' => 16, 'refcount' => 1]

SDLRender::SDLDestroyTexture($texture['ptr']);   // subsequent calls take the int
```

Passing the array where an `int` is expected is a `TypeError` from the
extension, which is at least a loud failure.

`SDLRenderReadPixels` returns a full surface array whose `pixels` member is
itself `['ptr' => …, 'data' => [...]]`, with `data` an array of packed
integers, one per pixel, in the surface's `format`. On macOS that format is
`SDL_PIXELFORMAT_ARGB8888` (`372645892`), so a pixel cleared to RGB(12,34,56)
reads back as `0xFF0C2238`. `examples/proof_window_typed.php` byte-checks
76,800 of them.

## Return types that disagree with SDL's own

`SDLRenderClear` returns `void`. `SDL_RenderClear` returns `bool`. The failure
signal is discarded by the extension; a caller who needs it must check
`SDLError::SDLGetError()`.

Three methods return `?array` where the rest return `array`.

## Failure is reported the SDL way

`false`, `0`, or `''`, with a message left in `SDL_GetError()`. **This package
never throws.** Converting that convention into exceptions is policy, and
policy is composition — see [projection-rule.md](/projection-rule.md).

Note also that `SDL_GetError()` is not cleared by a successful call. Call
`SDLError::SDLClearError()` before an operation whose error you intend to read,
or you may read a stale message from something unrelated.

## Method names flatten SDL's underscores

`SDL_GL_CreateContext` is `SDLGLCreateContext`. `SDL_EGL_GetCurrentDisplay` is
`SDLEGLGetCurrentDisplay`. `SDL_rand` is `SDLRand` — the case changes too.

Two methods are not named `SDL*` at all:
`SDLGPU::readFromGPUTransferBuffer` and `SDLGPU::writeToGPUTransferBuffer`.

## Seven classes are empty

`SDLAssert`, `SDLList`, `SDLLog`, `SDLUtils`, `Events\SDLKeymap`,
`Events\SDLScancodeTables` and `Events\SDLWindowEvents` declare no public
methods at 0.7.0. They are projected as empty final classes rather than
omitted, so the parity gate can see the class exists and that nothing was
silently skipped.

## Four helpers are not public

`SDLSurface::buildSurfaceArray`, `packSurfaceFromPtr`, `buildPaletteArray` and
`packPaletteFromPtr` are non-public and therefore not projected. If you count
720 methods by reflection and this package says 716, that is the difference.
