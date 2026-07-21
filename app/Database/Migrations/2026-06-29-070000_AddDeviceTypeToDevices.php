<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDeviceTypeToDevices extends Migration
{
    public function up()
    {
        $this->forge->addColumn('devices', [
            'device_type' => [
                'type'       => 'VARCHAR',
                'constraint' => '10',
                'default'    => 'pc',
                'null'       => false,
            ]
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('devices', 'device_type');
    }
}
