<?php

declare(strict_types=1);

namespace Tests\E2E;

use App\Database\Migrations\AddMultitenantCompanies;
use App\Database\Seeds\UserSeeder;
use App\Libraries\NebulaService;
use App\Models\AuditLogModel;
use App\Models\DeviceModel;
use App\Models\NetworkModel;
use CodeIgniter\Security\Exceptions\SecurityException;
use Config\AuthGroups;
use Config\Services;

require_once __DIR__ . '/BaseE2ETestCase.php';

class Tier2BoundaryCasesTest extends BaseE2ETestCase
{
    // =========================================================================
    // F1: Migration & Seeding Boundaries (5 tests)
    // =========================================================================

    public function testT2F101IdempotentRerunOfUserSeeder(): void
    {
        $this->seeder->call(UserSeeder::class);
        $this->seeder->call(UserSeeder::class);
        $this->refreshAuthGroupsConfig();

        $principalCount = $this->db->table('companies')->where('slug', 'principal')->countAllResults();
        $adminCount     = $this->db->table('auth_identities')->where('secret', 'admin@demo.com')->countAllResults();

        $this->assertSame(1, $principalCount);
        $this->assertSame(1, $adminCount);
    }

    public function testT2F102NullableCompanyIdForSuperadmin(): void
    {
        $superadmin = $this->createUser('superadmin', null, ['username' => 'nullable_sa']);
        $row        = $this->db->table('users')->where('id', $superadmin->id)->get()->getRow();

        $this->assertNotNull($row);
        $this->assertNull($row->company_id);
    }

    public function testT2F103MigrationRollbackAndReapply(): void
    {
        $migration = new AddMultitenantCompanies();
        $this->db->resetDataCache();
        $migration->down();
        $this->db->resetDataCache();

        $this->assertFalse($this->db->tableExists('companies'));
        $this->assertFalse($this->db->fieldExists('company_id', 'users'));
        $this->assertFalse($this->db->fieldExists('company_id', 'networks'));
        $this->assertFalse($this->db->fieldExists('company_id', 'audit_logs'));

        $migration->up();
        $this->db->resetDataCache();

        $this->assertTrue($this->db->tableExists('companies'));
        $this->assertTrue($this->db->fieldExists('company_id', 'users'));
        $this->assertTrue($this->db->fieldExists('company_id', 'networks'));
        $this->assertTrue($this->db->fieldExists('company_id', 'audit_logs'));
        $this->assertNotNull($this->db->table('companies')->where('slug', 'principal')->get()->getRow());
    }

    public function testT2F104AuthGroupsDbMatrixSyncFallback(): void
    {
        $this->db->table('auth_group_permissions')
            ->where('group', 'superadmin')
            ->where('permission', 'admin.companies')
            ->delete();

        $authGroups = new AuthGroups();

        $this->assertContains('admin.companies', $authGroups->matrix['superadmin']);
        $this->assertContains('admin.settings', $authGroups->matrix['superadmin']);
        $this->assertContains('admin.roles', $authGroups->matrix['superadmin']);
    }

    public function testT2F105NonSuperadminUserWithoutCompanyIdBlocked(): void
    {
        $orphanSupervisor = $this->createUser('supervisor', null, ['username' => 'orphan_sup']);
        $orphanUser       = $this->createUser('user', null, ['username' => 'orphan_usr']);

        $respSup = $this->actingAsUser($orphanSupervisor)->getWithActor('dashboard');
        $respSup->assertRedirectTo('/login');
        $this->assertFalse(auth()->loggedIn());
        $this->assertNotEmpty(session('error'));

        $respUsr = $this->actingAsUser($orphanUser)->getWithActor('networks');
        $respUsr->assertRedirectTo('/login');
        $this->assertFalse(auth()->loggedIn());
        $this->assertNotEmpty(session('error'));
    }

    // =========================================================================
    // F2: Companies CRUD Boundaries (6 tests)
    // =========================================================================

    public function testT2F201DuplicateCompanySlugRejected(): void
    {
        $superadmin = $this->createUser('superadmin', null);
        $before     = $this->db->table('companies')->countAllResults();

        $resp = $this->actingAsUser($superadmin)->postWithCsrf('companies/store', [
            'name'         => 'Principal Duplicate',
            'slug'         => 'principal',
            'max_networks' => '0',
            'max_devices'  => '0',
            'active'       => '1',
        ]);

        $this->assertTrue($resp->isRedirect());
        $this->assertSame($before, $this->db->table('companies')->countAllResults());
        $this->assertNotEmpty(session('errors') ?: session('error'));
    }

    public function testT2F202EmptyWhitespaceOrOversizedCompanyNameRejected(): void
    {
        $superadmin = $this->createUser('superadmin', null);
        $before     = $this->db->table('companies')->countAllResults();

        foreach (['', '   ', str_repeat('A', 300)] as $badName) {
            $resp = $this->actingAsUser($superadmin)->postWithCsrf('companies/store', [
                'name'         => $badName,
                'slug'         => 'valid-slug-' . strlen($badName),
                'max_networks' => '0',
                'max_devices'  => '0',
                'active'       => '1',
            ]);
            $this->assertTrue($resp->isRedirect());
        }

        $this->assertSame($before, $this->db->table('companies')->countAllResults());
    }

    public function testT2F203InvalidSlugCharactersNeverStoredUnsafe(): void
    {
        $superadmin = $this->createUser('superadmin', null);

        $resp = $this->actingAsUser($superadmin)->postWithCsrf('companies/store', [
            'name'         => 'Bad Slug Corp',
            'slug'         => 'Bad Slug!!<script>',
            'max_networks' => '0',
            'max_devices'  => '0',
            'active'       => '1',
        ]);

        $this->assertTrue($resp->isRedirect());

        $unsafeRow = $this->db->table('companies')->like('slug', '<script>')->get()->getRow();
        $this->assertNull($unsafeRow);

        $row = $this->db->table('companies')->where('name', 'Bad Slug Corp')->get()->getRow();
        if ($row !== null) {
            $this->assertMatchesRegularExpression('/^[a-zA-Z0-9_-]+$/', $row->slug);
        }
    }

    public function testT2F204NegativeQuotasRejectedZeroAccepted(): void
    {
        $superadmin = $this->createUser('superadmin', null);

        $badResp = $this->actingAsUser($superadmin)->postWithCsrf('companies/store', [
            'name'         => 'Neg Quota Co',
            'slug'         => 'neg-quota-co',
            'max_networks' => '-1',
            'max_devices'  => '-5',
            'active'       => '1',
        ]);
        $this->assertTrue($badResp->isRedirect());
        $this->assertNull($this->db->table('companies')->where('slug', 'neg-quota-co')->get()->getRow());

        $okResp = $this->actingAsUser($superadmin)->postWithCsrf('companies/store', [
            'name'         => 'Unlimited Co',
            'slug'         => 'unlimited-co',
            'max_networks' => '0',
            'max_devices'  => '0',
            'active'       => '1',
        ]);
        $okResp->assertRedirectTo('/companies');
        $this->assertNotNull($this->db->table('companies')->where('slug', 'unlimited-co')->get()->getRow());
    }

    public function testT2F205DeleteProtectionCompanyWithUsersOrNetworks(): void
    {
        $superadmin = $this->createUser('superadmin', null);

        // Case (a): company with 1 user and 0 networks
        $coWithUser = $this->createCompany(['name' => 'Has User Co', 'slug' => 'has-user-co']);
        $this->createUser('user', (int) $coWithUser->id);

        $respA = $this->actingAsUser($superadmin)->postWithCsrf("companies/delete/{$coWithUser->id}");
        $this->assertTrue($respA->isRedirect());
        $this->assertNotEmpty(session('error'));
        $this->assertNotNull($this->db->table('companies')->where('id', $coWithUser->id)->get()->getRow());

        // Case (b): company with 0 users and 1 network
        $coWithNet = $this->createCompany(['name' => 'Has Net Co', 'slug' => 'has-net-co']);
        $this->createNetworkDirect((int) $superadmin->id, (int) $coWithNet->id);

        $respB = $this->actingAsUser($superadmin)->postWithCsrf("companies/delete/{$coWithNet->id}");
        $this->assertTrue($respB->isRedirect());
        $this->assertNotEmpty(session('error'));
        $this->assertNotNull($this->db->table('companies')->where('id', $coWithNet->id)->get()->getRow());
    }

    public function testT2F206NonExistentCompanyIdHandledCleanly(): void
    {
        $superadmin = $this->createUser('superadmin', null);
        $this->actingAsUser($superadmin);

        foreach ([
            $this->getWithActor('companies/edit/999999'),
            $this->postWithCsrf('companies/update/999999', ['name' => 'Ghost', 'slug' => 'ghost']),
            $this->postWithCsrf('companies/toggle-active/999999'),
            $this->postWithCsrf('companies/delete/999999'),
        ] as $resp) {
            $status = $resp->response()->getStatusCode();
            $this->assertTrue($resp->isRedirect() || $status === 404, "Expected clean redirect or 404, got {$status}.");
        }
    }

    // =========================================================================
    // F3: CompanyActiveFilter & Auth Boundaries (5 tests)
    // =========================================================================

    public function testT2F301SupervisorPointingToDeletedCompanyIdBlocked(): void
    {
        $company = $this->createCompany();
        $sup     = $this->createUser('supervisor', (int) $company->id);
        $this->db->table('companies')->where('id', $company->id)->delete();

        $resp = $this->actingAsUser($sup)->getWithActor('dashboard');
        $resp->assertRedirectTo('/login');
        $this->assertFalse(auth()->loggedIn());
        $this->assertNotEmpty(session('error'));
    }

    public function testT2F302AjaxRequestFromDeactivatedCompanyBlocked(): void
    {
        $company = $this->createCompany(['active' => 1]);
        $user    = $this->createUser('user', (int) $company->id);
        $net     = $this->createNetworkDirect((int) $user->id, (int) $company->id);
        $this->createDeviceDirect((int) $net->id);

        $this->db->table('companies')->where('id', $company->id)->update(['active' => 0]);

        $resp = $this->actingAsUser($user)->getWithActor(
            "devices/realtime-status/{$net->id}",
            [],
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        $status = $resp->response()->getStatusCode();
        $this->assertTrue(
            $resp->isRedirect() || in_array($status, [401, 403], true),
            "Inactive company AJAX must be blocked, got {$status}."
        );
        $this->assertFalse(auth()->loggedIn());
        $this->assertStringNotContainsString('"peers"', $this->rawBody($resp));
    }

    public function testT2F303PublicOneTimeShareRouteAccessibleUnauthenticated(): void
    {
        $company = $this->createCompany(['active' => 1]);
        $user    = $this->createUser('user', (int) $company->id);
        $net     = $this->createNetworkDirect((int) $user->id, (int) $company->id, ['cidr' => '10.45.0.0/24']);

        $this->actingAsUser($user)->postWithCsrf('devices/store', [
            'network_id'  => (string) $net->id,
            'name'        => 'Public-Share-Dev',
            'device_type' => 'pc',
            'expiration'  => 'never',
        ]);
        $dev = (new DeviceModel())->where('network_id', $net->id)->first();

        $gen   = $this->actingAsUser($user)->postWithCsrf("devices/generate-share-link/{$dev->id}");
        $token = (string) (json_decode($gen->getJSON(), true)['token'] ?? '');
        $this->assertNotSame('', $token);

        $this->actingAsGuest();
        $this->getWithActor("connect/{$token}")->assertStatus(200);
        $this->getWithActor("connect/{$token}/download?platform=yml")->assertStatus(200);
    }

    public function testT2F304LoginAttemptByUserOfInactiveCompanyBlocked(): void
    {
        $company = $this->createCompany(['active' => 0]);
        $this->createUser('supervisor', (int) $company->id, [
            'email'    => 'inactivelogin@netcrew.test',
            'password' => 'Str0ng!Pass123',
        ]);

        $this->actingAsGuest();
        $this->postWithCsrf('login', [
            'email'    => 'inactivelogin@netcrew.test',
            'password' => 'Str0ng!Pass123',
        ]);

        $this->activeSession = $_SESSION;
        $dashResp            = $this->getWithActor('dashboard');
        $dashResp->assertRedirectTo('/login');
        $this->assertFalse(auth()->loggedIn());
        $this->assertNotEmpty(session('error'));
    }

    public function testT2F305ToggleCompanyBackToActiveRestoresAccess(): void
    {
        $superadmin = $this->createUser('superadmin', null);
        $company    = $this->createCompany(['active' => 0]);
        $supervisor = $this->createUser('supervisor', (int) $company->id);

        $blocked = $this->actingAsUser($supervisor)->getWithActor('dashboard');
        $blocked->assertRedirectTo('/login');

        $this->actingAsUser($superadmin)->postWithCsrf("companies/toggle-active/{$company->id}");
        $this->assertSame(1, (int) $this->db->table('companies')->where('id', $company->id)->get()->getRow()->active);

        $restored = $this->actingAsUser($supervisor)->getWithActor('dashboard');
        $restored->assertStatus(200);
    }

    // =========================================================================
    // F4: Role & Route Authorization Boundaries (5 tests)
    // =========================================================================

    public function testT2F401SupervisorDeniedSettingsPostMutations(): void
    {
        $company    = $this->createCompany();
        $supervisor = $this->createUser('supervisor', (int) $company->id);
        $this->actingAsUser($supervisor);

        $respNebula = $this->postWithCsrf('settings/nebula/update', [
            'nebula_lighthouse_host' => 'evil.attacker.test',
            'nebula_lighthouse_port' => '9999',
            'nebula_cipher'          => 'chacha20',
        ]);
        $this->assertAccessDenied($respNebula);

        $respSmtp = $this->postWithCsrf('settings/smtp/update', [
            'smtp_host' => 'smtp.evil.test',
            'smtp_port' => '2525',
        ]);
        $this->assertAccessDenied($respSmtp);

        $respClear = $this->postWithCsrf('settings/maintenance/clear-all');
        $this->assertAccessDenied($respClear);

        $this->assertSame('vpn.netcrew.test', (string) service('settings')->get('Nebula.lighthouseHost'));
        $this->assertNotNull($this->db->table('companies')->where('id', $company->id)->get()->getRow());
    }

    public function testT2F402SupervisorDeniedRolePrivilegeEscalation(): void
    {
        $company    = $this->createCompany();
        $supervisor = $this->createUser('supervisor', (int) $company->id);

        $resp = $this->actingAsUser($supervisor)->postWithCsrf('roles/update/supervisor', [
            'permissions' => ['admin.companies', 'admin.settings', 'admin.roles', 'admin.users'],
        ]);

        $this->assertAccessDenied($resp);

        $escalated = $this->db->table('auth_group_permissions')
            ->where('group', 'supervisor')
            ->whereIn('permission', ['admin.companies', 'admin.settings', 'admin.roles'])
            ->countAllResults();
        $this->assertSame(0, $escalated);
    }

    public function testT2F403SupervisorDeniedCompaniesMutations(): void
    {
        $company    = $this->createCompany();
        $supervisor = $this->createUser('supervisor', (int) $company->id);
        $targetCo   = $this->createCompany(['name' => 'Protected Co']);

        $beforeCount = $this->db->table('companies')->countAllResults();

        $this->actingAsUser($supervisor);
        $this->assertAccessDenied($this->postWithCsrf('companies/store', [
            'name'   => 'Rogue Co',
            'slug'   => 'rogue-co',
            'active' => '1',
        ]));
        $this->assertAccessDenied($this->postWithCsrf("companies/delete/{$targetCo->id}"));

        $this->assertSame($beforeCount, $this->db->table('companies')->countAllResults());
        $this->assertNotNull($this->db->table('companies')->where('id', $targetCo->id)->get()->getRow());
    }

    public function testT2F404UserDeniedAuditLogsAndSettingsAuditTab(): void
    {
        $company = $this->createCompany();
        $user    = $this->createUser('user', (int) $company->id);
        AuditLogModel::log('SECRET_AUDIT', 'Confidential Audit Entry', null, 'auditor', (int) $company->id);

        $this->actingAsUser($user);
        $this->assertAccessDenied($this->getWithActor('audit-logs'), 'Confidential Audit Entry', 'Registro de Auditoría');
        $this->assertAccessDenied($this->getWithActor('settings?tab=audit'), 'Confidential Audit Entry', 'Registro de Auditoría');
    }

    public function testT2F405MissingCsrfTokenRejectedOnMutatingPosts(): void
    {
        $superadmin = $this->createUser('superadmin', null);
        $before     = $this->db->table('companies')->countAllResults();

        $this->actingAsUser($superadmin);

        try {
            $resp = $this->postWithoutCsrf('companies/store', [
                'name'   => 'No CSRF Co',
                'slug'   => 'no-csrf-co',
                'active' => '1',
            ]);
            $this->assertSame(403, $resp->response()->getStatusCode());
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }

        $this->assertSame($before, $this->db->table('companies')->countAllResults());
    }

    // =========================================================================
    // F5: Users Controller Scoping Boundaries (5 tests)
    // =========================================================================

    public function testT2F501CrossCompanyUserIdorDeniedForSupervisor(): void
    {
        $coA  = $this->createCompany();
        $coB  = $this->createCompany();
        $supA = $this->createUser('supervisor', (int) $coA->id);
        $usrB = $this->createUser('user', (int) $coB->id, ['username' => 'victim_b']);

        $this->actingAsUser($supA);

        $this->assertAccessDenied($this->getWithActor("users/edit/{$usrB->id}"), 'victim_b', $usrB->email);
        $this->assertAccessDenied($this->postWithCsrf("users/update/{$usrB->id}", [
            'username' => 'pwnedB',
            'email'    => $usrB->email,
            'group'    => 'user',
            'active'   => '1',
        ]));
        $this->assertAccessDenied($this->postWithCsrf("users/toggle-active/{$usrB->id}"));
        $this->assertAccessDenied($this->postWithCsrf("users/delete/{$usrB->id}"));

        $reloaded = auth()->getProvider()->findById($usrB->id);
        $this->assertNotNull($reloaded);
        $this->assertSame('victim_b', $reloaded->username);
        $this->assertFalse($reloaded->isBanned());
    }

    public function testT2F502SupervisorCannotAssignSuperadminGroup(): void
    {
        $coA  = $this->createCompany();
        $supA = $this->createUser('supervisor', (int) $coA->id);

        $resp = $this->actingAsUser($supA)->postWithCsrf('users/store', [
            'username'     => 'escalatedSa',
            'email'        => 'escalated_sa@a.test',
            'group'        => 'superadmin',
            'password'     => 'Str0ng!Pass1',
            'pass_confirm' => 'Str0ng!Pass1',
            'active'       => '1',
        ]);

        $this->assertTrue($resp->isRedirect());
        $created = auth()->getProvider()->findByCredentials(['email' => 'escalated_sa@a.test']);
        $this->assertNull($created);
    }

    public function testT2F503SupervisorSpoofedCompanyIdForcedToOwnCompany(): void
    {
        $coA  = $this->createCompany();
        $coB  = $this->createCompany();
        $supA = $this->createUser('supervisor', (int) $coA->id);
        $usrA = $this->createUser('user', (int) $coA->id, ['username' => 'stayInA']);

        $this->actingAsUser($supA);

        $this->postWithCsrf('users/store', [
            'username'     => 'spoofStore',
            'email'        => 'spoof_store@a.test',
            'group'        => 'user',
            'company_id'   => (string) $coB->id,
            'password'     => 'Str0ng!Pass1',
            'pass_confirm' => 'Str0ng!Pass1',
            'active'       => '1',
        ]);
        $created = auth()->getProvider()->findByCredentials(['email' => 'spoof_store@a.test']);
        if ($created !== null) {
            $this->assertSame((int) $coA->id, (int) $created->company_id);
        }

        $this->postWithCsrf("users/update/{$usrA->id}", [
            'username'   => 'stayInA',
            'email'      => $usrA->email,
            'group'      => 'user',
            'company_id' => (string) $coB->id,
            'active'     => '1',
        ]);
        $reloaded = auth()->getProvider()->findById($usrA->id);
        $this->assertSame((int) $coA->id, (int) $reloaded->company_id);
    }

    public function testT2F504SuperadminCreatingNonSuperadminWithoutCompanyFails(): void
    {
        $superadmin = $this->createUser('superadmin', null);
        $this->actingAsUser($superadmin);

        foreach (['supervisor', 'user'] as $role) {
            $email = "nocompany_{$role}@test.local";
            $resp  = $this->postWithCsrf('users/store', [
                'username'     => "nocomp{$role}",
                'email'        => $email,
                'group'        => $role,
                'company_id'   => '',
                'password'     => 'Str0ng!Pass1',
                'pass_confirm' => 'Str0ng!Pass1',
                'active'       => '1',
            ]);
            $this->assertTrue($resp->isRedirect());
            $this->assertNull(auth()->getProvider()->findByCredentials(['email' => $email]));
        }
    }

    public function testT2F505SupervisorCannotEditDeleteSuperadminOrSelfDelete(): void
    {
        $coA        = $this->createCompany();
        $superadmin = $this->createUser('superadmin', null, ['username' => 'platform_sa']);
        $supA       = $this->createUser('supervisor', (int) $coA->id);

        $this->actingAsUser($supA);
        $this->assertAccessDenied($this->getWithActor("users/edit/{$superadmin->id}"), 'platform_sa');
        $this->assertAccessDenied($this->postWithCsrf("users/delete/{$superadmin->id}"));
        $this->assertNotNull(auth()->getProvider()->findById($superadmin->id));

        $this->postWithCsrf("users/delete/{$supA->id}");
        $this->assertNotNull(auth()->getProvider()->findById($supA->id));

        $this->postWithCsrf("users/toggle-active/{$supA->id}");
        $this->assertFalse(auth()->getProvider()->findById($supA->id)->isBanned());
    }

    // =========================================================================
    // F6: Networks Controller Scoping & Quota Boundaries (6 tests)
    // =========================================================================

    public function testT2F601MaxNetworksQuotaBoundary(): void
    {
        $coA  = $this->createCompany(['max_networks' => 2]);
        $coB  = $this->createCompany(['max_networks' => 2]);
        $supA = $this->createUser('supervisor', (int) $coA->id);
        $supB = $this->createUser('supervisor', (int) $coB->id);

        $this->actingAsUser($supA);
        $this->postWithCsrf('networks/store', ['name' => 'Quota-1', 'cidr' => '10.61.0.0', 'dns' => '1.1.1.1', 'owner_id' => (string) $supA->id])
            ->assertRedirectTo('/networks');
        $this->postWithCsrf('networks/store', ['name' => 'Quota-2', 'cidr' => '10.62.0.0', 'dns' => '1.1.1.1', 'owner_id' => (string) $supA->id])
            ->assertRedirectTo('/networks');

        $thirdResp = $this->postWithCsrf('networks/store', ['name' => 'Quota-3', 'cidr' => '10.63.0.0', 'dns' => '1.1.1.1', 'owner_id' => (string) $supA->id]);
        $this->assertTrue($thirdResp->isRedirect());
        $this->assertNotEmpty(session('error'));
        $this->assertSame(2, $this->db->table('networks')->where('company_id', $coA->id)->countAllResults());

        // Company B remains unaffected
        $this->actingAsUser($supB)
            ->postWithCsrf('networks/store', ['name' => 'CoB-Net-1', 'cidr' => '10.64.0.0', 'dns' => '1.1.1.1', 'owner_id' => (string) $supB->id])
            ->assertRedirectTo('/networks');
        $this->assertSame(1, $this->db->table('networks')->where('company_id', $coB->id)->countAllResults());
    }

    public function testT2F602MaxNetworksZeroUnlimitedBoundary(): void
    {
        $coA  = $this->createCompany(['max_networks' => 0]);
        $supA = $this->createUser('supervisor', (int) $coA->id);

        $this->actingAsUser($supA);
        for ($i = 1; $i <= 3; $i++) {
            $this->postWithCsrf('networks/store', [
                'name'     => "Unlimited-Net-{$i}",
                'cidr'     => "10.6{$i}.0.0",
                'dns'      => '1.1.1.1',
                'owner_id' => (string) $supA->id,
            ])->assertRedirectTo('/networks');
        }

        $this->assertSame(3, $this->db->table('networks')->where('company_id', $coA->id)->countAllResults());
    }

    public function testT2F603CrossCompanyNetworkIdorDenied(): void
    {
        $coA  = $this->createCompany();
        $coB  = $this->createCompany();
        $supA = $this->createUser('supervisor', (int) $coA->id);
        $usrB = $this->createUser('user', (int) $coB->id);
        $netB = $this->createNetworkDirect((int) $usrB->id, (int) $coB->id, ['name' => 'Secret-Net-B', 'cidr' => '10.65.0.0/24']);

        $this->actingAsUser($supA);
        $this->assertAccessDenied($this->getWithActor("networks/edit/{$netB->id}"), 'Secret-Net-B');
        $this->assertAccessDenied($this->postWithCsrf("networks/update/{$netB->id}", [
            'name' => 'Hijacked-B', 'cidr' => '10.65.0.0', 'dns' => '1.1.1.1', 'owner_id' => (string) $usrB->id, 'active' => '1',
        ]));
        $this->assertAccessDenied($this->postWithCsrf("networks/toggle-active/{$netB->id}"));
        $this->assertAccessDenied($this->postWithCsrf("networks/delete/{$netB->id}"));
        $this->assertAccessDenied($this->getWithActor("networks/{$netB->id}/lighthouse-config"), 'BEGIN NEBULA CERTIFICATE');

        $stillB = (new NetworkModel())->find($netB->id);
        $this->assertNotNull($stillB);
        $this->assertSame('Secret-Net-B', $stillB->name);
        $this->assertSame(1, (int) $stillB->active);
    }

    public function testT2F604SameCompanyUserHorizontalNetworkIdorDenied(): void
    {
        $coA    = $this->createCompany();
        $userA1 = $this->createUser('user', (int) $coA->id);
        $userA2 = $this->createUser('user', (int) $coA->id);
        $netA2  = $this->createNetworkDirect((int) $userA2->id, (int) $coA->id, ['name' => 'Peer-Net-A2', 'cidr' => '10.66.0.0/24']);

        $this->actingAsUser($userA1);
        $this->assertAccessDenied($this->getWithActor("networks/edit/{$netA2->id}"), 'Peer-Net-A2');
        $this->assertAccessDenied($this->postWithCsrf("networks/update/{$netA2->id}", [
            'name' => 'Hijacked-A2', 'cidr' => '10.66.0.0', 'dns' => '1.1.1.1', 'owner_id' => (string) $userA1->id, 'active' => '1',
        ]));
        $this->assertAccessDenied($this->postWithCsrf("networks/toggle-active/{$netA2->id}"));
        $this->assertAccessDenied($this->postWithCsrf("networks/delete/{$netA2->id}"));
        $this->assertAccessDenied($this->getWithActor("networks/{$netA2->id}/lighthouse-config"), 'BEGIN NEBULA CERTIFICATE');

        $this->assertSame('Peer-Net-A2', (new NetworkModel())->find($netA2->id)->name);
    }

    public function testT2F605InvalidOverlappingOrRestrictedCidrRejected(): void
    {
        $coA  = $this->createCompany();
        $supA = $this->createUser('supervisor', (int) $coA->id);
        $this->createNetworkDirect((int) $supA->id, (int) $coA->id, ['cidr' => '10.67.0.0/24']);
        service('settings')->set('Nebula.restrictedCidrs', '10.99.0.0/24');

        $before = $this->db->table('networks')->countAllResults();
        $this->actingAsUser($supA);

        foreach (['999.999.0.0', '10.99.0.0', '10.67.0.0'] as $badCidr) {
            $resp = $this->postWithCsrf('networks/store', [
                'name'     => 'Bad-Net-' . $badCidr,
                'cidr'     => $badCidr,
                'dns'      => '1.1.1.1',
                'owner_id' => (string) $supA->id,
            ]);
            $this->assertTrue($resp->isRedirect());
        }

        $this->assertSame($before, $this->db->table('networks')->countAllResults());
    }

    public function testT2F606CrossCompanyOwnerIdTamperingRejected(): void
    {
        $coA  = $this->createCompany();
        $coB  = $this->createCompany();
        $supA = $this->createUser('supervisor', (int) $coA->id);
        $usrB = $this->createUser('user', (int) $coB->id);

        $resp = $this->actingAsUser($supA)->postWithCsrf('networks/store', [
            'name'     => 'Tampered-Owner-Net',
            'cidr'     => '10.68.0.0',
            'dns'      => '1.1.1.1',
            'owner_id' => (string) $usrB->id,
        ]);

        $this->assertTrue($resp->isRedirect());
        $net = (new NetworkModel())->where('name', 'Tampered-Owner-Net')->first();
        if ($net !== null) {
            $this->assertNotSame((int) $usrB->id, (int) $net->owner_id);
        } else {
            $this->assertNull($net);
        }
    }

    // =========================================================================
    // F7: Devices Controller Scoping & Quota Boundaries (5 tests)
    // =========================================================================

    public function testT2F701MaxDevicesQuotaAcrossMultipleCompanyNetworks(): void
    {
        $coA  = $this->createCompany(['max_networks' => 0, 'max_devices' => 2]);
        $supA = $this->createUser('supervisor', (int) $coA->id);
        $net1 = $this->createNetworkDirect((int) $supA->id, (int) $coA->id, ['cidr' => '10.71.0.0/24']);
        $net2 = $this->createNetworkDirect((int) $supA->id, (int) $coA->id, ['cidr' => '10.72.0.0/24']);

        $this->actingAsUser($supA);
        $this->postWithCsrf('devices/store', ['network_id' => (string) $net1->id, 'name' => 'D1', 'device_type' => 'pc', 'expiration' => 'never'])
            ->assertRedirectTo("/networks/{$net1->id}/devices");
        $this->postWithCsrf('devices/store', ['network_id' => (string) $net2->id, 'name' => 'D2', 'device_type' => 'pc', 'expiration' => 'never'])
            ->assertRedirectTo("/networks/{$net2->id}/devices");

        $third = $this->postWithCsrf('devices/store', ['network_id' => (string) $net2->id, 'name' => 'D3', 'device_type' => 'pc', 'expiration' => 'never']);
        $this->assertTrue($third->isRedirect());
        $this->assertNotEmpty(session('error'));

        $totalCoA = $this->db->table('devices')
            ->join('networks', 'networks.id = devices.network_id')
            ->where('networks.company_id', $coA->id)
            ->countAllResults();
        $this->assertSame(2, $totalCoA);
    }

    public function testT2F702MaxDevicesZeroUnlimitedBoundary(): void
    {
        $coA  = $this->createCompany(['max_networks' => 0, 'max_devices' => 0]);
        $supA = $this->createUser('supervisor', (int) $coA->id);
        $net1 = $this->createNetworkDirect((int) $supA->id, (int) $coA->id, ['cidr' => '10.73.0.0/24']);
        $net2 = $this->createNetworkDirect((int) $supA->id, (int) $coA->id, ['cidr' => '10.74.0.0/24']);

        $this->actingAsUser($supA);
        $this->postWithCsrf('devices/store', ['network_id' => (string) $net1->id, 'name' => 'U1', 'device_type' => 'pc', 'expiration' => 'never'])->assertRedirectTo("/networks/{$net1->id}/devices");
        $this->postWithCsrf('devices/store', ['network_id' => (string) $net1->id, 'name' => 'U2', 'device_type' => 'pc', 'expiration' => 'never'])->assertRedirectTo("/networks/{$net1->id}/devices");
        $this->postWithCsrf('devices/store', ['network_id' => (string) $net2->id, 'name' => 'U3', 'device_type' => 'pc', 'expiration' => 'never'])->assertRedirectTo("/networks/{$net2->id}/devices");

        $this->assertSame(3, $this->db->table('devices')->whereIn('network_id', [$net1->id, $net2->id])->countAllResults());
    }

    public function testT2F703CrossCompanyDeviceIdorDeniedAcrossAllRoutes(): void
    {
        $coA  = $this->createCompany();
        $coB  = $this->createCompany();
        $supA = $this->createUser('supervisor', (int) $coA->id);
        $usrB = $this->createUser('user', (int) $coB->id);
        $netB = $this->createNetworkDirect((int) $usrB->id, (int) $coB->id, ['cidr' => '10.75.0.0/24']);
        $devB = $this->createDeviceDirect((int) $netB->id, ['name' => 'Secret-Dev-B']);

        $this->actingAsUser($supA);

        $this->assertAccessDenied($this->getWithActor("networks/{$netB->id}/devices"), 'Secret-Dev-B');
        $this->assertAccessDenied($this->getWithActor("devices/create/{$netB->id}"), 'Secret-Dev-B');
        $this->assertAccessDenied($this->postWithCsrf('devices/store', ['network_id' => (string) $netB->id, 'name' => 'Injected', 'device_type' => 'pc']));
        $this->assertAccessDenied($this->getWithActor("devices/show/{$devB->id}"), 'Secret-Dev-B');
        $this->assertAccessDenied($this->getWithActor("devices/edit/{$devB->id}"), 'Secret-Dev-B');
        $this->assertAccessDenied($this->postWithCsrf("devices/update/{$devB->id}", ['name' => 'Hacked', 'device_type' => 'pc']));
        $this->assertAccessDenied($this->postWithCsrf("devices/rotate-keys/{$devB->id}"));
        $this->assertAccessDenied($this->postWithCsrf("devices/toggle-active/{$devB->id}"));
        $this->assertAccessDenied($this->postWithCsrf("devices/delete/{$devB->id}"));
        $this->assertAccessDenied($this->getWithActor("devices/download/{$devB->id}"), 'BEGIN NEBULA');

        $jsonResp = $this->getWithActor("devices/details-json/{$devB->id}");
        $this->assertSame(403, $jsonResp->response()->getStatusCode());

        $rtResp = $this->getWithActor("devices/realtime-status/{$netB->id}");
        $this->assertSame(403, $rtResp->response()->getStatusCode());

        $shareResp = $this->postWithCsrf("devices/generate-share-link/{$devB->id}");
        $this->assertSame(403, $shareResp->response()->getStatusCode());

        $stillB = (new DeviceModel())->find($devB->id);
        $this->assertNotNull($stillB);
        $this->assertSame('Secret-Dev-B', $stillB->name);
    }

    public function testT2F704SameCompanyUserHorizontalDeviceIdorDenied(): void
    {
        $coA    = $this->createCompany();
        $userA1 = $this->createUser('user', (int) $coA->id);
        $userA2 = $this->createUser('user', (int) $coA->id);
        $netA2  = $this->createNetworkDirect((int) $userA2->id, (int) $coA->id, ['cidr' => '10.76.0.0/24']);
        $devA2  = $this->createDeviceDirect((int) $netA2->id, ['name' => 'Peer-Dev-A2']);

        $this->actingAsUser($userA1);
        $this->assertAccessDenied($this->getWithActor("devices/show/{$devA2->id}"), 'Peer-Dev-A2');
        $this->assertAccessDenied($this->postWithCsrf("devices/delete/{$devA2->id}"));
        $this->assertSame(403, $this->getWithActor("devices/details-json/{$devA2->id}")->response()->getStatusCode());
        $this->assertNotNull((new DeviceModel())->find($devA2->id));
    }

    public function testT2F705InvalidDeviceInputsAndExhaustedOneTimeShareToken(): void
    {
        $coA   = $this->createCompany();
        $userA = $this->createUser('user', (int) $coA->id);
        $netA  = $this->createNetworkDirect((int) $userA->id, (int) $coA->id, ['cidr' => '10.77.0.0/24']);

        $this->actingAsUser($userA);

        $badName = $this->postWithCsrf('devices/store', ['network_id' => (string) $netA->id, 'name' => 'X', 'device_type' => 'pc']);
        $this->assertTrue($badName->isRedirect());

        $badType = $this->postWithCsrf('devices/store', ['network_id' => (string) $netA->id, 'name' => 'ValidName', 'device_type' => 'toaster']);
        $this->assertTrue($badType->isRedirect());

        $this->assertSame(0, $this->db->table('devices')->where('network_id', $netA->id)->countAllResults());

        // Valid device + single-use share token exhaustion
        $this->postWithCsrf('devices/store', ['network_id' => (string) $netA->id, 'name' => 'SingleUseDev', 'device_type' => 'pc', 'expiration' => 'never']);
        $dev   = (new DeviceModel())->where('network_id', $netA->id)->first();
        $token = (string) (json_decode($this->postWithCsrf("devices/generate-share-link/{$dev->id}")->getJSON(), true)['token'] ?? '');

        $this->actingAsGuest();
        $this->getWithActor("connect/{$token}/download?platform=yml")->assertStatus(200);

        $secondDl = $this->getWithActor("connect/{$token}/download?platform=yml");
        $this->assertSame(404, $secondDl->response()->getStatusCode());
    }

    // =========================================================================
    // F8: Nebula Overlay & YAML Boundaries (5 tests)
    // =========================================================================

    public function testT2F801BuildLighthouseConfigHandlesCipherAndRelaySettings(): void
    {
        $globalCa = NebulaService::getOrCreateGlobalCa();
        $lhCert   = NebulaService::signDeviceCert($globalCa['crt'], $globalCa['key'], 'lh-global', '10.0.0.1/8', 'lighthouse');
        $vNet     = (object) ['id' => 0, 'name' => 'Global', 'cidr' => '10.0.0.0/8', 'nebula_ca_crt' => $globalCa['crt']];

        service('settings')->set('Nebula.cipher', 'chacha20');
        service('settings')->set('Nebula.relayEnabled', 0);
        $yaml1 = NebulaService::buildLighthouseConfig($vNet, $lhCert['crt'], $lhCert['key']);
        $this->assertValidNebulaYaml($yaml1, ['cipher: chacha20', 'am_relay: false']);

        service('settings')->set('Nebula.cipher', 'aes');
        service('settings')->set('Nebula.relayEnabled', 1);
        $yaml2 = NebulaService::buildLighthouseConfig($vNet, $lhCert['crt'], $lhCert['key']);
        $this->assertValidNebulaYaml($yaml2, ['cipher: aes', 'am_relay: true']);
    }

    public function testT2F802TwoCompanyNetworksHaveDistinctNetGroupsAndSlash8Mask(): void
    {
        $coA  = $this->createCompany();
        $coB  = $this->createCompany();
        $supA = $this->createUser('supervisor', (int) $coA->id);
        $supB = $this->createUser('supervisor', (int) $coB->id);

        $this->actingAsUser($supA)->postWithCsrf('networks/store', ['name' => 'CoA-Mesh', 'cidr' => '10.81.0.0', 'dns' => '1.1.1.1', 'owner_id' => (string) $supA->id]);
        $net1 = (new NetworkModel())->where('name', 'CoA-Mesh')->first();
        $this->actingAsUser($supA)->postWithCsrf('devices/store', ['network_id' => (string) $net1->id, 'name' => 'Dev-A', 'device_type' => 'pc', 'expiration' => 'never']);
        $dev1 = (new DeviceModel())->where('network_id', $net1->id)->first();

        $this->actingAsUser($supB)->postWithCsrf('networks/store', ['name' => 'CoB-Mesh', 'cidr' => '10.82.0.0', 'dns' => '1.1.1.1', 'owner_id' => (string) $supB->id]);
        $net2 = (new NetworkModel())->where('name', 'CoB-Mesh')->first();
        $this->actingAsUser($supB)->postWithCsrf('devices/store', ['network_id' => (string) $net2->id, 'name' => 'Dev-B', 'device_type' => 'pc', 'expiration' => 'never']);
        $dev2 = (new DeviceModel())->where('network_id', $net2->id)->first();

        $yamlA    = $this->rawBody($this->actingAsUser($supA)->getWithActor("devices/download/{$dev1->id}?platform=yml"));
        $inboundA = $this->extractYamlInboundBlock($yamlA);
        $this->assertStringContainsString("group: net_{$net1->id}", $inboundA);
        $this->assertStringNotContainsString("group: net_{$net2->id}", $inboundA);
        $this->assertStringNotContainsString('host: any', $inboundA);

        $yamlB    = $this->rawBody($this->actingAsUser($supB)->getWithActor("devices/download/{$dev2->id}?platform=yml"));
        $inboundB = $this->extractYamlInboundBlock($yamlB);
        $this->assertStringContainsString("group: net_{$net2->id}", $inboundB);
        $this->assertStringNotContainsString("group: net_{$net1->id}", $inboundB);
        $this->assertStringNotContainsString('host: any', $inboundB);

        $certA = $this->inspectNebulaCertJson((string) $dev1->nebula_crt);
        $certB = $this->inspectNebulaCertJson((string) $dev2->nebula_crt);
        $this->assertStringEndsWith('/8', $this->getCertFirstIp($certA));
        $this->assertStringEndsWith('/8', $this->getCertFirstIp($certB));
    }

    public function testT2F803Slash24IpExhaustionBoundaryRejects254thDevice(): void
    {
        $coA  = $this->createCompany(['max_devices' => 0]);
        $supA = $this->createUser('supervisor', (int) $coA->id);
        $netA = $this->createNetworkDirect((int) $supA->id, (int) $coA->id, ['cidr' => '10.83.0.0/24']);

        $rows = [];
        $now  = date('Y-m-d H:i:s');
        for ($host = 2; $host <= 254; $host++) {
            $rows[] = [
                'network_id'    => $netA->id,
                'name'          => "Seeded-{$host}",
                'ip_address'    => "10.83.0.{$host}",
                'public_key'    => hash('sha256', "seed-{$netA->id}-{$host}"),
                'private_key'   => 'nebula',
                'nebula_crt'    => 'SEED_CRT',
                'nebula_key'    => 'SEED_KEY',
                'nebula_groups' => "net_{$netA->id}",
                'active'        => 1,
                'device_type'   => 'pc',
                'routing_mode'  => 'split',
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }
        $this->db->table('devices')->insertBatch($rows);

        $resp = $this->actingAsUser($supA)->postWithCsrf('devices/store', [
            'network_id'  => (string) $netA->id,
            'name'        => 'Overflow-Node',
            'device_type' => 'pc',
            'expiration'  => 'never',
        ]);

        $this->assertTrue($resp->isRedirect());
        $this->assertNotEmpty(session('error'));
        $this->assertSame(253, $this->db->table('devices')->where('network_id', $netA->id)->countAllResults());
    }

    public function testT2F804LighthouseConfigDownloadValidYamlWithoutWarnings(): void
    {
        $coA  = $this->createCompany();
        $supA = $this->createUser('supervisor', (int) $coA->id);
        $this->actingAsUser($supA)->postWithCsrf('networks/store', [
            'name'     => 'LH-DL-Net',
            'cidr'     => '10.84.0.0',
            'dns'      => '1.1.1.1',
            'owner_id' => (string) $supA->id,
        ]);
        $net = (new NetworkModel())->where('name', 'LH-DL-Net')->first();

        $resp = $this->actingAsUser($supA)->getWithActor("networks/{$net->id}/lighthouse-config");
        $resp->assertStatus(200);
        $this->assertValidNebulaYaml($this->rawBody($resp), ['am_lighthouse: true', 'inbound:']);
    }

    public function testT2F805RotateKeysPreservesSlash8AndNetGroupIsolation(): void
    {
        $coA  = $this->createCompany();
        $supA = $this->createUser('supervisor', (int) $coA->id);
        $this->actingAsUser($supA)->postWithCsrf('networks/store', [
            'name'     => 'Rot-Iso-Net',
            'cidr'     => '10.85.0.0',
            'dns'      => '1.1.1.1',
            'owner_id' => (string) $supA->id,
        ]);
        $net = (new NetworkModel())->where('name', 'Rot-Iso-Net')->first();

        $this->actingAsUser($supA)->postWithCsrf('devices/store', [
            'network_id'  => (string) $net->id,
            'name'        => 'Rot-Iso-Dev',
            'device_type' => 'pc',
            'expiration'  => 'never',
        ]);
        $dev = (new DeviceModel())->where('network_id', $net->id)->first();

        $this->actingAsUser($supA)->postWithCsrf("devices/rotate-keys/{$dev->id}")->assertRedirectTo("devices/show/{$dev->id}");
        $rotated = (new DeviceModel())->find($dev->id);

        $certDetails = $this->inspectNebulaCertJson((string) $rotated->nebula_crt);
        $this->assertStringEndsWith('/8', $this->getCertFirstIp($certDetails));
        $this->assertContains("net_{$net->id}", $certDetails['details']['groups'] ?? []);

        $yaml    = $this->rawBody($this->actingAsUser($supA)->getWithActor("devices/download/{$dev->id}?platform=yml"));
        $inbound = $this->extractYamlInboundBlock($yaml);
        $this->assertStringContainsString("group: net_{$net->id}", $inbound);
        $this->assertStringContainsString('group: lighthouse', $inbound);
    }

    // =========================================================================
    // F9: Audit & Dashboard Boundaries (5 tests)
    // =========================================================================

    public function testT2F901AuditSearchQueryCannotLeakForeignCompanyLogs(): void
    {
        $coA  = $this->createCompany();
        $coB  = $this->createCompany();
        $supA = $this->createUser('supervisor', (int) $coA->id);

        AuditLogModel::log('LOGIN_SUCCESS', 'Alpha Match Event', null, 'alpha_actor', (int) $coA->id);
        AuditLogModel::log('LOGIN_SUCCESS', 'Beta UniqueSecret999 Event', null, 'beta_unique_actor', (int) $coB->id);

        $this->actingAsUser($supA);

        $broadResp = $this->getWithActor('audit-logs?q=LOGIN');
        $broadResp->assertStatus(200);
        $broadResp->assertSee('Alpha Match Event');
        $broadResp->assertDontSee('Beta UniqueSecret999 Event');

        $targetResp = $this->getWithActor('audit-logs?q=UniqueSecret999');
        $targetResp->assertStatus(200);
        $targetResp->assertDontSee('Beta UniqueSecret999 Event');
    }

    public function testT2F902AuditLogsEmptyStateForFreshCompany(): void
    {
        $coC  = $this->createCompany(['name' => 'Brand New C']);
        $supC = $this->createUser('supervisor', (int) $coC->id);

        $resp = $this->actingAsUser($supC)->getWithActor('audit-logs?q=nonexistent_term_xyz');
        $resp->assertStatus(200);
        $resp->assertSee('Sin resultados');
    }

    public function testT2F903DashboardWhenCompanyHasZeroNetworksAndDevices(): void
    {
        $coC  = $this->createCompany(['name' => 'Empty Dash Co']);
        $supC = $this->createUser('supervisor', (int) $coC->id);

        $resp = $this->actingAsUser($supC)->getWithActor('dashboard');
        $resp->assertStatus(200);
        $resp->assertSee('Panel de Administración');
        $resp->assertSee('0');
    }

    public function testT2F904AuditLogModelRecordsExplicitAndImplicitCompanyId(): void
    {
        $coA  = $this->createCompany();
        $supA = $this->createUser('supervisor', (int) $coA->id);
        $sa   = $this->createUser('superadmin', null);

        $this->actingAsGuest();
        auth()->login($sa);
        AuditLogModel::log('SA_PLATFORM', 'Platform wide log');
        AuditLogModel::log('SA_EXPLICIT_CO', 'Explicit company log', (int) $sa->id, $sa->username, (int) $coA->id);
        auth()->logout();

        $this->actingAsGuest();
        auth()->login($supA);
        AuditLogModel::log('SUP_ACTION', 'Supervisor implicit company log');
        auth()->logout();

        $saPlatform = $this->db->table('audit_logs')->where('action', 'SA_PLATFORM')->get()->getRow();
        $saExplicit = $this->db->table('audit_logs')->where('action', 'SA_EXPLICIT_CO')->get()->getRow();
        $supImplicit = $this->db->table('audit_logs')->where('action', 'SUP_ACTION')->get()->getRow();

        $this->assertNull($saPlatform->company_id);
        $this->assertSame((int) $coA->id, (int) $saExplicit->company_id);
        $this->assertSame((int) $coA->id, (int) $supImplicit->company_id);
    }

    public function testT2F905SupervisorBlockedFromSettingsTabsOnlyAuditLogsAllowed(): void
    {
        $coA  = $this->createCompany();
        $supA = $this->createUser('supervisor', (int) $coA->id);

        $this->actingAsUser($supA);
        $this->assertAccessDenied($this->getWithActor('settings/nebula'), 'Ajustes Globales Nebula');
        $this->assertAccessDenied($this->getWithActor('settings?tab=nebula'), 'Ajustes Globales Nebula');
        $this->getWithActor('audit-logs')->assertStatus(200);
    }

    // =========================================================================
    // F10: Views & Visual Fidelity Boundaries (5 tests)
    // =========================================================================

    public function testT2F1001XssPayloadsProperlyEscapedInViews(): void
    {
        $superadmin = $this->createUser('superadmin', null);
        $xssCompany = $this->createCompany([
            'name' => '<img src=x onerror=alert(1)>',
            'slug' => 'xss-co',
        ]);
        $xssNet = $this->createNetworkDirect((int) $superadmin->id, (int) $xssCompany->id, [
            'name' => '<b>Net</b>',
            'cidr' => '10.88.0.0/24',
        ]);
        $this->createDeviceDirect((int) $xssNet->id, [
            'name' => '<script>x</script>',
        ]);

        $this->actingAsUser($superadmin);

        $coHtml = $this->rawBody($this->getWithActor('companies'));
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $coHtml);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $coHtml);

        $netHtml = $this->rawBody($this->getWithActor('networks'));
        $this->assertStringNotContainsString('<b>Net</b>', $netHtml);
        $this->assertStringContainsString('&lt;b&gt;Net&lt;/b&gt;', $netHtml);

        $devHtml = $this->rawBody($this->getWithActor("networks/{$xssNet->id}/devices"));
        $this->assertStringNotContainsString('<script>x</script>', $devHtml);
        $this->assertStringContainsString('&lt;script&gt;x&lt;/script&gt;', $devHtml);
    }

    public function testT2F1002EmptyStatesInPrimaryListViews(): void
    {
        $superadmin = $this->createUser('superadmin', null);
        $coEmpty    = $this->createCompany(['name' => 'Empty Views Co']);
        $supEmpty   = $this->createUser('supervisor', (int) $coEmpty->id);
        $netEmpty   = $this->createNetworkDirect((int) $supEmpty->id, (int) $coEmpty->id, ['cidr' => '10.89.0.0/24']);

        $coHtml = $this->rawBody($this->actingAsUser($superadmin)->getWithActor('companies?q=no_match_zzz'));
        $this->assertStringContainsString('ti-', $coHtml);

        $this->actingAsUser($supEmpty);
        $netHtml = $this->rawBody($this->getWithActor('networks'));
        $devHtml = $this->rawBody($this->getWithActor("networks/{$netEmpty->id}/devices"));

        $this->assertStringContainsString('ti-', $netHtml);
        $this->assertStringContainsString('ti-', $devHtml);
    }

    public function testT2F1003MobileCardMarkupParityAcrossListViews(): void
    {
        $superadmin = $this->createUser('superadmin', null);
        $coA        = $this->createCompany(['name' => 'Parity Co']);
        $usrA       = $this->createUser('user', (int) $coA->id);
        $this->createNetworkDirect((int) $usrA->id, (int) $coA->id, ['cidr' => '10.90.0.0/24']);
        AuditLogModel::log('PARITY_CHECK', 'Parity log entry', (int) $usrA->id, $usrA->username, (int) $coA->id);

        $this->actingAsUser($superadmin);

        foreach (['companies', 'users', 'networks', 'audit-logs', 'dashboard'] as $route) {
            $html = $this->rawBody($this->getWithActor($route));
            $this->assertStringContainsString('d-none d-md-block', $html, "Missing desktop table wrapper on /{$route}.");
            $this->assertStringContainsString('d-block d-md-none', $html, "Missing mobile card wrapper on /{$route}.");
            $this->assertStringContainsString('mobile-card-item', $html, "Missing mobile-card-item on /{$route}.");
        }
    }

    public function testT2F1004SelfEditViewGuardsForSuperadminAndSupervisor(): void
    {
        $coA        = $this->createCompany();
        $superadmin = $this->createUser('superadmin', null);
        $supA       = $this->createUser('supervisor', (int) $coA->id);

        foreach ([$superadmin, $supA] as $actor) {
            $html = $this->rawBody($this->actingAsUser($actor)->getWithActor("users/edit/{$actor->id}"));
            $this->assertStringContainsString('disabled', $html);
            $this->assertStringContainsString('type="hidden" name="group"', $html);
            $this->assertStringContainsString('type="hidden" name="active" value="1"', $html);
        }
    }

    public function testT2F1005SupervisorDashboardOmitsEmpresasCardAndForeignNetworks(): void
    {
        $coA  = $this->createCompany(['name' => 'Co A Own']);
        $coB  = $this->createCompany(['name' => 'Co B Foreign']);
        $supA = $this->createUser('supervisor', (int) $coA->id);
        $usrB = $this->createUser('user', (int) $coB->id);
        $this->createNetworkDirect((int) $usrB->id, (int) $coB->id, ['name' => 'Foreign-Dash-Net', 'cidr' => '10.91.0.0/24']);

        $html = $this->rawBody($this->actingAsUser($supA)->getWithActor('dashboard'));
        $this->assertStringContainsString('col-4', $html);
        $this->assertStringNotContainsString('col-6 col-md-3', $html);
        $this->assertStringNotContainsString('Foreign-Dash-Net', $html);
        $this->assertStringNotContainsString('Co B Foreign', $html);
    }
}
