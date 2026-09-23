<?php
namespace Services;

use App\Core\Config;
use App\Core\NotificationService;
use Twilio\Rest\Client;

class SMSService implements NotificationService
{
    protected Client $client;
    protected string $from;

    public function __construct()
    {
        Config::load(dirname(__DIR__) . '/config');
        //show(Config::get('sms.token'));

        // Get DB config from your config helper
        $from = Config::get('sms.from');
        $sid   = Config::get('sms.id');
        $token = Config::get('sms.token');

        $this->client = new Client($sid, $token);
        $this->from = $from;
    }

    public function send(string $to, string $message): bool
    {
        try {
            $this->client->messages->create($to, [
                'from' => $this->from,
                'body' => $message
            ]);

            return true;
        } catch (\Exception $e) {
            echo ("SMS send failed: " . $e->getMessage());
            return false;
        }
    }
}
