<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddExpiresAtToDevices extends Migration
{
    public function up()
    {
        $this->forge->addColumn('devices', [
            'expires_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ]
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('devices', 'expires_at');
    }
}

