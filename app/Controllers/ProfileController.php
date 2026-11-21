<?php

namespace App\Controllers;

use CodeIgniter\Files\File;
use CodeIgniter\Shield\Models\UserModel;

class ProfileController extends BaseController
{
    // ---------------------------------------------------------------------
    // Muestra la vista principal
    // ---------------------------------------------------------------------
    public function index()
    {
        $user = auth()->user();

        if (!$user) {
            return redirect()->to(base_url('login'))->with('error', 'Usuario no encontrado.');
        }

        $data = [
            'title' => 'Mi Perfil de Usuario',
            'user'  => $user,
        ];

        echo view('template/header', $data);
        echo view('user/profile', $data);
        echo view('template/footer');
    }

    // ---------------------------------------------------------------------
    // Actualiza los datos del perfil
    // ---------------------------------------------------------------------
    public function update()
    {
        if ($this->request->getMethod() !== 'POST') {
            return redirect()->back();
        }

        $user = auth()->user();
        if (!$user) {
            return redirect()->to(base_url('login'))->with('error', 'Sesión expirada.');
        }

        // El campo 'email' usa validación especial de Shield si queremos usar email_is_unique
        // o manual a auth_identities
        $rules = [
            'username' => [
                'rules' => "required|alpha_numeric_space|min_length[3]|max_length[30]|is_unique[users.username,id,{$user->id}]|not_in_list[netcrew,root,admin,system]",
                'errors' => [
                    'is_unique'   => 'El nombre de usuario ya está en uso.',
                    'not_in_list' => 'Este nombre de usuario no está permitido.',
                ]
            ],
            'name'     => 'permit_empty|max_length[100]',
            'email'    => [
                'rules' => "required|valid_email|is_unique[auth_identities.secret,user_id,{$user->id}]",
                'errors' => [
                    'is_unique' => 'El correo electrónico ya está en uso.'
                ]
            ],
            'phone'    => [
                'label' => 'teléfono', 
                'rules' => 'permit_empty|max_length[20]|regex_match[/^\+[0-9 \-\.]{7,20}$/]',
                'errors' => [
                    'regex_match' => 'El teléfono debe incluir el código de país (ej: +34 o +58).'
                ]
            ],
            'theme'    => 'permit_empty|in_list[light,dark,system]',
        ];

        // Detectar si el usuario solicita un cambio sensible (correo o contraseña)
        $password        = (string) $this->request->getPost('password');
        $newEmail        = trim((string) $this->request->getPost('email'));
        $emailIdentity   = $user->getEmailIdentity();
        $currentEmail    = $emailIdentity ? (string) $emailIdentity->secret : (string) $user->email;
        $emailChanged    = strcasecmp($newEmail, $currentEmail) !== 0;
        $passwordChanged = $password !== '';

        if ($passwordChanged) {
            $rules['password']         = 'required|min_length[8]';
            $rules['password_confirm'] = 'required|matches[password]';
        }

        if ($emailChanged || $passwordChanged) {
            $rules['current_password'] = [
                'label'  => 'contraseña actual',
                'rules'  => 'required',
                'errors' => [
                    'required' => 'Debes ingresar tu contraseña actual para cambiar tu correo electrónico o contraseña.',
                ],
            ];
        }

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        if ($emailChanged || $passwordChanged) {
            $currentPassword = (string) $this->request->getPost('current_password');
            $currentHash     = $emailIdentity ? (string) $emailIdentity->secret2 : (string) $user->password_hash;

            if ($currentHash === '' || !service('passwords')->verify($currentPassword, $currentHash)) {
                return redirect()->back()->withInput()->with('error', 'La contraseña actual no es correcta.');
            }
        }

        // Llenar datos base
        $fillData = [
            'username' => $this->request->getPost('username'),
            'name'     => $this->request->getPost('name'),
            'email'    => $this->request->getPost('email'),
            'phone'    => $this->request->getPost('phone'),
            'theme'    => $this->request->getPost('theme') ?: 'system',
        ];

        if (!empty($password)) {
            $fillData['password'] = $password;
        }

        $user->fill($fillData);

        // Gestión de la foto de perfil
        $img = $this->request->getFile('profile_pic');
        if ($img && $img->isValid() && !$img->hasMoved()) {
            $validationRule = [
                'profile_pic' => [
                    'label' => 'Foto de Perfil',
                    'rules' => [
                        'uploaded[profile_pic]',
                        'is_image[profile_pic]',
                        'mime_in[profile_pic,image/jpg,image/jpeg,image/gif,image/png,image/webp]',
                        'max_size[profile_pic,2048]',
                    ],
                ],
            ];

            if ($this->validate($validationRule)) {
                $newName = $img->getRandomName();
                $uploadDir = FCPATH . 'uploads/profile/';
                
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $img->move($uploadDir, $newName);
                
                // Borrar foto antigua si existe
                if (!empty($user->profile_pic)) {
                    $oldPath = $uploadDir . $user->profile_pic;
                    if (file_exists($oldPath)) {
                        unlink($oldPath);
                    }
                }

                $user->profile_pic = $newName;
            } else {
                return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            }
        }

        // Guardar usuario
        $usersModel = new UserModel();
        try {
            $usersModel->save($user);

            if ($this->request->getPost('password')) {
                model(\CodeIgniter\Shield\Models\RememberModel::class)->purgeRememberTokens($user);
            }

            // Guardar el tema explícitamente directo en BD (evita restricciones de Shield)
            $db = \Config\Database::connect();
            $db->table('users')->where('id', $user->id)->update([
                'theme' => $this->request->getPost('theme') ?: 'system'
            ]);
        } catch (\Exception $e) {
            log_message('error', '[ProfileUpdate] ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
            return redirect()->back()->withInput()->with('error', 'Error al guardar: ' . esc($e->getMessage()));
        }

        return redirect()->to('profile')->with('message', 'Perfil actualizado.');
    }
}
