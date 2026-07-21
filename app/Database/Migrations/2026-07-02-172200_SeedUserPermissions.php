<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class SeedUserPermissions extends Migration
{
    public function up()
    {
        $data = [
            [
                'group'      => 'user',
                'permission' => 'networks.view',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'group'      => 'user',
                'permission' => 'networks.create',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'group'      => 'user',
                'permission' => 'networks.edit',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'group'      => 'user',
                'permission' => 'networks.delete',
                'created_at' => date('Y-m-d H:i:s'),
            ]
        ];

        // Usamos ignore para evitar duplicados si ya existen
        $this->db->table('auth_group_permissions')->ignore(true)->insertBatch($data);
    }

    public function down()
    {
        // En caso de rollback, eliminamos estos permisos específicos del grupo user
        $this->db->table('auth_group_permissions')
                 ->where('group', 'user')
                 ->whereIn('permission', [
                     'networks.view',
                     'networks.create',
                     'networks.edit',
                     'networks.delete'
                 ])
                 ->delete();
    }
}
