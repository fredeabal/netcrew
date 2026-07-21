<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDevicesAndSupervisorPermissions extends Migration
{
    public function up()
    {
        $data = [
            // Supervisor permissions
            ['group' => 'supervisor', 'permission' => 'admin.users', 'created_at' => date('Y-m-d H:i:s')],
            ['group' => 'supervisor', 'permission' => 'networks.view', 'created_at' => date('Y-m-d H:i:s')],
            ['group' => 'supervisor', 'permission' => 'networks.create', 'created_at' => date('Y-m-d H:i:s')],
            ['group' => 'supervisor', 'permission' => 'networks.edit', 'created_at' => date('Y-m-d H:i:s')],
            ['group' => 'supervisor', 'permission' => 'networks.delete', 'created_at' => date('Y-m-d H:i:s')],
            ['group' => 'supervisor', 'permission' => 'devices.view', 'created_at' => date('Y-m-d H:i:s')],
            ['group' => 'supervisor', 'permission' => 'devices.create', 'created_at' => date('Y-m-d H:i:s')],
            ['group' => 'supervisor', 'permission' => 'devices.edit', 'created_at' => date('Y-m-d H:i:s')],
            ['group' => 'supervisor', 'permission' => 'devices.delete', 'created_at' => date('Y-m-d H:i:s')],

            // User devices permissions
            ['group' => 'user', 'permission' => 'devices.view', 'created_at' => date('Y-m-d H:i:s')],
            ['group' => 'user', 'permission' => 'devices.create', 'created_at' => date('Y-m-d H:i:s')],
            ['group' => 'user', 'permission' => 'devices.edit', 'created_at' => date('Y-m-d H:i:s')],
            ['group' => 'user', 'permission' => 'devices.delete', 'created_at' => date('Y-m-d H:i:s')],
        ];

        // Usamos ignore(true) para evitar duplicados si ya existen
        $this->db->table('auth_group_permissions')->ignore(true)->insertBatch($data);
    }

    public function down()
    {
        // En caso de rollback, eliminamos estos permisos específicos añadidos
        $this->db->table('auth_group_permissions')
                 ->groupStart()
                     ->where('group', 'supervisor')
                     ->whereIn('permission', [
                         'admin.users',
                         'networks.view',
                         'networks.create',
                         'networks.edit',
                         'networks.delete',
                         'devices.view',
                         'devices.create',
                         'devices.edit',
                         'devices.delete'
                     ])
                 ->groupEnd()
                 ->orGroupStart()
                     ->where('group', 'user')
                     ->whereIn('permission', [
                         'devices.view',
                         'devices.create',
                         'devices.edit',
                         'devices.delete'
                     ])
                 ->groupEnd()
                 ->delete();
    }
}
