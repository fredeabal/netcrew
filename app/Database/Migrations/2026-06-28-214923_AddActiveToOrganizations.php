<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddActiveToOrganizations extends Migration
{
    public function up()
    {
        $this->forge->addColumn('organizations', [
            'active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
                'null'       => false,
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('organizations', 'active');
    }
}
