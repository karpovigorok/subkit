<?php

namespace SubKit\Filament\Resources\PlanResource\RelationManagers;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Actions\DetachAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FeaturesRelationManager extends RelationManager
{
    protected static string $relationship = 'features';

    protected static ?string $title = 'Features';

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('value')
                ->label('Value')
                ->nullable()
                ->placeholder('Unlimited / 5 GB / 10 users')
                ->helperText('Optional per-plan value shown alongside the feature name.'),

            Toggle::make('is_highlighted')
                ->label('Highlighted')
                ->default(false)
                ->helperText('Emphasise this feature in the pricing table.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Feature')
                    ->searchable(),

                TextColumn::make('value')
                    ->label('Value')
                    ->placeholder('—'),

                IconColumn::make('is_highlighted')
                    ->label('Highlighted')
                    ->boolean(),

                TextColumn::make('sort_order')
                    ->label('Order'),
            ])
            ->reorderable('sort_order')
            ->headerActions([
                AttachAction::make()
                    ->label('Add feature')
                    ->preloadRecordSelect()
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect()
                            ->label('Feature')
                            ->placeholder('Select a feature from the global list'),

                        TextInput::make('value')
                            ->label('Value')
                            ->nullable()
                            ->placeholder('Unlimited / 5 GB / 10 users'),

                        Toggle::make('is_highlighted')
                            ->label('Highlighted')
                            ->default(false),
                    ]),
            ])
            ->actions([
                EditAction::make(),
                DetachAction::make()->label('Remove'),
            ])
            ->paginated(false);
    }
}
