<?php

namespace SubKit\Filament\Resources;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use SubKit\Filament\Resources\FeatureResource\Pages;
use SubKit\Models\Feature;

class FeatureResource extends Resource
{
    protected static ?string $model = Feature::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationLabel = 'Features';

    protected static string|\UnitEnum|null $navigationGroup = 'Subscriptions';

    protected static ?int $navigationSort = 2;

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

            TextInput::make('icon')
                ->label('Icon')
                ->maxLength(50)
                ->placeholder('heroicon-o-check')
                ->helperText('Icon identifier (e.g. Heroicon name).'),

            TextInput::make('sort_order')
                ->label('Sort order')
                ->numeric()
                ->default(0)
                ->minValue(0),

            Textarea::make('description')
                ->label('Description')
                ->rows(2)
                ->maxLength(500)
                ->columnSpanFull(),
        ]);
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

                TextColumn::make('icon')
                    ->label('Icon')
                    ->placeholder('—'),

                TextColumn::make('description')
                    ->label('Description')
                    ->limit(60)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),

                TextColumn::make('plans_count')
                    ->label('Plans')
                    ->counts('plans')
                    ->badge()
                    ->color('success'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('sort_order', 'asc');
    }

    // -------------------------------------------------------------------------
    // Pages
    // -------------------------------------------------------------------------

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeatures::route('/'),
            'create' => Pages\CreateFeature::route('/create'),
            'edit' => Pages\EditFeature::route('/{record}/edit'),
        ];
    }
}
