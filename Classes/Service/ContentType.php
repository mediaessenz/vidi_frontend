<?php
namespace TYPO3\CMS\VidiFrontend\Service;

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

use Fab\VidiFrontend\Plugin\PluginParameter;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Service related to the Content type.
 */
class ContentType implements SingletonInterface {

	/**
	 * @var array
	 */
	protected $contentTypes = array();

	/**
	 * Return the current content type.
	 *
	 * @throws \Exception
	 * @return string
	 */
	public function getCurrentType() {

		$parameters = GeneralUtility::_GP(PluginParameter::PREFIX);
		if (empty($parameters['contentElement'])) {
			throw new \Exception('Missing parameter...', 1414713537);
		}

		$contentElementIdentifier = (int)$parameters['contentElement'];

		if (empty($this->contentTypes[$contentElementIdentifier])) {

			$clause = sprintf('uid = %s ', $contentElementIdentifier);
			$clause .= $this->getPageRepository()->enableFields('tt_content');
			$clause .= $this->getPageRepository()->deleteClause('tt_content');
			$contentElement = $this->getDatabaseConnection()->exec_SELECTgetSingleRow('*', 'tt_content', $clause);

			$xml = GeneralUtility::xml2array($contentElement['pi_flexform']);

			if (!empty($xml['data']['sDEF']['lDEF']['settings.dataType']['vDEF'])) {
				$dataType = $xml['data']['sDEF']['lDEF']['settings.dataType']['vDEF'];
			} else {
				throw new \Exception('I could find data type in Content Element: ' . $contentElementIdentifier, 1413992029);
			}
			$this->contentTypes[$contentElementIdentifier] = $dataType;
		}

		return $this->contentTypes[$contentElementIdentifier];
	}

	/**
	 * Returns a pointer to the database.
	 *
	 * @return \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected function getDatabaseConnection() {
		return $GLOBALS['TYPO3_DB'];
	}

	/**
	 * Returns an instance of the page repository.
	 *
	 * @return \TYPO3\CMS\Frontend\Page\PageRepository
	 */
	protected function getPageRepository() {
		return $GLOBALS['TSFE']->sys_page;
	}
}