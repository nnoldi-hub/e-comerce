<?php

namespace Tests\Feature\Admin;

use App\Notifications\LowStockNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/** Livrarea reală (fără fake pe notificări): clopoțelul din admin și emailul. */
class LowStockNotificationDeliveryTest extends AdminTestCase
{
    private function notification(bool $outOfStock = false): LowStockNotification
    {
        return new LowStockNotification(7, 3, 'Tricou', 'TR-1', $outOfStock ? 0 : 4, 5, $outOfStock);
    }

    public function test_the_database_message_has_the_filament_format_and_is_stored(): void
    {
        Mail::fake();
        $manager = $this->makeStaff('Manager Inventar');

        Notification::send($manager, $this->notification());

        $stored = $manager->notifications()->sole();
        $this->assertSame('Stoc redus: Tricou', data_get($stored->data, 'title'));
        $this->assertStringContainsString('TR-1', (string) data_get($stored->data, 'body'));
    }

    public function test_titles_and_links_differ_for_out_of_stock(): void
    {
        $low = $this->notification();
        $out = $this->notification(true);

        $this->assertSame('Stoc redus: Tricou', $low->title());
        $this->assertSame('Stoc epuizat: Tricou', $out->title());
        $this->assertStringContainsString('nu mai are stoc', $out->body());
        $this->assertStringEndsWith('/admin/products/3/edit', $low->url());
    }

    public function test_the_mail_carries_the_product_link(): void
    {
        $mail = $this->notification()->toMail($this->makeStaff('Manager Inventar'));

        $this->assertSame('Stoc redus: Tricou', $mail->subject);
        $this->assertStringEndsWith('/admin/products/3/edit', $mail->actionUrl);
    }
}
