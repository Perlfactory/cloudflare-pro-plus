<?php

declare(strict_types=1);

if (!class_exists('pm_Domain')) {
    class pm_Domain
    {
        private $id;
        private $name;

        public function __construct($id, $name)
        {
            $this->id = $id;
            $this->name = $name;
        }

        public function getId()
        {
            return $this->id;
        }

        public static function getByDomainId($id)
        {
            foreach (self::domains() as $domain) {
                if ((int) $domain->getId() === (int) $id) {
                    return $domain;
                }
            }
            throw new RuntimeException('Domain not found.');
        }

        public static function getByName($name)
        {
            foreach (self::domains() as $domain) {
                if ($domain->name === strtolower((string) $name)) {
                    return $domain;
                }
            }
            throw new RuntimeException('Domain not found.');
        }

        private static function domains()
        {
            return [
                new self(20, 'mydom-9.de'),
                new self(22, 'sub.mydom-9.de'),
            ];
        }
    }
}

if (!class_exists('pm_DomainAlias')) {
    class pm_DomainAlias
    {
        public static function getByDomainAliasId($id)
        {
            if ((int) $id !== 1) {
                throw new RuntimeException('Alias not found.');
            }

            return new self();
        }

        public function getDomain()
        {
            return new pm_Domain(20, 'mydom-9.de');
        }

        public function getId()
        {
            return 1;
        }
    }
}

require_once __DIR__ . '/../plib/library/CloudflarePro/DomainSyncService.php';

$service = (new ReflectionClass('CloudflarePro_DomainSyncService'))->newInstanceWithoutConstructor();
$method = new ReflectionMethod($service, 'pleskUrlForRecord');
$method->setAccessible(true);

$cases = [
    'main domain' => [
        'link' => ['domain_id' => '20', 'domain_name' => 'mydom-9.de'],
        'record' => 'mydom-9.de',
        'expected' => '/admin/subscription/login/id/20?pageUrl=/smb/web/overview/id/20/type/domain',
    ],
    'subdomain' => [
        'link' => ['domain_id' => '20', 'domain_name' => 'mydom-9.de'],
        'record' => 'sub.mydom-9.de',
        'expected' => '/admin/subscription/login/id/20?pageUrl=/smb/web/overview/id/22/type/domain',
    ],
    'domain alias' => [
        'link' => ['domain_id' => 'alias:1', 'domain_name' => 'yourdom-9.de'],
        'record' => 'yourdom-9.de',
        'expected' => '/admin/subscription/login/id/20?pageUrl=/smb/web/overview/id/1/alias',
    ],
];

foreach ($cases as $description => $case) {
    $actual = $method->invoke($service, $case['link'], $case['record']);
    if ($actual !== $case['expected']) {
        fwrite(STDERR, sprintf(
            "%s failed: expected %s, got %s\n",
            $description,
            var_export($case['expected'], true),
            var_export($actual, true)
        ));
        exit(1);
    }
}

fwrite(STDOUT, "Plesk record link tests passed.\n");
