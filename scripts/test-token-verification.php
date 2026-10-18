<?php

require_once __DIR__ . '/../plib/library/CloudflarePro/DomainSyncService.php';

class CloudflareProTestDomains
{
    public function find($id)
    {
        return [
            'id' => 1,
            'token_id' => 1,
            'domain_id' => 20,
            'domain_name' => 'example.com',
            'zone_name' => 'example.com',
            'zone_id' => 'zone-1',
            'auto_sync' => 1,
        ];
    }

    public function markSynced($id, $count)
    {
    }

    public function markError($id, $message)
    {
    }
}

class CloudflareProTestTokens
{
    public function secret($id)
    {
        return 'test-token-01234567890123456789';
    }
}

class CloudflareProTestSettings
{
    public function all()
    {
        return [
            'enable_autosync' => 1,
            'validate_token_before_sync' => 1,
            'create_www_for_subdomains' => 0,
        ];
    }
}

class CloudflareProTestPlesk
{
    public $records = [];

    public function recordsForDomainName($name)
    {
        return $this->records;
    }

    public function recordsForDomainId($id)
    {
        return $this->records;
    }
}

class CloudflareProTestClient
{
    public $verifyCalls = 0;

    public function verifyToken($token)
    {
        $this->verifyCalls++;
        return ['success' => true];
    }

    public function listDnsRecords($token, $zoneId)
    {
        return [];
    }

    public function createDnsRecord($token, $zoneId, array $record)
    {
        return ['success' => true];
    }

    public function updateDnsRecord($token, $zoneId, $recordId, array $record)
    {
        return ['success' => true];
    }
}

$service = (new ReflectionClass('CloudflarePro_DomainSyncService'))->newInstanceWithoutConstructor();
$properties = [
    'domains' => new CloudflareProTestDomains(),
    'tokens' => new CloudflareProTestTokens(),
    'settings' => new CloudflareProTestSettings(),
    'plesk' => new CloudflareProTestPlesk(),
    'cloudflare' => new CloudflareProTestClient(),
];
foreach ($properties as $name => $value) {
    $property = new ReflectionProperty($service, $name);
    $property->setAccessible(true);
    $property->setValue($service, $value);
}

$plesk = $properties['plesk'];
$cloudflare = $properties['cloudflare'];

$result = $service->syncLinkIncremental(1, 'example.com', 'example.com', true);
if (empty($result['empty_source']) || $cloudflare->verifyCalls !== 0) {
    fwrite(STDERR, "empty autosync source must not verify the token.\n");
    exit(1);
}

$plesk->records = [[
    'type' => 'A',
    'name' => 'example.com',
    'ttl' => 300,
    'value' => '192.0.2.10',
]];
$service->syncLinkIncremental(1, 'example.com', 'example.com', true);
if ($cloudflare->verifyCalls !== 1) {
    fwrite(STDERR, "a non-empty autosync must verify the token once.\n");
    exit(1);
}

$service->syncLinkIncremental(1, 'example.com', 'example.com', false);
if ($cloudflare->verifyCalls !== 1) {
    fwrite(STDERR, "an autosync retry must not verify the token again.\n");
    exit(1);
}

fwrite(STDOUT, "Token verification flow tests passed.\n");
