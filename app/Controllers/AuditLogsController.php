<?php

namespace App\Controllers;

use App\Models\AuditLogModel;

class AuditLogsController extends BaseController
{
    public function index()
    {
        $model = new AuditLogModel();
        $search = $this->request->getGet('q');

        $builder = $model->orderBy('id', 'DESC');

        if (!empty($search)) {
            $builder->groupStart()
                    ->like('username', $search)
                    ->orLike('action', $search)
                    ->orLike('description', $search)
                    ->orLike('ip_address', $search)
                    ->groupEnd();
        }

        $perPage = 25;
        $logs = $builder->paginate($perPage, 'audit');
        $pager = $model->pager;

        $data = [
            'title'  => 'Registro de Auditoría',
            'logs'   => $logs,
            'pager'  => $pager,
            'search' => $search,
        ];

        echo view('template/header', $data);
        echo view('audit/index', $data);
        echo view('template/footer');
    }
}

