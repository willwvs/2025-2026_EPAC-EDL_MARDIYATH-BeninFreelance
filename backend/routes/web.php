<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PrestataireController;
use App\Http\Controllers\ServiceController;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response(
        File::get(base_path('../index.html')),
        200,
        ['Content-Type' => 'text/html']
    );
});

Route::get('/login', function () {
    return response(
        File::get(base_path('../login.html')),
        200,
        ['Content-Type' => 'text/html']
    );
})->name('login.form');

Route::get('/login.html', function () {
    return redirect('/login');
});

Route::get('/register/client', function () {
    return response(
        File::get(base_path('../register-client.html')),
        200,
        ['Content-Type' => 'text/html']
    );
})->name('register.client.form');

Route::get('/register-client.html', function () {
    return redirect('/register/client');
});

Route::get('/register/prestataire', function () {
    return response(
        File::get(base_path('../register-prestataire.html')),
        200,
        ['Content-Type' => 'text/html']
    );
})->name('register.prestataire.form');

Route::get('/register-prestataire.html', function () {
    return redirect('/register/prestataire');
});

Route::get('/index.html', function () {
    return redirect('/');
});

Route::middleware('app.auth:client')->group(function () {
    Route::get('/dashboard/client', function () {
        return response(
            File::get(base_path('../dashboard-client.html')),
            200,
            ['Content-Type' => 'text/html']
        );
    });

    Route::get('/contact/prestataire/{prestataireId}', function () {
        return response(
            File::get(base_path('../contact-prestataire.html')),
            200,
            ['Content-Type' => 'text/html']
        );
    })->whereNumber('prestataireId');

    Route::get('/api/client/me', [ClientController::class, 'me']);
    Route::patch('/api/client/profile', [ClientController::class, 'updateProfile']);
    Route::get('/api/client/prestataires/{id}', [ClientController::class, 'prestataireProfil'])->whereNumber('id');
    Route::get('/api/client/prestataires/{id}/cv', [ClientController::class, 'prestataireCv'])->whereNumber('id');
    Route::get('/api/client/contact-context', [MessageController::class, 'contactContext']);
    Route::get('/api/client/messages', [MessageController::class, 'indexClient']);
    Route::post('/api/client/messages', [MessageController::class, 'storeFirstContact']);
});

Route::middleware('app.auth:prestataire')->group(function () {
    Route::get('/dashboard/prestataire', function () {
        return response(
            File::get(base_path('../dashboard-prestataire.html')),
            200,
            ['Content-Type' => 'text/html']
        );
    });

    Route::get('/api/prestataire/me', [PrestataireController::class, 'me']);
    Route::get('/api/prestataire/cv', [PrestataireController::class, 'myCv']);
    Route::patch('/api/prestataire/profile', [PrestataireController::class, 'updateProfile']);
    Route::post('/api/prestataire/photo', [PrestataireController::class, 'uploadPhoto']);
    Route::get('/api/prestataire/services', [ServiceController::class, 'indexMine']);
    Route::post('/api/prestataire/services', [ServiceController::class, 'store']);
    Route::patch('/api/prestataire/services/{id}', [ServiceController::class, 'update']);
    Route::delete('/api/prestataire/services/{id}', [ServiceController::class, 'destroy']);
    Route::get('/api/prestataire/messages', [MessageController::class, 'indexPrestataire']);
});

Route::get('/profil-prestataire', function () {
    return redirect('/dashboard/prestataire');
});

Route::get('/profil-prestataire.html', function () {
    return redirect('/dashboard/prestataire');
});

Route::get('/dashboard-prestataire.html', function () {
    return redirect('/dashboard/prestataire');
});

Route::get('/profil-client.html', function () {
    return redirect('/dashboard/client');
});

Route::get('/dashboard-client.html', function () {
    return redirect('/dashboard/client');
});

Route::get('/prestataires/{id}', function () {
    return response(
        File::get(base_path('../prestataire-teaser.html')),
        200,
        ['Content-Type' => 'text/html']
    );
})->whereNumber('id');

Route::get('/prestataires/{id}/profil', function () {
    return response(
        File::get(base_path('../prestataire-public.html')),
        200,
        ['Content-Type' => 'text/html']
    );
})->whereNumber('id');

Route::get('/assets/{path}', function (string $path) {
    $fullPath = base_path('../assets/' . $path);
    if (! File::exists($fullPath)) {
        abort(404);
    }

    return response(
        File::get($fullPath),
        200,
        ['Content-Type' => File::mimeType($fullPath) ?: 'application/octet-stream']
    );
})->where('path', '.*');

Route::get('/csrf-token', function () {
    return response()->json(['token' => csrf_token()]);
});

Route::get('/api/profile', function () {
    if (! session('auth_id')) {
        return response()->json(['ok' => false], 401);
    }

    return response()->json([
        'ok' => true,
        'name' => session('auth_name'),
    ]);
});

Route::get('/api/marketplace/meta', [MarketplaceController::class, 'meta']);
Route::get('/api/marketplace/prestataires', [MarketplaceController::class, 'searchPrestataires']);
Route::get('/api/prestataires/{id}/teaser', [MarketplaceController::class, 'teaser'])->whereNumber('id');
Route::get('/api/prestataires/{id}/public', [MarketplaceController::class, 'publicProfile'])->whereNumber('id');

Route::post('/register/client', [AuthController::class, 'registerClient'])->name('register.client');
Route::post('/register/prestataire', [AuthController::class, 'registerPrestataire'])->name('register.prestataire');
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::prefix('admin')->group(function () {
    Route::get('/', function () {
        return response(
            File::get(base_path('../admin.html')),
            200,
            ['Content-Type' => 'text/html']
        );
    });

    Route::get('/api/session', [AdminController::class, 'sessionStatus']);
    Route::post('/login', [AdminController::class, 'loginApi']);

    Route::middleware('admin.auth')->group(function () {
        Route::post('/logout', [AdminController::class, 'logoutApi']);
        Route::get('/api/prestataires', [AdminController::class, 'listApi']);
        Route::get('/api/prestataires/{id}', [AdminController::class, 'showApi']);
        Route::post('/api/prestataires/{id}/valider', [AdminController::class, 'validateApi']);
        Route::post('/api/prestataires/{id}/refuser', [AdminController::class, 'refuseApi']);
        Route::get('/prestataires/{id}/fichiers/{type}', [AdminController::class, 'file'])
            ->where('type', 'cv|identite');
    });
});

Route::get('/admin.html', function () {
    return redirect('/admin');
});

Route::get('/admin/login', function () {
    return redirect('/admin');
});
