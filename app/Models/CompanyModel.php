<?php

namespace App\Models;

use CodeIgniter\Model;

class CompanyModel extends Model
{
    protected $table            = 'companies';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'name',
        'slug',
        'active',
        'max_networks',
        'max_devices',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'id'           => 'permit_empty|is_natural_no_zero',
        'name'         => 'required|min_length[2]|max_length[120]',
        'slug'         => 'required|alpha_dash|min_length[2]|max_length[120]|is_unique[companies.slug,id,{id}]',
        'active'       => 'permit_empty|in_list[0,1]',
        'max_networks' => 'permit_empty|is_natural',
        'max_devices'  => 'permit_empty|is_natural',
    ];

    protected $validationMessages = [
        'name' => [
            'required'   => 'El nombre de la empresa es obligatorio.',
            'min_length' => 'El nombre de la empresa debe tener al menos 2 caracteres.',
            'max_length' => 'El nombre de la empresa no puede superar los 120 caracteres.',
        ],
        'slug' => [
            'required'   => 'El identificador (slug) es obligatorio.',
            'alpha_dash' => 'El identificador solo puede contener letras, números, guiones y guiones bajos.',
            'is_unique'  => 'Ya existe una empresa con ese identificador.',
        ],
    ];

    /**
     * Devuelve el listado de empresas con conteo de usuarios, redes y dispositivos.
     */
    public function getCompaniesWithStats(?string $search = null): array
    {
        $builder = $this->select('companies.*')
            ->orderBy('companies.id', 'ASC');

        if (! empty($search)) {
            $builder->groupStart()
                ->like('companies.name', $search)
                ->orLike('companies.slug', $search)
                ->groupEnd();
        }

        $companies = $builder->findAll();
        if (empty($companies)) {
            return [];
        }

        $db = $this->db;

        $usersCounts = [];
        foreach ($db->table('users')->select('company_id, COUNT(id) as cnt')->where('company_id IS NOT NULL', null, false)->groupBy('company_id')->get()->getResult() as $row) {
            $usersCounts[(int) $row->company_id] = (int) $row->cnt;
        }

        $networksCounts = [];
        foreach ($db->table('networks')->select('company_id, COUNT(id) as cnt')->where('company_id IS NOT NULL', null, false)->groupBy('company_id')->get()->getResult() as $row) {
            $networksCounts[(int) $row->company_id] = (int) $row->cnt;
        }

        $devicesCounts = [];
        foreach ($db->table('devices')
            ->select('networks.company_id, COUNT(devices.id) as cnt')
            ->join('networks', 'networks.id = devices.network_id', 'inner')
            ->where('networks.company_id IS NOT NULL', null, false)
            ->groupBy('networks.company_id')
            ->get()
            ->getResult() as $row) {
            $devicesCounts[(int) $row->company_id] = (int) $row->cnt;
        }

        foreach ($companies as $company) {
            $cid                    = (int) $company->id;
            $company->users_count   = $usersCounts[$cid] ?? 0;
            $company->networks_count = $networksCounts[$cid] ?? 0;
            $company->devices_count = $devicesCounts[$cid] ?? 0;
        }

        return $companies;
    }

    /**
     * Genera un slug limpio a partir de un nombre.
     */
    public static function generateSlug(string $name): string
    {
        $slug = mb_strtolower(trim($name), 'UTF-8');
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug) ?: $slug;
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'empresa-' . time();
    }
}
