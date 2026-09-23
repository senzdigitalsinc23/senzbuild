<?php
declare(strict_types=1);

namespace App\Core;

/**
 * SQS Queue Driver — AWS Simple Queue Service.
 *
 * Requires: aws/aws-sdk-php
 * Config: queue.connections.sqs.driver = 'sqs'
 */
class SqsQueue
{
    protected ?object $client = null;
    protected string $queueUrl;
    protected Logger $logger;

    public function __construct(Logger $logger, array $config = [])
    {
        $this->logger = $logger;

        if (class_exists('\Aws\Sqs\SqsClient')) {
            $this->client = new \Aws\Sqs\SqsClient($config);
        }
    }

    public function isConnected(): bool
    {
        return $this->client !== null;
    }

    public function push(string $jobClass, array $data = [], string $queue = 'default'): int
    {
        if (!$this->client) {
            throw new \RuntimeException('AWS SDK not available for SQS driver');
        }

        $payload = json_encode([
            'job_class'  => $jobClass,
            'data'       => $data,
            'dispatched' => time(),
            'queue'      => $queue,
        ], JSON_THROW_ON_ERROR);

        $result = $this->client->sendMessage([
            'QueueUrl'    => $this->queueUrl,
            'MessageBody' => $payload,
        ]);

        $this->logger->info("SQS job pushed: {$jobClass}");
        return (int)$result['MessagesProcessedCount'] ?? 1;
    }

    public function pop(string $queue = 'default'): ?array
    {
        if (!$this->client) {
            return null;
        }

        $result = $this->client->receiveMessage([
            'QueueUrl' => $this->queueUrl,
            'MaxNumberOfMessages' => 1,
            'VisibilityTimeout' => 30,
        ]);

        $messages = $result['Messages'] ?? [];
        if (empty($messages)) {
            return null;
        }

        $msg = $messages[0];
        $body = json_decode($msg['Body'], true);

        // Delete from SQS after processing
        $this->client->deleteMessage([
            'QueueUrl'      => $this->queueUrl,
            'ReceiptHandle' => $msg['ReceiptHandle'],
        ]);

        return $body;
    }

    public function size(string $queue = 'default'): int
    {
        if (!$this->client) {
            return 0;
        }
        $result = $this->client->getQueueAttributes([
            'QueueUrl' => $this->queueUrl,
            'AttributeNames' => ['ApproximateNumberOfMessages'],
        ]);
        return (int)($result['Attributes']['ApproximateNumberOfMessages'] ?? 0);
    }
}
