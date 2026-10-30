<?php

namespace App\Notifications;

use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $variantId,
        public readonly int $productId,
        public readonly string $productName,
        public readonly string $sku,
        public readonly int $stock,
        public readonly int $threshold,
        public readonly bool $outOfStock,
    ) {
        $this->afterCommit();
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120];
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function title(): string
    {
        return $this->outOfStock
            ? "Stoc epuizat: {$this->productName}"
            : "Stoc redus: {$this->productName}";
    }

    public function body(): string
    {
        return $this->outOfStock
            ? "Varianta {$this->sku} nu mai are stoc."
            : "Varianta {$this->sku} are {$this->stock} buc. (prag: {$this->threshold}).";
    }

    public function url(): string
    {
        return url('/admin/products/'.$this->productId.'/edit');
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->line($this->body())
            ->action('Deschide produsul', $this->url());
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $notification = FilamentNotification::make()
            ->title($this->title())
            ->body($this->body())
            ->icon('heroicon-o-exclamation-triangle')
            ->iconColor($this->outOfStock ? 'danger' : 'warning');

        return $notification->getDatabaseMessage();
    }
}
