<?php

namespace App\Models;

use CodeIgniter\Model;

class NetworkModel extends Model
{
    protected $table            = 'networks';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'company_id', 'owner_id', 'name', 'cidr', 'dns', 'active', 'engine', 'nebula_ca_crt', 'nebula_ca_key'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Callbacks
    protected $beforeInsert = ['encryptCaKey'];
    protected $beforeUpdate = ['encryptCaKey'];

    // Validation
    protected $validationRules      = [
        'id'         => 'permit_empty|is_natural_no_zero',
        'company_id' => 'permit_empty|is_natural_no_zero',
        'name'       => 'required|min_length[3]|max_length[100]',
        'cidr'       => 'required|min_length[9]|max_length[45]|is_unique[networks.cidr,id,{id}]',
        'dns'        => 'permit_empty|string|max_length[255]',
        'owner_id'   => 'required|integer',
        'engine'     => 'permit_empty|in_list[nebula]',
    ];
    protected $validationMessages   = [
        'name' => [
            'required' => 'El nombre de la red es obligatorio.',
            'min_length' => 'El nombre de la red debe tener al menos 3 caracteres.',
            'max_length' => 'El nombre de la red no puede superar los 100 caracteres.',
        ],
        'cidr' => [
            'required'   => 'El rango CIDR es obligatorio.',
            'min_length' => 'El rango CIDR no es válido.',
            'max_length' => 'El rango CIDR no puede superar los 45 caracteres.',
            'is_unique'  => 'Este rango de IPs ya está siendo utilizado por otra red.',
        ],
        'owner_id' => [
            'required' => 'El propietario es obligatorio.',
            'integer'  => 'El ID de propietario debe ser un número entero.',
        ]
    ];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Obtiene las redes filtradas por rol y empresa.
     * - superadmin: ve todas las redes del sistema (opcionalmente filtradas por empresa).
     * - companyAdmin (supervisor): ve todas las redes de su empresa.
     * - user: ve solo sus redes dentro de su empresa.
     */
    public function getNetworksForUser(
        int $userId,
        bool $isSuperAdmin = false,
        $search = null,
        $perPage = 10,
        ?int $companyId = null,
        bool $isCompanyAdmin = false
    ) {
        $builder = $this->select('networks.*, users.username as owner_username, companies.name as company_name, COUNT(devices.id) as device_count')
                        ->join('users', 'users.id = networks.owner_id', 'left')
                        ->join('companies', 'companies.id = networks.company_id', 'left')
                        ->join('devices', 'devices.network_id = networks.id', 'left')
                        ->groupBy('networks.id');

        if ($isSuperAdmin) {
            if ($companyId !== null) {
                $builder->where('networks.company_id', $companyId);
            }
        } elseif ($isCompanyAdmin) {
            $builder->where('networks.company_id', (int) $companyId);
        } else {
            $builder->where('networks.owner_id', $userId);
            if ($companyId !== null) {
                $builder->where('networks.company_id', $companyId);
            }
        }

        if (!empty($search)) {
            $builder->groupStart()
                    ->like('networks.name', $search)
                    ->orLike('networks.cidr', $search)
                    ->orLike('users.username', $search)
                    ->orLike('companies.name', $search)
                    ->groupEnd();
        }

        return $builder->paginate($perPage, 'networks');
    }

    /**
     * Sugiere el siguiente bloque /24 libre dentro de 10.0.0.0/8 (empezando en 10.1.0.0)
     * para evitar colisiones con el Lighthouse global (10.0.0.1) y entre empresas.
     */
    public function suggestNextCidr(): string
    {
        $used = array_column($this->select('cidr')->findAll(), 'cidr');
        $usedMap = array_flip($used);

        for ($second = 1; $second <= 254; $second++) {
            for ($third = 0; $third <= 254; $third++) {
                $candidate = "10.{$second}.{$third}.0/24";
                if (! isset($usedMap[$candidate])) {
                    return "10.{$second}.{$third}.0";
                }
            }
        }

        return '10.50.0.0';
    }

    /**
     * Cifra la llave privada de la CA de Nebula antes de persistirla.
     */
    protected function encryptCaKey(array $data): array
    {
        if (!empty($data['data']['nebula_ca_key'])) {
            $encrypter = \Config\Services::encrypter();
            $data['data']['nebula_ca_key'] = base64_encode($encrypter->encrypt($data['data']['nebula_ca_key']));
        }
        return $data;
    }

    /**
     * Descifra la llave privada de la CA de Nebula.
     */
    public function decryptCaKey(string $encryptedKey): string
    {
        try {
            $encrypter = \Config\Services::encrypter();
            return $encrypter->decrypt(base64_decode($encryptedKey));
        } catch (\Throwable $e) {
            log_message('error', 'Error al desencriptar llave CA de Nebula: ' . $e->getMessage());
            return '';
        }
    }
}
