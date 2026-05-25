<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Prestataire;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class EmailVerificationController extends Controller
{
    public function verify(Request $request)
    {
        $role = (string) $request->query('role', '');
        $token = (string) $request->query('token', '');

        if (! in_array($role, ['client', 'prestataire'], true) || strlen($token) < 32) {
            return $this->htmlResponse('Lien invalide ou expiré.', false);
        }

        if ($role === 'client') {
            $user = Client::query()->where('email_verify_token', $token)->first();
            $table = 'clients';
        } else {
            $user = Prestataire::query()->where('email_verify_token', $token)->first();
            $table = 'prestataires';
        }

        if (! $user) {
            return $this->htmlResponse('Lien invalide ou déjà utilisé.', false);
        }

        if (
            Schema::hasColumn($table, 'email_verify_expires_at')
            && $user->email_verify_expires_at
            && $user->email_verify_expires_at->isPast()
        ) {
            return $this->htmlResponse('Ce lien a expiré. Demandez un nouvel e-mail depuis la page de connexion.', false);
        }

        $user->update([
            'email_verified_at' => now(),
            'email_verify_token' => null,
            'email_verify_expires_at' => null,
        ]);

        $redirect = $role === 'client' ? '/login' : '/login';

        return $this->htmlResponse('Votre adresse e-mail est confirmée. Vous pouvez vous connecter.', true, $redirect);
    }

    private function htmlResponse(string $message, bool $ok, ?string $loginHref = '/login')
    {
        $color = $ok ? '#2ECC71' : '#E74C3C';
        $btn = $loginHref
            ? '<p style="margin-top:24px;"><a href="'.htmlspecialchars($loginHref, ENT_QUOTES, 'UTF-8').'" style="background:#F5A623;color:#0A0A0F;padding:12px 20px;border-radius:8px;font-weight:bold;text-decoration:none;">Connexion</a></p>'
            : '';

        $html = <<<HTML
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Vérification e-mail</title></head>
<body style="background:#0A0A0F;color:#F0EDE8;font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:24px;">
<div style="max-width:420px;background:#13131A;border:1px solid #2A2A3D;border-radius:16px;padding:32px;text-align:center;">
<h1 style="font-size:22px;margin:0 0 12px;">Bénin<span style="color:#F5A623;">Freelance</span></h1>
<p style="color:{$color};font-size:15px;line-height:1.5;">{$message}</p>
{$btn}
</div></body></html>
HTML;

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
