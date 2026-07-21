<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Validation\StrictRules\CreditCardRules;
use CodeIgniter\Validation\StrictRules\FileRules;
use CodeIgniter\Validation\StrictRules\FormatRules;
use CodeIgniter\Validation\StrictRules\Rules;

class Validation extends BaseConfig
{
    // --------------------------------------------------------------------
    // Setup
    // --------------------------------------------------------------------

    /**
     * Stores the classes that contain the
     * rules that are available.
     *
     * @var list<string>
     */
    public array $ruleSets = [
        Rules::class,
        FormatRules::class,
        FileRules::class,
        CreditCardRules::class,
    ];

    /**
     * Specifies the views that are used to display the
     * errors.
     *
     * @var array<string, string>
     */
    public array $templates = [
        'list'   => 'CodeIgniter\Validation\Views\list',
        'single' => 'CodeIgniter\Validation\Views\single',
    ];

    // --------------------------------------------------------------------
    // Rules
    // --------------------------------------------------------------------

    public array $registration = [
        'username' => [
            'label'  => 'Auth.username',
            'rules'  => [
                'required',
                'max_length[30]',
                'min_length[3]',
                'alpha_numeric_space',
                'is_unique[users.username]',
                'not_in_list[netcrew,root,admin,system]',
            ],
            'errors' => [
                'not_in_list' => 'Este nombre de usuario no está permitido.',
            ],
        ],
        'email' => [
            'label'  => 'Auth.email',
            'rules'  => [
                'required',
                'max_length[254]',
                'valid_email',
                'is_unique[auth_identities.secret]',
            ],
        ],
        'phone' => [
            'label'  => 'Teléfono',
            'rules'  => [
                'permit_empty',
                'max_length[20]',
                'regex_match[/^\+[0-9 \-\.]{7,20}$/]'
            ],
            'errors' => [
                'regex_match' => 'El teléfono debe incluir el código de país (ej: +34 o +58).'
            ]
        ],
        'password' => [
            'label'  => 'Auth.password',
            'rules'  => 'required|max_byte[72]|strong_password[]',
            'errors' => [
                'max_byte' => 'Auth.errorPasswordTooLongBytes',
            ],
        ],
        'password_confirm' => [
            'label'  => 'Auth.passwordConfirm',
            'rules'  => 'required|matches[password]',
        ],
    ];
}
