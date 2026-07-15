<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Prestataire;
use App\Support\AccountEmailVerification;
use App\Support\PrestataireVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function registerClient(Request $request)
    {
        $data = $request->validate([
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'email' => 'required|email|max:150|unique:clients,email',
            'telephone' => 'required|string|max:20',
            'localite' => 'required|string|max:100',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $passwordColumn = $this->passwordColumn('clients');

        $client = Client::create([
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'email' => $data['email'],
            'telephone' => $data['telephone'],
            'localite' => $data['localite'],
            $passwordColumn => Hash::make($data['password']),
        ]);

        AccountEmailVerification::sendForClient($client);

        return response()->json([
            'ok' => true,
            'message' => 'Compte créé. Consultez votre boîte mail pour confirmer votre adresse.',
            'redirect' => '/verify-email/sent?role=client',
        ]);
    }

    public function registerPrestataire(Request $request)
    {
        $data = $request->validate([
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'email' => 'required|email|max:150|unique:prestataires,email',
            'telephone' => 'required|string|max:20',
            'localite' => 'required|string|max:100',
            'password' => 'required|string|min:8|confirmed',
            'cv' => 'required|file|mimes:pdf|max:5120',
            'piece_identite' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $passwordColumn = $this->passwordColumn('prestataires');
        $cvFile = $request->file('cv');
        $idFile = $request->file('piece_identite');

        $cvName = (string) Str::uuid().'.'.$cvFile->getClientOriginalExtension();
        $idName = (string) Str::uuid().'.'.$idFile->getClientOriginalExtension();

        $cvPath = $cvFile->storeAs('verification/cv', $cvName, 'local');
        $idPath = $idFile->storeAs('verification/identite', $idName, 'local');

        $create = [
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'email' => $data['email'],
            'telephone' => $data['telephone'],
            'localite' => $data['localite'],
            $passwordColumn => Hash::make($data['password']),
            'chemin_cv' => $cvPath,
            'chemin_identite' => $idPath,
            'statut_verification' => 'en_attente',
        ];
        if (Schema::hasColumn('prestataires', 'plan')) {
            $create['plan'] = 'gratuit';
        }
        if (Schema::hasColumn('prestataires', 'trial_ends_at')) {
            $create['trial_ends_at'] = now()->addDays((int) config('marketplace.trial_days', 7));
        }

        $prestataire = Prestataire::create($create);
        $id = $prestataire->id;
        $row = Prestataire::find($id);

        try {
            $ia = gemini_analyze_prestataire_dossier(
                (string) config('services.gemini_api_key', ''),
                $row,
                $cvPath,
                $idPath
            );
        } catch (\Throwable $e) {
            Log::error('Erreur inattendue analyse dossier (Gemini)', ['e' => $e->getMessage()]);
            $ia = [
                'statut_verification' => 'en_attente',
                'motif_refus_ia' => 'Erreur technique lors de l’examen du dossier. Réessayez plus tard.',
            ];
        }

        $iaStatut = $ia['statut_verification'];
        $update = [
            'statut_verification' => $iaStatut,
            'motif_refus_ia' => $ia['motif_refus_ia'],
        ];

        if (
            Schema::hasColumn('prestataires', 'ia_decided_at')
            && in_array($iaStatut, ['valide', 'refuse'], true)
        ) {
            $update['ia_decided_at'] = now();
        }

        Prestataire::where('id', $id)->update($update);

        $row = Prestataire::find($id);
        AccountEmailVerification::sendForPrestataire($row);

        return response()->json([
            'ok' => true,
            'message' => 'Dossier soumis. Confirmez votre e-mail ; vous serez notifié de la décision sur votre dossier.',
            'redirect' => '/verify-email/sent?role=prestataire',
        ]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'role' => 'required|in:client,prestataire',
        ]);

        if ($data['role'] === 'client') {
            $user = Client::where('email', $data['email'])->first();
            if (! $user || ! Hash::check($data['password'], $user->{$this->passwordColumn('clients')})) {
                return response()->json(['ok' => false, 'message' => 'Identifiants invalides.'], 422);
            }

            if (! AccountEmailVerification::isVerified($user, 'clients')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Confirmez votre adresse e-mail avant de vous connecter (lien envoyé à l’inscription).',
                ], 403);
            }

            if (! \App\Support\AccountStatus::isActive($user, 'clients')) {
                return response()->json([
                    'ok' => false,
                    'message' => \App\Support\AccountStatus::blockMessage((string) ($user->statut ?? '')),
                ], 403);
            }

            $request->session()->regenerate();
            session([
                'auth_id' => $user->id,
                'auth_type' => 'client',
                'auth_name' => trim($user->prenom.' '.$user->nom),
            ]);

            return response()->json(['ok' => true, 'redirect' => '/dashboard/client']);
        }

        $user = Prestataire::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->{$this->passwordColumn('prestataires')})) {
            return response()->json(['ok' => false, 'message' => 'Identifiants invalides.'], 422);
        }

        if (! AccountEmailVerification::isVerified($user, 'prestataires')) {
            return response()->json([
                'ok' => false,
                'message' => 'Confirmez votre adresse e-mail avant de vous connecter.',
            ], 403);
        }

        if (! \App\Support\AccountStatus::isActive($user, 'prestataires')) {
            return response()->json([
                'ok' => false,
                'message' => \App\Support\AccountStatus::blockMessage((string) ($user->statut ?? '')),
            ], 403);
        }

        $effective = PrestataireVerification::effectiveStatus($user);

        if ($effective === 'refuse') {
            return response()->json([
                'ok' => false,
                'message' => 'Compte refusé. '.($user->motif_refus_ia ?: 'Merci de resoumettre vos documents.'),
            ], 403);
        }

        if ($effective !== 'valide') {
            return response()->json([
                'ok' => false,
                'message' => 'Dossier en examen par le comité d’experts (délai maximal 24 h après analyse automatique).',
            ], 403);
        }

        $request->session()->regenerate();
        session([
            'auth_id' => $user->id,
            'auth_type' => 'prestataire',
            'auth_name' => trim($user->prenom.' '.$user->nom),
        ]);

        return response()->json(['ok' => true, 'redirect' => '/dashboard/prestataire']);
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true, 'redirect' => '/login']);
    }

    private function passwordColumn(string $table): string
    {
        return Schema::hasColumn($table, 'mot_de_passe') ? 'mot_de_passe' : 'password';
    }
}
