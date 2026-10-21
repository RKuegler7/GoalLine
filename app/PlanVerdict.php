<?php

namespace App;

enum PlanVerdict: string
{
    case OnTrack = 'on_track';
    case AtRisk = 'at_risk';
    case OffTrack = 'off_track';

    /**
     * The label shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::OnTrack => 'Still in line',
            self::AtRisk => 'At risk',
            self::OffTrack => 'Off track',
        };
    }
}
