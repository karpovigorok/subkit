<?php

namespace SubKit\Filament\Resources;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use SubKit\Filament\Resources\TopUpResource\Pages;
use SubKit\Filament\Resources\TopUpResource\RelationManagers\ProviderPricesRelationManager;
use SubKit\Models\TopUp;

/**
 * Admin for one-off Top-up prices (ADR-003). SubKit only launches the checkout;
 * what a purchase grants is the host application's concern.
 */
class TopUpResource extends Resource
{
    protected static ?string $model = TopUp::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Top-ups';

    protected static string|\UnitEnum|null $navigationGroup = 'Subscriptions';

    protected static ?int $navigationSort = 3;

    // -------------------------------------------------------------------------
    // Form
    // -------------------------------------------------------------------------

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')
                ->label('Name')
                ->required()
                ->maxLength(255),

            TextInput::make('code')
                ->label('Code')
                ->required()
                ->maxLength(100)
                ->alphaDash()
                ->unique(ignoreRecord: true)
                // The code is sent to your webhook listener as `topup_code` metadata, so it must stay stable.
                ->disabledOn('edit')
                ->helperText('Stable identifier used in the snippets, the checkout form and `topup_code` metadata. Cannot be changed after creation.'),

            Textarea::make('description')
                ->label('Description')
                ->rows(2)
                ->maxLength(500)
                ->columnSpanFull(),

            TextInput::make('amount')
                ->label('Display price')
                ->numeric()
                ->minValue(0)
                ->prefix(config('subkit.currency.symbol', '$'))
                ->placeholder('99.00')
                ->helperText('Shown in the top-up snippets. The provider price stays authoritative for the actual charge, so keep them in sync.')
                ->formatStateUsing(fn (?int $state): ?string => $state !== null ? number_format($state / 100, 2, '.', '') : null)
                ->dehydrateStateUsing(fn (?string $state): ?int => filled($state) ? (int) round((float) $state * 100) : null),

            TextInput::make('currency')
                ->label('Currency')
                ->maxLength(3)
                ->minLength(3)
                ->alpha()
                ->placeholder(config('subkit.currency.code', 'USD'))
                ->helperText('ISO code. Leave empty to use the configured default.')
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? strtoupper($state) : null),

            Toggle::make('is_active')
                ->label('Active')
                ->default(true)
                ->helperText('Inactive top-ups cannot be purchased.'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public static function providerOptions(): array
    {
        $names = array_keys(config('subkit.providers', []));

        return array_combine($names, array_map('ucfirst', $names));
    }

    // -------------------------------------------------------------------------
    // Table
    // -------------------------------------------------------------------------

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),

                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('formatted_price')
                    ->label('Price')
                    ->placeholder('—'),

                TextColumn::make('providerPrices.provider')
                    ->label('Providers')
                    ->badge()
                    ->color('gray'),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
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
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTopUps::route('/'),
            'create' => Pages\CreateTopUp::route('/create'),
            'edit' => Pages\EditTopUp::route('/{record}/edit'),
        ];
    }
}
