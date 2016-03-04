<?php
namespace Fab\VidiFrontend\Controller;

/**
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Fab\Vidi\Persistence\Matcher;
use Fab\VidiFrontend\Configuration\ColumnsConfiguration;
use Fab\VidiFrontend\Configuration\ContentElementConfiguration;
use Fab\VidiFrontend\Persistence\PagerFactory;
use Fab\VidiFrontend\Service\ContentElementService;
use Fab\VidiFrontend\Service\ContentType;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Configuration\FrontendConfigurationManager;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use Fab\Vidi\Domain\Model\Content;
use Fab\VidiFrontend\Persistence\MatcherFactory;
use Fab\VidiFrontend\Persistence\OrderFactory;

/**
 * Controller which handles actions related to Vidi in the Backend.
 */
class ContentController extends ActionController
{

    /**
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException
     */
    public function initializeAction()
    {

        if ($this->arguments->hasArgument('content')) {

            /** @var \Fab\VidiFrontend\TypeConverter\ContentConverter $typeConverter */
            $typeConverter = $this->objectManager->get('Fab\VidiFrontend\TypeConverter\ContentConverter');

            $this->arguments->getArgument('content')
                ->getPropertyMappingConfiguration()
                ->setTypeConverter($typeConverter);
        }

        if ($this->arguments->hasArgument('columns')) {

            /** @var \Fab\VidiFrontend\TypeConverter\ContentConverter $typeConverter */
            $typeConverter = $this->objectManager->get('Fab\VidiFrontend\TypeConverter\ArrayConverter');

            $this->arguments->getArgument('columns')
                ->getPropertyMappingConfiguration()
                ->setTypeConverter($typeConverter);
        }

        if ($this->arguments->hasArgument('contentData')) {

            /** @var \Fab\VidiFrontend\TypeConverter\ContentConverter $typeConverter */
            $typeConverter = $this->objectManager->get('Fab\VidiFrontend\TypeConverter\ContentDataConverter');

            $this->arguments->getArgument('contentData')
                ->getPropertyMappingConfiguration()
                ->setTypeConverter($typeConverter);
        }
    }

    /**
     * List action for this controller.
     *
     * @return string|null
     */
    public function indexAction()
    {

        $settings = $this->computeFinalSettings($this->settings);

        $possibleMessage = null;
        if (empty($settings['dataType'])) {
            $possibleMessage = '<strong style="color: red">Please select a content type to be displayed!</strong>';
        }
        $dataType = $settings['dataType'];

        // Set dynamic value for the sake of the Visual Search.
        if ($settings['isVisualSearchBar']) {
            $settings['loadContentByAjax'] = 1;
        }

        // Handle columns case
        $columns = ColumnsConfiguration::getInstance()->get($dataType, $settings['columns']);
        if (empty($columns)) {
            $possibleMessage = '<strong style="color: red">Please select at least one column to be displayed!</strong>';
        } else {

            $this->view->assign('columns', $columns);

            // Assign values.
            $this->view->assign('settings', $settings);
            $this->view->assign('gridIdentifier', $this->getGridIdentifier($settings));
            $this->view->assign('contentElementIdentifier', $this->configurationManager->getContentObject()->data['uid']);
            $this->view->assign('dataType', $dataType);
            $this->view->assign('objects', array());

            if (!$settings['loadContentByAjax']) {

                // Initialize some objects related to the query.
                $matcher = MatcherFactory::getInstance()->getMatcher($settings, array(), $dataType);
                $order = OrderFactory::getInstance()->getOrder($settings, $dataType);

                // Fetch objects via the Content Service.
                $contentService = $this->getContentService($dataType)->findBy($matcher, $order);
                $this->view->assign('objects', $contentService->getObjects());
            }

            // Initialize Content Element settings to be accessible across the request life cycle.
            $contentObjectRenderer = $this->configurationManager->getContentObject();
            ContentElementConfiguration::getInstance($contentObjectRenderer->data);
        }

        return $possibleMessage;
    }

    /**
     * List Row action for this controller. Output a json list of contents
     *
     * @param array $contentData
     * @return void
     */
    public function listAction(array $contentData)
    {
        $dataType = GeneralUtility::_GP('dataType');

        // In the context of Ajax, we must define manually the current Content Element object.
        $contentObjectRenderer = $this->getContentElementService($dataType)->getContentObjectRender($contentData);
        $this->configurationManager->setContentObject($contentObjectRenderer);

        $settings = ContentElementConfiguration::getInstance($contentData)->getSettings();
        $settings = $this->computeFinalSettings($settings);

        // Initialize some objects related to the query.
        $matcher = MatcherFactory::getInstance()->getMatcher($settings, array(), $dataType);
        if ($settings['logicalSeparator'] === Matcher::LOGICAL_OR) {
            $matcher->setLogicalSeparatorForEquals(Matcher::LOGICAL_OR);
            $matcher->setLogicalSeparatorForLike(Matcher::LOGICAL_OR);
            $matcher->setLogicalSeparatorForIn(Matcher::LOGICAL_OR);
            #$matcher->setLogicalSeparatorForSearchTerm(Matcher::LOGICAL_OR);
            #$matcher->setDefaultLogicalSeparator(Matcher::LOGICAL_OR);
        }

        $order = OrderFactory::getInstance()->getOrder($settings, $dataType);
        $pager = PagerFactory::getInstance()->getPager();

        // Fetch objects via the Content Service.
        $contentService = $this->getContentService($dataType)->findBy($matcher, $order, $pager->getLimit(), $pager->getOffset());
        $pager->setCount($contentService->getNumberOfObjects());

        // Set format.
        $this->request->setFormat('json');

        // Assign values.
        $this->view->assign('objects', $contentService->getObjects());
        $this->view->assign('numberOfObjects', $contentService->getNumberOfObjects());
        $this->view->assign('pager', $pager);
        $this->view->assign('response', $this->response);
    }

    /**
     * @param Content $content
     * @return string|void
     */
    public function showAction(Content $content)
    {
        $settings = $this->computeFinalSettings($this->settings);

        // Configure the template path according to the Plugin settings.
        $pathAbs = GeneralUtility::getFileAbsFileName($settings['templateDetail']);
        if (!is_file($pathAbs)) {
            return sprintf('<strong style="color:red;">I could not find the template file %s.</strong>', $pathAbs);
        }

        $variableName = 'object';
        $dataType = $this->getContentType()->getCurrentType();
        if (isset($settings['fluidVariables'][$dataType]) && basename($settings['templateDetail']) !== 'Show.html') {
            $variableName = $settings['fluidVariables'][$dataType];
        }

        $this->view->setTemplatePathAndFilename($pathAbs);
        $this->view->assign($variableName, $content);
    }

    /**
     * Merge with "raw" TypoScript configuration into Flexform settings.
     *
     * @param array $settings
     * @return array
     */
    protected function computeFinalSettings(array $settings) {

        $configuration = $this->configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT);
        $ts = GeneralUtility::removeDotsFromTS($configuration['plugin.']['tx_vidifrontend.']['settings.']);
        ArrayUtility::mergeRecursiveWithOverrule($settings, $ts);

        return $settings;
    }

    /**
     * Take some specific values and transform as as unique md5 identifier.
     *
     * @param array $settings
     * @return string
     */
    protected function getGridIdentifier(array $settings)
    {

        $key = $this->configurationManager->getContentObject()->data['uid'];
        $key .= $settings['dataType'];
        $key .= $settings['columns'];
        $key .= $settings['sorting'];
        $key .= $settings['direction'];
        $key .= $settings['defaultNumberOfItems'];
        $key .= $settings['loadContentByAjax'];
        $key .= $settings['facets'];
        $key .= $settings['isVisualSearchBar'];
        return md5($key);
    }

    /**
     * Get the Vidi Module Loader.
     *
     * @param string $dataType
     * @return \Fab\VidiFrontend\Service\ContentService
     */
    protected function getContentService($dataType)
    {
        return GeneralUtility::makeInstance('Fab\VidiFrontend\Service\ContentService', $dataType);
    }

    /**
     * Get the Vidi Module Loader.
     *
     * @param string $dataType
     * @return ContentElementService
     */
    protected function getContentElementService($dataType)
    {
        return GeneralUtility::makeInstance(ContentElementService::class, $dataType);
    }

    /**
     * @return ContentType
     */
    protected function getContentType()
    {
        return GeneralUtility::makeInstance(ContentType::class);
    }

}
