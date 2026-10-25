<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddIntegrityIndexes extends Migration
{
    public function up()
    {
        $this->db->query('CREATE UNIQUE INDEX IF NOT EXISTS idx_devices_network_ip ON devices (network_id, ip_address);');
        $this->db->query('CREATE UNIQUE INDEX IF NOT EXISTS idx_devices_public_key ON devices (public_key);');
        $this->db->query('CREATE UNIQUE INDEX IF NOT EXISTS idx_networks_cidr ON networks (cidr);');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_devices_network_id ON devices (network_id);');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_devices_active ON devices (active);');
    }

    public function down()
    {
        $this->db->query('DROP INDEX IF EXISTS idx_devices_network_ip;');
        $this->db->query('DROP INDEX IF EXISTS idx_devices_public_key;');
        $this->db->query('DROP INDEX IF EXISTS idx_networks_cidr;');
        $this->db->query('DROP INDEX IF EXISTS idx_devices_network_id;');
        $this->db->query('DROP INDEX IF EXISTS idx_devices_active;');
    }
}
