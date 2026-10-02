<?php

namespace Tests\Unit;

use App\Exceptions\UnsupportedNotificationChannelException;
use App\Notifications\Channels\ChannelResult;
use App\Notifications\Channels\DatabaseNotificationChannel;
use App\Notifications\Channels\MailNotificationChannel;
use App\Notifications\Channels\NotificationChannelFactory;
use App\Notifications\Channels\NotificationChannelInterface;
use App\Notifications\Channels\NotificationPayload;
use stdClass;
use Tests\TestCase;

class NotificationChannelFactoryTest extends TestCase
{
    public function test_it_resolves_every_configured_channel_to_its_own_implementation(): void
    {
        $factory = app(NotificationChannelFactory::class);

        $this->assertInstanceOf(DatabaseNotificationChannel::class, $factory->make('database'));
        $this->assertInstanceOf(MailNotificationChannel::class, $factory->make('mail'));

        $this->assertSame('database', $factory->make('database')->channel());
        $this->assertSame('mail', $factory->make('mail')->channel());
    }

    public function test_the_supported_channels_are_exactly_the_configured_keys(): void
    {
        $this->assertSame(
            array_keys((array) config('marketplace.notifications.channels')),
            app(NotificationChannelFactory::class)->supported(),
        );
    }

    public function test_channel_names_are_normalised_before_the_lookup(): void
    {
        $this->assertInstanceOf(
            MailNotificationChannel::class,
            app(NotificationChannelFactory::class)->make('  MAIL  '),
        );
    }

    public function test_an_unknown_channel_is_rejected_and_names_the_supported_ones(): void
    {
        try {
            app(NotificationChannelFactory::class)->make('smoke-signal');

            $this->fail('An unregistered channel must be rejected.');
        } catch (UnsupportedNotificationChannelException $exception) {
            $this->assertSame('smoke-signal', $exception->channel);
            $this->assertSame(['database', 'mail'], $exception->supported);
            $this->assertStringContainsString('not supported', $exception->getMessage());
            $this->assertStringContainsString('database, mail', $exception->getMessage());
        }
    }

    public function test_a_misconfigured_channel_class_is_rejected(): void
    {
        config()->set('marketplace.notifications.channels.database', stdClass::class);

        try {
            app(NotificationChannelFactory::class)->make('database');

            $this->fail('A class that is not a channel must be rejected.');
        } catch (UnsupportedNotificationChannelException $exception) {
            $this->assertSame('database', $exception->channel);
            $this->assertStringContainsString('is misconfigured', $exception->getMessage());
            $this->assertStringContainsString(stdClass::class, $exception->getMessage());
        }
    }

    public function test_a_new_channel_is_registered_through_config_alone(): void
    {
        config()->set('marketplace.notifications.channels.pigeon', ChannelFakeStrategy::class);

        $factory = app(NotificationChannelFactory::class);

        $this->assertInstanceOf(ChannelFakeStrategy::class, $factory->make('pigeon'));
        $this->assertContains('pigeon', $factory->supported());
    }

    public function test_channel_results_report_instead_of_throwing(): void
    {
        $delivered = ChannelResult::delivered('database', 'row-1', 'stored');

        $this->assertTrue($delivered->delivered);
        $this->assertSame(
            ['delivered' => true, 'channel' => 'database', 'reference' => 'row-1', 'message' => 'stored'],
            $delivered->toArray(),
        );

        $failed = ChannelResult::failed('mail', 'transport down');

        $this->assertFalse($failed->delivered);
        $this->assertNull($failed->reference);
        $this->assertSame('transport down', $failed->message);
    }

    public function test_payloads_carry_audience_content_for_the_channels(): void
    {
        $payload = NotificationPayload::make('customer_confirmation', 'Subject', 'Headline', ['a' => 1]);

        $this->assertSame('customer_confirmation', $payload->kind);
        $this->assertSame(
            ['kind' => 'customer_confirmation', 'subject' => 'Subject', 'headline' => 'Headline', 'data' => ['a' => 1]],
            $payload->toArray(),
        );
    }
}

/** Channel used to prove a config-only registration works. */
class ChannelFakeStrategy implements NotificationChannelInterface
{
    public function channel(): string
    {
        return 'pigeon';
    }

    public function send(object $notifiable, NotificationPayload $payload): ChannelResult
    {
        return ChannelResult::delivered('pigeon', null, $payload->subject);
    }
}
