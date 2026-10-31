<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRoutingModeToDevices extends Migration
{
    public function up()
    {
        $this->forge->addColumn('devices', [
            'routing_mode' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'default'    => 'split',
                'null'       => false,
            ]
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('devices', 'routing_mode');
    }
}

