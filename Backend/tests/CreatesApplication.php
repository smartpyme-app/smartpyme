<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Tests\Support\GuardiaBaseDePruebas;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * La guardia corre antes de cualquier trait de base de datos (RefreshDatabase, etc.):
     * si la base no es de pruebas, la prueba falla sin ejecutar una sola consulta.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        GuardiaBaseDePruebas::antesDeArrancar();

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        GuardiaBaseDePruebas::proteger($app);

        return $app;
    }
}
