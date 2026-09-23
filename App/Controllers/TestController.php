<?php
declare(strict_types=1);


namespace App\Controllers;

use App\Core\Queue;
use App\Core\Request;
use App\Core\Response;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\MomoService;
use Jobs\GenerateReportJob;
use Repositories\TransactionRepository;
use Services\EmailService;
use Services\Payments\PaymentFactory;
use Services\Payments\PaymentService;
use Services\Reports\ReportFactory;
use Services\SMSService;

class TestController
{
    protected EmailService $mail;
    protected SMSService $sms;
    protected Response $response;
    protected TransactionRepository $transactions;
    //protected MomoService $momo;

    /**
     * TestController constructor.
     *
     * @param MoMoService $momo Service for Mobile Money operations
     * @param TransactionRepository $transactions Repository for transaction data
     * @param SMSService $sms Service for SMS operations
     * @param Response $response Response object
     */
    public function __construct(Response $response) {
        $this->response = $response;
        $this->mail = new EmailService();
    }

    /**
     * Handles Mobile Money webhooks.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function webhook(Request $request, Response $response): Response
    {
        $dto = \App\DTOs\PaymentWebhookDTO::fromArray((array)($request->json() ?? []));

        if (empty($dto->transactionId)) {
            $response->setContent((string)json_encode([
                'success' => false,
                'message' => 'Invalid webhook payload'
            ]));
            $response->setStatusCode(400);
            return $response;
        }

        $payment = Payment::where('transaction_id', $dto->transactionId)->first();

        if (!$payment) {
            $response->setContent((string)json_encode([
                'success' => false,
                'message' => 'Transaction not found'
            ]));
            $response->setStatusCode(404);
            return $response;
        }

        $payment->status = $dto->status;
        $payment->reference = $dto->reference;
        $payment->reason = $dto->reason;
        $payment->save();

        $response->setContent((string)json_encode([
            'success' => true,
            'message' => "Payment status updated to {$dto->status}"
        ]));
        return $response;
    }


    /**
     * Sends a test email.
     *
     * @return string JSON response
     */
    public function mail(): string
    {
        $sent = $this->mail->send('senzu.dogi23@gmail.com', 'You are receiving a test mail', 'Mail Testing');

        if ($sent) {
            return (string)json_encode(['success' => true, 'message' => 'Email sent successfully']);
        }
        return (string)json_encode(['success' => false, 'message' => 'Email not sent']);
    }

    
    /**
     * Sends a test SMS.
     *
     * @return string JSON response
     */
    public function sms(): string
    {
        $sent = $this->sms->send('+233242737120', 'You are receiving a test SMS');

        if ($sent) {
            return (string)json_encode(['success' => true, 'message' => 'Email sent successfully']);
        }
        return (string)json_encode(['success' => false, 'message' => 'Email not sent']);
    }

    /**
     * Queues a PDF report generation job.
     *
     * @return string Message
     */
    public function pdfReport(): string
    {
        $data = [
            ['ID', 'Name', 'Score'],
            [1, 'Alice', 95],
            [2, 'Bob', 88],
        ];

        $type = (string)($_GET['type'] ?? 'pdf');
        $filePath = __DIR__ . "/../../storage/report_$type." . $type;

        $job = new GenerateReportJob($data, $type, $filePath);

        $queue = new Queue();
        $queue->push($job);

        return "Report generation queued. Check later at /report/download?type=$type";
    }

    /**
     * Initiates a payment charge.
     *
     * @param Request $request
     * @return Response
     */
    public function pay(Request $request): Response
    {
        $dto = \App\DTOs\PaymentChargeDTO::fromArray($request->getBodyParams());
        $reference = (string)uniqid('txn_');

        $payment = new PaymentService($dto->provider);
        $result = $payment->charge($dto->phone, $dto->amount, $reference);

        $response = new Response();
        $response->setContent((string)json_encode($result));
        return $response;
    }

    /**
     * Handles Stripe webhooks.
     *
     * @param Request $request
     * @return string JSON response
     */
    public function stripe(Request $request): string
    {
        $payload = (string)@file_get_contents('php://input');
        $sigHeader = (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
        $endpointSecret = (string)getenv('STRIPE_WEBHOOK_SECRET');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sigHeader, $endpointSecret
            );
        } catch (\Exception $e) {
            http_response_code(400);
            return (string)json_encode(['error' => 'Invalid payload/signature']);
        }

        $object = $event->data->object;
        $transactionId = (string)($object->id ?? uniqid('stripe_', true));
        $amount = (float)(isset($object->amount) ? $object->amount / 100 : 0);
        $currency = strtoupper((string)($object->currency ?? 'USD'));

        if ($event->type === 'payment_intent.succeeded') {
            $this->transactions->create([
                'gateway' => 'stripe',
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'succeeded',
                'payload' => (string)json_encode($object),
            ]);
        } elseif ($event->type === 'payment_intent.payment_failed') {
            $this->transactions->create([
                'gateway' => 'stripe',
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'failed',
                'payload' => (string)json_encode($object),
            ]);
        }

        http_response_code(200);
        return (string)json_encode(['status' => 'recorded']);
    }

    /**
     * Handles PayPal webhooks.
     *
     * @param Request $request
     * @return string JSON response
     */
    public function paypal(Request $request): string
    {
        $payload = (array)json_decode((string)file_get_contents("php://input"), true);
        $transactionId = (string)($payload['resource']['id'] ?? uniqid('paypal_', true));
        $amount = (float)($payload['resource']['amount']['value'] ?? 0);
        $currency = (string)($payload['resource']['amount']['currency_code'] ?? 'USD');
        $eventType = (string)($payload['event_type'] ?? 'unknown');

        $status = match ($eventType) {
            'PAYMENT.CAPTURE.COMPLETED' => 'succeeded',
            'PAYMENT.CAPTURE.DENIED' => 'failed',
            default => 'pending',
        };

        $this->transactions->create([
            'gateway' => 'paypal',
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'currency' => $currency,
            'status' => $status,
            'payload' => (string)json_encode($payload),
        ]);

        http_response_code(200);
        return (string)json_encode(['status' => 'recorded']);
    }

    /**
     * Initiates a Mobile Money payment.
     *
     * @param Request $request
     * @return Response
     */
    public function initiateMoMo(Request $request): Response
    {
        $dto = \App\DTOs\PaymentChargeDTO::fromArray($request->getBodyParams());
        $reference = (string)uniqid('momo_');

        $result = $this->momo->requestToPay($dto->phone, $dto->amount, $reference);

        // log transaction
        $this->transactions->create([
            'gateway' => 'mtn_momo',
            'transaction_id' => (string)($result['referenceId'] ?? ''),
            'amount' => $dto->amount,
            'currency' => (string)getenv('MOMO_CURRENCY'),
            'status' => 'pending',
            'payload' => (string)json_encode($result['body'] ?? [])
        ]);

        $response = new Response();
        $response->setContent((string)json_encode($result));
        return $response;
    }

    /**
     * Handles Mobile Money payment request.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function momoPay(Request $request, Response $response): Response
    {
        $transactionId = (string)uniqid('txn_');
        $dto = \App\DTOs\PaymentChargeDTO::fromArray($request->getBodyParams());

        try {
            $result = $this->momo->requestToPay($transactionId, $dto->amount, $dto->phone);
            $response->setContent((string)json_encode([
                'success' => true,
                'message' => 'Payment request sent',
                'data' => $result
            ]));
            return $response;
        } catch (\Exception $e) {
            $response->setContent((string)json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
            $response->setStatusCode(500);
            return $response;
        }
    }
}
