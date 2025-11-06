<?php

use CerberusIAM\Http\Controllers\CerberusCallbackController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')
    ->get('/cerberus/callback', CerberusCallbackController::class)
    ->name('cerberus.callback');
