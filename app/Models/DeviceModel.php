<?php

namespace App\Models;

use CodeIgniter\Model;

class DeviceModel extends Model
{
    protected $table            = 'devices';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['network_id', 'name', 'ip_address', 'public_key', 'private_key', 'active', 'os', 'device_type', 'last_seen'];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Callbacks
    protected $beforeInsert = ['encryptPrivateKey'];
    protected $beforeUpdate = ['encryptPrivateKey'];

    // Validation
    protected $validationRules      = [
        'network_id'  => 'required|integer',
        'name'        => 'required|min_length[2]|max_length[100]',
        'ip_address'  => 'required|valid_ip',
        'public_key'  => 'required',
        'private_key' => 'required',
        'os'          => 'permit_empty|in_list[linux,windows,osx,ios,android]',
        'device_type' => 'required|in_list[pc,server,mobile,tablet,router]',
    ];
    
    protected $validationMessages   = [
        'network_id' => [
            'required' => 'La red es obligatoria.',
            'integer'  => 'El ID de la red debe ser un número entero.',
        ],
        'name' => [
            'required'   => 'El nombre del dispositivo es obligatorio.',
            'min_length' => 'El nombre debe tener al menos 2 caracteres.',
            'max_length' => 'El nombre no puede superar los 100 caracteres.',
        ],
        'ip_address' => [
            'required' => 'La dirección IP es obligatoria.',
            'valid_ip' => 'La dirección IP no es válida.',
        ],
        'public_key' => [
            'required' => 'La llave pública es obligatoria.',
        ],
        'private_key' => [
            'required' => 'La llave privada es obligatoria.',
        ],
    ];
    
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Callback para encriptar la llave privada antes de guardarla.
     */
    protected function encryptPrivateKey(array $data)
    {
        if (isset($data['data']['private_key'])) {
            $encrypter = \Config\Services::encrypter();
            $data['data']['private_key'] = base64_encode($encrypter->encrypt($data['data']['private_key']));
        }
        return $data;
    }

    /**
     * Desencripta una llave privada.
     */
    public function decryptKey(string $encryptedKey): string
    {
        try {
            $encrypter = \Config\Services::encrypter();
            return $encrypter->decrypt(base64_decode($encryptedKey));
        } catch (\Exception $e) {
            log_message('error', 'Error al desencriptar llave privada de WireGuard: ' . $e->getMessage());
            return '';
        }
    }
}
