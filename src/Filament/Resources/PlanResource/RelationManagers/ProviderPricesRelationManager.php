<?php

namespace SubKit\Filament\Resources\PlanResource\RelationManagers;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class ProviderPricesRelationManager extends RelationManager
{
    protected static string $relationship = 'providerPrices';

    protected static ?string $title = 'Provider Prices';

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('provider')
                ->label('Provider')
                ->required()
                ->options(fn () => array_combine(
                    array_keys(config('subkit.providers', [])),
                    array_map('ucfirst', array_keys(config('subkit.providers', [])))
                ))
                ->unique(
                    table: 'subkit_plan_provider_prices',
                    column: 'provider',
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('plan_id', $this->getOwnerRecord()->id),
                )
                ->validationMessages(['unique' => 'This provider already has a price assigned to this plan.'])
                ->helperText('Select the payment provider.'),

            TextInput::make('provider_price_id')
                ->label('Provider Price ID')
                ->required()
                ->placeholder('price_xxx')
                ->helperText('The price/product ID from the payment provider dashboard.'),
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
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->paginated(false);
    }
}
