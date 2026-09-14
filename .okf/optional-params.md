---
type: Decision
title: Optional parameters forward a trimmed argument list, never a guessed default
description: >-
  ext-sdl3 declares 111 parameters optional but reports no default through
  reflection. Rather than invent one, the 73 affected methods forward only the
  arguments the caller actually supplied.
tags: [projection, reflection, zephir, defaults, sdl3]
status: draft
generated:
  by: claude-fable-5
  at: 2026-09-13T00:00:00Z
---

# Optional parameters

## The problem

ext-sdl3 is compiled with Zephir, and its arginfo marks parameters optional
without recording the default value. Reflection shows this as
`isOptional() === true` while `isDefaultValueAvailable() === false`:

```
SDLRender::SDLCreateRenderer(int $window, ??? $name = <optional, no default>)
SDLRender::SDLRenderGeometryRaw(…, int $colorStride = <optional, no default>)
```

111 parameters across 73 methods are like this. PHP will not let a projection
declare a parameter optional without writing *some* default, so the generator
appears to be forced into guessing what ext-sdl3 would have used.

Guessing is the thing this layer is not allowed to do. If the extension's
internal default for `$colorStride` is not the `0` the projection wrote, then a
caller who omits the argument gets different behaviour through the typed
surface than through the extension — precisely the class of silent divergence
the projection exists to rule out.

## What was measured

Two facts, established by probing the loaded extension rather than reasoning
about Zephir:

1. **For untyped optional parameters, omitting is identical to passing `null`.**
   `SDLCreateRenderer($w)` and `SDLCreateRenderer($w, null)` both produce a
   working renderer on the `metal` backend, and `SDLRenderReadPixels($r)` and
   `SDLRenderReadPixels($r, null)` return byte-identical pixel data. 79 of the
   111 are untyped — the 0.8.0 wave adds `SDLVulkan::SDLVulkanLoadLibrary`'s
   `$path`, confirmed the same way: omitted or `null`, SDL searches its own
   default search path either way.
2. **For typed optional parameters, the default is not observable from PHP.**
   32 parameters — `int $colorStride`, `bool $cycle`, `int $scaleMode` and
   friends. Nothing in the extension's PHP surface reveals what it uses.

So `null` could have been justified for the majority and nothing could be
justified for the rest.

## The decision

**Forward exactly the arguments the caller supplied.** The 72 affected methods
emit:

```php
public static function SDLCreateRenderer(int $window, mixed $name = null): int
{
    return ExtSDLRender::SDLCreateRenderer(
        ...array_slice([$window, $name], 0, func_num_args())
    );
}
```

The defaults written into the signature — `0`, `0.0`, `false`, `''`, `[]`,
`null` by declared type — exist only to make the declaration legal PHP. They
are **never forwarded when the caller omits the argument**, because
`array_slice(…, 0, func_num_args())` cuts the list to the caller's own arity.
An omitted argument stays omitted and the extension's own default applies,
whatever it is. The projection never has to know.

This is still exactly one extension call, so the projection law holds and
`verify-parity.mjs` passes.

## Why this works with named arguments too

PHP fills skipped parameters with their declared defaults and counts up to the
last one supplied, so `func_num_args()` is right for the ordinary cases:

* `f(a: 1)` → `func_num_args()` is 1 → one argument forwarded. Correct.
* `f(a: 1, b: 2, c: 3)` → 3 → three forwarded. Correct.

The one imperfect case is a *gap*: `f(a: 1, c: 3)` reports 3, so `$b` is
forwarded carrying the cosmetic default. That is unavoidable in any scheme and
affects only callers who skip a middle argument by name.

## Trade-off accepted

A static analyser reading the signature sees `= 0` and will believe a caller
who omits the argument sends `0`. That is the cost, and it is the cheaper
error: it misleads a tool about a value that is in fact never sent, whereas the
alternative misleads the *extension* about a value the caller never chose.

## Arity is enforced, not merely declared

The extension rejects a short call itself:

```
ArgumentCountError: SDLBlitSurfaceScaled() expects at least 2 arguments, 1 given
```

and `verify-parity.mjs` compares both `getNumberOfParameters()` and
`getNumberOfRequiredParameters()` against reflection for all 727 methods, so
the projection cannot quietly make a required parameter optional or the
reverse. `tests/Feature/VideoRoundTripTest.php` exercises the trimmed list
directly: it calls `SDLRenderReadPixels($renderer)` with the optional rect
omitted and asserts the pixels come back correct.

## If ext-sdl3 starts reporting defaults

A future Zephir may emit `ZEND_BEGIN_ARG_WITH_DEFAULT_VALUE`. If
`isDefaultValueAvailable()` becomes true, the right move is to emit the real
default and forward every parameter plainly — the `array_slice` form exists
only because the information is missing. `Emitter::renderMethod()` is the one
place to change.
