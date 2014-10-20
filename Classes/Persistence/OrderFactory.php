<?php
namespace TYPO3\CMS\VidiFrontend\Persistence;

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

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Vidi\Tca\TcaService;

/**
 * Factory class related to Order object.
 */
class OrderFactory implements SingletonInterface {

	/**
	 * Gets a singleton instance of this class.
	 *
	 * @return \TYPO3\CMS\VidiFrontend\Persistence\OrderFactory
	 */
	static public function getInstance() {
		return GeneralUtility::makeInstance('TYPO3\CMS\VidiFrontend\Persistence\OrderFactory');
	}

	/**
	 * Returns an order object.
	 *
	 * @param string $dataType
	 * @return \TYPO3\CMS\Vidi\Persistence\Order
	 */
	public function getOrder($dataType) {

		// Default ordering
		$order = TcaService::table($dataType)->getDefaultOrderings();

		return GeneralUtility::makeInstance('TYPO3\CMS\Vidi\Persistence\Order', $order);
	}

}
