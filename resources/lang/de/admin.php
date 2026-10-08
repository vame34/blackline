<?php

return [
    'navigation' => [
        'groups' => [
            'signals' => 'Signale',
            'billing' => 'Abrechnung',
            'access' => 'Zugang',
        ],
        'resources' => [
            'signals' => 'Signale',
            'signal_categories' => 'Signalkategorien',
            'users' => 'Benutzer',
        ],
    ],
    'resources' => [
        'signal' => [
            'label' => 'Signal',
            'plural' => 'Signale',
            'fields' => [
                'category' => 'Kategorie',
                'strategy_key' => 'Strategieschlüssel',
                'exchange' => 'Austausch',
                'symbol' => 'Symbol',
                'timeframe' => 'Zeitrahmen',
                'direction' => 'Richtung',
                'entry_at' => 'Eintrittszeit',
                'entry_price' => 'Eintrittspreis',
                'stop_loss' => 'Stop-Loss',
                'take_profit' => 'Nehmen Sie Gewinn mit',
                'reward_risk' => 'Belohnung/Risiko',
                'status' => 'Status',
                'published_at' => 'Veröffentlicht unter',
                'expires_at' => 'Läuft ab um',
                'notes' => 'Notizen',
            ],
            'options' => [
                'direction' => [
                    'long' => 'Lang',
                    'short' => 'Kurz',
                ],
                'status' => [
                    'draft' => 'Entwurf',
                    'published' => 'Veröffentlicht',
                    'closed' => 'Geschlossen',
                    'archived' => 'Archiviert',
                ],
            ],
        ],
        'signal_category' => [
            'label' => 'Signalkategorie',
            'plural' => 'Signalkategorien',
            'fields' => [
                'slug' => 'Schnecke',
                'name' => 'Name',
                'sort_order' => 'Sortierreihenfolge',
                'updated_at' => 'Aktualisiert',
            ],
        ],
        'signal_update' => [
            'label' => 'Signalaktualisierung',
            'plural' => 'Signalaktualisierungen',
            'fields' => [
                'type' => 'Typ',
                'title' => 'Titel',
                'body' => 'Körper',
                'published_at' => 'Veröffentlicht unter',
            ],
            'options' => [
                'type' => [
                    'published' => 'Veröffentlicht',
                    'entry' => 'Eintrag',
                    'level_update' => 'Level-Update',
                    'canceled' => 'Abgesagt',
                    'closed' => 'Geschlossen',
                    'note' => 'Notiz',
                ],
            ],
        ],
        'user' => [
            'label' => 'Benutzer',
            'plural' => 'Benutzer',
            'fields' => [
                'name' => 'Name',
                'email' => 'E-Mail',
                'email_verified_at' => 'E-Mail bestätigt unter',
                'roles' => 'Rollen',
                'password' => 'Passwort',
                'verified' => 'Verifiziert',
                'created_at' => 'Erstellt',
            ],
        ],
    ],
];
