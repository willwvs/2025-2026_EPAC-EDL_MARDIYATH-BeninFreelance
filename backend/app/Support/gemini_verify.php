<?php

/**
 * Analyse procédurale du dossier prestataire via Gemini 2.5 Flash + Google Search.
 * @see https://ai.google.dev/gemini-api/docs/google-search
 *
 * @return array{statut_verification:string, motif_refus_ia:?string, erreur:?string}
 */
function gemini_ia_result(string $statut, ?string $motif, ?string $code = null): array
{
    return [
        'statut_verification' => $statut,
        'motif_refus_ia' => $motif,
        'erreur' => $code,
    ];
}

function gemini_analyze_prestataire_dossier(string $apiKey, object $prestataire, string $cvRelativePath, string $idRelativePath): array
{
    if ($apiKey === '') {
        return gemini_ia_result('en_attente', 'Examen automatique non configuré (clé API manquante).', 'missing_api_key');
    }

    $maxBytes = 3 * 1024 * 1024;
    $cvAbs = \Illuminate\Support\Facades\Storage::disk('local')->path($cvRelativePath);
    $idAbs = \Illuminate\Support\Facades\Storage::disk('local')->path($idRelativePath);

    $bio = property_exists($prestataire, 'biographie') && $prestataire->biographie
        ? (string) $prestataire->biographie
        : '(non fournie)';

    $parts = [[
        'text' => <<<TXT
Tu aides a pre-evaluer un dossier prestataire pour une marketplace au Benin (premier filtre avant un comité d'experts).
Analyse le CV (PDF) et la piece d'identite (image ou PDF) fournis en pieces jointes.

Profil declare (texte):
- Nom: {$prestataire->nom} {$prestataire->prenom}
- Email: {$prestataire->email}
- Localite: {$prestataire->localite}
- Biographie declaree: {$bio}

Tu disposes de l'outil google_search pour du contexte public general (formats, bonnes pratiques KYC) et faire des recherches sur la véracité de la personne sur ce qu'elle dit. Pas d'affirmations sur une personne hors documents.

Regles: "valide" si coherent et lisible; "refuse" si illisible ou incoherence majeure; "en_attente" si doute technique.

Dans motif_public: message court pour l'utilisateur final. Interdit d'y mentionner "IA", "intelligence artificielle", "robot", "Gemini" ou tout outil technique. Formulation possible: "comité d'experts", "examen du dossier", "validation".

Reponds UNIQUEMENT avec ce JSON, sans markdown ni texte autour:
{"decision":"valide|refuse|en_attente","motif_public":"message court et generique (pas de donnees sensibles)"}
TXT,
    ]];

    if (is_readable($cvAbs) && filesize($cvAbs) <= $maxBytes) {
        $parts[] = [
            'inline_data' => [
                'mime_type' => 'application/pdf',
                'data' => base64_encode((string) file_get_contents($cvAbs)),
            ],
        ];
    }

    if (is_readable($idAbs) && filesize($idAbs) <= $maxBytes) {
        $mime = match (strtolower(pathinfo($idAbs, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
        $parts[] = [
            'inline_data' => [
                'mime_type' => $mime,
                'data' => base64_encode((string) file_get_contents($idAbs)),
            ],
        ];
    }

    $url = sprintf(
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash:generateContent?key=%s',
        rawurlencode($apiKey)
    );

    $payload = [
        'contents' => [['role' => 'user', 'parts' => $parts]],
        'tools' => [['google_search' => new \stdClass]],
        'generationConfig' => ['temperature' => 0.2],
    ];

    try {
        $response = \Illuminate\Support\Facades\Http::timeout(120)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, $payload);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::warning('Gemini HTTP exception', ['e' => $e->getMessage()]);

        return gemini_ia_result('en_attente', 'Erreur technique lors de l’examen du dossier.', 'http_exception');
    }

    if (! $response->successful()) {
        \Illuminate\Support\Facades\Log::warning('Gemini API error', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return gemini_ia_result('en_attente', 'Service d’examen temporairement indisponible. Réessayez plus tard.', 'api_error');
    }

    $text = $response->json('candidates.0.content.parts.0.text');
    if (! is_string($text) || $text === '') {
        return gemini_ia_result('en_attente', 'Réponse d’examen vide ou inattendue.', 'empty_response');
    }

    $json = preg_match('/\{[\s\S]*\}/', $text, $m) ? json_decode($m[0], true) : null;
    if (! is_array($json) || empty($json['decision'])) {
        return gemini_ia_result('en_attente', 'Examen incomplet. Un administrateur pourra relire le dossier.', 'bad_json');
    }

    $map = ['valide' => 'valide', 'refuse' => 'refuse', 'en_attente' => 'en_attente'];
    $statut = $map[$json['decision']] ?? 'en_attente';
    $motif = isset($json['motif_public']) ? (string) $json['motif_public'] : null;

    $motifDb = match ($statut) {
        'refuse' => $motif ?: 'Dossier non conforme.',
        'en_attente' => $motif ?: 'Dossier en cours d’examen par le comité d’experts.',
        default => null,
    };

    return gemini_ia_result($statut, $motifDb, null);
}
