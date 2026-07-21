<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDevicesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'network_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
            ],
            'ip_address' => [
                'type'       => 'VARCHAR',
                'constraint' => '45',
            ],
            'public_key' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
            ],
            'private_key' => [
                'type'       => 'TEXT',
            ],
            'active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('network_id', 'networks', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('devices');
    }

    public function down()
    {
        $this->forge->dropTable('devices', true);
    }
}
