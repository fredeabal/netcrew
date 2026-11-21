<?php

declare(strict_types=1);

namespace Tests\E2E;

use App\Libraries\NebulaService;
use App\Models\AuditLogModel;
use App\Models\DeviceModel;
use App\Models\NetworkModel;

require_once __DIR__ . '/BaseE2ETestCase.php';

class Tier4RealWorldScenariosTest extends BaseE2ETestCase
{
    public function testT401DualTenantEnterpriseOnboardingAndStrictIsolation(): void
    {
        $superadmin = $this->createUser('superadmin', null);

        // 1. Create Alpha Logistics & Beta Health
        $this->actingAsUser($superadmin);
        $this->postWithCsrf('companies/store', [
            'name'         => 'Alpha Logistics',
            'slug'         => 'alpha-logistics',
            'max_networks' => '2',
            'max_devices'  => '4',
            'active'       => '1',
        ])->assertRedirectTo('/companies');

        $this->postWithCsrf('companies/store', [
            'name'         => 'Beta Health',
            'slug'         => 'beta-health',
            'max_networks' => '1',
            'max_devices'  => '2',
            'active'       => '1',
        ])->assertRedirectTo('/companies');

        $alpha = $this->db->table('companies')->where('slug', 'alpha-logistics')->get()->getRow();
        $beta  = $this->db->table('companies')->where('slug', 'beta-health')->get()->getRow();
        $this->assertNotNull($alpha);
        $this->assertNotNull($beta);

        // 2. Create supervisorAlpha & supervisorBeta
        $this->postWithCsrf('users/store', [
            'username'     => 'supervisorAlpha',
            'email'        => 'sup.alpha@alpha.test',
            'phone'        => '+34600000101',
            'group'        => 'supervisor',
            'company_id'   => (string) $alpha->id,
            'password'     => 'Str0ng!Pass1',
            'pass_confirm' => 'Str0ng!Pass1',
            'active'       => '1',
        ])->assertRedirectTo('/users');

        $this->postWithCsrf('users/store', [
            'username'     => 'supervisorBeta',
            'email'        => 'sup.beta@beta.test',
            'phone'        => '+34600000201',
            'group'        => 'supervisor',
            'company_id'   => (string) $beta->id,
            'password'     => 'Str0ng!Pass1',
            'pass_confirm' => 'Str0ng!Pass1',
            'active'       => '1',
        ])->assertRedirectTo('/users');

        $supAlpha = auth()->getProvider()->findByCredentials(['email' => 'sup.alpha@alpha.test']);
        $supBeta  = auth()->getProvider()->findByCredentials(['email' => 'sup.beta@beta.test']);

        // 3. Alpha onboarding: engAlpha, Alpha-HQ, Alpha-Branch, 4 devices
        $this->actingAsUser($supAlpha);
        $this->postWithCsrf('users/store', [
            'username'     => 'engAlpha',
            'email'        => 'eng.alpha@alpha.test',
            'phone'        => '+34600000102',
            'group'        => 'user',
            'password'     => 'Str0ng!Pass1',
            'pass_confirm' => 'Str0ng!Pass1',
            'active'       => '1',
        ])->assertRedirectTo('/users');
        $engAlpha = auth()->getProvider()->findByCredentials(['email' => 'eng.alpha@alpha.test']);

        $cidr1 = (new NetworkModel())->suggestNextCidr();
        $this->postWithCsrf('networks/store', [
            'name'     => 'Alpha-HQ',
            'cidr'     => $cidr1,
            'dns'      => '1.1.1.1',
            'owner_id' => (string) $engAlpha->id,
        ])->assertRedirectTo('/networks');
        $alphaHq = (new NetworkModel())->where('name', 'Alpha-HQ')->first();

        $cidr2 = (new NetworkModel())->suggestNextCidr();
        $this->assertNotSame($cidr1, $cidr2);
        $this->postWithCsrf('networks/store', [
            'name'     => 'Alpha-Branch',
            'cidr'     => $cidr2,
            'dns'      => '1.1.1.1',
            'owner_id' => (string) $supAlpha->id,
        ])->assertRedirectTo('/networks');
        $alphaBranch = (new NetworkModel())->where('name', 'Alpha-Branch')->first();

        // Quota max_networks=2 blocks 3rd
        $this->postWithCsrf('networks/store', [
            'name'     => 'Alpha-Overflow',
            'cidr'     => (new NetworkModel())->suggestNextCidr(),
            'dns'      => '1.1.1.1',
            'owner_id' => (string) $supAlpha->id,
        ]);
        $this->assertNull((new NetworkModel())->where('name', 'Alpha-Overflow')->first());

        foreach ([$alphaHq, $alphaBranch] as $net) {
            for ($i = 1; $i <= 2; $i++) {
                $this->postWithCsrf('devices/store', [
                    'network_id'  => (string) $net->id,
                    'name'        => "{$net->name}-Dev-{$i}",
                    'device_type' => 'pc',
                    'expiration'  => 'never',
                ])->assertRedirectTo("/networks/{$net->id}/devices");
            }
        }
        // Quota max_devices=4 blocks 5th
        $this->postWithCsrf('devices/store', [
            'network_id'  => (string) $alphaHq->id,
            'name'        => 'Alpha-Dev-5',
            'device_type' => 'pc',
            'expiration'  => 'never',
        ]);
        $this->assertNull((new DeviceModel())->where('name', 'Alpha-Dev-5')->first());

        // 4. Beta onboarding: Beta-Core + 2 devices
        $this->actingAsUser($supBeta);
        $cidr3 = (new NetworkModel())->suggestNextCidr();
        $this->postWithCsrf('networks/store', [
            'name'     => 'Beta-Core',
            'cidr'     => $cidr3,
            'dns'      => '8.8.8.8',
            'owner_id' => (string) $supBeta->id,
        ])->assertRedirectTo('/networks');
        $betaCore = (new NetworkModel())->where('name', 'Beta-Core')->first();

        for ($i = 1; $i <= 2; $i++) {
            $this->postWithCsrf('devices/store', [
                'network_id'  => (string) $betaCore->id,
                'name'        => "Beta-Dev-{$i}",
                'device_type' => 'server',
                'expiration'  => 'never',
            ])->assertRedirectTo("/networks/{$betaCore->id}/devices");
        }
        $betaDev1 = (new DeviceModel())->where('name', 'Beta-Dev-1')->first();

        // 5. Cross-tenant attack checks by supervisor_alpha
        $this->actingAsUser($supAlpha);
        $this->assertAccessDenied($this->getWithActor("networks/edit/{$betaCore->id}"), 'Beta-Core');
        $this->assertAccessDenied($this->postWithCsrf("networks/delete/{$betaCore->id}"));
        $this->assertAccessDenied($this->getWithActor("networks/{$betaCore->id}/devices"), 'Beta-Dev-1');
        $this->assertAccessDenied($this->getWithActor("devices/show/{$betaDev1->id}"), 'Beta-Dev-1');
        $this->assertSame(403, $this->getWithActor("devices/details-json/{$betaDev1->id}")->response()->getStatusCode());
        $this->assertAccessDenied($this->getWithActor("users/edit/{$supBeta->id}"), 'supervisorBeta');
        $this->assertAccessDenied($this->getWithActor('settings'), 'Configuración SMTP');
        $this->assertAccessDenied($this->getWithActor('roles'), 'Gestión de Roles');
        $this->assertAccessDenied($this->getWithActor('companies'), 'Beta Health');

        // 6. Standard user engAlpha scoping
        $this->actingAsUser($engAlpha);
        $engHqResp = $this->getWithActor("networks/{$alphaHq->id}/devices");
        $engHqResp->assertStatus(200);
        $engHqResp->assertSee('Alpha-HQ-Dev-1');
        $this->assertAccessDenied($this->getWithActor("networks/{$alphaBranch->id}/devices"), 'Alpha-Branch-Dev-1');
        $this->assertAccessDenied($this->getWithActor('users'), 'supervisorAlpha');
        $this->assertAccessDenied($this->getWithActor('audit-logs'), 'Registro de Auditoría');

        // Global CA shared + distinct /24 + distinct net_<id>
        $this->assertSame(trim((string) $alphaHq->nebula_ca_crt), trim((string) $betaCore->nebula_ca_crt));
        $this->assertNotSame($alphaHq->cidr, $alphaBranch->cidr);
        $this->assertNotSame($alphaHq->cidr, $betaCore->cidr);
    }

    public function testT402NebulaGlobalCaSlash8KeyRotationAndLighthouseVerification(): void
    {
        $superadmin = $this->createUser('superadmin', null);
        $co1        = $this->createCompany(['name' => 'Nebula Co 1']);
        $co2        = $this->createCompany(['name' => 'Nebula Co 2']);
        $sup1       = $this->createUser('supervisor', (int) $co1->id);
        $sup2       = $this->createUser('supervisor', (int) $co2->id);

        // 1. Superadmin updates Nebula global settings
        $this->actingAsUser($superadmin)->postWithCsrf('settings/nebula/update', [
            'nebula_lighthouse_host' => 'vpn.netcrew.test',
            'nebula_lighthouse_port' => '4242',
            'nebula_cipher'          => 'aes',
            'nebula_relay_enabled'   => '1',
        ])->assertRedirectTo('/settings?tab=nebula');

        // 2. Two companies create Net-1 & Net-2 with 2 devices each
        $this->actingAsUser($sup1)->postWithCsrf('networks/store', [
            'name' => 'Net-1', 'cidr' => '10.60.0.0', 'dns' => '1.1.1.1', 'owner_id' => (string) $sup1->id,
        ])->assertRedirectTo('/networks');
        $net1 = (new NetworkModel())->where('name', 'Net-1')->first();

        for ($i = 1; $i <= 2; $i++) {
            $this->actingAsUser($sup1)->postWithCsrf('devices/store', [
                'network_id' => (string) $net1->id, 'name' => "N1-D{$i}", 'device_type' => 'pc', 'expiration' => 'never',
            ])->assertRedirectTo("/networks/{$net1->id}/devices");
        }

        $this->actingAsUser($sup2)->postWithCsrf('networks/store', [
            'name' => 'Net-2', 'cidr' => '10.61.0.0', 'dns' => '1.1.1.1', 'owner_id' => (string) $sup2->id,
        ])->assertRedirectTo('/networks');
        $net2 = (new NetworkModel())->where('name', 'Net-2')->first();

        for ($i = 1; $i <= 2; $i++) {
            $this->actingAsUser($sup2)->postWithCsrf('devices/store', [
                'network_id' => (string) $net2->id, 'name' => "N2-D{$i}", 'device_type' => 'pc', 'expiration' => 'never',
            ])->assertRedirectTo("/networks/{$net2->id}/devices");
        }

        $n1d1 = (new DeviceModel())->where('name', 'N1-D1')->first();
        $n2d1 = (new DeviceModel())->where('name', 'N2-D1')->first();

        // 3. Inspect client YAMLs, certs, and Lighthouse YAML
        $yaml1 = $this->rawBody($this->actingAsUser($sup1)->getWithActor("devices/download/{$n1d1->id}?platform=yml"));
        $yaml2 = $this->rawBody($this->actingAsUser($sup2)->getWithActor("devices/download/{$n2d1->id}?platform=yml"));
        $lh1   = $this->rawBody($this->actingAsUser($sup1)->getWithActor("networks/{$net1->id}/lighthouse-config"));

        $this->assertValidNebulaYaml($lh1, ['am_lighthouse: true', 'inbound:']);
        $this->assertSame(
            trim($this->extractFirstPemFromYaml($yaml1, 'ca')),
            trim($this->extractFirstPemFromYaml($yaml2, 'ca'))
        );

        $cert1 = $this->inspectNebulaCertJson($this->extractFirstPemFromYaml($yaml1, 'cert'));
        $cert2 = $this->inspectNebulaCertJson($this->extractFirstPemFromYaml($yaml2, 'cert'));
        $this->assertStringEndsWith('/8', $this->getCertFirstIp($cert1));
        $this->assertStringEndsWith('/8', $this->getCertFirstIp($cert2));
        $this->assertContains("net_{$net1->id}", $cert1['details']['groups'] ?? []);
        $this->assertContains("net_{$net2->id}", $cert2['details']['groups'] ?? []);

        $in1 = $this->extractYamlInboundBlock($yaml1);
        $this->assertStringContainsString("group: net_{$net1->id}", $in1);
        $this->assertStringContainsString('group: lighthouse', $in1);
        $this->assertStringNotContainsString("net_{$net2->id}", $in1);

        // 4. Rotate keys on N1-D1, generate one-time share link & consume
        $oldCert = (string) $n1d1->nebula_crt;
        $this->actingAsUser($sup1)->postWithCsrf("devices/rotate-keys/{$n1d1->id}")->assertRedirectTo("devices/show/{$n1d1->id}");
        $rotated = (new DeviceModel())->find($n1d1->id);
        $this->assertNotSame($oldCert, (string) $rotated->nebula_crt);

        $shareJson = json_decode(
            $this->actingAsUser($sup1)->postWithCsrf("devices/generate-share-link/{$n1d1->id}")->getJSON(),
            true
        );
        $token = (string) ($shareJson['token'] ?? '');
        $this->assertNotSame('', $token);

        $this->actingAsGuest();
        $this->getWithActor("connect/{$token}")->assertStatus(200);
        $shareDl = $this->getWithActor("connect/{$token}/download?platform=yml");
        $shareDl->assertStatus(200);
        $this->assertSame(trim((string) $rotated->nebula_crt), trim($this->extractFirstPemFromYaml($this->rawBody($shareDl), 'cert')));

        // 5. Second fetch of share download rejected
        $this->assertSame(404, $this->getWithActor("connect/{$token}/download?platform=yml")->response()->getStatusCode());
    }

    public function testT403CompanySuspensionImmediateRevocationAndReactivationLifecycle(): void
    {
        $superadmin = $this->createUser('superadmin', null);
        $gamma      = $this->createCompany(['name' => 'Gamma Corp', 'slug' => 'gamma-corp', 'active' => 1]);
        $supGamma   = $this->createUser('supervisor', (int) $gamma->id, [
            'email' => 'sup@gamma.test', 'password' => 'Str0ng!Pass123',
        ]);
        $usrGamma   = $this->createUser('user', (int) $gamma->id, [
            'email' => 'usr@gamma.test', 'password' => 'Str0ng!Pass123',
        ]);
        $netGamma   = $this->createNetworkDirect((int) $usrGamma->id, (int) $gamma->id, ['name' => 'Gamma-Net', 'cidr' => '10.120.0.0/24']);
        $this->createDeviceDirect((int) $netGamma->id, ['name' => 'Gamma-Dev']);

        // 1. Active sessions work
        $this->actingAsUser($supGamma)->getWithActor('dashboard')->assertStatus(200);
        $this->actingAsUser($usrGamma)->getWithActor('networks')->assertStatus(200);

        // 2. Superadmin suspends Gamma Corp
        $this->actingAsUser($superadmin)->postWithCsrf("companies/toggle-active/{$gamma->id}")->assertRedirectTo('/companies');

        // 3. Immediate revocation across GET, POST, AJAX
        $this->actingAsUser($supGamma);
        $this->getWithActor('dashboard')->assertRedirectTo('/login');
        $this->assertFalse(auth()->loggedIn());

        $this->actingAsUser($supGamma);
        $postBlocked = $this->postWithCsrf('networks/store', ['name' => 'Should-Fail', 'cidr' => '10.121.0.0', 'dns' => '1.1.1.1', 'owner_id' => (string) $supGamma->id]);
        $postBlocked->assertRedirectTo('/login');
        $this->assertNull((new NetworkModel())->where('name', 'Should-Fail')->first());

        $this->actingAsUser($supGamma);
        $this->getWithActor('audit-logs')->assertRedirectTo('/login');

        $this->actingAsUser($usrGamma);
        $this->getWithActor('networks')->assertRedirectTo('/login');

        $this->actingAsUser($usrGamma);
        $ajaxResp = $this->getWithActor("devices/realtime-status/{$netGamma->id}", [], ['X-Requested-With' => 'XMLHttpRequest']);
        $this->assertTrue($ajaxResp->isRedirect() || in_array($ajaxResp->response()->getStatusCode(), [401, 403], true));
        $this->assertFalse(auth()->loggedIn());

        // 4. Superadmin (even with company_id = gamma->id) never blocked
        $saWithGamma = $this->createUser('superadmin', (int) $gamma->id, ['username' => 'sa_gamma_co']);
        $this->actingAsUser($saWithGamma);
        $this->getWithActor('companies')->assertStatus(200);
        $this->getWithActor('dashboard')->assertStatus(200);
        $this->getWithActor('settings')->assertStatus(200);

        // 5. Reactivate Gamma Corp -> users log in again & access intact resources
        $this->actingAsUser($superadmin)->postWithCsrf("companies/toggle-active/{$gamma->id}")->assertRedirectTo('/companies');

        $this->actingAsGuest()->postWithCsrf('login', ['email' => 'sup@gamma.test', 'password' => 'Str0ng!Pass123']);
        $this->activeSession = $_SESSION;
        $gammaDash = $this->getWithActor('dashboard');
        $gammaDash->assertStatus(200);
        $gammaDash->assertSee('Gamma-Net');

        $this->actingAsGuest()->postWithCsrf('login', ['email' => 'usr@gamma.test', 'password' => 'Str0ng!Pass123']);
        $this->activeSession = $_SESSION;
        $gammaDev = $this->getWithActor("networks/{$netGamma->id}/devices");
        $gammaDev->assertStatus(200);
        $gammaDev->assertSee('Gamma-Dev');
    }

    public function testT404MultiRoleAuditComplianceAndForensicSearchVerification(): void
    {
        $superadmin = $this->createUser('superadmin', null, ['username' => 'sa_forensic']);
        $coA        = $this->createCompany(['name' => 'Forensic A']);
        $coB        = $this->createCompany(['name' => 'Forensic B']);
        $supA       = $this->createUser('supervisor', (int) $coA->id, ['username' => 'sup_forensic_a']);
        $usrA       = $this->createUser('user', (int) $coA->id, ['username' => 'usr_forensic_a']);
        $supB       = $this->createUser('supervisor', (int) $coB->id, ['username' => 'sup_forensic_b']);

        // 1. Seed auditable events across A, B, and platform
        AuditLogModel::log('LOGIN_SUCCESS', 'Alpha Login Event', (int) $supA->id, $supA->username, (int) $coA->id);
        AuditLogModel::log('DEVICE_CREATE', 'Alpha DEVICE_ALPHA_NODE Created', (int) $supA->id, $supA->username, (int) $coA->id);
        AuditLogModel::log('DEVICE_ROTATE_KEYS', 'Alpha DEVICE_ROTATE_ALPHA', (int) $supA->id, $supA->username, (int) $coA->id);
        AuditLogModel::log('DEVICE_DELETE', 'Beta SECRET_BETA_MARKER Deleted', (int) $supB->id, $supB->username, (int) $coB->id);
        AuditLogModel::log('SETTINGS_NEBULA_UPDATE', 'Platform PLATFORM_MARKER Updated', (int) $superadmin->id, $superadmin->username, null);

        // 2. Supervisor A searches & inspects /audit-logs
        $this->actingAsUser($supA);
        $devSearch = $this->getWithActor('audit-logs?q=DEVICE');
        $devSearch->assertStatus(200);
        $devSearch->assertSee('Alpha DEVICE_ALPHA_NODE Created');
        $devSearch->assertSee('Alpha DEVICE_ROTATE_ALPHA');
        $devSearch->assertDontSee('Beta SECRET_BETA_MARKER Deleted');
        $devSearch->assertDontSee('Platform PLATFORM_MARKER Updated');

        $crossSearch = $this->getWithActor('audit-logs?q=SECRET_BETA_MARKER');
        $crossSearch->assertStatus(200);
        $crossSearch->assertDontSee('Beta SECRET_BETA_MARKER Deleted');

        // 3. Supervisor A denied /settings
        $this->assertAccessDenied($this->getWithActor('settings?tab=audit'), 'Platform PLATFORM_MARKER Updated');
        $this->assertAccessDenied($this->getWithActor('settings'), 'Configuración SMTP');

        // 4. User A denied /audit-logs
        $this->actingAsUser($usrA);
        $this->assertAccessDenied($this->getWithActor('audit-logs'), 'Alpha Login Event');

        // 5. Superadmin sees complete global audit trail
        $this->actingAsUser($superadmin);
        $saAudit = $this->rawBody($this->getWithActor('audit-logs'));
        $this->assertStringContainsString('Alpha DEVICE_ALPHA_NODE Created', $saAudit);
        $this->assertStringContainsString('Beta SECRET_BETA_MARKER Deleted', $saAudit);
        $this->assertStringContainsString('Platform PLATFORM_MARKER Updated', $saAudit);

        $saSettingsAudit = $this->rawBody($this->getWithActor('settings?tab=audit'));
        $this->assertStringContainsString('Alpha DEVICE_ALPHA_NODE Created', $saSettingsAudit);
        $this->assertStringContainsString('Beta SECRET_BETA_MARKER Deleted', $saSettingsAudit);
    }

    public function testT405CompanyDecommissioningAndReferentialDeleteProtectionLifecycle(): void
    {
        $superadmin = $this->createUser('superadmin', null);

        $this->actingAsUser($superadmin);
        $this->postWithCsrf('companies/store', [
            'name' => 'Delta Temp', 'slug' => 'delta-temp', 'max_networks' => '0', 'max_devices' => '0', 'active' => '1',
        ])->assertRedirectTo('/companies');
        $delta = $this->db->table('companies')->where('slug', 'delta-temp')->get()->getRow();

        $userDelta = $this->createUser('user', (int) $delta->id, ['username' => 'user_delta']);
        $netDelta  = $this->createNetworkDirect((int) $userDelta->id, (int) $delta->id, ['name' => 'net_delta', 'cidr' => '10.125.0.0/24']);
        $devDelta  = $this->createDeviceDirect((int) $netDelta->id, ['name' => 'dev_delta']);

        // Attempt 1: blocked (users + networks exist)
        $this->postWithCsrf("companies/delete/{$delta->id}");
        $this->assertNotEmpty(session('error'));
        $this->assertNotNull($this->db->table('companies')->where('id', $delta->id)->get()->getRow());

        // Delete network (cascades/removes device)
        $this->postWithCsrf("networks/delete/{$netDelta->id}")->assertRedirectTo('/networks');
        $this->assertNull((new DeviceModel())->find($devDelta->id));

        // Attempt 2: still blocked (user_delta exists)
        $this->postWithCsrf("companies/delete/{$delta->id}");
        $this->assertNotEmpty(session('error'));
        $this->assertNotNull($this->db->table('companies')->where('id', $delta->id)->get()->getRow());

        // Delete user_delta -> Attempt 3 succeeds
        $this->postWithCsrf("users/delete/{$userDelta->id}")->assertRedirectTo('/users');
        $this->postWithCsrf("companies/delete/{$delta->id}")->assertRedirectTo('/companies');
        $this->assertNull($this->db->table('companies')->where('id', $delta->id)->get()->getRow());

        $companiesHtml = $this->rawBody($this->getWithActor('companies'));
        $this->assertStringNotContainsString('Delta Temp', $companiesHtml);
        $this->getWithActor('dashboard')->assertStatus(200);
    }

    public function testT406FullUiNavigationModernizeLayoutAndXssCspFidelityAcrossAllRoles(): void
    {
        $superadmin = $this->createUser('superadmin', null);
        $xssCo      = $this->createCompany([
            'name' => 'XssCo <script>alert(1)</script> & "Quotes"',
            'slug' => 'xss-fidelity-co',
        ]);
        $supA = $this->createUser('supervisor', (int) $xssCo->id, ['username' => 'sup_fidelity']);
        $usrA = $this->createUser('user', (int) $xssCo->id, ['username' => 'usr_fidelity']);

        $netA = $this->createNetworkDirect((int) $usrA->id, (int) $xssCo->id, [
            'name' => 'Net <script>alert(1)</script>',
            'cidr' => '10.126.0.0/24',
        ]);
        $devA = $this->createDeviceDirect((int) $netA->id, [
            'name' => 'Dev <script>alert(1)</script>',
        ]);

        // 1. All primary views for superadmin
        $this->actingAsUser($superadmin);
        $saRoutes = [
            'dashboard',
            'companies',
            'companies/create',
            "companies/edit/{$xssCo->id}",
            'users',
            'users/create',
            "users/edit/{$supA->id}",
            'networks',
            'networks/create',
            "networks/edit/{$netA->id}",
            "networks/{$netA->id}/devices",
            "devices/show/{$devA->id}",
            'roles',
            'settings',
            'audit-logs',
        ];

        foreach ($saRoutes as $route) {
            $resp = $this->getWithActor($route);
            $resp->assertStatus(200, "Superadmin GET /{$route} failed.");
            $html = $this->rawBody($resp);

            $this->assertStringNotContainsString('<script>alert(1)</script>', $html, "Unescaped XSS on /{$route}");
            $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*(?:\bsrc=|\bnonce=|\{csp-script-nonce\}))[^>]*>/i', $html, "Missing CSP nonce on /{$route}");
            $this->assertDoesNotMatchRegularExpression('/\son(click|change|load|error|submit)\s*=/i', $html, "Inline event handler on /{$route}");
        }

        // 2. Permitted views for supervisor
        $this->actingAsUser($supA);
        foreach (['dashboard', 'users', 'users/create', 'networks', 'networks/create', "networks/{$netA->id}/devices", 'audit-logs'] as $route) {
            $resp = $this->getWithActor($route);
            $resp->assertStatus(200, "Supervisor GET /{$route} failed.");
            $html = $this->rawBody($resp);
            $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
            $this->assertStringNotContainsString(site_url('companies'), $html);
            $this->assertStringNotContainsString(site_url('roles'), $html);
            $this->assertStringNotContainsString(site_url('settings'), $html);
        }

        // 3. Permitted views for standard user
        $this->actingAsUser($usrA);
        foreach (['dashboard', 'networks', "networks/{$netA->id}/devices", "devices/show/{$devA->id}", 'profile', 'guide'] as $route) {
            $resp = $this->getWithActor($route);
            $resp->assertStatus(200, "User GET /{$route} failed.");
            $html = $this->rawBody($resp);
            $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
            $this->assertStringNotContainsString(site_url('users'), $html);
            $this->assertStringNotContainsString(site_url('companies'), $html);
            $this->assertStringNotContainsString(site_url('audit-logs'), $html);
        }
    }
}
