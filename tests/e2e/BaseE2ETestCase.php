<?php

declare(strict_types=1);

namespace Tests\E2E;

use App\Libraries\NebulaService;
use App\Models\CompanyModel;
use App\Models\DeviceModel;
use App\Models\NetworkModel;
use CodeIgniter\Config\Factories;
use CodeIgniter\Database\Config as DatabaseConfig;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Config\AuthGroups;
use Config\Database;
use Config\Services;
use ReflectionProperty;

abstract class BaseE2ETestCase extends CIUnitTestCase
{
    use DatabaseTestTrait {
        migrateDatabase as protected traitMigrateDatabase;
    }
    use FeatureTestTrait;

    protected $DBGroup     = 'tests';
    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = false;
    protected $namespace   = null;

    protected string $csrfSecret = '0123456789abcdef0123456789abcdef';

    /**
     * @var array<string, mixed>
     */
    protected array $activeSession = [];

    protected string $scratchDir = '/Users/esweb/.gemini/antigravity/scratch/teamwork_tmp/752b80f9-a8d4-4790-b50a-1a3395236f8a';

    protected static bool $overrodePrefix = false;

    protected function resetFactories()
    {
        parent::resetFactories();
        $this->ensureUnprefixedTestsDatabase(true);
    }

    protected function setUp(): void
    {
        $this->ensureUnprefixedTestsDatabase(false);

        parent::setUp();

        Services::resetSingle('auth');
        Services::resetSingle('security');
        Services::resetSingle('settings');
        Services::resetSingle('throttler');

        $this->seedBaselinePermissions();
        $this->seedBaselineNebulaSettings();
        $this->refreshAuthGroupsConfig();

        $this->activeSession = [
            'csrf_test_name' => $this->csrfSecret,
        ];
        $_SESSION = $this->activeSession;
        $this->withSession($this->activeSession);
        $this->withHeaders([]);
    }

    protected function migrateDatabase()
    {
        $this->db->query('PRAGMA legacy_alter_table = ON');
        try {
            $this->traitMigrateDatabase();
        } finally {
            $this->db->query('PRAGMA legacy_alter_table = OFF');
            $this->db->resetDataCache();
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->scratchDir)) {
            foreach (glob($this->scratchDir . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($this->scratchDir);
        }

        Services::resetSingle('auth');
        Services::resetSingle('security');
        Services::resetSingle('settings');
        Services::resetSingle('throttler');

        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$overrodePrefix) {
            config(Database::class)->tests['DBPrefix'] = 'db_';
            self::resetCachedTestsConnectionIfPrefixMismatch('db_');
            self::$overrodePrefix = false;
        }

        parent::tearDownAfterClass();
    }

    /**
     * Overrides Database::$tests['DBPrefix'] = '' so raw-table migrations
     * (e.g., AddIntegrityIndexes) execute cleanly on SQLite3 :memory:.
     */
    protected function ensureUnprefixedTestsDatabase(bool $forceFreshConnection = false): void
    {
        $dbConfig = config(Database::class);
        if (($dbConfig->tests['DBPrefix'] ?? '') !== '') {
            $dbConfig->tests['DBPrefix'] = '';
            self::$overrodePrefix        = true;
        }

        if ($forceFreshConnection) {
            self::resetCachedTestsConnection();
        } else {
            self::resetCachedTestsConnectionIfPrefixMismatch('');
        }
    }

    protected static function resetCachedTestsConnection(): void
    {
        $prop      = new ReflectionProperty(DatabaseConfig::class, 'instances');
        $instances = $prop->getValue();

        if (isset($instances['tests'])) {
            try {
                $instances['tests']->close();
            } catch (\Throwable $e) {
                // Ignore close failures on already-closed connections
            }
            unset($instances['tests']);
            $prop->setValue(null, $instances);
        }
    }

    protected static function resetCachedTestsConnectionIfPrefixMismatch(string $expectedPrefix): void
    {
        $prop      = new ReflectionProperty(DatabaseConfig::class, 'instances');
        $instances = $prop->getValue();

        if (isset($instances['tests']) && $instances['tests']->getPrefix() !== $expectedPrefix) {
            try {
                $instances['tests']->close();
            } catch (\Throwable $e) {
                // Ignore close failures on already-closed connections
            }
            unset($instances['tests']);
            $prop->setValue(null, $instances);
        }
    }

    protected function seedBaselinePermissions(): void
    {
        $now = date('Y-m-d H:i:s');
        $map = [
            'superadmin' => [
                'admin.companies',
                'admin.users',
                'admin.roles',
                'admin.settings',
                'networks.view',
                'networks.create',
                'networks.edit',
                'networks.delete',
                'devices.view',
                'devices.create',
                'devices.edit',
                'devices.delete',
            ],
            'supervisor' => [
                'admin.users',
                'networks.view',
                'networks.create',
                'networks.edit',
                'networks.delete',
                'devices.view',
                'devices.create',
                'devices.edit',
                'devices.delete',
            ],
            'user' => [
                'networks.view',
                'networks.create',
                'networks.edit',
                'networks.delete',
                'devices.view',
                'devices.create',
                'devices.edit',
                'devices.delete',
            ],
        ];

        $table = $this->db->table('auth_group_permissions');
        foreach ($map as $group => $permissions) {
            foreach ($permissions as $permission) {
                $exists = $this->db->table('auth_group_permissions')
                    ->where('group', $group)
                    ->where('permission', $permission)
                    ->countAllResults();
                if ($exists === 0) {
                    $table->insert([
                        'group'      => $group,
                        'permission' => $permission,
                        'created_at' => $now,
                    ]);
                }
            }
        }
    }

    protected function seedBaselineNebulaSettings(): void
    {
        $settings = service('settings');
        $settings->set('Nebula.lighthouseHost', 'vpn.netcrew.test');
        $settings->set('Nebula.lighthousePort', 4242);
        $settings->set('Nebula.relayEnabled', 1);
        $settings->set('Nebula.cipher', 'aes');
    }

    protected function refreshAuthGroupsConfig(): void
    {
        $fresh = new AuthGroups();
        Factories::injectMock('config', AuthGroups::class, $fresh);
        Factories::injectMock('config', 'AuthGroups', $fresh);
    }

    protected function getPrincipalCompany(): object
    {
        $company = $this->db->table('companies')->where('slug', 'principal')->get()->getRow();
        if (! $company) {
            return $this->createCompany([
                'name' => 'Principal',
                'slug' => 'principal',
            ]);
        }

        return $company;
    }

    protected function createCompany(array $overrides = []): object
    {
        static $counter = 0;
        $counter++;

        $name = $overrides['name'] ?? ('Company ' . $counter);
        $slug = $overrides['slug'] ?? CompanyModel::generateSlug((string) $name) . '-' . $counter;

        $data = array_merge([
            'name'         => $name,
            'slug'         => $slug,
            'active'       => 1,
            'max_networks' => 0,
            'max_devices'  => 0,
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ], $overrides);

        $this->db->table('companies')->insert($data);
        $id = (int) $this->db->insertID();

        return $this->db->table('companies')->where('id', $id)->get()->getRow();
    }

    protected function createUser(string $group, ?int $companyId, array $overrides = []): User
    {
        static $counter = 0;
        $counter++;

        $username = $overrides['username'] ?? ($group . '_user_' . $counter);
        $email    = $overrides['email'] ?? ($username . '@netcrew.test');
        $password = $overrides['password'] ?? 'Str0ng!Pass123';
        $phone    = $overrides['phone'] ?? '+34600000' . str_pad((string) ($counter % 1000), 3, '0', STR_PAD_LEFT);
        $active   = $overrides['active'] ?? 1;

        $provider = auth()->getProvider();
        $user     = new User([
            'username'   => $username,
            'email'      => $email,
            'phone'      => $phone,
            'password'   => $password,
            'company_id' => $companyId,
        ]);
        $user->active = (int) $active;

        $provider->save($user);
        $userId = (int) $provider->getInsertID();

        // Ensure explicit nullable company_id persistence
        $this->db->table('users')->where('id', $userId)->update(['company_id' => $companyId]);

        $saved = $provider->findById($userId);
        $saved->addGroup($group);

        if ((int) $active === 1) {
            $saved->activate();
        } else {
            $saved->ban('Desactivado en fixture');
        }

        return $provider->findById($userId);
    }

    protected function actingAsUser(User $user): self
    {
        $this->activeSession = [
            'user'           => ['id' => (int) $user->id],
            'csrf_test_name' => $this->csrfSecret,
        ];
        $_SESSION = $this->activeSession;

        Services::resetSingle('auth');
        Services::resetSingle('security');

        $this->withSession($this->activeSession);
        $this->withHeaders([]);

        return $this;
    }

    protected function actingAsGuest(): self
    {
        $this->activeSession = [
            'csrf_test_name' => $this->csrfSecret,
        ];
        $_SESSION = $this->activeSession;

        Services::resetSingle('auth');
        Services::resetSingle('security');

        $this->withSession($this->activeSession);
        $this->withHeaders([]);

        return $this;
    }

    protected function freshCsrfToken(): string
    {
        $_SESSION['csrf_test_name'] = $this->csrfSecret;
        Services::resetSingle('security');

        return csrf_hash();
    }

    protected function getWithActor(string $uri, array $params = [], array $headers = []): TestResponse
    {
        $_SESSION = $this->activeSession;
        Services::resetSingle('auth');
        Services::resetSingle('security');

        $this->withSession($this->activeSession);
        $this->withHeaders($headers);

        $response = $this->get($uri, $params);
        $this->withHeaders([]);

        return $response;
    }

    protected function postWithCsrf(string $uri, array $params = [], array $headers = []): TestResponse
    {
        if (! array_key_exists('csrf_test_name', $params)) {
            $params['csrf_test_name'] = $this->freshCsrfToken();
        }

        $_SESSION = $this->activeSession;
        Services::resetSingle('auth');
        Services::resetSingle('security');

        $this->withSession($this->activeSession);
        $this->withHeaders($headers);

        $response = $this->post($uri, $params);
        $this->withHeaders([]);

        return $response;
    }

    protected function postWithoutCsrf(string $uri, array $params = [], array $headers = []): TestResponse
    {
        unset($params['csrf_test_name']);

        $_SESSION = $this->activeSession;
        Services::resetSingle('auth');
        Services::resetSingle('security');

        $this->withSession($this->activeSession);
        $this->withHeaders($headers);

        $response = $this->post($uri, $params);
        $this->withHeaders([]);

        return $response;
    }

    protected function rawBody(TestResponse $response): string
    {
        return (string) $response->response()->getBody();
    }

    protected function assertAccessDenied(TestResponse $response, string ...$forbiddenSnippets): void
    {
        $status = $response->response()->getStatusCode();
        $body   = $this->rawBody($response);

        if ($status === 403 || $status === 401) {
            foreach ($forbiddenSnippets as $snippet) {
                $this->assertStringNotContainsString($snippet, $body);
            }
            return;
        }

        $this->assertTrue(
            $response->isRedirect(),
            "Expected 401/403 or redirect denial, got HTTP {$status}."
        );

        $location   = $response->getRedirectUrl();
        $parsedPath = parse_url($location, PHP_URL_PATH);
        $path       = ($parsedPath === null || $parsedPath === false || $parsedPath === '') ? '/' : (string) $parsedPath;

        $this->assertMatchesRegularExpression(
            '#^(/|/dashboard|/login|/networks(?:/\d+/devices)?|/users|/devices/show/\d+|index\.php/?)$#',
            str_replace('/index.php', '', $path) ?: '/',
            "Unexpected redirect target on access denial: {$location}"
        );

        foreach ($forbiddenSnippets as $snippet) {
            $this->assertStringNotContainsString($snippet, $body);
        }
    }

    protected function createNetworkDirect(int $ownerId, ?int $companyId, array $overrides = []): object
    {
        static $counter = 10;
        $counter++;

        $globalCa = NebulaService::getOrCreateGlobalCa();
        $model    = new NetworkModel();

        $data = array_merge([
            'company_id'    => $companyId,
            'owner_id'      => $ownerId,
            'name'          => 'Direct-Net-' . $counter,
            'cidr'          => "10.{$counter}.0.0/24",
            'dns'           => '1.1.1.1',
            'active'        => 1,
            'engine'        => 'nebula',
            'nebula_ca_crt' => $globalCa['crt'],
            'nebula_ca_key' => $globalCa['key'],
        ], $overrides);

        $model->insert($data);
        $id = (int) $model->getInsertID();

        return $model->find($id);
    }

    protected function createDeviceDirect(int $networkId, array $overrides = []): object
    {
        static $counter = 1;
        $counter++;

        $network = (new NetworkModel())->find($networkId);
        $octets  = explode('.', explode('/', (string) ($network->cidr ?? '10.10.0.0/24'))[0]);
        $host    = (($counter % 250) + 2);
        $ip      = "{$octets[0]}.{$octets[1]}.{$octets[2]}.{$host}";

        $rawKey   = "-----BEGIN NEBULA X25519 PRIVATE KEY-----\nTESTKEY{$counter}\n-----END NEBULA X25519 PRIVATE KEY-----\n";
        $encKey   = base64_encode(Services::encrypter()->encrypt($rawKey));
        $netGroup = NebulaService::getNetworkGroupName($networkId);

        $data = array_merge([
            'network_id'    => $networkId,
            'name'          => 'Direct-Dev-' . $counter,
            'ip_address'    => $ip,
            'public_key'    => hash('sha256', 'direct-fp-' . $networkId . '-' . $counter . '-' . microtime(true)),
            'private_key'   => 'nebula',
            'nebula_crt'    => "-----BEGIN NEBULA CERTIFICATE-----\nTESTCERT{$counter}\n-----END NEBULA CERTIFICATE-----\n",
            'nebula_key'    => $encKey,
            'nebula_groups' => $netGroup,
            'active'        => 1,
            'device_type'   => 'pc',
            'routing_mode'  => 'split',
            'expires_at'    => null,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ], $overrides);

        // Intentionally omit 'os' column (absent in SQLite migrations)
        unset($data['os']);

        $this->db->table('devices')->insert($data);
        $id = (int) $this->db->insertID();

        return (new DeviceModel())->find($id);
    }

    /**
     * Runs `/opt/homebrew/bin/nebula-cert print -json` on a PEM certificate string.
     *
     * @return array<string, mixed>
     */
    protected function inspectNebulaCertJson(string $pemCert): array
    {
        $bin = NebulaService::findLocalBinary();
        $this->assertNotNull($bin, 'nebula-cert binary must be available for cryptographic assertions.');

        if (! is_dir($this->scratchDir)) {
            mkdir($this->scratchDir, 0700, true);
        }

        $certFile = $this->scratchDir . '/inspect_' . bin2hex(random_bytes(6)) . '.crt';
        file_put_contents($certFile, trim($pemCert) . "\n");

        try {
            $out  = [];
            $code = 1;
            exec(sprintf('%s print -json -path %s 2>&1', escapeshellarg($bin), escapeshellarg($certFile)), $out, $code);
            $raw = implode("\n", $out);
            $this->assertSame(0, $code, 'nebula-cert print -json failed: ' . $raw);

            $decoded = json_decode($raw, true);
            $this->assertIsArray($decoded, 'nebula-cert print -json output must be valid JSON: ' . $raw);

            return $decoded[0] ?? $decoded;
        } finally {
            @unlink($certFile);
        }
    }

    /**
     * @param array<string, mixed> $certDetails
     */
    protected function getCertFirstIp(array $certDetails): string
    {
        return (string) ($certDetails['details']['networks'][0] ?? $certDetails['details']['ips'][0] ?? '');
    }

    protected function extractFirstPemFromYaml(string $yaml, string $key): string
    {
        $pattern = '/^  ' . preg_quote($key, '/') . ': \|\R((?:    .*\R?)+)/m';
        $this->assertSame(1, preg_match($pattern, $yaml, $matches), "Missing PEM block for pki.{$key} in YAML.");

        $lines = preg_split("/\r\n|\n|\r/", rtrim($matches[1]));
        $clean = array_map(static fn (string $line): string => preg_replace('/^    /', '', $line) ?? $line, $lines);

        return implode("\n", $clean) . "\n";
    }

    /**
     * @param list<string> $requiredFragments
     */
    protected function assertValidNebulaYaml(string $yaml, array $requiredFragments = []): void
    {
        $this->assertNotSame('', trim($yaml));
        $this->assertStringNotContainsString('Undefined variable', $yaml);
        $this->assertStringNotContainsString('Warning:', $yaml);
        $this->assertStringNotContainsString('Fatal error:', $yaml);
        $this->assertStringNotContainsString('<html', strtolower($yaml));
        $this->assertStringContainsString('pki:', $yaml);
        $this->assertStringContainsString('lighthouse:', $yaml);
        $this->assertStringContainsString('firewall:', $yaml);

        foreach ($requiredFragments as $fragment) {
            $this->assertStringContainsString($fragment, $yaml);
        }
    }

    protected function extractYamlInboundBlock(string $yaml): string
    {
        if (preg_match('/^  inbound:\R((?:    .*\R?)+)/m', $yaml, $m) !== 1) {
            return '';
        }

        return $m[1];
    }
}
