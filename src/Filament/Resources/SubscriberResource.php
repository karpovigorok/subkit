<?php

namespace SubKit\Filament\Resources;

use SubKit\Filament\Resources\SubscriberResource\Pages;
use SubKit\Models\PlanProviderPrice;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Laravel\Cashier\Subscription as CashierSubscription;

class SubscriberResource extends Resource
{
    protected static ?string $model = CashierSubscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Subscribers';

    protected static ?string $navigationGroup = 'Subscriptions';

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'subscribers';

    // -------------------------------------------------------------------------
    // Table
    // -------------------------------------------------------------------------

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('User')
                    ->description(fn (CashierSubscription $record): string => $record->user?->email ?? '—')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('stripe_price')
                    ->label('Plan')
                    ->formatStateUsing(function (string $state): string {
                        return PlanProviderPrice::where('provider_price_id', $state)
                            ->first()?->plan?->name ?? $state;
                    })
                    ->placeholder('—'),

                TextColumn::make('stripe_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state)))
                    ->color(fn (string $state): string => match ($state) {
                        'active'             => 'success',
                        'trialing'           => 'info',
                        'past_due', 'unpaid' => 'warning',
                        'paused'             => 'warning',
                        'canceled'           => 'danger',
                        default              => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('ends_at')
                    ->label('Ends / Cancels')
                    ->description(fn (CashierSubscription $record): string => $record->onGracePeriod() ? 'Grace period' : '')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Subscribed')
                    ->date('M j, Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('stripe_status')
                    ->label('Status')
                    ->options([
                        'active'    => 'Active',
                        'trialing'  => 'Trialing',
                        'past_due'  => 'Past Due',
                        'paused'    => 'Paused',
                        'canceled'  => 'Canceled',
                        'unpaid'    => 'Unpaid',
                    ]),
            ])
            ->actions([
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('The subscription will be canceled. By default, access continues until the end of the current billing period.')
                    ->form([
                        Toggle::make('immediately')
                            ->label('Cancel immediately (cut access now, skip billing period end)')
                            ->default(false),
                    ])
                    ->visible(fn (CashierSubscription $record): bool =>
                        in_array($record->stripe_status, ['active', 'trialing', 'past_due'], strict: true)
                        && ! $record->onGracePeriod()
                    )
                    ->action(function (CashierSubscription $record, array $data): void {
                        $data['immediately'] ? $record->cancelNow() : $record->cancel();

                        Notification::make()
                            ->title($data['immediately'] ? 'Subscription canceled immediately.' : 'Subscription will cancel at period end.')
                            ->success()
                            ->send();
                    }),

                Action::make('resume')
                    ->label('Resume')
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (CashierSubscription $record): bool => $record->onGracePeriod())
                    ->action(function (CashierSubscription $record): void {
                        $record->resume();

                        Notification::make()
                            ->title('Subscription resumed.')
                            ->success()
                            ->send();
                    }),

                Action::make('stripe')
                    ->label('Open in Stripe')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (CashierSubscription $record): string =>
                        'https://dashboard.stripe.com/customers/' . ($record->user?->stripe_id ?? '')
                    )
                    ->openUrlInNewTab()
                    ->visible(fn (CashierSubscription $record): bool =>
                        $record->user?->stripe_id !== null
                    ),
            ])
            ->defaultSort('created_at', 'desc');
    }

    // -------------------------------------------------------------------------
    // Pages
    // -------------------------------------------------------------------------

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscribers::route('/'),
        ];
    }
}
