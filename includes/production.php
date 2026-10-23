<?php

function productionPeriods(): array
{
    return ['oggi' => 'Oggi', 'ieri' => 'Ieri', '7_giorni' => 'Ultimi 7 Giorni',
        'mese' => 'Mese Corrente', 'anno' => 'Anno Corrente', 'tutto' => "Dall'inizio"];
}

function productionPeriodBounds(string $period, ?DateTimeImmutable $now = null): array
{
    $zone = new DateTimeZone('Europe/Rome');
    $today = ($now ?? new DateTimeImmutable('now', $zone))->setTimezone($zone)->setTime(0, 0);
    [$start, $end] = match ($period) {
        'oggi' => [$today, $today->modify('+1 day')],
        'ieri' => [$today->modify('-1 day'), $today],
        '7_giorni' => [$today->modify('-6 days'), $today->modify('+1 day')],
        'mese' => [$today->modify('first day of this month'), $today->modify('first day of next month')],
        'anno' => [$today->setDate((int)$today->format('Y'), 1, 1), $today->setDate((int)$today->format('Y') + 1, 1, 1)],
        default => [null, null],
    };
    $utc = new DateTimeZone('UTC');
    return [$start?->setTimezone($utc)->format('Y-m-d H:i:s'), $end?->setTimezone($utc)->format('Y-m-d H:i:s')];
}
