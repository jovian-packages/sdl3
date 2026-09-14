<?php

declare(strict_types=1);

use Jovian\Bindings\Sdl3\Events\SDLWindowEvents;
use Jovian\Bindings\Sdl3\Video\SDLMetal;
use Jovian\Bindings\Sdl3\Video\SDLVulkan;

beforeEach(function () {
    if (! extension_loaded('sdl3')) {
        test()->markTestSkipped('ext-sdl3 is not loaded');
    }
});

it('projects the Metal view calls', function () {
    expect(method_exists(SDLMetal::class, 'SDLMetalCreateView'))->toBeTrue()
        ->and(method_exists(SDLMetal::class, 'SDLMetalGetLayer'))->toBeTrue()
        ->and(method_exists(SDLMetal::class, 'SDLMetalDestroyView'))->toBeTrue();
});

it('projects the seven Vulkan calls', function () {
    foreach (['SDLVulkanLoadLibrary', 'SDLVulkanUnloadLibrary', 'SDLVulkanGetVkGetInstanceProcAddr', 'SDLVulkanGetInstanceExtensions', 'SDLVulkanCreateSurface', 'SDLVulkanDestroySurface', 'SDLVulkanGetPresentationSupport'] as $method) {
        expect(method_exists(SDLVulkan::class, $method))->toBeTrue();
    }
});

it('projects the window event reader', function () {
    expect(method_exists(SDLWindowEvents::class, 'SDLReadWindowEvent'))->toBeTrue();
});

it('names the Vulkan instance extensions', function () {
    // SDL_Vulkan_LoadLibrary needs the video subsystem up first — SDL's own
    // precondition, not a projection quirk. Same sdl3InitVideo()/
    // sdl3QuitVideo() pairing as the rest of the suite (tests/Pest.php).
    sdl3InitVideo();

    expect(SDLVulkan::SDLVulkanLoadLibrary())->toBeTrue()
        ->and(SDLVulkan::SDLVulkanGetInstanceExtensions())->toContain('VK_KHR_surface');
    SDLVulkan::SDLVulkanUnloadLibrary();

    sdl3QuitVideo();
});
