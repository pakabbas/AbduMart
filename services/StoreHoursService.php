<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

class StoreHoursService
{
    public const DAY_KEYS = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

    public const DAY_LABELS = [
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
        'sunday' => 'Sunday',
    ];

    public static function defaultHours(): array
    {
        $hours = [];
        foreach (self::DAY_KEYS as $day) {
            $hours[$day] = [
                'closed' => $day === 'sunday',
                'open' => '09:00',
                'close' => '21:00',
            ];
        }

        return $hours;
    }

    public static function timezone(): string
    {
        $tz = trim((string) setting('store_timezone', 'America/Detroit'));
        try {
            new DateTimeZone($tz);
        } catch (Throwable) {
            return 'America/Detroit';
        }

        return $tz;
    }

    public static function locationLabel(): string
    {
        $label = trim((string) setting('store_location', ''));
        if ($label !== '') {
            return $label;
        }

        return trim((string) setting('mart.address', config('mart.address')));
    }

    /**
     * @return array<string, array{closed:bool,open:string,close:string}>
     */
    public static function weeklyHours(): array
    {
        $raw = (string) setting('store_hours_json', '');
        if ($raw === '') {
            return self::defaultHours();
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return self::defaultHours();
        }

        $hours = self::defaultHours();
        foreach (self::DAY_KEYS as $day) {
            if (!isset($decoded[$day]) || !is_array($decoded[$day])) {
                continue;
            }
            $row = $decoded[$day];
            $hours[$day] = [
                'closed' => !empty($row['closed']),
                'open' => self::normalizeTime((string) ($row['open'] ?? '09:00'), '09:00'),
                'close' => self::normalizeTime((string) ($row['close'] ?? '21:00'), '21:00'),
            ];
        }

        return $hours;
    }

    /**
     * @return array<int, array{date:string,name:string}>
     */
    public static function holidays(): array
    {
        $raw = (string) setting('store_holidays_json', '');
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $holidays = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $date = self::normalizeDate((string) ($row['date'] ?? ''));
            if ($date === null) {
                continue;
            }
            $holidays[] = [
                'date' => $date,
                'name' => trim((string) ($row['name'] ?? '')),
            ];
        }

        usort($holidays, static fn (array $a, array $b): int => strcmp($a['date'], $b['date']));

        return $holidays;
    }

    public static function isHoliday(DateTimeImmutable $moment): bool
    {
        $date = $moment->format('Y-m-d');
        foreach (self::holidays() as $holiday) {
            if ($holiday['date'] === $date) {
                return true;
            }
        }

        return false;
    }

    public static function holidayName(DateTimeImmutable $moment): ?string
    {
        $date = $moment->format('Y-m-d');
        foreach (self::holidays() as $holiday) {
            if ($holiday['date'] === $date) {
                $name = trim($holiday['name']);
                return $name !== '' ? $name : null;
            }
        }

        return null;
    }

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone(self::timezone()));
    }

    public static function isOpen(?DateTimeImmutable $moment = null): bool
    {
        return self::status($moment)['open'];
    }

    /**
     * @return array{
     *   open: bool,
     *   reason: string,
     *   message: string,
     *   banner_message: string,
     *   next_open_at: ?string,
     *   next_open_label: ?string,
     *   timezone: string
     * }
     */
    public static function status(?DateTimeImmutable $moment = null): array
    {
        $moment ??= self::now();
        $timezone = self::timezone();

        if (self::isHoliday($moment)) {
            $next = self::findNextOpenMoment($moment);
            $label = $next ? self::formatOpenLabel($next, $moment) : null;

            return [
                'open' => false,
                'reason' => 'holiday',
                'message' => 'Closed today for a holiday.',
                'banner_message' => self::buildBannerMessage($label, 'holiday'),
                'next_open_at' => $next?->format(DateTimeImmutable::ATOM),
                'next_open_label' => $label,
                'timezone' => $timezone,
            ];
        }

        $dayKey = strtolower($moment->format('l'));
        $hours = self::weeklyHours();
        $day = $hours[$dayKey] ?? ['closed' => true, 'open' => '09:00', 'close' => '21:00'];

        if (!empty($day['closed'])) {
            $next = self::findNextOpenMoment($moment);
            $label = $next ? self::formatOpenLabel($next, $moment) : null;

            return [
                'open' => false,
                'reason' => 'closed_day',
                'message' => 'Closed today.',
                'banner_message' => self::buildBannerMessage($label, 'closed_day'),
                'next_open_at' => $next?->format(DateTimeImmutable::ATOM),
                'next_open_label' => $label,
                'timezone' => $timezone,
            ];
        }

        $openAt = self::timeOnDate($moment, $day['open']);
        $closeAt = self::timeOnDate($moment, $day['close']);

        if ($moment < $openAt) {
            $label = self::formatOpenLabel($openAt, $moment);

            return [
                'open' => false,
                'reason' => 'before_hours',
                'message' => 'Not open yet today.',
                'banner_message' => self::buildBannerMessage($label, 'before_hours'),
                'next_open_at' => $openAt->format(DateTimeImmutable::ATOM),
                'next_open_label' => $label,
                'timezone' => $timezone,
            ];
        }

        if ($moment >= $closeAt) {
            $next = self::findNextOpenMoment($moment->modify('+1 minute'));
            $label = $next ? self::formatOpenLabel($next, $moment) : null;

            return [
                'open' => false,
                'reason' => 'after_hours',
                'message' => 'Closed for today.',
                'banner_message' => self::buildBannerMessage($label, 'after_hours'),
                'next_open_at' => $next?->format(DateTimeImmutable::ATOM),
                'next_open_label' => $label,
                'timezone' => $timezone,
            ];
        }

        return [
            'open' => true,
            'reason' => 'open',
            'message' => 'Open now.',
            'banner_message' => '',
            'next_open_at' => null,
            'next_open_label' => null,
            'timezone' => $timezone,
        ];
    }

    /**
     * @param array<string, mixed> $hoursInput
     * @param array<int, array<string, mixed>> $holidaysInput
     * @return array{hours_json:string,holidays_json:string}
     */
    public static function encodeSettings(array $hoursInput, array $holidaysInput): array
    {
        $hours = self::defaultHours();
        foreach (self::DAY_KEYS as $day) {
            $row = $hoursInput[$day] ?? [];
            if (!is_array($row)) {
                continue;
            }
            $hours[$day] = [
                'closed' => !empty($row['closed']),
                'open' => self::normalizeTime((string) ($row['open'] ?? '09:00'), '09:00'),
                'close' => self::normalizeTime((string) ($row['close'] ?? '21:00'), '21:00'),
            ];
        }

        $holidays = [];
        foreach ($holidaysInput as $row) {
            if (!is_array($row)) {
                continue;
            }
            $date = self::normalizeDate((string) ($row['date'] ?? ''));
            if ($date === null) {
                continue;
            }
            $holidays[] = [
                'date' => $date,
                'name' => trim((string) ($row['name'] ?? '')),
            ];
        }

        usort($holidays, static fn (array $a, array $b): int => strcmp($a['date'], $b['date']));

        return [
            'hours_json' => json_encode($hours, JSON_THROW_ON_ERROR),
            'holidays_json' => json_encode($holidays, JSON_THROW_ON_ERROR),
        ];
    }

    public static function usTimezoneOptions(): array
    {
        return [
            'America/New_York' => 'Eastern (America/New_York)',
            'America/Detroit' => 'Eastern — Michigan (America/Detroit)',
            'America/Chicago' => 'Central (America/Chicago)',
            'America/Denver' => 'Mountain (America/Denver)',
            'America/Phoenix' => 'Mountain — Arizona (America/Phoenix)',
            'America/Los_Angeles' => 'Pacific (America/Los_Angeles)',
            'America/Anchorage' => 'Alaska (America/Anchorage)',
            'Pacific/Honolulu' => 'Hawaii (Pacific/Honolulu)',
        ];
    }

    private static function buildBannerMessage(?string $nextLabel, string $reason): string
    {
        if ($nextLabel === null) {
            return 'Abdu Mart is closed right now. You can still add items to your cart and browse the menu.';
        }

        if ($reason === 'holiday') {
            return 'Abdu Mart is closed today. We open ' . $nextLabel . '. You can still add items to your cart and browse the menu.';
        }

        return 'Abdu Mart opens ' . $nextLabel . '. You can still add items to your cart and browse the menu.';
    }

    private static function findNextOpenMoment(DateTimeImmutable $from): ?DateTimeImmutable
    {
        $cursor = $from;
        for ($i = 0; $i < 370; $i++) {
            if (self::isHoliday($cursor)) {
                $cursor = $cursor->setTime(0, 0)->modify('+1 day');
                continue;
            }

            $dayKey = strtolower($cursor->format('l'));
            $day = self::weeklyHours()[$dayKey] ?? null;
            if ($day === null || !empty($day['closed'])) {
                $cursor = $cursor->setTime(0, 0)->modify('+1 day');
                continue;
            }

            $openAt = self::timeOnDate($cursor, $day['open']);
            if ($cursor <= $openAt) {
                return $openAt;
            }

            $cursor = $cursor->setTime(0, 0)->modify('+1 day');
        }

        return null;
    }

    private static function timeOnDate(DateTimeImmutable $date, string $time): DateTimeImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', self::normalizeTime($time, '09:00')));

        return $date->setTime($hour, $minute, 0);
    }

    private static function formatOpenLabel(DateTimeImmutable $openAt, DateTimeImmutable $reference): string
    {
        $timeLabel = $openAt->format('g:i A');
        $openDate = $openAt->format('Y-m-d');
        $refDate = $reference->format('Y-m-d');

        if ($openDate === $refDate) {
            return 'today at ' . $timeLabel;
        }

        $tomorrow = $reference->modify('+1 day')->format('Y-m-d');
        if ($openDate === $tomorrow) {
            return 'tomorrow at ' . $timeLabel;
        }

        return 'on ' . $openAt->format('l') . ' at ' . $timeLabel;
    }

    private static function normalizeTime(string $time, string $fallback): string
    {
        $time = trim($time);
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m) === 1) {
            $hour = max(0, min(23, (int) $m[1]));
            $minute = max(0, min(59, (int) $m[2]));

            return sprintf('%02d:%02d', $hour, $minute);
        }

        return $fallback;
    }

    private static function normalizeDate(string $date): ?string
    {
        $date = trim($date);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return null;
        }

        $parts = explode('-', $date);
        if (count($parts) !== 3 || !checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
            return null;
        }

        return $date;
    }
}
