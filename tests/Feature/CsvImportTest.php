<?php

use App\Support\CsvActions;

function tmpCsv(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'csv').'.csv';
    file_put_contents($path, $content);

    return $path;
}

it('handles a UTF-8 BOM header from Excel without corrupting the first column', function () {
    $rows = [];
    $handler = function (array $r) use (&$rows): string {
        $rows[] = $r;

        return 'created';
    };
    $bom = "\xEF\xBB\xBF";
    $path = tmpCsv($bom."sku,name\nA1,Widget\n");

    $result = CsvActions::process($path, $handler);
    unlink($path);

    expect($result['created'])->toBe(1)
        ->and($rows[0])->toHaveKey('sku')   // NOT the BOM-prefixed "﻿sku"
        ->and($rows[0]['sku'])->toBe('A1');
});

it('skips wholly blank lines without counting them as errors', function () {
    $handler = fn (array $r): string => 'created';
    $path = tmpCsv("sku,name\nA1,Widget\n\n   \n");

    $result = CsvActions::process($path, $handler);
    unlink($path);

    expect($result['created'])->toBe(1)->and($result['skipped'])->toBe(0);
});

it('pads a short row and tolerates empty trailing columns', function () {
    $rows = [];
    $handler = function (array $r) use (&$rows): string {
        $rows[] = $r;

        return 'created';
    };
    $path = tmpCsv("sku,name,note\nA1,Widget\nA2,Gadget,,\n");

    $result = CsvActions::process($path, $handler);
    unlink($path);

    expect($result['created'])->toBe(2)
        ->and($rows[0]['note'])->toBe('')   // short row padded
        ->and($rows[1]['sku'])->toBe('A2'); // empty trailing column dropped
});

it('skips a row that has genuine extra columns', function () {
    $handler = fn (array $r): string => 'created';
    $path = tmpCsv("sku,name\nA1,Widget,EXTRA\n");

    $result = CsvActions::process($path, $handler);
    unlink($path);

    expect($result['created'])->toBe(0)->and($result['skipped'])->toBe(1);
});
