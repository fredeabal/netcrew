<?php

declare(strict_types=1);

namespace Tests\E2E;

use App\Database\Seeds\UserSeeder;
use App\Libraries\NebulaService;
use App\Models\AuditLogModel;
use App\Models\CompanyModel;
use App\Models\DeviceModel;
use App\Models\NetworkModel;
use Config\AuthGroups;
use Config\Services;

require_once __DIR__ . '/BaseE2ETestCase.php';

class Tier1FeatureCoverageTest extends BaseE2ETestCase
{
    // =========================================================================
    // F1: Multitenant DB Migration, Seeding & Principal Company
    // =========================================================================

    public function testT1F101CleanMigrationCreatesCompaniesTable(): void
    {
        $this->assertTrue($this->db->tableExists('companies'));
        $fields = $this->db->getFieldNames('companies');

        foreach (['id', 'name', 'slug', 'active', 'max_networks', 'max_devices', 'created_at', 'updated_at'] as $col) {
            $this->assertContains($col, $fields, "Expected column '{$col}' in companies table.");
        }
    }

    public function testT1F102MigrationAddsCompanyIdToTenantTables(): void
    {
        foreach (['users', 'networks', 'audit_logs'] as $table) {
            $this->assertTrue($this->db->tableExists($table));
            $this->assertTrue($this->db->fieldExists('company_id', $table), "Missing company_id on {$table}.");

            $fieldData = $this->db->getFieldData($table);
            $matched   = null;
            foreach ($fieldData as $meta) {
                if ($meta->name === 'company_id') {
                    $matched = $meta;
                    break;
                }
            }
            $this->assertNotNull($matched);
            $this->assertTrue((bool) $matched->nullable, "company_id on {$table} must be nullable.");
        }
    }

    public function testT1F103DefaultPrincipalCompanySeeded(): void
    {
        $principal = $this->db->table('companies')->where('slug', 'principal')->get()->getRow();

        $this->assertNotNull($principal);
        $this->assertSame('Principal', $principal->name);
        $this->assertSame(1, (int) $principal->active);
    }

    public function testT1F104AdminCompaniesPermissionSeededForSuperadmin(): void
    {
        $this->seeder->call(UserSeeder::class);
        $this->refreshAuthGroupsConfig();

        $superPerms = $this->db->table('auth_group_permissions')
            ->where('group', 'superadmin')
            ->where('permission', 'admin.companies')
            ->countAllResults();
        $supPerms = $this->db->table('auth_group_permissions')
            ->where('group', 'supervisor')
            ->where('permission', 'admin.companies')
            ->countAllResults();
        $userPerms = $this->db->table('auth_group_permissions')
            ->where('group', 'user')
            ->where('permission', 'admin.companies')
            ->countAllResults();

        $this->assertGreaterThan(0, $superPerms, 'UserSeeder must seed admin.companies for superadmin.');
        $this->assertSame(0, $supPerms);
        $this->assertSame(0, $userPerms);

        $company    = $this->getPrincipalCompany();
        $superadmin = $this->createUser('superadmin', null, ['username' => 'sa_perm_check']);
        $supervisor = $this->createUser('supervisor', (int) $company->id, ['username' => 'sup_perm_check']);
        $stdUser    = $this->createUser('user', (int) $company->id, ['username' => 'usr_perm_check']);

        $this->assertTrue($superadmin->can('admin.companies'));
        $this->assertFalse($supervisor->can('admin.companies'));
        $this->assertFalse($stdUser->can('admin.companies'));
    }

    public function testT1F105SeededSuperadminAuthenticatesViaLoginAndReachesDashboard(): void
    {
        $this->seeder->call(UserSeeder::class);
        $this->refreshAuthGroupsConfig();

        $loginResp = $this->postWithCsrf('login', [
            'email'    => 'admin@demo.com',
            'password' => 'admin1234',
        ]);

        $this->assertTrue($loginResp->isRedirect());
        $this->assertTrue(auth()->loggedIn());

        $this->activeSession = $_SESSION;
        $dashResp            = $this->getWithActor('dashboard');
        $dashResp->assertStatus(200);
        $dashResp->assertSee('Panel de Administración');
    }

    // =========================================================================
    // F2: Companies CRUD, Slugs & Delete Protection
    // =========================================================================

    public function testT1F201SuperadminListsCompaniesWithStats(): void
    {
        $superadmin = $this->createUser('superadmin', null);
        $acme       = $this->createCompany(['name' => 'Acme Networks', 'slug' => 'acme-networks']);
        $userA      = $this->createUser('user', (int) $acme->id);
        $netA       = $this->createNetworkDirect((int) $userA->id, (int) $acme->id);
        $this->createDeviceDirect((int) $netA->id);

        $response = $this->actingAsUser($superadmin)->getWithActor('companies');

        $response->assertStatus(200);
        $response->assertSee('Principal');
        $response->assertSee('Acme Networks');
        $response->assertSee('acme-networks');
    }

    public function testT1F202CreateCompanyWithExplicitSlugAndQuotas(): void
    {
        $superadmin = $this->createUser('superadmin', null);

        $response = $this->actingAsUser($superadmin)->postWithCsrf('companies/store', [
            'name'         => 'Acme Corp',
            'slug'         => 'acme-corp',
            'max_networks' => '5',
            'max_devices'  => '20',
            'active'       => '1',
        ]);

        $response->assertRedirectTo('/companies');

        $row = $this->db->table('companies')->where('slug', 'acme-corp')->get()->getRow();
        $this->assertNotNull($row);
        $this->assertSame('Acme Corp', $row->name);
        $this->assertSame(5, (int) $row->max_networks);
        $this->assertSame(20, (int) $row->max_devices);
        $this->assertSame(1, (int) $row->active);
    }

    public function testT1F203CreateCompanyWithBlankSlugAutoGeneratesSlug(): void
    {
        $superadmin = $this->createUser('superadmin', null);

        $response = $this->actingAsUser($superadmin)->postWithCsrf('companies/store', [
            'name'         => 'Beta Tech Solutions',
            'slug'         => '',
            'max_networks' => '0',
            'max_devices'  => '0',
            'active'       => '1',
        ]);

        $response->assertRedirectTo('/companies');

        $row = $this->db->table('companies')->where('name', 'Beta Tech Solutions')->get()->getRow();
        $this->assertNotNull($row);
        $this->assertSame('beta-tech-solutions', $row->slug);
        $this->assertSame(0, (int) $row->max_networks);
        $this->assertSame(0, (int) $row->max_devices);
    }

    public function testT1F204EditAndUpdateCompanyAttributes(): void
    {
        $superadmin = $this->createUser('superadmin', null);
        $company    = $this->createCompany(['name' => 'Old Co', 'slug' => 'old-co']);

        $editResp = $this->actingAsUser($superadmin)->getWithActor("companies/edit/{$company->id}");
        $editResp->assertStatus(200);
        $editResp->assertSee('Old Co');

        $updateResp = $this->actingAsUser($superadmin)->postWithCsrf("companies/update/{$company->id}", [
            'name'         => 'Acme Global',
            'slug'         => 'acme-global',
            'max_networks' => '10',
            'max_devices'  => '50',
            'active'       => '1',
        ]);

        $updateResp->assertRedirectTo('/companies');

        $updated = $this->db->table('companies')->where('id', $company->id)->get()->getRow();
        $this->assertSame('Acme Global', $updated->name);
        $this->assertSame('acme-global', $updated->slug);
        $this->assertSame(10, (int) $updated->max_networks);
        $this->assertSame(50, (int) $updated->max_devices);

        $listResp = $this->actingAsUser($superadmin)->getWithActor('companies');
        $listResp->assertSee('Acme Global');
    }

    public function testT1F205ToggleActiveAndDeleteEmptyCompany(): void
    {
        $superadmin = $this->createUser('superadmin', null);
        $company    = $this->createCompany(['name' => 'Disposable Co', 'slug' => 'disposable-co', 'active' => 1]);

        $toggleResp = $this->actingAsUser($superadmin)->postWithCsrf("companies/toggle-active/{$company->id}");
        $toggleResp->assertRedirectTo('/companies');

        $afterToggle = $this->db->table('companies')->where('id', $company->id)->get()->getRow();
        $this->assertSame(0, (int) $afterToggle->active);

        $deleteResp = $this->actingAsUser($superadmin)->postWithCsrf("companies/delete/{$company->id}");
        $deleteResp->assertRedirectTo('/companies');

        $afterDelete = $this->db->table('companies')->where('id', $company->id)->get()->getRow();
        $this->assertNull($afterDelete);
    }

    // =========================================================================
    // F3: CompanyActiveFilter (company-active) & Auth Events
    // =========================================================================

    public function testT1F301ActiveCompanySupervisorAccessesAuthenticatedRoutes(): void
    {
        $companyA   = $this->createCompany(['name' => 'Active A', 'active' => 1]);
        $supervisor = $this->createUser('supervisor', (int) $companyA->id);

        $this->actingAsUser($supervisor);

        foreach (['dashboard', 'users', 'networks', 'audit-logs'] as $route) {
            $resp = $this->getWithActor($route);
            $resp->assertStatus(200, "Expected 200 for active supervisor on /{$route}.");
        }
    }

    public function testT1F302ActiveCompanyUserAccessesAllowedRoutes(): void
    {
        $companyA = $this->createCompany(['name' => 'Active User Co', 'active' => 1]);
        $user     = $this->createUser('user', (int) $companyA->id);

        $this->actingAsUser($user);

        foreach (['dashboard', 'networks', 'profile', 'guide'] as $route) {
            $resp = $this->getWithActor($route);
            $resp->assertStatus(200, "Expected 200 for active user on /{$route}.");
        }
    }

    public function testT1F303InactiveCompanySupervisorBlockedByFilter(): void
    {
        $companyA   = $this->createCompany(['name' => 'Suspended A', 'active' => 0]);
        $supervisor = $this->createUser('supervisor', (int) $companyA->id);

        $response = $this->actingAsUser($supervisor)->getWithActor('dashboard');

        $response->assertRedirectTo('/login');
        $this->assertFalse(auth()->loggedIn());
        $this->assertNotEmpty(session('error'));
    }

    public function testT1F304InactiveCompanyUserBlockedOnNetworksAndDevices(): void
    {
        $companyA = $this->createCompany(['name' => 'Suspended B', 'active' => 0]);
        $user     = $this->createUser('user', (int) $companyA->id);
        $net      = $this->createNetworkDirect((int) $user->id, (int) $companyA->id);

        $respNet = $this->actingAsUser($user)->getWithActor('networks');
        $respNet->assertRedirectTo('/login');
        $this->assertFalse(auth()->loggedIn());
        $this->assertNotEmpty(session('error'));

        $respDev = $this->actingAsUser($user)->getWithActor("networks/{$net->id}/devices");
        $respDev->assertRedirectTo('/login');
        $this->assertFalse(auth()->loggedIn());
    }

    public function testT1F305SuperadminExemptFromCompanyActiveFilter(): void
    {
        $inactiveCompany = $this->createCompany(['name' => 'Inactive SA Co', 'active' => 0]);
        $saNull          = $this->createUser('superadmin', null, ['username' => 'sa_null_co']);
        $saInactiveCo    = $this->createUser('superadmin', (int) $inactiveCompany->id, ['username' => 'sa_inact_co']);

        foreach ([$saNull, $saInactiveCo] as $sa) {
            $this->actingAsUser($sa);
            foreach (['dashboard', 'companies', 'settings'] as $route) {
                $resp = $this->getWithActor($route);
                $resp->assertStatus(200, "Superadmin must not be blocked on /{$route}.");
                $this->assertTrue(auth()->loggedIn());
            }
        }
    }

    // =========================================================================
    // F4: Role & Route Authorization Matrix
    // =========================================================================

    public function testT1F401SuperadminAccessesGlobalPlatformRoutes(): void
    {
        $superadmin = $this->createUser('superadmin', null);
        $this->actingAsUser($superadmin);

        foreach (['companies', 'settings', 'settings/nebula', 'roles', 'audit-logs'] as $route) {
            $resp = $this->getWithActor($route);
            $resp->assertStatus(200);
        }

        $settingsHtml = $this->rawBody($this->getWithActor('settings'));
        $this->assertStringContainsString('nebula', strtolower($settingsHtml));
        $this->assertStringContainsString('smtp', strtolower($settingsHtml));
        $this->assertStringContainsString('audit', strtolower($settingsHtml));
        $this->assertStringContainsString('maintenance', strtolower($settingsHtml));
    }

    public function testT1F402SupervisorAllowedCompanyAdminRoutes(): void
    {
        $companyA   = $this->createCompany();
        $supervisor = $this->createUser('supervisor', (int) $companyA->id);

        $this->actingAsUser($supervisor);

        foreach (['dashboard', 'users', 'networks', 'audit-logs'] as $route) {
            $resp = $this->getWithActor($route);
            $resp->assertStatus(200);
        }
    }

    public function testT1F403SupervisorDeniedSettingsRolesCompanies(): void
    {
        $companyA   = $this->createCompany();
        $supervisor = $this->createUser('supervisor', (int) $companyA->id);

        $this->actingAsUser($supervisor);

        $this->assertAccessDenied($this->getWithActor('settings'), 'Configuración SMTP', 'Ajustes Globales Nebula');
        $this->assertAccessDenied($this->getWithActor('settings/smtp'), 'Servidor SMTP');
        $this->assertAccessDenied($this->getWithActor('roles'), 'Gestión de Roles y Permisos');
        $this->assertAccessDenied($this->getWithActor('companies'), 'Nueva Empresa');
    }

    public function testT1F404UserDeniedAdminAndAuditRoutes(): void
    {
        $companyA = $this->createCompany();
        $user     = $this->createUser('user', (int) $companyA->id);

        $this->actingAsUser($user);

        $this->assertAccessDenied($this->getWithActor('users'), 'Gestión de Usuarios');
        $this->assertAccessDenied($this->getWithActor('audit-logs'), 'Registro de Auditoría');
        $this->assertAccessDenied($this->getWithActor('roles'), 'Gestión de Roles y Permisos');
        $this->assertAccessDenied($this->getWithActor('settings'), 'Configuración');
        $this->assertAccessDenied($this->getWithActor('companies'), 'Empresas');
    }

    public function testT1F405RoutesIncludeRequiredMultitenantFilters(): void
    {
        $routes = service('routes', false);
        $routes->loadRoutes();

        $allGetRoutes  = $routes->getRoutes('GET');
        $allPostRoutes = $routes->getRoutes('POST');

        $this->assertArrayHasKey('companies', $allGetRoutes);
        $this->assertArrayHasKey('companies/store', $allPostRoutes);

        $companiesFilters = $routes->getRoutesOptions('companies', 'GET')['filter'] ?? [];
        $companiesFilters = is_array($companiesFilters) ? $companiesFilters : [$companiesFilters];
        $this->assertTrue(
            in_array('permission:admin.companies', $companiesFilters, true),
            'GET companies must be protected with permission:admin.companies.'
        );
        $this->assertTrue(
            in_array('company-active', $companiesFilters, true),
            'GET companies must include company-active filter.'
        );

        $dashboardFilters = $routes->getRoutesOptions('dashboard', 'GET')['filter'] ?? [];
        $dashboardFilters = is_array($dashboardFilters) ? $dashboardFilters : [$dashboardFilters];
        $this->assertTrue(
            in_array('company-active', $dashboardFilters, true),
            'GET dashboard must include company-active filter.'
        );

        $settingsFilters = $routes->getRoutesOptions('settings', 'GET')['filter'] ?? [];
        $settingsFilters = is_array($settingsFilters) ? $settingsFilters : [$settingsFilters];
        $this->assertTrue(
            in_array('group:superadmin', $settingsFilters, true),
            'settings routes must be restricted to group:superadmin.'
        );

        $rolesFilters = $routes->getRoutesOptions('roles', 'GET')['filter'] ?? [];
        $rolesFilters = is_array($rolesFilters) ? $rolesFilters : [$rolesFilters];
        $this->assertTrue(
            in_array('group:superadmin', $rolesFilters, true),
            'roles routes must be restricted to group:superadmin.'
        );

        $auditFilters = $routes->getRoutesOptions('audit-logs', 'GET')['filter'] ?? [];
        $auditFilters = is_array($auditFilters) ? $auditFilters : [$auditFilters];
        $this->assertTrue(
            in_array('group:superadmin,supervisor', $auditFilters, true),
            'audit-logs route must allow group:superadmin,supervisor.'
        );
    }

    // =========================================================================
    // F5: Company-Scoped User Management
    // =========================================================================

    public function testT1F501SuperadminListsAllUsersWithCompanyName(): void
    {
        $companyA   = $this->createCompany(['name' => 'Alpha Tenant']);
        $companyB   = $this->createCompany(['name' => 'Beta Tenant']);
        $superadmin = $this->createUser('superadmin', null, ['username' => 'globalboss']);
        $this->createUser('user', (int) $companyA->id, ['username' => 'alice_alpha']);
        $this->createUser('user', (int) $companyB->id, ['username' => 'bob_beta']);

        $response = $this->actingAsUser($superadmin)->getWithActor('users');

        $response->assertStatus(200);
        $response->assertSee('alice_alpha');
        $response->assertSee('bob_beta');
        $response->assertSee('Alpha Tenant');
        $response->assertSee('Beta Tenant');
    }

    public function testT1F502SuperadminCreatesSupervisorInCompanyB(): void
    {
        $superadmin = $this->createUser('superadmin', null);
        $companyB   = $this->createCompany(['name' => 'Beta Co']);

        $response = $this->actingAsUser($superadmin)->postWithCsrf('users/store', [
            'username'     => 'supb',
            'email'        => 'supb@b.com',
            'phone'        => '+34600111222',
            'group'        => 'supervisor',
            'company_id'   => (string) $companyB->id,
            'password'     => 'Str0ng!Pass1',
            'pass_confirm' => 'Str0ng!Pass1',
            'active'       => '1',
        ]);

        $response->assertRedirectTo('/users');

        $created = auth()->getProvider()->findByCredentials(['email' => 'supb@b.com']);
        $this->assertNotNull($created);
        $this->assertSame((int) $companyB->id, (int) $created->company_id);
        $this->assertTrue($created->inGroup('supervisor'));
    }

    public function testT1F503SupervisorListsStrictlyOwnCompanyUsers(): void
    {
        $companyA    = $this->createCompany(['name' => 'Tenant A']);
        $companyB    = $this->createCompany(['name' => 'Tenant B']);
        $superadmin  = $this->createUser('superadmin', null, ['username' => 'sa_hidden']);
        $supervisorA = $this->createUser('supervisor', (int) $companyA->id, ['username' => 'sup_a_own']);
        $userA       = $this->createUser('user', (int) $companyA->id, ['username' => 'user_a_own']);
        $userB       = $this->createUser('user', (int) $companyB->id, ['username' => 'user_b_foreign']);

        $response = $this->actingAsUser($supervisorA)->getWithActor('users');

        $response->assertStatus(200);
        $response->assertSee('user_a_own');
        $response->assertDontSee('user_b_foreign');
        $response->assertDontSee('sa_hidden');
    }

    public function testT1F504SupervisorCreatesStandardUserInOwnCompany(): void
    {
        $companyA    = $this->createCompany(['name' => 'Tenant A']);
        $supervisorA = $this->createUser('supervisor', (int) $companyA->id);

        $response = $this->actingAsUser($supervisorA)->postWithCsrf('users/store', [
            'username'     => 'usera2',
            'email'        => 'usera2@a.com',
            'phone'        => '+34600222333',
            'group'        => 'user',
            'password'     => 'Str0ng!Pass1',
            'pass_confirm' => 'Str0ng!Pass1',
            'active'       => '1',
        ]);

        $response->assertRedirectTo('/users');

        $created = auth()->getProvider()->findByCredentials(['email' => 'usera2@a.com']);
        $this->assertNotNull($created);
        $this->assertSame((int) $companyA->id, (int) $created->company_id);
        $this->assertTrue($created->inGroup('user'));
    }

    public function testT1F505SupervisorEditsTogglesAndDeletesOwnCompanyUser(): void
    {
        $companyA    = $this->createCompany();
        $supervisorA = $this->createUser('supervisor', (int) $companyA->id);
        $targetUser  = $this->createUser('user', (int) $companyA->id, ['username' => 'editable_a']);

        $this->actingAsUser($supervisorA);

        $updateResp = $this->postWithCsrf("users/update/{$targetUser->id}", [
            'username'   => 'editedA',
            'email'      => $targetUser->email,
            'phone'      => '+34611222333',
            'group'      => 'user',
            'company_id' => (string) $companyA->id,
            'active'     => '1',
        ]);
        $updateResp->assertRedirectTo('/users');

        $reloaded = auth()->getProvider()->findById($targetUser->id);
        $this->assertSame('editedA', $reloaded->username);

        $toggleResp = $this->postWithCsrf("users/toggle-active/{$targetUser->id}");
        $toggleResp->assertRedirectTo('/users');
        $this->assertTrue(auth()->getProvider()->findById($targetUser->id)->isBanned());

        $deleteResp = $this->postWithCsrf("users/delete/{$targetUser->id}");
        $deleteResp->assertRedirectTo('/users');
        $this->assertNull(auth()->getProvider()->findById($targetUser->id));
    }

    // =========================================================================
    // F6: Company-Scoped Network Management, CIDR & Quotas
    // =========================================================================

    public function testT1F601SuperadminCreatesNetworkAssignedToCompanyB(): void
    {
        $superadmin = $this->createUser('superadmin', null);
        $companyB   = $this->createCompany(['name' => 'Beta Nets']);
        $userB      = $this->createUser('user', (int) $companyB->id);

        $response = $this->actingAsUser($superadmin)->postWithCsrf('networks/store', [
            'name'       => 'Net-B1',
            'cidr'       => '10.20.0.0',
            'dns'        => '1.1.1.1',
            'owner_id'   => (string) $userB->id,
            'company_id' => (string) $companyB->id,
        ]);

        $response->assertRedirectTo('/networks');

        $net = (new NetworkModel())->where('name', 'Net-B1')->first();
        $this->assertNotNull($net);
        $this->assertSame((int) $companyB->id, (int) $net->company_id);
        $this->assertSame('10.20.0.0/24', $net->cidr);
        $this->assertSame((int) $userB->id, (int) $net->owner_id);
    }

    public function testT1F602SupervisorListsAndCreatesNetworkInOwnCompany(): void
    {
        $companyA    = $this->createCompany(['name' => 'Co A']);
        $companyB    = $this->createCompany(['name' => 'Co B']);
        $supervisorA = $this->createUser('supervisor', (int) $companyA->id);
        $userA       = $this->createUser('user', (int) $companyA->id);
        $userB       = $this->createUser('user', (int) $companyB->id);

        $this->createNetworkDirect((int) $userA->id, (int) $companyA->id, ['name' => 'Visible-Net-A', 'cidr' => '10.21.0.0/24']);
        $this->createNetworkDirect((int) $userB->id, (int) $companyB->id, ['name' => 'Hidden-Net-B', 'cidr' => '10.22.0.0/24']);

        $this->actingAsUser($supervisorA);

        $listResp = $this->getWithActor('networks');
        $listResp->assertStatus(200);
        $listResp->assertSee('Visible-Net-A');
        $listResp->assertDontSee('Hidden-Net-B');

        $storeResp = $this->postWithCsrf('networks/store', [
            'name'     => 'Net-A-New',
            'cidr'     => '10.23.0.0',
            'dns'      => '1.1.1.1',
            'owner_id' => (string) $userA->id,
        ]);
        $storeResp->assertRedirectTo('/networks');

        $created = (new NetworkModel())->where('name', 'Net-A-New')->first();
        $this->assertNotNull($created);
        $this->assertSame((int) $companyA->id, (int) $created->company_id);
    }

    public function testT1F603UserSeesOnlyOwnNetworksInOwnCompany(): void
    {
        $companyA = $this->createCompany();
        $companyB = $this->createCompany();
        $userA1   = $this->createUser('user', (int) $companyA->id);
        $userA2   = $this->createUser('user', (int) $companyA->id);
        $userB1   = $this->createUser('user', (int) $companyB->id);

        $this->createNetworkDirect((int) $userA1->id, (int) $companyA->id, ['name' => 'Owned-By-A1', 'cidr' => '10.24.0.0/24']);
        $this->createNetworkDirect((int) $userA2->id, (int) $companyA->id, ['name' => 'Owned-By-A2', 'cidr' => '10.25.0.0/24']);
        $this->createNetworkDirect((int) $userB1->id, (int) $companyB->id, ['name' => 'Owned-By-B1', 'cidr' => '10.26.0.0/24']);

        $resp = $this->actingAsUser($userA1)->getWithActor('networks');
        $resp->assertStatus(200);
        $resp->assertSee('Owned-By-A1');
        $resp->assertDontSee('Owned-By-A2');
        $resp->assertDontSee('Owned-By-B1');
    }

    public function testT1F604SuggestNextCidrReturnsNonCollidingSubnet(): void
    {
        $companyA    = $this->createCompany();
        $supervisorA = $this->createUser('supervisor', (int) $companyA->id);
        $this->createNetworkDirect((int) $supervisorA->id, (int) $companyA->id, ['cidr' => '10.1.0.0/24']);

        $suggested = (new NetworkModel())->suggestNextCidr();
        $this->assertMatchesRegularExpression('/^10\.\d+\.\d+\.0$/', $suggested);
        $this->assertNotSame('10.1.0.0', $suggested);
        $this->assertNotSame('10.0.0.0', $suggested);

        $createResp = $this->actingAsUser($supervisorA)->getWithActor('networks/create');
        $createResp->assertStatus(200);
        $createResp->assertSee($suggested);
    }

    public function testT1F605SupervisorUpdatesTogglesDownloadsLighthouseAndDeletesOwnNetwork(): void
    {
        $companyA    = $this->createCompany();
        $supervisorA = $this->createUser('supervisor', (int) $companyA->id);
        $userA       = $this->createUser('user', (int) $companyA->id);
        $netA        = $this->createNetworkDirect((int) $userA->id, (int) $companyA->id, [
            'name' => 'Net-Lifecycle',
            'cidr' => '10.27.0.0/24',
        ]);

        $this->actingAsUser($supervisorA);

        $updResp = $this->postWithCsrf("networks/update/{$netA->id}", [
            'name'     => 'Net-Lifecycle-Updated',
            'cidr'     => '10.27.0.0',
            'dns'      => '8.8.8.8',
            'owner_id' => (string) $userA->id,
            'active'   => '1',
        ]);
        $updResp->assertRedirectTo('/networks');
        $this->assertSame('Net-Lifecycle-Updated', (new NetworkModel())->find($netA->id)->name);

        $togResp = $this->postWithCsrf("networks/toggle-active/{$netA->id}");
        $togResp->assertRedirectTo('/networks');
        $this->assertSame(0, (int) (new NetworkModel())->find($netA->id)->active);

        $lhResp = $this->getWithActor("networks/{$netA->id}/lighthouse-config");
        $lhResp->assertStatus(200);
        $this->assertValidNebulaYaml($this->rawBody($lhResp), ['am_lighthouse: true']);

        $delResp = $this->postWithCsrf("networks/delete/{$netA->id}");
        $delResp->assertRedirectTo('/networks');
        $this->assertNull((new NetworkModel())->find($netA->id));
    }

    // =========================================================================
    // F7: Scoped Device Management, Key Rotation & Quotas
    // =========================================================================

    public function testT1F701CreateDeviceInAuthorizedNetwork(): void
    {
        $companyA    = $this->createCompany();
        $supervisorA = $this->createUser('supervisor', (int) $companyA->id);
        $netA        = $this->createNetworkDirect((int) $supervisorA->id, (int) $companyA->id, ['cidr' => '10.30.0.0/24']);

        $resp = $this->actingAsUser($supervisorA)->postWithCsrf('devices/store', [
            'network_id'  => (string) $netA->id,
            'name'        => 'Laptop-1',
            'device_type' => 'pc',
            'expiration'  => 'never',
        ]);

        $resp->assertRedirectTo("/networks/{$netA->id}/devices");

        $device = (new DeviceModel())->where('network_id', $netA->id)->where('name', 'Laptop-1')->first();
        $this->assertNotNull($device);
        $this->assertStringStartsWith('10.30.0.', $device->ip_address);
        $this->assertNotEmpty($device->public_key);
        $this->assertStringContainsString('BEGIN NEBULA CERTIFICATE', (string) $device->nebula_crt);
        $this->assertNotEmpty($device->nebula_key);
    }

    public function testT1F702ListShowAndEditDevice(): void
    {
        $companyA = $this->createCompany();
        $userA    = $this->createUser('user', (int) $companyA->id);
        $netA     = $this->createNetworkDirect((int) $userA->id, (int) $companyA->id, ['cidr' => '10.31.0.0/24']);

        $this->actingAsUser($userA)->postWithCsrf('devices/store', [
            'network_id'  => (string) $netA->id,
            'name'        => 'Node-Orig',
            'device_type' => 'pc',
            'expiration'  => 'never',
        ]);
        $dev = (new DeviceModel())->where('network_id', $netA->id)->first();

        $listResp = $this->actingAsUser($userA)->getWithActor("networks/{$netA->id}/devices");
        $listResp->assertStatus(200);
        $listResp->assertSee('Node-Orig');

        $showResp = $this->actingAsUser($userA)->getWithActor("devices/show/{$dev->id}");
        $showResp->assertStatus(200);
        $showResp->assertSee('Node-Orig');

        $updResp = $this->actingAsUser($userA)->postWithCsrf("devices/update/{$dev->id}", [
            'name'        => 'Laptop-Renamed',
            'device_type' => 'server',
            'expiration'  => '7d',
        ]);
        $updResp->assertRedirectTo("/devices/show/{$dev->id}");

        $updated = (new DeviceModel())->find($dev->id);
        $this->assertSame('Laptop-Renamed', $updated->name);
        $this->assertSame('server', $updated->device_type);
        $this->assertNotEmpty($updated->expires_at);
        $daysDiff = (strtotime($updated->expires_at) - time()) / 86400;
        $this->assertGreaterThan(6.0, $daysDiff);
        $this->assertLessThan(7.5, $daysDiff);
    }

    public function testT1F703RotateKeysRegeneratesKeypairAndCert(): void
    {
        $companyA = $this->createCompany();
        $userA    = $this->createUser('user', (int) $companyA->id);
        $netA     = $this->createNetworkDirect((int) $userA->id, (int) $companyA->id, ['cidr' => '10.32.0.0/24']);

        $this->actingAsUser($userA)->postWithCsrf('devices/store', [
            'network_id'  => (string) $netA->id,
            'name'        => 'Rotate-Node',
            'device_type' => 'pc',
            'expiration'  => 'never',
        ]);
        $before = (new DeviceModel())->where('network_id', $netA->id)->first();

        $rotResp = $this->actingAsUser($userA)->postWithCsrf("devices/rotate-keys/{$before->id}");
        $rotResp->assertRedirectTo("devices/show/{$before->id}");

        $after = (new DeviceModel())->find($before->id);
        $this->assertNotSame($before->public_key, $after->public_key);
        $this->assertNotSame($before->nebula_crt, $after->nebula_crt);

        $auditCount = $this->db->table('audit_logs')
            ->where('action', 'DEVICE_ROTATE_KEYS')
            ->countAllResults();
        $this->assertGreaterThan(0, $auditCount);
    }

    public function testT1F704ToggleActiveAndDownloadConfig(): void
    {
        $companyA = $this->createCompany();
        $userA    = $this->createUser('user', (int) $companyA->id);
        $netA     = $this->createNetworkDirect((int) $userA->id, (int) $companyA->id, ['cidr' => '10.33.0.0/24']);

        $this->actingAsUser($userA)->postWithCsrf('devices/store', [
            'network_id'  => (string) $netA->id,
            'name'        => 'Download-Node',
            'device_type' => 'pc',
            'expiration'  => 'never',
        ]);
        $dev = (new DeviceModel())->where('network_id', $netA->id)->first();

        $this->actingAsUser($userA)->postWithCsrf("devices/toggle-active/{$dev->id}");
        $this->assertSame(0, (int) (new DeviceModel())->find($dev->id)->active);

        $this->actingAsUser($userA)->postWithCsrf("devices/toggle-active/{$dev->id}");
        $this->assertSame(1, (int) (new DeviceModel())->find($dev->id)->active);

        $dlResp = $this->actingAsUser($userA)->getWithActor("devices/download/{$dev->id}?platform=yml");
        $dlResp->assertStatus(200);
        $this->assertValidNebulaYaml($this->rawBody($dlResp), ['pki:', 'lighthouse:', 'firewall:']);
    }

    public function testT1F705GenerateOneTimeShareLinkAndConsumePublicly(): void
    {
        $companyA = $this->createCompany();
        $userA    = $this->createUser('user', (int) $companyA->id);
        $netA     = $this->createNetworkDirect((int) $userA->id, (int) $companyA->id, ['cidr' => '10.34.0.0/24']);

        $this->actingAsUser($userA)->postWithCsrf('devices/store', [
            'network_id'  => (string) $netA->id,
            'name'        => 'Share-Node',
            'device_type' => 'pc',
            'expiration'  => 'never',
        ]);
        $dev = (new DeviceModel())->where('network_id', $netA->id)->first();

        $genResp = $this->actingAsUser($userA)->postWithCsrf("devices/generate-share-link/{$dev->id}");
        $genResp->assertStatus(200);
        $json = json_decode($genResp->getJSON(), true);
        $this->assertTrue((bool) ($json['success'] ?? false));
        $token = (string) ($json['token'] ?? '');
        $this->assertNotSame('', $token);

        $this->actingAsGuest();
        $connectResp = $this->getWithActor("connect/{$token}");
        $connectResp->assertStatus(200);

        $dlResp = $this->getWithActor("connect/{$token}/download?platform=yml");
        $dlResp->assertStatus(200);
        $this->assertValidNebulaYaml($this->rawBody($dlResp), ['pki:', 'firewall:']);
    }

    // =========================================================================
    // F8: Nebula Global CA /8, net_<id> Isolation & buildLighthouseConfig()
    // =========================================================================

    public function testT1F801GetOrCreateGlobalCaIsIdempotent(): void
    {
        $first  = NebulaService::getOrCreateGlobalCa();
        $second = NebulaService::getOrCreateGlobalCa();

        $this->assertStringContainsString('BEGIN NEBULA CERTIFICATE', $first['crt']);
        $this->assertStringContainsString('BEGIN NEBULA', $first['key']);
        $this->assertSame(trim($first['crt']), trim($second['crt']));
        $this->assertSame(trim($first['key']), trim($second['key']));

        $settings = service('settings');
        $this->assertSame(trim($first['crt']), trim((string) $settings->get('Nebula.globalCaCrt')));
        $this->assertNotEmpty($settings->get('Nebula.globalCaKey'));
    }

    public function testT1F802GetNetworkGroupNameFormatting(): void
    {
        $this->assertSame('net_42', NebulaService::getNetworkGroupName(42));
        $this->assertSame('net_1', NebulaService::getNetworkGroupName(1));
    }

    public function testT1F803BuildLighthouseConfigValidYamlWithoutUndefinedIsolatedGroup(): void
    {
        $globalCa = NebulaService::getOrCreateGlobalCa();
        $lhCert   = NebulaService::signDeviceCert(
            $globalCa['crt'],
            $globalCa['key'],
            'netcrew-lighthouse-global',
            '10.0.0.1/8',
            'lighthouse'
        );

        $virtualNet = (object) [
            'id'            => 0,
            'name'          => 'NetCrew Global',
            'cidr'          => '10.0.0.0/8',
            'nebula_ca_crt' => $globalCa['crt'],
        ];

        $yaml = NebulaService::buildLighthouseConfig($virtualNet, $lhCert['crt'], $lhCert['key']);

        $this->assertValidNebulaYaml($yaml, [
            'am_lighthouse: true',
            'dev: nebula1',
            'inbound:',
        ]);
    }

    public function testT1F804DeviceCertSignedWithGlobalCaUsesSlash8AndNetGroup(): void
    {
        $companyA    = $this->createCompany();
        $supervisorA = $this->createUser('supervisor', (int) $companyA->id);

        $this->actingAsUser($supervisorA)->postWithCsrf('networks/store', [
            'name'     => 'Global-CA-Net',
            'cidr'     => '10.40.0.0',
            'dns'      => '1.1.1.1',
            'owner_id' => (string) $supervisorA->id,
        ]);
        $net = (new NetworkModel())->where('name', 'Global-CA-Net')->first();
        $this->assertNotNull($net);
        $this->assertTrue(NebulaService::usesGlobalCa($net));

        $this->actingAsUser($supervisorA)->postWithCsrf('devices/store', [
            'network_id'  => (string) $net->id,
            'name'        => 'Slash8-Node',
            'device_type' => 'pc',
            'expiration'  => 'never',
        ]);
        $dev = (new DeviceModel())->where('network_id', $net->id)->first();
        $this->assertNotNull($dev);

        $certDetails = $this->inspectNebulaCertJson((string) $dev->nebula_crt);
        $firstIp     = $this->getCertFirstIp($certDetails);
        $groups      = $certDetails['details']['groups'] ?? [];

        $this->assertNotSame('', $firstIp);
        $this->assertStringEndsWith('/8', $firstIp, 'Global CA device cert must use /8 mask.');
        $this->assertContains('net_' . $net->id, $groups, 'Device cert must belong to net_<network_id> group.');
    }

    public function testT1F805ClientYamlFirewallInboundEnforcesNetGroupAndLighthouse(): void
    {
        $companyA    = $this->createCompany();
        $supervisorA = $this->createUser('supervisor', (int) $companyA->id);

        $this->actingAsUser($supervisorA)->postWithCsrf('networks/store', [
            'name'     => 'Isolated-Net',
            'cidr'     => '10.41.0.0',
            'dns'      => '1.1.1.1',
            'owner_id' => (string) $supervisorA->id,
        ]);
        $net = (new NetworkModel())->where('name', 'Isolated-Net')->first();

        $this->actingAsUser($supervisorA)->postWithCsrf('devices/store', [
            'network_id'  => (string) $net->id,
            'name'        => 'Isolated-Node',
            'device_type' => 'pc',
            'expiration'  => 'never',
        ]);
        $dev = (new DeviceModel())->where('network_id', $net->id)->first();

        $yaml = $this->rawBody(
            $this->actingAsUser($supervisorA)->getWithActor("devices/download/{$dev->id}?platform=yml")
        );

        $this->assertValidNebulaYaml($yaml);
        $inbound = $this->extractYamlInboundBlock($yaml);
        $expectedGroup = 'net_' . $net->id;

        $this->assertStringContainsString("group: {$expectedGroup}", $inbound);
        $this->assertStringContainsString('proto: icmp', $inbound);
        $this->assertStringContainsString('proto: any', $inbound);
        $this->assertStringContainsString('group: lighthouse', $inbound);
        $this->assertStringNotContainsString('host: any', $inbound);
    }

    // =========================================================================
    // F9: Scoped Audit Logging & Dashboard Metrics
    // =========================================================================

    public function testT1F901AuthEventsRecordUsersCompanyId(): void
    {
        $companyA = $this->createCompany(['name' => 'Audit Co A']);
        $userA    = $this->createUser('user', (int) $companyA->id, [
            'username' => 'audit_login_user',
            'email'    => 'audit_login@a.test',
            'password' => 'Str0ng!Pass123',
        ]);

        $this->actingAsGuest();
        $this->postWithCsrf('login', [
            'email'    => 'audit_login@a.test',
            'password' => 'Str0ng!Pass123',
        ]);

        $this->activeSession = $_SESSION;
        $this->getWithActor('logout');

        $loginRow = $this->db->table('audit_logs')
            ->where('user_id', $userA->id)
            ->where('action', 'LOGIN_SUCCESS')
            ->get()
            ->getRow();
        $logoutRow = $this->db->table('audit_logs')
            ->where('user_id', $userA->id)
            ->where('action', 'LOGOUT')
            ->get()
            ->getRow();

        $this->assertNotNull($loginRow);
        $this->assertSame((int) $companyA->id, (int) $loginRow->company_id);
        $this->assertNotNull($logoutRow);
        $this->assertSame((int) $companyA->id, (int) $logoutRow->company_id);
    }

    public function testT1F902SuperadminSeesGlobalAuditLogs(): void
    {
        $companyA   = $this->createCompany();
        $companyB   = $this->createCompany();
        $superadmin = $this->createUser('superadmin', null);

        AuditLogModel::log('TEST_ACTION_A', 'Evento Tenant Alpha', null, 'alpha_actor', (int) $companyA->id);
        AuditLogModel::log('TEST_ACTION_B', 'Evento Tenant Beta', null, 'beta_actor', (int) $companyB->id);
        AuditLogModel::log('TEST_ACTION_SYS', 'Evento Global Platform', null, 'sys_actor', null);

        $auditResp = $this->actingAsUser($superadmin)->getWithActor('audit-logs');
        $auditResp->assertStatus(200);
        $auditResp->assertSee('Evento Tenant Alpha');
        $auditResp->assertSee('Evento Tenant Beta');
        $auditResp->assertSee('Evento Global Platform');

        $settingsResp = $this->actingAsUser($superadmin)->getWithActor('settings?tab=audit');
        $settingsResp->assertStatus(200);
        $settingsResp->assertSee('Evento Tenant Alpha');
        $settingsResp->assertSee('Evento Tenant Beta');
    }

    public function testT1F903SupervisorSeesStrictlyOwnCompanyAuditLogs(): void
    {
        $companyA    = $this->createCompany(['name' => 'Audit A']);
        $companyB    = $this->createCompany(['name' => 'Audit B']);
        $supervisorA = $this->createUser('supervisor', (int) $companyA->id);

        AuditLogModel::log('NET_A_EVENT', 'Evento Exclusivo Empresa A', null, 'user_a', (int) $companyA->id);
        AuditLogModel::log('NET_B_EVENT', 'Evento Secreto Empresa B', null, 'user_b', (int) $companyB->id);
        AuditLogModel::log('PLATFORM_EVENT', 'Evento Plataforma Global', null, 'sa_global', null);

        $resp = $this->actingAsUser($supervisorA)->getWithActor('audit-logs');
        $resp->assertStatus(200);
        $resp->assertSee('Evento Exclusivo Empresa A');
        $resp->assertSee('audit-logs');
        $resp->assertDontSee('Evento Secreto Empresa B');
        $resp->assertDontSee('Evento Plataforma Global');
        $resp->assertDontSee('Servidor SMTP');
    }

    public function testT1F904DashboardMetricsForSuperadmin(): void
    {
        $superadmin = $this->createUser('superadmin', null);
        $companyA   = $this->createCompany(['name' => 'DashCo A']);
        $userA      = $this->createUser('user', (int) $companyA->id);
        $netA       = $this->createNetworkDirect((int) $userA->id, (int) $companyA->id, ['name' => 'Dash-Net-A']);
        $this->createDeviceDirect((int) $netA->id);

        $resp = $this->actingAsUser($superadmin)->getWithActor('dashboard');
        $resp->assertStatus(200);
        $resp->assertSee('Empresas');
        $resp->assertSee('Usuarios');
        $resp->assertSee('Redes VPN');
        $resp->assertSee('Dispositivos');
        $resp->assertSee('Dash-Net-A');
        $resp->assertSee('DashCo A');
    }

    public function testT1F905DashboardMetricsForSupervisorAndUser(): void
    {
        $companyA    = $this->createCompany(['name' => 'Dash Co A']);
        $companyB    = $this->createCompany(['name' => 'Dash Co B']);
        $supervisorA = $this->createUser('supervisor', (int) $companyA->id);
        $userA       = $this->createUser('user', (int) $companyA->id);
        $userB       = $this->createUser('user', (int) $companyB->id);

        $netA = $this->createNetworkDirect((int) $userA->id, (int) $companyA->id, ['name' => 'Dash-A-Net', 'cidr' => '10.42.0.0/24']);
        $this->createNetworkDirect((int) $userB->id, (int) $companyB->id, ['name' => 'Dash-B-Net', 'cidr' => '10.43.0.0/24']);
        $this->createDeviceDirect((int) $netA->id);

        $supResp = $this->actingAsUser($supervisorA)->getWithActor('dashboard');
        $supResp->assertStatus(200);
        $supResp->assertSee('Panel de Administración');
        $supResp->assertSee('Dash-A-Net');
        $supResp->assertDontSee('Dash-B-Net');
        $supResp->assertDontSee('Dash Co B');

        $usrResp = $this->actingAsUser($userA)->getWithActor('dashboard');
        $usrResp->assertStatus(200);
        $usrResp->assertSee('Dash-A-Net');
        $usrResp->assertDontSee('Dash-B-Net');
        $usrResp->assertDontSee('Panel de Administración');
    }

    // =========================================================================
    // F10: Multitenant Views, Sidebar & Bootstrap 5 / Modernize Visual Fidelity
    // =========================================================================

    public function testT1F1001SidebarNavigationVisibilityPerRole(): void
    {
        $companyA    = $this->createCompany();
        $superadmin  = $this->createUser('superadmin', null);
        $supervisorA = $this->createUser('supervisor', (int) $companyA->id);
        $userA       = $this->createUser('user', (int) $companyA->id);

        $saHtml = $this->rawBody($this->actingAsUser($superadmin)->getWithActor('dashboard'));
        $this->assertStringContainsString('Empresas', $saHtml);
        $this->assertStringContainsString('Usuarios', $saHtml);
        $this->assertStringContainsString('Roles', $saHtml);
        $this->assertStringContainsString('Configuración', $saHtml);
        $this->assertStringContainsString('Redes', $saHtml);

        $supHtml = $this->rawBody($this->actingAsUser($supervisorA)->getWithActor('dashboard'));
        $this->assertStringContainsString('Redes', $supHtml);
        $this->assertStringContainsString('Usuarios', $supHtml);
        $this->assertStringContainsString('Auditoría', $supHtml);
        $this->assertStringNotContainsString(site_url('companies'), $supHtml);
        $this->assertStringNotContainsString(site_url('roles'), $supHtml);
        $this->assertStringNotContainsString(site_url('settings'), $supHtml);

        $usrHtml = $this->rawBody($this->actingAsUser($userA)->getWithActor('dashboard'));
        $this->assertStringContainsString('Dashboard', $usrHtml);
        $this->assertStringContainsString('Redes', $usrHtml);
        $this->assertStringNotContainsString(site_url('users'), $usrHtml);
        $this->assertStringNotContainsString(site_url('companies'), $usrHtml);
        $this->assertStringNotContainsString(site_url('audit-logs'), $usrHtml);
    }

    public function testT1F1002CompaniesViewsModernizeStructure(): void
    {
        $superadmin = $this->createUser('superadmin', null);
        $company    = $this->createCompany(['name' => 'UI Co']);

        $this->actingAsUser($superadmin);

        $listHtml = $this->rawBody($this->getWithActor('companies'));
        $this->assertStringContainsString('card border shadow-none', $listHtml);
        $this->assertStringContainsString('breadcrumb', $listHtml);
        $this->assertStringContainsString('d-none d-md-block', $listHtml);
        $this->assertStringContainsString('net-table', $listHtml);
        $this->assertStringContainsString('d-block d-md-none', $listHtml);
        $this->assertStringContainsString('mobile-card-item', $listHtml);
        $this->assertStringContainsString('data-row-href', $listHtml);
        $this->assertStringContainsString('data-confirm', $listHtml);
        $this->assertStringContainsString('csrf_test_name', $listHtml);

        $createHtml = $this->rawBody($this->getWithActor('companies/create'));
        $this->assertStringContainsString('csrf_test_name', $createHtml);
        $this->assertStringContainsString('name="slug"', $createHtml);

        $editHtml = $this->rawBody($this->getWithActor("companies/edit/{$company->id}"));
        $this->assertStringContainsString('csrf_test_name', $editHtml);
        $this->assertStringContainsString('UI Co', $editHtml);
    }

    public function testT1F1003UsersViewsCompanySelectorAndColumn(): void
    {
        $companyA    = $this->createCompany(['name' => 'Selector Co A']);
        $superadmin  = $this->createUser('superadmin', null);
        $supervisorA = $this->createUser('supervisor', (int) $companyA->id);
        $userA       = $this->createUser('user', (int) $companyA->id);

        $this->actingAsUser($superadmin);
        $saList   = $this->rawBody($this->getWithActor('users'));
        $saCreate = $this->rawBody($this->getWithActor('users/create'));
        $saEdit   = $this->rawBody($this->getWithActor("users/edit/{$userA->id}"));

        $this->assertStringContainsString('Empresa', $saList);
        $this->assertStringContainsString('Selector Co A', $saList);
        $this->assertStringContainsString('name="company_id"', $saCreate);
        $this->assertStringContainsString('name="company_id"', $saEdit);

        $this->actingAsUser($supervisorA);
        $supCreate = $this->rawBody($this->getWithActor('users/create'));
        $this->assertStringNotContainsString('name="company_id"', $supCreate);
    }

    public function testT1F1004NetworksViewsCompanySelectorAndColumn(): void
    {
        $companyA    = $this->createCompany(['name' => 'Net Co A']);
        $superadmin  = $this->createUser('superadmin', null);
        $supervisorA = $this->createUser('supervisor', (int) $companyA->id);
        $netA        = $this->createNetworkDirect((int) $supervisorA->id, (int) $companyA->id);

        $this->actingAsUser($superadmin);
        $saList   = $this->rawBody($this->getWithActor('networks'));
        $saCreate = $this->rawBody($this->getWithActor('networks/create'));
        $saEdit   = $this->rawBody($this->getWithActor("networks/edit/{$netA->id}"));

        $this->assertStringContainsString('Empresa', $saList);
        $this->assertStringContainsString('Net Co A', $saList);
        $this->assertStringContainsString('name="company_id"', $saCreate);
        $this->assertStringContainsString('name="company_id"', $saEdit);

        $this->actingAsUser($supervisorA);
        $supCreate = $this->rawBody($this->getWithActor('networks/create'));
        $this->assertStringNotContainsString('name="company_id"', $supCreate);
    }

    public function testT1F1005AdminDashboardResponsiveCardsAndCspCompliance(): void
    {
        $companyA    = $this->createCompany();
        $superadmin  = $this->createUser('superadmin', null);
        $supervisorA = $this->createUser('supervisor', (int) $companyA->id);

        $saHtml  = $this->rawBody($this->actingAsUser($superadmin)->getWithActor('dashboard'));
        $supHtml = $this->rawBody($this->actingAsUser($supervisorA)->getWithActor('dashboard'));

        $this->assertStringContainsString('col-6 col-md-3', $saHtml);
        $this->assertStringContainsString('Empresas', $saHtml);
        $this->assertStringContainsString('col-4', $supHtml);

        $this->assertDoesNotMatchRegularExpression('/\son(click|change|load|error|submit)\s*=/i', $saHtml);
        $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*(?:\bsrc=|\bnonce=|\{csp-script-nonce\}))[^>]*>/i', $saHtml);
    }
}
