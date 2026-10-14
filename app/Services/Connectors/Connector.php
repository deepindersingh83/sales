<?php

namespace App\Services\Connectors;

/**
 * A CRM/ERP/data connector. The CSV importer is the only fully-live driver in
 * this build; the others are registered as available integrations whose
 * credentials the customer supplies to activate them. Each connector declares
 * the config fields it needs so the UI can render a credentials form, and
 * ultimately yields normalised transaction rows for the TransactionUpserter —
 * the same shape the CSV path produces — so nothing downstream changes.
 */
interface Connector
{
    /** Machine key, e.g. "salesforce". */
    public function key(): string;

    /** Human label, e.g. "Salesforce". */
    public function label(): string;

    public function category(): string; // crm | erp | accounting | payments | files | bi

    /**
     * Config fields the connector needs (name => label), rendered as a
     * credentials form. Empty for connectors that need no config.
     *
     * @return array<string, string>
     */
    public function configFields(): array;

    /** Whether a live integration is implemented (vs. scaffold awaiting build). */
    public function isLive(): bool;
}
