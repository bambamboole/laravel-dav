<?php

declare(strict_types=1);

namespace Workbench\App\Support;

use Illuminate\Support\Collection;
use Laravel\Boost\Install\Skill;
use Laravel\Boost\Install\SkillComposer;

use function Orchestra\Testbench\package_path;

// Discovers custom skills from the package root's .ai instead of the Testbench
// skeleton that base_path() points at. Skills are marked non-custom so the
// writer copies them into .claude/skills rather than symlinking to a canonical
// base_path('.ai/skills') that resolves into the vendor skeleton.
class BoostSkillComposer extends SkillComposer
{
    protected function discoverExplicitUserSkills(): Collection
    {
        $path = package_path('.ai/skills');

        if (! is_dir($path)) {
            return collect();
        }

        return collect(glob($path.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR))
            ->map(fn (string $skillPath): ?Skill => $this->parseSkill($skillPath, 'user', custom: false))
            ->filter()
            ->keyBy(fn (Skill $skill): string => $skill->name);
    }

    protected function discoverPackageSpecificUserSkills(): Collection
    {
        $userAiPath = package_path('.ai');

        if (! is_dir($userAiPath)) {
            return collect();
        }

        return $this->discoverPackagePaths($userAiPath)
            ->flatMap(fn (array $package): Collection => $this->discoverSkillsFromPath(
                $package['path'],
                $package['name'],
                $package['version'],
            ));
    }
}
