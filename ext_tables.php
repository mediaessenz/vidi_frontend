<?php
if (!defined('TYPO3_MODE')) {
    die ('Access denied.');
}

$configuration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['vidi_frontend']);

// Possible Static TS loading
if (true === isset($configuration['autoload_typoscript']) && true === (bool)$configuration['autoload_typoscript']) {
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('vidi_frontend', 'Configuration/TypoScript', 'Vidi Frontend: generic List Component');
}

if (TYPO3_MODE === 'BE') {
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
        'Fab.vidi_frontend',
        'Pi1',
        'Generic List Component'
    );

    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist']['vidifrontend_pi1'] = 'pi_flexform';
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue(
        'vidifrontend_pi1',
        sprintf('FILE:EXT:vidi_frontend/Configuration/FlexForm/VidiFrontend.xml')
    );

    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist']['vidifrontend_pi1'] = 'layout, select_key, pages, recursive';
    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist']['vidifrontend_pi1'] = 'pi_flexform';

    $GLOBALS['TBE_MODULES_EXT']["xMOD_db_new_content_el"]['addElClasses']['Fab\VidiFrontend\Backend\Wizard'] = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('vidi_frontend') . 'Classes/Backend/Wizard.php';
}