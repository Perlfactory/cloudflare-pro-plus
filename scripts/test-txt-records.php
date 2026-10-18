<?php

declare(strict_types=1);

require_once __DIR__ . '/../plib/library/CloudflarePro/DomainSyncService.php';

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

fwrite(STDOUT, "TXT record quoting tests passed.\n");
