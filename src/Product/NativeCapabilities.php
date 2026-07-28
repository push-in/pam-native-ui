<?php

declare(strict_types=1);

namespace Pam\MobileUi\Product;

use Pam\Native\AnimationKeyframe;
use Pam\Native\MediaType;
use Pam\Native\NativeMenuItem;
use Pam\Native\Renderable;
use Pam\Native\UI\Animated;
use Pam\Native\UI\BottomSheet;
use Pam\Native\UI\InteractionRegion;
use Pam\Native\UI\MediaPlayer;
use Pam\Native\UI\WebView;

/**
 * Stable PAM Mobile UI entry points for capability-backed native surfaces.
 *
 * These factories deliberately return Pam Native primitives, so product
 * components can compose them without creating a parallel renderer.
 */
final class NativeCapabilities
{
    private function __construct()
    {
    }

    public static function bottomSheet(
        Renderable $content,
        array $snapPoints = [0.4, 0.9],
        int $index = 0,
    ): BottomSheet {
        return BottomSheet::make($content, $snapPoints, $index)
            ->cornerRadius(24)
            ->dragEnabled()
            ->handleVisible()
            ->backdropDismiss();
    }

    public static function web(string $source): WebView
    {
        return WebView::make($source)
            ->javaScriptEnabled()
            ->domStorageEnabled()
            ->allowsInlineMedia();
    }

    public static function video(string $source, bool $autoPlay = false): MediaPlayer
    {
        return MediaPlayer::make($source, MediaType::Video)
            ->controls()
            ->autoPlay($autoPlay);
    }

    public static function audio(string $source, bool $autoPlay = false): MediaPlayer
    {
        return MediaPlayer::make($source, MediaType::Audio)
            ->controls()
            ->autoPlay($autoPlay);
    }

    /** @param list<NativeMenuItem> $menu */
    public static function interaction(
        Renderable $content,
        array $menu = [],
    ): InteractionRegion {
        return InteractionRegion::make($content)->contextMenu($menu);
    }

    public static function entrance(
        Renderable $content,
        int $durationMs = 280,
    ): Animated {
        return Animated::make(
            $content,
            [
                new AnimationKeyframe(0.0, opacity: 0.0, translationY: 12.0),
                new AnimationKeyframe(1.0, opacity: 1.0, translationY: 0.0),
            ],
            $durationMs,
        );
    }
}
