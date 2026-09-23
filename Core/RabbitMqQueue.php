<?php
declare(strict_types=1);

namespace App\Core;

/**
 * RabbitMQ Queue Driver — AMQP-based job queue.
 *
 * Requires: enqueued/amqp-lib or php-amqplib/php-amqplib
 * Config: queue.connections.rabbitmq.driver = 'rabbitmq'
 */
class RabbitMqQueue
{
    protected ?object $connection = null;
    protected ?object $channel = null;
    protected string $queueName;
    protected Logger $logger;

    public function __construct(Logger $logger, array $config = [])
    {
        $this->logger = $logger;
        $this->queueName = $config['queue'] ?? 'default';

        // Try php-amqplib first
        if (class_exists(\PhpAmqpLib\Connection\AMQPStreamConnection::class)) {
            $this->connection = new \PhpAmqpLib\Connection\AMQPStreamConnection(
                $config['host'] ?? 'localhost',
                (int)($config['port'] ?? 5672),
                $config['username'] ?? 'guest',
                $config['password'] ?? 'guest'
            );
            $this->channel = $this->connection->channel();
        }
    }

    public function isConnected(): bool
    {
        return $this->channel !== null;
    }

    public function push(string $jobClass, array $data = [], string $queue = 'default'): int
    {
        if (!$this->channel) {
            throw new \RuntimeException('RabbitMQ connection not available');
        }

        $this->channel->queue_declare($queue, false, true, false, false);

        $payload = json_encode([
            'job_class'  => $jobClass,
            'data'       => $data,
            'dispatched' => time(),
            'queue'      => $queue,
        ], JSON_THROW_ON_ERROR);

        $msg = new \PhpAmqpLib\Message\AMQPMessage($payload, [
            'content_type' => 'application/json',
            'delivery_mode' => 2, // persistent
        ]);

        $this->channel->basic_publish($msg, '', $queue);
        $this->logger->info("RabbitMQ job pushed: {$jobClass} to {$queue}");

        return 1;
    }

    public function pop(string $queue = 'default'): ?array
    {
        if (!$this->channel) {
            return null;
        }

        $this->channel->queue_declare($queue, false, true, false, false);

        $msg = $this->channel->basic_get($queue, false);
        if ($msg === null) {
            return null;
        }

        $body = json_decode($msg->getBody(), true);
        $this->channel->basic_ack($msg->getDeliveryTag());

        return $body;
    }

    public function size(string $queue = 'default'): int
    {
        if (!$this->channel) {
            return 0;
        }
        list($count) = $this->channel->queue_declare($queue, false, true, false, false);
        return (int)$count;
    }
}
