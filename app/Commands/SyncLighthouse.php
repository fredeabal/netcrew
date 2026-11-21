<?php

namespace App\Commands;

use App\Libraries\NebulaService;
use App\Models\NetworkModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SyncLighthouse extends BaseCommand
{
    protected $group       = 'NetCrew';
    protected $name        = 'nebula:sync-lighthouse';
    protected $description = 'Configura /etc/nebula/config.yml con la red activa y reinicia el servicio Lighthouse.';

    public function run(array $params)
    {
        if (NebulaService::getGlobalCa() !== null) {
            if (NebulaService::syncGlobalLighthouse()) {
                CLI::write('Lighthouse global sincronizado (10.0.0.1/8).', 'green');
            } else {
                CLI::error('No se pudo sincronizar el Lighthouse global local.');
            }
            return;
        }

        $model   = new NetworkModel();
        $network = $model->where('active', 1)->orderBy('id', 'ASC')->first();

        if (! $network) {
            CLI::write('No hay redes activas para configurar el Lighthouse.', 'yellow');
            return;
        }

        $caKey = $model->decryptCaKey((string) ($network->nebula_ca_key ?? ''));
        if (empty($network->nebula_ca_crt) || $caKey === '') {
            CLI::error('La red activa no tiene CA válida.');
            return;
        }

        if (NebulaService::syncLocalLighthouse($network, $caKey)) {
            CLI::write("Lighthouse sincronizado para '{$network->name}' ({$network->cidr}).", 'green');
        } else {
            CLI::error('No se pudo sincronizar el Lighthouse local.');
        }
    }
}
