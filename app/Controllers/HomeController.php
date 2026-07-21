<?php

namespace App\Controllers;

class HomeController extends BaseController
{
    // ---------------------------------------------------------------------
    // Método principal: Renderiza la Landing Page de NetCrew
    // ---------------------------------------------------------------------
    public function index()
    {
        if (auth()->loggedIn()) {
            return redirect()->to('dashboard');
        }
        return redirect()->to('login');
    }
}
