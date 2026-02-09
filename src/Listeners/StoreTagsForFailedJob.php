<?php

namespace HorizonPg\Listeners;

use HorizonPg\Contracts\TagRepository;
use HorizonPg\Events\JobFailed;

class StoreTagsForFailedJob
{
    /**
     * The tag repository implementation.
     *
     * @var \HorizonPg\Contracts\TagRepository
     */
    public $tags;

    /**
     * Create a new listener instance.
     *
     * @param  \HorizonPg\Contracts\TagRepository  $tags
     * @return void
     */
    public function __construct(TagRepository $tags)
    {
        $this->tags = $tags;
    }

    /**
     * Handle the event.
     *
     * @param  \HorizonPg\Events\JobFailed  $event
     * @return void
     */
    public function handle(JobFailed $event)
    {
        $tags = collect($event->payload->tags())
            ->map(fn ($tag) => 'failed:'.$tag)
            ->all();

        $this->tags->addTemporary(
            config('horizon.trim.failed', 2880), $event->payload->id(), $tags
        );
    }
}
