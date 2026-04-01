<?php

namespace SubKit\Filament\Resources;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Laravel\Cashier\Subscription;
use SubKit\Enums\SubscriptionInterval;
use SubKit\Filament\Resources\PlanResource\Pages;
use SubKit\Filament\Resources\PlanResource\RelationManagers\FeaturesRelationManager;
use SubKit\Filament\Resources\PlanResource\RelationManagers\ProviderPricesRelationManager;
use SubKit\Models\Plan;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Plans';

    protected static ?string $navigationGroup = 'Subscriptions';

    protected static ?int $navigationSort = 0;

    // -------------------------------------------------------------------------
    // Form
    // -------------------------------------------------------------------------

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Plan details')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (string $operation, ?string $state, callable $set): void {
                            if ($operation === 'create') {
                                $set('code', Str::slug($state ?? '', '_'));
                            }
                        }),

                    TextInput::make('code')
                        ->label('Code')
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('Auto-generated from name. Used as the plan identifier in code.'),

                    Select::make('interval')
                        ->label('Billing interval')
                        ->options([
                            SubscriptionInterval::Monthly->value => 'Monthly',
                            SubscriptionInterval::Yearly->value => 'Yearly',
                        ])
                        ->required(),

                    TextInput::make('trial_days')
                        ->label('Trial days')
                        ->numeric()
                        ->minValue(0)
                        ->placeholder('0 = no trial'),

                    TextInput::make('price')
                        ->label('Price')
                        ->numeric()
                        ->minValue(0)
                        ->prefix(config('subkit.currency.symbol', '$'))
                        ->placeholder('9.99')
                        ->helperText('Leave empty for free plans.')
                        ->formatStateUsing(fn (?int $state): ?string => $state ? number_format($state / 100, 2) : null)
                        ->dehydrateStateUsing(fn (?string $state): ?int => filled($state) ? (int) round((float) $state * 100) : null),

                    TextInput::make('version')
                        ->label('Version')
                        ->numeric()
                        ->required()
                        ->default(1)
                        ->minValue(1)
                        ->helperText('Increment when plan changes. Never edit an active plan — create a new version instead.'),

                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->helperText('Only active plans appear in the pricing table.'),
                ]),

            Section::make('Description')
                ->schema([
                    Textarea::make('description')
                        ->label('Description')
                        ->rows(3)
                        ->maxLength(1000),
                ]),
        ]);
    }

    // -------------------------------------------------------------------------
    // Table
    // -------------------------------------------------------------------------

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono'),

                TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),

                TextColumn::make('interval')
                    ->label('Interval')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (SubscriptionInterval $state): string => ucfirst($state->value)),

                TextColumn::make('trial_days')
                    ->label('Trial days')
                    ->placeholder('—'),

                TextColumn::make('price')
                    ->label('Price')
                    ->formatStateUsing(fn (?int $state): string => $state
                        ? config('subkit.currency.symbol', '$').number_format($state / 100, 2)
                        : 'Free'
                    ),

                TextColumn::make('version')
                    ->label('Version')
                    ->badge()
                    ->color('gray'),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                //                TextColumn::make('providerPrices_count')
                //                    ->label('Providers')
                //                    ->counts('providerPrices')
                //                    ->badge()
                //                    ->color('success'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (Plan $record): bool => Subscription::whereIn(
                        'stripe_price',
                        $record->providerPrices()->pluck('provider_price_id')
                    )->doesntExist()),
            ])
            ->defaultSort('id', 'asc');
    }

    // -------------------------------------------------------------------------
    // Relations & pages
    // -------------------------------------------------------------------------

    public static function getRelations(): array
    {
        return [
            ProviderPricesRelationManager::class,
            FeaturesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
