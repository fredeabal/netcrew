<?php

namespace App\Controllers;

use App\Models\NetworkModel;

class DashboardController extends BaseController
{
    public function index()
    {
        $user = auth()->user();
        $networkModel = new NetworkModel();
        
        $data = [
            'title' => 'Dashboard'
        ];

        if ($user->inGroup('superadmin', 'supervisor')) {
            $userModel = auth()->getProvider();
            $deviceModel = new \App\Models\DeviceModel();
            $data['totalUsers'] = $userModel->countAllResults();
            $data['totalNetworks'] = $networkModel->countAllResults();
            $data['totalDevices'] = $deviceModel->countAllResults();
            // Fetch last 5 networks
            $data['recentNetworks'] = $networkModel->select('networks.*, users.username as owner_username')
                                                  ->join('users', 'users.id = networks.owner_id', 'left')
                                                  ->orderBy('networks.created_at', 'DESC')
                                                  ->limit(5)
                                                  ->find();
        } else {
            // Regular user
            $data['networks'] = $networkModel->where('owner_id', $user->id)
                                             ->orderBy('created_at', 'DESC')
                                             ->find();
            $data['networksCount'] = count($data['networks']);
            
            $deviceModel = new \App\Models\DeviceModel();
            $networkIds = array_column($data['networks'], 'id');
            $data['devicesCount'] = empty($networkIds) ? 0 : $deviceModel->whereIn('network_id', $networkIds)->countAllResults();
        }

        echo view('template/header', $data);
        
        if ($user->inGroup('superadmin', 'supervisor')) {
            echo view('dashboards/admin', $data);
        } else {
            echo view('dashboards/user', $data);
        }
        
        echo view('template/footer');
    }
}
