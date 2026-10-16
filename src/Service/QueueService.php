<?php

declare(strict_types=1);

namespace LabTrack\Service;

use PDO;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;
use Throwable;

class QueueService
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function getPdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        if (function_exists('db_pdo')) {
            $this->pdo = db_pdo();
            return $this->pdo;
        }

        throw new RuntimeException('Database connection (PDO) is not configured or available.');
    }

    /**
     * Enqueue an email notification job into notification_jobs table.
     *
     * @param array<string, mixed> $extraPayload
     */
    public function enqueueEmail(
        string $recipient,
        string $subject,
        string $bodyHtml,
        array $extraPayload = [],
        int $delaySeconds = 0
    ): int {
        $pdo = $this->getPdo();

        $payload = array_merge([
            'body' => $bodyHtml,
        ], $extraPayload);

        $availableAt = date('Y-m-d H:i:s', time() + max(0, $delaySeconds));

        $sql = 'INSERT INTO notification_jobs
                (channel, recipient, subject, payload, status, attempts, max_attempts, available_at)
                VALUES
                (:channel, :recipient, :subject, :payload, \'pending\', 0, 3, :available_at)';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':channel' => 'email',
            ':recipient' => $recipient,
            ':subject' => $subject,
            ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ':available_at' => $availableAt,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Reserve and fetch the next pending job.
     *
     * @return array<string, mixed>|null
     */
    public function fetchNextPendingJob(): ?array
    {
        $pdo = $this->getPdo();

        $pdo->beginTransaction();
        try {
            $sql = 'SELECT * FROM notification_jobs
                    WHERE status = \'pending\' AND available_at <= NOW()
                    ORDER BY job_id ASC
                    LIMIT 1
                    FOR UPDATE';
            $stmt = $pdo->query($sql);
            if ($stmt === false) {
                $pdo->rollBack();
                return null;
            }

            $job = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$job) {
                $pdo->rollBack();
                return null;
            }

            $upd = $pdo->prepare('UPDATE notification_jobs SET status = \'processing\' WHERE job_id = :id');
            $upd->execute([':id' => $job['job_id']]);

            $pdo->commit();
            return $job;
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('QueueService::fetchNextPendingJob error: ' . $e->getMessage());
            return null;
        }
    }

    public function markJobSent(int $jobId): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare('UPDATE notification_jobs SET status = \'sent\' WHERE job_id = :id');
        $stmt->execute([':id' => $jobId]);
    }

    public function markJobFailed(int $jobId, string $errorMessage): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare('SELECT attempts, max_attempts FROM notification_jobs WHERE job_id = :id');
        $stmt->execute([':id' => $jobId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return;
        }

        $attempts = (int) $row['attempts'] + 1;
        $maxAttempts = (int) $row['max_attempts'];

        if ($attempts >= $maxAttempts) {
            $upd = $pdo->prepare('UPDATE notification_jobs SET status = \'failed\', attempts = :att, last_error = :err WHERE job_id = :id');
            $upd->execute([':att' => $attempts, ':err' => $errorMessage, ':id' => $jobId]);
        } else {
            // Exponential backoff: 30s, 60s, 120s
            $backoffSeconds = (int) (pow(2, $attempts) * 15);
            $nextAvailable = date('Y-m-d H:i:s', time() + $backoffSeconds);

            $upd = $pdo->prepare('UPDATE notification_jobs SET status = \'pending\', attempts = :att, available_at = :avail, last_error = :err WHERE job_id = :id');
            $upd->execute([
                ':att' => $attempts,
                ':avail' => $nextAvailable,
                ':err' => $errorMessage,
                ':id' => $jobId,
            ]);
        }
    }

    /**
     * Process a single queued notification job.
     *
     * @param (callable(string, string, string): bool)|null $mailer
     */
    public function processNextJob(?callable $mailer = null): ?bool
    {
        $job = $this->fetchNextPendingJob();
        if ($job === null) {
            return null;
        }

        $jobId = (int) $job['job_id'];
        $recipient = (string) $job['recipient'];
        $subject = (string) $job['subject'];
        $rawPayload = (string) $job['payload'];
        $payload = json_decode($rawPayload, true) ?: [];
        $body = (string) ($payload['body'] ?? '');

        try {
            if ($mailer !== null) {
                $success = (bool) $mailer($recipient, $subject, $body);
            } else {
                $success = $this->sendMailViaPhpMailer($recipient, $subject, $body);
            }

            if ($success) {
                $this->markJobSent($jobId);
                return true;
            }

            $this->markJobFailed($jobId, 'Mailer returned false without throwing');
            return false;
        } catch (Throwable $e) {
            $this->markJobFailed($jobId, $e->getMessage());
            return false;
        }
    }

    /**
     * Process pending jobs in the queue.
     *
     * @param (callable(string, string, string): bool)|null $mailer
     */
    public function work(bool $once = false, int $limit = 50, ?callable $mailer = null): int
    {
        $processed = 0;
        while ($processed < $limit) {
            $result = $this->processNextJob($mailer);
            if ($result === null) {
                break;
            }
            $processed++;
            if ($once) {
                break;
            }
        }
        return $processed;
    }

    private function sendMailViaPhpMailer(string $recipient, string $subject, string $bodyHtml): bool
    {
        // If running in development without mail server, log and return true
        $appEnv = function_exists('config_env') ? (string) config_env('APP_ENV', 'production') : 'production';
        $mailHost = function_exists('config_env') ? (string) config_env('MAIL_HOST', '') : '';

        if ($mailHost === '' || $appEnv === 'testing') {
            error_log(sprintf('[QueueService Mailer] Simulated send to %s | Subject: %s', $recipient, $subject));
            return true;
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $mailHost;
        $mail->Port = (int) config_env('MAIL_PORT', 587);
        $mail->SMTPAuth = filter_var(config_env('MAIL_AUTH', false), FILTER_VALIDATE_BOOL);
        $mail->Username = (string) config_env('MAIL_USERNAME', '');
        $mail->Password = (string) config_env('MAIL_PASSWORD', '');
        $mail->setFrom((string) config_env('MAIL_FROM_ADDRESS', 'noreply@labtrack.local'), (string) config_env('MAIL_FROM_NAME', 'LabTrack'));
        $mail->addAddress($recipient);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $bodyHtml;

        return $mail->send();
    }
}
