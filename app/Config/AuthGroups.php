<?php

declare(strict_types=1);

/**
 * This file is part of CodeIgniter Shield.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Config;

use CodeIgniter\Shield\Config\AuthGroups as ShieldAuthGroups;

class AuthGroups extends ShieldAuthGroups
{
    /**
     * --------------------------------------------------------------------
     * Default Group
     * --------------------------------------------------------------------
     * The group that a newly registered user is added to.
     */
    public string $defaultGroup = 'user';

    public function __construct()
    {
        parent::__construct();

        try {
            $db = \Config\Database::connect();
            if ($db->tableExists('auth_group_permissions')) {
                $rows = $db->table('auth_group_permissions')->get()->getResultArray();

                $dbMatrix = [];
                foreach (array_keys($this->groups) as $group) {
                    $dbMatrix[$group] = [];
                }

                foreach ($rows as $row) {
                    if (isset($dbMatrix[$row['group']])) {
                        $dbMatrix[$row['group']][] = $row['permission'];
                    }
                }

                // Asegurar acceso permanente de superadmin para evitar bloqueos
                if (isset($dbMatrix['superadmin'])) {
                    if (!in_array('admin.users', $dbMatrix['superadmin'])) $dbMatrix['superadmin'][] = 'admin.users';
                    if (!in_array('admin.roles', $dbMatrix['superadmin'])) $dbMatrix['superadmin'][] = 'admin.roles';
                    if (!in_array('admin.settings', $dbMatrix['superadmin'])) $dbMatrix['superadmin'][] = 'admin.settings';
                    if (!in_array('networks.view', $dbMatrix['superadmin'])) $dbMatrix['superadmin'][] = 'networks.view';
                    if (!in_array('networks.create', $dbMatrix['superadmin'])) $dbMatrix['superadmin'][] = 'networks.create';
                    if (!in_array('networks.edit', $dbMatrix['superadmin'])) $dbMatrix['superadmin'][] = 'networks.edit';
                    if (!in_array('networks.delete', $dbMatrix['superadmin'])) $dbMatrix['superadmin'][] = 'networks.delete';
                    if (!in_array('devices.view', $dbMatrix['superadmin'])) $dbMatrix['superadmin'][] = 'devices.view';
                    if (!in_array('devices.create', $dbMatrix['superadmin'])) $dbMatrix['superadmin'][] = 'devices.create';
                    if (!in_array('devices.edit', $dbMatrix['superadmin'])) $dbMatrix['superadmin'][] = 'devices.edit';
                    if (!in_array('devices.delete', $dbMatrix['superadmin'])) $dbMatrix['superadmin'][] = 'devices.delete';
                }

                $this->matrix = $dbMatrix;
            }
        } catch (\Throwable $e) {
            // Silenciar fallos en CLI o migración
        }
    }

    /**
     * --------------------------------------------------------------------
     * Groups
     * --------------------------------------------------------------------
     * An associative array of the available groups in the system, where the keys
     * are the group names and the values are arrays of the group info.
     *
     * Whatever value you assign as the key will be used to refer to the group
     * when using functions such as:
     *      $user->addGroup('superadmin');
     *
     * @var array<string, array<string, string>>
     *
     * @see https://codeigniter4.github.io/shield/quick_start_guide/using_authorization/#change-available-groups for more info
     */
    public array $groups = [
        'superadmin' => [
            'title'       => 'Administrador',
            'description' => 'Administrador global. Control absoluto y configuración técnica de la infraestructura.',
        ],
        'supervisor' => [
            'title'       => 'Supervisor',
            'description' => 'Supervisor técnico. Gestión y monitoreo general de usuarios y dispositivos.',
        ],

        'user' => [
            'title'       => 'Usuario',
            'description' => 'Usuario estándar del sistema.',
        ],
    ];

    /**
     * --------------------------------------------------------------------
     * Permissions
     * --------------------------------------------------------------------
     * The available permissions in the system.
     *
     * If a permission is not listed here it cannot be used.
     */
    public array $permissions = [
        'admin.users'          => 'Gestión completa de usuarios',
        'admin.roles'          => 'Gestión de roles y permisos',
        'admin.settings'       => 'Acceso a configuración del sistema',
        'networks.view'   => 'Ver redes',
        'networks.create' => 'Crear redes',
        'networks.edit'   => 'Editar redes',
        'networks.delete' => 'Eliminar redes',
        'devices.view'    => 'Ver dispositivos',
        'devices.create'  => 'Crear dispositivos',
        'devices.edit'    => 'Editar dispositivos',
        'devices.delete'  => 'Eliminar dispositivos',
    ];

    /**
     * --------------------------------------------------------------------
     * Permissions Matrix
     * --------------------------------------------------------------------
     * Maps permissions to groups.
     *
     * This defines group-level permissions.
     */
    public array $matrix = [
        'superadmin' => [
            'admin.*',
            'networks.*',
            'devices.*',
        ],
        'supervisor' => [
            'admin.users',
            'networks.*',
            'devices.*',
        ],

        'user' => [
            'networks.*',
            'devices.*',
        ],
    ];
}
