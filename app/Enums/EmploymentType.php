<?php

namespace App\Enums;

enum EmploymentType: string
{
    case Permanent = 'permanent';
    case Contract = 'contract';
    case Probation = 'probation';
    case PartTime = 'part_time';
    case Intern = 'intern';

    public function label(): string
    {
        return match ($this) {
            self::Permanent => 'Permanent',
            self::Contract => 'Contract',
            self::Probation => 'Probation',
            self::PartTime => 'Part-time',
            self::Intern => 'Intern',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
