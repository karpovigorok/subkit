<?php

namespace SubKit\Filament\Resources;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use SubKit\Filament\Resources\PlanSetResource\Pages;
use SubKit\Filament\Resources\PlanSetResource\RelationManagers\PlanSetItemsRelationManager;
use SubKit\Models\PlanSet;
use SubKit\View\Components\BaseSubscriptionComponent;

class PlanSetResource extends Resource
{
    protected static ?string $model = PlanSet::class;

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationLabel = 'Plan Sets';

    protected static ?string $navigationGroup = 'Subscriptions';

    protected static ?int $navigationSort = 1;

    // -------------------------------------------------------------------------
    // Form
    // -------------------------------------------------------------------------

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Plan Set')
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
                        ->helperText('Auto-generated from name. Use in Blade: <x-subkit::pricing-table set="..." />'),

                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),

                    Select::make('theme')
                        ->label('UI Theme')
                        ->options(fn () => BaseSubscriptionComponent::availableThemes())
                        ->default('default')
                        ->helperText('Visual theme for the pricing table. New themes appear automatically when added to the themes directory.'),
                ]),

            Section::make('Description')
                ->schema([
                    Textarea::make('description')
                        ->label('Description / subtitle')
                        ->rows(2)
                        ->maxLength(500)
                        ->helperText('Optional subtitle shown above the pricing table.'),
                ]),

            Section::make('Button Overrides')
                ->description('Override the default button labels for this plan set. Leave blank to use the default translations.')
                ->columns(3)
                ->schema([
                    TextInput::make('subscribe_label')
                        ->label('Subscribe button')
                        ->maxLength(100)
                        ->placeholder(__('subkit::messages.buttons.get_started'))
                        ->helperText('Shown on paid plan cards for authenticated users.'),

                    TextInput::make('free_label')
                        ->label('Free plan button')
                        ->maxLength(100)
                        ->placeholder(__('subkit::messages.buttons.get_started_free'))
                        ->helperText('Shown on $0 plan cards for authenticated users.'),

                    TextInput::make('guest_label')
                        ->label('Guest button')
                        ->maxLength(100)
                        ->placeholder(__('subkit::messages.buttons.create_account_to_subscribe'))
                        ->helperText('Shown to unauthenticated visitors.'),
                ]),

            Section::make('URLs')
                ->description('All fields accept a route name, a relative path, or a full URL.')
                ->columns(2)
                ->schema([
                    TextInput::make('success_url')
                        ->label('Success URL')
                        ->maxLength(500)
                        ->placeholder("'dashboard', '/thanks?utm=fb', or 'https://…'")
                        ->helperText('Redirect here after a successful checkout.'),

                    TextInput::make('cancel_url')
                        ->label('Cancel URL')
                        ->maxLength(500)
                        ->placeholder("'pricing', '/pricing', or 'https://…'")
                        ->helperText('Redirect here when the user cancels checkout.'),

                    TextInput::make('free_url')
                        ->label('Free Plan URL')
                        ->maxLength(500)
                        ->placeholder("'register', '/signup', or 'https://…'")
                        ->helperText('CTA destination for $0 plans (authenticated users).'),

                    TextInput::make('guest_url')
                        ->label('Guest URL')
                        ->maxLength(500)
                        ->placeholder("'login', '/register', or 'https://…'")
                        ->helperText('Where unauthenticated visitors are sent. Defaults to /register.'),
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
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),

                TextColumn::make('code')
                    ->label('Code')
                    ->copyable()
                    ->fontFamily('mono')
                    ->searchable(),

                TextColumn::make('items_count')
                    ->label('Plans')
                    ->counts('items')
                    ->badge()
                    ->color('success'),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (PlanSet $record): string => route('subkit.plan-set.preview', $record->code))
                    ->openUrlInNewTab(),
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
            PlanSetItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlanSets::route('/'),
            'create' => Pages\CreatePlanSet::route('/create'),
            'edit' => Pages\EditPlanSet::route('/{record}/edit'),
        ];
    }
}
