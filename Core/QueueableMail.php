<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Queueable Mail — send emails through the queue system.
 *
 * Usage:
 *   class WelcomeEmail extends QueueableMail
 *   {
 *       public function __construct(User $user) { $this->user = $user; }
 *       public function build(): array {
 *           return [
 *               'to'      => $this->user->email,
 *               'subject' => 'Welcome!',
 *               'view'    => 'emails.welcome',
 *               'data'    => ['name' => $this->user->name],
 *           ];
 *       }
 *   }
 *
 *   Queue::push(new WelcomeEmail($user));
 */
abstract class QueueableMail
{
    protected Queue $queue;

    public function __construct()
    {
        $this->queue = app()->make(Queue::class);
    }

    /**
     * Build the email configuration.
     *
     * @return array{to: string, subject: string, view: string, data: array}
     */
    abstract public function build(): array;

    /**
     * Send the queued email.
     */
    public function handle(): void
    {
        $config = $this->build();

        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = Config::get('email.smtp_host', 'smtp.gmail.com');
        $mailer->SMTPAuth = true;
        $mailer->Username = Config::get('email.smtp_user');
        $mailer->Password = Config::get('email.smtp_pass');
        $mailer->SMTPSecure = Config::get('email.encryption', 'ssl');
        $mailer->Port = (int)Config::get('email.port', 465);

        $mailer->setFrom(Config::get('email.from_address', 'noreply@example.com'), Config::get('email.from_name', 'App'));
        $mailer->addAddress($config['to']);
        $mailer->Subject = $config['subject'];

        // Render view
        $viewPath = dirname(__DIR__) . '/app/views/emails/' . $config['view'] . '.php';
        if (file_exists($viewPath)) {
            extract($config['data'] ?? []);
            $mailer->Body = (new View())->render($config['view'], $config['data'] ?? []);
        } else {
            $mailer->Body = "Hello, welcome!";
        }

        $mailer->send();
    }

    /**
     * Queue this email for sending.
     */
    public function queue(): int
    {
        return $this->queue->dispatch(static::class, (array)$this);
    }
}
