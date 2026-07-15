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
            'version' => '1.0',
            'updated' => '15 juillet 2026',
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
                'title' => 'Bienvenue sur BéninFreelance',
                'blocks' => [
                    [
                        'type' => 'p',
                        'text' => 'BéninFreelance est une marketplace de services freelances au Bénin. Elle met en relation des clients qui cherchent un professionnel (développeur, graphiste, artisan, consultant…) et des prestataires vérifiés qui proposent leurs compétences.',
                    ],
                    [
                        'type' => 'p',
                        'text' => 'Ce guide vous explique comment utiliser la plateforme selon votre objectif : trouver un prestataire et commander un service, ou publier vos offres et être payé en toute sécurité. Chaque section est conçue pour être lue indépendamment : allez directement à celle qui vous concerne.',
                    ],
                    [
                        'type' => 'callout',
                        'tone' => 'info',
                        'title' => 'Deux espaces distincts',
                        'text' => 'Le compte Client et le compte Prestataire sont séparés. Si vous souhaitez les deux rôles, créez deux inscriptions avec le même e-mail seulement si la plateforme le permet — en pratique, utilisez le parcours adapté à chaque besoin depuis l\'accueil.',
                    ],
                    [
                        'type' => 'list',
                        'title' => 'Ce que vous pouvez faire sur la plateforme',
                        'items' => [
                            'Rechercher des services et des prestataires par catégorie, localité et note',
                            'Discuter en messagerie avant de commander',
                            'Passer commande, payer en Mobile Money via FedaPay, suivre la livraison',
                            'Valider le travail, laisser un avis ou ouvrir un litige si nécessaire',
                            'Côté prestataire : publier des services, accepter des commandes, livrer et gérer son abonnement',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'demarrer',
                'title' => 'Démarrer : créer un compte',
                'blocks' => [
                    [
                        'type' => 'p',
                        'text' => 'Depuis la page d\'accueil, cliquez sur « Commencer » ou « Connexion ». Sur l\'écran de connexion, choisissez d\'abord votre profil (Client ou Prestataire), puis connectez-vous — ou créez un compte via les liens d\'inscription.',
                    ],
                    [
                        'type' => 'h',
                        'text' => 'Inscription client',
                    ],
                    [
                        'type' => 'steps',
                        'items' => [
                            ['title' => 'Ouvrir l\'inscription', 'text' => 'Allez sur /register/client ou choisissez « Trouver un prestataire » / le parcours client depuis l\'accueil.'],
                            ['title' => 'Identité', 'text' => 'Renseignez prénom, nom, adresse e-mail et téléphone. Utilisez une adresse e-mail à laquelle vous avez accès : elle servira à vérifier le compte et recevoir les notifications importantes.'],
                            ['title' => 'Localisation', 'text' => 'Indiquez votre ville et votre quartier. Cela aide les prestataires à situer le besoin (surtout pour les services de proximité).'],
                            ['title' => 'Mot de passe', 'text' => 'Choisissez un mot de passe solide, acceptez les conditions, puis validez. Suivez le lien de confirmation e-mail si la plateforme le demande.'],
                            ['title' => 'Connexion', 'text' => 'Connectez-vous en mode Client. Vous arrivez sur votre espace : Accueil, Recherche, Mon compte, Messages, Commandes.'],
                        ],
                    ],
                    [
                        'type' => 'h',
                        'text' => 'Inscription prestataire',
                    ],
                    [
                        'type' => 'steps',
                        'items' => [
                            ['title' => 'Ouvrir l\'inscription', 'text' => 'Allez sur /register/prestataire ou « Proposer mes services » depuis l\'accueil.'],
                            ['title' => 'Vos informations', 'text' => 'Complétez identité, contact et localisation professionnelle.'],
                            ['title' => 'Vos compétences', 'text' => 'Décrivez votre métier, vos spécialités et ce que vous proposez aux clients.'],
                            ['title' => 'Vos documents', 'text' => 'Joignez un CV au format PDF et une pièce d\'identité. Ces pièces sont examinées pour valider votre profil (vérification humaine et/ou assistée).'],
                            ['title' => 'Sécurité', 'text' => 'Définissez votre mot de passe et validez l\'inscription. Comptez en général jusqu\'à 24 h pour l\'activation après étude du dossier.'],
                        ],
                    ],
                    [
                        'type' => 'callout',
                        'tone' => 'warn',
                        'title' => 'Compte en attente de validation',
                        'text' => 'Tant que votre dossier prestataire n\'est pas validé, vous ne pouvez pas souscrire à un abonnement payant ni apparaître pleinement comme prestataire actif. Surveillez vos e-mails et l\'état du compte dans « Mon profil ».',
                    ],
                ],
            ],
            [
                'id' => 'client-recherche',
                'title' => 'Client — Chercher et choisir un prestataire',
                'role' => 'client',
                'blocks' => [
                    [
                        'type' => 'p',
                        'text' => 'Connecté en tant que client (ou depuis la recherche publique), ouvrez « Explorer » / « Recherche ». Vous pouvez filtrer par catégorie de service, localité et note.',
                    ],
                    [
                        'type' => 'steps',
                        'items' => [
                            ['title' => 'Parcourir les résultats', 'text' => 'Les profils Pro apparaissent en priorité dans la recherche. Chaque carte montre les infos essentielles (métier, note, localité).'],
                            ['title' => 'Voir le teaser', 'text' => 'Cliquez sur un prestataire pour ouvrir sa fiche courte, puis « Voir le profil complet » pour le détail, les services et les avis.'],
                            ['title' => 'Ouvrir un service', 'text' => 'Depuis un service (/service/…), lisez la description, le délai typique et le tarif indicatif avant de contacter.'],
                            ['title' => 'Contacter', 'text' => 'Le bouton « Contacter » vous amène à démarrer une conversation (connexion client requise). Exposez clairement votre besoin dès le premier message.'],
                        ],
                    ],
                    [
                        'type' => 'callout',
                        'tone' => 'tip',
                        'title' => 'Conseil',
                        'text' => 'Avant de commander, échangez sur le scope, le délai et le budget. Une discussion claire évite les malentendus et les retouches inutiles.',
                    ],
                ],
            ],
            [
                'id' => 'client-commande',
                'title' => 'Client — Commander, payer, valider',
                'role' => 'client',
                'blocks' => [
                    [
                        'type' => 'p',
                        'text' => 'Une fois le fil de discussion ouvert, vous pouvez proposer une commande au prestataire. Le paiement n\'est demandé qu\'après acceptation par le prestataire.',
                    ],
                    [
                        'type' => 'h',
                        'text' => 'Cycle de vie d\'une commande',
                    ],
                    [
                        'type' => 'steps',
                        'items' => [
                            ['title' => 'Proposer une commande', 'text' => 'Renseignez un titre clair, une description détaillée, le budget en FCFA et le délai (en jours). La commande passe en « En attente du prestataire » (délai typique d\'acceptation : 48 h).'],
                            ['title' => 'Acceptation', 'text' => 'Si le prestataire accepte, le statut devient « En attente de paiement ». Vous pouvez alors cliquer sur « Payer maintenant ».'],
                            ['title' => 'Paiement Mobile Money', 'text' => 'Vous êtes redirigé vers FedaPay. Payez avec le moyen proposé (sandbox ou live selon l\'environnement). Au retour sur la plateforme, le statut passe à « Travail en cours ».'],
                            ['title' => 'Livraison', 'text' => 'Le prestataire soumet le livrable (fichiers). La commande passe « À valider ». Vous disposez d\'environ 14 jours pour approuver ; passé ce délai, le paiement peut être libéré automatiquement.'],
                            ['title' => 'Approuver ou demander une retouche', 'text' => '« Approuver et libérer le paiement » clôture positivement. « Demander une retouche » renvoie le prestataire en révision pour une nouvelle version.'],
                            ['title' => 'Laisser un avis', 'text' => 'Après clôture, notez le prestataire (1 à 5 étoiles) et ajoutez un commentaire. Vous pourrez modifier votre avis plus tard depuis le détail de la commande.'],
                        ],
                    ],
                    [
                        'type' => 'h',
                        'text' => 'Litige',
                    ],
                    [
                        'type' => 'p',
                        'text' => 'Si un désaccord sérieux survient (qualité, non-livraison, etc.), vous pouvez ouvrir un litige depuis la commande. L\'équipe de moderation examine les échanges et tranche (remboursement total/partiel ou libération au prestataire). Utilisez le litige en dernier recours après avoir tenté une résolution en messagerie.',
                    ],
                    [
                        'type' => 'callout',
                        'tone' => 'info',
                        'title' => 'Argent sécurisé',
                        'text' => 'Quand vous payez, les fonds sont sécurisés jusqu\'à validation (ou décision de litige / auto-libération). La plateforme ne prélève pas de commission sur vos commandes : le modèle repose sur l\'abonnement des prestataires.',
                    ],
                ],
            ],
            [
                'id' => 'client-espace',
                'title' => 'Client — Votre espace',
                'role' => 'client',
                'blocks' => [
                    [
                        'type' => 'list',
                        'title' => 'Menu de l\'espace client',
                        'items' => [
                            'Accueil — vue d\'ensemble et raccourcis',
                            'Recherche — retrouver des services et profils',
                            'Mon compte — informations personnelles',
                            'Messages — toutes vos discussions',
                            'Commandes — suivi, paiement, validation, avis',
                        ],
                    ],
                    [
                        'type' => 'p',
                        'text' => 'Gardez un œil sur Messages et Commandes : les étapes critiques (acceptation, paiement, livraison à valider) s\'y reflètent. Vérifiez aussi votre boîte e-mail pour les confirmations importantes.',
                    ],
                ],
            ],
            [
                'id' => 'presta-profil',
                'title' => 'Prestataire — Profil, CV et services',
                'role' => 'prestataire',
                'blocks' => [
                    [
                        'type' => 'p',
                        'text' => 'Après validation de votre compte, complétez votre vitrine : photo, présentation, CV et services publiés. C\'est ce que les clients voient avant de vous écrire.',
                    ],
                    [
                        'type' => 'steps',
                        'items' => [
                            ['title' => 'Mon profil', 'text' => 'Mettez à jour photo, bio et informations. Vérifiez que votre CV PDF est accessible (aperçu dans le tableau de bord).'],
                            ['title' => 'Mes services', 'text' => 'Créez des fiches service (titre, description, catégorie, photo, tarif / délai). Respectez le plafond de votre plan (2 en essai, 10 en Starter, illimité en Pro).'],
                            ['title' => 'Prévisualiser', 'text' => 'Ouvrez vos fiches publiques pour contrôler le rendu côté client. Des services clairs et illustrés convertissent mieux.'],
                        ],
                    ],
                    [
                        'type' => 'callout',
                        'tone' => 'tip',
                        'title' => 'Qualité des annonces',
                        'text' => 'Un titre précis, une description sans jargon superflu, et une photo réelle du type de prestation augmentent vos chances d\'être contacté.',
                    ],
                ],
            ],
            [
                'id' => 'presta-abo',
                'title' => 'Prestataire — Essai gratuit et abonnements',
                'role' => 'prestataire',
                'blocks' => [
                    [
                        'type' => 'p',
                        'text' => 'À l\'inscription validée, vous bénéficiez d\'un essai gratuit de 7 jours : visibilité dans la recherche, messagerie avec les clients, jusqu\'à 2 services. Aucune commission n\'est prise sur vos commandes.',
                    ],
                    [
                        'type' => 'list',
                        'title' => 'Plans',
                        'items' => [
                            'Essai gratuit — 0 FCFA / 7 jours — 2 services max — messagerie et visibilité pendant l\'essai',
                            'Starter — 3 500 FCFA / mois — jusqu\'à 10 services — messagerie et bonne visibilité',
                            'Pro — 6 000 FCFA / mois — services illimités — priorité maximale dans les résultats de recherche',
                        ],
                    ],
                    [
                        'type' => 'p',
                        'text' => 'Souscrivez depuis « Abonnement ». Le paiement passe par FedaPay. Si vous êtes déjà sur un plan payant actif (Starter ou Pro), le bouton du même plan est désactivé : pas de double souscription au plan courant. Vous pouvez en revanche passer d\'un plan à l\'autre.',
                    ],
                    [
                        'type' => 'callout',
                        'tone' => 'warn',
                        'title' => 'Après l\'essai sans abonnement',
                        'text' => 'Sans Starter ou Pro actif à la fin de l\'essai, votre profil n\'apparaît plus dans la recherche client et la messagerie est bloquée jusqu\'à souscription. Souscrivez à temps pour ne pas perdre de contacts.',
                    ],
                ],
            ],
            [
                'id' => 'presta-commandes',
                'title' => 'Prestataire — Messagerie et commandes',
                'role' => 'prestataire',
                'blocks' => [
                    [
                        'type' => 'steps',
                        'items' => [
                            ['title' => 'Répondre aux messages', 'text' => 'Dans « Messagerie », répondez rapidement. Un client convaincu propose ensuite une commande depuis le fil.'],
                            ['title' => 'Accepter ou refuser', 'text' => 'Dans « Commandes », acceptez si le brief et le budget vous conviennent, ou refusez avec un motif. Respectez le délai d\'environ 48 h pour répondre.'],
                            ['title' => 'Attendre le paiement', 'text' => 'Après acceptation, le client paie. Ne commencez le travail important qu\'une fois le statut « Travail en cours » (paiement confirmé).'],
                            ['title' => 'Livrer', 'text' => 'Soumettez le résultat (fichiers, jusqu\'à plusieurs pièces selon les limites indiquées). En cas de retouche demandée, soumettez une nouvelle version.'],
                            ['title' => 'Encaissement', 'text' => 'Quand le client valide (ou après auto-libération / résolution favorable), le payout Mobile Money est déclenché vers le numéro associé à votre profil.'],
                        ],
                    ],
                    [
                        'type' => 'callout',
                        'tone' => 'info',
                        'title' => 'Téléphone Mobile Money',
                        'text' => 'Assurez-vous que le numéro renseigné pour recevoir les paiements est correct et actif. Un mauvais numéro bloque ou retarde le payout.',
                    ],
                ],
            ],
            [
                'id' => 'paiements',
                'title' => 'Paiements, délais et sécurité',
                'blocks' => [
                    [
                        'type' => 'list',
                        'title' => 'Repères utiles',
                        'items' => [
                            'Paiements via FedaPay (Mobile Money et moyens proposés sur la page de checkout)',
                            'Délai d\'acceptation d\'une commande par le prestataire : environ 48 heures',
                            'Délai de validation client après livraison : environ 14 jours (puis libération automatique possible)',
                            'Commission commande : 0 % — la rémunération de la plateforme vient des abonnements prestataires',
                            'Litiges tranchés par l\'administration après examen du dossier',
                        ],
                    ],
                    [
                        'type' => 'p',
                        'text' => 'Ne partagez jamais vos mots de passe. Méfiez-vous des demandes de paiement hors plateforme : le parcours sécurisé est conçu pour protéger client et prestataire. En cas d\'anomalie de paiement, utilisez la page de retour FedaPay et le détail de commande ; contactez le support si le statut reste incohérent.',
                    ],
                ],
            ],
            [
                'id' => 'faq',
                'title' => 'Questions fréquentes',
                'blocks' => [
                    [
                        'type' => 'faq',
                        'items' => [
                            [
                                'q' => 'Puis-je modifier un avis déjà publié ?',
                                'a' => 'Oui. En tant que client, ouvrez la commande concernée et utilisez « Modifier » sur votre avis pour changer la note ou le commentaire.',
                            ],
                            [
                                'q' => 'Pourquoi mon profil prestataire a disparu de la recherche ?',
                                'a' => 'L\'essai gratuit est probablement terminé sans abonnement Starter ou Pro actif. Souscrivez dans « Abonnement » pour réapparaître et rouvrir la messagerie.',
                            ],
                            [
                                'q' => 'Le prestataire ne répond pas à ma proposition',
                                'a' => 'Il dispose d\'un délai limité pour accepter. Passé ce délai, la proposition peut expirer. Relancez poliment en message ou cherchez un autre profil.',
                            ],
                            [
                                'q' => 'Comment changer de plan (Starter ↔ Pro) ?',
                                'a' => 'Depuis Abonnement, souscrivez au plan souhaité. Vous ne pouvez pas racheter le même plan déjà actif, mais vous pouvez passer à l\'autre offre.',
                            ],
                            [
                                'q' => 'Puis-je annuler une commande ?',
                                'a' => 'Oui tant qu\'elle n\'est pas payée (par ex. en attente d\'acceptation ou de paiement). Après paiement, utilisez la messagerie, la retouche, ou le litige selon le cas.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'aide',
                'title' => 'Aller plus loin',
                'blocks' => [
                    [
                        'type' => 'p',
                        'text' => 'Gardez ce guide sous la main (page web ou PDF). Les libellés de l\'interface peuvent légèrement évoluer, mais le parcours reste le même : rechercher → échanger → commander → payer → livrer → valider → noter.',
                    ],
                    [
                        'type' => 'list',
                        'title' => 'Liens utiles',
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
                        'text' => 'BéninFreelance — EPAC / UAC — Groupe 5. Guide version 1.0 · juillet 2026.',
                    ],
                ],
            ],
        ];
    }
}
