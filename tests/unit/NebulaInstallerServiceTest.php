<?php

namespace Tests\Unit;

use App\Libraries\NebulaInstallerService;
use CodeIgniter\Test\CIUnitTestCase;

final class NebulaInstallerServiceTest extends CIUnitTestCase
{
    private const SAMPLE_YML = <<<YAML
pki:
  ca: test

tun:
  disabled: false
  drop_local_broadcast: false
  drop_multicast: false
  mtu: 1300
YAML;

    public function testWindowsBatInjectsTunDevAndServiceRecovery(): void
    {
        $bundle = NebulaInstallerService::buildForPlatform('windows', 'servidor', self::SAMPLE_YML);

        $this->assertSame('instalar-netcrew-servidor.bat', $bundle['filename']);
        $this->assertMatchesRegularExpression("/FromBase64String\\('([^']+)'\\)/", $bundle['body']);

        preg_match("/FromBase64String\\('([^']+)'\\)/", $bundle['body'], $m);
        $decodedYml = base64_decode($m[1], true);

        $this->assertIsString($decodedYml);
        $this->assertStringContainsString("tun:\n  dev: Nebula\n  disabled: false", $decodedYml);
        $this->assertStringContainsString('sc.exe failure Nebula reset= 86400 actions= restart/5000/restart/10000/restart/30000', $bundle['body']);
        $this->assertStringContainsString('NetCrew Nebula ICMPv4', $bundle['body']);
    }

    public function testWindowsPs1InjectsTunDevAndServiceRecovery(): void
    {
        $bundle = NebulaInstallerService::buildForPlatform('win-ps1', 'servidor', self::SAMPLE_YML);

        $this->assertSame('instalar-netcrew-servidor.ps1', $bundle['filename']);
        preg_match("/FromBase64String\\('([^']+)'\\)/", $bundle['body'], $m);
        $decodedYml = base64_decode($m[1], true);

        $this->assertIsString($decodedYml);
        $this->assertStringContainsString("tun:\n  dev: Nebula\n  disabled: false", $decodedYml);
        $this->assertStringContainsString('sc.exe failure Nebula reset= 86400 actions= restart/5000/restart/10000/restart/30000', $bundle['body']);
        $this->assertStringContainsString('NetCrew Nebula ICMPv4', $bundle['body']);
    }

    public function testWindowsDoesNotDuplicateExistingTunDev(): void
    {
        $ymlWithDev = str_replace("tun:\n", "tun:\n  dev: CustomTun\n", self::SAMPLE_YML);
        $bundle     = NebulaInstallerService::buildForPlatform('windows', 'servidor', $ymlWithDev);

        preg_match("/FromBase64String\\('([^']+)'\\)/", $bundle['body'], $m);
        $decodedYml = base64_decode($m[1], true);

        $this->assertSame(1, substr_count((string) $decodedYml, 'dev:'));
        $this->assertStringContainsString('dev: CustomTun', (string) $decodedYml);
    }

    public function testNonWindowsPlatformsKeepTunWithoutWindowsDev(): void
    {
        $ymlBundle = NebulaInstallerService::buildForPlatform('yml', 'iphone', self::SAMPLE_YML);
        $this->assertStringNotContainsString('dev: Nebula', $ymlBundle['body']);

        $macBundle = NebulaInstallerService::buildForPlatform('mac-sh', 'macbook', self::SAMPLE_YML);
        $this->assertStringNotContainsString('dev: Nebula', $macBundle['body']);
    }
}
