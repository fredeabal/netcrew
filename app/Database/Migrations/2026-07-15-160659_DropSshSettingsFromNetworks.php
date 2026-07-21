<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class DropSshSettingsFromNetworks extends Migration
{
    public function up()
    {
        $this->forge->dropColumn('networks', [
            'wg_ssh_host',
            'wg_ssh_port',
            'wg_ssh_user',
            'wg_ssh_password',
            'wg_interface',
        ]);
    }

    public function down()
    {
        $fields = [
            'wg_ssh_host' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'wg_ssh_port' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 22,
            ],
            'wg_ssh_user' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'wg_ssh_password' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'wg_interface' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'wg0',
            ],
        ];

        $this->forge->addColumn('networks', $fields);
    }
}
