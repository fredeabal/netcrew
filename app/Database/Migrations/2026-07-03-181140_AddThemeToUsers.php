<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddThemeToUsers extends Migration
{
    public function up()
    {
        $fields = [
            'theme' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'default'    => 'system',
                'null'       => false,
            ],
        ];
        $this->forge->addColumn('users', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('users', 'theme');
    }
}
