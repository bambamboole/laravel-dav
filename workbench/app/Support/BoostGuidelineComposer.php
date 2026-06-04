<?php

declare(strict_types=1);

namespace Workbench\App\Support;

use Laravel\Boost\Install\GuidelineComposer;

use function Orchestra\Testbench\package_path;

// Composes custom guidelines from the package root's .ai/guidelines instead of
// the Testbench skeleton that base_path() points at.
class BoostGuidelineComposer extends GuidelineComposer
{
    public function customGuidelinePath(string $path = ''): string
    {
        return package_path($this->userGuidelineDir.'/'.ltrim($path, '/'));
    }
}
