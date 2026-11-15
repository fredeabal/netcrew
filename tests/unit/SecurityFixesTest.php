<?php

namespace Tests\Unit;

use App\Controllers\DeviceController;
use App\Libraries\NebulaService;
use App\Models\NetworkModel;
use CodeIgniter\Config\Services;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use Config\Database;

final class SecurityFixesTest extends CIUnitTestCase
{
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();

        $encConfig      = config('Encryption');
        $encConfig->key = 'hex2bin:' . str_repeat('a1', 32);
        Services::resetSingle('encrypter');

        $this->db = Database::connect('tests');

        $tSettings = $this->db->prefixTable('settings');
        $tNetworks = $this->db->prefixTable('networks');
        $tDevices  = $this->db->prefixTable('devices');
        $tTokens   = $this->db->prefixTable('device_share_tokens');

        $this->db->query("CREATE TABLE IF NOT EXISTS {$tSettings} (id INTEGER PRIMARY KEY AUTOINCREMENT, class TEXT, key TEXT, value TEXT, type TEXT, context TEXT, created_at TEXT, updated_at TEXT)");
        $this->db->query("CREATE TABLE IF NOT EXISTS {$tNetworks} (id INTEGER PRIMARY KEY AUTOINCREMENT, owner_id INTEGER, name TEXT, cidr TEXT, interface TEXT, port INTEGER, dns TEXT, active INTEGER DEFAULT 1, nebula_ca_crt TEXT, nebula_ca_key TEXT, created_at TEXT, updated_at TEXT)");
        $this->db->query("CREATE TABLE IF NOT EXISTS {$tDevices} (id INTEGER PRIMARY KEY AUTOINCREMENT, network_id INTEGER, name TEXT, ip_address TEXT, public_key TEXT, nebula_crt TEXT, nebula_key TEXT, nebula_groups TEXT, device_type TEXT, routing_mode TEXT, active INTEGER DEFAULT 1, expires_at TEXT, last_seen TEXT, created_at TEXT, updated_at TEXT)");
        $this->db->query("CREATE TABLE IF NOT EXISTS {$tTokens} (id INTEGER PRIMARY KEY AUTOINCREMENT, device_id INTEGER, token TEXT, expires_at TEXT, used INTEGER DEFAULT 0, created_at TEXT)");

        $this->db->query("DELETE FROM {$tSettings}");
        $this->db->query("DELETE FROM {$tNetworks}");
        $this->db->query("DELETE FROM {$tDevices}");
        $this->db->query("DELETE FROM {$tTokens}");
    }

    public function testBuildClientConfigSanitizesYamlNewlinesAndQuotes(): void
    {
        service('settings')->set('Nebula.lighthouseHost', "vpn.example.com\"\nsshd:\n  enabled: true");
        service('settings')->set('Nebula.lighthousePort', 4242);

        $network = (object) [
            'name'          => "Corp\nsshd:\n  enabled: true",
            'cidr'          => '10.10.0.0/24',
            'nebula_ca_crt' => "-----BEGIN NEBULA CERTIFICATE-----\nCA\n-----END NEBULA CERTIFICATE-----",
        ];
        $device = (object) [
            'name'       => "Laptop\r\nlisten:\r\n  port: 9999",
            'ip_address' => '10.10.0.2',
        ];

        $yaml = NebulaService::buildClientConfig($network, $device, 'CRT', 'KEY');

        $this->assertStringNotContainsString("\nsshd:", $yaml);
        $this->assertStringNotContainsString("\nlisten:\n  port: 9999", $yaml);
        $this->assertStringContainsString('# Red: Corp sshd:   enabled: true (10.10.0.0/24)', $yaml);
        $this->assertStringContainsString('# Nodo: Laptop listen:   port: 9999 (10.10.0.2)', $yaml);
        $this->assertStringContainsString('"10.10.0.1": ["vpn.example.comsshd:  enabled: true:4242"]', $yaml);
    }

    public function testBuildLighthouseConfigSanitizesYamlNewlines(): void
    {
        $network = (object) [
            'name'          => "Mesh\nsshd:\n  enabled: true",
            'cidr'          => '10.20.0.0/24',
            'nebula_ca_crt' => "-----BEGIN NEBULA CERTIFICATE-----\nCA\n-----END NEBULA CERTIFICATE-----",
        ];

        $yaml = NebulaService::buildLighthouseConfig($network, 'LH_CRT', 'LH_KEY');

        $this->assertStringNotContainsString("\nsshd:", $yaml);
        $this->assertStringContainsString('# Red: Mesh sshd:   enabled: true (10.20.0.0/24)', $yaml);
    }

    public function testSignDeviceCertRejectsExpiredTimestamp(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('expiración');

        NebulaService::signDeviceCert(
            'CA_CRT',
            'CA_KEY',
            'expired-node',
            '10.10.0.5/24',
            'devices',
            date('Y-m-d H:i:s', time() - 3600)
        );
    }

    public function testNetworkModelUpdateActiveDoesNotDoubleEncryptCaKey(): void
    {
        $model      = new NetworkModel($this->db);
        $plainCaKey = "-----BEGIN NEBULA X25519 PRIVATE KEY-----\nTESTKEY123\n-----END NEBULA X25519 PRIVATE KEY-----";

        $networkId = $model->insert([
            'owner_id'      => 1,
            'name'          => 'TestNet',
            'cidr'          => '10.30.0.0/24',
            'interface'     => 'nebula0',
            'port'          => 4242,
            'dns'           => '1.1.1.1',
            'active'        => 1,
            'nebula_ca_crt' => 'TEST_CRT',
            'nebula_ca_key' => $plainCaKey,
        ]);

        $this->assertIsInt($networkId);

        $model->update($networkId, ['active' => 0]);
        $updated = $model->find($networkId);

        $this->assertSame(0, (int) $updated->active);
        $this->assertSame($plainCaKey, $model->decryptCaKey((string) $updated->nebula_ca_key));
    }

    public function testDownloadShareConsumesTokenOnceAndRejectsReplay(): void
    {
        $encrypter = Services::encrypter();
        $encCaKey  = base64_encode($encrypter->encrypt('CA_KEY'));
        $encDevKey = base64_encode($encrypter->encrypt('DEV_KEY'));

        $this->db->table('networks')->insert([
            'owner_id'      => 1,
            'name'          => 'ShareNet',
            'cidr'          => '10.40.0.0/24',
            'interface'     => 'nebula0',
            'port'          => 4242,
            'dns'           => '1.1.1.1',
            'active'        => 1,
            'nebula_ca_crt' => 'CA_CRT',
            'nebula_ca_key' => $encCaKey,
        ]);
        $netId = (int) $this->db->insertID();

        $this->db->table('devices')->insert([
            'network_id' => $netId,
            'name'       => 'ShareNode',
            'ip_address' => '10.40.0.2',
            'public_key' => 'fp1',
            'nebula_crt' => 'DEV_CRT',
            'nebula_key' => $encDevKey,
            'active'     => 1,
        ]);
        $devId = (int) $this->db->insertID();

        $token = str_repeat('a', 48);
        $this->db->table('device_share_tokens')->insert([
            'device_id'  => $devId,
            'token'      => $token,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'used'       => 1, // Already viewed once via connectShare()
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $appConfig  = new App();
        $request    = new IncomingRequest($appConfig, new URI('http://example.com/connect/' . $token . '/download?platform=yml'), null, new UserAgent());
        $response   = new Response($appConfig);
        $controller = new DeviceController();
        $controller->initController($request, $response, Services::logger());

        // First download succeeds and transitions used -> 2
        $first = $controller->downloadShare($token);
        $this->assertSame(200, $first->getStatusCode());
        $this->assertStringContainsString('DEV_KEY', (string) $first->getBody());

        $row = $this->db->table('device_share_tokens')->where('token', $token)->get()->getRow();
        $this->assertSame(2, (int) $row->used);

        // Second download must be rejected with 404
        $second = $controller->downloadShare($token);
        $this->assertSame(404, $second->getStatusCode());
    }

    public function testRevokeFingerprintPersistsValidHexAndRejectsInvalid(): void
    {
        $this->db->query('CREATE TABLE IF NOT EXISTS ' . $this->db->prefixTable('settings') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            class TEXT NOT NULL,
            key TEXT NOT NULL,
            value TEXT NULL,
            type TEXT NOT NULL DEFAULT "string",
            context TEXT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');

        $fp1 = str_repeat('a1b2c3d4', 8);
        $fp2 = strtoupper(str_repeat('deadbeef', 8));

        NebulaService::revokeFingerprint(42, $fp1);
        NebulaService::revokeFingerprint(42, $fp1); // duplicate
        NebulaService::revokeFingerprint(42, $fp2);
        NebulaService::revokeFingerprint(42, "bad-fp\ninjected: true");

        $list = NebulaService::getBlocklist(42);
        $this->assertSame([$fp1, strtolower($fp2)], $list);
    }

    public function testBuildLighthouseConfigRendersBlocklistSafely(): void
    {
        $network = (object) [
            'id'            => 7,
            'name'          => 'CorpNet',
            'cidr'          => '10.99.0.0/24',
            'nebula_ca_crt' => "-----BEGIN NEBULA CERTIFICATE-----\nCA\n-----END NEBULA CERTIFICATE-----",
        ];

        $validFp = str_repeat('0123456789abcdef', 4);
        $yaml = NebulaService::buildLighthouseConfig($network, 'CERT_PEM', 'KEY_PEM', [
            strtoupper($validFp),
            "invalid\nmalicious: 1",
        ]);

        $this->assertStringContainsString("  blocklist:\n    - \"{$validFp}\"", $yaml);
        $this->assertStringNotContainsString('malicious', $yaml);

        $yamlEmpty = NebulaService::buildLighthouseConfig($network, 'CERT_PEM', 'KEY_PEM', []);
        $this->assertStringNotContainsString('blocklist:', $yamlEmpty);
    }

    public function testCspAndSriConfiguration(): void
    {
        $appConfig = new App();
        $this->assertTrue($appConfig->CSPEnabled);

        $csp = new \Config\ContentSecurityPolicy();
        $this->assertSame('none', $csp->objectSrc);
        $this->assertSame('self', $csp->frameAncestors);
        $this->assertNotContains('unsafe-inline', (array) $csp->scriptSrc);
        $this->assertNotContains('unsafe-inline', (array) $csp->scriptSrcElem);

        $footer = file_get_contents(APPPATH . 'Views/template/footer.php');
        $this->assertIsString($footer);
        $this->assertStringContainsString(
            'integrity="sha384-D4fI2O1dD9gQnn73J775jfm7LFa+lp87psAf0aiqjF9EQjnhGwZwkGm+2bffcJSF" crossorigin="anonymous"',
            $footer
        );
    }
}
