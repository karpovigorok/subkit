<?php

namespace SubKit\Filament\Resources\PlanResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use SubKit\Models\PlanAssignment;

class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    protected static ?string $title = 'Assignments';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Hidden::make('assignable_type')
                ->default(fn (): string => config('subkit.billable_model', '')),

            Select::make('assignable_id')
                ->label('Subscriber')
                ->required()
                ->searchable()
                ->getSearchResultsUsing(function (string $search): array {
                    $model = config('subkit.billable_model');
                    if (! $model) {
                        return [];
                    }
                    $col = config('subkit.billable_search_column', 'email');

                    return $model::where($col, 'like', "%{$search}%")
                        ->limit(50)
                        ->pluck($col, 'id')
                        ->toArray();
                })
                ->getOptionLabelsUsing(function (array $values): array {
                    $model = config('subkit.billable_model');
                    if (! $model) {
                        return [];
                    }
                    $col = config('subkit.billable_search_column', 'email');

                    return $model::whereIn('id', $values)
                        ->pluck($col, 'id')
                        ->toArray();
                })
                ->helperText('Search by email. Configure the model in config/subkit.php under billable_model.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('assignable_id')
            ->columns([
                TextColumn::make('subscriber_label')
                    ->label('Subscriber')
                    ->getStateUsing(function (PlanAssignment $record): string {
                        $col = config('subkit.billable_search_column', 'email');

                        return $record->assignable?->{$col} ?? "ID {$record->assignable_id}";
                    }),

                TextColumn::make('created_at')
                    ->label('Assigned')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()->label('Assign subscriber'),
            ])
            ->recordActions([
                DeleteAction::make()->label('Remove'),
            ])
            ->paginated(false);
    }
}
