<?php

namespace SubKit\Filament\Resources\PlanResource\RelationManagers;

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

class LimitsRelationManager extends RelationManager
{
    protected static string $relationship = 'limits';

    protected static ?string $title = 'Limits';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('key')
                ->label('Key')
                ->required()
                ->placeholder('max_locations')
                ->helperText('Snake_case identifier used in code to read this limit.')
                ->unique(
                    table: 'subkit_plan_limits',
                    column: 'key',
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('plan_id', $this->getOwnerRecord()->id),
                ),

            TextInput::make('value')
                ->label('Value')
                ->required()
                ->placeholder('100'),

            Select::make('type')
                ->label('Type')
                ->required()
                ->default('string')
                ->options([
                    'int' => 'Integer',
                    'bool' => 'Boolean',
                    'string' => 'String',
                ])
                ->helperText('Determines how the value is cast when read in code.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('key')
            ->columns([
                TextColumn::make('key')
                    ->label('Key')
                    ->searchable()
                    ->fontFamily('mono'),

                TextColumn::make('value')
                    ->label('Value'),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'int' => 'info',
                        'bool' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                CreateAction::make()->label('Add limit'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->paginated(false);
    }
}
