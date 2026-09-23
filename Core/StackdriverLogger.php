<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Stackdriver/Google Cloud Logging log channel.
 *
 * Sends structured JSON logs compatible with Google Cloud Logging.
 *
 * Usage:
 *   Logger::channel('stackdriver', [
 *       'projectId' => 'my-project',
 *       'logName'   => 'my-app',
 *   ]);
 */
class StackdriverLogger implements \Psr\Log\LoggerInterface
{
    protected string $projectId;
    protected string $logName;
    protected array $buffer = [];
    protected int $bufferSize = 100;

    public function __construct(string $projectId, string $logName = 'default')
    {
        $this->projectId = $projectId;
        $this->logName = $logName;
    }

    public function emergency($message, array $context = []): void
    {
        $this->log(\Psr\Log\LogLevel::EMERGENCY, $message, $context);
    }

    public function alert($message, array $context = []): void
    {
        $this->log(\Psr\Log\LogLevel::ALERT, $message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->log(\Psr\Log\LogLevel::CRITICAL, $message, $context);
    }

    public function error($message, array $context = []): void
    {
        $this->log(\Psr\Log\LogLevel::ERROR, $message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->log(\Psr\Log\LogLevel::WARNING, $message, $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->log(\Psr\Log\LogLevel::NOTICE, $message, $context);
    }

    public function info($message, array $context = []): void
    {
        $this->log(\Psr\Log\LogLevel::INFO, $message, $context);
    }

    public function debug($message, array $context = []): void
    {
        $this->log(\Psr\Log\LogLevel::DEBUG, $message, $context);
    }

    /**
     * @param mixed $level
     */
    public function log($level, $message, array $context = []): void
    {
        $entry = [
            'timestamp' => date('c'),
            'severity'  => $this->mapSeverity($level),
            'message'   => $this->interpolate($message, $context),
            'serviceContext' => [
                'service' => $this->logName,
                'version' => '1.0.0',
            ],
            'labels' => [
                'project_id' => $this->projectId,
                'log_name'   => $this->logName,
            ],
        ];

        $this->buffer[] = $entry;

        if (count($this->buffer) >= $this->bufferSize) {
            $this->flush();
        }
    }

    /**
     * Flush buffered logs to Stackdriver API.
     */
    public function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        foreach ($this->buffer as $entry) {
            $this->sendEntry($entry);
        }
        $this->buffer = [];
    }

    protected function sendEntry(array $entry): void
    {
        // Use Google Cloud Logging API via HTTP
        $url = 'https://logging.googleapis.com/v2/projects/' . urlencode($this->projectId) . '/logEntries:write';
        $payload = json_encode([
            'entries' => [$entry],
        ], JSON_THROW_ON_ERROR);

        $token = $this->getAccessToken();
        if ($token === null) {
            // Fallback: write to stderr
            error_log(json_encode($entry));
            return;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    protected function getAccessToken(): ?string
    {
        // Try OAuth2 metadata server (GCP environments)
        if (function_exists('curl_init')) {
            $ch = curl_init('http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/token');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Metadata-Flavor: Google']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            curl_close($ch);
            if ($result !== false) {
                $data = json_decode($result, true);
                return $data['access_token'] ?? null;
            }
        }
        return null;
    }

    protected function mapSeverity(string $level): string
    {
        $map = [
            \Psr\Log\LogLevel::EMERGENCY => 'EMERGENCY',
            \Psr\Log\LogLevel::ALERT     => 'ALERT',
            \Psr\Log\LogLevel::CRITICAL => 'CRITICAL',
            \Psr\Log\LogLevel::ERROR    => 'ERROR',
            \Psr\Log\LogLevel::WARNING  => 'WARNING',
            \Psr\Log\LogLevel::INFO     => 'INFO',
            \Psr\Log\LogLevel::DEBUG    => 'DEBUG',
            \Psr\Log\LogLevel::NOTICE   => 'DEFAULT',
        ];
        return $map[$level] ?? 'DEFAULT';
    }

    protected function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            $replace['{' . $key . '}'] = is_scalar($val) || $val === null ? (string)$val : json_encode($val);
        }
        return strtr($message, $replace);
    }
}
