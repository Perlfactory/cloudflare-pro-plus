<?php

class CloudflarePro_Permissions
{
    const ACCESS = 'access_cloudflare_pro';

    public static function canAccess()
    {
        if (self::isAdmin()) {
            return true;
        }

        $client = self::currentClient();
        if (self::hasClientPermission($client, self::ACCESS, null)) {
            return true;
        }

        foreach (self::accessibleDomains() as $domain) {
            if (is_a($domain, 'pm_DomainAlias')) {
                continue;
            }

            if (!$domain->hasHosting()) {
                continue;
            }

            if (self::hasEffectivePermission($client, $domain, self::ACCESS)) {
                return true;
            }
        }

        return false;
    }

    public static function assertAccess()
    {
        if (!self::canAccess()) {
            throw new pm_Exception('Cloudflare Pro is not enabled for this account.');
        }
    }

    public static function isAdmin()
    {
        try {
            $client = self::currentClient();
            return $client && method_exists($client, 'isAdmin') && $client->isAdmin();
        } catch (Exception $e) {
            return false;
        }
    }

    public static function currentClient()
    {
        try {
            if (
                method_exists('pm_Session', 'isImpersonated') &&
                method_exists('pm_Session', 'getImpersonatedClientId') &&
                pm_Session::isImpersonated()
            ) {
                return pm_Client::getByClientId(pm_Session::getImpersonatedClientId());
            }
        } catch (Exception $e) {
        }

        return pm_Session::getClient();
    }

    public static function accessibleDomains()
    {
        $client = self::currentClient();
        if ($client->isAdmin()) {
            return self::appendAliases(pm_Domain::getAllDomains(false));
        }

        $domains = array();

        try {
            foreach (pm_Session::getCurrentDomains() as $domain) {
                if (self::hasDomainAccess($client, $domain)) {
                    $domains[$domain->getId()] = $domain;
                }
            }
        } catch (Exception $e) {
        }

        try {
            $domain = pm_Session::getCurrentDomain();
            if (self::hasDomainAccess($client, $domain)) {
                $domains[$domain->getId()] = $domain;
            }
        } catch (Exception $e) {
        }

        try {
            foreach (pm_Domain::getDomainsByClient($client, false) as $domain) {
                if (self::hasDomainAccess($client, $domain)) {
                    $domains[$domain->getId()] = $domain;
                }
            }
        } catch (Exception $e) {
        }

        try {
            foreach (pm_Domain::getAllDomains(false) as $domain) {
                if (self::hasDomainAccess($client, $domain)) {
                    $domains[$domain->getId()] = $domain;
                }
            }
        } catch (Exception $e) {
        }

        return self::appendAliases($domains);
    }

    private static function appendAliases(array $domains)
    {
        if (!class_exists('pm_DomainAlias') || !method_exists('pm_DomainAlias', 'getByDomain')) {
            return $domains;
        }

        $result = $domains;
        $primaryDomains = [];
        foreach ($domains as $domain) {
            if (is_a($domain, 'pm_DomainAlias')) {
                continue;
            }

            $primaryDomains[(string) $domain->getId()] = $domain;
        }

        foreach ($primaryDomains as $domain) {
            try {
                $aliases = pm_DomainAlias::getByDomain($domain);
            } catch (Throwable $e) {
                continue;
            }

            if ($aliases instanceof Traversable) {
                $aliases = iterator_to_array($aliases);
            } elseif (!is_array($aliases)) {
                $aliases = $aliases ? [$aliases] : [];
            }

            foreach ($aliases as $alias) {
                if (!$alias || !method_exists($alias, 'getId') || !method_exists($alias, 'getName')) {
                    continue;
                }

                $result['alias:' . (string) $alias->getId()] = $alias;
            }
        }

        return $result;
    }

    private static function hasDomainAccess($client, $domain)
    {
        if ($client->isAdmin()) {
            return true;
        }

        try {
            if ($client->hasAccessToDomain($domain->getId())) {
                return true;
            }
        } catch (Exception $e) {
        }

        try {
            $owner = $domain->getClient();
            if ((int) $owner->getId() === (int) $client->getId()) {
                return true;
            }

            if ($client->isReseller()) {
                try {
                    if ((int) $owner->getProperty('vendor_id') === (int) $client->getId()) {
                        return true;
                    }
                } catch (Exception $e) {
                }
            }
        } catch (Exception $e) {
        }

        return false;
    }

    private static function hasEffectivePermission($client, $domain, $permission)
    {
        return self::hasClientPermission($client, $permission, $domain) ||
            self::hasPlanPermission($domain, $permission);
    }

    private static function hasPlanPermission($domain, $permission)
    {
        try {
            return self::normalizeBoolean($domain->hasPermission($permission));
        } catch (Exception $e) {
            return false;
        }
    }

    private static function hasClientPermission($client, $permission, $domain)
    {
        try {
            return self::normalizeBoolean($client->hasPermission($permission, $domain));
        } catch (Exception $e) {
        }

        try {
            return self::normalizeBoolean($client->hasPermission($permission));
        } catch (Exception $e) {
            return false;
        }
    }

    private static function normalizeBoolean($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value !== 0;
        }

        return in_array(strtolower((string) $value), array('true', 'yes', 'on', 'enabled'), true);
    }
}
