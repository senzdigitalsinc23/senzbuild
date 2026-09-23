<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Kafka Queue Driver — Apache Kafka-based job queue.
 *
 * Requires: confluentinc/confluent-kafka-php
 * Config: queue.connections.kafka.driver = 'kafka'
 */
class KafkaQueue
{
    protected ?object $producer = null;
    protected ?object $consumer = null;
    protected string $topic;
    protected Logger $logger;

    public function __construct(Logger $logger, array $config = [])
    {
        $this->logger = $logger;
        $this->topic = $config['topic'] ?? 'jobs';

        if (class_exists(\RdKafka\Producer::class)) {
            $this->producer = new \RdKafka\Producer();
            $this->producer->addBrokers(implode(',', $config['brokers'] ?? ['localhost:9092']));
        }
    }

    public function isConnected(): bool
    {
        return $this->producer !== null;
    }

    public function push(string $jobClass, array $data = [], string $queue = 'default'): int
    {
        if (!$this->producer) {
            throw new \RuntimeException('Kafka extension not available');
        }

        $payload = json_encode([
            'job_class'  => $jobClass,
            'data'       => $data,
            'dispatched' => time(),
            'queue'      => $queue,
        ], JSON_THROW_ON_ERROR);

        $topic = $this->producer->newTopic($this->topic);
        $topic->produce(RD_KAFKA_PARTITION_UA, 0, $payload);
        $this->producer->poll(0);
        $this->producer->flush(30000);

        $this->logger->info("Kafka job pushed: {$jobClass} to topic {$this->topic}");
        return 1;
    }

    public function pop(string $queue = 'default'): ?array
    {
        if (!$this->producer) {
            return null;
        }

        // Kafka consumer would need a separate consumer instance
        // This is a simplified implementation
        return null;
    }

    public function size(string $queue = 'default'): int
    {
        return 0; // Kafka doesn't expose simple queue length
    }
}
