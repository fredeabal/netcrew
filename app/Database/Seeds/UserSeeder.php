<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\Shield\Entities\User;

class UserSeeder extends Seeder
{
    public function run()
    {
        $users = auth()->getProvider();

        if (ENVIRONMENT === 'production' && $users->countAllResults() > 0) {
            echo "¡ERROR! No puedes ejecutar este Seeder porque ya existen usuarios. Destruiría todos los datos.\n";
            return;
        }

        // Desactivar claves foráneas para permitir truncar tablas en SQLite
        $this->db->query('PRAGMA foreign_keys = OFF;');

        // Eliminar usuarios y permisos existentes
        $this->db->table('auth_identities')->truncate();
        $this->db->table('auth_groups_users')->truncate();
        $this->db->table('users')->truncate();
        $this->db->table('auth_group_permissions')->truncate();

        // Reactivar claves foráneas
        $this->db->query('PRAGMA foreign_keys = ON;');

        // Crear único Superadmin inicial
        $admin = new User([
            'username' => 'admin',
            'email'    => 'admin@demo.com',
            'phone'    => '+34000000000',
            'password' => 'admin1234',
        ]);
        $users->save($admin);

        // Sembrar permisos por defecto
        $defaultPermissions = [
            // Permisos de Superadmin
            ['group' => 'superadmin', 'permission' => 'admin.companies'],
            ['group' => 'superadmin', 'permission' => 'admin.users'],
            ['group' => 'superadmin', 'permission' => 'admin.roles'],
            ['group' => 'superadmin', 'permission' => 'admin.settings'],
            ['group' => 'superadmin', 'permission' => 'networks.view'],
            ['group' => 'superadmin', 'permission' => 'networks.create'],
            ['group' => 'superadmin', 'permission' => 'networks.edit'],
            ['group' => 'superadmin', 'permission' => 'networks.delete'],
            ['group' => 'superadmin', 'permission' => 'devices.view'],
            ['group' => 'superadmin', 'permission' => 'devices.create'],
            ['group' => 'superadmin', 'permission' => 'devices.edit'],
            ['group' => 'superadmin', 'permission' => 'devices.delete'],
            
            // Permisos de Supervisor (Gestionar usuarios, redes y dispositivos)
            ['group' => 'supervisor', 'permission' => 'admin.users'],
            ['group' => 'supervisor', 'permission' => 'networks.view'],
            ['group' => 'supervisor', 'permission' => 'networks.create'],
            ['group' => 'supervisor', 'permission' => 'networks.edit'],
            ['group' => 'supervisor', 'permission' => 'networks.delete'],
            ['group' => 'supervisor', 'permission' => 'devices.view'],
            ['group' => 'supervisor', 'permission' => 'devices.create'],
            ['group' => 'supervisor', 'permission' => 'devices.edit'],
            ['group' => 'supervisor', 'permission' => 'devices.delete'],
            
            // Permisos de Usuario Normal (Gestión de Redes y Dispositivos)
            ['group' => 'user', 'permission' => 'networks.view'],
            ['group' => 'user', 'permission' => 'networks.create'],
            ['group' => 'user', 'permission' => 'networks.edit'],
            ['group' => 'user', 'permission' => 'networks.delete'],
            ['group' => 'user', 'permission' => 'devices.view'],
            ['group' => 'user', 'permission' => 'devices.create'],
            ['group' => 'user', 'permission' => 'devices.edit'],
            ['group' => 'user', 'permission' => 'devices.delete'],
            
        ];

        $now = date('Y-m-d H:i:s');
        foreach ($defaultPermissions as &$p) {
            $p['created_at'] = $now;
        }

        $this->db->table('auth_group_permissions')->insertBatch($defaultPermissions);

        // Obtener objeto completo con ID, asignarle el grupo superadmin y activarlo
        $admin = $users->findById($users->getInsertID());
        $admin->addGroup('superadmin');
        $admin->activate();
        $admin->forcePasswordReset();

        // Asegurar empresa predeterminada 'Principal' sin truncar companies y asignar usuarios no-superadmin
        $defaultCompanyId = null;
        if ($this->db->tableExists('companies')) {
            $existingCompany = $this->db->table('companies')->orderBy('id', 'ASC')->get()->getRow();
            if (! $existingCompany) {
                $companyNow = date('Y-m-d H:i:s');
                $this->db->table('companies')->insert([
                    'name'         => 'Principal',
                    'slug'         => 'principal',
                    'active'       => 1,
                    'max_networks' => 0,
                    'max_devices'  => 0,
                    'created_at'   => $companyNow,
                    'updated_at'   => $companyNow,
                ]);
                $defaultCompanyId = (int) $this->db->insertID();
            } else {
                $defaultCompanyId = (int) $existingCompany->id;
            }
        }

        if ($defaultCompanyId !== null && $this->db->fieldExists('company_id', 'users')) {
            $superadminRows = $this->db->table('auth_groups_users')
                ->select('user_id')
                ->where('group', 'superadmin')
                ->get()
                ->getResultArray();
            $superadminIds = array_map(static fn ($r) => (int) $r['user_id'], $superadminRows);

            if (! empty($superadminIds)) {
                $this->db->table('users')
                    ->whereIn('id', $superadminIds)
                    ->update(['company_id' => null]);
            }

            $builder = $this->db->table('users')->where('company_id', null);
            if (! empty($superadminIds)) {
                $builder->whereNotIn('id', $superadminIds);
            }
            $builder->update(['company_id' => $defaultCompanyId]);
        }

        // Establecer puerto SMTP predeterminado en 587 y formato HTML
        $settings = service('settings');
        $settings->set('Email.SMTPPort', 587);
        $settings->set('Email.mailType', 'html');

        // Configuración predeterminada de Nebula P2P (Lighthouse)
        $settings->set('Nebula.sshHost', '127.0.0.1');
        $settings->set('Nebula.sshUser', 'root');
        $settings->set('Nebula.sshPort', 22);
        $settings->set('Nebula.sshAuthType', 'key');
        $settings->set('Nebula.lighthousePort', 4242);
        $settings->set('Nebula.relayEnabled', 1);
        $settings->set('Nebula.cipher', 'aes');

        $baseUrl = env('app.baseURL') ?: config('App')->baseURL;
        $hostOnly = '127.0.0.1';
        if (!empty($baseUrl)) {
            $parsed = parse_url($baseUrl);
            if (!empty($parsed['host'])) {
                $hostOnly = $parsed['host'];
            }
        }
        $settings->set('Nebula.lighthouseHost', $hostOnly);

        // Cargar llave SSH autogenerada si existe en writable
        $sshKeyPath = WRITEPATH . 'netcrew_ssh_key';
        if (file_exists($sshKeyPath)) {
            $sshKeyContent = trim(file_get_contents($sshKeyPath));
            if (!empty($sshKeyContent)) {
                try {
                    $encrypter = \Config\Services::encrypter();
                    $encryptedKey = base64_encode($encrypter->encrypt($sshKeyContent));
                    $settings->set('Nebula.sshPrivateKey', $encryptedKey);
                } catch (\Exception $e) {
                    log_message('error', 'Error al encriptar llave SSH autogenerada: ' . $e->getMessage());
                }
            }
        }
    }
}
