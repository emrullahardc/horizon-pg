<?php

namespace HorizonPg\Tests;

use Orchestra\Testbench\TestCase;
use HorizonPg\HorizonServiceProvider;
use Illuminate\Support\Facades\DB;
use HorizonPg\JobPayload;

class RepositoryTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [HorizonServiceProvider::class];
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    public function test_jobs_can_be_stored_and_retrieved()
    {
        $repository = app(\HorizonPg\Contracts\JobRepository::class);
        $payload = new JobPayload(json_encode(['id' => 'test-job', 'displayName' => 'TestJob']));

        $repository->pushed('connection', 'default', $payload);

        $job = $repository->getJobs(['test-job'])->first();
        $this->assertEquals('test-job', $job->id);
        $this->assertEquals('pending', $job->status);
    }

    public function test_tags_can_be_stored_and_counted()
    {
        $repository = app(\HorizonPg\Contracts\TagRepository::class);

        $repository->add('test-job', ['tag1', 'tag2']);

        $this->assertEquals(1, $repository->count('tag1'));
        $this->assertEquals(1, $repository->count('tag2'));
        $this->assertContains('test-job', $repository->jobs('tag1'));
    }

    public function test_delayed_jobs_are_tracked()
    {
        $repository = app(\HorizonPg\Contracts\JobRepository::class);
        $payload = new JobPayload(json_encode(['id' => 'delayed-job', 'displayName' => 'DelayedJob']));

        $repository->pushed('connection', 'default', $payload);

        $job = DB::table('horizon_jobs')->where('id', 'delayed-job')->first();
        $this->assertEquals('pending', $job->status);
    }
}
