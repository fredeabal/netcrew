<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Shield\Entities\User;

class UsersController extends BaseController
{
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
        $groups = config('AuthGroups')->groups;
        if (! auth()->user()->inGroup('superadmin')) {
            unset($groups['superadmin']);
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
        $allowedGroups = array_keys(config('AuthGroups')->groups);
        if (! auth()->user()->inGroup('superadmin')) {
            $allowedGroups = array_filter($allowedGroups, fn($g) => $g !== 'superadmin');
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


        if ($user->inGroup('superadmin') && !auth()->user()->inGroup('superadmin')) {
            return redirect()->to('users')->with('error', 'Sin permisos para editar a un superadmin.');
        }

        $groups = config('AuthGroups')->groups;
        if (! auth()->user()->inGroup('superadmin')) {
            unset($groups['superadmin']);
        }

        $data = [
            'title'  => 'Editar Usuario',
            'user'   => $user,
            'groups' => $groups
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


        if ($user->inGroup('superadmin') && !auth()->user()->inGroup('superadmin')) {
            return redirect()->to('users')->with('error', 'Sin permisos para editar a un superadmin.');
        }

        $allowedGroups = array_keys(config('AuthGroups')->groups);
        if (! auth()->user()->inGroup('superadmin')) {
            $allowedGroups = array_filter($allowedGroups, fn($g) => $g !== 'superadmin');
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
        if (!$this->request->getPost('active') && $user->id === auth()->id()) {
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

        // Update group
        $user->syncGroups($this->request->getPost('group'));

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

            // No permitir a un admin normal desactivar a un superadmin
            if ($user->inGroup('superadmin') && !auth()->user()->inGroup('superadmin')) {
                return redirect()->to('users')->with('error', 'Sin permisos para desactivar superadmin.');
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
            if ($user->inGroup('superadmin') && !auth()->user()->inGroup('superadmin')) {
                return redirect()->to('users')->with('error', 'Sin permisos para eliminar a un superadmin.');
            }
            try {
                // Eliminar foto de perfil si la tiene (Cumplimiento de Regla 3: Limpieza de Servidor)
                if (!empty($user->profile_pic) && file_exists(FCPATH . 'uploads/profile/' . $user->profile_pic)) {
                    unlink(FCPATH . 'uploads/profile/' . $user->profile_pic);
                }

                // Obtener redes del usuario para borrar sus dispositivos primero
                $networkModel = model(\App\Models\NetworkModel::class);
                $deviceModel  = model(\App\Models\DeviceModel::class);
                
                $userNetworks = $networkModel->where('owner_id', $user->id)->findAll();
                foreach ($userNetworks as $network) {
                    // Borrar los dispositivos (nodos) de la red
                    $deviceModel->where('network_id', $network->id)->delete();
                }

                // Borrar las redes del usuario
                $networkModel->where('owner_id', $user->id)->delete();

                // Borrar identidades primero para evitar registros huérfanos
                $identityModel = model(\CodeIgniter\Shield\Models\UserIdentityModel::class);
                $identityModel->where('user_id', $user->id)->delete();

                // Borrar usuario
                $this->usersModel->delete($user->id, true);
                return redirect()->to('users')->with('message', 'Usuario eliminado.');
            } catch (\Throwable $e) {
                return redirect()->to('users')->with('error', 'Error al eliminar usuario: ' . $e->getMessage());
            }
        }

        return redirect()->to('users')->with('error', 'Usuario no encontrado.');
    }

}
