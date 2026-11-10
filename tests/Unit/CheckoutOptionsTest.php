<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use SubKit\Checkout\CheckoutContext;
use SubKit\Checkout\CheckoutOptions;
use SubKit\Exceptions\InvalidCheckoutOptionsException;
use Tests\TestCase;

class CheckoutOptionsTest extends TestCase
{
    private const SUB = CheckoutContext::KIND_SUBSCRIPTION;

    private const TOP_UP = CheckoutContext::KIND_TOP_UP;

    /** @return array<string, string> */
    private static function keys(int $count): array
    {
        return array_fill_keys(array_map(fn (int $i) => "k{$i}", range(1, $count)), 'v');
    }

    public function test_null_and_empty_return_nothing(): void
    {
        $this->assertSame([], CheckoutOptions::validate(null, self::SUB));
        $this->assertSame([], CheckoutOptions::validate([], self::SUB));
        $this->assertSame([], CheckoutOptions::validate(['metadata' => []], self::SUB));
    }

    public function test_valid_subscription_options_pass_and_values_are_stringified(): void
    {
        $out = CheckoutOptions::validate([
            'metadata' => ['customer_id' => 42, 'vip' => true, 'ref' => 'abc'],
            'transfer_data' => ['destination' => 'acct_1ABC'],
            'application_fee_percent' => 12.5,
        ], self::SUB);

        $this->assertSame(['customer_id' => '42', 'vip' => 'true', 'ref' => 'abc'], $out['metadata']);
        $this->assertSame(['destination' => 'acct_1ABC'], $out['transfer_data']);
        $this->assertSame(12.5, $out['application_fee_percent']);
    }

    public function test_valid_top_up_options_pass(): void
    {
        $out = CheckoutOptions::validate([
            'transfer_data' => ['destination' => 'acct_1ABC'],
            'application_fee_amount' => 300,
        ], self::TOP_UP);

        $this->assertSame(300, $out['application_fee_amount']);
    }

    #[DataProvider('invalidCases')]
    public function test_invalid_options_are_rejected(mixed $raw, string $kind): void
    {
        $this->expectException(InvalidCheckoutOptionsException::class);

        CheckoutOptions::validate($raw, $kind);
    }

    public static function invalidCases(): array
    {
        $acct = ['destination' => 'acct_1ABC'];

        return [
            'not an array' => ['nope', self::SUB],
            'unknown key' => [['allow_promotion_codes' => true], self::SUB],
            'percent on top-up' => [['transfer_data' => $acct, 'application_fee_percent' => 10], self::TOP_UP],
            'amount on subscription' => [['transfer_data' => $acct, 'application_fee_amount' => 10], self::SUB],
            'reserved key name' => [['metadata' => ['name' => 'x']], self::SUB],
            'reserved key type' => [['metadata' => ['type' => 'x']], self::SUB],
            'reserved key plan_code' => [['metadata' => ['plan_code' => 'x']], self::SUB],
            'reserved key topup_code' => [['metadata' => ['topup_code' => 'x']], self::TOP_UP],
            'key too long' => [['metadata' => [str_repeat('k', 41) => 'x']], self::SUB],
            'numeric key' => [['metadata' => ['42' => 'x']], self::SUB],
            'empty value' => [['metadata' => ['k' => '']], self::SUB],
            'percent with 3 decimals' => [['transfer_data' => $acct, 'application_fee_percent' => 12.345], self::SUB],
            'key with brackets' => [['metadata' => ['a[b]' => 'x']], self::SUB],
            'value too long' => [['metadata' => ['k' => str_repeat('v', 501)]], self::SUB],
            'array value' => [['metadata' => ['k' => ['x']]], self::SUB],
            'null value' => [['metadata' => ['k' => null]], self::SUB],
            'too many keys (subscription)' => [['metadata' => self::keys(48)], self::SUB],
            'too many keys (top-up)' => [['metadata' => self::keys(49)], self::TOP_UP],
            'bad destination' => [['transfer_data' => ['destination' => 'cus_123']], self::SUB],
            'extra transfer_data key' => [['transfer_data' => $acct + ['amount' => 5]], self::SUB],
            'fee without destination' => [['application_fee_percent' => 10], self::SUB],
            'percent zero' => [['transfer_data' => $acct, 'application_fee_percent' => 0], self::SUB],
            'percent over 100' => [['transfer_data' => $acct, 'application_fee_percent' => 101], self::SUB],
            'percent as string' => [['transfer_data' => $acct, 'application_fee_percent' => '10'], self::SUB],
            'amount as float' => [['transfer_data' => $acct, 'application_fee_amount' => 1.5], self::TOP_UP],
        ];
    }

    public function test_percent_with_two_decimals_and_keys_containing_digits_are_accepted(): void
    {
        $out = CheckoutOptions::validate([
            'metadata' => ['order_42' => 'x', '4a' => 'y'],
            'transfer_data' => ['destination' => 'acct_1ABC'],
            'application_fee_percent' => 12.34,
        ], self::SUB);

        $this->assertSame(12.34, $out['application_fee_percent']);
        $this->assertSame(['order_42' => 'x', '4a' => 'y'], $out['metadata']);
    }

    public function test_metadata_limit_boundaries_are_accepted(): void
    {
        $sub = CheckoutOptions::validate(['metadata' => self::keys(47)], self::SUB);
        $top = CheckoutOptions::validate(['metadata' => self::keys(48)], self::TOP_UP);

        $this->assertCount(47, $sub['metadata']);
        $this->assertCount(48, $top['metadata']);
    }
}
