<?php

namespace SubKit\Filament\Resources\TopUpResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use SubKit\Filament\Resources\TopUpResource;

class ProviderPricesRelationManager extends RelationManager
{
    protected static string $relationship = 'providerPrices';

    protected static ?string $title = 'Provider Prices';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('provider')
                ->label('Provider')
                ->required()
                ->options(fn () => TopUpResource::providerOptions())
                ->unique(
                    table: 'subkit_top_up_provider_prices',
                    column: 'provider',
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('top_up_id', $this->getOwnerRecord()->id),
                )
                ->validationMessages(['unique' => 'This provider already has a price assigned to this top-up.'])
                ->helperText('The payment provider that sells this top-up.'),

            TextInput::make('provider_price_id')
                ->label('Provider Price ID')
                ->required()
                ->placeholder('price_xxx')
                ->helperText('The ONE-TIME price/product reference from the provider dashboard (not a recurring price).'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('provider')
                    ->label('Provider')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('provider_price_id')
                    ->label('Provider Price ID')
                    ->copyable()
                    ->fontFamily('mono'),
            ])
            ->headerActions([
                CreateAction::make()->label('Add provider price'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->paginated(false);
    }
}
