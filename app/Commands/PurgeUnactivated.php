<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Models\UserIdentityModel;
use App\Models\NetworkModel;
use App\Models\DeviceModel;

class PurgeUnactivated extends BaseCommand
{
    protected $group       = 'Auth';
    protected $name        = 'auth:purge-unactivated';
    protected $description = 'Elimina usuarios no activados que han superado el tiempo limite de activacion (24 horas).';

    public function run(array $params)
    {
        $userModel     = model(UserModel::class);
        $networkModel  = model(NetworkModel::class);
        $deviceModel   = model(DeviceModel::class);
        $identityModel = model(UserIdentityModel::class);

        // Expiración: 24 horas (puedes reducirlo si quieres, ej: 12 horas o 1 hora)
        $limitTime = Time::now()->subDays(1)->toDateTimeString();

        $unactivatedUsers = $userModel
            ->where('active', 0)
            ->where('created_at <', $limitTime)
            ->findAll();

        if (empty($unactivatedUsers)) {
            CLI::write('No se encontraron usuarios no activados expirados.', 'green');
            return;
        }

        $count = 0;
        foreach ($unactivatedUsers as $user) {
            // Eliminar foto de perfil si existe
            if (!empty($user->profile_pic) && file_exists(FCPATH . 'uploads/profile/' . $user->profile_pic)) {
                unlink(FCPATH . 'uploads/profile/' . $user->profile_pic);
            }

            // Borrar dispositivos de las redes
            $userNetworks = $networkModel->where('owner_id', $user->id)->findAll();
            foreach ($userNetworks as $network) {
                $deviceModel->where('network_id', $network->id)->delete();
            }

            // Borrar redes
            $networkModel->where('owner_id', $user->id)->delete();

            // Borrar identidades de Shield (correo, contraseña, token)
            $identityModel->where('user_id', $user->id)->delete();

            // Borrar el registro del usuario
            $userModel->delete($user->id, true);
            $count++;
        }

        CLI::write("Se purgaron {$count} usuarios no activados correctamente.", 'green');
    }
}
