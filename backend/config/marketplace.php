<?php

return [
    /** Villes principales du Bénin (formulaires + filtres recherche). */
    'localites_suggestees' => [
        'Cotonou',
        'Porto-Novo',
        'Parakou',
        'Abomey',
        'Abomey-Calavi',
        'Allada',
        'Aplahoué',
        'Bohicon',
        'Comè',
        'Covè',
        'Dassa-Zoumè',
        'Djougou',
        'Grand-Popo',
        'Kandi',
        'Lokossa',
        'Malanville',
        'Natitingou',
        'Nikki',
        'Ouidah',
        'Pobè',
        'Savé',
        'Savalou',
    ],

    /** Commission plateforme désactivée (remplacée par abonnement mensuel). */
    'commission_rate' => (float) env('APP_COMMISSION_RATE', 0),

    'auto_release_days' => (int) env('APP_AUTO_RELEASE_DAYS', 14),
    'accept_deadline_hours' => (int) env('APP_ACCEPT_DEADLINE_HOURS', 48),
    // En sandbox : clôturer le litige en base si l'API payout FedaPay refuse (solde insuffisant, etc.)
    'dispute_allow_db_close' => filter_var(
        env('DISPUTE_ALLOW_DB_CLOSE', env('FEDAPAY_ENVIRONMENT', 'sandbox') === 'sandbox'),
        FILTER_VALIDATE_BOOL
    ),

    /** Durée d'un cycle d'abonnement payé (jours). */
    'subscription_days' => (int) env('APP_SUBSCRIPTION_DAYS', 30),

    /** Essai gratuit à l'inscription (messagerie + 2 services). */
    'trial_days' => (int) env('APP_TRIAL_DAYS', 7),

    /**
     * Plans freemium CDC.
     * services_max null = illimité.
     * priority : ordre de tri recherche (plus haut = d'abord).
     * Après expiration de l'essai sans plan payant : messagerie bloquée.
     */
    'plans' => [
        'gratuit' => [
            'label' => 'Essai gratuit',
            'prix' => 0,
            'services_max' => 2,
            'priority' => 0,
        ],
        'starter' => [
            'label' => 'Starter',
            'prix' => 3500,
            'services_max' => 10,
            'priority' => 1,
        ],
        'pro' => [
            'label' => 'Pro',
            'prix' => 6000,
            'services_max' => null,
            'priority' => 2,
        ],
    ],
];
