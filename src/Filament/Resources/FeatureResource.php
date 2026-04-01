<?php

namespace SubKit\Filament\Resources;

use SubKit\Filament\Resources\FeatureResource\Pages;
use SubKit\Models\Feature;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FeatureResource extends Resource
{
    protected static ?string $model = Feature::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationLabel = 'Features';

    protected static ?string $navigationGroup = 'Subscriptions';

    protected static ?int $navigationSort = 2;

    // -------------------------------------------------------------------------
    // Form
    // -------------------------------------------------------------------------

    public static function form(Form $form): Form
    {
        return $form->schema([
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
            ->actions([
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
            'index'  => Pages\ListFeatures::route('/'),
            'create' => Pages\CreateFeature::route('/create'),
            'edit'   => Pages\EditFeature::route('/{record}/edit'),
        ];
    }
}
