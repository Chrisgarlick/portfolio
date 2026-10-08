<?php

declare(strict_types=1);

use App\Support\FitScore;

/*
| The live page's scoreFit(), ported. These cases pin the arithmetic so the
| server cannot drift from the thresholds the result copy was written for.
*/

it('scores the strongest answers as a high fit', function (): void {
    $fit = FitScore::from([
        'hours' => '10h+',                                           // 3
        'task' => 'Assembling monthly client reports from three tools', // 1
        'stack' => 'Notion, Xero',                                   // 1
        'priority' => 'All three',                                   // 2
    ]);

    expect($fit->score)->toBe(7)->and($fit->tier)->toBe('high')->and($fit->isHigh())->toBeTrue();
});

it('puts the boundaries where the live page did', function (array $answers, int $score, string $tier): void {
    $fit = FitScore::from($answers);

    expect([$fit->score, $fit->tier])->toBe([$score, $tier]);
})->with([
    'six is high' => [['hours' => '10h+', 'task' => str_repeat('x', 20), 'stack' => '', 'priority' => 'All three'], 6, 'high'],
    'five is medium' => [['hours' => '2-10h', 'task' => str_repeat('x', 20), 'stack' => '', 'priority' => 'All three'], 5, 'medium'],
    'four is medium' => [['hours' => '2-10h', 'task' => 'short', 'stack' => 'Gmail', 'priority' => 'Reduce time'], 4, 'medium'],
    'three is low' => [['hours' => '<2h', 'task' => 'short', 'stack' => '', 'priority' => 'All three'], 3, 'low'],
    'the minimum' => [['hours' => '<2h', 'task' => 'x', 'stack' => '', 'priority' => ''], 1, 'low'],
]);

it('counts a task of exactly twenty characters, ignoring surrounding space', function (): void {
    $short = FitScore::from(['hours' => '<2h', 'task' => '  '.str_repeat('x', 19).'  ']);
    $long = FitScore::from(['hours' => '<2h', 'task' => str_repeat('x', 20)]);

    expect($long->score - $short->score)->toBe(1);
});

it('does not score the business type, which only shapes the follow-up', function (): void {
    $base = ['hours' => '2-10h', 'task' => 'x', 'priority' => 'Reduce time'];

    expect(FitScore::from([...$base, 'businessType' => 'Agency'])->score)
        ->toBe(FitScore::from([...$base, 'businessType' => 'Other'])->score);
});
