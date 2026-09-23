<?php
declare(strict_types=1);

namespace App\Core;

/**
 * PHP Enum for common framework constants.
 *
 * Usage:
 *   HttpMethods::GET->value        // 'GET'
 *   LogLevel::Error->value         // 'error'
 *   QueueStatus::Pending->value    // 'pending'
 */

enum HttpMethods: string
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case PATCH = 'PATCH';
    case DELETE = 'DELETE';
    case OPTIONS = 'OPTIONS';
    case HEAD = 'HEAD';
}

enum LogLevel: string
{
    case Emergency = 'emergency';
    case Alert = 'alert';
    case Critical = 'critical';
    case Error = 'error';
    case Warning = 'warning';
    case Notice = 'notice';
    case Info = 'info';
    case Debug = 'debug';
}

enum QueueStatus: string
{
    case Pending = 'pending';
    case Reserved = 'reserved';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Deleted = 'deleted';
}

enum CacheDriver: string
{
    case File = 'file';
    case Redis = 'redis';
    case Memcached = 'memcached';
    case Array = 'array';
}

enum TenantStrategy: string
{
    case SingleDb = 'single_db';
    case SeparateDb = 'separate_db';
    case SeparateSchema = 'separate_schema';
}

enum AuthGuard: string
{
    case Web = 'web';
    case Api = 'api';
    case Admin = 'admin';
}

enum NotificationChannel: string
{
    case Mail = 'mail';
    case Sms = 'sms';
    case Slack = 'slack';
    case Database = 'db';
}

enum HttpResponseCode: int
{
    case Ok = 200;
    case Created = 201;
    case NoContent = 204;
    case BadRequest = 400;
    case Unauthorized = 401;
    case Forbidden = 403;
    case NotFound = 404;
    case UnprocessableEntity = 422;
    case InternalError = 500;
    case ServiceUnavailable = 503;
}
