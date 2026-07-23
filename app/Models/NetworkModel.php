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
        'owner_id', 'name', 'cidr', 'dns', 'active'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Callbacks

    // Validation
    protected $validationRules      = [
        'id'       => 'permit_empty|is_natural_no_zero',
        'name'     => 'required|min_length[3]|max_length[100]',
        'cidr'     => 'required|min_length[9]|max_length[45]|is_unique[networks.cidr,id,{id}]',
        'dns'      => 'permit_empty|string|max_length[255]',
        'owner_id' => 'required|integer',
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
     * Obtiene las redes filtradas por usuario.
     * Si el usuario es superadmin, devuelve todas las redes del sistema.
     * Soporta buscador y paginado.
     */
    public function getNetworksForUser(int $userId, bool $isSuperAdmin = false, $search = null, $perPage = 10)
    {
        $builder = $this->select('networks.*, users.username as owner_username, COUNT(devices.id) as device_count')
                        ->join('users', 'users.id = networks.owner_id', 'left')
                        ->join('devices', 'devices.network_id = networks.id', 'left')
                        ->groupBy('networks.id');

        if (!$isSuperAdmin) {
            $builder->where('networks.owner_id', $userId);
        }

        if (!empty($search)) {
            $builder->groupStart()
                    ->like('networks.name', $search)
                    ->orLike('networks.cidr', $search)
                    ->orLike('users.username', $search)
                    ->groupEnd();
        }

        return $builder->paginate($perPage, 'networks');
    }


}
