<?php

namespace App\Controllers;

use App\Libraries\NebulaService;
use App\Models\CompanyModel;
use App\Models\DeviceModel;
use App\Models\NetworkModel;

class DashboardController extends BaseController
{
    public function index()
    {
        $user         = auth()->user();
        $networkModel = new NetworkModel();
        $deviceModel  = new DeviceModel();

        $data = [
            'title' => 'Dashboard',
        ];

        // Estado base de Nebula P2P
        $telemetry = [
            'isConfigured'  => NebulaService::isConfigured(),
            'isInterfaceUp' => NebulaService::isLighthouseRunning(),
        ];

        $isSuperAdmin   = $this->isSuperAdmin();
        $isCompanyAdmin = $this->isCompanyAdmin();

        if ($isSuperAdmin) {
            $userModel              = auth()->getProvider();
            $companyModel           = new CompanyModel();
            $data['isSuperAdmin']   = true;
            $data['totalCompanies'] = $companyModel->countAllResults();
            $data['totalUsers']     = $userModel->countAllResults();
            $data['totalNetworks']  = $networkModel->countAllResults();
            $data['totalDevices']   = $deviceModel->countAllResults();

            // Últimas 5 redes registradas con conteo de nodos
            $data['recentNetworks'] = $networkModel
                ->select('networks.*, users.username as owner_username, companies.name as company_name')
                ->selectCount('devices.id', 'device_count')
                ->join('users', 'users.id = networks.owner_id', 'left')
                ->join('companies', 'companies.id = networks.company_id', 'left')
                ->join('devices', 'devices.network_id = networks.id', 'left')
                ->groupBy('networks.id')
                ->orderBy('networks.created_at', 'DESC')
                ->findAll(5);
        } elseif ($isCompanyAdmin) {
            $companyId            = $this->currentCompanyId();
            $company              = $this->currentCompany();
            $data['isSuperAdmin'] = false;
            $data['companyName']  = $company ? $company->name : null;

            if ($companyId === null) {
                $data['totalUsers']     = 0;
                $data['totalNetworks']  = 0;
                $data['totalDevices']   = 0;
                $data['recentNetworks'] = [];
            } else {
                $userModel        = auth()->getProvider();
                $db               = $userModel->db;
                $usersTable       = $db->prefixTable('users');
                $groupsUsersTable = $db->prefixTable('auth_groups_users');
                $groupColumn      = $db->protectIdentifiers('group');

                $data['totalUsers'] = $userModel
                    ->where('users.company_id', $companyId)
                    ->where("{$usersTable}.id NOT IN (SELECT user_id FROM {$groupsUsersTable} WHERE {$groupColumn} = 'superadmin')", null, false)
                    ->countAllResults();

                $data['totalNetworks'] = $networkModel->where('company_id', $companyId)->countAllResults();
                $data['totalDevices']  = $deviceModel
                    ->join('networks', 'networks.id = devices.network_id', 'inner')
                    ->where('networks.company_id', $companyId)
                    ->countAllResults();

                $data['recentNetworks'] = $networkModel
                    ->select('networks.*, users.username as owner_username, companies.name as company_name')
                    ->selectCount('devices.id', 'device_count')
                    ->join('users', 'users.id = networks.owner_id', 'left')
                    ->join('companies', 'companies.id = networks.company_id', 'left')
                    ->join('devices', 'devices.network_id = networks.id', 'left')
                    ->where('networks.company_id', $companyId)
                    ->groupBy('networks.id')
                    ->orderBy('networks.created_at', 'DESC')
                    ->findAll(5);
            }
        } else {
            // Usuario estándar
            $companyId = $this->currentCompanyId();
            if ($companyId === null) {
                $data['networksCount'] = 0;
                $data['networks']      = [];
                $data['devicesCount']  = 0;
            } else {
                $data['networksCount'] = $networkModel
                    ->where('owner_id', (int) $user->id)
                    ->where('company_id', $companyId)
                    ->countAllResults();

                $data['networks'] = $networkModel
                    ->select('networks.*, users.username as owner_username, companies.name as company_name')
                    ->selectCount('devices.id', 'device_count')
                    ->join('users', 'users.id = networks.owner_id', 'left')
                    ->join('companies', 'companies.id = networks.company_id', 'left')
                    ->join('devices', 'devices.network_id = networks.id', 'left')
                    ->where('networks.owner_id', (int) $user->id)
                    ->where('networks.company_id', $companyId)
                    ->groupBy('networks.id')
                    ->orderBy('networks.created_at', 'DESC')
                    ->findAll(5);

                $data['devicesCount'] = $deviceModel
                    ->join('networks', 'networks.id = devices.network_id', 'inner')
                    ->where('networks.owner_id', (int) $user->id)
                    ->where('networks.company_id', $companyId)
                    ->countAllResults();
            }
        }

        $data['telemetry'] = $telemetry;

        echo view('template/header', $data);

        if ($isSuperAdmin || $isCompanyAdmin) {
            echo view('dashboards/admin', $data);
        } else {
            echo view('dashboards/user', $data);
        }

        echo view('template/footer');
    }
}
