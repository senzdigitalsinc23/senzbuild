<?php
declare(strict_types=1);

namespace App\Core;

/**
 * SAML 2.0 / LDAP / Active Directory SSO — identity provider integration.
 *
 * Provides SAML 2.0 authentication flow (SP-initiated).
 * For LDAP/AD, integrates with PHP's ldap_* functions.
 *
 * Usage:
 *   $saml = new SamlServiceProvider($config);
 *   $saml->authenticate($request);  // Initiates SAML flow
 */
class SamlServiceProvider
{
    protected array $config;
    protected string $entityId;
    protected string $acsUrl;
    protected ?string $nameId = null;
    protected ?array $attributes = null;

    /** @var array<string, string> Supported IdP entity IDs => metadata URLs */
    protected static array $idps = [];

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->entityId = $config['sp_entity_id'] ?? 'php-framework-sp';
        $this->acsUrl = $config['acs_url'] ?? '/saml/acs';

        // Register configured IdPs
        foreach (($config['idps'] ?? []) as $id => $meta) {
            self::$idps[$id] = $meta;
        }
    }

    /**
     * Register an Identity Provider.
     */
    public static function registerIdp(string $id, array $metadata): void
    {
        self::$idps[$id] = $metadata;
    }

    /**
     * List registered IdPs.
     */
    public static function listIdps(): array
    {
        return array_keys(self::$idps);
    }

    /**
     * Begin SAML authentication flow — returns the SAML auth request URL.
     *
     * @param string $idpId IdP identifier
     * @return string SAML auth request URL
     */
    public function initiate(string $idpId = 'default'): string
    {
        $idp = self::$idps[$idpId] ?? self::$idps[array_key_first(self::$idps)] ?? null;
        if ($idp === null) {
            throw new \InvalidArgumentException("IdP '{$idpId}' not found");
        }

        $ssoUrl = $idp['sso_url'] ?? '';
        if (empty($ssoUrl)) {
            throw new \InvalidArgumentException("IdP '{$idpId}' has no SSO URL");
        }

        // Build SAML auth request (simplified — in production use php-saml library)
        $requestId = bin2hex(random_bytes(16));
        $now = time();

        $xml = <<<SAML
<?xml version="1.0" encoding="UTF-8"?>
<samlp:AuthnRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
                    xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
                    ID="_{$requestId}"
                    Version="2.0"
                    IssueInstant="{$this->isoTime($now)}"
                    IssueInstant="{$this->isoTime($now)}"
                    Destination="{$ssoUrl}"
                    ProtocolBinding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST"
                    AssertionConsumerServiceURL="{$this->acsUrl}">
    <saml:Issuer>{$this->entityId}</saml:Issuer>
    <samlp:NameIDPolicy Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress"
                        AllowCreate="true"/>
</samlp:AuthnRequest>
SAML;

        // Store request state for ACS verification
        $_SESSION['saml_request_id'] = $requestId;
        $_SESSION['saml_entity_id'] = $this->entityId;

        // Return encoded request URL
        return $ssoUrl . '?SAMLRequest=' . urlencode(base64_encode($xml));
    }

    /**
     * Process SAML Assertion Response at ACS endpoint.
     *
     * @param array $postData $_POST data from IdP
     * @return array User attributes
     */
    public function processResponse(array $postData): array
    {
        $samlResponse = base64_decode($postData['SAMLResponse'] ?? '');
        if ($samlResponse === false) {
            throw new \InvalidArgumentException('Invalid SAML response');
        }

        // Parse attributes from SAML XML (simplified parsing)
        $attributes = $this->parseSamlAttributes($samlResponse);

        $this->nameId = $attributes['email'] ?? $attributes['nameid'] ?? null;
        $this->attributes = $attributes;

        return $attributes;
    }

    /**
     * Get the authenticated user's name ID.
     */
    public function getNameId(): ?string
    {
        return $this->nameId;
    }

    /**
     * Get authenticated user attributes.
     */
    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    /**
     * Check if user is authenticated via SAML.
     */
    public function isAuthenticated(): bool
    {
        return $this->nameId !== null;
    }

    // ── LDAP / Active Directory ──────────────────────────────────────────

    /**
     * Authenticate via LDAP/AD.
     *
     * @param string $username Username/email
     * @param string $password Plain text password
     * @param string $connection LDAP connection name
     * @return array User info or null on failure
     */
    public function ldapAuthenticate(string $username, string $password, string $connection = 'default'): ?array
    {
        $ldapConfig = $this->config['ldap'][$connection] ?? null;
        if ($ldapConfig === null) {
            return null;
        }

        if (!extension_loaded('ldap')) {
            throw new \RuntimeException('PHP LDAP extension is not loaded');
        }

        $ds = ldap_connect($ldapConfig['host'], (int)($ldapConfig['port'] ?? 389));
        if ($ds === false) {
            return null;
        }

        ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);

        // Bind with user credentials
        $bind = ldap_bind($ds, $ldapConfig['bind_dn'], $ldapConfig['bind_password']);
        if (!$bind) {
            return null;
        }

        // Search for user
        $filter = str_replace(':username', $username, $ldapConfig['user_filter'] ?? '(uid=:username)');
        $result = ldap_search($ds, $ldapConfig['base_dn'], $filter, [
            'cn', 'mail', 'uid', 'givenName', 'sn', 'memberOf',
        ]);

        if ($result === false) {
            return null;
        }

        $entries = ldap_get_entries($ds, $result);
        if ($entries['count'] === 0) {
            return null;
        }

        $user = $entries[0];

        // Verify password
        $userDn = str_replace(':username', $username, $ldapConfig['user_dn'] ?? "uid=:username,{$ldapConfig['base_dn']}");
        $userBind = ldap_bind($ds, $userDn, $password);
        ldap_close($ds);

        if (!$userBind) {
            return null;
        }

        return [
            'username' => $user['uid'][0] ?? $username,
            'email'    => $user['mail'][0] ?? null,
            'name'     => ($user['givenName'][0] ?? '') . ' ' . ($user['sn'][0] ?? ''),
            'groups'   => $user['memberOf'] ?? [],
        ];
    }

    // ── Internal ─────────────────────────────────────────────────────────

    protected function parseSamlAttributes(string $xml): array
    {
        $attrs = [];
        // Simple regex-based attribute extraction
        preg_match_all('/<saml:Attribute Name="([^"]+)">\s*<saml:AttributeValue>([^<]+)<\/saml:AttributeValue>/', $xml, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $attrs[$match[1]] = $match[2];
        }

        // Extract NameID
        preg_match('/<saml:NameID[^>]*>([^<]+)<\/saml:NameID>/', $xml, $nameId);
        if (!empty($nameId[1])) {
            $attrs['nameid'] = $nameId[1];
        }

        return $attrs;
    }

    protected function isoTime(int $timestamp): string
    {
        return gmdate('Y-m-d\TH:i:s\Z', $timestamp);
    }
}
