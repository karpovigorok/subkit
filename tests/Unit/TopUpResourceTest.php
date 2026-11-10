<?php

use Filament\Forms\Components\TextInput;
use Filament\Panel;
use Filament\Schemas\Schema;
use SubKit\Filament\Resources\TopUpResource;
use SubKit\Filament\Resources\TopUpResource\RelationManagers\ProviderPricesRelationManager;
use SubKit\Filament\SubKitPlugin;
use SubKit\Models\TopUp;

function topUpFormField(string $name): mixed
{
    $schema = TopUpResource::form(Schema::make());

    return collect($schema->getComponents())->first(fn ($c) => $c->getName() === $name);
}

it('is registered by the plugin and bound to the TopUp model', function () {
    $panel = Mockery::mock(Panel::class);
    $panel->shouldReceive('resources')->once()->withArgs(fn (array $r) => in_array(TopUpResource::class, $r, true));

    (new SubKitPlugin)->register($panel);

    expect(TopUpResource::getModel())->toBe(TopUp::class)
        ->and(array_keys(TopUpResource::getPages()))->toBe(['index', 'create', 'edit']);
});

it('offers the configured providers in the provider prices relation manager', function () {
    config(['subkit.providers' => ['stripe' => 'X', 'paypal' => 'Y']]);

    expect(TopUpResource::providerOptions())->toBe(['stripe' => 'Stripe', 'paypal' => 'Paypal'])
        ->and(TopUpResource::getRelations())->toContain(
            ProviderPricesRelationManager::class
        );
});

it('has the fields a top-up needs', function () {
    foreach (['name', 'code', 'description', 'amount', 'currency', 'is_active'] as $field) {
        expect(topUpFormField($field))->not->toBeNull();
    }

    expect(topUpFormField('code'))->toBeInstanceOf(TextInput::class)
        ->and(topUpFormField('code')->isRequired())->toBeTrue();
});
