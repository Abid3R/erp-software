<?php

namespace App\Enums;

enum OpportunityStage: string
{
    case Prospecting = 'prospecting';
    case Qualification = 'qualification';
    case Proposal = 'proposal';
    case Negotiation = 'negotiation';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Won => 'success',
            self::Negotiation, self::Proposal => 'info',
            self::Qualification, self::Prospecting => 'warning',
            self::Lost => 'danger',
        };
    }

    /** Still an open deal in the pipeline (not won/lost). */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Won, self::Lost], true);
    }

    /** Default win probability (%) suggested for the stage. */
    public function defaultProbability(): int
    {
        return match ($this) {
            self::Prospecting => 10,
            self::Qualification => 25,
            self::Proposal => 50,
            self::Negotiation => 75,
            self::Won => 100,
            self::Lost => 0,
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
