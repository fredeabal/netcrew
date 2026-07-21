<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PivotToNetworks extends Migration
{
    public function up()
    {
        // Disable foreign key checks for dropping tables
        $this->db->disableForeignKeyChecks();
        $this->forge->dropTable('organization_users', true);
        $this->forge->dropTable('organizations', true);
        $this->db->enableForeignKeyChecks();

        // Create networks table
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'owner_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
            ],
            'cidr' => [
                'type'       => 'VARCHAR',
                'constraint' => '45',
                'default'    => '10.50.0.0/24',
            ],
            'dns' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => true,
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
        $this->forge->addForeignKey('owner_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('networks');
    }

    public function down()
    {
        $this->forge->dropTable('networks', true);
    }
}
