<?php

namespace HorizonPg\Listeners;

use HorizonPg\Contracts\TagRepository;
use HorizonPg\Events\JobPushed;

class StoreMonitoredTags
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
     * @param  \HorizonPg\Events\JobPushed  $event
     * @return void
     */
    public function handle(JobPushed $event)
    {
        $monitoring = $this->tags->monitored($event->payload->tags());

        if (! empty($monitoring)) {
            $this->tags->add($event->payload->id(), $monitoring);
        }
    }
}
