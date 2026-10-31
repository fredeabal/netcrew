<?php

namespace App\Models;

use CodeIgniter\Model;

class AuditLogModel extends Model
{
    protected $table            = 'audit_logs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'username',
        'action',
        'description',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $useTimestamps = false;

    /**
     * Helper estático para registrar un log de auditoría fácilmente
     */
    public static function log(string $action, string $description = '', ?int $userId = null, ?string $username = null): void
    {
        try {
            $user = auth()->user();
            $request = service('request');

            $data = [
                'user_id'     => $userId ?? ($user ? $user->id : null),
                'username'    => $username ?? ($user ? ($user->username ?? $user->email) : 'Sistema'),
                'action'      => $action,
                'description' => $description,
                'ip_address'  => $request ? $request->getIPAddress() : '127.0.0.1',
                'user_agent'  => $request ? mb_substr((string) $request->getUserAgent(), 0, 255) : null,
                'created_at'  => date('Y-m-d H:i:s'),
            ];

            (new self())->insert($data);
        } catch (\Throwable $e) {
            log_message('error', 'Fallo al registrar log de auditoría: ' . $e->getMessage());
        }
    }
}

