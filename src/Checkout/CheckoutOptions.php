<?php

namespace SubKit\Checkout;

use SubKit\Exceptions\InvalidCheckoutOptionsException;

/**
 * Validates what the host's checkout hook returns (ADR-002).
 *
 * The hook is the only trusted source of these values. Anything outside the allowlist,
 * any reserved metadata key and any value Stripe would reject is refused up front, so
 * mistakes surface in development instead of as a failed Stripe call mid-checkout.
 */
final class CheckoutOptions
{
    /** Metadata keys set by Cashier or by SubKit itself. The hook cannot use them. */
    public const RESERVED_METADATA_KEYS = [
        'name',
        'type',
        'is_on_session_checkout',
        'plan_code',
        'subkit_kind',
        'topup_code',
    ];

    private const STRIPE_MAX_KEYS = 50;

    private const STRIPE_MAX_KEY_LENGTH = 40;

    private const STRIPE_MAX_VALUE_LENGTH = 500;

    /** Keys Cashier/SubKit add on top of the host's metadata, per checkout kind. */
    private const KEYS_SET_BY_PACKAGE = [
        CheckoutContext::KIND_SUBSCRIPTION => 3, // name, type, is_on_session_checkout
        CheckoutContext::KIND_TOP_UP => 2,       // subkit_kind, topup_code
    ];

    private const ALLOWED = [
        CheckoutContext::KIND_SUBSCRIPTION => ['metadata', 'transfer_data', 'application_fee_percent'],
        CheckoutContext::KIND_TOP_UP => ['metadata', 'transfer_data', 'application_fee_amount'],
    ];

    /**
     * @param  mixed  $raw  Whatever the hook returned.
     * @param  CheckoutContext::KIND_*  $kind
     * @return array{metadata?: array<string, string>, transfer_data?: array{destination: string}, application_fee_percent?: int|float, application_fee_amount?: int}
     *
     * @throws InvalidCheckoutOptionsException
     */
    public static function validate(mixed $raw, string $kind): array
    {
        if ($raw === null) {
            return [];
        }

        if (! is_array($raw)) {
            throw new InvalidCheckoutOptionsException('The checkout hook must return an array or null.');
        }

        $unknown = array_diff(array_keys($raw), self::ALLOWED[$kind]);
        if ($unknown !== []) {
            throw new InvalidCheckoutOptionsException(sprintf(
                'The checkout hook returned unsupported key(s) [%s] for a %s checkout. Allowed: %s.',
                implode(', ', $unknown),
                $kind,
                implode(', ', self::ALLOWED[$kind]),
            ));
        }

        $out = [];

        if (array_key_exists('metadata', $raw)) {
            $metadata = self::metadata($raw['metadata'], $kind);
            if ($metadata !== []) {
                $out['metadata'] = $metadata;
            }
        }

        if (array_key_exists('transfer_data', $raw)) {
            $out['transfer_data'] = self::transferData($raw['transfer_data']);
        }

        if (array_key_exists('application_fee_percent', $raw)) {
            $out['application_fee_percent'] = self::feePercent($raw['application_fee_percent']);
        }

        if (array_key_exists('application_fee_amount', $raw)) {
            $out['application_fee_amount'] = self::feeAmount($raw['application_fee_amount']);
        }

        if ((isset($out['application_fee_percent']) || isset($out['application_fee_amount'])) && ! isset($out['transfer_data'])) {
            throw new InvalidCheckoutOptionsException(
                'An application fee requires transfer_data.destination to be set as well.'
            );
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private static function metadata(mixed $metadata, string $kind): array
    {
        if (! is_array($metadata)) {
            throw new InvalidCheckoutOptionsException('metadata must be an array of key => value pairs.');
        }

        $max = self::STRIPE_MAX_KEYS - self::KEYS_SET_BY_PACKAGE[$kind];
        if (count($metadata) > $max) {
            throw new InvalidCheckoutOptionsException("metadata may contain at most {$max} keys for a {$kind} checkout.");
        }

        $clean = [];

        foreach ($metadata as $key => $value) {
            $key = (string) $key;

            if (in_array($key, self::RESERVED_METADATA_KEYS, true)) {
                throw new InvalidCheckoutOptionsException("metadata key [{$key}] is reserved.");
            }

            // All-digit keys become int array keys in PHP, and Cashier's array_merge() would renumber them.
            if (ctype_digit($key)) {
                throw new InvalidCheckoutOptionsException(
                    "metadata key [{$key}] is invalid: keys made only of digits are not supported."
                );
            }

            if ($key === '' || strlen($key) > self::STRIPE_MAX_KEY_LENGTH || str_contains($key, '[') || str_contains($key, ']')) {
                throw new InvalidCheckoutOptionsException(
                    "metadata key [{$key}] is invalid: 1-".self::STRIPE_MAX_KEY_LENGTH.' characters, no square brackets.'
                );
            }

            if (! is_scalar($value)) {
                throw new InvalidCheckoutOptionsException("metadata value for [{$key}] must be a string, number or boolean.");
            }

            $value = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;

            // Stripe treats an empty value as "unset", so the key would silently vanish.
            if ($value === '') {
                throw new InvalidCheckoutOptionsException("metadata value for [{$key}] must not be empty.");
            }

            if (strlen($value) > self::STRIPE_MAX_VALUE_LENGTH) {
                throw new InvalidCheckoutOptionsException(
                    "metadata value for [{$key}] exceeds ".self::STRIPE_MAX_VALUE_LENGTH.' characters.'
                );
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    /**
     * @return array{destination: string}
     */
    private static function transferData(mixed $transferData): array
    {
        if (! is_array($transferData) || array_keys($transferData) !== ['destination']) {
            throw new InvalidCheckoutOptionsException('transfer_data must be an array containing only [destination].');
        }

        $destination = $transferData['destination'];

        if (! is_string($destination) || ! preg_match('/^acct_[A-Za-z0-9]+$/', $destination)) {
            throw new InvalidCheckoutOptionsException('transfer_data.destination must be a connected account ID (acct_...).');
        }

        return ['destination' => $destination];
    }

    private static function feePercent(mixed $percent): int|float
    {
        if (! is_int($percent) && ! is_float($percent)) {
            throw new InvalidCheckoutOptionsException('application_fee_percent must be a number.');
        }

        if ($percent <= 0 || $percent > 100) {
            throw new InvalidCheckoutOptionsException('application_fee_percent must be greater than 0 and at most 100.');
        }

        // Stripe accepts at most two decimal places.
        if (abs($percent * 100 - round($percent * 100)) > 1e-9) {
            throw new InvalidCheckoutOptionsException('application_fee_percent may have at most 2 decimal places.');
        }

        return $percent;
    }

    private static function feeAmount(mixed $amount): int
    {
        if (! is_int($amount) || $amount <= 0) {
            throw new InvalidCheckoutOptionsException('application_fee_amount must be a positive integer (smallest currency unit).');
        }

        return $amount;
    }
}
