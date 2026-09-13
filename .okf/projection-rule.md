---
type: Decision
title: Projection, not composition — and why this package has no Runtime
description: >-
  A method belongs in jovian/sdl3 only if it is exactly one extension call with
  the same arguments in the same order. ext-sdl3 has no object model, so unlike
  jovian/metal there is nothing here to hand-write at all.
tags: [projection, layering, scope, sdl3, runtime]
status: draft
generated:
  by: claude-fable-5
  at: 2026-09-13T00:00:00Z
---

# Projection, not composition

`ext-sdl3` binds SDL3 1:1 and withholds two things. Constants are withheld and
this package is their home. Composition is withheld too, and its home is one
layer further up, in `venusian-sdl3`.

The test for whether a method belongs here is mechanical: **can it be written
as exactly one extension call, with the same arguments in the same order?** If
yes it belongs here. If it needs two calls, a branch on a result, an invented
default, or a reshaped return, it is composition and belongs in
`venusian-sdl3`.

All 716 generated bodies satisfy this. Two independent checks enforce it —
`tests/Generator/GenerateCheckTest.php` and `scripts/gates/verify-parity.mjs`
both count `Ext*::` occurrences per body and require exactly one.

The two things a body is allowed to do beyond the bare call are both
coercions, not decisions:

* unwrap a backed enum to its int (`$x instanceof \BackedEnum ? $x->value : $x`);
* trim the forwarded argument list to the caller's own arity
  (see [optional-params.md](/optional-params.md)).

Neither inspects a value, neither can fail, and neither changes what SDL sees.

## Why there is no `src/Runtime/`

This is the sharpest difference from `jovian/metal`, and it is not an omission.

`ext-metal` has an object model: a Bridge, a handle registry, retain/release,
weak identity. `jovian/metal` therefore hand-projects `ObjCObject`, `Registry`,
`Lifetime` and `Bridge`, and its generated methods are *instance* methods that
read `$this->handle`.

`ext-sdl3` has none of that. Reflection reports:

* **29 classes, 0 functions, 0 constants**;
* **716 public methods, every single one `public static`**;
* no registry call, no bridge call, no lifetime call, nothing resembling glue
  between PHP objects and native ones.

Handles are **raw native pointers widened to a PHP `int`**. `SDL_CreateWindow`
comes back as `4620105136`. PHP refcounting has no relationship to it; the
caller owns the lifetime and must call the matching `SDLDestroyWindow`.

So the faithful projection of an all-static, pointer-as-int extension is an
all-static, pointer-as-int package. Adding an `SDLWindow` object here would be
inventing a lifetime SDL does not have, and inventing lifetimes is composition.
`verify-style.mjs` enforces the consequence: every projection class is `final`,
every method is `static`, and `$this` may not appear.

When someone wants `$window->setTitle('x')`, that is `venusian-sdl3`.

## What the layer above is for

Concretely, these all belong in `venusian-sdl3`, not here:

* an object wrapper that owns a handle and destroys it on `__destruct`;
* normalising the extension's inconsistent array returns into value objects
  (see [quirks.md](/quirks.md));
* an event loop that pumps `SDLReadEvent` and dispatches on `SDLEventType`;
* `init()` helpers that combine `SDL_Init` with error checking and throwing;
* anything that turns SDL's `false`-plus-`SDL_GetError()` convention into an
  exception.

That last one is worth stating plainly: **this package never throws.** SDL
reports failure by returning `false`, `0`, or an empty string and leaving a
message in `SDL_GetError()`. The projection passes that through exactly.
Converting it to an exception is a policy decision, and policy is composition.
