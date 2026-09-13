<?php

declare(strict_types=1);

/*
| The projection against the extension it projects.
|
| ext-sdl3 ships no annotated source, so Reflection is the specification and
| this file is where the package states that it matches. The gate of the same
| name runs the identical comparison from the command line; keeping both means
| a developer running `pest` and CI running the gate cannot disagree.
*/

use Jovian\Bindings\Sdl3\Generator\ExtSurface;

beforeEach(function (): void {
    sdl3RequireExtension();
});

/** `Sdl3\SDL\Video\SDLVideo` => `Jovian\Bindings\Sdl3\Video\SDLVideo`. */
function projectedFqcn(string $extFqcn): string
{
    $parts = explode('\\', $extFqcn);

    return 'Jovian\\Bindings\\Sdl3\\' . implode('\\', array_slice($parts, 2));
}

it('projects every class the extension declares', function (): void {
    $ext = new ExtSurface();
    expect($ext->classes)->not->toBeEmpty();

    foreach ($ext->classes as $class) {
        expect(class_exists(projectedFqcn($class->fqcn)))
            ->toBeTrue(projectedFqcn($class->fqcn) . ' is missing');
    }
});

it('projects every public method exactly once, with a matching arity', function (): void {
    $ext = new ExtSurface();
    $seen = 0;

    foreach ($ext->classes as $class) {
        $projected = new ReflectionClass(projectedFqcn($class->fqcn));
        $names = [];
        foreach ($projected->getMethods() as $method) {
            $names[] = $method->getName();
        }
        expect($names)->toEqual(array_unique($names), $class->fqcn . ' declares a method twice');

        foreach ($class->methods as $method) {
            expect($projected->hasMethod($method->name))
                ->toBeTrue($class->fqcn . '::' . $method->name . ' is not projected');

            $mine = $projected->getMethod($method->name);
            $theirs = new ReflectionMethod($class->fqcn, $method->name);

            expect($mine->getNumberOfParameters())->toBe($theirs->getNumberOfParameters(), $method->name . ' arity');
            expect($mine->getNumberOfRequiredParameters())->toBe($theirs->getNumberOfRequiredParameters(), $method->name . ' required arity');
            expect($mine->isStatic())->toBeTrue($method->name . ' must be static, as the extension is');
            $seen++;
        }

        // Nothing extra: a projected method with no extension counterpart
        // would be composition wearing a projection's clothes.
        foreach ($projected->getMethods() as $method) {
            expect(method_exists($class->fqcn, $method->getName()))
                ->toBeTrue($method->getName() . ' is projected but the extension has no such method');
        }
    }

    expect($seen)->toBe($ext->methodCount());
});

it('keeps every parameter name, so named arguments still work', function (): void {
    $ext = new ExtSurface();

    foreach ($ext->classes as $class) {
        foreach ($class->methods as $method) {
            $mine = new ReflectionMethod(projectedFqcn($class->fqcn), $method->name);
            $names = array_map(fn (ReflectionParameter $p): string => $p->getName(), $mine->getParameters());
            $expected = array_map(fn ($p): string => $p->name, $method->params);
            expect($names)->toBe($expected, $class->fqcn . '::' . $method->name);
        }
    }
});
