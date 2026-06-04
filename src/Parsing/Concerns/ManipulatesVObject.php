<?php

namespace Bambamboole\LaravelDav\Parsing\Concerns;

use Sabre\VObject\Component;

trait ManipulatesVObject
{
    private function addIfPresent(Component $component, string $name, ?string $value): void
    {
        if (! empty($value)) {
            $component->add($name, $value);
        }
    }

    private function setOrRemove(Component $component, string $name, ?string $value): void
    {
        unset($component->{$name});
        $this->addIfPresent($component, $name, $value);
    }
}
