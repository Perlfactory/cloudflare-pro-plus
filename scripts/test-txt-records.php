<?php

declare(strict_types=1);

require_once __DIR__ . '/../plib/library/CloudflarePro/DomainSyncService.php';
require_once __DIR__ . '/../plib/library/CloudflarePro/PleskDnsService.php';

$service = (new ReflectionClass('CloudflarePro_DomainSyncService'))->newInstanceWithoutConstructor();
$method = new ReflectionMethod($service, 'cloudflareRecord');
$method->setAccessible(true);

$cases = [
    'unquoted content is quoted' => [
        'input' => 'v=spf1 include:_spf.example.com ~all',
        'expected' => '"v=spf1 include:_spf.example.com ~all"',
    ],
    'already quoted content is preserved' => [
        'input' => '"google-site-verification=abc123"',
        'expected' => '"google-site-verification=abc123"',
    ],
    'multiple character strings are preserved' => [
        'input' => '"part-one" "part-two"',
        'expected' => '"part-one" "part-two"',
    ],
];

foreach ($cases as $description => $case) {
    $payload = $method->invoke($service, [
        'type' => 'TXT',
        'name' => 'example.com',
        'content' => $case['input'],
    ]);

    if ($payload['content'] !== $case['expected']) {
        fwrite(STDERR, sprintf(
            "%s failed: expected %s, got %s\n",
            $description,
            var_export($case['expected'], true),
            var_export($payload['content'], true)
        ));
        exit(1);
    }
}

$normalize = new ReflectionMethod($service, 'normalizeCloudflareRecord');
$normalize->setAccessible(true);
$recordsMatch = new ReflectionMethod($service, 'recordsMatch');
$recordsMatch->setAccessible(true);

$longContent = str_repeat('a', 300);
$expectedLongContent = '"' . str_repeat('a', 255) . '" "' . str_repeat('a', 45) . '"';
$longPayload = $method->invoke($service, [
    'type' => 'TXT',
    'name' => 'selector.example.com',
    'content' => $longContent,
]);
if ($longPayload['content'] !== $expectedLongContent) {
    fwrite(STDERR, "long TXT content was not split into 255-byte strings.\n");
    exit(1);
}

$cloudflareRecord = $normalize->invoke($service, [
    'id' => 'txt-1',
    'type' => 'TXT',
    'name' => 'selector.example.com',
    'content' => $expectedLongContent,
    'ttl' => 1,
]);
if (!$recordsMatch->invoke($service, $longPayload, $cloudflareRecord)) {
    fwrite(STDERR, "Cloudflare's split TXT strings should match the local payload.\n");
    exit(1);
}

$unquotedCloudflareRecord = $normalize->invoke($service, [
    'id' => 'txt-1',
    'type' => 'TXT',
    'name' => 'selector.example.com',
    'content' => $longContent,
    'ttl' => 1,
]);
if ($recordsMatch->invoke($service, $longPayload, $unquotedCloudflareRecord)) {
    fwrite(STDERR, "An unquoted Cloudflare TXT value should trigger an update.\n");
    exit(1);
}

$plesk = (new ReflectionClass('CloudflarePro_PleskDnsService'))->newInstanceWithoutConstructor();
$pleskContent = new ReflectionMethod($plesk, 'content');
$pleskContent->setAccessible(true);
$importedTxt = $pleskContent->invoke($plesk, 'TXT', $expectedLongContent);
if ($importedTxt !== $longContent) {
    fwrite(STDERR, "quoted TXT character strings were not unquoted for Plesk.\n");
    exit(1);
}
if ($pleskContent->invoke($plesk, 'TXT', 'plain-value') !== 'plain-value') {
    fwrite(STDERR, "plain TXT content should remain unchanged for Plesk.\n");
    exit(1);
}

fwrite(STDOUT, "TXT record quoting tests passed.\n");
