<?php

use Bitrix\Main\Localization\Loc;

return [
    'edit1' => [
        'TAB_NAME' => Loc::getMessage('#MESS#_TAB'),
        'TAB_TITLE' => Loc::getMessage('#MESS#_TITLE'),
        'ICON' => '',
        'options' => [
            [
                'type' => 'checkbox',
                'name' => 'active',
                'title' => Loc::getMessage('#MESS#_ACTIVE'),
                'value' => 'N',
            ],
            [
                'type' => 'text',
                'name' => 'name',
                'title' => Loc::getMessage('#MESS#_TEXT'),
                'value' => 'Denis',
            ],
            [
                'type' => 'list',
                'name' => 'someList',
                'title' => Loc::getMessage('#MESS#_SOMELIST'),
                'value' => '1',
                'list' => [
                    '1' => 'Понедельник',
                    '2' => 'Вторник',
                    '3' => 'Среда',
                    '4' => 'Четверг',
                    '5' => 'Пятница',
                    '6' => 'Суббота',
                    '7' => 'Воскресенье'
                ],
            ],
            [
                'type' => 'message',
                'message' => Loc::getMessage('#MESS#_MESSAGE'),
            ],
            [
                'type' => 'heading',
                'heading' => Loc::getMessage('#MESS#_HEADING'),
            ],
            [
                'type' => 'textarea',
                'name' => 'message',
                'title' => Loc::getMessage('#MESS#_TEXTAREA'),
                'value' => 'Text here...',
            ],
        ]
    ],
];
