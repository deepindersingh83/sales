<?php

namespace App\Services\Connectors;

/**
 * The catalogue of integrations. Live connectors are fully implemented; the
 * rest are scaffolds — registered, config-aware, and ready for a driver
 * implementation + the customer's credentials.
 */
class ConnectorRegistry
{
    /**
     * @return array<int, array{key:string, label:string, category:string, live:bool, fields:array<string,string>}>
     */
    public function all(): array
    {
        return [
            $this->entry('csv', 'CSV Upload', 'files', true, []),
            $this->entry('rest_api', 'REST API / Webhook', 'files', true, []),

            $this->entry('salesforce', 'Salesforce', 'crm', false, ['instance_url' => 'Instance URL', 'client_id' => 'Client ID', 'client_secret' => 'Client Secret']),
            $this->entry('hubspot', 'HubSpot', 'crm', false, ['api_key' => 'Private App Token']),
            $this->entry('dynamics', 'Microsoft Dynamics', 'crm', false, ['tenant_id' => 'Tenant ID', 'client_id' => 'Client ID', 'client_secret' => 'Client Secret']),
            $this->entry('pipedrive', 'Pipedrive', 'crm', false, ['api_token' => 'API Token']),
            $this->entry('zoho_crm', 'Zoho CRM', 'crm', false, ['client_id' => 'Client ID', 'client_secret' => 'Client Secret']),

            $this->entry('netsuite', 'NetSuite', 'erp', false, ['account_id' => 'Account ID', 'consumer_key' => 'Consumer Key', 'consumer_secret' => 'Consumer Secret']),
            $this->entry('quickbooks', 'QuickBooks', 'accounting', false, ['realm_id' => 'Realm ID', 'client_id' => 'Client ID', 'client_secret' => 'Client Secret']),
            $this->entry('xero', 'Xero', 'accounting', false, ['client_id' => 'Client ID', 'client_secret' => 'Client Secret']),

            $this->entry('stripe', 'Stripe', 'payments', false, ['secret_key' => 'Secret Key']),
            $this->entry('paypal', 'PayPal', 'payments', false, ['client_id' => 'Client ID', 'client_secret' => 'Client Secret']),

            $this->entry('snowflake', 'Snowflake', 'bi', false, ['account' => 'Account', 'user' => 'User', 'password' => 'Password']),
            $this->entry('powerbi', 'Power BI', 'bi', true, []),   // consumes the /api/v1/payouts feed
            $this->entry('tableau', 'Tableau', 'bi', true, []),
        ];
    }

    /**
     * @param  array<string, string>  $fields
     * @return array{key:string, label:string, category:string, live:bool, fields:array<string,string>}
     */
    private function entry(string $key, string $label, string $category, bool $live, array $fields): array
    {
        return compact('key', 'label', 'category', 'live', 'fields');
    }
}
