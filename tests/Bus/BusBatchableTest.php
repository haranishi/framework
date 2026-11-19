<?php

namespace Illuminate\Tests\Bus;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\BatchRepository;
use Illuminate\Container\Container;
use Illuminate\Support\Testing\Fakes\BatchFake;
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use PHPUnit\Framework\TestCase;
use stdClass;

class BusBatchableTest extends TestCase
{
    use VerifiesDoubles;

    public function test_batch_may_be_retrieved()
    {
        $class = new class
        {
            use Batchable;
        };

        $this->assertSame($class, $class->withBatchId('test-batch-id'));
        $this->assertSame('test-batch-id', $class->batchId);

        Container::setInstance($container = new Container);

        $repository = Double::for(BatchRepository::class);
        $repository->expects('find')->with('test-batch-id')->returns('test-batch');
        $container->instance(BatchRepository::class, $repository);

        $this->assertSame('test-batch', $class->batch());

        Container::setInstance(null);
    }

    public function test_with_fake_batch_sets_and_returns_fake()
    {
        $job = new class
        {
            use Batchable;
        };

        [$self, $batch] = $job->withFakeBatch('test-batch-id', 'test-batch-name', 3, 3, 0, [], []);

        $this->assertSame($job, $self);
        $this->assertInstanceOf(BatchFake::class, $batch);
        $this->assertSame($batch, $job->batch());
        $this->assertSame('test-batch-id', $job->batch()->id);
        $this->assertSame('test-batch-name', $job->batch()->name);
        $this->assertSame(3, $job->batch()->totalJobs);
    }

    public function test_jobs_added_to_a_fake_batch_are_pending()
    {
        $job = new class
        {
            use Batchable;
        };

        [, $batch] = $job->withFakeBatch(totalJobs: 4, pendingJobs: 2);

        $batch->add(new stdClass);

        $this->assertSame(5, $batch->totalJobs);
        $this->assertSame(3, $batch->pendingJobs);
        $this->assertSame(2, $batch->processedJobs());
        $this->assertSame(40, $batch->progress());
    }

    public function test_batching_reflects_cancelled_state()
    {
        $job = new class
        {
            use Batchable;
        };

        $job->withFakeBatch('test-batch-id', 'test-batch-name');

        // Initially not cancelled
        $this->assertTrue($job->batching());

        // Cancel the batch and ensure batching() returns false
        $job->batch()->cancel();
        $this->assertFalse($job->batching());
    }

    public function test_batching_returns_false_when_batch_is_finished()
    {
        $job = new class
        {
            use Batchable;
        };

        $job->withFakeBatch('test-batch-id', 'test-batch-name', finishedAt: \Carbon\CarbonImmutable::now());

        $this->assertFalse($job->batching());
    }
}
