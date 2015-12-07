<?php
if (!defined('TYPO3_MODE')) die ('Access denied.');

$tca = array(
    'grid_frontend' => [
        'columns' => [

            # Overrides the "usergroup" column from the Vidi BE module.
            'usergroup' => [
                'renderers' => [
                    'Fab\Vidi\Grid\RelationRenderer',
                ],
                'label' => 'LLL:EXT:vidi/Resources/Private/Language/fe_users.xlf:usergroup',
            ],

            # Overrides the "__buttons" column from the Vidi BE module.
            '__buttons' => [
                'renderer' => 'Fab\VidiFrontend\Grid\ShowButtonRenderer',
            ],
        ],
    ],
);

\TYPO3\CMS\Core\Utility\ArrayUtility::mergeRecursiveWithOverrule($GLOBALS['TCA']['fe_users'], $tca);