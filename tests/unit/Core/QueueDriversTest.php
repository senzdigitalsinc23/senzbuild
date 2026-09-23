<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\SqsQueue;
use App\Core\RabbitMqQueue;
use App\Core\KafkaQueue;
use App\Core\ArrayQueue;

class QueueDriversTest extends TestCase
{
    public function test_sqs_queue_class_exists(): void
    {
        $this->assertTrue(class_exists(SqsQueue::class));
    }

    public function test_rabbitmq_queue_class_exists(): void
    {
        $this->assertTrue(class_exists(RabbitMqQueue::class));
    }

    public function test_kafka_queue_class_exists(): void
    {
        $this->assertTrue(class_exists(KafkaQueue::class));
    }

    public function test_array_queue_push_pop(): void
    {
        ArrayQueue::push('TestJob', ['id' => 1], 'default');
        $job = ArrayQueue::pop('default');
        $this->assertNotNull($job);
        $this->assertSame('TestJob', $job['job_class']);
    }

    public function test_array_queue_size(): void
    {
        ArrayQueue::push('Job1', [], 'q1');
        ArrayQueue::push('Job2', [], 'q1');
        $this->assertSame(2, ArrayQueue::size('q1'));
    }

    public function test_array_queue_failed(): void
    {
        ArrayQueue::fail('BadJob', 'Connection lost');
        $failed = ArrayQueue::failed();
        $this->assertCount(1, $failed);
        $this->assertSame('BadJob', $failed[0]['job_class']);
    }

    public function test_array_queue_flush(): void
    {
        ArrayQueue::push('Job1', [], 'default');
        ArrayQueue::flush();
        $this->assertSame(0, ArrayQueue::size('default'));
    }
}
