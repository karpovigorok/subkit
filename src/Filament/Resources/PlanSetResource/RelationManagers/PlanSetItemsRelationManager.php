<?php

namespace SubKit\Filament\Resources\PlanSetResource\RelationManagers;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use SubKit\Models\Plan;
use SubKit\Models\PlanSetItem;

class PlanSetItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Plans in this set';

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('plan_id')
                ->label('Plan')
                ->options(function (): array {
                    $existing = $this->getOwnerRecord()
                        ->items()
                        ->pluck('plan_id')
                        ->all();

                    return Plan::where('is_active', true)
                        ->whereNotIn('id', $existing)
                        ->whereHas('providerPrices')
                        ->get()
                        ->mapWithKeys(fn (Plan $p) => [
                            $p->id => "{$p->name} ({$p->code})",
                        ])
                        ->all();
                })
                ->required()
                ->searchable()
                ->hiddenOn('edit'),

            Placeholder::make('plan_name')
                ->label('Plan')
                ->content(fn (PlanSetItem $record): string => "{$record->plan->name} ({$record->plan->code})")
                ->visibleOn('edit'),

            Toggle::make('is_highlighted')
                ->label('Highlighted ("Recommended" badge)')
                ->default(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->width('40px'),

                TextColumn::make('plan.name')
                    ->label('Plan')
                    ->description(fn (PlanSetItem $record): ?string => $record->plan->is_active ? null : '⚠ Inactive — hidden from pricing table'),

                TextColumn::make('plan.code')
                    ->label('Code')
                    ->fontFamily('mono')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('plan.interval')
                    ->label('Interval')
                    ->formatStateUsing(fn ($state) => ucfirst($state->value)),

                TextColumn::make('plan.formatted_price')
                    ->label('Price'),

                IconColumn::make('is_highlighted')
                    ->label('Highlighted')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->label('Add plan'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()->label('Remove'),
            ])
            ->paginated(false);
    }
}
