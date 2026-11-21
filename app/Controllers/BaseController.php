<?php

namespace App\Controllers;

use App\Models\CompanyModel;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController proporciona helpers y utilidades comunes para los controladores.
 */
abstract class BaseController extends Controller
{
    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
    }

    /**
     * Indica si el usuario autenticado es Superadministrador global de la plataforma.
     */
    protected function isSuperAdmin(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->inGroup('superadmin');
    }

    /**
     * Indica si el usuario autenticado es Administrador de su Empresa (supervisor).
     */
    protected function isCompanyAdmin(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->inGroup('supervisor');
    }

    /**
     * Devuelve el ID de empresa del usuario autenticado (null para superadmin global sin empresa fija).
     */
    protected function currentCompanyId(): ?int
    {
        $user = auth()->user();
        if (! $user || empty($user->company_id)) {
            return null;
        }

        return (int) $user->company_id;
    }

    /**
     * Devuelve el registro de empresa del usuario autenticado, si aplica.
     */
    protected function currentCompany(): ?object
    {
        $companyId = $this->currentCompanyId();
        if ($companyId === null) {
            return null;
        }

        return (new CompanyModel())->find($companyId);
    }

    /**
     * Comprueba si el usuario autenticado puede acceder a una red concreta.
     * - superadmin: acceso total.
     * - supervisor (Admin de Empresa): acceso a todas las redes de su empresa.
     * - user: acceso solo a sus propias redes dentro de su empresa.
     */
    protected function canAccessNetwork(object $network): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        $userCompanyId = $this->currentCompanyId();
        if ($userCompanyId === null || (int) ($network->company_id ?? 0) !== $userCompanyId) {
            return false;
        }

        if ($this->isCompanyAdmin()) {
            return true;
        }

        return (int) $network->owner_id === (int) $user->id;
    }
}
