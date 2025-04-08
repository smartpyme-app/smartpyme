<?php 

use App\Http\Controllers\Api\SuperAdmin\PlanesController;

    Route::get('/planes',                 [PlanesController::class, 'index']);
    Route::post('/plan',                 [PlanesController::class, 'store']);
    Route::get('/plan/{id}',             [PlanesController::class, 'read']);
    Route::delete('/plan/{id}',          [PlanesController::class, 'delete']);

?>
