<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddNebulaFieldsToDevices extends Migration
{
    public function up()
    {
        $fields = [
            'nebula_crt' => [
                'type'  => 'TEXT',
                'null'  => true,
                'after' => 'private_key',
            ],
            'nebula_key' => [
                'type'  => 'TEXT',
                'null'  => true,
                'after' => 'nebula_crt',
            ],
            'nebula_groups' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => true,
                'after'      => 'nebula_key',
            ],
        ];

        $this->forge->addColumn('devices', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('devices', ['nebula_crt', 'nebula_key', 'nebula_groups']);
    }
}
