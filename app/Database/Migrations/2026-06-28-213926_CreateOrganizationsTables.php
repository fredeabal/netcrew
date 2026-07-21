<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOrganizationsTables extends Migration
{
    public function up()
    {
        // ---------------------------------------------------------------------
        // Tabla: organizations
        // ---------------------------------------------------------------------
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
            ],
            'owner_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true, // In case owner is deleted or not set initially
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        
        // Foreign key for owner_id pointing to users
        // CodeIgniter Shield's users table is 'users'
        $this->forge->addForeignKey('owner_id', 'users', 'id', 'CASCADE', 'SET NULL');
        
        $this->forge->createTable('organizations', true);

        // ---------------------------------------------------------------------
        // Tabla: organization_users
        // ---------------------------------------------------------------------
        $this->forge->addField([
            'organization_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'role' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'default'    => 'member', // member, admin, owner, etc.
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        
        $this->forge->addKey(['organization_id', 'user_id']);
        $this->forge->addForeignKey('organization_id', 'organizations', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('organization_users', true);
    }

    public function down()
    {
        $this->forge->dropTable('organization_users', true);
        $this->forge->dropTable('organizations', true);
    }
}
