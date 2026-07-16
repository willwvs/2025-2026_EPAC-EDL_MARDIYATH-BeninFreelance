<?php

namespace App\Support;

/**
 * Contenu unique du Guide d'utilisation (page web + PDF).
 */
final class GuideContent
{
    /** @return array<string, mixed> */
    public static function meta(): array
    {
        return [
            'title' => 'Guide d\'utilisation — BéninFreelance',
            'subtitle' => 'Tout ce qu\'il faut savoir pour utiliser la plateforme, pas à pas',
            'version' => '1.1',
            'updated' => '16 juillet 2026',
            'brand' => 'BéninFreelance',
        ];
    }

    /**
     * Sommaire / chapitres.
     *
     * @return list<array{id: string, title: string, role?: string, blocks: list<array<string, mixed>>}>
     */
    public static function chapters(): array
    {
        return [
            [
                'id' => 'bienvenue',
                'title' => 'Bienvenue — comprendre la plateforme',
                'blocks' => [
                    [
                        'type' => 'p',
                        'text' => 'BéninFreelance est une marketplace de services freelances. Un client décrit son besoin, choisit un prestataire, et la plateforme sécurise le paiement jusqu’à validation du livrable.',
                    ],
                    [
                        'type' => 'p',
                        'text' => 'Ce guide est écrit pour les débutants. Il est structuré en 2 parcours séparés : si vous êtes Client, suivez la section Client ; si vous êtes Prestataire, suivez la section Prestataire.',
                    ],
                    [
                        'type' => 'callout',
                        'tone' => 'info',
                        'title' => 'Deux rôles, deux espaces',
                        'text' => 'Le compte Client et le compte Prestataire sont séparés. La messagerie, les commandes et l’abonnement suivent le rôle que vous avez choisi lors de la connexion.',
                    ],
                    [
                        'type' => 'list',
                        'title' => 'Vue d’ensemble (même logique côté Client et Prestataire)',
                        'items' => [
                            'Client : chercher → échanger → commander → payer → livrer/valider → noter',
                            'Prestataire : compléter le profil → créer des services → répondre en messagerie → accepter/ livrer → être payé',
                            'Paiements : passent par FedaPay, et le statut des commandes guide les actions autorisées',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'demarrer',
                'title' => 'Démarrer — créer un compte et se connecter',
                'blocks' => [
                    [
                        'type' => 'p',
                        'text' => 'Avant toute action, choisissez votre rôle puis connectez-vous. Utilisez toujours une adresse e-mail à laquelle vous avez accès : elle sert aux notifications importantes.',
                    ],
                    [
                        'type' => 'steps',
                        'items' => [
                            ['title' => 'Étape 1 — Aller au bon parcours', 'text' => 'Depuis l’accueil, choisissez « Trouver un prestataire » (Client) ou « Proposer mes services » (Prestataire).'],
                            ['title' => 'Étape 2 — Renseigner les informations', 'text' => 'Complétez identité, localisation et contact. Pour le prestataire, ajoutez les documents (CV PDF + pièce d’identité).'],
                            ['title' => 'Étape 3 — Finaliser et vérifier', 'text' => 'Validez l’inscription puis attendez la validation si vous êtes prestataire. Ensuite, connectez-vous avec le mode correspondant.'],
                        ],
                    ],
                    [
                        'type' => 'callout',
                        'tone' => 'warn',
                        'title' => 'Prestataire : dossier en attente',
                        'text' => 'Tant que votre dossier prestataire n’est pas validé, vous ne pourrez pas souscrire à un abonnement payant ni apparaître pleinement dans la recherche.',
                    ],
                    [
                        'type' => 'visual',
                        'title' => 'Repères d’écran',
                        'caption' => 'Après connexion, repérez rapidement les menus principaux.',
                        'kind' => 'sidebar-mock',
                        'zones' => [
                            ['label' => 'Accueil / Tableau de bord', 'hint' => 'Vue d’ensemble'],
                            ['label' => 'Messages', 'hint' => 'Conversations'],
                            ['label' => 'Commandes', 'hint' => 'Statuts et suivi'],
                        ],
                    ],
                ],
            ],

            // ============================
            // CLIENT
            // ============================
            [
                'id' => 'client-recherche',
                'title' => 'Client — Trouver un prestataire et décider',
                'role' => 'client',
                'blocks' => [
                    [
                        'type' => 'p',
                        'text' => 'Objectif : repérer un prestataire adapté, puis démarrer la conversation avant de proposer une commande.',
                    ],
                    [
                        'type' => 'steps',
                        'items' => [
                            ['title' => 'Étape 1 — Ouvrir la recherche', 'text' => 'Dans votre espace, allez sur « Recherche » (ou via l’exploration publique).'],
                            ['title' => 'Étape 2 — Utiliser les filtres', 'text' => 'Filtrez par catégorie, localité et note. Commencez par 2–3 profils pertinents pour comparer.'],
                            ['title' => 'Étape 3 — Ouvrir une fiche complète', 'text' => 'Cliquez sur un prestataire puis ouvrez le profil complet pour lire les services et les avis.'],
                            ['title' => 'Étape 4 — Contacter avec précision', 'text' => 'Depuis le service, démarrez « Contacter ». Envoyez dès le premier message : besoin, délais souhaités, budget indicatif, et contraintes (ex. fichiers, format).'],
                        ],
                    ],
                    [
                        'type' => 'visual',
                        'title' => 'Ce que vous devez préparer avant de commander',
                        'caption' => 'Une commande claire = moins d’allers-retours.',
                        'kind' => 'check-mock',
                        'zones' => [
                            ['label' => 'Le besoin exact', 'hint' => 'Description courte + précision'],
                            ['label' => 'Le délai', 'hint' => 'Nombre de jours'],
                            ['label' => 'Le budget', 'hint' => 'Montant en FCFA'],
                            ['label' => 'Le livrable attendu', 'hint' => 'Formats / fichiers'],
                        ],
                    ],
                    [
                        'type' => 'callout',
                        'tone' => 'tip',
                        'title' => 'Astuce',
                        'text' => 'Si vous discutez d’abord, vous réduisez le risque de retouche et vous accélérez le passage au statut « En attente de paiement ».',
                    ],
                ],
            ],
            [
                'id' => 'client-commande',
                'title' => 'Client — Proposer une commande, payer, valider',
                'role' => 'client',
                'blocks' => [
                    [
                        'type' => 'p',
                        'text' => 'Quand le prestataire est convaincu, vous pouvez proposer une commande. Le paiement n’est demandé qu’après acceptation par le prestataire.',
                    ],
                    [
                        'type' => 'h',
                        'text' => 'Étapes d’une commande (dans l’ordre)',
                    ],
                    [
                        'type' => 'steps',
                        'items' => [
                            ['title' => '1) Proposer la commande', 'text' => 'Depuis la messagerie ou le détail de la discussion, cliquez pour créer une commande : donnez un titre clair, une description précise, le budget (FCFA) et un délai (en jours).'],
                            ['title' => '2) Attendre l’acceptation', 'text' => 'La commande passe « En attente du prestataire ». Si le prestataire accepte (environ 48 h), le statut devient « En attente de paiement ».'],
                            ['title' => '3) Payer via FedaPay', 'text' => 'Cliquez « Payer maintenant » : vous êtes redirigé vers FedaPay. Finalisez le paiement puis revenez sur la plateforme. Le statut passe à « Travail en cours ».'],
                            ['title' => '4) Recevoir et demander une retouche si besoin', 'text' => 'Après livraison, la commande passe « À valider ». Si le résultat ne correspond pas, demandez une retouche ; le prestataire soumet une nouvelle version.'],
                            ['title' => '5) Valider et libérer', 'text' => 'Quand c’est bon : utilisez « Approuver et libérer le paiement ». La commande est clôturée.'],
                            ['title' => '6) Laisser un avis', 'text' => 'Après clôture, notez le prestataire (1 à 5) et ajoutez un commentaire. Vous pourrez modifier votre avis plus tard depuis le détail de la commande.'],
                        ],
                    ],
                    [
                        'type' => 'callout',
                        'tone' => 'warn',
                        'title' => 'Important : ne lancez pas le paiement trop tôt',
                        'text' => 'Si vous n’avez pas le bouton « Payer maintenant », c’est normal : tant que le prestataire n’a pas accepté, le paiement n’est pas autorisé.',
                    ],
                    [
                        'type' => 'h',
                        'text' => 'Litige (quand ça ne se passe pas comme prévu)',
                    ],
                    [
                        'type' => 'p',
                        'text' => 'En cas de désaccord sérieux (non-livraison, qualité très insuffisante…), ouvrez un litige depuis la commande. L’équipe examine les échanges et tranche (remboursement ou libération). Le litige sert de dernier recours après tentative en messagerie.',
                    ],
                    [
                        'type' => 'visual',
                        'title' => 'Où regarder pour savoir quoi faire',
                        'caption' => 'Votre “tableau de bord d’action”.',
                        'kind' => 'status-mock',
                        'zones' => [
                            ['label' => 'Commandes', 'hint' => 'Statut et boutons'],
                            ['label' => 'Messagerie', 'hint' => 'Brief + retouches'],
                            ['label' => 'Retour FedaPay', 'hint' => 'Confirmation paiement'],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'client-cas-pratique',
                'title' => 'Client — Cas pratique : du message à la validation',
                'role' => 'client',
                'blocks' => [
                    [
                        'type' => 'callout',
                        'tone' => 'info',
                        'title' => 'Exemple',
                        'text' => 'Vous voulez faire créer une bannière. Vous discutez d’abord en messagerie, puis vous transformez le brief en commande pour activer le paiement sécurisé.',
                    ],
                    [
                        'type' => 'steps',
                        'items' => [
                            ['title' => 'Étape 1 — Brief dans la messagerie', 'text' => 'Décrivez le besoin (dimensions, style), donnez le délai souhaité et le budget indicatif.'],
                            ['title' => 'Étape 2 — Créer la commande', 'text' => 'Dans le fil, renseignez le titre, la description, le budget (FCFA) et le délai (jours). La commande devient “En attente du prestataire”.'],
                            ['title' => 'Étape 3 — Attendre l’acceptation', 'text' => 'Quand le prestataire accepte, vérifiez que le statut passe “En attente de paiement”. Sans ce statut, le bouton paiement peut ne pas être disponible.'],
                            ['title' => 'Étape 4 — Payer sur FedaPay', 'text' => 'Cliquez “Payer maintenant”, complétez le paiement, puis revenez sur la plateforme. Le statut doit passer “Travail en cours”.'],
                            ['title' => 'Étape 5 — Livrer/valider', 'text' => 'Après livraison, la commande passe “À valider”. Si c’est parfait, “Approuver et libérer”. Sinon, demandez une retouche et suivez la nouvelle version.'],
                        ],
                    ],
                    [
                        'type' => 'visual',
                        'title' => 'Checkpoints (à vérifier à chaque étape)',
                        'caption' => 'Si l’un des points n’est pas bon, stoppez et relisez avant d’aller plus loin.',
                        'kind' => 'check-mock',
                        'zones' => [
                            ['label' => 'Statut = “En attente de paiement”', 'hint' => 'Autorise le paiement'],
                            ['label' => 'Budget et délai cohérents', 'hint' => 'Réduit les retouches'],
                            ['label' => 'Livrable reçu + aperçu', 'hint' => 'Avant “Approuver”'],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'client-espace',
                'title' => 'Client — Votre espace : quoi utiliser, dans quel ordre',
                'role' => 'client',
                'blocks' => [
                    [
                        'type' => 'list',
                        'title' => 'Menu principal',
                        'items' => [
                            'Accueil : repères et raccourcis',
                            'Recherche : trouver des prestataires',
                            'Mon compte : vos informations',
                            'Messages : discuter avant la commande',
                            'Commandes : suivre tout le cycle et valider',
                        ],
                    ],
                    [
                        'type' => 'callout',
                        'tone' => 'info',
                        'title' => 'Routine recommandée',
                        'text' => 'Toujours commencer par Recherche, passer ensuite par Messagerie, puis seulement proposer une commande quand tout est clair. Sur Commandes, surveillez le bouton “Payer maintenant” puis “À valider”.',
                    ],
                    [
                        'type' => 'visual',
                        'title' => 'Ordre conseillé (pour ne pas se perdre)',
                        'caption' => 'Un chemin simple à suivre à chaque commande.',
                        'kind' => 'flow-mock',
                        'zones' => [
                            ['label' => '1. Recherche', 'hint' => 'Choisir un prestataire'],
                            ['label' => '2. Messagerie', 'hint' => 'Valider le brief'],
                            ['label' => '3. Commandes', 'hint' => 'Créer → payer → valider'],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'client-paiements',
                'title' => 'Client — Paiement sécurisé : délais et bonnes pratiques',
                'role' => 'client',
                'blocks' => [
                    [
                        'type' => 'list',
                        'title' => 'Repères à connaître',
                        'items' => [
                            'Paiement : FedaPay (Mobile Money et options proposées au checkout)',
                            'Acceptation prestataire : environ 48 heures',
                            'Validation client après livraison : environ 14 jours',
                            'Commission sur vos commandes : 0 % (modèle basé sur les abonnements prestataires)',
                            'Litiges : tranchés par l’administration après examen',
                        ],
                    ],
                    [
                        'type' => 'p',
                        'text' => 'Ne partagez jamais vos mots de passe. Payez uniquement via FedaPay sur la plateforme. Si le statut ne semble pas correct après retour, revenez au détail de la commande et comparez avec la confirmation de FedaPay.',
                    ],
                    [
                        'type' => 'callout',
                        'tone' => 'warn',
                        'title' => 'Erreur fréquente',
                        'text' => 'Si vous payez “en dehors”, vous perdez la protection du cycle de commande. La plateforme ne peut pas sécuriser un paiement non effectué via son parcours.',
                    ],
                ],
            ],
            [
                'id' => 'client-faq',
                'title' => 'Client — Questions fréquentes',
                'role' => 'client',
                'blocks' => [
                    [
                        'type' => 'faq',
                        'items' => [
                            [
                                'q' => 'Pourquoi je ne vois pas le bouton “Payer maintenant” ?',
                                'a' => 'Parce que le prestataire n’a pas encore accepté la commande. Une fois acceptée, le statut passe “En attente de paiement”.',
                            ],
                            [
                                'q' => 'Puis-je modifier un avis déjà publié ?',
                                'a' => 'Oui. Ouvrez la commande concernée et utilisez “Modifier” sur votre avis pour changer note ou commentaire.',
                            ],
                            [
                                'q' => 'Que faire si le prestataire ne répond pas ?',
                                'a' => 'Vérifiez d’abord le statut de la commande (acceptation/paiement). Si une proposition expire, contactez à nouveau ou choisissez un autre prestataire après comparaison.',
                            ],
                        ],
                    ],
                ],
            ],

            // ============================
            // PRESTATAIRE
            // ============================
            [
                'id' => 'presta-profil',
                'title' => 'Prestataire — Compléter votre profil et vos services',
                'role' => 'prestataire',
                'blocks' => [
                    [
                        'type' => 'p',
                        'text' => 'Objectif : rendre votre vitrine claire pour les clients, puis publier des services conformes à vos limites de plan.',
                    ],
                    [
                        'type' => 'steps',
                        'items' => [
                            ['title' => 'Étape 1 — Remplir “Mon profil”', 'text' => 'Ajoutez une photo, une bio, et vérifiez vos documents (CV PDF, etc.). Les clients voient ces éléments avant de vous écrire.'],
                            ['title' => 'Étape 2 — Créer “Mes services”', 'text' => 'Pour chaque service : titre, catégorie, description, photo, tarif indicatif et délai. Respectez le plafond autorisé par votre formule.'],
                            ['title' => 'Étape 3 — Prévisualiser', 'text' => 'Ouvrez vos services côté public pour contrôler rendu et cohérence (titre lisible, description compréhensible, photo pertinente).'],
                        ],
                    ],
                    [
                        'type' => 'visual',
                        'title' => 'Checklist avant de publier un service',
                        'caption' => 'Un service “complet” diminue les retouches.',
                        'kind' => 'check-mock',
                        'zones' => [
                            ['label' => 'Titre précis', 'hint' => 'Ce que vous faites, en 1 phrase'],
                            ['label' => 'Description claire', 'hint' => 'Étendue du travail'],
                            ['label' => 'Délai réaliste', 'hint' => 'En jours'],
                            ['label' => 'Tarif indicatif', 'hint' => 'FCFA'],
                        ],
                    ],
                    [
                        'type' => 'callout',
                        'tone' => 'tip',
                        'title' => 'Astuce',
                        'text' => 'Une photo représentative et une description sans jargon augmentent la conversion des clients vers la messagerie puis les commandes.',
                    ],
                ],
            ],
            [
                'id' => 'presta-abo',
                'title' => 'Prestataire — Essai gratuit et abonnements (Starter / Pro)',
                'role' => 'prestataire',
                'blocks' => [
                    [
                        'type' => 'p',
                        'text' => 'Après validation de votre compte, vous démarrez avec un essai gratuit de 7 jours : visibilité, messagerie et capacité de publication limitée.',
                    ],
                    [
                        'type' => 'list',
                        'title' => 'Plans (repères)',
                        'items' => [
                            'Essai gratuit : 0 FCFA / 7 jours — 2 services max',
                            'Starter : 3 500 FCFA / mois — jusqu’à 10 services',
                            'Pro : 6 000 FCFA / mois — services illimités',
                        ],
                    ],
                    [
                        'type' => 'steps',
                        'items' => [
                            ['title' => 'Étape 1 — Aller sur “Abonnement”', 'text' => 'Depuis votre dashboard prestataire, ouvrez le menu Abonnement.'],
                            ['title' => 'Étape 2 — Choisir le plan', 'text' => 'Sélectionnez Starter ou Pro. Le bouton du plan déjà actif est désactivé pour éviter la double souscription.'],
                            ['title' => 'Étape 3 — Payer via FedaPay', 'text' => 'Finalisez sur FedaPay puis revenez sur la plateforme. Le statut s’actualise.'],
                        ],
                    ],
                    [
                        'type' => 'callout',
                        'tone' => 'warn',
                        'title' => 'Après l’essai',
                        'text' => 'Sans abonnement Starter ou Pro actif, votre profil peut disparaître de la recherche et la messagerie peut être bloquée. Souscrivez avant la fin de l’essai pour éviter la perte de contacts.',
                    ],
                ],
            ],
            [
                'id' => 'presta-commandes',
                'title' => 'Prestataire — Gérer les commandes et être payé',
                'role' => 'prestataire',
                'blocks' => [
                    [
                        'type' => 'steps',
                        'items' => [
                            ['title' => 'Étape 1 — Répondre en messagerie', 'text' => 'Dans “Messagerie”, répondez rapidement. Un bon échange mène à la création de la commande.'],
                            ['title' => 'Étape 2 — Accepter ou refuser', 'text' => 'Dans “Commandes”, acceptez si budget et brief conviennent, sinon refusez avec un motif.'],
                            ['title' => 'Étape 3 — Attendre le paiement', 'text' => 'Ne commencez le gros du travail qu’une fois la commande en “Travail en cours” (paiement confirmé).'],
                            ['title' => 'Étape 4 — Livrer', 'text' => 'Soumettez le livrable (fichiers). En cas de retouche, soumettez une nouvelle version.'],
                            ['title' => 'Étape 5 — Encaissement', 'text' => 'Le paiement est déclenché après validation client (ou décision favorable après litige).'],
                        ],
                    ],
                    [
                        'type' => 'visual',
                        'title' => 'Ne pas rater les statuts',
                        'caption' => 'Vos actions autorisées dépendent du statut.',
                        'kind' => 'status-mock',
                        'zones' => [
                            ['label' => 'En attente du prestataire', 'hint' => 'Accepter/refuser'],
                            ['label' => 'En attente de paiement', 'hint' => 'Préparer livraison'],
                            ['label' => 'Travail en cours', 'hint' => 'Démarrer effectivement'],
                            ['label' => 'À valider', 'hint' => 'Attendre validation/retouche'],
                        ],
                    ],
                    [
                        'type' => 'callout',
                        'tone' => 'info',
                        'title' => 'Mobile Money : vérifier votre numéro',
                        'text' => 'Assurez-vous que le numéro associé à votre compte pour recevoir les paiements est correct et actif.',
                    ],
                ],
            ],
            [
                'id' => 'presta-cas-pratique',
                'title' => 'Prestataire — Cas pratique : de l’acceptation au payout',
                'role' => 'prestataire',
                'blocks' => [
                    [
                        'type' => 'callout',
                        'tone' => 'info',
                        'title' => 'Exemple',
                        'text' => 'Vous recevez une commande pour une prestation de design. Vous suivez le cycle de statut : accepter, attendre le paiement, livrer, puis être payé après validation client ou résolution favorable en cas de litige.',
                    ],
                    [
                        'type' => 'steps',
                        'items' => [
                            ['title' => 'Étape 1 — Accepter rapidement', 'text' => 'Dans “Commandes”, acceptez si le brief et le budget vous conviennent.'],
                            ['title' => 'Étape 2 — Préparer après paiement', 'text' => 'Attendez que la commande passe “Travail en cours”. C’est le signal que le paiement est confirmé.'],
                            ['title' => 'Étape 3 — Livrer une version complète', 'text' => 'Soumettez le livrable dans “Commandes”. Si une retouche est demandée, renvoyez une nouvelle version.'],
                            ['title' => 'Étape 4 — Suivre “À valider”', 'text' => 'Le client vérifie. Vous devez rester disponible en messagerie si des ajustements sont nécessaires.'],
                            ['title' => 'Étape 5 — Être payé', 'text' => 'Quand la validation client (ou décision favorable) arrive, le paiement est déclenché vers le numéro Mobile Money lié à votre profil.'],
                        ],
                    ],
                    [
                        'type' => 'visual',
                        'title' => 'À surveiller (pour éviter les erreurs)',
                        'caption' => 'Ces vérifications réduisent les risques de conflit et retard.',
                        'kind' => 'check-mock',
                        'zones' => [
                            ['label' => 'Statut “En attente de paiement”', 'hint' => 'Ne commencez pas le gros travail'],
                            ['label' => 'Numéro Mobile Money correct', 'hint' => 'Sur votre profil'],
                            ['label' => 'Livrable soumis à temps', 'hint' => 'Avec format attendu'],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'presta-paiements',
                'title' => 'Prestataire — Paiement, délais et protection',
                'role' => 'prestataire',
                'blocks' => [
                    [
                        'type' => 'list',
                        'title' => 'Repères',
                        'items' => [
                            'Paiement : FedaPay (confirmé avant “Travail en cours”)',
                            'Acceptation prestataire : environ 48 heures',
                            'Validation client après livraison : environ 14 jours',
                            'Litiges : examinateur/administration tranche en cas de problème',
                        ],
                    ],
                    [
                        'type' => 'p',
                        'text' => 'Ne demandez pas un paiement en dehors de la plateforme. Si le client ne valide pas, communiquez via la messagerie et utilisez le statut/les actions autorisées. En cas d’anomalie, utilisez le détail de commande comme référence.',
                    ],
                ],
            ],
            [
                'id' => 'presta-faq',
                'title' => 'Prestataire — Questions fréquentes',
                'role' => 'prestataire',
                'blocks' => [
                    [
                        'type' => 'faq',
                        'items' => [
                            [
                                'q' => 'Pourquoi je n’apparais plus dans la recherche ?',
                                'a' => 'Votre essai gratuit est probablement terminé sans abonnement Starter ou Pro actif. Ouvrez “Abonnement” et souscrivez pour réactiver la visibilité.',
                            ],
                            [
                                'q' => 'Puis-je repayer le même plan si je suis déjà dessus ?',
                                'a' => 'Non : le bouton du plan actif est désactivé. Vous pouvez en revanche passer de Starter à Pro (ou inversement selon les règles).',
                            ],
                            [
                                'q' => 'Quand je suis payé ?',
                                'a' => 'Après validation client (ou décision favorable). La commande doit passer par les statuts et actions du cycle normal.',
                            ],
                        ],
                    ],
                ],
            ],

            // ============================
            // FIN COMMUNE
            // ============================
            [
                'id' => 'aide',
                'title' => 'Aller plus loin — liens utiles',
                'blocks' => [
                    [
                        'type' => 'p',
                        'text' => 'Gardez ce guide sous la main (page web ou PDF). Les libellés peuvent évoluer, mais le parcours reste : rechercher → échanger → commander → payer → livrer → valider → noter.',
                    ],
                    [
                        'type' => 'list',
                        'title' => 'Liens',
                        'items' => [
                            'Accueil — /',
                            'Recherche — /search',
                            'Inscription client — /register/client',
                            'Inscription prestataire — /register/prestataire',
                            'Connexion — /login',
                            'Tarifs prestataire — /#tarifs',
                        ],
                    ],
                    [
                        'type' => 'callout',
                        'tone' => 'info',
                        'title' => 'Projet',
                        'text' => 'BéninFreelance — EPAC / UAC — Groupe 5. Guide version 1.1 · juillet 2026.',
                    ],
                ],
            ],
        ];
    }
}
