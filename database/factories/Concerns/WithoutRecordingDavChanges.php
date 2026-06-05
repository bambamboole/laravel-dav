<?php

namespace Bambamboole\LaravelDav\Database\Factories\Concerns;

use Bambamboole\LaravelDav\Support\DavChangeRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait WithoutRecordingDavChanges
{
    /**
     * @param  Collection<int, Model>  $results
     */
    protected function store(Collection $results): void
    {
        DavChangeRecorder::withoutRecording(fn () => parent::store($results));
    }
}
