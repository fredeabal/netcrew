<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMultitenantCompanies extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('companies')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => '120',
                ],
                'slug' => [
                    'type'       => 'VARCHAR',
                    'constraint' => '120',
                ],
                'active' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 1,
                ],
                'max_networks' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'default'    => 0,
                ],
                'max_devices' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'default'    => 0,
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
            $this->forge->addUniqueKey('slug');
            $this->forge->createTable('companies', true);
        }

        $companyColumn = [
            'company_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
        ];

        if ($this->db->tableExists('users') && ! $this->db->fieldExists('company_id', 'users')) {
            $this->forge->addColumn('users', $companyColumn);
        }

        if ($this->db->tableExists('networks') && ! $this->db->fieldExists('company_id', 'networks')) {
            $this->forge->addColumn('networks', $companyColumn);
        }

        if ($this->db->tableExists('audit_logs') && ! $this->db->fieldExists('company_id', 'audit_logs')) {
            $this->forge->addColumn('audit_logs', $companyColumn);
        }

        // Crear empresa por defecto si no existe ninguna
        $now            = date('Y-m-d H:i:s');
        $defaultCompany = $this->db->table('companies')->orderBy('id', 'ASC')->get()->getRow();

        if (! $defaultCompany) {
            $this->db->table('companies')->insert([
                'name'         => 'Principal',
                'slug'         => 'principal',
                'active'       => 1,
                'max_networks' => 0,
                'max_devices'  => 0,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
            $defaultCompanyId = (int) $this->db->insertID();
        } else {
            $defaultCompanyId = (int) $defaultCompany->id;
        }

        // Backfill de redes y logs existentes a la empresa por defecto
        if ($this->db->tableExists('networks')) {
            $this->db->table('networks')
                ->where('company_id', null)
                ->update(['company_id' => $defaultCompanyId]);
        }

        if ($this->db->tableExists('audit_logs')) {
            $this->db->table('audit_logs')
                ->where('company_id', null)
                ->update(['company_id' => $defaultCompanyId]);
        }

        // Asignar usuarios no-superadmin existentes a la empresa por defecto
        if ($this->db->tableExists('users') && $this->db->tableExists('auth_groups_users')) {
            $superadminRows = $this->db->table('auth_groups_users')
                ->select('user_id')
                ->where('group', 'superadmin')
                ->get()
                ->getResultArray();
            $superadminIds = array_map(static fn ($r) => (int) $r['user_id'], $superadminRows);

            $builder = $this->db->table('users')->where('company_id', null);
            if (! empty($superadminIds)) {
                $builder->whereNotIn('id', $superadminIds);
            }
            $builder->update(['company_id' => $defaultCompanyId]);
        }

        // Añadir permiso admin.companies al grupo superadmin
        if ($this->db->tableExists('auth_group_permissions')) {
            $exists = $this->db->table('auth_group_permissions')
                ->where('group', 'superadmin')
                ->where('permission', 'admin.companies')
                ->countAllResults();

            if ($exists === 0) {
                $this->db->table('auth_group_permissions')->insert([
                    'group'      => 'superadmin',
                    'permission' => 'admin.companies',
                ]);
            }
        }
    }

    public function down()
    {
        if ($this->db->tableExists('auth_group_permissions')) {
            $this->db->table('auth_group_permissions')
                ->where('permission', 'admin.companies')
                ->delete();
        }

        if ($this->db->tableExists('audit_logs') && $this->db->fieldExists('company_id', 'audit_logs')) {
            $this->forge->dropColumn('audit_logs', 'company_id');
        }

        if ($this->db->tableExists('networks') && $this->db->fieldExists('company_id', 'networks')) {
            $this->forge->dropColumn('networks', 'company_id');
        }

        if ($this->db->tableExists('users') && $this->db->fieldExists('company_id', 'users')) {
            $this->forge->dropColumn('users', 'company_id');
        }

        $this->forge->dropTable('companies', true);
    }
}
