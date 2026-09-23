<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Tests\Factory\QueueFake;
use Tests\Factory\CacheFake;
use Tests\Factory\MailFake;

class FakesTest extends TestCase
{
    protected function tearDown(): void
    {
        QueueFake::clear();
        CacheFake::clear();
        MailFake::clear();
    }

    // ── QueueFake ───────────────────────────────────────────────────────

    public function test_queue_fake_pushes_jobs(): void
    {
        QueueFake::push('job1');
        QueueFake::push('job2');
        QueueFake::assertPushed('job1');
        QueueFake::assertPushed('job2');
        QueueFake::clear();
        $this->assertTrue(true);
    }

    public function test_queue_fake_assert_pushed(): void
    {
        QueueFake::push('test_job');
        QueueFake::assertPushed('test_job');
        QueueFake::clear();
        $this->assertTrue(true);
    }

    public function test_queue_fake_assert_nothing_pushed(): void
    {
        QueueFake::assertNothingPushed();
        $this->assertTrue(true);
    }

    public function test_queue_fake_assert_pushed_fails_when_not_pushed(): void
    {
        $this->expectException(\Exception::class);
        QueueFake::assertPushed('missing_job');
    }

    // ── CacheFake ───────────────────────────────────────────────────────

    public function test_cache_fake_put_and_get(): void
    {
        CacheFake::put('key1', 'value1', 60);
        $this->assertSame('value1', CacheFake::get('key1'));
    }

    public function test_cache_fake_has(): void
    {
        CacheFake::put('key1', 'value1');
        $this->assertTrue(CacheFake::has('key1'));
        $this->assertFalse(CacheFake::has('missing'));
    }

    public function test_cache_fake_forget(): void
    {
        CacheFake::put('key1', 'value1');
        CacheFake::forget('key1');
        $this->assertFalse(CacheFake::has('key1'));
    }

    public function test_cache_fake_flush(): void
    {
        CacheFake::put('key1', 'value1');
        CacheFake::flush();
        $this->assertFalse(CacheFake::has('key1'));
    }

    public function test_cache_fake_expired_entry(): void
    {
        CacheFake::put('expiring', 'value', -1); // already expired
        $this->assertNull(CacheFake::get('expiring'));
    }

    public function test_cache_fake_tagged(): void
    {
        $tagged = CacheFake::tag('posts');
        $tagged->put('recent', ['a', 'b'], 60);
        $this->assertSame(['a', 'b'], $tagged->get('recent'));
    }

    // ── MailFake ────────────────────────────────────────────────────────

    public function test_mail_fake_sends(): void
    {
        MailFake::send('welcome_email');
        MailFake::assertSent('welcome_email');
        $this->assertTrue(true);
    }

    public function test_mail_fake_assert_nothing_sent(): void
    {
        MailFake::assertNothingSent();
        $this->assertTrue(true);
    }

    public function test_mail_fake_assert_sent_fails(): void
    {
        $this->expectException(\Exception::class);
        MailFake::assertSent('missing_email');
    }

    public function test_mail_fake_queued(): void
    {
        MailFake::queue('queued_mail');
        MailFake::assertSent('queued_mail');
        $this->assertTrue(true);
    }
}
