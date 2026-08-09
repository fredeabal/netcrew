<?php

namespace App\Controllers;

use App\Controllers\BaseController;

class RolesController extends BaseController
{
    public function index()
    {
        $data = [
            'title' => 'Gestión de Roles y Permisos',
            'groups' => config('AuthGroups')->groups,
            'permissions' => config('AuthGroups')->permissions
        ];

        echo view('template/header', $data);
        echo view('roles/list', $data);
        echo view('template/footer');
    }

    // ---------------------------------------------------------------------
    // Editar permisos del rol
    // ---------------------------------------------------------------------
    public function edit(string $groupName)
    {
        $groups = config('AuthGroups')->groups;

        if (!isset($groups[$groupName])) {
            return redirect()->to('roles')->with('error', 'El rol especificado no existe.');
        }

        $allPermissions = config('AuthGroups')->permissions;

        if ($groupName === 'superadmin') {
            $assignedPerms = array_keys($allPermissions);
        } else {
            $db = \Config\Database::connect();
            $assigned = $db->table('auth_group_permissions')
                           ->where('group', $groupName)
                           ->get()
                           ->getResultArray();
            
            $assignedPerms = array_column($assigned, 'permission');
        }

        // Mapeo amigable de permisos
        $friendlyNames = [
            'admin.users'        => 'Gestionar Usuarios',
            'admin.roles'        => 'Gestionar Roles',
            'admin.settings'     => 'Ajustes del Sistema',
        ];

        // Agrupar permisos por categoría
        $categories = [
            'admin'    => 'Administración',
            'networks' => 'Gestión de Redes',
            'devices'  => 'Gestión de Dispositivos',
        ];

        $allPermissions = config('AuthGroups')->permissions;
        $groupedPermissions = [];

        foreach ($allPermissions as $key => $description) {
            $parts = explode('.', $key);
            $catKey = $parts[0] ?? 'otros';
            $catName = $categories[$catKey] ?? 'Otros Permisos';

            // Usar el mapping de friendlyNames si existe, o generar uno dinámico basado en la regla de AGENTS.md
            if (isset($friendlyNames[$key])) {
                $friendly = $friendlyNames[$key];
            } else {
                // Formato simplificado: la acción en mayúscula inicial
                $action = end($parts);
                $friendly = ucfirst(strtolower($action)); // ej: 'create' -> 'Crear', 'view' -> 'Ver'
                
                // Mapeo básico de verbos comunes al español
                $verbMap = [
                    'view'   => 'Ver',
                    'create' => 'Crear',
                    'edit'   => 'Editar',
                    'delete' => 'Eliminar',
                    'access' => 'Acceder'
                ];
                
                if (isset($verbMap[$action])) {
                    $friendly = $verbMap[$action];
                }
            }

            $groupedPermissions[$catName][] = [
                'key'         => $key,
                'description' => $description,
                'friendly'    => $friendly,
                'checked'     => in_array($key, $assignedPerms)
            ];
        }

        $data = [
            'title'              => 'Editar Permisos del Rol: ' . $groups[$groupName]['title'],
            'groupName'          => $groupName,
            'groupInfo'          => $groups[$groupName],
            'groupedPermissions' => $groupedPermissions
        ];

        echo view('template/header', $data);
        echo view('roles/edit', $data);
        echo view('template/footer');
    }

    // ---------------------------------------------------------------------
    // Actualizar permisos del rol
    // ---------------------------------------------------------------------
    public function update(string $groupName)
    {
        $groups = config('AuthGroups')->groups;

        if (!isset($groups[$groupName])) {
            return redirect()->to('roles')->with('error', 'El rol especificado no existe.');
        }

        // El superadmin no debería poder quitarse sus propios permisos
        $selectedPerms = $this->request->getPost('permissions') ?? [];

        if ($groupName === 'superadmin') {
            $selectedPerms = array_keys(config('AuthGroups')->permissions);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        // Eliminar permisos antiguos
        $db->table('auth_group_permissions')
           ->where('group', $groupName)
           ->delete();

        // Insertar los nuevos permisos
        if (!empty($selectedPerms)) {
            $insertData = [];
            foreach ($selectedPerms as $perm) {
                // Verificar que el permiso exista en la configuración
                if (array_key_exists($perm, config('AuthGroups')->permissions)) {
                    $insertData[] = [
                        'group'      => $groupName,
                        'permission' => $perm,
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                }
            }
            if (!empty($insertData)) {
                $db->table('auth_group_permissions')->insertBatch($insertData);
            }
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->back()->with('error', 'Error al guardar.');
        }

        return redirect()->to('roles')->with('message', 'Permisos actualizados.');
    }
}
