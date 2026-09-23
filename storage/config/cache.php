<?php
/**
 * Auto-generated config cache — do not edit manually.
 * Regenerate with: php tools/cache_config.php
 * Generated at: 2026-09-22 19:21:50
 */

return array (
  'api' => 
  array (
    'app' => '',
    'env' => 'local',
    'debug' => 'true',
    'url' => 'http://localhost:8000',
    'api_key' => '',
  ),
  'app' => 
  array (
    'name' => 'API Project',
    'env' => 'local',
    'debug' => 'true',
    'url' => 'http://localhost/api-project',
    'display_errors' => 'true',
    'log_path' => 'D:\\Soft Projx\\API Template\\framework\\config/../storage/logs',
  ),
  'database' => 
  array (
    'driver' => 'mysql',
    'host' => 'localhost',
    'dbname' => 'api_project',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
  ),
  'email' => 
  array (
    'host' => false,
    'username' => false,
    'password' => false,
    'from' => false,
    'name' => false,
    'port' => false,
    'encryption' => false,
  ),
  'pdo_options' => 
  array (
    3 => 2,
    19 => 2,
    20 => false,
  ),
  'queue' => 
  array (
    'default' => 'database',
    'connections' => 
    array (
      'database' => 
      array (
        'driver' => 'database',
        'table' => 'queue_jobs',
        'queue' => 'default',
        'retry_after' => 90,
        'min_attempts' => 3,
        'max_attempts' => 10,
        'backoff' => 'exponential',
      ),
      'sync' => 
      array (
        'driver' => 'sync',
      ),
    ),
    'workers' => 
    array (
      'timeout' => 60,
      'sleep' => 3,
      'max_jobs' => 0,
      'memory' => 128,
    ),
    'failed' => 
    array (
      'driver' => 'database-table',
      'table' => 'queue_failed_jobs',
      'expire' => 604800,
    ),
  ),
  'sms' => 
  array (
    'sid' => false,
    'token' => false,
    'from' => false,
  ),
  'storage_mimes' => 
  array (
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'svg' => 'image/svg+xml',
    'pdf' => 'application/pdf',
    'txt' => 'text/plain',
    'csv' => 'text/csv',
    'json' => 'application/json',
    'zip' => 'application/zip',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'mp4' => 'video/mp4',
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav',
    'exe' => 'application/x-msdownload',
  ),
);
