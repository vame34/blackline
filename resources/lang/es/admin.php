<?php

return [
    'navigation' => [
        'groups' => [
            'signals' => 'Señales',
            'billing' => 'Facturación',
            'access' => 'Acceso',
        ],
        'resources' => [
            'signals' => 'Señales',
            'signal_categories' => 'Categorías de señal',
            'users' => 'Usuarios',
        ],
    ],
    'resources' => [
        'signal' => [
            'label' => 'Señal',
            'plural' => 'Señales',
            'fields' => [
                'category' => 'Categoría',
                'strategy_key' => 'Clave de estrategia',
                'exchange' => 'Intercambio',
                'symbol' => 'Símbolo',
                'timeframe' => 'Periodo de tiempo',
                'direction' => 'Dirección',
                'entry_at' => 'hora de entrada',
                'entry_price' => 'Precio de entrada',
                'stop_loss' => 'detener la pérdida',
                'take_profit' => 'Tomar ganancias',
                'reward_risk' => 'Recompensa/riesgo',
                'status' => 'Estado',
                'published_at' => 'Publicado en',
                'expires_at' => 'Vence en',
                'notes' => 'Notas',
            ],
            'options' => [
                'direction' => [
                    'long' => 'Largo',
                    'short' => 'Corto',
                ],
                'status' => [
                    'draft' => 'Borrador',
                    'published' => 'Publicado',
                    'closed' => 'Cerrado',
                    'archived' => 'Archivado',
                ],
            ],
        ],
        'signal_category' => [
            'label' => 'Categoría de señal',
            'plural' => 'Categorías de señal',
            'fields' => [
                'slug' => 'Babosa',
                'name' => 'Nombre',
                'sort_order' => 'orden de clasificación',
                'updated_at' => 'Actualizado',
            ],
        ],
        'signal_update' => [
            'label' => 'Actualización de señal',
            'plural' => 'Actualizaciones de señal',
            'fields' => [
                'type' => 'Tipo',
                'title' => 'Título',
                'body' => 'Cuerpo',
                'published_at' => 'Publicado en',
            ],
            'options' => [
                'type' => [
                    'published' => 'Publicado',
                    'entry' => 'Entrada',
                    'level_update' => 'Actualización de nivel',
                    'canceled' => 'Cancelado',
                    'closed' => 'Cerrado',
                    'note' => 'Nota',
                ],
            ],
        ],
        'user' => [
            'label' => 'Usuario',
            'plural' => 'Usuarios',
            'fields' => [
                'name' => 'Nombre',
                'email' => 'Correo electrónico',
                'email_verified_at' => 'Correo electrónico verificado en',
                'roles' => 'Roles',
                'password' => 'Contraseña',
                'verified' => 'Verificado',
                'created_at' => 'Creado',
            ],
        ],
    ],
];
