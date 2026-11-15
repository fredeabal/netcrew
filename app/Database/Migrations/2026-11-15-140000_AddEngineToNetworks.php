<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddEngineToNetworks extends Migration
{
    public function up()
    {
        $fields = [
            'engine' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'default'    => 'wireguard',
                'null'       => false,
                'after'      => 'active',
            ],
            'nebula_ca_crt' => [
                'type'  => 'TEXT',
                'null'  => true,
                'after' => 'engine',
            ],
            'nebula_ca_key' => [
                'type'  => 'TEXT',
                'null'  => true,
                'after' => 'nebula_ca_crt',
            ],
        ];

        $this->forge->addColumn('networks', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('networks', ['engine', 'nebula_ca_crt', 'nebula_ca_key']);
    }
}
