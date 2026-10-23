<?php

namespace Slakbal\TenancyFoundation\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

trait HasUserAttributes
{
    /*
        public function birthdayAge(): Attribute
        {
            return Attribute::make(
                get: fn () => birthdayAge($this->personal_id)
            );
        }

        public function birthday(): Attribute
        {
            return Attribute::make(
                get: fn () => birthday($this->personal_id)
            );
        }

        public function age(): Attribute
        {
            return Attribute::make(
                get: fn () => (int) age($this->personal_id)
            );
        }
        */

    /**
     * Get the user's initials - transferred from original model method
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    /**
     * Get the user's initials .. only used in the startkit view not in filament can be removed later
     * TODO see if the can be removed as it is only used in the starterkit view and not in filament. But keep for now could maybe use it
     */
    //    public function initials(): string
    //    {
    //        return Str::of($this->name)
    //            ->explode(' ')
    //            ->take(2)
    //            ->map(fn ($word) => Str::substr($word, 0, 1))
    //            ->implode('');
    //    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => "{$this->first_name} {$this->last_name}",
        );
    }

    protected function formattedName(): Attribute
    {
        return Attribute::make(
            get: fn () => "{$this->last_name}, {$this->first_name}",
        );
    }

    protected function firstName(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => upperCaseWordOrNull($value),
        );
    }

    protected function lastName(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => upperCaseWordOrNull($value),
        );
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => mb_strtolower((string) $value),
        );
    }
}
