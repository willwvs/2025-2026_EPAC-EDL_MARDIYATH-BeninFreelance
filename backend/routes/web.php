<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AvisController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentWebhookController;
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
    Route::get('/api/client/messages/thread', [MessageController::class, 'threadClient']);
    Route::post('/api/client/messages', [MessageController::class, 'storeClientMessage']);
    Route::get('/api/client/commandes', [OrderController::class, 'indexClient']);
    Route::post('/api/client/commandes', [OrderController::class, 'proposer']);
    Route::get('/api/client/commandes/{id}', [OrderController::class, 'showClient'])->whereNumber('id');
    Route::post('/api/client/commandes/{id}/payer', [OrderController::class, 'payer'])->whereNumber('id');
    Route::post('/api/client/commandes/{id}/valider', [OrderController::class, 'valider'])->whereNumber('id');
    Route::post('/api/client/commandes/{id}/retouche', [OrderController::class, 'retouche'])->whereNumber('id');
    Route::post('/api/client/commandes/{id}/annuler', [OrderController::class, 'annuler'])->whereNumber('id');
    Route::post('/api/client/commandes/{id}/litige', [OrderController::class, 'ouvrirLitige'])->whereNumber('id');
    Route::post('/api/client/commandes/{id}/avis', [AvisController::class, 'storeForCommande'])->whereNumber('id');
    Route::get('/api/client/commandes/{commandeId}/livraisons/{livraisonId}/fichier', [OrderController::class, 'downloadLivraison'])
        ->whereNumber(['commandeId', 'livraisonId']);
    Route::get('/api/client/counters', [OrderController::class, 'countersClient']);
    Route::get('/api/client/avis', [ClientController::class, 'avisIndex']);
    Route::post('/api/client/avis/{id}/signaler', [AvisController::class, 'signaler'])->whereNumber('id');
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
    Route::post('/api/prestataire/services/{id}/photo', [ServiceController::class, 'uploadPhoto'])->whereNumber('id');
    Route::delete('/api/prestataire/services/{id}', [ServiceController::class, 'destroy']);
    Route::get('/api/prestataire/messages', [MessageController::class, 'indexPrestataire']);
    Route::get('/api/prestataire/messages/thread', [MessageController::class, 'threadPrestataire']);
    Route::post('/api/prestataire/messages', [MessageController::class, 'storePrestataireMessage']);
    Route::get('/api/prestataire/commandes', [OrderController::class, 'indexPrestataire']);
    Route::get('/api/prestataire/commandes/{id}', [OrderController::class, 'showPrestataire'])->whereNumber('id');
    Route::post('/api/prestataire/commandes/{id}/accepter', [OrderController::class, 'accepter'])->whereNumber('id');
    Route::post('/api/prestataire/commandes/{id}/refuser', [OrderController::class, 'refuser'])->whereNumber('id');
    Route::post('/api/prestataire/commandes/{id}/livrer', [OrderController::class, 'livrer'])->whereNumber('id');
    Route::get('/api/prestataire/commandes/{commandeId}/livraisons/{livraisonId}/fichier', [OrderController::class, 'downloadLivraison'])
        ->whereNumber(['commandeId', 'livraisonId']);
    Route::get('/api/prestataire/counters', [OrderController::class, 'countersPrestataire']);
    Route::get('/api/prestataire/avis', [PrestataireController::class, 'avisIndex']);
});

Route::get('/api/messages/{id}/attachment', [MessageController::class, 'attachment'])->whereNumber('id');

Route::post('/api/webhooks/fedapay/payin', [PaymentWebhookController::class, 'payinWebhook']);
Route::post('/api/webhooks/fedapay/payout', [PaymentWebhookController::class, 'payoutWebhook']);
Route::get('/payments/return', [PaymentWebhookController::class, 'paymentReturn']);

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

Route::get('/verify-email', [EmailVerificationController::class, 'verify']);

Route::get('/verify-email/sent', function () {
    return response(
        File::get(base_path('../verify-email-sent.html')),
        200,
        ['Content-Type' => 'text/html']
    );
});

Route::get('/search', function () {
    return response(
        File::get(base_path('../search.html')),
        200,
        ['Content-Type' => 'text/html']
    );
});

Route::get('/service/{id}', function (int $id) {
    return response(
        File::get(base_path('../service.html')),
        200,
        ['Content-Type' => 'text/html']
    );
})->whereNumber('id');

Route::get('/api/marketplace/meta', [MarketplaceController::class, 'meta']);
Route::get('/api/marketplace/services', [MarketplaceController::class, 'searchServices']);
Route::get('/api/marketplace/services/{id}', [MarketplaceController::class, 'showService'])->whereNumber('id');
Route::get('/api/marketplace/prestataires', [MarketplaceController::class, 'searchPrestataires']);
Route::get('/api/prestataires/{id}/teaser', [MarketplaceController::class, 'teaser'])->whereNumber('id');
Route::get('/api/prestataires/{id}/public', [MarketplaceController::class, 'publicProfile'])->whereNumber('id');
Route::get('/api/prestataires/{id}/avis', [MarketplaceController::class, 'publicAvis'])->whereNumber('id');

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
        Route::get('/api/prestataires/export.csv', [AdminController::class, 'exportPrestatairesCsv']);
        Route::get('/api/prestataires/{id}', [AdminController::class, 'showApi'])->whereNumber('id');
        Route::post('/api/prestataires/{id}/valider', [AdminController::class, 'validateApi']);
        Route::post('/api/prestataires/{id}/refuser', [AdminController::class, 'refuseApi']);
        Route::get('/prestataires/{id}/fichiers/{type}', [AdminController::class, 'file'])
            ->where('type', 'cv|identite');
        Route::get('/api/commandes', [AdminController::class, 'listCommandes']);
        Route::post('/api/commandes/{id}/rembourser', [AdminController::class, 'rembourserCommande'])->whereNumber('id');
        Route::get('/api/litiges', [AdminController::class, 'listDisputes']);
        Route::get('/api/litiges/{id}', [AdminController::class, 'showDispute'])->whereNumber('id');
        Route::post('/api/litiges/{id}/resoudre', [AdminController::class, 'resolveDispute'])->whereNumber('id');
        Route::get('/api/commandes/{commandeId}/livraisons/{livraisonId}/fichier', [AdminController::class, 'downloadLivraison'])
            ->whereNumber(['commandeId', 'livraisonId']);
        Route::get('/api/messages/{id}/attachment', [AdminController::class, 'downloadMessageAttachment'])->whereNumber('id');
        Route::get('/api/payouts', [AdminController::class, 'listPayouts']);
        Route::post('/api/payouts/{id}/marquer-paye', [AdminController::class, 'markPayoutPaid'])->whereNumber('id');
        Route::get('/api/clients', [AdminController::class, 'listClients']);
        Route::get('/api/clients/export.csv', [AdminController::class, 'exportClientsCsv']);
        Route::post('/api/clients/{id}/statut', [AdminController::class, 'updateClientStatut'])->whereNumber('id');
        Route::post('/api/prestataires/{id}/statut', [AdminController::class, 'updatePrestataireStatut'])->whereNumber('id');
        Route::get('/api/avis', [AdminController::class, 'listAvis']);
        Route::post('/api/avis/{id}/dismiss-signalement', [AdminController::class, 'dismissAvisSignalement'])->whereNumber('id');
        Route::delete('/api/avis/{id}', [AdminController::class, 'deleteAvis'])->whereNumber('id');
        Route::get('/api/categories', [AdminController::class, 'listCategories']);
        Route::post('/api/categories', [AdminController::class, 'storeCategory']);
        Route::patch('/api/categories/{id}', [AdminController::class, 'updateCategory'])->whereNumber('id');
        Route::delete('/api/categories/{id}', [AdminController::class, 'deleteCategory'])->whereNumber('id');
    });
});

Route::get('/admin.html', function () {
    return redirect('/admin');
});

Route::get('/admin/login', function () {
    return redirect('/admin');
});
