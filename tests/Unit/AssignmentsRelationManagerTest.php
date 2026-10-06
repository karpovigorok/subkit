<?php

use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use SubKit\Filament\Resources\PlanResource\RelationManagers\AssignmentsRelationManager;

it('configures the single subscriber select so Filament can validate its value', function () {
    $schema = (new AssignmentsRelationManager)->form(Schema::make());

    $select = collect($schema->getComponents())->first(fn ($c) => $c instanceof Select);

    expect($select)->toBeInstanceOf(Select::class);
    assert($select instanceof Select);

    expect($select->isMultiple())->toBeFalse();

    // Throws a LogicException when neither options() nor getOptionLabelUsing() is set.
    expect(fn () => $select->getInValidationRuleValues())->not->toThrow(LogicException::class);
});
