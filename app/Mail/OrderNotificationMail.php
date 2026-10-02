<?php

namespace App\Mail;

use App\Notifications\Channels\NotificationPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Order confirmation / new-order email rendered from a channel payload.
 *
 * The body is built from the payload's pre-formatted data (no Blade view),
 * so the mail channel owns its formatting exactly like the database channel
 * owns its row shape. The payload's orders list is shared by both audiences:
 * the customer version holds every split order, the vendor version holds
 * the single order that vendor must fulfil.
 */
class OrderNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly NotificationPayload $payload) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->payload->subject);
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->renderHtml(), textString: $this->renderText());
    }

    private function renderText(): string
    {
        $lines = [$this->payload->headline, ''];

        /** @var array<string, mixed> $order */
        foreach ((array) ($this->payload->data['orders'] ?? []) as $order) {
            $lines[] = sprintf(
                'Order #%s — %s — total %s',
                (string) ($order['order_id'] ?? $order['id'] ?? '?'),
                (string) ($order['status'] ?? 'pending'),
                (string) ($order['total_price'] ?? '0.00'),
            );
        }

        $payment = $this->payload->data['payment'] ?? null;

        if (is_array($payment)) {
            $lines[] = '';

            if (($payment['successful'] ?? true) === true) {
                $lines[] = 'Payment succeeded.';
            } else {
                $first = is_array($payment['results'] ?? null) ? ($payment['results'][0]['message'] ?? null) : null;
                $lines[] = 'Payment needs attention: '.(is_string($first) && $first !== '' ? $first : 'a payment attempt was declined.');
            }
        }

        return implode("\n", $lines);
    }

    private function renderHtml(): string
    {
        return '<div>'.nl2br(e($this->renderText())).'</div>';
    }
}
