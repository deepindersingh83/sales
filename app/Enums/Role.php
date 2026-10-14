<?php

namespace App\Enums;

/**
 * The four per-workspace roles from the spec. Stored as a string on the
 * workspace_user pivot (a user can hold a different role in each workspace).
 */
enum Role: string
{
    case FullAdmin = 'full_admin';
    case PlanAdmin = 'plan_admin';
    case LimitedAdmin = 'limited_admin';
    case Participant = 'participant';

    public function label(): string
    {
        return match ($this) {
            self::FullAdmin => 'Full Admin',
            self::PlanAdmin => 'Plan Admin',
            self::LimitedAdmin => 'Limited Admin',
            self::Participant => 'Participant',
        };
    }

    /**
     * Roles that reach the admin area at all (participants do not).
     */
    public function isAdmin(): bool
    {
        return $this !== self::Participant;
    }

    /**
     * Unrestricted control over the whole workspace.
     */
    public function isFullAdmin(): bool
    {
        return $this === self::FullAdmin;
    }

    /**
     * Can create/modify data (full + plan admins). Limited admins are read-only;
     * participants only see their own released data.
     */
    public function canWrite(): bool
    {
        return in_array($this, [self::FullAdmin, self::PlanAdmin], true);
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $r) => ['value' => $r->value, 'label' => $r->label()],
            self::cases()
        );
    }
}
