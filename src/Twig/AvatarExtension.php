<?php

namespace App\Twig;

use Composer\InstalledVersions;
use DiceBear\Avatar;
use DiceBear\Style;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AvatarExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('dicebear_avatar', [$this, 'generateAvatar']),
        ];
    }

    public function generateAvatar(string $seed, string $styleName = 'glyphs', int $size = 36): string
    {
        $basePath = InstalledVersions::getInstallPath('dicebear/styles');
        $style = Style::fromJson(file_get_contents($basePath . '/src/' . $styleName . '.json'));
        $avatar = new Avatar($style, [
            'seed' => $seed,
            'size' => $size,
        ]);

        return $avatar->toDataUri();
    }
}
