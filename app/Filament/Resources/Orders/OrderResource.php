<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Shipment;
use App\Models\User;
use App\ValueObjects\Money;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Comenzile sunt doar de citit în admin: orice schimbare trece prin acțiunile
 * de domeniu (tranziție de status, expediere, livrare, rambursare), apelate
 * din pagina de detaliu.
 */
class OrderResource extends Resource
{
    public const PAYMENT_STATUS_LABELS = [
        'pending' => 'În așteptare',
        'paid' => 'Încasată',
        'failed' => 'Eșuată',
        'refunded' => 'Rambursată',
        'partially_refunded' => 'Parțial rambursată',
    ];

    public const REFUND_STATUS_LABELS = [
        'pending' => 'În curs',
        'succeeded' => 'Reușită',
        'failed' => 'Eșuată',
    ];

    public const METHOD_LABELS = ['cod' => 'Ramburs', 'card' => 'Card', 'bank' => 'Transfer bancar'];

    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static string|UnitEnum|null $navigationGroup = 'Vânzări';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'comandă';

    protected static ?string $pluralModelLabel = 'comenzi';

    protected static ?string $recordTitleAttribute = 'number';

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Acceptă Money (cast-ul real) sau bani întregi; pentru ultimii se dă moneda.
     */
    public static function money(Money|int $value, string $currency = 'RON'): string
    {
        $minor = $value instanceof Money ? $value->amount() : $value;
        $code = $value instanceof Money ? $value->currency() : $currency;

        return number_format($minor / 100, 2, ',', '.').' '.$code;
    }

    /** Eticheta unui status, indiferent dacă vine ca enum sau ca string din baza de date. */
    public static function statusLabel(mixed $status): string
    {
        if ($status instanceof OrderStatus) {
            return $status->label();
        }

        return is_string($status) ? (OrderStatus::tryFrom($status)?->label() ?? $status) : '—';
    }

    public static function statusColor(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::PENDING => 'gray',
            OrderStatus::AWAITING_PAYMENT, OrderStatus::RETURNED => 'warning',
            OrderStatus::PAID, OrderStatus::PROCESSING => 'info',
            OrderStatus::SHIPPED => 'primary',
            OrderStatus::DELIVERED, OrderStatus::COMPLETED => 'success',
            OrderStatus::CANCELLED => 'danger',
            OrderStatus::REFUNDED => 'gray',
        };
    }

    public static function paymentColor(PaymentStatus $status): string
    {
        return match ($status) {
            PaymentStatus::PAID => 'success',
            PaymentStatus::FAILED => 'danger',
            PaymentStatus::PENDING => 'gray',
            PaymentStatus::REFUNDED, PaymentStatus::PARTIALLY_REFUNDED => 'warning',
        };
    }

    public static function methodLabel(?string $method): string
    {
        return $method === null ? '—' : (self::METHOD_LABELS[$method] ?? $method);
    }

    /**
     * Adresa pe rânduri, în ordinea în care o scrie un curier: nume, firmă,
     * adresă, cod poștal + localitate, județ, țară, telefon.
     *
     * @param  array<string, mixed>|null  $address
     * @return list<string>
     */
    public static function addressLines(?array $address): array
    {
        $a = $address ?? [];
        $get = fn (string $key): string => is_scalar($a[$key] ?? null) ? trim((string) $a[$key]) : '';

        $country = strtoupper($get('country'));
        $locality = trim($get('postal_code').' '.$get('city'));
        $county = $get('county');

        $lines = [
            trim($get('first_name').' '.$get('last_name')),
            $get('company_name'),
            $get('cui') !== '' ? 'CUI: '.$get('cui') : '',
            $get('reg_com') !== '' ? 'Reg. Com.: '.$get('reg_com') : '',
            $get('address_line1'),
            $get('address_line2'),
            $county !== '' && $locality !== '' ? $locality.', jud. '.$county : ($locality !== '' ? $locality : $county),
            $country === 'RO' ? 'România' : $country,
            $get('phone') !== '' ? 'Tel: '.$get('phone') : '',
        ];

        return array_values(array_filter($lines, fn (string $line): bool => $line !== ''));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label('Număr')->searchable()->sortable()->copyable(),
                TextColumn::make('placed_at')->label('Plasată')->dateTime('d.m.Y H:i')->sortable()->placeholder('—'),
                TextColumn::make('email')->label('Client')->searchable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->state(fn (Order $record): string => $record->status->label())
                    ->color(fn (Order $record): string => self::statusColor($record->status)),
                TextColumn::make('payment_status')->label('Plată')->badge()
                    ->state(fn (Order $record): string => self::PAYMENT_STATUS_LABELS[$record->payment_status->value] ?? $record->payment_status->value)
                    ->color(fn (Order $record): string => self::paymentColor($record->payment_status)),
                TextColumn::make('payment_method')->label('Metodă')
                    ->state(fn (Order $record): string => self::methodLabel($record->payment_method))
                    ->toggleable(),
                TextColumn::make('grand_total')->label('Total')
                    ->state(fn (Order $record): string => self::money($record->grand_total))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('grand_total', $direction)),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->multiple()
                    ->options(collect(OrderStatus::cases())->mapWithKeys(fn (OrderStatus $s) => [$s->value => $s->label()])->all()),
                SelectFilter::make('payment_status')->label('Plată')->multiple()
                    ->options(self::PAYMENT_STATUS_LABELS),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Comandă')->schema([
                TextEntry::make('number')->label('Număr')->copyable(),
                TextEntry::make('placed_at')->label('Plasată')->dateTime('d.m.Y H:i')->placeholder('—'),
                TextEntry::make('status')->label('Status')->badge()
                    ->state(fn (Order $record): string => $record->status->label())
                    ->color(fn (Order $record): string => self::statusColor($record->status)),
                TextEntry::make('payment_status')->label('Plată')->badge()
                    ->state(fn (Order $record): string => self::PAYMENT_STATUS_LABELS[$record->payment_status->value] ?? $record->payment_status->value)
                    ->color(fn (Order $record): string => self::paymentColor($record->payment_status)),
                TextEntry::make('fulfillment_status')->label('Pregătire')
                    ->state(fn (Order $record): string => $record->fulfillment_status->label()),
                TextEntry::make('payment_method')->label('Metodă de plată')
                    ->state(fn (Order $record): string => self::methodLabel($record->payment_method)),
                TextEntry::make('email')->label('Email')->copyable(),
                TextEntry::make('customer')->label('Cont client')
                    ->state(fn (Order $record): string => $record->user_id === null
                        ? 'Oaspete (fără cont)'
                        : (string) (User::query()->whereKey($record->user_id)->value('name') ?? '—')),
                TextEntry::make('coupon_code')->label('Cupon')->placeholder('—'),
                TextEntry::make('customer_note')->label('Notă client')->placeholder('—')->columnSpanFull(),
            ])->columns(3),

            Section::make('Totaluri')->schema([
                TextEntry::make('subtotal')->label('Subtotal')->state(fn (Order $record): string => self::money($record->subtotal)),
                TextEntry::make('discount_total')->label('Reducere')->state(fn (Order $record): string => self::money($record->discount_total)),
                TextEntry::make('shipping_total')->label('Transport')->state(fn (Order $record): string => self::money($record->shipping_total)),
                TextEntry::make('tax_total')->label('TVA inclus')->state(fn (Order $record): string => self::money($record->tax_total)),
                TextEntry::make('grand_total')->label('Total')->weight('bold')->state(fn (Order $record): string => self::money($record->grand_total)),
            ])->columns(5),

            Section::make('Articole')->schema([
                RepeatableEntry::make('line_items')->hiddenLabel()
                    ->state(fn (Order $record): array => OrderItem::query()->where('order_id', $record->id)->orderBy('id')->get()->map(fn (OrderItem $item): array => [
                        'name' => $item->name,
                        'sku' => $item->sku,
                        'quantity' => $item->quantity,
                        'unit_price' => self::money($item->unit_price, $record->currency),
                        'line_total' => self::money($item->line_total, $record->currency),
                    ])->all())
                    ->schema([
                        TextEntry::make('name')->label('Produs'),
                        TextEntry::make('sku')->label('SKU'),
                        TextEntry::make('quantity')->label('Cant.'),
                        TextEntry::make('unit_price')->label('Preț unitar'),
                        TextEntry::make('line_total')->label('Total linie'),
                    ])->columns(5),
            ]),

            Section::make('Adrese')->schema([
                TextEntry::make('billing')->label('Facturare')->listWithLineBreaks()
                    ->state(fn (Order $record): array => self::addressLines($record->billing_address)),
                TextEntry::make('shipping')->label('Livrare')->listWithLineBreaks()
                    ->state(fn (Order $record): array => self::addressLines($record->shipping_address)),
            ])->columns(2),

            Section::make('Plăți și rambursări')->schema([
                RepeatableEntry::make('payment_rows')->label('Plăți')
                    ->state(fn (Order $record): array => Payment::query()->where('order_id', $record->id)->orderBy('id')->get()
                        ->map(fn (Payment $p): array => [
                            'ref' => '#'.$p->id.' · '.$p->provider.($p->method ? ' / '.$p->method : ''),
                            'amount' => self::money($p->amount),
                            'status' => self::PAYMENT_STATUS_LABELS[$p->status->value] ?? $p->status->value,
                            'paid_at' => $p->paid_at?->format('d.m.Y H:i') ?? '—',
                        ])->all())
                    ->schema([
                        TextEntry::make('ref')->label('Plată'),
                        TextEntry::make('amount')->label('Sumă'),
                        TextEntry::make('status')->label('Status'),
                        TextEntry::make('paid_at')->label('Încasată la'),
                    ])->columns(4),
                RepeatableEntry::make('refund_rows')->label('Rambursări')
                    ->state(fn (Order $record): array => Refund::query()->where('order_id', $record->id)->orderBy('id')->get()
                        ->map(fn (Refund $r): array => [
                            'ref' => '#'.$r->id.' (plata #'.$r->payment_id.')',
                            'amount' => self::money($r->amount),
                            'status' => self::REFUND_STATUS_LABELS[$r->status->value] ?? $r->status->value,
                            'detail' => $r->status === RefundStatus::FAILED ? ($r->failure_reason ?? '—') : ($r->reason ?? '—'),
                            'processed_at' => $r->processed_at?->format('d.m.Y H:i') ?? '—',
                        ])->all())
                    ->visible(fn (Order $record): bool => Refund::query()->where('order_id', $record->id)->exists())
                    ->schema([
                        TextEntry::make('ref')->label('Rambursare'),
                        TextEntry::make('amount')->label('Sumă'),
                        TextEntry::make('status')->label('Status'),
                        TextEntry::make('detail')->label('Motiv / eroare'),
                        TextEntry::make('processed_at')->label('Procesată la'),
                    ])->columns(5),
            ]),

            Section::make('Expediere')->schema([
                TextEntry::make('carrier')->label('Curier')
                    ->state(fn (Order $record): string => self::shipmentOf($record)->carrier ?? '—'),
                TextEntry::make('tracking')->label('AWB')
                    ->state(fn (Order $record): string => self::shipmentOf($record)->tracking_number ?? '—'),
                TextEntry::make('tracking_url')->label('Link tracking')
                    ->state(fn (Order $record): string => self::shipmentOf($record)->tracking_url ?? '—')
                    ->url(fn (Order $record): ?string => self::shipmentOf($record)?->tracking_url, shouldOpenInNewTab: true),
                TextEntry::make('shipment_status')->label('Status expediere')
                    ->state(fn (Order $record): string => self::shipmentOf($record)?->status->label() ?? '—'),
                TextEntry::make('shipped_at')->label('Expediată la')
                    ->state(fn (Order $record): string => self::shipmentOf($record)?->shipped_at?->format('d.m.Y H:i') ?? '—'),
                TextEntry::make('delivered_at')->label('Livrată la')
                    ->state(fn (Order $record): string => self::shipmentOf($record)?->delivered_at?->format('d.m.Y H:i') ?? '—'),
            ])->columns(3)
                ->visible(fn (Order $record): bool => self::shipmentOf($record) !== null),

            Section::make('Istoric')->schema([
                RepeatableEntry::make('history_rows')->hiddenLabel()
                    ->state(fn (Order $record): array => OrderStatusHistory::query()
                        ->where('order_id', $record->id)->with('changedBy')->orderBy('created_at')->orderBy('id')->get()
                        ->map(fn (OrderStatusHistory $h): array => [
                            'at' => $h->created_at?->format('d.m.Y H:i') ?? '—',
                            'change' => ($h->from_status === null ? '—' : self::statusLabel($h->from_status)).' → '.self::statusLabel($h->to_status),
                            'by' => (string) (data_get($h->changedBy, 'name') ?? 'Sistem'),
                            'comment' => $h->comment ?? '—',
                        ])->all())
                    ->schema([
                        TextEntry::make('at')->label('Când'),
                        TextEntry::make('change')->label('Schimbare'),
                        TextEntry::make('by')->label('De către'),
                        TextEntry::make('comment')->label('Comentariu'),
                    ])->columns(4),
            ])->collapsible(),
        ]);
    }

    public static function shipmentOf(Order $order): ?Shipment
    {
        return Shipment::query()->where('order_id', $order->id)->first();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
