<?php

use App\Domain\Inventory\WeightedAverageCost;
use Brick\Math\BigDecimal;

it('computes the moving average on receipt (spec #15 worked example)', function () {
    // 100 @ 50 already on hand, receive 50 @ 60 -> 8000 / 150 = 53.333333
    $avg = WeightedAverageCost::recompute(
        BigDecimal::of('100'), BigDecimal::of('50'),
        BigDecimal::of('50'), BigDecimal::of('60'), 6,
    );

    expect((string) $avg)->toBe('53.333333');
});

it('uses the received cost when there was no prior stock', function () {
    $avg = WeightedAverageCost::recompute(
        BigDecimal::of('0'), BigDecimal::of('0'),
        BigDecimal::of('100'), BigDecimal::of('50'), 6,
    );

    expect((string) $avg)->toBe('50.000000');
});

it('is unaffected by order and stays exact across several receipts', function () {
    // (10*100 + 10*110) / 20 = 105; then (+5 @ 132): (20*105 + 5*132)/25 = 2760/25 = 110.4
    $avg = WeightedAverageCost::recompute(
        BigDecimal::of('10'), BigDecimal::of('100'),
        BigDecimal::of('10'), BigDecimal::of('110'), 6,
    );
    expect((string) $avg)->toBe('105.000000');

    $avg = WeightedAverageCost::recompute(
        BigDecimal::of('20'), $avg,
        BigDecimal::of('5'), BigDecimal::of('132'), 6,
    );
    expect((string) $avg)->toBe('110.400000');
});
