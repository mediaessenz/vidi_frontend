<?php
if (!defined('TYPO3_MODE')) die ('Access denied.');

$tca = array(
	'grid_frontend' => array(
		'facets' => array(
			'uid',
			'username',
			'first_name',
			'last_name',
			'usergroup',
			new \TYPO3\CMS\Vidi\Facet\StandardFacet(
				'disable',
				'LLL:EXT:vidi/Resources/Private/Language/locallang.xlf:active',
				array(
					'0' => 'LLL:EXT:vidi/Resources/Private/Language/locallang.xlf:active.0',
					'1' => 'LLL:EXT:vidi/Resources/Private/Language/locallang.xlf:active.1'
				)
			),
		),
		'columns' => array(
			'uid' => array(
				'width' => '5px',
			),
			'username' => array(
			),
			'name' => array(
			),
			'email' => array(
			),
			'usergroup' => array(
				'renderers' => array(
					'TYPO3\CMS\Vidi\Grid\RelationRenderer',
				),
				'label' => 'LLL:EXT:vidi/Resources/Private/Language/fe_users.xlf:usergroup',
			),
			'__buttons' => array(
				'renderer' => 'Fab\VidiFrontend\Grid\ShowButtonRenderer',
			),
		),
	),
);

\TYPO3\CMS\Core\Utility\ArrayUtility::mergeRecursiveWithOverrule($GLOBALS['TCA']['fe_users'], $tca);