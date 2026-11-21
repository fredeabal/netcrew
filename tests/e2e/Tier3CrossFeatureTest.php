<?php

declare(strict_types=1);

namespace Tests\E2E;

use App\Libraries\NebulaService;
use App\Models\AuditLogModel;
use App\Models\DeviceModel;
use App\Models\NetworkModel;

require_once __DIR__ . '/BaseE2ETestCase.php';

class Tier3CrossFeatureTest extends BaseE2ETestCase
{
    public function testT301CompaniesTimesUsersInheritanceAndVisibility(): void
    {
        $superadmin = $this->createUser('superadmin', null);

        $this->actingAsUser($superadmin)->postWithCsrf('companies/store', [
            'name'         => 'Pairwise Co A',
            'slug'         => 'pairwise-co-a',
            'max_networks' => '0',
            'max_devices'  => '0',
            'active'       => '1',
        ])->assertRedirectTo('/companies');

        $companyA = $this->db->table('companies')->where('slug', 'pairwise-co-a')->get()->getRow();
        $this->assertNotNull($companyA);

        $this->actingAsUser($superadmin)->postWithCsrf('users/store', [
            'username'     => 'supPairwiseA',
            'email'        => 'sup_pairwise_a@a.test',
            'phone'        => '+34600100100',
            'group'        => 'supervisor',
            'company_id'   => (string) $companyA->id,
            'password'     => 'Str0ng!Pass1',
            'pass_confirm' => 'Str0ng!Pass1',
            'active'       => '1',
        ])->assertRedirectTo('/users');

        $supA = auth()->getProvider()->findByCredentials(['email' => 'sup_pairwise_a@a.test']);
        $this->assertNotNull($supA);

        // Supervisor creates userA1 without sending company_id
        $this->actingAsUser($supA)->postWithCsrf('users/store', [
            'username'     => 'userPairwiseA1',
            'email'        => 'user_pairwise_a1@a.test',
            'phone'        => '+34600100101',
            'group'        => 'user',
            'password'     => 'Str0ng!Pass1',
            'pass_confirm' => 'Str0ng!Pass1',
            'active'       => '1',
        ])->assertRedirectTo('/users');

        $userA1 = auth()->getProvider()->findByCredentials(['email' => 'user_pairwise_a1@a.test']);
        $this->assertNotNull($userA1);
        $this->assertSame((int) $companyA->id, (int) $userA1->company_id);

        $saList = $this->rawBody($this->actingAsUser($superadmin)->getWithActor('users'));
        $this->assertStringContainsString('Pairwise Co A', $saList);
        $this->assertStringContainsString('userPairwiseA1', $saList);

        $supList = $this->rawBody($this->actingAsUser($supA)->getWithActor('users'));
        $this->assertStringContainsString('userPairwiseA1', $supList);
        $this->assertStringNotContainsString($superadmin->username, $supList);
    }

    public function testT302CompaniesToggleActiveTimesCompanyActiveFilter(): void
    {
        $superadmin = $this->createUser('superadmin', null);
        $companyA   = $this->createCompany(['name' => 'Toggle Co A', 'active' => 1]);
        $supA       = $this->createUser('supervisor', (int) $companyA->id, [
            'email'    => 'toggle_sup_a@a.test',
            'password' => 'Str0ng!Pass123',
        ]);

        // Active -> dashboard 200
        $this->actingAsUser($supA)->getWithActor('dashboard')->assertStatus(200);

        // Suspend Company A -> supervisor logged out & redirected /login
        $this->actingAsUser($superadmin)->postWithCsrf("companies/toggle-active/{$companyA->id}")->assertRedirectTo('/companies');
        $blocked = $this->actingAsUser($supA)->getWithActor('networks');
        $blocked->assertRedirectTo('/login');
        $this->assertFalse(auth()->loggedIn());
        $this->assertNotEmpty(session('error'));

        // Reactivate Company A -> supervisor logs in again & accesses dashboard
        $this->actingAsUser($superadmin)->postWithCsrf("companies/toggle-active/{$companyA->id}")->assertRedirectTo('/companies');
        $this->actingAsGuest()->postWithCsrf('login', [
            'email'    => 'toggle_sup_a@a.test',
            'password' => 'Str0ng!Pass123',
        ]);
        $this->activeSession = $_SESSION;
        $this->getWithActor('dashboard')->assertStatus(200);
    }

    public function testT303CompanyActiveFilterTimesSuperadminRbac(): void
    {
        $companyA   = $this->createCompany(['name' => 'Inactive For SA', 'active' => 0]);
        $superadmin = $this->createUser('superadmin', (int) $companyA->id);

        $this->actingAsUser($superadmin);
        foreach (['companies', 'settings', 'roles', 'audit-logs'] as $route) {
            $this->getWithActor($route)->assertStatus(200);
            $this->assertTrue(auth()->loggedIn());
        }
    }

    public function testT304MaxNetworksQuotaTimesNetworkCreation(): void
    {
        $companyA = $this->createCompany(['max_networks' => 1]);
        $companyB = $this->createCompany(['max_networks' => 2]);
        $supA     = $this->createUser('supervisor', (int) $companyA->id);
        $supB     = $this->createUser('supervisor', (int) $companyB->id);

        $this->actingAsUser($supA);
        $this->postWithCsrf('networks/store', ['name' => 'A-Net-1', 'cidr' => '10.101.0.0', 'dns' => '1.1.1.1', 'owner_id' => (string) $supA->id])
            ->assertRedirectTo('/networks');
        $failResp = $this->postWithCsrf('networks/store', ['name' => 'A-Net-2', 'cidr' => '10.102.0.0', 'dns' => '1.1.1.1', 'owner_id' => (string) $supA->id]);
        $this->assertTrue($failResp->isRedirect());
        $this->assertNotEmpty(session('error'));
        $this->assertSame(1, $this->db->table('networks')->where('company_id', $companyA->id)->countAllResults());

        $this->actingAsUser($supB);
        $this->postWithCsrf('networks/store', ['name' => 'B-Net-1', 'cidr' => '10.103.0.0', 'dns' => '1.1.1.1', 'owner_id' => (string) $supB->id])
            ->assertRedirectTo('/networks');
        $this->postWithCsrf('networks/store', ['name' => 'B-Net-2', 'cidr' => '10.104.0.0', 'dns' => '1.1.1.1', 'owner_id' => (string) $supB->id])
            ->assertRedirectTo('/networks');
        $this->assertSame(2, $this->db->table('networks')->where('company_id', $companyB->id)->countAllResults());
    }

    public function testT305MaxDevicesQuotaAcrossNetworksAndSlotRecovery(): void
    {
        $companyA = $this->createCompany(['max_networks' => 0, 'max_devices' => 2]);
        $supA     = $this->createUser('supervisor', (int) $companyA->id);
        $net1     = $this->createNetworkDirect((int) $supA->id, (int) $companyA->id, ['cidr' => '10.105.0.0/24']);
        $net2     = $this->createNetworkDirect((int) $supA->id, (int) $companyA->id, ['cidr' => '10.106.0.0/24']);

        $this->actingAsUser($supA);
        $this->postWithCsrf('devices/store', ['network_id' => (string) $net1->id, 'name' => 'Q-Dev-1', 'device_type' => 'pc', 'expiration' => 'never'])
            ->assertRedirectTo("/networks/{$net1->id}/devices");
        $this->postWithCsrf('devices/store', ['network_id' => (string) $net2->id, 'name' => 'Q-Dev-2', 'device_type' => 'pc', 'expiration' => 'never'])
            ->assertRedirectTo("/networks/{$net2->id}/devices");

        // 3rd blocked
        $this->postWithCsrf('devices/store', ['network_id' => (string) $net1->id, 'name' => 'Q-Dev-3', 'device_type' => 'pc', 'expiration' => 'never']);
        $this->assertNotEmpty(session('error'));
        $this->assertNull((new DeviceModel())->where('name', 'Q-Dev-3')->first());

        // Delete device in Net2 frees slot -> creating in Net1 succeeds
        $dev2 = (new DeviceModel())->where('network_id', $net2->id)->first();
        $this->postWithCsrf("devices/delete/{$dev2->id}")->assertRedirectTo("networks/{$net2->id}/devices");

        $this->postWithCsrf('devices/store', ['network_id' => (string) $net1->id, 'name' => 'Q-Dev-3-Retry', 'device_type' => 'pc', 'expiration' => 'never'])
            ->assertRedirectTo("/networks/{$net1->id}/devices");
        $this->assertNotNull((new DeviceModel())->where('name', 'Q-Dev-3-Retry')->first());
    }

    public function testT306NetworksTimesNebulaGlobalCaSlash8AndNetGroups(): void
    {
        $coA  = $this->createCompany();
        $coB  = $this->createCompany();
        $supA = $this->createUser('supervisor', (int) $coA->id);
        $supB = $this->createUser('supervisor', (int) $coB->id);

        $this->actingAsUser($supA)->postWithCsrf('networks/store', ['name' => 'Net-Iso-1', 'cidr' => '10.111.0.0', 'dns' => '1.1.1.1', 'owner_id' => (string) $supA->id]);
        $net1 = (new NetworkModel())->where('name', 'Net-Iso-1')->first();
        $this->actingAsUser($supA)->postWithCsrf('devices/store', ['network_id' => (string) $net1->id, 'name' => 'DevA', 'device_type' => 'pc', 'expiration' => 'never']);
        $devA = (new DeviceModel())->where('network_id', $net1->id)->first();

        $this->actingAsUser($supB)->postWithCsrf('networks/store', ['name' => 'Net-Iso-2', 'cidr' => '10.112.0.0', 'dns' => '1.1.1.1', 'owner_id' => (string) $supB->id]);
        $net2 = (new NetworkModel())->where('name', 'Net-Iso-2')->first();
        $this->actingAsUser($supB)->postWithCsrf('devices/store', ['network_id' => (string) $net2->id, 'name' => 'DevB', 'device_type' => 'pc', 'expiration' => 'never']);
        $devB = (new DeviceModel())->where('network_id', $net2->id)->first();

        $this->assertSame(trim((string) $net1->nebula_ca_crt), trim((string) $net2->nebula_ca_crt));
        $this->assertNotSame($net1->cidr, $net2->cidr);

        $certA = $this->inspectNebulaCertJson((string) $devA->nebula_crt);
        $certB = $this->inspectNebulaCertJson((string) $devB->nebula_crt);
        $this->assertStringEndsWith('/8', $this->getCertFirstIp($certA));
        $this->assertStringEndsWith('/8', $this->getCertFirstIp($certB));
        $this->assertContains("net_{$net1->id}", $certA['details']['groups'] ?? []);
        $this->assertContains("net_{$net2->id}", $certB['details']['groups'] ?? []);

        $lhYaml = $this->rawBody($this->actingAsUser($supA)->getWithActor("networks/{$net1->id}/lighthouse-config"));
        $this->assertValidNebulaYaml($lhYaml, ['am_lighthouse: true']);
    }

    public function testT307DeviceRotateKeysTimesNebulaCertsAndScopedAudit(): void
    {
        $coA  = $this->createCompany();
        $coB  = $this->createCompany();
        $supA = $this->createUser('supervisor', (int) $coA->id);
        $supB = $this->createUser('supervisor', (int) $coB->id);

        $this->actingAsUser($supA)->postWithCsrf('networks/store', ['name' => 'Rot-Audit-Net', 'cidr' => '10.113.0.0', 'dns' => '1.1.1.1', 'owner_id' => (string) $supA->id]);
        $netA = (new NetworkModel())->where('name', 'Rot-Audit-Net')->first();
        $this->actingAsUser($supA)->postWithCsrf('devices/store', ['network_id' => (string) $netA->id, 'name' => 'DevA-Rot', 'device_type' => 'pc', 'expiration' => 'never']);
        $devA = (new DeviceModel())->where('network_id', $netA->id)->first();

        $this->actingAsUser($supA)->postWithCsrf("devices/rotate-keys/{$devA->id}")->assertRedirectTo("devices/show/{$devA->id}");
        $reloaded = (new DeviceModel())->find($devA->id);
        $this->assertNotSame($devA->public_key, $reloaded->public_key);

        $yaml = $this->rawBody($this->actingAsUser($supA)->getWithActor("devices/download/{$devA->id}?platform=yml"));
        $this->assertStringContainsString("group: net_{$netA->id}", $this->extractYamlInboundBlock($yaml));

        $auditA = $this->rawBody($this->actingAsUser($supA)->getWithActor('audit-logs'));
        $this->assertStringContainsString('DevA-Rot', $auditA);

        $auditB = $this->rawBody($this->actingAsUser($supB)->getWithActor('audit-logs'));
        $this->assertStringNotContainsString('DevA-Rot', $auditB);
    }

    public function testT308UsersTimesNetworksTimesDevicesHierarchicalScoping(): void
    {
        $coA    = $this->createCompany();
        $supA   = $this->createUser('supervisor', (int) $coA->id);
        $userA1 = $this->createUser('user', (int) $coA->id);
        $userA2 = $this->createUser('user', (int) $coA->id);

        $net1 = $this->createNetworkDirect((int) $userA1->id, (int) $coA->id, ['name' => 'Hier-Net-1', 'cidr' => '10.114.0.0/24']);
        $net2 = $this->createNetworkDirect((int) $userA2->id, (int) $coA->id, ['name' => 'Hier-Net-2', 'cidr' => '10.115.0.0/24']);

        $this->actingAsUser($userA1);
        $this->postWithCsrf('devices/store', ['network_id' => (string) $net1->id, 'name' => 'U1-Own-Dev', 'device_type' => 'pc', 'expiration' => 'never'])
            ->assertRedirectTo("/networks/{$net1->id}/devices");
        $this->assertAccessDenied($this->postWithCsrf('devices/store', ['network_id' => (string) $net2->id, 'name' => 'U1-Stolen-Dev', 'device_type' => 'pc']));
        $this->assertNull((new DeviceModel())->where('name', 'U1-Stolen-Dev')->first());

        $this->actingAsUser($supA);
        $net1Resp = $this->getWithActor("networks/{$net1->id}/devices");
        $net1Resp->assertStatus(200);
        $net1Resp->assertSee('U1-Own-Dev');
        $this->getWithActor("networks/{$net2->id}/devices")->assertStatus(200);
    }

    public function testT309RbacTimesAuditLogsAcrossTenantsAndPlatform(): void
    {
        $coA        = $this->createCompany();
        $coB        = $this->createCompany();
        $superadmin = $this->createUser('superadmin', null);
        $supA       = $this->createUser('supervisor', (int) $coA->id);

        AuditLogModel::log('NETWORK_CREATE', 'Created Net Alpha-1', null, 'supA', (int) $coA->id);
        AuditLogModel::log('DEVICE_CREATE', 'Created Dev Beta-1', null, 'supB', (int) $coB->id);
        AuditLogModel::log('SETTINGS_UPDATE', 'Updated Global Settings', (int) $superadmin->id, $superadmin->username, null);

        $this->actingAsUser($supA);
        $supAudit = $this->rawBody($this->getWithActor('audit-logs'));
        $this->assertStringContainsString('Created Net Alpha-1', $supAudit);
        $this->assertStringNotContainsString('Created Dev Beta-1', $supAudit);
        $this->assertStringNotContainsString('Updated Global Settings', $supAudit);
        $this->assertAccessDenied($this->getWithActor('settings?tab=audit'), 'Updated Global Settings');

        $this->actingAsUser($superadmin);
        $saAudit = $this->rawBody($this->getWithActor('settings?tab=audit'));
        $this->assertStringContainsString('Created Net Alpha-1', $saAudit);
        $this->assertStringContainsString('Created Dev Beta-1', $saAudit);
        $this->assertStringContainsString('Updated Global Settings', $saAudit);
    }

    public function testT310CompanyDeleteProtectionTimesUsersAndNetworks(): void
    {
        $superadmin = $this->createUser('superadmin', null);
        $coX        = $this->createCompany(['name' => 'Temp Decom X', 'slug' => 'temp-decom-x']);
        $userX      = $this->createUser('user', (int) $coX->id);
        $netX       = $this->createNetworkDirect((int) $userX->id, (int) $coX->id, ['cidr' => '10.116.0.0/24']);

        $this->actingAsUser($superadmin);

        // Both user and network exist -> fail
        $this->postWithCsrf("companies/delete/{$coX->id}");
        $this->assertNotEmpty(session('error'));
        $this->assertNotNull($this->db->table('companies')->where('id', $coX->id)->get()->getRow());

        // Delete network, user still exists -> fail
        $this->postWithCsrf("networks/delete/{$netX->id}")->assertRedirectTo('/networks');
        $this->postWithCsrf("companies/delete/{$coX->id}");
        $this->assertNotEmpty(session('error'));
        $this->assertNotNull($this->db->table('companies')->where('id', $coX->id)->get()->getRow());

        // Delete user -> delete company succeeds
        $this->postWithCsrf("users/delete/{$userX->id}")->assertRedirectTo('/users');
        $this->postWithCsrf("companies/delete/{$coX->id}")->assertRedirectTo('/companies');
        $this->assertNull($this->db->table('companies')->where('id', $coX->id)->get()->getRow());
    }

    public function testT311CompaniesTimesDashboardTimesViewsAcrossAllRoles(): void
    {
        $coA        = $this->createCompany(['name' => 'MultiDash A']);
        $coB        = $this->createCompany(['name' => 'MultiDash B']);
        $superadmin = $this->createUser('superadmin', null);
        $supA       = $this->createUser('supervisor', (int) $coA->id);
        $userA1     = $this->createUser('user', (int) $coA->id);
        $userB1     = $this->createUser('user', (int) $coB->id);

        $this->createNetworkDirect((int) $userA1->id, (int) $coA->id, ['name' => 'MD-Net-A', 'cidr' => '10.117.0.0/24']);
        $this->createNetworkDirect((int) $userB1->id, (int) $coB->id, ['name' => 'MD-Net-B', 'cidr' => '10.118.0.0/24']);

        $saDash = $this->rawBody($this->actingAsUser($superadmin)->getWithActor('dashboard'));
        $this->assertStringContainsString('Empresas', $saDash);
        $this->assertStringContainsString('MD-Net-A', $saDash);
        $this->assertStringContainsString('MD-Net-B', $saDash);

        $supDash = $this->rawBody($this->actingAsUser($supA)->getWithActor('dashboard'));
        $this->assertStringNotContainsString('col-6 col-md-3', $supDash);
        $this->assertStringContainsString('MD-Net-A', $supDash);
        $this->assertStringNotContainsString('MD-Net-B', $supDash);

        $usrDash = $this->rawBody($this->actingAsUser($userA1)->getWithActor('dashboard'));
        $this->assertStringContainsString('MD-Net-A', $usrDash);
        $this->assertStringNotContainsString('Panel de Administración', $usrDash);
    }

    public function testT312RolesControllerTimesViewsAndAdminCompaniesLabel(): void
    {
        $coA        = $this->createCompany();
        $superadmin = $this->createUser('superadmin', null);
        $supA       = $this->createUser('supervisor', (int) $coA->id);

        $this->actingAsUser($superadmin);
        $this->getWithActor('roles')->assertStatus(200);
        $editHtml = $this->rawBody($this->getWithActor('roles/edit/supervisor'));
        $this->assertStringContainsString('admin.companies', $editHtml);
        $this->assertStringContainsString('Gestionar Empresas', $editHtml);

        $this->actingAsUser($supA);
        $this->assertAccessDenied($this->getWithActor('roles'), 'Gestión de Roles y Permisos');
    }

    public function testT313NetworkToggleActiveTimesDeviceShowInstallCard(): void
    {
        $coA  = $this->createCompany();
        $supA = $this->createUser('supervisor', (int) $coA->id);
        $netA = $this->createNetworkDirect((int) $supA->id, (int) $coA->id, ['cidr' => '10.119.0.0/24']);
        $devA = $this->createDeviceDirect((int) $netA->id, ['name' => 'Show-Toggle-Dev']);

        $this->actingAsUser($supA);
        $activeShow = $this->rawBody($this->getWithActor("devices/show/{$devA->id}"));
        $this->assertStringContainsString('install-client-card', $activeShow);

        $this->postWithCsrf("networks/toggle-active/{$netA->id}")->assertRedirectTo('/networks');
        $inactiveShow = $this->rawBody($this->getWithActor("devices/show/{$devA->id}"));
        $this->assertStringNotContainsString('install-client-card', $inactiveShow);

        $this->postWithCsrf("networks/toggle-active/{$netA->id}")->assertRedirectTo('/networks');
        $restoredShow = $this->rawBody($this->getWithActor("devices/show/{$devA->id}"));
        $this->assertStringContainsString('install-client-card', $restoredShow);
    }

    public function testT314AuthEventsTimesAuditScopingAcrossCompanies(): void
    {
        $coA  = $this->createCompany();
        $coB  = $this->createCompany();
        $supA = $this->createUser('supervisor', (int) $coA->id);
        $usrA = $this->createUser('user', (int) $coA->id, [
            'username' => 'ev_user_a',
            'email'    => 'ev_a@a.test',
            'password' => 'Str0ng!Pass123',
        ]);
        $usrB = $this->createUser('user', (int) $coB->id, [
            'username' => 'ev_user_b',
            'email'    => 'ev_b@b.test',
            'password' => 'Str0ng!Pass123',
        ]);

        // User A login + logout
        $this->actingAsGuest()->postWithCsrf('login', ['email' => 'ev_a@a.test', 'password' => 'Str0ng!Pass123']);
        $this->activeSession = $_SESSION;
        $this->getWithActor('logout');

        // User A failed login
        $this->actingAsGuest()->postWithCsrf('login', ['email' => 'ev_a@a.test', 'password' => 'WrongPass!999']);

        // User B login
        $this->actingAsGuest()->postWithCsrf('login', ['email' => 'ev_b@b.test', 'password' => 'Str0ng!Pass123']);

        $auditHtml = $this->rawBody($this->actingAsUser($supA)->getWithActor('audit-logs'));
        $this->assertStringContainsString('ev_user_a', $auditHtml);
        $this->assertStringNotContainsString('ev_user_b', $auditHtml);
    }
}
