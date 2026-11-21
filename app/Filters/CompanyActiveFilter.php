<?php

namespace App\Filters;

use App\Models\CompanyModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class CompanyActiveFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! function_exists('auth') || ! auth()->loggedIn()) {
            return;
        }

        $user = auth()->user();
        if (! $user || $user->inGroup('superadmin')) {
            return;
        }

        $companyId = $user->company_id ?? null;
        if (empty($companyId)) {
            auth()->logout();
            return redirect()->to('/login')->with('error', 'Tu cuenta no tiene una empresa asignada. Contacta con el administrador.');
        }

        $company = (new CompanyModel())->find((int) $companyId);
        if (! $company || empty($company->active)) {
            auth()->logout();
            return redirect()->to('/login')->with('error', 'Tu empresa se encuentra suspendida o inactiva. Contacta con soporte.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
