<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AppMailer
{
    public static function send(string $to, string $subject, string $html): bool
    {
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if ((string) config('services.resend.key', '') === '') {
            Log::warning('RESEND_API_KEY manquant — e-mail non envoyé.', ['to' => $to, 'subject' => $subject]);

            return false;
        }

        try {
            Mail::html($html, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });

            return true;
        } catch (\Throwable $e) {
            Log::error('Envoi e-mail échoué', ['to' => $to, 'subject' => $subject, 'e' => $e->getMessage()]);

            return false;
        }
    }

    public static function verifyEmailUrl(string $role, string $token): string
    {
        return url('/verify-email?'.http_build_query([
            'role' => $role,
            'token' => $token,
        ]));
    }

    public static function sendSignupVerification(string $role, string $email, string $prenom, string $token): bool
    {
        $url = self::verifyEmailUrl($role, $token);
        $name = htmlspecialchars($prenom !== '' ? $prenom : 'Bonjour', ENT_QUOTES, 'UTF-8');
        $html = <<<HTML
<p style="font-family:sans-serif;color:#333;">Bonjour {$name},</p>
<p style="font-family:sans-serif;color:#333;">Confirmez votre adresse e-mail pour activer votre compte BéninFreelance.</p>
<p><a href="{$url}" style="display:inline-block;background:#F5A623;color:#0A0A0F;padding:12px 20px;border-radius:8px;font-weight:bold;text-decoration:none;">Confirmer mon e-mail</a></p>
<p style="font-family:sans-serif;color:#666;font-size:13px;">Ce lien expire sous 24 h. Si vous n'êtes pas à l'origine de cette inscription, ignorez ce message.</p>
HTML;

        return self::send($email, 'Confirmez votre e-mail — BéninFreelance', $html);
    }

    public static function sendPrestataireDecision(string $email, string $prenom, string $statut, ?string $motif): bool
    {
        $name = htmlspecialchars($prenom !== '' ? $prenom : '', ENT_QUOTES, 'UTF-8');
        $loginUrl = url('/login');

        if ($statut === 'valide') {
            $html = <<<HTML
<p style="font-family:sans-serif;color:#333;">Bonjour {$name},</p>
<p style="font-family:sans-serif;color:#333;">Votre dossier prestataire a été <strong>validé</strong>. Vous pouvez vous connecter et publier vos services.</p>
<p><a href="{$loginUrl}" style="display:inline-block;background:#F5A623;color:#0A0A0F;padding:12px 20px;border-radius:8px;font-weight:bold;text-decoration:none;">Se connecter</a></p>
HTML;
            $subject = 'Compte prestataire validé — BéninFreelance';
        } else {
            $motifText = $motif !== null && trim($motif) !== ''
                ? htmlspecialchars(trim($motif), ENT_QUOTES, 'UTF-8')
                : 'Dossier non conforme aux critères de la plateforme.';
            $html = <<<HTML
<p style="font-family:sans-serif;color:#333;">Bonjour {$name},</p>
<p style="font-family:sans-serif;color:#333;">Après examen, votre dossier prestataire a été <strong>refusé</strong>.</p>
<p style="font-family:sans-serif;color:#555;"><strong>Motif :</strong> {$motifText}</p>
<p style="font-family:sans-serif;color:#666;font-size:13px;">Vous pouvez contacter le support ou soumettre un nouveau dossier si besoin.</p>
HTML;
            $subject = 'Décision sur votre dossier — BéninFreelance';
        }

        return self::send($email, $subject, $html);
    }

    public static function sendCommandeProposee(string $email, string $prenom, object $commande): bool
    {
        $name = htmlspecialchars($prenom !== '' ? $prenom : '', ENT_QUOTES, 'UTF-8');
        $titre = htmlspecialchars((string) ($commande->titre ?? ''), ENT_QUOTES, 'UTF-8');
        $url = url('/dashboard/prestataire?section=commandes');
        $html = <<<HTML
<p style="font-family:sans-serif;color:#333;">Bonjour {$name},</p>
<p style="font-family:sans-serif;color:#333;">Un client vous a proposé une commande : <strong>{$titre}</strong>.</p>
<p style="font-family:sans-serif;color:#333;">Vous avez <strong>48 h</strong> pour accepter ou refuser.</p>
<p><a href="{$url}" style="display:inline-block;background:#F5A623;color:#0A0A0F;padding:12px 20px;border-radius:8px;font-weight:bold;text-decoration:none;">Voir la commande</a></p>
HTML;

        return self::send($email, 'Nouvelle commande — BéninFreelance', $html);
    }

    public static function sendCommandeAcceptee(string $email, string $prenom, object $commande): bool
    {
        $name = htmlspecialchars($prenom !== '' ? $prenom : '', ENT_QUOTES, 'UTF-8');
        $titre = htmlspecialchars((string) ($commande->titre ?? ''), ENT_QUOTES, 'UTF-8');
        $url = url('/dashboard/client?section=commandes&commande_id='.(int) $commande->id);
        $html = <<<HTML
<p style="font-family:sans-serif;color:#333;">Bonjour {$name},</p>
<p style="font-family:sans-serif;color:#333;">Votre commande <strong>{$titre}</strong> a été acceptée. Procédez au paiement sécurisé.</p>
<p><a href="{$url}" style="display:inline-block;background:#F5A623;color:#0A0A0F;padding:12px 20px;border-radius:8px;font-weight:bold;text-decoration:none;">Payer la commande</a></p>
HTML;

        return self::send($email, 'Commande acceptée — paiement requis', $html);
    }

    public static function sendCommandePayee(string $email, string $prenom, object $commande): bool
    {
        $name = htmlspecialchars($prenom !== '' ? $prenom : '', ENT_QUOTES, 'UTF-8');
        $titre = htmlspecialchars((string) ($commande->titre ?? ''), ENT_QUOTES, 'UTF-8');
        $url = url('/dashboard/prestataire?section=commandes&commande_id='.(int) $commande->id);
        $html = <<<HTML
<p style="font-family:sans-serif;color:#333;">Bonjour {$name},</p>
<p style="font-family:sans-serif;color:#333;">La commande <strong>{$titre}</strong> est payée. Vous pouvez démarrer le travail.</p>
<p><a href="{$url}" style="display:inline-block;background:#F5A623;color:#0A0A0F;padding:12px 20px;border-radius:8px;font-weight:bold;text-decoration:none;">Ouvrir la commande</a></p>
HTML;

        return self::send($email, 'Commande payée — travail à livrer', $html);
    }

    public static function sendCommandeLivree(string $email, string $prenom, object $commande): bool
    {
        $name = htmlspecialchars($prenom !== '' ? $prenom : '', ENT_QUOTES, 'UTF-8');
        $titre = htmlspecialchars((string) ($commande->titre ?? ''), ENT_QUOTES, 'UTF-8');
        $url = url('/dashboard/client?section=commandes&commande_id='.(int) $commande->id);
        $html = <<<HTML
<p style="font-family:sans-serif;color:#333;">Bonjour {$name},</p>
<p style="font-family:sans-serif;color:#333;">Le prestataire a soumis la livraison pour <strong>{$titre}</strong>. Vous avez 14 jours pour valider ou demander une retouche.</p>
<p><a href="{$url}" style="display:inline-block;background:#F5A623;color:#0A0A0F;padding:12px 20px;border-radius:8px;font-weight:bold;text-decoration:none;">Examiner la livraison</a></p>
HTML;

        return self::send($email, 'Livraison soumise — à valider', $html);
    }

    public static function sendCommandeTerminee(string $email, string $prenom, object $commande): bool
    {
        $name = htmlspecialchars($prenom !== '' ? $prenom : '', ENT_QUOTES, 'UTF-8');
        $titre = htmlspecialchars((string) ($commande->titre ?? ''), ENT_QUOTES, 'UTF-8');
        $url = url('/dashboard/client?section=commandes&commande_id='.(int) $commande->id);
        $html = <<<HTML
<p style="font-family:sans-serif;color:#333;">Bonjour {$name},</p>
<p style="font-family:sans-serif;color:#333;">La commande <strong>{$titre}</strong> est terminée. Vous pouvez laisser un avis.</p>
<p><a href="{$url}" style="display:inline-block;background:#F5A623;color:#0A0A0F;padding:12px 20px;border-radius:8px;font-weight:bold;text-decoration:none;">Laisser un avis</a></p>
HTML;

        return self::send($email, 'Commande terminée', $html);
    }

    public static function sendPayoutOk(string $email, string $prenom, object $commande, int $montant): bool
    {
        $name = htmlspecialchars($prenom !== '' ? $prenom : '', ENT_QUOTES, 'UTF-8');
        $titre = htmlspecialchars((string) ($commande->titre ?? ''), ENT_QUOTES, 'UTF-8');
        $m = number_format($montant, 0, ',', ' ');
        $html = <<<HTML
<p style="font-family:sans-serif;color:#333;">Bonjour {$name},</p>
<p style="font-family:sans-serif;color:#333;">Le versement de <strong>{$m} FCFA</strong> pour la commande <strong>{$titre}</strong> a été effectué sur votre Mobile Money.</p>
HTML;

        return self::send($email, 'Versement reçu — BéninFreelance', $html);
    }

    public static function sendRappelReview(string $email, string $prenom, object $commande, int $daysLeft): bool
    {
        $name = htmlspecialchars($prenom !== '' ? $prenom : '', ENT_QUOTES, 'UTF-8');
        $titre = htmlspecialchars((string) ($commande->titre ?? ''), ENT_QUOTES, 'UTF-8');
        $url = url('/dashboard/client?section=commandes&commande_id='.(int) $commande->id);
        $html = <<<HTML
<p style="font-family:sans-serif;color:#333;">Bonjour {$name},</p>
<p style="font-family:sans-serif;color:#333;">Rappel : il reste <strong>{$daysLeft} jour(s)</strong> pour valider la livraison de <strong>{$titre}</strong>, sinon elle sera validée automatiquement.</p>
<p><a href="{$url}" style="display:inline-block;background:#F5A623;color:#0A0A0F;padding:12px 20px;border-radius:8px;font-weight:bold;text-decoration:none;">Voir la commande</a></p>
HTML;

        return self::send($email, 'Rappel validation livraison', $html);
    }
}
