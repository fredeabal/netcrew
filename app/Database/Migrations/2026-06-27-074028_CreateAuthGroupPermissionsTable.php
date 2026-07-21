<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuthGroupPermissionsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INTEGER',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'group' => [
                'type'       => 'VARCHAR',
                'constraint' => '80',
            ],
            'permission' => [
                'type'       => 'VARCHAR',
                'constraint' => '80',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['group', 'permission']);
        $this->forge->createTable('auth_group_permissions', true);
    }

    public function down()
    {
        $this->forge->dropTable('auth_group_permissions', true);
    }
}
