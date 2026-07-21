<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLastSeenToDevices extends Migration
{
    public function up()
    {
        $this->forge->addColumn('devices', [
            'last_seen' => [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'active',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('devices', 'last_seen');
    }
}
