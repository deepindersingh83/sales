<?php

namespace App\Enums;

enum RewardType: string
{
    case Commission = 'commission';
    case Override = 'override';
    case CashFixed = 'cash_fixed';
    case CashPctRevenue = 'cash_pct_revenue';
    case CashPctProfit = 'cash_pct_profit';
    case CashPctSalary = 'cash_pct_salary';
    case Badge = 'badge';
    case Email = 'email';
    case Announcement = 'announcement';
    case Prize = 'prize';

    public function label(): string
    {
        return match ($this) {
            self::Commission => 'Commission',
            self::Override => 'Manager override',
            self::CashFixed => 'Fixed cash',
            self::CashPctRevenue => 'Cash % of revenue',
            self::CashPctProfit => 'Cash % of profit',
            self::CashPctSalary => 'Cash % of salary',
            self::Badge => 'Badge',
            self::Email => 'Email',
            self::Announcement => 'Announcement',
            self::Prize => 'Prize',
        };
    }

    /**
     * Whether this reward yields a monetary payout (vs. recognition-only).
     */
    public function isCash(): bool
    {
        return in_array($this, [
            self::Commission,
            self::Override,
            self::CashFixed,
            self::CashPctRevenue,
            self::CashPctProfit,
            self::CashPctSalary,
        ], true);
    }
}
