<?php

namespace App\Controllers;

use App\Models\AuditLogModel;
use App\Models\CompanyModel;
use App\Models\NetworkModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\RedirectResponse;

class CompaniesController extends BaseController
{
    protected CompanyModel $companyModel;

    public function __construct()
    {
        $this->companyModel = new CompanyModel();
    }

    protected function denyIfUnauthorized(): ?RedirectResponse
    {
        $user = auth()->user();
        if (! $user || (! $this->isSuperAdmin() && ! $user->can('admin.companies'))) {
            return redirect()->to('/dashboard')->with('error', 'No tienes permisos para gestionar empresas.');
        }

        return null;
    }

    public function index()
    {
        if ($deny = $this->denyIfUnauthorized()) {
            return $deny;
        }

        $rawSearch = $this->request->getGet('q');
        $search    = (is_string($rawSearch) && trim($rawSearch) !== '') ? trim($rawSearch) : null;

        $data = [
            'title'     => 'Gestión de Empresas',
            'companies' => $this->companyModel->getCompaniesWithStats($search),
            'search'    => $search,
        ];

        echo view('template/header', $data);
        echo view('companies/index', $data);
        echo view('template/footer');
    }

    public function create()
    {
        if ($deny = $this->denyIfUnauthorized()) {
            return $deny;
        }

        $data = [
            'title' => 'Nueva Empresa',
        ];

        echo view('template/header', $data);
        echo view('companies/create', $data);
        echo view('template/footer');
    }

    public function store()
    {
        if ($deny = $this->denyIfUnauthorized()) {
            return $deny;
        }

        if (strtoupper($this->request->getMethod()) !== 'POST') {
            return redirect()->to('/companies');
        }

        $name           = trim((string) ($this->request->getPost('name') ?? ''));
        $rawSlug        = trim((string) ($this->request->getPost('slug') ?? ''));
        $slug           = $rawSlug !== '' ? $rawSlug : CompanyModel::generateSlug($name);
        $rawMaxNetworks = trim((string) ($this->request->getPost('max_networks') ?? ''));
        $rawMaxDevices  = trim((string) ($this->request->getPost('max_devices') ?? ''));
        $rawActive      = $this->request->getPost('active');
        $active         = ($rawActive === null) ? 1 : (in_array((string) $rawActive, ['0', ''], true) ? 0 : 1);

        $data = [
            'name'         => $name,
            'slug'         => $slug,
            'max_networks' => $rawMaxNetworks === '' ? 0 : $rawMaxNetworks,
            'max_devices'  => $rawMaxDevices === '' ? 0 : $rawMaxDevices,
            'active'       => $active,
        ];

        if (! $this->companyModel->save($data)) {
            return redirect()->back()->withInput()->with('errors', $this->companyModel->errors());
        }

        $newId = (int) $this->companyModel->getInsertID();
        AuditLogModel::log('COMPANY_CREATE', "Creó la empresa '{$name}' (slug: {$slug})", null, null, $newId);

        return redirect()->to('/companies')->with('message', 'Empresa creada correctamente.');
    }

    public function edit($id)
    {
        if ($deny = $this->denyIfUnauthorized()) {
            return $deny;
        }

        $company = $this->companyModel->find((int) $id);
        if (! $company) {
            return redirect()->to('/companies')->with('error', 'Empresa no encontrada.');
        }

        $data = [
            'title'   => 'Editar Empresa',
            'company' => $company,
        ];

        echo view('template/header', $data);
        echo view('companies/edit', $data);
        echo view('template/footer');
    }

    public function update($id)
    {
        if ($deny = $this->denyIfUnauthorized()) {
            return $deny;
        }

        if (strtoupper($this->request->getMethod()) !== 'POST') {
            return redirect()->to('/companies');
        }

        $id      = (int) $id;
        $company = $this->companyModel->find($id);
        if (! $company) {
            return redirect()->to('/companies')->with('error', 'Empresa no encontrada.');
        }

        $name           = trim((string) ($this->request->getPost('name') ?? ''));
        $rawSlug        = trim((string) ($this->request->getPost('slug') ?? ''));
        $slug           = $rawSlug !== '' ? $rawSlug : CompanyModel::generateSlug($name);
        $rawMaxNetworks = trim((string) ($this->request->getPost('max_networks') ?? ''));
        $rawMaxDevices  = trim((string) ($this->request->getPost('max_devices') ?? ''));
        $active         = ! empty($this->request->getPost('active')) ? 1 : 0;

        if (! $this->isSuperAdmin() && $this->currentCompanyId() === $id && $active === 0) {
            return redirect()->back()->withInput()->with('error', 'No puedes desactivar tu propia empresa.');
        }

        $data = [
            'id'           => $id,
            'name'         => $name,
            'slug'         => $slug,
            'max_networks' => $rawMaxNetworks === '' ? 0 : $rawMaxNetworks,
            'max_devices'  => $rawMaxDevices === '' ? 0 : $rawMaxDevices,
            'active'       => $active,
        ];

        if (! $this->companyModel->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->companyModel->errors());
        }

        AuditLogModel::log('COMPANY_UPDATE', "Actualizó la empresa '{$name}' (ID: {$id})", null, null, $id);

        return redirect()->to('/companies')->with('message', 'Empresa actualizada correctamente.');
    }

    public function toggleActive($id)
    {
        if ($deny = $this->denyIfUnauthorized()) {
            return $deny;
        }

        if (strtoupper($this->request->getMethod()) !== 'POST') {
            return redirect()->to('/companies');
        }

        $id      = (int) $id;
        $company = $this->companyModel->find($id);
        if (! $company) {
            return redirect()->to('/companies')->with('error', 'Empresa no encontrada.');
        }

        if (! $this->isSuperAdmin() && $this->currentCompanyId() === $id && ! empty($company->active)) {
            return redirect()->to('/companies')->with('error', 'No puedes desactivar tu propia empresa.');
        }

        $newActive = empty($company->active) ? 1 : 0;
        $this->companyModel->update($id, ['active' => $newActive]);

        $status = $newActive ? 'activada' : 'desactivada';
        AuditLogModel::log('COMPANY_TOGGLE', "Cambió estado a {$status} para la empresa '{$company->name}' (ID: {$id})", null, null, $id);

        return redirect()->to('/companies')->with('message', "Empresa {$status}.");
    }

    public function delete($id)
    {
        if ($deny = $this->denyIfUnauthorized()) {
            return $deny;
        }

        if (strtoupper($this->request->getMethod()) !== 'POST') {
            return redirect()->to('/companies');
        }

        $id      = (int) $id;
        $company = $this->companyModel->find($id);
        if (! $company) {
            return redirect()->to('/companies')->with('error', 'Empresa no encontrada.');
        }

        if (! $this->isSuperAdmin() && $this->currentCompanyId() === $id) {
            return redirect()->to('/companies')->with('error', 'No puedes eliminar tu propia empresa.');
        }

        $usersCount = (new UserModel())->where('company_id', $id)->countAllResults();
        if ($usersCount > 0) {
            return redirect()->to('/companies')->with('error', "No se puede eliminar la empresa porque tiene {$usersCount} usuario(s) asociado(s).");
        }

        $networksCount = (new NetworkModel())->where('company_id', $id)->countAllResults();
        if ($networksCount > 0) {
            return redirect()->to('/companies')->with('error', "No se puede eliminar la empresa porque tiene {$networksCount} red(es) asociada(s).");
        }

        if (! $this->companyModel->delete($id)) {
            return redirect()->to('/companies')->with('error', 'No se pudo eliminar la empresa.');
        }

        AuditLogModel::log('COMPANY_DELETE', "Eliminó la empresa '{$company->name}' (ID: {$id})", null, null, $id);

        return redirect()->to('/companies')->with('message', 'Empresa eliminada correctamente.');
    }
}
