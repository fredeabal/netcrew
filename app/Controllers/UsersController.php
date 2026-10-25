<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Libraries\WireguardService;
use App\Models\DeviceModel;
use App\Models\NetworkModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserIdentityModel;
use CodeIgniter\Shield\Models\UserModel;

class UsersController extends BaseController
{
    /**
     * @var UserModel
     */
    protected $usersModel;
    protected $perPage = 50;

    public function __construct()
    {
        $this->usersModel = auth()->getProvider();
    }

    public function index()
    {
        $search = $this->request->getGet('q');
        $groups = config('AuthGroups')->groups;



        if ($search) {
            $this->usersModel
                ->groupStart()
                ->like('users.username', $search)
                ->orLike('users.id', $search)
                ->groupEnd()
                ->orGroupStart()
                ->where('users.id IN (SELECT user_id FROM ' . $this->usersModel->db->prefixTable('auth_identities') . ' WHERE secret LIKE ' . $this->usersModel->db->escape("%{$search}%") . ' AND type = ' . $this->usersModel->db->escape('email_password') . ')', null, false)
                ->groupEnd();
        }

        $users = $this->usersModel->withIdentities()->paginate($this->perPage, 'users');
        $pager = $this->usersModel->pager;

        $usersWithInfo = [];
        foreach ($users as $user) {
            $userGroups = $user->getGroups();
            $groupKey = !empty($userGroups) ? $userGroups[0] : '';
            $groupTitle = isset($groups[$groupKey]) ? $groups[$groupKey]['title'] : 'Sin rol';

            $email = $user->email;

            $lastLogin = $user->lastLogin();
            $lastLoginDate = $lastLogin && $lastLogin->date ? date('d/m/Y', strtotime((string)$lastLogin->date)) : 'Nunca';
            $lastLoginTime = $lastLogin && $lastLogin->date ? date('H:i', strtotime((string)$lastLogin->date)) : '';

            $usersWithInfo[] = (object)[
                'id'            => $user->id,
                'username'      => $user->username,
                'email'         => $email,
                'active'        => !$user->isBanned(),
                'group'         => $groupKey,
                'groupTitle'    => $groupTitle,
                'lastLoginDate' => $lastLoginDate,
                'lastLoginTime' => $lastLoginTime,
            ];
        }

        $data = [
            'title'  => 'Gestión de Usuarios',
            'users'  => $usersWithInfo,
            'pager'  => $pager,
            'search' => $search,
            'total'  => count($usersWithInfo),
        ];

        echo view('template/header', $data);
        echo view('users/list', $data);
        echo view('template/footer');
    }
    public function create()
    {
        $groups = $this->getAssignableGroups();
        if (empty($groups)) {
            return redirect()->to('users')->with('error', 'No tienes permisos para crear usuarios con ningún rol.');
        }

        $data = [
            'title' => 'Crear Usuario',
            'groups' => $groups
        ];

        echo view('template/header', $data);
        echo view('users/create', $data);
        echo view('template/footer');
    }

    public function store()
    {
        $assignableGroups = $this->getAssignableGroups();
        $allowedGroups = array_keys($assignableGroups);

        if (empty($allowedGroups)) {
            return redirect()->to('users')->with('error', 'No tienes permisos para asignar roles en el sistema.');
        }

        $rules = [
            'email'    => [
                'label' => 'correo electrónico',
                'rules' => 'required|valid_email|is_unique[auth_identities.secret]',
                'errors' => [
                    'is_unique' => 'El correo electrónico ya está en uso.'
                ]
            ],
            'username' => [
                'label' => 'nombre de usuario',
                'rules' => 'required|alpha_numeric_space|min_length[3]|is_unique[users.username]|not_in_list[netcrew,root,admin,system]',
                'errors' => [
                    'is_unique' => 'El nombre de usuario ya está en uso.',
                    'not_in_list' => 'Este nombre de usuario no está permitido.'
                ]
            ],
            'phone' => [
                'label' => 'teléfono', 
                'rules' => 'permit_empty|max_length[20]|regex_match[/^\+[0-9 \-\.]{7,20}$/]',
                'errors' => [
                    'regex_match' => 'El teléfono debe incluir el código de país (ej: +34 o +58).'
                ]
            ],
            'password'     => ['label' => 'contraseña', 'rules' => 'required|min_length[8]'],
            'pass_confirm' => ['label' => 'confirmar contraseña', 'rules' => 'required|matches[password]'],
            'group'    => ['label' => 'rol/grupo', 'rules' => 'required|in_list[' . implode(',', $allowedGroups) . ']']
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $user = new User([
            'username' => $this->request->getPost('username'),
            'email'    => $this->request->getPost('email'),
            'phone'    => $this->request->getPost('phone'),
            'password' => $this->request->getPost('password'),
        ]);
        $user->active = 1; // Siempre activo para evitar el flujo de verificación de correo de Shield

        $db = \Config\Database::connect();
        $db->transBegin();

        try {
            $this->usersModel->save($user);
            $newUserId = $this->usersModel->getInsertID();
            $userObj = $this->usersModel->findById($newUserId);
            $userObj->addGroup($this->request->getPost('group'));

            // Si no se marcó como activo, lo baneamos (desactivamos) de entrada
            if (!$this->request->getPost('active')) {
                $userObj->ban('Desactivado por el administrador');
            }

            $db->transCommit();
            return redirect()->to('users')->with('message', 'Usuario creado.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', 'Error al crear usuario.');
        }
    }

    public function edit($id)
    {
        $user = $this->usersModel->findById($id);
        if (! $user) {
            return redirect()->to('users')->with('error', 'Usuario no encontrado.');
        }

        // Validación de jerarquía: no se puede editar a usuarios de nivel superior o igual (salvo uno mismo o superadmin)
        if (! $this->canManageUser($user)) {
            return redirect()->to('users')->with('error', 'Sin permisos para editar a este usuario.');
        }

        // Si se edita a sí mismo, el rol no puede ser modificado (solo se muestra su rol actual)
        $isSelf = ((int)$user->id === (int)auth()->id());
        if ($isSelf) {
            $userGroup = !empty($user->getGroups()) ? $user->getGroups()[0] : 'user';
            $allGroups = config('AuthGroups')->groups;
            $groups = isset($allGroups[$userGroup]) ? [$userGroup => $allGroups[$userGroup]] : [];
        } else {
            $groups = $this->getAssignableGroups();
        }

        $data = [
            'title'  => 'Editar Usuario',
            'user'   => $user,
            'groups' => $groups,
            'isSelf' => $isSelf,
        ];

        echo view('template/header', $data);
        echo view('users/edit', $data);
        echo view('template/footer');
    }

    public function update($id)
    {
        $user = $this->usersModel->findById($id);
        if (! $user) {
            return redirect()->to('users')->with('error', 'Usuario no encontrado.');
        }

        // Validación de jerarquía
        if (! $this->canManageUser($user)) {
            return redirect()->to('users')->with('error', 'Sin permisos para editar a este usuario.');
        }

        $isSelf = ((int)$user->id === (int)auth()->id());
        $assignableGroups = $this->getAssignableGroups();
        $allowedGroups = array_keys($assignableGroups);

        // Si se edita a sí mismo, su propio grupo actual es válido aunque no esté en assignableGroups
        if ($isSelf) {
            $currentUserGroup = !empty($user->getGroups()) ? $user->getGroups()[0] : 'user';
            $allowedGroups[] = $currentUserGroup;
            $allowedGroups = array_values(array_unique($allowedGroups));
        }

        $rules = [
            'username' => [
                'label' => 'nombre de usuario',
                'rules' => 'required|alpha_numeric_space|min_length[3]|is_unique[users.username,id,' . $id . ']|not_in_list[netcrew,root]',
                'errors' => [
                    'is_unique' => 'El nombre de usuario ya está en uso.',
                    'not_in_list' => 'Este nombre de usuario no está permitido.'
                ]
            ],
            'email'    => [
                'label' => 'correo electrónico',
                'rules' => 'required|valid_email|is_unique[auth_identities.secret,user_id,' . $id . ']',
                'errors' => [
                    'is_unique' => 'El correo electrónico ya está en uso.'
                ]
            ],
            'phone' => [
                'label' => 'teléfono', 
                'rules' => 'permit_empty|max_length[20]|regex_match[/^\+[0-9 \-\.]{7,20}$/]',
                'errors' => [
                    'regex_match' => 'El teléfono debe incluir el código de país (ej: +34 o +58).'
                ]
            ],
            'group'    => ['label' => 'rol/grupo', 'rules' => 'required|in_list[' . implode(',', $allowedGroups) . ']']
        ];

        if ($this->request->getPost('password')) {
            $rules['password']     = ['label' => 'contraseña', 'rules' => 'min_length[8]'];
            $rules['pass_confirm'] = ['label' => 'confirmar contraseña', 'rules' => 'required|matches[password]'];
        }

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        // Validación para evitar que el usuario se desactive a sí mismo
        if (!$this->request->getPost('active') && $isSelf) {
            return redirect()->back()->withInput()->with('error', 'No puedes desactivar tu propia cuenta.');
        }

        $user->fill([
            'username' => $this->request->getPost('username'),
            'email'    => $this->request->getPost('email'),
            'phone'    => $this->request->getPost('phone'),
        ]);

        if ($this->request->getPost('password')) {
            $user->setPassword($this->request->getPost('password'));
        }

        $this->usersModel->save($user);

        // Actualizar grupo SOLO si no es uno mismo (previene auto-escalada de privilegios)
        if (! $isSelf) {
            $user->syncGroups($this->request->getPost('group'));
        }

        // Gestor de estado activo/inactivo mediante baneo
        if ($this->request->getPost('active')) {
            $user->unBan();
        } else {
            $user->ban('Desactivado por el administrador');
        }

        return redirect()->to('users')->with('message', 'Usuario actualizado.');
    }

    public function toggleActive($id)
    {
        if ($this->request->getMethod() !== 'POST') {
            return redirect()->back();
        }

        $user = $this->usersModel->findById($id);
        if ($user) {

            // No permitir desactivarse a sí mismo
            if ($user->id === auth()->id() && !$user->isBanned()) {
                return redirect()->to('users')->with('error', 'No puedes desactivar tu cuenta.');
            }

            // Validación de jerarquía
            if (! $this->canManageUser($user)) {
                return redirect()->to('users')->with('error', 'Sin permisos para cambiar el estado de este usuario.');
            }

            if ($user->isBanned()) {
                $user->unBan();
                $statusStr = 'activado';
            } else {
                $user->ban('Desactivado por el administrador');
                $statusStr = 'desactivado';
            }

            return redirect()->to('users')->with('message', "Usuario {$statusStr}.");
        }

        return redirect()->to('users')->with('error', 'Usuario no encontrado.');
    }

    public function delete($id)
    {
        $user = $this->usersModel->findById($id);
        if ($user) {
            // No permitir eliminarse a sí mismo
            if ($user->id === auth()->id()) {
                return redirect()->to('users')->with('error', 'No puedes eliminar tu propia cuenta.');
            }

            // Validación de jerarquía
            if (! $this->canManageUser($user)) {
                return redirect()->to('users')->with('error', 'Sin permisos para eliminar a este usuario.');
            }

            $db = \Config\Database::connect();
            $db->transStart();

            try {
                // Eliminar foto de perfil si la tiene (Cumplimiento de Regla 3: Limpieza de Servidor)
                if (!empty($user->profile_pic) && file_exists(FCPATH . 'uploads/profile/' . $user->profile_pic)) {
                    unlink(FCPATH . 'uploads/profile/' . $user->profile_pic);
                }

                // Obtener redes del usuario para borrar sus dispositivos primero
                $networkModel = model(\App\Models\NetworkModel::class);
                $deviceModel  = model(\App\Models\DeviceModel::class);
                
                $userNetworks = $networkModel->where('owner_id', $user->id)->findAll();
                $keysToRemove = [];

                foreach ($userNetworks as $network) {
                    $devices = $deviceModel->where('network_id', $network->id)->findAll();
                    foreach ($devices as $d) {
                        if (!empty($d->public_key)) {
                            $keysToRemove[] = $d->public_key;
                        }
                    }
                    // Borrar los dispositivos (nodos) de la red
                    $deviceModel->where('network_id', $network->id)->delete();
                }

                // Borrar peers en Wireguard si hay dispositivos asociados y SSH está configurado
                if (!empty($keysToRemove) && \App\Libraries\WireguardService::isConfigured()) {
                    try {
                        $wg = \App\Libraries\WireguardService::fromSettings();
                        $wg->removePeers($keysToRemove);
                        $wg->saveConfig();
                        $wg->syncFirewall();
                    } catch (\Throwable $e) {
                        log_message('warning', 'Aviso no bloqueante: no se pudieron retirar peers en WireGuard al borrar usuario: ' . $e->getMessage());
                    }
                }

                // Borrar las redes del usuario
                $networkModel->where('owner_id', $user->id)->delete();

                // Borrar identidades primero para evitar registros huérfanos
                $identityModel = model(\CodeIgniter\Shield\Models\UserIdentityModel::class);
                $identityModel->where('user_id', $user->id)->delete();

                // Borrar usuario
                $this->usersModel->delete($user->id, true);

                $db->transComplete();

                if ($db->transStatus() === false) {
                    return redirect()->to('users')->with('error', 'Error en la transacción al eliminar usuario.');
                }

                return redirect()->to('users')->with('message', 'Usuario eliminado correctamente.');
            } catch (\Throwable $e) {
                $db->transRollback();
                return redirect()->to('users')->with('error', 'Error al eliminar usuario: ' . $e->getMessage());
            }
        }

        return redirect()->to('users')->with('error', 'Usuario no encontrado.');
    }

    // ---------------------------------------------------------------------
    // Helpers de Control Jerárquico y Prevención de Escalada de Privilegios
    // ---------------------------------------------------------------------

    /**
     * Nivel jerárquico numérico de cada grupo (mayor = mayor privilegio).
     */
    protected function getGroupLevel(string $group): int
    {
        return match ($group) {
            'superadmin' => 100,
            'supervisor' => 50,
            default      => 10,
        };
    }

    /**
     * Nivel jerárquico máximo del usuario según sus grupos asignados.
     *
     * @param User|object|null $user
     */
    protected function getUserLevel($user): int
    {
        if (! $user || ! method_exists($user, 'getGroups')) {
            return 0;
        }

        $groups = $user->getGroups();
        if (empty($groups)) {
            return 10;
        }

        $max = 0;
        foreach ($groups as $g) {
            $level = $this->getGroupLevel((string) $g);
            if ($level > $max) {
                $max = $level;
            }
        }

        return $max;
    }

    /**
     * Devuelve los grupos que el usuario autenticado tiene permitido asignar a otros.
     * - superadmin: puede asignar cualquier grupo.
     * - supervisor: solo puede asignar roles inferiores ('user' / roles base).
     * - user: solo puede asignar roles de su mismo nivel base ('user').
     * En NINGÚN caso un no-superadmin puede asignar 'superadmin' ni 'supervisor'.
     */
    protected function getAssignableGroups(): array
    {
        $currentUser = auth()->user();
        if (! $currentUser) {
            return [];
        }

        $allGroups = config('AuthGroups')->groups;

        if ($currentUser->inGroup('superadmin')) {
            return $allGroups;
        }

        $myLevel = $this->getUserLevel($currentUser);
        $assignable = [];

        foreach ($allGroups as $key => $data) {
            $groupLevel = $this->getGroupLevel($key);
            // Solo se permiten roles de nivel menor que el supervisor, o nivel base user
            if ($myLevel >= 50 ? ($groupLevel < $myLevel) : ($groupLevel <= $myLevel)) {
                $assignable[$key] = $data;
            }
        }

        return $assignable;
    }

    /**
     * Comprueba si el usuario autenticado tiene jerarquía suficiente para gestionar a otro usuario.
     *
     * @param User|object|null $targetUser
     */
    protected function canManageUser($targetUser): bool
    {
        $currentUser = auth()->user();
        if (! $currentUser || ! $targetUser) {
            return false;
        }

        // El superadmin puede gestionar a cualquier cuenta
        if (method_exists($currentUser, 'inGroup') && $currentUser->inGroup('superadmin')) {
            return true;
        }

        $myLevel     = $this->getUserLevel($currentUser);
        $targetLevel = $this->getUserLevel($targetUser);

        // Nadie que no sea superadmin puede gestionar a un superadmin o a alguien de mayor nivel
        if ($targetLevel >= 100 || $targetLevel > $myLevel) {
            return false;
        }

        // Si tienen el mismo nivel y no es uno mismo, un no-superadmin no puede gestionar a su par
        $targetId  = is_object($targetUser) ? ($targetUser->id ?? null) : null;
        $currentId = is_object($currentUser) ? ($currentUser->id ?? null) : null;

        if ($targetLevel === $myLevel && (int) $targetId !== (int) $currentId) {
            return false;
        }

        return true;
    }
}
