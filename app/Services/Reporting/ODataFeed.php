<?php

namespace App\Services\Reporting;

use App\Models\Credit;
use App\Models\Reward;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * A read-only OData v4 feed (the subset Power BI, Excel and Tableau use) over
 * the workspace's payouts, credits and transactions. Supports $filter
 * (eq/ne/gt/ge/lt/le joined by "and"), $orderby, $select, $top, $skip and
 * $count, with server-driven paging via @odata.nextLink. Tenant isolation
 * comes from the models' workspace scope, pinned by the API middleware.
 */
class ODataFeed
{
    public const NAMESPACE = 'SalesManagement';

    /** Server page size; clients follow @odata.nextLink for more. */
    public const PAGE_SIZE = 1000;

    /**
     * Entity sets. Each property: [Edm type, filter/sort column or null, value getter].
     *
     * @return array<string, array{type:string, query:\Closure, properties:array<string, array{0:string, 1:?string, 2:\Closure}>}>
     */
    public function entitySets(): array
    {
        $datetime = fn ($v) => $v ? Carbon::parse($v)->utc()->format('Y-m-d\TH:i:s\Z') : null;

        return [
            'Payouts' => [
                'type' => 'Payout',
                'query' => fn () => Reward::released()->with(['user:id,name', 'plan:id,name']),
                'properties' => [
                    'Id' => ['Edm.Int64', 'id', fn (Reward $r) => $r->id],
                    'UserId' => ['Edm.Int64', 'user_id', fn (Reward $r) => $r->user_id],
                    'UserName' => ['Edm.String', null, fn (Reward $r) => $r->user?->name],
                    'PlanId' => ['Edm.Int64', 'plan_id', fn (Reward $r) => $r->plan_id],
                    'PlanName' => ['Edm.String', null, fn (Reward $r) => $r->plan?->name],
                    'RewardType' => ['Edm.String', 'reward_type', fn (Reward $r) => $r->reward_type->value],
                    'Amount' => ['Edm.Decimal', 'computed_amount', fn (Reward $r) => $r->computed_amount !== null ? (float) $r->computed_amount : null],
                    'Currency' => ['Edm.String', 'currency', fn (Reward $r) => $r->currency],
                    'CalcRunId' => ['Edm.Int64', 'calc_run_id', fn (Reward $r) => $r->calc_run_id],
                    'CreatedAt' => ['Edm.DateTimeOffset', 'created_at', fn (Reward $r) => $datetime($r->created_at)],
                ],
            ],
            'Credits' => [
                'type' => 'Credit',
                'query' => fn () => Credit::released()->with(['user:id,name', 'transaction:id,external_id']),
                'properties' => [
                    'Id' => ['Edm.Int64', 'id', fn (Credit $c) => $c->id],
                    'UserId' => ['Edm.Int64', 'user_id', fn (Credit $c) => $c->user_id],
                    'UserName' => ['Edm.String', null, fn (Credit $c) => $c->user?->name],
                    'TransactionId' => ['Edm.Int64', 'transaction_id', fn (Credit $c) => $c->transaction_id],
                    'TransactionExternalId' => ['Edm.String', null, fn (Credit $c) => $c->transaction?->external_id],
                    'CreditedAmount' => ['Edm.Decimal', 'credited_amount', fn (Credit $c) => (float) $c->credited_amount],
                    'Currency' => ['Edm.String', 'currency', fn (Credit $c) => $c->currency],
                    'CalcRunId' => ['Edm.Int64', 'calc_run_id', fn (Credit $c) => $c->calc_run_id],
                    'CreatedAt' => ['Edm.DateTimeOffset', 'created_at', fn (Credit $c) => $datetime($c->created_at)],
                ],
            ],
            'Transactions' => [
                'type' => 'Transaction',
                'query' => fn () => Transaction::query(),
                'properties' => [
                    'Id' => ['Edm.Int64', 'id', fn (Transaction $t) => $t->id],
                    'ExternalId' => ['Edm.String', 'external_id', fn (Transaction $t) => $t->external_id],
                    'SourceSystem' => ['Edm.String', 'source_system', fn (Transaction $t) => $t->source_system],
                    'Amount' => ['Edm.Decimal', 'amount', fn (Transaction $t) => (float) $t->amount],
                    'ProfitAmount' => ['Edm.Decimal', 'profit_amount', fn (Transaction $t) => $t->profit_amount !== null ? (float) $t->profit_amount : null],
                    'Currency' => ['Edm.String', 'currency', fn (Transaction $t) => $t->currency],
                    'TransactionDate' => ['Edm.Date', 'transaction_date', fn (Transaction $t) => $t->transaction_date?->toDateString()],
                    'Customer' => ['Edm.String', null, fn (Transaction $t) => data_get($t->raw_data, 'customer')],
                    'Product' => ['Edm.String', null, fn (Transaction $t) => data_get($t->raw_data, 'product')],
                    'Excluded' => ['Edm.Boolean', 'excluded', fn (Transaction $t) => (bool) $t->excluded],
                    'IsPaid' => ['Edm.Boolean', 'is_paid', fn (Transaction $t) => (bool) $t->is_paid],
                    'CreatedAt' => ['Edm.DateTimeOffset', 'created_at', fn (Transaction $t) => $datetime($t->created_at)],
                ],
            ],
        ];
    }

    /** CSDL ($metadata) document describing every entity set. */
    public function metadata(): string
    {
        $types = '';
        $sets = '';

        foreach ($this->entitySets() as $setName => $set) {
            $props = '';
            foreach ($set['properties'] as $name => [$type]) {
                $nullable = $name === 'Id' ? ' Nullable="false"' : '';
                $props .= "<Property Name=\"{$name}\" Type=\"{$type}\"{$nullable}/>";
            }
            $types .= "<EntityType Name=\"{$set['type']}\"><Key><PropertyRef Name=\"Id\"/></Key>{$props}</EntityType>";
            $sets .= '<EntitySet Name="'.$setName.'" EntityType="'.self::NAMESPACE.'.'.$set['type'].'"/>';
        }

        return '<?xml version="1.0" encoding="utf-8"?>'
            .'<edmx:Edmx Version="4.0" xmlns:edmx="http://docs.oasis-open.org/odata/ns/edmx"><edmx:DataServices>'
            .'<Schema Namespace="'.self::NAMESPACE.'" xmlns="http://docs.oasis-open.org/odata/ns/edm">'
            .$types.'<EntityContainer Name="Container">'.$sets.'</EntityContainer>'
            .'</Schema></edmx:DataServices></edmx:Edmx>';
    }

    /**
     * Run a query against an entity set.
     *
     * @param  array<string, mixed>  $options  raw $-prefixed query options
     * @return array{value: array<int, array<string, mixed>>, count: ?int, next_skip: ?int, next_top: ?int}
     *
     * @throws ODataQueryException on an unsupported or malformed option
     */
    public function query(string $setName, array $options): array
    {
        $set = $this->entitySets()[$setName];
        $properties = $set['properties'];

        /** @var Builder $query */
        $query = ($set['query'])();

        if (filled($options['$filter'] ?? null)) {
            $this->applyFilter($query, (string) $options['$filter'], $properties);
        }

        $count = filter_var($options['$count'] ?? false, FILTER_VALIDATE_BOOLEAN) ? (clone $query)->count() : null;

        $this->applyOrderBy($query, (string) ($options['$orderby'] ?? ''), $properties);

        $skip = $this->nonNegativeInt($options['$skip'] ?? null, '$skip') ?? 0;
        $top = $this->nonNegativeInt($options['$top'] ?? null, '$top');
        $take = $top === null ? self::PAGE_SIZE : min($top, self::PAGE_SIZE);

        $rows = $query->skip($skip)->take($take + 1)->get();
        $hasMore = $rows->count() > $take;
        $rows = $rows->take($take);

        $select = $this->select((string) ($options['$select'] ?? ''), $properties);

        $value = $rows->map(function ($model) use ($select) {
            $row = [];
            foreach ($select as $name => [, , $getter]) {
                $row[$name] = $getter($model);
            }

            return $row;
        })->values()->all();

        $remaining = $top === null ? null : $top - $take;
        $more = $hasMore && ($remaining === null || $remaining > 0);

        return [
            'value' => $value,
            'count' => $count,
            'next_skip' => $more ? $skip + $take : null,
            'next_top' => $more ? $remaining : null,
        ];
    }

    /**
     * @param  array<string, array{0:string, 1:?string, 2:\Closure}>  $properties
     */
    protected function applyFilter(Builder $query, string $filter, array $properties): void
    {
        $literal = "'(?:[^']|'')*'|\\d{4}-\\d{2}-\\d{2}(?:T[0-9:.]+(?:Z|[+-]\\d{2}:\\d{2})?)?|-?\\d+(?:\\.\\d+)?|true|false|null";
        $pattern = "/\\G\\s*([A-Za-z]+)\\s+(eq|ne|gt|ge|lt|le)\\s+({$literal})\\s*(?:(and)\\s+|$)/i";
        $operators = ['eq' => '=', 'ne' => '!=', 'gt' => '>', 'ge' => '>=', 'lt' => '<', 'le' => '<='];

        $offset = 0;
        $length = strlen($filter);
        while ($offset < $length) {
            if (! preg_match($pattern, $filter, $m, 0, $offset)) {
                throw new ODataQueryException('Unsupported $filter. Use: Property eq|ne|gt|ge|lt|le value [and …].');
            }
            $offset += strlen($m[0]);

            [$type, $column] = $this->filterableProperty($m[1], $properties);
            $op = strtolower($m[2]);
            $value = $this->literal($m[3], $type);

            if ($value === null) {
                in_array($op, ['eq', 'ne'], true)
                    ? ($op === 'eq' ? $query->whereNull($column) : $query->whereNotNull($column))
                    : throw new ODataQueryException('null can only be compared with eq or ne.');
            } elseif ($type === 'Edm.Date') {
                $query->whereDate($column, $operators[$op], $value);
            } else {
                $query->where($column, $operators[$op], $value);
            }

            if (empty($m[4]) && $offset < $length) {
                throw new ODataQueryException('Unsupported $filter. Clauses must be joined with "and".');
            }
        }
    }

    /**
     * @param  array<string, array{0:string, 1:?string, 2:\Closure}>  $properties
     */
    protected function applyOrderBy(Builder $query, string $orderBy, array $properties): void
    {
        foreach (array_filter(array_map('trim', explode(',', $orderBy))) as $clause) {
            if (! preg_match('/^([A-Za-z]+)(?:\s+(asc|desc))?$/i', $clause, $m)) {
                throw new ODataQueryException("Unsupported \$orderby clause: {$clause}");
            }
            [, $column] = $this->filterableProperty($m[1], $properties);
            $query->orderBy($column, strtolower($m[2] ?? 'asc'));
        }

        // Stable order so server-driven paging never repeats or skips rows.
        $query->orderBy($query->getModel()->getQualifiedKeyName());
    }

    /**
     * @param  array<string, array{0:string, 1:?string, 2:\Closure}>  $properties
     * @return array<string, array{0:string, 1:?string, 2:\Closure}>
     */
    protected function select(string $select, array $properties): array
    {
        $names = array_filter(array_map('trim', explode(',', $select)));
        if ($names === [] || $names === ['*']) {
            return $properties;
        }

        $unknown = array_diff($names, array_keys($properties));
        if ($unknown !== []) {
            throw new ODataQueryException('Unknown property in $select: '.implode(', ', $unknown));
        }

        return array_intersect_key($properties, array_flip($names));
    }

    /**
     * @param  array<string, array{0:string, 1:?string, 2:\Closure}>  $properties
     * @return array{0:string, 1:string}
     */
    protected function filterableProperty(string $name, array $properties): array
    {
        if (! isset($properties[$name])) {
            throw new ODataQueryException("Unknown property: {$name}");
        }
        if ($properties[$name][1] === null) {
            throw new ODataQueryException("Property {$name} cannot be used to filter or sort.");
        }

        return [$properties[$name][0], $properties[$name][1]];
    }

    protected function literal(string $raw, string $type): mixed
    {
        $lower = strtolower($raw);

        return match (true) {
            $lower === 'null' => null,
            $lower === 'true', $lower === 'false' => $lower === 'true',
            str_starts_with($raw, "'") => str_replace("''", "'", substr($raw, 1, -1)),
            (bool) preg_match('/^\d{4}-\d{2}-\d{2}/', $raw) => $type === 'Edm.Date'
                ? Carbon::parse($raw)->toDateString()
                : Carbon::parse($raw)->utc()->format('Y-m-d H:i:s'),
            default => $raw + 0,
        };
    }

    protected function nonNegativeInt(mixed $value, string $option): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! ctype_digit((string) $value)) {
            throw new ODataQueryException("{$option} must be a non-negative integer.");
        }

        return (int) $value;
    }
}
